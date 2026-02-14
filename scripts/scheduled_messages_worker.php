<?php

/**
 * Worker: Envia mensagens agendadas de follow-up no horário correto
 * Cron: a cada 5 minutos
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\ScheduledMessageService;

echo "[" . date('Y-m-d H:i:s') . "] Scheduled Messages Worker - INICIANDO\n";

try {
    // Busca mensagens pendentes
    $messages = ScheduledMessageService::getPendingMessages();
    
    if (empty($messages)) {
        echo "Nenhuma mensagem pendente para enviar.\n";
        exit(0);
    }
    
    echo "Encontradas " . count($messages) . " mensagem(ns) para enviar.\n";
    
    $sent = 0;
    $failed = 0;
    
    foreach ($messages as $message) {
        echo "\n[Mensagem #{$message['id']}]\n";
        echo "  Para: " . ($message['lead_name'] ?? $message['tenant_name'] ?? 'Desconhecido') . "\n";
        echo "  Agendada: {$message['scheduled_at']}\n";
        echo "  Texto: " . substr($message['message_text'], 0, 50) . "...\n";
        
        $success = ScheduledMessageService::send($message['id']);
        
        if ($success) {
            echo "  ✅ Enviada com sucesso!\n";
            $sent++;
        } else {
            echo "  ❌ Falha no envio\n";
            $failed++;
        }
        
        // Delay de 2 segundos entre envios para não sobrecarregar
        sleep(2);
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Enviadas: {$sent}\n";
    echo "Falhas: {$failed}\n";
    echo "Total: " . count($messages) . "\n";
    
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n[" . date('Y-m-d H:i:s') . "] Worker finalizado.\n";
exit(0);
