<?php

/**
 * Script para verificar se o usuário tem as permissões corretas
 * e tentar diferentes formas de conexão
 */

require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;

Env::load();

$host = Env::get('DB_HOST', 'localhost');
$port = Env::get('DB_PORT', '3306');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');
$database = Env::get('DB_NAME', 'pixel_hub');

echo "=== Diagnóstico de Permissões MySQL ===\n\n";

// Tentativa 1: Conectar sem especificar banco
echo "1. Testando conexão sem especificar banco...\n";
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    
    echo "   ✓ Conectado ao servidor\n";
    
    // Tenta verificar o usuário
    echo "\n2. Verificando informações do usuário...\n";
    try {
        $userInfo = $pdo->query("SELECT USER() as user, DATABASE() as db")->fetch();
        echo "   ✓ Usuário conectado: {$userInfo['user']}\n";
        echo "   ✓ Banco atual: " . ($userInfo['db'] ?: '(nenhum)') . "\n";
        
        // Lista bancos acessíveis
        echo "\n3. Listando bancos acessíveis...\n";
        $databases = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        echo "   ✓ Bancos encontrados: " . count($databases) . "\n";
        
        if (in_array($database, $databases)) {
            echo "   ✓ O banco '{$database}' está na lista\n";
            
            // Tenta usar o banco
            echo "\n4. Tentando usar o banco '{$database}'...\n";
            try {
                $pdo->exec("USE `{$database}`");
                echo "   ✓ Sucesso! Conseguiu usar o banco\n";
                
                // Tenta listar tabelas
                echo "\n5. Listando tabelas...\n";
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                echo "   ✓ Tabelas encontradas: " . count($tables) . "\n";
                
                if (count($tables) > 0) {
                    echo "\n   Primeiras tabelas:\n";
                    foreach (array_slice($tables, 0, 5) as $table) {
                        echo "     - {$table}\n";
                    }
                }
                
                echo "\n✓✓✓ SUCESSO! A conexão está funcionando agora! ✓✓✓\n";
                exit(0);
                
            } catch (PDOException $e) {
                echo "   ✗ Erro ao usar o banco: {$e->getMessage()}\n";
            }
        } else {
            echo "   ✗ O banco '{$database}' NÃO está na lista\n";
            echo "\n   Bancos disponíveis:\n";
            foreach ($databases as $db) {
                echo "     - {$db}\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "   ✗ Erro: {$e->getMessage()}\n";
    }
    
} catch (PDOException $e) {
    echo "   ✗ Falha na conexão: {$e->getMessage()}\n";
    exit(1);
}

// Tentativa 2: Conectar diretamente especificando o banco
echo "\n\n6. Tentando conectar diretamente especificando o banco...\n";
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );
    
    $pdo2 = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    
    echo "   ✓✓✓ SUCESSO! Conexão direta funcionou! ✓✓✓\n";
    
    $tables = $pdo2->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "   ✓ Tabelas encontradas: " . count($tables) . "\n";
    
    exit(0);
    
} catch (PDOException $e) {
    echo "   ✗ Falha: {$e->getMessage()}\n";
    echo "   Código: {$e->getCode()}\n\n";
}

echo "\n=== RECOMENDAÇÕES ===\n\n";

if (strpos($e->getMessage(), '1044') !== false) {
    echo "O erro 1044 indica problema de permissões.\n\n";
    echo "OPÇÕES PARA RESOLVER:\n\n";
    
    echo "1. Via cPanel - Seção 'MySQL Databases' ou 'Manage My Databases':\n";
    echo "   - Procure por 'Add User To Database' ou similar\n";
    echo "   - Associe o usuário '{$username}' ao banco '{$database}'\n";
    echo "   - Dê todas as permissões (ALL PRIVILEGES)\n\n";
    
    echo "2. Via cPanel - Seção 'Remote MySQL' ou 'Remote Database Access':\n";
    echo "   - Adicione o IP: 179.187.207.148\n";
    echo "   - Ou adicione: % (permite qualquer IP)\n\n";
    
    echo "3. Contatar o suporte do hosting:\n";
    echo "   - Peça para eles executarem:\n";
    echo "     GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'%';\n";
    echo "     FLUSH PRIVILEGES;\n\n";
    
    echo "4. Verificar se há alguma ferramenta específica do hosting:\n";
    echo "   - Procure por 'Database Users' ou 'MySQL Users'\n";
    echo "   - Pode estar em 'Advanced' ou 'Developer Tools'\n";
}

