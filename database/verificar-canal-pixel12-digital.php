<?php

/**
 * Script para verificar se o canal "Pixel 12 Digital" existe no banco
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando Canal 'Pixel 12 Digital' ===\n\n";

// 1. Verifica estrutura da tabela primeiro
echo "1. Verificando estrutura da tabela tenant_message_channels:\n";
$stmt = $db->query("DESCRIBE tenant_message_channels");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasSessionId = false;
echo "   Colunas encontradas:\n";
foreach ($columns as $col) {
    echo "   - {$col['Field']} ({$col['Type']})\n";
    if ($col['Field'] === 'session_id') {
        $hasSessionId = true;
    }
}

// 2. Verifica se o canal existe exatamente como "Pixel 12 Digital"
echo "\n2. Buscando canal exato 'Pixel 12 Digital':\n";
$selectFields = $hasSessionId 
    ? "id, tenant_id, provider, channel_id, session_id, is_enabled, created_at"
    : "id, tenant_id, provider, channel_id, is_enabled, created_at";

$stmt = $db->prepare("
    SELECT {$selectFields}
    FROM tenant_message_channels
    WHERE channel_id = 'Pixel 12 Digital'
       OR channel_id = 'pixel12digital'
       OR channel_id = 'Pixel12 Digital'
       OR LOWER(channel_id) = 'pixel 12 digital'
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ❌ Nenhum canal encontrado com nome exato\n\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $channel) {
        echo "   - ID: {$channel['id']}\n";
        echo "     Tenant ID: " . ($channel['tenant_id'] ?: 'NULL') . "\n";
        echo "     Provider: {$channel['provider']}\n";
        echo "     Channel ID: {$channel['channel_id']}\n";
        if ($hasSessionId && isset($channel['session_id'])) {
            echo "     Session ID: " . ($channel['session_id'] ?: 'NULL') . "\n";
        }
        echo "     Is Enabled: {$channel['is_enabled']}\n";
        echo "     Created At: {$channel['created_at']}\n\n";
    }
}

// 3. Verifica todos os canais relacionados a pixel12
echo "3. Buscando todos os canais relacionados a 'pixel12':\n";
$selectFields2 = $hasSessionId 
    ? "id, tenant_id, provider, channel_id, session_id, is_enabled"
    : "id, tenant_id, provider, channel_id, is_enabled";

$stmt2 = $db->prepare("
    SELECT {$selectFields2}
    FROM tenant_message_channels
    WHERE LOWER(channel_id) LIKE '%pixel12%'
       OR LOWER(channel_id) LIKE '%pixel%'
    ORDER BY channel_id
");
$stmt2->execute();
$allChannels = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($allChannels)) {
    echo "   ❌ Nenhum canal encontrado relacionado a pixel12\n\n";
} else {
    echo "   ✅ Encontrados " . count($allChannels) . " canal(is):\n";
    foreach ($allChannels as $channel) {
        echo "   - Channel ID: {$channel['channel_id']}\n";
        if ($hasSessionId && isset($channel['session_id'])) {
            echo "     Session ID: " . ($channel['session_id'] ?: 'NULL') . "\n";
        }
        echo "     Tenant ID: " . ($channel['tenant_id'] ?: 'NULL') . "\n";
        echo "     Is Enabled: {$channel['is_enabled']}\n\n";
    }
}

// 4. Testa busca case-insensitive
echo "4. Testando busca case-insensitive:\n";
$testNames = [
    'Pixel 12 Digital',
    'pixel 12 digital',
    'PIXEL 12 DIGITAL',
    'Pixel12 Digital',
    'pixel12digital',
    'pixel12 digital'
];

foreach ($testNames as $testName) {
    $stmt4 = $db->prepare("
        SELECT channel_id" . ($hasSessionId ? ", session_id" : "") . "
        FROM tenant_message_channels
        WHERE LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
           OR channel_id = ?
           OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', ''))
    ");
    $stmt4->execute([$testName, $testName, $testName]);
    $result = $stmt4->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $sessionInfo = $hasSessionId && isset($result['session_id']) ? ", session_id=" . ($result['session_id'] ?: 'NULL') : "";
        echo "   ✅ '{$testName}' encontrado: channel_id={$result['channel_id']}{$sessionInfo}\n";
    } else {
        echo "   ❌ '{$testName}' NÃO encontrado\n";
    }
}

// 5. Verifica todos os canais habilitados
echo "\n5. Todos os canais habilitados (wpp_gateway):\n";
$stmt5 = $db->prepare("
    SELECT {$selectFields2}
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
      AND is_enabled = 1
    ORDER BY channel_id
");
$stmt5->execute();
$enabledChannels = $stmt5->fetchAll(PDO::FETCH_ASSOC);

if (empty($enabledChannels)) {
    echo "   ❌ Nenhum canal habilitado encontrado\n";
} else {
    echo "   ✅ Encontrados " . count($enabledChannels) . " canal(is) habilitado(s):\n";
    foreach ($enabledChannels as $channel) {
        echo "   - Channel ID: {$channel['channel_id']}\n";
        if ($hasSessionId && isset($channel['session_id'])) {
            echo "     Session ID: " . ($channel['session_id'] ?: 'NULL') . "\n";
        }
        echo "     Tenant ID: " . ($channel['tenant_id'] ?: 'NULL') . "\n\n";
    }
}

echo "\n=== Fim da verificação ===\n";
