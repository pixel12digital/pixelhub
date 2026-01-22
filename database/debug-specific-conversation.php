<?php
/**
 * Debug específico para a conversa que não está sendo formatada
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/ContactHelper.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\ContactHelper;

Env::load();
$db = DB::getConnection();

$contactId = '187982447419485@lid';
$sessionId = 'imobsites';

echo "=== DEBUG: Conversa Específica ===\n\n";
echo "Contact ID: {$contactId}\n";
echo "Session ID: {$sessionId}\n\n";

// 1. Verifica se tem mapeamento
$lidId = str_replace('@lid', '', $contactId);
$lidBusinessId = $lidId . '@lid';

echo "1. Verificando mapeamento...\n";
$stmt = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ?");
$stmt->execute([$lidBusinessId]);
$mapping = $stmt->fetch(PDO::FETCH_ASSOC);

if ($mapping) {
    echo "   ✓ Mapeamento encontrado: {$mapping['phone_number']}\n";
} else {
    echo "   ✗ Sem mapeamento\n";
}

// 2. Testa resolveLidPhone
echo "\n2. Testando resolveLidPhone()...\n";
$resolved = ContactHelper::resolveLidPhone($contactId, $sessionId);
echo "   Resultado: " . ($resolved ?? 'NULL') . "\n";

// 3. Testa resolveLidPhonesBatch (como é usado na listagem)
echo "\n3. Testando resolveLidPhonesBatch() (como usado na listagem)...\n";
$batchData = [
    [
        'contactId' => $contactId,
        'sessionId' => $sessionId
    ]
];
$batchResult = ContactHelper::resolveLidPhonesBatch($batchData);
echo "   Resultado do batch:\n";
foreach ($batchResult as $key => $value) {
    echo "     [{$key}] => {$value}\n";
}

// 4. Verifica como seria usado na listagem
echo "\n4. Simulando uso na listagem...\n";
$lidIdForMap = str_replace('@lid', '', $contactId);
$realPhone = $batchResult[$lidIdForMap] ?? null;
echo "   lidId usado no mapa: {$lidIdForMap}\n";
echo "   realPhone encontrado: " . ($realPhone ?? 'NULL') . "\n";

// 5. Testa formatação
echo "\n5. Testando formatação...\n";
$formatted = ContactHelper::formatContactId($contactId, $realPhone);
echo "   Formatado: {$formatted}\n";

// 6. Verifica se há eventos com esse contato
echo "\n6. Verificando eventos...\n";
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
    )
    LIMIT 5
");
$pattern = "%{$contactId}%";
$stmt->execute([$pattern, $pattern]);
$eventCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Eventos encontrados: {$eventCount['total']}\n";

if ($eventCount['total'] > 0) {
    echo "\n7. Tentando extrair número dos eventos...\n";
    $stmt = $db->prepare("
        SELECT payload
        FROM communication_events
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.to') LIKE ?
        )
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$pattern, $pattern]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $idx => $event) {
        $phone = ContactHelper::extractPhoneFromPayload($event['payload']);
        echo "   Evento #" . ($idx + 1) . ": " . ($phone ?? 'NULL') . "\n";
    }
}

echo "\n=== FIM ===\n";

