# Script PowerShell para executar no servidor do Hub (Windows)
# Verifica logs relacionados ao teste do webhook

$correlationId = "9858a507-cc4c-4632-8f92-462535eab504"
$testTime = "21:35"
$containerName = "gateway-hub"  # Ajustar conforme necessário

Write-Host "=== Verificando Logs do Hub no Servidor ===" -ForegroundColor Cyan
Write-Host "correlation_id: $correlationId"
Write-Host "horário do teste: ~$testTime"
Write-Host "container: $containerName"
Write-Host ""

# Verifica se o container existe
$containers = docker ps -a --format "{{.Names}}"
if ($containers -notcontains $containerName) {
    Write-Host "⚠️  Container '$containerName' não encontrado." -ForegroundColor Yellow
    Write-Host "Containers disponíveis:"
    docker ps -a --format "table {{.Names}}\t{{.Status}}"
    Write-Host ""
    $containerName = Read-Host "Digite o nome do container do Hub"
}

Write-Host "=== Buscando por correlation_id ===" -ForegroundColor Green
docker logs --since 21:30 $containerName 2>&1 | Select-String -Pattern $correlationId -CaseSensitive:$false | Select-Object -Last 20

Write-Host ""
Write-Host "=== Buscando HUB_WEBHOOK_IN próximo ao horário do teste ===" -ForegroundColor Green
docker logs --since 21:30 $containerName 2>&1 | Select-String -Pattern "HUB_WEBHOOK_IN.*$testTime" -CaseSensitive:$false | Select-Object -Last 10

Write-Host ""
Write-Host "=== Buscando HUB_MSG_SAVE próximo ao horário do teste ===" -ForegroundColor Green
docker logs --since 21:30 $containerName 2>&1 | Select-String -Pattern "HUB_MSG_SAVE.*$testTime" -CaseSensitive:$false | Select-Object -Last 10

Write-Host ""
Write-Host "=== Buscando HUB_MSG_DROP próximo ao horário do teste ===" -ForegroundColor Green
docker logs --since 21:30 $containerName 2>&1 | Select-String -Pattern "HUB_MSG_DROP.*$testTime" -CaseSensitive:$false | Select-Object -Last 10

Write-Host ""
Write-Host "=== Buscando erros/exceções próximo ao horário do teste ===" -ForegroundColor Yellow
docker logs --since 21:30 $containerName 2>&1 | Select-String -Pattern "Exception|Error|Fatal.*$testTime" -CaseSensitive:$false | Select-Object -Last 10

Write-Host ""
Write-Host "=== Últimas 30 linhas de log (para contexto) ===" -ForegroundColor Cyan
docker logs --tail 30 $containerName 2>&1

