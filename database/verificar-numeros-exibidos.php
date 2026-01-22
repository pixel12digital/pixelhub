<?php
/**
 * Script para verificar números exibidos vs números reais no banco
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
use PixelHub\Core\ContactHelper;

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE NÚMEROS EXIBIDOS ===\n\n";

// Busca algumas conversas não vinculadas para verificar os números
$stmt = $db->query("
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        t.phone as tenant_phone
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.channel_type = 'whatsapp'
      AND c.is_incoming_lead = 1
      AND (c.status IS NULL OR c.status NOT IN ('closed', 'archived'))
    ORDER BY c.last_message_at DESC, c.created_at DESC
    LIMIT 10
");

$conversations = $stmt->fetchAll();

echo "=== EXEMPLOS DE CONVERSAS NÃO VINCULADAS ===\n\n";

foreach ($conversations as $conv) {
    echo "ID: {$conv['id']}\n";
    echo "Contact External ID: " . ($conv['contact_external_id'] ?? 'NULL') . "\n";
    echo "Contact Name: " . ($conv['contact_name'] ?? 'NULL') . "\n";
    echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "Tenant Phone: " . ($conv['tenant_phone'] ?? 'NULL') . "\n";
    
    // Simula a lógica do ContactHelper
    $contactId = $conv['contact_external_id'];
    $tenantPhone = $conv['tenant_phone'];
    
    // Simula busca na tabela whatsapp_business_ids
    $realPhone = null;
    if (!empty($conv['tenant_id']) && !empty($tenantPhone)) {
        $realPhone = $tenantPhone;
    } elseif (strpos($contactId ?? '', '@lid') !== false) {
        try {
            $lidId = str_replace('@lid', '', $contactId);
            $lidBusinessId = $lidId . '@lid';
            $mapStmt = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ? LIMIT 1");
            $mapStmt->execute([$lidBusinessId]);
            $mapping = $mapStmt->fetch();
            if ($mapping && !empty($mapping['phone_number'])) {
                $realPhone = $mapping['phone_number'];
                echo "Mapeamento encontrado na tabela whatsapp_business_ids: {$realPhone}\n";
            } else {
                echo "Nenhum mapeamento encontrado na tabela whatsapp_business_ids\n";
            }
        } catch (\Exception $e) {
            echo "Erro ao buscar mapeamento: " . $e->getMessage() . "\n";
        }
    }
    
    // Formata como o ContactHelper faz
    $formatted = ContactHelper::formatContactId($contactId, $realPhone);
    echo "Número formatado (exibido): {$formatted}\n";
    
    // Análise do número
    if (strpos($contactId ?? '', '@lid') !== false) {
        $number = str_replace('@lid', '', $contactId);
        echo "Número extraído do @lid (sem sufixo): {$number}\n";
        if (strlen($number) >= 4) {
            $phoneNumber = substr($number, 4);
            echo "Número após substr(4): {$phoneNumber}\n";
        }
    }
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}

// Verifica a estrutura da tabela whatsapp_business_ids
echo "=== VERIFICAÇÃO DA TABELA whatsapp_business_ids ===\n\n";
try {
    $checkStmt = $db->query("SHOW TABLES LIKE 'whatsapp_business_ids'");
    if ($checkStmt->rowCount() > 0) {
        $descStmt = $db->query("DESCRIBE whatsapp_business_ids");
        $columns = $descStmt->fetchAll();
        echo "Tabela existe. Colunas:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}: {$col['Type']}\n";
        }
        
        $countStmt = $db->query("SELECT COUNT(*) as total FROM whatsapp_business_ids");
        $count = $countStmt->fetch()['total'];
        echo "\nTotal de registros: {$count}\n";
        
        if ($count > 0) {
            $sampleStmt = $db->query("SELECT business_id, phone_number FROM whatsapp_business_ids LIMIT 5");
            $samples = $sampleStmt->fetchAll();
            echo "\nExemplos:\n";
            foreach ($samples as $sample) {
                echo "  business_id: {$sample['business_id']} -> phone_number: {$sample['phone_number']}\n";
            }
        }
    } else {
        echo "Tabela whatsapp_business_ids não existe!\n";
    }
} catch (\Exception $e) {
    echo "Erro ao verificar tabela: " . $e->getMessage() . "\n";
}

echo "\n";
