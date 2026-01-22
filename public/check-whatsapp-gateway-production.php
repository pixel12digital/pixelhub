<?php

/**
 * Script de verificação para produção - WhatsApp Gateway
 * 
 * Acesse: https://hub.pixel12digital.com.br/public/check-whatsapp-gateway-production.php
 * 
 * Verifica se todos os arquivos e configurações do WhatsApp Gateway estão presentes
 * 
 * NOTA: Este arquivo precisa estar acessível diretamente (antes do .htaccess redirecionar)
 * Se não funcionar, use a rota /settings/whatsapp-gateway/check diretamente no sistema
 */

// Se for acessado via rota do sistema, o index.php já fez o setup
// Se acessado diretamente, fazemos o setup básico
if (!defined('BASE_PATH')) {
    // Carrega autoload básico
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    } else {
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
    }
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificação WhatsApp Gateway - Produção</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #023A8D; 
            border-bottom: 3px solid #023A8D;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            border-left: 4px solid #023A8D;
            padding-left: 10px;
        }
        .ok { 
            color: green; 
            font-weight: bold;
        }
        .error { 
            color: red; 
            font-weight: bold;
        }
        .warning {
            color: orange;
            font-weight: bold;
        }
        .info { 
            color: blue; 
        }
        pre { 
            background: #f5f5f5; 
            padding: 15px; 
            border-radius: 5px;
            border-left: 4px solid #023A8D;
            overflow-x: auto;
        }
        .check-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #ddd;
            padding-left: 15px;
        }
        .check-item.ok { border-left-color: green; }
        .check-item.error { border-left-color: red; }
        .check-item.warning { border-left-color: orange; }
        .summary {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #023A8D;
        }
        .summary h3 {
            margin-top: 0;
            color: #023A8D;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
<div class=\"container\">";

echo "<h1>🔍 Verificação WhatsApp Gateway - Produção</h1>\n";
echo "<p>Este script verifica se todos os arquivos e configurações necessários para o WhatsApp Gateway estão presentes em produção.</p>\n";

$checks = [];
$errors = [];
$warnings = [];

// ============================================
// 1. Verificar arquivos essenciais
// ============================================
echo "<h2>1. Arquivos Essenciais</h2>\n";

$requiredFiles = [
    'src/Controllers/WhatsAppGatewaySettingsController.php' => 'Controller principal de configurações',
    'src/Controllers/WhatsAppGatewayTestController.php' => 'Controller de testes',
    'src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php' => 'Cliente do gateway',
    'views/settings/whatsapp_gateway.php' => 'View de configurações',
    'views/settings/whatsapp_gateway_test.php' => 'View de testes',
];

foreach ($requiredFiles as $file => $description) {
    $fullPath = __DIR__ . '/../' . $file;
    $exists = file_exists($fullPath);
    
    if ($exists) {
        $checks[] = ['type' => 'ok', 'message' => "✅ {$description}: <code>{$file}</code>"];
        echo "<div class=\"check-item ok\">✅ {$description}: <code>{$file}</code></div>\n";
    } else {
        $error = "❌ {$description}: <code>{$file}</code> NÃO ENCONTRADO";
        $checks[] = ['type' => 'error', 'message' => $error];
        $errors[] = $error;
        echo "<div class=\"check-item error\">{$error}</div>\n";
    }
}

// ============================================
// 2. Verificar rotas no index.php
// ============================================
echo "<h2>2. Rotas Registradas</h2>\n";

$indexPath = __DIR__ . '/index.php';
if (file_exists($indexPath)) {
    $indexContent = file_get_contents($indexPath);
    
    $requiredRoutes = [
        '/settings/whatsapp-gateway' => 'Rota principal de configurações',
        '/settings/whatsapp-gateway/test' => 'Rota de testes',
        'WhatsAppGatewaySettingsController' => 'Controller de configurações referenciado',
        'WhatsAppGatewayTestController' => 'Controller de testes referenciado',
    ];
    
    foreach ($requiredRoutes as $search => $description) {
        if (strpos($indexContent, $search) !== false) {
            $checks[] = ['type' => 'ok', 'message' => "✅ {$description}: encontrada em index.php"];
            echo "<div class=\"check-item ok\">✅ {$description}: encontrada em <code>index.php</code></div>\n";
        } else {
            $error = "❌ {$description}: NÃO encontrada em index.php";
            $checks[] = ['type' => 'error', 'message' => $error];
            $errors[] = $error;
            echo "<div class=\"check-item error\">{$error}</div>\n";
        }
    }
} else {
    $error = "❌ Arquivo index.php não encontrado!";
    $checks[] = ['type' => 'error', 'message' => $error];
    $errors[] = $error;
    echo "<div class=\"check-item error\">{$error}</div>\n";
}

// ============================================
// 3. Verificar menu no layout
// ============================================
echo "<h2>3. Menu de Navegação</h2>\n";

$layoutPath = __DIR__ . '/../views/layout/main.php';
if (file_exists($layoutPath)) {
    $layoutContent = file_get_contents($layoutPath);
    
    if (strpos($layoutContent, '/settings/whatsapp-gateway') !== false) {
        $checks[] = ['type' => 'ok', 'message' => '✅ Link do WhatsApp Gateway encontrado no menu'];
        echo "<div class=\"check-item ok\">✅ Link do WhatsApp Gateway encontrado no menu (main.php)</div>\n";
    } else {
        $error = "❌ Link do WhatsApp Gateway NÃO encontrado no menu!";
        $checks[] = ['type' => 'error', 'message' => $error];
        $errors[] = $error;
        echo "<div class=\"check-item error\">{$error}</div>\n";
    }
    
    if (strpos($layoutContent, 'WhatsApp Gateway') !== false) {
        $checks[] = ['type' => 'ok', 'message' => '✅ Texto "WhatsApp Gateway" encontrado no menu'];
        echo "<div class=\"check-item ok\">✅ Texto \"WhatsApp Gateway\" encontrado no menu</div>\n";
    } else {
        $warning = "⚠️ Texto \"WhatsApp Gateway\" não encontrado no menu (pode estar usando outra descrição)";
        $checks[] = ['type' => 'warning', 'message' => $warning];
        $warnings[] = $warning;
        echo "<div class=\"check-item warning\">{$warning}</div>\n";
    }
} else {
    $error = "❌ Arquivo views/layout/main.php não encontrado!";
    $checks[] = ['type' => 'error', 'message' => $error];
    $errors[] = $error;
    echo "<div class=\"check-item error\">{$error}</div>\n";
}

// ============================================
// 4. Verificar dependências
// ============================================
echo "<h2>4. Dependências e Classes</h2>\n";

$dependencies = [
    'PixelHub\\Core\\CryptoHelper' => 'Classe CryptoHelper (para criptografia do secret)',
    'PixelHub\\Core\\Env' => 'Classe Env (para variáveis de ambiente)',
    'PixelHub\\Core\\Auth' => 'Classe Auth (para autenticação)',
];

foreach ($dependencies as $class => $description) {
    if (class_exists($class) || (function_exists('class_exists') && class_exists($class))) {
        $checks[] = ['type' => 'ok', 'message' => "✅ {$description}: disponível"];
        echo "<div class=\"check-item ok\">✅ {$description}: disponível</div>\n";
    } else {
        // Tenta carregar manualmente
        try {
            if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            } else {
                spl_autoload_register(function ($className) {
                    $prefix = 'PixelHub\\';
                    $baseDir = __DIR__ . '/../src/';
                    $len = strlen($prefix);
                    if (strncmp($prefix, $className, $len) !== 0) {
                        return;
                    }
                    $relativeClass = substr($className, $len);
                    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
                    if (file_exists($file)) {
                        require $file;
                    }
                });
            }
            
            if (class_exists($class)) {
                $checks[] = ['type' => 'ok', 'message' => "✅ {$description}: disponível (carregado via autoload)"];
                echo "<div class=\"check-item ok\">✅ {$description}: disponível (carregado via autoload)</div>\n";
            } else {
                $error = "❌ {$description}: NÃO encontrada";
                $checks[] = ['type' => 'error', 'message' => $error];
                $errors[] = $error;
                echo "<div class=\"check-item error\">{$error}</div>\n";
            }
        } catch (\Exception $e) {
            $error = "❌ {$description}: Erro ao verificar - " . $e->getMessage();
            $checks[] = ['type' => 'error', 'message' => $error];
            $errors[] = $error;
            echo "<div class=\"check-item error\">{$error}</div>\n";
        }
    }
}

