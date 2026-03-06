<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== SIMULANDO FLUXO COMPLETO DE ENVIO ===\n\n";

$db = DB::getConnection();

// Simula payload do modal Nova Mensagem
$_POST = [
    'channel' => 'whatsapp',
    'to' => '(47) 99929-1994',
    'message' => 'Teste de mensagem',
    'tenant_id' => '',  // Vazio conforme console
    'channel_id' => 'pixel12digital',
    'thread_id' => ''  // Nova conversa
];

echo "PAYLOAD RECEBIDO:\n";
print_r($_POST);
echo "\n";

// Extrai variáveis
$channel = $_POST['channel'] ?? null;
$to = $_POST['to'] ?? null;
$message = trim($_POST['message'] ?? '');
$tenantIdFromPost = isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null;
$channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? trim($_POST['channel_id']) : null;
$threadId = $_POST['thread_id'] ?? null;

echo "VARIÁVEIS EXTRAÍDAS:\n";
echo "  channel: " . ($channel ?: 'NULL') . "\n";
echo "  to: " . ($to ?: 'NULL') . "\n";
echo "  message: " . ($message ?: 'NULL') . "\n";
echo "  tenantIdFromPost: " . ($tenantIdFromPost ?: 'NULL') . "\n";
echo "  channelId: " . ($channelId ?: 'NULL') . "\n";
echo "  threadId: " . ($threadId ?: 'NULL') . "\n\n";

// VALIDAÇÃO 1: Canal vazio
if (empty($channel)) {
    echo "❌ ERRO 400: Canal vazio\n";
    exit(1);
}
echo "✅ Validação 1 OK: Canal = {$channel}\n";

// VALIDAÇÃO 2: Telefone vazio (para WhatsApp)
if ($channel === 'whatsapp' && empty($to)) {
    echo "❌ ERRO 400: Telefone vazio\n";
    exit(1);
}
echo "✅ Validação 2 OK: Telefone = {$to}\n";

// VALIDAÇÃO 3: Mensagem vazia (para texto)
if (empty($message)) {
    echo "❌ ERRO 400: Mensagem vazia\n";
    exit(1);
}
echo "✅ Validação 3 OK: Mensagem não vazia\n\n";

// Simula lógica de resolução de canal
echo "INICIANDO RESOLUÇÃO DE CANAL:\n";
echo str_repeat('-', 80) . "\n";

$tenantId = $tenantIdFromPost;
$targetChannels = [];

// PRIORIDADE ABSOLUTA: channel_id do POST
if (!empty($channelId) && empty($targetChannels)) {
    echo "PRIORIDADE ABSOLUTA: channel_id do POST = '{$channelId}'\n";
    
    // Simula validateGatewaySessionId
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
    $validatedChannel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($validatedChannel && !empty($validatedChannel['channel_id'])) {
        $targetChannels = [trim($validatedChannel['channel_id'])];
        echo "✅ ChannelId validado: '{$channelId}' → '{$validatedChannel['channel_id']}'\n";
        echo "   Canal encontrado: ID={$validatedChannel['id']}, tenant_id={$validatedChannel['tenant_id']}\n";
    } else {
        echo "⚠️ ChannelId do POST não validado: '{$channelId}'\n";
        echo "   Tentando fallback...\n\n";
        
        // Busca canais no banco
        $checkStmt = $db->prepare("
            SELECT channel_id, tenant_id, is_enabled
            FROM tenant_message_channels
            WHERE provider = 'wpp_gateway'
            AND (
                channel_id = ?
                OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
                OR LOWER(REPLACE(channel_id, ' ', '')) = ?
            )
            LIMIT 5
        ");
        $checkStmt->execute([$channelId, $channelId, $sessionIdNormalized]);
        $foundChannels = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($foundChannels)) {
            echo "   Canais encontrados no banco:\n";
            foreach ($foundChannels as $ch) {
                echo "     - channel_id: '{$ch['channel_id']}', tenant_id: {$ch['tenant_id']}, enabled: {$ch['is_enabled']}\n";
            }
            
            // Tenta usar o primeiro encontrado
            $firstFound = $foundChannels[0];
            if ($firstFound['is_enabled']) {
                $targetChannels = [trim($firstFound['channel_id'])];
                echo "   ✅ Usando canal do banco: '{$firstFound['channel_id']}'\n";
            } else {
                echo "   ❌ Canal encontrado mas está desabilitado\n";
            }
        } else {
            echo "   ❌ Nenhum canal encontrado no banco\n";
        }
    }
}

echo "\n";

// VALIDAÇÃO FINAL
if (empty($targetChannels)) {
    echo "❌ ERRO 400: Nenhum canal WhatsApp identificado para envio\n";
    echo "   Este é o erro que está ocorrendo!\n";
    exit(1);
}

echo "✅ SUCESSO! Canal identificado: " . implode(', ', $targetChannels) . "\n";
echo "\n" . str_repeat('=', 80) . "\n";
echo "SIMULAÇÃO CONCLUÍDA COM SUCESSO\n";
echo str_repeat('=', 80) . "\n";
