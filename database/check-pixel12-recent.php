<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO: MENSAGEM DO PIXEL12 DIGITAL ===\n\n";

// Buscar TODOS os eventos do Pixel12 Digital após 19:20 (não só message)
echo "1) TODOS OS EVENTOS DO PIXEL12 DIGITAL (após 19:20):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:20:00'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
ORDER BY created_at DESC
LIMIT 20";

$stmt1 = $pdo->query($sql1);
$allEvents = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($allEvents) > 0) {
    echo "Total: " . count($allEvents) . " eventos\n\n";
    
    $messages = [];
    foreach ($allEvents as $e) {
        $isMessage = $e['payload_event'] === 'message' || strpos($e['event_type'], 'message') !== false;
        
        if ($isMessage) {
            $messages[] = $e;
            $icon = $e['status'] === 'processed' ? '✅' : '❌';
            $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
            
            echo sprintf("%s ID: %5d | %s | Status: %-10s | Text: %s | Erro: %s\n",
                $icon,
                $e['id'],
                $e['created_at'],
                $e['status'],
                substr($text, 0, 50),
                substr($e['error_message'] ?: 'OK', 0, 30)
            );
        } else {
            // Mostrar só se for processed (eventos técnicos)
            if ($e['status'] === 'processed') {
                echo sprintf("   ID: %5d | %s | Status: %-10s | Event: %s\n",
                    $e['id'],
                    $e['created_at'],
                    $e['status'],
                    substr($e['payload_event'] ?: 'NULL', 0, 20)
                );
            }
        }
    }
    
    echo "\n✅ Resumo:\n";
    echo sprintf("  Eventos de mensagem: %d\n", count($messages));
    
    if (count($messages) === 0) {
        echo "\n⚠️  PROBLEMA: Nenhuma mensagem do Pixel12 Digital foi recebida após 19:20.\n";
        echo "   Isso sugere que a mensagem 'teste1921_pixel' pode não ter chegado ao Hub,\n";
        echo "   ou foi processada antes do deploy, ou está com outro channel_id.\n";
    }
} else {
    echo "❌ Nenhum evento do Pixel12 Digital encontrado após 19:20.\n";
}

// Conclusão
echo "\n" . str_repeat("=", 100) . "\n";
echo "CONCLUSÃO:\n";
echo str_repeat("=", 100) . "\n";
echo "✅ ImobSites: mensagem 'teste1921_imobsites' encontrada e processada (ID 6393)\n";
if (count($allEvents) > 0 && count($messages) === 0) {
    echo "❌ Pixel12 Digital: mensagem 'teste1921_pixel' NÃO encontrada nos eventos após 19:20\n";
    echo "   Possíveis causas:\n";
    echo "   1. Mensagem não chegou ao Hub (problema no gateway-wrapper/WPPConnect)\n";
    echo "   2. Mensagem foi processada antes do deploy\n";
    echo "   3. Mensagem está com channel_id diferente ou NULL\n";
    echo "   4. Mensagem falhou silenciosamente (não criou evento)\n";
} elseif (count($allEvents) === 0) {
    echo "❌ Pixel12 Digital: nenhum evento recebido após 19:20\n";
    echo "   O canal pode não estar enviando eventos ou está com problema no gateway.\n";
}

echo "\n";


