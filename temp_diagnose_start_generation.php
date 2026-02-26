<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Diagnóstico: Por que o start não está sendo gerado? ===\n\n";

// Verifica estado atual do tenant 146
$stmt = $db->prepare("SELECT billing_auto_send, billing_started_at FROM tenants WHERE id = 146");
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estado atual do tenant 146:\n";
echo "- billing_auto_send: " . ($tenant['billing_auto_send'] ? 'SIM (1)' : 'NÃO (0)') . "\n";
echo "- billing_started_at: " . ($tenant['billing_started_at'] ?? 'NULL') . "\n\n";

// Simula a lógica do controller
$wasOff = !$tenant || $tenant['billing_auto_send'] == 0;
$autoSend = 1; // Simulando que o usuário marcou o checkbox
$turningOn = $autoSend == 1 && $wasOff;

echo "Lógica do controller:\n";
echo "- wasOff: " . ($wasOff ? 'TRUE' : 'FALSE') . " (estava desligado?)\n";
echo "- autoSend: " . $autoSend . " (valor do checkbox)\n";
echo "- turningOn: " . ($turningOn ? 'TRUE' : 'FALSE') . " (está ativando agora?)\n\n";

if (!$turningOn) {
    echo "❌ PROBLEMA IDENTIFICADO!\n";
    echo "A condição 'turningOn' é FALSE, então o start NÃO será gerado.\n\n";
    echo "MOTIVO: O automático já está ATIVO (billing_auto_send = 1)\n";
    echo "SOLUÇÃO: O controller só gera start quando está ATIVANDO (de 0 para 1)\n\n";
    echo "Como o automático já está ativo, clicar em 'Iniciar' apenas SALVA as configurações,\n";
    echo "mas NÃO gera uma nova mensagem de start.\n\n";
    echo "Para forçar a geração, você precisa:\n";
    echo "1. Desmarcar o checkbox 'Automático'\n";
    echo "2. Clicar em 'Salvar'\n";
    echo "3. Marcar novamente o checkbox 'Automático'\n";
    echo "4. Clicar em 'Iniciar'\n";
} else {
    echo "✅ A condição está correta, o start DEVERIA ser gerado.\n";
}
