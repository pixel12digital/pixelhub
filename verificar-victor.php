<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$stmt = $db->query("SELECT id, contact_external_id, remote_key, thread_key, tenant_id, message_count FROM conversations WHERE id IN (15, 17)");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Conversas 15 e 17 ===\n\n";
foreach ($rows as $row) {
    echo "ID: {$row['id']}\n";
    echo "  contact_external_id: {$row['contact_external_id']}\n";
    echo "  remote_key: " . ($row['remote_key'] ?: 'NULL') . "\n";
    echo "  thread_key: " . ($row['thread_key'] ?: 'NULL') . "\n";
    echo "  tenant_id: " . ($row['tenant_id'] ?: 'NULL') . "\n";
    echo "  message_count: {$row['message_count']}\n";
    echo "\n";
}

// Verifica se são duplicados
if (count($rows) === 2) {
    $remoteKey1 = $rows[0]['remote_key'];
    $remoteKey2 = $rows[1]['remote_key'];
    
    if ($remoteKey1 && $remoteKey2 && $remoteKey1 === $remoteKey2) {
        echo "✅ São duplicados (mesmo remote_key: {$remoteKey1})\n";
    } else {
        echo "⚠️  NÃO são duplicados por remote_key\n";
        echo "   remote_key 15: " . ($remoteKey1 ?: 'NULL') . "\n";
        echo "   remote_key 17: " . ($remoteKey2 ?: 'NULL') . "\n";
        
        // Verifica se uma é lid:xxx e outra é tel:xxx (mesmo número)
        if ($remoteKey1 && $remoteKey2) {
            $lid1 = preg_replace('/^lid:/', '', $remoteKey1);
            $tel1 = preg_replace('/^tel:/', '', $remoteKey1);
            $lid2 = preg_replace('/^lid:/', '', $remoteKey2);
            $tel2 = preg_replace('/^tel:/', '', $remoteKey2);
            
            if (($lid1 && $tel2 && $lid1 === $tel2) || ($tel1 && $lid2 && $tel1 === $lid2)) {
                echo "   ⚠️  MAS são o mesmo contato (lid vs tel do mesmo número)\n";
            }
        }
    }
}

