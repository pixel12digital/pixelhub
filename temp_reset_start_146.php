<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Resetando Start do Tenant 146 ===\n\n";

// 1. Cancela mensagem antiga
echo "1. Cancelando mensagem antiga...\n";
$stmt = $db->prepare("UPDATE billing_start_messages SET status = 'cancelled' WHERE tenant_id = 146 AND status = 'pending'");
$stmt->execute();
echo "   ✓ Mensagem antiga cancelada\n\n";

// 2. Reseta billing_started_at
echo "2. Resetando billing_started_at...\n";
$stmt = $db->prepare("UPDATE tenants SET billing_started_at = NULL WHERE id = 146");
$stmt->execute();
echo "   ✓ billing_started_at resetado\n\n";

echo "✅ PRONTO! Agora faça o seguinte:\n\n";
echo "1. Acesse: https://hub.pixel12digital.com.br/tenants/view?id=146&tab=financial\n";
echo "2. O botão vai mostrar 'INICIAR' com badge '⚠️ AGUARDANDO START'\n";
echo "3. Clique em 'INICIAR'\n";
echo "4. Modal vai abrir com a mensagem NOVA (com links e personalização)\n";
echo "5. Revise e aprove!\n";
