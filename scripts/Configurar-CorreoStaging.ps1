<#
.SYNOPSIS
  Configura el correo saliente de staging con Gmail SMTP (cuenta + contraseña de aplicación).

.DESCRIPTION
  Staging no tenia ninguna variable MAIL_*: Laravel usaba el mailer "log" y los correos (bienvenida,
  olvido de contrasena, comprobantes, facturas...) solo se escribian en el log. Este script:
    1. Lee la revision mas reciente de la task definition urbanblade-staging-barber.
    2. Le agrega las variables MAIL_* (no secretas) y el secreto MAIL_PASSWORD.
    3. (solo con -Aplicar) guarda la contrasena en Secrets Manager, registra una revision nueva,
       actualiza el servicio uba-stg-barber y espera a que se estabilice.

  SIN -Aplicar es una simulacion: solo lee de AWS y muestra lo que cambiaria. No escribe nada.
  La contrasena se pide por pantalla (no se imprime ni se guarda en disco mas alla de un archivo
  temporal que se borra). Ejecutalo con un perfil de AWS que pueda escribir en Secrets Manager y
  registrar task definitions; el usuario "staging-deploy" NO puede.

  Requisitos de la cuenta de Gmail: verificacion en dos pasos activa y una "contrasena de
  aplicacion" de 16 caracteres (myaccount.google.com/apppasswords). Gmail reescribe el remitente
  a la cuenta autenticada, por eso el remitente es la misma cuenta.

.EXAMPLE
  .\scripts\Configurar-CorreoStaging.ps1 -Cuenta barberia@gmail.com -Profile admin            # simulacion
  .\scripts\Configurar-CorreoStaging.ps1 -Cuenta barberia@gmail.com -Profile admin -Aplicar   # aplica

.NOTES
  Reversion: el script imprime la revision anterior; para volver, actualiza el servicio a esa
  revision con `aws ecs update-service --task-definition <arn anterior> --force-new-deployment`.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Cuenta,
    [string]$Profile,
    [switch]$Aplicar
)

$ErrorActionPreference = 'Stop'
$region = 'us-east-1'
$cluster = 'urbanblade-staging'
$servicio = 'uba-stg-barber'
$familia = 'urbanblade-staging-barber'
$secretoNombre = 'urbanblade/staging/barber/MAIL_PASSWORD'

function Invoke-Aws {
    param([Parameter(Mandatory)][string[]]$Argumentos)
    $extra = @('--region', $region, '--cli-connect-timeout', '20')
    if ($Profile) { $extra += @('--profile', $Profile) }
    $salida = & aws @Argumentos @extra
    if ($LASTEXITCODE -ne 0) { throw "aws fallo: $($Argumentos[0..1] -join ' ')" }
    return $salida
}

if ($Cuenta -notmatch '^[^@\s]+@gmail\.com$') {
    throw "La cuenta debe ser una direccion @gmail.com (recibido: $Cuenta)."
}

$identidad = ((Invoke-Aws @('sts', 'get-caller-identity', '--query', 'Arn', '--output', 'text')) -join '').Trim()
Write-Host "Identidad de AWS en uso: $identidad"
if ($Aplicar -and $identidad -match 'user/staging-deploy') {
    # Puede leer Secrets Manager, pero crear el secreto y registrar la task definition suele exigir mas permisos.
    # Si AWS los niega, el script se detiene con un mensaje claro y no deja nada a medias.
    Write-Warning 'Estas con el usuario de despliegue (staging-deploy). Si AWS niega crear el secreto o registrar la task definition, usa -Profile con una cuenta de administrador o la consola web de AWS.'
}

Write-Host "Leyendo la task definition actual de $familia ..."
$td = (Invoke-Aws @('ecs', 'describe-task-definition', '--task-definition', $familia, '--query', 'taskDefinition', '--output', 'json')) -join "`n" | ConvertFrom-Json
Write-Host "Revision actual: $($td.taskDefinitionArn)"

$contenedor = $td.containerDefinitions[0]
$variablesMail = [ordered]@{
    MAIL_MAILER       = 'smtp'
    MAIL_HOST         = 'smtp.gmail.com'
    MAIL_PORT         = '587'
    MAIL_USERNAME     = $Cuenta
    MAIL_FROM_ADDRESS = $Cuenta
    MAIL_FROM_NAME    = 'UrbanBlade'
}

# Sin MAIL_SCHEME a proposito: en el puerto 587 Symfony Mailer negocia STARTTLS solo.
# (MAIL_SCHEME=tls no es un esquema valido y rompe el envio.)
$entorno = @($contenedor.environment | Where-Object { $variablesMail.Keys -notcontains $_.name })
foreach ($nombre in $variablesMail.Keys) { $entorno += [pscustomobject]@{ name = $nombre; value = $variablesMail[$nombre] } }

