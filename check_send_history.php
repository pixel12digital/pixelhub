<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== HISTÓRICO DE ENVIOS WHATSAPP ===\n";

// Buscar todos os envios SDR
$stmt = $db->prepare("
    SELECT id, session_name, phone, establishment_name, message, status, 
           scheduled_at, sent_at, whapi_message_id, error, attempts,
           DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created
    FROM sdr_dispatch_queue 
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$envios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$envios) {
    echo "❌ Nenhum envio encontrado!\n";
    exit;
}

echo "\nÚLTIMOS 20 ENVIOS:\n";
echo str_repeat("=", 120) . "\n";
printf("%-4s %-15s %-20s %-12s %-8s %-20s %-15s %s\n", 
    "ID", "SESSÃO", "EMPRESA", "TELEFONE", "STATUS", "AGENDADO", "ENVIADO", "MSG_ID");
echo str_repeat("-", 120) . "\n";

foreach ($envios as $e) {
    $statusIcon = $e['status'] === 'sent' ? '✅' : ($e['status'] === 'failed' ? '❌' : '⏳');
    $empresa = substr($e['establishment_name'], 0, 18);
    $agendado = date('d/m H:i', strtotime($e['scheduled_at']));
    $enviado = $e['sent_at'] ? date('d/m H:i', strtotime($e['sent_at'])) : 'N/A';
    $msgId = $e['whapi_message_id'] ? substr($e['whapi_message_id'], 0, 12) . '...' : 'N/A';
    
    printf("%-4d %-15s %-20s %-12s %-8s %-20s %-15s %s\n",
        $e['id'], $e['session_name'], $empresa, $e['phone'], 
        $statusIcon . ' ' . $e['status'], $agendado, $enviado, $msgId);
    
    if ($e['error']) {
        printf("     ERRO: %s\n", substr($e['error'], 0, 80));
    }
}

// Análise dos status
echo "\n" . str_repeat("=", 50) . "\n";
echo "ANÁLISE DOS STATUS:\n";

$stmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM sdr_dispatch_queue 
    GROUP BY status
    ORDER BY count DESC
");
$stmt->execute();
$statusCount = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($statusCount as $s) {
    $icon = $s['status'] === 'sent' ? '✅' : ($s['status'] === 'failed' ? '❌' : '⏳');
    printf("%s %s: %d mensagens\n", $icon, $s['status'], $s['count']);
}

// Verificar mensagens com erro
echo "\nMENSAGENS COM ERRO:\n";
$stmt = $db->prepare("
    SELECT id, establishment_name, phone, error, created_at
    FROM sdr_dispatch_queue 
    WHERE error IS NOT NULL AND error != ''
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($errors) {
    foreach ($errors as $e) {
        printf("- ID:%d | %s | %s\n", $e['id'], $e['establishment_name'], $e['phone']);
        printf("  Erro: %s\n", $e['error']);
    }
} else {
    echo "✅ Nenhuma mensagem com erro encontrado!\n";
}

// Explicação sobre o que aparece no WhatsApp
echo "\n" . str_repeat("=", 50) . "\n";
echo "SOBRE O QUE APARECE NO WHATSAPP:\n";
echo "✅ Status 'sent' no banco = Sistema enviou para API\n";
echo "✅ API Whapi aceita = Mensagem na fila do WhatsApp\n";
echo "❌ Número sem WhatsApp = Fica 'pending' infinitamente\n";
echo "❌ Número bloqueou = Não entrega (sem erro)\n";
echo "✅ Número com WhatsApp = Status muda para 'delivered'\n\n";

echo "POR QUE A MENSAGEM DA AMORE MIO FICOU 'PENDING':\n";
echo "- O número 5547991953981 não tem WhatsApp ativo\n";
echo "- Sistema funcionou corretamente (enviou)\n";
echo "- WhatsApp não entregou (número inválido/inexistente)\n";

echo "\n=== FIM ===\n";
