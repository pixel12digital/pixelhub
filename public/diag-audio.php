<?php
/**
 * Diagnóstico de Áudio - Execução direta via web ou CLI
 * Acesso: https://hub.pixel12digital.com.br/diag-audio.php?key=diag2026
 */

// Proteção básica
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== 'diag2026') {
    http_response_code(403);
    die('Acesso negado');
}

header('Content-Type: text/plain; charset=utf-8');

// Carrega .env
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if ($line && strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim($val, '"\'');
        }
    }
}

try {
    $db = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("ERRO DB: " . $e->getMessage());
}

echo "=== DIAGNÓSTICO DE ÁUDIO - " . date('Y-m-d H:i:s') . " ===\n\n";

// 1. Últimos eventos INBOUND
echo "1) ÚLTIMOS 15 EVENTOS INBOUND (todas mídias):\n";
echo str_repeat("-", 80) . "\n";
$sql = "SELECT 
    ce.id,
    ce.event_id,
    ce.created_at,
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) AS raw_type,
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.mimetype')) AS mimetype,
    CASE 
        WHEN ce.payload LIKE '%audioMessage%' OR ce.payload LIKE '%ptt%' OR ce.payload LIKE '%audio%' THEN 'AUDIO'
        WHEN ce.payload LIKE '%imageMessage%' OR ce.payload LIKE '%image%' THEN 'IMAGE'
        WHEN ce.payload LIKE '%videoMessage%' OR ce.payload LIKE '%video%' THEN 'VIDEO'
        ELSE 'TEXT/OTHER'
    END AS detected_type
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
ORDER BY ce.created_at DESC
LIMIT 15";
$stmt = $db->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as $e) {
    echo sprintf("ID:%s | %s | From:%s | Type:%s | Mime:%s | Detected:%s\n",
        $e['id'],
        $e['created_at'],
        substr($e['msg_from'] ?? 'N/A', 0, 20),
        $e['raw_type'] ?? 'N/A',
        substr($e['mimetype'] ?? 'N/A', 0, 25),
        $e['detected_type']
    );
}
if (empty($events)) echo "NENHUM EVENTO ENCONTRADO!\n";

// 2. Eventos de ÁUDIO especificamente
echo "\n\n2) EVENTOS DE ÁUDIO (últimas 48h):\n";
echo str_repeat("-", 80) . "\n";
$sql = "SELECT 
    ce.id,
    ce.event_id,
    ce.created_at,
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
  AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
  AND (ce.payload LIKE '%audioMessage%' OR ce.payload LIKE '%ptt%' 
       OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) IN ('audio', 'ptt'))
ORDER BY ce.created_at DESC
LIMIT 10";
$stmt = $db->query($sql);
$audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($audios as $a) {
    echo sprintf("ID:%s | %s | From:%s\n", $a['id'], $a['created_at'], $a['msg_from'] ?? 'N/A');
}
if (empty($audios)) echo "NENHUM ÁUDIO NAS ÚLTIMAS 48H!\n";

// 3. Mídias salvas
echo "\n\n3) ÚLTIMAS 15 MÍDIAS SALVAS (communication_media):\n";
echo str_repeat("-", 80) . "\n";
$sql = "SELECT id, event_id, media_type, mime_type, stored_path, file_size, created_at 
        FROM communication_media ORDER BY created_at DESC LIMIT 15";
$stmt = $db->query($sql);
$medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($medias as $m) {
    $pathShort = $m['stored_path'] ? '...' . substr($m['stored_path'], -30) : 'NULL';
    echo sprintf("ID:%s | Event:%s | Type:%s | Mime:%s | Size:%s | Path:%s | %s\n",
        $m['id'],
        substr($m['event_id'] ?? 'N/A', 0, 15),
        $m['media_type'] ?? 'N/A',
        $m['mime_type'] ?? 'N/A',
        $m['file_size'] ?? 'N/A',
        $pathShort,
        $m['created_at']
    );
}
if (empty($medias)) echo "NENHUMA MÍDIA SALVA!\n";

// 4. Mídias de ÁUDIO
echo "\n\n4) MÍDIAS DE ÁUDIO (últimas 48h):\n";
echo str_repeat("-", 80) . "\n";
$sql = "SELECT id, event_id, media_type, mime_type, stored_path, created_at 
        FROM communication_media 
        WHERE media_type = 'audio' OR mime_type LIKE 'audio/%'
        ORDER BY created_at DESC LIMIT 10";
$stmt = $db->query($sql);
$audioMedias = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($audioMedias as $m) {
    echo sprintf("ID:%s | Event:%s | Mime:%s | Path:%s | %s\n",
        $m['id'],
        $m['event_id'] ?? 'N/A',
        $m['mime_type'] ?? 'N/A',
        $m['stored_path'] ? 'EXISTS' : 'NULL',
        $m['created_at']
    );
}
if (empty($audioMedias)) echo "NENHUMA MÍDIA DE ÁUDIO!\n";

// 5. Verificar se arquivo existe (última mídia)
if (!empty($medias)) {
    echo "\n\n5) VERIFICAÇÃO DE ARQUIVOS (últimas 3 mídias):\n";
    echo str_repeat("-", 80) . "\n";
    $storageBase = __DIR__ . '/../storage/media/';
    foreach (array_slice($medias, 0, 3) as $m) {
        if ($m['stored_path']) {
            $fullPath = $storageBase . $m['stored_path'];
            $exists = file_exists($fullPath);
            $size = $exists ? filesize($fullPath) : 0;
            echo sprintf("ID:%s | Path:%s | Exists:%s | Size:%s bytes\n",
                $m['id'],
                $m['stored_path'],
                $exists ? 'SIM' : 'NAO',
                $size
            );
        }
    }
}

// 6. Estrutura da tabela communication_media
echo "\n\n6) COLUNAS DA TABELA communication_media:\n";
echo str_repeat("-", 80) . "\n";
$sql = "SHOW COLUMNS FROM communication_media";
$stmt = $db->query($sql);
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo sprintf("%s (%s) %s\n", $c['Field'], $c['Type'], $c['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
}

// 7. Últimos erros no error_log (se acessível)
echo "\n\n7) RESUMO:\n";
echo str_repeat("-", 80) . "\n";
echo "Total eventos inbound (últimas 48h): ";
$sql = "SELECT COUNT(*) FROM communication_events WHERE event_type = 'whatsapp.inbound.message' AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
echo $db->query($sql)->fetchColumn() . "\n";

echo "Total eventos de áudio (últimas 48h): " . count($audios) . "\n";
echo "Total mídias salvas: " . count($medias) . "\n";
echo "Total mídias de áudio: " . count($audioMedias) . "\n";

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
