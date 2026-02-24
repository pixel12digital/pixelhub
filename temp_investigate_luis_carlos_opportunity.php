<?php
/**
 * Investigação: Incoerência Luis Carlos - Filtro vs Detalhes da Oportunidade
 */

// Carregar .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
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

echo "=== INVESTIGAÇÃO: INCOERÊNCIA LUIS CARLOS ===\n\n";

// 1. Buscar a oportunidade "Loja Virtual" do Luis Carlos
echo "1. OPORTUNIDADE 'Loja Virtual' do Luis Carlos:\n";
echo str_repeat('-', 80) . "\n";

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
        t.id as tenant_id_check,
        t.name as tenant_name,
        t.status as tenant_status
    FROM opportunities o
    LEFT JOIN leads l ON o.lead_id = l.id
    LEFT JOIN tenants t ON o.tenant_id = t.id
    WHERE o.name = 'Loja Virtual' AND l.name LIKE '%Luis Carlos%'
");
$stmt->execute();
$opp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$opp) {
    echo "Oportunidade não encontrada.\n";
} else {
    echo "Oportunidade ID: {$opp['id']}\n";
    echo "  Nome: {$opp['name']}\n";
    echo "  Stage: {$opp['stage']}\n";
    echo "  Status: {$opp['status']}\n";
    echo "  Lead ID: {$opp['lead_id']}\n";
    echo "  Lead Nome: {$opp['lead_name']}\n";
    echo "  Lead Telefone: {$opp['lead_phone']}\n";
    echo "  Tenant ID (campo tenant_id): " . ($opp['tenant_id'] ?? 'NULL') . "\n";
    echo "  Tenant ID Check: " . ($opp['tenant_id_check'] ?? 'NULL') . "\n";
    echo "  Tenant Nome: " . ($opp['tenant_name'] ?? 'NULL') . "\n";
    echo "  Tenant Status: " . ($opp['tenant_status'] ?? 'NULL') . "\n";
    echo "  Valor estimado: R$ " . number_format($opp['estimated_value'], 2, ',', '.') . "\n";
    echo "  Criado em: {$opp['created_at']}\n";
    echo "\n";
    
    // 2. Verificar qual filtro mostraria esta oportunidade
    echo "\n2. ANÁLISE DO FILTRO:\n";
    echo str_repeat('-', 80) . "\n";
    
    if ($opp['tenant_id'] === null || $opp['tenant_id'] === '') {
        echo "✓ Esta oportunidade TEM tenant_id = NULL\n";
        echo "✓ Portanto, aparece no filtro 'Pixel12 Digital (agência)' (tenant_id='')\n";
        echo "✓ Isso está CORRETO segundo a lógica do código:\n";
        echo "    OpportunityService.php linha 169-170:\n";
        echo "    if (\$filters['tenant_id'] === null || \$filters['tenant_id'] === '') {\n";
        echo "        \$where[] = 'o.tenant_id IS NULL';\n";
        echo "    }\n";
    } else {
        echo "✗ Esta oportunidade TEM tenant_id = {$opp['tenant_id']}\n";
        echo "✗ Portanto, NÃO deveria aparecer no filtro 'Pixel12 Digital (agência)'\n";
        echo "✗ Deveria aparecer apenas no filtro da conta ID {$opp['tenant_id']}\n";
    }
    
    // 3. Verificar o card exibido na listagem
    echo "\n\n3. CARD NA LISTAGEM (como aparece na interface):\n";
    echo str_repeat('-', 80) . "\n";
    echo "Segundo a query do OpportunityService::list():\n\n";
    
    // Simular a query exata usada na listagem
    $stmt = $db->prepare("
        SELECT 
            o.id,
            o.name,
            o.stage,
            o.status,
            o.estimated_value,
            o.lead_id,
            o.tenant_id,
            o.created_at,
            COALESCE(t.company, t.name, l.name, l.phone, l.email) as contact_name,
            COALESCE(t.phone, l.phone) as contact_phone,
            COALESCE(t.email, l.email) as contact_email,
            CASE
                WHEN o.lead_id IS NOT NULL THEN 'lead'
                WHEN o.tenant_id IS NOT NULL THEN
                    CASE WHEN t.contact_type = 'client' THEN 'cliente' ELSE 'lead' END
                ELSE 'lead'
            END as contact_type
        FROM opportunities o
        LEFT JOIN leads l ON o.lead_id = l.id
        LEFT JOIN tenants t ON o.tenant_id = t.id
        WHERE o.id = ?
    ");
    $stmt->execute([$opp['id']]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Card exibido:\n";
    echo "  Contact Name: {$card['contact_name']}\n";
    echo "  Contact Phone: {$card['contact_phone']}\n";
    echo "  Contact Type: {$card['contact_type']}\n";
    echo "  Lead ID: {$card['lead_id']}\n";
    echo "  Tenant ID: " . ($card['tenant_id'] ?? 'NULL') . "\n";
    
    // 4. Verificar a view de detalhes
    echo "\n\n4. VIEW DE DETALHES (view.php):\n";
    echo str_repeat('-', 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT o.*,
               COALESCE(t.name, l.name, l.phone, l.email) as contact_name,
               CASE 
                   WHEN o.tenant_id IS NOT NULL THEN 
                       CASE WHEN t.contact_type = 'client' THEN 'cliente' ELSE 'lead' END
                   ELSE 'lead' 
               END as contact_type,
               l.name as lead_name,
               l.phone as lead_phone,
               l.email as lead_email,
               t.name as tenant_name,
               t.phone as tenant_phone,
               t.email as tenant_email
        FROM opportunities o
        LEFT JOIN leads l ON o.lead_id = l.id
        LEFT JOIN tenants t ON o.tenant_id = t.id
        WHERE o.id = ?
    ");
    $stmt->execute([$opp['id']]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Detalhes exibidos:\n";
    echo "  Contact Type: {$detail['contact_type']}\n";
    echo "  Lead ID: {$detail['lead_id']}\n";
    echo "  Lead Name: {$detail['lead_name']}\n";
    echo "  Tenant ID: " . ($detail['tenant_id'] ?? 'NULL') . "\n";
    echo "  Tenant Name: " . ($detail['tenant_name'] ?? 'NULL') . "\n";
    
    echo "\n\nSeção 'Conta Vinculada' (view.php linha 440-456):\n";
    if (!empty($detail['tenant_id'])) {
        echo "  Mostra: '{$detail['tenant_name']}' com botão 'Alterar'\n";
    } else {
        echo "  Mostra: 'Nenhuma conta vinculada' com botão '+ Vincular conta'\n";
    }
}

// 5. Verificar se há outras oportunidades com o mesmo problema
echo "\n\n5. OUTRAS OPORTUNIDADES COM tenant_id = NULL:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT 
        o.id,
        o.name,
        o.stage,
        o.lead_id,
        o.tenant_id,
        l.name as lead_name
    FROM opportunities o
    LEFT JOIN leads l ON o.lead_id = l.id
    WHERE o.tenant_id IS NULL
    ORDER BY o.created_at DESC
    LIMIT 10
");
$others = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de oportunidades com tenant_id = NULL: " . count($others) . "\n\n";
foreach ($others as $other) {
    echo "  - Oportunidade #{$other['id']}: {$other['name']} (Lead: {$other['lead_name']})\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo str_repeat('-', 80) . "\n";

if ($opp && ($opp['tenant_id'] === null || $opp['tenant_id'] === '')) {
    echo "✓ NÃO HÁ INCOERÊNCIA.\n\n";
    echo "A oportunidade 'Loja Virtual' do Luis Carlos:\n";
    echo "  1. TEM tenant_id = NULL (sem conta vinculada)\n";
    echo "  2. APARECE no filtro 'Pixel12 Digital (agência)' (correto)\n";
    echo "  3. MOSTRA 'Nenhuma conta vinculada' nos detalhes (correto)\n\n";
    echo "O comportamento está consistente.\n";
    echo "O filtro 'Pixel12 Digital (agência)' mostra oportunidades SEM conta vinculada.\n";
} else {
    echo "✗ INCOERÊNCIA DETECTADA!\n\n";
    echo "A oportunidade tem tenant_id = {$opp['tenant_id']}\n";
    echo "Mas está aparecendo no filtro errado.\n";
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
