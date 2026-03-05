<?php
/**
 * DIAGNÓSTICO COMPLETO DO ERRO 400 NO SERVIDOR
 * 
 * Execute este script no servidor: php temp_server_diagnostic.php
 */

require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== DIAGNÓSTICO DE ERRO 400 NO SERVIDOR ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

$db = DB::getConnection();

// 1. Verificar versão do código
echo "1. VERIFICANDO VERSÃO DO CÓDIGO:\n";
echo str_repeat('-', 80) . "\n";

$controllerFile = __DIR__ . '/src/Controllers/CommunicationHubController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Verifica se tem a validação case-insensitive
    if (strpos($content, 'LOWER(REPLACE(channel_id') !== false) {
        echo "✅ Código atualizado: Validação case-insensitive presente\n";
    } else {
        echo "❌ PROBLEMA: Código desatualizado - validação case-insensitive AUSENTE\n";
        echo "   SOLUÇÃO: Execute 'git pull origin main'\n";
    }
    
    // Verifica linha específica da validação
    if (strpos($content, 'validateGatewaySessionId') !== false) {
        echo "✅ Método validateGatewaySessionId existe\n";
    } else {
        echo "❌ PROBLEMA: Método validateGatewaySessionId NÃO EXISTE\n";
    }
} else {
    echo "❌ ERRO: Arquivo CommunicationHubController.php não encontrado\n";
}

// 2. Verificar OPcache
echo "\n2. VERIFICANDO OPCACHE:\n";
echo str_repeat('-', 80) . "\n";

if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status();
    if ($opcache && $opcache['opcache_enabled']) {
        echo "⚠️ OPcache ATIVADO\n";
        echo "   Pode estar servindo código antigo em cache\n";
        echo "   SOLUÇÃO: Reinicie o PHP-FPM ou Apache\n";
        
        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo "   ✅ OPcache limpo via script\n";
        }
    } else {
        echo "✅ OPcache desativado ou não disponível\n";
    }
} else {
    echo "✅ OPcache não instalado\n";
}

// 3. Testar validação de canal
echo "\n3. TESTANDO VALIDAÇÃO DE CANAL:\n";
echo str_repeat('-', 80) . "\n";

$channelId = 'pixel12digital';
$sessionIdNormalized = strtolower(preg_replace('/\s+/', '', trim($channelId)));

$sql = "SELECT id, channel_id, tenant_id, is_enabled 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway'
        AND is_enabled = 1
        AND (
            channel_id = ? 
            OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?)) 
            OR LOWER(REPLACE(channel_id, ' ', '')) = ? 
            OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', ''))
        )
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$channelId, $channelId, $sessionIdNormalized, $channelId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✅ VALIDAÇÃO OK: Canal encontrado\n";
    echo "   ID: {$result['id']}\n";
    echo "   channel_id: {$result['channel_id']}\n";
    echo "   tenant_id: {$result['tenant_id']}\n";
    echo "   is_enabled: {$result['is_enabled']}\n";
} else {
    echo "❌ VALIDAÇÃO FALHOU: Canal não encontrado\n";
    echo "   Buscando por: '{$channelId}'\n";
    echo "   Normalizado: '{$sessionIdNormalized}'\n\n";
    
    // Lista todos os canais
    echo "   Canais disponíveis:\n";
    $allChannels = $db->query("
        SELECT id, channel_id, tenant_id, is_enabled 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway'
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allChannels as $ch) {
        echo "     - ID: {$ch['id']}, channel_id: '{$ch['channel_id']}', tenant_id: {$ch['tenant_id']}, enabled: {$ch['is_enabled']}\n";
    }
}

// 4. Verificar últimos erros no log
echo "\n4. ÚLTIMOS ERROS NO LOG:\n";
echo str_repeat('-', 80) . "\n";

$logFile = __DIR__ . '/logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $errorLines = [];
    
    foreach ($lines as $line) {
        if (stripos($line, 'ERRO 400') !== false || 
            stripos($line, 'Nenhum canal') !== false ||
            stripos($line, 'channel_id') !== false) {
            $errorLines[] = $line;
        }
    }
    
    $errorLines = array_slice($errorLines, -10);
    
    if (count($errorLines) > 0) {
        echo "Últimas 10 linhas relevantes:\n";
        foreach ($errorLines as $line) {
            echo "  " . trim($line) . "\n";
        }
    } else {
        echo "Nenhum erro recente encontrado\n";
    }
} else {
    echo "⚠️ Arquivo de log não encontrado: {$logFile}\n";
}

// 5. Verificar estrutura da tabela
echo "\n5. ESTRUTURA DA TABELA tenant_message_channels:\n";
echo str_repeat('-', 80) . "\n";

$columns = $db->query("DESCRIBE tenant_message_channels")->fetchAll(PDO::FETCH_ASSOC);
$hasSessionId = false;

foreach ($columns as $col) {
    if ($col['Field'] === 'session_id') {
        $hasSessionId = true;
    }
    echo "  {$col['Field']} ({$col['Type']})\n";
}

if ($hasSessionId) {
    echo "\n✅ Coluna 'session_id' existe\n";
} else {
    echo "\n⚠️ Coluna 'session_id' NÃO existe (usando channel_id)\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "DIAGNÓSTICO CONCLUÍDO\n";
echo str_repeat('=', 80) . "\n";
echo "\nPróximos passos:\n";
echo "1. Se código desatualizado: git pull origin main\n";
echo "2. Se OPcache ativo: sudo systemctl restart php-fpm (ou apache2)\n";
echo "3. Se validação falhou: verificar dados dos canais no banco\n";
