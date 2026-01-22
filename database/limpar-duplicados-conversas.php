<?php

/**
 * Script de Limpeza: Resolve duplicados existentes na tabela conversations
 * 
 * Estratégia:
 * 1. Identifica todos os pares duplicados por remote_key
 * 2. Para cada par, escolhe conversa canônica (critério: thread_key completo, mais recente, mais mensagens)
 * 3. Migra dados se necessário
 * 4. Marca duplicada como deletada (soft delete) ou deleta se não houver referências
 */

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

echo "=== SCRIPT DE LIMPEZA: Duplicados de Conversas ===\n\n";

// Modo dry-run (apenas simula, não executa)
$dryRun = isset($argv[1]) && $argv[1] === '--dry-run';
if ($dryRun) {
    echo "⚠️  MODO DRY-RUN: Nenhuma alteração será feita\n\n";
}

try {
    // 1. Identificar duplicados
    echo "1) Identificando duplicados por remote_key...\n";
    
    // Primeiro: duplicados exatos por remote_key
    $duplicates = $db->query("
        SELECT 
            channel_type,
            remote_key,
            COUNT(*) as count,
            GROUP_CONCAT(id ORDER BY id) as conversation_ids
        FROM conversations
        WHERE remote_key IS NOT NULL 
        AND remote_key != ''
        GROUP BY channel_type, remote_key
        HAVING count > 1
        ORDER BY count DESC, channel_type, remote_key
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Segundo: detectar casos onde lid:xxx e tel:xxx representam o mesmo número
    echo "2) Identificando duplicados por número (lid vs tel)...\n";
    
    $lidTelDuplicates = $db->query("
        SELECT 
            c1.id as id1,
            c1.remote_key as remote_key1,
            c1.tenant_id as tenant_id1,
            c2.id as id2,
            c2.remote_key as remote_key2,
            c2.tenant_id as tenant_id2,
            CASE 
                WHEN c1.remote_key LIKE 'lid:%' THEN REPLACE(c1.remote_key, 'lid:', '')
                WHEN c2.remote_key LIKE 'lid:%' THEN REPLACE(c2.remote_key, 'lid:', '')
            END as number
        FROM conversations c1
        INNER JOIN conversations c2 ON (
            c1.channel_type = c2.channel_type
            AND c1.id < c2.id
            AND (
                (c1.remote_key LIKE 'lid:%' AND c2.remote_key LIKE 'tel:%' AND REPLACE(c1.remote_key, 'lid:', '') = REPLACE(c2.remote_key, 'tel:', ''))
                OR
                (c1.remote_key LIKE 'tel:%' AND c2.remote_key LIKE 'lid:%' AND REPLACE(c1.remote_key, 'tel:', '') = REPLACE(c2.remote_key, 'lid:', ''))
            )
        )
        WHERE c1.remote_key IS NOT NULL 
        AND c1.remote_key != ''
        AND c2.remote_key IS NOT NULL 
        AND c2.remote_key != ''
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Converte lid/tel duplicates para o mesmo formato
    foreach ($lidTelDuplicates as $dup) {
        $duplicates[] = [
            'channel_type' => 'whatsapp', // Assumindo WhatsApp para esses casos
            'remote_key' => 'lid:' . $dup['number'] . '|tel:' . $dup['number'], // Marca especial
            'count' => 2,
            'conversation_ids' => $dup['id1'] . ',' . $dup['id2'],
            'is_lid_tel_duplicate' => true,
            'number' => $dup['number']
        ];
    }
    
    if (!empty($lidTelDuplicates)) {
        echo "   ⚠️  Encontrados " . count($lidTelDuplicates) . " pares lid/tel duplicados\n";
    }
    
    if (empty($duplicates)) {
        echo "   ✅ Nenhum duplicado encontrado!\n\n";
        exit(0);
    }
    
    echo "   ⚠️  Encontrados " . count($duplicates) . " grupos de duplicados\n\n";
    
    // 2. Processar cada grupo de duplicados
    $processed = 0;
    $merged = 0;
    $errors = [];
    
    foreach ($duplicates as $group) {
        $channelType = $group['channel_type'];
        $remoteKey = $group['remote_key'];
        $count = (int)$group['count'];
        $conversationIds = explode(',', $group['conversation_ids']);
        $isLidTelDuplicate = isset($group['is_lid_tel_duplicate']) && $group['is_lid_tel_duplicate'];
        
        if ($isLidTelDuplicate) {
            echo "   Processando: lid/tel duplicado, number={$group['number']}, count={$count}\n";
        } else {
            echo "   Processando: channel_type={$channelType}, remote_key={$remoteKey}, count={$count}\n";
        }
        echo "      IDs: " . implode(', ', $conversationIds) . "\n";
        
        // Busca detalhes de todas as conversas do grupo
        $placeholders = str_repeat('?,', count($conversationIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT 
                id,
                conversation_key,
                contact_external_id,
                remote_key,
                thread_key,
                contact_key,
                tenant_id,
                is_incoming_lead,
                message_count,
                last_message_at,
                created_at,
                updated_at
            FROM conversations
            WHERE id IN ($placeholders)
            ORDER BY 
                CASE WHEN thread_key IS NOT NULL AND thread_key != '' THEN 0 ELSE 1 END,
                message_count DESC,
                last_message_at DESC,
                created_at ASC
        ");
        $stmt->execute($conversationIds);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($conversations) < 2) {
            echo "      ⚠️  Menos de 2 conversas encontradas, pulando...\n\n";
            continue;
        }
        
        // Escolhe conversa canônica (primeira da ordenação)
        $canonical = $conversations[0];
        $duplicatesToMerge = array_slice($conversations, 1);
        
        // Para lid/tel duplicates, prioriza a que tem thread_key completo
        if ($isLidTelDuplicate) {
            foreach ($conversations as $conv) {
                if ($conv['thread_key'] && $conv['thread_key'] !== '') {
                    $canonical = $conv;
                    $duplicatesToMerge = array_filter($conversations, function($c) use ($conv) {
                        return $c['id'] !== $conv['id'];
                    });
                    break;
                }
            }
        }
        
        echo "      ✅ Conversa canônica escolhida: ID={$canonical['id']}\n";
        echo "         - remote_key: {$canonical['remote_key']}\n";
        echo "         - thread_key: " . ($canonical['thread_key'] ?: 'NULL') . "\n";
        echo "         - message_count: {$canonical['message_count']}\n";
        echo "         - last_message_at: {$canonical['last_message_at']}\n";
        echo "         - tenant_id: " . ($canonical['tenant_id'] ?: 'NULL') . "\n";
        echo "      📋 Conversas a mesclar: " . count($duplicatesToMerge) . "\n";
        
        foreach ($duplicatesToMerge as $dup) {
            echo "         - ID={$dup['id']}, remote_key={$dup['remote_key']}, tenant_id=" . ($dup['tenant_id'] ?: 'NULL') . ", messages={$dup['message_count']}\n";
        }
        
        // 3. Mesclar dados das duplicadas na canônica
        if (!$dryRun) {
            $db->beginTransaction();
            
            try {
                // Atualiza conversa canônica com dados mais recentes/completos
                $updateFields = [];
                $updateValues = [];
                
                // Se canônica não tem tenant_id mas duplicada tem, atualiza
                foreach ($duplicatesToMerge as $dup) {
                    if (!$canonical['tenant_id'] && $dup['tenant_id']) {
                        $updateFields[] = "tenant_id = ?";
                        $updateValues[] = $dup['tenant_id'];
                        echo "      🔄 Atualizando tenant_id da canônica: NULL -> {$dup['tenant_id']}\n";
                        break; // Usa primeiro tenant_id encontrado
                    }
                }
                
                // Se canônica não tem thread_key mas duplicada tem, atualiza
                foreach ($duplicatesToMerge as $dup) {
                    if ((!$canonical['thread_key'] || $canonical['thread_key'] === '') && $dup['thread_key']) {
                        $updateFields[] = "thread_key = ?";
                        $updateValues[] = $dup['thread_key'];
                        $updateFields[] = "contact_key = ?";
                        $updateValues[] = $dup['contact_key'] ?: null;
                        echo "      🔄 Atualizando thread_key da canônica\n";
                        break;
                    }
                }
                
                // Para lid/tel duplicates, atualiza remote_key da canônica para usar lid: (mais específico)
                if ($isLidTelDuplicate) {
                    $lidRemoteKey = 'lid:' . $group['number'];
                    if ($canonical['remote_key'] !== $lidRemoteKey) {
                        $updateFields[] = "remote_key = ?";
                        $updateValues[] = $lidRemoteKey;
                        echo "      🔄 Atualizando remote_key da canônica para: {$lidRemoteKey}\n";
                    }
                }
                
                // Atualiza last_message_at se duplicada for mais recente
                foreach ($duplicatesToMerge as $dup) {
                    if ($dup['last_message_at'] && (!$canonical['last_message_at'] || $dup['last_message_at'] > $canonical['last_message_at'])) {
                        $updateFields[] = "last_message_at = ?";
                        $updateValues[] = $dup['last_message_at'];
                        echo "      🔄 Atualizando last_message_at da canônica: {$canonical['last_message_at']} -> {$dup['last_message_at']}\n";
                        break;
                    }
                }
                
                // Atualiza message_count (soma total)
                $totalMessages = $canonical['message_count'];
                foreach ($duplicatesToMerge as $dup) {
                    $totalMessages += $dup['message_count'];
                }
                if ($totalMessages > $canonical['message_count']) {
                    $updateFields[] = "message_count = ?";
                    $updateValues[] = $totalMessages;
                    echo "      🔄 Atualizando message_count da canônica: {$canonical['message_count']} -> {$totalMessages}\n";
                }
                
                // Executa update se houver campos para atualizar
                if (!empty($updateFields)) {
                    $updateValues[] = $canonical['id'];
                    $updateSql = "UPDATE conversations SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
                    $updateStmt = $db->prepare($updateSql);
                    $updateStmt->execute($updateValues);
                }
                
                // 4. Deleta duplicadas (hard delete - não há soft delete na tabela)
                $deleteIds = array_column($duplicatesToMerge, 'id');
                $deletePlaceholders = str_repeat('?,', count($deleteIds) - 1) . '?';
                $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id IN ($deletePlaceholders)");
                $deleteStmt->execute($deleteIds);
                
                $deletedCount = $deleteStmt->rowCount();
                echo "      ✅ {$deletedCount} conversa(s) duplicada(s) deletada(s)\n";
                
                $db->commit();
                $merged++;
                $processed++;
                
            } catch (\Exception $e) {
                $db->rollBack();
                $errors[] = "Erro ao processar remote_key={$remoteKey}: " . $e->getMessage();
                echo "      ❌ Erro: " . $e->getMessage() . "\n";
            }
        } else {
            echo "      [DRY-RUN] Simulando merge...\n";
            $processed++;
        }
        
        echo "\n";
    }
    
    // 5. Resumo
    echo "\n=== RESUMO ===\n";
    echo "Grupos processados: {$processed}\n";
    if (!$dryRun) {
        echo "Grupos mesclados: {$merged}\n";
        if (!empty($errors)) {
            echo "Erros: " . count($errors) . "\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
        }
    } else {
        echo "[DRY-RUN] Nenhuma alteração foi feita\n";
    }
    
    // 6. Verificar se ainda há duplicados
    echo "\n6) Verificando se ainda há duplicados...\n";
    $remaining = $db->query("
        SELECT COUNT(*) as count
        FROM (
            SELECT channel_type, remote_key
            FROM conversations
            WHERE remote_key IS NOT NULL AND remote_key != ''
            GROUP BY channel_type, remote_key
            HAVING COUNT(*) > 1
        ) as dup
    ")->fetchColumn();
    
    if ($remaining > 0) {
        echo "   ⚠️  Ainda existem {$remaining} grupos de duplicados\n";
    } else {
        echo "   ✅ Nenhum duplicado restante!\n";
        echo "\n   ✅ Agora você pode executar a migration de índice único:\n";
        echo "      php database/migrations/20260122_prevent_conversation_duplication.php\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Script concluído!\n\n";

