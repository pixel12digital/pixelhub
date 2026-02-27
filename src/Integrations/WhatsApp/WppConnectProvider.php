<?php

namespace PixelHub\Integrations\WhatsApp;

use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

/**
 * Provider WPPConnect (gateway próprio)
 * 
 * Wrapper do WhatsAppGatewayClient existente para manter 100% de compatibilidade
 * GARANTE: Zero breaking changes - todo código existente continua funcionando
 */
class WppConnectProvider implements WhatsAppProviderInterface
{
    private WhatsAppGatewayClient $client;
    private ?string $channelId;
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
        $this->channelId = $config['channel_id'] ?? null;
        
        // Usa o client existente - ZERO mudanças no comportamento atual
        $this->client = new WhatsAppGatewayClient();
    }

    /**
     * {@inheritDoc}
     */
    public function sendText(string $to, string $text, ?array $metadata = null): array
    {
        // Resolve channel_id (prioridade: metadata > config > null)
        $channelId = $metadata['channel_id'] ?? $this->channelId;
        
        if (empty($channelId)) {
            return [
                'success' => false,
                'error' => 'channel_id não fornecido para WPPConnect',
                'provider' => 'wppconnect'
            ];
        }

        // Chama método existente - mantém 100% compatibilidade
        $response = $this->client->sendText($channelId, $to, $text, $metadata);
        
        // Adiciona identificação do provider na resposta
        $response['provider'] = 'wppconnect';
        $response['provider_type'] = 'wppconnect';
        
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null, ?array $metadata = null): array
    {
        $channelId = $metadata['channel_id'] ?? $this->channelId;
        
        if (empty($channelId)) {
            return [
                'success' => false,
                'error' => 'channel_id não fornecido para WPPConnect',
                'provider' => 'wppconnect'
            ];
        }

        // Detecta se é base64 ou URL
        $isBase64 = strpos($imageUrl, 'data:') === 0 || !filter_var($imageUrl, FILTER_VALIDATE_URL);
        
        if ($isBase64) {
            $response = $this->client->sendImage($channelId, $to, $imageUrl, null, $caption, $metadata);
        } else {
            $response = $this->client->sendImage($channelId, $to, null, $imageUrl, $caption, $metadata);
        }
        
        $response['provider'] = 'wppconnect';
        $response['provider_type'] = 'wppconnect';
        
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function sendAudio(string $to, string $audioUrl, ?array $metadata = null): array
    {
        $channelId = $metadata['channel_id'] ?? $this->channelId;
        
        if (empty($channelId)) {
            return [
                'success' => false,
                'error' => 'channel_id não fornecido para WPPConnect',
                'provider' => 'wppconnect'
            ];
        }

        // WPPConnect usa sendAudioBase64Ptt
        $isBase64 = strpos($audioUrl, 'data:') === 0 || !filter_var($audioUrl, FILTER_VALIDATE_URL);
        
        if (!$isBase64) {
            return [
                'success' => false,
                'error' => 'WPPConnect requer áudio em base64 (não suporta URL direta)',
                'provider' => 'wppconnect'
            ];
        }

        $response = $this->client->sendAudioBase64Ptt($channelId, $to, $audioUrl, $metadata);
        
        $response['provider'] = 'wppconnect';
        $response['provider_type'] = 'wppconnect';
        
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null, ?array $metadata = null): array
    {
        $channelId = $metadata['channel_id'] ?? $this->channelId;
        
        if (empty($channelId)) {
            return [
                'success' => false,
                'error' => 'channel_id não fornecido para WPPConnect',
                'provider' => 'wppconnect'
            ];
        }

        $isBase64 = strpos($documentUrl, 'data:') === 0 || !filter_var($documentUrl, FILTER_VALIDATE_URL);
        
        if ($isBase64) {
            $response = $this->client->sendDocument($channelId, $to, $documentUrl, null, $filename, $caption, $metadata);
        } else {
            $response = $this->client->sendDocument($channelId, $to, null, $documentUrl, $filename, $caption, $metadata);
        }
        
        $response['provider'] = 'wppconnect';
        $response['provider_type'] = 'wppconnect';
        
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function sendVideo(string $to, string $videoUrl, ?string $caption = null, ?array $metadata = null): array
    {
        $channelId = $metadata['channel_id'] ?? $this->channelId;
        
        if (empty($channelId)) {
            return [
                'success' => false,
                'error' => 'channel_id não fornecido para WPPConnect',
                'provider' => 'wppconnect'
            ];
        }

        $isBase64 = strpos($videoUrl, 'data:') === 0 || !filter_var($videoUrl, FILTER_VALIDATE_URL);
        
        if ($isBase64) {
            $response = $this->client->sendVideo($channelId, $to, $videoUrl, null, $caption, $metadata);
        } else {
            $response = $this->client->sendVideo($channelId, $to, null, $videoUrl, $caption, $metadata);
        }
        
        $response['provider'] = 'wppconnect';
        $response['provider_type'] = 'wppconnect';
        
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderInfo(): array
    {
        return [
            'provider_type' => 'wppconnect',
            'provider_name' => 'WPPConnect Gateway',
            'status' => 'active',
            'channel_id' => $this->channelId,
            'info' => [
                'description' => 'Gateway WPPConnect próprio (VPS Hostinger)',
                'supports_base64' => true,
                'supports_url' => true,
                'webhook_endpoint' => '/api/whatsapp/webhook'
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): array
    {
        $errors = [];
        
        // Valida se tem channel_id configurado
        if (empty($this->channelId) && empty($this->config['channel_id'])) {
            $errors[] = 'channel_id não configurado';
        }
        
        // Tenta validar conexão com o gateway
        try {
            $channels = $this->client->listChannels();
            if (!($channels['success'] ?? false)) {
                $errors[] = 'Falha ao conectar com gateway WPPConnect: ' . ($channels['error'] ?? 'Erro desconhecido');
            }
        } catch (\Exception $e) {
            $errors[] = 'Exceção ao validar gateway: ' . $e->getMessage();
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'provider_type' => 'wppconnect'
        ];
    }

    /**
     * Obtém o client WPPConnect subjacente (para compatibilidade com código legado)
     * 
     * @return WhatsAppGatewayClient
     */
    public function getUnderlyingClient(): WhatsAppGatewayClient
    {
        return $this->client;
    }
}
