<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGANDO TRANSCRIÇÃO DE ÁUDIOS E CONTEXTO COMPLETO ===\n\n";

// 1. Verifica se existe tabela de áudios/mensagens
echo "1. VERIFICANDO ESTRUTURA DE MENSAGENS COM ÁUDIOS:\n";

// Verifica tabelas relacionadas a mensagens
$tables = [
    'messages',
    'communication_events',
    'message_attachments',
    'audio_transcriptions',
    'conversation_messages'
];

foreach ($tables as $table) {
    $stmt = $db->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ Tabela encontrada: {$table}\n";
        
        // Verifica estrutura
        $stmt = $db->prepare("DESCRIBE {$table}");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $audioColumns = [];
        foreach ($columns as $col) {
            if (stripos($col['Field'], 'audio') !== false || 
                stripos($col['Field'], 'transcript') !== false ||
                stripos($col['Field'], 'media') !== false ||
                stripos($col['Field'], 'attachment') !== false ||
                stripos($col['Field'], 'content_type') !== false) {
                $audioColumns[] = $col['Field'] . ' (' . $col['Type'] . ')';
            }
        }
        
        if (!empty($audioColumns)) {
            echo "   Colunas de áudio/transcrição: " . implode(', ', $audioColumns) . "\n";
        }
    } else {
        echo "❌ Tabela não encontrada: {$table}\n";
    }
}

// 2. Verifica se há funcionalidade de transcrição
echo "\n2. VERIFICANDO FUNCIONALIDADE DE TRANSCRIÇÃO:\n";

// Busca por arquivos relacionados a transcrição
$transcriptionFiles = [
    'src/Services/AudioTranscriptionService.php',
    'src/Services/SpeechToTextService.php',
    'src/Services/WhisperService.php',
    'src/Helpers/AudioHelper.php',
    'src/Core/AudioTranscription.php'
];

foreach ($transcriptionFiles as $file) {
    if (file_exists($file)) {
        echo "✅ Arquivo encontrado: {$file}\n";
    } else {
        echo "❌ Arquivo não encontrado: {$file}\n";
    }
}

// 3. Verifica se há mensagens com áudio no banco
echo "\n3. VERIFICANDO MENSAGENS COM ÁUDIOS EXISTENTES:\n";

// Tenta encontrar mensagens com conteúdo de áudio
$possibleTables = ['messages', 'communication_events'];
$audioMessagesFound = false;

foreach ($possibleTables as $table) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM {$table} WHERE content_type LIKE '%audio%' OR message LIKE '%audio%' OR attachment_url IS NOT NULL");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($count['total'] > 0) {
            echo "✅ Encontradas {$count['total']} mensagens com áudio em {$table}\n";
            $audioMessagesFound = true;
            
            // Pega alguns exemplos
            $stmt = $db->prepare("SELECT id, message, content_type, attachment_url, created_at FROM {$table} WHERE content_type LIKE '%audio%' OR message LIKE '%audio%' OR attachment_url IS NOT NULL LIMIT 3");
            $stmt->execute();
            $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($examples as $example) {
                echo "   Exemplo: ID {$example['id']} | Content: " . substr($example['message'] ?? 'N/A', 0, 50) . "... | Type: " . ($example['content_type'] ?? 'N/A') . "\n";
            }
        }
    } catch (Exception $e) {
        // Tabela não existe ou erro
    }
}

if (!$audioMessagesFound) {
    echo "❌ Nenhuma mensagem com áudio encontrada\n";
}

// 4. Verifica como o histórico é carregado atualmente
echo "\n4. VERIFICANDO COMO HISTÓRICO É CARREGADO ATUALMENTE:\n";

// Verifica no arquivo AISuggestReplyService
$serviceFile = 'src/Services/AISuggestReplyService.php';
if (file_exists($serviceFile)) {
    $content = file_get_contents($serviceFile);
    
    // Busca por como o conversation_history é usado
    if (strpos($content, 'conversation_history') !== false) {
        echo "✅ conversation_history encontrado no AISuggestReplyService\n";
        
        // Busca por trechos relevantes
        $patterns = [
            'conversation_history' => strpos($content, 'conversation_history'),
            'buildUserPrompt' => strpos($content, 'buildUserPrompt'),
            'buildChatSystemPrompt' => strpos($content, 'buildChatSystemPrompt'),
            'getLearnedExamples' => strpos($content, 'getLearnedExamples')
        ];
        
        foreach ($patterns as $pattern => $found) {
            $status = $found !== false ? '✅' : '❌';
            echo "   {$status} {$pattern}\n";
        }
    } else {
        echo "❌ conversation_history não encontrado\n";
    }
} else {
    echo "❌ AISuggestReplyService não encontrado\n";
}

// 5. Verifica se há API de transcrição configurada
echo "\n5. VERIFICANDO CONFIGURAÇÃO DE TRANSCRIÇÃO:\n";

// Verifica se há configuração para OpenAI Whisper ou similar
$configFiles = [
    'config/ai.php',
    'config/app.php',
    '.env'
];

foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        echo "✅ Config encontrado: {$configFile}\n";
        
        $content = file_get_contents($configFile);
        
        $transcriptionConfigs = [
            'whisper' => strpos($content, 'whisper'),
            'transcription' => strpos($content, 'transcription'),
            'speech_to_text' => strpos($content, 'speech_to_text'),
            'openai_audio' => strpos($content, 'openai_audio')
        ];
        
        foreach ($transcriptionConfigs as $config => $found) {
            $status = $found !== false ? '✅' : '❌';
            echo "   {$status} {$config}\n";
        }
    } else {
        echo "❌ Config não encontrado: {$configFile}\n";
    }
}

// 6. Simula implementação de transcrição no contexto
echo "\n6. SIMULANDO IMPLEMENTAÇÃO DE TRANSCRIÇÃO NO CONTEXTO:\n";

$implementationPlan = [
    '1. Verificar mensagens com áudio na conversa',
    '2. Transcrever áudios usando API (Whisper/OpenAI)',
    '3. Incluir transcrição no conversation_history',
    '4. Passar contexto completo para IA',
    '5. IA usa contexto real para gerar propostas'
];

foreach ($implementationPlan as $step) {
    echo "✅ {$step}\n";
}

echo "\n=== DIAGNÓSTICO ===\n";
echo "🔍 ANÁLISE NECESSÁRIA:\n";
echo "1. Verificar estrutura real de mensagens com áudio\n";
echo "2. Confirmar se transcrição já existe\n";
echo "3. Implementar leitura de áudios no histórico\n";
echo "4. Integrar transcrição no contexto da IA\n";
echo "5. Testar com conversas reais com áudio\n\n";

echo "📋 PRÓXIMOS PASSOS:\n";
echo "1. Investigar estrutura exata das mensagens\n";
echo "2. Verificar se transcrição já está implementada\n";
echo "3. Implementar se necessário\n";
echo "4. Atualizar AISuggestReplyService para incluir áudios\n";
echo "5. Testar inteligência contextual real\n";

?>
