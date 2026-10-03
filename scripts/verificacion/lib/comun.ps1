<#
    Utilidades compartidas por verificar.ps1 y puerta-seguridad.ps1.

    Este archivo NO tiene bloque param() a propósito: así se puede hacer
    dot-source desde los otros dos sin que sus parámetros choquen con los de
    quien lo carga.
#>

<#
.SYNOPSIS
    Localiza la carpeta contenedora de UrbanBlade subiendo desde un punto.

.DESCRIPTION
    Sube nivel a nivel buscando la carpeta que contiene los cuatro repositorios
    (cada uno con su .git). Así los scripts siguen funcionando si se mueven de
    sitio — que es justo lo que pasó al traerlos de _verificacion/ a
    barber/scripts/verificacion/.

    Se puede forzar con la variable de entorno UB_RAIZ (útil para pruebas).
#>
function Buscar-RaizDelWorkspace {
    param([string]$Desde)

    if ($env:UB_RAIZ) {
        $forzada = Resolve-Path -LiteralPath $env:UB_RAIZ -ErrorAction SilentlyContinue
        if ($forzada) { return $forzada.Path }
        throw "UB_RAIZ apunta a una ruta que no existe: $env:UB_RAIZ"
    }

    $punto = if ($Desde) { $Desde } else { $PSScriptRoot }
    $actual = (Resolve-Path -LiteralPath $punto).Path
    $repos = @('barber', 'frontend-urban', 'spark', 'UrbanBladeMobile')

    for ($nivel = 0; $nivel -lt 8; $nivel++) {
        $completa = $true
        foreach ($repo in $repos) {
            if (-not (Test-Path -LiteralPath (Join-Path $actual (Join-Path $repo '.git')))) {
                $completa = $false
                break
            }
        }
        if ($completa) { return $actual }

        $padre = Split-Path $actual -Parent
        if (-not $padre -or $padre -eq $actual) { break }
        $actual = $padre
    }

    throw ("No encontré la carpeta contenedora (la que tiene {0}) subiendo desde {1}. " -f ($repos -join '/, '), $punto) +
          "Pásala con -Raiz o con la variable de entorno UB_RAIZ."
}

<#
.SYNOPSIS
    Localiza el ejecutable de Docker.

.DESCRIPTION
    En Windows, Docker Desktop instala el CLI en
    "C:\Program Files\Docker\Docker\resources\bin" y no siempre lo pone en el PATH.
    Sin esto, el sandbox reportaba "falta Docker" en una máquina que lo tenía
    instalado y corriendo.

    Devuelve la ruta completa, o $null si no lo encuentra.
#>
function Buscar-Docker {
    $enPath = Get-Command docker -ErrorAction SilentlyContinue
    if ($enPath) { return $enPath.Source }

    $candidatos = @()
    if ($env:ProgramFiles) {
        $candidatos += (Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\docker.exe')
    }
    if ($env:LOCALAPPDATA) {
        $candidatos += (Join-Path $env:LOCALAPPDATA 'Docker\wsl\docker.exe')
    }

    foreach ($candidato in $candidatos) {
        if (Test-Path -LiteralPath $candidato -PathType Leaf) { return $candidato }
    }

    return $null
}
