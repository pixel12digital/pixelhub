<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== ANÁLISE DA ESTRUTURA DO PAYLOAD ===\n\n";

// Pegar um evento falhado recente e mostrar estrutura completa
$sql = "SELECT id, payload, error_message
FROM communication_events
WHERE source_system='wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND status='failed'
  AND error_message LIKE '%conversation_not_resolved%'
ORDER BY id DESC
LIMIT 3";

$stmt = $pdo->query($sql);
$samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($samples as $index => $sample) {
    echo str_repeat("=", 100) . "\n";
    echo "AMOSTRA #" . ($index + 1) . " - Event ID: " . $sample['id'] . "\n";
    echo str_repeat("-", 100) . "\n";
    
    $payload = json_decode($sample['payload'], true);
    
    echo "Estrutura do payload (primeiro nível):\n";
    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            echo "  - $key: [array com " . count($value) . " elementos]\n";
            // Mostrar keys do array se for pequeno
            if (count($value) <= 10) {
                echo "    Keys: " . implode(', ', array_keys($value)) . "\n";
            }
        } else {
            $valStr = is_string($value) ? substr($value, 0, 50) : (string)$value;
            echo "  - $key: $valStr\n";
        }
    }
    
    // Buscar 'from' em todos os níveis possíveis
    echo "\n🔍 Buscando 'from' em todos os níveis:\n";
    $fromPaths = [];
    
    function findKeyInArray($arr, $key, $path = '', &$results = []) {
        if (is_array($arr)) {
            foreach ($arr as $k => $v) {
                $currentPath = $path ? "$path.$k" : $k;
                if ($k === $key && !empty($v)) {
                    $results[] = ['path' => $currentPath, 'value' => $v];
                }
                if (is_array($v)) {
                    findKeyInArray($v, $key, $currentPath, $results);
                }
            }
        }
        return $results;
    }
    
    $fromPaths = findKeyInArray($payload, 'from');
    $remoteJidPaths = findKeyInArray($payload, 'remoteJid');
    $authorPaths = findKeyInArray($payload, 'author');
    
    if (count($fromPaths) > 0) {
        echo "  ✅ Encontrado 'from':\n";
        foreach ($fromPaths as $fp) {
            echo "     - $fp[path]: $fp[value]\n";
        }
    } else {
        echo "  ❌ 'from' não encontrado em nenhum nível\n";
    }
    
    if (count($remoteJidPaths) > 0) {
        echo "  ✅ Encontrado 'remoteJid':\n";
        foreach ($remoteJidPaths as $rp) {
            echo "     - $rp[path]: $rp[value]\n";
        }
    }
    
    if (count($authorPaths) > 0) {
        echo "  ✅ Encontrado 'author':\n";
        foreach ($authorPaths as $ap) {
            echo "     - $ap[path]: $ap[value]\n";
        }
    }
    
    // Verificar se é evento de grupo
    $isGroup = false;
    if (isset($payload['message']['key']['remoteJid'])) {
        $remoteJid = $payload['message']['key']['remoteJid'];
        if (strpos($remoteJid, '@g.us') !== false) {
            $isGroup = true;
            echo "\n  📢 Este é um evento de GRUPO: $remoteJid\n";
            if (isset($payload['message']['key']['participant'])) {
                echo "  👤 Participante: " . $payload['message']['key']['participant'] . "\n";
            }
        }
    }
    
    // Mostrar estrutura do message se existir
    if (isset($payload['message'])) {
        echo "\n📨 Estrutura do 'message':\n";
        $message = $payload['message'];
        foreach ($message as $key => $value) {
            if (is_array($value)) {
                echo "  - $key: [array]\n";
                if (isset($value['remoteJid'])) {
                    echo "    remoteJid: " . $value['remoteJid'] . "\n";
                }
                if (isset($value['participant'])) {
                    echo "    participant: " . $value['participant'] . "\n";
                }
                if (isset($value['from'])) {
                    echo "    from: " . $value['from'] . "\n";
                }
            } else {
                $valStr = is_string($value) ? substr($value, 0, 50) : (string)$value;
                echo "  - $key: $valStr\n";
            }
        }
    }
    
    // Mostrar estrutura do raw se existir
    if (isset($payload['raw']['payload'])) {
        echo "\n🔧 Estrutura do 'raw.payload':\n";
        $rawPayload = $payload['raw']['payload'];
        echo "  Keys: " . implode(', ', array_keys($rawPayload)) . "\n";
        if (isset($rawPayload['key'])) {
            echo "  key.remoteJid: " . ($rawPayload['key']['remoteJid'] ?? 'NULL') . "\n";
            echo "  key.participant: " . ($rawPayload['key']['participant'] ?? 'NULL') . "\n";
        }
        if (isset($rawPayload['messageStubParameters'])) {
            echo "  messageStubParameters: " . json_encode($rawPayload['messageStubParameters']) . "\n";
        }
    }
    
    echo "\n";
}

echo "\n";

