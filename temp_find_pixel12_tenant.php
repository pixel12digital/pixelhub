<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== BUSCANDO TENANTS RELACIONADOS ===\n\n";

// Busca Pixel12
$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE name LIKE '%pixel%' ORDER BY id");
$stmt->execute();
$pixel = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pixel) > 0) {
    echo "Tenants com 'Pixel':\n";
    foreach ($pixel as $t) {
        echo "  ID: {$t['id']} - {$t['name']} - Tel: " . ($t['phone'] ?: 'NULL') . "\n";
    }
    echo "\n";
}

// Busca Haiti
$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE name LIKE '%haiti%' ORDER BY id");
$stmt->execute();
$haiti = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($haiti) > 0) {
    echo "Tenants com 'Haiti':\n";
    foreach ($haiti as $t) {
        echo "  ID: {$t['id']} - {$t['name']} - Tel: " . ($t['phone'] ?: 'NULL') . "\n";
    }
    echo "\n";
}

// Busca Charles (dono da Pixel12)
$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE name LIKE '%charles%' OR name LIKE '%dietrich%' ORDER BY id");
$stmt->execute();
$charles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($charles) > 0) {
    echo "Tenants com 'Charles' ou 'Dietrich':\n";
    foreach ($charles as $t) {
        echo "  ID: {$t['id']} - {$t['name']} - Tel: " . ($t['phone'] ?: 'NULL') . "\n";
    }
    echo "\n";
}

echo "=== SUGESTÃO ===\n\n";
echo "Se o Luiz Carlos é um lead da PRÓPRIA Pixel12 Digital,\n";
echo "você precisa criar um tenant para a Pixel12 Digital primeiro,\n";
echo "ou usar um tenant existente que represente a empresa.\n\n";

echo "Qual tenant deve atender o Luiz Carlos?\n";
