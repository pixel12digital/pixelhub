<?php
/**
 * Script para corrigir conversas vinculadas incorretamente a tenants
 * 
 * PROBLEMA: Conversas estão sendo vinculadas automaticamente ao tenant do channel_id,
 * mas o número de telefone do contato não corresponde ao número do tenant.
 * Isso faz com que conversas de números desconhecidos apareçam vinculadas a clientes errados.
 * 
 * SOLUÇÃO: Desvincula conversas onde o número do contato não corresponde ao número do tenant.
 * Essas conversas devem aparecer como "Contato Desconhecido" na seção de conversas não vinculadas.
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

$db = DB::getConnection();

echo "===========================================\n";
echo "CORREÇÃO DE CONVERSAS VINCULADAS INCORRETAMENTE\n";
echo "===========================================\n\n";

// Busca todas as conversas vinculadas a tenants
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.is_incoming_lead,
        c.channel_id,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM conversations c
    INNER JOIN tenants t ON c.tenant_id = t.id
    WHERE c.tenant_id IS NOT NULL
    AND c.channel_type = 'whatsapp'
    ORDER BY c.id ASC
");

$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de conversas vinculadas encontradas: " . count($conversations) . "\n\n";

$conversationsToUnlink = [];
$correctlyLinked = [];

// Normaliza número de telefone (remove caracteres não numéricos e @lid)
function normalizePhone($phone) {
    if (empty($phone)) return null;
    // Remove @lid e tudo após @
    $cleaned = preg_replace('/@.*$/', '', (string) $phone);
    // Remove tudo exceto dígitos
    return preg_replace('/[^0-9]/', '', $cleaned);
}

foreach ($conversations as $conv) {
    $contactPhone = normalizePhone($conv['contact_external_id']);
    $tenantPhone = normalizePhone($conv['tenant_phone']);
    
    // Se não conseguiu normalizar o contato, pula
    if (empty($contactPhone)) {
        continue;
    }
    
    // Se o tenant não tem telefone cadastrado, marca para desvincular
    // (conversa não pode estar vinculada a tenant sem telefone conhecido)
    if (empty($tenantPhone)) {
        $conversationsToUnlink[] = [
            'conversation' => $conv,
            'reason' => 'Tenant não possui telefone cadastrado',
            'contact' => $contactPhone,
            'tenant_phone' => null
        ];
        continue;
    }
    
    // Verifica se os números correspondem
    // Considera variações do 9º dígito em números BR (55 + DDD + número)
    $contactMatches = false;
    
    // Comparação exata
    if ($contactPhone === $tenantPhone) {
        $contactMatches = true;
    }
    
    // Se são números BR (começam com 55 e têm pelo menos 12 dígitos), 
    // tenta comparar com/sem 9º dígito
    if (!$contactMatches && strlen($contactPhone) >= 12 && strlen($tenantPhone) >= 12 && 
        substr($contactPhone, 0, 2) === '55' && substr($tenantPhone, 0, 2) === '55') {
        
        // Remove 9º dígito de ambos para comparação
        $contactWithout9th = substr($contactPhone, 0, 4) . substr($contactPhone, 5);
        $tenantWithout9th = substr($tenantPhone, 0, 4) . substr($tenantPhone, 5);
        
        if ($contactWithout9th === $tenantWithout9th) {
            $contactMatches = true;
        }
        
        // Tenta adicionar 9º dígito em ambos
        if (!$contactMatches && strlen($contactPhone) === 12 && strlen($tenantPhone) === 12) {
            $contactWith9th = substr($contactPhone, 0, 4) . '9' . substr($contactPhone, 4);
            $tenantWith9th = substr($tenantPhone, 0, 4) . '9' . substr($tenantPhone, 4);
            
            if ($contactWith9th === $tenantWith9th) {
                $contactMatches = true;
            }
        }
    }
    
    if (!$contactMatches) {
        // Números não correspondem - marca para desvincular
        $conversationsToUnlink[] = [
            'conversation' => $conv,
            'reason' => 'Número do contato não corresponde ao número do tenant',
            'contact' => $contactPhone,
            'tenant_phone' => $tenantPhone
        ];
    } else {
        $correctlyLinked[] = $conv;
    }
}

echo "Análise:\n";
echo "  - Conversas corretamente vinculadas: " . count($correctlyLinked) . "\n";
echo "  - Conversas para desvincular: " . count($conversationsToUnlink) . "\n\n";

if (empty($conversationsToUnlink)) {
    echo "✅ Nenhuma conversa precisa ser desvinculada!\n";
    exit(0);
}

echo "Detalhes das conversas a desvincular:\n";
echo "===========================================\n";

foreach ($conversationsToUnlink as $item) {
    $c = $item['conversation'];
    echo sprintf(
        "Conversa ID: %d | Contato: %s | Tenant: %s (ID: %d) | Motivo: %s\n",
        $c['id'],
        $item['contact'] ?: 'N/A',
        $c['tenant_name'],
        $c['tenant_id'],
        $item['reason']
    );
}

echo "\n";

// Verifica se foi passado parâmetro --yes ou --force
$autoConfirm = in_array('--yes', $argv) || in_array('--force', $argv) || in_array('-y', $argv);

if (!$autoConfirm) {
    // Pergunta confirmação
    echo "Deseja desvincular essas " . count($conversationsToUnlink) . " conversas? (sim/não): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) !== 'sim' && strtolower($line) !== 's' && strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
        echo "Operação cancelada.\n";
        exit(0);
    }
} else {
    echo "Confirmando automaticamente (--yes detectado)...\n\n";
}

echo "\nIniciando desvinculação...\n\n";

$conversationIds = array_column($conversationsToUnlink, 'conversation');
$conversationIds = array_column($conversationIds, 'id');

$placeholders = str_repeat('?,', count($conversationIds) - 1) . '?';

// Atualiza as conversas para desvinculá-las
$updateStmt = $db->prepare("
    UPDATE conversations 
    SET tenant_id = NULL, 
        is_incoming_lead = 1
    WHERE id IN ($placeholders)
");

$updateStmt->execute($conversationIds);
$rowsAffected = $updateStmt->rowCount();

echo "✅ Desvinculação concluída!\n";
echo "   - Conversas atualizadas: $rowsAffected\n";
echo "   - Essas conversas agora aparecerão como 'Contato Desconhecido' na seção 'Conversas não vinculadas'\n\n";

// Busca estatísticas por tenant para reporte
$tenantStats = [];
foreach ($conversationsToUnlink as $item) {
    $tenantId = $item['conversation']['tenant_id'];
    $tenantName = $item['conversation']['tenant_name'];
    
    if (!isset($tenantStats[$tenantId])) {
        $tenantStats[$tenantId] = [
            'name' => $tenantName,
            'count' => 0
        ];
    }
    $tenantStats[$tenantId]['count']++;
}

echo "Resumo por tenant:\n";
echo "===========================================\n";
foreach ($tenantStats as $tenantId => $stats) {
    echo sprintf("  - %s (ID: %d): %d conversas desvinculadas\n", $stats['name'], $tenantId, $stats['count']);
}

echo "\n✅ Correção concluída com sucesso!\n";

