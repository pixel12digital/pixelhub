<?php
/**
 * Fix pontual: corrige conversas Meta com is_incoming_lead=1 incorreto
 * Acesse UMA VEZ: https://hub.pixel12digital.com.br/fix_meta_conversations.php
 * APAGUE após usar.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (class_exists('PixelHub\\Core\\Env')) {
    \PixelHub\Core\Env::load(__DIR__ . '/../');
} elseif (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}

$db = \PixelHub\Core\DB::getConnection();

echo "Conversas Meta com is_incoming_lead=1 ANTES do fix:\n";
$before = $db->query("SELECT id, contact_name, contact_external_id, is_incoming_lead, source, last_message_direction FROM conversations WHERE provider_type='meta_official' AND is_incoming_lead=1")->fetchAll(\PDO::FETCH_ASSOC);
echo count($before) . " conversas afetadas:\n";
foreach ($before as $r) {
    echo "  ID={$r['id']} {$r['contact_external_id']} ({$r['contact_name']}) source={$r['source']} dir={$r['last_message_direction']}\n";
}

$stmt = $db->prepare("UPDATE conversations SET is_incoming_lead = 0 WHERE provider_type = 'meta_official' AND is_incoming_lead = 1");
$stmt->execute();
$affected = $stmt->rowCount();

echo "\nFix executado: {$affected} conversas atualizadas para is_incoming_lead=0\n";
echo "Agora essas conversas aparecem no Inbox normalmente.\n";
echo "\nAPAGUE este arquivo após executar!\n";
