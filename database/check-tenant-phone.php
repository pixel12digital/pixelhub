<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

// Número encontrado no payload: +55 94 8119-7615
$phone1 = '554981197615';
$phone2 = '9481197615';
$phone3 = '9481197615';
$phone4 = '8197615';

echo "Buscando tenant com telefone relacionado a 554981197615...\n\n";

$stmt = $db->prepare("
    SELECT id, name, phone, email, cpf_cnpj 
    FROM tenants 
    WHERE phone LIKE ? 
       OR phone LIKE ? 
       OR phone LIKE ?
       OR phone LIKE ?
    LIMIT 10
");
$stmt->execute(["%{$phone1}%", "%{$phone2}%", "%{$phone3}%", "%{$phone4}%"]);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "❌ Nenhum tenant encontrado.\n";
} else {
    echo "✅ Encontrados " . count($tenants) . " tenant(s):\n\n";
    foreach ($tenants as $t) {
        echo "ID: {$t['id']}\n";
        echo "Nome: {$t['name']}\n";
        echo "Phone: {$t['phone']}\n";
        echo "Email: " . ($t['email'] ?? 'NULL') . "\n";
        echo "CPF/CNPJ: " . ($t['cpf_cnpj'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

// Verificar tenant ID 2 que apareceu no evento
echo "\nVerificando Tenant ID 2 (que apareceu no evento):\n";
$stmt = $db->prepare("SELECT id, name, phone, email FROM tenants WHERE id = 2");
$stmt->execute();
$tenant2 = $stmt->fetch(PDO::FETCH_ASSOC);
if ($tenant2) {
    echo "ID: {$tenant2['id']}\n";
    echo "Nome: {$tenant2['name']}\n";
    echo "Phone: " . ($tenant2['phone'] ?? 'NULL') . "\n";
    echo "Email: " . ($tenant2['email'] ?? 'NULL') . "\n";
} else {
    echo "Tenant ID 2 não encontrado.\n";
}

