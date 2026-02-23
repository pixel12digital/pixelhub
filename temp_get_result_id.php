<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

// Busca um resultado da receita Witmarsum (ID 7)
$stmt = $db->prepare("SELECT id, name, city, state FROM prospecting_results WHERE recipe_id = 7 LIMIT 1");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "ID encontrado: {$result['id']}\n";
    echo "Nome: {$result['name']}\n";
    echo "Cidade: {$result['city']}\n";
    echo "Estado: {$result['state']}\n";
} else {
    echo "Nenhum resultado encontrado para a receita ID 7\n";
}
