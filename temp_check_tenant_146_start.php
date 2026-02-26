<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();

echo "=== Verificando Tenant 146 - A Pousada da Praia ===\n\n";

$stmt = $db->prepare("SELECT id, name, billing_auto_send, billing_auto_channel, billing_started_at FROM tenants WHERE id = 146");
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant) {
    echo "ID: " . $tenant['id'] . "\n";
    echo "Nome: " . $tenant['name'] . "\n";
    echo "Automático: " . ($tenant['billing_auto_send'] ? 'SIM' : 'NÃO') . "\n";
    echo "Canal: " . ($tenant['billing_auto_channel'] ?? 'não definido') . "\n";
    echo "Start dado em: " . ($tenant['billing_started_at'] ?? 'NUNCA (NULL)') . "\n\n";
    
    $needsStart = !empty($tenant['billing_auto_send']) && empty($tenant['billing_started_at']);
    
    echo "=== DIAGNÓSTICO ===\n";
    echo "Precisa dar START? " . ($needsStart ? 'SIM ✅' : 'NÃO') . "\n";
    echo "Botão deve mostrar: " . ($needsStart ? 'INICIAR' : 'SALVAR') . "\n";
    echo "Badge AGUARDANDO START: " . ($needsStart ? 'DEVE APARECER ⚠️' : 'NÃO APARECE') . "\n";
} else {
    echo "Tenant 146 não encontrado!\n";
}
