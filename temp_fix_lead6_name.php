<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== CORREÇÃO: ATUALIZAR NOME DO LEAD #6 ===\n\n";

// 1. Verifica estado atual do Lead #6
$stmt = $db->prepare("SELECT * FROM leads WHERE id = 6");
$stmt->execute();
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("❌ ERRO: Lead #6 não encontrado!\n");
}

echo "Estado ATUAL do Lead #6:\n";
echo "  Nome: " . ($lead['name'] ?: '❌ NULL') . "\n";
echo "  Telefone: {$lead['phone']}\n";
echo "  Email: " . ($lead['email'] ?: 'NULL') . "\n\n";

// 2. Busca informações de eventos para tentar descobrir o nome
$stmt = $db->prepare("
    SELECT 
        ce.payload
    FROM communication_events ce
    INNER JOIN conversations c ON ce.conversation_id = c.id
    WHERE c.lead_id = 6
    ORDER BY ce.created_at ASC
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Buscando nome nos eventos de comunicação...\n";
$discoveredName = null;

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    
    // Tenta extrair nome de várias fontes
    $possibleNames = [
        $payload['notifyName'] ?? null,
        $payload['raw']['payload']['notifyName'] ?? null,
        $payload['sender']['name'] ?? null,
        $payload['sender']['pushname'] ?? null,
        $payload['sender']['verifiedName'] ?? null,
    ];
    
    foreach ($possibleNames as $name) {
        if (!empty($name) && 
            $name !== 'Pixel12 Digital' && 
            $name !== 'Pixel12Digital' &&
            $name !== 'pixel12digital' &&
            strlen($name) > 2) {
            $discoveredName = $name;
            echo "  ✅ Nome encontrado: {$name}\n";
            break 2;
        }
    }
}

if (!$discoveredName) {
    echo "  ⚠️  Nenhum nome encontrado nos eventos\n";
    echo "  💡 Você pode atualizar manualmente pela interface do PixelHub\n\n";
    
    echo "Para atualizar manualmente, execute:\n";
    echo "  UPDATE leads SET name = 'Nome do Lead' WHERE id = 6;\n\n";
    
    echo "Ou acesse: /leads/edit?id=6 na interface web\n";
    exit;
}

echo "\n";

// 3. Atualiza o nome do Lead #6
$updateStmt = $db->prepare("UPDATE leads SET name = ? WHERE id = 6");
$updateStmt->execute([$discoveredName]);

echo "✅ Nome do Lead #6 atualizado para: {$discoveredName}\n\n";

// 4. Atualiza o contact_name da conversa vinculada
$updateConvStmt = $db->prepare("
    UPDATE conversations 
    SET contact_name = ? 
    WHERE lead_id = 6 AND (contact_name IS NULL OR contact_name = '')
");
$updateConvStmt->execute([$discoveredName]);

echo "✅ contact_name da conversa atualizado\n\n";

// 5. Verifica resultado final
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_name,
        c.lead_id,
        l.name as lead_name
    FROM conversations c
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.lead_id = 6
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "RESULTADO FINAL:\n";
echo "  Conversa ID: {$result['id']}\n";
echo "  Contact Name: " . ($result['contact_name'] ?: 'NULL') . "\n";
echo "  Lead Name: " . ($result['lead_name'] ?: 'NULL') . "\n\n";

echo "✅ CORREÇÃO CONCLUÍDA!\n";
echo "Agora a conversa deve aparecer com o nome '{$discoveredName}' no Inbox\n";
