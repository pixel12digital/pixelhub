<?php

// Carrega bootstrap do PixelHub
require_once __DIR__ . '/public/index.php';

// Carrega apenas o necessário para DB
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Inicia sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'src/Core/DB.php';
$db = PixelHub\Core\DB::getConnection();

// Buscar conversa da Fátima pelo telefone final 1354
$stmt = $db->prepare('SELECT * FROM conversations WHERE contact_external_id LIKE "%1354" ORDER BY id DESC LIMIT 1');
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "Conversa encontrada: ID={$conv['id']}, lead_id={$conv['lead_id']}\n";
    
    if ($conv['lead_id']) {
        // Buscar opportunities para este lead
        $stmt = $db->prepare('SELECT * FROM opportunities WHERE lead_id = ? ORDER BY created_at DESC');
        $stmt->execute([$conv['lead_id']]);
        $opps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Opportunities para lead_id={$conv['lead_id']}: " . count($opps) . "\n";
        foreach ($opps as $opp) {
            echo "- ID={$opp['id']}, status={$opp['status']}, pipeline_id={$opp['pipeline_id']}, stage={$opp['stage']}\n";
        }
        
        // Buscar dados do lead
        $stmt = $db->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([$conv['lead_id']]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Lead: ID={$lead['id']}, name={$lead['name']}, status={$lead['status']}\n";
    } else {
        echo "Conversa não tem lead_id vinculado\n";
    }
} else {
    echo "Conversa não encontrada para telefone final 1354\n";
}
