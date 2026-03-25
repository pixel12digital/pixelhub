<?php
/**
 * Backfill: seta last_inbound_at em sdr_conversations onde está NULL
 * mas existem eventos inbound em communication_events para o mesmo telefone.
 * Também seta conversation_id se estiver faltando.
 *
 * Uso: php scripts/sdr_backfill_inbound.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    $base = __DIR__ . '/../src/';
    $file = $base . str_replace('\\', '/', substr($class, strlen('PixelHub\\'))) . '.php';
    if (strncmp('PixelHub\\', $class, 9) === 0 && file_exists($file)) require $file;
});

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "[BACKFILL] Início " . date('Y-m-d H:i:s') . "\n";

// Busca sdr_conversations sem last_inbound_at, criadas nos últimos 7 dias
$convs = $db->query("
    SELECT id, phone FROM sdr_conversations
    WHERE last_inbound_at IS NULL
      AND stage NOT IN ('closed_win','closed_lost','opted_out')
      AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetchAll(PDO::FETCH_ASSOC);

echo "[BACKFILL] " . count($convs) . " conversas sem last_inbound_at\n";

$updated = 0;
foreach ($convs as $conv) {
    $phone  = $conv['phone'];
    // digits only para buscar no JSON
    $digits = preg_replace('/[^0-9]/', '', $phone);

    // Busca último inbound para esse telefone
    $stmt = $db->prepare("
        SELECT ce.id, ce.created_at, ce.conversation_id
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message'
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
          )
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    $stmt->execute(['%' . $digits . '%', '%' . substr($digits, -9) . '%']);
    $evt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($evt) {
        $db->prepare("
            UPDATE sdr_conversations
            SET last_inbound_at = ?,
                conversation_id  = COALESCE(conversation_id, ?),
                updated_at       = NOW()
            WHERE id = ?
        ")->execute([$evt['created_at'], $evt['conversation_id'], $conv['id']]);
        echo "[BACKFILL] Atualizado conv SDR #{$conv['id']} ({$phone}) → last_inbound_at={$evt['created_at']}\n";
        $updated++;
    }
}

echo "[BACKFILL] {$updated} conversas atualizadas.\n";
echo "[BACKFILL] Fim " . date('Y-m-d H:i:s') . "\n";
