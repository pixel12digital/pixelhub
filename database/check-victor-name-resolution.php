<?php
/**
 * Script para diagnosticar resolução de nome do contato Victor (169183207809126)
 * 
 * Executa queries para verificar:
 * 1. Cache de nomes (wa_contact_names_cache)
 * 2. Eventos recentes com esse telefone
 * 3. Conversas relacionadas
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();
if (!$db) {
    die("Erro: Não foi possível conectar ao banco de dados.\n");
}

$phoneE164 = '55169183207809126'; // Telefone resolvido do Victor
$phoneDigits = '169183207809126'; // Digits only (sem 55)
$phoneVariants = [
    $phoneE164,
    $phoneDigits,
    '55' . $phoneDigits,
    $phoneDigits . '@lid',
    $phoneE164 . '@s.whatsapp.net',
    $phoneE164 . '@c.us',
];

echo "=== DIAGNÓSTICO: Resolução de Nome - Victor (169183207809126) ===\n\n";

// 1. Verifica cache de nomes
echo "1. CACHE DE NOMES (wa_contact_names_cache):\n";
echo str_repeat("-", 80) . "\n";

foreach ($phoneVariants as $variant) {
    $stmt = $db->prepare("
        SELECT id, provider, session_id, phone_e164, display_name, source, updated_at, created_at
        FROM wa_contact_names_cache
        WHERE phone_e164 = ? OR phone_e164 LIKE ?
        ORDER BY updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$variant, "%{$phoneDigits}%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($results)) {
        echo "  Variant: {$variant}\n";
        foreach ($results as $row) {
            echo sprintf(
                "    - ID: %d | Provider: %s | Session: %s | Phone: %s | Name: %s | Source: %s | Updated: %s\n",
                $row['id'],
                $row['provider'] ?? 'NULL',
                $row['session_id'] ?? 'NULL',
                $row['phone_e164'],
                $row['display_name'],
                $row['source'],
                $row['updated_at']
            );
        }
    }
}

// Verifica todos os registros relacionados
$stmt = $db->prepare("
    SELECT id, provider, session_id, phone_e164, display_name, source, updated_at
    FROM wa_contact_names_cache
    WHERE phone_e164 LIKE ? OR phone_e164 LIKE ?
    ORDER BY updated_at DESC
    LIMIT 10
");
$stmt->execute(["%{$phoneDigits}%", "%169183207809126%"]);
$allCache = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($allCache)) {
    echo "  ❌ NENHUM REGISTRO ENCONTRADO NO CACHE\n";
} else {
    echo "  ✅ Total de registros encontrados: " . count($allCache) . "\n";
}

echo "\n";

// 2. Verifica eventos recentes
echo "2. EVENTOS RECENTES (communication_events):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.to') as to_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
        JSON_EXTRACT(ce.payload, '$.message.notifyName') as notifyName,
        JSON_EXTRACT(ce.payload, '$.raw.payload.notifyName') as raw_notifyName,
        JSON_EXTRACT(ce.payload, '$.raw.payload.sender.verifiedName') as verifiedName,
        JSON_EXTRACT(ce.payload, '$.raw.payload.sender.name') as sender_name,
        JSON_EXTRACT(ce.payload, '$.raw.payload.sender.formattedName') as formattedName,
        JSON_EXTRACT(ce.payload, '$.pushName') as pushName
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(ce.payload, '$.from') LIKE ? 
        OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$phonePattern = "%{$phoneDigits}%";
$stmt->execute([$phonePattern, $phonePattern, $phonePattern, $phonePattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "  ❌ NENHUM EVENTO ENCONTRADO\n";
} else {
    echo "  ✅ Total de eventos encontrados: " . count($events) . "\n\n";
    foreach ($events as $idx => $event) {
        echo sprintf("  Evento #%d (ID: %d, Tipo: %s, Data: %s):\n", 
            $idx + 1, 
            $event['id'], 
            $event['event_type'],
            $event['created_at']
        );
        echo sprintf("    From: %s\n", $event['from_field'] ?? 'NULL');
        echo sprintf("    To: %s\n", $event['to_field'] ?? 'NULL');
        echo sprintf("    Message.From: %s\n", $event['message_from'] ?? 'NULL');
        echo sprintf("    Message.To: %s\n", $event['message_to'] ?? 'NULL');
        
        $nameFields = [];
        if ($event['notifyName']) $nameFields[] = "notifyName=" . trim($event['notifyName'], '"');
        if ($event['raw_notifyName']) $nameFields[] = "raw.notifyName=" . trim($event['raw_notifyName'], '"');
        if ($event['verifiedName']) $nameFields[] = "verifiedName=" . trim($event['verifiedName'], '"');
        if ($event['sender_name']) $nameFields[] = "sender.name=" . trim($event['sender_name'], '"');
        if ($event['formattedName']) $nameFields[] = "formattedName=" . trim($event['formattedName'], '"');
        if ($event['pushName']) $nameFields[] = "pushName=" . trim($event['pushName'], '"');
        
        if (!empty($nameFields)) {
            echo "    ✅ Campos de nome encontrados: " . implode(', ', $nameFields) . "\n";
        } else {
            echo "    ❌ NENHUM CAMPO DE NOME ENCONTRADO\n";
        }
        echo "\n";
    }
}

// 3. Verifica conversas relacionadas
echo "3. CONVERSAS RELACIONADAS:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.channel_id,
        c.tenant_id,
        c.last_message_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_external_id LIKE ? 
       OR c.contact_external_id LIKE ?
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmt->execute(["%{$phoneDigits}%", "%169183207809126%"]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "  ❌ NENHUMA CONVERSA ENCONTRADA\n";
} else {
    echo "  ✅ Total de conversas encontradas: " . count($conversations) . "\n\n";
    foreach ($conversations as $conv) {
        echo sprintf(
            "  Conversa ID: %d | Key: %s | Contact ID: %s | Contact Name: %s | Channel: %s | Tenant: %s\n",
            $conv['id'],
            $conv['conversation_key'],
            $conv['contact_external_id'],
            $conv['contact_name'] ?? 'NULL',
            $conv['channel_id'] ?? 'NULL',
            $conv['tenant_name'] ?? 'NULL'
        );
    }
}

echo "\n";
echo "=== FIM DO DIAGNÓSTICO ===\n";
echo "\n";
echo "PRÓXIMOS PASSOS:\n";
echo "1. Verifique os logs [NAME_TRACE] no error_log do PHP\n";
echo "2. Se não houver nome nos eventos, verifique se o gateway retorna nome via API\n";
echo "3. Se o gateway retornar nome, verifique se normalizeDisplayName() está descartando\n";
echo "4. Verifique se o phone_e164 está sendo salvo corretamente no cache\n";

