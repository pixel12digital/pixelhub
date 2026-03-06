<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== TESTANDO VALIDAÇÃO DE CHANNEL_ID ===\n\n";

$db = DB::getConnection();

// Testa a query exata que está sendo usada
$sessionId = 'pixel12digital';
$tenantId = null;

echo "1. Testando busca com channel_id = '{$sessionId}':\n";
echo str_repeat('-', 80) . "\n";

// Simula a query exata do validateGatewaySessionId
$sessionIdNormalized = strtolower(preg_replace('/\s+/', '', trim($sessionId)));

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

echo "Query SQL:\n{$sql}\n\n";
echo "Parâmetros:\n";
echo "  1: '{$sessionId}'\n";
echo "  2: '{$sessionId}'\n";
echo "  3: '{$sessionIdNormalized}'\n";
echo "  4: '{$sessionId}'\n\n";

$stmt = $db->prepare($sql);
$stmt->execute([$sessionId, $sessionId, $sessionIdNormalized, $sessionId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✅ SUCESSO! Canal encontrado:\n";
    print_r($result);
} else {
    echo "❌ FALHA! Nenhum canal encontrado\n\n";
    
    // Testa manualmente cada condição
    echo "2. Testando cada condição separadamente:\n";
    echo str_repeat('-', 80) . "\n";
    
    // Lista todos os canais
    $allChannels = $db->query("
        SELECT id, channel_id, tenant_id, is_enabled 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway'
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Canais disponíveis:\n";
    foreach ($allChannels as $ch) {
        echo "  - ID: {$ch['id']}, channel_id: '{$ch['channel_id']}', tenant_id: {$ch['tenant_id']}, enabled: {$ch['is_enabled']}\n";
        
        // Testa cada condição
        $match1 = ($ch['channel_id'] === $sessionId);
        $match2 = (strtolower(trim($ch['channel_id'])) === strtolower(trim($sessionId)));
        $match3 = (strtolower(str_replace(' ', '', $ch['channel_id'])) === $sessionIdNormalized);
        $match4 = (strtolower(str_replace(' ', '', $ch['channel_id'])) === strtolower(str_replace(' ', '', $sessionId)));
        
        echo "    Condição 1 (exato): " . ($match1 ? 'MATCH' : 'no match') . "\n";
        echo "    Condição 2 (trim+lower): " . ($match2 ? 'MATCH' : 'no match') . "\n";
        echo "    Condição 3 (normalized): " . ($match3 ? 'MATCH' : 'no match') . "\n";
        echo "    Condição 4 (both normalized): " . ($match4 ? 'MATCH' : 'no match') . "\n";
        
        if ($match1 || $match2 || $match3 || $match4) {
            echo "    ✅ ESTE CANAL DEVERIA TER SIDO ENCONTRADO!\n";
        }
        echo "\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "TESTE CONCLUÍDO\n";
echo str_repeat('=', 80) . "\n";
