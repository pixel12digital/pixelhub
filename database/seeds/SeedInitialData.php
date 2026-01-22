<?php

/**
 * Seed inicial: Cria usuário admin e tenant de exemplo
 * 
 * Uso: php database/seed.php
 */

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    // Autoload manual simples
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../../src/';
        
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

echo "=== Seed Inicial - Pixel Hub ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se já existe o admin
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@pixel12.test']);
    $existingAdmin = $stmt->fetch();
    
    if ($existingAdmin) {
        echo "⊘ Usuário admin já existe (ID: {$existingAdmin['id']})\n";
    } else {
        // Cria usuário admin interno
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, is_internal, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            'Admin Pixel12',
            'admin@pixel12.test',
            $passwordHash
        ]);
        $adminId = $db->lastInsertId();
        echo "✓ Usuário admin criado (ID: {$adminId})\n";
    }
    
    // Busca o admin (pode ser o existente ou o recém-criado)
    if (!$existingAdmin) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute(['admin@pixel12.test']);
        $admin = $stmt->fetch();
        $adminId = $admin['id'];
    } else {
        $adminId = $existingAdmin['id'];
    }
    
    // Verifica se já existe o tenant de exemplo
    $stmt = $db->prepare("SELECT id FROM tenants WHERE name = ?");
    $stmt->execute(['Cliente Exemplo']);
    $existingTenant = $stmt->fetch();
    
    if ($existingTenant) {
        echo "⊘ Tenant 'Cliente Exemplo' já existe (ID: {$existingTenant['id']})\n";
        $tenantId = $existingTenant['id'];
    } else {
        // Cria tenant de exemplo
        $stmt = $db->prepare("
            INSERT INTO tenants (name, status, created_at, updated_at)
            VALUES (?, 'active', NOW(), NOW())
        ");
        $stmt->execute(['Cliente Exemplo']);
        $tenantId = $db->lastInsertId();
        echo "✓ Tenant 'Cliente Exemplo' criado (ID: {$tenantId})\n";
    }
    
    // Verifica se já existe o vínculo
    $stmt = $db->prepare("SELECT id FROM tenant_users WHERE tenant_id = ? AND user_id = ?");
    $stmt->execute([$tenantId, $adminId]);
    $existingLink = $stmt->fetch();
    
    if ($existingLink) {
        echo "⊘ Vínculo tenant-user já existe\n";
    } else {
        // Cria vínculo
        $stmt = $db->prepare("
            INSERT INTO tenant_users (tenant_id, user_id, role, created_at, updated_at)
            VALUES (?, ?, 'admin_cliente', NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $adminId]);
        echo "✓ Vínculo tenant-user criado\n";
    }
    
    echo "\n✓ Seed concluído!\n";
    echo "\nCredenciais de acesso:\n";
    echo "Email: admin@pixel12.test\n";
    echo "Senha: 123456\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    error_log("Erro no seed: " . $e->getMessage());
    exit(1);
}

