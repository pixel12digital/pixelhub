<?php

// Simples consulta direta ao MySQL sem dependências do PixelHub
$host = 'mysql03.univale.com.br';
$dbname = 'pixel12digital';
$username = 'pixel12digital';
$password = 'pixel@123#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado ao banco de dados\n";
    
    // Buscar conversa da Fátima pelo telefone final 1354
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE contact_external_id LIKE "%1354" ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conv) {
        echo "Conversa encontrada: ID={$conv['id']}, lead_id={$conv['lead_id']}\n";
        
        if ($conv['lead_id']) {
            // Buscar opportunities para este lead
            $stmt = $pdo->prepare('SELECT * FROM opportunities WHERE lead_id = ? ORDER BY created_at DESC');
            $stmt->execute([$conv['lead_id']]);
            $opps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Opportunities para lead_id={$conv['lead_id']}: " . count($opps) . "\n";
            foreach ($opps as $opp) {
                echo "- ID={$opp['id']}, status={$opp['status']}, pipeline_id={$opp['pipeline_id']}, stage={$opp['stage']}\n";
            }
            
            // Buscar dados do lead
            $stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
            $stmt->execute([$conv['lead_id']]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Lead: ID={$lead['id']}, name={$lead['name']}, status={$lead['status']}\n";
        } else {
            echo "Conversa não tem lead_id vinculado\n";
        }
    } else {
        echo "Conversa não encontrada para telefone final 1354\n";
    }
    
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
}
