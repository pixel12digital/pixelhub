<?php
// Verificação direta dos arquivos modificados
error_log("=== VERIFICAÇÃO DIRETA DOS ARQUIVOS MODIFICADOS === " . date('Y-m-d H:i:s'));

echo "<h1>VERIFICAÇÃO DIRETA - ARQUIVOS MODIFICADOS</h1>";

// Lista de arquivos que modifiquei
$arquivosModificados = [
    __DIR__ . '/src/Services/TrackingCodesService.php',
    __DIR__ . '/src/Controllers/TrackingCodesController.php',
    __DIR__ . '/views/settings/tracking_codes.php',
    __DIR__ . '/public/index.php'
];

foreach ($arquivosModificados as $arquivo) {
    $nome = basename($arquivo);
    echo "<h2>Verificando: $nome</h2>";
    
    if (!file_exists($arquivo)) {
        echo "<p style='color: red;'>❌ Arquivo não existe: $arquivo</p>";
        continue;
    }
    
    echo "<h3>Sintaxe PHP:</h3>";
    $output = [];
    $return_var = 0;
    exec("php -l \"$arquivo\" 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "<p style='color: green;'>✅ Sintaxe OK</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro de sintaxe:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
        
        // Mostra linha específica do erro
        foreach ($output as $linha) {
            if (strpos($linha, 'error') !== false) {
                echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($linha) . "</p>";
                break;
            }
        }
    }
    
    // Verifica se há erros de sintaxe específicos
    $content = file_get_contents($arquivo);
    
    // Verifica se há erros comuns
    $errosComuns = [
        'Parse error',
        'syntax error',
        'unexpected',
        'T_ENCAPS_EXPECTED',
        'T_VARIABLE',
        'T_STRING',
        'T_FUNCTION',
        'T_CLASS',
        'T_NEW',
        'T_PAAMAY',
        'T_ECHO',
        'T_OPEN_TAG',
        'T_CLOSE_TAG'
    ];
    
    foreach ($errosComuns as $erro) {
        if (strpos($content, $erro) !== false) {
            echo "<p style='color: red;'><strong>Erro encontrado: '$erro'</strong></p>";
            
            // Encontra a linha do erro
            $linhas = file($arquivo);
            foreach ($linhas as $num => $linha) {
                if (strpos($linha, $erro) !== false) {
                    echo "<p><strong>Linha " . ($num + 1) . ":</strong> " . htmlspecialchars($linha) . "</p>";
                    break;
                }
            }
        }
    }
    
    echo "<hr>";
}

// Teste cada arquivo individualmente
echo "<h2>TESTE INDIVIDUAL DOS ARQUIVOS</h2>";

// Teste TrackingCodesService
echo "<h3>Teste TrackingCodesService</h3>";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $service = new \PixelHub\Services\TrackingCodesService();
    echo "<p>✅ TrackingCodesService instanciado com sucesso</p>";
    
    // Teste getChannels
    $channels = $service->getChannels();
    echo "<p>✅ getChannels() - " . count($channels, COUNT_RECURSIVE) . " categorias</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro em TrackingCodesService:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";

// Teste TrackingCodesController
echo "<h3>Teste TrackingCodesController</h3>";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $controller = new \PixelHub\Controllers\TrackingCodesController();
    echo "<p>✅ TrackingCodesController instanciado com sucesso</p>";
    
    // Teste método index
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p>✅ Método index() executou - " . strlen($output) . " caracteres</p>";
        
        // Verifica se há HTML válido
        if (strpos($output, '<html') !== false || strpos($output, '<div') !== false) {
            echo "<p>✅ Saída HTML válida</p>";
        } else {
            echo "<p>⚠️ Saída não parece ser HTML</p>";
            echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "</pre>";
        }
    } else {
        echo "<p>❌ Método index() não produziu saída</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro em TrackingCodesController:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";

// Teste view
echo "<h3>Teste View tracking_codes.php</h3>";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Simula dados para view
    $codes = [
        [
            'id' => 1,
            'code' => 'TEST123',
            'channel' => 'google_ads',
            'origin_page' => '/test',
            'cta_position' => 'header',
            'campaign_name' => 'Test Campaign',
            'is_active' => 1
        ]
    ];
    
    ob_start();
    include __DIR__ . '/views/settings/tracking_codes.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p>✅ View renderizada - " . strlen($output) . " caracteres</p>";
        
        // Verifica se há erros PHP
        $error = error_get_last();
        if ($error) {
            "<p style='color: red;'><strong>❌ Erro PHP na view:</strong> " . htmlspecialchars($error['message']) . "</p>";
        }
    } else {
        echo "<p>❌ View não produziu saída</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro na view:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>RESUMO</h2>";
echo "<p>Verificação concluída. Se algum teste acima falhar, o erro está identificado.</p>";

error_log("=== FIM VERIFICAÇÃO DIRETA === " . date('Y-m-d H:i:s'));
?>
