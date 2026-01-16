<?php

/**
 * Migration: Adiciona suporte a remote_key, contact_key, thread_key
 * 
 * Arquitetura: Identidade primária baseada em remote_key ao invés de telefone
 * - remote_key: identidade canônica do contato (lid:xxx, tel:xxx, jid:xxx)
 * - contact_key: provider:session_id:remote_key
 * - thread_key: provider:session_id:remote_key (mesmo que contact_key para WhatsApp)
 */

require_once __DIR__ . '/../../src/Core/Env.php';
require_once __DIR__ . '/../../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== MIGRATION: Adicionar suporte a remote_key ===\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Adicionar colunas em conversations
    echo "1) Adicionando colunas em conversations...\n";
    
    // Verifica se as colunas já existem antes de adicionar
    $checkColumns = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'session_id'")->fetch();
    if (!$checkColumns) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN session_id VARCHAR(128) NULL AFTER channel_id");
    }
    
    $checkColumns = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'contact_key'")->fetch();
    if (!$checkColumns) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN contact_key VARCHAR(255) NULL AFTER contact_external_id");
    }
    
    $checkColumns = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'remote_key'")->fetch();
    if (!$checkColumns) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN remote_key VARCHAR(255) NULL AFTER contact_key");
    }
    
    $checkColumns = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'thread_key'")->fetch();
    if (!$checkColumns) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN thread_key VARCHAR(255) NULL AFTER remote_key");
    }
    
    // Índices (verifica se existem antes de criar)
    $indexes = $pdo->query("SHOW INDEX FROM conversations WHERE Key_name = 'idx_conversations_thread_key'")->fetch();
    if (!$indexes) {
        $pdo->exec("CREATE INDEX idx_conversations_thread_key ON conversations(thread_key)");
    }
    
    $indexes = $pdo->query("SHOW INDEX FROM conversations WHERE Key_name = 'idx_conversations_contact_key'")->fetch();
    if (!$indexes) {
        $pdo->exec("CREATE INDEX idx_conversations_contact_key ON conversations(contact_key)");
    }
    
    $indexes = $pdo->query("SHOW INDEX FROM conversations WHERE Key_name = 'idx_conversations_session_id'")->fetch();
    if (!$indexes) {
        $pdo->exec("CREATE INDEX idx_conversations_session_id ON conversations(session_id)");
    }
    
    $indexes = $pdo->query("SHOW INDEX FROM conversations WHERE Key_name = 'idx_conversations_remote_key'")->fetch();
    if (!$indexes) {
        $pdo->exec("CREATE INDEX idx_conversations_remote_key ON conversations(remote_key)");
    }
    
    echo "   ✅ Colunas adicionadas em conversations\n\n";
    
    // 2. Backfill: preencher session_id com channel_id
    echo "2) Backfill: preenchendo session_id com channel_id...\n";
    
    $pdo->exec("
        UPDATE conversations
        SET session_id = COALESCE(session_id, channel_id)
        WHERE session_id IS NULL AND channel_id IS NOT NULL AND channel_id <> ''
    ");
    
    $updated = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "   ✅ {$updated} conversas atualizadas\n\n";
    
    // 3. Backfill: criar remote_key a partir de contact_external_id
    echo "3) Backfill: criando remote_key a partir de contact_external_id...\n";
    
    $pdo->exec("
        UPDATE conversations
        SET remote_key = CONCAT('tel:', REPLACE(REPLACE(REPLACE(contact_external_id, '@c.us', ''), '@s.whatsapp.net', ''), '@', ''))
        WHERE (remote_key IS NULL OR remote_key = '')
          AND contact_external_id IS NOT NULL
          AND contact_external_id <> ''
          AND contact_external_id NOT LIKE '%@lid%'
          AND contact_external_id NOT LIKE '%@g.us%'
    ");
    
    // Para @lid, criar remote_key como lid:xxx
    $pdo->exec("
        UPDATE conversations
        SET remote_key = CONCAT('lid:', REPLACE(contact_external_id, '@lid', ''))
        WHERE (remote_key IS NULL OR remote_key = '')
          AND contact_external_id LIKE '%@lid%'
    ");
    
    $updated2 = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "   ✅ {$updated2} conversas com remote_key criado\n\n";
    
    // 4. Backfill: criar contact_key e thread_key
    echo "4) Backfill: criando contact_key e thread_key...\n";
    
    $pdo->exec("
        UPDATE conversations
        SET contact_key = CONCAT('wpp_gateway:', COALESCE(session_id, ''), ':', COALESCE(remote_key, '')),
            thread_key = CONCAT('wpp_gateway:', COALESCE(session_id, ''), ':', COALESCE(remote_key, ''))
        WHERE (contact_key IS NULL OR contact_key = '' OR thread_key IS NULL OR thread_key = '')
          AND session_id IS NOT NULL
          AND session_id <> ''
          AND remote_key IS NOT NULL
          AND remote_key <> ''
    ");
    
    $updated3 = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "   ✅ {$updated3} conversas com contact_key/thread_key criados\n\n";
    
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    echo "✅ Migration concluída com sucesso!\n\n";
    
    // Verificar resultado
    $check = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(session_id) as with_session,
            COUNT(remote_key) as with_remote_key,
            COUNT(contact_key) as with_contact_key,
            COUNT(thread_key) as with_thread_key
        FROM conversations
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "Estatísticas:\n";
    echo "  Total conversas: {$check['total']}\n";
    echo "  Com session_id: {$check['with_session']}\n";
    echo "  Com remote_key: {$check['with_remote_key']}\n";
    echo "  Com contact_key: {$check['with_contact_key']}\n";
    echo "  Com thread_key: {$check['with_thread_key']}\n";
    
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Erro na migration: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

