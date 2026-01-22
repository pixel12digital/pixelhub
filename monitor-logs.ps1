# Script PowerShell para monitorar logs do Hub (Windows)
# Uso: .\monitor-logs.ps1

Write-Host "=== Monitor de Logs WhatsApp Webhook ===" -ForegroundColor Cyan
Write-Host "Pressione Ctrl+C para parar" -ForegroundColor Yellow
Write-Host ""

# Caminhos possíveis dos logs
$logPaths = @(
    "logs\pixelhub.log",
    "C:\xampp\apache\logs\error.log",
    "C:\xampp\php\logs\php_error_log"
)

# Encontra o primeiro arquivo de log que existe
$logFile = $null
foreach ($path in $logPaths) {
    if (Test-Path $path) {
        $logFile = $path
        Write-Host "Monitorando: $logFile" -ForegroundColor Green
        break
    }
}

if (-not $logFile) {
    Write-Host "Nenhum arquivo de log encontrado. Tentando criar logs\pixelhub.log..." -ForegroundColor Yellow
    $logDir = "logs"
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    $logFile = "logs\pixelhub.log"
    New-Item -ItemType File -Path $logFile -Force | Out-Null
    Write-Host "Criado: $logFile" -ForegroundColor Green
}

# Padrões de log para filtrar
$patterns = @(
    "HUB_WEBHOOK_IN",
    "HUB_MSG_SAVE_OK",
    "HUB_MSG_DROP",
    "HUB_CONV_MATCH",
    "HUB_MSG_DIRECTION",
    "HUB_CHANNEL_ID",
    "HUB_PHONE_NORM",
    "INCOMING_MSG"
)

# Função para colorir logs
function Write-ColoredLog {
    param($line)
    
    if ($line -match "HUB_WEBHOOK_IN") {
        Write-Host $line -ForegroundColor Cyan
    }
    elseif ($line -match "HUB_MSG_SAVE_OK") {
        Write-Host $line -ForegroundColor Green
    }
    elseif ($line -match "HUB_MSG_DROP") {
        Write-Host $line -ForegroundColor Red
    }
    elseif ($line -match "HUB_CONV_MATCH") {
        Write-Host $line -ForegroundColor Yellow
    }
    elseif ($line -match "HUB_MSG_DIRECTION") {
        Write-Host $line -ForegroundColor Magenta
    }
    elseif ($line -match "HUB_CHANNEL_ID") {
        Write-Host $line -ForegroundColor Blue
    }
    elseif ($line -match "HUB_PHONE_NORM") {
        Write-Host $line -ForegroundColor White
    }
    elseif ($line -match "INCOMING_MSG") {
        Write-Host $line -ForegroundColor Cyan
    }
    else {
        Write-Host $line
    }
}

# Variável para rastrear última posição lida
$lastPosition = 0
if (Test-Path $logFile) {
    $lastPosition = (Get-Item $logFile).Length
}

# Loop principal
try {
    Write-Host "Aguardando novos logs... (Ctrl+C para parar)" -ForegroundColor Gray
    Write-Host ""
    
    while ($true) {
        if (Test-Path $logFile) {
            $file = Get-Item $logFile
            $currentSize = $file.Length
            
            # Se arquivo cresceu, lê novas linhas
            if ($currentSize -gt $lastPosition) {
                $stream = [System.IO.File]::OpenRead($logFile)
                $stream.Position = $lastPosition
                $reader = New-Object System.IO.StreamReader($stream)
                
                while ($null -ne ($line = $reader.ReadLine())) {
                    foreach ($pattern in $patterns) {
                        if ($line -match $pattern) {
                            Write-ColoredLog $line
                            break
                        }
                    }
                }
                
                $reader.Close()
                $stream.Close()
                $lastPosition = $currentSize
            }
        }
        
        Start-Sleep -Milliseconds 500
    }
}
catch {
    Write-Host "`nMonitoramento interrompido." -ForegroundColor Yellow
}

