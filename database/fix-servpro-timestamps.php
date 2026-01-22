<?php

/**
 * Script para corrigir timestamps incorretos das conversas
 * Atualiza last_message_at com base no timestamp real dos eventos
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

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== CORREÇÃO: TIMESTAMPS DAS CONVERSAS ===\n\n";

$db = DB::getConnection();

// Busca conversas com timestamps incorretos
echo "1. Buscando conversas com timestamps que precisam correção:\n";
$stmt = $db->query("
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.last_message_at,
        c.updated_at,
        (
            SELECT MAX(
                GREATEST(
                    COALESCE(ce.created_at, '1970-01-01'),
                    CASE 
                        WHEN JSON_EXTRACT(ce.payload, '$.message.timestamp') IS NOT NULL THEN
                            FROM_UNIXTIME(CAST(JSON_EXTRACT(ce.payload, '$.message.timestamp') AS UNSIGNED))
                        WHEN JSON_EXTRACT(ce.payload, '$.timestamp') IS NOT NULL THEN
                            FROM_UNIXTIME(CAST(JSON_EXTRACT(ce.payload, '$.timestamp') AS UNSIGNED))
                        ELSE ce.created_at
                    END
                )
            )
            FROM communication_events ce
            WHERE ce.event_type LIKE '%whatsapp%'
            AND (
                JSON_EXTRACT(ce.payload, '$.from') LIKE CONCAT('%', c.contact_external_id, '%')
                OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE CONCAT('%', c.contact_external_id, '%')
                OR JSON_EXTRACT(ce.payload, '$.to') LIKE CONCAT('%', c.contact_external_id, '%')
                OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE CONCAT('%', c.contact_external_id, '%')
            )
        ) as real_last_message_at
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
    AND c.status NOT IN ('closed', 'archived')
    HAVING real_last_message_at IS NOT NULL
    AND real_last_message_at != c.last_message_at
    ORDER BY c.last_message_at DESC
    LIMIT 10
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ✅ Nenhuma conversa com timestamp incorreto encontrada!\n";
    exit(0);
}

echo "   Encontradas " . count($conversations) . " conversas:\n\n";

foreach ($conversations as $conv) {
    echo "   - {$conv['contact_name']} ({$conv['contact_external_id']})\n";
    echo "     Last Message At (atual): {$conv['last_message_at']}\n";
    echo "     Last Message At (correto): {$conv['real_last_message_at']}\n";
    echo "\n";
}

echo "\n";

// Pergunta confirmação
echo "⚠️  ATENÇÃO: Esta operação vai atualizar last_message_at das conversas acima.\n";
echo "Deseja continuar? (digite 'SIM' para confirmar): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if ($line !== 'SIM') {
    echo "Operação cancelada.\n";
    exit(0);
}

try {
    $db->beginTransaction();
    
    $updated = 0;
    foreach ($conversations as $conv) {
        $stmt = $db->prepare("
            UPDATE conversations 
            SET last_message_at = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $conv['real_last_message_at'],
            $conv['id']
        ]);
        $updated++;
        
        echo "✅ Conversa ID {$conv['id']} atualizada: {$conv['last_message_at']} -> {$conv['real_last_message_at']}\n";
    }
    
    $db->commit();
    
    echo "\n✅ Correção concluída! {$updated} conversa(s) atualizada(s).\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ ERRO ao corrigir: {$e->getMessage()}\n";
    exit(1);
}

echo "\n";

