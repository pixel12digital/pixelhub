<?php

/**
 * Script para verificar se a tabela communication_events existe e está correta
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
use PixelHub\Core\DB;

Env::load();

echo "=== Verificação: communication_events ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a tabela existe
    echo "1. Verificando se a tabela existe...\n";
    $checkStmt = $db->query("SHOW TABLES LIKE 'communication_events'");
    if ($checkStmt->rowCount() === 0) {
        echo "   ✗ ERRO: Tabela communication_events NÃO existe!\n\n";
        echo "   SOLUÇÃO: Execute a migration:\n";
        echo "   php database/migrate.php\n\n";
        exit(1);
    }
    echo "   ✓ Tabela communication_events existe\n\n";
    
    // Verifica estrutura da tabela
    echo "2. Verificando estrutura da tabela...\n";
    $columns = $db->query("SHOW COLUMNS FROM communication_events")->fetchAll(PDO::FETCH_ASSOC);
    echo "   Colunas encontradas: " . count($columns) . "\n";
    
    $requiredColumns = [
        'event_id',
        'idempotency_key',
        'event_type',
        'source_system',
        'tenant_id',
        'trace_id',
        'correlation_id',
        'payload',
        'metadata',
        'status',
        'created_at',
        'updated_at'
    ];
    
    $existingColumns = array_column($columns, 'Field');
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    
    if (!empty($missingColumns)) {
        echo "   ✗ Colunas faltando: " . implode(', ', $missingColumns) . "\n\n";
        echo "   SOLUÇÃO: Recrie a tabela executando a migration novamente\n";
        exit(1);
    }
    echo "   ✓ Todas as colunas necessárias estão presentes\n\n";
    
    // Verifica se a migration foi executada
    echo "3. Verificando se a migration foi executada...\n";
    $migrationName = '20250201_create_communication_events_table';
    $stmt = $db->prepare("SELECT migration_name, run_at FROM migrations WHERE migration_name = ?");
    $stmt->execute([$migrationName]);
    $migration = $stmt->fetch();
    
    if ($migration) {
        echo "   ✓ Migration '{$migrationName}' executada em: {$migration['run_at']}\n\n";
    } else {
        echo "   ⚠ AVISO: Migration '{$migrationName}' não está registrada na tabela migrations\n";
        echo "   (Mas a tabela existe, então pode ter sido criada manualmente)\n\n";
    }
    
    // Testa inserção de um evento de teste
    echo "4. Testando inserção de evento...\n";
    try {
        $testEventId = 'test-' . uniqid();
        $testIdempotencyKey = 'test:' . time();
        
        $testStmt = $db->prepare("
            INSERT INTO communication_events 
            (event_id, idempotency_key, event_type, source_system, tenant_id, 
             trace_id, correlation_id, payload, metadata, status, created_at, updated_at)
            VALUES (?, ?, 'test.check', 'check_script', NULL, ?, NULL, ?, NULL, 'queued', NOW(), NOW())
        ");
        
        $testTraceId = 'test-trace-' . uniqid();
        $testPayload = json_encode(['test' => true, 'message' => 'Teste de inserção']);
        
        $testStmt->execute([
            $testEventId,
            $testIdempotencyKey,
            $testTraceId,
            $testPayload
        ]);
        
        echo "   ✓ Inserção de teste bem-sucedida\n\n";
        
        // Remove o registro de teste
        $deleteStmt = $db->prepare("DELETE FROM communication_events WHERE event_id = ?");
        $deleteStmt->execute([$testEventId]);
        echo "   ✓ Registro de teste removido\n\n";
        
    } catch (\PDOException $e) {
        echo "   ✗ ERRO ao inserir evento de teste: " . $e->getMessage() . "\n";
        echo "   SQL State: " . $e->getCode() . "\n\n";
        exit(1);
    }
    
    // Verifica versão do MySQL
    echo "5. Verificando versão do MySQL...\n";
    $version = $db->query("SELECT VERSION() as version")->fetch()['version'];
    echo "   Versão: {$version}\n";
    
    // MySQL 5.7+ suporta tipo JSON nativo
    $versionParts = explode('.', $version);
    $majorVersion = (int)$versionParts[0];
    $minorVersion = (int)$versionParts[1];
    
    if ($majorVersion < 5 || ($majorVersion === 5 && $minorVersion < 7)) {
        echo "   ⚠ AVISO: MySQL versão < 5.7 pode não suportar tipo JSON nativo\n";
        echo "   Isso pode causar problemas com os campos payload e metadata\n\n";
    } else {
        echo "   ✓ Versão do MySQL suporta tipo JSON nativo\n\n";
    }
    
    // Conta eventos existentes
    echo "6. Estatísticas da tabela...\n";
    $countStmt = $db->query("SELECT COUNT(*) as total FROM communication_events");
    $total = $countStmt->fetch()['total'];
    echo "   Total de eventos: {$total}\n";
    
    if ($total > 0) {
        $recentStmt = $db->query("
            SELECT event_type, COUNT(*) as count 
            FROM communication_events 
            GROUP BY event_type 
            ORDER BY count DESC 
            LIMIT 5
        ");
        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Tipos de eventos mais comuns:\n";
        foreach ($recent as $row) {
            echo "     - {$row['event_type']}: {$row['count']}\n";
        }
    }
    
    echo "\n=== Resumo ===\n";
    echo "✓ Tabela communication_events está OK e funcionando!\n";
    echo "✓ Pronto para simular webhooks\n\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

