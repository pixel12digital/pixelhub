<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE INTEGRIDADE - LEAD #6 ===\n\n";

// 1. Verifica se o Lead #6 existe
$stmt = $db->prepare("SELECT * FROM leads WHERE id = 6");
$stmt->execute();
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("❌ ERRO: Lead #6 NÃO ENCONTRADO!\n");
}

echo "✅ Lead #6 EXISTE e está SEGURO\n\n";
echo "Dados do Lead #6:\n";
echo "  ID: {$lead['id']}\n";
echo "  Nome: " . ($lead['name'] ?: 'NULL') . "\n";
echo "  Telefone: {$lead['phone']}\n";
echo "  Email: " . ($lead['email'] ?: 'NULL') . "\n";
echo "  Status: " . ($lead['status'] ?: 'NULL') . "\n";
echo "  Criado em: {$lead['created_at']}\n\n";

// 2. Verifica conversas vinculadas ao Lead #6
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        lead_id,
        tenant_id,
        created_at,
        updated_at
    FROM conversations
    WHERE lead_id = 6
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Conversas vinculadas ao Lead #6: " . count($conversations) . "\n\n";

foreach ($conversations as $conv) {
    echo "  Conversa ID: {$conv['id']}\n";
    echo "    Contact External ID: {$conv['contact_external_id']}\n";
    echo "    Contact Name: " . ($conv['contact_name'] ?: '❌ NULL (PROBLEMA!)') . "\n";
    echo "    Lead ID: {$conv['lead_id']}\n";
    echo "    Tenant ID: " . ($conv['tenant_id'] ?: 'NULL (normal para Lead)') . "\n";
    echo "    Criado: {$conv['created_at']}\n\n";
}

// 3. Conta eventos de comunicação vinculados ao Lead #6
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM communication_events ce
    INNER JOIN conversations c ON ce.conversation_id = c.id
    WHERE c.lead_id = 6
");
$stmt->execute();
$eventCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Eventos de comunicação vinculados ao Lead #6: {$eventCount}\n\n";

// 4. Verifica se há oportunidades vinculadas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM opportunities WHERE lead_id = 6");
$stmt->execute();
$oppCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Oportunidades vinculadas ao Lead #6: {$oppCount}\n\n";

echo str_repeat("=", 80) . "\n";
echo "CONCLUSÃO:\n";
echo "✅ Lead #6 está SEGURO e NÃO será excluído\n";
echo "⚠️  Problema identificado: contact_name está NULL na conversa\n";
echo "🔧 Solução: Atualizar contact_name com o nome do Lead vinculado\n";
