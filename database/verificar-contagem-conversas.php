<?php
/**
 * Script para verificar discrepância entre contagem exibida e números reais
 * de conversas não vinculadas
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

echo "=== DIAGNÓSTICO DE CONTAGEM DE CONVERSAS NÃO VINCULADAS ===\n\n";

// 1. Conta conversas com is_incoming_lead = 1 (o que a UI mostra)
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
      AND is_incoming_lead = 1
      AND (status IS NULL OR status NOT IN ('closed', 'archived'))
");
$countWithFlag = $stmt->fetch()['total'] ?? 0;
echo "1. Conversas com is_incoming_lead = 1 (ativa): {$countWithFlag}\n";

// 2. Conta conversas sem tenant_id mas sem a flag
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
      AND (tenant_id IS NULL OR tenant_id = 0)
      AND (is_incoming_lead IS NULL OR is_incoming_lead = 0)
      AND (status IS NULL OR status NOT IN ('closed', 'archived'))
");
$countWithoutFlag = $stmt->fetch()['total'] ?? 0;
echo "2. Conversas sem tenant_id mas SEM flag is_incoming_lead: {$countWithoutFlag}\n";

// 3. Conta todas as conversas sem tenant_id (independente da flag)
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
      AND (tenant_id IS NULL OR tenant_id = 0)
      AND (status IS NULL OR status NOT IN ('closed', 'archived'))
");
$countAllWithoutTenant = $stmt->fetch()['total'] ?? 0;
echo "3. TOTAL de conversas sem tenant_id (independente da flag): {$countAllWithoutTenant}\n";

// 4. Verifica inconsistências: conversas com tenant_id mas com flag is_incoming_lead = 1
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
      AND tenant_id IS NOT NULL 
      AND tenant_id != 0
      AND is_incoming_lead = 1
      AND (status IS NULL OR status NOT IN ('closed', 'archived'))
");
$countInconsistent = $stmt->fetch()['total'] ?? 0;
echo "4. Conversas COM tenant_id mas com flag is_incoming_lead = 1 (INCONSISTENTE): {$countInconsistent}\n";

// 5. Busca alguns exemplos de conversas sem tenant_id mas sem flag
echo "\n=== EXEMPLOS DE CONVERSAS SEM TENANT_ID MAS SEM FLAG ===\n";
$stmt = $db->query("
    SELECT id, conversation_key, contact_external_id, contact_name, tenant_id, is_incoming_lead, status, created_at
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
      AND (tenant_id IS NULL OR tenant_id = 0)
      AND (is_incoming_lead IS NULL OR is_incoming_lead = 0)
      AND (status IS NULL OR status NOT IN ('closed', 'archived'))
    ORDER BY created_at DESC
    LIMIT 5
");
$examples = $stmt->fetchAll();
if (empty($examples)) {
    echo "Nenhuma conversa encontrada com essa condição.\n";
} else {
    foreach ($examples as $ex) {
        echo sprintf(
            "ID: %d | Contact: %s | Tenant: %s | Flag: %s | Status: %s\n",
            $ex['id'],
            $ex['contact_external_id'] ?? 'NULL',
            $ex['tenant_id'] ?? 'NULL',
            $ex['is_incoming_lead'] ?? 'NULL',
            $ex['status'] ?? 'NULL'
        );
    }
}

echo "\n=== CONCLUSÃO ===\n";
echo "Número exibido no badge (is_incoming_lead = 1): {$countWithFlag}\n";
echo "Número REAL de conversas sem tenant (deveria ser exibido): {$countAllWithoutTenant}\n";
echo "Diferença: " . abs($countAllWithoutTenant - $countWithFlag) . "\n";

if ($countAllWithoutTenant > $countWithFlag) {
    echo "\n⚠️  PROBLEMA DETECTADO: Há " . ($countAllWithoutTenant - $countWithFlag) . " conversas sem tenant_id que não estão sendo contadas porque não têm a flag is_incoming_lead = 1.\n";
    echo "Essas conversas provavelmente foram criadas antes da lógica de is_incoming_lead ser implementada.\n";
}

if ($countInconsistent > 0) {
    echo "\n⚠️  INCONSISTÊNCIA: Há {$countInconsistent} conversas COM tenant_id mas com flag is_incoming_lead = 1.\n";
    echo "Essas conversas não deveriam aparecer como 'não vinculadas'.\n";
}

echo "\n";
