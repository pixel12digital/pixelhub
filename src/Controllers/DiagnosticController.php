<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Controllers\WhatsAppGatewaySettingsController;

/**
 * Controller para diagnóstico e logs de erros do sistema
 */
class DiagnosticController extends Controller
{
    /**
     * Diagnóstico Financeiro - Exibe todos os erros relacionados ao módulo financeiro
     * 
     * GET /diagnostic/financial
     */
    public function financial(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Busca erros de sincronização
        $syncErrors = $this->getSyncErrors();
        
        // Busca erros de webhook do Asaas
        $webhookErrors = $this->getWebhookErrors($db);
        
        // Busca erros de cobranças
        $billingErrors = $this->getBillingErrors($db);
        
        // Estatísticas gerais
        $stats = [
            'total_sync_errors' => count($syncErrors),
            'total_webhook_errors' => count($webhookErrors),
            'total_billing_errors' => count($billingErrors),
            'last_sync_error' => !empty($syncErrors) ? $syncErrors[0]['timestamp'] : null,
            'last_webhook_error' => !empty($webhookErrors) ? $webhookErrors[0]['created_at'] : null,
        ];

        $this->view('diagnostic.financial', [
            'syncErrors' => $syncErrors,
            'webhookErrors' => $webhookErrors,
            'billingErrors' => $billingErrors,
            'stats' => $stats,
        ]);
    }

