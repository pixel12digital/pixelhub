<?php

namespace PixelHub\Integrations\WhatsAppGateway;

use PixelHub\Core\Env;
use PixelHub\Services\GatewaySecret;

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
        // Usa GatewaySecret::getDecrypted() como fonte única do secret
        $this->secret = $secret ?? GatewaySecret::getDecrypted();
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
     * Envia áudio PTT (voice note) via base64Ptt (OGG/Opus)
     *
     * @param string $channelId ID do canal (ex: pixel12digital)
     * @param string $to Número do destinatário (formato: 5547...)
     * @param string $base64Ptt Base64 do arquivo OGG/Opus (PTT). Pode vir com prefixo data:audio/...;base64,
     * @param array|null $metadata Metadados adicionais (opcional)
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    public function sendAudioBase64Ptt(string $channelId, string $to, string $base64Ptt, ?array $metadata = null): array
    {
        // aceita data-uri e base64 puro
        $b64 = (string) $base64Ptt;
        $pos = stripos($b64, 'base64,');
        if ($pos !== false) {
            $b64 = substr($b64, $pos + 7);
        }

        // Valida tamanho do base64 (limite WhatsApp: 16MB)
        // Base64 é ~33% maior que o binário, então 16MB * 1.33 ≈ 21MB em base64
        $b64Length = strlen($b64);
        $estimatedBinarySize = ($b64Length * 3) / 4; // Aproximação do tamanho binário
        $maxSizeBytes = 16 * 1024 * 1024; // 16MB
        
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Tamanho do áudio: base64={$b64Length} bytes, estimado binário=" . round($estimatedBinarySize / 1024, 2) . " KB");
        
        if ($estimatedBinarySize > $maxSizeBytes) {
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] ❌ Áudio muito grande: " . round($estimatedBinarySize / 1024 / 1024, 2) . " MB (limite: 16 MB)");
            return [
                'success' => false,
                'error' => 'Áudio muito grande. Tamanho máximo permitido: 16 MB. Tamanho atual: ' . round($estimatedBinarySize / 1024 / 1024, 2) . ' MB',
                'error_code' => 'AUDIO_TOO_LARGE',
                'status' => 400
            ];
        }

        $payload = [
            'channel' => $channelId,
            'to' => $to,
            'type' => 'audio',
            'base64Ptt' => $b64
        ];

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        // Log do payload (sem o base64 completo para não poluir logs)
        $payloadForLog = $payload;
        $payloadForLog['base64Ptt'] = substr($b64, 0, 50) . '... (len=' . strlen($b64) . ')';
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Enviando áudio: channel={$channelId}, to={$to}, base64_len=" . strlen($b64));

        // Aumenta timeout para requisições de áudio (podem ser grandes)
        $originalTimeout = $this->timeout;
        $this->timeout = 60; // 60 segundos para áudio
        
        try {
            $response = $this->request('POST', '/api/messages', $payload);
        } finally {
            // Restaura timeout original
            $this->timeout = $originalTimeout;
        }

        // Log detalhado da resposta
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Resposta do gateway: success=" . ($response['success'] ? 'true' : 'false') . ", status=" . ($response['status'] ?? 'N/A') . ", error=" . ($response['error'] ?? 'N/A'));

        // Log completo da resposta raw para diagnóstico
        if (isset($response['raw'])) {
            $rawForLog = $response['raw'];
            // Remove base64Ptt do log se existir (muito grande)
            if (isset($rawForLog['base64Ptt'])) {
                $rawForLog['base64Ptt'] = '[REMOVED - too large]';
            }
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] Resposta raw completa: " . json_encode($rawForLog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // Normaliza resposta
        if (($response['success'] ?? false) && isset($response['raw'])) {
            $raw = $response['raw'];
            $response['message_id'] = $raw['id'] ?? $raw['messageId'] ?? $raw['message_id'] ?? null;

            // Extrai correlationId (referência principal para rastreamento assíncrono)
            $response['correlationId'] =
                $raw['correlationId']
                ?? $raw['correlation_id']
                ?? $raw['trace_id']
                ?? $raw['traceId']
                ?? $raw['request_id']
                ?? $raw['requestId']
                ?? null;
        }

        // Melhora mensagem de erro se vier do gateway
        if (!$response['success']) {
            $errorMsg = $response['error'] ?? 'Erro desconhecido';
            $rawResponse = $response['raw'] ?? [];
            
            // Extrai error_code do raw se existir
            if (isset($rawResponse['error_code']) && !isset($response['error_code'])) {
                $response['error_code'] = $rawResponse['error_code'];
            }
            
            // Detecta erros específicos do WPPConnect
            if (stripos($errorMsg, 'sendVoiceBase64') !== false || stripos($errorMsg, 'WPPConnect') !== false) {
                // Extrai mensagem mais específica se possível
                if (stripos($errorMsg, 'Erro ao enviar a mensagem') !== false) {
                    $response['error'] = 'Falha ao enviar áudio via WPPConnect. Verifique se a sessão está conectada e se o formato do áudio está correto (OGG/Opus).';
                    $response['error_code'] = $response['error_code'] ?? 'WPPCONNECT_SEND_ERROR';
                }
            }
            
            // Log erro detalhado com toda a resposta
            $errorLogData = [
                'error' => $errorMsg,
                'error_code' => $response['error_code'] ?? 'N/A',
                'status' => $response['status'] ?? 'N/A',
                'raw_response' => $rawResponse
            ];
            
            // Remove dados sensíveis do log
            if (isset($errorLogData['raw_response']['base64Ptt'])) {
                $errorLogData['raw_response']['base64Ptt'] = '[REMOVED - too large]';
            }
            
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] ❌ Erro detalhado: " . json_encode($errorLogData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // Também loga no pixelhub_log se disponível
            if (function_exists('pixelhub_log')) {
                pixelhub_log("[WhatsAppGateway::sendAudioBase64Ptt] Erro ao enviar áudio: " . $errorMsg . " (code: " . ($response['error_code'] ?? 'N/A') . ", status: " . ($response['status'] ?? 'N/A') . ")");
            }
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
     * Baixa mídia do WhatsApp Gateway (WPP Connect)
     * 
     * @param string $channelId ID do canal
     * @param string $mediaId ID da mídia ou URL da mídia (vem no payload da mensagem)
     * @return array { success: bool, data?: string (binary), mime_type?: string, error?: string }
     */
    public function downloadMedia(string $channelId, string $mediaId): array
    {
        // WPP Connect: Se mediaId é uma URL completa, usa diretamente
        // Caso contrário, usa o endpoint do gateway
        if (filter_var($mediaId, FILTER_VALIDATE_URL)) {
            $url = $mediaId;
        } else {
            // Tenta endpoint padrão do gateway
            $url = $this->baseUrl . "/api/channels/{$channelId}/media/{$mediaId}";
        }
        
        $ch = curl_init($url);
        
        $headers = [
            'X-Gateway-Secret: ' . $this->secret,
            'Accept: */*'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60, // Timeout maior para download de mídias
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_BINARYTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[WhatsAppGateway::downloadMedia] cURL Error: {$error}");
            return [
                'success' => false,
                'error' => "Erro de conexão: {$error}",
                'data' => null,
                'mime_type' => null
            ];
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("[WhatsAppGateway::downloadMedia] HTTP Error: {$httpCode}");
            return [
                'success' => false,
                'error' => "HTTP {$httpCode}",
                'data' => null,
                'mime_type' => null
            ];
        }
        
        return [
            'success' => true,
            'data' => $response,
            'mime_type' => $contentType ?: 'application/octet-stream',
            'error' => null
        ];
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

