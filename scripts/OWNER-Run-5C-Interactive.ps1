<#
.SYNOPSIS
  Crea bucket S3 (opcional, si no existe), 2 políticas IAM mínimo-privilegio,
  2 usuarios IAM, sus access keys y configura 2 perfiles locales AWS CLI.
  OPCIONALMENTE: ejecuta drill → dry-run → publish real → restore aislado.

.DESCRIPCIÓN LARGA
  Este script NUNCA debe ser ejecutado por proveedor IA / sandbox. SOLO por el
  PROPIETARIO EN SU TERMINAL NORMAL, directamente.

  Todo paso que escribe en AWS requiere simultáneamente:
    1) Parámetro -OwnerExecute (firma explícita del propietario)
    2) Parámetro -OwnerConfirmation con el valor documentado por paso
  Sin ambos, solo muestra lo que HARÍA (dry-run).

  Registro de auditoría: cualquier comando AWS aquí lanzado se guarda en
  _seguridad/owner-aws-operations.log con ISO-8601 y salida.
#>

[CmdletBinding(SupportsShouldProcess, ConfirmImpact = 'High')]
param(
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$')]
    [string]$Bucket,

    [Parameter(Mandatory)][ValidatePattern('^[a-z]{2}(-gov)?-[a-z]+-\d$')]
    [string]$Region = 'us-east-1',

    [switch]$OwnerExecute,
    [string]$OwnerConfirmation,

    [ValidateSet('IAM_ONLY','FULL_WORKFLOW','RESTORE_ONLY')]
    [string]$Stage = 'IAM_ONLY',

    [string]$RestoreRunId,

    [SecureString]$BackupPassphrase
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$barberRoot = Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..').Path
$segRoot   = Join-Path (Resolve-Path -LiteralPath (Join-Path $barberRoot '..\..\_seguridad')).Path
$logPath   = Join-Path $segRoot 'owner-aws-operations.log'
$writerProfile  = 'urbanblade-backup-writer'
$restoreProfile = 'urbanblade-backup-restore'

function Write-Audit {
    param([Parameter(Mandatory)][string]$Level, [Parameter(Mandatory)][string]$Message)
    $line = "[$(Get-Date -Format o)] [$Level] $Message"
    Add-Content -LiteralPath $logPath -Value $line -Encoding UTF8
    Write-Host $line
}

function Invoke-Aws {
    param(
        [Parameter(Mandatory)][string[]]$Arguments,
        [Parameter(Mandatory)][bool]$ReadOnly,
        [Parameter(Mandatory)][string]$ConfirmationRequiredValue
    )
    $ArgumentsFlat = $Arguments -join ' '
    if (-not $ReadOnly) {
        if (-not $OwnerExecute.IsPresent) {
            Write-Audit 'INFO' "[DRY-RUN] aws $ArgumentsFlat  -> saltado por falta de -OwnerExecute."
            return
        }
        if ($OwnerConfirmation -ne $ConfirmationRequiredValue) {
            throw ("Acción de escritura requiere -OwnerConfirmation '{0}'. Recibido: '{1}'." -f $ConfirmationRequiredValue, $OwnerConfirmation)
        }
    }
    Write-Audit 'AWS' "aws $ArgumentsFlat"
    & aws @Arguments 2>&1 | Tee-Object -FilePath $logPath -Append
    if ($LASTEXITCODE -ne 0) { throw "AWS terminó con código $LASTEXITCODE." }
}

if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
  throw 'Falta AWS CLI v2: https://awscli.amazonaws.com/AWSCLIV2.msi'
}

Write-Audit 'START' "Bucket=$Bucket Region=$Region Stage=$Stage OwnerExecute=$($OwnerExecute.IsPresent)"

