<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Services\PhoneNormalizer;

echo "=== TESTE DE NORMALIZAÇÃO DE TELEFONE ===\n\n";

// Testa diferentes formatos de telefone
$testPhones = [
    '(47) 9616-4699',
    '47 9616-4699',
    '479616-4699',
    '47961646990',
    '+5547961646990',
    '5547961646990',
    '11999999999',
    '+55 11 99999-9999',
];

foreach ($testPhones as $phone) {
    try {
        $normalized = PhoneNormalizer::toE164OrNull($phone, 'BR', false);
        echo "Original: {$phone}\n";
        echo "Normalizado: " . ($normalized ?: 'NULL') . "\n";
        if ($normalized) {
            echo "Com +: +{$normalized}\n";
            echo "Válido para Meta API: " . (preg_match('/^\d{12,13}$/', $normalized) ? 'SIM' : 'NÃO') . "\n";
        }
        echo "---\n\n";
    } catch (Exception $e) {
        echo "Original: {$phone}\n";
        echo "ERRO: {$e->getMessage()}\n";
        echo "---\n\n";
    }
}

// Verifica o telefone específico do screenshot (se visível)
echo "=== TELEFONE DO CLIENTE CHARLES DIETRICH ===\n\n";

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$db = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE name LIKE '%Charles%' OR name LIKE '%Dietrich%' LIMIT 5");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenants as $tenant) {
    echo "ID: {$tenant['id']}\n";
    echo "Nome: {$tenant['name']}\n";
    echo "Telefone no banco: {$tenant['phone']}\n";
    
    if ($tenant['phone']) {
        try {
            $normalized = PhoneNormalizer::toE164OrNull($tenant['phone'], 'BR', false);
            echo "Telefone normalizado: " . ($normalized ?: 'NULL') . "\n";
            if ($normalized) {
                echo "Com +: +{$normalized}\n";
                echo "Válido para Meta API: " . (preg_match('/^\d{12,13}$/', $normalized) ? 'SIM' : 'NÃO') . "\n";
            }
        } catch (Exception $e) {
            echo "ERRO ao normalizar: {$e->getMessage()}\n";
        }
    }
    echo "---\n\n";
}
