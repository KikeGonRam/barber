[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Asunto,
    [Parameter(Mandatory)][string]$Mensaje,

    [ValidateSet('Gmail','Office365','AwsSes')]
    [string]$Proveedor = 'AwsSes',

    [string]$Destinatario,
    [string]$Remitente,

    [string]$AwsSesRegion = 'us-east-1',
    [string]$AwsSesProfile = 'urbanblade-backup-writer'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$credencialesDir = Join-Path $PSScriptRoot '..\..\_seguridad'
$confPath        = Join-Path $credencialesDir 'alertas-email.conf.json'

function Resolve-Config {
    param([Parameter(Mandatory)][string]$Campo, [string]$ValorParam)
    if ($ValorParam) { return $ValorParam }
    if (Test-Path -LiteralPath $confPath) {
        $c = Get-Content -LiteralPath $confPath -Raw | ConvertFrom-Json
        if ($c.PSObject.Properties.Name.Contains($Campo)) { return $c.$Campo }
    }
    throw "Falta configuración: $Campo. Pásalo por parámetro o define el archivo $confPath con: `{ `"Destinatario`": `"...`", `"Remitente`": `"...`", `"GmailAppPassword`": `"...`", `"Office365Password`": `"...`" }"
}

$DestinatarioResuelto = Resolve-Config -Campo 'Destinatario' -ValorParam $Destinatario
$RemitenteResuelto    = Resolve-Config -Campo 'Remitente'    -ValorParam $Remitente

Write-Warning "ALERTA [$Proveedor] -> $DestinatarioResuelto | $Asunto"
Write-Warning "Mensaje: $Mensaje"

switch ($Proveedor) {
    'Gmail' {
        $pwd = Resolve-Config -Campo 'GmailAppPassword' -ValorParam ''
        $sec = ConvertTo-SecureString -String $pwd -AsPlainText -Force
        $cred = [Management.Automation.PSCredential]::new($RemitenteResuelto, $sec)
        Send-MailMessage `
            -From $RemitenteResuelto `
            -To $DestinatarioResuelto `
            -Subject "[UrbanBlade Backup] $Asunto" `
            -Body $Mensaje `
            -SmtpServer 'smtp.gmail.com' `
            -Port 587 `
            -UseSsl `
            -Credential $cred
    }

    'Office365' {
        $pwd = Resolve-Config -Campo 'Office365Password' -ValorParam ''
        $sec = ConvertTo-SecureString -String $pwd -AsPlainText -Force
        $cred = [Management.Automation.PSCredential]::new($RemitenteResuelto, $sec)
        Send-MailMessage `
            -From $RemitenteResuelto `
            -To $DestinatarioResuelto `
            -Subject "[UrbanBlade Backup] $Asunto" `
            -Body $Mensaje `
            -SmtpServer 'smtp.office365.com' `
            -Port 587 `
            -UseSsl `
            -Credential $cred
    }

    'AwsSes' {
        if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
            throw 'AWS CLI v2 no está instalada. Para usar AWS SES, instálala: https://awscli.amazonaws.com/AWSCLIV2.msi'
        }

        $tmpBody = Join-Path $env:TEMP ("urbanblade-alert-" + [Guid]::NewGuid().ToString("N") + ".json")
        try {
            @{
                Source       = $RemitenteResuelto
                Destination  = @{ ToAddresses = @($DestinatarioResuelto) }
                Message      = @{
                    Subject = @{ Data = "[UrbanBlade Backup] $Asunto" }
                    Body    = @{ Text = @{ Data = $Mensaje } }
                }
            } | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $tmpBody -Encoding UTF8

            & aws ses send-email `
                --region $AwsSesRegion `
                --profile $AwsSesProfile `
                --cli-input-json "fileb://$tmpBody"

            if ($LASTEXITCODE -ne 0) { throw 'AWS SES send-email falló.' }
        } finally {
            if (Test-Path -LiteralPath $tmpBody) { Remove-Item -LiteralPath $tmpBody -Force -ErrorAction SilentlyContinue }
        }
    }
}
