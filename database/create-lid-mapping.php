<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== CRIAR MAPEAMENTO @lid ===\n\n";

$businessId = '208989199560861@lid';
$phoneNumber = '554797146908'; // Extraído do payload
$tenantId = 2;

// Verificar se já existe
$checkStmt = $pdo->prepare("SELECT * FROM whatsapp_business_ids WHERE business_id = ?");
$checkStmt->execute([$businessId]);
$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "⚠️  Mapeamento já existe:\n";
    echo json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    echo "Deseja atualizar? (Execute manualmente se necessário)\n";
} else {
    echo "Criando mapeamento...\n";
    echo "  business_id: $businessId\n";
    echo "  phone_number: $phoneNumber\n";
    echo "  tenant_id: $tenantId\n\n";
    
    try {
        $insertStmt = $pdo->prepare("
            INSERT INTO whatsapp_business_ids (business_id, phone_number, tenant_id, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $insertStmt->execute([$businessId, $phoneNumber, $tenantId]);
        
        $newId = $pdo->lastInsertId();
        echo "✅ Mapeamento criado com sucesso! ID: $newId\n\n";
        
        // Verificar
        $verifyStmt = $pdo->prepare("SELECT * FROM whatsapp_business_ids WHERE id = ?");
        $verifyStmt->execute([$newId]);
        $created = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Mapeamento criado:\n";
        echo json_encode($created, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
    } catch (Exception $e) {
        echo "❌ Erro ao criar mapeamento: " . $e->getMessage() . "\n";
    }
}

echo "\n";

