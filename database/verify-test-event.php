<?php

/**
 * Verifica o evento de teste que foi inserido
 * Event ID: de246798-13e5-4b78-8b47-93251119e6be
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
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

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\EventIngestionService;

Env::load();

echo "=== VERIFICAÇÃO DO TESTE: Webhook Simulado ===\n\n";

$db = DB::getConnection();

// Event ID que aparece na tela
$eventId = 'de246798-13e5-4b78-8b47-93251119e6be';

echo "1. Buscando evento no banco de dados...\n";
echo "   Event ID: {$eventId}\n\n";

try {
    // Busca o evento usando o EventIngestionService
    $event = EventIngestionService::findByEventId($eventId);
    
    if (!$event) {
        echo "✗ ERRO: Evento não encontrado no banco de dados!\n\n";
        echo "Verificando se o Event ID está correto...\n";
        
        // Busca eventos recentes
        $stmt = $db->query("
            SELECT event_id, event_type, source_system, created_at, status
            FROM communication_events
            WHERE event_type = 'whatsapp.inbound.message'
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent)) {
            echo "\nEventos recentes encontrados:\n";
            foreach ($recent as $e) {
                echo "  - Event ID: {$e['event_id']}\n";
                echo "    Tipo: {$e['event_type']}\n";
                echo "    Status: {$e['status']}\n";
                echo "    Criado em: {$e['created_at']}\n\n";
            }
        } else {
            echo "Nenhum evento encontrado na tabela.\n\n";
        }
        
        exit(1);
    }
    
    echo "✓ Evento encontrado no banco de dados!\n\n";
    
    // Exibe detalhes do evento
    echo "2. Detalhes do Evento:\n";
    echo str_repeat("-", 60) . "\n";
    echo "Event ID:     {$event['event_id']}\n";
    echo "Event Type:   {$event['event_type']}\n";
    echo "Source:       {$event['source_system']}\n";
    echo "Status:       {$event['status']}\n";
    echo "Trace ID:     {$event['trace_id']}\n";
    echo "Tenant ID:    " . ($event['tenant_id'] ?? 'NULL') . "\n";
    echo "Criado em:    {$event['created_at']}\n";
    echo str_repeat("-", 60) . "\n\n";
    
    // Decodifica e exibe payload
    echo "3. Payload do Evento:\n";
    $payload = json_decode($event['payload'], true);
    if ($payload) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    } else {
        echo "✗ Erro ao decodificar payload JSON\n";
        echo "Payload raw: " . substr($event['payload'], 0, 200) . "\n\n";
    }
    
    // Decodifica e exibe metadata
    if (!empty($event['metadata'])) {
        echo "4. Metadata do Evento:\n";
        $metadata = json_decode($event['metadata'], true);
        if ($metadata) {
            echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        } else {
            echo "✗ Erro ao decodificar metadata JSON\n\n";
        }
    }
    
    // Validações
    echo "5. Validações:\n";
    $validations = [];
    
    // Verifica event_type
    if ($event['event_type'] === 'whatsapp.inbound.message') {
        $validations[] = "✓ Event type correto: whatsapp.inbound.message";
    } else {
        $validations[] = "✗ Event type incorreto: {$event['event_type']}";
    }
    
    // Verifica source_system
    if ($event['source_system'] === 'pixelhub_test') {
        $validations[] = "✓ Source system correto: pixelhub_test";
    } else {
        $validations[] = "✗ Source system incorreto: {$event['source_system']}";
    }
    
    // Verifica status
    if ($event['status'] === 'queued') {
        $validations[] = "✓ Status correto: queued";
    } else {
        $validations[] = "✗ Status incorreto: {$event['status']}";
    }
    
    // Verifica payload
    if ($payload && isset($payload['channel_id']) && isset($payload['from']) && isset($payload['text'])) {
        $validations[] = "✓ Payload contém campos obrigatórios: channel_id, from, text";
        
        if ($payload['channel_id'] === 'Pixel12 Digital') {
            $validations[] = "✓ Channel ID correto: Pixel12 Digital";
        } else {
            $validations[] = "✗ Channel ID incorreto: {$payload['channel_id']}";
        }
        
        if ($payload['from'] === '554796164699') {
            $validations[] = "✓ Telefone (from) correto: 554796164699";
        } else {
            $validations[] = "✗ Telefone (from) incorreto: {$payload['from']}";
        }
        
        if (isset($payload['text']) && strpos($payload['text'], 'Teste simulado') !== false) {
            $validations[] = "✓ Mensagem (text) contém 'Teste simulado'";
        } else {
            $validations[] = "✗ Mensagem (text) não contém 'Teste simulado'";
        }
    } else {
        $validations[] = "✗ Payload não contém campos obrigatórios";
    }
    
    // Verifica metadata
    if (!empty($event['metadata'])) {
        $metadata = json_decode($event['metadata'], true);
        if ($metadata && isset($metadata['test']) && $metadata['test'] === true) {
            $validations[] = "✓ Metadata indica teste: test=true";
        } else {
            $validations[] = "✗ Metadata não indica teste corretamente";
        }
        
        if ($metadata && isset($metadata['simulated']) && $metadata['simulated'] === true) {
            $validations[] = "✓ Metadata indica simulação: simulated=true";
        } else {
            $validations[] = "✗ Metadata não indica simulação corretamente";
        }
    }
    
    foreach ($validations as $validation) {
        echo "  {$validation}\n";
    }
    
    echo "\n";
    
    // Verifica se o evento aparece na listagem
    echo "6. Verificação na listagem de eventos:\n";
    $stmt = $db->prepare("
        SELECT ce.*, t.name as tenant_name
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.event_id = ?
        ORDER BY ce.created_at DESC
    ");
    $stmt->execute([$eventId]);
    $listedEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($listedEvent) {
        echo "  ✓ Evento aparece na listagem (com JOIN com tenants)\n";
        echo "  Tenant Name: " . ($listedEvent['tenant_name'] ?? 'N/A') . "\n\n";
    } else {
        echo "  ✗ Evento não aparece na listagem\n\n";
    }
    
    // Resumo final
    echo str_repeat("=", 60) . "\n";
    echo "RESUMO DA VERIFICAÇÃO\n";
    echo str_repeat("=", 60) . "\n";
    
    $passedCount = count(array_filter($validations, function($v) { return strpos($v, '✓') !== false; }));
    $totalCount = count($validations);
    
    echo "Validações passadas: {$passedCount}/{$totalCount}\n\n";
    
    if ($passedCount === $totalCount) {
        echo "✓ TESTE COMPLETAMENTE VÁLIDO!\n";
        echo "✓ O webhook foi simulado com sucesso!\n";
        echo "✓ O evento foi inserido corretamente no banco de dados!\n";
        echo "✓ Todos os dados estão corretos!\n\n";
        exit(0);
    } else {
        echo "⚠ TESTE PARCIALMENTE VÁLIDO\n";
        echo "Algumas validações falharam. Verifique acima.\n\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

