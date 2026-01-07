<?php

/**
 * Script de investigação: Duplicidades e divergências na contagem de tarefas dos blocos
 * 
 * Este script investiga:
 * 1. Duplicidades na tabela agenda_block_tasks (mesmo bloco_id + task_id repetido)
 * 2. Tarefas soft-deletadas que podem estar sendo contadas incorretamente
 * 3. Divergências entre contagem e listagem para um bloco específico
 * 
 * USO:
 *   php database/investigate-block-tasks.php [block_id]
 * 
 * Exemplo:
 *   php database/investigate-block-tasks.php 1
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

echo "=== Investigação: Duplicidades e Divergências na Contagem de Tarefas ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a coluna deleted_at existe
    $hasDeletedAt = false;
    try {
        $checkStmt = $db->query("SHOW COLUMNS FROM tasks LIKE 'deleted_at'");
        $hasDeletedAt = $checkStmt->rowCount() > 0;
        echo "✓ Coluna 'deleted_at' existe: " . ($hasDeletedAt ? 'SIM' : 'NÃO') . "\n\n";
    } catch (\PDOException $e) {
        echo "⚠ Erro ao verificar coluna deleted_at: " . $e->getMessage() . "\n\n";
    }
    
    // 1. INVESTIGAÇÃO DE DUPLICIDADES GLOBAIS
    echo "=== 1. Duplicidades na Tabela agenda_block_tasks ===\n";
    $stmt = $db->query("
        SELECT 
            bloco_id,
            task_id,
            COUNT(*) as ocorrencias
        FROM agenda_block_tasks
        GROUP BY bloco_id, task_id
        HAVING COUNT(*) > 1
        ORDER BY ocorrencias DESC, bloco_id ASC, task_id ASC
    ");
    $duplicidades = $stmt->fetchAll();
    
    if (empty($duplicidades)) {
        echo "✓ Nenhuma duplicidade encontrada na pivot.\n\n";
    } else {
        echo "⚠ Encontradas " . count($duplicidades) . " duplicidades:\n\n";
        foreach ($duplicidades as $dup) {
            echo "  Bloco ID: {$dup['bloco_id']}, Task ID: {$dup['task_id']}, Ocorrências: {$dup['ocorrencias']}\n";
        }
        echo "\n";
    }
    
    // 2. INVESTIGAÇÃO DE UM BLOCO ESPECÍFICO (se fornecido)
    $blockId = isset($argv[1]) ? (int)$argv[1] : null;
    
    if ($blockId) {
        echo "=== 2. Investigação do Bloco ID: {$blockId} ===\n\n";
        
        // Busca informações do bloco
        $stmt = $db->prepare("
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.id = ?
        ");
        $stmt->execute([$blockId]);
        $bloco = $stmt->fetch();
        
        if (!$bloco) {
            echo "✗ Bloco ID {$blockId} não encontrado.\n\n";
        } else {
            echo "Informações do Bloco:\n";
            echo "  Data: {$bloco['data']}\n";
            echo "  Horário: {$bloco['hora_inicio']} - {$bloco['hora_fim']}\n";
            echo "  Tipo: {$bloco['tipo_nome']} ({$bloco['tipo_codigo']})\n";
            echo "  Status: {$bloco['status']}\n\n";
            
            // 2.1. Todas as linhas da pivot para este bloco
            echo "2.1. Todas as linhas na pivot (agenda_block_tasks):\n";
            $stmt = $db->prepare("
                SELECT 
                    abt.id as pivot_id,
                    abt.bloco_id,
                    abt.task_id,
                    t.title as task_title,
                    t.status as task_status,
                    t.deleted_at
                FROM agenda_block_tasks abt
                LEFT JOIN tasks t ON abt.task_id = t.id
                WHERE abt.bloco_id = ?
                ORDER BY abt.task_id ASC, abt.id ASC
            ");
            $stmt->execute([$blockId]);
            $pivotRows = $stmt->fetchAll();
            
            if (empty($pivotRows)) {
                echo "  Nenhuma linha encontrada.\n\n";
            } else {
                echo "  Total de linhas na pivot: " . count($pivotRows) . "\n\n";
                foreach ($pivotRows as $row) {
                    $deletedInfo = $row['deleted_at'] ? " (DELETADA: {$row['deleted_at']})" : "";
                    echo "  - Pivot ID: {$row['pivot_id']}, Task ID: {$row['task_id']}, Título: {$row['task_title']}, Status: {$row['task_status']}{$deletedInfo}\n";
                }
                echo "\n";
            }
            
            // 2.2. Contagem ANTIGA (sem filtros)
            echo "2.2. Contagem ANTIGA (COUNT(*) sem filtros):\n";
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_block_tasks WHERE bloco_id = ?");
            $stmt->execute([$blockId]);
            $oldCount = $stmt->fetch();
            echo "  Resultado: {$oldCount['count']} tarefa(s)\n\n";
            
            // 2.3. Contagem NOVA (com filtros - mesma lógica de getTasksByBlock)
            echo "2.3. Contagem NOVA (COUNT(DISTINCT) + filtro deleted_at):\n";
            if ($hasDeletedAt) {
                $stmt = $db->prepare("
                    SELECT COUNT(DISTINCT abt.task_id) as count
                    FROM agenda_block_tasks abt
                    INNER JOIN tasks t ON abt.task_id = t.id
                    WHERE abt.bloco_id = ? AND t.deleted_at IS NULL
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT COUNT(DISTINCT abt.task_id) as count
                    FROM agenda_block_tasks abt
                    INNER JOIN tasks t ON abt.task_id = t.id
                    WHERE abt.bloco_id = ?
                ");
            }
            $stmt->execute([$blockId]);
            $newCount = $stmt->fetch();
            echo "  Resultado: {$newCount['count']} tarefa(s)\n\n";
            
            // 2.4. Listagem de tarefas (mesma query de getTasksByBlock)
            echo "2.4. Listagem de tarefas (getTasksByBlock):\n";
            if ($hasDeletedAt) {
                $stmt = $db->prepare("
                    SELECT 
                        t.*,
                        p.name as project_name
                    FROM tasks t
                    INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                    INNER JOIN projects p ON t.project_id = p.id
                    WHERE abt.bloco_id = ? AND t.deleted_at IS NULL
                    ORDER BY t.status ASC, t.`order` ASC
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT 
                        t.*,
                        p.name as project_name
                    FROM tasks t
                    INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                    INNER JOIN projects p ON t.project_id = p.id
                    WHERE abt.bloco_id = ?
                    ORDER BY t.status ASC, t.`order` ASC
                ");
            }
            $stmt->execute([$blockId]);
            $tasks = $stmt->fetchAll();
            
            echo "  Total de tarefas na listagem: " . count($tasks) . "\n\n";
            foreach ($tasks as $task) {
                echo "  - Task ID: {$task['id']}, Título: {$task['title']}, Status: {$task['status']}\n";
            }
            echo "\n";
            
            // 2.5. Análise de divergência
            echo "2.5. Análise de Divergência:\n";
            $oldCountInt = (int)$oldCount['count'];
            $newCountInt = (int)$newCount['count'];
            $listCount = count($tasks);
            
            echo "  Contagem ANTIGA: {$oldCountInt}\n";
            echo "  Contagem NOVA: {$newCountInt}\n";
            echo "  Listagem: {$listCount}\n\n";
            
            if ($newCountInt === $listCount) {
                echo "  ✓ Contagem NOVA e Listagem estão alinhadas!\n";
            } else {
                echo "  ✗ Divergência entre contagem NOVA ({$newCountInt}) e listagem ({$listCount})\n";
            }
            
            if ($oldCountInt !== $newCountInt) {
                $diff = $oldCountInt - $newCountInt;
                echo "  ⚠ Diferença entre contagem ANTIGA e NOVA: {$diff} tarefa(s)\n";
                if ($diff > 0) {
                    echo "     (Provavelmente tarefas deletadas ou duplicidades)\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "=== 2. Investigação de Bloco Específico ===\n";
        echo "Para investigar um bloco específico, execute:\n";
        echo "  php database/investigate-block-tasks.php [block_id]\n";
        echo "Exemplo: php database/investigate-block-tasks.php 1\n\n";
    }
    
    // 3. SCRIPT DE LIMPEZA (apenas mostra, não executa)
    if (!empty($duplicidades)) {
        echo "=== 3. Script de Limpeza de Duplicidades (NÃO EXECUTADO) ===\n\n";
        echo "Para limpar duplicidades, você pode executar manualmente:\n\n";
        echo "-- Manter apenas a primeira ocorrência de cada par (bloco_id, task_id):\n";
        echo "-- DELETE abt1 FROM agenda_block_tasks abt1\n";
        echo "-- INNER JOIN agenda_block_tasks abt2\n";
        echo "-- WHERE abt1.bloco_id = abt2.bloco_id\n";
        echo "--   AND abt1.task_id = abt2.task_id\n";
        echo "--   AND abt1.id > abt2.id;\n\n";
        echo "ATENÇÃO: Faça backup antes de executar qualquer DELETE!\n\n";
    }
    
    echo "=== Fim da Investigação ===\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}










