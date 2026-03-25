<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

/**
 * Serviço para validação de números de telefone via API Whapi.Cloud
 */
class PhoneValidationService
{
    private $db;
    
    public function __construct()
    {
        $this->db = DB::getConnection();
    }
    
    /**
     * Valida um número de telefone via API Whapi.Cloud
     */
    public function validatePhoneNumber(string $phone, string $sessionName): array
    {
        // Pegar token da sessão
        $stmt = $this->db->prepare("
            SELECT whapi_api_token 
            FROM whatsapp_provider_configs 
            WHERE provider_type = 'whapi' AND session_name = ? AND is_active = 1
        ");
        $stmt->execute([$sessionName]);
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$config || !$config['whapi_api_token']) {
            return [
                'valid' => false,
                'status' => 'error',
                'error' => 'Configuração não encontrada para sessão: ' . $sessionName
            ];
        }
        
        // Descriptografar token
        $apiToken = $config['whapi_api_token'];
        if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
            $token = CryptoHelper::decrypt(substr($apiToken, 10));
        } else {
            $token = $apiToken;
        }
        
        // Fazer requisição para API
        $url = "https://gate.whapi.cloud/contacts";
        $data = [
            'contacts' => [$phone]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'valid' => false,
                'status' => 'error',
                'error' => 'Erro na requisição: ' . $curlError
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'valid' => false,
                'status' => 'error',
                'error' => 'HTTP ' . $httpCode . ': ' . substr($response, 0, 100)
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['contacts'][0])) {
            return [
                'valid' => false,
                'status' => 'error',
                'error' => 'Resposta inválida da API'
            ];
        }
        
        $contact = $responseData['contacts'][0];
        $status = $contact['status'] ?? 'invalid';
        
        return [
            'valid' => $status === 'valid',
            'status' => $status,
            'phone' => $contact['input'] ?? $phone,
            'response' => $contact
        ];
    }
    
    /**
     * Atualiza status de validação no job SDR
     */
    public function updateValidationStatus(int $jobId, array $validation): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE sdr_dispatch_queue 
                SET 
                    phone_validated = ?,
                    phone_validation_status = ?,
                    phone_validated_at = NOW()
                WHERE id = ?
            ");
            
            $validated = $validation['valid'] ? 1 : 0;
            $status = $validation['status'] ?? 'error';
            
            return $stmt->execute([$validated, $status, $jobId]);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar validação do job {$jobId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca job como failed devido a número inválido
     */
    public function markAsInvalidNumber(int $jobId, string $reason): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE sdr_dispatch_queue 
                SET 
                    status = 'failed',
                    error = ?,
                    failed_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([$reason, $jobId]);
        } catch (\Exception $e) {
            error_log("Erro ao marcar job {$jobId} como failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valida e atualiza múltiplos jobs em lote
     */
    public function validateBatch(array $jobIds): array
    {
        $results = [];
        
        // Buscar jobs
        $placeholders = str_repeat('?,', count($jobIds) - 1) . '?';
        $stmt = $this->db->prepare("
            SELECT id, phone, session_name 
            FROM sdr_dispatch_queue 
            WHERE id IN ({$placeholders}) AND phone_validated IS NULL
        ");
        $stmt->execute($jobIds);
        $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($jobs as $job) {
            $validation = $this->validatePhoneNumber($job['phone'], $job['session_name']);
            
            // Atualizar status
            $this->updateValidationStatus($job['id'], $validation);
            
            // Se inválido, marcar como failed
            (!$validation['valid']) && $this->markAsInvalidNumber(
                $job['id'], 
                'Número sem WhatsApp: ' . $validation['status']
            );
            
            $results[] = [
                'job_id' => $job['id'],
                'phone' => $job['phone'],
                'validation' => $validation
            ];
            
            // Pequena pausa para não sobrecarregar a API
            usleep(500000); // 0.5 segundo
        }
        
        return $results;
    }
}
