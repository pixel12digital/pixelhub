<?php

/**
 * Script para identificar e editar Lead #2
 */

// Carrega autoload do framework
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

// Carrega configurações do .env
Env::load();

// Obtém configurações do banco
$host = Env::get('DB_HOST', 'localhost');
$port = Env::get('DB_PORT', '3306');
$database = Env::get('DB_NAME', 'pixelhub');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');

try {
    // Conexão com o banco
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== IDENTIFICAR LEAD #2 ===\n\n";
    
    // 1. Buscar todos os leads migrados (ordenados por ID)
    $stmt = $pdo->query("
        SELECT id, name, phone, email, source, original_lead_id, created_at
        FROM tenants 
        WHERE contact_type = 'lead' 
        ORDER BY id ASC
    ");
    
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($leads)) {
        echo "❌ Nenhum lead encontrado na tabela tenants\n";
        exit;
    }
    
    echo "📊 Leads encontrados (ordenados por ID):\n";
    foreach ($leads as $i => $lead) {
        $num = $i + 1;
        echo "{$num}. ID: {$lead['id']} - Nome: '" . ($lead['name'] ?: '(em branco)') . "' - Tel: " . ($lead['phone'] ?: '(não informado)') . "\n";
    }
    
    // 2. Identificar qual é o #2
    if (count($leads) >= 2) {
        $lead2 = $leads[1]; // Índice 1 = segundo lead
        echo "\n🎯 Lead #2 identificado:\n";
        echo "ID: {$lead2['id']}\n";
        echo "Nome atual: '" . ($lead2['name'] ?: '(em branco)') . "'\n";
        echo "Telefone: " . ($lead2['phone'] ?: '(não informado)') . "\n";
        echo "Email: " . ($lead2['email'] ?: '(não informado)') . "\n";
        echo "Fonte: {$lead2['source']}\n";
        echo "Original Lead ID: {$lead2['original_lead_id']}\n";
        
        // 3. Oferecer opção para editar
        echo "\n📝 Para editar o nome, execute:\n";
        echo "php scripts/edit_lead_name.php {$lead2['id']} 'Novo Nome do Lead'\n";
        
    } else {
        echo "\n❌ Não há leads suficientes para identificar o #2\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERRO DE CONEXÃO: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
