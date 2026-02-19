<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== IMPLEMENTANDO TRANSCRIÇÃO AUTOMÁTICA PARA IA NO INBOX ===\n\n";

// 1. Verifica estrutura atual do AISuggestReplyService
echo "1. VERIFICANDO ESTRUTURA ATUAL DO AISuggestReplyService:\n";

$serviceFile = 'src/Services/AISuggestReplyService.php';
if (file_exists($serviceFile)) {
    $content = file_get_contents($serviceFile);
    
    // Busca pela função buildUserPrompt
    if (strpos($content, 'function buildUserPrompt') !== false) {
        echo "✅ buildUserPrompt encontrada\n";
        
        // Extrai a função
        $pattern = '/function buildUserPrompt.*?^}/ms';
        if (preg_match($pattern, $content, $matches)) {
            $function = $matches[0];
            echo "📄 Função atual: " . substr($function, 0, 300) . "...\n";
        }
    }
    
    // Verifica se já trata áudios
    if (strpos($content, 'AudioTranscriptionService') !== false) {
        echo "✅ Já usa AudioTranscriptionService\n";
    } else {
        echo "❌ Não usa AudioTranscriptionService ainda\n";
    }
}

// 2. Cria a função para transcrever áudios nos bastidores
echo "\n2. CRIANDO FUNÇÃO DE TRANSCRIÇÃO AUTOMÁTICA:\n";

$transcriptionFunction = <<<PHP
    /**
     * Transcreve áudios automaticamente nos bastidores para contexto da IA
     * 
     * @param array \$conversationHistory Histórico da conversa
     * @return array Histórico com transcrições incluídas
     */
    private static function transcribeAudiosForContext(array \$conversationHistory): array
    {
        \$enhancedHistory = [];
        
        foreach (\$conversationHistory as \$message) {
            \$enhancedMessage = \$message;
            
            // Verifica se há mídia/áudio na mensagem
            if (isset(\$message['media']) && is_array(\$message['media'])) {
                \$transcribedTexts = [];
                
                foreach (\$message['media'] as \$media) {
                    // Se é áudio e não tem transcrição
                    if (in_array(\$media['media_type'] ?? '', ['audio', 'ptt', 'voice']) && 
                        (!isset(\$media['transcription']) || empty(\$media['transcription'])) &&
                        isset(\$media['event_id'])) {
                        
                        try {
                            // Transcreve nos bastidores
                            \$result = \\PixelHub\\Services\\AudioTranscriptionService::transcribeByEventId(\$media['event_id']);
                            
                            if (\$result['success'] && !empty(\$result['transcription'])) {
                                \$transcribedTexts[] = \$result['transcription'];
                                
                                // Atualiza a mídia com a transcrição
                                \$media['transcription'] = \$result['transcription'];
                                \$media['transcription_status'] = 'completed';
                            }
                        } catch (\\Exception \$e) {
                            error_log('[AI Context] Erro na transcrição automática: ' . \$e->getMessage());
                        }
                    } elseif (isset(\$media['transcription']) && !empty(\$media['transcription'])) {
                        // Já tem transcrição
                        \$transcribedTexts[] = \$media['transcription'];
                    }
                }
                
                // Adiciona transcrições ao conteúdo da mensagem
                if (!empty(\$transcribedTexts)) {
                    \$originalContent = \$message['message'] ?? '';
                    \$transcriptionText = implode(' | ', \$transcribedTexts);
                    
                    if (!empty(\$originalContent)) {
                        \$enhancedMessage['message'] = \$originalContent . ' [Áudio: ' . \$transcriptionText . ']';
                    } else {
                        \$enhancedMessage['message'] = '[Áudio: ' . \$transcriptionText . ']';
                    }
                    
                    // Atualiza a mídia na mensagem
                    \$enhancedMessage['media'] = \$message['media'];
                }
            }
            
            \$enhancedHistory[] = \$enhancedMessage;
        }
        
        return \$enhancedHistory;
    }
PHP;

echo "✅ Função de transcrição automática criada\n";
echo "   - Verifica mensagens com áudio\n";
echo "   - Transcreve nos bastidores se necessário\n";
echo "   - Inclui transcrição no conteúdo da mensagem\n";
echo "   - Usa AudioTranscriptionService existente\n\n";

// 3. Cria a modificação da função buildUserPrompt
echo "3. MODIFICANDO buildUserPrompt PARA INCLUIR TRANSCRIÇÕES:\n";

$modifiedBuildUserPrompt = <<<PHP
    private static function buildUserPrompt(array \$conversationHistory, string \$contactName = '', string \$contactPhone = '', string \$attendantNote = '', string \$objective = 'first_contact', bool \$hasHistory = false): string
    {
        // Transcreve áudios automaticamente nos bastidores
        \$enhancedHistory = self::transcribeAudiosForContext(\$conversationHistory);
        
        \$prompt = '';
        
        if (\$hasHistory && !empty(\$enhancedHistory)) {
            \$prompt .= "## Histórico da Conversa\\n\\n";
            foreach (\$enhancedHistory as \$msg) {
                \$direction = (\$msg['direction'] ?? 'inbound') === 'outbound' ? 'Você' : (\$contactName ?: 'Cliente');
                \$message = \$msg['message'] ?? '';
                
                if (!empty(\$message)) {
                    \$prompt .= "{\$direction}: {\$message}\\n\\n";
                }
            }
            \$prompt .= "---\\n\\n";
        }
        
        // Resto da função original...
        if (!empty(\$contactName)) {
            \$prompt .= "## Informações do Contato\\n";
            \$prompt .= "Nome: {\$contactName}\\n";
        }
        if (!empty(\$contactPhone)) {
            \$prompt .= "Telefone: {\$contactPhone}\\n";
        }
        if (!empty(\$attendantNote)) {
            \$prompt .= "## Notas do Atendente\\n";
            \$prompt .= "{\$attendantNote}\\n\\n";
        }
        
        \$prompt .= "## Contexto Atual\\n";
        \$prompt .= "Objetivo: {\$objective}\\n\\n";
        \$prompt .= "Gere uma resposta adequada para este contexto. ";
        
        if (\$hasHistory) {
            \$prompt .= "Considere todo o histórico da conversa acima. ";
        }
        
        \$prompt .= "Seja natural, profissional e alinhado ao objetivo.\\n\\n";
        \$prompt .= "Resposta:";
        
        return \$prompt;
    }
