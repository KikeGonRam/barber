<#
.SYNOPSIS
  Levanta (o detiene) el entorno de DEMOSTRACIÓN aislado: MongoDB de desarrollo, API y web locales.

.DESCRIPTION
  Sirve para probar la aplicación con las cuentas ficticias de scripts/demo/sembrar-demo.php sin tocar
  la base compartida (Atlas). Garantías:
    - La API corre en un contenedor aparte (ub-demo-api) conectado SOLO a mongo-dev / urbanblade_dev.
    - Se monta un archivo vacío sobre .env y un directorio vacío sobre bootstrap/cache: ni las variables
      reales ni la configuración cacheada con Atlas pueden cargarse.
    - Usa una APP_KEY temporal, correo en modo "log" y sin claves de Stripe.
    - Solo escucha en 127.0.0.1 (API en 8001, web en 3002).
  No usa "php artisan serve": ese servidor descarta las variables de entorno y caería en la configuración cacheada.

.EXAMPLE
  .\scripts\demo\levantar-demo.ps1             # levanta mongo-dev y la API (y la web si hay compilación)
  .\scripts\demo\levantar-demo.ps1 -Sembrar    # además crea las cuentas y los datos de demostración
  .\scripts\demo\levantar-demo.ps1 -Detener    # detiene la API y la web (los datos quedan en mongo-dev)

.NOTES
  Requiere Docker. Para la web: en frontend-urban, `$env:NUXT_PUBLIC_API_BASE='http://localhost:8001/api/v1'; npm run build`
  una vez. Las cuentas son demo-<rol>@urbanblade.test; la contraseña se define con la variable UB_DEMO_CLAVE o se genera al sembrar (sembrar-demo.php la muestra).
#>
[CmdletBinding()]
param([switch]$Sembrar, [switch]$Detener)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$front = Join-Path (Split-Path $repo -Parent) 'frontend-urban'

if ($Detener) {
    docker rm -f ub-demo-api 2>$null | Out-Null
    $p = (Get-NetTCPConnection -LocalPort 3002 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    if ($p) { Stop-Process -Id $p -Force }
    Write-Host 'Entorno de demostración detenido. Los datos siguen en mongo-dev.'
    return
}

Push-Location $repo
try {
    docker compose --env-file .env.development -f docker-compose.yml -f docker-compose.development.yml up -d mongo-dev mongo-dev-init | Out-Null

    docker rm -f ub-demo-api 2>$null | Out-Null
    $vacio = Join-Path $env:TEMP 'ub_demo_env_vacio'
    New-Item -ItemType File -Force $vacio | Out-Null
    $bytes = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $envs = @(
        'APP_ENV=local', 'APP_DEBUG=true', ('APP_KEY=base64:' + [Convert]::ToBase64String($bytes)),
        'APP_URL=http://localhost:8001', 'FRONTEND_URL=http://localhost:3002',
        'DATA_ENVIRONMENT=development', 'DB_CONNECTION=mongodb',
        'MONGODB_URI=mongodb://mongo-dev:27017/?replicaSet=rsdev', 'MONGO_DATABASE=urbanblade_dev',
        'ANALYTICS_MONGODB_URI=mongodb://mongo-dev:27017/?replicaSet=rsdev', 'ANALYTICS_MONGO_DATABASE=urbanblade_analytics_dev',
        'APP_CONFIG_CACHE=/tmp/ub-demo-config.php', 'APP_EVENTS_CACHE=/tmp/ub-demo-events.php', 'APP_PACKAGES_CACHE=/tmp/ub-demo-packages.php',
        'APP_ROUTES_CACHE=/tmp/ub-demo-routes.php', 'APP_SERVICES_CACHE=/tmp/ub-demo-services.php',
        'CACHE_STORE=file', 'SESSION_DRIVER=file', 'QUEUE_CONNECTION=sync', 'MAIL_MAILER=log', 'LOG_CHANNEL=stderr',
        'FILESYSTEM_DISK=public', 'CORS_ALLOWED_ORIGINS=http://localhost:3002,http://127.0.0.1:3002',
        'FIRST_USER_ADMIN_ENABLED=false', 'TRUSTED_PROXY_HOPS=0'
    )
    $args = @('run', '-d', '--name', 'ub-demo-api', '--network', 'barber_internal', '-p', '127.0.0.1:8001:8000',
        '-v', "${repo}:/var/www/html", '-v', 'barber_vendor_data:/var/www/html/vendor',
        '-v', 'ub_demo_bootcache:/var/www/html/bootstrap/cache', '-v', "${vacio}:/var/www/html/.env:ro")
    foreach ($e in $envs) { $args += @('-e', $e) }
    $args += @('barber-app:latest', 'php', '-S', '0.0.0.0:8000', '-t', 'public', 'public/index.php')
    docker @args | Out-Null
    Start-Sleep -Seconds 4

    # Comprobación de aislamiento: la conexión debe resolver a mongo-dev / urbanblade_dev.
    $chequeo = docker exec ub-demo-api php artisan tinker --execute="echo config('database.connections.mongodb.database');" 2>&1 | Select-Object -Last 1
    if ($chequeo -notmatch 'urbanblade_dev') { docker rm -f ub-demo-api | Out-Null; throw "Aislamiento no verificado (base: $chequeo). Se detuvo la API." }
    Write-Host "API de demostración lista en http://localhost:8001 (base: $chequeo)."

    if ($Sembrar) {
        docker exec ub-demo-api php artisan tinker --execute="include 'scripts/demo/sembrar-demo.php';"
    }
} finally {
    Pop-Location
}

if (Test-Path (Join-Path $front '.output\server\index.mjs')) {
    $env:NUXT_PUBLIC_API_BASE = 'http://localhost:8001/api/v1'; $env:PORT = '3002'; $env:HOST = '127.0.0.1'; $env:NODE_ENV = 'production'
    if (-not (Get-NetTCPConnection -LocalPort 3002 -State Listen -ErrorAction SilentlyContinue)) {
        Start-Process -FilePath node -ArgumentList '.output/server/index.mjs' -WorkingDirectory $front -WindowStyle Hidden
    }
    Write-Host 'Web de demostración en http://127.0.0.1:3002'
} else {
    Write-Host 'La web no está compilada: en frontend-urban ejecuta una vez `npm run build` con NUXT_PUBLIC_API_BASE=http://localhost:8001/api/v1.'
}
