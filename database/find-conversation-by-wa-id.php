<?php
/**
 * Script para buscar conversa por ID do WhatsApp
 * 
 * Uso: php database/find-conversation-by-wa-id.php [wa_id]
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$waId = $argv[1] ?? '56083800395891';

// Remove espaços e caracteres não numéricos
$waId = preg_replace('/[^0-9]/', '', $waId);

echo "=== BUSCA DE CONVERSA POR ID WHATSAPP ===\n\n";
echo "ID WhatsApp: {$waId}\n\n";

// Busca conversas - tentando diferentes formatos
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.tenant_id,
        c.channel_id,
        c.status,
        c.contact_name,
        c.last_message_at,
        c.created_at,
        CONCAT('whatsapp_', c.id) as thread_id,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_external_id LIKE ?
       OR c.contact_external_id LIKE ?
       OR REPLACE(REPLACE(REPLACE(c.contact_external_id, ' ', ''), '-', ''), '@c.us', '') LIKE ?
       OR REPLACE(REPLACE(REPLACE(c.contact_external_id, ' ', ''), '-', ''), '@lid', '') LIKE ?
    ORDER BY c.last_message_at DESC
    LIMIT 10
");

$searchPattern1 = "%{$waId}%";
$searchPattern2 = "%{$waId}@%";
$searchPattern3 = "%" . preg_replace('/[^0-9]/', '', $waId) . "%";

$stmt->execute([$searchPattern1, $searchPattern2, $searchPattern3, $searchPattern3]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada com esse ID.\n\n";
    
    echo "Tentando busca mais ampla...\n";
    // Busca parcial
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.contact_external_id,
            c.tenant_id,
            CONCAT('whatsapp_', c.id) as thread_id
        FROM conversations c
        WHERE c.contact_external_id LIKE ?
        ORDER BY c.last_message_at DESC
        LIMIT 20
    ");
    $partialPattern = "%" . substr($waId, -8) . "%";
    $stmt->execute([$partialPattern]);
    $partialMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($partialMatches)) {
        echo "Encontrados possíveis matches (últimos 8 dígitos):\n";
        foreach ($partialMatches as $match) {
            echo "  - Thread: {$match['thread_id']}, Contact: {$match['contact_external_id']}\n";
        }
    } else {
        echo "Nenhum match encontrado nem mesmo parcialmente.\n";
    }
    exit(0);
}

echo "✅ Encontradas " . count($conversations) . " conversa(s):\n\n";

foreach ($conversations as $conv) {
    echo str_repeat("=", 60) . "\n";
    echo "Thread ID: {$conv['thread_id']}\n";
    echo "Conversation ID: {$conv['id']}\n";
    echo "Contact External ID: {$conv['contact_external_id']}\n";
    echo "Contact Name: " . ($conv['contact_name'] ?? 'NULL') . "\n";
    echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "Tenant Name: " . ($conv['tenant_name'] ?? 'NULL') . "\n";
    echo "Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
    echo "Status: " . ($conv['status'] ?? 'NULL') . "\n";
    echo "Última Mensagem: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
    echo "Criada em: " . ($conv['created_at'] ?? 'NULL') . "\n";
    echo "\n";
}

