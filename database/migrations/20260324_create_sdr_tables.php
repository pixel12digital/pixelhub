<?php

/**
 * Migration: SDR (Sales Development Representative) Tables
 * 
 * - sdr_dispatch_queue : fila de disparo da 1ª mensagem com timing humanizado
 * - sdr_conversations  : estado de cada conversa ativa pelo SDR
 */

require_once __DIR__ . '/../../src/Core/DB.php';
require_once __DIR__ . '/../../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "=== Migration: SDR Tables ===\n";

// ─── sdr_dispatch_queue ──────────────────────────────────────────────────────
$db->exec("
CREATE TABLE IF NOT EXISTS sdr_dispatch_queue (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    result_id           INT UNSIGNED NOT NULL COMMENT 'prospecting_results.id',
    recipe_id           INT UNSIGNED NOT NULL,
    phone               VARCHAR(30) NOT NULL,
    establishment_name  VARCHAR(255) NOT NULL,
    message             TEXT NOT NULL,
    scheduled_at        DATETIME NOT NULL,
    status              ENUM('queued','processing','sent','failed') NOT NULL DEFAULT 'queued',
    sent_at             DATETIME NULL,
    whapi_message_id    VARCHAR(255) NULL,
    error               TEXT NULL,
    attempts            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result (result_id),
    KEY idx_status_scheduled (status, scheduled_at),
    KEY idx_recipe (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fila de disparo da 1ª mensagem SDR (abertura)';
");
echo "OK: sdr_dispatch_queue\n";

// ─── sdr_conversations ───────────────────────────────────────────────────────
$db->exec("
CREATE TABLE IF NOT EXISTS sdr_conversations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    result_id           INT UNSIGNED NOT NULL COMMENT 'prospecting_results.id',
    conversation_id     INT UNSIGNED NULL COMMENT 'conversations.id — preenchido após 1ª resposta',
    phone               VARCHAR(30) NOT NULL,
    establishment_name  VARCHAR(255) NOT NULL,
    stage               ENUM(
                            'opening',
                            'decision_maker',
                            'qualification',
                            'exploration',
                            'scheduling',
                            'closed_win',
                            'closed_lost',
                            'opted_out'
                        ) NOT NULL DEFAULT 'opening',
    human_mode          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = IA pausada, humano assumiu',
    human_mode_set_by   INT UNSIGNED NULL,
    human_mode_at       DATETIME NULL,
    ai_history          JSON NULL COMMENT 'Array de {role, content} para contexto OpenAI',
    reply_after         DATETIME NULL COMMENT 'IA só responde após este timestamp (delay humanizado)',
    lead_id             INT UNSIGNED NULL,
    opp_id              INT UNSIGNED NULL,
    scheduled_at        DATETIME NULL COMMENT 'Data/hora da visita agendada',
    last_inbound_at     DATETIME NULL,
    last_ai_reply_at    DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result (result_id),
    KEY idx_stage (stage),
    KEY idx_human_mode (human_mode),
    KEY idx_conversation (conversation_id),
    KEY idx_reply_after (reply_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estado de cada conversa SDR (abertura → agendamento)';
");
echo "OK: sdr_conversations\n";

echo "=== Migration concluída ===\n";
