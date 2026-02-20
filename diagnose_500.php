<?php
// Diagnóstico sistemático do erro 500 na rota /settings/tracking-codes
error_log("=== DIAGNÓSTICO SISTEMÁTICO ERRO 500 === " . date('Y-m-d H:i:s'));

echo "<h1>DIAGNÓSTICO SISTEMÁTICO - ERRO 500 /settings/tracking-codes</h1>";

// 1) Verificação de DB fora de sincronia
echo "<h2>1️⃣ Migração/DB fora de sincronia</h2>";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    $db = \PixelHub\Core\DB::getConnection();
    
    // Verifica se tabela tracking_codes existe
    $stmt = $db->query("SHOW TABLES LIKE 'tracking_codes'");
    if ($stmt->rowCount() === 0) {
        echo "<p style='color: red;'>❌ Tabela tracking_codes NÃO existe</p>";
        die("ERRO CRÍTICO: Tabela tracking_codes não encontrada");
    }
    
    // Verifica estrutura completa
    $stmt = $db->query("DESCRIBE tracking_codes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $camposEsperados = [
        'id', 'code', 'source', 'description', 'is_active', 'created_by', 
        'created_at', 'updated_at', 'channel', 'origin_page', 'cta_position',
        'campaign_name', 'campaign_id', 'ad_group', 'ad_name', 'context_metadata'
    ];
    
    echo "<table border='1' cellpadding='3'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Status</th></tr>";
    
    $camposFaltantes = [];
    foreach ($camposEsperados as $campo) {
        $existe = false;
        foreach ($columns as $col) {
            if ($col['Field'] === $campo) {
                $existe = true;
                break;
            }
        }
        
        echo "<tr style='background: " . ($existe ? '#d4edda' : '#f8d7da') . "'>";
        echo "<td><strong>" . htmlspecialchars($campo) . "</strong></td>";
        echo "<td>" . ($existe ? '✅' : '❌') . "</td>";
        echo "<td>" . ($existe ? 'OK' : 'FALTANTE') . "</td>";
        echo "</tr>";
        
        if (!$existe) {
            $camposFaltantes[] = $campo;
        }
    }
    echo "</table>";
    
    if (!empty($camposFaltantes)) {
        echo "<h3 style='color: red;'>⚠️ CAMPOS FALTANTES:</h3>";
        echo "<p>" . implode(', ', $camposFaltantes) . "</p>";
        echo "<p>Este é o provável erro 500!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar DB: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 2) Verificação de Query com JOIN/relacionamento
echo "<h2>2️⃣ Query com JOIN/relacionamento</h2>";

try {
    // Testa a query principal do TrackingCodesService::listAll()
    $stmt = $db->prepare("
        SELECT tc.*, u.name as created_by_name
        FROM tracking_codes tc
        LEFT JOIN users u ON tc.created_by = u.id
        ORDER BY tc.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Query principal executou com sucesso - " . count($result) . " registros</p>";
    
    // Verifica se todos os campos esperados existem no resultado
    if (!empty($result)) {
        $primeiroRegistro = $result[0];
        $camposRegistro = array_keys($primeiroRegistro);
        
        echo "<h4>Campos no primeiro registro:</h4>";
        echo "<ul>";
        foreach ($camposRegistro as $campo) {
            $temCampo = in_array($campo, $camposEsperados) || in_array($campo, ['created_by_name']);
            echo "<li style='color: " . ($temCampo ? 'green' : 'red') . ";'>" . htmlspecialchars($campo) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro na query principal: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Este é o provável erro 500!</p>";
}

// 3) Verificação de Controller/Model
echo "<h2>3️⃣ Controller/Model</h2>";

try {
    // Verifica se classes existem
    if (!class_exists('PixelHub\Controllers\TrackingCodesController')) {
        echo "<p style='color: red;'>❌ TrackingCodesController não existe</p>";
    } else {
        echo "<p>✅ TrackingCodesController existe</p>";
        
        // Verifica se método index existe
        if (!method_exists('PixelHub\Controllers\TrackingCodesController', 'index')) {
            echo "<p style='color: red;'>❌ Método index() não existe</p>";
        } else {
            echo "<p>✅ Método index() existe</p>";
        }
    }
    
    if (!class_exists('PixelHub\Services\TrackingCodesService')) {
        echo "<p style='color: red;'>❌ TrackingCodesService não existe</p>";
    } else {
        echo "<p>✅ TrackingCodesService existe</p>";
        
        // Verifica métodos críticos
        $metodosCriticos = ['listAll', 'getChannels', 'getCtaPositions'];
        foreach ($metodosCriticos as $metodo) {
            if (!method_exists('PixelHub\Services\TrackingCodesService', $metodo)) {
                echo "<p style='color: red;'>❌ Método $metodo() não existe</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar classes: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4) Verificação de View
echo "<h2>4️⃣ View</h2>";

$viewFile = __DIR__ . '/views/settings/tracking_codes.php';
if (!file_exists($viewFile)) {
    echo "<p style='color: red;'>❌ View tracking_codes.php não existe</p>";
} else {
    echo "<p>✅ View tracking_codes.php existe</p>";
    
    // Verifica sintaxe
    $output = [];
    $return_var = 0;
    exec("php -l \"$viewFile\" 2>&1", $output, $return_var);
    
    if ($return_var !== 0) {
        echo "<p style='color: red;'>❌ Erro de sintaxe na view:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    } else {
        echo "<p>✅ Sintaxe da view OK</p>";
    }
    
    // Verifica se view usa campos que podem não existir
    $content = file_get_contents($viewFile);
    $camposUsadosNaView = [];
    
    // Procura por $code['campo']
    if (preg_match_all('/\$code\[\'([^\']+)\'\]/', $content, $matches)) {
        $camposUsadosNaView = $matches[1];
    }
    
    echo "<h4>Campos usados na view:</h4>";
    echo "<ul>";
    foreach ($camposUsadosNaView as $campo) {
        $existeNoDB = in_array($campo, array_column($columns ?? [], 'Field'));
        echo "<li style='color: " . ($existeNoDB ? 'green' : 'red') . ";'>" . htmlspecialchars($campo) . "</li>";
    }
    echo "</ul>";
}

// 5) Verificação de Validação/enum
echo "<h2>5️⃣ Validação/enum</h2>";

try {
    if (class_exists('PixelHub\Services\TrackingCodesService')) {
        $channels = \PixelHub\Services\TrackingCodesService::getChannels();
        echo "<p>✅ getChannels() funciona - " . count($channels) . " categorias</p>";
        
        $positions = \PixelHub\Services\TrackingCodesService::getCtaPositions();
        echo "<p>✅ getCtaPositions() funciona - " . count($positions) . " posições</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro nos métodos de validação: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 6) Verificação de Permissão/ACL
echo "<h2>6️⃣ Permissão/ACL</h2>";

try {
    if (class_exists('PixelHub\Core\Auth')) {
        // Verifica se Auth::requireInternal() funciona
        echo "<p>✅ Classe Auth existe</p>";
        
        // Verifica se usuário está logado (simulação)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user'])) {
            echo "<p>✅ Sessão de usuário existe</p>";
        } else {
            echo "<p>⚠️ Sessão de usuário não encontrada (pode causar erro 500)</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro na verificação de Auth: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 7) Verificação de include/path/autoload
echo "<h2>7️⃣ Include/path/autoload</h2>";

$arquivosCriticos = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/src/Services/TrackingCodesService.php',
    __DIR__ . '/src/Controllers/TrackingCodesController.php',
    __DIR__ . '/views/settings/tracking_codes.php'
];

foreach ($arquivosCriticos as $arquivo) {
    $nome = basename($arquivo);
    if (file_exists($arquivo)) {
        echo "<p>✅ $nome existe</p>";
    } else {
        echo "<p style='color: red;'>❌ $nome NÃO existe</p>";
    }
}

// 8) Simulação exata da rota
echo "<h2>8️⃣ Simulação exata da rota</h2>";

try {
    // Simula requisição GET /settings/tracking-codes
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/settings/tracking-codes';
    
    echo "<p>Simulando GET /settings/tracking-codes...</p>";
    
    // Tenta executar o controller
    $controller = new \PixelHub\Controllers\TrackingCodesController();
    
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p style='color: green;'>✅ Simulação executou com sucesso!</p>";
        echo "<p>Tamanho da saída: " . strlen($output) . " caracteres</p>";
        
        // Verifica se há HTML válido
        if (strpos($output, '<html') !== false || strpos($output, '<div') !== false) {
            echo "<p>✅ Saída HTML válida</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Simulação não produziu saída</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro na simulação:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "<p><strong>ESTE É O ERRO 500!</strong></p>";
}

// 9) Logs de erro recentes
echo "<h2>9️⃣ Logs de erro recentes</h2>";

$logFile = ini_get('error_log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $totalLines = count($lines);
    $start = max(0, $totalLines - 20);
    
    echo "<h4>Últimas 20 linhas do log:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px; max-height: 300px; overflow-y: auto;'>";
    for ($i = $start; $i < $totalLines; $i++) {
        $linha = $lines[$i];
        if (strpos($linha, 'tracking') !== false || strpos($linha, '500') !== false) {
            echo "<span style='color: red;'>" . htmlspecialchars($linha) . "</span>";
        } else {
            echo htmlspecialchars($linha);
        }
        echo "\n";
    }
    echo "</pre>";
}

echo "<h2>🎯 CONCLUSÃO</h2>";
echo "<p>Verifique os itens em vermelho acima. O erro 500 está em um deles.</p>";

error_log("=== FIM DIAGNÓSTICO SISTEMÁTICO === " . date('Y-m-d H:i:s'));
?>
