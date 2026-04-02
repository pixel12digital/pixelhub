<?php
/**
 * Fix: conversas meta_official + source=prospecting com is_incoming_lead=1 incorretamente.
 * Estas foram enviadas ativamente por nós e devem aparecer em normalThreads (is_incoming_lead=0).
 * APAGAR após executar.
 */
define('ROOT_PATH', dirname(__DIR__));
$envFile = ROOT_PATH . '/.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, "\"' \t");
    }
}
$dsn  = 'mysql:host=' . ($env['DB_HOST'] ?? 'localhost') . ';dbname=' . ($env['DB_NAME'] ?? '') . ';charset=utf8mb4';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? '';
try {
    $db = new PDO($dsn, $user, $pass, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

echo "===== FIX: meta_official prospecting is_incoming_lead =====\n\n";

// Lista conversas afetadas
$stmt = $db->query("
    SELECT id, contact_name, contact_external_id, source, last_message_direction, last_message_at
    FROM conversations
    WHERE provider_type = 'meta_official'
      AND source = 'prospecting'
      AND is_incoming_lead = 1
    ORDER BY last_message_at DESC
");
$rows = $stmt->fetchAll();
echo "Conversas meta_official+prospecting com is_incoming_lead=1: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  ID={$r['id']} name={$r['contact_name']} phone={$r['contact_external_id']} dir={$r['last_message_direction']} at={$r['last_message_at']}\n";
}

if (empty($rows)) {
    echo "\nNenhuma conversa para corrigir.\n";
} else {
    // Executa o fix
    $fix = $db->exec("
        UPDATE conversations
        SET is_incoming_lead = 0
        WHERE provider_type = 'meta_official'
          AND source = 'prospecting'
          AND is_incoming_lead = 1
    ");
    echo "\nFix executado: {$fix} conversas atualizadas para is_incoming_lead=0\n";
    echo "Agora aparecem como threads normais no Inbox.\n";
}

echo "\n===== FIM =====\n";
echo "\nAPAGUE este arquivo após executar!\n";
