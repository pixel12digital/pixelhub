<?php

/**
 * Script para verificar conversas com possíveis vínculos incorretos
 * Verifica casos onde o tenant não corresponde ao nome do contato
 * 
 * Uso: php database/verificar-vinculos-suspeitos.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando conversas com possíveis vínculos incorretos ===\n\n";

// Busca todas as conversas com tenant vinculado
echo "1. Buscando conversas com tenant vinculado:\n";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.last_message_at,
        t.name as tenant_name
    FROM conversations c
    INNER JOIN tenants t ON c.tenant_id = t.id
    WHERE c.tenant_id IS NOT NULL
      AND c.contact_name IS NOT NULL
      AND c.contact_name != ''
    ORDER BY c.last_message_at DESC
    LIMIT 50
");
$stmt->execute();
$conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversas)) {
    echo "   ❌ NENHUMA CONVERSA ENCONTRADA\n";
    exit(0);
}

echo "   ✅ Encontradas " . count($conversas) . " conversa(s) com tenant vinculado\n\n";

// Analisa possíveis inconsistências
echo "2. Análise de possíveis vínculos incorretos:\n\n";

$suspeitas = [];
foreach ($conversas as $conv) {
    $contactName = strtolower(trim($conv['contact_name']));
    $tenantName = strtolower(trim($conv['tenant_name']));
    
    // Extrai palavras-chave do nome do contato
    $contactWords = array_filter(explode(' ', $contactName), function($word) {
        return strlen($word) > 2; // Ignora palavras muito curtas
    });
    
    // Verifica se alguma palavra do contato aparece no tenant
    $matches = false;
    foreach ($contactWords as $word) {
        if (strpos($tenantName, $word) !== false) {
            $matches = true;
            break;
        }
    }
    
    // Se não houver correspondência, pode ser vínculo incorreto
    if (!$matches && count($contactWords) > 0) {
        // Exceções: alguns nomes podem não corresponder mas estar corretos
        // (ex: funcionários de uma empresa)
        $suspeitas[] = [
            'conversation_id' => $conv['id'],
            'contact_name' => $conv['contact_name'],
            'contact_external_id' => $conv['contact_external_id'],
            'tenant_id' => $conv['tenant_id'],
            'tenant_name' => $conv['tenant_name'],
            'last_message_at' => $conv['last_message_at']
        ];
    }
}

if (empty($suspeitas)) {
    echo "   ✅ Nenhuma suspeita encontrada - todos os vínculos parecem corretos\n";
} else {
    echo "   ⚠️  Encontradas " . count($suspeitas) . " conversa(s) com possível vínculo incorreto:\n\n";
    
    foreach ($suspeitas as $idx => $suspeita) {
        echo "   " . ($idx + 1) . ". Conversa ID {$suspeita['conversation_id']}:\n";
        echo "      - Contato: {$suspeita['contact_name']} ({$suspeita['contact_external_id']})\n";
        echo "      - Tenant atual: ID {$suspeita['tenant_id']} - {$suspeita['tenant_name']}\n";
        echo "      - Última mensagem: {$suspeita['last_message_at']}\n";
        echo "      - ⚠️  Nome do contato não corresponde ao nome do tenant\n";
        echo "\n";
    }
}

// Lista todas as conversas para referência
echo "\n3. Lista completa de conversas com tenant vinculado:\n";
echo "   (Últimas 20 conversas)\n\n";
$count = 0;
foreach ($conversas as $conv) {
    if ($count >= 20) break;
    echo "   - {$conv['contact_name']} → {$conv['tenant_name']} (ID: {$conv['tenant_id']})\n";
    $count++;
}

echo "\n=== FIM ===\n";

