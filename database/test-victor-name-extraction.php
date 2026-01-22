<?php
/**
 * Script de teste para verificar extração de nome do Victor
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/ContactHelper.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\ContactHelper;

// Carrega .env
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();
if (!$db) {
    die("Erro: Não foi possível conectar ao banco de dados.\n");
}

echo "=== TESTE: Extração de Nome - Victor ===\n\n";

// Teste 1: normalizeDisplayName com "~Victor"
echo "1. Teste normalizeDisplayName:\n";
$testNames = ["~Victor", "Victor", "~Victor~", "  ~Victor  "];
foreach ($testNames as $name) {
    $reflection = new ReflectionClass(ContactHelper::class);
    $method = $reflection->getMethod('normalizeDisplayName');
    $method->setAccessible(true);
    $normalized = $method->invoke(null, $name);
    echo sprintf("  '%s' -> '%s'\n", $name, $normalized ?? 'NULL');
}

echo "\n";

// Teste 2: extractNameFromPayload com payload real
echo "2. Teste extractNameFromPayload com evento real:\n";
$stmt = $db->prepare("
    SELECT ce.payload
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%169183207809126%'
    ORDER BY ce.created_at DESC
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    $name = ContactHelper::extractNameFromPayload($event['payload']);
    echo sprintf("  Nome extraído: '%s'\n", $name ?? 'NULL');
    
    // Mostra campos do payload
    $payload = json_decode($event['payload'], true);
    echo "  Campos encontrados no payload:\n";
    echo sprintf("    - message.notifyName: %s\n", $payload['message']['notifyName'] ?? 'NULL');
    echo sprintf("    - raw.payload.notifyName: %s\n", $payload['raw']['payload']['notifyName'] ?? 'NULL');
    echo sprintf("    - raw.payload.sender.verifiedName: %s\n", $payload['raw']['payload']['sender']['verifiedName'] ?? 'NULL');
    echo sprintf("    - raw.payload.sender.name: %s\n", $payload['raw']['payload']['sender']['name'] ?? 'NULL');
    echo sprintf("    - raw.payload.sender.formattedName: %s\n", $payload['raw']['payload']['sender']['formattedName'] ?? 'NULL');
} else {
    echo "  ❌ Nenhum evento encontrado\n";
}

echo "\n";

// Teste 3: resolveDisplayNameFromEvents
echo "3. Teste resolveDisplayNameFromEvents:\n";
$reflection = new ReflectionClass(ContactHelper::class);
$method = $reflection->getMethod('resolveDisplayNameFromEvents');
$method->setAccessible(true);
$name = $method->invoke(null, '55169183207809126', null, true); // traceLog = true
echo sprintf("  Nome resolvido: '%s'\n", $name ?? 'NULL');

echo "\n";

// Teste 4: resolveDisplayName completo
echo "4. Teste resolveDisplayName completo:\n";
$name = ContactHelper::resolveDisplayName('55169183207809126', null, 'wpp_gateway', null);
echo sprintf("  Nome resolvido: '%s'\n", $name ?? 'NULL');

echo "\n=== FIM DO TESTE ===\n";

