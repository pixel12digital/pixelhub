<?php
$db = new PDO('mysql:host=localhost;dbname=pixel12d_hub;charset=utf8mb4', 'pixel12d_hub', 'Pixel12Hub@2024');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT id, name, is_archived FROM tenants WHERE id = ?');
$stmt->execute([57]);
$tenant = $stmt->fetch();

echo "ID: " . $tenant['id'] . "\n";
echo "Name: " . $tenant['name'] . "\n";
echo "is_archived value: " . var_export($tenant['is_archived'], true) . "\n";
echo "is_archived type: " . gettype($tenant['is_archived']) . "\n";
echo "empty() check: " . (empty($tenant['is_archived']) ? 'TRUE (will show services)' : 'FALSE (will hide services)') . "\n";
echo "!empty() check: " . (!empty($tenant['is_archived']) ? 'TRUE (will hide services)' : 'FALSE (will show services)') . "\n";

$stmt = $db->prepare('SELECT COUNT(*) FROM hosting_accounts WHERE tenant_id = ?');
$stmt->execute([57]);
echo "\nHosting accounts in DB: " . $stmt->fetchColumn() . "\n";
