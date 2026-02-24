<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== INVESTIGANDO ESTRUTURA DE CONTAS NO SISTEMA ===\n\n";

// 1. Verifica estrutura da tabela opportunities
echo "1. ESTRUTURA DA TABELA OPPORTUNITIES:\n";
$stmt = $db->query("SHOW COLUMNS FROM opportunities");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})";
    if ($col['Key']) echo " [{$col['Key']}]";
    echo "\n";
}

echo "\n2. DADOS DA OPORTUNIDADE ID 8:\n";
$stmt = $db->prepare("SELECT * FROM opportunities WHERE id = 8");
$stmt->execute();
$opp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($opp) {
    foreach ($opp as $key => $value) {
        if ($value !== null) {
            echo "  {$key}: {$value}\n";
        }
    }
}

echo "\n3. VERIFICANDO SE EXISTE TABELA 'ACCOUNTS':\n";
$stmt = $db->query("SHOW TABLES LIKE 'accounts'");
$accountsTable = $stmt->fetch();

if ($accountsTable) {
    echo "✓ Tabela 'accounts' existe\n\n";
    
    echo "Estrutura da tabela accounts:\n";
    $stmt = $db->query("SHOW COLUMNS FROM accounts");
    $accountColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($accountColumns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\nBuscando conta 'Pixel12':\n";
    $stmt = $db->prepare("SELECT * FROM accounts WHERE name LIKE '%pixel%' LIMIT 5");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($accounts) > 0) {
        foreach ($accounts as $acc) {
            echo "  ID: {$acc['id']} - {$acc['name']}\n";
        }
    } else {
        echo "  Nenhuma conta encontrada com 'pixel'\n";
    }
} else {
    echo "✗ Tabela 'accounts' NÃO existe\n";
}

echo "\n4. VERIFICANDO CAMPO 'account_id' NA OPORTUNIDADE:\n";
$hasAccountId = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'account_id') {
        $hasAccountId = true;
        echo "✓ Campo 'account_id' existe na tabela opportunities\n";
        echo "  Tipo: {$col['Type']}\n";
        
        if ($opp && isset($opp['account_id'])) {
            echo "  Valor na oportunidade 8: " . ($opp['account_id'] ?: 'NULL') . "\n";
        }
        break;
    }
}

if (!$hasAccountId) {
    echo "✗ Campo 'account_id' NÃO existe na tabela opportunities\n";
}

echo "\n5. VERIFICANDO RELAÇÃO TENANT vs ACCOUNT:\n";
echo "Na imagem, vejo 'CONTA VINCULADA' e link '+ Vincular conta'\n";
echo "Isso sugere que existe um conceito de 'conta' separado de 'tenant'\n\n";

// Verifica se há alguma tabela relacionada a contas
echo "Buscando tabelas com 'account' no nome:\n";
$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (stripos($table, 'account') !== false) {
        echo "  - {$table}\n";
    }
}
