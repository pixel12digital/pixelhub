<?php

/**
 * Script para limpar todas as conversas e mensagens do banco de dados
 * 
 * Este script remove todos os registros das seguintes tabelas:
 * - conversations (conversas)
 * - chat_messages (mensagens de chat)
 * - chat_threads (threads de chat)
 * - communication_events (eventos de comunicação)
 * 
 * ATENÇÃO: Esta operação é IRREVERSÍVEL!
 */

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega variáveis de ambiente
Env::load();

try {
    $db = DB::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Limpeza de Conversas e Mensagens ===\n\n";
    
    // Conta registros antes da limpeza
    echo "Verificando registros existentes...\n";
    
    $tables = [
        'conversations' => 'Conversas',
        'chat_messages' => 'Mensagens de Chat',
        'chat_threads' => 'Threads de Chat',
        'communication_events' => 'Eventos de Comunicação'
    ];
    
    $counts = [];
    foreach ($tables as $table => $label) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as total FROM `{$table}`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $counts[$table] = $result['total'] ?? 0;
            echo "  - {$label}: {$counts[$table]} registros\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "  - {$label}: Tabela não existe (pulando...)\n";
                $counts[$table] = -1; // Marca como não existente
            } else {
                throw $e;
            }
        }
    }
    
    $totalToDelete = array_sum(array_filter($counts, fn($v) => $v > 0));
    
    if ($totalToDelete == 0) {
        echo "\n✅ Nenhum registro encontrado. Nada para limpar.\n";
        exit(0);
    }
    
    echo "\n⚠️  ATENÇÃO: Esta operação irá deletar {$totalToDelete} registros!\n";
    echo "Pressione Ctrl+C para cancelar ou Enter para continuar...\n";
    // $handle = fopen("php://stdin", "r");
    // $line = fgets($handle);
    // fclose($handle);
    
    echo "\nIniciando limpeza...\n\n";
    
    // Desabilita verificação de foreign keys temporariamente
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    try {
        // 1. Limpa mensagens de chat (pode ter FK para chat_threads)
        if (isset($counts['chat_messages']) && $counts['chat_messages'] > 0) {
            echo "Limpando chat_messages...\n";
            $stmt = $db->exec("TRUNCATE TABLE `chat_messages`");
            echo "  ✅ chat_messages limpo\n";
        }
        
        // 2. Limpa threads de chat
        if (isset($counts['chat_threads']) && $counts['chat_threads'] > 0) {
            echo "Limpando chat_threads...\n";
            $stmt = $db->exec("TRUNCATE TABLE `chat_threads`");
            echo "  ✅ chat_threads limpo\n";
        }
        
        // 3. Limpa conversas
        if (isset($counts['conversations']) && $counts['conversations'] > 0) {
            echo "Limpando conversations...\n";
            $stmt = $db->exec("TRUNCATE TABLE `conversations`");
            echo "  ✅ conversations limpo\n";
        }
        
        // 4. Limpa eventos de comunicação
        if (isset($counts['communication_events']) && $counts['communication_events'] > 0) {
            echo "Limpando communication_events...\n";
            $stmt = $db->exec("TRUNCATE TABLE `communication_events`");
            echo "  ✅ communication_events limpo\n";
        }
        
    } finally {
        // Reabilita verificação de foreign keys
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    echo "\n=== Limpeza Concluída ===\n\n";
    
    // Verifica se tudo foi limpo
    echo "Verificando resultado...\n";
    $allClean = true;
    foreach ($tables as $table => $label) {
        if ($counts[$table] == -1) continue; // Pula tabelas que não existem
        
        try {
            $stmt = $db->query("SELECT COUNT(*) as total FROM `{$table}`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $remaining = $result['total'] ?? 0;
            
            if ($remaining > 0) {
                echo "  ⚠️  {$label}: Ainda restam {$remaining} registros\n";
                $allClean = false;
            } else {
                echo "  ✅ {$label}: Limpo com sucesso\n";
            }
        } catch (PDOException $e) {
            echo "  ⚠️  {$label}: Erro ao verificar - {$e->getMessage()}\n";
        }
    }
    
    if ($allClean) {
        echo "\n✅ Todas as conversas e mensagens foram limpas com sucesso!\n";
    } else {
        echo "\n⚠️  Algumas tabelas ainda contêm registros.\n";
    }
    
} catch (PDOException $e) {
    echo "\n❌ ERRO: Falha ao conectar ao banco de dados:\n";
    echo "   {$e->getMessage()}\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERRO: {$e->getMessage()}\n";
    exit(1);
}

