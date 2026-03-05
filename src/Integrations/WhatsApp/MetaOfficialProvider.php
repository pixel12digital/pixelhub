<?php

namespace PixelHub\Integrations\WhatsApp;

use PixelHub\Core\CryptoHelper;

/**
 * Provider Meta Official API (WhatsApp Business API)
 * 
 * Implementa integração com a API oficial do Meta/Facebook para WhatsApp Business
 * Documentação: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaOfficialProvider implements WhatsAppProviderInterface
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $businessAccountId;
    private string $apiVersion = 'v21.0';
    private string $baseUrl = 'https://graph.facebook.com';
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Descriptografa access token se estiver criptografado
        $accessToken = $config['meta_access_token'] ?? '';
        if (!empty($accessToken) && strpos($accessToken, 'encrypted:') === 0) {
            $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
        }
        
        $this->phoneNumberId = $config['meta_phone_number_id'] ?? '';
        $this->accessToken = $accessToken;
        $this->businessAccountId = $config['meta_business_account_id'] ?? '';
        
        // Permite override da versão da API
        if (!empty($config['meta_api_version'])) {
            $this->apiVersion = $config['meta_api_version'];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function sendText(string $to, string $text, ?array $metadata = null): array
    {
        // Valida configuração
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        // Normaliza número (remove caracteres não numéricos)
        $to = preg_replace('/[^0-9]/', '', $to);

        // Monta payload da API Meta
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => true, // Habilita preview de links
                'body' => $text
            ]
        ];

        // Envia via API Meta
        return $this->sendRequest('/messages', $payload, $metadata);
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
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        // Meta API requer URL pública (não suporta base64 diretamente)
        if (strpos($imageUrl, 'data:') === 0) {
            return [
                'success' => false,
                'error' => 'Meta Official API requer URL pública para imagens (não suporta base64 diretamente)',
                'provider' => 'meta_official',
                'hint' => 'Faça upload da imagem para um servidor e forneça a URL pública'
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl
            ]
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        return $this->sendRequest('/messages', $payload, $metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function sendAudio(string $to, string $audioUrl, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        // Meta API requer URL pública
        if (strpos($audioUrl, 'data:') === 0) {
            return [
                'success' => false,
                'error' => 'Meta Official API requer URL pública para áudios (não suporta base64 diretamente)',
                'provider' => 'meta_official',
                'hint' => 'Faça upload do áudio para um servidor e forneça a URL pública'
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'audio',
            'audio' => [
                'link' => $audioUrl
            ]
        ];

        return $this->sendRequest('/messages', $payload, $metadata);
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
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        if (strpos($documentUrl, 'data:') === 0) {
            return [
                'success' => false,
                'error' => 'Meta Official API requer URL pública para documentos (não suporta base64 diretamente)',
                'provider' => 'meta_official',
                'hint' => 'Faça upload do documento para um servidor e forneça a URL pública'
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename
            ]
        ];

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        return $this->sendRequest('/messages', $payload, $metadata);
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
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        if (strpos($videoUrl, 'data:') === 0) {
            return [
                'success' => false,
                'error' => 'Meta Official API requer URL pública para vídeos (não suporta base64 diretamente)',
                'provider' => 'meta_official',
                'hint' => 'Faça upload do vídeo para um servidor e forneça a URL pública'
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'video',
            'video' => [
                'link' => $videoUrl
            ]
        ];

        if ($caption) {
            $payload['video']['caption'] = $caption;
        }

        return $this->sendRequest('/messages', $payload, $metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderInfo(): array
    {
        return [
            'provider_type' => 'meta_official',
            'provider_name' => 'Meta Official API',
            'status' => 'active',
            'phone_number_id' => $this->phoneNumberId,
            'business_account_id' => $this->businessAccountId,
            'api_version' => $this->apiVersion,
            'info' => [
                'description' => 'WhatsApp Business API oficial do Meta/Facebook',
                'supports_base64' => false,
                'supports_url' => true,
                'webhook_endpoint' => '/api/whatsapp/meta/webhook',
                'requires_public_urls' => true
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->phoneNumberId)) {
            $errors[] = 'Phone Number ID não configurado';
        }

        if (empty($this->accessToken)) {
            $errors[] = 'Access Token não configurado';
        }

        if (empty($this->businessAccountId)) {
            $errors[] = 'Business Account ID não configurado';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'provider_type' => 'meta_official'
        ];
    }

    /**
     * Envia requisição para a API Meta
     * 
     * @param string $endpoint Endpoint da API (ex: /messages)
     * @param array $payload Payload da requisição
     * @param array|null $metadata Metadados adicionais
     * @return array Resposta normalizada
     */
    private function sendRequest(string $endpoint, array $payload, ?array $metadata = null): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}{$endpoint}";

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Log da requisição
        error_log('[MetaOfficialProvider] Enviando mensagem: ' . json_encode([
            'url' => $url,
            'to' => $payload['to'] ?? 'N/A',
            'type' => $payload['type'] ?? 'N/A'
        ], JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[MetaOfficialProvider] Erro cURL: ' . $curlError);
            return [
                'success' => false,
                'error' => 'Erro de conexão com Meta API: ' . $curlError,
                'provider' => 'meta_official',
                'http_code' => $httpCode
            ];
        }

        $responseData = json_decode($response, true);

        // Log da resposta
        error_log('[MetaOfficialProvider] Resposta Meta API: ' . substr($response, 0, 500));

        // Verifica se houve erro
        if ($httpCode >= 400 || isset($responseData['error'])) {
            $errorMsg = $responseData['error']['message'] ?? 'Erro desconhecido da Meta API';
            $errorCode = $responseData['error']['code'] ?? $httpCode;
            
            error_log('[MetaOfficialProvider] Erro Meta API: ' . $errorMsg . ' (code: ' . $errorCode . ')');
            
            return [
                'success' => false,
                'error' => $errorMsg,
                'error_code' => $errorCode,
                'provider' => 'meta_official',
                'http_code' => $httpCode,
                'raw' => $responseData
            ];
        }

        // Sucesso - extrai message_id
        $messageId = $responseData['messages'][0]['id'] ?? null;
        
        return [
            'success' => true,
            'message_id' => $messageId,
            'provider' => 'meta_official',
            'provider_type' => 'meta_official',
            'http_code' => $httpCode,
            'raw' => $responseData,
            'metadata' => $metadata
        ];
    }

    /**
     * Envia template aprovado do Meta
     * 
     * @param string $to Número do destinatário
     * @param string $templateName Nome do template aprovado
     * @param string $languageCode Código do idioma (ex: pt_BR, en_US)
     * @param array $components Componentes do template (header, body, buttons)
     * @return array Resposta normalizada
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode, array $components = []): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $this->sendRequest('/messages', $payload, null);
    }
    
    /**
     * Envia mensagem interativa com botões (reply buttons)
     * 
     * @param string $to Número de destino (E.164)
     * @param string $text Texto da mensagem
     * @param array $buttons Array de botões [['id' => 'btn_1', 'title' => 'Botão 1'], ...]
     * @param array|null $metadata Metadados opcionais
     * @return array Resposta normalizada
     */
    public function sendInteractiveButtons(string $to, string $text, array $buttons, ?array $metadata = null): array
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuração Meta inválida: ' . implode(', ', $validation['errors']),
                'provider' => 'meta_official'
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $to);
        
        // Limita a 3 botões (limite do Meta API)
        $buttons = array_slice($buttons, 0, 3);
        
        // Formata botões para o formato Meta
        $formattedButtons = [];
        foreach ($buttons as $button) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id'    => mb_substr($button['id'] ?? md5($button['title']), 0, 256, 'UTF-8'),
                    'title' => mb_substr($button['title'], 0, 20, 'UTF-8') // Limite de 20 caracteres
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $text
                ],
                'action' => [
                    'buttons' => $formattedButtons
                ]
            ]
        ];

        return $this->sendRequest('/messages', $payload, $metadata);
    }
}