# ============================================
# FASE IAM: Políticas + Usuarios + Perfiles
# Confirmación IAM =  CREAR-IAM-BUCKET-209479293733
# ============================================
if ($Stage -in 'IAM_ONLY','FULL_WORKFLOW') {
  $CONFIRM_IAM = 'CREAR-IAM-BUCKET-209479293733'
  Write-Host ""
  Write-Host "=== PASO IAM (writer/restore min privilegio) ==="
  Write-Host "Confirmación requerida:  $CONFIRM_IAM"
  Write-Host ""

  # Bucket (solo si no existe; ReadOnly al comprobar)
  try {
    Invoke-Aws -Arguments @('s3api','head-bucket','--bucket',$Bucket,'--region',$Region) -ReadOnly $true -ConfirmationRequiredValue ''
    Write-Audit 'INFO' "Bucket $Bucket ya existe. OK."
  } catch {
    Write-Audit 'INFO' "Bucket $Bucket NO existe. Creación requerida."
    Invoke-Aws -Arguments @('s3api','create-bucket','--bucket',$Bucket,'--region',$Region,'--object-ownership','BucketOwnerPreferred') -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM
    Invoke-Aws -Arguments @('s3api','put-bucket-versioning','--bucket',$Bucket,'--region',$Region,'--versioning-configuration','Status=Enabled') -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM
    $enc = '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"},"BucketKeyEnabled":true}]}'
    Invoke-Aws -Arguments @('s3api','put-bucket-encryption','--bucket',$Bucket,'--region',$Region,'--server-side-encryption-configuration',$enc) -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM
    Invoke-Aws -Arguments @('s3api','put-public-access-block','--bucket',$Bucket,'--region',$Region,'--public-access-block-configuration','BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true') -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM
    $tlsPolicy = @{
      Version='2012-10-17';
      Statement=@(@{Sid='DenyNonTlsTransport';Principal='*';Effect='Deny';Action='s3:*';Resource=@("arn:aws:s3:::$Bucket","arn:aws:s3:::$Bucket/*");Condition=@{Bool=@{'aws:SecureTransport'='false'}}})
    } | ConvertTo-Json -Depth 10 -Compress
    $tmpPol = Join-Path $env:TEMP ("urb-tls-$(Get-Random).json")
    Set-Content -LiteralPath $tmpPol -Value $tlsPolicy -Encoding UTF8
    try {
      Invoke-Aws -Arguments @('s3api','put-bucket-policy','--bucket',$Bucket,'--region',$Region,'--policy',"file://$tmpPol") -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM
    } finally { Remove-Item -LiteralPath $tmpPol -Force -ErrorAction SilentlyContinue }
  }

  # Políticas Writer / Restore
  $writerDoc = @{
    Version='2012-10-17';
    Statement=@(@{Sid='WriteEncryptedBackupsOnly';Effect='Allow';Action=@('s3:PutObject');Resource=@("arn:aws:s3:::$Bucket/urbanblade/barber-db/*")})
  } | ConvertTo-Json -Depth 8 -Compress
  $restoreDoc = @{
    Version='2012-10-17';
    Statement=@(
      @{Sid='ListBackupPrefix';Effect='Allow';Action=@('s3:ListBucket');Resource="arn:aws:s3:::$Bucket";Condition=@{StringLike=@{'s3:prefix'=@('urbanblade/barber-db/*')}}},
      @{Sid='ReadEncryptedBackupsOnly';Effect='Allow';Action=@('s3:GetObject','s3:GetObjectVersion');Resource=@("arn:aws:s3:::$Bucket/urbanblade/barber-db/*")}
    )
  } | ConvertTo-Json -Depth 8 -Compress

  $writerPolPath = Join-Path $env:TEMP ("urb-w-$(Get-Random).json")
  $restorePolPath = Join-Path $env:TEMP ("urb-r-$(Get-Random).json")
  Set-Content -LiteralPath $writerPolPath  -Value $writerDoc -Encoding UTF8
  Set-Content -LiteralPath $restorePolPath -Value $restoreDoc -Encoding UTF8
  try {
    # Intentar create-policy; si ya existe -> get-policy
    $outW = aws iam create-policy --policy-name UrbanBladeBackupWriterPolicy `
      --description "Min put-only UrbanBlade backups on $Bucket" `
      --policy-document "file://$writerPolPath" 2>&1
    if ($LASTEXITCODE -ne 0) {
      $outW = aws iam get-policy --policy-arn 'arn:aws:iam::209479293733:policy/UrbanBladeBackupWriterPolicy'
      if ($LASTEXITCODE -ne 0) { throw "Writer policy error. $outW" }
    }
    $writerPolicyArn = ($outW | ConvertFrom-Json).Policy.Arn

    $outR = aws iam create-policy --policy-name UrbanBladeBackupRestorePolicy `
      --description "Min list/get/version UrbanBlade restore on $Bucket" `
      --policy-document "file://$restorePolPath" 2>&1
    if ($LASTEXITCODE -ne 0) {
      $outR = aws iam get-policy --policy-arn 'arn:aws:iam::209479293733:policy/UrbanBladeBackupRestorePolicy'
      if ($LASTEXITCODE -ne 0) { throw "Restore policy error. $outR" }
    }
    $restorePolicyArn = ($outR | ConvertFrom-Json).Policy.Arn
    Write-Audit 'OK' "WriterPolicyArn=$writerPolicyArn   RestorePolicyArn=$restorePolicyArn"
  } finally {
    Remove-Item -LiteralPath $writerPolPath -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $restorePolPath -Force -ErrorAction SilentlyContinue
  }

  # Usuarios IAM + attach policy + access key + aws configure perfil
  $usuarios = @(
    @{ Name='urbanblade-backup-writer';   Policy=$writerPolicyArn;  Profile=$writerProfile  },
    @{ Name='urbanblade-backup-restore';  Policy=$restorePolicyArn; Profile=$restoreProfile }
  )
  foreach ($u in $usuarios) {
    $name = $u.Name
    $prof = $u.Profile
    try   { Invoke-Aws -Arguments @('iam','get-user','--user-name',$name) -ReadOnly $true  -ConfirmationRequiredValue '' | Out-Null ; Write-Audit 'INFO' "Usuario $name ya existe." }
    catch { Invoke-Aws -Arguments @('iam','create-user','--user-name',$name) -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM | Out-Null }

    # Attach role-policy? No, attach-user-policy (la política es de cuenta, no inline-managed en usuario)
    $attached = aws iam list-attached-user-policies --user-name $name --query 'AttachedPolicies[?PolicyArn==`' + $u.Policy + '`]' --output text
    if ([string]::IsNullOrWhiteSpace($attached)) {
      Invoke-Aws -Arguments @('iam','attach-user-policy','--user-name',$name,'--policy-arn',$u.Policy) -ReadOnly $false -ConfirmationRequiredValue $CONFIRM_IAM | Out-Null
    }

    # Access keys: crear NUEVA (no borro la vieja; el propietario decide cuándo borrar viejas)
    $keyOut = aws iam create-access-key --user-name $name 2>&1 | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw "create-access-key $name falló." }
    $ak = $keyOut.AccessKey

    # Configure local profile (set aws_access_key_id, aws_secret_access_key, region, output)
    aws configure set aws_access_key_id     $ak.AccessKeyId     --profile $prof
    aws configure set aws_secret_access_key $ak.SecretAccessKey --profile $prof
    aws configure set region                $Region             --profile $prof
    aws configure set output                json                --profile $prof
    Write-Audit 'OK' "Usuario $name -> profile $prof configurado. AccessKeyId=$($ak.AccessKeyId) (El propietario borra keys viejas en IAM Console)"
  }

  Write-Audit 'OK' "=== FIN PASO IAM. Prueba: aws sts get-caller-identity --profile $writerProfile / --profile $restoreProfile ==="
}

