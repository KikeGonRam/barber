<#
.SYNOPSIS
  Activa la cache de CloudFront para los estaticos del frontend y agrega Cache-Control a las
  imagenes que ya estan en el bucket publico de subidas.

.DESCRIPTION
  Contexto (medido el 2026-10-09): la distribucion del frontend usa la politica Managed-CachingDisabled
  en su unico comportamiento, asi que TODO (JS con hash, imagenes, HTML) llega a la unica tarea de
  Fargate, y los objetos del bucket de subidas se guardaron sin Cache-Control. Este script:

    1. CloudFront: agrega a la distribucion del frontend dos comportamientos de solo lectura
       (/_nuxt/* y /images/*) con la politica gestionada Managed-CachingOptimized. El comportamiento
       por defecto (HTML, /api/*, SSR) NO cambia: sigue sin cache y con Managed-AllViewer.
       Nitro ya manda Cache-Control correcto: /_nuxt/* es "immutable" de un anio y /images/* 1 dia.
    2. S3: a los objetos del bucket publico que no tienen Cache-Control se les agrega
       "public, max-age=31536000, immutable" copiandolos sobre si mismos conservando Content-Type,
       metadatos y, si lo tenian, su permiso publico. Los nombres son UUID o hash, asi que un
       objeto nunca se reemplaza bajo el mismo nombre.

  SIN -Aplicar es una simulacion: solo lee de AWS (get-distribution-config, list-objects-v2,
  head-object, get-object-acl) y muestra lo que cambiaria. No escribe nada.

  Regla del propietario (2026-10-03, docs/FASE-5C-S3.md): la IA no escribe en AWS. Este script lo
  corre el propietario en su terminal con un perfil que pueda escribir en CloudFront y S3.

.PARAMETER Aplicar
  Ejecuta los cambios. Sin este parametro solo simula.

.PARAMETER SoloCloudFront
  Omite la parte de S3.

.PARAMETER SoloS3
  Omite la parte de CloudFront.

.EXAMPLE
  .\scripts\Optimizar-CacheStaging.ps1                    # simulacion
  .\scripts\Optimizar-CacheStaging.ps1 -Aplicar           # aplica CloudFront y S3
  .\scripts\Optimizar-CacheStaging.ps1 -Aplicar -SoloCloudFront

.NOTES
  Reversion de CloudFront: el script guarda la configuracion previa en la carpeta de respaldo
  (DistributionConfig sin cambios) e imprime el comando exacto:
    aws cloudfront get-distribution-config --id <ID> --query ETag --output text
    aws cloudfront update-distribution --id <ID> --if-match <ETag nuevo> --distribution-config file://<respaldo>
  Los cambios de CloudFront tardan unos minutos en propagarse (estado InProgress -> Deployed).
  Reversion de S3: el Cache-Control es inocuo; si hiciera falta quitarlo, repetir la copia con
  --metadata-directive REPLACE sin --cache-control.
#>
[CmdletBinding()]
param(
    [string]$Profile,
    [switch]$Aplicar,
    [switch]$SoloCloudFront,
    [switch]$SoloS3,
    [string]$DistribucionId = 'E1LFM8ET3R2TF3',
    [string]$Bucket = 'urbanblade-staging-uploads-209479293733',
    [string]$CarpetaRespaldo = (Join-Path ([IO.Path]::GetTempPath()) 'urbanblade-cache-respaldo')
)

$ErrorActionPreference = 'Stop'
$region = 'us-east-1'
$politicaCachingOptimized = '658327ea-f89d-4fab-a63d-7e88639e58f6'   # Managed-CachingOptimized
$patrones = @('/_nuxt/*', '/images/*')
$cacheControlS3 = 'public, max-age=31536000, immutable'

if ($SoloCloudFront -and $SoloS3) { throw 'No combines -SoloCloudFront con -SoloS3.' }

function Invoke-Aws {
    param([Parameter(Mandatory)][string[]]$Argumentos, [switch]$PermitirError)
    $extra = @('--region', $region, '--cli-connect-timeout', '20')
    if ($Profile) { $extra += @('--profile', $Profile) }
    $salida = & aws @Argumentos @extra 2>&1
    if ($LASTEXITCODE -ne 0) {
        if ($PermitirError) { return $null }
        throw "aws fallo: $($Argumentos[0..1] -join ' ')`n$($salida -join "`n")"
    }
    return $salida
}

function ConvertTo-Clon {
    param([Parameter(Mandatory)]$Objeto)
    return ($Objeto | ConvertTo-Json -Depth 30 | ConvertFrom-Json)
}

$identidad = ((Invoke-Aws @('sts', 'get-caller-identity', '--query', 'Arn', '--output', 'text')) -join '').Trim()
Write-Host "Identidad de AWS en uso: $identidad"
if ($Aplicar) {
    Write-Host 'MODO APLICAR: este script va a ESCRIBIR en CloudFront y/o S3.' -ForegroundColor Yellow
} else {
    Write-Host 'MODO SIMULACION: solo lectura, no se cambia nada. Agrega -Aplicar para ejecutar.' -ForegroundColor Cyan
}

# ---------------------------------------------------------------- CloudFront
if (-not $SoloS3) {
    Write-Host ''
    Write-Host "== CloudFront: distribucion $DistribucionId ==" -ForegroundColor Green
    $resp = (Invoke-Aws @('cloudfront', 'get-distribution-config', '--id', $DistribucionId, '--output', 'json')) -join "`n" | ConvertFrom-Json
    $etag = $resp.ETag
    $config = $resp.DistributionConfig
    Write-Host "Comentario: $($config.Comment) | ETag: $etag"

    $existentes = @()
    if ($config.CacheBehaviors -and $config.CacheBehaviors.Items) { $existentes = @($config.CacheBehaviors.Items) }
    $patronesActuales = @($existentes | ForEach-Object { $_.PathPattern })
    Write-Host "Comportamientos actuales: default + $($existentes.Count) ($($patronesActuales -join ', '))"
    Write-Host "Politica del default: $($config.DefaultCacheBehavior.CachePolicyId) (se mantiene)"

    $faltan = @($patrones | Where-Object { $patronesActuales -notcontains $_ })
    if ($faltan.Count -eq 0) {
        Write-Host 'CloudFront ya tiene los comportamientos /_nuxt/* y /images/*. Nada que hacer.'
    } else {
        $nuevos = @()
        foreach ($patron in $faltan) {
            $b = ConvertTo-Clon $config.DefaultCacheBehavior
            foreach ($obsoleta in 'ForwardedValues', 'MinTTL', 'DefaultTTL', 'MaxTTL') {
                if ($b.PSObject.Properties.Name -contains $obsoleta) { $b.PSObject.Properties.Remove($obsoleta) }
            }
            $b | Add-Member -NotePropertyName PathPattern -NotePropertyValue $patron -Force
            $b.CachePolicyId = $politicaCachingOptimized
            $b.Compress = $true
            $b.ViewerProtocolPolicy = 'redirect-to-https'
            $b.AllowedMethods = [pscustomobject]@{
                Quantity      = 2
                Items         = @('GET', 'HEAD')
                CachedMethods = [pscustomobject]@{ Quantity = 2; Items = @('GET', 'HEAD') }
            }
            $nuevos += $b
        }

        Write-Host "Se agregarian $($nuevos.Count) comportamiento(s):"
        foreach ($b in $nuevos) {
            Write-Host ("  {0,-12} -> origen {1} | cache {2} (Managed-CachingOptimized) | origen-request {3} | compress {4}" -f `
                    $b.PathPattern, $b.TargetOriginId, $b.CachePolicyId, $b.OriginRequestPolicyId, $b.Compress)
        }

        $todos = @($existentes) + @($nuevos)
        $nuevaConfig = ConvertTo-Clon $config
        $nuevaConfig.CacheBehaviors = [pscustomobject]@{ Quantity = $todos.Count; Items = $todos }

        if ($Aplicar) {
            New-Item -ItemType Directory -Force -Path $CarpetaRespaldo | Out-Null
            $marca = Get-Date -Format 'yyyyMMdd-HHmmss'
            $respaldo = Join-Path $CarpetaRespaldo "cloudfront-$DistribucionId-antes-$marca.json"
            $nueva = Join-Path $CarpetaRespaldo "cloudfront-$DistribucionId-nueva-$marca.json"
            ($config | ConvertTo-Json -Depth 30) | Set-Content -Path $respaldo -Encoding utf8
            ($nuevaConfig | ConvertTo-Json -Depth 30) | Set-Content -Path $nueva -Encoding utf8
            Write-Host "Respaldo de la configuracion previa: $respaldo"

            $estado = ((Invoke-Aws @('cloudfront', 'update-distribution', '--id', $DistribucionId, '--if-match', $etag,
                        '--distribution-config', "file://$nueva", '--query', 'Distribution.Status', '--output', 'text')) -join '').Trim()
            Write-Host "update-distribution aceptado. Estado: $estado. Esperando a que se propague (puede tardar varios minutos)..."
            Invoke-Aws @('cloudfront', 'wait', 'distribution-deployed', '--id', $DistribucionId) | Out-Null
            Write-Host 'Distribucion desplegada.' -ForegroundColor Green
            Write-Host 'Para revertir:'
            Write-Host "  aws cloudfront get-distribution-config --id $DistribucionId --query ETag --output text"
            Write-Host "  aws cloudfront update-distribution --id $DistribucionId --if-match <ETag nuevo> --distribution-config file://$respaldo"
        } else {
            Write-Host 'Simulacion: no se escribio nada en CloudFront.'
        }
    }
}

# ---------------------------------------------------------------- S3
if (-not $SoloCloudFront) {
    Write-Host ''
    Write-Host "== S3: bucket $Bucket ==" -ForegroundColor Green
    $claves = @()
    $token = $null
    do {
        $argsAws = @('s3api', 'list-objects-v2', '--bucket', $Bucket, '--output', 'json')
        if ($token) { $argsAws += @('--starting-token', $token) }
        $lista = (Invoke-Aws $argsAws) -join "`n" | ConvertFrom-Json
        if ($lista.Contents) { $claves += @($lista.Contents | ForEach-Object { $_.Key }) }
        $token = $lista.NextToken
    } while ($token)
    Write-Host "Objetos en el bucket: $($claves.Count)"

    $pendientes = 0; $yaTienen = 0; $hechos = 0
    foreach ($clave in $claves) {
        $h = (Invoke-Aws @('s3api', 'head-object', '--bucket', $Bucket, '--key', $clave, '--output', 'json')) -join "`n" | ConvertFrom-Json
        if ($h.CacheControl) { $yaTienen++; continue }
        $pendientes++

        # Si el objeto era publico por ACL, hay que conservarlo: copy-object no copia la ACL.
        $esPublicoPorAcl = $false
        $acl = Invoke-Aws @('s3api', 'get-object-acl', '--bucket', $Bucket, '--key', $clave, '--output', 'json') -PermitirError
        if ($acl) {
            $grants = (($acl -join "`n") | ConvertFrom-Json).Grants
            $esPublicoPorAcl = [bool]($grants | Where-Object { $_.Grantee.URI -like '*AllUsers' -and $_.Permission -eq 'READ' })
        }

        $resumen = "{0} | {1} | acl-publica={2}" -f $clave, $h.ContentType, $esPublicoPorAcl
        if (-not $Aplicar) { Write-Host "  [simulacion] $resumen"; continue }

        $origen = ($clave -split '/' | ForEach-Object { [uri]::EscapeDataString($_) }) -join '/'
        $argsAws = @('s3api', 'copy-object', '--bucket', $Bucket, '--key', $clave, '--copy-source', "$Bucket/$origen",
            '--metadata-directive', 'REPLACE', '--cache-control', $cacheControlS3, '--content-type', $h.ContentType)
        if ($h.ContentDisposition) { $argsAws += @('--content-disposition', $h.ContentDisposition) }
        if ($h.ContentEncoding) { $argsAws += @('--content-encoding', $h.ContentEncoding) }
        if ($h.ContentLanguage) { $argsAws += @('--content-language', $h.ContentLanguage) }
        if ($h.Metadata -and $h.Metadata.PSObject.Properties.Count -gt 0) {
            $pares = @($h.Metadata.PSObject.Properties | ForEach-Object { "$($_.Name)=$($_.Value)" })
            $argsAws += @('--metadata', ($pares -join ','))
        }
        if ($esPublicoPorAcl) { $argsAws += @('--acl', 'public-read') }
        Invoke-Aws $argsAws | Out-Null
        $hechos++
        Write-Host "  [ok] $resumen"
    }
    Write-Host "Con Cache-Control desde antes: $yaTienen | pendientes: $pendientes | actualizados ahora: $hechos"
    if ($Aplicar -and $hechos -gt 0) {
        Write-Host 'Comprobando que siguen siendo publicos (GET anonimo)...'
        $muestra = $claves | Select-Object -First 3
        foreach ($clave in $muestra) {
            $url = "https://$Bucket.s3.$region.amazonaws.com/$clave"
            try {
                $r = Invoke-WebRequest -Uri $url -Method Head -UseBasicParsing
                Write-Host ("  {0} -> {1} | Cache-Control: {2}" -f $clave, $r.StatusCode, $r.Headers['Cache-Control'])
            } catch {
                Write-Warning "  $clave -> $($_.Exception.Message)"
            }
        }
    }
}

Write-Host ''
Write-Host 'Listo.'
