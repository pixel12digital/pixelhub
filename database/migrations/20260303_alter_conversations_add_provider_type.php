<?php
/**
 * Migration: Adiciona provider_type em conversations
 * 
 * Permite identificar qual provider (WPPConnect ou Meta Official API) 
 * foi usado para criar a conversa, garantindo que respostas usem o mesmo provider
 */

require_once __DIR__ . '/../../src/Core/Env.php';
require_once __DIR__ . '/../../src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
    $db = DB::getConnection();
    
    echo "=== Migration: Adicionar provider_type em conversations ===\n\n";
    
    // Verifica se coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM conversations LIKE 'provider_type'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✓ Coluna provider_type já existe\n";
    } else {
        echo "Adicionando coluna provider_type...\n";
        $db->exec("
            ALTER TABLE conversations 
            ADD COLUMN provider_type ENUM('wppconnect', 'meta_official') DEFAULT 'wppconnect' 
            AFTER channel_id
        ");
        echo "✓ Coluna provider_type adicionada\n";
    }
    
    // Atualiza conversas existentes que vieram via Meta
    echo "\nAtualizando conversas Meta existentes...\n";
    $stmt = $db->exec("
        UPDATE conversations c
        INNER JOIN communication_events ce ON ce.conversation_id = c.id
        SET c.provider_type = 'meta_official'
        WHERE ce.source_system = 'meta_official'
        AND c.provider_type = 'wppconnect'
    ");
    echo "✓ {$stmt} conversa(s) Meta atualizada(s)\n";
    
    // Adiciona índice para performance
    echo "\nAdicionando índice...\n";
    try {
        $db->exec("ALTER TABLE conversations ADD INDEX idx_provider_type (provider_type)");
        echo "✓ Índice adicionado\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "✓ Índice já existe\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n=== Migration concluída com sucesso! ===\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
