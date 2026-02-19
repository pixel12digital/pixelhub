<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGANDO TRANSCRIÇÃO DE ÁUDIOS E CONTEXTO COMPLETO ===\n\n";

// 1. Verifica tabelas existentes
echo "1. VERIFICANDO TABELAS EXISTENTES:\n";
$stmt = $db->prepare('SHOW TABLES');
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$relevantTables = [];
foreach ($tables as $table) {
    if (strpos($table, 'message') !== false || 
        strpos($table, 'communication') !== false ||
        strpos($table, 'conversation') !== false ||
        strpos($table, 'audio') !== false ||
        strpos($table, 'transcript') !== false) {
        $relevantTables[] = $table;
    }
}

if (!empty($relevantTables)) {
    echo "✅ Tabelas relevantes encontradas:\n";
    foreach ($relevantTables as $table) {
        echo "   - {$table}\n";
    }
} else {
    echo "❌ Nenhuma tabela relevante encontrada\n";
}

// 2. Verifica estrutura das tabelas relevantes
echo "\n2. VERIFICANDO ESTRUTURA DAS TABELAS:\n";

foreach ($relevantTables as $table) {
    try {
        $stmt = $db->prepare("DESCRIBE {$table}");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n--- Tabela: {$table} ---\n";
        
        $audioColumns = [];
        $messageColumns = [];
        $contentColumns = [];
        
        foreach ($columns as $col) {
            $field = $col['Field'];
            if (stripos($field, 'audio') !== false) {
                $audioColumns[] = $field;
            }
            if (stripos($field, 'message') !== false || stripos($field, 'content') !== false) {
                $messageColumns[] = $field;
            }
            if (stripos($field, 'transcript') !== false || stripos($field, 'text') !== false) {
                $contentColumns[] = $field;
            }
        }
        
        if (!empty($audioColumns)) {
            echo "   ✅ Colunas de áudio: " . implode(', ', $audioColumns) . "\n";
        }
        if (!empty($messageColumns)) {
            echo "   ✅ Colunas de mensagem: " . implode(', ', $messageColumns) . "\n";
        }
        if (!empty($contentColumns)) {
            echo "   ✅ Colunas de conteúdo/transcrição: " . implode(', ', $contentColumns) . "\n";
        }
        
        if (empty($audioColumns) && empty($messageColumns) && empty($contentColumns)) {
            echo "   ⚠️  Nenhuma coluna relevante encontrada\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erro ao verificar tabela {$table}: " . $e->getMessage() . "\n";
    }
}

// 3. Verifica se há mensagens com áudio
echo "\n3. VERIFICANDO MENSAGENS COM ÁUDIOS:\n";

foreach ($relevantTables as $table) {
    try {
        // Tenta diferentes abordagens para encontrar áudios
        $queries = [
            "SELECT COUNT(*) as total FROM {$table} WHERE message LIKE '%audio%'",
            "SELECT COUNT(*) as total FROM {$table} WHERE content_type LIKE '%audio%'",
            "SELECT COUNT(*) as total FROM {$table} WHERE attachment_url LIKE '%audio%'",
            "SELECT COUNT(*) as total FROM {$table} WHERE media_type LIKE '%audio%'"
        ];
        
        foreach ($queries as $query) {
            try {
                $stmt = $db->prepare($query);
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($count['total'] > 0) {
                    echo "   ✅ {$table}: {$count['total']} mensagens com áudio encontradas\n";
                    
                    // Pega exemplos
                    $exampleQuery = str_replace('COUNT(*) as total', 'id, message, content_type, attachment_url, media_type', $query) . ' LIMIT 2';
                    $stmt = $db->prepare($exampleQuery);
                    $stmt->execute();
                    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($examples as $example) {
                        echo "     Exemplo: " . json_encode($example, JSON_UNESCAPED_UNICODE) . "\n";
                    }
                    break;
                }
            } catch (Exception $e) {
                // Query inválida para esta tabela, continua
            }
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erro ao buscar áudios em {$table}: " . $e->getMessage() . "\n";
    }
}

// 4. Verifica funcionalidade de transcrição existente
echo "\n4. VERIFICANDO FUNCIONALIDADE DE TRANSCRIÇÃO:\n";

$transcriptionFiles = [
    'src/Services/AudioTranscriptionService.php',
    'src/Services/SpeechToTextService.php',
    'src/Services/WhisperService.php',
    'src/Helpers/AudioHelper.php',
    'src/Core/AudioTranscription.php',
    'src/Services/OpenAIService.php' // Pode ter transcrição
];

foreach ($transcriptionFiles as $file) {
    if (file_exists($file)) {
        echo "✅ Arquivo encontrado: {$file}\n";
        
        // Verifica conteúdo
        $content = file_get_contents($file);
        
        if (strpos($content, 'transcript') !== false) {
            echo "   ✅ Contém funcionalidade de transcrição\n";
        }
        if (strpos($content, 'whisper') !== false) {
            echo "   ✅ Contém Whisper API\n";
        }
        if (strpos($content, 'audio') !== false) {
            echo "   ✅ Contém tratamento de áudio\n";
        }
    } else {
        echo "❌ Arquivo não encontrado: {$file}\n";
    }
}

// 5. Verifica como o histórico é usado atualmente
echo "\n5. VERIFICANDO USO DE HISTÓRICO ATUAL:\n";

$serviceFile = 'src/Services/AISuggestReplyService.php';
if (file_exists($serviceFile)) {
    echo "✅ AISuggestReplyService encontrado\n";
    
    $content = file_get_contents($serviceFile);
    
    // Busca por funções relevantes
    $functions = [
        'buildUserPrompt' => strpos($content, 'function buildUserPrompt'),
        'buildChatSystemPrompt' => strpos($content, 'function buildChatSystemPrompt'),
        'chat' => strpos($content, 'function chat'),
        'suggestChat' => strpos($content, 'function suggestChat')
    ];
    
    foreach ($functions as $func => $found) {
        $status = $found !== false ? '✅' : '❌';
        echo "   {$status} {$func}\n";
    }
    
    // Verifica se conversation_history é usado
    if (strpos($content, 'conversation_history') !== false) {
        echo "   ✅ conversation_history é usado\n";
        
        // Busca o trecho relevante
        $pattern = '/conversation_history.*?\]/s';
        if (preg_match($pattern, $content, $matches)) {
            echo "   📄 Trecho encontrado: " . substr($matches[0], 0, 200) . "...\n";
        }
    } else {
        echo "   ❌ conversation_history não encontrado\n";
    }
}

// 6. Plano de implementação
echo "\n6. PLANO DE IMPLEMENTAÇÃO - TRANSCRIÇÃO NO CONTEXTO:\n";

$implementationSteps = [
    '1. Identificar mensagens com áudio na conversa',
    '2. Verificar se áudio já foi transcrito',
    '3. Transcrever áudios usando API (se necessário)',
    '4. Incluir transcrição no conversation_history',
    '5. Passar contexto completo (texto + transcrições) para IA',
    '6. IA usa contexto real para gerar propostas inteligentes'
];

foreach ($implementationSteps as $step) {
    echo "✅ {$step}\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo "🔍 ANÁLISE NECESSÁRIA:\n";
echo "1. Mapear estrutura real de mensagens com áudio\n";
echo "2. Verificar se transcrição já existe\n";
echo "3. Implementar leitura de áudios no histórico\n";
echo "4. Integrar transcrição no contexto da IA\n";
echo "5. Testar inteligência contextual real\n\n";

echo "📋 PRÓXIMA AÇÃO:\n";
echo "Investigar estrutura exata das tabelas e implementar transcrição no contexto\n";

?>