// ============================================
// 5. Verificar .env (se existir)
// ============================================
echo "<h2>5. Configurações do Ambiente (.env)</h2>\n";

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    
    if (strpos($envContent, 'WPP_GATEWAY_BASE_URL') !== false) {
        $checks[] = ['type' => 'ok', 'message' => '✅ Variável WPP_GATEWAY_BASE_URL encontrada no .env'];
        echo "<div class=\"check-item ok\">✅ Variável WPP_GATEWAY_BASE_URL encontrada no .env</div>\n";
    } else {
        $warning = "⚠️ Variável WPP_GATEWAY_BASE_URL não encontrada no .env (será usado valor padrão)";
        $checks[] = ['type' => 'warning', 'message' => $warning];
        $warnings[] = $warning;
        echo "<div class=\"check-item warning\">{$warning}</div>\n";
    }
    
    if (strpos($envContent, 'WPP_GATEWAY_SECRET') !== false) {
        $checks[] = ['type' => 'ok', 'message' => '✅ Variável WPP_GATEWAY_SECRET encontrada no .env'];
        echo "<div class=\"check-item ok\">✅ Variável WPP_GATEWAY_SECRET encontrada no .env</div>\n";
    } else {
        $info = "ℹ️ Variável WPP_GATEWAY_SECRET não encontrada (será configurada na primeira vez)";
        $checks[] = ['type' => 'info', 'message' => $info];
        echo "<div class=\"check-item info\">{$info}</div>\n";
    }
} else {
    $warning = "⚠️ Arquivo .env não encontrado (pode ser normal se usando variáveis de ambiente do servidor)";
    $checks[] = ['type' => 'warning', 'message' => $warning];
    $warnings[] = $warning;
    echo "<div class=\"check-item warning\">{$warning}</div>\n";
}

