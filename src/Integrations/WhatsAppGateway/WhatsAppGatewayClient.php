<?php

namespace PixelHub\Integrations\WhatsAppGateway;

use PixelHub\Core\Env;

/**
 * Cliente HTTP para comunicação com o WPP Gateway
 * 
 * Base URL: https://wpp.pixel12digital.com.br
 * Autenticação: Header X-Gateway-Secret
 */
class WhatsAppGatewayClient
{
    private string $baseUrl;
    private string $secret;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $secret = null, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl ?? Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br'), '/');
        $this->secret = $secret ?? Env::get('WPP_GATEWAY_SECRET', '');
        $this->timeout = $timeout;

        if (empty($this->secret)) {
            throw new \RuntimeException('WPP_GATEWAY_SECRET não configurado');
        }
    }

    /**
     * Lista todos os canais (sessions)
     * 
     * @return array { success: bool, channels: array, error?: string }
     */
    public function listChannels(): array
    {
        return $this->request('GET', '/api/channels');
    }

    /**
     * Cria um novo canal (session)
     * 
     * @param string $channelId ID único do canal
     * @return array { success: bool, channel: array, error?: string }
     */
    public function createChannel(string $channelId): array
    {
        return $this->request('POST', '/api/channels', [
            'channel' => $channelId
        ]);
    }

    /**
     * Obtém dados de um canal específico
     * 
     * @param string $channelId ID do canal
     * @return array { success: bool, channel: array, error?: string }
     */
    public function getChannel(string $channelId): array
    {
        return $this->request('GET', "/api/channels/{$channelId}");
    }

    /**
     * Obtém QR code para conectar o WhatsApp
     * 
     * @param string $channelId ID do canal
     * @return array { success: bool, qr: string, error?: string }
     */
    public function getQr(string $channelId): array
    {
        return $this->request('GET', "/api/channels/{$channelId}/qr");
    }

    /**
     * Envia mensagem de texto
     * 
     * @param string $channelId ID do canal
     * @param string $to Número do destinatário (formato: 5511999999999)
     * @param string $text Texto da mensagem
     * @param array|null $metadata Metadados adicionais (opcional)
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    public function sendText(string $channelId, string $to, string $text, ?array $metadata = null): array
    {
        $payload = [
            'channel' => $channelId,
            'to' => $to,
            'text' => $text
        ];

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->request('POST', '/api/messages', $payload);

        // Normaliza resposta
        if ($response['success'] && isset($response['raw'])) {
            $raw = $response['raw'];
            $response['message_id'] = $raw['id'] ?? $raw['messageId'] ?? $raw['message_id'] ?? null;
            
            // Extrai correlationId (referência principal para rastreamento assíncrono)
            $response['correlationId'] = $raw['correlationId'] 
                ?? $raw['correlation_id'] 
                ?? $raw['trace_id'] 
                ?? $raw['traceId']
                ?? $raw['request_id']
                ?? $raw['requestId']
                ?? null;
        }

        return $response;
    }

    /**
     * Configura webhook para um canal específico
     * 
     * @param string $channelId ID do canal
     * @param string $url URL do webhook
     * @param string|null $secret Secret para validar webhook (opcional)
     * @return array { success: bool, error?: string }
     */
    public function setChannelWebhook(string $channelId, string $url, ?string $secret = null): array
    {
        $payload = ['url' => $url];
        if ($secret !== null) {
            $payload['secret'] = $secret;
        }

        return $this->request('POST', "/api/channels/{$channelId}/webhook", $payload);
    }

    /**
     * Configura webhook global (para todos os canais)
     * 
     * @param string $url URL do webhook
     * @param string|null $secret Secret para validar webhook (opcional)
     * @return array { success: bool, error?: string }
     */
    public function setGlobalWebhook(string $url, ?string $secret = null): array
    {
        $payload = ['url' => $url];
        if ($secret !== null) {
            $payload['secret'] = $secret;
        }

        return $this->request('POST', '/api/webhooks', $payload);
    }

    /**
     * Faz requisição HTTP para o gateway
     * 
     * @param string $method Método HTTP
     * @param string $endpoint Endpoint (sem base URL)
     * @param array|null $data Dados para enviar (JSON)
     * @return array Resposta normalizada
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // LOG TEMPORÁRIO: URL e header (sem expor secret completo)
        $secretPreview = !empty($this->secret) ? (substr($this->secret, 0, 4) . '...' . substr($this->secret, -4) . ' (len=' . strlen($this->secret) . ')') : 'VAZIO';
        $headerSet = !empty($this->secret) ? 'SIM' : 'NÃO';
        error_log("[WhatsAppGateway::request] URL: {$url}");
        error_log("[WhatsAppGateway::request] Header X-Gateway-Secret configurado: {$headerSet} - Preview: {$secretPreview}");

        $ch = curl_init($url);
        
        $headers = [
            'X-Gateway-Secret: ' . $this->secret,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // LOG TEMPORÁRIO: headers montados (sem secret completo)
        $headersForLog = array_map(function($h) {
            if (strpos($h, 'X-Gateway-Secret:') === 0) {
                $secretValue = substr($h, 17); // Remove "X-Gateway-Secret: "
                $preview = !empty($secretValue) ? (substr($secretValue, 0, 4) . '...' . substr($secretValue, -4)) : 'VAZIO';
                return 'X-Gateway-Secret: ' . $preview . ' (len=' . strlen($secretValue) . ')';
            }
            return $h;
        }, $headers);
        error_log("[WhatsAppGateway::request] Headers: " . json_encode($headersForLog, JSON_UNESCAPED_UNICODE));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // LOG TEMPORÁRIO: status code e body bruto
        error_log("[WhatsAppGateway::request] Response HTTP Status: {$httpCode}");
        if ($error) {
            error_log("[WhatsAppGateway::request] cURL Error: {$error}");
        } else {
            $bodyPreview = strlen($response) > 500 ? (substr($response, 0, 500) . '... (truncated)') : $response;
            error_log("[WhatsAppGateway::request] Response body (primeiros 500 chars): " . $bodyPreview);
        }

        // Log (sem expor secret)
        if (function_exists('pixelhub_log')) {
            pixelhub_log(sprintf(
                '[WhatsAppGateway] %s %s - HTTP %d',
                $method,
                $endpoint,
                $httpCode
            ));
        }

        if ($error) {
            error_log("[WhatsAppGateway] cURL Error: {$error}");
            return [
                'success' => false,
                'error' => "Erro de conexão: {$error}",
                'raw' => null,
                'status' => 0
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[WhatsAppGateway] JSON Error: " . json_last_error_msg() . " - Response: " . substr($response, 0, 200));
            return [
                'success' => false,
                'error' => 'Resposta inválida do gateway: ' . json_last_error_msg(),
                'raw' => $response,
                'status' => $httpCode
            ];
        }

        // Normaliza resposta
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'status' => $httpCode,
            'raw' => $decoded,
            'error' => $success ? null : ($decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode}")
        ];
    }
}

