<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== STATUS SDR DISPATCH ===\n";

// Jobs agendados hoje
$stmt = $db->prepare("
    SELECT id, result_id, session_name, phone, status, scheduled_at, created_at, sent_at
    FROM sdr_dispatch_queue 
    WHERE DATE(scheduled_at) = CURDATE()
    ORDER BY scheduled_at DESC
");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nJobs agendados hoje (" . count($jobs) . "):\n";
foreach ($jobs as $j) {
    echo sprintf(
        "[%d] ID:%d | %s | %s | %s | Agendado:%s | Criado:%s\n",
        $j['id'],
        $j['result_id'],
        $j['phone'],
        $j['session_name'],
        $j['status'],
        $j['scheduled_at'],
        $j['created_at']
    );
}

// Jobs com erro
$stmt = $db->prepare("
    SELECT id, result_id, phone, status, error, scheduled_at, sent_at
    FROM sdr_dispatch_queue 
    WHERE status = 'failed' AND DATE(sent_at) = CURDATE()
    ORDER BY sent_at DESC
");
$stmt->execute();
$failed = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($failed) {
    echo "\nJobs com falha hoje:\n";
    foreach ($failed as $f) {
        echo sprintf(
            "[ERRO] ID:%d | %s | %s | %s\n",
            $f['id'],
            $f['phone'],
            $f['status'],
            $f['error'] ?? 'Sem erro registrado'
        );
    }
}

// Verificar se worker está rodando (última atualização)
$stmt = $db->prepare("
    SELECT MAX(sent_at) as last_run
    FROM sdr_dispatch_queue 
    WHERE status IN ('sent', 'failed') AND DATE(sent_at) = CURDATE()
");
$stmt->execute();
$last = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nÚltima execução do worker: " . ($last['last_run'] ?? 'Nenhuma hoje') . "\n";

// Configurações das sessões WhatsApp
$stmt = $db->prepare("
    SELECT session_name, is_active, 
           CASE WHEN whapi_api_token IS NOT NULL AND whapi_api_token != '' THEN 1 ELSE 0 END as has_token,
           updated_at
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name IS NOT NULL
    ORDER BY session_name
");
$stmt->execute();
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nSessões WhatsApp configuradas:\n";
foreach ($sessions as $s) {
    echo sprintf(
        "- %s | ativa:%s | token:%s | atualizado:%s\n",
        $s['session_name'],
        $s['is_active'] ? 'SIM' : 'NÃO',
        $s['has_token'] ? 'SIM' : 'NÃO',
        $s['updated_at']
    );
}

echo "\n=== FIM ===\n";
