<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
use PixelHub\Core\DB;
use PDO;

/**
 * Serviço de transcrição de áudio usando OpenAI Whisper API
 * 
 * Regras P0:
 * - NÃO transcrever automaticamente no webhook
 * - NÃO criar cron/job recorrente
 * - APENAS transcrever quando usuário clicar manualmente
 */
class AudioTranscriptionService
{
    private const WHISPER_API_URL = 'https://api.openai.com/v1/audio/transcriptions';
    private const WHISPER_MODEL = 'whisper-1';
    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB limite da API
    private const TIMEOUT_SECONDS = 60;
    
    /**
     * Transcreve um áudio pelo ID da mídia
     * 
     * @param int $mediaId ID da mídia em communication_media
     * @return array ['success' => bool, 'transcription' => string|null, 'error' => string|null]
     */
    public static function transcribe(int $mediaId): array
    {
        $db = DB::getConnection();
        
        try {
            // 1. Busca informações da mídia
            $stmt = $db->prepare("
                SELECT id, event_id, stored_path, media_type, mime_type, file_size, 
                       transcription, transcription_status
                FROM communication_media 
                WHERE id = ?
            ");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$media) {
                return ['success' => false, 'error' => 'Mídia não encontrada'];
            }
            
            // 2. Verifica se é áudio
            $audioTypes = ['audio', 'voice', 'ptt'];
            if (!in_array($media['media_type'], $audioTypes)) {
                return ['success' => false, 'error' => 'Mídia não é um áudio'];
            }
            
            // 3. Se já transcrito, retorna a transcrição existente
            if ($media['transcription_status'] === 'completed' && !empty($media['transcription'])) {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transcription' => $media['transcription'],
                    'cached' => true
                ];
            }
            
            // 4. Se está processando, retorna status
            if ($media['transcription_status'] === 'processing') {
                return [
                    'success' => true,
                    'status' => 'processing',
                    'message' => 'Transcrição em andamento'
                ];
            }
            
            // 5. Verifica se arquivo existe
            $storagePath = $media['stored_path'];
            if (empty($storagePath)) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Caminho do arquivo não definido');
                return ['success' => false, 'error' => 'Caminho do arquivo não definido'];
            }
            
            // Monta caminho absoluto (mesmo padrão do WhatsAppMediaService)
            $basePath = __DIR__ . '/../../storage';
            $fullPath = $basePath . '/' . ltrim($storagePath, '/');
            
