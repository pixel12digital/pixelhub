<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== IMPLEMENTANDO TRANSCRIÇÃO DE ÁUDIOS DENTRO DO INBOX ===\n\n";

// 1. Verifica estrutura do INBOX
echo "1. VERIFICANDO ESTRUTURA DO INBOX:\n";

// Busca tabelas relacionadas ao INBOX
$stmt = $db->prepare('SHOW TABLES');
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$inboxTables = [];
foreach ($tables as $table) {
    if (strpos($table, 'inbox') !== false || 
        strpos($table, 'conversation') !== false ||
        strpos($table, 'thread') !== false) {
        $inboxTables[] = $table;
    }
}

if (!empty($inboxTables)) {
    echo "✅ Tabelas do INBOX encontradas:\n";
    foreach ($inboxTables as $table) {
        echo "   - {$table}\n";
    }
} else {
    echo "❌ Nenhuma tabela do INBOX encontrada\n";
}

// 2. Verifica estrutura de conversas do INBOX
echo "\n2. VERIFICANDO CONVERSAS DO INBOX:\n";

if (in_array('conversations', $inboxTables)) {
    try {
        $stmt = $db->prepare("DESCRIBE conversations");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "--- Estrutura da tabela conversations ---\n";
        
        $relevantColumns = [];
        foreach ($columns as $col) {
            $field = $col['Field'];
            if (stripos($field, 'message') !== false || 
                stripos($field, 'audio') !== false ||
                stripos($field, 'media') !== false ||
                stripos($field, 'attachment') !== false ||
                stripos($field, 'content') !== false) {
                $relevantColumns[] = $field . ' (' . $col['Type'] . ')';
            }
        }
        
        if (!empty($relevantColumns)) {
            echo "✅ Colunas relevantes: " . implode(', ', $relevantColumns) . "\n";
        } else {
            echo "⚠️  Nenhuma coluna de mensagem/áudio encontrada\n";
        }
        
        // Verifica se há mensagens com áudio
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM conversations WHERE message LIKE '%audio%' OR attachment_url IS NOT NULL");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($count['total'] > 0) {
            echo "✅ Encontradas {$count['total']} conversas com possível áudio\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao verificar conversations: " . $e->getMessage() . "\n";
    }
}

// 3. Verifica se há tabela de threads/mensagens do INBOX
echo "\n3. VERIFICANDO THREADS/MESSAGES DO INBOX:\n";

$possibleMessageTables = ['inbox_threads', 'inbox_messages', 'thread_messages', 'conversation_messages'];
$foundMessageTable = null;

foreach ($possibleMessageTables as $table) {
    if (in_array($table, $tables)) {
        $foundMessageTable = $table;
        break;
    }
}

