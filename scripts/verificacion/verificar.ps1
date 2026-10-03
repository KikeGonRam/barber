<#
.SYNOPSIS
    Sandbox de verificación: corre las pruebas de los 4 proyectos antes de subir.

.DESCRIPTION
    Un solo comando para verificar UrbanBlade antes de publicar. Detecta qué
    herramientas hay en la máquina, corre lo que se puede correr, y para lo que no
    se puede dice por qué y cuál es el comando exacto que falta.

    SIEMPRE empieza por la puerta de seguridad (puerta-seguridad.ps1): si algo
    apunta a datos reales, se detiene sin ejecutar ninguna prueba.

.PARAMETER Proyecto
    Qué verificar. 'todo' (por defecto) = todos los grupos.
    Grupos: seguridad, documentacion, barber, frontend, spark, mobile.

.PARAMETER Rapido
    Omite lo lento (las pruebas e2e de Playwright y el build de producción).

.PARAMETER Python
    Intérprete para spark. Por defecto 'python'. En el entorno del curso es el
    de conda dentro de WSL: usa -Python 'wsl -d Ubuntu -e conda run -n spark_env python'
    o activa el entorno antes de llamar a este script.

.EXAMPLE
    .\verificar.ps1
    .\verificar.ps1 -Proyecto frontend,spark
    .\verificar.ps1 -Rapido
#>
[CmdletBinding()]
param(
    # Acepta tanto '-Proyecto frontend,spark' (invocación normal) como
    # '-Proyecto frontend,spark' pasada por `pwsh -File`, donde los argumentos
    # llegan como un solo texto. Por eso NO lleva ValidateSet: se valida abajo.
    [string[]]$Proyecto = @('todo'),

    [switch]$Rapido,

    [string]$Python = 'python',

    # Vacío = localizar la carpeta contenedora subiendo desde este archivo
    # (ver lib/comun.ps1), de modo que el sandbox funcione desde donde se mueva.
    [string]$Raiz = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib\comun.ps1')

if ($Raiz) {
    $Raiz = (Resolve-Path -LiteralPath $Raiz).Path
}
else {
    $Raiz = Buscar-RaizDelWorkspace -Desde $PSScriptRoot
}

$script:Resultados = [System.Collections.Generic.List[object]]::new()

$todosLosGrupos = @('seguridad', 'documentacion', 'barber', 'frontend', 'spark', 'mobile')

# Normaliza: admite "frontend,spark" en un solo elemento, y repetir -Proyecto.
$Proyecto = @($Proyecto | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ })
foreach ($grupo in $Proyecto) {
    if ($grupo -notin ($todosLosGrupos + 'todo')) {
        throw "Grupo desconocido: '$grupo'. Válidos: $($todosLosGrupos -join ', '), todo."
    }
}
if ($Proyecto -contains 'todo') { $Proyecto = $todosLosGrupos }

# ─────────────────────────────────────────────────────────────────────────────
# Salida y registro de resultados
# ─────────────────────────────────────────────────────────────────────────────
function Escribir-Titulo {
    param([string]$Texto)
    Write-Host ''
    Write-Host "  ── $Texto " -ForegroundColor Cyan
}

function Agregar-Resultado {
    param(
        [string]$Grupo,
        [string]$Chequeo,
        [ValidateSet('OK', 'FALLO', 'OMITIDO', 'AVISO')][string]$Estado,
        [double]$Segundos,
        [string]$Detalle = ''
    )
    $script:Resultados.Add([pscustomobject]@{
        Grupo    = $Grupo
        Chequeo  = $Chequeo
        Estado   = $Estado
        Segundos = [math]::Round($Segundos, 1)
        Detalle  = $Detalle
    })
}

