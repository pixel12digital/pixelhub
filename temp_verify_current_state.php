<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO DO ESTADO ATUAL ===\n\n";

// 1. Verifica Lead #6
$stmt = $db->prepare("SELECT id, name, phone FROM leads WHERE id = 6");
$stmt->execute();
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

echo "LEAD #6:\n";
echo "  ID: {$lead['id']}\n";
echo "  Nome: " . ($lead['name'] ?: 'NULL') . "\n";
echo "  Telefone: {$lead['phone']}\n\n";

// 2. Verifica TODAS as conversas do Lead #6
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        lead_id,
        tenant_id,
        created_at
    FROM conversations
    WHERE lead_id = 6
    ORDER BY created_at DESC
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "CONVERSAS VINCULADAS AO LEAD #6: " . count($conversations) . "\n\n";
foreach ($conversations as $conv) {
    echo "Conversa ID: {$conv['id']}\n";
    echo "  Contact External ID: {$conv['contact_external_id']}\n";
    echo "  Contact Name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
    echo "  Lead ID: {$conv['lead_id']}\n";
    echo "  Criado: {$conv['created_at']}\n\n";
}

// 3. Verifica se há outras conversas com o mesmo telefone mas SEM lead_id
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        lead_id,
        tenant_id,
        created_at
    FROM conversations
    WHERE contact_external_id LIKE '%960125239%'
    ORDER BY created_at DESC
");
$stmt->execute();
$allConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "TODAS AS CONVERSAS COM TELEFONE SIMILAR: " . count($allConversations) . "\n\n";
foreach ($allConversations as $conv) {
    echo "Conversa ID: {$conv['id']}\n";
    echo "  Contact External ID: {$conv['contact_external_id']}\n";
    echo "  Contact Name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
    echo "  Lead ID: " . ($conv['lead_id'] ?: 'NULL') . "\n";
    echo "  Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "  Criado: {$conv['created_at']}\n\n";
}
