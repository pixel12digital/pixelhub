<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Verificando motivo do não envio - Viviane 18/02/2026 ===\n\n";

// 1. Detalhes completos da scheduled_message ID 2
echo "1. Detalhes da scheduled_message ID 2:\n";
$sql = "SELECT sm.*, 
       ami.title as agenda_title,
       ami.item_date,
       ami.time_start,
       ami.notes,
       o.title as opportunity_title,
       o.contact_name,
       o.contact_phone
FROM scheduled_messages sm
LEFT JOIN agenda_manual_items ami ON sm.agenda_item_id = ami.id
LEFT JOIN opportunities o ON sm.opportunity_id = o.id
WHERE sm.id = 2";
$stmt = $db->prepare($sql);
$stmt->execute();
$detail = $stmt->fetch(PDO::FETCH_ASSOC);

if ($detail) {
    echo "   ID: {$detail['id']}\n";
    echo "   Agendado: {$detail['scheduled_at']} | Status: {$detail['status']}\n";
    echo "   Agenda Item ID: {$detail['agenda_item_id']}\n";
    echo "   Título Agenda: {$detail['agenda_title']}\n";
    echo "   Data Agenda: {$detail['item_date']} | Horário: {$detail['time_start']}\n";
    echo "   Opportunity: {$detail['opportunity_title']}\n";
    echo "   Contato: {$detail['contact_name']} | Fone: {$detail['contact_phone']}\n";
    echo "   Mensagem: {$detail['message_text']}\n";
    if ($detail['failed_reason']) {
        echo "   Motivo Falha: {$detail['failed_reason']}\n";
    }
    echo "   Enviado em: " . ($detail['sent_at'] ?? 'N/A') . "\n";
    echo "   Reminder enviado: " . ($detail['reminder_sent'] ? 'Sim' : 'Não') . "\n";
    echo "   Response detectado: " . ($detail['response_detected'] ? 'Sim' : 'Não') . "\n";
    echo "   Criado por: {$detail['created_by']} | Criado em: {$detail['created_at']}\n";
} else {
    echo "   Scheduled_message ID 2 não encontrada.\n";
}

// 2. Verificar se existe algum worker/cron que deveria processar
echo "\n2. Verificando se existe algum worker para scheduled_messages:\n";
$workers = [
    'scripts/scheduled_messages_worker.php',
    'scripts/process_scheduled_messages.php',
    'scripts/send_scheduled_messages.php',
    'scripts/message_worker.php'
];

foreach ($workers as $worker) {
    if (file_exists(__DIR__ . '/' . $worker)) {
        echo "   ✓ Encontrado: $worker\n";
    } else {
        echo "   ✗ Não encontrado: $worker\n";
    }
}

// 3. Verificar logs do sistema
echo "\n3. Logs do sistema (18/02/2026):\n";
$logFiles = [
    'logs/pixelhub.log',
    'logs/scheduled_messages.log',
    'logs/worker.log',
    'logs/error.log'
];

foreach ($logFiles as $logFile) {
    $fullPath = __DIR__ . '/' . $logFile;
    if (file_exists($fullPath)) {
        echo "   Verificando $logFile...\n";
        $content = file_get_contents($fullPath);
        if (strpos($content, '2026-02-18') !== false) {
            echo "   ✓ Encontradas entradas para 18/02/2026\n";
        } else {
            echo "   - Nenhuma entrada para 18/02/2026\n";
        }
    } else {
        echo "   - Arquivo não existe: $logFile\n";
    }
}

// 4. Verificar estrutura da tabela opportunities para pegar contato
echo "\n4. Verificando opportunity ID 7:\n";
$sql2 = "SELECT * FROM opportunities WHERE id = 7";
$stmt2 = $db->prepare($sql2);
$stmt2->execute();
$opp = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($opp) {
    echo "   Opportunity ID: {$opp['id']}\n";
    echo "   Título: {$opp['title']}\n";
    echo "   Contact Name: {$opp['contact_name']}\n";
    echo "   Contact Phone: {$opp['contact_phone']}\n";
    echo "   Contact Email: {$opp['contact_email']}\n";
    echo "   Status: {$opp['status']}\n";
    echo "   Tenant ID: {$opp['tenant_id']}\n";
} else {
    echo "   Opportunity ID 7 não encontrada.\n";
}

// 5. Verificar se existe alguma configuração de cron
echo "\n5. Verificando configurações de envio automático:\n";
$sql3 = "SELECT * FROM tenant_message_channels WHERE tenant_id = " . ($opp['tenant_id'] ?? 'NULL');
$stmt3 = $db->prepare($sql3);
$stmt3->execute();
$channels = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (!empty($channels)) {
    foreach ($channels as $channel) {
        echo "   Canal: {$channel['channel_type']} | Session ID: {$channel['session_id']}\n";
        echo "   Ativo: " . ($channel['is_active'] ? 'Sim' : 'Não') . "\n";
    }
} else {
    echo "   Nenhum canal de mensagem configurado.\n";
}

echo "\n=== Diagnóstico ===\n";
echo "A mensagem foi agendada corretamente para 18/02/2026 às 08:00.\n";
echo "Status permanece como 'pending', indicando que não foi processada.\n";
echo "Possíveis causas:\n";
echo "1. Não existe worker/cron para processar scheduled_messages\n";
echo "2. Worker existe mas não está rodando\n";
echo "3. Worker falhou por falta de configuração (canal WhatsApp)\n";
echo "4. Worker falhou por erro no processo de envio\n";

echo "\n=== Próximos passos ===\n";
echo "1. Verificar se existe script worker e se está no cron\n";
echo "2. Verificar logs do worker no dia 18/02\n";
echo "3. Testar envio manual da mensagem\n";
echo "4. Verificar configuração do canal WhatsApp para este tenant\n";
?>
