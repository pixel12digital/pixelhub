<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== TESTE: Buscar evento Pixel12 Digital (busca ampla) ===\n\n";

// Tentar várias variações do texto
$patterns = [
    '%Teste de envio do ServPro para Pixel12 Digital%',
    '%ServPro%',
    '%Pixel12 Digital%',
    '%Teste%ServPro%',
    '%envio%ServPro%'
];

$found = false;

foreach ($patterns as $pattern) {
    echo "Buscando padrão: {$pattern}\n";
    
    $sql = "SELECT
      id,
      created_at,
      tenant_id,
      JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
      event_type,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.from')) AS from_id,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.to')) AS to_id,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.body')) AS body,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.text')) AS message_text,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.raw.payload.body')) AS raw_body
    FROM communication_events
    WHERE tenant_id = 2
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) IN ('pixel12digital','Pixel12 Digital')
      AND event_type = 'whatsapp.inbound.message'
      AND (
        payload LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(payload,'$.body')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.text')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(payload,'$.raw.payload.body')) LIKE ?
      )
    ORDER BY id DESC
    LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) > 0) {
        $found = true;
        echo "✅ " . count($events) . " evento(s) encontrado(s)!\n\n";
        
        echo str_repeat("=", 140) . "\n";
        echo sprintf("%-8s | %-19s | %-10s | %-25s | %-45s | %-45s | %-60s\n",
            "ID", "CREATED_AT", "TENANT_ID", "CHANNEL_ID", "FROM_ID", "TO_ID", "BODY/TEXT");
        echo str_repeat("-", 140) . "\n";
        
        foreach ($events as $e) {
            $text = $e['body'] ?: $e['message_text'] ?: $e['raw_body'] ?: 'NULL';
            
            echo sprintf("%-8s | %-19s | %-10s | %-25s | %-45s | %-45s | %-60s\n",
                $e['id'],
                $e['created_at'],
                $e['tenant_id'] ?: 'NULL',
                $e['channel_id'] ?: 'NULL',
                substr($e['from_id'] ?: 'NULL', 0, 43),
                substr($e['to_id'] ?: 'NULL', 0, 43),
                substr($text, 0, 58)
            );
        }
        
        echo str_repeat("=", 140) . "\n\n";
        
        // Análise
        foreach ($events as $e) {
            $fromId = $e['from_id'];
            $channelId = $e['channel_id'];
            
            echo "📋 Análise do evento ID {$e['id']}:\n";
            echo "   FROM_ID: " . ($fromId ?: 'NULL');
            if ($fromId) {
                if (strpos($fromId, '@lid') !== false) {
                    echo " → formato: @lid\n";
                } elseif (strpos($fromId, '@c.us') !== false) {
                    echo " → formato: @c.us\n";
                } elseif (preg_match('/^[0-9]+$/', $fromId)) {
                    echo " → formato: número puro\n";
                } else {
                    echo " → formato: outro\n";
                }
            } else {
                echo "\n";
            }
            
            echo "   CHANNEL_ID: " . ($channelId ?: 'NULL');
            if ($channelId) {
                if ($channelId === 'pixel12digital') {
                    echo " → minúsculo, sem espaço\n";
                } elseif ($channelId === 'Pixel12 Digital') {
                    echo " → com espaço e maiúscula\n";
                } else {
                    echo " → outro formato: '{$channelId}'\n";
                }
            } else {
                echo "\n";
            }
            echo "\n";
        }
        
        break; // Para na primeira busca que encontrar
    } else {
        echo "   Nenhum evento encontrado.\n\n";
    }
}

if (!$found) {
    echo "❌ Nenhum evento encontrado com nenhum dos padrões.\n\n";
    echo "Buscando os 3 eventos MAIS RECENTES do Pixel12 Digital para análise:\n\n";
    
    $sqlRecent = "SELECT
      id,
      created_at,
      tenant_id,
      JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
      event_type,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.from')) AS from_id,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.to')) AS to_id,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.body')) AS body,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.text')) AS message_text,
      JSON_UNQUOTE(JSON_EXTRACT(payload,'$.raw.payload.body')) AS raw_body
    FROM communication_events
    WHERE tenant_id = 2
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) IN ('pixel12digital','Pixel12 Digital')
      AND event_type = 'whatsapp.inbound.message'
    ORDER BY id DESC
    LIMIT 3";
    
    $stmtRecent = $pdo->query($sqlRecent);
    $recentEvents = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recentEvents) > 0) {
        echo str_repeat("=", 140) . "\n";
        echo sprintf("%-8s | %-19s | %-25s | %-45s | %-45s | %-60s\n",
            "ID", "CREATED_AT", "CHANNEL_ID", "FROM_ID", "TO_ID", "BODY/TEXT");
        echo str_repeat("-", 140) . "\n";
        
        foreach ($recentEvents as $re) {
            $text = $re['body'] ?: $re['message_text'] ?: $re['raw_body'] ?: 'NULL';
            
            echo sprintf("%-8s | %-19s | %-25s | %-45s | %-45s | %-60s\n",
                $re['id'],
                $re['created_at'],
                $re['channel_id'] ?: 'NULL',
                substr($re['from_id'] ?: 'NULL', 0, 43),
                substr($re['to_id'] ?: 'NULL', 0, 43),
                substr($text, 0, 58)
            );
        }
        
        echo str_repeat("=", 140) . "\n";
    }
}

echo "\n";

