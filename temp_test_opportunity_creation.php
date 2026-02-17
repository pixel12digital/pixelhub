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

echo "=== Testando criação de opportunity para Lead da Fátima ===\n";

// Buscar conversa da Fátima
$stmt = $db->prepare('SELECT * FROM conversations WHERE contact_name LIKE "%Fátima%" ORDER BY id DESC LIMIT 1');
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "Conversa encontrada: ID={$conv['id']}, lead_id={$conv['lead_id']}\n";
    
    if ($conv['lead_id']) {
        $leadId = $conv['lead_id'];
        $conversationId = $conv['id'];
        
        // Verificar se já existe opportunity
        $stmt = $db->prepare('SELECT * FROM opportunities WHERE lead_id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$leadId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo "Já existe opportunity: ID={$existing['id']}, stage={$existing['stage']}\n";
        } else {
            echo "Nenhuma opportunity encontrada. Criando...\n";
            
            // Buscar dados do lead
            $stmt = $db->prepare('SELECT name, phone, email FROM leads WHERE id = ?');
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lead) {
                $opportunityName = $lead['name'] ?: 'Lead #' . $leadId;
                
                // Criar opportunity
                $stmt = $db->prepare("
                    INSERT INTO opportunities 
                    (name, stage, status, lead_id, conversation_id, created_by, created_at, updated_at)
                    VALUES (?, 'new', 'active', ?, ?, NULL, NOW(), NOW())
                ");
                
                $stmt->execute([$opportunityName, $leadId, $conversationId]);
                
                $opportunityId = (int) $db->lastInsertId();
                
                echo "Opportunity criada: ID={$opportunityId}, name={$opportunityName}\n";
                
                // Verificar criação
                $stmt = $db->prepare('SELECT * FROM opportunities WHERE id = ?');
                $stmt->execute([$opportunityId]);
                $created = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($created) {
                    echo "✅ Opportunity verificada: status={$created['status']}, stage={$created['stage']}\n";
                
                // Testar filtros da tela Oportunidades
                echo "\n=== Testando filtros da tela Oportunidades ===\n";
                
                // Simula filtro padrão (status = active)
                $stmt = $db->prepare("
                    SELECT o.*, l.name as lead_name 
                    FROM opportunities o
                    LEFT JOIN leads l ON o.lead_id = l.id
                    WHERE o.status = 'active'
                    ORDER BY o.created_at DESC
                ");
                $stmt->execute();
                $activeOpps = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "Opportunities ativas (filtro padrão): " . count($activeOpps) . "\n";
                foreach ($activeOpps as $opp) {
                    echo "- ID={$opp['id']}, name={$opp['name']}, lead={$opp['lead_name']}, stage={$opp['stage']}\n";
                }
                
                // Verificar se a Fátima aparece
                $found = false;
                foreach ($activeOpps as $opp) {
                    if ($opp['lead_name'] === 'Fátima') {
                        $found = true;
                        echo "✅ Fátima encontrada no pipeline!\n";
                        break;
                    }
                }
                
                if (!$found) {
                    echo "❌ Fátima NÃO encontrada no pipeline\n";
                }
                } else {
                    echo "❌ Erro ao verificar opportunity\n";
                }
            } else {
                echo "❌ Lead não encontrado\n";
            }
        }
    } else {
        echo "❌ Conversa não tem lead_id\n";
    }
} else {
    echo "❌ Conversa da Fátima não encontrada\n";
}
