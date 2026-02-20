<?php
require_once __DIR__ . '/src/Core/bootstrap.php';

$db = \PixelHub\Core\DB::getConnection();

$phone = '5559923-5045';
$phoneDigits = preg_replace('/[^0-9]/', '', $phone); // 5559923504 -> mas vamos tentar variações
$variants = [
    '5559923504',   // sem o 5 extra
    '55599235045',  // completo
    '599235045',    // sem código país
    '9923504',      // só local (sem DDD)
    '99235045',     // DDD+número sem 9 extra
    '559923504',    // variação
];

echo "<h2>Buscando número: +55 55 9923-5045</h2>";
echo "<p>Dígitos: 5559923504 ou 55599235045</p>";

// 1. Busca em communication_conversations
echo "<h3>1. Conversas (communication_conversations)</h3>";
$stmt = $db->query("SELECT id, phone, contact_name, channel_type, channel_id, status, tenant_id, lead_id, created_at, updated_at 
                    FROM communication_conversations 
                    WHERE phone LIKE '%9923%' OR phone LIKE '%5045%'
                    ORDER BY created_at DESC LIMIT 20");
$convs = $stmt->fetchAll();
if ($convs) {
    echo "<table border='1' cellpadding='4'><tr><th>id</th><th>phone</th><th>contact_name</th><th>channel_type</th><th>channel_id</th><th>status</th><th>tenant_id</th><th>lead_id</th><th>created_at</th></tr>";
    foreach ($convs as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['phone']}</td><td>{$r['contact_name']}</td><td>{$r['channel_type']}</td><td>{$r['channel_id']}</td><td>{$r['status']}</td><td>{$r['tenant_id']}</td><td>{$r['lead_id']}</td><td>{$r['created_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Nenhuma conversa encontrada com esse número.</p>";
}

// 2. Busca em communication_events
echo "<h3>2. Eventos (communication_events)</h3>";
$stmt = $db->query("SELECT id, event_type, conversation_id, tenant_id, created_at, 
                    SUBSTRING(payload, 1, 500) as payload_preview
                    FROM communication_events 
                    WHERE payload LIKE '%9923%' OR payload LIKE '%5045%'
                    ORDER BY created_at DESC LIMIT 10");
$events = $stmt->fetchAll();
if ($events) {
    echo "<table border='1' cellpadding='4'><tr><th>id</th><th>event_type</th><th>conv_id</th><th>tenant_id</th><th>created_at</th><th>payload (500 chars)</th></tr>";
    foreach ($events as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['event_type']}</td><td>{$r['conversation_id']}</td><td>{$r['tenant_id']}</td><td>{$r['created_at']}</td><td><pre style='max-width:400px;overflow:auto;font-size:10px'>" . htmlspecialchars($r['payload_preview']) . "</pre></td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Nenhum evento encontrado com esse número no payload.</p>";
}

// 3. Verifica logs de webhook recentes (últimas 2 horas)
echo "<h3>3. Eventos recentes de webhook WhatsApp (últimas 2h)</h3>";
$stmt = $db->query("SELECT id, event_type, conversation_id, tenant_id, created_at,
                    SUBSTRING(payload, 1, 300) as payload_preview
                    FROM communication_events 
                    WHERE event_type LIKE 'whatsapp%'
                    AND created_at >= NOW() - INTERVAL 2 HOUR
                    ORDER BY created_at DESC LIMIT 20");
$recent = $stmt->fetchAll();
if ($recent) {
    echo "<table border='1' cellpadding='4'><tr><th>id</th><th>event_type</th><th>conv_id</th><th>tenant_id</th><th>created_at</th><th>payload preview</th></tr>";
    foreach ($recent as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['event_type']}</td><td>{$r['conversation_id']}</td><td>{$r['tenant_id']}</td><td>{$r['created_at']}</td><td><pre style='max-width:400px;overflow:auto;font-size:10px'>" . htmlspecialchars($r['payload_preview']) . "</pre></td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange'>Nenhum evento WhatsApp nas últimas 2 horas.</p>";
}

// 4. Verifica gateway sessions ativas
echo "<h3>4. Gateway Sessions ativas</h3>";
try {
    $stmt = $db->query("SELECT id, session_name, status, tenant_id, phone_number, created_at, updated_at 
                        FROM gateway_sessions WHERE status = 'connected' ORDER BY updated_at DESC LIMIT 10");
    $sessions = $stmt->fetchAll();
    if ($sessions) {
        echo "<table border='1' cellpadding='4'><tr><th>id</th><th>session_name</th><th>status</th><th>tenant_id</th><th>phone_number</th><th>updated_at</th></tr>";
        foreach ($sessions as $r) {
            echo "<tr><td>{$r['id']}</td><td>{$r['session_name']}</td><td>{$r['status']}</td><td>{$r['tenant_id']}</td><td>{$r['phone_number']}</td><td>{$r['updated_at']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red'>Nenhuma sessão conectada!</p>";
    }
} catch (\Exception $e) {
    echo "<p style='color:red'>Erro ao buscar sessions: " . $e->getMessage() . "</p>";
}

// 5. Verifica webhook_logs se existir
echo "<h3>5. Webhook logs (se existir tabela)</h3>";
try {
    $stmt = $db->query("SHOW TABLES LIKE '%webhook%'");
    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    echo "<p>Tabelas webhook: " . implode(', ', $tables ?: ['nenhuma']) . "</p>";
    
    foreach ($tables as $table) {
        $stmt2 = $db->query("SELECT * FROM `{$table}` WHERE created_at >= NOW() - INTERVAL 2 HOUR ORDER BY created_at DESC LIMIT 5");
        $rows = $stmt2->fetchAll();
        if ($rows) {
            echo "<p>Últimos registros em <b>{$table}</b>:</p>";
            echo "<pre>" . htmlspecialchars(print_r($rows, true)) . "</pre>";
        }
    }
} catch (\Exception $e) {
    echo "<p>Sem tabela webhook_logs: " . $e->getMessage() . "</p>";
}

// 6. Verifica arquivo de log do PHP
echo "<h3>6. Logs de erro PHP recentes (últimas linhas)</h3>";
$logPaths = [
    __DIR__ . '/logs/app.log',
    __DIR__ . '/storage/logs/app.log',
    ini_get('error_log'),
];
foreach ($logPaths as $logPath) {
    if ($logPath && file_exists($logPath)) {
        echo "<p>Arquivo: <b>{$logPath}</b></p>";
        $lines = file($logPath);
        $last = array_slice($lines, -50);
        $filtered = array_filter($last, fn($l) => stripos($l, '9923') !== false || stripos($l, '5045') !== false || stripos($l, 'webhook') !== false || stripos($l, 'WhatsApp') !== false || stripos($l, 'ingest') !== false);
        if ($filtered) {
            echo "<pre style='background:#f5f5f5;padding:10px;font-size:11px;max-height:300px;overflow:auto'>" . htmlspecialchars(implode('', $filtered)) . "</pre>";
        } else {
            echo "<p style='color:gray'>Nenhuma linha relevante nas últimas 50 linhas.</p>";
            echo "<pre style='background:#f5f5f5;padding:10px;font-size:11px;max-height:200px;overflow:auto'>" . htmlspecialchars(implode('', array_slice($last, -20))) . "</pre>";
        }
        break;
    }
}
