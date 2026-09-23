[CmdletBinding()]
param(
    [string]$Container = 'barber-mongo-test',
    [string]$OutputDirectory = 'storage/app/backup-drills',
    [SecureString]$Passphrase,
    [switch]$KeepEncryptedArtifact
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-Docker {
    param([Parameter(Mandatory)][string[]]$Arguments)

    & docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker terminó con código $LASTEXITCODE."
    }
}

function ConvertTo-PlainText {
    param([Parameter(Mandatory)][SecureString]$Value)

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Protect-Archive {
    param(
        [Parameter(Mandatory)][string]$InputPath,
        [Parameter(Mandatory)][string]$OutputPath,
        [Parameter(Mandatory)][SecureString]$Secret
    )

    $salt = [byte[]]::new(16)
    $nonce = [byte[]]::new(12)
    [Security.Cryptography.RandomNumberGenerator]::Fill($salt)
    [Security.Cryptography.RandomNumberGenerator]::Fill($nonce)

    $plainText = ConvertTo-PlainText -Value $Secret
    try {
        $kdf = [Security.Cryptography.Rfc2898DeriveBytes]::new(
            $plainText,
            $salt,
            210000,
            [Security.Cryptography.HashAlgorithmName]::SHA256
        )
        try {
            $key = $kdf.GetBytes(32)
        }
        finally {
            $kdf.Dispose()
        }

        $plainBytes = [IO.File]::ReadAllBytes($InputPath)
        $cipherBytes = [byte[]]::new($plainBytes.Length)
        $tag = [byte[]]::new(16)
        $aes = [Security.Cryptography.AesGcm]::new($key, 16)
        try {
            $aes.Encrypt($nonce, $plainBytes, $cipherBytes, $tag)
        }
        finally {
            $aes.Dispose()
            [Array]::Clear($key, 0, $key.Length)
            [Array]::Clear($plainBytes, 0, $plainBytes.Length)
        }

        $magic = [Text.Encoding]::ASCII.GetBytes('UBBK01')
        $stream = [IO.File]::Open($OutputPath, [IO.FileMode]::CreateNew)
        try {
            $stream.Write($magic)
            $stream.Write($salt)
            $stream.Write($nonce)
            $stream.Write($tag)
            $stream.Write($cipherBytes)
        }
        finally {
            $stream.Dispose()
            [Array]::Clear($cipherBytes, 0, $cipherBytes.Length)
        }
    }
    finally {
        $plainText = $null
    }
}

function Unprotect-Archive {
    param(
        [Parameter(Mandatory)][string]$InputPath,
        [Parameter(Mandatory)][string]$OutputPath,
        [Parameter(Mandatory)][SecureString]$Secret
    )

    $payload = [IO.File]::ReadAllBytes($InputPath)
    $magic = [Text.Encoding]::ASCII.GetString($payload, 0, 6)
    if ($magic -ne 'UBBK01') {
        throw 'El artefacto cifrado no tiene el formato UrbanBlade esperado.'
    }

    $salt = $payload[6..21]
    $nonce = $payload[22..33]
    $tag = $payload[34..49]
    $cipherBytes = $payload[50..($payload.Length - 1)]
    $plainBytes = [byte[]]::new($cipherBytes.Length)

    $plainText = ConvertTo-PlainText -Value $Secret
    try {
        $kdf = [Security.Cryptography.Rfc2898DeriveBytes]::new(
            $plainText,
            $salt,
            210000,
            [Security.Cryptography.HashAlgorithmName]::SHA256
        )
        try {
            $key = $kdf.GetBytes(32)
        }
        finally {
            $kdf.Dispose()
        }

        $aes = [Security.Cryptography.AesGcm]::new($key, 16)
        try {
            $aes.Decrypt($nonce, $cipherBytes, $tag, $plainBytes)
            [IO.File]::WriteAllBytes($OutputPath, $plainBytes)
        }
        finally {
            $aes.Dispose()
            [Array]::Clear($key, 0, $key.Length)
            [Array]::Clear($plainBytes, 0, $plainBytes.Length)
        }
    }
    finally {
        $plainText = $null
        [Array]::Clear($payload, 0, $payload.Length)
    }
}

if ($Container -ne 'barber-mongo-test') {
    throw 'Seguridad: este ensayo solo admite el contenedor barber-mongo-test.'
}

$runId = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + [Guid]::NewGuid().ToString('N').Substring(0, 8)
$sourceDatabase = "urbanblade_backup_drill_source_$runId"
$restoreDatabase = "urbanblade_backup_drill_restore_$runId"
$databasePattern = '^urbanblade_backup_drill_(source|restore)_[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$'

if ($sourceDatabase -notmatch $databasePattern -or $restoreDatabase -notmatch $databasePattern) {
    throw 'Seguridad: los nombres de las bases sintéticas no cumplen el patrón permitido.'
}

if (-not $Passphrase) {
    $Passphrase = Read-Host 'Frase de cifrado temporal para este ensayo' -AsSecureString
}

$expectedOutput = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..' 'storage/app/backup-drills'))
$resolvedOutput = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..' $OutputDirectory))
if ($resolvedOutput -ne $expectedOutput) {
    throw 'Seguridad: la salida solo puede escribirse en storage/app/backup-drills.'
}
[IO.Directory]::CreateDirectory($resolvedOutput) | Out-Null

$plainArchive = Join-Path $resolvedOutput "$runId.archive.gz"
$encryptedArchive = Join-Path $resolvedOutput "$runId.archive.gz.ubenc"
$restoredArchive = Join-Path $resolvedOutput "$runId.restore.archive.gz"
$manifestPath = Join-Path $resolvedOutput "$runId.manifest.json"
$containerArchive = "/tmp/$runId.archive.gz"
$containerRestoreArchive = "/tmp/$runId.restore.archive.gz"
$startedAt = [DateTime]::UtcNow
$completed = $false

$seedScript = @"
const dbName = '$sourceDatabase';
const target = db.getSiblingDB(dbName);
target.clients.insertMany([
  {_id: 1, name: 'Synthetic Client A', active: true},
  {_id: 2, name: 'Synthetic Client B', active: false}
]);
target.appointments.insertMany([
  {_id: 101, client_id: 1, status: 'scheduled'},
  {_id: 102, client_id: 2, status: 'completed'},
  {_id: 103, client_id: 1, status: 'cancelled'}
]);
target.clients.createIndex({name: 1}, {unique: true});
target.appointments.createIndex({client_id: 1, status: 1});
"@

$verifyScript = @"
const target = db.getSiblingDB('$restoreDatabase');
const result = {
  clients: target.clients.countDocuments({}),
  appointments: target.appointments.countDocuments({}),
  clientIndexes: target.clients.getIndexes().length,
  appointmentIndexes: target.appointments.getIndexes().length
};
print(JSON.stringify(result));
"@

try {
    $running = (& docker inspect --format '{{.State.Running}}' $Container 2>$null)
    if ($LASTEXITCODE -ne 0 -or $running -ne 'true') {
        throw 'barber-mongo-test no está disponible. Inícialo antes de ejecutar el ensayo.'
    }

    Invoke-Docker -Arguments @('exec', $Container, 'sh', '-lc', 'command -v mongosh && command -v mongodump && command -v mongorestore')
    Invoke-Docker -Arguments @('exec', $Container, 'mongosh', '--quiet', '--eval', $seedScript)
    Invoke-Docker -Arguments @(
        'exec', $Container, 'mongodump',
        '--db', $sourceDatabase,
        "--archive=$containerArchive",
        '--gzip'
    )
    Invoke-Docker -Arguments @('cp', "${Container}:${containerArchive}", $plainArchive)

    Protect-Archive -InputPath $plainArchive -OutputPath $encryptedArchive -Secret $Passphrase
    Remove-Item -LiteralPath $plainArchive -Force

    $encryptedHash = (Get-FileHash -LiteralPath $encryptedArchive -Algorithm SHA256).Hash
    Unprotect-Archive -InputPath $encryptedArchive -OutputPath $restoredArchive -Secret $Passphrase
    Invoke-Docker -Arguments @('cp', $restoredArchive, "${Container}:${containerRestoreArchive}")
    Remove-Item -LiteralPath $restoredArchive -Force

    Invoke-Docker -Arguments @(
        'exec', $Container, 'mongorestore',
        "--archive=$containerRestoreArchive",
        '--gzip',
        '--nsFrom', "$sourceDatabase.*",
        '--nsTo', "$restoreDatabase.*"
    )

    $verificationJson = (& docker exec $Container mongosh --quiet --eval $verifyScript | Select-Object -Last 1)
    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo verificar la restauración sintética.'
    }

    $verification = $verificationJson | ConvertFrom-Json
    if (
        $verification.clients -ne 2 -or
        $verification.appointments -ne 3 -or
        $verification.clientIndexes -ne 2 -or
        $verification.appointmentIndexes -ne 2
    ) {
        throw "La restauración no coincide con los conteos e índices esperados: $verificationJson"
    }

    $completed = $true
    $manifest = [ordered]@{
        schema_version = 1
        run_id = $runId
        environment = 'local-synthetic'
        container = $Container
        source_database = $sourceDatabase
        restore_database = $restoreDatabase
        started_at_utc = $startedAt.ToString('o')
        completed_at_utc = [DateTime]::UtcNow.ToString('o')
        encrypted_file = [IO.Path]::GetFileName($encryptedArchive)
        encrypted_size_bytes = (Get-Item -LiteralPath $encryptedArchive).Length
        encrypted_sha256 = $encryptedHash
        encryption = 'AES-256-GCM; PBKDF2-SHA256; 210000 iterations'
        verification = [ordered]@{
            clients = [int]$verification.clients
            appointments = [int]$verification.appointments
            client_indexes = [int]$verification.clientIndexes
            appointment_indexes = [int]$verification.appointmentIndexes
        }
        result = 'passed'
    }
    $manifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $manifestPath -Encoding utf8NoBOM

    Write-Host "Ensayo aprobado. Manifiesto: $manifestPath"
}
finally {
    foreach ($databaseName in @($sourceDatabase, $restoreDatabase)) {
        if ($databaseName -match $databasePattern) {
            $dropScript = "db.getSiblingDB('$databaseName').dropDatabase()"
            & docker exec $Container mongosh --quiet --eval $dropScript 2>$null | Out-Null
        }
    }

    & docker exec $Container rm -f -- $containerArchive $containerRestoreArchive 2>$null | Out-Null

    foreach ($temporaryPath in @($plainArchive, $restoredArchive)) {
        if (Test-Path -LiteralPath $temporaryPath) {
            Remove-Item -LiteralPath $temporaryPath -Force
        }
    }

    if (-not $completed -or -not $KeepEncryptedArtifact) {
        if (Test-Path -LiteralPath $encryptedArchive) {
            Remove-Item -LiteralPath $encryptedArchive -Force
        }
    }
}
