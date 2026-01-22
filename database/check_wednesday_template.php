<?php
require_once __DIR__ . '/check_agenda_tables.php';

$db = \PixelHub\Core\DB::getConnection();
$stmt = $db->query("
    SELECT t.*, bt.codigo, bt.nome 
    FROM agenda_block_templates t 
    INNER JOIN agenda_block_types bt ON t.tipo_id = bt.id 
    WHERE t.dia_semana = 3 
    ORDER BY t.hora_inicio
");
$templates = $stmt->fetchAll();

echo "\n=== Templates de Quarta-feira ===\n";
foreach ($templates as $t) {
    echo "{$t['hora_inicio']} - {$t['hora_fim']} -> {$t['codigo']} ({$t['nome']})\n";
}











