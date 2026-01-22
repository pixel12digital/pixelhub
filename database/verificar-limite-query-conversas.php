<?php
/**
 * Script para verificar se o LIMIT 100 na query está causando discrepância
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

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE LIMITE NA QUERY ===\n\n";

// Simula a query exata que está sendo usada no controller
$where = ["c.channel_type = 'whatsapp'"];
$where[] = "c.status NOT IN ('closed', 'archived')"; // status = 'active'
$whereClause = "WHERE " . implode(" AND ", $where);

// Total de conversas que correspondem aos filtros
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM conversations c
    {$whereClause}
");
$stmt->execute();
$total = $stmt->fetch()['total'] ?? 0;
echo "Total de conversas WhatsApp ativas: {$total}\n";

// Total de conversas com is_incoming_lead = 1 (que deveriam aparecer como não vinculadas)
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM conversations c
    {$whereClause}
    AND c.is_incoming_lead = 1
");
$stmt->execute();
$totalIncomingLeads = $stmt->fetch()['total'] ?? 0;
echo "Total de conversas com is_incoming_lead = 1: {$totalIncomingLeads}\n";

// Simula a query com LIMIT 100
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.tenant_id,
        c.is_incoming_lead,
        c.status,
        COALESCE(c.last_message_at, c.created_at) as last_activity
    FROM conversations c
    {$whereClause}
    ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.created_at DESC
    LIMIT 100
");
$stmt->execute();
$limitedResults = $stmt->fetchAll();

// Conta quantas são incoming leads nos primeiros 100 resultados
$incomingLeadsInLimit = 0;
foreach ($limitedResults as $conv) {
    if (!empty($conv['is_incoming_lead'])) {
        $incomingLeadsInLimit++;
    }
}

echo "Conversas retornadas pela query (LIMIT 100): " . count($limitedResults) . "\n";
echo "Conversas com is_incoming_lead = 1 nas primeiras 100: {$incomingLeadsInLimit}\n";

if ($totalIncomingLeads > 100) {
    echo "\n⚠️  PROBLEMA: Há {$totalIncomingLeads} conversas com is_incoming_lead = 1, mas a query só retorna 100 conversas no total.\n";
    echo "O badge mostra {$totalIncomingLeads}, mas apenas {$incomingLeadsInLimit} aparecem na lista (se estiverem entre as primeiras 100 por data).\n";
}

// Verifica quantas conversas com is_incoming_lead = 1 estão fora das primeiras 100
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM conversations c
    {$whereClause}
    AND c.is_incoming_lead = 1
    AND c.id NOT IN (
        SELECT id FROM (
            SELECT c2.id
            FROM conversations c2
            {$whereClause}
            ORDER BY COALESCE(c2.last_message_at, c2.created_at) DESC, c2.created_at DESC
            LIMIT 100
        ) as first_100
    )
");
$stmt->execute();
$incomingLeadsOutsideLimit = $stmt->fetch()['total'] ?? 0;

if ($incomingLeadsOutsideLimit > 0) {
    echo "\n⚠️  Há {$incomingLeadsOutsideLimit} conversas com is_incoming_lead = 1 que NÃO estão sendo retornadas pela query (fora das primeiras 100).\n";
    echo "Essas conversas não aparecem na lista, mas são contadas no badge.\n";
}

echo "\n";
