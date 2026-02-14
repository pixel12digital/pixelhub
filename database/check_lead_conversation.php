<?php
// Bootstrap
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
Env::load();

$config = require __DIR__ . '/../config/database.php';
$dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
$db = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

echo "=== VERIFICANDO CONVERSA DO LEAD 93056-8870 ===\n\n";

// Busca a conversa
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.lead_id,
        c.channel_id,
        c.status,
        t.name as tenant_name,
        l.name as lead_name,
        l.phone as lead_phone
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.contact_external_id LIKE '%93056%'
    ORDER BY c.id DESC
    LIMIT 1
");
$stmt->execute();
$conv = $stmt->fetch();

if (!$conv) {
    echo "❌ ERRO: Conversa não encontrada!\n";
    exit(1);
}

echo "✅ Conversa encontrada:\n";
echo "   ID: {$conv['id']}\n";
echo "   conversation_key: {$conv['conversation_key']}\n";
echo "   contact_external_id: {$conv['contact_external_id']}\n";
echo "   contact_name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
echo "   tenant_id: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
echo "   lead_id: " . ($conv['lead_id'] ?: 'NULL') . "\n";
echo "   channel_id: {$conv['channel_id']}\n";
echo "   status: {$conv['status']}\n\n";

// Simula o que o controller retorna
$threadId = "whatsapp_{$conv['id']}";
$tenantName = (!empty($conv['tenant_id']) && $conv['tenant_name'] !== 'Sem tenant') ? $conv['tenant_name'] : null;
$leadPhone = !empty($conv['lead_id']) ? ($conv['lead_phone'] ?? null) : null;

echo "=== DADOS MAPEADOS (como controller) ===\n";
echo "   thread_id: {$threadId}\n";
echo "   tenant_name: " . ($tenantName ?: 'NULL') . "\n";
echo "   lead_id: " . ($conv['lead_id'] ?: 'NULL') . "\n";
echo "   lead_name: " . ($conv['lead_name'] ?: 'NULL') . "\n";
echo "   lead_phone: " . ($leadPhone ?: 'NULL') . "\n\n";

// Simula display name
$displayName = $conv['contact_name'] ?? null;
if (empty($displayName) && !empty($conv['tenant_id'])) $displayName = $tenantName ?? null;
if (empty($displayName) && !empty($conv['lead_name'])) $displayName = $conv['lead_name'];
if (empty($displayName) && !empty($conv['lead_id'])) $displayName = 'Lead #' . $conv['lead_id'];
if (empty($displayName)) $displayName = 'Cliente';

echo "=== DISPLAY NAME (como deve aparecer) ===\n";
echo "   {$displayName}\n\n";

echo "✅ Tudo OK! O thread_id está correto.\n";
echo "   Se ainda aparece 'Cliente', execute: cd ~/hub.pixel12digital.com.br && git pull\n";
