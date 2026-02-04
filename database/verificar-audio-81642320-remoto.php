<?php
/**
 * Verificação do áudio 81642320 no banco remoto
 * Consulta evento 129276, mídia 480 e status do arquivo
 * 
 * Uso: php database/verificar-audio-81642320-remoto.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO ÁUDIO 81642320 - BANCO REMOTO ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

// 1. Conexão
$connInfo = $db->query("SELECT DATABASE() as db, @@hostname as host")->fetch(PDO::FETCH_ASSOC);
$dbHost = Env::get('DB_HOST', 'localhost');
echo "1) CONEXÃO:\n";
echo "   DB_HOST (env):  {$dbHost}\n";
echo "   DATABASE:       " . ($connInfo['db'] ?? 'N/A') . "\n";
echo "   @@hostname:     " . ($connInfo['host'] ?? 'N/A') . "\n";
echo "   (Se DB_HOST ≠ localhost, está usando banco remoto)\n\n";

// 2. Evento 129276
echo "2) EVENTO 129276:\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT id, event_id, conversation_id, status, created_at, tenant_id,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) AS raw_type,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.media.type')) AS media_type
    FROM communication_events
    WHERE id = 129276
");
$stmt->execute();
$ev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    echo "   ❌ Evento 129276 não encontrado\n\n";
} else {
    echo "   id:              {$ev['id']}\n";
    echo "   event_id:        {$ev['event_id']}\n";
    echo "   conversation_id: {$ev['conversation_id']}\n";
    echo "   status:          {$ev['status']}\n";
    echo "   created_at:      {$ev['created_at']}\n";
    echo "   tenant_id:       " . ($ev['tenant_id'] ?? 'NULL') . "\n";
    echo "   tipo (raw):      " . ($ev['raw_type'] ?? 'N/A') . "\n";
    echo "   tipo (media):    " . ($ev['media_type'] ?? 'N/A') . "\n";
    echo "   ✅ Evento existe no banco\n\n";
}

// 3. Mídia (communication_media)
$eventId = $ev['event_id'] ?? null;
echo "3) MÍDIA (communication_media) para event_id=" . ($eventId ?: 'N/A') . ":\n";
echo str_repeat("-", 80) . "\n";
$media = null;
if ($eventId) {
    $mStmt = $db->prepare("
        SELECT id, event_id, media_type, mime_type, stored_path, file_name, file_size, created_at
        FROM communication_media
        WHERE event_id = ?
    ");
    $mStmt->execute([$eventId]);
    $media = $mStmt->fetch(PDO::FETCH_ASSOC);
}

$fileExists = false;
if (!$media) {
    echo "   ❌ Nenhum registro em communication_media\n\n";
} else {
    echo "   id:          {$media['id']}\n";
    echo "   media_type:  {$media['media_type']}\n";
    echo "   mime_type:  {$media['mime_type']}\n";
    echo "   stored_path: {$media['stored_path']}\n";
    echo "   file_name:  {$media['file_name']}\n";
    echo "   file_size:  " . ($media['file_size'] ? number_format($media['file_size']/1024, 1) . ' KB' : 'NULL') . "\n";
    
    $localPath = __DIR__ . '/../storage/' . $media['stored_path'];
    $fileExists = file_exists($localPath);
    echo "   file_exists (local): " . ($fileExists ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   (Verificação local: " . $localPath . ")\n";
    echo "   ⚠️  Se banco=remoto e storage=local, arquivo pode existir apenas no servidor de produção\n\n";
}

// 4. Eventos 555381642320 entre 11:30 e 12:05 em 03/02 (para confirmar se há outro áudio às 11:38)
echo "4) EVENTOS 555381642320 em 03/02 entre 11:30 e 12:05:\n";
echo str_repeat("-", 80) . "\n";
$stmt4 = $db->prepare("
    SELECT id, created_at,
           COALESCE(
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')),
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')),
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))
           ) AS tipo
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
      AND source_system = 'wpp_gateway'
      AND (payload LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?)
      AND created_at >= '2026-02-03 11:30:00'
      AND created_at <  '2026-02-03 12:05:00'
    ORDER BY created_at ASC
");
$stmt4->execute(['%555381642320%', '%555381642320%']);
$evts = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($evts)) {
    echo "   ❌ Nenhum evento nessa janela\n\n";
} else {
    foreach ($evts as $e) {
        $mark = ($e['id'] == 129276) ? ' <-- áudio PTT' : '';
        echo "   [{$e['created_at']}] id={$e['id']} tipo={$e['tipo']}{$mark}\n";
    }
    echo "\n   Conclusão: " . (count($evts) === 1 ? "Apenas 1 evento (11:34). Não há áudio às 11:38." : "Múltiplos eventos.") . "\n\n";
}

// 5. Resumo
echo str_repeat("=", 80) . "\n";
echo "RESUMO:\n";
echo "- Evento 129276: " . ($ev ? "✅ no banco" : "❌ não encontrado") . "\n";
echo "- Mídia 480:    " . ($media ? "✅ no banco (stored_path preenchido)" : "❌ não encontrada") . "\n";
echo "- Arquivo local:" . (isset($fileExists) && $fileExists ? " ✅ existe" : (isset($fileExists) ? " ❌ não existe" : " N/A")) . "\n";
echo "\nPara verificar se o arquivo existe em PRODUÇÃO, execute este script no servidor HostMedia.\n";
echo "=== FIM ===\n";
