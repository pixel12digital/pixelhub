<?php
/**
 * Verificação de Sanidade - PATCH J
 * 
 * Confirma que não sobraram eventos/conversations órfãs após o PATCH J
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

echo "=== VERIFICAÇÃO DE SANIDADE - PATCH J ===\n\n";

$db = DB::getConnection();
$channelId = 'pixel12digital';

// 1. Verificar eventos órfãos
echo "1. Eventos órfãos (tenant_id=NULL) para '{$channelId}':\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT COUNT(*) as qtd
    FROM communication_events
    WHERE tenant_id IS NULL 
      AND (
          JSON_EXTRACT(metadata, '$.channel_id') = ?
          OR JSON_EXTRACT(payload, '$.session.id') = ?
          OR JSON_EXTRACT(payload, '$.sessionId') = ?
          OR JSON_EXTRACT(payload, '$.channelId') = ?
      )
");
$stmt->execute([$channelId, $channelId, $channelId, $channelId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$orphanEvents = (int) ($result['qtd'] ?? 0);

if ($orphanEvents === 0) {
    echo "✅ PASS: {$orphanEvents} eventos órfãos encontrados (esperado: 0)\n\n";
} else {
    echo "❌ FAIL: {$orphanEvents} eventos órfãos encontrados (esperado: 0)\n\n";
}

// 2. Verificar conversations órfãs
echo "2. Conversations órfãs (tenant_id=NULL) para '{$channelId}':\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT COUNT(*) as qtd
    FROM conversations
    WHERE tenant_id IS NULL 
      AND channel_id = ?
");
$stmt->execute([$channelId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$orphanConvs = (int) ($result['qtd'] ?? 0);

if ($orphanConvs === 0) {
    echo "✅ PASS: {$orphanConvs} conversations órfãs encontradas (esperado: 0)\n\n";
} else {
    echo "❌ FAIL: {$orphanConvs} conversations órfãs encontradas (esperado: 0)\n\n";
}

// 3. Verificar total de eventos atualizados para tenant_id=121
echo "3. Total de eventos com tenant_id=121 para '{$channelId}':\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT COUNT(*) as qtd
    FROM communication_events
    WHERE tenant_id = 121
      AND source_system = 'wpp_gateway'
      AND (
          JSON_EXTRACT(metadata, '$.channel_id') = ?
          OR JSON_EXTRACT(payload, '$.session.id') = ?
          OR JSON_EXTRACT(payload, '$.sessionId') = ?
          OR JSON_EXTRACT(payload, '$.channelId') = ?
      )
");
$stmt->execute([$channelId, $channelId, $channelId, $channelId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalEvents121 = (int) ($result['qtd'] ?? 0);

echo "   Total: {$totalEvents121} evento(s) com tenant_id=121\n\n";

// 4. Verificar total de conversations com tenant_id=121
echo "4. Total de conversations com tenant_id=121 para '{$channelId}':\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT COUNT(*) as qtd
    FROM conversations
    WHERE tenant_id = 121
      AND channel_id = ?
");
$stmt->execute([$channelId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalConvs121 = (int) ($result['qtd'] ?? 0);

echo "   Total: {$totalConvs121} conversation(s) com tenant_id=121\n\n";

// Resumo
echo str_repeat("=", 80) . "\n";
echo "RESUMO:\n";
echo str_repeat("=", 80) . "\n";

if ($orphanEvents === 0 && $orphanConvs === 0) {
    echo "✅ SUCESSO: Normalização completa!\n";
    echo "   - Eventos órfãos: {$orphanEvents}\n";
    echo "   - Conversations órfãs: {$orphanConvs}\n";
    echo "   - Eventos com tenant_id=121: {$totalEvents121}\n";
    echo "   - Conversations com tenant_id=121: {$totalConvs121}\n";
} else {
    echo "⚠️  ATENÇÃO: Ainda existem órfãos!\n";
    echo "   - Eventos órfãos: {$orphanEvents}\n";
    echo "   - Conversations órfãs: {$orphanConvs}\n";
}

echo "\n";

