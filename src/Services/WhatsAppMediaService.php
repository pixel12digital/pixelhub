<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\Storage;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PDO;

/**
 * Service para processar e armazenar mídias recebidas do WhatsApp
 */
class WhatsAppMediaService
{
    /**
     * Processa mídia de um evento de comunicação
     * 
     * @param array $event Evento da tabela communication_events
     * @return array|null Dados da mídia processada ou null se não houver mídia
     */
    public static function processMediaFromEvent(array $event): ?array
    {
        // Verifica se o evento tem mídia
        $payload = json_decode($event['payload'] ?? '{}', true);
        if (!$payload) {
            return null;
        }
        
        // Extrai informações de mídia do payload (suporta múltiplos formatos)
        $mediaId = $payload['mediaId'] 
            ?? $payload['media_id'] 
            ?? $payload['message']['mediaId'] 
            ?? $payload['message']['media_id']
            ?? $payload['media']['id']
            ?? $payload['message']['media']['id']
            ?? null;
        
        if (!$mediaId) {
            // Não é uma mensagem com mídia
            return null;
        }
        
        // Verifica se a mídia já foi processada
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM communication_media 
            WHERE event_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$event['event_id']]);
        $existingMedia = $stmt->fetch();
        
        if ($existingMedia && !empty($existingMedia['stored_path'])) {
            // Mídia já processada
            return [
                'id' => $existingMedia['id'],
                'event_id' => $existingMedia['event_id'],
                'media_type' => $existingMedia['media_type'],
                'mime_type' => $existingMedia['mime_type'],
                'stored_path' => $existingMedia['stored_path'],
                'file_name' => $existingMedia['file_name'],
                'file_size' => $existingMedia['file_size'],
                'url' => self::getMediaUrl($existingMedia['stored_path'])
            ];
        }
        
        // Extrai tipo de mídia
        $mediaType = $payload['type'] 
            ?? $payload['message']['type'] 
            ?? 'unknown';
        
        // Extrai mimetype
        $mimeType = $payload['mimetype'] 
            ?? $payload['mimeType'] 
            ?? $payload['message']['mimetype'] 
            ?? $payload['message']['mimeType']
            ?? self::guessMimeType($mediaType);
        
        // Extrai channel_id para baixar mídia
        $metadata = json_decode($event['metadata'] ?? '{}', true);
        $channelId = $metadata['channel_id'] 
            ?? $payload['channel'] 
            ?? $payload['channelId']
            ?? null;
        
        if (!$channelId) {
            error_log("[WhatsAppMediaService] Channel ID não encontrado para evento {$event['event_id']}");
            // Salva registro sem arquivo para não reprocessar
            return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
        }
        
        // Baixa mídia do WhatsApp Gateway
        try {
            $client = new WhatsAppGatewayClient();
            $downloadResult = $client->downloadMedia($channelId, $mediaId);
            
            if (!$downloadResult['success'] || empty($downloadResult['data'])) {
                error_log("[WhatsAppMediaService] Falha ao baixar mídia: {$downloadResult['error']}");
                // Salva registro sem arquivo
                return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
            }
            
            // Determina extensão do arquivo
            $extension = self::getExtensionFromMimeType($downloadResult['mime_type'] ?: $mimeType);
            
            // Cria diretório para mídias
            $tenantId = $event['tenant_id'] ?? null;
            $subDir = date('Y/m/d');
            $mediaDir = self::getMediaDir($tenantId, $subDir);
            Storage::ensureDirExists($mediaDir);
            
            // Gera nome de arquivo único
            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
            $storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $fileName;
            $fullPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;
            
            // Salva arquivo
            if (file_put_contents($fullPath, $downloadResult['data']) === false) {
                error_log("[WhatsAppMediaService] Falha ao salvar arquivo: {$fullPath}");
                return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
            }
            
            $fileSize = filesize($fullPath);
            
            // Salva registro no banco
            return self::saveMediaRecord(
                $event['event_id'], 
                $mediaId, 
                $mediaType, 
                $downloadResult['mime_type'] ?: $mimeType,
                $storedPath,
                $fileName,
                $fileSize
            );
            
        } catch (\Exception $e) {
            error_log("[WhatsAppMediaService] Exception ao processar mídia: " . $e->getMessage());
            return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
        }
    }
    
    /**
     * Salva registro de mídia no banco
     */
    private static function saveMediaRecord(
        string $eventId, 
        string $mediaId, 
        string $mediaType, 
        string $mimeType, 
        ?string $storedPath, 
        ?string $fileName, 
        ?int $fileSize
    ): ?array {
        $db = DB::getConnection();
        
        try {
            // Verifica se já existe
            $stmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ? LIMIT 1");
            $stmt->execute([$eventId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualiza registro existente
                $stmt = $db->prepare("
                    UPDATE communication_media 
                    SET media_id = ?, media_type = ?, mime_type = ?, 
                        stored_path = ?, file_name = ?, file_size = ?,
                        updated_at = NOW()
                    WHERE event_id = ?
                ");
                $stmt->execute([
                    $mediaId,
                    $mediaType,
                    $mimeType,
                    $storedPath,
                    $fileName,
                    $fileSize,
                    $eventId
                ]);
                
                return [
                    'id' => $existing['id'],
                    'event_id' => $eventId,
                    'media_type' => $mediaType,
                    'mime_type' => $mimeType,
                    'stored_path' => $storedPath,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'url' => $storedPath ? self::getMediaUrl($storedPath) : null
                ];
            } else {
                // Insere novo registro
                $stmt = $db->prepare("
                    INSERT INTO communication_media 
                    (event_id, media_id, media_type, mime_type, stored_path, file_name, file_size, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $eventId,
                    $mediaId,
                    $mediaType,
                    $mimeType,
                    $storedPath,
                    $fileName,
                    $fileSize
                ]);
                
                return [
                    'id' => $db->lastInsertId(),
                    'event_id' => $eventId,
                    'media_type' => $mediaType,
                    'mime_type' => $mimeType,
                    'stored_path' => $storedPath,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'url' => $storedPath ? self::getMediaUrl($storedPath) : null
                ];
            }
        } catch (\PDOException $e) {
            error_log("[WhatsAppMediaService] PDOException ao salvar mídia: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtém diretório para armazenar mídias
     */
    private static function getMediaDir(?int $tenantId, string $subDir): string
    {
        $baseDir = __DIR__ . '/../../storage/whatsapp-media';
        if ($tenantId) {
            $baseDir .= '/tenant-' . $tenantId;
        }
        if ($subDir) {
            $baseDir .= '/' . trim($subDir, '/');
        }
        return $baseDir;
    }
    
    /**
     * Gera URL pública para a mídia
     */
    private static function getMediaUrl(string $storedPath): string
    {
        if (function_exists('pixelhub_url')) {
            return pixelhub_url('/communication-hub/media?path=' . urlencode($storedPath));
        }
        
        // Fallback se pixelhub_url não estiver disponível
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        return $protocol . $domainName . $basePath . '/communication-hub/media?path=' . urlencode($storedPath);
    }
    
    /**
     * Adivinha mimetype baseado no tipo de mídia
     */
    private static function guessMimeType(string $mediaType): string
    {
        $mimeTypes = [
            'audio' => 'audio/ogg',
            'voice' => 'audio/ogg',
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'document' => 'application/pdf',
            'sticker' => 'image/webp'
        ];
        
        return $mimeTypes[strtolower($mediaType)] ?? 'application/octet-stream';
    }
    
    /**
     * Obtém extensão do arquivo baseado no mimetype
     */
    private static function getExtensionFromMimeType(string $mimeType): string
    {
        $extensions = [
            'audio/ogg' => 'ogg',
            'audio/oga' => 'oga',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/webm' => 'webm',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];
        
        return $extensions[strtolower($mimeType)] ?? 'bin';
    }
    
    /**
     * Busca mídia associada a um evento
     */
    public static function getMediaByEventId(string $eventId): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM communication_media 
            WHERE event_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$eventId]);
        $media = $stmt->fetch();
        
        if (!$media) {
            return null;
        }
        
        return [
            'id' => $media['id'],
            'event_id' => $media['event_id'],
            'media_type' => $media['media_type'],
            'mime_type' => $media['mime_type'],
            'stored_path' => $media['stored_path'],
            'file_name' => $media['file_name'],
            'file_size' => $media['file_size'],
            'url' => $media['stored_path'] ? self::getMediaUrl($media['stored_path']) : null
        ];
    }
}

