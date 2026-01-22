<?php
/**
 * Verifica logs do webhook para eventos 'message'
 * Busca em múltiplos locais possíveis de logs
 */

echo "=== VERIFICAÇÃO AUTOMÁTICA DE LOGS WEBHOOK ===\n\n";

$logFiles = [
    __DIR__ . '/../logs/pixelhub.log',
    __DIR__ . '/../logs/asaas_sync_errors.log',
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error_log.log',
    ini_get('error_log') ?: 'C:/xampp/php/logs/php_error_log.log'
];

$foundLogs = [];

// Busca em cada arquivo de log
foreach ($logFiles as $logFile) {
    if (!file_exists($logFile)) {
        continue;
    }
    
    echo "Verificando: {$logFile}\n";
    
    // Lê últimas 1000 linhas do arquivo
    $lines = file($logFile);
    if (!$lines) {
        continue;
    }
    
    // Busca últimas linhas (últimos eventos)
    $recentLines = array_slice($lines, -1000);
    
    // Busca por HUB_WEBHOOK_IN com message
    $messageEvents = [];
    $connectionEvents = [];
    $allWebhookEvents = [];
    
    foreach ($recentLines as $lineNum => $line) {
        // HUB_WEBHOOK_IN logs
        if (strpos($line, '[HUB_WEBHOOK_IN]') !== false) {
            $allWebhookEvents[] = [
                'line' => count($lines) - 1000 + $lineNum + 1,
                'content' => trim($line)
            ];
            
            if (strpos($line, 'eventType=message') !== false || 
                strpos($line, 'eventType=message.') !== false ||
                preg_match('/eventType=(message|message\.sent|message\.ack)/', $line)) {
                $messageEvents[] = [
                    'line' => count($lines) - 1000 + $lineNum + 1,
                    'content' => trim($line)
                ];
            }
            
            if (strpos($line, 'eventType=connection.update') !== false) {
                $connectionEvents[] = [
                    'line' => count($lines) - 1000 + $lineNum + 1,
                    'content' => trim($line)
                ];
            }
        }
        
        // WHATSAPP INBOUND RAW logs
        if (strpos($line, '[WHATSAPP INBOUND RAW]') !== false && strpos($line, '"event":"message"') !== false) {
            $messageEvents[] = [
                'line' => count($lines) - 1000 + $lineNum + 1,
                'content' => trim($line)
            ];
        }
    }
    
    if (!empty($allWebhookEvents) || !empty($messageEvents) || !empty($connectionEvents)) {
        $foundLogs[$logFile] = [
            'all_webhook' => $allWebhookEvents,
            'message_events' => $messageEvents,
            'connection_events' => $connectionEvents,
            'total_webhook' => count($allWebhookEvents),
            'total_message' => count($messageEvents),
            'total_connection' => count($connectionEvents)
        ];
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESULTADO DA BUSCA:\n";
echo str_repeat("=", 80) . "\n\n";

if (empty($foundLogs)) {
    echo "❌ Nenhum log de webhook encontrado nos arquivos verificados\n";
    echo "   Verificados:\n";
    foreach ($logFiles as $file) {
        echo "      - {$file} " . (file_exists($file) ? "✅ existe" : "❌ não existe") . "\n";
    }
} else {
    foreach ($foundLogs as $logFile => $data) {
        echo "📁 Arquivo: {$logFile}\n";
        echo "   Total de eventos webhook: {$data['total_webhook']}\n";
        echo "   Total de eventos 'message': {$data['total_message']}\n";
        echo "   Total de eventos 'connection.update': {$data['total_connection']}\n\n";
        
        if (!empty($data['message_events'])) {
            echo "   ✅ EVENTOS 'message' ENCONTRADOS:\n";
            foreach (array_slice($data['message_events'], -10) as $event) {
                echo "      [Linha {$event['line']}] {$event['content']}\n";
            }
            echo "\n";
        } else {
            echo "   ❌ Nenhum evento 'message' encontrado neste arquivo\n\n";
        }
        
        // Mostra últimos 5 eventos webhook para contexto
        if (!empty($data['all_webhook'])) {
            echo "   Últimos 5 eventos webhook (contexto):\n";
            foreach (array_slice($data['all_webhook'], -5) as $event) {
                echo "      [Linha {$event['line']}] " . substr($event['content'], 0, 150) . "...\n";
            }
            echo "\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "CONCLUSÃO:\n";
echo str_repeat("=", 80) . "\n\n";

$totalMessageEvents = 0;
foreach ($foundLogs as $data) {
    $totalMessageEvents += $data['total_message'];
}

if ($totalMessageEvents > 0) {
    echo "✅ Encontrados {$totalMessageEvents} evento(s) 'message' nos logs\n";
    echo "   → Eventos 'message' ESTÃO chegando no webhook\n";
    echo "   → Problema pode estar no processamento/gravação\n";
} else {
    echo "❌ Nenhum evento 'message' encontrado nos logs\n";
    echo "   → Eventos 'message' NÃO estão chegando no webhook\n";
    echo "   → Problema provavelmente está no gateway (não está enviando)\n";
}

echo "\n";

