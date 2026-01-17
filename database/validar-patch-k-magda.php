<?php
/**
 * Validação PATCH K - Magda (5511940863773)
 * 
 * Valida se o PATCH K resolveu a mistura de histórico e rótulos incorretos
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

echo "=== VALIDAÇÃO PATCH K - MAGDA (5511940863773) ===\n\n";

$db = DB::getConnection();
$contactPhone = '5511940863773';
$contactVariations = ['5511940863773', '+5511940863773'];
$sessionId = 'pixel12digital';
$tenantId = 121;

// ==========================================
// A) Conferir a conversa no banco
// ==========================================

echo "A) CONVERSA NO BANCO (fonte da verdade do painel):\n";
echo str_repeat("-", 80) . "\n";

$placeholders = str_repeat('?,', count($contactVariations) - 1) . '?';
$stmt = $db->prepare("
    SELECT id, conversation_key, tenant_id, channel_id, contact_external_id, created_at, updated_at
    FROM conversations
    WHERE contact_external_id IN ({$placeholders})
    ORDER BY id DESC
");
$stmt->execute($contactVariations);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada para {$contactPhone}\n";
    echo "   Isso pode indicar que a conversa ainda não foi criada.\n\n";
} else {
    foreach ($conversations as $idx => $conv) {
        $isCorrect = ($conv['tenant_id'] == $tenantId && $conv['channel_id'] == $sessionId);
        $status = $isCorrect ? '✅' : '❌';
        
        echo sprintf(
            "%s [%d] ID=%d | key=%s | tenant_id=%s | channel_id=%s | contact=%s | created=%s\n",
            $status,
            $idx + 1,
            $conv['id'],
            $conv['conversation_key'] ?: 'NULL',
            $conv['tenant_id'] ?: 'NULL',
            $conv['channel_id'] ?: 'NULL',
            $conv['contact_external_id'],
            $conv['created_at']
        );
        
        if (!$isCorrect) {
            echo "   ⚠️  ATENÇÃO: Esperado tenant_id={$tenantId} e channel_id='{$sessionId}'\n";
        }
    }
}

echo "\n";

// ==========================================
// B) Conferir eventos com channel_id "errado"
// ==========================================

echo "B) EVENTOS DO CONTATO - VERIFICAR channel_id:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) AS payload_session,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.sessionId')) AS payload_sessionId,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channelId')) AS payload_channelId,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND (
         JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
         OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
      )
    ORDER BY ce.created_at DESC
    LIMIT 200
");
$searchPattern = "%{$contactPhone}%";
$stmt->execute([$searchPattern, $searchPattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado para {$contactPhone}\n\n";
} else {
    echo "Total de eventos encontrados: " . count($events) . "\n\n";
    
    // Analisa channel_id de cada evento
    $wrongChannels = [];
    $correctChannels = [];
    $noChannel = [];
    
    foreach ($events as $event) {
        $channels = array_filter([
            $event['meta_channel'] ?: null,
            $event['payload_session'] ?: null,
            $event['payload_sessionId'] ?: null,
            $event['payload_channelId'] ?: null
        ]);
        
        $hasCorrectChannel = false;
        $hasWrongChannel = false;
        $wrongChannelValue = null;
        
        foreach ($channels as $channel) {
            $channelNormalized = strtolower(trim(str_replace(' ', '', $channel)));
            $sessionNormalized = strtolower(trim(str_replace(' ', '', $sessionId)));
            
            if ($channelNormalized === $sessionNormalized) {
                $hasCorrectChannel = true;
            } elseif (!empty($channel) && $channelNormalized !== $sessionNormalized) {
                $hasWrongChannel = true;
                $wrongChannelValue = $channel;
            }
        }
        
        if ($hasWrongChannel && !$hasCorrectChannel) {
            $wrongChannels[] = [
                'id' => $event['id'],
                'created_at' => $event['created_at'],
                'tenant_id' => $event['tenant_id'],
                'wrong_channel' => $wrongChannelValue,
                'meta_channel' => $event['meta_channel'],
                'payload_session' => $event['payload_session'],
                'from' => substr($event['p_from'] ?: 'N/A', 0, 30)
            ];
        } elseif ($hasCorrectChannel || (!empty($channels) && !$hasWrongChannel)) {
            $correctChannels[] = $event['id'];
        } else {
            $noChannel[] = $event['id'];
        }
    }
    
    echo "   Eventos com channel_id CORRETO (pixel12digital): " . count($correctChannels) . "\n";
    echo "   Eventos com channel_id ERRADO (outro valor): " . count($wrongChannels) . "\n";
    echo "   Eventos SEM channel_id: " . count($noChannel) . "\n\n";
    
    if (!empty($wrongChannels)) {
        echo "   ⚠️  ATENÇÃO: Eventos com channel_id incorreto:\n\n";
        foreach (array_slice($wrongChannels, 0, 10) as $wrong) {
            echo sprintf(
                "      ID=%d | created_at=%s | tenant_id=%s | wrong_channel='%s' | meta_channel='%s' | payload_session='%s' | from=%s\n",
                $wrong['id'],
                $wrong['created_at'],
                $wrong['tenant_id'] ?: 'NULL',
                $wrong['wrong_channel'] ?: 'NULL',
                $wrong['meta_channel'] ?: 'NULL',
                $wrong['payload_session'] ?: 'NULL',
                $wrong['from']
            );
        }
        if (count($wrongChannels) > 10) {
            echo "      ... e mais " . (count($wrongChannels) - 10) . " evento(s)\n";
        }
        echo "\n";
        echo "   ❌ PROBLEMA: Existem eventos com channel_id incorreto!\n";
        echo "      Isso pode causar rótulo \"IMOBSITES\" aparecer na conversa.\n";
        echo "      Verificar extração de sessionId no WhatsAppWebhookController.\n\n";
    } else {
        echo "   ✅ PASS: Todos os eventos têm channel_id correto ou não têm (será filtrado pelo PATCH K)\n\n";
    }
}

// ==========================================
// C) Checar se ainda existem órfãos
// ==========================================

echo "C) EVENTOS ÓRFÃOS (tenant_id=NULL) após PATCH J:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT COUNT(*) AS orfaos
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
      AND ce.tenant_id IS NULL
      AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
        OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
        OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
        OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
      )
");
$stmt->execute([$sessionId, $sessionId, $sessionId, $sessionId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$orphans = (int) ($result['orfaos'] ?? 0);

if ($orphans === 0) {
    echo "✅ PASS: {$orphans} eventos órfãos encontrados (esperado: 0)\n";
    echo "   PATCH J funcionou corretamente.\n\n";
} else {
    echo "❌ FAIL: {$orphans} eventos órfãos ainda existem (esperado: 0)\n";
    echo "   Re-executar PATCH J pode ser necessário.\n\n";
}

// ==========================================
// D) RESUMO FINAL
// ==========================================

echo str_repeat("=", 80) . "\n";
echo "RESUMO DA VALIDAÇÃO:\n";
echo str_repeat("=", 80) . "\n\n";

$allPass = true;

// Check A
$convCorrect = false;
if (!empty($conversations)) {
    foreach ($conversations as $conv) {
        if ($conv['tenant_id'] == $tenantId && $conv['channel_id'] == $sessionId) {
            $convCorrect = true;
            break;
        }
    }
}

if ($convCorrect) {
    echo "✅ A) Conversa no banco está correta (tenant_id={$tenantId}, channel_id='{$sessionId}')\n";
} else {
    echo "❌ A) Conversa no banco NÃO está correta\n";
    $allPass = false;
}

// Check B
if (empty($wrongChannels)) {
    echo "✅ B) Todos os eventos têm channel_id correto ou não têm\n";
} else {
    echo "❌ B) Encontrados " . count($wrongChannels) . " evento(s) com channel_id incorreto\n";
    $allPass = false;
}

// Check C
if ($orphans === 0) {
    echo "✅ C) Nenhum evento órfão encontrado (PATCH J funcionou)\n";
} else {
    echo "❌ C) Ainda existem {$orphans} evento(s) órfão(s)\n";
    $allPass = false;
}

echo "\n";

if ($allPass) {
    echo "✅ VALIDAÇÃO COMPLETA: PATCH K deve resolver o problema de mistura de histórico.\n";
} else {
    echo "⚠️  VALIDAÇÃO FALHOU: Problemas encontrados que podem causar mistura de histórico.\n";
    echo "   Se o problema persistir após PATCH K, investigar extração de sessionId no webhook.\n";
}

echo "\n";