    /**
     * Busca erros de sincronização do arquivo de log
     */
    private function getSyncErrors(): array
    {
        $errors = [];
        
        // Busca erros do arquivo de sincronização específico
        $syncLogFile = __DIR__ . '/../../logs/asaas_sync_errors.log';
        if (file_exists($syncLogFile)) {
            $lines = file($syncLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -100);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.+)$/', $line, $matches)) {
                    $errors[] = [
                        'timestamp' => $matches[1],
                        'message' => $matches[2],
                        'type' => 'sync'
                    ];
                }
            }
        }
        
        // Busca erros gerais do módulo financeiro relacionados a sincronização
        $financialLogFile = __DIR__ . '/../../logs/financial_errors.log';
        if (file_exists($financialLogFile)) {
            $lines = file($financialLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -50);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*\[sync\]\s*(.+)$/', $line, $matches)) {
                    $errors[] = [
                        'timestamp' => $matches[1],
                        'message' => $matches[2],
                        'type' => 'sync'
                    ];
                }
            }
        }
        
        // Ordena por timestamp (mais recentes primeiro)
        usort($errors, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Limita a 100 erros
        return array_slice($errors, 0, 100);
    }

    /**
     * Busca erros de webhook do Asaas do banco de dados
     */
    private function getWebhookErrors($db): array
    {
        $errors = [];

        try {
            // Verifica se a tabela existe
            $stmt = $db->query("SHOW TABLES LIKE 'asaas_webhook_logs'");
            if ($stmt->rowCount() === 0) {
                return $errors;
            }

            // Busca webhooks com erros (payload contém "error" ou status de erro)
            $stmt = $db->query("
                SELECT id, event, payload, created_at
                FROM asaas_webhook_logs
                WHERE payload LIKE '%\"error\"%' 
                   OR payload LIKE '%\"status\":\"ERROR\"%'
                   OR payload LIKE '%\"status\":\"FAILED\"%'
                ORDER BY created_at DESC
                LIMIT 50
            ");
            
            $results = $stmt->fetchAll();
            
            foreach ($results as $row) {
                $payload = json_decode($row['payload'], true);
                $errorMessage = 'Erro desconhecido';
                
                if (isset($payload['error'])) {
                    $errorMessage = is_array($payload['error']) ? json_encode($payload['error']) : $payload['error'];
                } elseif (isset($payload['status']) && in_array($payload['status'], ['ERROR', 'FAILED'])) {
                    $errorMessage = "Status: {$payload['status']}";
                }
                
                $errors[] = [
                    'id' => $row['id'],
                    'event' => $row['event'] ?? 'N/A',
                    'message' => $errorMessage,
                    'created_at' => $row['created_at'],
                    'type' => 'webhook',
                    'payload' => $row['payload']
                ];
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar webhook errors: " . $e->getMessage());
        }

        return $errors;
    }

    /**
     * Busca erros relacionados a cobranças (faturas com problemas)
     */
    private function getBillingErrors($db): array
    {
        $errors = [];

        try {
            // Busca faturas com problemas potenciais
            // 1. Faturas com valores inconsistentes
            $stmt = $db->query("
                SELECT id, tenant_id, asaas_payment_id, amount, status, due_date, created_at
                FROM billing_invoices
                WHERE amount <= 0 
                   OR (due_date IS NULL AND status IN ('pending', 'overdue'))
                   OR (asaas_payment_id IS NULL AND status != 'canceled')
                ORDER BY created_at DESC
                LIMIT 50
            ");
            
            $results = $stmt->fetchAll();
            
            foreach ($results as $row) {
                $errorMessage = '';
                if ($row['amount'] <= 0) {
                    $errorMessage = "Valor inválido: R$ {$row['amount']}";
                } elseif ($row['due_date'] === null && in_array($row['status'], ['pending', 'overdue'])) {
                    $errorMessage = "Data de vencimento ausente para fatura {$row['status']}";
                } elseif ($row['asaas_payment_id'] === null && $row['status'] !== 'canceled') {
                    $errorMessage = "Fatura sem ID do Asaas";
                }
                
                if ($errorMessage) {
                    $errors[] = [
                        'id' => $row['id'],
                        'invoice_id' => $row['id'],
                        'tenant_id' => $row['tenant_id'],
                        'message' => $errorMessage,
                        'created_at' => $row['created_at'],
                        'type' => 'billing',
                        'status' => $row['status']
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar billing errors: " . $e->getMessage());
        }

        return $errors;
    }

    /**
     * API JSON para buscar erros (para AJAX)
     * 
     * GET /diagnostic/financial/errors?type=sync|webhook|billing
     */
    public function getErrorsJson(): void
    {
        Auth::requireInternal();

        $type = $_GET['type'] ?? 'all';
        $db = DB::getConnection();

        $errors = [];

        if ($type === 'all' || $type === 'sync') {
            $errors['sync'] = $this->getSyncErrors();
        }

        if ($type === 'all' || $type === 'webhook') {
            $errors['webhook'] = $this->getWebhookErrors($db);
        }

        if ($type === 'all' || $type === 'billing') {
            $errors['billing'] = $this->getBillingErrors($db);
        }

        $this->json($errors);
    }

    /**
     * Diagnóstico de Comunicação - Página principal
     * 
     * GET /diagnostic/communication
     */
    public function communication(): void
    {
        Auth::requireInternal();

        // Verifica se diagnóstico está habilitado (variável de ambiente ou config)
        // Por padrão, habilitado (pode ser desativado via .env)
        $diagnosticsEnabled = filter_var(
            Env::get('COMMUNICATION_DIAGNOSTICS_ENABLED', 'true'), 
            FILTER_VALIDATE_BOOLEAN
        );

        $this->view('diagnostic.communication', [
            'diagnosticsEnabled' => $diagnosticsEnabled,
        ]);
    }

    /**
     * Executa diagnóstico de comunicação
     * 
     * POST /diagnostic/communication/run
     */
    public function runCommunicationDiagnostic(): void
    {
        // Limpa qualquer output anterior que possa corromper o JSON
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // Captura conteúdo bruto da requisição (para diagnóstico)
        $rawPost = $_POST; // Array $_POST completo
        $rawInput = file_get_contents('php://input'); // Body bruto (pode ser JSON ou form-data)
        
        // Log do conteúdo bruto recebido
        error_log("[CommunicationDiagnostic] === REQUISIÇÃO BRUTA RECEBIDA ===");
        error_log("[CommunicationDiagnostic] \$_POST: " . json_encode($rawPost, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        error_log("[CommunicationDiagnostic] php://input: " . ($rawInput ?: '(vazio)'));
        error_log("[CommunicationDiagnostic] Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
        
        Auth::requireInternal();
        
        $threadId = trim($_POST['thread_id'] ?? '');
        $testMessage = trim($_POST['test_message'] ?? '');
        $testType = $_POST['test_type'] ?? 'resolve_channel'; // resolve_channel, dry_run, send_real

        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        // Gera trace_id único para esta execução
        $traceId = 'diag_' . date('YmdHis') . '_' . uniqid();
        $steps = [];

        try {
            $db = DB::getConnection();
            $startTime = microtime(true);

            // Teste 1: Resolver canal
            $channelResolution = $this->diagnoseChannelResolution($db, $threadId, $traceId, $steps);
            $channelResolutionTime = (microtime(true) - $startTime) * 1000;

            $result = [
                'success' => true,
                'trace_id' => $traceId,
                'thread_id' => $_POST['thread_id'] ?? null, // Atribuído explicitamente de $_POST
                'test_type' => $_POST['test_type'] ?? null, // Atribuído explicitamente de $_POST
                'timestamp' => date('Y-m-d H:i:s'),
                'raw_request' => [
                    'post' => $rawPost,
                    'php_input' => $rawInput ?: null,
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                ],
                'steps' => $steps,
                'channel_resolution' => $channelResolution,
                'timings' => [
                    'channel_resolution_ms' => round($channelResolutionTime, 2),
                ],
            ];

            // Teste 2: Dry-run (se solicitado)
            if ($testType === 'dry_run' || $testType === 'send_real') {
                $dryRunStart = microtime(true);
                $dryRun = $this->diagnoseDryRun($db, $threadId, $testMessage, $channelResolution, $traceId, $steps);
                $dryRunTime = (microtime(true) - $dryRunStart) * 1000;
                $result['dry_run'] = $dryRun;
                $result['timings']['dry_run_ms'] = round($dryRunTime, 2);
            }

            // Teste 3: Envio real (se solicitado e confirmado)
            if ($testType === 'send_real') {
                $sendStart = microtime(true);
                $sendResult = $this->diagnoseRealSend($db, $threadId, $testMessage, $channelResolution, $traceId, $steps);
                $sendTime = (microtime(true) - $sendStart) * 1000;
                $result['send_result'] = $sendResult;
                $result['timings']['send_ms'] = round($sendTime, 2);
            }

            $result['timings']['total_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            // Log do trace_id
            error_log("[CommunicationDiagnostic] Trace ID: {$traceId} | Thread: {$threadId} | Test: {$testType}");

            $this->json($result);
        } catch (\Throwable $e) {
            // Log completo do erro para debug
            error_log("[CommunicationDiagnostic] Erro: " . $e->getMessage());
            error_log("[CommunicationDiagnostic] Trace: " . $e->getTraceAsString());
            
            // Limpa output antes de retornar JSON de erro
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            
            $this->json([
                'success' => false,
                'trace_id' => $traceId ?? 'unknown',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'raw_request' => [
                    'post' => $rawPost ?? null,
                    'php_input' => $rawInput ?? null,
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                ],
                'steps' => $steps ?? [],
            ], 500);
        }
    }

    /**
     * Diagnóstico: Resolver canal
     */
    private function diagnoseChannelResolution(\PDO $db, string $threadId, string $traceId, array &$steps): array
    {
        $stepStart = microtime(true);
        $result = [
            'thread_channel_id' => null,
            'channel_id_input' => null,
            'normalized_channel_id' => null,
            'winning_rule' => null,
            'failure_reason' => null,
            'details' => [],
        ];

        // Busca informações da thread
        $threadInfo = $this->getThreadInfo($db, $threadId);
        $result['thread_channel_id'] = $threadInfo['channel_id'] ?? null;
        $result['channel_id_input'] = $threadInfo['channel_id'] ?? null;

        $steps[] = [
            'step' => 'get_thread_info',
            'description' => 'Buscar informações da thread',
            'result' => $threadInfo ? 'success' : 'not_found',
            'data' => $threadInfo,
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ];

        if (!$threadInfo) {
            $result['failure_reason'] = 'Thread não encontrada';
            return $result;
        }

        // Normalização: 0/"0"/"" → null
        $normalized = $result['thread_channel_id'];
        if ($normalized === 0 || $normalized === '0' || $normalized === '') {
            $normalized = null;
        }
        $result['normalized_channel_id'] = $normalized;

        $steps[] = [
            'step' => 'normalize_channel_id',
            'description' => 'Normalizar channel_id (0/"0"/"" → null)',
            'result' => $normalized !== null ? 'has_value' : 'null',
            'data' => [
                'input' => $result['thread_channel_id'],
                'normalized' => $normalized,
            ],
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ];

        // Se já tem channel_id normalizado válido, usa ele
        if ($normalized !== null) {
            // Valida se o canal existe e está habilitado
            $channelStmt = $db->prepare("
                SELECT channel_id 
                FROM tenant_message_channels 
                WHERE channel_id = ? 
                AND provider = 'wpp_gateway' 
                AND is_enabled = 1
                LIMIT 1
            ");
            $channelStmt->execute([$normalized]);
            $channelData = $channelStmt->fetch();

            if ($channelData) {
                $result['winning_rule'] = 'usou channel_id da thread';
                $result['details']['validation'] = 'canal existe e está habilitado';
                return $result;
            } else {
                $result['failure_reason'] = 'channel_id da thread não existe ou não está habilitado';
                $result['normalized_channel_id'] = null; // Força busca alternativa
            }
        }

        // PRIORIDADE 2: Busca channel_id dos eventos
        if ($normalized === null && !empty($threadInfo['contact_external_id'])) {
            $eventStepStart = microtime(true);
            $contactId = $threadInfo['contact_external_id'];
            // Busca eventos que contenham o contact_id no payload (mais compatível)
            $eventStmt = $db->prepare("
                SELECT ce.payload, ce.created_at
                FROM communication_events ce
                WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                AND ce.payload LIKE ?
                ORDER BY ce.created_at DESC
                LIMIT 1
            ");
            // Busca o contact_id no payload (pode estar em vários formatos)
            $contactIdPattern = '%' . $contactId . '%';
            $eventStmt->execute([$contactIdPattern]);
            $event = $eventStmt->fetch();

            $steps[] = [
                'step' => 'search_inbound_event',
                'description' => 'Buscar último inbound event',
                'result' => $event ? 'found' : 'not_found',
                'data' => $event ? [
                    'created_at' => $event['created_at'] ?? null,
                    'has_payload' => !empty($event['payload']),
                ] : null,
                'time_ms' => round((microtime(true) - $eventStepStart) * 1000, 2),
            ];

            if ($event && !empty($event['payload'])) {
                $payload = json_decode($event['payload'], true);
                
                // Verifica se json_decode foi bem-sucedido
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                    $result['details']['json_decode_error'] = json_last_error_msg();
                } else {
                    // Tenta buscar session.id (formato encontrado nos payloads)
                    $sessionId = null;
                    if (isset($payload['session']['id'])) {
                        $sessionId = $payload['session']['id'];
                    } elseif (isset($payload['raw']['payload']['session'])) {
                        $sessionId = is_string($payload['raw']['payload']['session']) 
                            ? $payload['raw']['payload']['session'] 
                            : ($payload['raw']['payload']['session']['id'] ?? null);
                    }
                    
                    if ($sessionId) {
                        $result['normalized_channel_id'] = $sessionId;
                        $result['winning_rule'] = 'buscou último inbound event (session.id)';
                        $result['details']['json_path'] = 'session.id';
                        $result['details']['event_created_at'] = $event['created_at'] ?? null;
                    } else {
                        // Fallback: tenta outros caminhos
                        $jsonPaths = [
                            '$.channel_id',
                            '$.message.channel_id',
                            '$.channel',
                        ];

                        foreach ($jsonPaths as $path) {
                            $value = $this->jsonExtract($payload, $path);
                            if ($value !== null && $value !== '' && $value !== 0) {
                                $result['normalized_channel_id'] = is_numeric($value) ? (int) $value : $value;
                                $result['winning_rule'] = 'buscou último inbound event';
                                $result['details']['json_path'] = $path;
                                $result['details']['event_created_at'] = $event['created_at'] ?? null;
                                break;
                            }
                        }

                        if ($result['normalized_channel_id'] === null) {
                            $result['details']['json_paths_tried'] = $jsonPaths;
                            $result['details']['payload_keys'] = array_keys($payload);
                        }
                    }
                }
            } else {
                $result['failure_reason'] = 'não existe inbound event';
            }
        }

        // PRIORIDADE 3: Busca canal do tenant
        if ($result['normalized_channel_id'] === null && !empty($threadInfo['tenant_id'])) {
            $tenantStepStart = microtime(true);
            $tenantStmt = $db->prepare("
                SELECT channel_id 
                FROM tenant_message_channels 
                WHERE tenant_id = ? 
                AND provider = 'wpp_gateway' 
                AND is_enabled = 1
                LIMIT 1
            ");
            $tenantStmt->execute([$threadInfo['tenant_id']]);
            $tenantChannel = $tenantStmt->fetch();

            $steps[] = [
                'step' => 'search_tenant_channel',
                'description' => 'Buscar canal do tenant',
                'result' => $tenantChannel ? 'found' : 'not_found',
                'data' => $tenantChannel ? ['channel_id' => $tenantChannel['channel_id']] : null,
                'time_ms' => round((microtime(true) - $tenantStepStart) * 1000, 2),
            ];

            if ($tenantChannel) {
                $result['normalized_channel_id'] = (int) $tenantChannel['channel_id'];
                $result['winning_rule'] = 'fallback tenant_message_channels';
            }
        }

        // PRIORIDADE 4: Fallback para canal compartilhado
        if ($result['normalized_channel_id'] === null) {
            $fallbackStepStart = microtime(true);
            $fallbackStmt = $db->prepare("
                SELECT channel_id 
                FROM tenant_message_channels 
                WHERE provider = 'wpp_gateway' 
                AND is_enabled = 1
                LIMIT 1
            ");
            $fallbackStmt->execute();
            $fallbackChannel = $fallbackStmt->fetch();

            $steps[] = [
                'step' => 'search_shared_channel',
                'description' => 'Buscar canal compartilhado (fallback)',
                'result' => $fallbackChannel ? 'found' : 'not_found',
                'data' => $fallbackChannel ? ['channel_id' => $fallbackChannel['channel_id']] : null,
                'time_ms' => round((microtime(true) - $fallbackStepStart) * 1000, 2),
            ];

            if ($fallbackChannel) {
                $result['normalized_channel_id'] = (int) $fallbackChannel['channel_id'];
                $result['winning_rule'] = 'fallback tenant_message_channels (compartilhado)';
            } else {
                $result['failure_reason'] = 'tenant sem canais ativos';
            }
        }

        return $result;
    }

    /**
     * Diagnóstico: Dry-run do envio
     */
    private function diagnoseDryRun(\PDO $db, string $threadId, string $testMessage, array $channelResolution, string $traceId, array &$steps): array
    {
        $stepStart = microtime(true);
        $result = [
            'final_channel_id' => null,
            'validations' => [],
            'would_block' => false,
            'sanitized_payload' => null,
            'abort_point' => null,
        ];

        $channelId = $channelResolution['normalized_channel_id'] ?? null;
        $result['final_channel_id'] = $channelId;

        // Validações que rodariam
        $validations = [];

        // Validação 1: channel_id presente
        if ($channelId === null || $channelId === 0) {
            $validations[] = [
                'name' => 'channel_id_present',
                'passed' => false,
                'message' => 'channel_id é obrigatório e não pode ser 0',
                'would_block' => true,
            ];
            $result['would_block'] = true;
            $result['abort_point'] = 'validação de channel_id';
        } else {
            $validations[] = [
                'name' => 'channel_id_present',
                'passed' => true,
                'message' => 'channel_id presente e válido',
            ];
        }

        // Validação 2: canal existe e está habilitado
        if ($channelId !== null && $channelId !== 0) {
            $channelStmt = $db->prepare("
                SELECT channel_id, is_enabled 
                FROM tenant_message_channels 
                WHERE channel_id = ? 
                AND provider = 'wpp_gateway'
                LIMIT 1
            ");
            $channelStmt->execute([$channelId]);
            $channelData = $channelStmt->fetch();

            if (!$channelData) {
                $validations[] = [
                    'name' => 'channel_exists',
                    'passed' => false,
                    'message' => 'Canal não encontrado no banco',
                    'would_block' => true,
                ];
                $result['would_block'] = true;
                if (!$result['abort_point']) {
                    $result['abort_point'] = 'validação de existência do canal';
                }
            } elseif (!$channelData['is_enabled']) {
                $validations[] = [
                    'name' => 'channel_enabled',
                    'passed' => false,
                    'message' => 'Canal existe mas não está habilitado',
                    'would_block' => true,
                ];
                $result['would_block'] = true;
                if (!$result['abort_point']) {
                    $result['abort_point'] = 'validação de habilitação do canal';
                }
            } else {
                $validations[] = [
                    'name' => 'channel_exists',
                    'passed' => true,
                    'message' => 'Canal existe e está habilitado',
                ];
            }
        }

        // Validação 3: mensagem não vazia
        if (empty($testMessage)) {
            $validations[] = [
                'name' => 'message_not_empty',
                'passed' => false,
                'message' => 'Mensagem não pode estar vazia',
                'would_block' => true,
            ];
            $result['would_block'] = true;
            if (!$result['abort_point']) {
                $result['abort_point'] = 'validação de mensagem vazia';
            }
        } else {
            $validations[] = [
                'name' => 'message_not_empty',
                'passed' => true,
                'message' => 'Mensagem presente',
            ];
        }

        $result['validations'] = $validations;

        // Payload sanitizado (sem dados sensíveis)
        $threadInfo = $this->getThreadInfo($db, $threadId);
        $result['sanitized_payload'] = [
            'channel' => 'whatsapp',
            'thread_id' => $threadId,
            'channel_id' => $channelId,
            'to' => $threadInfo['contact_external_id'] ?? '[MASCARADO]',
            'message_length' => strlen($testMessage),
            'has_tenant_id' => !empty($threadInfo['tenant_id']),
        ];

        $steps[] = [
            'step' => 'dry_run_validation',
            'description' => 'Executar validações (dry-run)',
            'result' => $result['would_block'] ? 'would_block' : 'would_send',
            'data' => $result,
            'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ];

        return $result;
    }

    /**
     * Diagnóstico: Envio real
     */
    private function diagnoseRealSend(\PDO $db, string $threadId, string $testMessage, array $channelResolution, string $traceId, array &$steps): array
    {
        $stepStart = microtime(true);
        $result = [
            'success' => false,
            'provider_status' => null,
            'external_id' => null,
            'error' => null,
            'request_payload' => null,
            'response_payload' => null,
        ];

        $channelId = $channelResolution['normalized_channel_id'] ?? null;
        $threadInfo = $this->getThreadInfo($db, $threadId);

        if (!$channelId || $channelId === 0) {
            $result['error'] = 'channel_id inválido - não é possível enviar';
            return $result;
        }

        if (empty($testMessage)) {
            $result['error'] = 'mensagem vazia - não é possível enviar';
            return $result;
        }

        $to = $threadInfo['contact_external_id'] ?? null;
        if (!$to) {
            $result['error'] = 'destinatário não encontrado na thread';
            return $result;
        }

        // Normaliza telefone
        $phoneNormalized = \PixelHub\Services\WhatsAppBillingService::normalizePhone($to);
        if (empty($phoneNormalized)) {
            $result['error'] = 'telefone inválido após normalização';
            return $result;
        }

        // Prepara payload (sanitizado para log)
        $result['request_payload'] = [
            'channel_id' => $channelId,
            'to' => '[MASCARADO]',
            'to_normalized' => $phoneNormalized,
            'message_length' => strlen($testMessage),
        ];

        try {
            // Obtém secret descriptografado usando GatewaySecret (fonte única)
            $secret = \PixelHub\Services\GatewaySecret::getDecrypted();
            $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
            
            // Log do secret que será usado no envio real
            $secretPreview = !empty($secret) 
                ? (substr($secret, 0, 4) . '...' . substr($secret, -4) . ' (len=' . strlen($secret) . ')')
                : 'VAZIO';
            error_log("[CommunicationDiagnostic::diagnoseRealSend] send_real -> secret (descriptografado) preview: {$secretPreview}");
            
            // Envia via gateway usando o secret descriptografado (fonte única via GatewaySecret)
            $gateway = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient($baseUrl, $secret);
            
            $sendResult = $gateway->sendText($channelId, $phoneNormalized, $testMessage, [
                'sent_by' => Auth::user()['id'] ?? null,
                'sent_by_name' => Auth::user()['name'] ?? null,
                'trace_id' => $traceId,
            ]);

            $result['success'] = $sendResult['success'] ?? false;
            $result['provider_status'] = $sendResult['success'] ? 'sent' : 'failed';
            $result['external_id'] = $sendResult['message_id'] ?? $sendResult['correlationId'] ?? null;
            $result['error'] = $sendResult['error'] ?? null;

            // Response sanitizado
            $result['response_payload'] = [
                'success' => $result['success'],
                'message_id' => $result['external_id'],
                'has_error' => !empty($result['error']),
            ];

            $steps[] = [
                'step' => 'real_send',
                'description' => 'Enviar mensagem real via gateway',
                'result' => $result['success'] ? 'success' : 'failed',
                'data' => $result,
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $steps[] = [
                'step' => 'real_send',
                'description' => 'Enviar mensagem real via gateway',
                'result' => 'exception',
                'error' => $e->getMessage(),
                'time_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ];
        }

        return $result;
    }

    /**
     * Busca informações da thread (reutiliza lógica do CommunicationHubController)
     */
    private function getThreadInfo(\PDO $db, string $threadId): ?array
    {
        // Formato novo: whatsapp_{conversation_id}
        if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
            $conversationId = (int) $matches[1];
            
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    t.name as tenant_name,
                    tmc.channel_id as tenant_channel_id
                FROM conversations c
                LEFT JOIN tenants t ON c.tenant_id = t.id
                LEFT JOIN tenant_message_channels tmc ON c.tenant_id = tmc.tenant_id AND tmc.provider = 'wpp_gateway' AND tmc.is_enabled = 1
                WHERE c.id = ?
            ");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if ($conversation) {
                return [
                    'thread_id' => $threadId,
                    'conversation_id' => $conversationId,
                    'tenant_id' => $conversation['tenant_id'],
                    'contact_external_id' => $conversation['contact_external_id'],
                    'channel_id' => $conversation['tenant_channel_id'],
                ];
            }
        }

        // Formato antigo: whatsapp_{tenant_id}_{from}
        if (preg_match('/whatsapp_(\d+)_(.+)/', $threadId, $matches)) {
            $tenantId = (int) $matches[1];
            $from = $matches[2];

            $stmt = $db->prepare("
                SELECT t.*, tmc.channel_id
                FROM tenants t
                LEFT JOIN tenant_message_channels tmc ON t.id = tmc.tenant_id AND tmc.provider = 'wpp_gateway'
                WHERE t.id = ?
            ");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                return [
                    'thread_id' => $threadId,
                    'tenant_id' => $tenantId,
                    'contact_external_id' => $from,
                    'channel_id' => $tenant['channel_id'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Extrai valor de JSON usando path (simula JSON_EXTRACT do MySQL)
     */
    private function jsonExtract(array $data, string $path): mixed
    {
        $path = trim($path, '$.');
        $keys = explode('.', $path);
        
        $current = $data;
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }
}

