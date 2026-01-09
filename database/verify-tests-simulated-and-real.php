<?php

/**
 * Verifica os testes simulados e reais do WhatsApp Gateway
 * 
 * Eventos a verificar:
 * - Simulado: 2c2b8f96-9151-4b99-8150-2dffa74c842e
 * - Reais: eventos relacionados a "teste webhook real" e "teste simulado para Pixel Hub"
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

echo "=== VERIFICAÇÃO DE TESTES: Simulado e Real ===\n\n";

$db = DB::getConnection();

// Event ID do teste simulado
$simulatedEventId = '2c2b8f96-9151-4b99-8150-2dffa74c842e';

// Textos das mensagens reais para buscar
$realTestMessages = [
    'teste webhook real',
    'teste simulado para Pixel Hub'
];

echo "═══════════════════════════════════════════════════════════════\n";
echo "1. VERIFICAÇÃO DO TESTE SIMULADO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Event ID: {$simulatedEventId}\n\n";

try {
    // Busca o evento simulado
    $simulatedEvent = EventIngestionService::findByEventId($simulatedEventId);
    
    if (!$simulatedEvent) {
        echo "✗ ERRO: Evento simulado não encontrado no banco de dados!\n\n";
    } else {
        echo "✓ Evento simulado encontrado!\n\n";
        
        // Exibe detalhes
        echo "Detalhes do Evento Simulado:\n";
        echo str_repeat("-", 60) . "\n";
        echo "Event ID:     {$simulatedEvent['event_id']}\n";
        echo "Event Type:   {$simulatedEvent['event_type']}\n";
        echo "Source:       {$simulatedEvent['source_system']}\n";
        echo "Status:       {$simulatedEvent['status']}\n";
        echo "Trace ID:     {$simulatedEvent['trace_id']}\n";
        echo "Tenant ID:    " . ($simulatedEvent['tenant_id'] ?? 'NULL') . "\n";
        echo "Criado em:    {$simulatedEvent['created_at']}\n";
        echo str_repeat("-", 60) . "\n\n";
        
        // Decodifica payload
        $simulatedPayload = json_decode($simulatedEvent['payload'], true);
        if ($simulatedPayload) {
            echo "Payload do Evento Simulado:\n";
            echo json_encode($simulatedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        
        // Decodifica metadata
        if (!empty($simulatedEvent['metadata'])) {
            $simulatedMetadata = json_decode($simulatedEvent['metadata'], true);
            if ($simulatedMetadata) {
                echo "Metadata do Evento Simulado:\n";
                echo json_encode($simulatedMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            }
        }
        
        // Validações do evento simulado
        echo "Validações do Evento Simulado:\n";
        $simulatedValidations = [];
        
        if ($simulatedEvent['source_system'] === 'pixelhub_test') {
            $simulatedValidations[] = "✓ Source system correto: pixelhub_test (simulado)";
        } else {
            $simulatedValidations[] = "✗ Source system incorreto: {$simulatedEvent['source_system']} (esperado: pixelhub_test)";
        }
        
        if ($simulatedEvent['status'] === 'queued') {
            $simulatedValidations[] = "✓ Status correto: queued";
        } else {
            $simulatedValidations[] = "✗ Status incorreto: {$simulatedEvent['status']} (esperado: queued)";
        }
        
        if (!empty($simulatedEvent['metadata'])) {
            $metadata = json_decode($simulatedEvent['metadata'], true);
            if ($metadata && isset($metadata['test']) && $metadata['test'] === true) {
                $simulatedValidations[] = "✓ Metadata indica teste: test=true";
            }
            if ($metadata && isset($metadata['simulated']) && $metadata['simulated'] === true) {
                $simulatedValidations[] = "✓ Metadata indica simulação: simulated=true";
            }
        }
        
        foreach ($simulatedValidations as $validation) {
            echo "  {$validation}\n";
        }
        echo "\n";
    }
    
} catch (\Exception $e) {
    echo "✗ ERRO ao buscar evento simulado: " . $e->getMessage() . "\n\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "2. VERIFICAÇÃO DOS TESTES REAIS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Busca eventos reais relacionados às mensagens de teste
foreach ($realTestMessages as $index => $messageText) {
    echo "Buscando eventos relacionados a: \"{$messageText}\"\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        // Busca eventos que contenham o texto no payload
        $stmt = $db->prepare("
            SELECT 
                event_id,
                event_type,
                source_system,
                status,
                trace_id,
                tenant_id,
                payload,
                metadata,
                created_at
            FROM communication_events
            WHERE event_type = 'whatsapp.inbound.message'
            AND source_system = 'wpp_gateway'
            AND payload LIKE ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute(["%{$messageText}%"]);
        $realEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($realEvents)) {
            echo "  ⚠ Nenhum evento real encontrado para: \"{$messageText}\"\n";
            echo "  Tentando busca mais ampla...\n\n";
            
            // Busca mais ampla: últimos eventos do wpp_gateway
            $stmt = $db->prepare("
                SELECT 
                    event_id,
                    event_type,
                    source_system,
                    status,
                    trace_id,
                    tenant_id,
                    payload,
                    metadata,
                    created_at
                FROM communication_events
                WHERE event_type = 'whatsapp.inbound.message'
                AND source_system = 'wpp_gateway'
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $recentRealEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($recentRealEvents)) {
                echo "  Eventos reais recentes encontrados:\n";
                foreach ($recentRealEvents as $event) {
                    $payload = json_decode($event['payload'], true);
                    $text = $payload['text'] ?? $payload['body'] ?? $payload['message'] ?? 'N/A';
                    echo "    - Event ID: {$event['event_id']}\n";
                    echo "      Texto: " . substr($text, 0, 50) . "...\n";
                    echo "      Status: {$event['status']}\n";
                    echo "      Criado em: {$event['created_at']}\n\n";
                }
            } else {
                echo "  ✗ Nenhum evento real encontrado no sistema\n\n";
            }
        } else {
            echo "  ✓ " . count($realEvents) . " evento(s) real(is) encontrado(s)!\n\n";
            
            foreach ($realEvents as $event) {
                echo "  Evento Real:\n";
                echo "  " . str_repeat("-", 58) . "\n";
                echo "  Event ID:     {$event['event_id']}\n";
                echo "  Event Type:   {$event['event_type']}\n";
                echo "  Source:       {$event['source_system']}\n";
                echo "  Status:       {$event['status']}\n";
                echo "  Trace ID:     {$event['trace_id']}\n";
                echo "  Tenant ID:    " . ($event['tenant_id'] ?? 'NULL') . "\n";
                echo "  Criado em:    {$event['created_at']}\n";
                echo "  " . str_repeat("-", 58) . "\n";
                
                // Decodifica payload
                $realPayload = json_decode($event['payload'], true);
                if ($realPayload) {
                    echo "  Payload:\n";
                    echo "  " . json_encode($realPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                }
                
                // Validações do evento real
                echo "\n  Validações:\n";
                if ($event['source_system'] === 'wpp_gateway') {
                    echo "    ✓ Source system correto: wpp_gateway (real)\n";
                } else {
                    echo "    ✗ Source system incorreto: {$event['source_system']} (esperado: wpp_gateway)\n";
                }
                
                if ($event['status'] === 'queued') {
                    echo "    ✓ Status correto: queued\n";
                } else {
                    echo "    ⚠ Status: {$event['status']}\n";
                }
                
                echo "\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "  ✗ ERRO ao buscar eventos reais: " . $e->getMessage() . "\n\n";
    }
    
    echo "\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "3. COMPARAÇÃO: Simulado vs Real\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Busca todos os eventos recentes para comparação
try {
    $stmt = $db->query("
        SELECT 
            event_id,
            event_type,
            source_system,
            status,
            created_at,
            payload
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $allRecentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($allRecentEvents)) {
        echo "Eventos Recentes (últimos 20):\n";
        echo str_repeat("-", 60) . "\n";
        
        $simulatedCount = 0;
        $realCount = 0;
        
        foreach ($allRecentEvents as $event) {
            $payload = json_decode($event['payload'], true);
            $text = '';
            if (isset($payload['text']) && is_string($payload['text'])) {
                $text = $payload['text'];
            } elseif (isset($payload['body']) && is_string($payload['body'])) {
                $text = $payload['body'];
            } elseif (isset($payload['message']['text']) && is_string($payload['message']['text'])) {
                $text = $payload['message']['text'];
            } else {
                $text = 'N/A';
            }
            $textPreview = strlen($text) > 40 ? substr($text, 0, 40) . '...' : $text;
            
            $sourceIcon = $event['source_system'] === 'pixelhub_test' ? '🧪' : '📱';
            $sourceLabel = $event['source_system'] === 'pixelhub_test' ? 'SIMULADO' : 'REAL';
            
            if ($event['source_system'] === 'pixelhub_test') {
                $simulatedCount++;
            } else {
                $realCount++;
            }
            
            echo "{$sourceIcon} [{$sourceLabel}] {$event['event_id']}\n";
            echo "   Tipo: {$event['event_type']}\n";
            echo "   Status: {$event['status']}\n";
            echo "   Texto: {$textPreview}\n";
            echo "   Criado: {$event['created_at']}\n\n";
        }
        
        echo str_repeat("-", 60) . "\n";
        echo "Resumo:\n";
        echo "  🧪 Simulados: {$simulatedCount}\n";
        echo "  📱 Reais: {$realCount}\n";
        echo "  📊 Total: " . count($allRecentEvents) . "\n\n";
    }
    
} catch (\Exception $e) {
    echo "✗ ERRO ao buscar eventos para comparação: " . $e->getMessage() . "\n\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "4. RESUMO FINAL\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$summary = [];

if (isset($simulatedEvent) && $simulatedEvent) {
    $summary[] = "✓ Teste Simulado: Evento encontrado e validado";
} else {
    $summary[] = "✗ Teste Simulado: Evento não encontrado";
}

if (isset($realEvents) && !empty($realEvents)) {
    $summary[] = "✓ Testes Reais: " . count($realEvents) . " evento(s) encontrado(s)";
} else {
    $summary[] = "⚠ Testes Reais: Nenhum evento encontrado (pode ser normal se não houve mensagens reais)";
}

foreach ($summary as $item) {
    echo "  {$item}\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "FIM DA VERIFICAÇÃO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

