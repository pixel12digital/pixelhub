<?php

/**
 * Script para monitorar webhook em tempo real
 * Verifica se há problemas que podem estar impedindo o recebimento de mensagens
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

echo "=== Monitoramento do Webhook em Tempo Real ===\n\n";

// 1. Verifica eventos muito recentes (últimos 5 minutos)
echo "1. Eventos dos últimos 5 minutos:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.status,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents)) {
    echo "   ⚠️  NENHUM EVENTO recebido nos últimos 5 minutos\n";
    echo "   Isso indica que o webhook pode não estar recebendo mensagens\n";
} else {
    echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
    foreach ($recentEvents as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        $text = substr(($event['text'] ?: 'NULL'), 0, 50);
        echo "   - {$time} | Status: {$event['status']} | Text: {$text}\n";
    }
}

// 2. Verifica se há eventos em 'queued' há muito tempo (pode indicar problema de processamento)
echo "\n2. Eventos em 'queued' há mais de 1 minuto (pode indicar problema):\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        TIMESTAMPDIFF(SECOND, ce.created_at, NOW()) as seconds_queued
    FROM communication_events ce
    WHERE ce.status = 'queued'
    AND ce.created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt2->execute();
$queuedEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($queuedEvents)) {
    echo "   ✅ Nenhum evento preso em 'queued'\n";
} else {
    echo "   ⚠️  Encontrados " . count($queuedEvents) . " evento(s) preso(s) em 'queued':\n";
    foreach ($queuedEvents as $event) {
        $minutes = round($event['seconds_queued'] / 60, 1);
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Criado há: {$minutes} minuto(s)\n";
        echo "     Created At: {$event['created_at']}\n";
        echo "\n";
    }
    echo "   ⚠️  ATENÇÃO: Eventos presos em 'queued' podem indicar problema de processamento\n";
}

// 3. Verifica taxa de eventos por minuto (últimos 10 minutos)
echo "\n3. Taxa de eventos por minuto (últimos 10 minutos):\n";
$stmt3 = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%H:%i') as minute,
        COUNT(*) as count,
        GROUP_CONCAT(status SEPARATOR ', ') as statuses
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    GROUP BY minute
    ORDER BY minute DESC
");
$stmt3->execute();
$rateEvents = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($rateEvents)) {
    echo "   ⚠️  NENHUM EVENTO nos últimos 10 minutos\n";
} else {
    foreach ($rateEvents as $rate) {
        echo "   - {$rate['minute']}: {$rate['count']} evento(s) | Status: {$rate['statuses']}\n";
    }
}

// 4. Verifica se há problemas de conexão ou timeout
echo "\n4. Verificando configurações do servidor:\n";
echo "   - max_execution_time: " . ini_get('max_execution_time') . " segundos\n";
echo "   - memory_limit: " . ini_get('memory_limit') . "\n";
echo "   - post_max_size: " . ini_get('post_max_size') . "\n";
echo "   - upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";

// 5. Verifica última mensagem recebida do Charles Dietrich
echo "\n5. Última mensagem recebida do Charles Dietrich:\n";
$stmt4 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        TIMESTAMPDIFF(MINUTE, ce.created_at, NOW()) as minutes_ago
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 1
");
$stmt4->execute();
$lastMessage = $stmt4->fetch(PDO::FETCH_ASSOC);

if ($lastMessage) {
    $minutesAgo = $lastMessage['minutes_ago'];
    echo "   - Text: " . ($lastMessage['text'] ?: 'NULL') . "\n";
    echo "   - Recebida há: {$minutesAgo} minuto(s)\n";
    echo "   - Created At: {$lastMessage['created_at']}\n";
    
    if ($minutesAgo > 5) {
        echo "\n   ⚠️  ATENÇÃO: Nenhuma mensagem recebida há mais de 5 minutos\n";
        echo "   Possíveis causas:\n";
        echo "   1. O gateway parou de enviar webhooks\n";
        echo "   2. Há um problema de rede entre o gateway e o servidor\n";
        echo "   3. O webhook está retornando erro e o gateway parou de tentar\n";
        echo "   4. Há um problema de processamento que está fazendo o webhook falhar\n";
    }
} else {
    echo "   ❌ Nenhuma mensagem encontrada\n";
}

echo "\n=== Recomendações ===\n";
echo "1. Verifique os logs do servidor (error_log) para erros recentes\n";
echo "2. Verifique os logs do gateway para ver se está tentando enviar webhooks\n";
echo "3. Teste enviando uma nova mensagem e monitore em tempo real\n";
echo "4. Verifique se há algum problema de timeout ou memória\n";

echo "\n=== Fim do monitoramento ===\n";
echo "Execute este script periodicamente para monitorar o webhook em tempo real.\n";

