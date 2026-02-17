<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');
$stmt = $pdo->query('DESCRIBE communication_events');
echo "Colunas da tabela communication_events:\n";
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . ' - ' . $row[1] . "\n";
}
