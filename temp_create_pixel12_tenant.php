<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== CRIANDO TENANT PIXEL12 DIGITAL ===\n\n";

// Dados do tenant Pixel12 Digital
$tenantData = [
    'name' => 'Pixel12 Digital',
    'person_type' => 'pj',
    'cpf_cnpj' => '00000000000000', // CNPJ fictício - ajustar depois
    'document' => '00000000000000',
    'phone' => '47973095525', // Telefone da Pixel12
    'status' => 'active',
    'contact_type' => 'internal', // Empresa interna
    'source' => 'manual',
    'notes' => 'Tenant da própria Pixel12 Digital - criado automaticamente'
];

// Insere o tenant
$stmt = $db->prepare("
    INSERT INTO tenants (
        name, person_type, cpf_cnpj, document, phone, 
        status, contact_type, source, notes, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$result = $stmt->execute([
    $tenantData['name'],
    $tenantData['person_type'],
    $tenantData['cpf_cnpj'],
    $tenantData['document'],
    $tenantData['phone'],
    $tenantData['status'],
    $tenantData['contact_type'],
    $tenantData['source'],
    $tenantData['notes']
]);

if ($result) {
    $tenantId = $db->lastInsertId();
    echo "✓ Tenant criado com sucesso!\n";
    echo "  ID: {$tenantId}\n";
    echo "  Nome: {$tenantData['name']}\n\n";
    
    // Atualiza conversa 459
    echo "Vinculando conversa 459 ao tenant {$tenantId}...\n";
    $stmt = $db->prepare("UPDATE conversations SET tenant_id = ? WHERE id = 459");
    $stmt->execute([$tenantId]);
    echo "✓ Conversa 459 atualizada\n";
    
    // Atualiza oportunidade 8
    echo "Vinculando oportunidade 8 ao tenant {$tenantId}...\n";
    $stmt = $db->prepare("UPDATE opportunities SET tenant_id = ? WHERE id = 8");
    $stmt->execute([$tenantId]);
    echo "✓ Oportunidade 8 atualizada\n\n";
    
    echo "=== VERIFICAÇÃO FINAL ===\n\n";
    
    // Verifica conversa
    $stmt = $db->prepare("
        SELECT c.id, c.contact_name, c.tenant_id, t.name as tenant_name, c.lead_id, l.name as lead_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        LEFT JOIN leads l ON c.lead_id = l.id
        WHERE c.id = 459
    ");
    $stmt->execute();
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Conversa 459:\n";
    echo "  Contato: {$conv['contact_name']}\n";
    echo "  Lead: {$conv['lead_name']} (ID: {$conv['lead_id']})\n";
    echo "  Tenant: {$conv['tenant_name']} (ID: {$conv['tenant_id']})\n\n";
    
    echo "✓ CORREÇÃO CONCLUÍDA!\n";
    echo "Recarregue o Inbox para ver a conversa do Luiz Carlos vinculada à Pixel12 Digital.\n";
    
} else {
    echo "✗ Erro ao criar tenant\n";
    exit(1);
}
