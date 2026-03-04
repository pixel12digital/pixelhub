<?php
$host = 'r225us.hmservers.net';
$dbname = 'pixel12digital_pixelhub';
$user = 'pixel12digital_pixelhub';
$pass = 'Los@ngo#081081';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO: O QUE MUDOU? ===\n\n";

// 1. Verificar se tenant_message_channels foi alterado recentemente
echo "1. HISTÓRICO DE CANAIS (tenant_message_channels):\n";
$stmt = $pdo->query("
    SELECT id, tenant_id, provider, provider_type, channel_id, 
           is_enabled, created_at, updated_at
    FROM tenant_message_channels
    ORDER BY updated_at DESC
    LIMIT 10
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ⚠️  TABELA VAZIA - Nenhum canal configurado!\n";
    echo "   Isso explica por que webhooks não são processados.\n\n";
} else {
    foreach ($channels as $ch) {
        $status = $ch['is_enabled'] ? '✓' : '✗';
        echo "   [{$ch['id']}] {$status} | Tenant: {$ch['tenant_id']} | Provider: {$ch['provider']}\n";
        echo "      Channel ID: {$ch['channel_id']}\n";
        echo "      Created: {$ch['created_at']}\n";
        echo "      Updated: {$ch['updated_at']}\n";
        echo "\n";
    }
}

// 2. Verificar quando foi o último webhook processado com sucesso
echo "\n2. ÚLTIMO WEBHOOK PROCESSADO COM SUCESSO:\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, processed
    FROM webhook_raw_logs
    WHERE processed = 1
    ORDER BY received_at DESC
    LIMIT 1
");
$lastProcessed = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lastProcessed) {
    echo "   Webhook ID: {$lastProcessed['id']}\n";
    echo "   Timestamp: {$lastProcessed['received_at']}\n";
    echo "   Event Type: {$lastProcessed['event_type']}\n";
    
    $lastTime = new DateTime($lastProcessed['received_at']);
    $now = new DateTime();
    $diff = $now->diff($lastTime);
    echo "   Há: {$diff->h}h {$diff->i}min atrás\n";
} else {
    echo "   ❌ NENHUM webhook foi processado com sucesso!\n";
}

// 3. Comparar: webhooks processados vs não processados
echo "\n\n3. ESTATÍSTICAS DE PROCESSAMENTO (últimas 24h):\n";
$stmt = $pdo->query("
    SELECT 
        processed,
        COUNT(*) as total,
        MIN(received_at) as primeiro,
        MAX(received_at) as ultimo
    FROM webhook_raw_logs
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY processed
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($stats as $stat) {
    $status = $stat['processed'] ? '✓ PROCESSADOS' : '✗ NÃO PROCESSADOS';
    echo "   {$status}: {$stat['total']} webhooks\n";
    echo "      Primeiro: {$stat['primeiro']}\n";
    echo "      Último: {$stat['ultimo']}\n";
    echo "\n";
}

// 4. Ver se há padrão no horário em que parou
echo "\n4. TIMELINE DO PROBLEMA:\n";
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(received_at, '%Y-%m-%d %H:%i') as minuto,
        COUNT(*) as total,
        SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processados,
        SUM(CASE WHEN processed = 0 THEN 1 ELSE 0 END) as nao_processados
    FROM webhook_raw_logs
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)
    GROUP BY DATE_FORMAT(received_at, '%Y-%m-%d %H:%i')
    ORDER BY minuto DESC
    LIMIT 20
");
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($timeline as $t) {
    $ratio = $t['total'] > 0 ? round(($t['processados'] / $t['total']) * 100) : 0;
    echo "   {$t['minuto']} | Total: {$t['total']} | Processados: {$t['processados']} ({$ratio}%) | Não proc: {$t['nao_processados']}\n";
}

// 5. Verificar se há algum webhook com erro específico
echo "\n\n5. WEBHOOKS COM ERRO (últimas 2 horas):\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, error_message
    FROM webhook_raw_logs
    WHERE error_message IS NOT NULL
      AND received_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY received_at DESC
    LIMIT 10
");
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($errors)) {
    echo "   ✓ Nenhum webhook com erro registrado\n";
} else {
    foreach ($errors as $err) {
        echo "   [{$err['id']}] {$err['received_at']} | {$err['event_type']}\n";
        echo "      Erro: {$err['error_message']}\n";
    }
}

// 6. Verificar se existe a coluna event_id em webhook_raw_logs
echo "\n\n6. ESTRUTURA webhook_raw_logs:\n";
$stmt = $pdo->query("DESCRIBE webhook_raw_logs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasEventId = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'event_id') {
        $hasEventId = true;
    }
    echo "   - {$col['Field']} ({$col['Type']})\n";
}

if (!$hasEventId) {
    echo "\n   ⚠️  ATENÇÃO: Coluna 'event_id' não existe!\n";
    echo "   Isso pode indicar que o webhook não está vinculando ao evento processado.\n";
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
