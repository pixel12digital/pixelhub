<?php

/**
 * Script de diagnóstico completo para mensagem "Teste-17:14"
 * Verifica tenant, conversa, horários e por que não aparece na interface
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

echo "=== DIAGNÓSTICO COMPLETO: Mensagem Teste-17:14 ===\n\n";

$db = DB::getConnection();
$eventId = 'ed4e9725-9516-4524-a7e5-9f84bc04515c';
$phone = '554796164699';

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
echo "Payload timestamp: " . ($payload['timestamp'] ?? 'N/A') . "\n";

// Converte timestamp Unix para data
if (isset($payload['timestamp']) && is_numeric($payload['timestamp'])) {
    $timestampDate = date('Y-m-d H:i:s', $payload['timestamp']);
    echo "Timestamp convertido: {$timestampDate}\n";
    echo "Diferença com created_at: " . abs(strtotime($event['created_at']) - $payload['timestamp']) . " segundos\n";
}

echo "From: " . ($payload['from'] ?? 'N/A') . "\n";
echo "To: " . ($payload['to'] ?? 'N/A') . "\n";
echo "Channel ID: " . ($payload['channel_id'] ?? 'N/A') . "\n";
echo "Mensagem: {$text}\n\n";

// 2. Verifica tenant correto (Charles Dietrich)
echo "2. VERIFICAÇÃO DO TENANT (Charles Dietrich)\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        id,
        name,
        phone,
        email
    FROM tenants
    WHERE name LIKE '%Charles%'
       OR name LIKE '%Dietrich%'
       OR phone LIKE '%{$phone}%'
    ORDER BY id
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($tenants)) {
    echo "Tenants encontrados com 'Charles' ou 'Dietrich':\n";
    foreach ($tenants as $t) {
        $isCorrect = ($event['tenant_id'] == $t['id']) ? ' ← ATUAL' : '';
        echo "  - ID: {$t['id']}, Nome: {$t['name']}, Telefone: " . ($t['phone'] ?? 'N/A') . "{$isCorrect}\n";
    }
} else {
    echo "✗ Nenhum tenant encontrado com 'Charles' ou 'Dietrich'\n";
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
        OR c.contact_external_id LIKE ?
        OR c.remote_key LIKE ?
        OR c.thread_key LIKE ?
        OR c.contact_name LIKE '%Charles%'
        OR c.contact_name LIKE '%Dietrich%'
      )
    ORDER BY c.updated_at DESC, c.last_message_at DESC
");
$phonePattern1 = "%{$phone}%";
$phonePattern2 = "%" . substr($phone, 0, -1) . "%"; // Sem último dígito
$stmt->execute([$phonePattern1, $phonePattern2, "%{$phone}%", "%{$phone}%"]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($conversations)) {
    echo "Conversas encontradas: " . count($conversations) . "\n\n";
    foreach ($conversations as $conv) {
        $isEventTenant = ($conv['tenant_id'] == $event['tenant_id']) ? ' ← TENANT DO EVENTO' : '';
        echo "  - ID: {$conv['id']}\n";
        echo "    Conversation Key: {$conv['conversation_key']}\n";
        echo "    Contact External ID: {$conv['contact_external_id']}\n";
        echo "    Contact Name: " . ($conv['contact_name'] ?? 'N/A') . "\n";
        echo "    Remote Key: " . ($conv['remote_key'] ?? 'N/A') . "\n";
        echo "    Thread Key: " . ($conv['thread_key'] ?? 'N/A') . "\n";
        echo "    Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . " ({$conv['tenant_name']}){$isEventTenant}\n";
        echo "    Status: {$conv['status']}\n";
        echo "    Last Message At: " . ($conv['last_message_at'] ?? 'N/A') . "\n";
        echo "    Updated At: {$conv['updated_at']}\n";
        echo "\n";
    }
} else {
    echo "✗ Nenhuma conversa encontrada para este número\n";
}
echo "\n";

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
                OR JSON_EXTRACT(ce.payload, '$.from') LIKE ?
                OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
              )
              AND ce.tenant_id = ?
            ORDER BY ce.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([
            "%{$phone}%",
            "%{$phone}%",
            "%{$conv['contact_external_id']}%",
            "%{$conv['contact_external_id']}%",
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
            
            if (strpos($msgText, 'Teste-17:14') !== false) {
                $foundTeste = true;
                echo "    ✓ ENCONTRADA: Event ID {$msg['event_id']}\n";
                echo "      Texto: {$msgText}\n";
                echo "      From: " . json_decode($msg['from_field'], true) . "\n";
                echo "      Created At: {$msg['created_at']}\n";
                echo "      Timestamp: " . ($msg['timestamp'] ?? 'N/A') . "\n";
            }
        }
        
        if (!$foundTeste) {
            echo "    ✗ Mensagem 'Teste-17:14' NÃO encontrada nesta conversa\n";
        }
        echo "\n";
    }
} else {
    echo "✗ Não há conversas para verificar mensagens\n";
}
echo "\n";

// 5. Verifica mapeamento LID se aplicável
echo "5. MAPEAMENTO LID (se aplicável)\n";
echo str_repeat("-", 60) . "\n";
$fromValue = $payload['from'] ?? '';
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
    $stmt->execute(["%{$businessId}%", "%{$phone}%"]);
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

// 6. Análise de horários
echo "6. ANÁLISE DE HORÁRIOS\n";
echo str_repeat("-", 60) . "\n";
$createdAt = strtotime($event['created_at']);
$timestamp = $payload['timestamp'] ?? null;

if ($timestamp && is_numeric($timestamp)) {
    $timestampDate = date('Y-m-d H:i:s', $timestamp);
    $diff = abs($createdAt - $timestamp);
    
    echo "Created At (BD): {$event['created_at']} (Unix: {$createdAt})\n";
    echo "Timestamp (Payload): {$timestampDate} (Unix: {$timestamp})\n";
    echo "Diferença: {$diff} segundos (" . round($diff / 60, 2) . " minutos)\n";
    
    if ($diff > 3600) {
        echo "⚠ ATENÇÃO: Diferença maior que 1 hora! Pode haver problema de timezone.\n";
    }
} else {
    echo "✗ Timestamp não encontrado no payload\n";
}
echo "\n";

// 7. Verifica se a mensagem deveria aparecer na interface
echo "7. VERIFICAÇÃO DE EXIBIÇÃO NA INTERFACE\n";
echo str_repeat("-", 60) . "\n";

// Verifica se há thread_key correspondente
$threadKeyExpected = null;
if (isset($payload['channel_id']) && isset($payload['from'])) {
    $channelId = $payload['channel_id'];
    $from = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $payload['from']);
    $threadKeyExpected = "wpp_gateway:{$channelId}:tel:{$from}";
    echo "Thread Key esperado: {$threadKeyExpected}\n";
}

if (!empty($conversations)) {
    foreach ($conversations as $conv) {
        if ($conv['thread_key'] === $threadKeyExpected) {
            echo "✓ Thread Key corresponde!\n";
        } else {
            echo "✗ Thread Key NÃO corresponde:\n";
            echo "  Esperado: {$threadKeyExpected}\n";
            echo "  Encontrado: {$conv['thread_key']}\n";
        }
        
        // Verifica se o tenant_id do evento corresponde ao da conversa
        if ($conv['tenant_id'] == $event['tenant_id']) {
            echo "✓ Tenant ID corresponde ({$conv['tenant_id']})\n";
        } else {
            echo "✗ Tenant ID NÃO corresponde:\n";
            echo "  Evento: {$event['tenant_id']}\n";
            echo "  Conversa: {$conv['tenant_id']}\n";
        }
    }
} else {
    echo "✗ Nenhuma conversa encontrada para verificar\n";
}
echo "\n";

// Resumo final
echo str_repeat("=", 60) . "\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo str_repeat("=", 60) . "\n";

$issues = [];

if ($event['tenant_id'] != null) {
    $tenantName = $event['tenant_name'] ?? 'N/A';
    if (stripos($tenantName, 'Charles') === false && stripos($tenantName, 'Dietrich') === false) {
        $issues[] = "Tenant incorreto: '{$tenantName}' (deveria ser Charles Dietrich)";
    }
}

if (empty($conversations)) {
    $issues[] = "Nenhuma conversa encontrada para este número";
} else {
    $hasCorrectTenant = false;
    foreach ($conversations as $conv) {
        if ($conv['tenant_id'] == $event['tenant_id']) {
            $hasCorrectTenant = true;
            break;
        }
    }
    if (!$hasCorrectTenant) {
        $issues[] = "Nenhuma conversa encontrada com o tenant_id do evento";
    }
}

if (empty($issues)) {
    echo "✓ Todos os dados parecem corretos\n";
    echo "Se a mensagem não aparece, pode ser:\n";
    echo "  - Problema de cache no frontend\n";
    echo "  - Filtros aplicados na interface\n";
    echo "  - Problema na query de busca de mensagens\n";
} else {
    echo "✗ Problemas encontrados:\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
}

echo "\n";

