<?php

/**
 * Script para criar usuário master no sistema
 * 
 * Cria um usuário com is_internal = 1 para acesso completo ao painel
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

echo "=== Criar Usuário Master no Sistema ===\n\n";

// Configurações do novo usuário
$name = 'Admin Master';
$email = 'admin_master@pixel12digital.com.br';
$isInternal = 1; // 1 = usuário master/admin

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
echo "  Nome: {$name}\n";
echo "  Email: {$email}\n";
echo "  Senha: " . str_repeat('*', strlen($password)) . "\n";
echo "  Tipo: " . ($isInternal ? 'Master/Admin (is_internal = 1)' : 'Cliente') . "\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se o usuário já existe
    echo "Verificando se o usuário já existe...\n";
    $stmt = $db->prepare("SELECT id, name, email, is_internal FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "⚠ Usuário já existe!\n";
        echo "  ID: {$existingUser['id']}\n";
        echo "  Nome: {$existingUser['name']}\n";
        echo "  Email: {$existingUser['email']}\n";
        echo "  Tipo: " . ($existingUser['is_internal'] ? 'Master/Admin' : 'Cliente') . "\n\n";
        
        echo "Deseja atualizar a senha? (S/N): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) === 's' || strtolower($line) === 'y' || strtolower($line) === 'sim') {
            // Atualiza a senha
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    name = ?,
                    is_internal = ?,
                    updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$passwordHash, $name, $isInternal, $email]);
            
            echo "✓ Senha atualizada com sucesso!\n";
            echo "✓ Nome e tipo atualizados!\n\n";
        } else {
            echo "⊘ Nenhuma alteração realizada.\n";
            echo "\nCredenciais atuais:\n";
            echo "  Email: {$email}\n";
            echo "  (Senha não foi alterada)\n";
            exit(0);
        }
    } else {
        // Cria o novo usuário
        echo "Criando novo usuário master...\n";
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, is_internal, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $name,
            $email,
            $passwordHash,
            $isInternal
        ]);
        
        $userId = $db->lastInsertId();
        echo "✓ Usuário master criado com sucesso!\n";
        echo "  ID: {$userId}\n\n";
    }
    
    // Verifica o resultado final
    $stmt = $db->prepare("SELECT id, name, email, is_internal, created_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    echo "=== SUCESSO! ===\n\n";
    echo "Usuário master criado/atualizado:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Nome: {$user['name']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Tipo: " . ($user['is_internal'] ? 'Master/Admin (acesso completo)' : 'Cliente') . "\n";
    echo "  Criado em: {$user['created_at']}\n\n";
    
    echo "Credenciais de acesso:\n";
    echo "  Email: {$email}\n";
    echo "  Senha: {$password}\n\n";
    
    echo "Você pode fazer login no sistema com essas credenciais!\n";
    
} catch (\Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "\nDetalhes:\n";
    echo "  " . $e->getTraceAsString() . "\n";
    error_log("Erro ao criar usuário master: " . $e->getMessage());
    exit(1);
}

