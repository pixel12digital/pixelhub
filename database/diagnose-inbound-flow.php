<?php

/**
 * Diagnóstico do Fluxo Inbound - WhatsApp Gateway → PixelHub
 * 
 * Script para investigar por que mensagens não aparecem na Inbox
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

echo "═══════════════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO: Fluxo Inbound WhatsApp Gateway → PixelHub\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$db = DB::getConnection();

// 1. Verifica configuração de secret
echo "1. VERIFICAÇÃO DE CONFIGURAÇÃO\n";
echo str_repeat("-", 60) . "\n";
$webhookSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET');
if (empty($webhookSecret)) {
    echo "⚠ AVISO: PIXELHUB_WHATSAPP_WEBHOOK_SECRET não configurado\n";
    echo "  → Webhook aceita requisições SEM validação de secret\n";
    echo "  → Se gateway enviar secret, será rejeitado (403)\n\n";
} else {
    echo "✓ PIXELHUB_WHATSAPP_WEBHOOK_SECRET configurado\n";
    echo "  → Webhook valida header X-Gateway-Secret ou X-Webhook-Secret\n\n";
}

// 2. Verifica eventos recebidos nas últimas 24h
echo "2. EVENTOS RECEBIDOS (últimas 24 horas)\n";
echo str_repeat("-", 60) . "\n";
try {
    $stmt = $db->query("
        SELECT 
            event_id,
            event_type,
            source_system,
            status,
            tenant_id,
            created_at,
            error_message
        FROM communication_events
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $recentEvents = $stmt->fetchAll();
    
    if (empty($recentEvents)) {
        echo "❌ NENHUM evento recebido nas últimas 24 horas!\n\n";
    } else {
        echo "✓ " . count($recentEvents) . " evento(s) encontrado(s):\n\n";
        foreach ($recentEvents as $event) {
            $statusIcon = $event['status'] === 'processed' ? '✓' : ($event['status'] === 'failed' ? '✗' : '⏳');
            echo "  {$statusIcon} [{$event['source_system']}] {$event['event_type']} - Status: {$event['status']}\n";
            echo "     Event ID: {$event['event_id']}\n";
            echo "     Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
            echo "     Criado em: {$event['created_at']}\n";
            if (!empty($event['error_message'])) {
                echo "     ⚠ ERRO: " . substr($event['error_message'], 0, 100) . "\n";
            }
            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ ERRO ao buscar eventos: " . $e->getMessage() . "\n\n";
}

// 3. Verifica eventos whatsapp.inbound.message especificamente
echo "3. EVENTOS whatsapp.inbound.message (últimas 24 horas)\n";
echo str_repeat("-", 60) . "\n";
try {
    $stmt = $db->query("
        SELECT 
            event_id,
            source_system,
            status,
            tenant_id,
            created_at,
            payload
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $inboundMessages = $stmt->fetchAll();
    
    if (empty($inboundMessages)) {
        echo "❌ NENHUM evento whatsapp.inbound.message nas últimas 24 horas!\n\n";
        echo "  ⚠ Isso indica que:\n";
        echo "     - Gateway não está enviando webhook OU\n";
        echo "     - Webhook está sendo rejeitado (403/500) OU\n";
        echo "     - Payload está sendo recebido mas não está sendo processado\n\n";
    } else {
        echo "✓ " . count($inboundMessages) . " mensagem(s) inbound encontrada(s):\n\n";
        foreach ($inboundMessages as $msg) {
            $payload = json_decode($msg['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
            $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? 'N/A';
            $textPreview = strlen($text) > 50 ? substr($text, 0, 50) . '...' : $text;
            
            echo "  📱 From: {$from}\n";
            echo "     Texto: {$textPreview}\n";
            echo "     Status: {$msg['status']}\n";
            echo "     Tenant ID: " . ($msg['tenant_id'] ?? 'NULL') . "\n";
            echo "     Criado em: {$msg['created_at']}\n\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ ERRO ao buscar mensagens inbound: " . $e->getMessage() . "\n\n";
}

// 4. Verifica conversas criadas
echo "4. CONVERSAS CRIADAS (últimas 24 horas)\n";
echo str_repeat("-", 60) . "\n";
try {
    $checkStmt = $db->query("SHOW TABLES LIKE 'conversations'");
    if ($checkStmt->rowCount() === 0) {
        echo "⚠ Tabela 'conversations' não existe (migration não executada?)\n\n";
    } else {
        $stmt = $db->query("
            SELECT 
                id,
                conversation_key,
                channel_type,
                contact_external_id,
                contact_name,
                status,
                message_count,
                created_at
            FROM conversations
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $conversations = $stmt->fetchAll();
        
        if (empty($conversations)) {
            echo "❌ NENHUMA conversa criada nas últimas 24 horas!\n\n";
            echo "  ⚠ Isso indica que ConversationService não está sendo chamado\n\n";
        } else {
            echo "✓ " . count($conversations) . " conversa(s) criada(s):\n\n";
            foreach ($conversations as $conv) {
                echo "  💬 Key: {$conv['conversation_key']}\n";
                echo "     Canal: {$conv['channel_type']}\n";
                echo "     Contato: {$conv['contact_external_id']} ({$conv['contact_name']})\n";
                echo "     Status: {$conv['status']}\n";
                echo "     Mensagens: {$conv['message_count']}\n";
                echo "     Criada em: {$conv['created_at']}\n\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "✗ ERRO ao buscar conversas: " . $e->getMessage() . "\n\n";
}

// 5. Verifica canais configurados
echo "5. CANAIS CONFIGURADOS (tenant_message_channels)\n";
echo str_repeat("-", 60) . "\n";
try {
    $stmt = $db->query("
        SELECT 
            tmc.id,
            tmc.tenant_id,
            tmc.provider,
            tmc.channel_id,
            tmc.is_enabled,
            tmc.webhook_configured,
            t.name as tenant_name
        FROM tenant_message_channels tmc
        LEFT JOIN tenants t ON tmc.tenant_id = t.id
        WHERE tmc.provider = 'wpp_gateway'
        ORDER BY tmc.created_at DESC
    ");
    $channels = $stmt->fetchAll();
    
    if (empty($channels)) {
        echo "❌ NENHUM canal WhatsApp configurado!\n\n";
        echo "  ⚠ Isso explica por que tenant_id não é resolvido\n";
        echo "     Mas eventos ainda deveriam ser ingeridos (com tenant_id = NULL)\n\n";
    } else {
        echo "✓ " . count($channels) . " canal(is) configurado(s):\n\n";
        foreach ($channels as $channel) {
            $enabledIcon = $channel['is_enabled'] ? '✓' : '✗';
            $webhookIcon = $channel['webhook_configured'] ? '✓' : '✗';
            echo "  {$enabledIcon} Channel: {$channel['channel_id']}\n";
            echo "     Tenant: {$channel['tenant_name']} (ID: {$channel['tenant_id']})\n";
            echo "     Habilitado: " . ($channel['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
            echo "     Webhook configurado: " . ($channel['webhook_configured'] ? 'SIM' : 'NÃO') . "\n\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ ERRO ao buscar canais: " . $e->getMessage() . "\n\n";
}

// 6. Verifica logs de erro do PHP (últimas linhas)
echo "6. LOGS DE ERRO RECENTES\n";
echo str_repeat("-", 60) . "\n";
$logFile = __DIR__ . '/../logs/pixelhub.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);
    $recentLogs = array_filter(array_slice($logLines, -50), function($line) {
        return stripos($line, 'whatsapp') !== false || 
               stripos($line, 'webhook') !== false ||
               stripos($line, 'error') !== false ||
               stripos($line, 'exception') !== false;
    });
    
    if (empty($recentLogs)) {
        echo "⚠ Nenhum log relevante encontrado (últimas 50 linhas)\n\n";
    } else {
        echo "Últimos logs relevantes:\n\n";
        foreach (array_slice($recentLogs, -10) as $log) {
            echo "  " . trim($log) . "\n";
        }
        echo "\n";
    }
} else {
    echo "⚠ Arquivo de log não encontrado: {$logFile}\n\n";
}

// 7. Verifica endpoint acessível
echo "7. VERIFICAÇÃO DO ENDPOINT\n";
echo str_repeat("-", 60) . "\n";
$baseUrl = Env::get('BASE_URL', 'https://hub.pixel12digital.com.br');
$webhookUrl = $baseUrl . '/api/whatsapp/webhook';
echo "URL esperada do webhook: {$webhookUrl}\n\n";
echo "⚠ IMPORTANTE: Verifique no gateway se está configurado:\n";
echo "   URL: {$webhookUrl}\n";
if (!empty($webhookSecret)) {
    echo "   Header: X-Gateway-Secret = {$webhookSecret}\n";
} else {
    echo "   Header: X-Gateway-Secret (não configurado no Hub)\n";
}
echo "\n";

// 8. Resumo e diagnóstico
echo "═══════════════════════════════════════════════════════════════\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$eventsCount = count($recentEvents ?? []);
$inboundCount = count($inboundMessages ?? []);
$conversationsCount = isset($conversations) ? count($conversations) : 0;

echo "Eventos recebidos (24h): {$eventsCount}\n";
echo "Mensagens inbound (24h): {$inboundCount}\n";
echo "Conversas criadas (24h): {$conversationsCount}\n\n";

if ($inboundCount === 0) {
    echo "🔴 PROBLEMA IDENTIFICADO: Nenhuma mensagem inbound recebida\n\n";
    echo "Possíveis causas:\n";
    echo "1. Gateway não está enviando webhook para {$webhookUrl}\n";
    echo "2. Gateway está enviando para URL errada (localhost, outro domínio)\n";
    echo "3. Secret não configurado/enviado (rejeitado com 403)\n";
    echo "4. Webhook retorna erro 500 (ver logs acima)\n";
    echo "5. Payload não está no formato esperado\n\n";
    
    echo "AÇÕES RECOMENDADAS:\n";
    echo "1. Verificar logs do gateway (VPS) para ver tentativas de envio\n";
    echo "2. Verificar se webhook está configurado no gateway\n";
    echo "3. Testar endpoint manualmente: curl -X POST {$webhookUrl} -H 'Content-Type: application/json' -d '{\"event\":\"message\",\"channel\":\"test\",\"message\":{\"from\":\"test\",\"text\":\"test\"}}'\n";
} elseif ($inboundCount > 0 && $conversationsCount === 0) {
    echo "⚠ PROBLEMA IDENTIFICADO: Mensagens chegam mas conversas não são criadas\n\n";
    echo "Possíveis causas:\n";
    echo "1. ConversationService::resolveConversation() está falhando silenciosamente\n";
    echo "2. Payload não está no formato esperado pelo extractChannelInfo()\n";
    echo "3. Tabela conversations não existe (migration não executada)\n\n";
    
    echo "AÇÕES RECOMENDADAS:\n";
    echo "1. Verificar último evento inbound e seu payload\n";
    echo "2. Verificar logs do ConversationService\n";
    echo "3. Executar migration se necessário\n";
} else {
    echo "✓ Fluxo parece estar funcionando\n";
    echo "  → Mensagens estão chegando\n";
    echo "  → Conversas estão sendo criadas\n";
    echo "  → Verifique a UI para garantir que está lendo de conversations\n";
}

echo "\n";

