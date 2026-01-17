<?php
/**
 * Busca e confirma mensagens "envio0810" no banco de dados
 * 
 * Busca mensagens que contenham "envio0810" e permite confirmá-las
 * como enviadas no sistema.
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

echo "=== BUSCA E CONFIRMAÇÃO: MENSAGENS 'envio0810' ===\n\n";

$db = DB::getConnection();
$searchTerm = 'envio0810';

// ==========================================
// 1. BUSCAR MENSAGENS EM communication_events
// ==========================================

echo "1. Buscando mensagens em communication_events:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) AS p_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) AS p_msg_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.payload
    FROM communication_events ce
    WHERE (
        JSON_EXTRACT(ce.payload, '$.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.body') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.body') LIKE ?
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 100
");

$pattern = "%{$searchTerm}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado com conteúdo '{$searchTerm}'\n\n";
    
    // Busca variações
    echo "2. Buscando variações (envio 0810, Envio0810, etc):\n";
    echo str_repeat("-", 80) . "\n";
    
    $variations = ['envio 0810', 'Envio0810', 'ENVIO0810', 'Envio 0810'];
    $allEvents = [];
    
    foreach ($variations as $variation) {
        $pattern = "%{$variation}%";
        $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results)) {
            echo "   ✅ Encontrado com '{$variation}': " . count($results) . " evento(s)\n";
            $allEvents = array_merge($allEvents, $results);
        }
    }
    
    // Remove duplicatas
    $uniqueEvents = [];
    $seenIds = [];
    foreach ($allEvents as $event) {
        if (!in_array($event['event_id'], $seenIds)) {
            $uniqueEvents[] = $event;
            $seenIds[] = $event['event_id'];
        }
    }
    
    if (!empty($uniqueEvents)) {
        $events = $uniqueEvents;
        echo "\n   ✅ Total de eventos únicos: " . count($events) . "\n\n";
    } else {
        echo "\n   ❌ Nenhuma variação encontrada\n\n";
        exit(0);
    }
} else {
    echo "✅ Encontrados " . count($events) . " evento(s):\n\n";
}

// ==========================================
// 2. EXIBIR DETALHES DOS EVENTOS
// ==========================================

echo "2. Detalhes dos eventos encontrados:\n";
echo str_repeat("-", 80) . "\n\n";

$eventsToConfirm = [];

foreach ($events as $idx => $event) {
    $content = $event['p_text'] 
        ?: $event['p_body'] 
        ?: $event['p_msg_text'] 
        ?: $event['p_msg_body'] 
        ?: 'N/A';
    
    $from = substr($event['p_from'] ?: 'N/A', 0, 30);
    $to = substr($event['p_to'] ?: 'N/A', 0, 30);
    
    echo sprintf(
        "[%d] ID=%d | event_id=%s | type=%s | tenant_id=%s | channel_id=%s\n",
        $idx + 1,
        $event['id'],
        substr($event['event_id'] ?: 'NULL', 0, 20),
        $event['event_type'] ?: 'NULL',
        $event['tenant_id'] ?: 'NULL',
        $event['meta_channel'] ?: 'NULL'
    );
    echo sprintf(
        "    created_at=%s | from=%s | to=%s\n",
        $event['created_at'],
        $from,
        $to
    );
    echo sprintf(
        "    content='%s'\n",
        substr($content, 0, 150)
    );
    echo "\n";
    
    // Coleta eventos outbound para confirmação
    if ($event['event_type'] === 'whatsapp.outbound.message') {
        $eventsToConfirm[] = $event;
    }
}

// ==========================================
// 3. VERIFICAR SE JÁ EXISTEM REGISTROS EM billing_notifications
// ==========================================

if (!empty($eventsToConfirm)) {
    echo "3. Verificando registros em billing_notifications:\n";
    echo str_repeat("-", 80) . "\n\n";
    
    foreach ($eventsToConfirm as $event) {
        $tenantId = $event['tenant_id'];
        $phone = preg_replace('/[^0-9]/', '', $event['p_to'] ?: '');
        
        if ($tenantId && $phone) {
            // Normaliza telefone (adiciona 55 se necessário)
            if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
                $phoneNormalized = '55' . $phone;
            } elseif (strlen($phone) === 10 && substr($phone, 0, 2) !== '55') {
                $phoneNormalized = '55' . $phone;
            } else {
                $phoneNormalized = $phone;
            }
            
            $stmt = $db->prepare("
                SELECT id, status, sent_at, template, invoice_id
                FROM billing_notifications
                WHERE tenant_id = ?
                AND phone_normalized = ?
                AND sent_at >= DATE_SUB(?, INTERVAL 1 DAY)
                ORDER BY sent_at DESC
                LIMIT 5
            ");
            $stmt->execute([$tenantId, $phoneNormalized, $event['created_at']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($notifications)) {
                echo "   ✅ Evento {$event['event_id']}: Já existe registro em billing_notifications\n";
                foreach ($notifications as $notif) {
                    echo sprintf(
                        "      - ID=%d | status=%s | sent_at=%s | template=%s\n",
                        $notif['id'],
                        $notif['status'],
                        $notif['sent_at'],
                        $notif['template'] ?: 'N/A'
                    );
                }
            } else {
                echo "   ⚠️  Evento {$event['event_id']}: Nenhum registro encontrado em billing_notifications\n";
            }
        }
    }
}

// ==========================================
// 4. CONFIRMAR MENSAGENS OUTBOUND
// ==========================================

if (!empty($eventsToConfirm)) {
    echo "\n\n4. CONFIRMAÇÃO DE MENSAGENS OUTBOUND:\n";
    echo str_repeat("-", 80) . "\n\n";
    
    echo "Encontrados " . count($eventsToConfirm) . " evento(s) outbound para confirmar.\n\n";
    
    try {
        $db->beginTransaction();
        
        $confirmed = 0;
        $skipped = 0;
        
        foreach ($eventsToConfirm as $event) {
            $tenantId = $event['tenant_id'];
            $phone = preg_replace('/[^0-9]/', '', $event['p_to'] ?: '');
            $content = $event['p_text'] 
                ?: $event['p_body'] 
                ?: $event['p_msg_text'] 
                ?: $event['p_msg_body'] 
                ?: '';
            
            if (!$tenantId || !$phone) {
                echo "   ⚠️  Evento {$event['event_id']}: Dados insuficientes (tenant_id ou telefone faltando)\n";
                $skipped++;
                continue;
            }
            
            // Normaliza telefone
            if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
                $phoneNormalized = '55' . $phone;
            } elseif (strlen($phone) === 10 && substr($phone, 0, 2) !== '55') {
                $phoneNormalized = '55' . $phone;
            } else {
                $phoneNormalized = $phone;
            }
            
            // Verifica se já existe notificação recente
            $stmt = $db->prepare("
                SELECT id, status
                FROM billing_notifications
                WHERE tenant_id = ?
                AND phone_normalized = ?
                AND sent_at >= DATE_SUB(?, INTERVAL 1 DAY)
                AND message LIKE ?
                ORDER BY sent_at DESC
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $phoneNormalized, $event['created_at'], "%{$searchTerm}%"]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualiza status se necessário
                if ($existing['status'] !== 'sent_manual') {
                    $stmt = $db->prepare("
                        UPDATE billing_notifications
                        SET status = 'sent_manual',
                            sent_at = COALESCE(sent_at, ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$event['created_at'], $existing['id']]);
                    echo "   ✅ Evento {$event['event_id']}: Atualizado registro existente (ID: {$existing['id']})\n";
                    $confirmed++;
                } else {
                    echo "   ℹ️  Evento {$event['event_id']}: Já confirmado (ID: {$existing['id']})\n";
                    $skipped++;
                }
            } else {
                // Cria novo registro em whatsapp_generic_logs
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_generic_logs
                    (tenant_id, template_id, phone, message, sent_at, created_at)
                    VALUES (?, NULL, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $phoneNormalized,
                    $content,
                    $event['created_at']
                ]);
                $logId = (int) $db->lastInsertId();
                echo "   ✅ Evento {$event['event_id']}: Criado registro em whatsapp_generic_logs (ID: {$logId})\n";
                $confirmed++;
            }
        }
        
        $db->commit();
        
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "✅ Confirmação de mensagens outbound concluída!\n";
        echo "   - Confirmados: {$confirmed}\n";
        echo "   - Ignorados: {$skipped}\n";
        
    } catch (\Exception $e) {
        $db->rollBack();
        echo "\n❌ ERRO ao confirmar mensagens: " . $e->getMessage() . "\n";
        echo "   Rollback realizado.\n";
    }
} else {
    echo "\n3. Nenhuma mensagem outbound encontrada para confirmar.\n";
    echo "   (Apenas mensagens inbound foram encontradas)\n";
}

// ==========================================
// 5. REGISTRAR MENSAGENS INBOUND (se necessário)
// ==========================================

$inboundEvents = array_filter($events, function($event) {
    return $event['event_type'] === 'whatsapp.inbound.message';
});

if (!empty($inboundEvents)) {
    echo "\n\n5. REGISTRAR MENSAGENS INBOUND ENCONTRADAS:\n";
    echo str_repeat("-", 80) . "\n\n";
    
    echo "Encontradas " . count($inboundEvents) . " mensagem(ns) inbound com '{$searchTerm}'.\n";
    echo "Essas são mensagens recebidas (não enviadas).\n\n";
    
    echo "ℹ️  Mensagens inbound não precisam ser 'confirmadas' como enviadas.\n";
    echo "   Elas já estão registradas em communication_events.\n";
    echo "   Se desejar criar logs adicionais, isso pode ser feito manualmente.\n";
}

// ==========================================
// 5. RESUMO
// ==========================================

echo "\n\n" . str_repeat("=", 80) . "\n";
echo "Busca e confirmação concluída.\n";
echo str_repeat("=", 80) . "\n";

