<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
use PixelHub\Integrations\WhatsApp\WhapiCloudProvider;
use PixelHub\Services\WhatsAppProviderFactory;
use PixelHub\Controllers\SalesTrainingController;

/**
 * SDR (Sales Development Representative) — Serviço Central
 *
 * Responsável por:
 * - Planejamento do dia (sdr_planner) com timing humanizado
 * - Execução da fila (sdr_worker)
 * - Respostas automáticas da IA (sdr_ai_responder)
 * - Controle de takeover humano
 * - Notificações ao operador (47 996164699)
 * - Bot de comando via WhatsApp
 */
class SdrDispatchService
{
    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT       = 'sent';
    public const STATUS_FAILED     = 'failed';

    /** Janela de disparo da 1ª mensagem */
    public const DISPATCH_WINDOW_START = '09:00';
    public const DISPATCH_WINDOW_END   = '17:00';

    /** Janela de resposta IA (mais ampla — lead pode responder fora do horário) */
    public const AI_WINDOW_START = '07:30';
    public const AI_WINDOW_END   = '21:00';

    /** Máximo de envios por dia */
    public const MAX_PER_DAY = 100;

    /** Telefone do operador para notificações (sem +, sem espaço) */
    public const OWNER_PHONE = '5547996164699';

    // =========================================================================
    // PLANEJAMENTO — Humanized Timing
    // =========================================================================

    /**
     * Calcula horários humanizados para N envios dentro da janela do dia.
     * Distribui com perfis aleatórios: rápido/normal/devagar/pausa.
     * Nunca arredonda para :00 ou :05 — inclui segundos aleatórios.
     *
     * @return \DateTime[]
     */
    public static function calculateHumanTimes(int $count, ?string $date = null): array
    {
        if ($count <= 0) return [];

        $date = $date ?? date('Y-m-d');

        // Janela 09:00 – 17:00, com pausa de almoço 12:00-13:20
        $windowStart = strtotime("{$date} " . self::DISPATCH_WINDOW_START . ':00');
        $windowEnd   = strtotime("{$date} " . self::DISPATCH_WINDOW_END   . ':00');
        $lunchStart  = strtotime("{$date} 12:00:00");
        $lunchEnd    = strtotime("{$date} 13:20:00");

        // Distribuição de perfis de intervalo (em segundos)
        $profiles = [
            ['min' => 120,  'max' => 240,  'weight' => 15], // rápido: 2-4 min
            ['min' => 360,  'max' => 720,  'weight' => 45], // normal: 6-12 min
            ['min' => 900,  'max' => 1680, 'weight' => 25], // devagar: 15-28 min
            ['min' => 1800, 'max' => 3300, 'weight' => 15], // pausa: 30-55 min
        ];

        // Pico de manhã: entre 09:30-11:30 concentra ~40% dos envios
        $morningBoost = (int) round($count * 0.40);
        $morningStart = strtotime("{$date} 09:30:00");
        $morningEnd   = strtotime("{$date} 11:30:00");

        $times = [];
        $cursor = $windowStart + rand(30, 180); // inicia com 30s-3min de variação

        for ($i = 0; $i < $count; $i++) {
            // Pula almoço
            if ($cursor >= $lunchStart && $cursor < $lunchEnd) {
                $cursor = $lunchEnd + rand(60, 300);
            }

            // Respeita fim da janela
            if ($cursor >= $windowEnd) {
                $cursor = $windowEnd - rand(60, 300);
            }

            $dt = new \DateTime();
            $dt->setTimestamp($cursor);
            $times[] = clone $dt;

            // Sorteia próximo intervalo com base nos perfis
            $roll = rand(1, 100);
            $cumWeight = 0;
            $interval = 600; // fallback 10 min
            foreach ($profiles as $p) {
                $cumWeight += $p['weight'];
                if ($roll <= $cumWeight) {
                    $interval = rand($p['min'], $p['max']);
                    break;
                }
            }

            // Adiciona segundos aleatórios para parecer ainda mais humano
            $interval += rand(-30, 60);
            $interval = max(90, $interval);

            $cursor += $interval;
        }

        return $times;
    }

