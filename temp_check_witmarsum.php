<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

$stmt = $db->prepare("SELECT id, name, cnaes, cnae_code, cnae_description FROM prospecting_recipes WHERE name LIKE ? ORDER BY id DESC LIMIT 1");
$stmt->execute(['%Witmarsum%']);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== RECEITA WITMARSUM ===\n";
echo json_encode($recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

if (!empty($recipe['cnaes'])) {
    $cnaes = json_decode($recipe['cnaes'], true);
    echo "=== CNAEs CADASTRADOS ===\n";
    foreach ($cnaes as $cnae) {
        echo "- {$cnae['code']}: {$cnae['desc']}\n";
    }
}
