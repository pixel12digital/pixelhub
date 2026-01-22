<?php

/**
 * Migration: Previne duplicação de conversas por remote_key
 * 
 * CORREÇÃO: Adiciona índice único composto (channel_type, remote_key) para prevenir
 * criação de conversas duplicadas quando o mesmo contato aparece com identificadores
 * diferentes (ex: 169183207809126@lid vs 169183207809126).
 * 
 * IMPORTANTE: Esta migration NÃO remove duplicados existentes automaticamente.
 * Use o script de limpeza separado para resolver casos existentes.
 */

require_once __DIR__ . '/../../src/Core/Env.php';
require_once __DIR__ . '/../../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== MIGRATION: Prevenir duplicação de conversas ===\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Verificar se já existem duplicados
    echo "1) Verificando duplicados existentes...\n";
    
    $duplicates = $pdo->query("
        SELECT channel_type, remote_key, COUNT(*) as count
        FROM conversations
        WHERE remote_key IS NOT NULL 
        AND remote_key != ''
        GROUP BY channel_type, remote_key
        HAVING count > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($duplicates)) {
        echo "   ⚠️  ATENÇÃO: Encontrados " . count($duplicates) . " grupos de duplicados:\n";
        foreach ($duplicates as $dup) {
            echo "      - channel_type={$dup['channel_type']}, remote_key={$dup['remote_key']}, count={$dup['count']}\n";
        }
        echo "\n   ⚠️  IMPORTANTE: Esta migration NÃO remove duplicados automaticamente.\n";
        echo "   ⚠️  Execute o script de limpeza antes de adicionar o índice único.\n";
        echo "   ⚠️  Continuando sem adicionar índice único para evitar erro...\n\n";
        
        $pdo->rollBack();
        echo "❌ Migration cancelada. Resolva os duplicados primeiro.\n";
        exit(1);
    }
    
    echo "   ✅ Nenhum duplicado encontrado\n\n";
    
    // 2. Verificar se o índice único já existe
    echo "2) Verificando se índice único já existe...\n";
    
    $indexExists = $pdo->query("
        SHOW INDEX FROM conversations 
        WHERE Key_name = 'idx_unique_channel_remote_key'
    ")->fetch();
    
    if ($indexExists) {
        echo "   ✅ Índice único já existe, pulando criação\n\n";
    } else {
        // 3. Adicionar índice único composto
        // NOTA: MariaDB não suporta índices únicos parciais com WHERE
        // Vamos criar índice único simples, mas apenas para registros com remote_key não nulo
        // Isso é feito garantindo que NULL não seja considerado duplicado (comportamento padrão do MySQL/MariaDB)
        echo "3) Adicionando índice único composto (channel_type, remote_key)...\n";
        
        // Primeiro, garante que não há NULLs vazios que possam causar problemas
        $pdo->exec("
            UPDATE conversations 
            SET remote_key = NULL 
            WHERE remote_key = ''
        ");
        
        // Cria índice único composto
        // NOTA: Em MySQL/MariaDB, múltiplos NULLs são permitidos em índice único
        // Mas valores não-nulos devem ser únicos
        $pdo->exec("
            CREATE UNIQUE INDEX idx_unique_channel_remote_key 
            ON conversations(channel_type, remote_key)
        ");
        
        echo "   ✅ Índice único criado com sucesso\n";
        echo "   ℹ️  NOTA: Múltiplos NULLs são permitidos (comportamento padrão)\n\n";
    }
    
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    echo "✅ Migration concluída com sucesso!\n\n";
    
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Erro na migration: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

