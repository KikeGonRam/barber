<#
.SYNOPSIS
  Hace que las tareas de barber en staging compartan el contador de limites y el mutex del scheduler.

.DESCRIPTION
  Con mas de una tarea de uba-stg-barber y RUN_SCHEDULER=true, cada tarea corre schedule:work: los
  recordatorios y demas tareas programadas se ejecutan por duplicado, y los limites de intentos
  (throttle) se cuentan por tarea. Este script:
    1. Lee la revision mas reciente de la task definition urbanblade-staging-barber.
    2. Le agrega CACHE_LIMITER_STORE=mongodb y SCHEDULE_CACHE_STORE=mongodb (no secretas).
    3. (solo con -Aplicar) registra una revision nueva, actualiza el servicio y espera a que se estabilice.

  SIN -Aplicar es una simulacion: solo lee de AWS y muestra lo que cambiaria. No escribe nada.

  ORDEN: SCHEDULE_CACHE_STORE solo tiene efecto con una imagen que incluya routes/console.php con
  onOneServer() y config('cache.schedule_store'). Despliega primero esa imagen (ver
  docs/referencia/DESPLIEGUE_AWS_STAGING.md) y despues corre este script. Con la imagen vieja las variables
  se ignoran sin dano.

  Ejecutalo con un perfil que pueda registrar task definitions (ecs:RegisterTaskDefinition); el usuario
  "staging-deploy" no puede, ver la guia de despliegue.

.EXAMPLE
  .\scripts\Compartir-EstadoTareasStaging.ps1 -Profile admin            # simulacion
  .\scripts\Compartir-EstadoTareasStaging.ps1 -Profile admin -Aplicar   # aplica

.NOTES
  Reversion: el script imprime la revision anterior; para volver, actualiza el servicio a esa
  revision con `aws ecs update-service --task-definition <arn anterior> --force-new-deployment`.
  Comprobacion: en los logs de /ecs/urbanblade-staging/barber cada tarea programada debe aparecer
  en una sola tarea por ejecucion (no en las dos).
#>
[CmdletBinding()]
param(
    [string]$Profile,
    [switch]$Aplicar
)

$ErrorActionPreference = 'Stop'
$region = 'us-east-1'
$cluster = 'urbanblade-staging'
$servicio = 'uba-stg-barber'
$familia = 'urbanblade-staging-barber'

function Invoke-Aws {
    param([Parameter(Mandatory)][string[]]$Argumentos)
    $extra = @('--region', $region, '--cli-connect-timeout', '20')
    if ($Profile) { $extra += @('--profile', $Profile) }
    $salida = & aws @Argumentos @extra
    if ($LASTEXITCODE -ne 0) { throw "aws fallo: $($Argumentos[0..1] -join ' ')" }
    return $salida
}

$identidad = ((Invoke-Aws @('sts', 'get-caller-identity', '--query', 'Arn', '--output', 'text')) -join '').Trim()
Write-Host "Identidad de AWS en uso: $identidad"

Write-Host "Leyendo la task definition actual de $familia ..."
$td = (Invoke-Aws @('ecs', 'describe-task-definition', '--task-definition', $familia, '--query', 'taskDefinition', '--output', 'json')) -join "`n" | ConvertFrom-Json
Write-Host "Revision actual: $($td.taskDefinitionArn)"

$contenedor = $td.containerDefinitions[0]
$variables = [ordered]@{
    CACHE_LIMITER_STORE  = 'mongodb'
    SCHEDULE_CACHE_STORE = 'mongodb'
}

$entorno = @($contenedor.environment | Where-Object { $variables.Keys -notcontains $_.name })
foreach ($nombre in $variables.Keys) { $entorno += [pscustomobject]@{ name = $nombre; value = $variables[$nombre] } }
$contenedor.environment = $entorno

# Solo los campos que register-task-definition acepta (se descartan revision, estado, ARN...).
$permitidos = 'family', 'taskRoleArn', 'executionRoleArn', 'networkMode', 'containerDefinitions', 'volumes', 'placementConstraints', 'requiresCompatibilities', 'cpu', 'memory', 'runtimePlatform', 'ephemeralStorage', 'pidMode', 'ipcMode', 'proxyConfiguration', 'inferenceAccelerators'
$nueva = [ordered]@{}
foreach ($campo in $permitidos) { if ($null -ne $td.$campo) { $nueva[$campo] = $td.$campo } }

Write-Host ''
Write-Host 'Cambios en la task definition:'
foreach ($nombre in $variables.Keys) { Write-Host ("  + {0} = {1}" -f $nombre, $variables[$nombre]) }

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
Write-Host 'Listo. Revisa los logs de barber: cada tarea programada debe ejecutarse en una sola tarea.'
