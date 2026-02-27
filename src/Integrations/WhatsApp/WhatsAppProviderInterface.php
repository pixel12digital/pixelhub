<?php

namespace PixelHub\Integrations\WhatsApp;

/**
 * Interface para providers de WhatsApp
 * 
 * Define contrato comum para diferentes implementações:
 * - WPPConnect (gateway próprio)
 * - Meta Official API (API oficial do WhatsApp Business)
 */
interface WhatsAppProviderInterface
{
    /**
     * Envia mensagem de texto
     * 
     * @param string $to Número do destinatário (formato E.164: 5511999999999)
     * @param string $text Texto da mensagem
     * @param array|null $metadata Metadados adicionais (sent_by, etc.)
     * @return array { success: bool, message_id?: string, error?: string }
     */
    public function sendText(string $to, string $text, ?array $metadata = null): array;

    /**
     * Envia imagem
     * 
     * @param string $to Número do destinatário
     * @param string $imageUrl URL pública da imagem OU base64
     * @param string|null $caption Legenda da imagem (opcional)
     * @param array|null $metadata Metadados adicionais
     * @return array { success: bool, message_id?: string, error?: string }
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null, ?array $metadata = null): array;

    /**
     * Envia áudio (voice note)
     * 
     * @param string $to Número do destinatário
     * @param string $audioUrl URL pública do áudio OU base64
     * @param array|null $metadata Metadados adicionais
     * @return array { success: bool, message_id?: string, error?: string }
     */
    public function sendAudio(string $to, string $audioUrl, ?array $metadata = null): array;

    /**
     * Envia documento/arquivo
     * 
     * @param string $to Número do destinatário
     * @param string $documentUrl URL pública do documento OU base64
     * @param string $filename Nome do arquivo
     * @param string|null $caption Legenda do documento (opcional)
     * @param array|null $metadata Metadados adicionais
     * @return array { success: bool, message_id?: string, error?: string }
     */
    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null, ?array $metadata = null): array;

    /**
     * Envia vídeo
     * 
     * @param string $to Número do destinatário
     * @param string $videoUrl URL pública do vídeo OU base64
     * @param string|null $caption Legenda do vídeo (opcional)
     * @param array|null $metadata Metadados adicionais
     * @return array { success: bool, message_id?: string, error?: string }
     */
    public function sendVideo(string $to, string $videoUrl, ?string $caption = null, ?array $metadata = null): array;

    /**
     * Obtém informações do provider (status, configuração, etc.)
     * 
     * @return array { provider_type: string, status: string, info?: array }
     */
    public function getProviderInfo(): array;

    /**
     * Valida se o provider está configurado corretamente
     * 
     * @return array { valid: bool, errors?: array }
     */
    public function validateConfiguration(): array;
}
