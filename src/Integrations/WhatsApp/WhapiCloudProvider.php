<?php

namespace PixelHub\Integrations\WhatsApp;

use PixelHub\Core\CryptoHelper;

/**
 * Provider Whapi.Cloud (WhatsApp API não-oficial gerenciada)
 * 
 * Substitui o WPPConnect Gateway com API mais estável e gerenciada.
 * Documentação: https://whapi.cloud/docs
 * 
 * Base URL: https://gate.whapi.cloud
 * Auth: Bearer token
 * 
 * Vantagens sobre WPPConnect:
 * - Não precisa de VPS/Docker
 * - Suporta base64 E URL para mídia
 * - Auto-converte áudio para OGG/Opus (voice messages)
 * - Uptime gerenciado pelo serviço
 */
class WhapiCloudProvider implements WhatsAppProviderInterface
{
    private string $apiToken;
    private string $baseUrl = 'https://gate.whapi.cloud';
    private array $config;
    private int $timeout = 60;

    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Descriptografa token se estiver criptografado
        $apiToken = $config['whapi_api_token'] ?? '';
        if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
            $apiToken = CryptoHelper::decrypt(substr($apiToken, 10));
        }
        
        $this->apiToken = $apiToken;
        
        // Permite override da base URL (para testes)
        if (!empty($config['whapi_base_url'])) {
            $this->baseUrl = rtrim($config['whapi_base_url'], '/');
        }
        
        // Permite override do timeout
        if (!empty($config['whapi_timeout'])) {
            $this->timeout = (int) $config['whapi_timeout'];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function sendText(string $to, string $text, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Whapi.Cloud inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'whapi'
            ];
        }

        $to = $this->normalizePhone($to);

        $payload = [
            'to' => $to,
            'body' => $text
        ];

        $result = $this->sendRequest('POST', '/messages/text', $payload);
        $result['provider'] = 'whapi';
        $result['provider_type'] = 'whapi';
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Whapi.Cloud inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'whapi'
            ];
        }

        $to = $this->normalizePhone($to);

        $payload = [
            'to' => $to,
            'media' => $imageUrl // Aceita base64 ou URL
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        $result = $this->sendRequest('POST', '/messages/image', $payload);
        $result['provider'] = 'whapi';
        $result['provider_type'] = 'whapi';
        return $result;
    }

    /**
     * {@inheritDoc}
     * 
     * Whapi.Cloud auto-converte para OGG/Opus no endpoint /messages/voice
     * Aceita base64 diretamente (sem necessidade de pré-conversão)
     */
    public function sendAudio(string $to, string $audioUrl, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Whapi.Cloud inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'whapi'
            ];
        }

        $to = $this->normalizePhone($to);

        // Usa /messages/voice para PTT (voice note) — auto-converte para OGG/Opus
        $payload = [
            'to' => $to,
            'media' => $audioUrl // base64 ou URL
        ];

        $result = $this->sendRequest('POST', '/messages/voice', $payload);
        $result['provider'] = 'whapi';
        $result['provider_type'] = 'whapi';
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Whapi.Cloud inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'whapi'
            ];
        }

        $to = $this->normalizePhone($to);

        $payload = [
            'to' => $to,
            'media' => $documentUrl, // base64 ou URL
            'filename' => $filename
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        $result = $this->sendRequest('POST', '/messages/document', $payload);
        $result['provider'] = 'whapi';
        $result['provider_type'] = 'whapi';
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sendVideo(string $to, string $videoUrl, ?string $caption = null, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Whapi.Cloud inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'whapi'
            ];
        }

        $to = $this->normalizePhone($to);

        $payload = [
            'to' => $to,
            'media' => $videoUrl, // base64 ou URL
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        $result = $this->sendRequest('POST', '/messages/video', $payload);
        $result['provider'] = 'whapi';
        $result['provider_type'] = 'whapi';
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderInfo(): array
    {
        return [
            'provider_type' => 'whapi',
            'provider_name' => 'Whapi.Cloud',
            'status' => 'active',
            'info' => [
                'description' => 'Whapi.Cloud - WhatsApp API gerenciada (substitui WPPConnect)',
                'supports_base64' => true,
                'supports_url' => true,
                'auto_converts_audio' => true,
                'webhook_endpoint' => '/api/whatsapp/whapi/webhook'
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): array
    {
        $errors = [];
        
        if (empty($this->apiToken)) {
            $errors[] = 'API Token não configurado';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'provider_type' => 'whapi'
        ];
    }

    /**
     * Normaliza número de telefone para formato Whapi.Cloud
     * Whapi espera apenas números sem +, ex: 5547999999999
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Executa requisição HTTP para a API Whapi.Cloud
     * 
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint API endpoint (ex: /messages/text)
     * @param array|null $payload Request body
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    private function sendRequest(string $method, string $endpoint, ?array $payload = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Log da requisição (sem dados sensíveis)
        $payloadForLog = $payload;
        if ($payloadForLog && isset($payloadForLog['media']) && strlen($payloadForLog['media']) > 100) {
            $payloadForLog['media'] = substr($payloadForLog['media'], 0, 50) . '... (len=' . strlen($payload['media']) . ')';
        }
        error_log("[WhapiCloudProvider] {$method} {$endpoint} payload=" . json_encode($payloadForLog, JSON_UNESCAPED_UNICODE));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Erro de rede
        if ($curlErrno) {
            error_log("[WhapiCloudProvider] ❌ cURL error #{$curlErrno}: {$curlError}");
            return [
                'success' => false,
                'error' => "Erro de conexão com Whapi.Cloud: {$curlError}",
                'error_code' => 'WHAPI_CURL_ERROR',
                'status' => 0
            ];
        }

        // Parse response
        $responseData = json_decode($responseBody, true);
        
        error_log("[WhapiCloudProvider] Response HTTP {$httpCode}: " . substr($responseBody, 0, 500));

        // Sucesso (2xx)
        if ($httpCode >= 200 && $httpCode < 300) {
            $messageId = null;
            
            // Extrai message_id da resposta
            if (isset($responseData['sent']) && $responseData['sent'] === true) {
                $messageId = $responseData['message']['id'] ?? null;
            } elseif (isset($responseData['message']['id'])) {
                $messageId = $responseData['message']['id'];
            } elseif (isset($responseData['id'])) {
                $messageId = $responseData['id'];
            }

            return [
                'success' => true,
                'message_id' => $messageId,
                'raw' => $responseData,
                'status' => $httpCode
            ];
        }

        // Erro da API — Whapi retorna {"error": {"code": int, "message": str, "details": str}}
        $errObj = $responseData['error'] ?? null;
        if (is_array($errObj)) {
            $errorMessage = $errObj['message'] ?? ($errObj['details'] ?? json_encode($errObj));
        } else {
            $errorMessage = $responseData['message'] ?? ($errObj ?? "HTTP {$httpCode}");
        }
        error_log("[WhapiCloudProvider] ❌ API error: {$errorMessage} | full: " . substr($responseBody, 0, 300));

        return [
            'success' => false,
            'error' => "Whapi.Cloud: {$errorMessage}",
            'error_code' => 'WHAPI_API_ERROR',
            'status' => $httpCode,
            'raw' => $responseData
        ];
    }
}
