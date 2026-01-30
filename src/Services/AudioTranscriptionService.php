<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
use PDO;

/**
 * Serviço para transcrição de áudios usando OpenAI Whisper API
 * 
 * Processa áudios recebidos via WhatsApp e gera transcrições automaticamente.
 * Os áudios são armazenados em formato OGG/Opus (nativo do WhatsApp) que é 
 * suportado diretamente pela API Whisper.
 */
class AudioTranscriptionService
{
    /**
     * Endpoint da API Whisper
     */
    private const WHISPER_API_URL = 'https://api.openai.com/v1/audio/transcriptions';
    
    /**
     * Modelo Whisper a usar
     */
    private const WHISPER_MODEL = 'whisper-1';
    
    /**
     * Tamanho máximo do arquivo (25MB - limite da API)
     */
    private const MAX_FILE_SIZE = 25 * 1024 * 1024;
    
    /**
     * Timeout para requisição (segundos)
     */
    private const REQUEST_TIMEOUT = 60;
    
    /**
     * Transcreve um áudio específico pelo ID do registro em communication_media
     * 
     * @param int $mediaId ID do registro na tabela communication_media
     * @return array Resultado da transcrição ['success' => bool, 'transcription' => string|null, 'error' => string|null]
     */
    public static function transcribe(int $mediaId): array
    {
        try {
            $db = DB::getConnection();
            
            // Busca o registro de mídia
            $stmt = $db->prepare("
                SELECT id, event_id, media_type, mime_type, stored_path, file_size, 
                       transcription_status, transcription
                FROM communication_media 
                WHERE id = ?
            ");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$media) {
                return ['success' => false, 'transcription' => null, 'error' => 'Mídia não encontrada'];
            }
            
            // Verifica se é um áudio
            if ($media['media_type'] !== 'audio') {
                return ['success' => false, 'transcription' => null, 'error' => 'Mídia não é um áudio'];
            }
            
            // Verifica se já foi transcrito
            if ($media['transcription_status'] === 'completed' && !empty($media['transcription'])) {
                return ['success' => true, 'transcription' => $media['transcription'], 'error' => null];
            }
            
            // Verifica se tem arquivo armazenado
            if (empty($media['stored_path'])) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Arquivo de áudio não encontrado');
                return ['success' => false, 'transcription' => null, 'error' => 'Arquivo de áudio não encontrado'];
            }
            
            // Constrói caminho absoluto do arquivo
            $absolutePath = __DIR__ . '/../../storage/' . $media['stored_path'];
            
            if (!file_exists($absolutePath)) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Arquivo não existe no storage');
                return ['success' => false, 'transcription' => null, 'error' => 'Arquivo não existe no storage'];
            }
            
            // Verifica tamanho do arquivo
            $fileSize = filesize($absolutePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Arquivo excede limite de 25MB');
                return ['success' => false, 'transcription' => null, 'error' => 'Arquivo excede limite de 25MB'];
            }
            
            // Marca como processando
            self::updateTranscriptionStatus($mediaId, 'processing');
            
