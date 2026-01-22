<?php
/**
 * Script para verificar se os números resolvidos estão sendo exibidos corretamente
 * 
 * Uso: php database/verify-resolved-contacts-display.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/ContactHelper.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\ContactHelper;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO: Exibição de Números Resolvidos ===\n\n";

// Busca conversas que têm mapeamento
$stmt = $db->query("
    SELECT DISTINCT
        c.id as conversation_id,
        c.contact_external_id,
        c.channel_id,
        c.tenant_id,
        wbi.phone_number as mapped_phone
    FROM conversations c
    INNER JOIN whatsapp_business_ids wbi ON c.contact_external_id = wbi.business_id
    WHERE c.contact_external_id LIKE '%@lid'
    AND c.channel_type = 'whatsapp'
    ORDER BY c.last_message_at DESC
    LIMIT 25
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($conversations);
echo "Encontradas {$total} conversas com mapeamento\n\n";

if ($total === 0) {
    echo "Nenhuma conversa com mapeamento encontrada.\n";
    exit(0);
}

$correctlyFormatted = 0;
$incorrectlyFormatted = 0;

echo "Verificando formatação...\n\n";

foreach ($conversations as $conv) {
    $contactId = $conv['contact_external_id'];
    $sessionId = $conv['channel_id'];
    $mappedPhone = $conv['mapped_phone'];
    
    // Simula o que o sistema faz: resolve o LID e formata
    $resolvedPhone = ContactHelper::resolveLidPhone($contactId, $sessionId);
    $formatted = ContactHelper::formatContactId($contactId, $resolvedPhone);
    
    // Verifica se o formato está correto (não deve ser "ID WhatsApp: ...")
    $isCorrect = strpos($formatted, 'ID WhatsApp:') === false;
    
    if ($isCorrect) {
        echo sprintf("✓ Conversation ID %d: %s → %s\n", 
            $conv['conversation_id'],
            $contactId,
            $formatted
        );
        $correctlyFormatted++;
    } else {
        echo sprintf("✗ Conversation ID %d: %s → %s (NÚMERO MAPEADO: %s)\n", 
            $conv['conversation_id'],
            $contactId,
            $formatted,
            $mappedPhone
        );
        $incorrectlyFormatted++;
    }
}

echo "\n=== RESUMO ===\n";
echo "Total verificadas: {$total}\n";
echo "✓ Formatadas corretamente: {$correctlyFormatted}\n";
if ($incorrectlyFormatted > 0) {
    echo "✗ Com problemas: {$incorrectlyFormatted}\n";
}

echo "\n=== FIM ===\n";

