<?php
/**
 * Investigação: Luis Carlos - Duplicação e vinculação de conta
 */

// Carregar .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Conectar ao banco
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'pixel_hub';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== INVESTIGAÇÃO: LUIS CARLOS ===\n\n";

// 1. Buscar leads com nome Luis Carlos ou telefone terminando em 5045
echo "1. LEADS com nome 'Luis Carlos' ou telefone terminando em 5045:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        id,
        name,
        phone,
        email,
        source,
        status,
        converted_tenant_id,
        created_at,
        updated_at
    FROM leads
    WHERE name LIKE '%Luis Carlos%' 
       OR name LIKE '%Luiz Carlos%'
       OR phone LIKE '%5045'
    ORDER BY created_at DESC
");
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($leads)) {
    echo "Nenhum lead encontrado.\n";
} else {
    foreach ($leads as $lead) {
        echo "Lead ID: {$lead['id']}\n";
        echo "  Nome: {$lead['name']}\n";
        echo "  Telefone: {$lead['phone']}\n";
        echo "  Email: {$lead['email']}\n";
        echo "  Status: {$lead['status']}\n";
        echo "  Source: {$lead['source']}\n";
        echo "  Convertido para tenant_id: {$lead['converted_tenant_id']}\n";
        echo "  Criado em: {$lead['created_at']}\n";
        echo "  Atualizado em: {$lead['updated_at']}\n";
        echo "\n";
    }
}

// 2. Buscar tenants (contas) com nome Luis Carlos ou telefone terminando em 5045
echo "\n2. TENANTS (Contas) com nome 'Luis Carlos' ou telefone terminando em 5045:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        id,
        name,
        phone,
        email,
        status,
        created_at,
        updated_at
    FROM tenants
    WHERE (name LIKE '%Luis Carlos%' OR name LIKE '%Luiz Carlos%' OR phone LIKE '%5045')
    ORDER BY created_at DESC
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "Nenhum tenant encontrado.\n";
} else {
    foreach ($tenants as $tenant) {
        echo "Tenant ID: {$tenant['id']}\n";
        echo "  Nome: {$tenant['name']}\n";
        echo "  Telefone: {$tenant['phone']}\n";
        echo "  Email: {$tenant['email']}\n";
        echo "  Status: {$tenant['status']}\n";
        echo "  Criado em: {$tenant['created_at']}\n";
        echo "  Atualizado em: {$tenant['updated_at']}\n";
        echo "\n";
    }
}

// 3. Buscar oportunidades vinculadas a Luis Carlos
echo "\n3. OPORTUNIDADES vinculadas a Luis Carlos:\n";
echo str_repeat('-', 80) . "\n";

$leadIds = array_column($leads, 'id');
$tenantIds = array_column($tenants, 'id');

if (!empty($leadIds) || !empty($tenantIds)) {
    $conditions = [];
    $params = [];
    
    if (!empty($leadIds)) {
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $conditions[] = "lead_id IN ($placeholders)";
        $params = array_merge($params, $leadIds);
    }
    
    if (!empty($tenantIds)) {
        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $conditions[] = "tenant_id IN ($placeholders)";
        $params = array_merge($params, $tenantIds);
    }
    
    $whereClause = implode(' OR ', $conditions);
    
    $stmt = $db->prepare("
        SELECT 
            o.id,
            o.name,
            o.stage,
            o.status,
            o.lead_id,
            o.tenant_id,
            o.estimated_value,
            o.created_at,
            l.name as lead_name,
            l.phone as lead_phone,
            t.name as tenant_name,
            t.phone as tenant_phone
        FROM opportunities o
        LEFT JOIN leads l ON o.lead_id = l.id
        LEFT JOIN tenants t ON o.tenant_id = t.id
        WHERE $whereClause
        ORDER BY o.created_at DESC
    ");
    $stmt->execute($params);
    $opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($opportunities)) {
        echo "Nenhuma oportunidade encontrada.\n";
    } else {
        foreach ($opportunities as $opp) {
            echo "Oportunidade ID: {$opp['id']}\n";
            echo "  Nome: {$opp['name']}\n";
            echo "  Stage: {$opp['stage']}\n";
            echo "  Status: {$opp['status']}\n";
            echo "  Lead ID: {$opp['lead_id']} " . ($opp['lead_name'] ? "({$opp['lead_name']} - {$opp['lead_phone']})" : '') . "\n";
            echo "  Tenant ID (conta vinculada): {$opp['tenant_id']} " . ($opp['tenant_name'] ? "({$opp['tenant_name']} - {$opp['tenant_phone']})" : '') . "\n";
            echo "  Valor estimado: R$ " . number_format($opp['estimated_value'], 2, ',', '.') . "\n";
            echo "  Criado em: {$opp['created_at']}\n";
            echo "\n";
        }
    }
}

