[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$')]
    [string]$Bucket,

    [Parameter(Mandatory)][ValidatePattern('^[a-z]{2}(-gov)?-[a-z]+-\d$')]
    [string]$Region,

    [Parameter(Mandatory)][string]$WriterProfile,

    [Parameter(Mandatory)][string]$RestoreProfile,

    [string]$LockFile = (Join-Path (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..\_seguridad')).Path 'backup.lock'),

    [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9/_-]*$')]
    [string]$Prefix = 'urbanblade/barber-db',

    [int]$AlertIfOlderHours = 30,

    [int]$MaxWindowDurationHours = 2,

    [switch]$Execute
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$barberRoot = Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..').Path
$scriptsRoot = Join-Path $barberRoot 'scripts'
$drillScript  = Join-Path $scriptsRoot 'Invoke-SyntheticBackupDrill.ps1'
$pubScript    = Join-Path $scriptsRoot 'Publish-EncryptedBackupToS3.ps1'
$alertScript  = Join-Path $scriptsRoot 'Send-BackupAlert.ps1'
$outputDir    = Join-Path $barberRoot 'storage\app\backup-drills'

function Test-InsideWindow {
    $now = Get-Date
    return ($now.Hour -ge 2) -and ($now.Hour -lt 4)
}

function Invoke-StopwatchTook {
    param([Parameter(Mandatory)][TimeSpan]$Elapsed)
    return [int][Math]::Ceiling($Elapsed.TotalMinutes)
}

if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
    throw 'AWS CLI v2 no está instalada. Instala el MSI oficial: https://awscli.amazonaws.com/AWSCLIV2.msi, cierra los terminales y vuelve a ejecutar.'
}

if (-not (Test-Path -LiteralPath $drillScript))  { throw "Falta $drillScript"  }
if (-not (Test-Path -LiteralPath $pubScript))    { throw "Falta $pubScript"    }
if (-not (Test-Path -LiteralPath $alertScript))  { Write-Warning "Falta $alertScript; las alertas no se enviarán." }

Write-Host "Run-BackupDaily: bucket=$Bucket region=$Region writer=$WriterProfile restore=$RestoreProfile execute=$($Execute.IsPresent)"

# --- Lock file: evita 2 ejecuciones a la vez + valida que la anterior no se estancó
$lockDir = Split-Path -LiteralPath $LockFile
if (-not (Test-Path -LiteralPath $lockDir)) { New-Item -ItemType Directory -Force -Path $lockDir | Out-Null }

$lockStaleThreshold = (Get-Date).AddHours(-1 * (1 + $MaxWindowDurationHours))
if (Test-Path -LiteralPath $LockFile) {
    $lastLock = Get-Item -LiteralPath $LockFile
    if ($lastLock.LastWriteTime -lt $lockStaleThreshold) {
        Write-Warning "Lock file estancado desde $($lastLock.LastWriteTime.ToString('s')); se continúa pero NO se superpondrá si realmente hay otro proceso abierto."
        if (Test-Path -LiteralPath $alertScript) {
            & $alertScript -Asunto "URBANBLADE BACKUP: lock estancado" -Mensaje "Lock $LockFile estancado desde $($lastLock.LastWriteTime.ToString('s')); revisa ejecuciones colgadas."
        }
    } else {
        throw "Lock file activo en $LockFile. Ejecución anterior en curso o abortada sin limpieza."
    }
}

New-Item -ItemType File -Force -Path $LockFile | Out-Null
try {
    # --- Ventana permitida 02:00–04:00
    if (-not (Test-InsideWindow) -and -not $Execute) {
        Write-Warning 'Fuera de la ventana 02:00–04:00 local. Si quieres ejecutar ahora, usa -Execute explícitamente.'
        exit 0
    }

    # --- Paso 1: generar respaldo cifrado + manifiesto (sintético por defecto)
    $drillSw = [System.Diagnostics.Stopwatch]::StartNew()
    $drillOutput = & $drillScript -KeepEncryptedArtifact 2>&1 | Out-String -Width 240
    $drillSw.Stop()

    if ($LASTEXITCODE -ne 0 -or $drillOutput -notmatch 'Result\s*:\s*passed') {
        throw "Invoke-SyntheticBackupDrill falló.`n$drillOutput"
    }

    $latestManifest = Get-ChildItem -LiteralPath $outputDir -Filter *.manifest.json | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $latestArchive  = Get-ChildItem -LiteralPath $outputDir -Filter *.archive.gz.ubenc | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $latestManifest -or -not $latestArchive) { throw 'No se localizaron artefactos después del drill.' }

    Write-Host "Drill OK ($(Invoke-StopwatchTook -Elapsed $drillSw.Elapsed) min): archive=$($latestArchive.Name) manifest=$($latestManifest.Name)"

    # --- Paso 2: publicador (primero dry-run obligatorio)
    $pubSw = [System.Diagnostics.Stopwatch]::StartNew()
    & $pubScript `
        -Bucket $Bucket `
        -Region $Region `
        -Profile $WriterProfile `
        -EncryptedArchive $latestArchive.FullName `
        -Manifest $latestManifest.FullName
    if ($LASTEXITCODE -ne 0) { throw 'Publish dry-run falló.' }
    $pubSw.Stop()
    Write-Host "Publish dry-run OK ($(Invoke-StopwatchTook -Elapsed $pubSw.Elapsed) s)."

    if ($Execute) {
        $pubSw.Restart()
        & $pubScript `
            -Bucket $Bucket `
            -Region $Region `
            -Profile $WriterProfile `
            -EncryptedArchive $latestArchive.FullName `
            -Manifest $latestManifest.FullName `
            -Execute `
            -Confirmation SUBIR-RESPALDO-CIFRADO-S3
        if ($LASTEXITCODE -ne 0) { throw 'Publish real falló.' }
        $pubSw.Stop()
        Write-Host "Publish REAL OK ($(Invoke-StopwatchTook -Elapsed $pubSw.Elapsed) s)."
    } else {
        Write-Warning 'Saltando publicación REAL (-Execute no suministrado). Dry-run pasó correctamente.'
    }

    # --- Paso 3: checks de antigüedad (salida para monitoreo externo)
    if (Test-Path -LiteralPath $latestManifest.FullName) {
        $ageHours = [int][Math]::Round(((Get-Date) - $latestManifest.LastWriteTime).TotalHours, 2)
        if ($ageHours -gt $AlertIfOlderHours) {
            Write-Warning "Último manifiesto con $ageHours h > umbral ${AlertIfOlderHours} h."
            if (Test-Path -LiteralPath $alertScript) {
                & $alertScript `
                    -Asunto "URBANBLADE BACKUP: antigüedad $ageHours h (umbral ${AlertIfOlderHours} h)" `
                    -Mensaje "Revisar Run-BackupDaily.ps1. Último manifiesto: $($latestManifest.FullName)"
            }
        } else {
            Write-Host "Antigüedad manifiesto: $ageHours h (OK <= ${AlertIfOlderHours} h)."
        }
    }

    Write-Host 'Run-BackupDaily: finalizado OK.'
} finally {
    # Toca lock file para registrar éxito/fallo (la presencia del archivo por sí sola NO indica ok:
    # si el script lanzó excepción, se limpia a continuación el lock ANTES de re-lanzar la excepción)
    if (Test-Path -LiteralPath $LockFile) {
        try { Set-Content -LiteralPath $LockFile -Value ((Get-Date).ToString('o')) -NoNewline } catch {}
    }

    # En una siguiente ejecución con fallo estancado, el timestamps no cambia → threshold > 3h lo detecta.
    # Si la ejecución es OK: dejamos el lock (quien la quiera borrar por limpieza la borra),
    # y la siguiente comprobación usa LastWriteTime de este mismo archivo.
    $now = Get-Date
    if ((Get-Item -LiteralPath $LockFile -ErrorAction SilentlyContinue).LastWriteTime -lt $lockStaleThreshold) {
        Remove-Item -LiteralPath $LockFile -Force -ErrorAction SilentlyContinue
    }
}
