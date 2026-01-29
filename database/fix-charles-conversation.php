<?php
/**
 * Script para corrigir a conversa do Charles (121) e seus eventos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

echo "=== CORREÇÃO CONVERSA CHARLES (121) ===\n\n";

// 1. Atualizar tenant_id dos eventos da conversa 121 que estão NULL
echo "1. Atualizando tenant_id dos eventos da conversa 121 que estão NULL...\n";
$stmt = $db->prepare("UPDATE communication_events SET tenant_id = 25 WHERE conversation_id = 121 AND tenant_id IS NULL");
$stmt->execute();
echo "   Eventos atualizados: " . $stmt->rowCount() . "\n";

// 2. Atualizar channel_id da conversa 121
echo "\n2. Atualizando channel_id da conversa 121...\n";
$stmt = $db->prepare("UPDATE conversations SET channel_id = 'pixel12digital', session_id = 'pixel12digital' WHERE id = 121");
$stmt->execute();
echo "   Conversa atualizada: " . $stmt->rowCount() . "\n";

// 3. Verificar resultado
echo "\n3. Verificando resultado...\n";
$stmt = $db->query("SELECT id, tenant_id, conversation_id FROM communication_events WHERE conversation_id = 121");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Eventos da conversa 121:\n";
foreach ($events as $e) {
    echo "   - ID: {$e['id']} | tenant_id: " . ($e['tenant_id'] ?? 'NULL') . "\n";
}

$stmt = $db->query("SELECT id, channel_id, session_id, tenant_id FROM conversations WHERE id = 121");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\n   Conversa 121: channel_id=" . ($conv['channel_id'] ?? 'NULL') . ", session_id=" . ($conv['session_id'] ?? 'NULL') . ", tenant_id=" . ($conv['tenant_id'] ?? 'NULL') . "\n";

echo "\n=== FIM ===\n";
