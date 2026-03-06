<?php
/**
 * Verifica dados do lead 30 e da oportunidade
 */

$host = 'localhost';
$dbname = 'pixel12digital_pixelhub';
$user = 'root';
$pass = '';

try {
    $db = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICANDO LEAD 30 ===\n\n";
    
    // Busca dados do lead
    $stmt = $db->prepare("SELECT id, name, phone, email FROM leads WHERE id = 30");
    $stmt->execute();
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lead) {
        echo "Lead encontrado:\n";
        echo "  ID: {$lead['id']}\n";
        echo "  Nome: {$lead['name']}\n";
        echo "  Telefone: {$lead['phone']}\n";
        echo "  Email: {$lead['email']}\n\n";
    } else {
        echo "❌ Lead 30 não encontrado\n\n";
    }
    
    // Busca oportunidade vinculada ao lead 30
    echo "=== VERIFICANDO OPORTUNIDADE DO LEAD 30 ===\n\n";
    
    $stmt = $db->prepare("
        SELECT o.*, 
               l.name as lead_name, 
               l.phone as lead_phone, 
               l.email as lead_email
        FROM opportunities o
        LEFT JOIN leads l ON l.id = o.lead_id
        WHERE o.lead_id = 30
        ORDER BY o.id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $opp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($opp) {
        echo "Oportunidade encontrada:\n";
        echo "  ID: {$opp['id']}\n";
        echo "  Nome: {$opp['name']}\n";
        echo "  Lead ID: {$opp['lead_id']}\n";
        echo "  Lead Name (JOIN): {$opp['lead_name']}\n";
        echo "  Lead Phone (JOIN): {$opp['lead_phone']}\n";
        echo "  Lead Email (JOIN): {$opp['lead_email']}\n";
    } else {
        echo "❌ Nenhuma oportunidade encontrada para lead 30\n";
    }
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
