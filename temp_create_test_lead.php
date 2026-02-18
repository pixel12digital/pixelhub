<?php
// Criar lead de teste para validação

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\DB;

echo "=== Criando lead de teste ===\n\n";

$db = DB::getConnection();

// Criar lead de teste
$stmt = $db->prepare("
    INSERT INTO leads (name, phone, email, status, created_at, updated_at)
    VALUES (?, ?, ?, 'active', NOW(), NOW())
");

$leadName = 'Teste Worker';
$phone = '4796164699';
$email = 'teste@pixel12digital.com.br';

$stmt->execute([$leadName, $phone, $email]);
$leadId = $db->lastInsertId();

echo "✓ Lead criado: ID {$leadId} - {$leadName} ({$phone})\n";

// Atualizar opportunity para usar este lead
$stmt2 = $db->prepare("UPDATE opportunities SET lead_id = ? WHERE id = 7");
$stmt2->execute([$leadId]);
echo "✓ Opportunity ID 7 atualizada para lead {$leadId}\n";

// Atualizar mensagem para teste
$stmt3 = $db->prepare("UPDATE scheduled_messages SET message_text = ?, lead_id = ? WHERE id = 2");
$testMessage = "TESTE DE VALIDAÇÃO - Follow-up automatizado. Esta é uma mensagem de teste para validar o fluxo completo. Por favor, ignore. - " . date('H:i:s');
$stmt3->execute([$testMessage, $leadId]);
echo "✓ Mensagem atualizada para teste\n";

// Resetar status
$stmt4 = $db->prepare("UPDATE scheduled_messages SET status = 'pending', sent_at = NULL, failed_reason = NULL WHERE id = 2");
$stmt4->execute();
echo "✓ Mensagem ID 2 resetada para pending\n";

$stmt5 = $db->prepare("UPDATE agenda_manual_items SET status = 'pending', completed_at = NULL, completed_by = NULL WHERE id = 2");
$stmt5->execute();
echo "✓ Agenda Item ID 2 resetado para pending\n";

echo "\n=== Configuração concluída ===\n";
echo "Lead ID: {$leadId}\n";
echo "Telefone: 4796164699\n";
echo "Execute: php scripts/scheduled_messages_worker.php\n";
?>
