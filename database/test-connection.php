<?php

/**
 * Script para testar conexão com o banco de dados remoto
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

echo "=== Teste de Conexão com Banco de Dados ===\n\n";

// Verifica se o arquivo .env existe
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    echo "✗ Erro: Arquivo .env não encontrado!\n";
    echo "   Caminho esperado: {$envPath}\n\n";
    echo "Por favor, crie o arquivo .env na raiz do projeto com as configurações:\n";
    echo "DB_HOST=seu_host\n";
    echo "DB_PORT=3306\n";
    echo "DB_NAME=nome_do_banco\n";
    echo "DB_USER=seu_usuario\n";
    echo "DB_PASS=sua_senha\n";
    exit(1);
}

try {
    // Carrega variáveis do .env
    Env::load();
    
    // Obtém configurações
    $host = Env::get('DB_HOST', 'localhost');
    $port = Env::get('DB_PORT', '3306');
    $database = Env::get('DB_NAME', 'pixel_hub');
    $username = Env::get('DB_USER', 'root');
    $password = Env::get('DB_PASS', '');
    $charset = Env::get('DB_CHARSET', 'utf8mb4');
    
    echo "Configurações do .env:\n";
    echo "  Host: {$host}\n";
    echo "  Porta: {$port}\n";
    echo "  Banco: {$database}\n";
    echo "  Usuário: {$username}\n";
    echo "  Senha: " . (empty($password) ? '(vazia)' : '***' . substr($password, -3)) . "\n";
    echo "  Charset: {$charset}\n\n";
    
    echo "Testando conexão...\n";
    
    // Monta DSN
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );
    
    // Tenta conectar
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10, // Timeout de 10 segundos
    ];
    
    $startTime = microtime(true);
    $pdo = new PDO($dsn, $username, $password, $options);
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "✓ Conexão estabelecida com sucesso!\n";
    echo "  Tempo de conexão: {$connectionTime}ms\n\n";
    
    // Testa algumas queries
    echo "=== Testes Adicionais ===\n\n";
    
    // 1. Versão do MySQL
    $version = $pdo->query('SELECT VERSION() as version')->fetch();
    echo "✓ Versão do MySQL/MariaDB: {$version['version']}\n";
    
    // 2. Banco atual
    $currentDb = $pdo->query('SELECT DATABASE() as db')->fetch();
    echo "✓ Banco atual: {$currentDb['db']}\n";
    
    // 3. Lista tabelas
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tabelas encontradas: " . count($tables) . "\n";
    
    if (count($tables) > 0) {
        echo "\n  Tabelas:\n";
        foreach ($tables as $table) {
            echo "    - {$table}\n";
        }
    }
    
    // 4. Testa charset
    $charsetInfo = $pdo->query("SHOW VARIABLES LIKE 'character_set%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "\n✓ Charset do banco: " . ($charsetInfo['character_set_database'] ?? 'N/A') . "\n";
    echo "✓ Charset da conexão: " . ($charsetInfo['character_set_connection'] ?? 'N/A') . "\n";
    
    echo "\n✓ Todos os testes passaram com sucesso!\n";
    
} catch (PDOException $e) {
    echo "\n✗ Erro ao conectar:\n";
    echo "  Código: {$e->getCode()}\n";
    echo "  Mensagem: {$e->getMessage()}\n\n";
    
    // Dicas de troubleshooting
    echo "Dicas de solução:\n";
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "  - Verifique o usuário e senha no arquivo .env\n";
        echo "  - Verifique se o usuário tem permissão para acessar o banco\n";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "  - O banco de dados '{$database}' não existe\n";
        echo "  - Verifique o nome do banco no arquivo .env\n";
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

