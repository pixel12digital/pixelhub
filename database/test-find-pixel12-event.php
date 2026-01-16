<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== TESTE: Achar evento Pixel12 Digital por texto ===\n\n";

// Ajustando para a estrutura real (channel_id está no metadata, não como coluna direta)
$sql = "SELECT
  id,
  created_at,
  tenant_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  event_type,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.from')) AS from_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.to'))   AS to_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.body')) AS body
FROM communication_events
WHERE tenant_id = 2
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) IN ('pixel12digital','Pixel12 Digital')
  AND event_type = 'whatsapp.inbound.message'
  AND payload LIKE '%Teste de envio do ServPro para Pixel12 Digital%'
ORDER BY id DESC
LIMIT 10";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ " . count($events) . " evento(s) encontrado(s):\n\n";
    echo str_repeat("=", 140) . "\n";
    echo sprintf("%-8s | %-19s | %-10s | %-25s | %-35s | %-45s | %-45s | %-60s\n",
        "ID", "CREATED_AT", "TENANT_ID", "CHANNEL_ID", "EVENT_TYPE", "FROM_ID", "TO_ID", "BODY");
    echo str_repeat("-", 140) . "\n";
    
    foreach ($events as $e) {
        echo sprintf("%-8s | %-19s | %-10s | %-25s | %-35s | %-45s | %-45s | %-60s\n",
            $e['id'],
            $e['created_at'],
            $e['tenant_id'] ?: 'NULL',
            $e['channel_id'] ?: 'NULL',
            substr($e['event_type'], 0, 33),
            substr($e['from_id'] ?: 'NULL', 0, 43),
            substr($e['to_id'] ?: 'NULL', 0, 43),
            substr($e['body'] ?: 'NULL', 0, 58)
        );
    }
    
    echo str_repeat("=", 140) . "\n\n";
    
    // Análise do from_id
    foreach ($events as $e) {
        $fromId = $e['from_id'];
        $channelId = $e['channel_id'];
        
        echo "Análise do evento ID {$e['id']}:\n";
        echo "  - FROM_ID: " . ($fromId ?: 'NULL');
        if ($fromId) {
            if (strpos($fromId, '@lid') !== false) {
                echo " (formato: @lid)\n";
            } elseif (strpos($fromId, '@c.us') !== false) {
                echo " (formato: @c.us)\n";
            } elseif (preg_match('/^[0-9]+$/', $fromId)) {
                echo " (formato: número puro)\n";
            } else {
                echo " (formato: outro)\n";
            }
        } else {
            echo "\n";
        }
        echo "  - CHANNEL_ID: " . ($channelId ?: 'NULL');
        if ($channelId) {
            if ($channelId === 'pixel12digital') {
                echo " (minúsculo, sem espaço)\n";
            } elseif ($channelId === 'Pixel12 Digital') {
                echo " (com espaço e maiúscula)\n";
            } else {
                echo " (outro formato)\n";
            }
        } else {
            echo "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Nenhum evento encontrado.\n\n";
    echo "Verificando se há eventos do Pixel12 Digital recentes...\n\n";
    
    // Buscar eventos recentes do Pixel12 Digital para comparação
    $refSql = "SELECT id, created_at,
      JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
      event_type,
      LEFT(payload, 100) AS payload_preview
    FROM communication_events
    WHERE tenant_id = 2
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) IN ('pixel12digital','Pixel12 Digital')
      AND event_type = 'whatsapp.inbound.message'
    ORDER BY id DESC
    LIMIT 5";
    
    $refStmt = $pdo->query($refSql);
    $refEvents = $refStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($refEvents) > 0) {
        echo "Últimos 5 eventos do Pixel12 Digital:\n";
        foreach ($refEvents as $re) {
            echo "  ID {$re['id']} | {$re['created_at']} | Channel: {$re['channel_id']}\n";
        }
    }
}

echo "\n";

