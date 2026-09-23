[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$')]
    [string]$Bucket,

    [Parameter(Mandatory)][ValidatePattern('^[a-z]{2}(-gov)?-[a-z]+-\d$')]
    [string]$Region,

    [Parameter(Mandatory)][string]$Profile,
    [Parameter(Mandatory)][string]$EncryptedArchive,
    [Parameter(Mandatory)][string]$Manifest,

    [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9/_-]*$')]
    [string]$Prefix = 'urbanblade/barber-db',

    [switch]$Execute,
    [string]$Confirmation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-AwsCli {
    param([Parameter(Mandatory)][string[]]$Arguments)

    & aws @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "AWS CLI terminó con código $LASTEXITCODE."
    }
}

if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
    throw 'AWS CLI v2 no está disponible en PATH.'
}

if ($Bucket.Contains('://') -or $Bucket.Contains('/')) {
    throw 'Bucket debe contener solo el nombre, sin s3:// ni prefijos.'
}

$archivePath = (Resolve-Path -LiteralPath $EncryptedArchive).Path
$manifestPath = (Resolve-Path -LiteralPath $Manifest).Path

if ([IO.Path]::GetExtension($archivePath) -ne '.ubenc') {
    throw 'Solo se permite publicar un archivo cifrado con extensión .ubenc.'
}

$manifestData = Get-Content -Raw -LiteralPath $manifestPath | ConvertFrom-Json
$requiredFields = @(
    'run_id',
    'environment',
    'encrypted_file',
    'encrypted_sha256',
    'result'
)

foreach ($field in $requiredFields) {
    if (-not $manifestData.PSObject.Properties.Name.Contains($field)) {
        throw "El manifiesto no contiene el campo obligatorio: $field"
    }
}

if ($manifestData.result -ne 'passed') {
    throw 'El manifiesto no acredita un respaldo aprobado.'
}

if ($manifestData.encrypted_file -ne [IO.Path]::GetFileName($archivePath)) {
    throw 'El nombre del archivo cifrado no coincide con el manifiesto.'
}

$actualHash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash
if ($actualHash -ne $manifestData.encrypted_sha256) {
    throw 'El SHA-256 del archivo cifrado no coincide con el manifiesto.'
}

if ($Execute) {
    if ($Confirmation -ne 'SUBIR-RESPALDO-CIFRADO-S3') {
        throw 'La carga real requiere -Confirmation SUBIR-RESPALDO-CIFRADO-S3.'
    }

    if ($manifestData.environment -ne 'atlas-production') {
        throw 'La carga real solo acepta un manifiesto con environment atlas-production.'
    }
}

$runId = [string]$manifestData.run_id
if ($runId -notmatch '^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$') {
    throw 'El run_id del manifiesto no cumple el formato esperado.'
}

$year = $runId.Substring(0, 4)
$month = $runId.Substring(4, 2)
$normalizedPrefix = $Prefix.Trim('/')
$remoteBase = "s3://$Bucket/$normalizedPrefix/$year/$month/$runId"

$commonArguments = @(
    '--region', $Region,
    '--profile', $Profile,
    '--sse', 'AES256',
    '--checksum-algorithm', 'SHA256',
    '--no-progress',
    '--no-cli-pager'
)

if (-not $Execute) {
    $commonArguments += '--dryrun'
}

$archiveDestination = "$remoteBase/$([IO.Path]::GetFileName($archivePath))"
$manifestDestination = "$remoteBase/$([IO.Path]::GetFileName($manifestPath))"

Write-Host (if ($Execute) { 'Modo: CARGA REAL AUTORIZADA' } else { 'Modo: DRY-RUN, sin escritura en S3' })
Invoke-AwsCli -Arguments (@('s3', 'cp', $archivePath, $archiveDestination) + $commonArguments)
Invoke-AwsCli -Arguments (@('s3', 'cp', $manifestPath, $manifestDestination) + $commonArguments)

Write-Host "Destino validado: $remoteBase/"
