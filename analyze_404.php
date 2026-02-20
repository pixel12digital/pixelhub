<?php
// Análise criteriosa do problema 404
error_log("=== ANÁLISE CRITERIOSA 404 TRACKING CODES === " . date('Y-m-d H:i:s'));

echo "<h1>ANÁLISE CRITERIOSA - PROBLEMA 404</h1>";

// 1. Verifica se migrations foram executadas
echo "<h2>1. VERIFICAÇÃO DE MIGRATIONS</h2>";

$migrationFiles = [
    '20260220_add_tracking_to_opportunities_table.php',
    '20260220_create_tracking_codes_table.php', 
    '20260220_create_tracking_campaigns_table.php',
    '20260220_restructure_tracking_codes_table.php'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Migration</th><th>Existe</th><th>Status</th></tr>";

foreach ($migrationFiles as $file) {
    $filePath = __DIR__ . '/database/migrations/' . $file;
    $exists = file_exists($filePath);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($file) . "</td>";
    echo "<td>" . ($exists ? '✅ SIM' : '❌ NÃO') . "</td>";
    
    if ($exists) {
        $content = file_get_contents($filePath);
        $hasUpMethod = strpos($content, 'public function up(') !== false;
        echo "<td>" . ($hasUpMethod ? '✅ OK' : '❌ Sem up()') . "</td>";
    } else {
        echo "<td>-</td>";
    }
    
    echo "</tr>";
}
echo "</table>";

// 2. Verifica estrutura atual da tabela
echo "<h2>2. VERIFICAÇÃO ESTRUTURA ATUAL</h2>";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    $db = \PixelHub\Core\DB::getConnection();
    
    $stmt = $db->query("SHOW TABLES LIKE 'tracking%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Tabelas encontradas:</strong> " . implode(', ', $tables) . "</p>";
    
    if (in_array('tracking_codes', $tables)) {
        echo "<h3>Estrutura tracking_codes:</h3>";
        $stmt = $db->query("DESCRIBE tracking_codes");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='3'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Extra</th></tr>";
        
        $camposEsperados = [
            'code', 'channel', 'origin_page', 'cta_position', 
            'campaign_name', 'campaign_id', 'ad_group', 'ad_name', 'context_metadata'
        ];
        
        foreach ($columns as $col) {
            $campo = $col['Field'];
            $esperado = in_array($campo, $camposEsperados);
            
            echo "<tr style='background: " . ($esperado ? '#d4edda' : '#fff2f2') . "'>";
            echo "<td>" . htmlspecialchars($campo) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . ($col['Null'] === 'YES' ? 'SIM' : 'NÃO') . "</td>";
            echo "<td>" . htmlspecialchars($col['Extra'] ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verifica se todos os campos esperados existem
        $camposFaltantes = array_diff($camposEsperados, array_column($columns, 'Field'));
        if (!empty($camposFaltantes)) {
            echo "<p style='color: red;'><strong>⚠️ CAMPOS FALTANTES:</strong> " . implode(', ', $camposFaltantes) . "</p>";
        }
        
        // Testa inserção simples
        echo "<h3>Teste Inserção Simples:</h3>";
        try {
            $testCode = 'TEST_' . time();
            $stmt = $db->prepare("INSERT INTO tracking_codes (code, channel, source, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
            $stmt->execute([$testCode, 'test', 'other']);
            echo "<p>✅ Inserção teste OK - Código: " . htmlspecialchars($testCode) . "</p>";
            
            // Remove teste
            $stmt = $db->prepare("DELETE FROM tracking_codes WHERE code = ?");
            $stmt->execute([$testCode]);
            echo "<p>✅ Limpeza teste OK</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'><strong>❌ Erro na inserção:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'><strong>❌ Tabela tracking_codes não encontrada!</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro ao verificar estrutura:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Verifica sintaxe dos arquivos PHP
echo "<h2>3. VERIFICAÇÃO DE SINTAXE PHP</h2>";

$arquivosPHP = [
    __DIR__ . '/src/Services/TrackingCodesService.php',
    __DIR__ . '/src/Controllers/TrackingCodesController.php',
    __DIR__ . '/views/settings/tracking_codes.php'
];

foreach ($arquivosPHP as $arquivo) {
    $nome = basename($arquivo);
    echo "<h3>$nome</h3>";
    
    if (!file_exists($arquivo)) {
        echo "<p style='color: red;'>❌ Arquivo não existe</p>";
        continue;
    }
    
    // Verifica sintaxe com php -l
    $output = [];
    $return_var = 0;
    exec("php -l \"$arquivo\" 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "<p style='color: green;'>✅ Sintaxe OK</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro de sintaxe:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    }
}

// 4. Verifica se as rotas estão registradas
echo "<h2>4. VERIFICAÇÃO DE ROTAS</h2>";

try {
    $indexContent = file_get_contents(__DIR__ . '/public/index.php');
    
    $rotasEsperadas = [
        '/settings/tracking-codes',
        '/settings/tracking-codes/options',
        '/settings/tracking-codes/edit',
        '/settings/tracking-codes/store',
        '/settings/tracking-codes/update',
        '/settings/tracking-codes/delete',
        '/settings/tracking-codes/toggle'
    ];
    
    echo "<table border='1' cellpadding='3'>";
    echo "<tr><th>Rota</th><th>Encontrada</th></tr>";
    
    foreach ($rotasEsperadas as $rota) {
        $encontrada = strpos($indexContent, $rota) !== false;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($rota) . "</td>";
        echo "<td>" . ($encontrada ? '✅ SIM' : '❌ NÃO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar rotas: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 5. Verifica permissões do arquivo
echo "<h2>5. VERIFICAÇÃO DE PERMISSÕES</h2>";

$arquivosCriticos = [
    __DIR__ . '/src/Services/TrackingCodesService.php',
    __DIR__ . '/src/Controllers/TrackingCodesController.php',
    __DIR__ . '/views/settings/tracking_codes.php'
];

foreach ($arquivosCriticos as $arquivo) {
    $nome = basename($arquivo);
    $perms = fileperms($arquivo);
    
    echo "<p><strong>$nome:</strong> " . decoct($perms, 8) . " (octal)</p>";
    
    if (is_readable($arquivo)) {
        echo "<span style='color: green;'>✅ Leitura OK</span>";
    } else {
        echo "<span style='color: red;'>❌ Sem permissão de leitura</span>";
    }
    
    if (is_writable($arquivo)) {
        echo "<span style='color: green;'>✅ Escrita OK</span>";
    } else {
        echo "<span style='color: red;'>❌ Sem permissão de escrita</span>";
    }
    
    echo "<br>";
}

// 6. Verifica logs de erro do PHP
echo "<h2>6. VERIFICAÇÃO DE LOGS PHP</h2>";

$logFiles = [
    ini_get('error_log'),
    __DIR__ . '/logs/error.log',
    'C:/xampp/php/logs/php_error_log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile) && is_readable($logFile)) {
        $recentLogs = tailCustom($logFile, 10);
        if (!empty($recentLogs)) {
            echo "<h4>Logs recentes de " . htmlspecialchars($logFile) . ":</h4>";
            echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px;'>";
            echo htmlspecialchars($recentLogs);
            echo "</pre>";
        }
    }
}

function tailCustom($filepath, $lines = 10) {
    $handle = fopen($filepath, "r");
    $linecounter = $lines;
    $pos = -2;
    $beginning = [];
    while ($linecounter > 0) {
        fseek($handle, $pos, SEEK_END);
        fgets($handle);
        $pos--;
        if (trim(fgets($handle))) {
            $linecounter--;
        }
    }
    fseek($handle, $pos + 2);
    while (!feof($handle) && ($linecounter-- > 0)) {
        $beginning[] = trim(fgets($handle));
    }
    fclose($handle);
    return array_reverse($beginning);
}

echo "<h2>RESUMO DA ANÁLISE</h2>";
error_log("=== FIM ANÁLISE CRITERIOSA 404 TRACKING CODES === " . date('Y-m-d H:i:s'));
?>
