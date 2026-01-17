<?php
/**
 * Investigação: Mensagem no WhatsApp Web mas não no banco
 * 
 * Caso: "Envio0907" enviada hoje 09:08 de 554796474223 para 554797309525
 * sessão: pixel12digital
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== INVESTIGAÇÃO: WEBHOOK NÃO GRAVOU MENSAGEM HOJE ===\n\n";

$db = DB::getConnection();
$today = date('Y-m-d'); // 2026-01-17
$fromNumber = '554796474223'; // +55 47 9647-4223
$toNumber = '554797309525';
$content = 'Envio0907';
$sessionId = 'pixel12digital';

echo "Caso investigado:\n";
echo "  - Mensagem: '{$content}'\n";
echo "  - De: {$fromNumber}\n";
echo "  - Para: {$toNumber}\n";
echo "  - Sessão: {$sessionId}\n";
echo "  - Data/Hora no WhatsApp: 17/01/2026 09:08\n\n";

// ==========================================
// 1. Verificar se mensagem está no banco (qualquer data)
// ==========================================

echo "1. BUSCANDO MENSAGEM NO BANCO (qualquer data):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text
    FROM communication_events ce
    WHERE (
        JSON_EXTRACT(ce.payload, '$.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
    )
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$patternContent = "%{$content}%";
$patternFrom = "%{$fromNumber}%";
$patternTo = "%{$toNumber}%";

$stmt->execute([$patternContent, $patternContent, $patternFrom, $patternTo]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matches)) {
    echo "❌ Mensagem NÃO encontrada no banco (qualquer data)\n\n";
} else {
    echo "✅ Encontrada(s) " . count($matches) . " mensagem(ns):\n\n";
    foreach ($matches as $idx => $msg) {
        echo sprintf(
            "[%d] ID=%d | created_at=%s | tenant_id=%s | channel_id=%s | from=%s | to=%s\n",
            $idx + 1,
            $msg['id'],
            $msg['created_at'],
            $msg['tenant_id'] ?: 'NULL',
            $msg['meta_channel'] ?: 'NULL',
            substr($msg['p_from'] ?: 'N/A', 0, 25),
            substr($msg['p_to'] ?: 'N/A', 0, 25)
        );
    }
    echo "\n";
}

// ==========================================
// 2. Verificar eventos HOJE de qualquer tipo
// ==========================================

echo "2. EVENTOS HOJE (qualquer tipo/sistema):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$stmt->execute([$today]);
$todayEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($todayEvents)) {
    echo "❌ Nenhum evento criado hoje ({$today})\n";
    echo "   ⚠️  PROBLEMA: Webhook não está gravando eventos hoje!\n\n";
} else {
    echo "✅ Total de eventos hoje: " . count($todayEvents) . "\n\n";
    
    // Agrupa por source_system e event_type
    $bySystem = [];
    foreach ($todayEvents as $event) {
        $system = $event['source_system'] ?: 'NULL';
        $type = $event['event_type'] ?: 'NULL';
        $key = "{$system}::{$type}";
        if (!isset($bySystem[$key])) {
            $bySystem[$key] = 0;
        }
        $bySystem[$key]++;
    }
    
    echo "   Resumo por sistema/tipo:\n";
    foreach ($bySystem as $key => $count) {
        echo "      {$key}: {$count} evento(s)\n";
    }
    echo "\n";
    
    // Mostra alguns exemplos
    echo "   Primeiros 10 eventos:\n";
    foreach (array_slice($todayEvents, 0, 10) as $idx => $event) {
        echo sprintf(
            "      [%d] ID=%d | %s | type=%s | tenant_id=%s | channel_id=%s\n",
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'] ?: 'NULL',
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL'
        );
    }
    echo "\n";
}

// ==========================================
// 3. Verificar se webhook está recebendo eventos do wpp_gateway
// ==========================================

echo "3. EVENTOS DO WPP_GATEWAY HOJE:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$stmt->execute([$today]);
$wppEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($wppEvents)) {
    echo "❌ Nenhum evento do wpp_gateway hoje\n";
    echo "   ⚠️  PROBLEMA CRÍTICO: Webhook não está recebendo/processando eventos!\n\n";
} else {
    echo "✅ Total de eventos do wpp_gateway hoje: " . count($wppEvents) . "\n\n";
    
    // Verifica se há eventos da sessão pixel12digital
    $pixel12Events = array_filter($wppEvents, function($e) use ($sessionId) {
        return $e['meta_channel'] === $sessionId;
    });
    
    if (empty($pixel12Events)) {
        echo "   ⚠️  Nenhum evento da sessão '{$sessionId}' hoje\n";
        echo "      Mas existem eventos de outras sessões\n\n";
    } else {
        echo "   ✅ Eventos da sessão '{$sessionId}': " . count($pixel12Events) . "\n\n";
    }
    
    // Mostra últimos eventos
    echo "   Últimos 10 eventos:\n";
    foreach (array_slice($wppEvents, 0, 10) as $idx => $event) {
        $from = substr($event['p_from'] ?: 'N/A', 0, 20);
        $to = substr($event['p_to'] ?: 'N/A', 0, 20);
        echo sprintf(
            "      [%d] ID=%d | %s | type=%s | tenant_id=%s | channel_id=%s | from=%s | to=%s\n",
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'] ?: 'NULL',
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL',
            $from,
            $to
        );
    }
    echo "\n";
}

// ==========================================
// 4. Verificar conversas criadas/atualizadas hoje
// ==========================================

echo "4. CONVERSAS CRIADAS/ATUALIZADAS HOJE:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, conversation_key, tenant_id, channel_id, contact_external_id, created_at, updated_at
    FROM conversations
    WHERE DATE(created_at) = ? OR DATE(updated_at) = ?
    ORDER BY COALESCE(updated_at, created_at) DESC
    LIMIT 20
");

$stmt->execute([$today, $today]);
$todayConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($todayConvs)) {
    echo "❌ Nenhuma conversa criada/atualizada hoje\n\n";
} else {
    echo "✅ Total de conversas: " . count($todayConvs) . "\n\n";
    foreach ($todayConvs as $idx => $conv) {
        echo sprintf(
            "[%d] ID=%d | key=%s | tenant_id=%s | channel_id=%s | contact=%s | created=%s | updated=%s\n",
            $idx + 1,
            $conv['id'],
            $conv['conversation_key'] ?: 'NULL',
            $conv['tenant_id'] ?: 'NULL',
            $conv['channel_id'] ?: 'NULL',
            $conv['contact_external_id'],
            $conv['created_at'],
            $conv['updated_at'] ?: 'NULL'
        );
    }
    echo "\n";
}

// ==========================================
// 5. DIAGNÓSTICO FINAL
// ==========================================

echo str_repeat("=", 80) . "\n";
echo "DIAGNÓSTICO:\n";
echo str_repeat("=", 80) . "\n\n";

$hasTodayEvents = !empty($todayEvents);
$hasWppEvents = !empty($wppEvents);
$hasMessage = !empty($matches);

if (!$hasTodayEvents) {
    echo "❌ PROBLEMA CRÍTICO: Nenhum evento foi gravado hoje ({$today})\n";
    echo "   Causa provável:\n";
    echo "   1. Webhook não está sendo chamado pelo gateway\n";
    echo "   2. Webhook está sendo chamado mas retornando erro (500/400)\n";
    echo "   3. Webhook está processando mas não grava (exceção no EventIngestionService)\n\n";
    
    echo "   AÇÕES:\n";
    echo "   1. Verificar logs do servidor web (access.log, error.log)\n";
    echo "   2. Verificar logs PHP (error_log, application.log)\n";
    echo "   3. Testar endpoint manualmente: curl -X POST https://[DOMINIO]/api/whatsapp/webhook\n";
    echo "   4. Verificar se gateway está configurado para enviar webhook para URL correta\n\n";
} elseif (!$hasWppEvents) {
    echo "⚠️  PROBLEMA: Existem eventos hoje, mas NENHUM do wpp_gateway\n";
    echo "   Isso indica que o webhook do WhatsApp não está chegando\n\n";
    
    echo "   AÇÕES:\n";
    echo "   1. Verificar configuração do webhook no gateway\n";
    echo "   2. Verificar URL do webhook: https://[DOMINIO]/api/whatsapp/webhook\n";
    echo "   3. Verificar se gateway está enviando webhooks\n\n";
} elseif (!$hasMessage) {
    echo "⚠️  PROBLEMA: Webhook está funcionando (há eventos hoje), mas a mensagem específica não foi gravada\n";
    echo "   Possíveis causas:\n";
    echo "   1. Payload do webhook não contém 'Envio0907' no formato esperado\n";
    echo "   2. Mensagem foi gravada mas com conteúdo/números diferentes\n";
    echo "   3. Mensagem foi gravada mas em outro tenant/sessão\n\n";
    
    echo "   AÇÕES:\n";
    echo "   1. Verificar payload bruto do webhook nos logs\n";
    echo "   2. Verificar se mensagem foi gravada com números normalizados diferentes\n";
    echo "   3. Buscar eventos com from/to próximos (variações de número)\n\n";
} else {
    echo "✅ Mensagem encontrada no banco!\n";
    echo "   Mas não está aparecendo na UI ou foi gravada em data diferente\n\n";
}

echo "\n";

