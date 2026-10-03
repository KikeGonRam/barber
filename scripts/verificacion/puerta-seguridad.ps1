<#
.SYNOPSIS
    Puerta de seguridad: comprueba que las pruebas NO vayan a tocar datos reales.

.DESCRIPTION
    Existe por un incidente real (2026-08-28): la suite de barber corrió contra la
    base compartida de Atlas y el tearDown() de las pruebas de Feature borró datos
    de clientes y citas reales. No hubo respaldo recuperable.

    Esta puerta no ejecuta pruebas: las inspecciona antes. Se puede correr sola:

        .\puerta-seguridad.ps1

    Devuelve un objeto con Bloqueos (impiden verificar), Advertencias (permiten
    seguir, pero hay que saberlo) y Notas (contexto).

    La puerta NO modifica nada: solo lee archivos de configuración y el estado de
    git. Es seguro correrla cuantas veces se quiera.
#>
# Sin bloque param() a proposito: verificar.ps1 hace dot-source de este archivo
# para reutilizar Invoke-PuertaDeSeguridad, y un param() aqui pisaria variables
# del que lo carga. La raiz se resuelve sola (ver lib/comun.ps1) o con UB_RAIZ.
. (Join-Path $PSScriptRoot 'lib\comun.ps1')

function Leer-Env {
    param([Parameter(Mandatory)][string]$Ruta)

    $valores = @{}
    if (-not (Test-Path -LiteralPath $Ruta -PathType Leaf)) { return $valores }

    foreach ($linea in [System.IO.File]::ReadAllLines($Ruta)) {
        $limpia = $linea.Trim()
        if ($limpia.Length -eq 0 -or $limpia.StartsWith('#')) { continue }
        $partes = $limpia -split '=', 2
        if ($partes.Count -ne 2) { continue }
        $clave = $partes[0].Trim()
        $valor = $partes[1].Trim().Trim('"').Trim("'")
        if ($clave) { $valores[$clave] = $valor }
    }
    return $valores
}

function Es-Atlas {
    param([string]$Uri)
    if ([string]::IsNullOrWhiteSpace($Uri)) { return $false }
    return ($Uri -match 'mongodb\+srv://') -or ($Uri -match '\.mongodb\.net')
}

