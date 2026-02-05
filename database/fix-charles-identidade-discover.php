<?php
/**
 * Descobre e corrige conversa(s) Charles com contact_external_id errado (11 em vez de 47)
 * Busca por: contact_external_id=5511940863773 + channel ImobSites, ou eventos com @lid Charles
 * 
 * Uso: php database/fix-charles-identidade-discover.php
 */
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
\PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = \PixelHub\Core\DB::getConnection();

echo "=== Descoberta e correção identidade Charles (47 vs 11) ===\n\n";

// 1) Busca conversas com número errado (11) no canal ImobSites
$stmt = $db->prepare("
    SELECT id, contact_external_id, contact_name, channel_id, tenant_id, last_message_at
    FROM conversations
    WHERE channel_type = 'whatsapp'
    AND contact_external_id = '5511940863773'
    AND (channel_id IS NULL OR LOWER(TRIM(channel_id)) = 'imobsites')
    ORDER BY last_message_at DESC
");
$stmt->execute();
$convsWrong = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2) Busca eventos recentes com @lid Charles (208989199560861 ou similar) e texto teste
$stmt2 = $db->prepare("
    SELECT ce.event_id, ce.conversation_id, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.from')) as raw_from,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')), 50) as text_preview
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND (ce.payload LIKE '%teste1310%' OR ce.payload LIKE '%teste1329%' OR ce.payload LIKE '%208989199560861@lid%')
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt2->execute();
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "--- Conversas com 5511940863773 (11) no ImobSites ---\n";
if (empty($convsWrong)) {
    echo "  Nenhuma encontrada.\n";
} else {
    foreach ($convsWrong as $c) {
        echo "  id={$c['id']} | contact_name={$c['contact_name']} | channel={$c['channel_id']} | {$c['last_message_at']}\n";
    }
}

echo "\n--- Eventos teste1310/teste1329 ou @lid Charles ---\n";
if (empty($events)) {
    echo "  Nenhum encontrado.\n";
} else {
    foreach ($events as $e) {
        echo "  event={$e['event_id']} | conv_id={$e['conversation_id']} | from={$e['msg_from']}{$e['raw_from']} | {$e['created_at']} | text={$e['text_preview']}\n";
    }
}

// 3) Determina qual conversa corrigir
$toFix = [];
if (!empty($events)) {
    foreach ($events as $e) {
        if ($e['conversation_id']) {
            $toFix[$e['conversation_id']] = true;
        }
    }
}
if (!empty($convsWrong)) {
    foreach ($convsWrong as $c) {
        $toFix[$c['id']] = true;
    }
}

$conversationIds = array_keys($toFix);
if (empty($conversationIds)) {
    echo "\nNenhuma conversa para corrigir. Verifique se há eventos/conversas no banco.\n";
    exit(0);
}

$correctContactId = '208989199560861@lid';
$correctPhone = '5547996164699';

echo "\n--- Aplicando correção ---\n";
foreach ($conversationIds as $convId) {
    $stmt = $db->prepare("SELECT id, contact_external_id, contact_name FROM conversations WHERE id = ?");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv) continue;

    $old = $conv['contact_external_id'];
    if ($old === $correctContactId) {
        echo "  Conv {$convId}: já correto ({$correctContactId})\n";
        continue;
    }

    $upd = $db->prepare("UPDATE conversations SET contact_external_id = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$correctContactId, $convId]);
    echo "  Conv {$convId}: {$old} -> {$correctContactId} OK\n";

    // Mapeamento @lid -> phone (FORÇA correção - pode existir mapeamento errado 208...@lid -> 5511...)
    $before = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ?");
    $before->execute([$correctContactId]);
    $row = $before->fetch(PDO::FETCH_ASSOC);
    $oldPhone = $row['phone_number'] ?? null;
    $db->prepare("
        INSERT INTO whatsapp_business_ids (business_id, phone_number, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE phone_number = VALUES(phone_number)
    ")->execute([$correctContactId, $correctPhone]);
    if ($oldPhone && $oldPhone !== $correctPhone) {
        echo "    Mapeamento CORRIGIDO: {$correctContactId} -> {$oldPhone} (errado) substituído por {$correctPhone}\n";
    } else {
        echo "    Mapeamento {$correctContactId} -> {$correctPhone} OK\n";
    }
}

// 4) Busca TODAS conversas Charles Dietrich no ImobSites e corrige
$stmt3 = $db->prepare("
    SELECT id, contact_external_id, contact_name FROM conversations
    WHERE channel_type = 'whatsapp'
    AND (channel_id IS NULL OR LOWER(TRIM(channel_id)) = 'imobsites')
    AND (contact_name LIKE '%Charles%' OR contact_external_id = '5511940863773')
    AND contact_external_id != '208989199560861@lid'
");
$stmt3->execute();
$moreConvs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
if (!empty($moreConvs)) {
    echo "\n--- Conversas adicionais Charles/5511 no ImobSites ---\n";
    foreach ($moreConvs as $c) {
        $upd = $db->prepare("UPDATE conversations SET contact_external_id = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$correctContactId, $c['id']]);
        echo "  Conv {$c['id']}: {$c['contact_external_id']} -> {$correctContactId} OK\n";
    }
    // Força mapeamento @lid -> 47 (sobrescreve se estiver errado)
    $db->prepare("
        INSERT INTO whatsapp_business_ids (business_id, phone_number, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE phone_number = VALUES(phone_number)
    ")->execute([$correctContactId, $correctPhone]);
    echo "  Mapeamento {$correctContactId} -> {$correctPhone} garantido\n";
}

echo "\nConcluído. Recarregue o Inbox - deve exibir (47) 99616-4699.\n";