// ============================================
// Resumo
// ============================================
echo "<div class=\"summary\">";
echo "<h3>📊 Resumo da Verificação</h3>\n";

$okCount = count(array_filter($checks, fn($c) => $c['type'] === 'ok'));
$errorCount = count($errors);
$warningCount = count($warnings);

echo "<p><strong>Total de verificações:</strong> " . count($checks) . "</p>\n";
echo "<p class=\"ok\">✅ Sucesso: {$okCount}</p>\n";
if ($warningCount > 0) {
    echo "<p class=\"warning\">⚠️ Avisos: {$warningCount}</p>\n";
}
if ($errorCount > 0) {
    echo "<p class=\"error\">❌ Erros: {$errorCount}</p>\n";
}

if ($errorCount === 0) {
    echo "<p class=\"ok\" style=\"font-size: 18px; margin-top: 20px;\">✅ <strong>Todos os arquivos essenciais estão presentes!</strong></p>\n";
    echo "<p>Se ainda não estiver vendo o WhatsApp Gateway no menu, pode ser:</p>\n";
    echo "<ul>\n";
    echo "<li>Cache do navegador - limpe o cache ou use Ctrl+F5</li>\n";
    echo "<li>Cache do servidor - reinicie o servidor web ou limpe opcache do PHP</li>\n";
    echo "<li>Permissões de arquivo - verifique se os arquivos têm permissões corretas</li>\n";
    echo "</ul>\n";
} else {
    echo "<p class=\"error\" style=\"font-size: 18px; margin-top: 20px;\">❌ <strong>Encontrados {$errorCount} erro(s) que precisam ser corrigidos!</strong></p>\n";
    echo "<p><strong>Arquivos faltando:</strong></p>\n";
    echo "<ul>\n";
    foreach ($errors as $error) {
        echo "<li class=\"error\">" . strip_tags($error) . "</li>\n";
    }
    echo "</ul>\n";
    echo "<p><strong>Ação necessária:</strong> Faça upload dos arquivos faltantes do ambiente local para produção.</p>\n";
}

echo "</div>";

// ============================================
// Instruções
// ============================================
echo "<h2>📝 Instruções para Sincronização</h2>\n";
echo "<pre>";
echo "Para sincronizar os arquivos do ambiente local para produção:\n\n";
echo "1. Verifique se os seguintes arquivos existem em produção:\n";
foreach (array_keys($requiredFiles) as $file) {
    echo "   - {$file}\n";
}
echo "\n2. Se algum arquivo estiver faltando, faça upload via FTP/SFTP ou Git:\n";
echo "   - src/Controllers/WhatsAppGatewaySettingsController.php\n";
echo "   - src/Controllers/WhatsAppGatewayTestController.php\n";
echo "   - src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php\n";
echo "   - views/settings/whatsapp_gateway.php\n";
echo "   - views/settings/whatsapp_gateway_test.php\n";
echo "\n3. Verifique se as rotas estão em public/index.php (linhas 509-519)\n";
echo "\n4. Verifique se o menu está em views/layout/main.php (linhas 470-471)\n";
echo "\n5. Limpe cache do navegador e servidor após fazer upload\n";
echo "</pre>";

echo "</div></body></html>";

