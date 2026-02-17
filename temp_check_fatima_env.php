<?php

// Define constantes de ambiente manualmente para teste
$_ENV['DB_HOST'] = 'r225us.hmservers.net';
$_ENV['DB_NAME'] = 'pixel12digital_pixelhub';
$_ENV['DB_USER'] = 'pixel12digital_pixelhub';
$_ENV['DB_PASS'] = 'Los@ngo#081081';
$_ENV['DB_CHARSET'] = 'utf8mb4';

// Carrega classes do PixelHub
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Core/DB.php';

$db = PixelHub\Core\DB::getConnection();

// Buscar conversa da Fátima por nome ou telefone
$stmt = $db->prepare('SELECT * FROM conversations WHERE contact_name LIKE "%Fátima%" OR contact_external_id LIKE "%1354" ORDER BY id DESC LIMIT 5');
$stmt->execute();
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Busca por Fátima: " . count($convs) . "\n";
foreach ($convs as $conv) {
    echo "- ID={$conv['id']}, lead_id={$conv['lead_id']}, name={$conv['contact_name']}, contact={$conv['contact_external_id']}\n";
    
    if ($conv['lead_id']) {
        // Buscar opportunities para este lead
        $stmt = $db->prepare('SELECT * FROM opportunities WHERE lead_id = ? ORDER BY created_at DESC');
        $stmt->execute([$conv['lead_id']]);
        $opps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  Opportunities: " . count($opps) . "\n";
        foreach ($opps as $opp) {
            echo "    - ID={$opp['id']}, status={$opp['status']}, stage={$opp['stage']}\n";
        }
        
        // Buscar dados do lead
        $stmt = $db->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([$conv['lead_id']]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Lead: ID={$lead['id']}, name={$lead['name']}, status={$lead['status']}\n";
    }
}
