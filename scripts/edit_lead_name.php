<?php

/**
 * Script para editar nome de um lead específico
 * 
 * Uso: php scripts/edit_lead_name.php <lead_id> <novo_nome>
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

// Verifica parâmetros
if ($argc < 3) {
    echo "❌ Uso incorreto!\n";
    echo "Forma correta: php scripts/edit_lead_name.php <lead_id> <novo_nome>\n";
    echo "Exemplo: php scripts/edit_lead_name.php 123 'João Silva'\n";
    exit(1);
}

$leadId = (int) $argv[1];
$newName = trim($argv[2]);

if (empty($newName)) {
    echo "❌ O nome não pode estar em branco!\n";
    exit(1);
}

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
    
    echo "=== EDITAR NOME DO LEAD ===\n";
    echo "Lead ID: {$leadId}\n";
    echo "Novo nome: '{$newName}'\n\n";
    
    // 1. Verificar se o lead existe
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email, contact_type
        FROM tenants 
        WHERE id = ? AND contact_type = 'lead'
    ");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo "❌ Lead #{$leadId} não encontrado ou não é um lead!\n";
        exit(1);
    }
    
    echo "📊 Dados atuais:\n";
    echo "- Nome: '" . ($lead['name'] ?: '(em branco)') . "'\n";
    echo "- Telefone: " . ($lead['phone'] ?: '(não informado)') . "\n";
    echo "- Email: " . ($lead['email'] ?: '(não informado)') . "\n";
    echo "- Tipo: {$lead['contact_type']}\n\n";
    
    // 2. Atualizar o nome
    $stmt = $pdo->prepare("
        UPDATE tenants 
        SET name = ?, updated_at = NOW()
        WHERE id = ? AND contact_type = 'lead'
    ");
    
    $result = $stmt->execute([$newName, $leadId]);
    
    if ($result) {
        echo "✅ Nome atualizado com sucesso!\n";
        echo "📋 Novo nome: '{$newName}'\n";
        
        // 3. Verificar atualização
        $stmt = $pdo->prepare("
            SELECT name, updated_at 
            FROM tenants 
            WHERE id = ?
        ");
        $stmt->execute([$leadId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "🕒 Atualizado em: " . $updated['updated_at'] . "\n";
        echo "📋 Nome confirmado: '{$updated['name']}'\n";
        
    } else {
        echo "❌ Falha ao atualizar o nome!\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "❌ ERRO DE CONEXÃO: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