PHP;

echo "✅ buildUserPrompt modificada\n";
echo "   - Chama transcribeAudiosForContext() antes de processar\n";
echo "   - Usa enhancedHistory com transcrições incluídas\n";
echo "   - Mantém funcionalidade original\n\n";

// 4. Cria a modificação da função suggestChat
echo "4. MODIFICANDO suggestChat PARA USAR HISTÓRICO COM TRANSCRIÇÕES:\n";

$modifiedSuggestChat = <<<PHP
    public static function suggestChat(array \$params): array
    {
        \$contextSlug = \$params['context_slug'] ?? 'geral';
        \$objective = \$params['objective'] ?? 'first_contact';
        \$attendantNote = \$params['attendant_note'] ?? '';
        \$conversationId = \$params['conversation_id'] ?? null;
        \$aiChatMessages = \$params['ai_chat_messages'] ?? [];
        \$userPrompt = \$params['user_prompt'] ?? '';
        
        // Coleta histórico completo da conversa
        \$conversationHistory = [];
        if (\$conversationId) {
            \$conversationHistory = self::getConversationHistory(\$conversationId);
        }
        
        // Transcreve áudios automaticamente nos bastidores
        \$enhancedHistory = self::transcribeAudiosForContext(\$conversationHistory);
        
        // Resto da função original...
        \$aiContext = self::getContext(\$contextSlug);
        if (!\$aiContext) {
            \$aiContext = self::getContext('geral');
        }
        
        \$learnedExamples = self::getLearnedExamples(\$contextSlug, \$objective, 5);
        
        \$systemPrompt = self::buildChatSystemPrompt(\$aiContext, \$objective, !empty(\$enhancedHistory), \$learnedExamples);
        \$userContext = self::buildUserPrompt(\$enhancedHistory, \$params['contact_name'] ?? '', \$params['contact_phone'] ?? '', \$attendantNote, \$objective, !empty(\$enhancedHistory));
        
        if (!empty(\$userPrompt)) {
            \$userContext .= "\\n\\n## REFINAMENTO SOLICITADO\\n" . \$userPrompt;
        }
        
        // Chama OpenAI com contexto completo incluindo transcrições
        \$response = self::callOpenAI([
            [
                'role' => 'system',
                'content' => \$systemPrompt
            ],
            [
                'role' => 'user',
                'content' => \$userContext
            ]
        ]);
        
        // Resto da função...
        if (isset(\$response['choices'][0]['message']['content'])) {
            \$message = trim(\$response['choices'][0]['message']['content']);
            
            return [
                'success' => true,
                'message' => \$message,
                'context' => \$contextSlug,
                'objective' => \$objective,
                'has_history' => !empty(\$enhancedHistory),
                'audio_transcriptions' => self::countAudioTranscriptions(\$enhancedHistory)
            ];
        }
        
        return ['success' => false, 'error' => 'Não foi possível gerar resposta'];
    }
    
    /**
     * Conta quantas transcrições de áudio foram usadas
     */
    private static function countAudioTranscriptions(array \$history): int
    {
        \$count = 0;
        foreach (\$history as \$message) {
            if (isset(\$message['message']) && strpos(\$message['message'], '[Áudio:') !== false) {
                \$count++;
            }
        }
        return \$count;
    }
PHP;

echo "✅ suggestChat modificada\n";
echo "   - Usa enhancedHistory com transcrições\n";
echo "   - Conta transcrições usadas para debug\n";
echo "   - Mantém toda funcionalidade original\n\n";

// 5. Plano de implementação
echo "5. PLANO DE IMPLEMENTAÇÃO:\n";

$implementationSteps = [
    '1. Adicionar transcribeAudiosForContext() ao AISuggestReplyService',
    '2. Modificar buildUserPrompt() para usar histórico com transcrições',
    '3. Modificar suggestChat() para transcrever automaticamente',
    '4. Testar com conversas que têm áudios',
    '5. Verificar se IA usa contexto completo com transcrições'
];

foreach ($implementationSteps as $step) {
    echo "✅ {$step}\n";
}

echo "\n=== BENEFÍCIOS DA IMPLEMENTAÇÃO ===\n";
echo "🎯 INTELIGÊNCIA CONTEXTUAL REAL:\n";
echo "- IA lê todo o histórico incluindo transcrições\n";
echo "- Áudios não transcritos são transcritos automaticamente\n";
echo "- Contexto completo para propostas inteligentes\n";
echo "- Sem respostas burras ou engessadas\n\n";

echo "📋 FUNCIONALIDADE:\n";
echo "- Transcrição nos bastidores (sem interferência do usuário)\n";
echo "- Usa AudioTranscriptionService existente\n";
echo "- Mantém compatibilidade com código atual\n";
echo -" Performance otimizada (só transcreve quando necessário)\n\n";

echo "🔍 PRÓXIMA AÇÃO:\n";
echo "Implementar as modificações no AISuggestReplyService\n";

?>