            // Obtém API key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                self::updateTranscriptionStatus($mediaId, 'failed', 'Chave de API OpenAI não configurada');
                return ['success' => false, 'transcription' => null, 'error' => 'Chave de API OpenAI não configurada'];
            }
            
            // Chama API Whisper
            $result = self::callWhisperApi($absolutePath, $apiKey);
            
            if ($result['success']) {
                // Salva transcrição
                self::saveTranscription($mediaId, $result['transcription']);
                return ['success' => true, 'transcription' => $result['transcription'], 'error' => null];
            } else {
                self::updateTranscriptionStatus($mediaId, 'failed', $result['error']);
                return ['success' => false, 'transcription' => null, 'error' => $result['error']];
            }
            
        } catch (\Exception $e) {
            error_log("[AudioTranscriptionService] Erro ao transcrever áudio {$mediaId}: " . $e->getMessage());
            self::updateTranscriptionStatus($mediaId, 'failed', $e->getMessage());
            return ['success' => false, 'transcription' => null, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Transcreve um áudio pelo event_id (UUID do evento de comunicação)
     * 
     * @param string $eventId UUID do evento
     * @return array Resultado da transcrição
     */
    public static function transcribeByEventId(string $eventId): array
    {
        try {
            $db = DB::getConnection();
            
            $stmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? AND media_type = 'audio'");
            $stmt->execute([$eventId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$media) {
                return ['success' => false, 'transcription' => null, 'error' => 'Áudio não encontrado para este evento'];
            }
            
            return self::transcribe($media['id']);
            
        } catch (\Exception $e) {
            return ['success' => false, 'transcription' => null, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Processa áudios pendentes de transcrição (para uso em job/cron)
     * 
     * @param int $limit Número máximo de áudios a processar por execução
     * @return array Estatísticas do processamento ['processed' => int, 'success' => int, 'failed' => int, 'errors' => array]
     */
    public static function transcribePending(int $limit = 10): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        try {
            // Verifica se a API key está configurada antes de começar
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                $stats['errors'][] = 'Chave de API OpenAI não configurada';
                return $stats;
            }
            
            $db = DB::getConnection();
            
            // Busca áudios pendentes (nunca processados ou que falharam e podem ser retentados)
            // Prioriza: pending > failed (com limite de retentativas implícito pelo tempo)
            $stmt = $db->prepare("
                SELECT id, event_id, stored_path
                FROM communication_media 
                WHERE media_type = 'audio'
                  AND stored_path IS NOT NULL
                  AND (
                      transcription_status IS NULL 
                      OR transcription_status = 'pending'
                      OR (transcription_status = 'failed' AND transcription_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                  )
                ORDER BY 
                    CASE WHEN transcription_status IS NULL THEN 0
                         WHEN transcription_status = 'pending' THEN 1
                         ELSE 2 END,
                    created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($audios as $audio) {
                $stats['processed']++;
                
                $result = self::transcribe($audio['id']);
                
                if ($result['success']) {
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                    $stats['errors'][] = "Media #{$audio['id']}: " . ($result['error'] ?? 'Erro desconhecido');
                }
                
                // Pequena pausa entre requisições para não sobrecarregar a API
                usleep(500000); // 0.5 segundos
            }
            
        } catch (\Exception $e) {
            $stats['errors'][] = 'Erro geral: ' . $e->getMessage();
            error_log("[AudioTranscriptionService] Erro em transcribePending: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Obtém estatísticas de transcrição
     * 
     * @return array Estatísticas ['total_audios' => int, 'pending' => int, 'completed' => int, 'failed' => int, 'processing' => int]
     */
    public static function getStats(): array
    {
        try {
            $db = DB::getConnection();
            
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total_audios,
                    SUM(CASE WHEN transcription_status IS NULL OR transcription_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN transcription_status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN transcription_status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN transcription_status = 'processing' THEN 1 ELSE 0 END) as processing
                FROM communication_media 
                WHERE media_type = 'audio'
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_audios' => 0,
                'pending' => 0,
                'completed' => 0,
                'failed' => 0,
                'processing' => 0
            ];
            
        } catch (\Exception $e) {
            return [
                'total_audios' => 0,
                'pending' => 0,
                'completed' => 0,
                'failed' => 0,
                'processing' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Chama a API Whisper da OpenAI
     * 
     * @param string $filePath Caminho absoluto do arquivo de áudio
     * @param string $apiKey Chave da API OpenAI
     * @return array ['success' => bool, 'transcription' => string|null, 'error' => string|null]
     */
    private static function callWhisperApi(string $filePath, string $apiKey): array
    {
        $ch = curl_init();
        
        // Prepara o arquivo para upload
        $cFile = new \CURLFile($filePath);
        
        // Configura requisição multipart/form-data
        $postFields = [
            'file' => $cFile,
            'model' => self::WHISPER_MODEL,
            'language' => 'pt', // Português
            'response_format' => 'json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => self::WHISPER_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Verifica erro de curl
        if ($curlError) {
            error_log("[AudioTranscriptionService] Erro curl: {$curlError}");
            return ['success' => false, 'transcription' => null, 'error' => "Erro de conexão: {$curlError}"];
        }
        
        // Verifica resposta HTTP
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode}";
            error_log("[AudioTranscriptionService] Erro API Whisper (HTTP {$httpCode}): {$errorMessage}");
            return ['success' => false, 'transcription' => null, 'error' => "Erro API: {$errorMessage}"];
        }
        
        // Parse da resposta
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['text'])) {
            error_log("[AudioTranscriptionService] Resposta inválida da API Whisper");
            return ['success' => false, 'transcription' => null, 'error' => 'Resposta inválida da API'];
        }
        
        $transcription = trim($data['text']);
        
        // Log de sucesso
        $transcriptionPreview = mb_substr($transcription, 0, 100) . (mb_strlen($transcription) > 100 ? '...' : '');
        error_log("[AudioTranscriptionService] Transcrição bem-sucedida: \"{$transcriptionPreview}\"");
        
        return ['success' => true, 'transcription' => $transcription, 'error' => null];
    }
    
    /**
     * Atualiza o status da transcrição no banco
     */
    private static function updateTranscriptionStatus(int $mediaId, string $status, ?string $error = null): void
    {
        try {
            $db = DB::getConnection();
            
            $stmt = $db->prepare("
                UPDATE communication_media 
                SET transcription_status = ?,
                    transcription_error = ?,
                    transcription_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $error, $mediaId]);
            
        } catch (\Exception $e) {
            error_log("[AudioTranscriptionService] Erro ao atualizar status: " . $e->getMessage());
        }
    }
    
    /**
     * Salva a transcrição no banco
     */
    private static function saveTranscription(int $mediaId, string $transcription): void
    {
        try {
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
            
        } catch (\Exception $e) {
            error_log("[AudioTranscriptionService] Erro ao salvar transcrição: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtém e descriptografa a chave da API OpenAI
     */
    private static function getApiKey(): string
    {
        // Verifica se IA está ativa
        $isActive = Env::get('OPENAI_ACTIVE', '1') === '1';
        if (!$isActive) {
            return '';
        }
        
        $apiKeyRaw = Env::get('OPENAI_API_KEY');
        
        if (empty($apiKeyRaw)) {
            return '';
        }
        
        $apiKeyRaw = trim($apiKeyRaw);
        
        // Chaves OpenAI geralmente começam com "sk-" ou "pk-"
        if (strpos($apiKeyRaw, 'sk-') === 0 || strpos($apiKeyRaw, 'pk-') === 0) {
            return $apiKeyRaw;
        }
        
        // Se é muito longa (>100 chars) e não começa com sk/pk, provavelmente é criptografada
        if (strlen($apiKeyRaw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($apiKeyRaw);
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                error_log("[AudioTranscriptionService] Erro ao descriptografar chave OpenAI: " . $e->getMessage());
                return '';
            }
        }
        
        return $apiKeyRaw;
    }
    
    /**
     * Verifica se o serviço está configurado e pronto para uso
     * 
     * @return array ['ready' => bool, 'message' => string]
     */
    public static function checkHealth(): array
    {
        $apiKey = self::getApiKey();
        
        if (empty($apiKey)) {
            return [
                'ready' => false,
                'message' => 'Chave de API OpenAI não configurada ou IA desativada'
            ];
        }
        
        return [
            'ready' => true,
            'message' => 'Serviço de transcrição configurado e pronto'
        ];
    }
}
