<?php

/**
 * Script para verificar logs do webhook relacionados a "teste-1459"
 * Verifica logs do PHP (error_log) e busca por padrões relacionados
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando logs do webhook para 'teste-1459' ===\n\n";

// 1. Verifica eventos com timestamp próximo a 14:59 (assumindo que foi enviado hoje)
echo "1. Buscando eventos criados entre 14:55 e 15:05 de hoje:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND DATE(ce.created_at) = CURDATE()
    AND HOUR(ce.created_at) = 14
    AND MINUTE(ce.created_at) BETWEEN 55 AND 59
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
    )
    ORDER BY ce.created_at DESC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO encontrado entre 14:55 e 14:59 de hoje\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s):\n";
    foreach ($events as $event) {
        echo "   - Created At: {$event['created_at']}\n";
        echo "     Text: " . ($event['text'] ?: 'NULL') . "\n";
        echo "     From: " . ($event['from_field'] ?: 'NULL') . "\n";
        echo "\n";
    }
}

// 2. Verifica todos os eventos de hoje do Charles Dietrich
echo "\n2. Todos os eventos de hoje do Charles Dietrich (554796164699):\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND DATE(ce.created_at) = CURDATE()
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
    )
    ORDER BY ce.created_at DESC
");
$stmt2->execute();
$todayEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($todayEvents)) {
    echo "   ❌ NENHUM EVENTO encontrado hoje\n";
} else {
    echo "   ✅ Encontrados " . count($todayEvents) . " evento(s) hoje:\n";
    foreach ($todayEvents as $event) {
        $time = date('H:i', strtotime($event['created_at']));
        echo "   - {$time}: " . ($event['text'] ? substr($event['text'], 0, 50) : 'NULL') . "\n";
    }
}

// 3. Verifica se há eventos com texto similar (teste-14XX)
echo "\n3. Buscando eventos com padrão 'teste-14XX' de hoje:\n";
$stmt3 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND DATE(ce.created_at) = CURDATE()
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) LIKE 'teste-14%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) LIKE 'teste-14%'
    )
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
    )
    ORDER BY ce.created_at DESC
");
$stmt3->execute();
$teste14Events = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($teste14Events)) {
    echo "   ❌ NENHUM EVENTO encontrado com padrão 'teste-14XX'\n";
} else {
    echo "   ✅ Encontrados " . count($teste14Events) . " evento(s):\n";
    foreach ($teste14Events as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        echo "   - {$time}: {$event['text']}\n";
    }
}

echo "\n=== Conclusão ===\n";
if (empty($teste14Events)) {
    echo "❌ A mensagem 'teste-1459' NÃO foi recebida pelo webhook.\n";
    echo "   Possíveis causas:\n";
    echo "   1. A mensagem não foi enviada pelo WhatsApp\n";
    echo "   2. O webhook não foi chamado pelo gateway\n";
    echo "   3. A mensagem foi enviada mas não chegou ao webhook (problema de rede/configuração)\n";
    echo "   4. A mensagem foi enviada mas o gateway não processou corretamente\n";
} else {
    echo "✅ Mensagens com padrão 'teste-14XX' foram encontradas, mas 'teste-1459' não.\n";
    echo "   Verifique se a mensagem foi realmente enviada.\n";
}

echo "\n=== Fim da verificação ===\n";