// 4. Buscar conversas vinculadas a Luis Carlos
echo "\n4. CONVERSAS vinculadas a Luis Carlos:\n";
echo str_repeat('-', 80) . "\n";

if (!empty($leadIds) || !empty($tenantIds)) {
    $conditions = [];
    $params = [];
    
    if (!empty($leadIds)) {
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $conditions[] = "lead_id IN ($placeholders)";
        $params = array_merge($params, $leadIds);
    }
    
    if (!empty($tenantIds)) {
        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $conditions[] = "tenant_id IN ($placeholders)";
        $params = array_merge($params, $tenantIds);
    }
    
    $whereClause = implode(' OR ', $conditions);
    
    $stmt = $db->prepare("
        SELECT 
            id,
            conversation_key,
            lead_id,
            tenant_id,
            contact_external_id,
            last_message_at,
            created_at
        FROM conversations
        WHERE $whereClause
        ORDER BY last_message_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "Nenhuma conversa encontrada.\n";
    } else {
        foreach ($conversations as $conv) {
            echo "Conversa ID: {$conv['id']}\n";
            echo "  Conversation Key: {$conv['conversation_key']}\n";
            echo "  Lead ID: {$conv['lead_id']}\n";
            echo "  Tenant ID: {$conv['tenant_id']}\n";
            echo "  Contact External ID: {$conv['contact_external_id']}\n";
            echo "  Última mensagem: {$conv['last_message_at']}\n";
            echo "  Criado em: {$conv['created_at']}\n";
            echo "\n";
        }
    }
}

// 5. Verificar se há duplicação de telefone
echo "\n5. ANÁLISE DE DUPLICAÇÃO:\n";
echo str_repeat('-', 80) . "\n";

$allPhones = [];
foreach ($leads as $lead) {
    if (!empty($lead['phone'])) {
        $allPhones[] = ['type' => 'lead', 'id' => $lead['id'], 'name' => $lead['name'], 'phone' => $lead['phone']];
    }
}
foreach ($tenants as $tenant) {
    if (!empty($tenant['phone'])) {
        $allPhones[] = ['type' => 'tenant', 'id' => $tenant['id'], 'name' => $tenant['name'], 'phone' => $tenant['phone']];
    }
}

$phoneGroups = [];
foreach ($allPhones as $item) {
    $normalizedPhone = preg_replace('/[^0-9]/', '', $item['phone']);
    if (!isset($phoneGroups[$normalizedPhone])) {
        $phoneGroups[$normalizedPhone] = [];
    }
    $phoneGroups[$normalizedPhone][] = $item;
}

foreach ($phoneGroups as $phone => $items) {
    if (count($items) > 1) {
        echo "DUPLICAÇÃO DETECTADA - Telefone: $phone\n";
        foreach ($items as $item) {
            echo "  - {$item['type']} ID {$item['id']}: {$item['name']} ({$item['phone']})\n";
        }
        echo "\n";
    }
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
