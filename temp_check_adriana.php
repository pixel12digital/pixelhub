<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
Env::load();

$db = DB::getConnection();

// Busca Adriana
$stmt = $db->prepare("
    SELECT id, name, contact_type, status, phone, email, 
           lead_converted_at, created_at
    FROM tenants 
    WHERE name LIKE '%Adriana%'
    ORDER BY id DESC
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== REGISTROS DE ADRIANA ===\n\n";
foreach ($results as $row) {
    echo "ID: {$row['id']}\n";
    echo "Nome: {$row['name']}\n";
    echo "Tipo: {$row['contact_type']}\n";
    echo "Status: {$row['status']}\n";
    echo "Telefone: {$row['phone']}\n";
    echo "Email: {$row['email']}\n";
    echo "Convertido em: {$row['lead_converted_at']}\n";
    echo "Criado em: {$row['created_at']}\n";
    
    // Verifica se tem serviços ativos
    $stmtServices = $db->prepare("
        SELECT COUNT(*) as total 
        FROM hosting_accounts 
        WHERE tenant_id = ?
    ");
    $stmtServices->execute([$row['id']]);
    $services = $stmtServices->fetch(PDO::FETCH_ASSOC);
    echo "Serviços ativos: {$services['total']}\n";
    
    // Verifica oportunidades
    $stmtOpps = $db->prepare("
        SELECT COUNT(*) as total 
        FROM opportunities 
        WHERE tenant_id = ? OR lead_id = ?
    ");
    $stmtOpps->execute([$row['id'], $row['id']]);
    $opps = $stmtOpps->fetch(PDO::FETCH_ASSOC);
    echo "Oportunidades: {$opps['total']}\n";
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}
