<?php

/**
 * Diagnóstico completo: Por que "Teste-17-40" não está sendo exibida na interface?
 * 
 * Uso: php database/diagnostico-teste-17-40-exibicao.php
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

echo "=== DIAGNÓSTICO: Por que Teste-17-40 não está sendo exibida? ===\n\n";

$db = DB::getConnection();
$eventId = '30129e36-ac2f-4b65-b99f-c00cd2d155b4';

// 1. Busca o evento completo
echo "1. EVENTO DE COMUNICAÇÃO\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        ce.*,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("✗ Evento não encontrado!\n");
}

$payload = json_decode($event['payload'], true);
$text = $payload['text'] ?? $payload['message'] ?? $payload['body'] ?? 'N/A';
if (is_array($text)) {
    $text = json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

echo "Event ID: {$event['event_id']}\n";
echo "Tipo: {$event['event_type']}\n";
echo "Source: {$event['source_system']}\n";
echo "Status: {$event['status']}\n";
echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
echo "Tenant Nome: " . ($event['tenant_name'] ?? 'N/A') . "\n";
echo "Tenant Telefone: " . ($event['tenant_phone'] ?? 'N/A') . "\n";
echo "Criado em: {$event['created_at']}\n";
echo "From: " . ($payload['from'] ?? 'N/A') . "\n";
echo "To: " . ($payload['to'] ?? 'N/A') . "\n";
echo "Channel ID: " . ($payload['channel_id'] ?? 'N/A') . "\n";
echo "Mensagem: {$text}\n\n";

// 2. Calcula thread_key esperado
echo "2. THREAD_KEY ESPERADO\n";
echo str_repeat("-", 60) . "\n";
$threadKeyExpected = null;
$fromValue = $payload['from'] ?? '';
$channelId = $payload['channel_id'] ?? '';

// Remove sufixos do from
$fromClean = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $fromValue);

if ($channelId && $fromClean) {
    $threadKeyExpected = "wpp_gateway:{$channelId}:tel:{$fromClean}";
    echo "Thread Key esperado: {$threadKeyExpected}\n";
} else {
    echo "⚠ Não foi possível calcular thread_key (channel_id ou from ausente)\n";
}
echo "\n";

// 3. Busca conversas relacionadas
echo "3. CONVERSAS RELACIONADAS\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_id,
        c.session_id,
        c.contact_external_id,
        c.contact_name,
        c.remote_key,
        c.thread_key,
        c.tenant_id,
        c.status,
        c.last_message_at,
        c.updated_at,
        c.created_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.channel_type = 'whatsapp'
      AND (
        c.contact_external_id LIKE ?
        OR c.remote_key LIKE ?
        OR c.thread_key LIKE ?
        OR c.thread_key = ?
      )
    ORDER BY c.updated_at DESC, c.last_message_at DESC
");
$fromPattern = "%{$fromClean}%";
$stmt->execute([$fromPattern, $fromPattern, $fromPattern, $threadKeyExpected]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($conversations)) {
    echo "Conversas encontradas: " . count($conversations) . "\n\n";
    foreach ($conversations as $conv) {
        $isEventTenant = ($conv['tenant_id'] == $event['tenant_id']) ? ' ← TENANT DO EVENTO' : '';
        $isCorrectThreadKey = ($conv['thread_key'] === $threadKeyExpected) ? ' ← THREAD_KEY CORRETO' : '';
        
        echo "  - ID: {$conv['id']}\n";
        echo "    Conversation Key: {$conv['conversation_key']}\n";
        echo "    Contact External ID: {$conv['contact_external_id']}\n";
        echo "    Contact Name: " . ($conv['contact_name'] ?? 'N/A') . "\n";
        echo "    Remote Key: " . ($conv['remote_key'] ?? 'N/A') . "\n";
        echo "    Thread Key: {$conv['thread_key']}{$isCorrectThreadKey}\n";
        echo "    Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . " ({$conv['tenant_name']}){$isEventTenant}\n";
        echo "    Status: {$conv['status']}\n";
        echo "    Last Message At: " . ($conv['last_message_at'] ?? 'N/A') . "\n";
        echo "    Updated At: {$conv['updated_at']}\n";
        echo "\n";
    }
} else {
    echo "✗ Nenhuma conversa encontrada para este número/thread_key\n";
    echo "  Isso pode ser o motivo pelo qual a mensagem não aparece!\n\n";
}

// 4. Verifica se há mensagens na conversa relacionada ao evento
echo "4. MENSAGENS NA CONVERSA\n";
echo str_repeat("-", 60) . "\n";
if (!empty($conversations)) {
    foreach ($conversations as $conv) {
        echo "Conversa ID: {$conv['id']} (Tenant: {$conv['tenant_name']})\n";
        
        // Busca eventos relacionados a esta conversa
        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                ce.event_type,
                ce.status,
                ce.created_at,
                JSON_EXTRACT(ce.payload, '$.text') as text,
                JSON_EXTRACT(ce.payload, '$.from') as from_field,
                JSON_EXTRACT(ce.payload, '$.to') as to_field,
                JSON_EXTRACT(ce.payload, '$.timestamp') as timestamp
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
              AND (
                JSON_EXTRACT(ce.payload, '$.from') LIKE ?
                OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
              )
              AND ce.tenant_id = ?
            ORDER BY ce.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([
            "%{$fromClean}%",
            "%{$fromClean}%",
            $conv['tenant_id']
        ]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  Mensagens encontradas: " . count($messages) . "\n";
        $foundTeste = false;
        foreach ($messages as $msg) {
            $msgText = json_decode($msg['text'], true);
            if (is_array($msgText)) {
                $msgText = json_encode($msgText, JSON_UNESCAPED_UNICODE);
            }
            $msgText = (string)$msgText;
            
            if (strpos($msgText, 'Teste-17-40') !== false) {
                $foundTeste = true;
                echo "    ✓ ENCONTRADA: Event ID {$msg['event_id']}\n";
                echo "      Texto: {$msgText}\n";
                echo "      From: " . json_decode($msg['from_field'], true) . "\n";
                echo "      Created At: {$msg['created_at']}\n";
                echo "      Timestamp: " . ($msg['timestamp'] ?? 'N/A') . "\n";
            }
        }
        
        if (!$foundTeste) {
            echo "    ✗ Mensagem 'Teste-17-40' NÃO encontrada nesta conversa\n";
        }
        echo "\n";
    }
} else {
    echo "✗ Não há conversas para verificar mensagens\n\n";
}

// 5. Verifica como a interface busca mensagens (simula query do CommunicationHubController)
echo "5. SIMULAÇÃO DA QUERY DA INTERFACE\n";
echo str_repeat("-", 60) . "\n";

// Busca como o controller busca threads
$stmt = $db->prepare("
    SELECT 
        c.id as conversation_id,
        c.thread_key,
        c.contact_name,
        c.contact_external_id,
        c.tenant_id,
        c.status,
        c.last_message_at,
        c.updated_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.channel_type = 'whatsapp'
      AND c.status = 'active'
      AND c.tenant_id = ?
    ORDER BY c.last_message_at DESC, c.updated_at DESC
    LIMIT 50
");
$stmt->execute([$event['tenant_id']]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Threads ativas para tenant ID {$event['tenant_id']} ({$event['tenant_name']}): " . count($threads) . "\n\n";

$foundInThreads = false;
foreach ($threads as $thread) {
    if ($thread['thread_key'] === $threadKeyExpected) {
        $foundInThreads = true;
        echo "  ✓ Thread encontrado na lista:\n";
        echo "    Thread Key: {$thread['thread_key']}\n";
        echo "    Contact Name: " . ($thread['contact_name'] ?? 'N/A') . "\n";
        echo "    Last Message At: " . ($thread['last_message_at'] ?? 'N/A') . "\n";
        echo "\n";
        break;
    }
}

if (!$foundInThreads) {
    echo "  ✗ Thread NÃO encontrado na lista de threads ativas\n";
    echo "    Isso explica por que não aparece na interface!\n\n";
}

// 6. Verifica se há filtros aplicados
echo "6. VERIFICAÇÃO DE FILTROS\n";
echo str_repeat("-", 60) . "\n";
echo "Status da conversa: ";
if (!empty($conversations)) {
    foreach ($conversations as $conv) {
        echo "{$conv['status']} ";
        if ($conv['status'] !== 'active') {
            echo "⚠ Status não é 'active' - pode estar filtrado!\n";
        } else {
            echo "✓ Status correto\n";
        }
    }
} else {
    echo "N/A (sem conversa)\n";
}
echo "\n";

// 7. Verifica mapeamento LID se aplicável
echo "7. MAPEAMENTO LID (se aplicável)\n";
echo str_repeat("-", 60) . "\n";
if (strpos($fromValue, '@lid') !== false || strpos($fromValue, '@c.us') !== false) {
    $businessId = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $fromValue);
    echo "From contém LID ou @c.us: {$fromValue}\n";
    echo "Business ID extraído: {$businessId}\n";
    
    $stmt = $db->prepare("
        SELECT 
            wbi.business_id,
            wbi.phone_number,
            wbi.tenant_id,
            t.name as tenant_name
        FROM whatsapp_business_ids wbi
        LEFT JOIN tenants t ON wbi.tenant_id = t.id
        WHERE wbi.business_id LIKE ?
           OR wbi.phone_number LIKE ?
        ORDER BY wbi.updated_at DESC
    ");
    $stmt->execute(["%{$businessId}%", "%{$fromClean}%"]);
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($mappings)) {
        echo "Mapeamentos encontrados:\n";
        foreach ($mappings as $map) {
            echo "  - Business ID: {$map['business_id']}\n";
            echo "    Phone Number: {$map['phone_number']}\n";
            echo "    Tenant: {$map['tenant_name']} (ID: {$map['tenant_id']})\n";
        }
    } else {
        echo "✗ Nenhum mapeamento encontrado\n";
    }
} else {
    echo "From não contém LID ou @c.us: {$fromValue}\n";
}
echo "\n";

// Resumo final
echo str_repeat("=", 60) . "\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo str_repeat("=", 60) . "\n";

$issues = [];

if (empty($conversations)) {
    $issues[] = "CRÍTICO: Nenhuma conversa encontrada para este número/thread_key";
    $issues[] = "A mensagem existe no banco, mas não há conversa vinculada";
} else {
    $hasCorrectTenant = false;
    $hasCorrectThreadKey = false;
    $hasActiveStatus = false;
    
    foreach ($conversations as $conv) {
        if ($conv['tenant_id'] == $event['tenant_id']) {
            $hasCorrectTenant = true;
        }
        if ($conv['thread_key'] === $threadKeyExpected) {
            $hasCorrectThreadKey = true;
        }
        if ($conv['status'] === 'active') {
            $hasActiveStatus = true;
        }
    }
    
    if (!$hasCorrectTenant) {
        $issues[] = "Nenhuma conversa encontrada com o tenant_id do evento";
    }
    if (!$hasCorrectThreadKey) {
        $issues[] = "Thread_key da conversa não corresponde ao esperado";
    }
    if (!$hasActiveStatus) {
        $issues[] = "Status da conversa não é 'active' (pode estar filtrado)";
    }
}

if (!$foundInThreads && !empty($conversations)) {
    $issues[] = "Thread não aparece na lista de threads ativas do tenant";
}

if (empty($issues)) {
    echo "✓ Todos os dados parecem corretos\n";
    echo "Se a mensagem não aparece, pode ser:\n";
    echo "  - Problema de cache no frontend\n";
    echo "  - Filtros aplicados na interface (canal, status, cliente)\n";
    echo "  - Problema na query de busca de mensagens\n";
    echo "  - Mensagem muito antiga (fora do limite de busca)\n";
} else {
    echo "✗ Problemas encontrados:\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\n";
    echo "SOLUÇÕES SUGERIDAS:\n";
    if (empty($conversations)) {
        echo "  1. Criar/vincular uma conversa para este número\n";
        echo "  2. Verificar se o evento foi processado corretamente\n";
    }
    if (!empty($conversations) && !$hasActiveStatus) {
        echo "  1. Atualizar status da conversa para 'active'\n";
    }
    if (!$foundInThreads) {
        echo "  1. Verificar se o thread_key está correto\n";
        echo "  2. Verificar se o tenant_id está correto\n";
    }
}

echo "\n";