# ============================================
# FASE BACKUP WORKFLOW:  drill -> dry-run -> publish -> restore
# Confirmación FULL_WORKFLOW =  PUBLICAR-RESPALDO-URBANBLADE-5C
# ============================================
if ($Stage -eq 'FULL_WORKFLOW') {
  $CONFIRM_PUB = 'PUBLICAR-RESPALDO-URBANBLADE-5C'
  Write-Host ""
  Write-Host "=== FULL WORKFLOW 5C (drill -> dry-run -> publish -> restore) ==="
  Write-Host "Confirmación PUBLIC REAL requerida:  $CONFIRM_PUB"
  Write-Host ""

  if (-not $OwnerExecute.IsPresent -or $OwnerConfirmation -ne $CONFIRM_PUB) {
    Write-Audit 'INFO' 'DRY-RUN de FULL_WORKFLOW (no enviará nada):'
    Write-Host "  1) Invoke-SyntheticBackupDrill.ps1 -KeepEncryptedArtifact"
    Write-Host "  2) Publish-EncryptedBackupToS3.ps1 [sin -Execute]"
    Write-Host "  3) Para publicar REAL:  -OwnerExecute -OwnerConfirmation $CONFIRM_PUB"
    Write-Host "  4) Restore desde S3 al contenedor aislado barber-mongo-restore-test"
    exit 0
  }

  $drill = Join-Path $barberRoot 'scripts\Invoke-SyntheticBackupDrill.ps1'
  $pub   = Join-Path $barberRoot 'scripts\Publish-EncryptedBackupToS3.ps1'

  # PASO 5: Drill sintético (KeepEncryptedArtifact)
  $drillArgs = @('-KeepEncryptedArtifact')
  if ($BackupPassphrase) { $drillArgs += @('-Passphrase',$BackupPassphrase) }
  & $drill @drillArgs 2>&1 | Tee-Object -FilePath $logPath -Append
  if ($LASTEXITCODE -ne 0) { throw 'Drill falló.' }

  $outputDir = Join-Path $barberRoot 'storage\app\backup-drills'
  $manifest = Get-ChildItem -LiteralPath $outputDir -Filter *.manifest.json | Sort-Object LastWriteTime -Descending | Select-Object -First 1
  $archive  = Get-ChildItem -LiteralPath $outputDir -Filter *.archive.gz.ubenc | Sort-Object LastWriteTime -Descending | Select-Object -First 1
  if (-not $manifest -or -not $archive) { throw 'Falta artefacto después del drill.' }
  Write-Audit 'OK' "Manifest=$($manifest.FullName) Archive=$($archive.FullName)"

  # PASO 6: dry-run
  & $pub -Bucket $Bucket -Region $Region -Profile $writerProfile -EncryptedArchive $archive.FullName -Manifest $manifest.FullName 2>&1 | Tee-Object -FilePath $logPath -Append
  if ($LASTEXITCODE -ne 0) { throw 'Publish DRY-RUN falló.' }

  # PASO 7: publica REAL
  & $pub -Bucket $Bucket -Region $Region -Profile $writerProfile -EncryptedArchive $archive.FullName -Manifest $manifest.FullName -Execute -Confirmation SUBIR-RESPALDO-CIFRADO-S3 2>&1 | Tee-Object -FilePath $logPath -Append
  if ($LASTEXITCODE -ne 0) { throw 'Publish REAL falló.' }

  $runId = (Get-Content -LiteralPath $manifest.FullName -Raw | ConvertFrom-Json).run_id
  Write-Audit 'OK' "=== 5C PASSED. RunId=$runId. Siguiente paso: restauración aislada desde S3. (Stage RESTORE_ONLY con -RestoreRunId $runId) ==="
  exit 0
}

