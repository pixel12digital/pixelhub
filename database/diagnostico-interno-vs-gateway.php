<?php

/**
 * Script para diagnosticar se o problema é interno ou no gateway
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

echo "=== Diagnóstico: Problema Interno vs Gateway ===\n\n";

// 1. Verifica se há eventos que chegaram mas falharam no processamento
echo "1. Verificando eventos com status 'failed' ou 'queued' recentes:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.status,
        ce.created_at,
        TIMESTAMPDIFF(MINUTE, ce.created_at, NOW()) as minutes_ago,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.status IN ('failed', 'queued')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$failedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($failedEvents)) {
    echo "   ✅ Nenhum evento falhou no processamento recentemente\n";
    echo "   → Isso indica que o problema NÃO é no processamento interno\n\n";
} else {
    echo "   ⚠️  Encontrados " . count($failedEvents) . " evento(s) com problemas:\n";
    foreach ($failedEvents as $event) {
        echo "   - {$event['created_at']} ({$event['minutes_ago']} min atrás)\n";
        echo "     Status: {$event['status']}\n";
        echo "     Error: " . ($event['error_reason'] ?: 'NULL') . "\n";
        echo "     Text: " . substr(($event['text'] ?: 'NULL'), 0, 50) . "\n\n";
    }
    echo "   → Isso indica que eventos CHEGARAM mas falharam no processamento\n\n";
}

// 2. Verifica última vez que o webhook foi chamado (através de logs no banco)
echo "2. Verificando última vez que webhook foi chamado:\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        TIMESTAMPDIFF(MINUTE, ce.created_at, NOW()) as minutes_ago,
        ce.status,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 1
");
$stmt2->execute();
$lastEvent = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($lastEvent) {
    $minutesAgo = $lastEvent['minutes_ago'];
    echo "   Último evento recebido: {$lastEvent['created_at']} ({$minutesAgo} minutos atrás)\n";
    echo "   Text: " . substr(($lastEvent['text'] ?: 'NULL'), 0, 50) . "\n";
    echo "   Status: {$lastEvent['status']}\n\n";
    
    if ($minutesAgo > 30) {
        echo "   ⚠️  Nenhum evento recebido há mais de 30 minutos\n";
        echo "   → Isso indica que o GATEWAY não está enviando webhooks\n\n";
    } else {
        echo "   ✅ Eventos foram recebidos recentemente\n";
        echo "   → O webhook está funcionando, mas pode ter parado agora\n\n";
    }
} else {
    echo "   ❌ Nenhum evento encontrado no banco\n";
    echo "   → O webhook nunca foi chamado ou eventos não foram salvos\n\n";
}

// 3. Verifica se há problemas de validação (secret, JSON, etc)
echo "3. Verificando possíveis problemas de validação:\n";
$webhookSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET');
if (empty($webhookSecret)) {
    echo "   ⚠️  PIXELHUB_WHATSAPP_WEBHOOK_SECRET não está configurado\n";
    echo "   → Webhook aceita requisições sem validação de secret\n\n";
} else {
    echo "   ✅ PIXELHUB_WHATSAPP_WEBHOOK_SECRET está configurado\n";
    echo "   → Webhook valida secret nas requisições\n";
    echo "   → Se gateway não enviar secret correto, webhook retorna 403\n\n";
}

// 4. Verifica URL do webhook
$webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL');
echo "4. URL do webhook configurada:\n";
if (empty($webhookUrl)) {
    echo "   ⚠️  PIXELHUB_WHATSAPP_WEBHOOK_URL não está configurada\n";
    echo "   → Gateway pode não saber para onde enviar webhooks\n\n";
} else {
    echo "   ✅ URL: {$webhookUrl}\n";
    echo "   → Verifique se esta URL está acessível do gateway\n\n";
}

// 5. Verifica se há eventos que chegaram mas não foram salvos
echo "5. Análise de padrão de recebimento:\n";
$stmt3 = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as minute,
        COUNT(*) as count,
        GROUP_CONCAT(DISTINCT status SEPARATOR ', ') as statuses
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY minute
    ORDER BY minute DESC
    LIMIT 20
");
$stmt3->execute();
$pattern = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($pattern)) {
    echo "   ❌ Nenhum evento nos últimos 24 horas\n";
    echo "   → Webhook nunca funcionou ou parou há muito tempo\n\n";
} else {
    echo "   Padrão de recebimento (últimas 20 ocorrências):\n";
    $lastMinute = null;
    $gapFound = false;
    
    foreach ($pattern as $row) {
        $minute = $row['minute'];
        $count = $row['count'];
        $statuses = $row['statuses'];
        
        if ($lastMinute) {
            $gap = (strtotime($lastMinute) - strtotime($minute)) / 60;
            if ($gap > 5) {
                echo "   ⚠️  GAP de {$gap} minutos entre {$minute} e {$lastMinute}\n";
                $gapFound = true;
            }
        }
        
        echo "   - {$minute}: {$count} evento(s) | Status: {$statuses}\n";
        $lastMinute = $minute;
    }
    
    if (!$gapFound) {
        echo "\n   ✅ Padrão contínuo de recebimento (sem gaps grandes)\n";
    } else {
        echo "\n   ⚠️  Gaps encontrados indicam que gateway parou de enviar temporariamente\n";
    }
    echo "\n";
}

// 6. Conclusão
echo "=== CONCLUSÃO ===\n\n";

$isInternal = false;
$isGateway = false;
$evidence = [];

if (empty($failedEvents) && $lastEvent && $lastEvent['minutes_ago'] > 30) {
    $isGateway = true;
    $evidence[] = "Nenhum evento recebido há mais de 30 minutos";
    $evidence[] = "Nenhum evento falhou no processamento (webhook não está sendo chamado)";
}

if (!empty($failedEvents)) {
    $isInternal = true;
    $evidence[] = "Eventos chegaram mas falharam no processamento";
}

if (empty($lastEvent)) {
    $isGateway = true;
    $evidence[] = "Nenhum evento encontrado no banco (webhook nunca foi chamado)";
}

if ($isInternal) {
    echo "🔴 PROBLEMA INTERNO (no código/servidor):\n";
    echo "   - Eventos estão chegando ao webhook\n";
    echo "   - Mas estão falhando no processamento\n";
    echo "   - Verifique logs de erro e código de processamento\n\n";
} elseif ($isGateway) {
    echo "🟡 PROBLEMA NO GATEWAY:\n";
    echo "   - Webhook não está sendo chamado pelo gateway\n";
    echo "   - Nenhum evento chegou recentemente\n";
    echo "   - Gateway pode ter parado de enviar webhooks\n\n";
} else {
    echo "🟢 SITUAÇÃO NORMAL:\n";
    echo "   - Webhook está funcionando\n";
    echo "   - Eventos estão sendo recebidos\n";
    echo "   - Se mensagens não aparecem, problema pode ser na exibição\n\n";
}

if (!empty($evidence)) {
    echo "Evidências:\n";
    foreach ($evidence as $e) {
        echo "   - {$e}\n";
    }
}

echo "\n=== Fim do diagnóstico ===\n";

