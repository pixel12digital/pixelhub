<?php
/**
 * SDR Test Flow — Valida o fluxo completo enviando para um número de teste
 * Uso: php scripts/sdr_test_flow.php [phone] [session]
 * Ex:  php scripts/sdr_test_flow.php 47996164699 orsegups
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
use PixelHub\Services\SdrDispatchService;
use PixelHub\Services\PhoneNormalizer;

Env::load();

$rawPhone = $argv[1] ?? '47996164699';
$session  = $argv[2] ?? Env::get('SDR_WHAPI_SESSION', 'orsegups');
$testName = 'Pixel12Digital Teste';

$phone = PhoneNormalizer::toE164OrNull($rawPhone);
if (!$phone) {
    echo "[TEST] Telefone inválido: {$rawPhone}\n";
    exit(1);
}

echo "[TEST] === Teste de Fluxo SDR ===\n";
echo "[TEST] Telefone : {$phone}\n";
echo "[TEST] Sessão   : {$session}\n";
echo "[TEST] " . date('Y-m-d H:i:s') . "\n\n";

$db = DB::getConnection();

// 1. Verifica conversa ativa existente
$existingConv = $db->prepare("SELECT id, stage FROM sdr_conversations WHERE phone = ? AND stage NOT IN ('closed_win','closed_lost','opted_out') LIMIT 1");
$existingConv->execute([$phone]);
$existing = $existingConv->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    echo "[TEST] AVISO: conversa ativa #{$existing['id']} (stage={$existing['stage']}) já existe.\n";
    echo "[TEST] Atualizando last_inbound_at para re-testar AI responder...\n";
    $db->prepare("UPDATE sdr_conversations SET last_inbound_at = NOW(), updated_at = NOW() WHERE id = ?")
       ->execute([$existing['id']]);
    echo "[TEST] ✓ last_inbound_at atualizado. AI responder deve responder em até 2 min.\n";
    exit(0);
}

// 2. Insere job de teste direto na sdr_dispatch_queue (scheduled_at = agora)
$message = SdrDispatchService::buildOpeningMessage($testName);
echo "[TEST] Mensagem de abertura: \"{$message}\"\n";

$db->prepare("
    INSERT INTO sdr_dispatch_queue
        (result_id, recipe_id, session_name, phone, establishment_name, message, scheduled_at, status, created_at)
    VALUES (0, 0, ?, ?, ?, ?, NOW(), 'queued', NOW())
")->execute([$session, $phone, $testName, $message]);

$jobId = (int) $db->lastInsertId();
echo "[TEST] ✓ Job #{$jobId} inserido na fila\n";

// 3. Executa envio diretamente
$job = $db->prepare("SELECT * FROM sdr_dispatch_queue WHERE id = ?");
$job->execute([$jobId]);
$jobRow = $job->fetch(PDO::FETCH_ASSOC);

SdrDispatchService::markProcessing($jobId);
$result = SdrDispatchService::sendOpeningMessage($jobRow);

if (!($result['success'] ?? false)) {
    $err = $result['error'] ?? json_encode($result);
    SdrDispatchService::markFailed($jobId, $err);
    echo "[TEST] ERRO no envio: {$err}\n";
    exit(1);
}

$msgId = $result['message_id'] ?? ($result['id'] ?? 'ok');
SdrDispatchService::markSent($jobId, $msgId);
echo "[TEST] ✓ Mensagem enviada via {$session}! message_id={$msgId}\n";

// 4. Cria sdr_conversations para que AI processe a resposta
$db->prepare("
    INSERT INTO sdr_conversations (result_id, phone, establishment_name, stage, human_mode, created_at, updated_at)
    VALUES (0, ?, ?, 'opening', 0, NOW(), NOW())
    ON DUPLICATE KEY UPDATE updated_at = NOW()
")->execute([$phone, $testName]);
echo "[TEST] ✓ sdr_conversations criado\n";

echo "\n[TEST] === Próximos passos ===\n";
echo "[TEST] 1. Responda a mensagem que chegou no WhatsApp ({$phone})\n";
echo "[TEST] 2. Poller (~30s) vai ingerir a resposta e setar last_inbound_at\n";
echo "[TEST] 3. AI responder (cron */2 min) vai processar e responder via {$session}\n";
echo "[TEST] Fim " . date('H:i:s') . "\n";
