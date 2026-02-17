<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== Estrutura da tabela webhook_raw_logs ===\n";
$stmt = $pdo->query('DESCRIBE webhook_raw_logs');
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . ' - ' . $row[1] . "\n";
}
