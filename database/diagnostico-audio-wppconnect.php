<?php
/**
 * Script de diagnóstico para erro de envio de áudio via WPPConnect
 * 
 * Verifica:
 * 1. Status da sessão no gateway
 * 2. Configurações do gateway
 * 3. Logs recentes de erro
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\Env;
use PixelHub\Services\GatewaySecret;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

$channelId = 'pixel12digital';

echo "=== DIAGNÓSTICO: Erro de Envio de Áudio WPPConnect ===\n\n";

// 1. Verifica configurações do gateway
echo "1. CONFIGURAÇÕES DO GATEWAY:\n";
$baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
echo "   Base URL: {$baseUrl}\n";

try {
    $secret = GatewaySecret::getDecrypted();
    echo "   Secret: " . (!empty($secret) ? "CONFIGURADO (len=" . strlen($secret) . ")" : "VAZIO") . "\n";
} catch (\Exception $e) {
    echo "   Secret: ERRO ao obter - " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Verifica status da sessão
echo "2. STATUS DA SESSÃO '{$channelId}':\n";
try {
    $gateway = new WhatsAppGatewayClient($baseUrl, $secret);
    $channelInfo = $gateway->getChannel($channelId);
    
    if ($channelInfo['success']) {
        $channelData = $channelInfo['raw'] ?? [];
        
        // Extrai status de vários campos possíveis
        $status = $channelData['channel']['status'] 
            ?? $channelData['channel']['connection'] 
            ?? $channelData['status'] 
            ?? $channelData['connection'] 
            ?? 'N/A';
        
        $connected = $channelData['connected'] ?? false;
        
        echo "   ✅ Sessão encontrada\n";
        echo "   Status: {$status}\n";
        echo "   Connected (boolean): " . ($connected ? 'true' : 'false') . "\n";
        echo "   Estrutura completa:\n";
        echo "   " . json_encode($channelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($status !== 'connected' && $status !== 'open' && !$connected) {
            echo "\n   ⚠️ ATENÇÃO: Sessão NÃO está conectada!\n";
            echo "   Isso pode ser a causa do erro ao enviar áudio.\n";
        }
    } else {
        echo "   ❌ Erro ao buscar sessão:\n";
        echo "   " . ($channelInfo['error'] ?? 'Erro desconhecido') . "\n";
        echo "   Status HTTP: " . ($channelInfo['status'] ?? 'N/A') . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ EXCEÇÃO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";

// 3. Verifica logs recentes do PHP
echo "3. LOGS RECENTES DO PHP (últimas 20 linhas com 'audio' ou 'WPPConnect'):\n";
$logFile = __DIR__ . '/../logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -50); // Últimas 50 linhas
    $audioLines = array_filter($recentLines, function($line) {
        return stripos($line, 'audio') !== false || 
               stripos($line, 'WPPConnect') !== false || 
               stripos($line, 'sendVoiceBase64') !== false ||
               stripos($line, 'sendAudioBase64Ptt') !== false;
    });
    
    if (!empty($audioLines)) {
        foreach (array_slice($audioLines, -20) as $line) {
            echo "   " . trim($line) . "\n";
        }
    } else {
        echo "   Nenhum log relacionado a áudio encontrado nas últimas 50 linhas.\n";
    }
} else {
    echo "   Arquivo de log não encontrado: {$logFile}\n";
}

echo "\n";

// 4. Teste de envio (simulado - apenas validação)
echo "4. VALIDAÇÃO DE FORMATO DE ÁUDIO:\n";
echo "   Para testar o envio real, use o painel web.\n";
echo "   O código valida:\n";
echo "   - Tamanho máximo: 16MB\n";
echo "   - Formato: OGG/Opus (deve conter 'OpusHead')\n";
echo "   - Tamanho mínimo: 2000 bytes\n";

echo "\n";

// 5. Recomendações
echo "5. RECOMENDAÇÕES:\n";
echo "   - Verifique se a sessão está conectada no gateway\n";
echo "   - Verifique os logs do gateway WPPConnect no servidor\n";
echo "   - Teste enviar uma mensagem de texto primeiro para confirmar que a sessão funciona\n";
echo "   - Verifique se o formato do áudio está correto (OGG/Opus)\n";
echo "   - Verifique se o tamanho do áudio não excede 16MB\n";

echo "\n=== FIM DO DIAGNÓSTICO ===\n";

