<?php
/**
 * AUDITORIA: Duplicidade de Sessão no Inbound
 * 
 * Verifica se há duplicidade de registros de tenant_message_channels
 * que podem estar causando roteamento incorreto do inbound.
 * 
 * Foco: sessionId 'pixel12digital'
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== AUDITORIA: DUPLICIDADE DE SESSÃO NO INBOUND ===\n\n";

$db = DB::getConnection();

// ==========================================
// 1. REGISTROS PARA SESSÃO pixel12digital
// ==========================================

echo "1. REGISTROS PARA SESSÃO 'pixel12digital':\n";
echo str_repeat("-", 80) . "\n";

// Verifica se existe coluna session_id
$hasSessionId = false;
try {
    $checkStmt = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'");
    $hasSessionId = $checkStmt->fetch() !== false;
} catch (\Exception $e) {
    // Ignora erro
}

$sql = "
    SELECT id, tenant_id, provider, channel_id, is_enabled, created_at, updated_at
    " . ($hasSessionId ? ", COALESCE(session_id, 'NULL') as session_id" : ", 'N/A' as session_id") . "
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway' 
    AND channel_id = 'pixel12digital'
    " . ($hasSessionId ? "OR session_id = 'pixel12digital'" : "") . "
    ORDER BY is_enabled DESC, id DESC
";

$stmt = $db->prepare($sql);

$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    echo "❌ NENHUM registro encontrado para 'pixel12digital'\n";
} else {
    echo "Encontrados " . count($records) . " registro(s):\n\n";
    foreach ($records as $idx => $record) {
        echo sprintf(
            "  [%d] ID=%d | tenant_id=%d | channel_id='%s' | session_id='%s' | is_enabled=%d | created_at=%s\n",
            $idx + 1,
            $record['id'],
            $record['tenant_id'],
            $record['channel_id'] ?: 'NULL',
            $record['session_id'] ?: 'NULL',
            $record['is_enabled'],
            $record['created_at']
        );
    }
    
    // Verifica duplicidade
    $enabledCount = array_sum(array_column($records, 'is_enabled'));
    $uniqueTenants = array_unique(array_column($records, 'tenant_id'));
    
    if ($enabledCount > 1) {
        echo "\n⚠️  ATENÇÃO: {$enabledCount} registro(s) habilitado(s) para 'pixel12digital'!\n";
        echo "   Isso pode causar roteamento não-determinístico no inbound.\n";
    }
    
    if (count($uniqueTenants) > 1) {
        echo "\n⚠️  ATENÇÃO: Sessão 'pixel12digital' mapeada para múltiplos tenants: " . implode(', ', $uniqueTenants) . "\n";
        echo "   O inbound pode rotear para o tenant errado.\n";
    }
}

echo "\n\n";

// ==========================================
// 2. SESSÕES HABILITADAS POR TENANT
// ==========================================

echo "2. SESSÕES HABILITADAS POR TENANT:\n";
echo str_repeat("-", 80) . "\n";

$sql2 = "
    SELECT tenant_id, provider, channel_id, is_enabled
    " . ($hasSessionId ? ", COALESCE(session_id, 'NULL') as session_id" : ", 'N/A' as session_id") . "
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway' 
    AND is_enabled = 1
    ORDER BY channel_id, tenant_id
";

$stmt2 = $db->query($sql2);
$channels = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "❌ NENHUMA sessão habilitada encontrada\n";
} else {
    // Agrupa por tenant_id
    $byTenant = [];
    foreach ($channels as $channel) {
        $tenantId = $channel['tenant_id'];
        if (!isset($byTenant[$tenantId])) {
            $byTenant[$tenantId] = [];
        }
        $byTenant[$tenantId][] = $channel;
    }
    
    foreach ($byTenant as $tenantId => $tenantChannels) {
        $warning = ($tenantId == 121) ? '⚠️ TENANT RECÉM-CRIADO' : '';
        echo "\n  Tenant ID: {$tenantId}" . ($warning ? " ({$warning})" : '') . "\n";
        foreach ($tenantChannels as $channel) {
            $sessionDisplay = $channel['session_id'] !== 'NULL' ? $channel['session_id'] : $channel['channel_id'];
            echo sprintf(
                "    - channel_id='%s' | session_id='%s' | display='%s'\n",
                $channel['channel_id'] ?: 'NULL',
                $channel['session_id'] ?: 'NULL',
                $sessionDisplay
            );
        }
    }
    
    // Verifica duplicidade de channel_id entre tenants
    $channelToTenants = [];
    foreach ($channels as $channel) {
        $key = $channel['channel_id'] ?: 'NULL';
        if (!isset($channelToTenants[$key])) {
            $channelToTenants[$key] = [];
        }
        if (!in_array($channel['tenant_id'], $channelToTenants[$key])) {
            $channelToTenants[$key][] = $channel['tenant_id'];
        }
    }
    
    $duplicates = array_filter($channelToTenants, function($tenants) {
        return count($tenants) > 1;
    });
    
    if (!empty($duplicates)) {
        echo "\n\n⚠️  ATENÇÃO: DUPLICIDADE DETECTADA!\n";
        echo "   Os seguintes channel_id estão habilitados para múltiplos tenants:\n\n";
        foreach ($duplicates as $channelId => $tenantIds) {
            echo "    channel_id='{$channelId}': tenants " . implode(', ', $tenantIds) . "\n";
            echo "      → O inbound pode rotear para qualquer um deles (LIMIT 1 não-determinístico)!\n";
        }
    }
}

echo "\n\n";

// ==========================================
// 3. EVENTOS RECENTES DO INBOUND
// ==========================================

echo "3. EVENTOS RECENTES DO INBOUND (sessionId 'pixel12digital'):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.event_type, ce.tenant_id, 
           ce.metadata, ce.created_at,
           JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
    AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.sessionId') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.channelId') = 'pixel12digital'
    )
    ORDER BY ce.created_at DESC
    LIMIT 30
");

$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ NENHUM evento recente encontrado para 'pixel12digital'\n";
} else {
    echo "Encontrados " . count($events) . " evento(s) recente(s):\n\n";
    
    // Agrupa por tenant_id
    $byTenant = [];
    foreach ($events as $event) {
        $tenantId = $event['tenant_id'] ?: 'NULL';
        if (!isset($byTenant[$tenantId])) {
            $byTenant[$tenantId] = [];
        }
        $byTenant[$tenantId][] = $event;
    }
    
    foreach ($byTenant as $tenantId => $tenantEvents) {
        $warning = ($tenantId == 121) ? '⚠️ TENANT RECÉM-CRIADO' : '';
        echo "  Tenant ID: {$tenantId}" . ($warning ? " ({$warning})" : '') . " - {$tenantEvents[0]['created_at']} até {$tenantEvents[count($tenantEvents)-1]['created_at']}\n";
        echo "    Total: " . count($tenantEvents) . " evento(s)\n";
        echo "    Primeiro evento: " . $tenantEvents[count($tenantEvents)-1]['event_id'] . "\n";
        echo "    Último evento: " . $tenantEvents[0]['event_id'] . "\n";
    }
    
    if (count($byTenant) > 1) {
        echo "\n⚠️  ATENÇÃO: Eventos do inbound roteados para múltiplos tenants!\n";
        echo "   Isso confirma que há duplicidade ou roteamento incorreto.\n";
    }
}

echo "\n\n";

// ==========================================
// 4. COMPARAÇÃO: ANTES vs DEPOIS (aproximada)
// ==========================================

echo "4. ANÁLISE TEMPORAL (eventos por data):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT DATE(ce.created_at) as event_date,
           ce.tenant_id,
           COUNT(*) as event_count
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
    AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.sessionId') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.channelId') = 'pixel12digital'
    )
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(ce.created_at), ce.tenant_id
    ORDER BY event_date DESC, ce.tenant_id
");

$stmt->execute();
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($dailyStats)) {
    echo "❌ Nenhum evento nos últimos 7 dias\n";
} else {
    $currentDate = null;
    foreach ($dailyStats as $stat) {
        if ($currentDate !== $stat['event_date']) {
            if ($currentDate !== null) {
                echo "\n";
            }
            $currentDate = $stat['event_date'];
            echo "  {$currentDate}:\n";
        }
        echo sprintf(
            "    tenant_id=%s: %d evento(s)%s\n",
            $stat['tenant_id'] ?: 'NULL',
            $stat['event_count'],
            $stat['tenant_id'] == 121 ? ' ⚠️ TENANT RECÉM-CRIADO' : ''
        );
    }
}

echo "\n\n";

// ==========================================
// 5. RESUMO E RECOMENDAÇÕES
// ==========================================

echo "5. RESUMO E RECOMENDAÇÕES:\n";
echo str_repeat("-", 80) . "\n\n";

// Re-executa query de duplicidade para resumo
$sql3 = "
    SELECT tenant_id, COUNT(*) as count
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway' 
    AND channel_id = 'pixel12digital'
    " . ($hasSessionId ? "OR session_id = 'pixel12digital'" : "") . "
    AND is_enabled = 1
    GROUP BY tenant_id
";
$stmt = $db->prepare($sql3);

$stmt->execute();
$duplicateSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($duplicateSummary) > 1) {
    echo "❌ PROBLEMA CONFIRMADO: Duplicidade de sessão detectada!\n\n";
    echo "   A sessão 'pixel12digital' está habilitada para " . count($duplicateSummary) . " tenant(s):\n";
    foreach ($duplicateSummary as $summary) {
        echo "     - Tenant ID: {$summary['tenant_id']}\n";
    }
    echo "\n   IMPACTO:\n";
    echo "   - O inbound usa 'LIMIT 1' na query de resolução de tenant.\n";
    echo "   - Com múltiplos registros habilitados, o resultado é não-determinístico.\n";
    echo "   - Mensagens podem ser roteadas para o tenant errado.\n\n";
    
    echo "   CORREÇÃO RECOMENDADA:\n";
    echo "   1. Desabilitar temporariamente o registro do tenant 121 (is_enabled = 0)\n";
    echo "   2. Verificar se o inbound volta a funcionar corretamente\n";
    echo "   3. Se sim, confirmar que a duplicidade é a causa\n";
    echo "   4. Implementar solução definitiva (ex: constraint UNIQUE, owner_tenant_id)\n";
} else {
    echo "✅ Não foi detectada duplicidade óbvia de sessão habilitada.\n";
    echo "   Mas verifique os resultados acima para outros problemas potenciais.\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Auditoria concluída.\n";

