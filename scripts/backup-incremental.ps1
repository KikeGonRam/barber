# Backup incremental de MySQL (binlog) para Docker Compose
# Guarda este archivo como backup-incremental.ps1 y ejecútalo en PowerShell

$fecha = Get-Date -Format yyyyMMdd
$backupFile = "binlog_${fecha}.sql"
$localBackupDir = "./backups/incremental"
$containerBackupDir = "/backups/incremental"
$binlogPath = "/var/log/mysql"

# 1. Levanta el contenedor si no está corriendo
docker compose up -d mysql

# 2. Crea la carpeta incremental dentro del contenedor
# (no falla si ya existe)
docker compose exec mysql bash -c "mkdir -p $containerBackupDir"

# 3. Detecta el último binlog generado
echo "Listando binlogs disponibles..."
$binlogs = docker compose exec mysql bash -c "ls $binlogPath | grep mysql-bin."
$ultimoBinlog = $binlogs -split "\n" | Select-Object -Last 1

if (-not $ultimoBinlog) {
    Write-Host "No se encontró ningún binlog. ¿Está activado el binary log?"
    exit 1
}

Write-Host "Respaldo incremental desde: $ultimoBinlog"

# 4. Genera el respaldo incremental dentro del contenedor
docker compose exec mysql bash -c "mysqlbinlog $binlogPath/$ultimoBinlog > $containerBackupDir/$backupFile"

# 5. Crea la carpeta local si no existe
if (!(Test-Path $localBackupDir)) { New-Item -ItemType Directory -Path $localBackupDir }

# 6. Copia el respaldo incremental a tu máquina local
docker compose cp barber-mysql:$containerBackupDir/$backupFile $localBackupDir/

Write-Host "Backup incremental copiado a $localBackupDir/$backupFile"
