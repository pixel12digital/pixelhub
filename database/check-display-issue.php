<?php
/**
 * Script para verificar por que alguns números não estão sendo exibidos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/ContactHelper.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\ContactHelper;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE EXIBIÇÃO ===\n\n";

// Busca algumas conversas específicas que o usuário mencionou
$testIds = [
    '56083800395891@lid', // Conversa 80 - deveria mostrar (94) 8119-7615
    '118554015846500@lid', // Conversa 79
    '19563777405017@lid',  // Conversa 78
    '85775530107049@lid',  // Conversa 20 e 82 - deveria mostrar (18) 98145-0208
];

foreach ($testIds as $contactId) {
    echo "Verificando: {$contactId}\n";
    
    // 1. Verifica se tem mapeamento
    $lidId = str_replace('@lid', '', $contactId);
    $lidBusinessId = $lidId . '@lid';
    
    $stmt = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ?");
    $stmt->execute([$lidBusinessId]);
    $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mapping) {
        echo "  ✓ Mapeamento encontrado: {$mapping['phone_number']}\n";
        
        // 2. Testa resolução
        $resolved = ContactHelper::resolveLidPhone($contactId, 'imobsites');
        echo "  Resolvido via ContactHelper: " . ($resolved ?? 'NULL') . "\n";
        
        // 3. Testa formatação
        $formatted = ContactHelper::formatContactId($contactId, $resolved);
        echo "  Formatado: {$formatted}\n";
        
        // 4. Verifica se está correto
        if (strpos($formatted, 'ID WhatsApp:') !== false) {
            echo "  ✗ PROBLEMA: Ainda mostra ID WhatsApp ao invés de número!\n";
        } else {
            echo "  ✓ OK: Mostrando número formatado\n";
        }
    } else {
        echo "  ✗ Sem mapeamento\n";
        
        // Tenta resolver dos eventos
        $resolved = ContactHelper::resolveLidPhone($contactId, 'imobsites');
        if ($resolved) {
            echo "  ✓ Resolvido dos eventos: {$resolved}\n";
            $formatted = ContactHelper::formatContactId($contactId, $resolved);
            echo "  Formatado: {$formatted}\n";
        } else {
            echo "  ✗ Não foi possível resolver\n";
        }
    }
    echo "\n";
}

// Testa o resolveLidPhonesBatch que é usado na listagem
echo "=== TESTE: resolveLidPhonesBatch ===\n\n";

$batchData = [];
foreach ($testIds as $contactId) {
    $batchData[] = [
        'contactId' => $contactId,
        'sessionId' => 'imobsites'
    ];
}

$batchResult = ContactHelper::resolveLidPhonesBatch($batchData);

echo "Resultado do batch:\n";
foreach ($batchResult as $lidId => $phone) {
    echo "  {$lidId}@lid → {$phone}\n";
}

echo "\n=== FIM ===\n";

