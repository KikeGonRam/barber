<#
.SYNOPSIS
    Clona y levanta el proyecto UrbanBlade (barber) en Windows con Docker Desktop.

.DESCRIPTION
    Este script:
      1. Verifica que tengas Git, Docker Desktop y Node.js instalados.
      2. Clona el repositorio (o lo actualiza si ya existe localmente).
      3. Verifica que exista un archivo .env (el jefe de equipo te lo pasa por separado,
         este script NO genera uno falso ni sobreescribe uno existente).
      4. Levanta los contenedores con Docker Compose.
      5. Compila los assets del frontend (Vite/Tailwind) en tu máquina —
         esto NO corre dentro de Docker, necesitas Node.js instalado localmente.
      6. Imprime las URLs finales.

.PARAMETER RepoUrl
    URL del repositorio a clonar.

.PARAMETER Branch
    Rama a clonar/actualizar.

.PARAMETER TargetDir
    Carpeta donde se clonará el proyecto (relativa al directorio actual).

.EXAMPLE
    .\setup.ps1
    Usa los valores por defecto (rama feature/mongodb-migration).

.EXAMPLE
    .\setup.ps1 -Branch main -TargetDir C:\Proyectos\barber
#>

param(
    [string]$RepoUrl   = "https://github.com/KikeGonRam/barber.git",
    [string]$Branch    = "feature/mongodb-migration",
    [string]$TargetDir = "barber"
)

$ErrorActionPreference = "Stop"

function Write-Step($msg) {
    Write-Host ""
    Write-Host "==> $msg" -ForegroundColor Cyan
}

function Write-Ok($msg) {
    Write-Host "    OK: $msg" -ForegroundColor Green
}

function Write-Warn($msg) {
    Write-Host "    AVISO: $msg" -ForegroundColor Yellow
}

function Fail($msg) {
    Write-Host ""
    Write-Host "ERROR: $msg" -ForegroundColor Red
    exit 1
}

# ── 1. Verificar prerequisitos ────────────────────────────────────────────────
Write-Step "Verificando herramientas necesarias"

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Fail "Git no está instalado. Descárgalo de https://git-scm.com/download/win"
}
Write-Ok "Git encontrado: $(git --version)"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Fail "Docker no está instalado. Instala Docker Desktop: https://www.docker.com/products/docker-desktop/"
}
try {
    docker info *> $null
    Write-Ok "Docker Desktop está corriendo"
} catch {
    Fail "Docker Desktop no está corriendo. Ábrelo y espera a que inicie por completo, luego vuelve a ejecutar este script."
}

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    Write-Warn "Node.js no está instalado. Necesario para compilar el frontend (Vite/Tailwind)."
    Write-Warn "Descárgalo de https://nodejs.org/ (versión LTS) y vuelve a correr este script."
    $skipFrontend = $true
} else {
    Write-Ok "Node.js encontrado: $(node --version)"
}

# ── 2. Clonar o actualizar el repositorio ──────────────────────────────────────
Write-Step "Preparando el código fuente"

if (Test-Path $TargetDir) {
    Write-Warn "La carpeta '$TargetDir' ya existe. Actualizando en vez de clonar de nuevo..."
    Push-Location $TargetDir
    git fetch origin
    git checkout $Branch
    git pull origin $Branch
    Pop-Location
} else {
    git clone --branch $Branch $RepoUrl $TargetDir
    Write-Ok "Repositorio clonado en .\$TargetDir"
}

Push-Location $TargetDir

# ── 3. Verificar archivo .env ──────────────────────────────────────────────────
Write-Step "Verificando archivo .env"

if (-not (Test-Path ".env")) {
    Write-Host ""
    Write-Host "    No existe un archivo .env en este proyecto." -ForegroundColor Yellow
    Write-Host "    1. Pide el archivo .env al responsable del proyecto (contiene credenciales" -ForegroundColor Yellow
    Write-Host "       reales de MongoDB Atlas y no debe subirse a git)." -ForegroundColor Yellow
    Write-Host "    2. Colócalo en: $((Get-Location).Path)\.env" -ForegroundColor Yellow
    Write-Host "    3. Vuelve a ejecutar este script." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "    (Referencia de las variables esperadas: .env.example)" -ForegroundColor DarkGray
    Pop-Location
    exit 1
}
Write-Ok "Archivo .env encontrado"

if (-not (Select-String -Path ".env" -Pattern "^APP_KEY=.+" -Quiet)) {
    Write-Warn "APP_KEY parece vacío. Se generará automáticamente en el siguiente paso."
}

# ── 4. Levantar contenedores ───────────────────────────────────────────────────
Write-Step "Levantando contenedores con Docker Compose (puede tardar varios minutos la primera vez)"

docker compose up -d --build
if ($LASTEXITCODE -ne 0) { Fail "docker compose up falló. Revisa el mensaje de error arriba." }

Write-Step "Esperando a que el contenedor de la app esté saludable"
$maxRetries = 40
$ready = $false
for ($i = 1; $i -le $maxRetries; $i++) {
    $status = docker inspect --format='{{.State.Health.Status}}' barber-app 2>$null
    if ($status -eq "healthy") { $ready = $true; break }
    Write-Host "    ($i/$maxRetries) estado: $status ..." -ForegroundColor DarkGray
    Start-Sleep -Seconds 5
}
if ($ready) {
    Write-Ok "Contenedor barber-app saludable"
} else {
    Write-Warn "El contenedor sigue sin reportarse 'healthy'. Puede seguir instalando dependencias/migrando en segundo plano."
    Write-Warn "Revisa el progreso con: docker logs -f barber-app"
}

# Generar APP_KEY si falta (idempotente, no rompe nada si ya existe uno)
docker compose exec -T app php artisan key:generate --force *> $null

# ── 5. Generar clave de aplicación y correr migraciones ────────────────────────
Write-Step "Verificando migraciones"
docker compose exec -T app php artisan migrate --force

# ── 6. Compilar frontend (en el host, no en Docker) ────────────────────────────
if (-not $skipFrontend) {
    Write-Step "Instalando dependencias de Node y compilando el frontend"
    npm install
    npm run build
    Write-Ok "Frontend compilado"
} else {
    Write-Warn "Se omitió la compilación del frontend (instala Node.js y corre 'npm install && npm run build')"
}

# ── 7. Resumen final ────────────────────────────────────────────────────────────
Pop-Location

Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host " Proyecto listo" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  App:      http://localhost:8000"
Write-Host "  Mailpit:  http://localhost:8025   (correos de prueba)"
Write-Host ""
Write-Host "  Comandos útiles (dentro de la carpeta '$TargetDir'):"
Write-Host "    docker compose logs -f              # ver logs en vivo"
Write-Host "    docker compose exec app bash        # entrar al contenedor"
Write-Host "    docker compose down                 # apagar todo"
Write-Host "========================================================" -ForegroundColor Green