            if (!file_exists($fullPath)) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Arquivo não encontrado no storage');
                return ['success' => false, 'error' => 'Arquivo não encontrado'];
            }
            
            // 6. Verifica tamanho do arquivo
            $fileSize = filesize($fullPath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Arquivo excede limite de 25MB');
                return ['success' => false, 'error' => 'Arquivo excede limite de 25MB'];
            }
            
            // 7. Obtém API Key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                return ['success' => false, 'error' => 'API Key do OpenAI não configurada'];
            }
            
            // 8. Marca como processando
            self::updateTranscriptionStatus($mediaId, 'processing');
            
            // 9. Chama API do Whisper
            $result = self::callWhisperApi($fullPath, $apiKey);
            
            if ($result['success']) {
                // 10. Salva transcrição
                self::saveTranscription($mediaId, $result['transcription']);
                
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transcription' => $result['transcription']
                ];
            } else {
                // 11. Registra erro
                self::updateTranscriptionStatus($mediaId, 'failed', $result['error']);
                return ['success' => false, 'error' => $result['error']];
            }
            
        } catch (\Exception $e) {
            error_log("[AudioTranscription] Erro ao transcrever mídia {$mediaId}: " . $e->getMessage());
            self::updateTranscriptionStatus($mediaId, 'failed', $e->getMessage());
            return ['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Transcreve um áudio pelo event_id
     * 
     * @param string $eventId ID do evento em communication_events
     * @return array
     */
    public static function transcribeByEventId(string $eventId): array
    {
        $db = DB::getConnection();
        
        // Busca mídia pelo event_id
        $stmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            return ['success' => false, 'error' => 'Mídia não encontrada para este evento'];
        }
        
        return self::transcribe((int) $media['id']);
    }
    
    /**
     * Retorna status da transcrição de uma mídia
     * 
     * @param string $eventId
     * @return array
     */
    public static function getStatus(string $eventId): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT transcription, transcription_status, transcription_error, transcription_at
            FROM communication_media 
            WHERE event_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$eventId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            return ['success' => false, 'error' => 'Mídia não encontrada'];
        }
        
        return [
            'success' => true,
            'status' => $media['transcription_status'] ?? 'pending',
            'transcription' => $media['transcription'],
            'error' => $media['transcription_error'],
            'transcribed_at' => $media['transcription_at']
        ];
    }
    
    /**
     * Retorna estatísticas de transcrição
     * 
     * @return array
     */
    public static function getStats(): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_audios,
                SUM(CASE WHEN transcription IS NOT NULL AND transcription != '' THEN 1 ELSE 0 END) as transcribed,
                SUM(CASE WHEN transcription_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN transcription_status = 'pending' OR transcription_status IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN transcription_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN transcription_status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM communication_media
            WHERE media_type IN ('audio', 'voice', 'ptt')
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verifica saúde do serviço (API Key válida)
     * 
     * @return array
     */
    public static function checkHealth(): array
    {
        $apiKey = self::getApiKey();
        
        if (empty($apiKey)) {
            return [
                'healthy' => false,
                'error' => 'API Key não configurada'
            ];
        }
        
        // Testa conexão com OpenAI (models endpoint)
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['healthy' => true, 'message' => 'API Key válida'];
        } elseif ($httpCode === 401) {
            return ['healthy' => false, 'error' => 'API Key inválida ou expirada'];
        } else {
            return ['healthy' => false, 'error' => "Erro HTTP {$httpCode}"];
        }
    }
    
    /**
     * Chama a API do Whisper para transcrever o áudio
     * 
     * @param string $filePath Caminho absoluto do arquivo de áudio
     * @param string $apiKey API Key do OpenAI
     * @return array
     */
    private static function callWhisperApi(string $filePath, string $apiKey): array
    {
        try {
            // Prepara o arquivo para upload
            $cFile = new \CURLFile($filePath);
            
            // Detecta mime type se possível
            $mimeType = mime_content_type($filePath);
            if ($mimeType) {
                $cFile->setMimeType($mimeType);
            }
            
            $postData = [
                'file' => $cFile,
                'model' => self::WHISPER_MODEL,
                'language' => 'pt', // Português brasileiro
                'response_format' => 'json'
            ];
            
            $ch = curl_init(self::WHISPER_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            ]);
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log("[AudioTranscription] Whisper API chamada - HTTP {$httpCode}, {$duration}ms");
            
            if ($curlError) {
                return ['success' => false, 'error' => 'Erro de conexão: ' . $curlError];
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? "Erro HTTP {$httpCode}";
                return ['success' => false, 'error' => $errorMsg];
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['text'])) {
                return [
                    'success' => true,
                    'transcription' => trim($data['text']),
                    'duration_ms' => $duration
                ];
            }
            
            return ['success' => false, 'error' => 'Resposta inválida da API'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Exceção: ' . $e->getMessage()];
        }
    }
    
    /**
     * Atualiza status da transcrição no banco
     */
    private static function updateTranscriptionStatus(int $mediaId, string $status, ?string $error = null): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE communication_media 
            SET transcription_status = ?, 
                transcription_error = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $error, $mediaId]);
    }
    
    /**
     * Salva transcrição no banco
     */
    private static function saveTranscription(int $mediaId, string $transcription): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE communication_media 
            SET transcription = ?,
                transcription_status = 'completed',
                transcription_error = NULL,
                transcription_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transcription, $mediaId]);
    }
    
    /**
     * Obtém a API Key do OpenAI (reutiliza lógica existente)
     */
    private static function getApiKey(): string
    {
        $apiKeyRaw = Env::get('OPENAI_API_KEY');
        
        if (empty($apiKeyRaw)) {
            return '';
        }
        
        $apiKeyRaw = trim($apiKeyRaw);
        
        // Chaves OpenAI geralmente começam com "sk-" ou "pk-"
        // Se começa com isso, está em texto plano
        if (strpos($apiKeyRaw, 'sk-') === 0 || strpos($apiKeyRaw, 'pk-') === 0) {
            return $apiKeyRaw;
        }
        
        // Se é muito longa (>100 chars), provavelmente é criptografada
        if (strlen($apiKeyRaw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($apiKeyRaw);
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                error_log("[AudioTranscription] Erro ao descriptografar API Key: " . $e->getMessage());
                return '';
            }
        }
        
        // Retorna como está (pode ser formato diferente)
        return $apiKeyRaw;
    }
}
