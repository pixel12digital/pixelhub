<?php
// Endpoint temporário para testar envio Meta API
// Acesse: POST /test_meta_send.php

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Auth;
use PixelHub\Services\MetaTemplateService;
use PixelHub\Services\PhoneNormalizer;
use PixelHub\Core\CryptoHelper;

header('Content-Type: application/json');

try {
    // Verifica autenticação
    session_start();
    Auth::requireInternal();
    
    error_log("[TEST_META_SEND] Iniciando teste de envio Meta API");
    error_log("[TEST_META_SEND] POST: " . json_encode($_POST));
    
    $templateId = (int)($_POST['template_id'] ?? 0);
    $to = $_POST['to'] ?? '';
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    
    if (!$templateId || !$to || !$tenantId) {
        echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
        exit;
    }
    
    $db = DB::getConnection();
    
    // 1. Busca template
    $template = MetaTemplateService::getById($templateId);
    if (!$template || $template['status'] !== 'approved') {
        echo json_encode(['success' => false, 'error' => 'Template não encontrado ou não aprovado']);
        exit;
    }
    
    // 2. Busca config Meta
    $stmt = $db->prepare("
        SELECT meta_business_account_id, meta_access_token, meta_phone_number_id
        FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official' 
        AND is_active = 1
        AND is_global = 1
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config) {
        echo json_encode(['success' => false, 'error' => 'Configuração Meta não encontrada']);
        exit;
    }
    
    // 3. Descriptografa token
    $accessToken = $config['meta_access_token'];
    if (strpos($accessToken, 'encrypted:') === 0) {
        $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
    }
    
    // 4. Normaliza telefone
    $normalizedPhone = PhoneNormalizer::toE164OrNull($to, 'BR', false);
    if (!$normalizedPhone) {
        echo json_encode(['success' => false, 'error' => 'Telefone inválido']);
        exit;
    }
    
    // 5. Monta payload
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $normalizedPhone,
        'type' => 'template',
        'template' => [
            'name' => $template['template_name'],
            'language' => [
                'code' => $template['language']
            ]
        ]
    ];
    
    // 6. Envia para Meta API
    $url = "https://graph.facebook.com/v18.0/{$config['meta_phone_number_id']}/messages";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("[TEST_META_SEND] HTTP Code: {$httpCode}");
    error_log("[TEST_META_SEND] Response: {$response}");
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && isset($result['messages'][0]['id'])) {
        echo json_encode([
            'success' => true,
            'message_id' => $result['messages'][0]['id'],
            'phone' => $normalizedPhone,
            'template' => $template['template_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error']['message'] ?? 'Erro desconhecido',
            'http_code' => $httpCode,
            'response' => $result
        ]);
    }
    
} catch (Exception $e) {
    error_log("[TEST_META_SEND] EXCEÇÃO: " . $e->getMessage());
    error_log("[TEST_META_SEND] Stack: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
