# Script PowerShell: Automatización de tareas Redis
# Guarda como redis-tasks.ps1 y programa en el Programador de tareas de Windows

$fecha = Get-Date -Format yyyyMMdd
$backupDir = "./backups/redis"

# 1. Snapshot RDB manual (diario)
docker compose exec redis redis-cli BGSAVE
if (!(Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir }
docker compose exec redis cp /data/dump.rdb /backups/redis/dump_$fecha.rdb

# 2. Rotación de archivos AOF (semanal)
docker compose exec redis redis-cli BGREWRITEAOF

# 3. Monitoreo de memoria (cada 5 min, opcional)
$meminfo = docker compose exec redis redis-cli INFO memory
$meminfo | Out-File "$backupDir/memory_$fecha.log" -Encoding utf8

Write-Host "Tareas de Redis ejecutadas. Backup y monitoreo completados."
