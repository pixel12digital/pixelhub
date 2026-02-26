<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== COLUNAS DA TABELA billing_invoices ===\n\n";
$cols = $db->query("SHOW COLUMNS FROM billing_invoices")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "{$col['Field']} ({$col['Type']})\n";
}

echo "\n=== FATURAS DO TENANT 25 ===\n\n";
$invoices = $db->query("
    SELECT *
    FROM billing_invoices
    WHERE tenant_id = 25
      AND status IN ('pending', 'overdue')
      AND (is_deleted IS NULL OR is_deleted = 0)
    ORDER BY due_date
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($invoices)) {
    echo "Nenhuma fatura pendente/vencida.\n";
} else {
    foreach ($invoices as $inv) {
        echo "ID: {$inv['id']}\n";
        echo "Vencimento: {$inv['due_date']}\n";
        echo "Status: {$inv['status']}\n";
        echo "Valor: R$ {$inv['value']}\n";
        echo "Dias vencido: " . (strtotime('now') - strtotime($inv['due_date'])) / 86400 . "\n";
        echo str_repeat('-', 50) . "\n";
    }
}
