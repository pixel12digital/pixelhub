<?php
// Carrega variáveis de ambiente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$db = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== VERIFICAÇÃO CONFIGURAÇÃO META API ===\n\n";

// 1. Verifica configuração Meta
$stmt = $db->prepare("
    SELECT id, tenant_id, meta_business_account_id, meta_phone_number_id, 
           is_active, is_global, created_at
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official'
");
$stmt->execute();
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Configurações Meta encontradas: " . count($configs) . "\n\n";

foreach ($configs as $config) {
    echo "ID: {$config['id']}\n";
    echo "Tenant ID: " . ($config['tenant_id'] ?: 'NULL (global)') . "\n";
    echo "Business Account ID: {$config['meta_business_account_id']}\n";
    echo "Phone Number ID: {$config['meta_phone_number_id']}\n";
    echo "Ativa: " . ($config['is_active'] ? 'SIM' : 'NÃO') . "\n";
    echo "Global: " . ($config['is_global'] ? 'SIM' : 'NÃO') . "\n";
    echo "Criada em: {$config['created_at']}\n";
    echo "---\n\n";
}

// 2. Verifica templates aprovados
$stmt = $db->query("
    SELECT id, template_name, category, status, language
    FROM whatsapp_templates
    WHERE status = 'approved'
    ORDER BY template_name
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Templates aprovados: " . count($templates) . "\n\n";

foreach ($templates as $template) {
    echo "ID: {$template['id']}\n";
    echo "Nome: {$template['template_name']}\n";
    echo "Categoria: {$template['category']}\n";
    echo "Idioma: {$template['language']}\n";
    echo "---\n\n";
}

// 3. Verifica últimos logs de erro
echo "=== ÚLTIMOS ERROS NO LOG ===\n\n";
$logFile = __DIR__ . '/logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    
    foreach ($lastLines as $line) {
        if (stripos($line, 'meta') !== false || stripos($line, 'template') !== false) {
            echo $line;
        }
    }
} else {
    echo "Arquivo de log não encontrado\n";
}
