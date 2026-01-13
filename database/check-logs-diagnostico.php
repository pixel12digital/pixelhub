<?php
/**
 * Verificação 2: Buscar logs de diagnóstico no banco ou arquivo
 * 
 * Verifica se os logs temporários foram gerados
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== VERIFICAÇÃO 2: Logs de Diagnóstico ===\n\n";

// Busca eventos recentes do ServPro
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.status,
        ce.processed_at
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    AND (
        ce.payload LIKE '%554796474223%'
        OR ce.payload LIKE '%4796474223%'
        OR ce.payload LIKE '%TESTE SERVPRO%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 3
");

$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento recente encontrado.\n";
    echo "   Envie uma mensagem de teste e execute novamente.\n";
    exit(1);
}

echo "📋 EVENTOS RECENTES ENCONTRADOS:\n";
foreach ($events as $event) {
    echo "   event_id: {$event['event_id']}\n";
    echo "   event_type: {$event['event_type']}\n";
    echo "   created_at: {$event['created_at']}\n";
    echo "   status: {$event['status']}\n";
    echo "   processed_at: " . ($event['processed_at'] ?: 'NULL') . "\n";
    echo "\n";
}

$latestEvent = $events[0];

echo "=== INSTRUÇÕES PARA VERIFICAR LOGS ===\n\n";
echo "Os logs de diagnóstico devem estar em:\n";
echo "1. error_log do PHP (geralmente em /var/log/php/error.log)\n";
echo "2. Arquivo de log do PixelHub (se configurado: logs/pixelhub.log)\n";
echo "3. Logs do servidor web (Apache/Nginx)\n\n";

echo "Buscar por:\n";
echo "  grep 'DIAGNOSTICO' /caminho/do/log\n";
echo "  grep 'CONVERSATION UPSERT' /caminho/do/log\n\n";

echo "Ou verificar logs do PHP:\n";
echo "  tail -100 /var/log/php/error.log | grep DIAGNOSTICO\n\n";

echo "Evento mais recente para rastrear:\n";
echo "  event_id: {$latestEvent['event_id']}\n";
echo "  created_at: {$latestEvent['created_at']}\n\n";

