<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== BUSCANDO TENANT DA PIXEL12 DIGITAL ===\n\n";

// Busca tenants com contact_type = 'internal' ou similar
echo "1. Tenants com contact_type = 'internal':\n";
$stmt = $db->prepare("SELECT id, name, phone, contact_type FROM tenants WHERE contact_type = 'internal' ORDER BY id");
$stmt->execute();
$internal = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($internal) > 0) {
    foreach ($internal as $t) {
        echo "  ID: {$t['id']} - {$t['name']} - {$t['contact_type']}\n";
    }
} else {
    echo "  Nenhum tenant com contact_type='internal'\n";
}

echo "\n2. Tenants com telefone 47973095525 (telefone da Pixel12):\n";
$stmt = $db->prepare("SELECT id, name, phone, contact_type FROM tenants WHERE phone LIKE '%47973095525%' OR phone LIKE '%4797309525%'");
$stmt->execute();
$byPhone = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($byPhone) > 0) {
    foreach ($byPhone as $t) {
        echo "  ID: {$t['id']} - {$t['name']} - Tel: {$t['phone']}\n";
    }
} else {
    echo "  Nenhum tenant com esse telefone\n";
}

echo "\n3. Tenants com 'Charles' ou 'Dietrich' (dono da Pixel12):\n";
$stmt = $db->prepare("SELECT id, name, phone, cpf_cnpj FROM tenants WHERE name LIKE '%charles%' OR name LIKE '%dietrich%' ORDER BY id");
$stmt->execute();
$charles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($charles) > 0) {
    foreach ($charles as $t) {
        echo "  ID: {$t['id']} - {$t['name']}\n";
    }
} else {
    echo "  Nenhum tenant encontrado\n";
}

echo "\n4. Verificando tenant ID 25 (Charles Dietrich mencionado em memórias):\n";
$stmt = $db->prepare("SELECT * FROM tenants WHERE id = 25");
$stmt->execute();
$t25 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($t25) {
    echo "  ID: {$t25['id']}\n";
    echo "  Nome: {$t25['name']}\n";
    echo "  Telefone: " . ($t25['phone'] ?: 'NULL') . "\n";
    echo "  CNPJ/CPF: " . ($t25['cpf_cnpj'] ?: 'NULL') . "\n";
    echo "  Contact Type: " . ($t25['contact_type'] ?: 'NULL') . "\n";
} else {
    echo "  Tenant ID 25 não encontrado\n";
}

echo "\n5. Listando primeiros 30 tenants para você escolher:\n";
$stmt = $db->query("SELECT id, name, phone FROM tenants ORDER BY id LIMIT 30");
$first30 = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($first30 as $t) {
    echo "  ID: {$t['id']} - {$t['name']} - Tel: " . ($t['phone'] ?: 'NULL') . "\n";
}

echo "\n=== AÇÃO NECESSÁRIA ===\n";
echo "Por favor, me informe qual é o ID do tenant que representa a Pixel12 Digital.\n";
echo "Ou se não existe, posso criar um novo tenant para a Pixel12 Digital.\n";
