<?php

/**
 * Script para testar o endpoint /messages/check
 * 
 * Uso: php database/test-messages-check-endpoint.php
 * 
 * Reproduz localmente o endpoint usando exatamente os params do console:
 * - thread_id=whatsapp_34
 * - after_timestamp=2026-01-14 13:57:50
 * - after_event_id=...
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

echo "=== TESTE DO ENDPOINT /messages/check ===\n\n";

// Parâmetros de teste (ajuste conforme necessário)
$testCases = [
    [
        'thread_id' => 'whatsapp_34',
        'after_timestamp' => '2026-01-14 13:57:50',
        'after_event_id' => null,
        'description' => 'Thread 34 (ServPro) - após 13:57:50'
    ],
    [
        'thread_id' => 'whatsapp_35',
        'after_timestamp' => '2026-01-14 13:57:50',
        'after_event_id' => null,
        'description' => 'Thread 35 (Charles) - após 13:57:50'
    ],
    [
        'thread_id' => 'whatsapp_34',
        'after_timestamp' => '2026-01-14 15:20:00',
        'after_event_id' => null,
        'description' => 'Thread 34 - após 15:20:00 (período das mensagens 4699/4223)'
    ]
];

foreach ($testCases as $testCase) {
    echo "--- Teste: {$testCase['description']} ---\n";
    echo "  thread_id: {$testCase['thread_id']}\n";
    echo "  after_timestamp: {$testCase['after_timestamp']}\n";
    echo "  after_event_id: " . ($testCase['after_event_id'] ?? 'NULL') . "\n\n";
    
    // Resolve thread para pegar dados da conversa (simula resolveThreadToConversation)
    $threadId = $testCase['thread_id'];
    $conversationData = null;
    
    if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
        $conversationId = (int) $matches[1];
        
        $stmt = $db->prepare("
            SELECT id as conversation_id, contact_external_id, tenant_id
            FROM conversations
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conv) {
            $conversationData = [
                'conversation_id' => $conv['conversation_id'],
                'contact_external_id' => $conv['contact_external_id'],
                'tenant_id' => $conv['tenant_id']
            ];
        }
    }
    
    if (!$conversationData) {
        echo "❌ Thread não encontrado\n\n";
        continue;
    }
    
    echo "✅ Conversation encontrada:\n";
    echo "  - Conversation ID: {$conversationData['conversation_id']}\n";
    echo "  - Contact External ID: {$conversationData['contact_external_id']}\n";
    echo "  - Tenant ID: {$conversationData['tenant_id']}\n\n";
    
    // Normaliza contato (simula normalização do backend)
    $normalizeContact = function($contact) {
        if (empty($contact)) return null;
        $cleaned = preg_replace('/@.*$/', '', (string) $contact);
        $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
        if (strlen($digitsOnly) >= 12 && substr($digitsOnly, 0, 2) === '55') {
            return $digitsOnly;
        }
        return $digitsOnly;
    };
    $normalizedContact = $normalizeContact($conversationData['contact_external_id']);
    
    echo "  - Contact Normalizado: " . ($normalizedContact ?: 'NULL') . "\n\n";
    
    if (empty($normalizedContact)) {
        echo "❌ Contact não pode ser normalizado\n\n";
        continue;
    }
    
    // Monta query (simula checkNewMessages)
    $where = [
        "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
    ];
    $params = [];
    
    // Filtro por contato
    $contactPatterns = ["%{$normalizedContact}%"];
    
    if (strlen($normalizedContact) >= 12 && substr($normalizedContact, 0, 2) === '55') {
        if (strlen($normalizedContact) === 13) {
            $without9th = substr($normalizedContact, 0, 4) . substr($normalizedContact, 5);
            $contactPatterns[] = "%{$without9th}%";
        } elseif (strlen($normalizedContact) === 12) {
            $with9th = substr($normalizedContact, 0, 4) . '9' . substr($normalizedContact, 4);
            $contactPatterns[] = "%{$with9th}%";
        }
    }
    
    $contactConditions = [];
    foreach ($contactPatterns as $pattern) {
        $contactConditions[] = "(
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        )";
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
    }
    $where[] = "(" . implode(" OR ", $contactConditions) . ")";
    
    // Filtro por tenant
    if ($conversationData['tenant_id']) {
        $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
        $params[] = $conversationData['tenant_id'];
    }
    
    // Filtro por timestamp
    if ($testCase['after_timestamp']) {
        $where[] = "(ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))";
        $params[] = $testCase['after_timestamp'];
        $params[] = $testCase['after_timestamp'];
        $params[] = $testCase['after_event_id'] ?? '';
    }
    
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    // COUNT total
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events ce
        {$whereClause}
    ");
    $countStmt->execute($params);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = $countResult['total'] ?? 0;
    
    echo "📊 RESULTADO:\n";
    echo "  - COUNT(*) TOTAL: {$totalCount}\n";
    
    // Busca eventos
    $stmt = $db->prepare("
        SELECT ce.event_id, ce.created_at, ce.payload
        FROM communication_events ce
        {$whereClause}
        ORDER BY ce.created_at ASC, ce.event_id ASC
        LIMIT 20
    ");
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  - Events encontrados: " . count($events) . "\n\n";
    
    if ($totalCount > 0 && count($events) === 0) {
        echo "⚠️  ATENÇÃO: COUNT > 0 mas nenhum evento retornado (pode ser problema de LIMIT ou filtro)\n\n";
    } elseif ($totalCount > 0) {
        echo "✅ Mensagens encontradas:\n";
        foreach ($events as $event) {
            $payload = json_decode($event['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? 'NULL';
            $to = $payload['to'] ?? $payload['message']['to'] ?? 'NULL';
            echo "  - Event ID: {$event['event_id']}, Created: {$event['created_at']}, From: {$from}, To: {$to}\n";
        }
        echo "\n";
    } else {
        echo "❌ Nenhuma mensagem encontrada\n\n";
    }
    
    echo "---\n\n";
}

echo "=== FIM DO TESTE ===\n";

