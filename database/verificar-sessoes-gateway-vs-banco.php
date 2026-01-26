<?php

/**
 * Script para verificar se as sessões do gateway correspondem aos canais no banco
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

echo "=== Verificando Sessões do Gateway vs Banco ===\n\n";

// Sessões conectadas no gateway (conforme imagem)
$gatewaySessions = [
    'pixel12digital',
    'imobsites'
];

echo "Sessões conectadas no gateway:\n";
foreach ($gatewaySessions as $session) {
    echo "  - {$session}\n";
}

echo "\n=== Verificando no banco de dados ===\n\n";

// Verifica estrutura da tabela
$stmt = $db->query("DESCRIBE tenant_message_channels");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasSessionId = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'session_id') {
        $hasSessionId = true;
        break;
    }
}

echo "1. Verificando canais no banco:\n";
echo "----------------------------------------\n";

$selectFields = $hasSessionId 
    ? "id, tenant_id, provider, channel_id, session_id, is_enabled"
    : "id, tenant_id, provider, channel_id, is_enabled";

$stmt = $db->prepare("
    SELECT {$selectFields}
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    ORDER BY channel_id
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "❌ Nenhum canal encontrado no banco\n";
} else {
    echo "✅ Encontrados " . count($channels) . " canal(is) no banco:\n\n";
    foreach ($channels as $channel) {
        $channelId = $channel['channel_id'];
        $sessionId = $hasSessionId && isset($channel['session_id']) ? $channel['session_id'] : null;
        
        // Normaliza para comparação (remove espaços, lowercase)
        $normalizedChannelId = strtolower(preg_replace('/\s+/', '', $channelId));
        $normalizedSessionId = $sessionId ? strtolower(preg_replace('/\s+/', '', $sessionId)) : null;
        
        // Verifica se corresponde a alguma sessão do gateway
        $matchesGateway = false;
        $matchedSession = null;
        foreach ($gatewaySessions as $gatewaySession) {
            $normalizedGateway = strtolower(preg_replace('/\s+/', '', $gatewaySession));
            if ($normalizedChannelId === $normalizedGateway || 
                ($normalizedSessionId && $normalizedSessionId === $normalizedGateway)) {
                $matchesGateway = true;
                $matchedSession = $gatewaySession;
                break;
            }
        }
        
        $status = $matchesGateway ? "✅" : "⚠️";
        echo "{$status} ID: {$channel['id']}\n";
        echo "   Channel ID: {$channelId}\n";
        if ($sessionId) {
            echo "   Session ID: {$sessionId}\n";
        }
        echo "   Tenant ID: " . ($channel['tenant_id'] ?: 'NULL') . "\n";
        echo "   Is Enabled: " . ($channel['is_enabled'] ? 'Sim' : 'Não') . "\n";
        if ($matchesGateway) {
            echo "   ✅ CORRESPONDE à sessão do gateway: {$matchedSession}\n";
        } else {
            echo "   ⚠️  NÃO corresponde a nenhuma sessão do gateway\n";
        }
        echo "\n";
    }
}

echo "\n2. Verificando correspondência específica:\n";
echo "----------------------------------------\n";

foreach ($gatewaySessions as $gatewaySession) {
    echo "Sessão do gateway: '{$gatewaySession}'\n";
    
    // Busca exata
    $normalizedGateway = strtolower(preg_replace('/\s+/', '', $gatewaySession));
    
    $stmt = $db->prepare("
        SELECT {$selectFields}
        FROM tenant_message_channels
        WHERE provider = 'wpp_gateway'
        AND (
            channel_id = ?
            OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
            OR LOWER(REPLACE(channel_id, ' ', '')) = ?
            " . ($hasSessionId ? "OR session_id = ? OR LOWER(REPLACE(session_id, ' ', '')) = ?" : "") . "
        )
        LIMIT 5
    ");
    
    $params = [$gatewaySession, $gatewaySession, $normalizedGateway];
    if ($hasSessionId) {
        $params[] = $gatewaySession;
        $params[] = $normalizedGateway;
    }
    
    $stmt->execute($params);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($matches)) {
        echo "   ❌ NÃO encontrado no banco\n";
        echo "   ⚠️  Esta sessão do gateway não está cadastrada no banco!\n";
    } else {
        echo "   ✅ Encontrado no banco:\n";
        foreach ($matches as $match) {
            echo "      - Channel ID: {$match['channel_id']}\n";
            if ($hasSessionId && isset($match['session_id'])) {
                echo "        Session ID: " . ($match['session_id'] ?: 'NULL') . "\n";
            }
            echo "        Tenant ID: " . ($match['tenant_id'] ?: 'NULL') . "\n";
            echo "        Is Enabled: " . ($match['is_enabled'] ? 'Sim' : 'Não') . "\n";
        }
    }
    echo "\n";
}

echo "\n3. Recomendações:\n";
echo "----------------------------------------\n";

// Verifica se há sessões do gateway sem correspondência no banco
$allChannelsNormalized = [];
foreach ($channels as $channel) {
    $normalized = strtolower(preg_replace('/\s+/', '', $channel['channel_id']));
    $allChannelsNormalized[] = $normalized;
    if ($hasSessionId && isset($channel['session_id']) && $channel['session_id']) {
        $normalizedSession = strtolower(preg_replace('/\s+/', '', $channel['session_id']));
        $allChannelsNormalized[] = $normalizedSession;
    }
}

$missingSessions = [];
foreach ($gatewaySessions as $gatewaySession) {
    $normalizedGateway = strtolower(preg_replace('/\s+/', '', $gatewaySession));
    if (!in_array($normalizedGateway, $allChannelsNormalized)) {
        $missingSessions[] = $gatewaySession;
    }
}

if (!empty($missingSessions)) {
    echo "⚠️  Sessões do gateway SEM correspondência no banco:\n";
    foreach ($missingSessions as $missing) {
        echo "   - {$missing}\n";
    }
    echo "\n   AÇÃO NECESSÁRIA: Cadastrar essas sessões na tabela tenant_message_channels\n";
} else {
    echo "✅ Todas as sessões do gateway têm correspondência no banco\n";
}

echo "\n=== Fim da verificação ===\n";

