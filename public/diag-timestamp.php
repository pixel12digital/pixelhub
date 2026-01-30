<?php
/**
 * Diagnóstico de formato de timestamps nas conversas
 */

// Carrega .env manualmente
$envFile = __DIR__ . '/../.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value, '"\'');
        }
    }
}

// Segurança básica
$key = $_GET['key'] ?? '';
if ($key !== 'diag2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $envVars['DB_HOST'] ?? 'localhost',
        $envVars['DB_NAME'] ?? 'pixelhub'
    );
    $pdo = new PDO($dsn, $envVars['DB_USER'] ?? 'root', $envVars['DB_PASS'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "=== DIAGNÓSTICO DE TIMESTAMPS - " . date('Y-m-d H:i:s') . " ===\n\n";
    
    // Verifica timezone do MySQL
    $stmt = $pdo->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz, NOW() as now_time");
    $tz = $stmt->fetch();
    echo "1) TIMEZONE DO MYSQL:\n";
    echo "   Global: {$tz['global_tz']}\n";
    echo "   Session: {$tz['session_tz']}\n";
    echo "   NOW(): {$tz['now_time']}\n\n";
    
    // Verifica timezone do PHP
    echo "2) TIMEZONE DO PHP:\n";
    echo "   date_default_timezone_get(): " . date_default_timezone_get() . "\n";
    echo "   date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "\n\n";
    
    // Últimas conversas com timestamps
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.contact_name,
            c.last_message_at,
            c.created_at,
            c.updated_at
        FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        LIMIT 5
    ");
    
    echo "3) TIMESTAMPS DAS ÚLTIMAS 5 CONVERSAS:\n";
    echo str_repeat('-', 100) . "\n";
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}\n";
        echo "  Contact: {$row['contact_name']}\n";
        echo "  last_message_at: '{$row['last_message_at']}'\n";
        echo "  created_at: '{$row['created_at']}'\n";
        echo "  updated_at: '{$row['updated_at']}'\n";
        
        // Testa o regex
        $timestamp = $row['last_message_at'] ?: $row['created_at'];
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/', $timestamp, $m)) {
            $formatted = "{$m[3]}/{$m[2]} {$m[4]}:{$m[5]}";
            echo "  REGEX RESULT: '{$formatted}'\n";
        } else {
            echo "  REGEX RESULT: NO MATCH (timestamp='{$timestamp}')\n";
        }
        echo "\n";
    }
    
    // Verifica Charles Dietrich especificamente
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.contact_name,
            c.last_message_at,
            c.created_at,
            c.updated_at
        FROM conversations c
        WHERE c.contact_name LIKE '%Charles%' OR c.contact_external_id LIKE '%554796164699%'
        ORDER BY c.id DESC
        LIMIT 1
    ");
    $charles = $stmt->fetch();
    
    if ($charles) {
        echo "4) CONVERSA DE CHARLES DIETRICH:\n";
        echo str_repeat('-', 100) . "\n";
        echo "ID: {$charles['id']}\n";
        echo "Contact: {$charles['contact_name']}\n";
        echo "last_message_at RAW: '{$charles['last_message_at']}'\n";
        echo "created_at RAW: '{$charles['created_at']}'\n";
        echo "updated_at RAW: '{$charles['updated_at']}'\n";
        
        $timestamp = $charles['last_message_at'] ?: $charles['created_at'];
        echo "\nTIMESTAMP USADO: '{$timestamp}'\n";
        
        // Testa diferentes formas de formatação
        echo "\nTESTES DE FORMATAÇÃO:\n";
        
        // 1. Regex manual
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/', $timestamp, $m)) {
            echo "  Regex manual: {$m[3]}/{$m[2]} {$m[4]}:{$m[5]}\n";
        } else {
            echo "  Regex manual: NO MATCH\n";
        }
        
        // 2. DateTime sem timezone
        try {
            $dt = new DateTime($timestamp);
            echo "  DateTime (no tz): " . $dt->format('d/m H:i') . "\n";
        } catch (Exception $e) {
            echo "  DateTime (no tz): ERROR - " . $e->getMessage() . "\n";
        }
        
        // 3. DateTime com UTC
        try {
            $dt = new DateTime($timestamp, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            echo "  DateTime (UTC->SP): " . $dt->format('d/m H:i') . "\n";
        } catch (Exception $e) {
            echo "  DateTime (UTC->SP): ERROR - " . $e->getMessage() . "\n";
        }
        
        // 4. DateTime com SP
        try {
            $dt = new DateTime($timestamp, new DateTimeZone('America/Sao_Paulo'));
            echo "  DateTime (SP): " . $dt->format('d/m H:i') . "\n";
        } catch (Exception $e) {
            echo "  DateTime (SP): ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== FIM DO DIAGNÓSTICO ===\n";
    
} catch (PDOException $e) {
    echo "ERRO DB: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
