<?php
/**
 * Script para verificar registro de log genérico de WhatsApp
 * 
 * Uso: php database/check-whatsapp-generic-log.php [tenant_id]
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
use PDO;

// Carrega .env
Env::load();

// Obtém configurações
$host = Env::get('DB_HOST', 'localhost');
$port = Env::get('DB_PORT', '3306');
$database = Env::get('DB_NAME', 'pixel_hub');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');
$charset = Env::get('DB_CHARSET', 'utf8mb4');

$tenantId = isset($argv[1]) ? (int) $argv[1] : 25;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );
    
    $db = new PDO(
        $dsn,
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Busca último registro do tenant
    $stmt = $db->prepare("
        SELECT 
            wgl.*,
            t.name as tenant_name,
            wt.name as template_name
        FROM whatsapp_generic_logs wgl
        LEFT JOIN tenants t ON wgl.tenant_id = t.id
        LEFT JOIN whatsapp_templates wt ON wgl.template_id = wt.id
        WHERE wgl.tenant_id = ?
        ORDER BY wgl.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        echo "✅ Registro encontrado para tenant_id = {$tenantId}\n\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID:              {$log['id']}\n";
        echo "Tenant ID:       {$log['tenant_id']} ({$log['tenant_name']})\n";
        echo "Template ID:     " . ($log['template_id'] ?: 'NULL') . ($log['template_name'] ? " ({$log['template_name']})" : '') . "\n";
        echo "Phone:           {$log['phone']}\n";
        echo "Sent At:         {$log['sent_at']}\n";
        echo "Created At:      {$log['created_at']}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Mensagem:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo wordwrap($log['message'], 80) . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        // Verifica se o telefone está normalizado corretamente
        $phoneNormalized = preg_replace('/[^0-9]/', '', $log['phone']);
        if (strlen($phoneNormalized) >= 12 && substr($phoneNormalized, 0, 2) === '55') {
            echo "✅ Telefone está normalizado corretamente (formato: 55XXXXXXXXXXX)\n";
        } else {
            echo "⚠️  Telefone pode não estar normalizado corretamente\n";
        }
    } else {
        echo "❌ Nenhum registro encontrado para tenant_id = {$tenantId}\n";
        echo "\nVerificando se existem registros na tabela...\n";
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM whatsapp_generic_logs");
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Total de registros na tabela: {$total['total']}\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

