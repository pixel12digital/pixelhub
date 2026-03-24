<?php

/**
 * SDR AI Responder — Respostas Automáticas da IA
 *
 * Executa a cada 2 min via cron, seg–sáb, 07:30–21:00:
 *   *\/2 7-21 * * 1-6  cd ~/hub.pixel12digital.com.br && php scripts/sdr_ai_responder.php >> logs/sdr_ai_responder.log 2>&1
 *
 * Fluxo:
 * 1. Busca conversas SDR com resposta do lead não respondida pela IA
 *    (human_mode=0, last_inbound_at > last_ai_reply_at, reply_after <= NOW())
 * 2. Para cada conversa, carrega histórico e chama OpenAI
 * 3. Calcula delay humanizado e agenda envio
 * 4. Detecta estágio e intenções especiais (agendamento, opt-out)
 * 5. Envia via Whapi e atualiza estado da conversa
 *
 * ATENÇÃO: Ao receber webhook inbound, o WhapiWebhookController deve
 * atualizar sdr_conversations.last_inbound_at e conversation_id.
 */

// ─── Bootstrap ──────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    $prefix  = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use PixelHub\Core\Env;
use PixelHub\Services\SdrDispatchService;

Env::load();

$L = '[SDR_AI]';
echo "{$L} === Início === " . date('Y-m-d H:i:s') . "\n";

// ─── 0. Verifica pausa manual ────────────────────────────────────
if (SdrDispatchService::isPaused()) {
    echo "{$L} SDR pausado. Encerrando.\n";
    exit(0);
}

// ─── 1. Processa respostas pendentes ─────────────────────────────
try {
    $stats = SdrDispatchService::processInboundReplies();

    echo "{$L} Processadas: {$stats['processed']}\n";

    if (!empty($stats['errors'])) {
        foreach ($stats['errors'] as $err) {
            echo "{$L} ERRO: {$err}\n";
        }
    }
} catch (\Throwable $e) {
    echo "{$L} FATAL: {$e->getMessage()}\n";
    error_log("{$L} Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}

echo "{$L} === Fim === " . date('Y-m-d H:i:s') . "\n";

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents(
    $logDir . '/sdr_ai_responder.log',
    "[" . date('Y-m-d H:i:s') . "] processed={$stats['processed']} errors=" . count($stats['errors']) . "\n",
    FILE_APPEND
);