    /**
     * Enfileira leads de uma receita para disparo no dia atual.
     * Anti-duplicata: ignora result_id já presente na fila (qualquer status).
     *
     * @return array ['enqueued' => int, 'skipped_duplicate' => int, 'skipped_no_phone' => int]
     */
    public static function planDay(int $recipeId, int $maxPerDay = self::MAX_PER_DAY): array
    {
        $db = DB::getConnection();

        // Busca resultados com telefone, não descartados e sem envio prévio
        $stmt = $db->prepare("
            SELECT pr.id, pr.name, pr.phone
            FROM prospecting_results pr
            WHERE pr.recipe_id = ?
              AND pr.status != 'discarded'
              AND pr.whatsapp_sent_at IS NULL
              AND (pr.phone IS NOT NULL AND pr.phone != '')
              AND NOT EXISTS (
                  SELECT 1 FROM sdr_dispatch_queue dq WHERE dq.result_id = pr.id
              )
            ORDER BY pr.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$recipeId, $maxPerDay]);
        $candidates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = ['enqueued' => 0, 'skipped_duplicate' => 0, 'skipped_no_phone' => 0];

        if (empty($candidates)) {
            return $stats;
        }

        $times = self::calculateHumanTimes(count($candidates));
        $insertQueue = $db->prepare("
            INSERT INTO sdr_dispatch_queue
                (result_id, recipe_id, phone, establishment_name, message, scheduled_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");
        $insertConv = $db->prepare("
            INSERT INTO sdr_conversations
                (result_id, phone, establishment_name, stage, human_mode, created_at, updated_at)
            VALUES (?, ?, ?, 'opening', 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");

        foreach ($candidates as $i => $lead) {
            $phone = PhoneNormalizer::toE164OrNull($lead['phone']);
            if (!$phone) {
                $stats['skipped_no_phone']++;
                continue;
            }

            $name    = $lead['name'] ?? 'estabelecimento';
            $message = self::buildOpeningMessage($name);
            $schAt   = isset($times[$i]) ? $times[$i]->format('Y-m-d H:i:s') : date('Y-m-d 09:00:00');

            $insertQueue->execute([$lead['id'], $recipeId, $phone, $name, $message, $schAt]);
            $insertConv->execute([$lead['id'], $phone, $name]);
            $stats['enqueued']++;
        }

        return $stats;
    }

    /**
     * Gera a mensagem de abertura para um estabelecimento.
     */
    public static function buildOpeningMessage(string $name): string
    {
        $cleanName = mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
        return "Oi, é da {$cleanName}? Qual o horário de funcionamento, por gentileza?";
    }

    // =========================================================================
    // WORKER — Execução da fila
    // =========================================================================

    /**
     * Retorna jobs prontos para envio (scheduled_at <= NOW(), status = queued).
     */
    public static function fetchReadyJobs(int $limit = 5): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM sdr_dispatch_queue
            WHERE status = 'queued'
              AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function markProcessing(int $jobId): void
    {
        $db = DB::getConnection();
        $db->prepare("UPDATE sdr_dispatch_queue SET status='processing', attempts=attempts+1 WHERE id=?")
           ->execute([$jobId]);
    }

    public static function markSent(int $jobId, string $whapiMsgId): void
    {
        $db = DB::getConnection();
        $db->prepare("UPDATE sdr_dispatch_queue SET status='sent', sent_at=NOW(), whapi_message_id=? WHERE id=?")
           ->execute([$whapiMsgId, $jobId]);
    }

    public static function markFailed(int $jobId, string $error): void
    {
        $db = DB::getConnection();
        $db->prepare("UPDATE sdr_dispatch_queue SET status='failed', error=? WHERE id=?")
           ->execute([substr($error, 0, 500), $jobId]);
    }

    /**
     * Envia a mensagem de abertura via Whapi (sessão SDR/Orsegups).
     * Registra o envio em communication_events para que apareça no Inbox.
     */
    public static function sendOpeningMessage(array $job): array
    {
        $provider = self::getSdrProvider();
        $result   = $provider->sendText($job['phone'], $job['message']);

        if (!($result['success'] ?? false)) {
            return $result;
        }

        // Marca whatsapp_sent_at no prospecting_results
        $db = DB::getConnection();
        $db->prepare("UPDATE prospecting_results SET whatsapp_sent_at=NOW() WHERE id=?")
           ->execute([$job['result_id']]);

        // Registra em communication_events para aparecer no Inbox
        self::registerOutboundEvent($job['phone'], $job['message'], $result['message_id'] ?? null);

        return $result;
    }

    // =========================================================================
    // AI RESPONDER — Conversas automáticas
    // =========================================================================

    /**
     * Processa todas as conversas SDR que aguardam resposta da IA.
     * - human_mode = 0
     * - last_inbound_at > last_ai_reply_at (ou last_ai_reply_at IS NULL)
     * - reply_after <= NOW() (delay humanizado já passou)
     *
     * @return array ['processed' => int, 'errors' => string[]]
     */
    public static function processInboundReplies(): array
    {
        $db   = DB::getConnection();
        $stats = ['processed' => 0, 'errors' => []];

        $stmt = $db->query("
            SELECT sc.*
            FROM sdr_conversations sc
            WHERE sc.human_mode = 0
              AND sc.stage NOT IN ('closed_win','closed_lost','opted_out')
              AND sc.last_inbound_at IS NOT NULL
              AND (sc.last_ai_reply_at IS NULL OR sc.last_inbound_at > sc.last_ai_reply_at)
              AND (sc.reply_after IS NULL OR sc.reply_after <= NOW())
            ORDER BY sc.last_inbound_at ASC
            LIMIT 10
        ");
        $convs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($convs as $conv) {
            try {
                self::handleConversationTurn($conv, $db);
                $stats['processed']++;
            } catch (\Throwable $e) {
                $err = "[SDR_AI] conv #{$conv['id']}: " . $e->getMessage();
                error_log($err);
                $stats['errors'][] = $err;
            }
        }

        return $stats;
    }

    /**
     * Trata um turno de conversa: carrega histórico → chama IA → envia resposta.
     */
    private static function handleConversationTurn(array $conv, \PDO $db): void
    {
        // 1. Carrega histórico de mensagens
        $history = self::loadConversationHistory($conv);

        if (empty($history)) {
            error_log("[SDR_AI] conv #{$conv['id']}: histórico vazio, ignorando");
            return;
        }

        // 2. Monta ai_history com mensagens novas
        $aiHistory = json_decode($conv['ai_history'] ?? '[]', true) ?: [];

        // Adiciona apenas mensagens do lead ainda não no ai_history
        $existingCount = count($aiHistory);
        foreach ($history as $msg) {
            if ($msg['direction'] === 'inbound') {
                $aiHistory[] = ['role' => 'user', 'content' => $msg['text']];
            }
        }

        // 3. Chama OpenAI
        $aiReply = self::callSdrAI($aiHistory);

        if (empty($aiReply)) {
            error_log("[SDR_AI] conv #{$conv['id']}: IA retornou vazio");
            return;
        }

        // 4. Calcula delay humanizado
        $lastInbound   = end($history)['text'] ?? '';
        $delaySecs     = self::calculateReplyDelay($lastInbound, $aiReply);
        $replyAfter    = (new \DateTime())->modify("+{$delaySecs} seconds");

        // 5. Detecta stage e intenção de agendamento
        $newStage      = self::detectStage($aiReply, $lastInbound, $conv['stage']);
        $wantsSchedule = self::detectSchedulingIntent($aiReply);

        // 6. Persiste ai_history + reply_after + stage
        $aiHistory[] = ['role' => 'assistant', 'content' => $aiReply];
        $db->prepare("
            UPDATE sdr_conversations SET
                ai_history   = ?,
                reply_after  = ?,
                stage        = ?,
                updated_at   = NOW()
            WHERE id = ?
        ")->execute([json_encode($aiHistory, JSON_UNESCAPED_UNICODE), $replyAfter->format('Y-m-d H:i:s'), $newStage, $conv['id']]);

        // 7. Envia quando delay passa (worker chama novamente)
        // Para simplificar, envia imediatamente se delaySecs <= 60, senão agenda em reply_pending
        // O ai_responder roda a cada 2min — o delay é controlado por reply_after no WHERE acima
        // Aqui já passamos pelo filtro reply_after <= NOW(), então podemos enviar
        self::deliverAiReply($conv, $aiReply, $db);

        // 8. Agendamento
        if ($wantsSchedule && $newStage === 'scheduling') {
            // Notifica operador — IA chegou no ponto de agendamento
            self::notifyOwner(
                "📅 Lead em agendamento!\n" .
                "*{$conv['establishment_name']}* ({$conv['phone']})\n" .
                "Última: \"{$lastInbound}\"\n" .
                "Resposta IA: \"{$aiReply}\"\n" .
                "→ /prospecting (ver conversa)"
            );
        }

        // 9. Se IA detectar opt-out ou irritação, aciona takeover
        if (self::detectOptOut($lastInbound) || self::detectFrustration($lastInbound)) {
            self::setHumanMode($conv['id'], true, null);
            self::notifyOwner(
                "⚠️ Takeover automático\n" .
                "*{$conv['establishment_name']}* ({$conv['phone']})\n" .
                "Motivo: opt-out/irritação detectado\n" .
                "Lead: \"{$lastInbound}\"\n" .
                "→ Responda 'devolver {$conv['id']}' para voltar à IA"
            );
        }
    }

    /**
     * Entrega a resposta da IA via Whapi e registra no Inbox.
     */
    private static function deliverAiReply(array $conv, string $text, \PDO $db): void
    {
        $provider = self::getSdrProvider();
        $result   = $provider->sendText($conv['phone'], $text);

        if (!($result['success'] ?? false)) {
            error_log("[SDR_AI] Falha ao enviar resposta conv #{$conv['id']}: " . ($result['error'] ?? 'unknown'));
            return;
        }

        self::registerOutboundEvent($conv['phone'], $text, $result['message_id'] ?? null);

        $db->prepare("
            UPDATE sdr_conversations
            SET last_ai_reply_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$conv['id']]);
    }

    /**
     * Carrega histórico de mensagens da conversa (inbound + outbound) via communication_events.
     */
    public static function loadConversationHistory(array $conv): array
    {
        $db = DB::getConnection();

        if (!empty($conv['conversation_id'])) {
            // Conversa já vinculada — carrega via conversation_id
            $stmt = $db->prepare("
                SELECT
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body'))      AS text,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.direction')) AS direction,
                    created_at
                FROM communication_events
                WHERE conversation_id = ?
                  AND JSON_EXTRACT(payload, '$.body') IS NOT NULL
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $stmt->execute([$conv['conversation_id']]);
        } else {
            // Ainda sem conversation_id — busca por telefone direto
            $phone = $conv['phone'];
            $stmt = $db->prepare("
                SELECT
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body'))      AS text,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.direction')) AS direction,
                    created_at
                FROM communication_events
                WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE ?
                   OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to'))   LIKE ?
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $like = '%' . ltrim($phone, '5') . '%';
            $stmt->execute([$like, $like]);
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_filter($rows, fn($r) => !empty($r['text']));
    }

    /**
     * Chama OpenAI com o prompt do SDR e retorna a resposta de Charles.
     *
     * @param array $aiHistory Array de {role, content}
     */
    public static function callSdrAI(array $aiHistory): string
    {
        $apiKey = self::getOpenAiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY não configurada');
        }

        $model       = Env::get('OPENAI_MODEL', 'gpt-4.1-mini');
        $temperature = 0.75;
        $maxTokens   = 300;

        $messages = [['role' => 'system', 'content' => self::buildSdrSystemPrompt()]];
        foreach ($aiHistory as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new \RuntimeException('cURL error: ' . $curlErr);
        if ($httpCode !== 200) {
            $err = json_decode($response, true)['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('OpenAI error: ' . $err);
        }

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Calcula delay humanizado antes de responder.
     * Simula tempo de leitura + digitação, com variação.
     */
    public static function calculateReplyDelay(string $inboundText, string $replyText): int
    {
        $wordsIn  = max(1, str_word_count($inboundText));
        $wordsOut = max(1, str_word_count($replyText));

        $readTime  = (int) round($wordsIn  * 0.4); // ~0.4s por palavra lida
        $typeTime  = (int) round($wordsOut * 0.7); // ~0.7s por palavra digitada
        $variation = rand(-15, 30);

        $delay = $readTime + $typeTime + $variation;
        return max(40, min($delay, 240)); // entre 40s e 4min
    }

    // =========================================================================
    // DETECÇÃO DE STAGE / INTENT
    // =========================================================================

    public static function detectStage(string $aiReply, string $inbound, string $current): string
    {
        $reply = mb_strtolower($aiReply);
        $inb   = mb_strtolower($inbound);

        if (self::detectOptOut($inbound)) return 'opted_out';

        if (strpos($reply, 'combinado') !== false || strpos($reply, 'segunda') !== false
            || strpos($reply, 'terça') !== false || strpos($reply, 'tarde então') !== false
            || strpos($reply, 'manhã então') !== false) {
            return 'closed_win';
        }

        if (strpos($reply, 'fica melhor') !== false || strpos($reply, 'manhã ou tarde') !== false
            || strpos($reply, 'qual dia') !== false) {
            return 'scheduling';
        }

        if (strpos($reply, 'mostrar') !== false || strpos($reply, 'te mostrar') !== false
            || strpos($reply, '2 min') !== false || strpos($reply, 'lojas aqui') !== false) {
            return 'exploration';
        }

        if (strpos($reply, 'monitoramento') !== false || strpos($reply, 'empresa terceira') !== false
            || strpos($reply, 'vocês mesmos que monitoram') !== false) {
            return 'qualification';
        }

        if (strpos($reply, 'com quem eu falo') !== false || strpos($reply, 'pode ser você') !== false) {
            return 'decision_maker';
        }

        return $current; // mantém stage atual se não identificou
    }

    public static function detectSchedulingIntent(string $aiReply): bool
    {
        $lower = mb_strtolower($aiReply);
        return strpos($lower, 'manhã ou tarde') !== false
            || strpos($lower, 'qual dia') !== false
            || strpos($lower, 'fica melhor pra você') !== false;
    }

    public static function detectOptOut(string $text): bool
    {
        $lower = mb_strtolower($text);
        $triggers = ['para de me mandar', 'não me mande', 'me tira', 'pode tirar', 'sai da minha', 'bloquear', 'denunciar'];
        foreach ($triggers as $t) {
            if (strpos($lower, $t) !== false) return true;
        }
        return false;
    }

    public static function detectFrustration(string $text): bool
    {
        $lower = mb_strtolower($text);
        $triggers = ['idiota', 'chato', 'abusado', 'me deixa em paz', 'cala a boca', 'vai se', 'para com isso'];
        foreach ($triggers as $t) {
            if (strpos($lower, $t) !== false) return true;
        }
        return false;
    }

    // =========================================================================
    // TAKEOVER / CONTROLE HUMANO
    // =========================================================================

    /**
     * Ativa ou desativa modo humano para uma conversa SDR.
     */
    public static function setHumanMode(int $sdrConvId, bool $human, ?int $userId): void
    {
        $db = DB::getConnection();
        $db->prepare("
            UPDATE sdr_conversations SET
                human_mode         = ?,
                human_mode_set_by  = ?,
                human_mode_at      = ?,
                updated_at         = NOW()
            WHERE id = ?
        ")->execute([$human ? 1 : 0, $userId, $human ? date('Y-m-d H:i:s') : null, $sdrConvId]);
    }

    // =========================================================================
    // AGENDAMENTO (quando IA confirma visita)
    // =========================================================================

    /**
     * Finaliza o agendamento: cria lead + oportunidade + agenda + notifica operador.
     */
    public static function bookVisit(int $sdrConvId, string $period, string $dayOfWeek): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT sc.*, pr.recipe_id FROM sdr_conversations sc LEFT JOIN prospecting_results pr ON pr.id = sc.result_id WHERE sc.id = ?");
        $stmt->execute([$sdrConvId]);
        $conv = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$conv) return false;

        try {
            $db->beginTransaction();

            // Cria lead se ainda não existir
            $leadId = $conv['lead_id'];
            if (!$leadId) {
                $leadId = LeadService::create([
                    'name'   => $conv['establishment_name'],
                    'phone'  => $conv['phone'],
                    'source' => 'sdr_prospecting',
                ]);
                $db->prepare("UPDATE prospecting_results SET lead_id=? WHERE id=?")->execute([$leadId, $conv['result_id']]);
            }

            // Cria oportunidade se ainda não existir
            $oppId = $conv['opp_id'];
            if (!$oppId) {
                $oppId = OpportunityService::create([
                    'name'    => $conv['establishment_name'] . ' — SDR',
                    'lead_id' => $leadId,
                    'stage'   => 'new',
                    'origin'  => 'sdr_whatsapp',
                ], 0);
            }

            // Busca tipo de bloco COMERCIAL
            $agendaDate = self::resolveNextWeekday($dayOfWeek);
            $tipoId = $db->query("SELECT id FROM agenda_block_types WHERE UPPER(nome) LIKE '%COMERCIAL%' AND ativo=1 LIMIT 1")->fetchColumn();
            if (!$tipoId) {
                $tipoId = $db->query("SELECT id FROM agenda_block_types WHERE ativo=1 LIMIT 1")->fetchColumn();
            }

            AgendaService::createManualBlock(
                new \DateTime($agendaDate),
                [
                    'tipo_id'     => (int) $tipoId,
                    'resumo'      => "Visita SDR — {$conv['establishment_name']}",
                    'hora_inicio' => $period === 'manha' ? '09:00' : '14:00',
                    'hora_fim'    => $period === 'manha' ? '10:00' : '15:00',
                ]
            );

            // Atualiza sdr_conversations
            $db->prepare("
                UPDATE sdr_conversations SET
                    stage        = 'closed_win',
                    lead_id      = ?,
                    opp_id       = ?,
                    scheduled_at = ?,
                    updated_at   = NOW()
                WHERE id = ?
            ")->execute([$leadId, $oppId, $agendaDate . ' ' . ($period === 'manha' ? '09:00:00' : '14:00:00'), $sdrConvId]);

            $db->commit();

            // Notifica operador
            $dayLabel    = ucfirst($dayOfWeek);
            $periodLabel = $period === 'manha' ? 'manhã' : 'tarde';
            self::notifyOwner(
                "✅ *Visita agendada!*\n" .
                "Empresa: *{$conv['establishment_name']}*\n" .
                "Telefone: {$conv['phone']}\n" .
                "Quando: {$dayLabel} à {$periodLabel} ({$agendaDate})\n" .
                "Lead: " . Env::get('APP_URL', 'https://hub.pixel12digital.com.br') . "/leads/edit?id={$leadId}\n" .
                "Oportunidade: " . Env::get('APP_URL', 'https://hub.pixel12digital.com.br') . "/opportunities/view?id={$oppId}"
            );

            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log("[SDR_BOOKING] Erro: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // BOT DE COMANDO (controle via WhatsApp do operador)
    // =========================================================================

    /**
     * Processa comando recebido do operador via WhatsApp.
     * Comandos: "assumir ID", "devolver ID", "status", "pausar", "retomar"
     *
     * @return string Resposta a enviar de volta ao operador
     */
    public static function processCommand(string $text, string $fromPhone): string
    {
        // Verifica se é do operador
        $normalized = PhoneNormalizer::toE164OrNull($fromPhone);
        if ($normalized !== self::OWNER_PHONE) {
            return ''; // Ignora comandos de outros números
        }

        $text  = trim(mb_strtolower($text));
        $parts = preg_split('/\s+/', $text);
        $cmd   = $parts[0] ?? '';
        $id    = isset($parts[1]) ? (int) $parts[1] : 0;

        switch ($cmd) {
            case 'assumir':
                if (!$id) return "❌ Informe o ID da conversa. Ex: *assumir 42*";
                self::setHumanMode($id, true, null);
                return "✅ Conversa #{$id} assumida. IA pausada. Você pode responder diretamente no Inbox.";

            case 'devolver':
                if (!$id) return "❌ Informe o ID da conversa. Ex: *devolver 42*";
                self::setHumanMode($id, false, null);
                return "✅ Conversa #{$id} devolvida à IA. IA retoma no próximo ciclo.";

            case 'status':
                $stats = self::getDailyStats();
                return "📊 *Status SDR — hoje*\n" .
                       "Disparados: {$stats['sent_today']}\n" .
                       "Em conversa: {$stats['active_convs']}\n" .
                       "Humano ativo: {$stats['human_mode']}\n" .
                       "Agendados hoje: {$stats['scheduled_today']}\n" .
                       "Na fila: {$stats['queued']}";

            case 'pausar':
                self::setPauseFlag(true);
                return "⏸️ Disparo pausado. Nenhuma mensagem será enviada até *retomar*.";

            case 'retomar':
                self::setPauseFlag(false);
                return "▶️ Disparo retomado.";

            default:
                return "🤖 *Comandos SDR disponíveis:*\n" .
                       "• *assumir ID* — pausa IA e você assume\n" .
                       "• *devolver ID* — IA retoma a conversa\n" .
                       "• *status* — resumo do dia\n" .
                       "• *pausar* — para todos os disparos\n" .
                       "• *retomar* — resume disparos";
        }
    }

    // =========================================================================
    // ESTATÍSTICAS
    // =========================================================================

    public static function getDailyStats(): array
    {
        $db   = DB::getConnection();
        $today = date('Y-m-d');

        $sentToday = $db->query("SELECT COUNT(*) FROM sdr_dispatch_queue WHERE status='sent' AND DATE(sent_at)='{$today}'")->fetchColumn();
        $queued    = $db->query("SELECT COUNT(*) FROM sdr_dispatch_queue WHERE status='queued'")->fetchColumn();
        $active    = $db->query("SELECT COUNT(*) FROM sdr_conversations WHERE stage NOT IN ('closed_win','closed_lost','opted_out') AND human_mode=0")->fetchColumn();
        $humanMode = $db->query("SELECT COUNT(*) FROM sdr_conversations WHERE human_mode=1")->fetchColumn();
        $scheduled = $db->query("SELECT COUNT(*) FROM sdr_conversations WHERE stage='closed_win' AND DATE(updated_at)='{$today}'")->fetchColumn();

        return [
            'sent_today'     => (int) $sentToday,
            'queued'         => (int) $queued,
            'active_convs'   => (int) $active,
            'human_mode'     => (int) $humanMode,
            'scheduled_today'=> (int) $scheduled,
        ];
    }

    // =========================================================================
    // HELPERS INTERNOS
    // =========================================================================

    /**
     * Envia notificação de texto para o operador via sessão SDR.
     */
    public static function notifyOwner(string $text): void
    {
        try {
            $provider = self::getSdrProvider();
            $provider->sendText(self::OWNER_PHONE, $text);
        } catch (\Throwable $e) {
            error_log("[SDR_NOTIFY] Falha: " . $e->getMessage());
        }
    }

    /**
     * Registra evento de saída em communication_events para aparecer no Inbox.
     */
    private static function registerOutboundEvent(string $toPhone, string $text, ?string $msgId): void
    {
        try {
            $db = DB::getConnection();
            $payload = json_encode([
                'to'        => $toPhone,
                'body'      => $text,
                'direction' => 'outbound',
                'source'    => 'sdr_auto',
                'message_id'=> $msgId,
            ], JSON_UNESCAPED_UNICODE);

            $db->prepare("
                INSERT INTO communication_events
                    (event_type, source_system, payload, created_at)
                VALUES ('message', 'sdr_auto', ?, NOW())
            ")->execute([$payload]);
        } catch (\Throwable $e) {
            error_log("[SDR] Falha ao registrar evento outbound: " . $e->getMessage());
        }
    }

    /**
     * Retorna o provider Whapi para o canal SDR.
     * Session resolvível via:
     *   1. Env SDR_WHAPI_SESSION (ex: 'orsegups')
     *   2. integration_settings.sdr_whapi_session
     *   3. Fallback: primeiro canal ativo (padrão)
     */
    public static function getSdrProvider(): WhapiCloudProvider
    {
        // 1. Tenta env
        $session = Env::get('SDR_WHAPI_SESSION', '');

        // 2. Tenta integration_settings
        if (empty($session)) {
            try {
                $db  = DB::getConnection();
                $row = $db->query("SELECT integration_value FROM integration_settings WHERE integration_key='sdr_whapi_session' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $session = $row['integration_value'] ?? '';
            } catch (\Throwable $e) {
                $session = '';
            }
        }

        // 3. Se tiver session_name configurável, usa por sessão
        if (!empty($session)) {
            return WhatsAppProviderFactory::getWhapiProviderBySession($session);
        }

        // 4. Fallback: primeiro canal ativo (comportamento anterior)
        return WhatsAppProviderFactory::getWhapiProviderBySession('pixel12digital')
            ?: new WhapiCloudProvider([]);
    }

    private static function getOpenAiKey(): string
    {
        $raw = Env::get('OPENAI_API_KEY', '');
        if (empty($raw)) return '';
        if (strpos($raw, 'sk-') === 0) return $raw;
        try {
            return CryptoHelper::decrypt($raw);
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    private static function resolveNextWeekday(string $dayName): string
    {
        $map = ['segunda'=>'monday','terça'=>'tuesday','quarta'=>'wednesday','quinta'=>'thursday','sexta'=>'friday','sábado'=>'saturday'];
        $en  = $map[mb_strtolower($dayName)] ?? 'monday';
        return date('Y-m-d', strtotime("next {$en}"));
    }

    private static function setPauseFlag(bool $paused): void
    {
        $db = DB::getConnection();
        $val = $paused ? '1' : '0';
        $db->prepare("INSERT INTO integration_settings (integration_key, integration_value, updated_at) VALUES ('sdr_paused', ?, NOW()) ON DUPLICATE KEY UPDATE integration_value=?, updated_at=NOW()")
           ->execute([$val, $val]);
    }

    public static function isPaused(): bool
    {
        $db  = DB::getConnection();
        $val = $db->query("SELECT integration_value FROM integration_settings WHERE integration_key='sdr_paused' LIMIT 1")->fetchColumn();
        return $val === '1';
    }

    // =========================================================================
    // SISTEMA PROMPT DO SDR
    // =========================================================================

    /**
     * Retorna o system prompt de Charles.
     * Delega diretamente para SalesTrainingController::buildSystemPrompt()
     * — fonte única de verdade para o prompt.
     */
    public static function buildSdrSystemPrompt(): string
    {
        return SalesTrainingController::buildSystemPrompt();
    }
}
