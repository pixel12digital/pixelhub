<?php

/**
 * Worker: Envia lembretes para mensagens sem resposta após 24h
 * Cron: a cada hora
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Services\ScheduledMessageService;

echo "[" . date('Y-m-d H:i:s') . "] Scheduled Messages Reminders - INICIANDO\n";

try {
    // Busca mensagens sem resposta após 24h
    $messages = ScheduledMessageService::getNoResponseReminders();
    
    if (empty($messages)) {
        echo "Nenhum lembrete para enviar.\n";
        exit(0);
    }
    
    echo "Encontrados " . count($messages) . " lembrete(s) para enviar.\n";
    
    foreach ($messages as $message) {
        $contactName = $message['lead_name'] ?? $message['tenant_name'] ?? 'Contato';
        $oppName = $message['opportunity_name'] ?? '';
        
        echo "\n[Lembrete #{$message['id']}]\n";
        echo "  Contato: {$contactName}\n";
        if ($oppName) echo "  Oportunidade: {$oppName}\n";
        echo "  Enviada em: {$message['sent_at']}\n";
        
        ScheduledMessageService::sendNoResponseReminder($message['id']);
        echo "  ✅ Lembrete enviado\n";
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Lembretes enviados: " . count($messages) . "\n";
    
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n[" . date('Y-m-d H:i:s') . "] Worker finalizado.\n";
exit(0);
