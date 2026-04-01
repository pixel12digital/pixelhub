<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
use PixelHub\Core\PhoneNormalizer;
use PixelHub\Integrations\WhatsApp\WhapiCloudProvider;
use PixelHub\Services\WhatsAppProviderFactory;
use PixelHub\Services\EventIngestionService;
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

        $windowStart = strtotime("{$date} " . self::DISPATCH_WINDOW_START . ':00');
        $windowEnd   = strtotime("{$date} " . self::DISPATCH_WINDOW_END   . ':00');

        // Se faltam menos de 30 min para o fim da janela hoje, usa próximo dia útil
        if ($date === date('Y-m-d') && time() >= ($windowEnd - 1800)) {
            $next = date('Y-m-d', strtotime('tomorrow'));
            // Pula final de semana
            $dow = (int) date('N', strtotime($next));
            if ($dow === 6) $next = date('Y-m-d', strtotime($next . ' +2 days'));
            if ($dow === 7) $next = date('Y-m-d', strtotime($next . ' +1 day'));
            return self::calculateHumanTimes($count, $next);
        }

        $lunchStart  = strtotime("{$date} 12:00:00");
        $lunchEnd    = strtotime("{$date} 13:20:00");

        // Ponto de início: max(agora + 1-5 min buffer, início da janela)
        $start = max(time() + rand(60, 300), $windowStart);

        // Se o início cair no almoço, mover para depois do almoço
        if ($start >= $lunchStart && $start < $lunchEnd) {
            $start = $lunchEnd + rand(60, 300);
        }

        // Calcular tempo disponível líquido (excluindo almoço, se ainda está por vir)
        $netAvail = $windowEnd - $start;
        if ($lunchStart < $windowEnd && $lunchEnd > $start) {
            $overlap = min($lunchEnd, $windowEnd) - max($lunchStart, $start);
            $netAvail -= max(0, $overlap);
        }
        $netAvail = max($netAvail, $count * 180); // garante ao menos 3 min/contato

        // Intervalo base adaptativo: tempo disponível dividido pelo número de contatos
        // Clamped entre 3 min (mínimo humano) e 45 min (máximo realista)
        $baseInterval = (int) ($netAvail / $count);
        $baseInterval = max(180, min(2700, $baseInterval));

        $times = [];
        $cursor = $start + rand(30, 90);

        for ($i = 0; $i < $count; $i++) {
            // Pula almoço
            if ($cursor >= $lunchStart && $cursor < $lunchEnd) {
                $cursor = $lunchEnd + rand(60, 300);
            }

            $dt = new \DateTime();
            $dt->setTimestamp($cursor);
            $times[] = clone $dt;

            // Variação humana bimodal:
            // 70% do tempo: intervalo "normal" (±30% do base)
            // 30% do tempo: intervalo "distração" (1.5×-2.5× o base — pausa para responder outro lead)
            $roll = rand(1, 100);
            if ($roll <= 70) {
                $variance = (int) ($baseInterval * 0.30);
                $interval = $baseInterval + rand(-$variance, $variance);
            } else {
                $interval = (int) ($baseInterval * (1.5 + lcg_value())); // 1.5x–2.5x
            }

            // Segundos aleatórios (evita horários redondos como 14:00:00)
            $interval += rand(-45, 45);
            $interval  = max(180, $interval); // nunca menos de 3 min

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
    public static function planDay(int $recipeId, int $maxPerDay = self::MAX_PER_DAY, string $sessionName = ''): array
    {
        $db = DB::getConnection();

        // Busca resultados com telefone, não descartados e sem envio prévio
        $stmt = $db->prepare("
            SELECT pr.id, pr.name, pr.source,
                   CASE
                       WHEN pr.source = 'instagram' THEN pr.phone_instagram
                       WHEN pr.source = 'google_maps' THEN pr.phone_google
                       ELSE pr.phone_minhareceita
                   END AS phone
            FROM prospecting_results pr
            WHERE pr.recipe_id = ?
              AND pr.status != 'discarded'
              AND pr.whatsapp_sent_at IS NULL
              AND ((pr.source = 'instagram' AND pr.phone_instagram IS NOT NULL AND pr.phone_instagram != '')
                OR (pr.source = 'google_maps' AND pr.phone_google IS NOT NULL AND pr.phone_google != '')
                OR (pr.source IN ('minha_receita','minhareceita','cnpj_ws','cnpjws') AND pr.phone_minhareceita IS NOT NULL AND pr.phone_minhareceita != ''))
              AND NOT EXISTS (
                  SELECT 1 FROM sdr_dispatch_queue dq WHERE dq.result_id = pr.id
              )
            ORDER BY pr.found_at ASC
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
                (result_id, recipe_id, session_name, phone, establishment_name, message, scheduled_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");
        $insertConv = $db->prepare("
            INSERT INTO sdr_conversations
                (result_id, phone, establishment_name, stage, human_mode, created_at, updated_at)
            VALUES (?, ?, ?, 'opening', 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");
        $checkPhone = $db->prepare("
            SELECT 1 FROM sdr_dispatch_queue WHERE phone = ? AND status IN ('queued','processing') LIMIT 1
        ");

        foreach ($candidates as $i => $lead) {
            $phone = PhoneNormalizer::toE164OrNull($lead['phone']);
            if (!$phone) {
                $stats['skipped_no_phone']++;
                continue;
            }

            // Dedup por telefone: evita dois opens ATIVOS para o mesmo número
            $checkPhone->execute([$phone]);
            if ($checkPhone->fetch()) {
                $stats['skipped_duplicate']++;
                continue;
            }

            $name    = $lead['name'] ?? 'estabelecimento';
            $message = self::buildOpeningMessage($name);
            $schAt   = isset($times[$i]) ? $times[$i]->format('Y-m-d H:i:s') : date('Y-m-d 09:00:00');

            $insertQueue->execute([$lead['id'], $recipeId, $sessionName, $phone, $name, $message, $schAt]);
            $insertConv->execute([$lead['id'], $phone, $name]);
            $stats['enqueued']++;
        }

        return $stats;
    }

    /**
     * Enfileira apenas os result_ids selecionados pelo usuário.
     *
     * @param  int[] $resultIds IDs de prospecting_results a enfileirar
     * @return array ['enqueued' => int, 'skipped_duplicate' => int, 'skipped_no_phone' => int]
     */
    public static function planSelection(array $resultIds, string $sessionName = ''): array
    {
        $stats = ['enqueued' => 0, 'skipped_duplicate' => 0, 'skipped_no_phone' => 0];
        if (empty($resultIds)) {
            return $stats;
        }

        $db           = DB::getConnection();
        $placeholders = implode(',', array_fill(0, count($resultIds), '?'));

        $stmt = $db->prepare("
            SELECT pr.id, pr.name, pr.source, pr.recipe_id,
                   CASE
                       WHEN pr.source = 'instagram' THEN pr.phone_instagram
                       WHEN pr.source = 'google_maps' THEN pr.phone_google
                       ELSE pr.phone_minhareceita
                   END AS phone
            FROM prospecting_results pr
            WHERE pr.id IN ($placeholders)
              AND pr.status != 'discarded'
              AND pr.whatsapp_sent_at IS NULL
              AND ((pr.source = 'instagram' AND pr.phone_instagram IS NOT NULL AND pr.phone_instagram != '')
                OR (pr.source = 'google_maps' AND pr.phone_google IS NOT NULL AND pr.phone_google != '')
                OR (pr.source IN ('minha_receita','minhareceita','cnpj_ws','cnpjws') AND pr.phone_minhareceita IS NOT NULL AND pr.phone_minhareceita != ''))
              AND NOT EXISTS (
                  SELECT 1 FROM sdr_dispatch_queue dq WHERE dq.result_id = pr.id
              )
            ORDER BY pr.id ASC
        ");
        $stmt->execute($resultIds);
        $candidates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($candidates)) {
            return $stats;
        }

        $times = self::calculateHumanTimes(count($candidates));
        $insertQueue = $db->prepare("
            INSERT INTO sdr_dispatch_queue
                (result_id, recipe_id, session_name, phone, establishment_name, message, scheduled_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");
        $insertConv = $db->prepare("
            INSERT INTO sdr_conversations
                (result_id, phone, establishment_name, stage, human_mode, created_at, updated_at)
            VALUES (?, ?, ?, 'opening', 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");
        $checkPhone = $db->prepare("
            SELECT 1 FROM sdr_dispatch_queue WHERE phone = ? AND status IN ('queued','processing') LIMIT 1
        ");

        foreach ($candidates as $i => $lead) {
            $phone = PhoneNormalizer::toE164OrNull($lead['phone']);
            if (!$phone) {
                $stats['skipped_no_phone']++;
                continue;
            }

            // Dedup por telefone: evita dois opens ATIVOS para o mesmo número
            $checkPhone->execute([$phone]);
            if ($checkPhone->fetch()) {
                $stats['skipped_duplicate']++;
                continue;
            }

            $name    = $lead['name'] ?? 'estabelecimento';
            $message = self::buildOpeningMessage($name);
            $schAt   = isset($times[$i]) ? $times[$i]->format('Y-m-d H:i:s') : date('Y-m-d 09:00:00');

            $insertQueue->execute([$lead['id'], $lead['recipe_id'], $sessionName, $phone, $name, $message, $schAt]);
            $insertConv->execute([$lead['id'], $phone, $name]);
            $stats['enqueued']++;
        }

        return $stats;
    }

    /**
     * Encurta nome do estabelecimento para a saudação (primeiras 2 palavras ou até separador).
     * Ex: "Ana Agnol Acessórios De Moda" → "Ana Agnol"
     *     "M.M. Jóias - Alianças De Moedas Antigas" → "M.M. Jóias"
     */
    public static function shortenBusinessName(string $name): string
    {
        $name = mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');

        foreach ([' - ', ' – ', ' | ', ', ', ': '] as $sep) {
            $pos = mb_strpos($name, $sep);
            if ($pos !== false && $pos > 3) {
                $name = mb_substr($name, 0, $pos);
                break;
            }
        }

        $words = explode(' ', trim($name));
        if (count($words) > 3) {
            $name = implode(' ', array_slice($words, 0, 2));
        }

        return trim($name);
    }

    /**
     * Gera a mensagem de abertura para um estabelecimento.
     */
    public static function buildOpeningMessage(string $name): string
    {
        $shortName = self::shortenBusinessName($name);
        return "Oi, é da {$shortName}? Qual o horário de funcionamento, por gentileza?";
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
        // 1. Validar número de telefone antes de enviar
        $validation = self::validatePhoneNumber($job['phone'], $job['session_name'] ?? '');
        
        // Atualizar status da validação no job
        $db = DB::getConnection();
        $db->prepare("
            UPDATE sdr_dispatch_queue 
            SET phone_validated = ?, phone_validation_status = ?, phone_validated_at = NOW()
            WHERE id = ?
        ")->execute([$validation['valid'] ? 1 : 0, $validation['status'], $job['id']]);
        
        // Se número inválido, marcar como failed e não enviar
        if (!$validation['valid']) {
            $errorMsg = 'Número sem WhatsApp: ' . $validation['status'];
            self::markFailed($job['id'], $errorMsg);
            
            return [
                'success' => false,
                'error' => $errorMsg,
                'validation' => $validation
            ];
        }
        
        // 2. Prosseguir com envio normal (usa telefone normalizado pela validação, ex: 9º dígito BR)
        $sendPhone  = $validation['phone_normalized'] ?? $validation['phone'] ?? $job['phone'];
        if ($sendPhone !== $job['phone']) {
            // Atualiza phone no job com o número normalizado
            $db->prepare("UPDATE sdr_dispatch_queue SET phone=? WHERE id=?")->execute([$sendPhone, $job['id']]);
        }
        $jobSession = $job['session_name'] ?? '';
        $provider   = !empty($jobSession)
            ? WhatsAppProviderFactory::getWhapiProviderBySession($jobSession)
            : self::getSdrProvider();
        $result   = $provider->sendText($sendPhone, $job['message']);

        if (!($result['success'] ?? false)) {
            return $result;
        }

        // Marca whatsapp_sent_at no prospecting_results
        $db->prepare("UPDATE prospecting_results SET whatsapp_sent_at=NOW() WHERE id=?")
           ->execute([$job['result_id']]);

        // Registra em communication_events para aparecer no Inbox
        $sessionName   = $job['session_name'] ?? 'orsegups';
        $establishName = $job['establishment_name'] ?? '';
        self::registerOutboundEvent($sendPhone, $job['message'], $result['message_id'] ?? null, $sessionName, $establishName);

        return $result;
    }

    /**
     * Valida um número de telefone via API Whapi.Cloud
     */
    public static function validatePhoneNumber(string $phone, string $sessionName): array
    {
        // Pegar token da sessão
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT whapi_api_token 
            FROM whatsapp_provider_configs 
            WHERE provider_type = 'whapi' AND session_name = ? AND is_active = 1
        ");
        $stmt->execute([$sessionName]);
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$config || !$config['whapi_api_token']) {
            return [
                'valid' => false,
                'status' => 'error',
                'error' => 'Configuração não encontrada para sessão: ' . $sessionName
            ];
        }
        
        // Descriptografar token
        $apiToken = $config['whapi_api_token'];
        if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
            $token = CryptoHelper::decrypt(substr($apiToken, 10));
        } else {
            $token = $apiToken;
        }
        
        $result = self::callWhapiContacts($phone, $token);
        
        // Falso-negativo para BR 8 dígitos (formato pré-2012):
        // Se inválido e número tem 12 dígitos (55+DDD+8 dígitos), tenta com 9º dígito.
        if (!$result['valid'] && $result['status'] !== 'error') {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($digits) === 12 && substr($digits, 0, 2) === '55') {
                // Insere '9' após DDD: 55(2)+DDD(2)+9+subscriber(8) = 13 dígitos
                $phoneWith9 = substr($digits, 0, 4) . '9' . substr($digits, 4);
                $result9 = self::callWhapiContacts($phoneWith9, $token);
                if ($result9['valid']) {
                    return array_merge($result9, ['phone' => $phoneWith9, 'phone_normalized' => $phoneWith9]);
                }
            }
        }
        
        return $result;
    }

    public static function callWhapiContacts(string $phone, string $token): array
    {
        $url  = 'https://gate.whapi.cloud/contacts';
        $data = ['contacts' => [$phone]];
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return ['valid' => false, 'status' => 'error', 'error' => 'Erro na requisição: ' . $curlError];
        }
        if ($httpCode !== 200) {
            return ['valid' => false, 'status' => 'error', 'error' => 'HTTP ' . $httpCode . ': ' . substr($response, 0, 100)];
        }
        
        $responseData = json_decode($response, true);
        if (!isset($responseData['contacts'][0])) {
            return ['valid' => false, 'status' => 'error', 'error' => 'Resposta inválida da API'];
        }
        
        $contact = $responseData['contacts'][0];
        $status  = $contact['status'] ?? 'invalid';
        return [
            'valid'    => $status === 'valid',
            'status'   => $status,
            'phone'    => $contact['input'] ?? $phone,
            'response' => $contact
        ];
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

        // 2. Quando contato responde pela primeira vez → marcar como 'qualified'
        // (qualificação real = contato demonstrou interesse ao responder)
        $hasInbound = !empty(array_filter($history, fn($m) => $m['direction'] === 'inbound'));
        if ($hasInbound && !empty($conv['result_id'])) {
            $db->prepare("
                UPDATE prospecting_results
                SET status = 'qualified', updated_at = NOW()
                WHERE id = ? AND status = 'new'
            ")->execute([$conv['result_id']]);
        }

        // 3. Monta ai_history com mensagens novas
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
        // Usa a sessão da conversa; fallback para getSdrProvider()
        $convSession = $conv['session_name'] ?? '';
        $provider = !empty($convSession)
            ? WhatsAppProviderFactory::getWhapiProviderBySession($convSession)
            : self::getSdrProvider();
        $result   = $provider->sendText($conv['phone'], $text);

        if (!($result['success'] ?? false)) {
            error_log("[SDR_AI] Falha ao enviar resposta conv #{$conv['id']}: " . ($result['error'] ?? 'unknown'));
            return;
        }

        $sessionName   = Env::get('SDR_WHAPI_SESSION', 'orsegups');
        $establishName = $conv['establishment_name'] ?? '';
        self::registerOutboundEvent($conv['phone'], $text, $result['message_id'] ?? null, $sessionName, $establishName);

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
     * Usa EventIngestionService::ingest() para que a mensagem seja vinculada
     * à conversa correta (conversation_id) e apareça no thread do Inbox.
     */
    private static function registerOutboundEvent(string $toPhone, string $text, ?string $msgId, string $sessionName = 'orsegups', string $contactName = ''): void
    {
        try {
            $digits = preg_replace('/[^0-9]/', '', $toPhone);
            $chatId = $digits . '@s.whatsapp.net';

            $normalizedPayload = [
                'id'         => $msgId,
                'message_id' => $msgId,
                'from'       => null,
                'to'         => $chatId,
                'timestamp'  => time(),
                'type'       => 'chat',
                'text'       => $text,
                'body'       => $text,
                'fromMe'     => true,
                'direction'  => 'outbound',
                'message'    => [
                    'id'      => $msgId,
                    'from'    => null,
                    'to'      => $chatId,
                    'type'    => 'chat',
                    'body'    => $text,
                    'text'    => $text,
                    'fromMe'  => true,
                    'key'     => ['id' => $msgId, 'remoteJid' => $chatId, 'fromMe' => true],
                ],
                'whapi_channel_id' => $sessionName,
            ];

            EventIngestionService::ingest([
                'event_type'         => 'whatsapp.outbound.message',
                'source_system'      => 'whapi_cloud',
                'payload'            => $normalizedPayload,
                'tenant_id'          => null,
                'process_media_sync' => false,
                'metadata'           => [
                    'channel_id'    => $sessionName,
                    'provider_type' => 'whapi',
                    'message_id'    => $msgId,
                    'via'           => 'sdr_dispatch',
                ],
            ]);

            // Atualiza contact_name na conversa criada pelo ingest
            if (!empty($contactName)) {
                try {
                    $db = DB::getConnection();
                    $db->prepare("
                        UPDATE conversations
                        SET contact_name = ?
                        WHERE contact_external_id IN (?, ?)
                          AND channel_id = ?
                          AND (contact_name IS NULL OR contact_name = '' OR contact_name = 'Contato Desconhecido')
                    ")->execute([$contactName, $chatId, $digits, $sessionName]);
                } catch (\Throwable $ex) {
                    error_log("[SDR] Falha ao atualizar contact_name: " . $ex->getMessage());
                }
            }
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

        // 4. Fallback: sessão padrão orsegups
        return WhatsAppProviderFactory::getWhapiProviderBySession('orsegups')
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
