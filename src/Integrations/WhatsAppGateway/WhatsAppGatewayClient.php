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
    private ?string $requestId = null;

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
     * Define request/correlation ID para enviar no header X-Request-Id ao gateway.
     * O gateway deve logar esse ID em cada etapa (received → decode → convert → sendVoiceBase64 → returned).
     */
    public function setRequestId(string $id): void
    {
        $this->requestId = $id;
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
        // Codifica o channelId para URL (resolve problemas com espaços e caracteres especiais)
        $encodedChannelId = rawurlencode($channelId);
        return $this->request('GET', "/api/channels/{$encodedChannelId}");
    }

    /**
     * Obtém QR code para conectar o WhatsApp
     * 
     * @param string $channelId ID do canal
     * @return array { success: bool, qr: string, error?: string }
     */
    public function getQr(string $channelId): array
    {
        // Codifica o channelId para URL
        $encodedChannelId = rawurlencode($channelId);
        return $this->request('GET', "/api/channels/{$encodedChannelId}/qr");
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
     * Envia áudio PTT (voice note) via base64.
     * Aceita OGG/Opus ou WebM; quando audio_mime=audio/webm, o gateway (VPS) converte para OGG.
     *
     * @param string $channelId ID do canal (ex: pixel12digital)
     * @param string $to Número do destinatário (formato: 5547...)
     * @param string $base64Ptt Base64 do áudio (OGG/Opus ou WebM). Pode vir com prefixo data:audio/...;base64,
     * @param array|null $metadata Metadados (sent_by, etc.)
     * @param array $options Opcional: ['audio_mime' => 'audio/webm'|'audio/ogg', 'is_voice' => bool]. Se audio_mime=audio/webm, gateway converte na VPS.
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    public function sendAudioBase64Ptt(string $channelId, string $to, string $base64Ptt, ?array $metadata = null, array $options = []): array
    {
        // Normalização: envia somente base64 cru (gateway nunca recebe dataURL)
        $b64 = (string) $base64Ptt;
        $pos = stripos($b64, 'base64,');
        if ($pos !== false) {
            $b64 = substr($b64, $pos + 7);
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] base64 sanitized: removed dataURL prefix, raw_base64_len=" . strlen($b64));
        }
        $b64 = trim($b64);

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

        // Contrato normalizado: gateway pode converter WebM→OGG quando recebe audio_mime + is_voice
        if (!empty($options['audio_mime'])) {
            $payload['audio_mime'] = (string) $options['audio_mime'];
            $payload['is_voice'] = $options['is_voice'] ?? true;
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] audio_mime=" . $payload['audio_mime'] . ", is_voice=" . ($payload['is_voice'] ? 'true' : 'false'));
        }

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        // Log do payload (sem o base64 completo para não poluir logs)
        $payloadForLog = $payload;
        $payloadForLog['base64Ptt'] = substr($b64, 0, 50) . '... (len=' . strlen($b64) . ')';
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Enviando áudio: channel={$channelId}, to={$to}, base64_len=" . strlen($b64));

        // Aumenta timeout para requisições de áudio (podem ser grandes)
        $originalTimeout = $this->timeout;
        $this->timeout = 120; // 120s para áudio (evita 504 antes do upstream responder)
        
        $requestStartTime = microtime(true);
        $requestStartTimestamp = date('Y-m-d H:i:s.u');
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] ===== INÍCIO REQUISIÇÃO AO GATEWAY ======");
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Timestamp: {$requestStartTimestamp}");
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Timeout configurado: {$this->timeout}s");
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] channel_id: {$channelId}, to: {$to}");
        error_log("[WhatsAppGateway::sendAudioBase64Ptt] Base64 length: " . strlen($b64) . " bytes");
        
        try {
            $response = $this->request('POST', '/api/messages', $payload);
            
            $requestTime = (microtime(true) - $requestStartTime) * 1000;
            $requestEndTimestamp = date('Y-m-d H:i:s.u');
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] Requisição concluída em {$requestTime}ms");
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] Timestamp após requisição: {$requestEndTimestamp}");
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] Success: " . ($response['success'] ? 'true' : 'false'));
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] ===== FIM REQUISIÇÃO AO GATEWAY ======");
        } catch (\Exception $e) {
            $requestTime = (microtime(true) - $requestStartTime) * 1000;
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] ❌ EXCEÇÃO durante requisição após {$requestTime}ms: " . $e->getMessage());
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] Stack trace: " . $e->getTraceAsString());
            throw $e;
        } finally {
            // Restaura timeout original
            $this->timeout = $originalTimeout;
        }

        // Log detalhado da resposta (usa pixelhub_log se disponível para garantir que apareça no log do projeto)
        $logMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Resposta do gateway: success=" . ($response['success'] ? 'true' : 'false') . ", status=" . ($response['status'] ?? 'N/A') . ", error=" . ($response['error'] ?? 'N/A');
        error_log($logMsg);
        if (function_exists('pixelhub_log')) {
            pixelhub_log($logMsg);
        }

        // Log completo da resposta raw para diagnóstico
        if (isset($response['raw'])) {
            $rawForLog = $response['raw'];
            // Remove base64Ptt do log se existir (muito grande)
            if (isset($rawForLog['base64Ptt'])) {
                $rawForLog['base64Ptt'] = '[REMOVED - too large]';
            }
            
            // Log TODOS os campos da resposta para diagnóstico completo
            $allFieldsMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Resposta raw completa (TODOS os campos): " . json_encode($rawForLog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            error_log($allFieldsMsg);
            if (function_exists('pixelhub_log')) {
                pixelhub_log($allFieldsMsg);
            }
            
            // Log campos individuais para facilitar análise
            if (is_array($rawForLog)) {
                $fieldsList = [];
                foreach ($rawForLog as $key => $value) {
                    if (is_string($value) && strlen($value) > 200) {
                        $fieldsList[] = "{$key}: " . substr($value, 0, 200) . "... (truncated, total: " . strlen($value) . " chars)";
                    } elseif (is_array($value)) {
                        $fieldsList[] = "{$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                    } else {
                        $fieldsList[] = "{$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value);
                    }
                }
                $fieldsMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Campos da resposta: " . implode(" | ", $fieldsList);
                error_log($fieldsMsg);
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($fieldsMsg);
                }
                
                // Extrai correlationId se existir (útil para rastreamento no gateway)
                if (isset($rawForLog['correlationId'])) {
                    $corrIdMsg = "[WhatsAppGateway::sendAudioBase64Ptt] CorrelationId para rastreamento: " . $rawForLog['correlationId'];
                    error_log($corrIdMsg);
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log($corrIdMsg);
                    }
                }
            }
        } else {
            $noRawMsg = "[WhatsAppGateway::sendAudioBase64Ptt] ⚠️ ATENÇÃO: Resposta não contém campo 'raw'";
            error_log($noRawMsg);
            if (function_exists('pixelhub_log')) {
                pixelhub_log($noRawMsg);
            }
        }
        
        // Log adicional: se a resposta for string (não decodificada), loga ela também
        if (isset($response['raw']) && is_string($response['raw'])) {
            $rawStrPreview = strlen($response['raw']) > 1000 ? substr($response['raw'], 0, 1000) . '... (truncated, total: ' . strlen($response['raw']) . ' bytes)' : $response['raw'];
            $rawStrMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Resposta raw (string): " . $rawStrPreview;
            error_log($rawStrMsg);
            if (function_exists('pixelhub_log')) {
                pixelhub_log($rawStrMsg);
            }
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
            $rawResponse = $response['raw'] ?? [];
            
            // Tenta extrair mensagem de erro do raw primeiro (pode ser mais específica)
            $rawErrorMsg = $rawResponse['error'] ?? $rawResponse['message'] ?? $rawResponse['error_message'] ?? null;
            $errorMsg = $rawErrorMsg ?? $response['error'] ?? 'Erro desconhecido';
            
            // Extrai error_code do raw se existir
            if (isset($rawResponse['error_code']) && !isset($response['error_code'])) {
                $response['error_code'] = $rawResponse['error_code'];
            }
            
            // Log da mensagem original para debug
            error_log("[WhatsAppGateway::sendAudioBase64Ptt] Mensagem de erro original: {$errorMsg}");
            if ($rawErrorMsg && $rawErrorMsg !== $errorMsg) {
                error_log("[WhatsAppGateway::sendAudioBase64Ptt] Mensagem do raw: {$rawErrorMsg}");
            }
            
            // Detecta erros específicos do WPPConnect
            if (stripos($errorMsg, 'sendVoiceBase64') !== false || stripos($errorMsg, 'WPPConnect') !== false || stripos($errorMsg, 'wppconnect') !== false) {
                // Timeout apenas quando a mensagem indica explicitamente timeout (evita falso positivo com "30" solto)
                $isTimeout = stripos($errorMsg, 'timeout') !== false
                    || stripos($errorMsg, 'timed out') !== false
                    || stripos($errorMsg, '30000ms') !== false
                    || preg_match('/\b30\s*second/i', $errorMsg) === 1
                    || preg_match('/\b30s\b/i', $errorMsg) === 1
                    || stripos($errorMsg, '30 segundos') !== false;
                if ($isTimeout) {
                    $response['error'] = 'O gateway WPPConnect está demorando mais de 30 segundos para processar o áudio. Isso pode acontecer se o áudio for muito grande ou se o gateway estiver sobrecarregado. Tente gravar um áudio mais curto (menos de 1 minuto) ou aguarde alguns minutos e tente novamente.';
                    $response['error_code'] = $response['error_code'] ?? 'WPPCONNECT_TIMEOUT';
                } else {
                    // Preserva mensagem original se ela for específica (mais de 50 caracteres ou contém detalhes)
                    $isGenericError = (stripos($errorMsg, 'Erro ao enviar a mensagem') !== false || stripos($errorMsg, 'Failed to send') !== false) && strlen($errorMsg) < 50;
                    
                    if ($isGenericError) {
                        // Só substitui se for mensagem genérica muito curta
                        $correlationId = is_array($rawResponse) ? ($rawResponse['correlationId'] ?? null) : null;
                        $errorText = 'Falha ao enviar áudio via WPPConnect. Verifique se a sessão está conectada e se o formato do áudio está correto (OGG/Opus).';
                        if ($correlationId) {
                            $errorText .= ' ID de rastreamento: ' . $correlationId;
                        }
                        $response['error'] = $errorText;
                        $response['error_code'] = $response['error_code'] ?? 'WPPCONNECT_SEND_ERROR';
                    } else {
                        // Mantém a mensagem original do gateway (pode conter informações úteis)
                        $correlationId = is_array($rawResponse) ? ($rawResponse['correlationId'] ?? null) : null;
                        $errorText = $errorMsg;
                        if ($correlationId && stripos($errorText, $correlationId) === false) {
                            $errorText .= ' (ID: ' . $correlationId . ')';
                        }
                        $response['error'] = $errorText;
                        $response['error_code'] = $response['error_code'] ?? 'WPPCONNECT_SEND_ERROR';
                    }
                }
            }
            
            // Log erro detalhado com toda a resposta
            $errorLogData = [
                'error' => $errorMsg,
                'error_code' => $response['error_code'] ?? 'N/A',
                'status' => $response['status'] ?? 'N/A',
                'raw_response' => $rawResponse,
                'response_keys' => is_array($rawResponse) ? array_keys($rawResponse) : (is_string($rawResponse) ? 'STRING (length: ' . strlen($rawResponse) . ')' : gettype($rawResponse)),
                'correlationId' => is_array($rawResponse) ? ($rawResponse['correlationId'] ?? 'N/A') : 'N/A'
            ];
            
            // Se houver correlationId, loga separadamente para facilitar rastreamento
            if (is_array($rawResponse) && isset($rawResponse['correlationId'])) {
                $corrIdMsg = "[WhatsAppGateway::sendAudioBase64Ptt] ⚠️ CorrelationId do erro: " . $rawResponse['correlationId'] . " (use este ID para rastrear o erro nos logs do gateway)";
                error_log($corrIdMsg);
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($corrIdMsg);
                }
            }
            
            // Remove dados sensíveis do log
            if (is_array($errorLogData['raw_response']) && isset($errorLogData['raw_response']['base64Ptt'])) {
                $errorLogData['raw_response']['base64Ptt'] = '[REMOVED - too large]';
            }
            
            // Log completo do erro (usa pixelhub_log se disponível)
            $errorDetailMsg = "[WhatsAppGateway::sendAudioBase64Ptt] ❌ Erro detalhado: " . json_encode($errorLogData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            error_log($errorDetailMsg);
            if (function_exists('pixelhub_log')) {
                pixelhub_log($errorDetailMsg);
            }
            
            // Log adicional: se raw_response for string, tenta extrair informações úteis
            if (is_string($rawResponse) && !empty($rawResponse)) {
                $rawStrMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Raw response (string) - Primeiros 2000 chars: " . substr($rawResponse, 0, 2000);
                error_log($rawStrMsg);
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($rawStrMsg);
                }
                
                // Tenta detectar padrões comuns de erro
                if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $rawResponse, $matches)) {
                    $titleMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Título HTML encontrado: " . trim($matches[1]);
                    error_log($titleMsg);
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log($titleMsg);
                    }
                }
                if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $rawResponse, $matches)) {
                    $h1Msg = "[WhatsAppGateway::sendAudioBase64Ptt] H1 HTML encontrado: " . trim($matches[1]);
                    error_log($h1Msg);
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log($h1Msg);
                    }
                }
                if (preg_match('/"error"\s*:\s*"([^"]+)"/i', $rawResponse, $matches)) {
                    $errorFieldMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Campo 'error' encontrado no JSON: " . $matches[1];
                    error_log($errorFieldMsg);
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log($errorFieldMsg);
                    }
                }
                if (preg_match('/"message"\s*:\s*"([^"]+)"/i', $rawResponse, $matches)) {
                    $messageFieldMsg = "[WhatsAppGateway::sendAudioBase64Ptt] Campo 'message' encontrado no JSON: " . $matches[1];
                    error_log($messageFieldMsg);
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log($messageFieldMsg);
                    }
                }
            }
            
            // Também loga no pixelhub_log se disponível
            if (function_exists('pixelhub_log')) {
                pixelhub_log("[WhatsAppGateway::sendAudioBase64Ptt] Erro ao enviar áudio: " . $errorMsg . " (code: " . ($response['error_code'] ?? 'N/A') . ", status: " . ($response['status'] ?? 'N/A') . ")");
            }
        }

        return $response;
    }

    /**
     * Envia imagem via base64 ou URL
     * 
     * @param string $channelId ID do canal
     * @param string $to Número do destinatário (formato: 5511999999999)
     * @param string|null $base64 Base64 da imagem (opcional se fornecer $url)
     * @param string|null $url URL da imagem (opcional se fornecer $base64)
     * @param string|null $caption Legenda da imagem (opcional)
     * @param array|null $metadata Metadados adicionais (opcional)
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    public function sendImage(string $channelId, string $to, ?string $base64 = null, ?string $url = null, ?string $caption = null, ?array $metadata = null): array
    {
        if (empty($base64) && empty($url)) {
            return [
                'success' => false,
                'error' => 'É necessário fornecer base64 ou url da imagem',
                'error_code' => 'MISSING_MEDIA',
                'status' => 400
            ];
        }

        $payload = [
            'channel' => $channelId,
            'to' => $to,
            'type' => 'image',
            'text' => $caption ?? '' // Gateway exige campo text mesmo para imagens
        ];

        if ($base64) {
            // Remove prefixo data:image/...;base64, se existir
            $b64 = $base64;
            $pos = stripos($b64, 'base64,');
            if ($pos !== false) {
                $b64 = substr($b64, $pos + 7);
            }
            $payload['base64'] = $b64;
        } elseif ($url) {
            $payload['url'] = $url;
        }

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        // Log do payload (sem base64 completo)
        $payloadForLog = $payload;
        if (isset($payloadForLog['base64']) && strlen($payloadForLog['base64']) > 100) {
            $payloadForLog['base64'] = substr($payloadForLog['base64'], 0, 50) . '... (len=' . strlen($payload['base64']) . ')';
        }
        error_log("[WhatsAppGateway::sendImage] Payload: " . json_encode($payloadForLog, JSON_UNESCAPED_UNICODE));
        
        $response = $this->request('POST', '/api/messages', $payload);

        // Log detalhado da resposta
        error_log("[WhatsAppGateway::sendImage] Response success: " . ($response['success'] ? 'true' : 'false'));
        error_log("[WhatsAppGateway::sendImage] Response error: " . ($response['error'] ?? 'N/A'));
        error_log("[WhatsAppGateway::sendImage] Response HTTP status: " . ($response['status'] ?? 'N/A'));
        
        if (isset($response['raw'])) {
            $rawForLog = $response['raw'];
            if (is_array($rawForLog)) {
                // Remove campos grandes para log
                if (isset($rawForLog['base64'])) $rawForLog['base64'] = '(truncated)';
                error_log("[WhatsAppGateway::sendImage] Response raw: " . json_encode($rawForLog, JSON_UNESCAPED_UNICODE));
            } else {
                $rawPreview = is_string($rawForLog) && strlen($rawForLog) > 500 
                    ? substr($rawForLog, 0, 500) . '...' 
                    : $rawForLog;
                error_log("[WhatsAppGateway::sendImage] Response raw: " . print_r($rawPreview, true));
            }
        } else {
            error_log("[WhatsAppGateway::sendImage] ⚠️ Response não contém 'raw'");
        }

        // Normaliza resposta
        if ($response['success'] && isset($response['raw'])) {
            $raw = $response['raw'];
            $response['message_id'] = $raw['id'] ?? $raw['messageId'] ?? $raw['message_id'] ?? null;
            $response['correlationId'] = $raw['correlationId'] 
                ?? $raw['correlation_id'] 
                ?? $raw['trace_id'] 
                ?? $raw['traceId']
                ?? null;
            
            // Log do message_id extraído
            error_log("[WhatsAppGateway::sendImage] message_id extraído: " . ($response['message_id'] ?? 'NULL'));
            
            // ALERTA: Se não tiver message_id, pode indicar que a mensagem não foi enviada
            if (empty($response['message_id'])) {
                error_log("[WhatsAppGateway::sendImage] ⚠️ ALERTA: Gateway retornou success mas SEM message_id - possível falha silenciosa!");
            }
        }

        return $response;
    }

    /**
     * Envia documento/PDF via base64 ou URL
     * 
     * @param string $channelId ID do canal
     * @param string $to Número do destinatário (formato: 5511999999999)
     * @param string|null $base64 Base64 do documento (opcional se fornecer $url)
     * @param string|null $url URL do documento (opcional se fornecer $base64)
     * @param string $fileName Nome do arquivo (obrigatório)
     * @param string|null $caption Legenda do documento (opcional)
     * @param array|null $metadata Metadados adicionais (opcional)
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    public function sendDocument(string $channelId, string $to, ?string $base64 = null, ?string $url = null, string $fileName = 'document.pdf', ?string $caption = null, ?array $metadata = null): array
    {
        if (empty($base64) && empty($url)) {
            return [
                'success' => false,
                'error' => 'É necessário fornecer base64 ou url do documento',
                'error_code' => 'MISSING_MEDIA',
                'status' => 400
            ];
        }

        $payload = [
            'channel' => $channelId,
            'to' => $to,
            'type' => 'document',
            'fileName' => $fileName,
            'text' => $caption ?? '' // Gateway exige campo text mesmo para documentos
        ];

        if ($base64) {
            // Remove prefixo data:application/...;base64, se existir
            $b64 = $base64;
            $pos = stripos($b64, 'base64,');
            if ($pos !== false) {
                $b64 = substr($b64, $pos + 7);
            }
            $payload['base64'] = $b64;
        } elseif ($url) {
            $payload['url'] = $url;
        }

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        // Log do payload (sem base64 completo)
        $payloadForLog = $payload;
        if (isset($payloadForLog['base64']) && strlen($payloadForLog['base64']) > 100) {
            $payloadForLog['base64'] = substr($payloadForLog['base64'], 0, 50) . '... (len=' . strlen($payload['base64']) . ')';
        }
        error_log("[WhatsAppGateway::sendDocument] Payload: " . json_encode($payloadForLog, JSON_UNESCAPED_UNICODE));
        
        // Aumenta timeout para documentos (podem ser grandes)
        $originalTimeout = $this->timeout;
        $this->timeout = 90;
        
        try {
            $response = $this->request('POST', '/api/messages', $payload);
        } finally {
            $this->timeout = $originalTimeout;
        }

        // Log detalhado da resposta
        error_log("[WhatsAppGateway::sendDocument] Response success: " . ($response['success'] ? 'true' : 'false'));
        error_log("[WhatsAppGateway::sendDocument] Response error: " . ($response['error'] ?? 'N/A'));

        // Normaliza resposta
        if ($response['success'] && isset($response['raw'])) {
            $raw = $response['raw'];
            $response['message_id'] = $raw['id'] ?? $raw['messageId'] ?? $raw['message_id'] ?? null;
            $response['correlationId'] = $raw['correlationId'] 
                ?? $raw['correlation_id'] 
                ?? $raw['trace_id'] 
                ?? $raw['traceId']
                ?? null;
            
            error_log("[WhatsAppGateway::sendDocument] message_id extraído: " . ($response['message_id'] ?? 'NULL'));
            
            if (empty($response['message_id'])) {
                error_log("[WhatsAppGateway::sendDocument] ⚠️ ALERTA: Gateway retornou success mas SEM message_id!");
            }
        }

        return $response;
    }

    /**
     * Envia vídeo via base64 ou URL
     * 
     * @param string $channelId ID do canal
     * @param string $to Número do destinatário (formato: 5511999999999)
     * @param string|null $base64 Base64 do vídeo (opcional se fornecer $url)
     * @param string|null $url URL do vídeo (opcional se fornecer $base64)
     * @param string|null $caption Legenda do vídeo (opcional)
     * @param array|null $metadata Metadados adicionais (opcional)
     * @return array { success: bool, message_id?: string, error?: string, raw?: array }
     */
    public function sendVideo(string $channelId, string $to, ?string $base64 = null, ?string $url = null, ?string $caption = null, ?array $metadata = null): array
    {
        if (empty($base64) && empty($url)) {
            return [
                'success' => false,
                'error' => 'É necessário fornecer base64 ou url do vídeo',
                'error_code' => 'MISSING_MEDIA',
                'status' => 400
            ];
        }

        $payload = [
            'channel' => $channelId,
            'to' => $to,
            'type' => 'video'
        ];

        if ($base64) {
            // Remove prefixo data:video/...;base64, se existir
            $b64 = $base64;
            $pos = stripos($b64, 'base64,');
            if ($pos !== false) {
                $b64 = substr($b64, $pos + 7);
            }
            $payload['base64'] = $b64;
        } elseif ($url) {
            $payload['url'] = $url;
        }

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        // Aumenta timeout para vídeos (podem ser grandes)
        $originalTimeout = $this->timeout;
        $this->timeout = 120;
        
        try {
            $response = $this->request('POST', '/api/messages', $payload);
        } finally {
            $this->timeout = $originalTimeout;
        }

        // Normaliza resposta
        if ($response['success'] && isset($response['raw'])) {
            $raw = $response['raw'];
            $response['message_id'] = $raw['id'] ?? $raw['messageId'] ?? $raw['message_id'] ?? null;
            $response['correlationId'] = $raw['correlationId'] 
                ?? $raw['correlation_id'] 
                ?? $raw['trace_id'] 
                ?? $raw['traceId']
                ?? null;
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

        // Codifica o channelId para URL
        $encodedChannelId = rawurlencode($channelId);
        return $this->request('POST', "/api/channels/{$encodedChannelId}/webhook", $payload);
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
     * @param string $channelId ID do canal/sessão
     * @param string $messageId ID da mensagem (ex: false_554796164699@c.us_3EB0...)
     * @return array { success: bool, data?: string (binary), mime_type?: string, error?: string }
     */
    public function downloadMedia(string $channelId, string $messageId): array
    {
        error_log("[WhatsAppGateway::downloadMedia] Iniciando download - channel: {$channelId}, messageId: " . substr($messageId, 0, 50) . "...");
        
        // Endpoint correto do wrapper: GET /api/media/{channel}/{messageId}
        $encodedChannelId = rawurlencode($channelId);
        $encodedMessageId = rawurlencode($messageId);
        $url = $this->baseUrl . "/api/media/{$encodedChannelId}/{$encodedMessageId}";
        
        error_log("[WhatsAppGateway::downloadMedia] URL: {$url}");
        
        $ch = curl_init($url);
        
        $headers = [
            'X-Gateway-Secret: ' . $this->secret,
            'Accept: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120, // Timeout maior para download de mídias
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
            error_log("[WhatsAppGateway::downloadMedia] HTTP Error: {$httpCode}, Response: " . substr($response, 0, 200));
            return [
                'success' => false,
                'error' => "HTTP {$httpCode}",
                'data' => null,
                'mime_type' => null
            ];
        }
        
        // Resposta do wrapper é JSON com base64
        $json = json_decode($response, true);
        
        if (!$json || !isset($json['success'])) {
            error_log("[WhatsAppGateway::downloadMedia] Resposta inválida (não é JSON)");
            return [
                'success' => false,
                'error' => 'Resposta inválida do gateway',
                'data' => null,
                'mime_type' => null
            ];
        }
        
        if (!$json['success']) {
            $errorMsg = $json['error'] ?? 'Erro desconhecido';
            error_log("[WhatsAppGateway::downloadMedia] Gateway retornou erro: {$errorMsg}");
            return [
                'success' => false,
                'error' => $errorMsg,
                'data' => null,
                'mime_type' => null
            ];
        }
        
        // Decodifica base64 para binário
        $base64 = $json['media_base64'] ?? null;
        if (!$base64) {
            error_log("[WhatsAppGateway::downloadMedia] Resposta sem media_base64");
            return [
                'success' => false,
                'error' => 'Resposta sem dados de mídia',
                'data' => null,
                'mime_type' => null
            ];
        }
        
        $binaryData = base64_decode($base64);
        $mimeType = $json['mime_type'] ?? 'application/octet-stream';
        
        // CORREÇÃO: O wppconnectAdapter pode ter codificado JSON como binário
        // Se o "binário" é na verdade JSON com dados de mídia, extrair o áudio real
        if (strlen($binaryData) > 0 && $binaryData[0] === '{') {
            $innerJson = json_decode($binaryData, true);
            if ($innerJson && json_last_error() === JSON_ERROR_NONE) {
                error_log("[WhatsAppGateway::downloadMedia] Detectado JSON aninhado, extraindo dados reais...");
                
                // WPP Connect retorna: {"mimetype": "...", "data": "base64_audio", ...}
                // ou pode ser {"base64": "...", "mimetype": "..."}
                $innerBase64 = $innerJson['data'] ?? $innerJson['base64'] ?? $innerJson['body'] ?? null;
                $innerMime = $innerJson['mimetype'] ?? $innerJson['mimeType'] ?? $innerJson['mime_type'] ?? null;
                
                if ($innerBase64) {
                    $binaryData = base64_decode($innerBase64);
                    if ($innerMime) {
                        $mimeType = $innerMime;
                    }
                    error_log("[WhatsAppGateway::downloadMedia] Extraído áudio do JSON interno! mime={$mimeType}, size=" . strlen($binaryData) . " bytes");
                } else {
                    error_log("[WhatsAppGateway::downloadMedia] JSON interno não contém campo de dados (data/base64/body)");
                }
            }
        }
        
        // Verifica se é áudio OGG válido (começa com "OggS")
        if (strlen($binaryData) >= 4) {
            $header = substr($binaryData, 0, 4);
            if ($header === 'OggS') {
                error_log("[WhatsAppGateway::downloadMedia] Confirmado: arquivo OGG válido");
                $mimeType = 'audio/ogg';
            }
        }
        
        error_log("[WhatsAppGateway::downloadMedia] Download OK! mime={$mimeType}, size=" . strlen($binaryData) . " bytes");
        
        return [
            'success' => true,
            'data' => $binaryData,
            'mime_type' => $mimeType,
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
        if ($this->requestId !== null && $this->requestId !== '') {
            $headers[] = 'X-Request-Id: ' . $this->requestId;
        }
        
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

        $curlStartTime = microtime(true);
        error_log("[WhatsAppGateway::request] Configurando cURL: timeout={$this->timeout}s, method={$method}, endpoint={$endpoint}");
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        
        // Log adicional para áudio
        if (isset($data['type']) && $data['type'] === 'audio') {
            error_log("[WhatsAppGateway::request] Requisição de áudio detectada, timeout={$this->timeout}s");
        }

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $curlExecStartTime = microtime(true);
        $curlExecStartTimestamp = date('Y-m-d H:i:s.u');
        error_log("[WhatsAppGateway::request] Executando curl_exec()... Timestamp: {$curlExecStartTimestamp}");
        if (function_exists('pixelhub_log')) {
            pixelhub_log("[WhatsAppGateway::request] Executando curl_exec()... Timestamp: {$curlExecStartTimestamp}");
        }
        
        $rawResponse = curl_exec($ch);
        
        $curlExecTime = (microtime(true) - $curlExecStartTime) * 1000;
        $curlExecEndTimestamp = date('Y-m-d H:i:s.u');
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        $headerSize = (int) ($curlInfo['header_size'] ?? 0);
        $responseHeadersStr = $headerSize > 0 ? substr((string) $rawResponse, 0, $headerSize) : '';
        $response = $headerSize > 0 ? substr((string) $rawResponse, $headerSize) : (string) $rawResponse;
        curl_close($ch);
        
        $totalTimeSeconds = $curlInfo['total_time'] ?? 0;
        $reqIdUsed = $this->requestId ?? 'N/A';
        $effectiveUrl = $curlInfo['url'] ?? $url;
        $effParsed = parse_url($effectiveUrl);
        $effHost = $effParsed['host'] ?? null;
        $effPort = $effParsed['port'] ?? null;
        if ($effPort === null && isset($effParsed['scheme'])) {
            $effPort = strtolower($effParsed['scheme']) === 'https' ? 443 : 80;
        }
        $connectTimeoutUsed = 10; // CURLOPT_CONNECTTIMEOUT
        error_log("[WhatsAppGateway::request] ROUTE request_id={$reqIdUsed} effective_url={$effectiveUrl} host=" . ($effHost ?? 'N/A') . " port=" . ($effPort ?? 'N/A') . " http_code={$httpCode} content_type=" . ($curlInfo['content_type'] ?? 'N/A') . " primary_ip=" . ($curlInfo['primary_ip'] ?? 'N/A') . " total_time_s=" . round((float)$totalTimeSeconds, 2) . " connect_timeout_s={$connectTimeoutUsed} total_timeout_s={$this->timeout}");
        error_log("[WhatsAppGateway::request] URL={$url} total_time_s=" . round((float)$totalTimeSeconds, 2) . " http_code={$httpCode} X-Request-Id={$reqIdUsed}");
        error_log("[WhatsAppGateway::request] curl_exec() concluído em {$curlExecTime}ms ({$totalTimeSeconds}s)");
        error_log("[WhatsAppGateway::request] Timestamp após curl_exec: {$curlExecEndTimestamp}");
        error_log("[WhatsAppGateway::request] HTTP Code: {$httpCode}");
        error_log("[WhatsAppGateway::request] Total time: {$totalTimeSeconds}s");
        error_log("[WhatsAppGateway::request] Connect time: " . ($curlInfo['connect_time'] ?? 'N/A') . "s");
        error_log("[WhatsAppGateway::request] Start transfer time: " . ($curlInfo['starttransfer_time'] ?? 'N/A') . "s");
        
        if (function_exists('pixelhub_log')) {
            pixelhub_log("[WhatsAppGateway::request] ROUTE request_id={$reqIdUsed} effective_url={$effectiveUrl} host=" . ($effHost ?? 'N/A') . " port=" . ($effPort ?? 'N/A') . " http_code={$httpCode} content_type=" . ($curlInfo['content_type'] ?? 'N/A') . " primary_ip=" . ($curlInfo['primary_ip'] ?? 'N/A') . " total_time_s=" . round((float)$totalTimeSeconds, 2) . " connect_timeout_s={$connectTimeoutUsed} total_timeout_s={$this->timeout}");
            pixelhub_log("[WhatsAppGateway::request] URL={$url} total_time_s=" . round((float)$totalTimeSeconds, 2) . " http_code={$httpCode} X-Request-Id={$reqIdUsed}");
            pixelhub_log("[WhatsAppGateway::request] curl_exec() concluído em {$curlExecTime}ms ({$totalTimeSeconds}s), HTTP {$httpCode}");
        }
        
        // Detecta timeout do Nginx (504) após ~60 segundos
        if ($httpCode === 504 && $totalTimeSeconds >= 58 && $totalTimeSeconds <= 62) {
            $timeoutMsg = "[WhatsAppGateway::request] ⚠️ TIMEOUT DO NGINX DETECTADO: O gateway retornou 504 após {$totalTimeSeconds}s. O timeout do Nginx no servidor do gateway precisa ser aumentado (atualmente está em ~60s, recomendado: 120s ou mais).";
            error_log($timeoutMsg);
            if (function_exists('pixelhub_log')) {
                pixelhub_log($timeoutMsg);
            }
        }
        
        if ($error) {
            error_log("[WhatsAppGateway::request] ❌ cURL Error: {$error}");
            
            // Detecta timeout específico
            if (stripos($error, 'timeout') !== false || stripos($error, 'timed out') !== false) {
                error_log("[WhatsAppGateway::request] ⚠️ TIMEOUT DETECTADO após {$curlExecTime}ms (timeout configurado: {$this->timeout}s)");
                error_log("[WhatsAppGateway::request] Total time: " . ($curlInfo['total_time'] ?? 'N/A') . "s");
                error_log("[WhatsAppGateway::request] Connect time: " . ($curlInfo['connect_time'] ?? 'N/A') . "s");
                error_log("[WhatsAppGateway::request] Start transfer time: " . ($curlInfo['starttransfer_time'] ?? 'N/A') . "s");
                
                return [
                    'success' => false,
                    'error' => "Timeout de {$this->timeout}s excedido ao enviar áudio. O gateway pode estar sobrecarregado ou o arquivo muito grande.",
                    'error_code' => 'TIMEOUT',
                    'raw' => null,
                    'status' => 0,
                    'timeout_info' => [
                        'configured_timeout' => $this->timeout,
                        'actual_time' => $curlExecTime / 1000,
                        'total_time' => $curlInfo['total_time'] ?? null,
                        'connect_time' => $curlInfo['connect_time'] ?? null,
                        'starttransfer_time' => $curlInfo['starttransfer_time'] ?? null
                    ]
                ];
            }
        }

        // LOG TEMPORÁRIO: status code e body bruto (também usa pixelhub_log)
        $statusMsg = "[WhatsAppGateway::request] Response HTTP Status: {$httpCode}";
        error_log($statusMsg);
        if (function_exists('pixelhub_log')) {
            pixelhub_log($statusMsg);
        }
        
        $contentType = $curlInfo['content_type'] ?? 'N/A';
        $contentTypeMsg = "[WhatsAppGateway::request] Content-Type: {$contentType}";
        error_log($contentTypeMsg);
        if (function_exists('pixelhub_log')) {
            pixelhub_log($contentTypeMsg);
        }
        
        $lengthMsg = "[WhatsAppGateway::request] Response length: " . strlen($response) . " bytes";
        error_log($lengthMsg);
        if (function_exists('pixelhub_log')) {
            pixelhub_log($lengthMsg);
        }
        
        $isHtml = stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false;
        if ($httpCode >= 500 || $isHtml) {
            $keyHeaderKeys = ['server', 'via', 'cf-ray', 'x-cache', 'x-served-by', 'x-amz-cf-id'];
            $keyHeaders = [];
            foreach (explode("\n", str_replace("\r\n", "\n", $responseHeadersStr)) as $line) {
                if (strpos($line, ':') !== false) {
                    [$name, $val] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    if (in_array($name, $keyHeaderKeys, true)) {
                        $keyHeaders[$name] = trim($val);
                    }
                }
            }
            $diag = [
                'request_id' => $this->requestId ?? 'N/A',
                'effective_url' => $curlInfo['url'] ?? $url,
                'primary_ip' => $curlInfo['primary_ip'] ?? null,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'total_time' => $totalTimeSeconds,
                'resp_headers' => $keyHeaders,
                'body_preview' => strlen($response) > 200 ? substr($response, 0, 200) . '...' : $response,
            ];
            error_log("[WhatsAppGateway::request] DIAG_504_HTML request_id=" . ($this->requestId ?? 'N/A') . " effective_url=" . ($curlInfo['url'] ?? $url) . " primary_ip=" . ($curlInfo['primary_ip'] ?? 'N/A') . " http_code={$httpCode} content_type={$contentType} total_time_s={$totalTimeSeconds}");
            error_log("[WhatsAppGateway::request] DIAG_504_HTML resp_headers=" . json_encode($keyHeaders, JSON_UNESCAPED_UNICODE));
            error_log("[WhatsAppGateway::request] DIAG_504_HTML body_preview=" . (strlen($response) > 200 ? substr($response, 0, 200) . '...' : $response));
            if (function_exists('pixelhub_log')) {
                pixelhub_log("[WhatsAppGateway::request] DIAG_504_HTML " . json_encode($diag, JSON_UNESCAPED_UNICODE));
            }
        }
        
        if ($error) {
            $curlErrorMsg = "[WhatsAppGateway::request] cURL Error: {$error}";
            error_log($curlErrorMsg);
            if (function_exists('pixelhub_log')) {
                pixelhub_log($curlErrorMsg);
            }
        } else {
            // Log mais detalhado da resposta para debug
            if (strlen($response) > 0) {
                $bodyPreview = strlen($response) > 2000 ? (substr($response, 0, 2000) . '... (truncated, total: ' . strlen($response) . ' bytes)') : $response;
                $bodyMsg = "[WhatsAppGateway::request] Response body completo: " . $bodyPreview;
                error_log($bodyMsg);
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($bodyMsg);
                }
                
                // Detecta se é HTML (página de erro)
                if (stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false) {
                    $htmlWarningMsg = "[WhatsAppGateway::request] ⚠️ ATENÇÃO: Resposta parece ser HTML (página de erro do servidor)";
                    error_log($htmlWarningMsg);
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log($htmlWarningMsg);
                    }
                    // Tenta extrair mensagem de erro do HTML
                    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $response, $matches)) {
                        $titleMsg = "[WhatsAppGateway::request] Título da página HTML: " . $matches[1];
                        error_log($titleMsg);
                        if (function_exists('pixelhub_log')) {
                            pixelhub_log($titleMsg);
                        }
                    }
                    if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $response, $matches)) {
                        $h1Msg = "[WhatsAppGateway::request] H1 da página HTML: " . $matches[1];
                        error_log($h1Msg);
                        if (function_exists('pixelhub_log')) {
                            pixelhub_log($h1Msg);
                        }
                    }
                }
            } else {
                $emptyMsg = "[WhatsAppGateway::request] ⚠️ ATENÇÃO: Resposta vazia (0 bytes)";
                error_log($emptyMsg);
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($emptyMsg);
                }
            }
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

        // Verifica se a resposta está vazia
        if (empty($response)) {
            error_log("[WhatsAppGateway::request] ❌ ERRO: Resposta vazia do gateway (HTTP {$httpCode})");
            return [
                'success' => false,
                'error' => "Gateway retornou resposta vazia (HTTP {$httpCode}). Verifique se o gateway está funcionando corretamente.",
                'error_code' => 'EMPTY_RESPONSE',
                'raw' => null,
                'status' => $httpCode
            ];
        }

        // 401: evidência única para classificar (Basic Auth vs Secret) — sem interpretação
        if ($httpCode === 401) {
            $authHeaderKeys = ['server', 'www-authenticate', 'via', 'cf-ray', 'date'];
            $respHeadersPreview = [];
            foreach (explode("\n", str_replace("\r\n", "\n", $responseHeadersStr)) as $line) {
                if (strpos($line, ':') !== false) {
                    [$name, $val] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    if (in_array($name, $authHeaderKeys, true)) {
                        $respHeadersPreview[$name] = trim($val);
                    }
                }
            }
            $bodyPreviewShort = strlen($response) > 300 ? substr($response, 0, 300) . '...' : $response;
            $secretPresent = !empty($this->secret);
            $secretLen = $secretPresent ? strlen($this->secret) : 0;
            $secretFingerprint = $secretPresent ? substr(hash('sha256', $this->secret), 0, 8) : null;
            error_log("[WhatsAppGateway::request] 401 UNAUTHORIZED resp_headers_preview=" . json_encode($respHeadersPreview, JSON_UNESCAPED_UNICODE) . " body_preview_len=" . strlen($bodyPreviewShort) . " secret_sent present=" . ($secretPresent ? 'true' : 'false') . " len=" . $secretLen . " fingerprint=" . ($secretFingerprint ?? 'N/A'));
            return [
                'success' => false,
                'error' => 'Gateway retornou 401 Unauthorized.',
                'error_code' => 'UNAUTHORIZED',
                'status' => 401,
                'effective_url' => $effectiveUrl ?? $curlInfo['url'] ?? $url,
                'primary_ip' => $curlInfo['primary_ip'] ?? null,
                'http_code' => 401,
                'content_type' => $contentType,
                'resp_headers_preview' => $respHeadersPreview,
                'body_preview' => $bodyPreviewShort,
                'request_id' => $reqIdUsed,
                'secret_sent' => [
                    'present' => $secretPresent,
                    'len' => $secretLen,
                    'fingerprint' => $secretFingerprint,
                ],
            ];
        }

        // Classifica HTML como GATEWAY_HTML_ERROR e retorna estrutura acionável (evita falso WPPCONNECT_TIMEOUT)
        $contentTypeForCheck = $curlInfo['content_type'] ?? '';
        $isHtmlResponse = (stripos($contentTypeForCheck, 'text/html') !== false)
            || (strlen(trim($response)) > 0 && strpos(ltrim($response), '<') === 0);
        if ($isHtmlResponse) {
            $bodyPreviewShort = strlen($response) > 300 ? substr($response, 0, 300) . '...' : $response;
            error_log("[WhatsAppGateway::request] GATEWAY_HTML_ERROR http_code={$httpCode} effective_url=" . ($effectiveUrl ?? $url) . " primary_ip=" . ($curlInfo['primary_ip'] ?? 'N/A') . " request_id={$reqIdUsed}");
            return [
                'success' => false,
                'error' => 'Gateway retornou página HTML em vez de JSON. Possível 504 Gateway Time-out ou erro de proxy.',
                'error_code' => 'GATEWAY_HTML_ERROR',
                'raw' => $response,
                'status' => $httpCode,
                'gateway_html_error' => [
                    'http_code' => $httpCode,
                    'content_type' => $contentTypeForCheck ?: 'N/A',
                    'effective_url' => $effectiveUrl ?? $curlInfo['url'] ?? $url,
                    'primary_ip' => $curlInfo['primary_ip'] ?? null,
                    'request_id' => $reqIdUsed,
                    'body_preview' => $bodyPreviewShort,
                ],
            ];
        }

        // Tenta decodificar JSON
        $decoded = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            $jsonErrorMsg = json_last_error_msg();
            $responsePreview = strlen($response) > 2000 ? substr($response, 0, 2000) . '... (truncated)' : $response;
            
            error_log("[WhatsAppGateway::request] ❌ ERRO JSON: {$jsonErrorMsg} (code: {$jsonError})");
            error_log("[WhatsAppGateway::request] HTTP Status: {$httpCode}");
            error_log("[WhatsAppGateway::request] Content-Type: {$contentType}");
            error_log("[WhatsAppGateway::request] Response length: " . strlen($response) . " bytes");
            error_log("[WhatsAppGateway::request] Response preview (primeiros 2000 chars): {$responsePreview}");
            
            // Detecta tipo de erro comum
            $errorMessage = "Resposta inválida do gateway: {$jsonErrorMsg}";
            $errorCode = 'GATEWAY_ERROR';
            
            if (stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false) {
                $errorMessage = "Gateway retornou página HTML ao invés de JSON. O servidor pode estar com erro interno.";
                $errorCode = 'GATEWAY_HTML_ERROR';
                
                // Tenta extrair mensagem útil do HTML
                $htmlTitle = null;
                if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $response, $matches)) {
                    $htmlTitle = trim($matches[1]);
                    $errorMessage .= " Título da página: " . $htmlTitle;
                }
                
                // Detecta timeout 504 especificamente
                if ($htmlTitle && stripos($htmlTitle, '504') !== false && stripos($htmlTitle, 'timeout') !== false) {
                    $errorCode = 'GATEWAY_TIMEOUT';
                    $errorMessage = "Timeout do gateway (504). O servidor do gateway demorou mais de 60 segundos para processar o áudio. Possíveis causas:\n";
                    $errorMessage .= "- Arquivo de áudio muito grande\n";
                    $errorMessage .= "- Gateway sobrecarregado\n";
                    $errorMessage .= "- Problemas de rede\n";
                    $errorMessage .= "Tente novamente com um áudio menor ou aguarde alguns minutos.";
                }
            } elseif ($httpCode >= 500) {
                $errorMessage = "Erro interno do gateway (HTTP {$httpCode}). O servidor pode estar sobrecarregado.";
                $errorCode = 'GATEWAY_SERVER_ERROR';
            } elseif ($httpCode === 0) {
                $errorMessage = "Não foi possível conectar ao gateway. Verifique se o serviço está online.";
                $errorCode = 'GATEWAY_CONNECTION_ERROR';
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'raw' => $response, // Retorna resposta bruta para debug
                'status' => $httpCode,
                'json_error' => $jsonErrorMsg,
                'response_preview' => substr($response, 0, 500) // Primeiros 500 chars para debug
            ];
        }

        // Normaliza resposta
        $success = $httpCode >= 200 && $httpCode < 300;
        
        // Log detalhado da resposta decodificada
        if (!$success) {
            error_log("[WhatsAppGateway::request] ❌ Resposta de erro do gateway (HTTP {$httpCode})");
            error_log("[WhatsAppGateway::request] Resposta decodificada: " . json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // Tenta extrair todas as possíveis mensagens de erro
            $possibleErrors = [];
            if (isset($decoded['error'])) {
                $possibleErrors[] = "error: " . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
            }
            if (isset($decoded['message'])) {
                $possibleErrors[] = "message: " . (is_string($decoded['message']) ? $decoded['message'] : json_encode($decoded['message']));
            }
            if (isset($decoded['error_message'])) {
                $possibleErrors[] = "error_message: " . (is_string($decoded['error_message']) ? $decoded['error_message'] : json_encode($decoded['error_message']));
            }
            if (isset($decoded['details'])) {
                $possibleErrors[] = "details: " . (is_string($decoded['details']) ? $decoded['details'] : json_encode($decoded['details']));
            }
            if (isset($decoded['data']) && is_array($decoded['data']) && isset($decoded['data']['error'])) {
                $possibleErrors[] = "data.error: " . (is_string($decoded['data']['error']) ? $decoded['data']['error'] : json_encode($decoded['data']['error']));
            }
            
            if (!empty($possibleErrors)) {
                error_log("[WhatsAppGateway::request] Possíveis mensagens de erro encontradas: " . implode(" | ", $possibleErrors));
            } else {
                error_log("[WhatsAppGateway::request] ⚠️ Nenhuma mensagem de erro explícita encontrada na resposta");
                error_log("[WhatsAppGateway::request] Chaves disponíveis na resposta: " . (is_array($decoded) ? implode(', ', array_keys($decoded)) : 'N/A'));
            }
        }

        return [
            'success' => $success,
            'status' => $httpCode,
            'raw' => $decoded,
            'error' => $success ? null : ($decoded['error'] ?? $decoded['message'] ?? $decoded['error_message'] ?? $decoded['details'] ?? ($decoded['data']['error'] ?? null) ?? "HTTP {$httpCode}")
        ];
    }
}

