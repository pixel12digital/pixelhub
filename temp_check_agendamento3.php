<?php

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
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

try {
    // Carrega variáveis do .env
    Env::load();
    
    // Obtém configurações
    $host = Env::get('DB_HOST', 'localhost');
    $port = Env::get('DB_PORT', '3306');
    $database = Env::get('DB_NAME', 'pixel_hub');
    $username = Env::get('DB_USER', 'root');
    $password = Env::get('DB_PASS', '');
    
    echo "=== Verificando agendamento do dia 18/02/2026 às 08:00 ===\n";
    echo "Conectando ao banco: {$host}:{$port}/{$database}\n\n";
    
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Buscar em scheduled_messages
    echo "1. Tabela scheduled_messages:\n";
    $sql = "SELECT * FROM scheduled_messages WHERE DATE(scheduled_at) = '2026-02-18' ORDER BY scheduled_at";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "   Nenhuma mensagem encontrada em scheduled_messages.\n";
    } else {
        foreach ($messages as $msg) {
            echo "   ID: {$msg['id']} | Agendado: {$msg['scheduled_at']} | Status: {$msg['status']} | Destinatário: {$msg['recipient_phone']}\n";
            echo "   Conteúdo: " . substr($msg['message_content'], 0, 100) . "...\n\n";
        }
    }
    
    // 2. Buscar em agenda_blocks
    echo "\n2. Tabela agenda_blocks (18/02/2026):\n";
    $sql2 = "SELECT 
        b.id as block_id,
        b.block_date,
        b.start_time,
        b.end_time,
        b.tenant_id,
        t.id as task_id,
        t.title,
        t.description,
        t.status as task_status,
        t.session_id,
        t.scheduled_send_time
    FROM agenda_blocks b
    LEFT JOIN agenda_block_tasks t ON b.id = t.agenda_block_id
    WHERE b.block_date = '2026-02-18'
    ORDER BY b.start_time, t.id";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute();
    $blocks = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($blocks)) {
        echo "   Nenhum bloco encontrado para 18/02/2026.\n";
    } else {
        foreach ($blocks as $block) {
            echo "   Bloco ID: {$block['block_id']} | Data: {$block['block_date']} | Horário: {$block['start_time']} - {$block['end_time']}\n";
            if ($block['task_id']) {
                echo "     Task ID: {$block['task_id']} | Título: {$block['title']}\n";
                echo "     Status: {$block['task_status']} | Sessão: {$block['session_id']} | Envio agendado: {$block['scheduled_send_time']}\n";
                echo "     Descrição: " . substr($block['description'] ?? '', 0, 100) . "...\n";
            }
            echo "\n";
        }
    }
    
    // 3. Buscar por "Viviane" ou "E-commerce"
    echo "\n3. Buscando por 'Viviane' ou 'E-commerce' em qualquer agendamento:\n";
    $sql3 = "SELECT 
        b.id as block_id,
        b.block_date,
        b.start_time,
        t.id as task_id,
        t.title,
        t.description,
        t.status as task_status
    FROM agenda_blocks b
    LEFT JOIN agenda_block_tasks t ON b.id = t.agenda_block_id
    WHERE (t.title LIKE '%Viviane%' OR t.title LIKE '%E-commerce%' OR t.description LIKE '%Viviane%' OR t.description LIKE '%E-commerce%')
    AND b.block_date >= '2026-02-17' AND b.block_date <= '2026-02-19'
    ORDER BY b.block_date, b.start_time";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute();
    $vivianeTasks = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($vivianeTasks)) {
        echo "   Nenhum agendamento encontrado com 'Viviane' ou 'E-commerce'.\n";
    } else {
        foreach ($vivianeTasks as $task) {
            echo "   Bloco ID: {$task['block_id']} | Data: {$task['block_date']} | Horário: {$task['start_time']}\n";
            echo "     Task ID: {$task['task_id']} | Título: {$task['title']}\n";
            echo "     Status: {$task['task_status']}\n";
            echo "     Descrição: " . substr($task['description'] ?? '', 0, 100) . "...\n\n";
        }
    }
    
    // 4. Verificar logs de envio
    echo "\n4. Verificando logs de comunicação (18/02/2026):\n";
    $sql4 = "SELECT 
        id,
        created_at,
        event_type,
        channel_type,
        contact_external_id,
        JSON_EXTRACT(event_data, '$.message.content') as message_content
    FROM communication_events 
    WHERE DATE(created_at) = '2026-02-18'
    AND event_type IN('message.outbound', 'message.sent')
    ORDER BY created_at
    LIMIT 10";
    $stmt4 = $pdo->prepare($sql4);
    $stmt4->execute();
    $logs = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "   Nenhum log de envio encontrado para 18/02/2026.\n";
    } else {
        foreach ($logs as $log) {
            echo "   ID: {$log['id']} | Horário: {$log['created_at']} | Tipo: {$log['event_type']}\n";
            echo "     Contato: {$log['contact_external_id']} | Conteúdo: " . substr($log['message_content'] ?? '', 0, 80) . "...\n\n";
        }
    }
    
    // 5. Verificar agenda_manual_items (follow-ups manuais)
    echo "\n5. Tabela agenda_manual_items (follow-ups manuais):\n";
    $sql5 = "SELECT 
        id,
        scheduled_date,
        scheduled_time,
        title,
        description,
        status,
        lead_id,
        opportunity_id,
        contact_name,
        contact_phone
    FROM agenda_manual_items 
    WHERE DATE(scheduled_date) = '2026-02-18'
    AND (title LIKE '%Viviane%' OR description LIKE '%Viviane%' OR contact_name LIKE '%Viviane%' OR title LIKE '%E-commerce%' OR description LIKE '%E-commerce%')
    ORDER BY scheduled_time";
    $stmt5 = $pdo->prepare($sql5);
    $stmt5->execute();
    $manualItems = $stmt5->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($manualItems)) {
        echo "   Nenhum follow-up manual encontrado para 18/02/2026.\n";
    } else {
        foreach ($manualItems as $item) {
            echo "   ID: {$item['id']} | Agendado: {$item['scheduled_date']} {$item['scheduled_time']}\n";
            echo "   Título: {$item['title']}\n";
            echo "   Contato: {$item['contact_name']} | Fone: {$item['contact_phone']}\n";
            echo "   Status: {$item['status']} | Lead ID: {$item['lead_id']} | Opportunity ID: {$item['opportunity_id']}\n";
            echo "   Descrição: " . substr($item['description'] ?? '', 0, 100) . "...\n\n";
        }
    }
    
    echo "\n=== Verificação concluída ===\n";
    
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
