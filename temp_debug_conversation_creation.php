<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';
require_once ROOT_PATH . 'src/Core/DB.php';
require_once ROOT_PATH . 'src/Services/ConversationService.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\ConversationService;

Env::load();

echo "=== DEBUG: CRIAÇÃO DE CONVERSA DO ANDREI LIMA ===\n\n";

// Simular criação de conversa com os dados do evento do Andrei
$conversationData = [
    'channel_type' => 'whatsapp',
    'channel_account_id' => 'pixel12digital',
    'contact_external_id' => '554797797101@c.us',
    'tenant_id' => null, // Sem tenant vinculado
    'contact_name' => 'Andrei Lima',
    'last_message_at' => date('Y-m-d H:i:s')
];

echo "Tentando criar/resolver conversa com os dados:\n";
echo json_encode($conversationData, JSON_PRETTY_PRINT) . "\n\n";

try {
    $conversationId = ConversationService::resolveConversation($conversationData);
    
    echo "✅ Conversa criada/resolvida com sucesso!\n";
    echo "Conversation ID: {$conversationId}\n\n";
    
    // Buscar a conversa criada
    $db = DB::getConnection();
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            conversation_key,
            contact_external_id,
            contact_name,
            lead_id,
            status,
            last_message_at,
            created_at
        FROM conversations
        WHERE id = :id
    ");
    $stmt->execute(['id' => $conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        echo "Dados da conversa:\n";
        foreach ($conversation as $key => $value) {
            echo "  {$key}: " . ($value ?? 'NULL') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO ao criar conversa: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Verificar se já existe conversa com este contact_external_id
echo "\n\n--- VERIFICANDO CONVERSAS EXISTENTES ---\n";

$db = DB::getConnection();
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        conversation_key,
        contact_external_id,
        contact_name,
        status,
        created_at
    FROM conversations
    WHERE contact_external_id LIKE :pattern
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute(['pattern' => '%97797101%']);
$existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($existing) > 0) {
    echo "Conversas existentes encontradas:\n";
    foreach ($existing as $conv) {
        echo sprintf(
            "  ID: %d | Tenant: %s | Contact: %s | Nome: %s | Criado: %s\n",
            $conv['id'],
            $conv['tenant_id'] ?? 'NULL',
            $conv['contact_external_id'],
            $conv['contact_name'] ?? 'N/A',
            $conv['created_at']
        );
    }
} else {
    echo "Nenhuma conversa existente encontrada.\n";
}
