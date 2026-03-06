<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/DB.php';
use PixelHub\Core\DB;
$db = DB::getConnection();

$rows = $db->query("SELECT id, name, response_type, response_message, next_buttons, forward_to_human, update_lead_status FROM chatbot_flows ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "--- ID:{$r['id']} [{$r['name']}] fwd:{$r['forward_to_human']} status:{$r['update_lead_status']}\n";
    echo "msg: " . trim($r['response_message']) . "\n";
    echo "btn: " . ($r['next_buttons'] ?? 'NULL') . "\n\n";
}
