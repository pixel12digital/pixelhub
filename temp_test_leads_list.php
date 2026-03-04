<?php
require 'vendor/autoload.php';

$leads = \PixelHub\Services\ContactService::searchLeads(null, 200);
echo 'Total de leads (sem busca): ' . count($leads) . PHP_EOL;
foreach ($leads as $l) {
    echo '  - ID=' . $l['id'] . ', Nome=' . $l['name'] . ', Status=' . $l['status'] . PHP_EOL;
}

echo "\nBusca por 'Lead #12':\n";
$leads = \PixelHub\Services\ContactService::searchLeads('Lead #12', 200);
echo 'Total: ' . count($leads) . PHP_EOL;
foreach ($leads as $l) {
    echo '  - ID=' . $l['id'] . ', Nome=' . $l['name'] . PHP_EOL;
}
