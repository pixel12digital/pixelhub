<?php
// Verificar canais e mapeamento de tenants
$envFile = __DIR__ . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$host = $envVars['DB_HOST'] ?? 'localhost';
$dbname = $envVars['DB_NAME'] ?? '';
$username = $envVars['DB_USER'] ?? '';
$password = $envVars['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== VERIFICAÇÃO DE CANAIS E MAPEAMENTO ===\n\n";

// 1. Listar todos os canais
echo "--- CANAIS CONFIGURADOS (tenant_message_channels) ---\n";
$stmt = $db->query("
    SELECT 
        tmc.id,
        tmc.tenant_id,
        t.name as tenant_name,
        tmc.provider,
        tmc.channel_id,
        tmc.is_enabled,
        tmc.webhook_configured,
        tmc.metadata
    FROM tenant_message_channels tmc
    LEFT JOIN tenants t ON t.id = tmc.tenant_id
    ORDER BY tmc.tenant_id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($channels) . " canais\n\n";
foreach ($channels as $channel) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$channel['id']} | Tenant: {$channel['tenant_id']} ({$channel['tenant_name']})\n";
    echo "Provider: {$channel['provider']} | Channel ID: {$channel['channel_id']}\n";
    echo "Enabled: " . ($channel['is_enabled'] ? 'SIM' : 'NÃO') . " | Webhook: " . ($channel['webhook_configured'] ? 'SIM' : 'NÃO') . "\n";
    
    if ($channel['metadata']) {
        $metadata = json_decode($channel['metadata'], true);
        echo "Metadata: " . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}

// 2. Verificar se existe mapeamento de números para tenants
echo "\n--- VERIFICAR TABELAS DE MAPEAMENTO ---\n";
$tables = ['whatsapp_business_ids', 'wa_pnlid_cache'];

foreach ($tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela '$table' existe\n";
        
        // Mostrar estrutura
        $stmt = $db->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Colunas: ";
        echo implode(', ', array_column($columns, 'Field')) . "\n";
        
        // Contar registros
        $stmt = $db->query("SELECT COUNT(*) as total FROM $table");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Registros: {$count['total']}\n\n";
    } else {
        echo "✗ Tabela '$table' NÃO existe\n\n";
    }
}

// 3. Verificar se há alguma tabela de mapeamento de contatos
echo "\n--- BUSCAR TABELAS DE CONTATOS/MAPEAMENTO ---\n";
$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$contact_tables = array_filter($tables, function($table) {
    return stripos($table, 'contact') !== false || 
           stripos($table, 'phone') !== false || 
           stripos($table, 'mapping') !== false;
});

if (count($contact_tables) > 0) {
    echo "Tabelas encontradas:\n";
    foreach ($contact_tables as $table) {
        echo "  - $table\n";
    }
} else {
    echo "Nenhuma tabela de contatos/mapeamento encontrada.\n";
}

// 4. Verificar como o sistema resolve tenant_id
echo "\n--- ANÁLISE: COMO O SISTEMA RESOLVE TENANT_ID ---\n";
echo "Baseado nos dados coletados:\n\n";

echo "DOUGLAS (3765):\n";
echo "  - Número: 47953460858953@lid\n";
echo "  - Canal: pixel12digital\n";
echo "  - Tenant resolvido: NULL ❌\n";
echo "  - Status: processing (travado)\n\n";

echo "JOÃO MARQUES (6584):\n";
echo "  - Número: 554196206584@c.us\n";
echo "  - Canal: pixel12digital\n";
echo "  - Tenant resolvido: 29 ✓\n";
echo "  - Conversa criada: ID 455 ✓\n\n";

echo "HIPÓTESE:\n";
echo "O sistema provavelmente resolve tenant_id através de:\n";
echo "1. Mapeamento direto: tenant_message_channels.channel_id = 'pixel12digital'\n";
echo "2. Problema: Pode haver múltiplos tenants usando o mesmo channel_id\n";
echo "3. Solução esperada: Mapear por número de telefone do destinatário (TO)\n\n";

// 5. Verificar eventos processados vs não processados
echo "\n--- ESTATÍSTICAS DE PROCESSAMENTO (últimas 48h) ---\n";
$stmt = $db->query("
    SELECT 
        status,
        COUNT(*) as total,
        COUNT(DISTINCT tenant_id) as tenants_distintos
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    GROUP BY status
    ORDER BY total DESC
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($stats as $stat) {
    echo sprintf(
        "Status: %-12s | Total: %5d | Tenants: %3s\n",
        $stat['status'],
        $stat['total'],
        $stat['tenants_distintos'] ?? 'N/A'
    );
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
