<?php
/**
 * Script para buscar áudio recebido de pixe12digital por volta de 16:38
 * Duração aproximada: 4 segundos
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

Env::load();

echo "=== BUSCANDO ÁUDIO RECEBIDO ~16:38 de pixe12digital ===\n\n";

$db = DB::getConnection();

// Busca áudios recebidos hoje por volta de 16:38 (16:30 - 16:45)
$targetHour = '16';
$targetMinStart = '30';
$targetMinEnd = '45';

echo "1. Buscando áudios recebidos entre 16:30 e 16:45 de hoje...\n\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id,
        cm.id as media_id,
        cm.media_type,
        cm.mime_type,
        cm.stored_path,
        cm.file_name,
        cm.file_size,
        cm.created_at as media_created_at
    FROM communication_events ce
    LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND DATE(ce.created_at) = CURDATE()
    AND HOUR(ce.created_at) = ?
    AND MINUTE(ce.created_at) BETWEEN ? AND ?
    AND (
        cm.media_type IN ('audio', 'ptt', 'voice')
        OR ce.payload LIKE '%\"type\":\"audio\"%'
        OR ce.payload LIKE '%\"type\":\"ptt\"%'
        OR ce.payload LIKE '%\"type\":\"voice\"%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt->execute([$targetHour, $targetMinStart, $targetMinEnd]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ Nenhum áudio encontrado entre 16:30 e 16:45 de hoje.\n\n";
    
    // Tenta buscar qualquer áudio recebido hoje por volta das 16:38 (ampliando busca)
    echo "2. Ampliando busca para áudios de hoje por volta de 16:38 (16:00 - 17:00)...\n\n";
    
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.payload,
            cm.id as media_id,
            cm.media_type,
            cm.mime_type,
            cm.stored_path,
            cm.file_name,
            cm.file_size
        FROM communication_events ce
        LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
        WHERE ce.event_type = 'whatsapp.inbound.message'
        AND DATE(ce.created_at) = CURDATE()
        AND HOUR(ce.created_at) BETWEEN 16 AND 17
        AND (
            cm.media_type IN ('audio', 'ptt', 'voice')
            OR ce.payload LIKE '%\"type\":\"audio\"%'
            OR ce.payload LIKE '%\"type\":\"ptt\"%'
            OR ce.payload LIKE '%\"type\":\"voice\"%'
        )
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "   ❌ Nenhum áudio encontrado entre 16:00 e 17:00 de hoje.\n\n";
        
        // Busca qualquer áudio de hoje
        echo "3. Buscando qualquer áudio recebido hoje...\n\n";
        
        $stmt = $db->query("
            SELECT 
                ce.id,
                ce.event_id,
                ce.event_type,
                ce.created_at,
                cm.media_type,
                cm.stored_path,
                cm.file_name,
                cm.file_size
            FROM communication_events ce
            LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
            WHERE ce.event_type = 'whatsapp.inbound.message'
            AND DATE(ce.created_at) = CURDATE()
            AND cm.media_type IN ('audio', 'ptt', 'voice')
            ORDER BY ce.created_at DESC
            LIMIT 10
        ");
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (empty($events)) {
    echo "   ❌ Nenhum áudio recebido hoje foi encontrado.\n";
    echo "\n=== Verifique:\n";
    echo "   - Se o áudio foi realmente recebido hoje\n";
    echo "   - Se o sistema está processando mensagens corretamente\n";
    echo "   - Verifique a tabela communication_events e communication_media\n";
    exit(0);
}

echo "   ✅ Encontrados " . count($events) . " áudio(s) encontrado(s)\n\n";

// Analisa cada evento
foreach ($events as $i => $event) {
    $payload = json_decode($event['payload'], true);
    if (!$payload) {
        continue;
    }
    
    $type = $payload['type'] 
        ?? $payload['message']['type'] 
        ?? $payload['message']['message']['type'] 
        ?? 'unknown';
    
    $from = $payload['from'] 
        ?? $payload['message']['from'] 
        ?? $payload['message']['key']['remoteJid']
        ?? 'N/A';
    
    $body = $payload['body'] 
        ?? $payload['text'] 
        ?? $payload['message']['body']
        ?? $payload['message']['conversation']
        ?? 'N/A';
    
    // Extrai duração se disponível (em segundos)
    $duration = null;
    if (isset($payload['seconds'])) {
        $duration = $payload['seconds'];
    } elseif (isset($payload['message']['seconds'])) {
        $duration = $payload['message']['seconds'];
    } elseif (isset($payload['duration'])) {
        $duration = $payload['duration'];
    }
    
    // Verifica se pode ser o áudio procurado (4 segundos)
    $matchesDuration = ($duration !== null && abs($duration - 4) <= 1); // tolerância de 1 segundo
    
    echo "   📎 Áudio " . ($i + 1) . ":\n";
    echo "      - Event ID: {$event['event_id']}\n";
    echo "      - Data/Hora: {$event['created_at']}\n";
    echo "      - Tipo: {$type}\n";
    echo "      - From: {$from}\n";
    if ($duration !== null) {
        echo "      - Duração: {$duration}s " . ($matchesDuration ? "✅ (próximo de 4s)" : "") . "\n";
    } else {
        echo "      - Duração: N/A (não informada no payload)\n";
    }
    echo "      - Tenant ID: " . ($event['tenant_id'] ?? 'N/A') . "\n";
    
    // Verifica processamento
    if ($event['media_id']) {
        $absolutePath = __DIR__ . '/../storage/' . ltrim($event['stored_path'], '/');
        $fileExists = file_exists($absolutePath);
        
        echo "      - ✅ Mídia processada:\n";
        echo "        * ID: {$event['media_id']}\n";
        echo "        * Tipo: {$event['media_type']}\n";
        echo "        * MIME: {$event['mime_type']}\n";
        echo "        * Arquivo: {$event['stored_path']} " . ($fileExists ? '✅' : '❌') . "\n";
        echo "        * Nome: {$event['file_name']}\n";
        echo "        * Tamanho: " . ($event['file_size'] ? number_format($event['file_size'] / 1024, 2) . ' KB' : 'N/A') . "\n";
        
        if ($fileExists) {
            $fileSize = filesize($absolutePath);
            echo "        * Tamanho real: " . number_format($fileSize / 1024, 2) . " KB\n";
        }
        
        // Gera URL
        if ($event['stored_path']) {
            // Monta URL manualmente (assumindo domínio local padrão)
            $baseUrl = 'http://localhost/painel.pixel12digital';
            $url = $baseUrl . '/communication-hub/media?path=' . urlencode($event['stored_path']);
            echo "        * URL: {$url}\n";
        }
    } else {
        echo "      - ❌ Mídia NÃO foi processada ainda\n";
        
        // Tenta processar agora
        echo "      - 🔄 Tentando processar agora...\n";
        try {
            $result = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
            if ($result) {
                echo "        ✅ Mídia processada com sucesso!\n";
                echo "        * Caminho: {$result['stored_path']}\n";
                echo "        * URL: {$result['url']}\n";
            } else {
                echo "        ❌ Falha ao processar mídia\n";
            }
        } catch (\Exception $e) {
            echo "        ❌ Erro: " . $e->getMessage() . "\n";
        }
    }
    
    // Busca se tem referência a "pixe12digital" ou "pixel12" no payload
    $payloadStr = json_encode($payload);
    if (stripos($payloadStr, 'pixe12digital') !== false || 
        stripos($payloadStr, 'pixel12') !== false ||
        stripos($payloadStr, 'pixel') !== false) {
        echo "      - 🔍 Menciona 'pixe12digital' ou 'pixel12' no payload ✅\n";
    }
    
    echo "\n   " . str_repeat("━", 70) . "\n\n";
}

// Busca também mensagens recebidas que contenham "pixe12digital" no texto
echo "4. Buscando mensagens com 'pixe12digital' ou 'pixel12' no payload/texto...\n\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.created_at,
        ce.payload,
        cm.media_type,
        cm.stored_path
    FROM communication_events ce
    LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND (
        ce.payload LIKE '%pixe12digital%'
        OR ce.payload LIKE '%pixel12%'
        OR ce.payload LIKE '%Pixel12%'
        OR ce.payload LIKE '%PIXEL12%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$stmt->execute();
$textMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($textMatches)) {
    echo "   ✅ Encontradas " . count($textMatches) . " mensagem(ns) mencionando pixel12\n\n";
    foreach ($textMatches as $match) {
        $payload = json_decode($match['payload'], true);
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        $createdAt = $match['created_at'];
        $hasAudio = $match['media_type'] && in_array($match['media_type'], ['audio', 'ptt', 'voice']);
        
        echo "   - Event ID: {$match['event_id']}\n";
        echo "     Data: {$createdAt}\n";
        echo "     From: {$from}\n";
        echo "     Tem áudio: " . ($hasAudio ? '✅ Sim' : '❌ Não') . "\n";
        if ($match['stored_path']) {
            echo "     Path: {$match['stored_path']}\n";
        }
        echo "\n";
    }
} else {
    echo "   ❌ Nenhuma mensagem encontrada mencionando 'pixe12digital' ou 'pixel12'\n\n";
}

echo "\n=== Fim da Busca ===\n";

