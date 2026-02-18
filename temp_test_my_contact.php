<?php
// Teste com contato real do usuário

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

echo "=== Teste com contato 4796164699 ===\n\n";

$db = DB::getConnection();

// 1. Resetar mensagem ID 2 para pending
$stmt = $db->prepare("UPDATE scheduled_messages SET status = 'pending', sent_at = NULL, failed_reason = NULL WHERE id = 2");
$stmt->execute();
echo "✓ Mensagem ID 2 resetada para pending\n";

// 2. Atualizar agenda_manual_items para pending
$stmt2 = $db->prepare("UPDATE agenda_manual_items SET status = 'pending', completed_at = NULL, completed_by = NULL WHERE id = 2");
$stmt2->execute();
echo "✓ Agenda Item ID 2 resetado para pending\n";

// 3. Buscar lead do usuário para substituir na mensagem
$stmt3 = $db->prepare("SELECT id, name, phone FROM leads WHERE phone LIKE '%4796164699%'");
$stmt3->execute();
$lead = $stmt3->fetch();

if ($lead) {
    echo "✓ Lead encontrado: {$lead['name']} ({$lead['phone']})\n";
    
    // 4. Atualizar opportunity para usar este lead
    $stmt4 = $db->prepare("UPDATE opportunities SET lead_id = ? WHERE id = 7");
    $stmt4->execute([$lead['id']]);
    echo "✓ Opportunity ID 7 atualizada para lead {$lead['id']}\n";
    
    // 5. Atualizar mensagem para teste
    $stmt5 = $db->prepare("UPDATE scheduled_messages SET message_text = ? WHERE id = 2");
    $testMessage = "TESTE DE VALIDAÇÃO - Follow-up automatizado. Esta é uma mensagem de teste para validar o fluxo completo. Por favor, ignore.";
    $stmt5->execute([$testMessage]);
    echo "✓ Mensagem atualizada para teste\n";
    
} else {
    echo "❌ Lead não encontrado para o telefone 4796164699\n";
}

echo "\n=== Pronto para testar worker ===\n";
echo "Execute: php scripts/scheduled_messages_worker.php\n";
?>
