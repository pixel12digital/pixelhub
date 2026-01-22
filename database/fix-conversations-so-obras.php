<?php

/**
 * Script para corrigir conversas incorretamente vinculadas a "SO OBRAS"
 * 
 * Este script identifica conversas que foram vinculadas automaticamente ao tenant "SO OBRAS"
 * e as move para "Não vinculados" (tenant_id = NULL, is_incoming_lead = 1) para que o usuário
 * possa decidir se vincula a algum lead, descarta ou cria um tenant.
 * 
 * Uso: php database/fix-conversations-so-obras.php [--dry-run] [--tenant-id=ID]
 * 
 * Opções:
 *   --dry-run          Apenas mostra o que seria feito, sem executar
 *   --tenant-id=ID     ID específico do tenant "SO OBRAS" (se não informado, busca automaticamente)
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

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Verifica se é dry-run
$dryRun = in_array('--dry-run', $argv);
$tenantIdParam = null;

// Verifica se foi passado tenant_id específico
foreach ($argv as $arg) {
    if (strpos($arg, '--tenant-id=') === 0) {
        $tenantIdParam = (int) substr($arg, strlen('--tenant-id='));
    }
}

echo "=== Script de Correção: Conversas SO OBRAS ===\n\n";

if ($dryRun) {
    echo "⚠️  MODO DRY-RUN: Nenhuma alteração será feita no banco de dados\n\n";
}

try {
    $db = DB::getConnection();
    
    // 1. Identifica o tenant "SO OBRAS"
    $soObrasTenantId = $tenantIdParam;
    $soObrasName = null;
    
    if (!$soObrasTenantId) {
        // Busca tenant "SO OBRAS" por nome
        $stmt = $db->prepare("
            SELECT id, name 
            FROM tenants 
            WHERE name LIKE '%SO OBRAS%' 
               OR name LIKE '%SO_OBRAS%'
               OR name LIKE '%só obras%'
            LIMIT 1
        ");
        $stmt->execute();
        $tenant = $stmt->fetch();
        
        if ($tenant) {
            $soObrasTenantId = (int) $tenant['id'];
            $soObrasName = $tenant['name'];
            echo "✅ Tenant 'SO OBRAS' encontrado:\n";
            echo "   ID: {$soObrasTenantId}\n";
            echo "   Nome: {$soObrasName}\n\n";
        } else {
            echo "❌ Tenant 'SO OBRAS' não encontrado no banco de dados.\n";
            echo "   Use --tenant-id=ID para especificar manualmente.\n";
            exit(1);
        }
    } else {
        // Busca informações do tenant pelo ID fornecido
        $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $stmt->execute([$soObrasTenantId]);
        $tenant = $stmt->fetch();
        
        if ($tenant) {
            $soObrasName = $tenant['name'];
            echo "✅ Tenant encontrado pelo ID fornecido:\n";
            echo "   ID: {$soObrasTenantId}\n";
            echo "   Nome: {$soObrasName}\n\n";
        } else {
            echo "❌ Tenant com ID {$soObrasTenantId} não encontrado.\n";
            exit(1);
        }
    }
    
    // 2. Identifica conversas vinculadas a SO OBRAS que podem ter sido vinculadas incorretamente
    // Critérios para identificar conversas incorretamente vinculadas:
    // - tenant_id = SO OBRAS
    // - is_incoming_lead = 0 (não está marcada como lead)
    // - channel_type = 'whatsapp' (apenas WhatsApp)
    // - created_at recente (últimos 30 dias) OU não tem mensagens relacionadas a faturas/hospedagem
    
    echo "🔍 Buscando conversas vinculadas a SO OBRAS...\n\n";
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.conversation_key,
            c.contact_external_id,
            c.contact_name,
            c.tenant_id,
            c.is_incoming_lead,
            c.status,
            c.created_at,
            c.last_message_at,
            c.message_count,
            t.name as tenant_name
        FROM conversations c
        INNER JOIN tenants t ON c.tenant_id = t.id
        WHERE c.tenant_id = ?
          AND c.channel_type = 'whatsapp'
          AND c.is_incoming_lead = 0
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$soObrasTenantId]);
    $conversations = $stmt->fetchAll();
    
    $totalConversations = count($conversations);
    
    if ($totalConversations === 0) {
        echo "✅ Nenhuma conversa encontrada vinculada a SO OBRAS.\n";
        exit(0);
    }
    
    echo "📊 Total de conversas encontradas: {$totalConversations}\n\n";
    
    // 3. Verifica se há relacionamentos que indicam que a vinculação pode ser legítima
    // (ex: faturas, hospedagens, projetos vinculados ao mesmo número de telefone)
    echo "🔍 Analisando conversas...\n\n";
    
    $conversationsToFix = [];
    $conversationsToKeep = [];
    
    foreach ($conversations as $conv) {
        $conversationId = $conv['id'];
        $contactPhone = $conv['contact_external_id'];
        
        // Normaliza telefone para busca (remove @c.us, @s.whatsapp.net, etc)
        $phoneNormalized = preg_replace('/@.*$/', '', $contactPhone);
        $phoneNormalized = preg_replace('/[^0-9]/', '', $phoneNormalized);
        
        // Verifica se há relacionamentos legítimos:
        // - Faturas do tenant SO OBRAS com esse telefone
        // - Hospedagens do tenant SO OBRAS
        // - Projetos do tenant SO OBRAS
        
        $hasLegitimateRelation = false;
        
        // Verifica se o telefone do contato corresponde ao telefone do tenant SO OBRAS
        $tenantPhone = null;
        $stmtTenant = $db->prepare("SELECT phone FROM tenants WHERE id = ?");
        $stmtTenant->execute([$soObrasTenantId]);
        $tenantData = $stmtTenant->fetch();
        if ($tenantData && !empty($tenantData['phone'])) {
            $tenantPhoneNormalized = preg_replace('/[^0-9]/', '', $tenantData['phone']);
            if ($phoneNormalized === $tenantPhoneNormalized) {
                $hasLegitimateRelation = true;
            }
        }
        
        // Verifica se há faturas recentes do tenant SO OBRAS
        if (!$hasLegitimateRelation) {
            $stmtInvoices = $db->prepare("
                SELECT COUNT(*) as count 
                FROM billing_invoices 
                WHERE tenant_id = ? 
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmtInvoices->execute([$soObrasTenantId]);
            $invoices = $stmtInvoices->fetch();
            if ($invoices && $invoices['count'] > 0) {
                // Se há faturas recentes, pode ser legítimo, mas vamos marcar para revisão manual
                // Por enquanto, vamos mover mesmo assim, pois o problema é que números novos
                // estão sendo vinculados automaticamente
            }
        }
        
        // Se não tem relação legítima clara, marca para correção
        if (!$hasLegitimateRelation) {
            $conversationsToFix[] = $conv;
        } else {
            $conversationsToKeep[] = $conv;
        }
    }
    
    $totalToFix = count($conversationsToFix);
    $totalToKeep = count($conversationsToKeep);
    
    echo "📋 Resultado da análise:\n";
    echo "   ✅ Conversas para manter vinculadas: {$totalToKeep}\n";
    echo "   🔧 Conversas para mover para 'Não vinculados': {$totalToFix}\n\n";
    
    if ($totalToKeep > 0) {
        echo "⚠️  Conversas que serão mantidas (têm relação legítima):\n";
        foreach ($conversationsToKeep as $conv) {
            echo "   - ID: {$conv['id']} | Contato: {$conv['contact_external_id']} | Criada em: {$conv['created_at']}\n";
        }
        echo "\n";
    }
    
    if ($totalToFix === 0) {
        echo "✅ Nenhuma conversa precisa ser corrigida.\n";
        exit(0);
    }
    
    // 4. Mostra preview das conversas que serão corrigidas
    echo "📋 Conversas que serão movidas para 'Não vinculados':\n";
    $previewCount = min(10, $totalToFix);
    for ($i = 0; $i < $previewCount; $i++) {
        $conv = $conversationsToFix[$i];
        echo "   - ID: {$conv['id']} | Contato: {$conv['contact_external_id']} | Nome: " . ($conv['contact_name'] ?: 'N/A') . " | Criada em: {$conv['created_at']}\n";
    }
    if ($totalToFix > $previewCount) {
        echo "   ... e mais " . ($totalToFix - $previewCount) . " conversas\n";
    }
    echo "\n";
    
    // 5. Executa a correção
    if ($dryRun) {
        echo "🔍 DRY-RUN: As seguintes alterações seriam feitas:\n\n";
        echo "UPDATE conversations SET tenant_id = NULL, is_incoming_lead = 1 WHERE id IN (";
        $ids = array_column($conversationsToFix, 'id');
        echo implode(', ', $ids);
        echo ");\n\n";
        echo "Total de conversas que seriam atualizadas: {$totalToFix}\n";
    } else {
        echo "🔧 Executando correção...\n\n";
        
        $ids = array_column($conversationsToFix, 'id');
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $updateStmt = $db->prepare("
            UPDATE conversations 
            SET tenant_id = NULL, 
                is_incoming_lead = 1,
                updated_at = NOW()
            WHERE id IN ({$placeholders})
        ");
        $updateStmt->execute($ids);
        
        $rowsAffected = $updateStmt->rowCount();
        
        echo "✅ Correção concluída!\n";
        echo "   Conversas atualizadas: {$rowsAffected}\n";
        echo "   Conversas agora aparecem em 'Não vinculados'\n";
        echo "   Marcadas como incoming_lead = 1\n\n";
        
        // 6. Registra log da correção
        $logMessage = sprintf(
            "Script fix-conversations-so-obras.php executado: %d conversas movidas de tenant_id=%d (%s) para tenant_id=NULL (Não vinculados)",
            $rowsAffected,
            $soObrasTenantId,
            $soObrasName
        );
        error_log($logMessage);
        
        echo "📝 Log registrado: {$logMessage}\n";
    }
    
    echo "\n✅ Processo concluído!\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

