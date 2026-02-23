<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $db = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== VERIFICAÇÃO: CONVERSA DO ANDREI LIMA ===\n\n";

// Liberar eventos travados em processing
$releaseStmt = $db->query("
    UPDATE communication_events
    SET status = 'queued', updated_at = NOW()
    WHERE status = 'processing'
        AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$released = $releaseStmt->rowCount();

if ($released > 0) {
    echo "✅ Liberados {$released} eventos travados em 'processing'\n\n";
}

// Buscar conversas do Andrei Lima
$searchPatterns = [
    '9779-7101',
    '97797101',
    '47977971',
    '5547977971'
];

echo "--- BUSCANDO CONVERSAS ---\n";

$found = false;
foreach ($searchPatterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.tenant_id,
            c.conversation_key,
            c.contact_external_id,
            c.contact_name,
            c.lead_id,
            c.status,
            c.last_message_at,
            c.created_at,
            t.name as tenant_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.contact_external_id LIKE :pattern 
            OR c.conversation_key LIKE :pattern
        ORDER BY c.last_message_at DESC
        LIMIT 5
    ");
    
    $stmt->execute(['pattern' => '%' . $pattern . '%']);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($conversations) > 0) {
        $found = true;
        echo "\n🔍 Padrão '$pattern' - " . count($conversations) . " conversa(s) encontrada(s):\n";
        
        foreach ($conversations as $conv) {
            echo sprintf(
                "  ID: %d | Tenant: %s (%d) | Contact: %s | Nome: %s\n",
                $conv['id'],
                $conv['tenant_name'] ?? 'Não vinculado',
                $conv['tenant_id'] ?? 0,
                $conv['contact_external_id'],
                $conv['contact_name'] ?? 'N/A'
            );
            echo sprintf(
                "  Lead: %s | Status: %s | Última msg: %s | Criado: %s\n\n",
                $conv['lead_id'] ?? 'NULL',
                $conv['status'],
                $conv['last_message_at'],
                $conv['created_at']
            );
        }
    }
}

if (!$found) {
    echo "❌ Nenhuma conversa encontrada para Andrei Lima.\n";
    echo "\nVerificando eventos processados...\n\n";
    
    // Buscar eventos processados do Andrei
    foreach ($searchPatterns as $pattern) {
        $stmt = $db->prepare("
            SELECT 
                id,
                event_id,
                event_type,
                tenant_id,
                status,
                created_at,
                processed_at
            FROM communication_events
            WHERE (payload LIKE :pattern OR metadata LIKE :pattern)
                AND status = 'processed'
            ORDER BY created_at DESC
            LIMIT 5
        ");
        
        $stmt->execute(['pattern' => '%' . $pattern . '%']);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($events) > 0) {
            echo "✅ Eventos processados encontrados (padrão: $pattern):\n";
            foreach ($events as $evt) {
                echo sprintf(
                    "  ID: %d | Type: %s | Tenant: %d | Status: %s | Processado: %s\n",
                    $evt['id'],
                    $evt['event_type'],
                    $evt['tenant_id'] ?? 0,
                    $evt['status'],
                    $evt['processed_at'] ?? 'NULL'
                );
            }
            echo "\n";
        }
    }
}

// Estatísticas gerais
echo "\n--- ESTATÍSTICAS GERAIS ---\n";

$stats = $db->query("
    SELECT 
        COUNT(*) as total_conversations,
        COUNT(CASE WHEN tenant_id IS NULL OR tenant_id = 0 THEN 1 END) as unlinked,
        COUNT(CASE WHEN tenant_id IS NOT NULL AND tenant_id > 0 THEN 1 END) as linked
    FROM conversations
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch(PDO::FETCH_ASSOC);

echo "Conversas criadas (últimas 24h): {$stats['total_conversations']}\n";
echo "  Vinculadas: {$stats['linked']}\n";
echo "  Não vinculadas: {$stats['unlinked']}\n";
