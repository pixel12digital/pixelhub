<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== IDENTIFICAÇÃO DOS FALHAS DO PIXEL12 DIGITAL ===\n\n";

// 1) Top @lid/jid que estão causando fails
echo "1) TOP FROM_ID QUE ESTÃO FALHANDO (Pixel12 Digital):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT
  COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.message.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.message.key.remoteJid')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.key.remoteJid')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.remoteJid')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.remoteJid')),
    'NO_FROM'
  ) AS from_id,
  COUNT(*) AS total
FROM communication_events
WHERE source_system='wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND status='failed'
  AND COALESCE(error_message,'') LIKE '%conversation_not_resolved%'
GROUP BY from_id
ORDER BY total DESC
LIMIT 30";

$stmt1 = $pdo->query($sql1);
$topFailures = $stmt1->fetchAll(PDO::FETCH_ASSOC);

echo "Total de from_id únicos falhando: " . count($topFailures) . "\n\n";

$topFromIds = [];
foreach ($topFailures as $tf) {
    $fromId = $tf['from_id'];
    $total = $tf['total'];
    $topFromIds[] = $fromId;
    
    $isLid = strpos($fromId, '@lid') !== false;
    $isJid = strpos($fromId, '@c.us') !== false || strpos($fromId, '@s.whatsapp.net') !== false;
    $isGroup = strpos($fromId, '@g.us') !== false;
    
    $type = $isLid ? '[@lid]' : ($isJid ? '[JID]' : ($isGroup ? '[GRUPO]' : '[OUTRO]'));
    
    echo sprintf("  [%3d vezes] %s %s\n", $total, $type, $fromId);
    
    // Se for JID, extrair número sugerido
    if ($isJid) {
        $cleanNumber = preg_replace('/@.*$/', '', $fromId);
        $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);
        if (strlen($cleanNumber) >= 10) {
            echo "              💡 Sugestão phone_number: $cleanNumber\n";
        }
    }
}

// 2) Verificar mapeamentos existentes
echo "\n\n2) VERIFICANDO MAPEAMENTOS EXISTENTES:\n";
echo str_repeat("=", 100) . "\n";

if (count($topFromIds) > 0) {
    // Limita aos 5 principais para não gerar query gigante
    $top5 = array_slice($topFromIds, 0, 5);
    $placeholders = str_repeat('?,', count($top5) - 1) . '?';
    
    $sql2 = "SELECT business_id, phone_number, tenant_id, created_at, updated_at
FROM whatsapp_business_ids
WHERE business_id IN ($placeholders)";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($top5);
    $mappings = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($mappings) > 0) {
        echo "✅ Mapeamentos encontrados:\n";
        foreach ($mappings as $m) {
            echo sprintf("  - %s → %s (Tenant: %s)\n",
                $m['business_id'],
                $m['phone_number'],
                $m['tenant_id']
            );
        }
    } else {
        echo "❌ Nenhum mapeamento encontrado para os principais from_id!\n";
        echo "   Isso explica por que estão falhando.\n";
    }
    
    // Verificar quais não têm mapeamento
    $mappedIds = array_column($mappings, 'business_id');
    $unmappedIds = array_diff($top5, $mappedIds);
    
    if (count($unmappedIds) > 0) {
        echo "\n⚠️  IDs SEM MAPEAMENTO (precisam ser criados):\n";
        foreach ($unmappedIds as $unmapped) {
            echo "  - $unmapped\n";
        }
    }
} else {
    echo "Nenhum from_id encontrado nos eventos falhados.\n";
}

// 3) Extrair amostra de payload para análise
echo "\n\n3) AMOSTRA DE PAYLOAD (evento falhado recente):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, payload, error_message
FROM communication_events
WHERE source_system='wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND status='failed'
  AND error_message LIKE '%conversation_not_resolved%'
ORDER BY id DESC
LIMIT 1";

$stmt3 = $pdo->query($sql3);
$sample = $stmt3->fetch(PDO::FETCH_ASSOC);

if ($sample) {
    $payload = json_decode($sample['payload'], true);
    echo "Event ID: " . $sample['id'] . "\n";
    echo "Error: " . $sample['error_message'] . "\n\n";
    
    echo "Estrutura do payload:\n";
    echo "Keys principais: " . implode(', ', array_keys($payload)) . "\n\n";
    
    // Tentar extrair from de múltiplas formas
    $fromAttempts = [
        'payload.from' => $payload['from'] ?? null,
        'payload.message.from' => $payload['message']['from'] ?? null,
        'payload.data.from' => $payload['data']['from'] ?? null,
        'payload.data.message.from' => $payload['data']['message']['from'] ?? null,
        'payload.raw.payload.from' => $payload['raw']['payload']['from'] ?? null,
    ];
    
    echo "Tentativas de extrair 'from':\n";
    foreach ($fromAttempts as $path => $value) {
        if ($value) {
            echo "  ✅ $path: $value\n";
        } else {
            echo "  ❌ $path: NULL\n";
        }
    }
}

// 4) Preparar sugestões de INSERT
echo "\n\n4) SUGESTÕES DE INSERT (para IDs sem mapeamento):\n";
echo str_repeat("=", 100) . "\n";

if (isset($unmappedIds) && count($unmappedIds) > 0) {
    echo "Para criar os mapeamentos faltantes, você pode executar:\n\n";
    
    foreach ($unmappedIds as $unmapped) {
        if (strpos($unmapped, '@lid') !== false) {
            // Se for @lid, precisa descobrir o phone_number
            echo "-- Mapeamento para: $unmapped\n";
            echo "-- ⚠️  PRECISA DESCOBRIR O PHONE_NUMBER CORRETO\n";
            echo "INSERT INTO whatsapp_business_ids (business_id, phone_number, tenant_id, created_at, updated_at)\n";
            echo "VALUES ('$unmapped', '55XXXXXXXXXXX', 2, NOW(), NOW());\n\n";
        } elseif (strpos($unmapped, '@c.us') !== false || strpos($unmapped, '@s.whatsapp.net') !== false) {
            // Se for JID, pode extrair o número
            $cleanNumber = preg_replace('/@.*$/', '', $unmapped);
            $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);
            
            echo "-- JID: $unmapped → Phone: $cleanNumber\n";
            echo "-- ⚠️  Este é um número direto (não precisa de mapeamento @lid)\n";
            echo "-- O Hub deveria conseguir extrair isso diretamente\n\n";
        }
    }
} else {
    echo "Todos os principais IDs já têm mapeamento.\n";
}

echo "\n";

