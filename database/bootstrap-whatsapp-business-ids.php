<?php
/**
 * Script de bootstrap para inserir mapeamentos conhecidos de WhatsApp Business IDs
 * 
 * Uso: php database/bootstrap-whatsapp-business-ids.php
 */

// Autoloader simples
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

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== Bootstrap: WhatsApp Business IDs ===\n\n";

// Verifica se a tabela existe
$stmt = $db->query("SHOW TABLES LIKE 'whatsapp_business_ids'");
if ($stmt->rowCount() === 0) {
    echo "❌ Tabela whatsapp_business_ids não existe. Execute a migration primeiro.\n";
    exit(1);
}

// Mapeamentos conhecidos
$mappings = [
    [
        'business_id' => '10523374551225@lid',
        'phone_number' => '554796474223',
        'tenant_id' => 2, // Pixel12 Digital
        'description' => 'ServPro'
    ],
    // Adicione outros mapeamentos conhecidos aqui
];

echo "📋 Mapeamentos a inserir: " . count($mappings) . "\n\n";

$inserted = 0;
$skipped = 0;
$errors = 0;

foreach ($mappings as $mapping) {
    $businessId = $mapping['business_id'];
    $phoneNumber = $mapping['phone_number'];
    $tenantId = $mapping['tenant_id'] ?? null;
    $description = $mapping['description'] ?? 'N/A';
    
    echo "→ Processando: {$description} ({$businessId} → {$phoneNumber})...\n";
    
    // Verifica se já existe
    $stmt = $db->prepare("SELECT id FROM whatsapp_business_ids WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "  ⊘ Já existe (id: {$existing['id']}), pulando...\n";
        $skipped++;
        continue;
    }
    
    // Insere
    try {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_business_ids (business_id, phone_number, tenant_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$businessId, $phoneNumber, $tenantId]);
        $id = $db->lastInsertId();
        echo "  ✓ Inserido com sucesso (id: {$id})\n";
        $inserted++;
    } catch (\Exception $e) {
        echo "  ✗ Erro ao inserir: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Resumo ===\n";
echo "Inseridos: {$inserted}\n";
echo "Pulados (já existiam): {$skipped}\n";
echo "Erros: {$errors}\n\n";

if ($inserted > 0 || $skipped > 0) {
    echo "✓ Processo concluído!\n";
} else {
    echo "⚠️ Nenhum mapeamento foi processado.\n";
}