if ($Aplicar) {
    $existe = $true
    try { Invoke-Aws @('secretsmanager', 'describe-secret', '--secret-id', $secretoNombre, '--query', 'ARN', '--output', 'text') | Out-Null } catch { $existe = $false }

    $segura = Read-Host -Prompt "Contrasena de aplicacion de Gmail para $Cuenta (16 caracteres)" -AsSecureString
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
    try { $clave = ([Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)) -replace '\s', '' } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
    if ($clave.Length -ne 16) { throw 'Una contrasena de aplicacion de Gmail tiene 16 caracteres (sin espacios). Revisa que no sea la contrasena normal de la cuenta.' }

    $temporal = [IO.Path]::GetTempFileName()
    try {
        Set-Content -Path $temporal -Value $clave -NoNewline -Encoding ascii
        try {
            if ($existe) {
                Invoke-Aws @('secretsmanager', 'put-secret-value', '--secret-id', $secretoNombre, '--secret-string', "file://$temporal") | Out-Null
            } else {
                Invoke-Aws @('secretsmanager', 'create-secret', '--name', $secretoNombre, '--description', 'Contrasena de aplicacion de Gmail para el correo saliente de barber (staging)', '--secret-string', "file://$temporal") | Out-Null
            }
        } catch {
            throw "No se pudo guardar el secreto (revisa el error de AWS de arriba; si dice AccessDenied, esta identidad no puede escribir en Secrets Manager). No se cambio nada mas. Detalle: $($_.Exception.Message)"
        }
    } finally {
        Remove-Item -Path $temporal -Force -ErrorAction SilentlyContinue
        $clave = $null
    }
    $arnSecreto = (Invoke-Aws @('secretsmanager', 'describe-secret', '--secret-id', $secretoNombre, '--query', 'ARN', '--output', 'text')).Trim()
    Write-Host 'Secreto MAIL_PASSWORD guardado en Secrets Manager.'
} else {
    $arnSecreto = "arn:aws:secretsmanager:${region}:<cuenta>:secret:${secretoNombre}-XXXXXX   (simulacion)"
}

$secretos = @($contenedor.secrets | Where-Object { $_.name -ne 'MAIL_PASSWORD' })
$secretos += [pscustomobject]@{ name = 'MAIL_PASSWORD'; valueFrom = $arnSecreto }

$contenedor.environment = $entorno
$contenedor.secrets = $secretos

# Solo los campos que register-task-definition acepta (se descartan revision, estado, ARN...).
$permitidos = 'family', 'taskRoleArn', 'executionRoleArn', 'networkMode', 'containerDefinitions', 'volumes', 'placementConstraints', 'requiresCompatibilities', 'cpu', 'memory', 'runtimePlatform', 'ephemeralStorage', 'pidMode', 'ipcMode', 'proxyConfiguration', 'inferenceAccelerators'
$nueva = [ordered]@{}
foreach ($campo in $permitidos) { if ($null -ne $td.$campo) { $nueva[$campo] = $td.$campo } }

Write-Host ''
Write-Host 'Cambios en la task definition:'
foreach ($nombre in $variablesMail.Keys) { Write-Host ("  + {0} = {1}" -f $nombre, $variablesMail[$nombre]) }
Write-Host "  + MAIL_PASSWORD (secreto) <- $secretoNombre"

if (-not $Aplicar) {
    Write-Host ''
    Write-Host 'SIMULACION: no se escribio nada en AWS. Para aplicar, vuelve a correr con -Aplicar.'
    return
}

$archivoTd = [IO.Path]::GetTempFileName()
try {
    # UTF-8 sin BOM: en Windows PowerShell 5.1 "-Encoding utf8" agrega un BOM y AWS CLI rechaza el JSON.
    [IO.File]::WriteAllText($archivoTd, ($nueva | ConvertTo-Json -Depth 30), (New-Object Text.UTF8Encoding $false))
    $arnNuevo = (Invoke-Aws @('ecs', 'register-task-definition', '--cli-input-json', "file://$archivoTd", '--query', 'taskDefinition.taskDefinitionArn', '--output', 'text')).Trim()
} finally {
    Remove-Item -Path $archivoTd -Force -ErrorAction SilentlyContinue
}
Write-Host "Nueva revision registrada: $arnNuevo"
Write-Host "Revision anterior (para revertir): $($td.taskDefinitionArn)"

Invoke-Aws @('ecs', 'update-service', '--cluster', $cluster, '--service', $servicio, '--task-definition', $arnNuevo, '--force-new-deployment', '--query', 'service.deployments[0].rolloutState', '--output', 'text') | Out-Null
Write-Host 'Esperando a que el servicio se estabilice (hasta ~10 min)...'
Invoke-Aws @('ecs', 'wait', 'services-stable', '--cluster', $cluster, '--services', $servicio) | Out-Null
Write-Host 'Listo. Prueba: crea una cuenta o usa "Olvide mi contrasena" con un correo real y revisa la bandeja y spam.'
Write-Host 'Si la tarea no arranca por permisos, agrega secretsmanager:GetSecretValue sobre el secreto al rol ecsTaskExecutionRole.'
