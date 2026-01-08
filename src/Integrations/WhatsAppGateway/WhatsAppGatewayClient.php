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

        $ch = curl_init($url);
        
        $headers = [
            'X-Gateway-Secret: ' . $this->secret,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

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
                'raw' => null
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[WhatsAppGateway] JSON Error: " . json_last_error_msg());
            return [
                'success' => false,
                'error' => 'Resposta inválida do gateway',
                'raw' => $response
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