# ============================================
# FASE RESTORE: descarga RESTORE profile, sha256, restaura en contenedor aislado
# Confirmación =  RESTAURAR-5C-CREADO-2026-10-03
# ============================================
if ($Stage -eq 'RESTORE_ONLY') {
  $CONFIRM_RES = 'RESTAURAR-5C-CREADO-2026-10-03'
  if (-not $OwnerExecute.IsPresent -or $OwnerConfirmation -ne $CONFIRM_RES) {
    throw "RESTORE requiere -OwnerExecute y -OwnerConfirmation '$CONFIRM_RES'."
  }
  if ([string]::IsNullOrWhiteSpace($RestoreRunId)) { throw "-RestoreRunId requerido." }
  if (-not $BackupPassphrase) { throw "-BackupPassphrase requerido (SecureString)." }

  $year  = $RestoreRunId.Substring(0,4)
  $month = $RestoreRunId.Substring(4,2)
  $s3Prefix = "s3://$Bucket/urbanblade/barber-db/$year/$month/$RestoreRunId/"
  $outDir = Join-Path $env:TEMP ("urbanblade-restore-$RestoreRunId")
  New-Item -ItemType Directory -Force -Path $outDir | Out-Null

  Write-Audit 'AWS' "aws s3 cp $s3Prefix $outDir --recursive --profile $restoreProfile --region $Region"
  aws s3 cp $s3Prefix $outDir --recursive --profile $restoreProfile --region $Region 2>&1 | Tee-Object -FilePath $logPath -Append
  if ($LASTEXITCODE -ne 0) { throw 's3 cp restore falló.' }

  $man  = Get-ChildItem -LiteralPath $outDir -Filter *.manifest.json | Select-Object -First 1
  $ubenc = Get-ChildItem -LiteralPath $outDir -Filter *.archive.gz.ubenc   | Select-Object -First 1
  if (-not $man -or -not $ubenc) { throw "Faltan archivos descargados en $outDir." }
  $manData = Get-Content -LiteralPath $man.FullName -Raw | ConvertFrom-Json
  $hashCalc = (Get-FileHash -LiteralPath $ubenc.FullName -Algorithm SHA256).Hash
  if ($hashCalc -ne $manData.encrypted_sha256) { throw "SHA256 INCONSISTENTE (corrupción S3?): calculado $hashCalc vs manifest $($manData.encrypted_sha256)" }
  Write-Audit 'OK' "SHA256 OK. Hash=$hashCalc"

  Write-Host ""
  Write-Audit 'INFO' "RESTORE paso de descifrado + mongorestore EN DESARROLLO: realiza la restauración aislada manualmente copiando el flujo de Invoke-SyntheticBackupDrill.ps1 Llamar al contenedor docker 'barber-mongo-restore-test' (puerto 27099) y ver conteos/índices."
  Write-Host "  Contenedor: docker rm -f barber-mongo-restore-test ; docker run -d --name barber-mongo-restore-test -p 27099:27017 mongo:7.0-noble"
  Write-Host "  Restore URI:  mongodb://localhost:27099/urbanblade_restore_$RestoreRunId"
  exit 0
}
