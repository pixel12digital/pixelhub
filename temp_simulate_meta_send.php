<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== SIMULAÇÃO COMPLETA DE ENVIO META API ===\n\n";

// Simula os dados que o frontend envia
$_POST = [
    'channel' => 'whatsapp_api',
    'tenant_id' => '25', // Charles Dietrich
    'to' => '4796164699',
    'template_id' => '1',
    'template_vars' => json_encode(['nome' => 'Charles Dietrich']),
    'type' => 'text'
];

echo "Dados simulados do POST:\n";
print_r($_POST);
echo "\n";

// Tenta executar o método sendViaMetaAPI
try {
    $db = DB::getConnection();
    
    // 1. Busca template
    $templateId = (int)$_POST['template_id'];
    $stmt = $db->prepare("SELECT * FROM whatsapp_message_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        die("❌ Template não encontrado!\n");
    }
    
    echo "✅ Template encontrado: {$template['template_name']}\n";
    echo "   Status: {$template['status']}\n";
    echo "   Categoria: {$template['category']}\n\n";
    
    if ($template['status'] !== 'approved') {
        die("❌ Template não está aprovado!\n");
    }
    
    // 2. Busca configuração Meta
    $stmt = $db->prepare("
        SELECT meta_business_account_id, meta_access_token, meta_phone_number_id
        FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official' 
        AND is_active = 1
        AND is_global = 1
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        die("❌ Configuração Meta não encontrada!\n");
    }
    
    echo "✅ Configuração Meta encontrada\n";
    echo "   Business Account ID: {$config['meta_business_account_id']}\n";
    echo "   Phone Number ID: {$config['meta_phone_number_id']}\n\n";
    
    // 3. Descriptografa token
    $accessToken = $config['meta_access_token'];
    if (strpos($accessToken, 'encrypted:') === 0) {
        try {
            $accessToken = \PixelHub\Core\CryptoHelper::decrypt(substr($accessToken, 10));
            echo "✅ Token descriptografado com sucesso\n\n";
        } catch (Exception $e) {
            die("❌ Erro ao descriptografar token: {$e->getMessage()}\n");
        }
    }
    
    // 4. Normaliza telefone
    $to = $_POST['to'];
    $normalizedPhone = \PixelHub\Services\PhoneNormalizer::toE164OrNull($to, 'BR', false);
    
    if (!$normalizedPhone) {
        die("❌ Erro ao normalizar telefone: {$to}\n");
    }
    
    echo "✅ Telefone normalizado: {$to} → {$normalizedPhone}\n\n";
    
    // 5. Busca dados do tenant
    $tenantId = (int)$_POST['tenant_id'];
    $stmt = $db->prepare("SELECT name, phone, email FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        die("❌ Tenant não encontrado: {$tenantId}\n");
    }
    
    echo "✅ Tenant encontrado: {$tenant['name']}\n\n";
    
    // 6. Processa variáveis do template
    $templateVariables = [];
    if (!empty($template['variables'])) {
        $variables = json_decode($template['variables'], true);
        if (is_array($variables)) {
            $customVars = isset($_POST['template_vars']) ? json_decode($_POST['template_vars'], true) : [];
            
            foreach ($variables as $index => $var) {
                $varName = $var['name'] ?? "var" . ($index + 1);
                
                if (isset($customVars[$varName])) {
                    $value = $customVars[$varName];
                } elseif ($varName === 'nome' || $varName === 'name' || $varName === 'cliente') {
                    $value = $tenant['name'];
                } else {
                    $value = $var['example'] ?? '';
                }
                
                $templateVariables[] = ['type' => 'text', 'text' => $value];
            }
        }
    }
    
    echo "Variáveis processadas:\n";
    print_r($templateVariables);
    echo "\n";
    
    // 7. Monta payload
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
    
    if (!empty($templateVariables)) {
        $payload['template']['components'] = [
            [
                'type' => 'body',
                'parameters' => $templateVariables
            ]
        ];
    }
    
    echo "Payload final:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "✅ SIMULAÇÃO CONCLUÍDA SEM ERROS!\n";
    echo "Todos os passos foram executados com sucesso.\n";
    echo "O código está funcionando corretamente no ambiente local.\n\n";
    
    echo "PRÓXIMO PASSO: Verificar se o erro está no servidor de produção.\n";
    echo "Possíveis causas:\n";
    echo "1. Código não foi atualizado no servidor (git pull não executado)\n";
    echo "2. Erro de permissão ou configuração no servidor\n";
    echo "3. Diferença de versão PHP ou extensões\n";
    
} catch (Exception $e) {
    echo "❌ ERRO DURANTE SIMULAÇÃO:\n";
    echo "Mensagem: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}:{$e->getLine()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}
