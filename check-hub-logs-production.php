<?php
/**
 * Script para verificar logs do Hub em produção
 * Pode ser executado via CLI ou web
 */

$correlationId = '9858a507-cc4c-4632-8f92-462535eab504';
$testTime = '21:35';
$containerName = 'gateway-hub'; // Ajustar se necessário

echo "=== Verificação de Logs do Hub - Produção ===\n";
echo "correlation_id: $correlationId\n";
echo "horário do teste: ~$testTime\n\n";

// Função para executar comando e capturar output
function execCommand($command) {
    $output = [];
    $returnVar = 0;
    exec($command . ' 2>&1', $output, $returnVar);
    return [
        'output' => $output,
        'return_code' => $returnVar
    ];
}

// 1. Verifica se Docker está disponível
echo "1. Verificando Docker...\n";
$dockerCheck = execCommand('docker --version');
if ($dockerCheck['return_code'] === 0) {
    echo "   ✅ Docker disponível\n";
    $dockerAvailable = true;
} else {
    echo "   ⚠️  Docker não disponível (pode estar em outro servidor)\n";
    $dockerAvailable = false;
}

// 2. Lista containers disponíveis
if ($dockerAvailable) {
    echo "\n2. Containers disponíveis:\n";
    $containers = execCommand('docker ps -a --format "{{.Names}}\t{{.Status}}"');
    foreach ($containers['output'] as $line) {
        if (!empty(trim($line))) {
            echo "   $line\n";
        }
    }
    
    // Tenta encontrar container do Hub
    $hubContainer = null;
    $allContainers = execCommand('docker ps -a --format "{{.Names}}"');
    foreach ($allContainers['output'] as $name) {
        $name = trim($name);
        if (stripos($name, 'hub') !== false || stripos($name, 'pixel') !== false) {
            $hubContainer = $name;
            break;
        }
    }
    
    if (!$hubContainer) {
        $hubContainer = $containerName; // Usa o padrão
    }
    
    echo "\n   Usando container: $hubContainer\n";
} else {
    $hubContainer = null;
}

// 3. Busca por correlation_id nos logs do Docker
if ($dockerAvailable && $hubContainer) {
    echo "\n3. Buscando correlation_id nos logs do Docker...\n";
    $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i '$correlationId' | tail -20";
    $result = execCommand($cmd);
    
    if (!empty($result['output'])) {
        echo "   ✅ Encontradas " . count($result['output']) . " linhas:\n";
        foreach ($result['output'] as $line) {
            echo "   $line\n";
        }
    } else {
        echo "   ❌ Nenhuma linha encontrada com correlation_id\n";
    }
}

// 4. Busca HUB_WEBHOOK_IN
if ($dockerAvailable && $hubContainer) {
    echo "\n4. Buscando HUB_WEBHOOK_IN próximo ao horário do teste...\n";
    $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_WEBHOOK_IN.*$testTime' | tail -10";
    $result = execCommand($cmd);
    
    if (!empty($result['output'])) {
        echo "   ✅ Encontradas " . count($result['output']) . " linhas:\n";
        foreach ($result['output'] as $line) {
            echo "   $line\n";
        }
    } else {
        echo "   ❌ Nenhuma linha encontrada\n";
    }
}

// 5. Busca HUB_MSG_SAVE
if ($dockerAvailable && $hubContainer) {
    echo "\n5. Buscando HUB_MSG_SAVE próximo ao horário do teste...\n";
    $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_MSG_SAVE.*$testTime' | tail -10";
    $result = execCommand($cmd);
    
    if (!empty($result['output'])) {
        echo "   ✅ Encontradas " . count($result['output']) . " linhas:\n";
        foreach ($result['output'] as $line) {
            echo "   $line\n";
        }
    } else {
        echo "   ❌ Nenhuma linha encontrada\n";
    }
}

// 6. Busca HUB_MSG_DROP
if ($dockerAvailable && $hubContainer) {
    echo "\n6. Buscando HUB_MSG_DROP próximo ao horário do teste...\n";
    $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_MSG_DROP.*$testTime' | tail -10";
    $result = execCommand($cmd);
    
    if (!empty($result['output'])) {
        echo "   ⚠️  Encontradas " . count($result['output']) . " linhas (evento foi descartado):\n";
        foreach ($result['output'] as $line) {
            echo "   $line\n";
        }
    } else {
        echo "   ✅ Nenhuma linha encontrada (evento não foi descartado)\n";
    }
}

// 7. Busca erros/exceções
if ($dockerAvailable && $hubContainer) {
    echo "\n7. Buscando erros/exceções próximo ao horário do teste...\n";
    $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -iE 'Exception|Error|Fatal.*$testTime' | tail -10";
    $result = execCommand($cmd);
    
    if (!empty($result['output'])) {
        echo "   ❌ Encontradas " . count($result['output']) . " linhas de erro:\n";
        foreach ($result['output'] as $line) {
            echo "   $line\n";
        }
    } else {
        echo "   ✅ Nenhum erro encontrado\n";
    }
}

// 8. Verifica arquivo de log local (se existir)
echo "\n8. Verificando arquivo de log local...\n";
$logFiles = [
    __DIR__ . '/logs/pixelhub.log',
    __DIR__ . '/storage/logs/pixelhub.log',
    __DIR__ . '/var/log/pixelhub.log',
    '/var/log/pixelhub.log',
];

$logFileFound = false;
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "   ✅ Arquivo encontrado: $logFile\n";
        $logFileFound = true;
        
        // Busca correlation_id no arquivo
        $lines = file($logFile);
        $found = false;
        foreach ($lines as $lineNum => $line) {
            if (stripos($line, $correlationId) !== false) {
                if (!$found) {
                    echo "   ✅ Encontrado correlation_id na linha " . ($lineNum + 1) . ":\n";
                    $found = true;
                }
                echo "   " . trim($line) . "\n";
            }
        }
        
        if (!$found) {
            echo "   ❌ correlation_id não encontrado no arquivo\n";
        }
        break;
    }
}

if (!$logFileFound) {
    echo "   ⚠️  Arquivo de log local não encontrado\n";
}

// 9. Verifica banco de dados
echo "\n9. Verificando banco de dados...\n";
try {
    require 'src/Core/DB.php';
    require 'src/Core/Env.php';
    
    \PixelHub\Core\Env::load();
    $db = \PixelHub\Core\DB::getConnection();
    
    $stmt = $db->prepare("
        SELECT 
            id,
            event_id,
            correlation_id,
            event_type,
            status,
            created_at,
            JSON_EXTRACT(payload, '$.message.id') as message_id
        FROM communication_events 
        WHERE correlation_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$correlationId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($events) {
        echo "   ✅ Encontrados " . count($events) . " eventos no banco:\n";
        foreach ($events as $event) {
            echo "   [{$event['created_at']}] event_id: {$event['event_id']} | status: {$event['status']}\n";
        }
    } else {
        echo "   ❌ Nenhum evento encontrado no banco com esse correlation_id\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Erro ao acessar banco: " . $e->getMessage() . "\n";
}

// Resumo final
echo "\n=== Resumo ===\n";
echo "Execute este script no servidor de produção para verificar os logs.\n";
echo "Se Docker não estiver disponível, verifique os logs manualmente:\n";
echo "  docker logs --since 21:30 $containerName 2>&1 | grep -i '$correlationId\\|HUB_WEBHOOK_IN\\|HUB_MSG_SAVE\\|HUB_MSG_DROP'\n";

