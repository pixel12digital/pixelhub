<?php
// Remote database connection
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

try {
    $db = $pdo;
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT id, name, is_archived FROM tenants WHERE id = ?");
    $stmt->execute([57]);
    $tenant = $stmt->fetch();
    
    echo "Tenant ID: " . $tenant['id'] . "\n";
    echo "Name: " . $tenant['name'] . "\n";
    echo "is_archived: " . ($tenant['is_archived'] ?? 'NULL') . "\n";
    echo "is_archived (raw): " . var_export($tenant['is_archived'], true) . "\n";
    echo "is_archived (empty check): " . (empty($tenant['is_archived']) ? 'EMPTY (false)' : 'NOT EMPTY (true)') . "\n";
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM hosting_accounts WHERE tenant_id = ?");
    $stmt->execute([57]);
    $count = $stmt->fetchColumn();
    echo "\nHosting accounts count in DB: " . $count . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
