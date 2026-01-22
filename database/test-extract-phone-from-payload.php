<?php
/**
 * Script de teste para extrair número do payload
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/ContactHelper.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\ContactHelper;

Env::load();
$db = DB::getConnection();

echo "=== TESTE: Extração de Número do Payload ===\n\n";

// Busca o evento da conversa 80
$eventId = 'b22f0721-1055-45c9-b0be-0b28444886fc';
$stmt = $db->prepare("SELECT payload FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Evento não encontrado!\n");
}

echo "1. Extraindo número do payload...\n";
$phone = ContactHelper::extractPhoneFromPayload($event['payload']);
echo "   Número encontrado: " . ($phone ?? 'NULL') . "\n\n";

if ($phone) {
    echo "2. Testando formatação...\n";
    $formatted = ContactHelper::formatContactId('56083800395891@lid', $phone);
    echo "   Formatado: {$formatted}\n\n";
    
    echo "3. Testando resolução LID...\n";
    $resolved = ContactHelper::resolveLidPhone('56083800395891@lid', 'ImobSites');
    echo "   Resolvido: " . ($resolved ?? 'NULL') . "\n\n";
}

echo "=== FIM DO TESTE ===\n";

