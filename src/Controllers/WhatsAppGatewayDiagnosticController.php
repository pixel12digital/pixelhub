<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\EventIngestionService;
use PDO;

/**
 * Controller para página de diagnóstico do WhatsApp Gateway
 */
class WhatsAppGatewayDiagnosticController extends Controller
{
    /**
     * Página principal de diagnóstico
     * 
     * GET /settings/whatsapp-gateway/diagnostic
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Estado atual do gateway
        $webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL', '');
        if (empty($webhookUrl)) {
            $webhookUrl = pixelhub_url('/api/whatsapp/webhook');
        }
        
        $serverTime = date('Y-m-d H:i:s');
        $timezone = date_default_timezone_get();

        // Busca canais configurados
        $channelsStmt = $db->query("
            SELECT tmc.*, t.name as tenant_name
            FROM tenant_message_channels tmc
            LEFT JOIN tenants t ON tmc.tenant_id = t.id
            WHERE tmc.provider = 'wpp_gateway'
            ORDER BY tmc.created_at DESC
            LIMIT 10
        ");
        $channels = $channelsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('settings.whatsapp_gateway_diagnostic', [
            'webhookUrl' => $webhookUrl,
            'serverTime' => $serverTime,
            'timezone' => $timezone,
            'channels' => $channels,
        ]);
    }

    /**
     * Lista últimas mensagens (endpoint AJAX)
     * 
     * GET /settings/whatsapp-gateway/diagnostic/messages
     */
    public function getMessages(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $phone = $_GET['phone'] ?? null;
        $threadId = $_GET['thread_id'] ?? null;
        $interval = $_GET['interval'] ?? '15min'; // 15min, 1h, 24h

        $db = DB::getConnection();

        // Calcula intervalo
        $intervalMap = [
            '15min' => 'INTERVAL 15 MINUTE',
            '1h' => 'INTERVAL 1 HOUR',
            '24h' => 'INTERVAL 24 HOUR'
        ];
        $intervalSql = $intervalMap[$interval] ?? $intervalMap['15min'];

        $where = [
            "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')",
            "ce.created_at >= DATE_SUB(NOW(), {$intervalSql})"
        ];
        $params = [];

        // Filtro por telefone
        if ($phone) {
            $where[] = "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )";
            $pattern = "%{$phone}%";
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        $stmt = $db->prepare("
            SELECT 
                ce.id as message_id,
                ce.event_id,
                ce.created_at,
                ce.event_type,
                ce.tenant_id,
                ce.source_system,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to,
                JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
            FROM communication_events ce
            {$whereClause}
            ORDER BY ce.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Resolve thread_id para cada mensagem
        foreach ($messages as &$msg) {
            $from = $msg['from_contact'] ?: $msg['msg_from'] ?: null;
            $to = $msg['to_contact'] ?: $msg['msg_to'] ?: null;
            $contact = $from ?: $to;
            
            $msg['direction'] = $msg['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            $msg['thread_id'] = null;

            if ($contact && $msg['tenant_id']) {
                $normalizeContact = function($c) {
                    if (empty($c)) return null;
                    $cleaned = preg_replace('/@.*$/', '', (string) $c);
                    return preg_replace('/[^0-9]/', '', $cleaned);
                };
                $normalized = $normalizeContact($contact);
                
                if ($normalized) {
                    $convStmt = $db->prepare("
                        SELECT id
                        FROM conversations
                        WHERE tenant_id = ?
                          AND contact_external_id LIKE ?
                        LIMIT 1
                    ");
                    $pattern = "%{$normalized}%";
                    $convStmt->execute([$msg['tenant_id'], $pattern]);
                    $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($conv) {
                        $msg['thread_id'] = 'whatsapp_' . $conv['id'];
                    }
                }
            }
        }
        unset($msg);

        // Filtro por thread_id (aplicado após resolver threads)
        if ($threadId) {
            $messages = array_filter($messages, function($msg) use ($threadId) {
                return $msg['thread_id'] === $threadId;
            });
            $messages = array_values($messages); // Reindexa
        }

        $this->json([
            'success' => true,
            'messages' => $messages,
            'count' => count($messages)
        ]);
    }

    /**
     * Verifica logs do servidor para webhooks do ServPro
     * 
     * GET /settings/whatsapp-gateway/diagnostic/check-servpro-logs
     */
    public function checkServproLogs(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $phone = $_GET['phone'] ?? '554796474223';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

        // Busca possíveis locais de log
        $logDir = __DIR__ . '/../../logs';
        $logFile = realpath($logDir) . '/pixelhub.log';
        if ($logFile === false) {
            $logFile = $logDir . '/pixelhub.log';
        }

        $phpErrorLog = ini_get('error_log');
        $possibleLogs = [
            $logFile,
            $phpErrorLog ?: null,
            'C:/xampp/php/logs/php_error_log',
            'C:/xampp/apache/logs/error.log'
        ];

        $logs = [];
        $foundLogs = [];

        foreach ($possibleLogs as $logPath) {
            if (empty($logPath) || !file_exists($logPath)) {
                continue;
            }

            try {
                // Lê últimas linhas do arquivo
                $lines = file($logPath);
                if ($lines === false) {
                    continue;
                }

                // Pega últimas N linhas
                $recentLines = array_slice($lines, -$limit);
                
                // Filtra linhas relacionadas ao webhook e ao número
                foreach ($recentLines as $lineNum => $line) {
                    if (stripos($line, 'WHATSAPP INBOUND RAW') !== false || 
                        stripos($line, 'WEBHOOK INSTRUMENTADO') !== false ||
                        stripos($line, $phone) !== false ||
                        stripos($line, '4223') !== false) {
                        $foundLogs[] = [
                            'file' => basename($logPath),
                            'line' => count($lines) - count($recentLines) + $lineNum + 1,
                            'content' => trim($line),
                            'timestamp' => $this->extractTimestamp($line)
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Ignora erros de leitura
            }
        }

        // Ordena por timestamp (mais recente primeiro)
        usort($foundLogs, function($a, $b) {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });

        $this->json([
            'success' => true,
            'phone' => $phone,
            'logs_found' => count($foundLogs),
            'logs' => array_slice($foundLogs, 0, 50), // Limita a 50
            'log_files_checked' => array_filter($possibleLogs, function($path) {
                return !empty($path) && file_exists($path);
            })
        ]);
    }

    /**
     * Extrai timestamp de uma linha de log
     */
    private function extractTimestamp(string $line): ?string
    {
        // Tenta extrair timestamp no formato [YYYY-MM-DD HH:MM:SS]
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Lista logs instrumentados (endpoint AJAX)
     * 
     * GET /settings/whatsapp-gateway/diagnostic/logs
     */
    public function getLogs(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $threadId = $_GET['thread_id'] ?? null;
        $phone = $_GET['phone'] ?? null;
        $eventId = $_GET['event_id'] ?? null;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;

        // Por enquanto, busca do error_log via tabela de debug (se existir)
        // Ou retorna mensagem indicando que precisa ler do arquivo
        $db = DB::getConnection();

        // Verifica se existe tabela de debug logs
        $hasDebugTable = false;
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'whatsapp_debug_logs'");
            $hasDebugTable = $checkStmt->rowCount() > 0;
        } catch (\Exception $e) {
            // Tabela não existe
        }

        if ($hasDebugTable) {
            $where = [];
            $params = [];

            if ($threadId) {
                // Extrai conversation_id do thread_id
                if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                    $conversationId = (int) $matches[1];
                    $where[] = "conversation_id = ?";
                    $params[] = $conversationId;
                }
            }

            if ($phone) {
                $where[] = "log_message LIKE ?";
                $params[] = "%{$phone}%";
            }

            if ($eventId) {
                $where[] = "log_message LIKE ?";
                $params[] = "%{$eventId}%";
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $stmt = $db->prepare("
                SELECT *
                FROM whatsapp_debug_logs
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $params[] = $limit;
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->json([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs)
            ]);
        } else {
            // Retorna mensagem indicando que precisa ler do arquivo
            $this->json([
                'success' => false,
                'message' => 'Tabela de debug logs não existe. Logs devem ser lidos do arquivo error.log do servidor.',
                'logs' => []
            ]);
        }
    }

    /**
     * Simula webhook (POST)
     * 
     * POST /settings/whatsapp-gateway/diagnostic/simulate-webhook
     */
    public function simulateWebhook(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $template = $_POST['template'] ?? 'inbound';
        $from = trim($_POST['from'] ?? '');
        $to = trim($_POST['to'] ?? '');
        $body = trim($_POST['body'] ?? 'Mensagem de teste');
        $eventId = trim($_POST['event_id'] ?? '');
        $channelId = trim($_POST['channel_id'] ?? 'Pixel12 Digital');
        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

        if (empty($from)) {
            $this->json(['success' => false, 'error' => 'Campo "from" é obrigatório'], 400);
            return;
        }

        // Monta payload baseado no template
        $payload = $this->buildWebhookPayload($template, $from, $to, $body, $eventId, $channelId);

        // Chama o endpoint real do webhook internamente
        // Constrói URL absoluta a partir do host atual
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $webhookPath = pixelhub_url('/api/whatsapp/webhook');
        $webhookUrl = $protocol . '://' . $host . $webhookPath;
        
        // Simula requisição POST
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Secret: ' . Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET', '')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desenvolvimento
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Para desenvolvimento
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $result = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_status' => $httpCode,
            'response' => json_decode($response, true) ?: $response,
            'curl_error' => $curlError ?: null,
            'payload_sent' => $payload,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Registra execução (se tabela existir)
        try {
            $db = DB::getConnection();
            $checkStmt = $db->query("SHOW TABLES LIKE 'whatsapp_debug_executions'");
            if ($checkStmt->rowCount() > 0) {
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_debug_executions 
                    (template, from_contact, to_contact, body, http_status, response, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $template,
                    $from,
                    $to,
                    $body,
                    $httpCode,
                    json_encode($result)
                ]);
            }
        } catch (\Exception $e) {
            // Ignora erro de tabela não existir
        }

        $this->json($result);
    }

    /**
     * Checklist de teste - captura estado atual
     * 
     * POST /settings/whatsapp-gateway/diagnostic/checklist-capture
     */
    public function checklistCapture(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $phone = trim($_POST['phone'] ?? '');
        $threadId = trim($_POST['thread_id'] ?? '');

        if (empty($phone) && empty($threadId)) {
            $this->json(['success' => false, 'error' => 'phone ou thread_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();
        $timestamp = date('Y-m-d H:i:s');
        $report = [
            'timestamp' => $timestamp,
            'phone' => $phone,
            'thread_id' => $threadId,
            'checks' => []
        ];

        // 1. Verifica se webhook chegou (última mensagem nos últimos 30s)
        $stmt = $db->prepare("
            SELECT id, event_id, created_at, source_system
            FROM communication_events
            WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
              AND (
                  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?
              )
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $pattern = "%{$phone}%";
        $stmt->execute([$pattern, $pattern]);
        $webhookMessage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $report['checks']['webhook_received'] = [
            'status' => $webhookMessage ? 'OK' : 'FAIL',
            'message_id' => $webhookMessage['id'] ?? null,
            'event_id' => $webhookMessage['event_id'] ?? null,
            'created_at' => $webhookMessage['created_at'] ?? null
        ];

        // 2. Verifica se inseriu no banco
        $report['checks']['inserted'] = [
            'status' => $webhookMessage ? 'OK' : 'FAIL',
            'id' => $webhookMessage['id'] ?? null
        ];

        // 3. Verifica thread_id
        if ($webhookMessage && $threadId) {
            // Resolve thread_id da mensagem
            $normalizeContact = function($c) {
                if (empty($c)) return null;
                $cleaned = preg_replace('/@.*$/', '', (string) $c);
                return preg_replace('/[^0-9]/', '', $cleaned);
            };
            
            $stmt2 = $db->prepare("
                SELECT tenant_id, JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_contact
                FROM communication_events
                WHERE id = ?
            ");
            $stmt2->execute([$webhookMessage['id']]);
            $msgData = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($msgData && $msgData['tenant_id']) {
                $normalized = $normalizeContact($msgData['from_contact']);
                if ($normalized) {
                    $convStmt = $db->prepare("
                        SELECT id
                        FROM conversations
                        WHERE tenant_id = ?
                          AND contact_external_id LIKE ?
                        LIMIT 1
                    ");
                    $pattern2 = "%{$normalized}%";
                    $convStmt->execute([$msgData['tenant_id'], $pattern2]);
                    $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $resolvedThreadId = $conv ? 'whatsapp_' . $conv['id'] : null;
                    $report['checks']['thread_id'] = [
                        'status' => $resolvedThreadId === $threadId ? 'OK' : 'FAIL',
                        'expected' => $threadId,
                        'resolved' => $resolvedThreadId
                    ];
                }
            }
        }

        // 4. Verifica se conversation foi atualizada (last_message_at, unread_count)
        if ($webhookMessage && $threadId) {
            if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                $conversationId = (int) $matches[1];
                
                $convStmt = $db->prepare("
                    SELECT id, last_message_at, unread_count, message_count, updated_at
                    FROM conversations
                    WHERE id = ?
                ");
                $convStmt->execute([$conversationId]);
                $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    $msgCreatedAt = $webhookMessage['created_at'];
                    $convLastMessageAt = $conv['last_message_at'];
                    
                    // Verifica se last_message_at foi atualizado
                    $lastMessageUpdated = $convLastMessageAt && 
                        strtotime($convLastMessageAt) >= strtotime($msgCreatedAt) - 5; // Tolerância de 5s
                    
                    // Verifica se unread_count foi incrementado (para inbound)
                    $unreadIncremented = false;
                    if ($webhookMessage['source_system'] === 'wpp_gateway') {
                        // Busca se é inbound
                        $directionStmt = $db->prepare("
                            SELECT event_type
                            FROM communication_events
                            WHERE id = ?
                        ");
                        $directionStmt->execute([$webhookMessage['id']]);
                        $directionData = $directionStmt->fetch(PDO::FETCH_ASSOC);
                        $isInbound = ($directionData['event_type'] ?? '') === 'whatsapp.inbound.message';
                        $unreadIncremented = $isInbound ? ($conv['unread_count'] > 0) : true; // Para outbound, não precisa incrementar
                    }
                    
                    $unreadCorrect = $isInbound ? ($conv['unread_count'] > 0) : true;
                    $allOk = $lastMessageUpdated && $unreadCorrect;
                    
                    $diagnosis = [];
                    if (!$lastMessageUpdated) {
                        $diagnosis[] = '❌ last_message_at NÃO foi atualizado → ConversationService::updateConversationMetadata() não foi chamado ou falhou';
                    }
                    if ($isInbound && !$unreadCorrect) {
                        $diagnosis[] = '❌ unread_count NÃO foi incrementado para mensagem inbound → Badge não aparece';
                    }
                    if (!$allOk) {
                        $diagnosis[] = '⚠️ Se last_message_at não atualiza → Conversation não sobe ao topo da lista';
                    }
                    
                    $report['checks']['conversation_updated'] = [
                        'status' => $allOk ? 'OK' : 'FAIL',
                        'last_message_at' => $convLastMessageAt,
                        'message_created_at' => $msgCreatedAt,
                        'last_message_updated' => $lastMessageUpdated ? 'SIM' : 'NÃO',
                        'unread_count' => $conv['unread_count'],
                        'unread_correct' => $unreadCorrect ? 'SIM' : 'NÃO',
                        'is_inbound' => $isInbound ? 'SIM' : 'NÃO',
                        'message_count' => $conv['message_count'],
                        'conversation_updated_at' => $conv['updated_at'],
                        'diagnosis' => !empty($diagnosis) ? implode(' | ', $diagnosis) : '✅ Conversation atualizada corretamente'
                    ];
                }
            }
        }

        // 5. Verifica /messages/check (simula endpoint real)
        if ($threadId && $webhookMessage) {
            // Usa a mesma lógica do CommunicationHubController::checkNewMessages()
            if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                $conversationId = (int) $matches[1];
                
                $convStmt = $db->prepare("
                    SELECT id as conversation_id, contact_external_id, tenant_id
                    FROM conversations
                    WHERE id = ?
                ");
                $convStmt->execute([$conversationId]);
                $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    $normalizeContact = function($c) {
                        if (empty($c)) return null;
                        $cleaned = preg_replace('/@.*$/', '', (string) $c);
                        return preg_replace('/[^0-9]/', '', $cleaned);
                    };
                    $normalizedContact = $normalizeContact($conv['contact_external_id']);
                    
                    if ($normalizedContact) {
                        // Busca mensagens após a última conhecida (simula after_timestamp)
                        $afterTimestamp = date('Y-m-d H:i:s', strtotime($webhookMessage['created_at']) - 10); // 10s antes
                        
                        $checkStmt = $db->prepare("
                            SELECT COUNT(*) as total
                            FROM communication_events ce
                            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                              AND ce.tenant_id = ?
                              AND (
                                  JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
                              )
                              AND (ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))
                        ");
                        $pattern = "%{$normalizedContact}%";
                        $checkStmt->execute([
                            $conv['tenant_id'],
                            $pattern, $pattern, $pattern, $pattern,
                            $afterTimestamp,
                            $afterTimestamp,
                            '' // after_event_id vazio
                        ]);
                        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $hasNew = ($checkResult['total'] ?? 0) > 0;
                        
                        $diagnosis = [];
                        if (!$hasNew) {
                            $diagnosis[] = '❌ /messages/check não encontra mensagem → Frontend não sabe que há mensagens novas';
                            $diagnosis[] = 'Possíveis causas: normalização de contato diferente, filtros incorretos, ou after_timestamp muito recente';
                        }
                        
                        $report['checks']['check_detects'] = [
                            'status' => $hasNew ? 'OK' : 'FAIL',
                            'has_new' => $hasNew,
                            'count' => (int) ($checkResult['total'] ?? 0),
                            'after_timestamp' => $afterTimestamp,
                            'normalized_contact' => $normalizedContact,
                            'diagnosis' => !empty($diagnosis) ? implode(' | ', $diagnosis) : '✅ Endpoint /messages/check detecta mensagem corretamente'
                        ];
                    }
                }
            }
        }

        // 6. Verifica /messages/new (simula endpoint real)
        if ($threadId && $webhookMessage) {
            if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                $conversationId = (int) $matches[1];
                
                $convStmt = $db->prepare("
                    SELECT id as conversation_id, contact_external_id, tenant_id
                    FROM conversations
                    WHERE id = ?
                ");
                $convStmt->execute([$conversationId]);
                $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    $normalizeContact = function($c) {
                        if (empty($c)) return null;
                        $cleaned = preg_replace('/@.*$/', '', (string) $c);
                        return preg_replace('/[^0-9]/', '', $cleaned);
                    };
                    $normalizedContact = $normalizeContact($conv['contact_external_id']);
                    
                    if ($normalizedContact) {
                        $afterTimestamp = date('Y-m-d H:i:s', strtotime($webhookMessage['created_at']) - 10);
                        
                        $newStmt = $db->prepare("
                            SELECT ce.id, ce.event_id, ce.created_at
                            FROM communication_events ce
                            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                              AND ce.tenant_id = ?
                              AND (
                                  JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
                              )
                              AND (ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))
                            ORDER BY ce.created_at ASC
                            LIMIT 20
                        ");
                        $pattern = "%{$normalizedContact}%";
                        $newStmt->execute([
                            $conv['tenant_id'],
                            $pattern, $pattern, $pattern, $pattern,
                            $afterTimestamp,
                            $afterTimestamp,
                            '' // after_event_id vazio
                        ]);
                        $newMessages = $newStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $diagnosis = [];
                        if (empty($newMessages)) {
                            $diagnosis[] = '❌ /messages/new não retorna mensagem → Mensagens não são carregadas no frontend';
                            $diagnosis[] = 'Possíveis causas: normalização de contato diferente, filtros incorretos, ou after_timestamp muito recente';
                        }
                        
                        $report['checks']['new_returns'] = [
                            'status' => !empty($newMessages) ? 'OK' : 'FAIL',
                            'messages_count' => count($newMessages),
                            'after_timestamp' => $afterTimestamp,
                            'diagnosis' => !empty($diagnosis) ? implode(' | ', $diagnosis) : '✅ Endpoint /messages/new retorna mensagem corretamente'
                        ];
                    }
                }
            }
        }

        // 7. Verifica ordenação na lista (last_activity)
        if ($threadId) {
            if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                $conversationId = (int) $matches[1];
                
                // Busca posição da conversation na lista ordenada por last_activity
                $positionStmt = $db->prepare("
                    SELECT 
                        c.id,
                        c.last_message_at,
                        c.unread_count,
                        (SELECT COUNT(*) + 1 
                         FROM conversations c2 
                         WHERE c2.last_message_at > c.last_message_at 
                           OR (c2.last_message_at = c.last_message_at AND c2.id < c.id)
                        ) as position
                    FROM conversations c
                    WHERE c.id = ?
                ");
                $positionStmt->execute([$conversationId]);
                $positionData = $positionStmt->fetch(PDO::FETCH_ASSOC);
                
                // Busca total de conversations
                $totalStmt = $db->query("SELECT COUNT(*) as total FROM conversations");
                $totalData = $totalStmt->fetch(PDO::FETCH_ASSOC);
                $totalConversations = $totalData['total'] ?? 0;
                
                $isAtTop = ($positionData['position'] ?? 999) <= 3; // Top 3
                
                $diagnosis = [];
                if (!$isAtTop) {
                    $diagnosis[] = '⚠️ Conversation não está no topo da lista (posição ' . ($positionData['position'] ?? '?') . ' de ' . $totalConversations . ')';
                    $diagnosis[] = 'Causa: last_message_at não foi atualizado ou há conversations mais recentes';
                    $diagnosis[] = 'Impacto: Conversation não aparece primeiro na lista do frontend';
                }
                
                $report['checks']['list_ordering'] = [
                    'status' => $isAtTop ? 'OK' : 'WARNING',
                    'position' => $positionData['position'] ?? null,
                    'total_conversations' => $totalConversations,
                    'is_at_top' => $isAtTop ? 'SIM' : 'NÃO',
                    'last_message_at' => $positionData['last_message_at'] ?? null,
                    'unread_count' => $positionData['unread_count'] ?? null,
                    'diagnosis' => !empty($diagnosis) ? implode(' | ', $diagnosis) : '✅ Conversation está no topo da lista'
                ];
            }
        }

        $this->json([
            'success' => true,
            'report' => $report
        ]);
    }

    /**
     * Constrói payload de webhook baseado no template
     */
    private function buildWebhookPayload(string $template, string $from, string $to, string $body, string $eventId, string $channelId): array
    {
        $basePayload = [
            'event' => $template === 'inbound' ? 'message' : ($template === 'outbound' ? 'message.sent' : 'message.ack'),
            'channel' => $channelId,
            'session' => [
                'id' => $channelId
            ],
            'timestamp' => time()
        ];

        if ($template === 'inbound') {
            $basePayload['from'] = $from;
            $basePayload['message'] = [
                'id' => $eventId ?: 'test_' . uniqid(),
                'from' => $from,
                'text' => $body,
                'timestamp' => time()
            ];
        } elseif ($template === 'outbound') {
            $basePayload['to'] = $to;
            $basePayload['message'] = [
                'id' => $eventId ?: 'test_' . uniqid(),
                'to' => $to,
                'text' => $body,
                'timestamp' => time()
            ];
        }

        return $basePayload;
    }

    /**
     * Verifica logs do webhook (página web)
     * 
     * GET /settings/whatsapp-gateway/diagnostic/check-logs
     */
    public function checkWebhookLogs(): void
    {
        Auth::requireInternal();

        $correlationId = $_GET['correlation_id'] ?? '9858a507-cc4c-4632-8f92-462535eab504';
        $testTime = $_GET['test_time'] ?? '21:35';
        $containerName = $_GET['container'] ?? 'gateway-hub';

        // Função para executar comando e capturar output
        $execCommand = function($command) {
            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);
            return [
                'output' => $output,
                'return_code' => $returnVar,
                'command' => $command
            ];
        };

        // Verifica Docker
        $dockerCheck = $execCommand('docker --version');
        $dockerAvailable = $dockerCheck['return_code'] === 0;

        // Lista containers
        $containers = [];
        $hubContainer = null;
        if ($dockerAvailable) {
            $containersCmd = $execCommand('docker ps -a --format "{{.Names}}\t{{.Status}}"');
            $containers = $containersCmd['output'];
            
            // Tenta encontrar container do Hub
            $allContainers = $execCommand('docker ps -a --format "{{.Names}}"');
            foreach ($allContainers['output'] as $name) {
                $name = trim($name);
                if (stripos($name, 'hub') !== false || stripos($name, 'pixel') !== false) {
                    $hubContainer = $name;
                    break;
                }
            }
            
            if (!$hubContainer) {
                $hubContainer = $containerName;
            }
        }

        // Busca logs
        $logs = [
            'correlation_id' => [],
            'webhook_in' => [],
            'msg_save' => [],
            'msg_drop' => [],
            'errors' => [],
            'recent_hub_logs' => [] // Logs HUB_* recentes para diagnóstico
        ];

        // Busca em arquivos de log do Pixel Hub (fallback se Docker não disponível)
        // Os logs que queremos são do Hub, não do gateway
        $logFiles = [
            __DIR__ . '/../../logs/pixelhub.log',
            __DIR__ . '/../../storage/logs/pixelhub.log',
            __DIR__ . '/../../var/log/pixelhub.log',
            ini_get('error_log') ?: null,
            '/var/log/php/error.log',
            '/var/log/apache2/error.log',
            '/var/log/nginx/error.log',
        ];

        $foundLogFile = null;
        foreach ($logFiles as $logFile) {
            if ($logFile && file_exists($logFile) && is_readable($logFile)) {
                $foundLogFile = $logFile;
                break;
            }
        }

        if ($dockerAvailable && $hubContainer) {
            // Busca correlation_id
            $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i '$correlationId' | tail -30";
            $result = $execCommand($cmd);
            $logs['correlation_id'] = $result['output'];

            // Busca HUB_WEBHOOK_IN
            $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_WEBHOOK_IN' | tail -20";
            $result = $execCommand($cmd);
            $logs['webhook_in'] = $result['output'];

            // Busca HUB_MSG_SAVE
            $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_MSG_SAVE' | tail -20";
            $result = $execCommand($cmd);
            $logs['msg_save'] = $result['output'];

            // Busca HUB_MSG_DROP
            $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_MSG_DROP' | tail -20";
            $result = $execCommand($cmd);
            $logs['msg_drop'] = $result['output'];

            // Busca erros
            $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -iE 'Exception|Error|Fatal' | tail -20";
            $result = $execCommand($cmd);
            $logs['errors'] = $result['output'];
        } elseif ($foundLogFile) {
            // Fallback: busca em arquivo de log
            try {
                $lines = file($foundLogFile);
                if ($lines !== false) {
                    $totalLines = count($lines);
                    $startIndex = max(0, $totalLines - 5000); // Últimas 5000 linhas
                    
                    for ($i = $startIndex; $i < $totalLines; $i++) {
                        $line = $lines[$i];
                        $lineTrimmed = trim($line);
                        
                        // Ignora linhas de roteamento/URL que contêm o correlation_id
                        if (stripos($line, 'Path calculado') !== false || 
                            stripos($line, 'REQUEST_URI') !== false ||
                            stripos($line, 'settings/whatsapp-gateway/diagnostic/check-logs') !== false) {
                            continue;
                        }
                        
                        // Busca correlation_id (mas não em URLs - apenas em logs HUB_*)
                        if (stripos($line, $correlationId) !== false) {
                            // Aceita se for um log HUB_* (não URL)
                            if (stripos($line, '[HUB_') !== false || 
                                (stripos($line, 'correlationId=') !== false && stripos($line, '[HUB_') !== false) ||
                                (stripos($line, 'correlation_id=') !== false && stripos($line, '[HUB_') !== false)) {
                                $logs['correlation_id'][] = $lineTrimmed;
                            }
                        }
                        
                        // Busca HUB_WEBHOOK_IN (qualquer horário, mas filtra por padrão)
                        if (stripos($line, 'HUB_WEBHOOK_IN') !== false) {
                            // Aceita se tiver o horário do teste OU se tiver correlation_id OU se for próximo (21:3x)
                            if (stripos($line, $testTime) !== false || 
                                stripos($line, '21:3') !== false ||
                                stripos($line, $correlationId) !== false ||
                                stripos($line, '19:3') !== false) { // UTC pode ser 19:35
                                $logs['webhook_in'][] = $lineTrimmed;
                            }
                        }
                        
                        // Busca HUB_MSG_SAVE
                        if (stripos($line, 'HUB_MSG_SAVE') !== false) {
                            if (stripos($line, $testTime) !== false || 
                                stripos($line, '21:3') !== false ||
                                stripos($line, $correlationId) !== false ||
                                stripos($line, '19:3') !== false) {
                                $logs['msg_save'][] = $lineTrimmed;
                            }
                        }
                        
                        // Busca HUB_MSG_DROP
                        if (stripos($line, 'HUB_MSG_DROP') !== false) {
                            if (stripos($line, $testTime) !== false || 
                                stripos($line, '21:3') !== false ||
                                stripos($line, $correlationId) !== false ||
                                stripos($line, '19:3') !== false) {
                                $logs['msg_drop'][] = $lineTrimmed;
                            }
                        }
                        
                        // Busca erros
                        if ((stripos($line, 'Exception') !== false || 
                             stripos($line, 'Error') !== false || 
                             stripos($line, 'Fatal') !== false) &&
                            (stripos($line, $testTime) !== false || 
                             stripos($line, '21:3') !== false ||
                             stripos($line, '19:3') !== false ||
                             stripos($line, $correlationId) !== false)) {
                            $logs['errors'][] = $lineTrimmed;
                        }
                    }
                    
                    // Busca logs HUB_* recentes (últimas 2 horas) para diagnóstico
                    $twoHoursAgo = date('Y-m-d H:i:s', strtotime('-2 hours'));
                    $recentAnyLogs = []; // Últimos logs de qualquer tipo (para diagnóstico)
                    
                    for ($i = $startIndex; $i < $totalLines; $i++) {
                        $line = $lines[$i];
                        $lineTrimmed = trim($line);
                        
                        // Busca logs HUB_*
                        if (stripos($line, '[HUB_') !== false) {
                            // Extrai timestamp da linha (formato [YYYY-MM-DD HH:MM:SS])
                            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                                $lineTimestamp = $matches[1];
                                if ($lineTimestamp >= $twoHoursAgo) {
                                    $logs['recent_hub_logs'][] = $lineTrimmed;
                                }
                            } else {
                                // Se não tem timestamp, adiciona mesmo assim (pode ser formato diferente)
                                $logs['recent_hub_logs'][] = $lineTrimmed;
                            }
                        }
                        
                        // Busca qualquer log recente (últimas 50 linhas) para ver se arquivo está sendo escrito
                        if ($i >= $totalLines - 50) {
                            // Busca por padrões relacionados a webhook/whatsapp
                            if (stripos($line, 'webhook') !== false || 
                                stripos($line, 'whatsapp') !== false ||
                                stripos($line, 'WHATSAPP') !== false ||
                                stripos($line, 'WEBHOOK') !== false ||
                                stripos($line, 'message') !== false ||
                                stripos($line, 'inbound') !== false) {
                                $recentAnyLogs[] = $lineTrimmed;
                            }
                        }
                    }
                    
                    // Limita logs recentes gerais
                    $logs['recent_any_logs'] = array_slice(array_unique($recentAnyLogs), -30);
                    
                    // Remove duplicatas e limita resultados
                    $logs['correlation_id'] = array_slice(array_unique($logs['correlation_id']), -30);
                    $logs['webhook_in'] = array_slice(array_unique($logs['webhook_in']), -20);
                    $logs['msg_save'] = array_slice(array_unique($logs['msg_save']), -20);
                    $logs['msg_drop'] = array_slice(array_unique($logs['msg_drop']), -20);
                    $logs['errors'] = array_slice(array_unique($logs['errors']), -20);
                    $logs['recent_hub_logs'] = array_slice(array_unique($logs['recent_hub_logs']), -50);
                }
            } catch (\Exception $e) {
                // Ignora erro de leitura
            }
        }

        // Verifica banco de dados
        $db = DB::getConnection();
        $events = [];
        try {
            $stmt = $db->prepare("
                SELECT 
                    id,
                    event_id,
                    correlation_id,
                    event_type,
                    status,
                    created_at,
                    JSON_EXTRACT(payload, '$.message.id') as message_id
                FROM communication_events 
                WHERE correlation_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$correlationId]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Ignora erro
        }

        // Informações sobre o arquivo de log
        $logFileInfo = null;
        if ($foundLogFile) {
            $logFileInfo = [
                'path' => $foundLogFile,
                'exists' => file_exists($foundLogFile),
                'readable' => is_readable($foundLogFile),
                'size' => file_exists($foundLogFile) ? filesize($foundLogFile) : 0,
                'modified' => file_exists($foundLogFile) ? date('Y-m-d H:i:s', filemtime($foundLogFile)) : null,
                'lines' => isset($totalLines) ? $totalLines : null,
                'php_error_log' => ini_get('error_log'),
                'log_errors' => ini_get('log_errors')
            ];
        }
        
        $this->view('settings.check_webhook_logs', [
            'correlationId' => $correlationId,
            'testTime' => $testTime,
            'containerName' => $hubContainer ?: $containerName,
            'dockerAvailable' => $dockerAvailable,
            'containers' => $containers,
            'logs' => $logs,
            'events' => $events,
            'logFile' => $foundLogFile ?? null,
            'logFileInfo' => $logFileInfo
        ]);
    }

}

