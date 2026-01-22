<?php

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE ESTRUTURA DE PAYLOAD ===\n\n";

$contacts = ['554796164699', '554796474223'];

foreach ($contacts as $contact) {
    echo "--- Contato: {$contact} ---\n";
    
    // Busca última mensagem deste contato
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.payload
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND (
              ce.payload LIKE ?
              OR ce.payload LIKE ?
          )
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    
    $pattern1 = "%{$contact}%";
    $pattern2 = "%{$contact}@%";
    $stmt->execute([$pattern1, $pattern2]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo "❌ Nenhuma mensagem encontrada\n\n";
        continue;
    }
    
    echo "✅ Última mensagem encontrada:\n";
    echo "  - ID: {$result['id']}\n";
    echo "  - Event ID: {$result['event_id']}\n";
    echo "  - Created: {$result['created_at']}\n";
    
    $payload = json_decode($result['payload'], true);
    
    echo "\n📦 Estrutura do Payload:\n";
    echo "  - Keys principais: " . implode(', ', array_keys($payload)) . "\n";
    
    // Tenta diferentes caminhos para encontrar o contato
    $foundFrom = null;
    $foundTo = null;
    
    if (isset($payload['from'])) {
        $foundFrom = $payload['from'];
        echo "  - payload.from: {$foundFrom}\n";
    }
    if (isset($payload['to'])) {
        $foundTo = $payload['to'];
        echo "  - payload.to: {$foundTo}\n";
    }
    if (isset($payload['message']['from'])) {
        $foundFrom = $payload['message']['from'];
        echo "  - payload.message.from: {$foundFrom}\n";
    }
    if (isset($payload['message']['to'])) {
        $foundTo = $payload['message']['to'];
        echo "  - payload.message.to: {$foundTo}\n";
    }
    if (isset($payload['contact'])) {
        echo "  - payload.contact: " . (is_array($payload['contact']) ? json_encode($payload['contact']) : $payload['contact']) . "\n";
    }
    
    // Busca no período 15:24-15:27 com estrutura correta
    echo "\n🔍 Buscando no período 15:24-15:27 com estrutura correta...\n";
    
    $searchPatterns = [
        "%{$contact}%",
        "%{$contact}@%",
    ];
    
    // Se encontrou formato com @, adiciona variação
    if ($foundFrom && strpos($foundFrom, '@') !== false) {
        $searchPatterns[] = "%{$foundFrom}%";
    }
    if ($foundTo && strpos($foundTo, '@') !== false) {
        $searchPatterns[] = "%{$foundTo}%";
    }
    
    // MySQL não tem ANY, vamos usar OR
    $whereConditions = [];
    $params = [];
    foreach ($searchPatterns as $pattern) {
        $whereConditions[] = "ce.payload LIKE ?";
        $params[] = $pattern;
    }
    
    $whereClause = "(" . implode(' OR ', $whereConditions) . ")";
    
    $stmt2 = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.created_at,
            ce.payload
        FROM communication_events ce
        WHERE ce.created_at >= '2026-01-14 15:24:00'
          AND ce.created_at <= '2026-01-14 15:27:59'
          AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND {$whereClause}
        ORDER BY ce.created_at ASC
    ");
    $stmt2->execute($params);
    $periodResults = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  - Encontradas: " . count($periodResults) . " mensagem(ns)\n";
    
    foreach ($periodResults as $msg) {
        $msgPayload = json_decode($msg['payload'], true);
        $msgFrom = $msgPayload['from'] ?? $msgPayload['message']['from'] ?? 'NULL';
        $msgTo = $msgPayload['to'] ?? $msgPayload['message']['to'] ?? 'NULL';
        echo "    * ID: {$msg['id']}, Event ID: {$msg['event_id']}, Created: {$msg['created_at']}\n";
        echo "      From: {$msgFrom}, To: {$msgTo}\n";
    }
    
    echo "\n";
}

echo "=== FIM ===\n";

