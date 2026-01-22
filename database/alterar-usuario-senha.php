<?php

/**
 * Script PHP para alterar usuário e senha do MySQL
 * 
 * Este script tenta conectar com as credenciais atuais e criar/alterar
 * o usuário admin_master com a nova senha
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

echo "=== Alterar Usuário e Senha do MySQL ===\n\n";

// Tenta carregar .env, mas não falha se não existir
try {
    Env::load();
} catch (\RuntimeException $e) {
    // .env não existe, usa credenciais padrão de produção
}

// Configurações - lê do .env (sem valores padrão sensíveis)
$host = Env::get('DB_HOST');
$port = Env::get('DB_PORT', '3306');
$database = Env::get('DB_NAME');
$currentUser = Env::get('DB_USER');
$currentPass = Env::get('DB_PASS');

if (empty($host) || empty($database) || empty($currentUser) || empty($currentPass)) {
    echo "❌ ERRO: Configure as variáveis no arquivo .env:\n";
    echo "   DB_HOST=seu_host\n";
    echo "   DB_NAME=seu_banco\n";
    echo "   DB_USER=seu_usuario\n";
    echo "   DB_PASS=sua_senha\n\n";
    exit(1);
}

// Novo usuário e senha - lê do .env ou solicita
$newUser = Env::get('ADMIN_MASTER_DB_USER', 'admin_master');
$newPassword = Env::get('ADMIN_MASTER_DB_PASSWORD');

if (empty($newPassword)) {
    echo "⚠ Senha do novo usuário não encontrada no .env (ADMIN_MASTER_DB_PASSWORD)\n";
    echo "Digite a senha para o novo usuário '{$newUser}': ";
    $newPassword = trim(fgets(STDIN));
    if (empty($newPassword)) {
        echo "❌ ERRO: Senha não pode ser vazia!\n";
        exit(1);
    }
}

echo "Configurações:\n";
echo "  Host: {$host}\n";
echo "  Porta: {$port}\n";
echo "  Banco: {$database}\n";
echo "  Usuário Atual: {$currentUser}\n";
echo "  Novo Usuário: {$newUser}\n";
echo "  Nova Senha: " . str_repeat('*', strlen($newPassword)) . "\n\n";

// Tenta conectar e criar o usuário
$pdo = null;
$adminUser = $currentUser;
$adminPassword = $currentPass;

// Tenta com credenciais atuais
echo "Tentando conectar com as credenciais atuais...\n";
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $pdo = new PDO($dsn, $adminUser, $adminPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    echo "✓ Conectado com sucesso!\n\n";
} catch (PDOException $e) {
    // Se falhar e estiver em localhost, tenta com root
    if (($host === 'localhost' || $host === '127.0.0.1') && $currentUser !== 'root') {
        echo "⚠ Falhou com credenciais atuais. Tentando com root...\n";
        try {
            $adminUser = 'root';
            $adminPassword = '';
            $pdo = new PDO($dsn, $adminUser, $adminPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
            ]);
            echo "✓ Conectado como root!\n\n";
        } catch (PDOException $e2) {
            echo "✗ ERRO: " . $e2->getMessage() . "\n\n";
            echo "Não foi possível conectar ao MySQL.\n";
            echo "Execute o script SQL manualmente no servidor:\n";
            echo "  database/alterar-usuario-senha.sql\n";
            exit(1);
        }
    } else {
        echo "✗ ERRO: " . $e->getMessage() . "\n\n";
        echo "Não foi possível conectar ao MySQL.\n";
        echo "Execute o script SQL manualmente no servidor:\n";
        echo "  database/alterar-usuario-senha.sql\n";
        exit(1);
    }
}

// Função para criar/atualizar o usuário
function createUser($pdo, $newUser, $newPassword, $database) {
    // Verifica se o usuário já existe
    echo "Verificando se o usuário '{$newUser}' já existe...\n";
    $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE User = '{$newUser}'");
    $existingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($existingUsers)) {
        echo "⚠ Usuário '{$newUser}' já existe nos seguintes hosts:\n";
        foreach ($existingUsers as $user) {
            echo "  - {$user['User']}@{$user['Host']}\n";
        }
        echo "  Removendo para recriar...\n\n";
    }
    
    // Remove o usuário existente se houver
    $pdo->exec("DROP USER IF EXISTS '{$newUser}'@'%'");
    $pdo->exec("DROP USER IF EXISTS '{$newUser}'@'localhost'");
    
    // Cria o novo usuário
    echo "Criando usuário '{$newUser}'...\n";
    $pdo->exec("CREATE USER '{$newUser}'@'%' IDENTIFIED BY '{$newPassword}'");
    echo "✓ Usuário criado com sucesso!\n";
    
    // Concede permissões
    echo "Concedendo permissões no banco '{$database}'...\n";
    $pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$newUser}'@'%'");
    $pdo->exec("FLUSH PRIVILEGES");
    echo "✓ Permissões concedidas!\n\n";
    
    // Verifica o resultado
    echo "Verificando usuário criado...\n";
    $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE User = '{$newUser}'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "  ✓ {$user['User']}@{$user['Host']}\n";
    }
}

// Executa a criação do usuário
try {
    createUser($pdo, $newUser, $newPassword, $database);
    
    echo "\n=== SUCESSO! ===\n\n";
    echo "Usuário '{$newUser}' criado/atualizado com sucesso!\n";
    echo "Agora você pode atualizar o arquivo .env com:\n";
    echo "  DB_USER={$newUser}\n";
    echo "  DB_PASS={$newPassword}\n\n";
    
    // Testa a conexão com o novo usuário
    echo "Testando conexão com o novo usuário...\n";
    try {
        $testDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $testPdo = new PDO($testDsn, $newUser, $newPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        echo "✓ Conexão com o novo usuário funcionando perfeitamente!\n";
    } catch (PDOException $e) {
        echo "⚠ Erro ao testar conexão: " . $e->getMessage() . "\n";
        echo "   Isso pode ser normal se o acesso remoto não estiver habilitado.\n";
        echo "   O usuário foi criado, mas pode precisar de configuração adicional.\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ ERRO ao criar usuário: " . $e->getMessage() . "\n\n";
    echo "O usuário atual pode não ter permissões para criar usuários.\n";
    echo "Execute o script SQL manualmente no servidor:\n";
    echo "  database/alterar-usuario-senha.sql\n";
    exit(1);
}
