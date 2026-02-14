<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PDO;

/**
 * Controller para sistema de alertas e monitoramento
 * 
 * Responsável por:
 * - Health check do gateway WhatsApp e sessões
 * - Listar alertas ativos (para banner global)
 * - Acknowledge de alertas (usuário ciente)
 */
class SystemAlertController extends Controller
{
    /**
     * Retorna alertas ativos não reconhecidos (para banner global)
     * GET /api/system-alerts
     */
    public function activeAlerts(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT id, alert_type, severity, title, message, session_id,
                       first_detected_at, last_checked_at, check_count,
                       acknowledged_at, acknowledged_by
                FROM system_alerts
                WHERE is_active = 1
                ORDER BY 
                    CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END,
                    first_detected_at DESC
            ");
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->json([
                'success' => true,
                'alerts' => $alerts,
                'has_unacknowledged' => count(array_filter($alerts, fn($a) => $a['acknowledged_at'] === null)) > 0,
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'alerts' => []], 500);
        }
    }

    /**
     * Marca alerta como reconhecido (usuário ciente)
     * POST /api/system-alerts/acknowledge
     * Body: { "alert_id": 123 } ou { "all": true }
     */
    public function acknowledge(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        $user = Auth::user();
        $userId = $user['id'] ?? null;

        try {
            $db = DB::getConnection();

            if (!empty($input['all'])) {
                $stmt = $db->prepare("
                    UPDATE system_alerts 
                    SET acknowledged_at = NOW(), acknowledged_by = ?
                    WHERE is_active = 1 AND acknowledged_at IS NULL
                ");
                $stmt->execute([$userId]);
                $count = $stmt->rowCount();
            } else {
                $alertId = (int)($input['alert_id'] ?? 0);
                if ($alertId <= 0) {
                    $this->json(['success' => false, 'error' => 'alert_id é obrigatório'], 400);
                    return;
                }
                $stmt = $db->prepare("
                    UPDATE system_alerts 
                    SET acknowledged_at = NOW(), acknowledged_by = ?
                    WHERE id = ? AND is_active = 1
                ");
                $stmt->execute([$userId, $alertId]);
                $count = $stmt->rowCount();

                // Log
                $this->logAlertEvent($db, $alertId, 'acknowledged', json_encode([
                    'user_id' => $userId,
                    'user_name' => $user['name'] ?? null,
                ]));
            }

            $this->json(['success' => true, 'acknowledged' => $count]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Executa health check do gateway e sessões WhatsApp
     * Chamado pelo cron job (scripts/system_health_check.php)
     * Também pode ser chamado via GET /api/system-alerts/check (interno)
     */
    public static function runHealthCheck(): array
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'gateway_reachable' => false,
            'sessions' => [],
            'alerts_created' => 0,
            'alerts_resolved' => 0,
            'errors' => [],
        ];

        try {
            Env::load(__DIR__ . '/../../.env', true);
            $db = DB::getConnection();

            // 1. Verificar se gateway está acessível
            $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br:8443');
            // Reutiliza o método centralizado de descriptografia do secret
            $secret = WhatsAppGatewaySettingsController::getDecryptedSecret();

            if (empty($secret)) {
                $results['errors'][] = 'WPP_GATEWAY_SECRET não configurado';
                self::createOrUpdateAlert($db, 'gateway_config_error', 'critical',
                    'Gateway WhatsApp: Secret não configurado',
                    'O WPP_GATEWAY_SECRET não está configurado no .env. O sistema não consegue se comunicar com o gateway.',
                    null, ['error' => 'missing_secret']
                );
                $results['alerts_created']++;
                return $results;
            }

            $gateway = new WhatsAppGatewayClient($baseUrl, $secret, 15);

            // Tenta listar canais (testa conectividade + autenticação)
            $channelsResult = $gateway->listChannels();

            if (empty($channelsResult['success'])) {
                $errorMsg = $channelsResult['error'] ?? 'Erro desconhecido';
                $errorCode = $channelsResult['error_code'] ?? 'UNKNOWN';
                $httpCode = $channelsResult['status'] ?? $channelsResult['http_code'] ?? 0;

                $results['errors'][] = "Gateway inacessível: {$errorMsg}";

                // Determinar causa provável
                $cause = 'Causa desconhecida';
                if ($errorCode === 'GATEWAY_HTML_ERROR' || $httpCode === 502) {
                    $cause = 'O servidor do gateway (VPS) retornou erro 502 Bad Gateway. Possíveis causas: container Docker parado, nginx mal configurado, ou servidor sem memória.';
                } elseif ($errorCode === 'GATEWAY_TIMEOUT' || $httpCode === 504) {
                    $cause = 'Timeout ao conectar com o gateway. O servidor pode estar sobrecarregado ou o container Docker pode estar travado.';
                } elseif ($errorCode === 'UNAUTHORIZED' || $httpCode === 401) {
                    $cause = 'Autenticação falhou. O secret configurado no PixelHub não corresponde ao do gateway.';
                } elseif ($httpCode === 0) {
                    $cause = 'Não foi possível conectar ao gateway. O servidor VPS pode estar offline ou o DNS pode estar incorreto.';
                }

                self::createOrUpdateAlert($db, 'gateway_offline', 'critical',
                    'Gateway WhatsApp OFFLINE',
                    "O gateway WhatsApp não está respondendo. {$cause}",
                    null, [
                        'error' => $errorMsg,
                        'error_code' => $errorCode,
                        'http_code' => $httpCode,
                        'cause' => $cause,
                        'gateway_url' => $baseUrl,
                    ]
                );
                $results['alerts_created']++;
                return $results;
            }

            // Gateway acessível
            $results['gateway_reachable'] = true;

            // Resolver alerta de gateway offline se existir
            $resolved = self::resolveAlert($db, 'gateway_offline');
            if ($resolved) $results['alerts_resolved']++;
            $resolved = self::resolveAlert($db, 'gateway_config_error');
            if ($resolved) $results['alerts_resolved']++;

            // 2. Verificar status de cada sessão
            $raw = $channelsResult['raw'] ?? [];
            $channels = $raw['channels'] ?? $raw['data']['channels'] ?? $channelsResult['channels'] ?? [];
            if (!is_array($channels)) $channels = [];

            foreach ($channels as $ch) {
                $sessionId = $ch['id'] ?? $ch['name'] ?? 'unknown';
                $status = strtolower(trim($ch['status'] ?? 'unknown'));

                $results['sessions'][$sessionId] = $status;

                if ($status !== 'connected') {
                    // Sessão desconectada
                    $cause = 'Causa desconhecida';
                    if ($status === 'disconnected' || $status === 'unpaired') {
                        $cause = 'A sessão foi desvinculada do WhatsApp. Isso pode acontecer quando: (1) o celular ficou sem internet por muito tempo, (2) o WhatsApp foi atualizado e invalidou a sessão, (3) o usuário desconectou manualmente em "Dispositivos vinculados". É necessário escanear o QR code novamente.';
                    } elseif ($status === 'initializing' || $status === 'starting') {
                        $cause = 'A sessão está inicializando. Aguarde alguns minutos. Se persistir, pode ser necessário reiniciar o container WPPConnect.';
                    } elseif ($status === 'qr') {
                        $cause = 'A sessão está aguardando escaneamento do QR code. Acesse Configurações > WhatsApp Gateway e clique em Reconectar.';
                    }

                    self::createOrUpdateAlert($db, 'session_disconnected', 'critical',
                        "Sessão WhatsApp \"{$sessionId}\" desconectada",
                        "A sessão \"{$sessionId}\" não está conectada (status: {$status}). {$cause}\n\nEnquanto desconectada, mensagens NÃO serão recebidas no Inbox.",
                        $sessionId, [
                            'session_id' => $sessionId,
                            'status' => $status,
                            'cause' => $cause,
                        ]
                    );
                    $results['alerts_created']++;
                } else {
                    // Sessão conectada - resolver alerta se existir
                    $resolved = self::resolveAlertBySession($db, 'session_disconnected', $sessionId);
                    if ($resolved) $results['alerts_resolved']++;
                }
            }

            // 3. Verificar se há sessões esperadas que não apareceram na lista
            // (sessão removida do gateway = problema grave)
            $expectedSessions = self::getExpectedSessions($db);
            foreach ($expectedSessions as $expected) {
                if (!isset($results['sessions'][$expected])) {
                    self::createOrUpdateAlert($db, 'session_missing', 'critical',
                        "Sessão WhatsApp \"{$expected}\" não encontrada no gateway",
                        "A sessão \"{$expected}\" deveria existir no gateway mas não foi encontrada. Pode ter sido removida acidentalmente ou o gateway perdeu os dados. É necessário recriar a sessão.",
                        $expected, [
                            'session_id' => $expected,
                            'cause' => 'Sessão não encontrada na lista do gateway',
                            'available_sessions' => array_keys($results['sessions']),
                        ]
                    );
                    $results['alerts_created']++;
                }
            }

            // Log permanente
            self::logAlertEventStatic($db, null, 'health_check_ok', json_encode($results));

        } catch (\Throwable $e) {
            $results['errors'][] = $e->getMessage();
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[SystemHealthCheck] ERRO: ' . $e->getMessage());
            }
            error_log('[SystemHealthCheck] ERRO: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Endpoint para executar health check manualmente
     * GET /api/system-alerts/check
     */
    public function check(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $results = self::runHealthCheck();
        $this->json(['success' => true, 'results' => $results]);
    }

    // ========== Métodos auxiliares ==========

    /**
     * Cria novo alerta ou atualiza existente (incrementa check_count)
     */
    private static function createOrUpdateAlert(PDO $db, string $alertType, string $severity, string $title, string $message, ?string $sessionId, array $context = []): void
    {
        // Busca alerta ativo do mesmo tipo/sessão
        if ($sessionId) {
            $stmt = $db->prepare("SELECT id, check_count FROM system_alerts WHERE alert_type = ? AND session_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$alertType, $sessionId]);
        } else {
            $stmt = $db->prepare("SELECT id, check_count FROM system_alerts WHERE alert_type = ? AND session_id IS NULL AND is_active = 1 LIMIT 1");
            $stmt->execute([$alertType]);
        }
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Atualiza existente
            $stmt = $db->prepare("
                UPDATE system_alerts 
                SET check_count = check_count + 1, 
                    last_checked_at = NOW(),
                    message = ?,
                    context_json = ?,
                    acknowledged_at = NULL,
                    acknowledged_by = NULL
                WHERE id = ?
            ");
            $stmt->execute([$message, json_encode($context, JSON_UNESCAPED_UNICODE), $existing['id']]);

            self::logAlertEventStatic($db, $existing['id'], 'check_failed', json_encode($context, JSON_UNESCAPED_UNICODE));
        } else {
            // Cria novo
            $stmt = $db->prepare("
                INSERT INTO system_alerts (alert_type, severity, title, message, context_json, session_id, is_active, first_detected_at, last_checked_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$alertType, $severity, $title, $message, json_encode($context, JSON_UNESCAPED_UNICODE), $sessionId]);
            $alertId = $db->lastInsertId();

            self::logAlertEventStatic($db, $alertId, 'detected', json_encode($context, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Resolve (desativa) alerta por tipo
     */
    private static function resolveAlert(PDO $db, string $alertType): bool
    {
        $stmt = $db->prepare("
            UPDATE system_alerts 
            SET is_active = 0, resolved_at = NOW()
            WHERE alert_type = ? AND is_active = 1
        ");
        $stmt->execute([$alertType]);

        if ($stmt->rowCount() > 0) {
            // Log de resolução
            $stmt2 = $db->prepare("SELECT id FROM system_alerts WHERE alert_type = ? AND resolved_at IS NOT NULL ORDER BY resolved_at DESC LIMIT 1");
            $stmt2->execute([$alertType]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                self::logAlertEventStatic($db, $row['id'], 'resolved', json_encode(['auto_resolved' => true]));
            }
            return true;
        }
        return false;
    }

    /**
     * Resolve alerta por tipo + sessão
     */
    private static function resolveAlertBySession(PDO $db, string $alertType, string $sessionId): bool
    {
        $stmt = $db->prepare("
            UPDATE system_alerts 
            SET is_active = 0, resolved_at = NOW()
            WHERE alert_type = ? AND session_id = ? AND is_active = 1
        ");
        $stmt->execute([$alertType, $sessionId]);

        if ($stmt->rowCount() > 0) {
            $stmt2 = $db->prepare("SELECT id FROM system_alerts WHERE alert_type = ? AND session_id = ? AND resolved_at IS NOT NULL ORDER BY resolved_at DESC LIMIT 1");
            $stmt2->execute([$alertType, $sessionId]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                self::logAlertEventStatic($db, $row['id'], 'resolved', json_encode(['auto_resolved' => true, 'session_id' => $sessionId]));
            }
            return true;
        }
        return false;
    }

    /**
     * Retorna lista de sessões esperadas (que já tiveram atividade)
     */
    private static function getExpectedSessions(PDO $db): array
    {
        try {
            // Sessões que tiveram atividade nos últimos 30 dias
            $stmt = $db->query("
                SELECT DISTINCT 
                    LOWER(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.session')), ' ', '')) as session_id
                FROM webhook_raw_logs
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND event_type IN ('message', 'onmessage', 'onselfmessage')
                HAVING session_id IS NOT NULL AND session_id != ''
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function logAlertEvent(PDO $db, int $alertId, string $eventType, ?string $details): void
    {
        self::logAlertEventStatic($db, $alertId, $eventType, $details);
    }

    private static function logAlertEventStatic(PDO $db, ?int $alertId, string $eventType, ?string $details): void
    {
        try {
            $stmt = $db->prepare("INSERT INTO system_alert_log (alert_id, event_type, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$alertId, $eventType, $details]);
        } catch (\Throwable $e) {
            error_log('[SystemAlert] Erro ao gravar log: ' . $e->getMessage());
        }
    }
}
