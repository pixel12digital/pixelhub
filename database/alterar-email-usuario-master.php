<?php

/**
 * Script para alterar email do usuário master
 * 
 * IMPORTANTE: Configure ADMIN_MASTER_PASSWORD no arquivo .env
 * ou será solicitado durante a execução
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

// Carrega .env
try {
    Env::load();
} catch (\RuntimeException $e) {
    // .env não existe, continuar
}

echo "=== Alterar Email do Usuário Master ===\n\n";

$oldEmail = 'admin_master@pixel12digital.com.br';
$newEmail = 'contato@pixel12digital.com.br';

// Obtém senha do .env ou solicita ao usuário
$password = Env::get('ADMIN_MASTER_PASSWORD');
if (empty($password)) {
    echo "⚠ Senha não encontrada no .env (ADMIN_MASTER_PASSWORD)\n";
    echo "Digite a senha para o usuário master: ";
    $password = trim(fgets(STDIN));
    if (empty($password)) {
        echo "❌ ERRO: Senha não pode ser vazia!\n";
        exit(1);
    }
}

echo "Configurações:\n";
echo "  Email antigo: {$oldEmail}\n";
echo "  Email novo: {$newEmail}\n";
echo "  Senha: " . str_repeat('*', strlen($password)) . " (mantida)\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se o usuário antigo existe
    echo "Verificando usuário antigo...\n";
    $stmt = $db->prepare("SELECT id, name, email, is_internal FROM users WHERE email = ?");
    $stmt->execute([$oldEmail]);
    $oldUser = $stmt->fetch();
    
    if (!$oldUser) {
        echo "⚠ Usuário com email '{$oldEmail}' não encontrado!\n";
        echo "Verificando se o novo email já existe...\n";
        
        $stmt = $db->prepare("SELECT id, name, email, is_internal FROM users WHERE email = ?");
        $stmt->execute([$newEmail]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            echo "✓ Usuário com email '{$newEmail}' já existe!\n";
            echo "  ID: {$existingUser['id']}\n";
            echo "  Nome: {$existingUser['name']}\n";
            echo "  Tipo: " . ($existingUser['is_internal'] ? 'Master/Admin' : 'Cliente') . "\n\n";
            
            // Atualiza a senha para garantir
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    is_internal = 1,
                    updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$passwordHash, $newEmail]);
            
            echo "✓ Senha atualizada e tipo garantido como Master!\n";
            $user = $existingUser;
        } else {
            echo "✗ Nenhum usuário encontrado. Criando novo usuário master...\n";
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password_hash, is_internal, created_at, updated_at)
                VALUES (?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([
                'Admin Master',
                $newEmail,
                $passwordHash
            ]);
            
            $userId = $db->lastInsertId();
            echo "✓ Novo usuário master criado (ID: {$userId})\n";
            
            $stmt = $db->prepare("SELECT id, name, email, is_internal, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    } else {
        echo "✓ Usuário encontrado (ID: {$oldUser['id']})\n\n";
        
        // Verifica se o novo email já existe
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$newEmail]);
        $emailExists = $stmt->fetch();
        
        if ($emailExists && $emailExists['id'] != $oldUser['id']) {
            echo "⚠ ATENÇÃO: O email '{$newEmail}' já está em uso por outro usuário!\n";
            echo "  ID do outro usuário: {$emailExists['id']}\n";
            echo "  Não é possível alterar o email.\n";
            exit(1);
        }
        
        // Atualiza o email e garante a senha
        echo "Alterando email e atualizando senha...\n";
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            UPDATE users 
            SET email = ?, 
                password_hash = ?,
                is_internal = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newEmail, $passwordHash, $oldUser['id']]);
        
        echo "✓ Email alterado com sucesso!\n";
        echo "✓ Senha atualizada!\n";
        echo "✓ Tipo garantido como Master!\n\n";
        
        // Busca o usuário atualizado
        $stmt = $db->prepare("SELECT id, name, email, is_internal, created_at FROM users WHERE id = ?");
        $stmt->execute([$oldUser['id']]);
        $user = $stmt->fetch();
    }
    
    echo "=== SUCESSO! ===\n\n";
    echo "Usuário master configurado:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Nome: {$user['name']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Tipo: " . ($user['is_internal'] ? 'Master/Admin (acesso completo)' : 'Cliente') . "\n";
    echo "  Criado em: {$user['created_at']}\n\n";
    
    echo "Credenciais de acesso:\n";
    echo "  Email: {$newEmail}\n";
    echo "  Senha: {$password}\n\n";
    
    echo "Você pode fazer login no sistema com essas credenciais!\n";
    
} catch (\Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao alterar email do usuário master: " . $e->getMessage());
    exit(1);
}

