<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();
$stmt = $db->query("DESCRIBE communication_events");
$rows = $stmt->fetchAll();

echo "Colunas da tabela communication_events:\n";
foreach ($rows as $row) {
    $marker = ($row['Field'] === 'conversation_id') ? ' <-- EXISTE!' : '';
    echo "  {$row['Field']}: {$row['Type']} ({$row['Null']}){$marker}\n";
}
