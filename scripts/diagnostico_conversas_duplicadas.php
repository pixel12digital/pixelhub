<?php
/**
 * Diagnóstico de Conversas Duplicadas
 * 
 * Este script identifica conversas que provavelmente representam o mesmo contato
 * mas aparecem duplicadas por variação de identificador (telefone vs @lid).
 * 
 * Uso: php scripts/diagnostico_conversas_duplicadas.php [tenant_id]
 * 
 * @author PixelHub
 * @date 2026-01-28
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "\n========================================\n";
echo "DIAGNÓSTICO DE CONVERSAS DUPLICADAS\n";
echo "========================================\n\n";

$db = DB::getConnection();

$tenantIdFilter = isset($argv[1]) ? (int)$argv[1] : null;

// 1. ANÁLISE: Conversas por tenant com contact_name duplicado
echo "1. CONVERSAS COM MESMO contact_name DENTRO DO MESMO TENANT\n";
echo str_repeat("-", 60) . "\n\n";

$query = "
    SELECT 
        c.tenant_id,
        t.name as tenant_name,
        c.contact_name,
        COUNT(*) as total_conversas,
        GROUP_CONCAT(c.id ORDER BY c.last_message_at DESC SEPARATOR ', ') as conversation_ids,
        GROUP_CONCAT(c.contact_external_id ORDER BY c.last_message_at DESC SEPARATOR ' | ') as identificadores
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_name IS NOT NULL 
    AND c.contact_name != ''
    AND c.channel_type = 'whatsapp'
    " . ($tenantIdFilter ? "AND c.tenant_id = $tenantIdFilter" : "") . "
    GROUP BY c.tenant_id, c.contact_name
    HAVING COUNT(*) > 1
    ORDER BY t.name, c.contact_name
";

$stmt = $db->query($query);
$duplicatesByName = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicatesByName)) {
    echo "Nenhuma duplicidade encontrada por contact_name.\n\n";
} else {
    echo "Encontradas " . count($duplicatesByName) . " duplicidades por contact_name:\n\n";
    
    foreach ($duplicatesByName as $row) {
        echo "TENANT: " . ($row['tenant_name'] ?? 'Não vinculado') . " (ID: " . ($row['tenant_id'] ?? 'NULL') . ")\n";
        echo "  CONTATO: {$row['contact_name']}\n";
        echo "  CONVERSAS: {$row['total_conversas']} registros\n";
        echo "  IDs: {$row['conversation_ids']}\n";
        echo "  IDENTIFICADORES: {$row['identificadores']}\n";
        echo "\n";
    }
}

// 2. ANÁLISE: Conversas @lid sem mapeamento em whatsapp_business_ids
echo "\n2. CONVERSAS COM @lid SEM MAPEAMENTO\n";
echo str_repeat("-", 60) . "\n\n";

$query2 = "
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        t.name as tenant_name,
        c.last_message_at,
        c.message_count
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    LEFT JOIN whatsapp_business_ids wb ON c.contact_external_id = wb.business_id
    WHERE c.contact_external_id LIKE '%@lid'
    AND wb.id IS NULL
    AND c.channel_type = 'whatsapp'
    " . ($tenantIdFilter ? "AND c.tenant_id = $tenantIdFilter" : "") . "
    ORDER BY c.last_message_at DESC
    LIMIT 50
";

$stmt2 = $db->query($query2);
$unmappedLids = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($unmappedLids)) {
    echo "Nenhuma conversa @lid sem mapeamento.\n\n";
} else {
    echo "Encontradas " . count($unmappedLids) . " conversas @lid sem mapeamento:\n\n";
    
    foreach ($unmappedLids as $row) {
        echo "  ID: {$row['id']} | @lid: {$row['contact_external_id']}\n";
        echo "    Nome: " . ($row['contact_name'] ?? 'NULL') . "\n";
        echo "    Tenant: " . ($row['tenant_name'] ?? 'Não vinculado') . "\n";
        echo "    Última msg: {$row['last_message_at']} | Total: {$row['message_count']} msgs\n\n";
    }
}

// 3. ANÁLISE DETALHADA: Possíveis duplicatas do filtro específico (Ponto do Golfe)
echo "\n3. ANÁLISE DETALHADA DE DUPLICATAS\n";
echo str_repeat("-", 60) . "\n\n";

// Busca conversas com mesmo contact_name e identifica quais são @lid vs telefone
$query3 = "
    SELECT 
        c1.id as id1,
        c1.contact_external_id as ext_id_1,
        c1.contact_name,
        c1.tenant_id,
        c1.message_count as msg_count_1,
        c1.last_message_at as last_msg_1,
        c1.status as status_1,
        c2.id as id2,
        c2.contact_external_id as ext_id_2,
        c2.message_count as msg_count_2,
        c2.last_message_at as last_msg_2,
        c2.status as status_2,
        t.name as tenant_name
    FROM conversations c1
    JOIN conversations c2 ON c1.contact_name = c2.contact_name 
        AND c1.tenant_id = c2.tenant_id 
        AND c1.id < c2.id
        AND c1.channel_type = c2.channel_type
    LEFT JOIN tenants t ON c1.tenant_id = t.id
    WHERE c1.contact_name IS NOT NULL 
    AND c1.contact_name != ''
    AND c1.channel_type = 'whatsapp'
    AND (
        (c1.contact_external_id LIKE '%@lid' AND c2.contact_external_id NOT LIKE '%@lid')
        OR (c1.contact_external_id NOT LIKE '%@lid' AND c2.contact_external_id LIKE '%@lid')
    )
    " . ($tenantIdFilter ? "AND c1.tenant_id = $tenantIdFilter" : "") . "
    ORDER BY c1.tenant_id, c1.contact_name
";

$stmt3 = $db->query($query3);
$detailedDuplicates = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($detailedDuplicates)) {
    echo "Nenhuma duplicata @lid vs telefone encontrada.\n\n";
} else {
    echo "Encontradas " . count($detailedDuplicates) . " duplicatas (@lid vs telefone):\n\n";
    
    foreach ($detailedDuplicates as $row) {
        $isLid1 = strpos($row['ext_id_1'], '@lid') !== false;
        $canonical = $isLid1 ? 2 : 1; // O registro com telefone é o canônico
        
        echo "CONTATO: {$row['contact_name']}\n";
        echo "TENANT: " . ($row['tenant_name'] ?? 'Não vinculado') . "\n";
        echo "\n";
        
        // Registro 1
        echo "  [" . ($canonical == 1 ? "MANTER" : "UNIFICAR") . "] Conversa #{$row['id1']}\n";
        echo "    Identificador: {$row['ext_id_1']}\n";
        echo "    Status: {$row['status_1']} | Msgs: {$row['msg_count_1']} | Última: {$row['last_msg_1']}\n";
        
        // Registro 2
        echo "  [" . ($canonical == 2 ? "MANTER" : "UNIFICAR") . "] Conversa #{$row['id2']}\n";
        echo "    Identificador: {$row['ext_id_2']}\n";
        echo "    Status: {$row['status_2']} | Msgs: {$row['msg_count_2']} | Última: {$row['last_msg_2']}\n";
        
        echo "\n  RECOMENDAÇÃO: Unificar #{$row['id' . ($canonical == 1 ? '2' : '1')]} -> #{$row['id' . $canonical]}\n";
        echo str_repeat("-", 50) . "\n\n";
    }
}

// 4. RESUMO E CONTAGENS
echo "\n4. RESUMO GERAL\n";
echo str_repeat("-", 60) . "\n\n";

// Total de conversas
$totalConversas = $db->query("SELECT COUNT(*) FROM conversations WHERE channel_type = 'whatsapp'")->fetchColumn();
echo "Total de conversas WhatsApp: $totalConversas\n";

// Conversas com @lid
$totalLid = $db->query("SELECT COUNT(*) FROM conversations WHERE contact_external_id LIKE '%@lid' AND channel_type = 'whatsapp'")->fetchColumn();
echo "Conversas com @lid: $totalLid (" . round($totalLid/$totalConversas*100, 1) . "%)\n";

// Conversas com telefone E.164
$totalPhone = $db->query("SELECT COUNT(*) FROM conversations WHERE contact_external_id REGEXP '^[0-9]+$' AND channel_type = 'whatsapp'")->fetchColumn();
echo "Conversas com telefone E.164: $totalPhone (" . round($totalPhone/$totalConversas*100, 1) . "%)\n";

// Mapeamentos existentes
$totalMappings = $db->query("SELECT COUNT(*) FROM whatsapp_business_ids")->fetchColumn();
echo "Mapeamentos @lid -> telefone: $totalMappings\n";

echo "\n========================================\n";
echo "FIM DO DIAGNÓSTICO\n";
echo "========================================\n\n";
