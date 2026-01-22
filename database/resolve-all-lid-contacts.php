<?php
/**
 * Script para resolver todos os contatos LID que podem ser resolvidos
 * 
 * Busca todas as conversas com @lid e tenta extrair o número real dos eventos,
 * criando mapeamentos automáticos quando encontrados.
 * 
 * Uso: php database/resolve-all-lid-contacts.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/ContactHelper.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\ContactHelper;

Env::load();
$db = DB::getConnection();

echo "=== RESOLUÇÃO DE TODOS OS CONTATOS LID ===\n\n";

// 1. Busca todas as conversas com @lid que ainda não têm mapeamento
echo "1. Buscando conversas com @lid sem mapeamento...\n";

$stmt = $db->query("
    SELECT DISTINCT
        c.id,
        c.contact_external_id,
        c.channel_id,
        c.tenant_id,
        c.last_message_at
    FROM conversations c
    WHERE c.contact_external_id LIKE '%@lid'
    AND c.channel_type = 'whatsapp'
    ORDER BY c.last_message_at DESC
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($conversations);
echo "   Encontradas {$total} conversas com @lid\n\n";

if ($total === 0) {
    echo "Nenhuma conversa para processar.\n";
    exit(0);
}

// 2. Processa cada conversa
echo "2. Processando conversas...\n\n";

$resolved = 0;
$alreadyMapped = 0;
$notFound = 0;
$errors = 0;

foreach ($conversations as $idx => $conv) {
    $contactId = $conv['contact_external_id'];
    $sessionId = $conv['channel_id'];
    $conversationId = $conv['id'];
    
    echo sprintf("[%d/%d] Processando: %s (Conversation ID: %d)\n", 
        $idx + 1, 
        $total, 
        $contactId,
        $conversationId
    );
    
    // Verifica se já existe mapeamento
    $lidId = str_replace('@lid', '', $contactId);
    $lidBusinessId = $lidId . '@lid';
    
    $checkStmt = $db->prepare("
        SELECT phone_number 
        FROM whatsapp_business_ids 
        WHERE business_id = ?
        LIMIT 1
    ");
    $checkStmt->execute([$lidBusinessId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing && !empty($existing['phone_number'])) {
        echo "   ✓ Já possui mapeamento: {$existing['phone_number']}\n";
        $alreadyMapped++;
        echo "\n";
        continue;
    }
    
    // Tenta resolver usando o ContactHelper (que busca nos eventos)
    try {
        $resolvedPhone = ContactHelper::resolveLidPhone($contactId, $sessionId);
        
        if (!empty($resolvedPhone)) {
            echo "   ✓ Resolvido: {$resolvedPhone}\n";
            
            // Garante que o mapeamento foi criado
            try {
                $insertStmt = $db->prepare("
                    INSERT IGNORE INTO whatsapp_business_ids (business_id, phone_number, tenant_id)
                    VALUES (?, ?, ?)
                ");
                $insertStmt->execute([
                    $lidBusinessId,
                    $resolvedPhone,
                    $conv['tenant_id'] ?: null
                ]);
                
                // Também atualiza o cache se tiver sessionId
                if (!empty($sessionId)) {
                    $cacheStmt = $db->prepare("
                        INSERT INTO wa_pnlid_cache (provider, session_id, pnlid, phone_e164)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE phone_e164=VALUES(phone_e164), updated_at=NOW()
                    ");
                    $cacheStmt->execute(['wpp_gateway', $sessionId, $lidId, $resolvedPhone]);
                }
                
                echo "   ✓ Mapeamento criado com sucesso\n";
                $resolved++;
            } catch (\Exception $e) {
                echo "   ⚠ Erro ao criar mapeamento: " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            echo "   ✗ Não foi possível resolver (número não encontrado nos eventos)\n";
            $notFound++;
        }
    } catch (\Exception $e) {
        echo "   ✗ Erro ao processar: " . $e->getMessage() . "\n";
        $errors++;
    }
    
    echo "\n";
    
    // Adiciona pequeno delay para não sobrecarregar o banco
    if (($idx + 1) % 10 === 0) {
        usleep(100000); // 0.1 segundo a cada 10 conversas
    }
}

// 3. Resumo
echo "\n=== RESUMO ===\n";
echo "Total processado: {$total}\n";
echo "✓ Resolvidos agora: {$resolved}\n";
echo "✓ Já tinham mapeamento: {$alreadyMapped}\n";
echo "✗ Não encontrados: {$notFound}\n";
if ($errors > 0) {
    echo "⚠ Erros: {$errors}\n";
}

echo "\n=== FIM ===\n";