if ($foundMessageTable) {
    echo "✅ Tabela de mensagens encontrada: {$foundMessageTable}\n";
    
    try {
        $stmt = $db->prepare("DESCRIBE {$foundMessageTable}");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $audioColumns = [];
        $messageColumns = [];
        
        foreach ($columns as $col) {
            $field = $col['Field'];
            if (stripos($field, 'audio') !== false) {
                $audioColumns[] = $field;
            }
            if (stripos($field, 'message') !== false || stripos($field, 'content') !== false) {
                $messageColumns[] = $field;
            }
        }
        
        if (!empty($audioColumns)) {
            echo "✅ Colunas de áudio: " . implode(', ', $audioColumns) . "\n";
        }
        if (!empty($messageColumns)) {
            echo "✅ Colunas de mensagem: " . implode(', ', $messageColumns) . "\n";
        }
        
        // Busca mensagens com áudio
        if (!empty($audioColumns)) {
            $audioColumn = $audioColumns[0];
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM {$foundMessageTable} WHERE {$audioColumn} IS NOT NULL");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($count['total'] > 0) {
                echo "✅ Encontradas {$count['total']} mensagens com áudio\n";
                
                // Pega exemplos
                $stmt = $db->prepare("SELECT id, message, {$audioColumn} FROM {$foundMessageTable} WHERE {$audioColumn} IS NOT NULL LIMIT 2");
                $stmt->execute();
                $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($examples as $example) {
                    echo "   Exemplo ID {$example['id']}: " . substr($example['message'] ?? 'Sem mensagem', 0, 50) . "...\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao verificar {$foundMessageTable}: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Nenhuma tabela de threads/mensagens encontrada\n";
}

// 4. Verifica como o INBOX carrega mensagens no frontend
echo "\n4. VERIFICANDO CARREGAMENTO DE MENSAGENS NO INBOX:\n";

$mainFile = 'views/layout/main.php';
if (file_exists($mainFile)) {
    $content = file_get_contents($mainFile);
    
    // Busca por funções do INBOX
    $inboxFunctions = [
        'loadInboxMessages' => strpos($content, 'loadInboxMessages'),
        'loadInboxThread' => strpos($content, 'loadInboxThread'),
        '_currentInboxMessages' => strpos($content, '_currentInboxMessages'),
        '_currentInboxThread' => strpos($content, '_currentInboxThread')
    ];
    
    foreach ($inboxFunctions as $func => $found) {
        $status = $found !== false ? '✅' : '❌';
        echo "   {$status} {$func}\n";
    }
    
    // Busca por onde as mensagens são carregadas
    if (strpos($content, '_currentInboxMessages') !== false) {
        echo "✅ _currentInboxMessages encontrado no frontend\n";
        
        // Busca o trecho relevante
        $pattern = '/_currentInboxMessages.*?messages.*?\]/s';
        if (preg_match($pattern, $content, $matches)) {
            echo "   📄 Trecho encontrado: " . substr($matches[0], 0, 200) . "...\n";
        }
    }
}

// 5. Verifica como o IA recebe o contexto do INBOX
echo "\n5. VERIFICANDO CONTEXTO DA IA NO INBOX:\n";

$serviceFile = 'src/Services/AISuggestReplyService.php';
if (file_exists($serviceFile)) {
    $content = file_get_contents($serviceFile);
    
    // Busca por como o conversation_history é construído
    if (strpos($content, 'conversation_history') !== false) {
        echo "✅ conversation_history encontrado no serviço\n";
        
        // Busca a função buildUserPrompt
        if (strpos($content, 'function buildUserPrompt') !== false) {
            echo "✅ buildUserPrompt encontrada\n";
            
            // Extrai a função
            $pattern = '/function buildUserPrompt.*?^}/ms';
            if (preg_match($pattern, $content, $matches)) {
                $function = $matches[0];
                echo "   📄 Função buildUserPrompt: " . substr($function, 0, 300) . "...\n";
                
                // Verifica se já trata áudios
                if (strpos($function, 'audio') !== false) {
                    echo "   ✅ Já trata áudios na função\n";
                } else {
                    echo "   ❌ Não trata áudios na função\n";
                }
            }
        }
    }
}

// 6. Plano de implementação focado no INBOX
echo "\n6. PLANO DE IMPLEMENTAÇÃO - TRANSCRIÇÃO NO INBOX:\n";

$implementationPlan = [
    '1. Identificar onde o INBOX carrega as mensagens',
    '2. Verificar se há áudios nas mensagens do INBOX',
    '3. Implementar transcrição de áudios no carregamento',
    '4. Incluir transcrições no conversation_history',
    '5. Passar contexto completo para IA',
    '6. IA usa contexto real do INBOX para propostas'
];

foreach ($implementationPlan as $step) {
    echo "✅ {$step}\n";
}

echo "\n=== FOCO TOTAL NO INBOX ===\n";
echo "🎯 OBJETIVO: Implementar transcrição de áudios diretamente no INBOX\n";
echo "📋 ESCOPO: Apenas estrutura do INBOX, ignorar COMMUNICATION\n";
echo "🔍 PRÓXIMA AÇÃO: Mapear exatamente como o INBOX funciona\n";

?>