function Invoke-PuertaDeSeguridad {
    param([Parameter(Mandatory)][string]$Raiz)

    $bloqueos = [System.Collections.Generic.List[string]]::new()
    $advertencias = [System.Collections.Generic.List[string]]::new()
    $notas = [System.Collections.Generic.List[string]]::new()

    # ── 1. Ningún archivo de credenciales puede estar versionado ────────────
    $patronSensible = '(^|/)\.env($|\.)|\.pem$|\.p12$|\.jks$|\.keystore$|adminsdk|accessKeys|credentials.*\.json$|service.?account|local\.properties|google-services\.json'
    $permitidos = '\.(example|testing|development\.example)$'
    foreach ($repo in @('barber', 'frontend-urban', 'spark', 'UrbanBladeMobile')) {
        $rutaRepo = Join-Path $Raiz $repo
        if (-not (Test-Path -LiteralPath (Join-Path $rutaRepo '.git'))) { continue }
        $rastreados = & git -C $rutaRepo ls-files 2>$null | Select-String -Pattern $patronSensible |
            Where-Object { $_.Line -notmatch $permitidos }
        if ($rastreados) {
            $lista = ($rastreados | ForEach-Object { $_.Line }) -join ', '
            $bloqueos.Add("$repo tiene credenciales VERSIONADAS en git: $lista. Hay que quitarlas del historial antes de seguir.")
        }
    }

    # ── 2. La config de PRUEBAS de barber debe apuntar a local ──────────────
    # Es exactamente el fallo del 2026-08-28: la suite corrió contra Atlas.
    $envTesting = Join-Path $Raiz 'barber\.env.testing'
    if (Test-Path -LiteralPath $envTesting -PathType Leaf) {
        $testing = Leer-Env -Ruta $envTesting
        $uri = $testing['MONGODB_URI']
        $base = $testing['MONGO_DATABASE']
        if (Es-Atlas $uri) {
            $bloqueos.Add("barber/.env.testing apunta a ATLAS ($uri). Los tests borran datos en su tearDown: es el incidente del 2026-08-28 listo para repetirse.")
        }
        if ($base -and $base -notmatch '_test$') {
            $bloqueos.Add("barber/.env.testing usa la base '$base', que no termina en '_test'. Debe ser una base de pruebas.")
        }
        if ($base -match '_test$' -and -not (Es-Atlas $uri)) {
            $notas.Add("barber/.env.testing correcto: base '$base' contra destino local. Los tests deben correrse con .\test.ps1.")
        }
    }
    else {
        $advertencias.Add('barber/.env.testing no existe: sin él no se pueden correr los tests de barber de forma segura.')
    }

    # ── 3. La config de DESARROLLO de barber apunta a Atlas (normal) ────────
    $envBarber = Join-Path $Raiz 'barber\.env'
    if (Test-Path -LiteralPath $envBarber -PathType Leaf) {
        $dev = Leer-Env -Ruta $envBarber
        if (Es-Atlas $dev['MONGODB_URI']) {
            $notas.Add("barber/.env apunta a Atlas (correcto para desarrollo). Por eso los tests SOLO deben correr con .\test.ps1, que fuerza .env.testing.")
        }
    }

    # ── 4. spark: separación de bases y e2e local ───────────────────────────
    $envSpark = Join-Path $Raiz 'spark\.env'
    if (Test-Path -LiteralPath $envSpark -PathType Leaf) {
        $spark = Leer-Env -Ruta $envSpark
        $core = $spark['MONGO_DB']
        $analitica = $spark['ANALYTICS_MONGO_DB']
        if ($core -and $analitica -and $core -eq $analitica) {
            $bloqueos.Add("spark/.env tiene MONGO_DB y ANALYTICS_MONGO_DB iguales ('$core'): la separación entre lectura y escritura está rota.")
        }
        foreach ($claveE2E in @('ANALYTICS_E2E_URI', 'CORE_E2E_URI')) {
            $uriE2E = $spark[$claveE2E]
            if ($uriE2E -and (Es-Atlas $uriE2E)) {
                $bloqueos.Add("spark/.env define $claveE2E apuntando a Atlas. Las pruebas de integración escriben: deben usar un MongoDB local.")
            }
        }
    }

    # ── 5. frontend: ¿la API configurada es local? ──────────────────────────
    $envFront = Join-Path $Raiz 'frontend-urban\.env'
    if (Test-Path -LiteralPath $envFront -PathType Leaf) {
        $front = Leer-Env -Ruta $envFront
        $api = $front['NUXT_PUBLIC_API_BASE']
        if ($api -and $api -notmatch '127\.0\.0\.1|localhost|\[::1\]') {
            $advertencias.Add("frontend-urban/.env apunta la API a '$api' (no local). Las pruebas e2e la sustituyen por un mock, pero una comprobación manual golpearía ese entorno.")
        }
    }

    # ── 6. ¿Qué se está verificando es lo que se va a subir? ───────────────
    foreach ($repo in @('barber', 'frontend-urban', 'spark', 'UrbanBladeMobile')) {
        $rutaRepo = Join-Path $Raiz $repo
        if (-not (Test-Path -LiteralPath (Join-Path $rutaRepo '.git'))) { continue }
        $sucio = & git -C $rutaRepo status --porcelain 2>$null
        if ($sucio) {
            $cuantos = ($sucio | Measure-Object).Count
            $advertencias.Add("$repo tiene $cuantos cambio(s) sin commitear: lo que verifiques no es exactamente lo que subirás.")
        }
        $rama = (& git -C $rutaRepo branch --show-current 2>$null)
        if ($rama -and $rama -ne 'main') {
            $advertencias.Add("$repo está en la rama '$rama', no en 'main'.")
        }
    }

    return [pscustomobject]@{
        Bloqueos      = $bloqueos
        Advertencias  = $advertencias
        Notas         = $notas
        HayBloqueos   = $bloqueos.Count -gt 0
    }
}

# Ejecución directa (no dot-source): muestra el resultado y sale con código útil.
if ($MyInvocation.InvocationName -ne '.') {
    $Raiz = Buscar-RaizDelWorkspace -Desde $PSScriptRoot

    Write-Host ''
    Write-Host '  PUERTA DE SEGURIDAD — ¿las pruebas pueden tocar datos reales?' -ForegroundColor Cyan
    Write-Host "  Raíz: $Raiz"
    Write-Host ''

    $resultado = Invoke-PuertaDeSeguridad -Raiz $Raiz

    foreach ($nota in $resultado.Notas) { Write-Host "  [i] $nota" -ForegroundColor DarkGray }
    if ($resultado.Notas.Count) { Write-Host '' }
    foreach ($aviso in $resultado.Advertencias) { Write-Host "  [!] $aviso" -ForegroundColor Yellow }
    if ($resultado.Advertencias.Count) { Write-Host '' }
    foreach ($bloqueo in $resultado.Bloqueos) { Write-Host "  [X] $bloqueo" -ForegroundColor Red }

    Write-Host ''
    if ($resultado.HayBloqueos) {
        Write-Host '  RESULTADO: BLOQUEADO. No corras pruebas hasta resolver lo anterior.' -ForegroundColor Red
        exit 1
    }
    Write-Host '  RESULTADO: sin bloqueos. Se puede verificar.' -ForegroundColor Green
    exit 0
}
