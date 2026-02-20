<?php
// Teste direto da rota /settings/tracking-codes
error_log("=== TESTE DIRETO DA ROTA /settings/tracking-codes === " . date('Y-m-d H:i:s'));

echo "<h1>TESTE DA ROTA /settings/tracking-codes</h1>";

// 1. Verifica se a rota está registrada
echo "<h2>1. Verificação da Rota</h2>";

$indexContent = file_get_contents(__DIR__ . '/public/index.php');

if (strpos($indexContent, "'/settings/tracking-codes'") !== false) {
    echo "<p>✅ Rota '/settings/tracking-codes' encontrada no index.php</p>";
    
    // Extrai a linha específica
    $linhas = file($indexContent);
    foreach ($linhas as $num => $linha) {
        if (strpos($linha, '/settings/tracking-codes') !== false) {
            echo "<p><strong>Linha " . ($num + 1) . ":</strong> " . htmlspecialchars($linha) . "</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Rota '/settings/tracking-codes' NÃO encontrada</p>";
}

// 2. Verifica se o controller existe
echo "<h2>2. Verificação do Controller</h2>";

$controllerFile = __DIR__ . '/src/Controllers/TrackingCodesController.php';

if (file_exists($controllerFile)) {
    echo "<p>✅ TrackingCodesController.php existe</p>";
    
    // Verifica sintaxe
    $output = [];
    $return_var = 0;
    exec("php -l \"$controllerFile\" 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "<p>✅ Sintaxe do controller OK</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro de sintaxe no controller:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    }
    
    // Verifica se a classe existe
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        
        if (class_exists('PixelHub\Controllers\TrackingCodesController')) {
            echo "<p>✅ Classe TrackingCodesController existe</p>";
            
            // Testa instanciar
            try {
                $controller = new \PixelHub\Controllers\TrackingCodesController();
                echo "<p>✅ Controller instanciado com sucesso</p>";
                
                // Testa se o método index existe
                if (method_exists($controller, 'index')) {
                    echo "<p>✅ Método index() existe</p>";
                    
                    // Testa executar o método
                    try {
                        ob_start();
                        $controller->index();
                        $output = ob_get_clean();
                        
                        if (!empty($output)) {
                            echo "<p>✅ Método index() executou - " . strlen($output) . " caracteres</p>";
                            
                            // Verifica se há HTML
                            if (strpos($output, '<html') !== false || strpos($output, '<div') !== false) {
                                echo "<p>✅ Saída HTML válida</p>";
                                
                                // Mostra primeiros 200 chars
                                echo "<p><strong>Primeiros 200 chars:</strong></p>";
                                echo "<pre>" . htmlspecialchars(substr($output, 0, 200)) . "</pre>";
                            } else {
                                echo "<p>⚠️ Saída não parece ser HTML</p>";
                                echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "</pre>";
                            }
                        } else {
                            echo "<p>❌ Método index() não produziu saída</p>";
                        }
                        
                    } catch (Exception $e) {
                        echo "<p style='color: red;'>❌ Erro ao executar método index():</p>";
                        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
                        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                    }
                    
                } else {
                    echo "<p style='color: red;'>❌ Método index() NÃO existe</p>";
                }
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erro ao instanciar controller:</p>";
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Classe TrackingCodesController NÃO existe</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao verificar classe:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    
} else {
    echo "<p style='color: red;'>❌ TrackingCodesController.php NÃO existe</p>";
}

// 3. Verifica se a view existe
echo "<h2>3. Verificação da View</h2>";

$viewFile = __DIR__ . '/views/settings/tracking_codes.php';

if (file_exists($viewFile)) {
    echo "<p>✅ tracking_codes.php existe</p>";
    
    // Verifica sintaxe
    $output = [];
    $return_var = 0;
    exec("php -l \"$viewFile\" 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "<p>✅ Sintaxe da view OK</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro de sintaxe na view:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    }
    
} else {
    echo "<p style='color: red;'>❌ tracking_codes.php NÃO existe</p>";
}

// 4. Teste manual da rota
echo "<h2>4. Teste Manual da Rota</h2>";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Simula a requisição
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/settings/tracking-codes';
    
    echo "<p>Simulando requisição GET /settings/tracking-codes...</p>";
    
    // Tenta executar o controller diretamente
    $controller = new \PixelHub\Controllers\TrackingCodesController();
    
    ob_start();
    $controller->index();
    $result = ob_get_clean();
    
    if (!empty($result)) {
        echo "<p>✅ Execução manual bem-sucedida</p>";
        echo "<p>Tamanho da saída: " . strlen($result) . " caracteres</p>";
        
        // Verifica se há erros
        $error = error_get_last();
        if ($error) {
            echo "<p style='color: red;'><strong>⚠️ Erro PHP detectado:</strong> " . htmlspecialchars($error['message']) . "</p>";
        }
        
    } else {
        echo "<p>❌ Execução manual não produziu saída</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro na execução manual:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>CONCLUSÃO</h2>";
echo "<p>Se algum teste acima falhar, o erro está identificado.</p>";

error_log("=== FIM TESTE DA ROTA /settings/tracking-codes === " . date('Y-m-d H:i:s'));
?>
