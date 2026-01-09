<?php

/**
 * Script de Teste - Integração WhatsApp Gateway
 * 
 * Testa o envio de mensagens via gateway pelo painel
 * 
 * Uso: php database/test-whatsapp-gateway.php
 */

// Autoload manual (mesmo padrão do index.php)
spl_autoload_register(function ($class) {
    $prefix = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Carrega configurações
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\WhatsAppBillingService;

// Carrega .env
try {
    Env::load(__DIR__ . '/../.env');
} catch (\Exception $e) {
    // Tenta carregar de variáveis de ambiente do sistema
    echo "Aviso: Não foi possível carregar .env: " . $e->getMessage() . "\n";
    echo "Usando variáveis de ambiente do sistema...\n\n";
}

echo "========================================\n";
echo "Teste de Integração WhatsApp Gateway\n";
echo "========================================\n\n";

// 1. Verificar variáveis de ambiente
echo "1. Verificando variáveis de ambiente...\n";
$baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
$secret = Env::get('WPP_GATEWAY_SECRET', '');

echo "   Base URL: {$baseUrl}\n";
if (empty($secret)) {
    echo "   ❌ WPP_GATEWAY_SECRET não configurado!\n";
    echo "   Configure no arquivo .env ou variáveis de ambiente\n";
    exit(1);
} else {
    echo "   ✅ WPP_GATEWAY_SECRET configurado (tamanho: " . strlen($secret) . " caracteres)\n";
}
echo "\n";

// 2. Verificar conexão com banco
echo "2. Verificando conexão com banco de dados...\n";
try {
    $db = DB::getConnection();
    echo "   ✅ Conexão com banco estabelecida\n";
} catch (\Exception $e) {
    echo "   ❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// 3. Verificar se tabela tenant_message_channels existe
echo "3. Verificando estrutura do banco...\n";
try {
    $checkStmt = $db->query("SHOW TABLES LIKE 'tenant_message_channels'");
    if ($checkStmt->rowCount() === 0) {
        echo "   ❌ Tabela tenant_message_channels não existe!\n";
        echo "   Execute a migration: 20250201_create_tenant_message_channels_table.php\n";
        exit(1);
    }
    echo "   ✅ Tabela tenant_message_channels existe\n";
} catch (\Exception $e) {
    echo "   ❌ Erro ao verificar tabela: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// 4. Listar tenants com channels configurados
echo "4. Buscando tenants com channels WhatsApp configurados...\n";
try {
    $stmt = $db->prepare("
        SELECT 
            tmc.id,
            tmc.tenant_id,
            tmc.channel_id,
            tmc.is_enabled,
            tmc.webhook_configured,
            t.name as tenant_name,
            t.phone as tenant_phone
        FROM tenant_message_channels tmc
        INNER JOIN tenants t ON tmc.tenant_id = t.id
        WHERE tmc.provider = 'wpp_gateway'
        AND tmc.is_enabled = 1
        ORDER BY tmc.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($channels)) {
        echo "   ⚠️  Nenhum tenant com channel WhatsApp configurado encontrado\n";
        echo "   Para configurar um channel:\n";
        echo "   1. Crie um channel no gateway via API\n";
        echo "   2. Insira registro em tenant_message_channels\n";
        echo "   3. Configure o webhook\n\n";
        
        // Pergunta se quer criar um teste
        echo "   Deseja testar apenas a conexão com o gateway? (s/n): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) !== 's') {
            exit(0);
        }
        
        // Teste básico de conexão
        echo "\n5. Testando conexão com gateway...\n";
        try {
            $gateway = new WhatsAppGatewayClient();
            $result = $gateway->listChannels();
            
            if ($result['success']) {
                echo "   ✅ Conexão com gateway estabelecida\n";
                echo "   Status HTTP: {$result['status']}\n";
                if (isset($result['raw']['channels'])) {
                    echo "   Canais encontrados: " . count($result['raw']['channels']) . "\n";
                }
            } else {
                echo "   ❌ Erro ao conectar com gateway: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
                echo "   Status HTTP: " . ($result['status'] ?? 'N/A') . "\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Exceção ao conectar: " . $e->getMessage() . "\n";
        }
        
        exit(0);
    } else {
        echo "   ✅ Encontrados " . count($channels) . " tenant(s) com channel configurado:\n\n";
        foreach ($channels as $index => $channel) {
            echo "   [" . ($index + 1) . "] Tenant: {$channel['tenant_name']} (ID: {$channel['tenant_id']})\n";
            echo "       Channel ID: {$channel['channel_id']}\n";
            echo "       Telefone: " . ($channel['tenant_phone'] ?? 'N/A') . "\n";
            echo "       Webhook configurado: " . ($channel['webhook_configured'] ? 'Sim' : 'Não') . "\n";
            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao buscar channels: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// 5. Selecionar channel para teste
echo "5. Selecionando channel para teste...\n";
$selectedChannel = $channels[0]; // Usa o primeiro
echo "   ✅ Selecionado: {$selectedChannel['tenant_name']} (Channel: {$selectedChannel['channel_id']})\n";
echo "\n";

// 6. Testar conexão com gateway
echo "6. Testando conexão com gateway...\n";
try {
    $gateway = new WhatsAppGatewayClient();
    $result = $gateway->listChannels();
    
    if ($result['success']) {
        echo "   ✅ Conexão com gateway estabelecida\n";
        echo "   Status HTTP: {$result['status']}\n";
    } else {
        echo "   ❌ Erro ao conectar com gateway: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
        echo "   Status HTTP: " . ($result['status'] ?? 'N/A') . "\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ❌ Exceção ao conectar: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// 7. Verificar status do channel
echo "7. Verificando status do channel...\n";
try {
    $result = $gateway->getChannel($selectedChannel['channel_id']);
    
    if ($result['success']) {
        echo "   ✅ Channel encontrado no gateway\n";
        $channelData = $result['raw'] ?? [];
        if (isset($channelData['status'])) {
            echo "   Status: {$channelData['status']}\n";
        }
        if (isset($channelData['connected'])) {
            echo "   Conectado: " . ($channelData['connected'] ? 'Sim' : 'Não') . "\n";
        }
    } else {
        echo "   ⚠️  Channel não encontrado ou erro: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Erro ao verificar channel: " . $e->getMessage() . "\n";
}
echo "\n";

// 8. Preparar teste de envio
echo "8. Preparando teste de envio...\n";
$testPhone = $selectedChannel['tenant_phone'] ?? null;

if (empty($testPhone)) {
    echo "   ⚠️  Tenant não tem telefone cadastrado\n";
    echo "   Digite um número de telefone para teste (formato: 5511999999999): ";
    $handle = fopen("php://stdin", "r");
    $testPhone = trim(fgets($handle));
    fclose($handle);
}

$phoneNormalized = WhatsAppBillingService::normalizePhone($testPhone);
if (empty($phoneNormalized)) {
    echo "   ❌ Telefone inválido: {$testPhone}\n";
    exit(1);
}

echo "   ✅ Telefone normalizado: {$phoneNormalized}\n";
echo "\n";

// 9. Confirmar envio
echo "9. Confirmação de envio de teste\n";
echo "   Channel: {$selectedChannel['channel_id']}\n";
echo "   Para: {$phoneNormalized}\n";
echo "   Mensagem: 'Teste de integração WhatsApp Gateway - PixelHub'\n";
echo "\n";
echo "   Deseja enviar a mensagem de teste? (s/n): ";
$handle = fopen("php://stdin", "r");
$confirm = trim(fgets($handle));
fclose($handle);

if (strtolower($confirm) !== 's') {
    echo "\n   Teste cancelado pelo usuário.\n";
    exit(0);
}

// 10. Enviar mensagem
echo "\n10. Enviando mensagem via gateway...\n";
try {
    $testMessage = "Teste de integração WhatsApp Gateway - PixelHub\n\nEsta é uma mensagem de teste enviada automaticamente pelo sistema.\n\nData: " . date('d/m/Y H:i:s');
    
    $result = $gateway->sendText(
        $selectedChannel['channel_id'],
        $phoneNormalized,
        $testMessage,
        [
            'test' => true,
            'source' => 'test_script',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    );
    
    if ($result['success']) {
        echo "   ✅ Mensagem enviada com sucesso!\n";
        echo "   Message ID: " . ($result['message_id'] ?? 'N/A') . "\n";
        echo "   Status HTTP: {$result['status']}\n";
        
        if (isset($result['raw'])) {
            echo "\n   Resposta completa do gateway:\n";
            echo "   " . json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "   ❌ Erro ao enviar mensagem\n";
        echo "   Erro: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
        echo "   Status HTTP: " . ($result['status'] ?? 'N/A') . "\n";
        
        if (isset($result['raw'])) {
            echo "\n   Resposta do gateway:\n";
            echo "   " . json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Exceção ao enviar: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo "   " . $e->getTraceAsString() . "\n";
}

echo "\n";
echo "========================================\n";
echo "Teste concluído!\n";
echo "========================================\n";

