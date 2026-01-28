<?php
/**
 * Unificação de Conversas Duplicadas
 * 
 * Este script unifica conversas duplicadas (mesmo contato com identificadores diferentes).
 * 
 * IMPORTANTE:
 * - Rode primeiro o diagnóstico: php scripts/diagnostico_conversas_duplicadas.php
 * - Este script FAZ ALTERAÇÕES no banco de dados
 * - Recomenda-se backup antes de executar
 * 
 * Uso: 
 *   php scripts/unificar_conversas_duplicadas.php --dry-run   (apenas simula)
 *   php scripts/unificar_conversas_duplicadas.php --execute   (executa de fato)
 *   php scripts/unificar_conversas_duplicadas.php --execute --tenant=36 (filtra por tenant)
 * 
 * @author PixelHub
 * @date 2026-01-28
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

// Parse argumentos
$dryRun = in_array('--dry-run', $argv);
$execute = in_array('--execute', $argv);
$tenantId = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--tenant=') === 0) {
        $tenantId = (int) substr($arg, 9);
    }
}

if (!$dryRun && !$execute) {
    echo "\n";
    echo "USO: php scripts/unificar_conversas_duplicadas.php [OPÇÃO]\n\n";
    echo "OPÇÕES:\n";
    echo "  --dry-run           Simula a unificação (não altera o banco)\n";
    echo "  --execute           Executa a unificação de fato\n";
    echo "  --tenant=ID         Filtra por tenant específico\n\n";
    echo "EXEMPLO:\n";
    echo "  php scripts/unificar_conversas_duplicadas.php --dry-run\n";
    echo "  php scripts/unificar_conversas_duplicadas.php --execute --tenant=36\n\n";
    exit(1);
}

echo "\n========================================\n";
echo "UNIFICAÇÃO DE CONVERSAS DUPLICADAS\n";
echo "========================================\n\n";

echo "MODO: " . ($dryRun ? "DRY-RUN (simulação)" : "EXECUÇÃO REAL") . "\n";
if ($tenantId) {
    echo "FILTRO: tenant_id = $tenantId\n";
}
echo "\n";

$db = DB::getConnection();

// Busca duplicatas: conversas com mesmo contact_name e tenant_id, uma com @lid e outra com telefone
$query = "
    SELECT 
        c1.id as id_lid,
        c1.contact_external_id as lid_id,
        c1.contact_name,
        c1.tenant_id,
        c1.message_count as msg_count_lid,
        c1.unread_count as unread_lid,
        c1.last_message_at as last_msg_lid,
        c1.status as status_lid,
        c1.created_at as created_lid,
        c2.id as id_phone,
        c2.contact_external_id as phone_id,
        c2.message_count as msg_count_phone,
        c2.unread_count as unread_phone,
        c2.last_message_at as last_msg_phone,
        c2.status as status_phone,
        c2.created_at as created_phone,
        t.name as tenant_name
    FROM conversations c1
    JOIN conversations c2 ON c1.contact_name = c2.contact_name 
        AND COALESCE(c1.tenant_id, 0) = COALESCE(c2.tenant_id, 0)
        AND c1.id != c2.id
        AND c1.channel_type = c2.channel_type
    LEFT JOIN tenants t ON c1.tenant_id = t.id
    WHERE c1.contact_name IS NOT NULL 
    AND c1.contact_name != ''
    AND c1.channel_type = 'whatsapp'
    AND c1.contact_external_id LIKE '%@lid'
    AND c2.contact_external_id NOT LIKE '%@lid'
    AND c2.contact_external_id REGEXP '^[0-9]+$'
    " . ($tenantId ? "AND c1.tenant_id = $tenantId" : "") . "
    ORDER BY c1.tenant_id, c1.contact_name
";

$stmt = $db->query($query);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "Nenhuma duplicata encontrada para unificar.\n\n";
    exit(0);
}

echo "Encontradas " . count($duplicates) . " duplicatas para unificar.\n\n";

$totalUnified = 0;
$totalErrors = 0;
$mappingsCreated = 0;

foreach ($duplicates as $idx => $dup) {
    $num = $idx + 1;
    echo "[$num/" . count($duplicates) . "] {$dup['contact_name']} (Tenant: " . ($dup['tenant_name'] ?? 'Não vinculado') . ")\n";
    echo "  @lid: #{$dup['id_lid']} ({$dup['lid_id']}) - {$dup['msg_count_lid']} msgs\n";
    echo "  Telefone: #{$dup['id_phone']} ({$dup['phone_id']}) - {$dup['msg_count_phone']} msgs\n";
    
    // Decisão: qual manter como canônico?
    // Prioridade: conversa com telefone (mais útil para exibição e identificação)
    $canonicalId = $dup['id_phone'];
    $mergeId = $dup['id_lid'];
    
    // Determina qual status manter (prioridade: active > open > new > archived > ignored)
    $statusPriority = ['active' => 5, 'open' => 4, 'new' => 3, 'archived' => 2, 'ignored' => 1];
    $finalStatus = ($statusPriority[$dup['status_phone']] ?? 0) >= ($statusPriority[$dup['status_lid']] ?? 0) 
        ? $dup['status_phone'] 
        : $dup['status_lid'];
    
    // Determina última mensagem (a mais recente entre as duas)
    $finalLastMsg = max($dup['last_msg_phone'], $dup['last_msg_lid']);
    
    // Soma contadores
    $finalMsgCount = $dup['msg_count_phone'] + $dup['msg_count_lid'];
    $finalUnread = $dup['unread_phone'] + $dup['unread_lid'];
    
    echo "  -> Mantendo #{$canonicalId} (telefone), mesclando #{$mergeId} (@lid)\n";
    echo "     Status final: {$finalStatus}, Total msgs: {$finalMsgCount}\n";
    
    if ($dryRun) {
        echo "  [DRY-RUN] Simulando unificação...\n\n";
        $totalUnified++;
        continue;
    }
    
    // EXECUÇÃO REAL
    try {
        $db->beginTransaction();
        
        // 1. Criar mapeamento @lid -> telefone se não existir
        $lidId = $dup['lid_id'];
        $phoneNumber = $dup['phone_id'];
        
        $checkMapping = $db->prepare("SELECT id FROM whatsapp_business_ids WHERE business_id = ?");
        $checkMapping->execute([$lidId]);
        
        if (!$checkMapping->fetch()) {
            $insertMapping = $db->prepare("
                INSERT INTO whatsapp_business_ids (business_id, phone_number, tenant_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $insertMapping->execute([$lidId, $phoneNumber, $dup['tenant_id']]);
            echo "  [OK] Mapeamento criado: {$lidId} -> {$phoneNumber}\n";
            $mappingsCreated++;
        }
        
        // 2. Atualizar eventos da conversa @lid para apontar para a canônica
        // NOTA: communication_events usa conversation_id (se existir) para vincular
        $updateEvents = $db->prepare("
            UPDATE communication_events 
            SET conversation_id = ?
            WHERE conversation_id = ?
        ");
        $affectedEvents = 0;
        if ($updateEvents->execute([$canonicalId, $mergeId])) {
            $affectedEvents = $updateEvents->rowCount();
        }
        echo "  [OK] {$affectedEvents} eventos migrados\n";
        
        // 3. Atualizar a conversa canônica com totais consolidados
        $updateCanonical = $db->prepare("
            UPDATE conversations 
            SET 
                message_count = ?,
                unread_count = ?,
                last_message_at = ?,
                status = CASE 
                    WHEN status = 'ignored' THEN 'ignored'
                    ELSE ?
                END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateCanonical->execute([
            $finalMsgCount,
            $finalUnread,
            $finalLastMsg,
            $finalStatus,
            $canonicalId
        ]);
        
        // 4. Deletar (soft) a conversa duplicada (@lid)
        // Em vez de deletar, marcamos como archived com um prefixo no conversation_key
        $archiveDuplicate = $db->prepare("
            UPDATE conversations 
            SET 
                status = 'archived',
                conversation_key = CONCAT('MERGED_', id, '_', conversation_key),
                contact_name = CONCAT('[UNIFICADA] ', COALESCE(contact_name, '')),
                updated_at = NOW()
            WHERE id = ?
        ");
        $archiveDuplicate->execute([$mergeId]);
        echo "  [OK] Conversa #{$mergeId} marcada como unificada (arquivada)\n";
        
        $db->commit();
        $totalUnified++;
        echo "  [SUCESSO] Unificação concluída!\n\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "  [ERRO] " . $e->getMessage() . "\n\n";
        $totalErrors++;
    }
}

// Resumo final
echo "========================================\n";
echo "RESUMO\n";
echo "========================================\n\n";
echo "Total de duplicatas processadas: " . count($duplicates) . "\n";
echo "Unificações " . ($dryRun ? "simuladas" : "realizadas") . ": {$totalUnified}\n";
if (!$dryRun) {
    echo "Mapeamentos criados: {$mappingsCreated}\n";
    echo "Erros: {$totalErrors}\n";
}
echo "\n";

if ($dryRun) {
    echo "Para executar de fato, rode:\n";
    echo "  php scripts/unificar_conversas_duplicadas.php --execute\n\n";
}

echo "========================================\n";
echo "FIM\n";
echo "========================================\n\n";
