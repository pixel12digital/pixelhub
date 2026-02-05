<?php
/**
 * Corrige conversas ImobSites com channel_id = NULL
 *
 * Quando o ConversationService rejeitava "ImobSites", conversas eram criadas
 * com channel_id = NULL. Isso impedia a exibição de mensagens no Inbox.
 *
 * Este script atualiza channel_id para 'ImobSites' quando:
 * - A conversa tem channel_id = NULL
 * - Existe pelo menos um evento da conversa com metadata.channel_id = 'ImobSites'
 *
 * Uso: php database/fix-conversations-imobsites-channel-null.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== Correção: conversas ImobSites com channel_id = NULL ===\n\n";

$db = DB::getConnection();

// 1. Lista conversas afetadas
$stmt = $db->query("
    SELECT c.id, c.contact_external_id, c.contact_name, c.channel_id, c.tenant_id,
           (SELECT COUNT(*) FROM communication_events ce WHERE ce.conversation_id = c.id) as event_count
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
      AND (c.channel_id IS NULL OR c.channel_id = '')
      AND EXISTS (
        SELECT 1 FROM communication_events ce
        WHERE ce.conversation_id = c.id
          AND (
            LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = 'imobsites'
            OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.sessionId')), ' ', ''))) = 'imobsites'
            OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')), ' ', ''))) = 'imobsites'
          )
        LIMIT 1
      )
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "Nenhuma conversa ImobSites com channel_id NULL encontrada.\n";
    exit(0);
}

echo "Encontradas " . count($conversations) . " conversa(s) para corrigir:\n\n";

foreach ($conversations as $c) {
    echo sprintf(
        "  ID %d | contact=%s | name=%s | events=%d\n",
        $c['id'],
        $c['contact_external_id'] ?? 'NULL',
        $c['contact_name'] ?? 'NULL',
        $c['event_count'] ?? 0
    );
}

echo "\nAtualizando channel_id para 'ImobSites'...\n";

$updateStmt = $db->prepare("
    UPDATE conversations
    SET channel_id = 'ImobSites', updated_at = NOW()
    WHERE id = ?
");
$updated = 0;
foreach ($conversations as $c) {
    $updateStmt->execute([$c['id']]);
    if ($updateStmt->rowCount() > 0) {
        $updated++;
        echo "  OK: conversa ID {$c['id']} atualizada\n";
    }
}

echo "\n=== Concluído: {$updated} conversa(s) corrigida(s) ===\n";
