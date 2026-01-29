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
        
        // NOVA DETECÇÃO: Verifica se há mídia codificada em base64 no campo "text"
        // Alguns gateways WhatsApp enviam áudio PTT ou imagens como base64 no campo text
        $base64AudioData = null;
        $base64ImageData = null;
        $base64ImageType = null; // 'jpeg' ou 'png'
        $text = $payload['text'] ?? $payload['message']['text'] ?? null;
        
        if ($text && strlen($text) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text)) {
            // Remove espaços e quebras de linha
            $textCleaned = preg_replace('/\s+/', '', $text);
            
            // Tenta decodificar base64
            $decoded = base64_decode($textCleaned, true);
            
            if ($decoded !== false) {
                // Verifica se é OGG (formato de áudio do WhatsApp)
                if (substr($decoded, 0, 4) === 'OggS') {
                    $base64AudioData = $decoded;
                    error_log("[WhatsAppMediaService] Áudio OGG detectado em base64 no campo text. Tamanho: " . strlen($base64AudioData) . " bytes");
                }
                // Verifica se é JPEG (começa com /9j/)
                elseif (substr($textCleaned, 0, 4) === '/9j/') {
                    $base64ImageData = $decoded;
                    $base64ImageType = 'jpeg';
                    error_log("[WhatsAppMediaService] Imagem JPEG detectada em base64 no campo text. Tamanho: " . strlen($base64ImageData) . " bytes");
                }
                // Verifica se é PNG (começa com iVBORw0KGgo)
                elseif (substr($textCleaned, 0, 12) === 'iVBORw0KGgo') {
                    $base64ImageData = $decoded;
                    $base64ImageType = 'png';
                    error_log("[WhatsAppMediaService] Imagem PNG detectada em base64 no campo text. Tamanho: " . strlen($base64ImageData) . " bytes");
                }
            }
        }
        
        // Extrai informações de mídia do payload (suporta múltiplos formatos)
        // Formato Baileys: message.message.audioMessage, message.message.imageMessage, etc.
        $messageContent = $payload['message']['message'] ?? null;
        
        // NOVO: Formato WPP Connect - dados de mídia em raw.payload
        $rawPayload = $payload['raw']['payload'] ?? null;
        $wppConnectMediaType = null;
        $wppConnectMediaData = null;
        
        if ($rawPayload && isset($rawPayload['type'])) {
            $rawType = $rawPayload['type'];
            // WPP Connect usa: ptt (voice), audio, image, video, document, sticker
            if (in_array($rawType, ['ptt', 'audio', 'image', 'video', 'document', 'sticker'])) {
                $wppConnectMediaType = $rawType === 'ptt' ? 'audio' : $rawType; // Normaliza ptt para audio
                $wppConnectMediaData = $rawPayload;
                error_log("[WhatsAppMediaService] WPP Connect: Detectado tipo '{$rawType}' em raw.payload, normalizado para '{$wppConnectMediaType}'");
            }
        }
        
        // Detecta tipo de mídia no formato Baileys
        $baileysMediaType = null;
        $baileysMediaData = null;
        
        if ($messageContent) {
            // Verifica diferentes tipos de mídia Baileys
            if (isset($messageContent['audioMessage'])) {
                $baileysMediaType = 'audio';
                $baileysMediaData = $messageContent['audioMessage'];
            } elseif (isset($messageContent['imageMessage'])) {
                $baileysMediaType = 'image';
                $baileysMediaData = $messageContent['imageMessage'];
            } elseif (isset($messageContent['videoMessage'])) {
                $baileysMediaType = 'video';
                $baileysMediaData = $messageContent['videoMessage'];
            } elseif (isset($messageContent['documentMessage'])) {
                $baileysMediaType = 'document';
                $baileysMediaData = $messageContent['documentMessage'];
            } elseif (isset($messageContent['stickerMessage'])) {
                $baileysMediaType = 'sticker';
                $baileysMediaData = $messageContent['stickerMessage'];
            }
        }
        
        // Se detectou áudio em base64, processa diretamente
        if ($base64AudioData) {
            return self::processBase64Audio($event, $base64AudioData);
        }
        
        // Se detectou imagem em base64, processa diretamente
        if ($base64ImageData && $base64ImageType) {
            return self::processBase64Image($event, $base64ImageData, $base64ImageType);
        }
        
        // Extrai mediaId (suporta múltiplos formatos: Baileys, WPP Connect, padrão)
        $mediaId = null;
        if ($baileysMediaData) {
            // Baileys: mediaId pode estar em diferentes lugares
            $mediaId = $baileysMediaData['mediaKey'] 
                ?? $baileysMediaData['url'] 
                ?? $baileysMediaData['directPath']
                ?? $payload['message']['key']['id'] // ID da mensagem pode ser usado como mediaId
                ?? null;
        } elseif ($wppConnectMediaData) {
            // NOVO: WPP Connect com dados em raw.payload
            // IMPORTANTE: Usa ID da mensagem (não mediaKey!) para download via gateway
            // mediaKey é a chave de criptografia, não o identificador para download
            $mediaId = $wppConnectMediaData['id']  // ID da mensagem (ex: false_554796164699@c.us_3EB0...)
                ?? $payload['message']['id']
                ?? $wppConnectMediaData['directPath']  // Fallback: caminho direto no WhatsApp CDN
                ?? null;
            error_log("[WhatsAppMediaService] WPP Connect: mediaId (msg id) extraído = " . ($mediaId ? substr($mediaId, 0, 80) . '...' : 'NULL'));
        } else {
            // WPP Connect: pode usar mediaUrl, media_id, ou id da mensagem
            // Formato padrão também suportado
            $mediaId = $payload['mediaId'] 
                ?? $payload['media_id'] 
                ?? $payload['mediaUrl'] // WPP Connect pode usar mediaUrl
                ?? $payload['media_url']
                ?? $payload['message']['mediaId'] 
                ?? $payload['message']['media_id']
                ?? $payload['message']['mediaUrl']
                ?? $payload['message']['media_url']
                ?? $payload['media']['id']
                ?? $payload['message']['media']['id']
                ?? $payload['message']['key']['id'] // Fallback: ID da mensagem
                ?? $payload['id'] // ID da mensagem no WPP Connect
                ?? $payload['messageId']
                ?? $payload['message_id']
                ?? null;
        }
        
        // Se não encontrou mediaId, verifica se é mídia pelo tipo
        if (!$mediaId) {
            $typeCheck = $baileysMediaType 
                ?? $wppConnectMediaType  // NOVO: inclui tipo do WPP Connect
                ?? $payload['type'] 
                ?? $payload['message']['type'] 
                ?? $payload['raw']['payload']['type']  // NOVO: fallback para raw.payload.type
                ?? null;
            if (in_array($typeCheck, ['audio', 'ptt', 'image', 'video', 'document', 'sticker'])) {
                // É mídia, mas sem mediaId - pode ser que o gateway forneça URL direta
                // Usa ID da mensagem como fallback
                $mediaId = $payload['id'] 
                    ?? $payload['messageId'] 
                    ?? $payload['message_id'] 
                    ?? $payload['message']['id'] 
                    ?? $payload['message']['key']['id']
                    ?? $payload['raw']['payload']['id']  // NOVO: fallback para raw.payload.id
                    ?? null;
            }
        }
        
        if (!$mediaId) {
            // Não é uma mensagem com mídia identificável
            $typeLog = $baileysMediaType 
                ?? $wppConnectMediaType
                ?? $payload['type'] 
                ?? $payload['message']['type']
                ?? $payload['raw']['payload']['type']
                ?? 'unknown';
            error_log("[WhatsAppMediaService] MediaId não encontrado no payload. Type: {$typeLog}");
            return null;
        }
        
        // Verifica se a mídia já foi processada
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM communication_media 
                WHERE event_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$event['event_id']]);
            $existingMedia = $stmt->fetch();
        } catch (\PDOException $e) {
            // Se a tabela não existe, loga e retorna null
            error_log("[WhatsAppMediaService] ERRO: Tabela communication_media não existe ou erro de acesso: " . $e->getMessage());
            error_log("[WhatsAppMediaService] Execute a migration: 20260116_create_communication_media_table.php");
            return null;
        }
        
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
        $mediaType = $baileysMediaType 
            ?? $wppConnectMediaType  // NOVO: WPP Connect
            ?? $payload['type'] 
            ?? $payload['message']['type']
            ?? $payload['raw']['payload']['type']  // NOVO: fallback para raw.payload
            ?? ($baileysMediaData ? 'media' : null)
            ?? 'unknown';
        
        // Normaliza tipo ptt para audio
        if ($mediaType === 'ptt') {
            $mediaType = 'audio';
        }
        
        // Extrai mimetype (Baileys, WPP Connect ou padrão)
        $mimeType = null;
        if ($baileysMediaData) {
            $mimeType = $baileysMediaData['mimetype'] 
                ?? $baileysMediaData['mimeType']
                ?? self::guessMimeType($mediaType);
        } elseif ($wppConnectMediaData) {
            // NOVO: WPP Connect com dados em raw.payload
            $mimeType = $wppConnectMediaData['mimetype'] 
                ?? $wppConnectMediaData['mimeType']
                ?? self::guessMimeType($mediaType);
            error_log("[WhatsAppMediaService] WPP Connect: mimeType extraído = {$mimeType}");
        } else {
            // WPP Connect usa 'mimetype' (sem camelCase)
            $mimeType = $payload['mimetype'] 
                ?? $payload['mimeType'] 
                ?? $payload['message']['mimetype'] 
                ?? $payload['message']['mimeType']
                ?? $payload['media']['mimetype'] // WPP Connect pode ter mídia aninhada
                ?? $payload['media']['mimeType']
                ?? self::guessMimeType($mediaType);
        }
        
        // Log para debug
        error_log("[WhatsAppMediaService] Processando mídia - event_id: {$event['event_id']}, mediaType: {$mediaType}, mediaId: {$mediaId}, mimeType: {$mimeType}");
        
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
            error_log("[WhatsAppMediaService] Iniciando download de mídia - channelId: {$channelId}, mediaId: {$mediaId}");
            $client = new WhatsAppGatewayClient();
            $downloadResult = $client->downloadMedia($channelId, $mediaId);
            
            if (!$downloadResult['success'] || empty($downloadResult['data'])) {
                $errorMsg = $downloadResult['error'] ?? 'Dados vazios ou download falhou';
                error_log("[WhatsAppMediaService] Falha ao baixar mídia: {$errorMsg} (channelId: {$channelId}, mediaId: {$mediaId})");
                // Salva registro sem arquivo
                return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
            }
            
            $dataSize = strlen($downloadResult['data']);
            error_log("[WhatsAppMediaService] Download bem-sucedido: {$dataSize} bytes baixados (channelId: {$channelId}, mediaId: {$mediaId})");
            
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
            $bytesWritten = file_put_contents($fullPath, $downloadResult['data']);
            if ($bytesWritten === false) {
                error_log("[WhatsAppMediaService] Falha ao salvar arquivo: {$fullPath}");
                return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
            }
            
            // Validação adicional: verifica se arquivo realmente existe após salvar
            if (!file_exists($fullPath)) {
                error_log("[WhatsAppMediaService] ERRO CRÍTICO: file_put_contents retornou {$bytesWritten} bytes mas arquivo não existe: {$fullPath}");
                return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
            }
            
            $fileSize = filesize($fullPath);
            if ($fileSize === false || $fileSize === 0) {
                error_log("[WhatsAppMediaService] ERRO: Arquivo salvo mas tamanho inválido (0 bytes ou erro): {$fullPath}");
                // Remove arquivo inválido
                @unlink($fullPath);
                return self::saveMediaRecord($event['event_id'], $mediaId, $mediaType, $mimeType, null, null, null);
            }
            
            error_log("[WhatsAppMediaService] Arquivo salvo com sucesso: {$storedPath} ({$fileSize} bytes, escrito: {$bytesWritten} bytes)");
            
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
     * Processa áudio codificado em base64 no campo text
     * 
     * @param array $event Evento da tabela communication_events
     * @param string $audioData Dados binários do áudio decodificado
     * @return array|null Dados da mídia processada
     */
    private static function processBase64Audio(array $event, string $audioData): ?array
    {
        try {
            // Verifica se já foi processada
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM communication_media 
                WHERE event_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$event['event_id']]);
            $existingMedia = $stmt->fetch();
            
            if ($existingMedia && !empty($existingMedia['stored_path'])) {
                // Mídia já processada - verifica se arquivo existe
                $absolutePath = __DIR__ . '/../../storage/' . $existingMedia['stored_path'];
                if (file_exists($absolutePath)) {
                    // Arquivo existe, retorna dados do banco
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
                } else {
                    // Arquivo não existe - recria
                    error_log("[WhatsAppMediaService] Arquivo de imagem não existe, recriando: {$existingMedia['stored_path']}");
                }
            }
            
            // Determina diretório para salvar
            $tenantId = $event['tenant_id'] ?? null;
            $subDir = date('Y/m/d', strtotime($event['created_at'] ?? 'now'));
            $mediaDir = self::getMediaDir($tenantId, $subDir);
            Storage::ensureDirExists($mediaDir);
            
            // Gera nome de arquivo único
            $fileName = bin2hex(random_bytes(16)) . '.ogg';
            $storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $fileName;
            $fullPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;
            
            // Salva arquivo
            if (file_put_contents($fullPath, $audioData) === false) {
                error_log("[WhatsAppMediaService] Falha ao salvar arquivo de áudio base64: {$fullPath}");
                return self::saveMediaRecord($event['event_id'], $event['event_id'], 'audio', 'audio/ogg', null, null, null);
            }
            
            $fileSize = filesize($fullPath);
            error_log("[WhatsAppMediaService] Áudio base64 salvo com sucesso: {$storedPath} ({$fileSize} bytes)");
            
            // Salva registro no banco
            return self::saveMediaRecord(
                $event['event_id'],
                $event['event_id'], // Usa event_id como media_id (fallback)
                'audio',
                'audio/ogg',
                $storedPath,
                $fileName,
                $fileSize
            );
            
        } catch (\Exception $e) {
            error_log("[WhatsAppMediaService] Exception ao processar áudio base64: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Processa imagem codificada em base64 no campo text
     * 
     * @param array $event Evento da tabela communication_events
     * @param string $imageData Dados binários da imagem decodificada
     * @param string $imageType Tipo da imagem: 'jpeg' ou 'png'
     * @return array|null Dados da mídia processada
     */
    private static function processBase64Image(array $event, string $imageData, string $imageType): ?array
    {
        try {
            // Verifica se já foi processada
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM communication_media 
                WHERE event_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$event['event_id']]);
            $existingMedia = $stmt->fetch();
            
            if ($existingMedia && !empty($existingMedia['stored_path'])) {
                // Mídia já processada - verifica se arquivo existe
                $absolutePath = __DIR__ . '/../../storage/' . $existingMedia['stored_path'];
                if (file_exists($absolutePath)) {
                    // Arquivo existe, retorna dados do banco
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
                } else {
                    // Arquivo não existe - recria
                    error_log("[WhatsAppMediaService] Arquivo de imagem não existe, recriando: {$existingMedia['stored_path']}");
                }
            }
            
            // Determina mime type e extensão
            $mimeType = $imageType === 'png' ? 'image/png' : 'image/jpeg';
            $extension = $imageType === 'png' ? 'png' : 'jpg';
            
            // Determina diretório para salvar
            $tenantId = $event['tenant_id'] ?? null;
            $subDir = date('Y/m/d', strtotime($event['created_at'] ?? 'now'));
            $mediaDir = self::getMediaDir($tenantId, $subDir);
            Storage::ensureDirExists($mediaDir);
            
            // Gera nome de arquivo único
            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
            $storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $fileName;
            $fullPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;
            
            // Salva arquivo
            if (file_put_contents($fullPath, $imageData) === false) {
                error_log("[WhatsAppMediaService] Falha ao salvar arquivo de imagem base64: {$fullPath}");
                return self::saveMediaRecord($event['event_id'], $event['event_id'], 'image', $mimeType, null, null, null);
            }
            
            $fileSize = filesize($fullPath);
            error_log("[WhatsAppMediaService] Imagem base64 salva com sucesso: {$storedPath} ({$fileSize} bytes)");
            
            // Salva registro no banco
            return self::saveMediaRecord(
                $event['event_id'],
                $event['event_id'], // Usa event_id como media_id (fallback)
                'image',
                $mimeType,
                $storedPath,
                $fileName,
                $fileSize
            );
            
        } catch (\Exception $e) {
            error_log("[WhatsAppMediaService] Exception ao processar imagem base64: " . $e->getMessage());
            return null;
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
     * CORRIGIDO: Sempre usa pixelhub_url() quando disponível para garantir URL absoluta correta
     */
    private static function getMediaUrl(string $storedPath): string
    {
        // Sempre tenta usar pixelhub_url() primeiro (mais robusto)
        if (function_exists('pixelhub_url')) {
            return pixelhub_url('/communication-hub/media?path=' . urlencode($storedPath));
        }
        
        // Fallback: constrói URL manualmente com BASE_PATH
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $url = $basePath . '/communication-hub/media?path=' . urlencode($storedPath);
        
        // Garante que começa com / se basePath estiver vazio
        if (empty($basePath) && $url[0] !== '/') {
            $url = '/' . $url;
        }
        
        return $url;
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
        
        // Gera URL da mídia (sempre atualiza para garantir URL correta)
        $mediaUrl = null;
        if (!empty($media['stored_path'])) {
            $mediaUrl = self::getMediaUrl($media['stored_path']);
        }
        
        // Retorna objeto media completo com todos os campos necessários
        // Formato padronizado usado em todas as respostas de mensagens com mídia
        return [
            'id' => (int) $media['id'],
            'event_id' => $media['event_id'],
            'type' => $media['media_type'], // Campo 'type' para compatibilidade
            'media_type' => $media['media_type'], // Campo original mantido
            'mime_type' => $media['mime_type'],
            'size' => $media['file_size'] ? (int) $media['file_size'] : null, // Campo 'size' para compatibilidade
            'file_size' => $media['file_size'] ? (int) $media['file_size'] : null, // Campo original mantido
            'url' => $mediaUrl,
            'path' => $media['stored_path'], // Campo 'path' para compatibilidade
            'stored_path' => $media['stored_path'], // Campo original mantido
            'file_name' => $media['file_name']
        ];
    }
}

