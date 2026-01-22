<?php

/**
 * Script para corrigir a exibição de Teste-17-40
 * 
 * Problemas identificados:
 * 1. Conversa está com status 'archived' (deveria ser 'active')
 * 2. Tenant_id da conversa (25) não corresponde ao do evento (2)
 * 
 * Uso: php database/corrigir-teste-17-40-exibicao.php
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

echo "=== CORREÇÃO: Teste-17-40 - Exibição na Interface ===\n\n";

$db = DB::getConnection();
$eventId = '30129e36-ac2f-4b65-b99f-c00cd2d155b4';
$conversationId = 11; // ID da conversa encontrada

// 1. Verifica estado atual
echo "1. ESTADO ATUAL\n";
echo str_repeat("-", 60) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.tenant_id as event_tenant_id,
        ce.payload,
        t.name as event_tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

$payload = json_decode($event['payload'], true);
$from = $payload['message']['from'] ?? '';
$channelId = $payload['session']['id'] ?? 'ImobSites';
$fromClean = str_replace('@lid', '', $from);
$threadKey = "wpp_gateway:{$channelId}:lid:{$fromClean}";

echo "Event ID: {$event['event_id']}\n";
echo "Event Tenant ID: {$event['event_tenant_id']} ({$event['event_tenant_name']})\n";
echo "Thread Key: {$threadKey}\n\n";

$stmt2 = $db->prepare("
    SELECT 
        id,
        thread_key,
        tenant_id,
        status,
        contact_name,
        last_message_at
    FROM conversations
    WHERE id = ?
");
$stmt2->execute([$conversationId]);
$conversation = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("✗ Conversa não encontrada!\n");
}

$stmt3 = $db->prepare("SELECT name FROM tenants WHERE id = ?");
$stmt3->execute([$conversation['tenant_id']]);
$convTenant = $stmt3->fetch(PDO::FETCH_ASSOC);

echo "Conversa ID: {$conversation['id']}\n";
echo "Thread Key: {$conversation['thread_key']}\n";
echo "Conversa Tenant ID: {$conversation['tenant_id']} (" . ($convTenant['name'] ?? 'N/A') . ")\n";
echo "Status: {$conversation['status']}\n";
echo "Contact Name: " . ($conversation['contact_name'] ?? 'N/A') . "\n";
echo "Last Message At: " . ($conversation['last_message_at'] ?? 'N/A') . "\n\n";

// 2. Identifica problemas
echo "2. PROBLEMAS IDENTIFICADOS\n";
echo str_repeat("-", 60) . "\n";

$problems = [];
if ($conversation['status'] !== 'active') {
    $problems[] = "Status é '{$conversation['status']}' (deveria ser 'active')";
}
if ($conversation['tenant_id'] != $event['event_tenant_id']) {
    $problems[] = "Tenant ID da conversa ({$conversation['tenant_id']}) não corresponde ao do evento ({$event['event_tenant_id']})";
}

if (empty($problems)) {
    echo "✓ Nenhum problema encontrado!\n\n";
} else {
    echo "✗ Problemas encontrados:\n";
    foreach ($problems as $problem) {
        echo "  - {$problem}\n";
    }
    echo "\n";
}

// 3. Aplica correções
if (!empty($problems)) {
    echo "3. APLICANDO CORREÇÕES\n";
    echo str_repeat("-", 60) . "\n";
    
    $updates = [];
    $params = [];
    
    if ($conversation['status'] !== 'active') {
        $updates[] = "status = 'active'";
    }
    
    if ($conversation['tenant_id'] != $event['event_tenant_id']) {
        $updates[] = "tenant_id = ?";
        $params[] = $event['event_tenant_id'];
    }
    
    // Atualiza last_message_at para a data do evento
    $updates[] = "last_message_at = (SELECT created_at FROM communication_events WHERE event_id = ?)";
    $params[] = $eventId;
    
    if (!empty($updates)) {
        $params[] = $conversationId;
        $sql = "UPDATE conversations SET " . implode(', ', $updates) . " WHERE id = ?";
        
        echo "SQL: {$sql}\n";
        echo "Parâmetros: " . json_encode($params) . "\n\n";
        
        try {
            $stmt4 = $db->prepare($sql);
            $stmt4->execute($params);
            
            echo "✓ Correções aplicadas com sucesso!\n\n";
            
            // Verifica resultado
            $stmt2->execute([$conversationId]);
            $updated = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            echo "4. ESTADO APÓS CORREÇÃO\n";
            echo str_repeat("-", 60) . "\n";
            echo "Status: {$updated['status']}\n";
            echo "Tenant ID: {$updated['tenant_id']}\n";
            echo "Last Message At: " . ($updated['last_message_at'] ?? 'N/A') . "\n\n";
            
            echo "✓ A mensagem agora deve aparecer na interface!\n";
            echo "  - Status atualizado para 'active'\n";
            if ($conversation['tenant_id'] != $event['event_tenant_id']) {
                echo "  - Tenant ID corrigido para {$event['event_tenant_id']}\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Erro ao aplicar correções: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "3. NENHUMA CORREÇÃO NECESSÁRIA\n";
    echo str_repeat("-", 60) . "\n";
    echo "A conversa já está correta.\n";
    echo "Se ainda não aparece, verifique:\n";
    echo "  - Filtros aplicados na interface\n";
    echo "  - Cache do navegador\n";
    echo "  - Permissões de acesso\n";
}

echo "\n";

