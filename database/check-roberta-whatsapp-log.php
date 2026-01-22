<?php

/**
 * Script para verificar log de WhatsApp da Roberta (tenant_id = 2)
 * 
 * Uso: php database/check-roberta-whatsapp-log.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();
$tenantId = 2;

echo "=== Verificando logs de WhatsApp para tenant_id = {$tenantId} (Roberta) ===\n\n";

// Busca registros em whatsapp_generic_logs
echo "1. Registros em whatsapp_generic_logs:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        template_id,
        phone,
        LEFT(message, 100) as message_preview,
        sent_at,
        created_at
    FROM whatsapp_generic_logs
    WHERE tenant_id = ?
    ORDER BY sent_at DESC, created_at DESC
    LIMIT 10
");
$stmt->execute([$tenantId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "   ❌ NENHUM REGISTRO ENCONTRADO\n";
} else {
    echo "   ✅ Encontrados " . count($logs) . " registro(s):\n";
    foreach ($logs as $log) {
        echo "   - ID: {$log['id']}\n";
        echo "     Template ID: " . ($log['template_id'] ?? 'NULL') . "\n";
        echo "     Phone: {$log['phone']}\n";
        echo "     Message: {$log['message_preview']}...\n";
        echo "     sent_at: " . ($log['sent_at'] ?? 'NULL') . "\n";
        echo "     created_at: {$log['created_at']}\n";
        echo "\n";
    }
}

// Busca registros em billing_notifications
echo "\n2. Registros em billing_notifications:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        invoice_id,
        template as stage,
        LEFT(message, 100) as message_preview,
        sent_at,
        created_at
    FROM billing_notifications
    WHERE tenant_id = ?
    ORDER BY sent_at DESC, created_at DESC
    LIMIT 10
");
$stmt->execute([$tenantId]);
$billingLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($billingLogs)) {
    echo "   ℹ️  Nenhum registro de cobrança\n";
} else {
    echo "   ✅ Encontrados " . count($billingLogs) . " registro(s):\n";
    foreach ($billingLogs as $log) {
        echo "   - ID: {$log['id']}\n";
        echo "     Invoice ID: " . ($log['invoice_id'] ?? 'NULL') . "\n";
        echo "     Stage: {$log['stage']}\n";
        echo "     Message: {$log['message_preview']}...\n";
        echo "     sent_at: " . ($log['sent_at'] ?? 'NULL') . "\n";
        echo "     created_at: {$log['created_at']}\n";
        echo "\n";
    }
}

// Testa WhatsAppHistoryService
echo "\n3. Testando WhatsAppHistoryService::getTimelineByTenant({$tenantId}):\n";
require_once __DIR__ . '/../src/Services/WhatsAppHistoryService.php';
$timeline = \PixelHub\Services\WhatsAppHistoryService::getTimelineByTenant($tenantId, 10);

if (empty($timeline)) {
    echo "   ❌ Timeline vazia\n";
} else {
    echo "   ✅ Timeline com " . count($timeline) . " registro(s):\n";
    foreach ($timeline as $item) {
        echo "   - Source: {$item['source']}\n";
        echo "     sent_at: " . ($item['sent_at'] ?? 'NULL') . "\n";
        echo "     Template: " . ($item['template_name'] ?? ($item['template_id'] === null ? 'NULL' : 'ID ' . $item['template_id'])) . "\n";
        echo "     Description: {$item['description']}\n";
        echo "\n";
    }
}

echo "\n=== Fim da verificação ===\n";