function Ejecutar-Chequeo {
    param(
        [string]$Grupo,
        [string]$Chequeo,
        [string]$Directorio,
        [string]$Comando,
        [string[]]$Argumentos = @(),
        [string]$AyudaSiFalta = ''
    )

    if (-not (Get-Command $Comando -ErrorAction SilentlyContinue)) {
        Write-Host ("  [OMITIDO] {0}: falta '{1}' en esta máquina." -f $Chequeo, $Comando) -ForegroundColor DarkYellow
        if ($AyudaSiFalta) { Write-Host "            $AyudaSiFalta" -ForegroundColor DarkGray }
        Agregar-Resultado -Grupo $Grupo -Chequeo $Chequeo -Estado 'OMITIDO' -Segundos 0 -Detalle "falta $Comando"
        return
    }

    # El progreso se escribe en la misma linea (se borra con \r). Se rellena a un
    # ancho fijo para que, en un terminal, la linea de resultado no deje restos de
    # la de progreso cuando es mas corta; y para que un log redirigido se lea igual.
    $ancho = 78
    $lineaProgreso = ("  [...] {0}" -f $Chequeo).PadRight($ancho)
    Write-Host $lineaProgreso -ForegroundColor Gray -NoNewline
    $inicio = Get-Date
    $salida = ''
    $codigo = 0
    Push-Location $Directorio
    try {
        $salida = (& $Comando @Argumentos 2>&1 | Out-String)
        $codigo = $LASTEXITCODE
        if ($null -eq $codigo) { $codigo = 0 }
    }
    catch {
        $salida = $_.Exception.Message
        $codigo = 1
    }
    finally { Pop-Location }
    $segundos = ((Get-Date) - $inicio).TotalSeconds

    if ($codigo -eq 0) {
        $lineaOk = ("`r  [OK]     {0} ({1}s)" -f $Chequeo, [math]::Round($segundos, 1)).PadRight($ancho)
        Write-Host $lineaOk -ForegroundColor Green
        Agregar-Resultado -Grupo $Grupo -Chequeo $Chequeo -Estado 'OK' -Segundos $segundos
    }
    else {
        $lineaFallo = ("`r  [FALLO]  {0} ({1}s)" -f $Chequeo, [math]::Round($segundos, 1)).PadRight($ancho)
        Write-Host $lineaFallo -ForegroundColor Red
        $ultimasLineas = ($salida -split "`r?`n" | Where-Object { $_.Trim() } | Select-Object -Last 12)
        foreach ($linea in $ultimasLineas) { Write-Host "            $linea" -ForegroundColor DarkRed }
        Agregar-Resultado -Grupo $Grupo -Chequeo $Chequeo -Estado 'FALLO' -Segundos $segundos -Detalle "código $codigo"
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# Grupo: seguridad — ningún archivo de credenciales versionado, sin llaves
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Seguridad {
    Escribir-Titulo 'seguridad'

    $patronArchivo = '(^|/)\.env($|\.)|\.pem$|\.p12$|\.jks$|\.keystore$|adminsdk|accessKeys|credentials.*\.json$|service.?account|local\.properties|google-services\.json'
    $permitidos = '\.(example|testing|development\.example)$'
    $patronContenido = 'AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY-----|sk_live_[0-9a-zA-Z]{10,}|AIza[0-9A-Za-z_-]{35}'

    foreach ($repo in @('barber', 'frontend-urban', 'spark', 'UrbanBladeMobile')) {
        $rutaRepo = Join-Path $Raiz $repo
        if (-not (Test-Path -LiteralPath (Join-Path $rutaRepo '.git'))) {
            Agregar-Resultado -Grupo 'seguridad' -Chequeo "$repo`_no_es_repo" -Estado 'AVISO' -Segundos 0 -Detalle 'sin .git'
            continue
        }

        $nombreArchivos = "$repo`: ningún archivo de credenciales versionado"
        $infractores = & git -C $rutaRepo ls-files 2>$null | Select-String -Pattern $patronArchivo |
            Where-Object { $_.Line -notmatch $permitidos }
        if ($infractores) {
            Write-Host ("  [FALLO]  {0}" -f $nombreArchivos) -ForegroundColor Red
            $infractores | ForEach-Object { Write-Host "            $($_.Line)" -ForegroundColor DarkRed }
            Agregar-Resultado -Grupo 'seguridad' -Chequeo $nombreArchivos -Estado 'FALLO' -Segundos 0 -Detalle "$($infractores.Count) archivo(s)"
        }
        else {
            Write-Host ("  [OK]     {0}" -f $nombreArchivos) -ForegroundColor Green
            Agregar-Resultado -Grupo 'seguridad' -Chequeo $nombreArchivos -Estado 'OK' -Segundos 0
        }

        $nombreLlaves = "$repo`: ninguna llave en el contenido versionado"
        $hallazgos = & git -C $rutaRepo grep -n -I -E $patronContenido -- . 2>$null
        if ($hallazgos) {
            Write-Host ("  [FALLO]  {0}" -f $nombreLlaves) -ForegroundColor Red
            $hallazgos | Select-Object -First 8 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkRed }
            Agregar-Resultado -Grupo 'seguridad' -Chequeo $nombreLlaves -Estado 'FALLO' -Segundos 0 -Detalle "$($hallazgos.Count) coincidencia(s)"
        }
        else {
            Write-Host ("  [OK]     {0}" -f $nombreLlaves) -ForegroundColor Green
            Agregar-Resultado -Grupo 'seguridad' -Chequeo $nombreLlaves -Estado 'OK' -Segundos 0
        }
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# Grupo: documentacion — los enlaces relativos de los .md resuelven
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Documentacion {
    Escribir-Titulo 'documentacion'

    $archivos = [System.Collections.Generic.List[string]]::new()
    foreach ($candidato in @(
            (Join-Path $Raiz 'README.md'),
            (Join-Path $Raiz 'barber\AGENTS.md'),
            (Join-Path $Raiz 'spark\CLAUDE.md'),
            (Join-Path $Raiz 'spark\README.md'),
            (Join-Path $Raiz 'frontend-urban\README.md'),
            (Join-Path $Raiz 'UrbanBladeMobile\README.md'),
            (Join-Path $Raiz 'barber\scripts\verificacion\README.md'))) {
        if (Test-Path -LiteralPath $candidato -PathType Leaf) { $archivos.Add($candidato) }
    }
    foreach ($carpeta in @((Join-Path $Raiz 'barber\docs'), (Join-Path $Raiz '_seguridad'))) {
        if (Test-Path -LiteralPath $carpeta) {
            Get-ChildItem -Path (Join-Path $carpeta '*') -File |
                Where-Object { $_.Extension -in '.md', '.txt' } |
                ForEach-Object { $archivos.Add($_.FullName) }
        }
    }

    $rotos = [System.Collections.Generic.List[string]]::new()
    $revisados = 0
    $enlaces = 0
    foreach ($archivo in $archivos) {
        $contenido = [System.IO.File]::ReadAllText($archivo)
        $directorio = Split-Path $archivo -Parent
        $revisados++
        foreach ($coincidencia in [regex]::Matches($contenido, '\]\(([^)]+)\)')) {
            $destino = $coincidencia.Groups[1].Value
            if ($destino -match '^https?://' -or $destino -match '^#') { continue }
            $enlaces++
            if (-not (Test-Path -LiteralPath (Join-Path $directorio $destino))) {
                $rotos.Add("$(Split-Path $archivo -Leaf) -> $destino")
            }
        }
    }

    if ($rotos.Count -eq 0) {
        Write-Host ("  [OK]     enlaces relativos: {0} resuelven en {1} archivos" -f $enlaces, $revisados) -ForegroundColor Green
        Agregar-Resultado -Grupo 'documentacion' -Chequeo 'enlaces relativos' -Estado 'OK' -Segundos 0 -Detalle "$enlaces enlaces"
    }
    else {
        Write-Host ("  [FALLO]  {0} enlace(s) roto(s)" -f $rotos.Count) -ForegroundColor Red
        $rotos | Select-Object -First 10 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkRed }
        Agregar-Resultado -Grupo 'documentacion' -Chequeo 'enlaces relativos' -Estado 'FALLO' -Segundos 0 -Detalle "$($rotos.Count) roto(s)"
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# Chequeo de contrato: el OpenAPI debe describir las rutas reales.
# Ver barber/docs/PLAN_CONTRATO_API.md y scripts/verificar_contrato_api.mjs.
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Contrato {
    param([Parameter(Mandatory)][string]$Dir)

    $chequeo = 'contrato OpenAPI vs rutas reales (160)'
    $temporal = Join-Path $Dir 'storage\verificar-contrato.json'
    $relativoTemporal = 'storage\verificar-contrato.json'

    if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
        Write-Host '  [OMITIDO] contrato OpenAPI: falta node.' -ForegroundColor DarkYellow
        Agregar-Resultado -Grupo 'barber' -Chequeo $chequeo -Estado 'OMITIDO' -Segundos 0 -Detalle 'falta node'
        return
    }

    $inicio = Get-Date
    $salida = ''
    $codigo = 0
    Push-Location $Dir
    try {
        # Las rutas reales se escriben DENTRO del contenedor, en el volumen montado,
        # para no mezclar la salida con los avisos de deprecacion de PHP.
        & docker exec --env-file .env.testing barber-app sh -c `
            "php artisan route:list --json > /var/www/html/storage/verificar-contrato.json 2>/dev/null" 2>&1 | Out-Null

        if (-not (Test-Path -LiteralPath $temporal)) {
            throw 'el contenedor no genero el listado de rutas'
        }

        $salida = (& node 'scripts\verificar_contrato_api.mjs' $relativoTemporal 'public\docs\openapi.yaml' 2>&1 | Out-String)
        $codigo = $LASTEXITCODE
        if ($null -eq $codigo) { $codigo = 0 }
    }
    catch {
        $salida = $_.Exception.Message
        $codigo = 1
    }
    finally {
        Remove-Item -LiteralPath $temporal -Force -ErrorAction SilentlyContinue
        Pop-Location
    }
    $segundos = ((Get-Date) - $inicio).TotalSeconds

    if ($codigo -eq 0) {
        Write-Host ("  [OK]     contrato OpenAPI: las rutas reales estan documentadas ({0}s)" -f [math]::Round($segundos, 1)) -ForegroundColor Green
        Agregar-Resultado -Grupo 'barber' -Chequeo $chequeo -Estado 'OK' -Segundos $segundos
    }
    else {
        Write-Host ("  [FALLO]  contrato OpenAPI desactualizado ({0}s)" -f [math]::Round($segundos, 1)) -ForegroundColor Red
        ($salida -split "`r?`n" | Where-Object { $_.Trim() } | Select-Object -First 10) |
            ForEach-Object { Write-Host "            $_" -ForegroundColor DarkRed }
        Write-Host '            Regenera con: docker exec --env-file .env.testing barber-app php artisan scribe:generate' -ForegroundColor DarkGray
        Agregar-Resultado -Grupo 'barber' -Chequeo $chequeo -Estado 'FALLO' -Segundos $segundos -Detalle 'deriva con routes/api.php'
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# Grupo: barber — solo por .\test.ps1 y solo con Docker arriba
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Barber {
    Escribir-Titulo 'barber'
    $dir = Join-Path $Raiz 'barber'

    # Buscar-Docker (lib/comun.ps1) mira tambien la ruta de instalacion de Docker
    # Desktop: en Windows no siempre esta en el PATH, y sin esto el sandbox decia
    # "falta Docker" teniendolo instalado y corriendo.
    $docker = Buscar-Docker
    if (-not $docker) {
        Write-Host '  [OMITIDO] tests de barber: falta Docker en esta máquina.' -ForegroundColor DarkYellow
        Write-Host '            Para correrlos: abre Docker Desktop, luego en barber/:  docker compose up -d' -ForegroundColor DarkGray
        Write-Host '                             y después:                        .\test.ps1' -ForegroundColor DarkGray
        Write-Host '            NUNCA "php artisan test" directo: apunta a la base compartida.' -ForegroundColor DarkGray
        Agregar-Resultado -Grupo 'barber' -Chequeo 'PHPUnit (104 archivos)' -Estado 'OMITIDO' -Segundos 0 -Detalle 'falta Docker'
        return
    }

    # test.ps1 invoca `docker` sin ruta absoluta: si no está en el PATH, hay que
    # añadirlo para que el script funcione.
    $dirDocker = Split-Path $docker -Parent
    if ($env:PATH -notlike "*$dirDocker*") { $env:PATH = "$dirDocker;$env:PATH" }

    $contenedor = (& docker ps --filter 'name=barber-app' --format '{{.Names}}' 2>$null | Out-String).Trim()
    if (-not $contenedor) {
        Write-Host '  [OMITIDO] el contenedor barber-app no está corriendo.' -ForegroundColor DarkYellow
        Write-Host '            Levántalo con:  cd barber; docker compose up -d' -ForegroundColor DarkGray
        Write-Host '            OJO: el entrypoint del contenedor corre "php artisan migrate --force" contra' -ForegroundColor DarkGray
        Write-Host '            lo que diga .env, que apunta a Atlas. Léelo antes de levantarlo.' -ForegroundColor DarkGray
        Agregar-Resultado -Grupo 'barber' -Chequeo 'PHPUnit (104 archivos, vía test.ps1)' -Estado 'OMITIDO' -Segundos 0 -Detalle 'contenedor apagado'
        Agregar-Resultado -Grupo 'barber' -Chequeo 'contrato OpenAPI vs rutas reales (160)' -Estado 'OMITIDO' -Segundos 0 -Detalle 'contenedor apagado'
        return
    }

    Ejecutar-Chequeo -Grupo 'barber' -Chequeo 'PHPUnit (104 archivos, vía test.ps1)' `
        -Directorio $dir -Comando 'pwsh' -Argumentos @('-NoProfile', '-File', '.\test.ps1')

    Verificar-Contrato -Dir $dir
}

# ─────────────────────────────────────────────────────────────────────────────
# Chequeo de tipos de TypeScript del frontend.
#
# Es BLOQUEANTE desde el 2026-10-02: ese día se corrigieron los 3 errores que
# arrastraba el árbol (en app/composables/useApi.ts y usePush.ts) y `tsc` queda
# en 0. Antes se reportaba como AVISO porque eran preexistentes y una puerta
# siempre en rojo se termina ignorando; ahora que está limpio, un error nuevo es
# una regresión de verdad.
#
# Ojo: es un chequeo PARCIAL. Sin vue-tsc (no instalado, y no se puede instalar
# sin red) los archivos .vue no se revisan, solo el TypeScript suelto.
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-TiposFrontend {
    $dir = Join-Path $Raiz 'frontend-urban'
    $tsconfig = Join-Path $dir '.nuxt\tsconfig.json'

    if (-not (Test-Path -LiteralPath $tsconfig)) {
        Write-Host '  [OMITIDO] tipos de TypeScript: falta .nuxt/tsconfig.json (se genera al compilar).' -ForegroundColor DarkYellow
        Agregar-Resultado -Grupo 'frontend' -Chequeo 'tipos TypeScript' -Estado 'OMITIDO' -Segundos 0 -Detalle 'sin .nuxt/tsconfig.json'
        return
    }

    $inicio = Get-Date
    $salida = ''
    Push-Location $dir
    try { $salida = (& npx tsc --noEmit -p .nuxt/tsconfig.json 2>&1 | Out-String) }
    catch { $salida = $_.Exception.Message }
    finally { Pop-Location }
    $segundos = ((Get-Date) - $inicio).TotalSeconds

    $errores = [regex]::Matches($salida, 'error TS\d+')
    if ($errores.Count -eq 0) {
        Write-Host ("  [OK]     tipos TypeScript ({0}s)" -f [math]::Round($segundos, 1)) -ForegroundColor Green
        Agregar-Resultado -Grupo 'frontend' -Chequeo 'tipos TypeScript' -Estado 'OK' -Segundos $segundos
        return
    }

    $archivos = [regex]::Matches($salida, '(?m)^(\S+\.tsx?)\(\d+,\d+\): error') |
        ForEach-Object { $_.Groups[1].Value } | Sort-Object -Unique
    Write-Host ("  [FALLO]  tipos TypeScript: {0} error(es) en {1} archivo(s) ({2}s)" -f $errores.Count, $archivos.Count, [math]::Round($segundos, 1)) -ForegroundColor Red
    $archivos | Select-Object -First 6 | ForEach-Object { Write-Host "            $_" -ForegroundColor DarkRed }
    Write-Host '            Revisa con: npx tsc --noEmit -p .nuxt/tsconfig.json' -ForegroundColor DarkGray
    Agregar-Resultado -Grupo 'frontend' -Chequeo 'tipos TypeScript' -Estado 'FALLO' -Segundos $segundos -Detalle "$($errores.Count) error(es) en $($archivos.Count) archivo(s)"
}

# ─────────────────────────────────────────────────────────────────────────────
# Grupo: frontend-urban — lint, build y e2e (API mockeada, no toca el backend)
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Frontend {
    Escribir-Titulo 'frontend-urban'
    $dir = Join-Path $Raiz 'frontend-urban'

    Ejecutar-Chequeo -Grupo 'frontend' -Chequeo 'ESLint (--max-warnings=0)' `
        -Directorio $dir -Comando 'npx' -Argumentos @('eslint', '.', '--max-warnings=0')

    Verificar-TiposFrontend

    if ($Rapido) {
        Write-Host '  [OMITIDO] build de producción y e2e (-Rapido)' -ForegroundColor DarkYellow
        Agregar-Resultado -Grupo 'frontend' -Chequeo 'build de producción' -Estado 'OMITIDO' -Segundos 0 -Detalle '-Rapido'
        Agregar-Resultado -Grupo 'frontend' -Chequeo 'Playwright e2e (58 pruebas)' -Estado 'OMITIDO' -Segundos 0 -Detalle '-Rapido'
        return
    }

    Ejecutar-Chequeo -Grupo 'frontend' -Chequeo 'build de producción' `
        -Directorio $dir -Comando 'npm' -Argumentos @('run', 'build')

    # --workers=1 iguala el comportamiento del CI (playwright.config.ts usa
    # workers: 1 en CI). Medido el 2026-10-02: con el paralelismo por defecto
    # (fullyParallel, ~CPU/2 workers) la suite falló 1 de 55 por contención de
    # recursos — auth.spec.ts:38 se pasó del timeout de 5s de toHaveURL. En
    # solitario ese test tarda ~2.5s y pasa 24/24 (--repeat-each=3). Con un solo
    # worker la verificación local es reproducible y comparable con el CI.
    # NO se añaden reintentos: reintentar esconde la inestabilidad, y esta puerta
    # existe justo para detectarla.
    Ejecutar-Chequeo -Grupo 'frontend' -Chequeo 'Playwright e2e (58 pruebas)' `
        -Directorio $dir -Comando 'npm' -Argumentos @('run', 'test:e2e', '--', '--workers=1')
}

# ─────────────────────────────────────────────────────────────────────────────
# Grupo: spark — pruebas y sintaxis (no necesitan Spark ni Java)
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Spark {
    Escribir-Titulo 'spark'
    $dir = Join-Path $Raiz 'spark'

    if (-not (Get-Command $Python -ErrorAction SilentlyContinue)) {
        Write-Host ("  [OMITIDO] spark: no encontré el intérprete '{0}'." -f $Python) -ForegroundColor DarkYellow
        Write-Host '            En el entorno del curso: activa conda spark_env dentro de WSL y vuelve a correr.' -ForegroundColor DarkGray
        Agregar-Resultado -Grupo 'spark' -Chequeo 'pytest' -Estado 'OMITIDO' -Segundos 0 -Detalle "falta $Python"
        Agregar-Resultado -Grupo 'spark' -Chequeo 'sintaxis de 45+ scripts' -Estado 'OMITIDO' -Segundos 0 -Detalle "falta $Python"
        return
    }

    Ejecutar-Chequeo -Grupo 'spark' -Chequeo 'pytest (guarda de solo lectura incluida)' `
        -Directorio $dir -Comando $Python -Argumentos @('-m', 'pytest', 'tests/', '-q')

    Ejecutar-Chequeo -Grupo 'spark' -Chequeo 'sintaxis de 45+ scripts' `
        -Directorio $dir -Comando $Python -Argumentos @('-m', 'compileall', '-q', 'config', 'data_ingestion', 'unidades', 'tests', 'scripts')
}

# ─────────────────────────────────────────────────────────────────────────────
# Grupo: mobile — lint, pruebas unitarias y build de debug
# ─────────────────────────────────────────────────────────────────────────────
function Verificar-Mobile {
    Escribir-Titulo 'UrbanBladeMobile'
    $dir = Join-Path $Raiz 'UrbanBladeMobile'

    $sdk = ''
    $localProperties = Join-Path $dir 'local.properties'
    if (Test-Path -LiteralPath $localProperties -PathType Leaf) {
        $lineaSdk = [System.IO.File]::ReadAllLines($localProperties) | Where-Object { $_ -match '^sdk\.dir=' } | Select-Object -First 1
        if ($lineaSdk) { $sdk = ($lineaSdk -replace '^sdk\.dir=', '').Replace('\\', '\').Replace('\:', ':') }
    }

    $problemas = [System.Collections.Generic.List[string]]::new()
    if (-not (Get-Command gradle -ErrorAction SilentlyContinue)) { $problemas.Add('falta Gradle 8.13 (el repo no versiona gradlew)') }
    if (-not $sdk) { $problemas.Add('local.properties no define sdk.dir') }
    elseif (-not (Test-Path -LiteralPath $sdk)) { $problemas.Add("el Android SDK de sdk.dir no existe: $sdk") }

    if ($problemas.Count) {
        Write-Host '  [OMITIDO] pruebas de Android:' -ForegroundColor DarkYellow
        foreach ($p in $problemas) { Write-Host "            - $p" -ForegroundColor DarkGray }
        Write-Host '            Para correrlas (con Android Studio y SDK instalados):' -ForegroundColor DarkGray
        Write-Host '              gradle :app:lintDebug :app:testDebugUnitTest :app:assembleDebug' -ForegroundColor DarkGray
        Agregar-Resultado -Grupo 'mobile' -Chequeo 'lint + tests JVM (40) + build debug' -Estado 'OMITIDO' -Segundos 0 -Detalle ($problemas -join '; ')
        return
    }

    Ejecutar-Chequeo -Grupo 'mobile' -Chequeo 'Android lint' -Directorio $dir -Comando 'gradle' -Argumentos @(':app:lintDebug', '--console=plain')
    Ejecutar-Chequeo -Grupo 'mobile' -Chequeo 'pruebas unitarias JVM (40)' -Directorio $dir -Comando 'gradle' -Argumentos @(':app:testDebugUnitTest', '--console=plain')
    Ejecutar-Chequeo -Grupo 'mobile' -Chequeo 'build de debug' -Directorio $dir -Comando 'gradle' -Argumentos @(':app:assembleDebug', '--console=plain')
}

# ─────────────────────────────────────────────────────────────────────────────
# Ejecución
# ─────────────────────────────────────────────────────────────────────────────
Write-Host ''
Write-Host '  ╔══════════════════════════════════════════════════════════════╗' -ForegroundColor Cyan
Write-Host '  ║   URBANBLADE — Verificación antes de subir                    ║' -ForegroundColor Cyan
Write-Host '  ╚══════════════════════════════════════════════════════════════╝' -ForegroundColor Cyan
Write-Host "  Raíz:    $Raiz"
Write-Host ("  Grupos:  {0}" -f ($Proyecto -join ', '))
if ($Rapido) { Write-Host '  Modo:    rápido (sin build ni e2e)' -ForegroundColor DarkYellow }

# La puerta de seguridad va siempre primero y puede abortar todo.
if ($Proyecto -contains 'seguridad' -or $Proyecto -contains 'barber' -or $Proyecto -contains 'spark') {
    Escribir-Titulo 'puerta de seguridad'
    . (Join-Path $PSScriptRoot 'puerta-seguridad.ps1')
    $puerta = Invoke-PuertaDeSeguridad -Raiz $Raiz

    foreach ($nota in $puerta.Notas) { Write-Host "  [i] $nota" -ForegroundColor DarkGray }
    foreach ($aviso in $puerta.Advertencias) { Write-Host "  [!] $aviso" -ForegroundColor Yellow }
    foreach ($bloqueo in $puerta.Bloqueos) { Write-Host "  [X] $bloqueo" -ForegroundColor Red }

    if ($puerta.HayBloqueos) {
        Agregar-Resultado -Grupo 'seguridad' -Chequeo 'puerta de seguridad' -Estado 'FALLO' -Segundos 0 -Detalle "$($puerta.Bloqueos.Count) bloqueo(s)"
        Write-Host ''
        Write-Host '  Las pruebas NO se ejecutan: primero hay que resolver los bloqueos.' -ForegroundColor Red
        $script:Resultados | Format-Table -AutoSize
        exit 1
    }
    Agregar-Resultado -Grupo 'seguridad' -Chequeo 'puerta de seguridad' -Estado 'OK' -Segundos 0 -Detalle "$($puerta.Advertencias.Count) advertencia(s)"
}

if ($Proyecto -contains 'seguridad') { Verificar-Seguridad }
if ($Proyecto -contains 'documentacion') { Verificar-Documentacion }
if ($Proyecto -contains 'barber') { Verificar-Barber }
if ($Proyecto -contains 'frontend') { Verificar-Frontend }
if ($Proyecto -contains 'spark') { Verificar-Spark }
if ($Proyecto -contains 'mobile') { Verificar-Mobile }

# ─────────────────────────────────────────────────────────────────────────────
# Resumen
# ─────────────────────────────────────────────────────────────────────────────
$ok = ($script:Resultados | Where-Object { $_.Estado -eq 'OK' } | Measure-Object).Count
$fallos = ($script:Resultados | Where-Object { $_.Estado -eq 'FALLO' } | Measure-Object).Count
$omitidos = ($script:Resultados | Where-Object { $_.Estado -eq 'OMITIDO' } | Measure-Object).Count
$avisos = ($script:Resultados | Where-Object { $_.Estado -eq 'AVISO' } | Measure-Object).Count

Write-Host ''
Write-Host '  ══ RESUMEN ═══════════════════════════════════════════════════' -ForegroundColor Cyan
Write-Host ("  Correctos: {0}    Fallos: {1}    Omitidos: {2}    Avisos: {3}" -f $ok, $fallos, $omitidos, $avisos)
Write-Host ''

if ($fallos -gt 0) {
    Write-Host '  FALLOS:' -ForegroundColor Red
    $script:Resultados | Where-Object { $_.Estado -eq 'FALLO' } | ForEach-Object {
        Write-Host ("    - [{0}] {1} {2}" -f $_.Grupo, $_.Chequeo, $_.Detalle) -ForegroundColor Red
    }
}

if ($omitidos -gt 0) {
    Write-Host '  OMITIDOS (no se pudieron correr en esta máquina):' -ForegroundColor DarkYellow
    $script:Resultados | Where-Object { $_.Estado -eq 'OMITIDO' } | ForEach-Object {
        Write-Host ("    - [{0}] {1} ({2})" -f $_.Grupo, $_.Chequeo, $_.Detalle) -ForegroundColor DarkYellow
    }
    Write-Host ''
    Write-Host '  Un omitido NO es un aprobado: significa que esa parte sigue sin verificar.' -ForegroundColor DarkYellow
}

if ($avisos -gt 0) {
    Write-Host ''
    Write-Host '  AVISOS (no bloquean, pero conviene mirarlos):' -ForegroundColor Yellow
    $script:Resultados | Where-Object { $_.Estado -eq 'AVISO' } | ForEach-Object {
        Write-Host ("    - [{0}] {1} {2}" -f $_.Grupo, $_.Chequeo, $_.Detalle) -ForegroundColor Yellow
    }
}

Write-Host ''
if ($fallos -gt 0) {
    Write-Host '  RESULTADO: NO subir todavía.' -ForegroundColor Red
    exit 1
}
if ($omitidos -gt 0) {
    Write-Host '  RESULTADO: lo ejecutado pasó, pero quedan partes sin verificar.' -ForegroundColor Yellow
    exit 0
}
Write-Host '  RESULTADO: todo verificado.' -ForegroundColor Green
exit 0
