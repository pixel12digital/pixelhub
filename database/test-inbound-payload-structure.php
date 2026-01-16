<?php

/**
 * Script para testar estrutura de payload inbound e comparar formatos
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

echo "=== TESTE: ESTRUTURA DE PAYLOAD INBOUND ===\n\n";

$db = DB::getConnection();

// Busca últimos 10 eventos inbound
$stmt = $db->query("
    SELECT 
        id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        payload
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 10
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento inbound encontrado\n";
    exit(1);
}

echo "Analisando " . count($events) . " evento(s) inbound recente(s):\n\n";

foreach ($events as $i => $event) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "EVENTO " . ($i + 1) . " - ID: {$event['id']} | Created: {$event['created_at']}\n";
    echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . " | Channel ID: " . trim($event['channel_id'] ?? 'NULL', '"') . "\n\n";
    
    $payload = json_decode($event['payload'], true);
    if (!$payload) {
        echo "❌ Payload inválido (JSON decode falhou)\n\n";
        continue;
    }
    
    // Testa todos os caminhos possíveis para extrair 'from'
    echo "📍 TESTANDO CAMINHOS PARA EXTRAIR 'from':\n";
    
    $fromPaths = [
        'payload[\'from\']' => $payload['from'] ?? null,
        'payload[\'message\'][\'from\']' => $payload['message']['from'] ?? null,
        'payload[\'data\'][\'from\']' => $payload['data']['from'] ?? null,
        'payload[\'raw\'][\'payload\'][\'from\']' => $payload['raw']['payload']['from'] ?? null,
        'payload[\'message\'][\'key\'][\'fromMe\']' => $payload['message']['key']['fromMe'] ?? null,
        'payload[\'message\'][\'key\'][\'remoteJid\']' => $payload['message']['key']['remoteJid'] ?? null,
        'payload[\'message\'][\'key\'][\'participant\']' => $payload['message']['key']['participant'] ?? null,
        'payload[\'data\'][\'key\'][\'remoteJid\']' => $payload['data']['key']['remoteJid'] ?? null,
        'payload[\'data\'][\'key\'][\'participant\']' => $payload['data']['key']['participant'] ?? null,
    ];
    
    foreach ($fromPaths as $path => $value) {
        if ($value !== null) {
            echo "  ✅ {$path}: " . var_export($value, true) . "\n";
        } else {
            echo "  ❌ {$path}: NULL\n";
        }
    }
    
    // Mostra estrutura completa (limitada)
    echo "\n📋 ESTRUTURA DO PAYLOAD:\n";
    echo "  Keys no nível raiz: " . implode(', ', array_keys($payload)) . "\n";
    
    if (isset($payload['message'])) {
        echo "  payload['message'] keys: " . implode(', ', array_keys($payload['message'])) . "\n";
        if (isset($payload['message']['key'])) {
            echo "  payload['message']['key'] keys: " . implode(', ', array_keys($payload['message']['key'])) . "\n";
        }
    }
    
    if (isset($payload['data'])) {
        echo "  payload['data'] keys: " . implode(', ', array_keys($payload['data'])) . "\n";
        if (isset($payload['data']['key'])) {
            echo "  payload['data']['key'] keys: " . implode(', ', array_keys($payload['data']['key'])) . "\n";
        }
    }
    
    if (isset($payload['raw'])) {
        echo "  payload['raw'] keys: " . implode(', ', array_keys($payload['raw'])) . "\n";
        if (isset($payload['raw']['payload'])) {
            echo "  payload['raw']['payload'] keys: " . implode(', ', array_keys($payload['raw']['payload'])) . "\n";
        }
    }
    
    // Simula extração usando lógica atual do ConversationService
    echo "\n🔍 SIMULANDO EXTRAÇÃO (ConversationService logic):\n";
    
    // Tenta extrair rawFrom usando a mesma lógica do código
    $rawFrom = null;
    
    // Tentativa 1: payload['from']
    if (isset($payload['from'])) {
        $rawFrom = $payload['from'];
        echo "  ✅ Extraído de payload['from']: {$rawFrom}\n";
    }
    
    // Tentativa 2: payload['message']['from']
    if (!$rawFrom && isset($payload['message']['from'])) {
        $rawFrom = $payload['message']['from'];
        echo "  ✅ Extraído de payload['message']['from']: {$rawFrom}\n";
    }
    
    // Tentativa 3: payload['message']['key']['remoteJid']
    if (!$rawFrom && isset($payload['message']['key']['remoteJid'])) {
        $rawFrom = $payload['message']['key']['remoteJid'];
        echo "  ✅ Extraído de payload['message']['key']['remoteJid']: {$rawFrom}\n";
    }
    
    // Tentativa 4: payload['message']['key']['participant'] (para grupos)
    if (!$rawFrom && isset($payload['message']['key']['participant'])) {
        $rawFrom = $payload['message']['key']['participant'];
        echo "  ✅ Extraído de payload['message']['key']['participant']: {$rawFrom}\n";
    }
    
    // Tentativa 5: payload['data']['from']
    if (!$rawFrom && isset($payload['data']['from'])) {
        $rawFrom = $payload['data']['from'];
        echo "  ✅ Extraído de payload['data']['from']: {$rawFrom}\n";
    }
    
    // Tentativa 6: payload['data']['key']['remoteJid']
    if (!$rawFrom && isset($payload['data']['key']['remoteJid'])) {
        $rawFrom = $payload['data']['key']['remoteJid'];
        echo "  ✅ Extraído de payload['data']['key']['remoteJid']: {$rawFrom}\n";
    }
    
    // Tentativa 7: payload['raw']['payload']['from']
    if (!$rawFrom && isset($payload['raw']['payload']['from'])) {
        $rawFrom = $payload['raw']['payload']['from'];
        echo "  ✅ Extraído de payload['raw']['payload']['from']: {$rawFrom}\n";
    }
    
    // Tentativa 8: payload['raw']['payload']['key']['remoteJid']
    if (!$rawFrom && isset($payload['raw']['payload']['key']['remoteJid'])) {
        $rawFrom = $payload['raw']['payload']['key']['remoteJid'];
        echo "  ✅ Extraído de payload['raw']['payload']['key']['remoteJid']: {$rawFrom}\n";
    }
    
    if (!$rawFrom) {
        echo "  ❌ NENHUM CAMINHO FUNCIONOU! Payload pode ter estrutura diferente.\n";
        echo "  📄 Payload completo (primeiros 1000 chars):\n";
        echo substr(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 1000) . "\n";
    } else {
        // Analisa formato
        echo "\n  📊 ANÁLISE DO FORMATO:\n";
        if (strpos($rawFrom, '@c.us') !== false) {
            $digits = preg_replace('/@.*$/', '', $rawFrom);
            echo "    Tipo: JID numérico (@c.us)\n";
            echo "    Dígitos extraídos: {$digits}\n";
            $normalized = \PixelHub\Services\PhoneNormalizer::toE164OrNull($digits);
            echo "    Normalizado E.164: " . ($normalized ?: 'FALHOU') . "\n";
        } elseif (strpos($rawFrom, '@s.whatsapp.net') !== false) {
            $digits = preg_replace('/@.*$/', '', $rawFrom);
            echo "    Tipo: JID numérico (@s.whatsapp.net)\n";
            echo "    Dígitos extraídos: {$digits}\n";
            $normalized = \PixelHub\Services\PhoneNormalizer::toE164OrNull($digits);
            echo "    Normalizado E.164: " . ($normalized ?: 'FALHOU') . "\n";
        } elseif (strpos($rawFrom, '@lid') !== false) {
            echo "    Tipo: Business ID (@lid)\n";
            echo "    Business ID: {$rawFrom}\n";
        } elseif (preg_match('/^[0-9]+$/', $rawFrom)) {
            echo "    Tipo: Apenas dígitos (sem sufixo)\n";
            echo "    Dígitos: {$rawFrom}\n";
            $normalized = \PixelHub\Services\PhoneNormalizer::toE164OrNull($rawFrom);
            echo "    Normalizado E.164: " . ($normalized ?: 'FALHOU') . "\n";
        } else {
            echo "    Tipo: Formato desconhecido\n";
            echo "    Valor: {$rawFrom}\n";
        }
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "=== FIM DO TESTE ===\n";

