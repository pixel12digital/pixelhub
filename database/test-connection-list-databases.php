<?php

/**
 * Script para testar conexão com o servidor MySQL e listar bancos disponíveis
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
use PDO;
use PDOException;

echo "=== Teste de Conexão (sem especificar banco) ===\n\n";

// Verifica se o arquivo .env existe
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    echo "✗ Erro: Arquivo .env não encontrado!\n";
    exit(1);
}

try {
    // Carrega variáveis do .env
    Env::load();
    
    // Obtém configurações
    $host = Env::get('DB_HOST', 'localhost');
    $port = Env::get('DB_PORT', '3306');
    $username = Env::get('DB_USER', 'root');
    $password = Env::get('DB_PASS', '');
    $charset = Env::get('DB_CHARSET', 'utf8mb4');
    $database = Env::get('DB_NAME', 'pixel_hub');
    
    echo "Configurações do .env:\n";
    echo "  Host: {$host}\n";
    echo "  Porta: {$port}\n";
    echo "  Usuário: {$username}\n";
    echo "  Senha: " . (empty($password) ? '(vazia)' : '***' . substr($password, -3)) . "\n";
    echo "  Banco esperado: {$database}\n";
    echo "  Charset: {$charset}\n\n";
    
    echo "Testando conexão com o servidor (sem especificar banco)...\n";
    
    // Monta DSN sem especificar banco
    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=%s',
        $host,
        $port,
        $charset
    );
    
    // Tenta conectar
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ];
    
    $startTime = microtime(true);
    $pdo = new PDO($dsn, $username, $password, $options);
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "✓ Conexão com o servidor estabelecida com sucesso!\n";
    echo "  Tempo de conexão: {$connectionTime}ms\n\n";
    
    // Testa algumas queries
    echo "=== Informações do Servidor ===\n\n";
    
    // 1. Versão do MySQL
    $version = $pdo->query('SELECT VERSION() as version')->fetch();
    echo "✓ Versão do MySQL/MariaDB: {$version['version']}\n";
    
    // 2. Usuário atual
    $currentUser = $pdo->query('SELECT USER() as user')->fetch();
    echo "✓ Usuário atual: {$currentUser['user']}\n";
    
    // 3. Lista bancos de dados disponíveis
    echo "\n=== Bancos de Dados Disponíveis ===\n";
    try {
        $databases = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        echo "Total de bancos encontrados: " . count($databases) . "\n\n";
        
        foreach ($databases as $db) {
            $marker = ($db === $database) ? " ← (esperado)" : "";
            echo "  - {$db}{$marker}\n";
        }
        
        // Verifica se o banco esperado existe
        if (in_array($database, $databases)) {
            echo "\n✓ O banco '{$database}' EXISTE no servidor!\n";
        } else {
            echo "\n✗ O banco '{$database}' NÃO EXISTE no servidor!\n";
            echo "  Bancos disponíveis para este usuário estão listados acima.\n";
        }
        
        // Testa permissões
        echo "\n=== Teste de Permissões ===\n";
        if (in_array($database, $databases)) {
            try {
                $pdo->exec("USE `{$database}`");
                echo "✓ Permissão para usar o banco '{$database}': OK\n";
                
                // Tenta listar tabelas
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                echo "✓ Tabelas encontradas: " . count($tables) . "\n";
                
                if (count($tables) > 0) {
                    echo "\n  Tabelas:\n";
                    foreach (array_slice($tables, 0, 10) as $table) {
                        echo "    - {$table}\n";
                    }
                    if (count($tables) > 10) {
                        echo "    ... e mais " . (count($tables) - 10) . " tabela(s)\n";
                    }
                }
            } catch (PDOException $e) {
                echo "✗ Erro ao acessar o banco '{$database}':\n";
                echo "  Código: {$e->getCode()}\n";
                echo "  Mensagem: {$e->getMessage()}\n";
                echo "\n  O banco existe, mas o usuário não tem permissão para acessá-lo.\n";
                echo "  É necessário conceder permissões no servidor MySQL:\n";
                echo "  GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'%';\n";
                echo "  FLUSH PRIVILEGES;\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "✗ Erro ao listar bancos: {$e->getMessage()}\n";
        echo "  O usuário pode não ter permissão para listar bancos.\n";
    }
    
    echo "\n✓ Teste concluído!\n";
    
} catch (PDOException $e) {
    echo "\n✗ Erro ao conectar:\n";
    echo "  Código: {$e->getCode()}\n";
    echo "  Mensagem: {$e->getMessage()}\n\n";
    
    // Dicas de troubleshooting
    echo "Dicas de solução:\n";
    if (strpos($e->getMessage(), 'Access denied') !== false && strpos($e->getMessage(), 'password') !== false) {
        echo "  - Erro 1045: Usuário ou senha incorretos\n";
        echo "  - Verifique DB_USER e DB_PASS no arquivo .env\n";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false || 
              strpos($e->getMessage(), 'timed out') !== false) {
        echo "  - Não foi possível conectar ao servidor {$host}:{$port}\n";
        echo "  - Verifique se o servidor está online\n";
        echo "  - Verifique se o firewall permite conexões na porta {$port}\n";
        echo "  - Verifique se o IP está permitido no servidor MySQL\n";
    } else {
        echo "  - Verifique todas as configurações no arquivo .env\n";
        echo "  - Verifique se o servidor MySQL está rodando\n";
    }
    
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro inesperado:\n";
    echo "  " . $e->getMessage() . "\n";
    exit(1);
}

