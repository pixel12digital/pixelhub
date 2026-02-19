<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== TESTE LOCAL: RASCUNHO DA PROPOSTA NÃO GERA ===\n\n";

// 1. Simula requisição autenticada (sem autenticação real)
echo "1. TESTE LOCAL DO ENDPOINT (SIMULAÇÃO):\n";

// Vou verificar se o problema está no controller ou no serviço
echo "Verificando se o problema está no controller...\n";

// 2. Testa diretamente o AISuggestReplyService
echo "\n2. TESTE DIRETO DO AISuggestReplyService:\n";

try {
    // Simula parâmetros que viriam do frontend
    $params = [
        'context_slug' => 'ecommerce',
        'objective' => 'send_proposal',
        'attendant_note' => 'Teste de proposta para Lidy sobre valores',
        'conversation_id' => null,
        'ai_chat_messages' => [],
        'user_prompt' => 'gere uma resposta inicial baseada no contexto e histórico da conversa',
        'thread_id' => null,
        'lead_id' => null,
        'contact_id' => null,
        'contact_name' => 'Lidy',
        'contact_phone' => '61999999999',
        'thread_messages' => []
    ];
    
    echo "Parâmetros de teste:\n";
    foreach ($params as $key => $value) {
        echo "- {$key}: " . (is_array($value) ? 'array(' . count($value) . ')' : $value) . "\n";
    }
    
    // Chama o serviço diretamente
    $result = \PixelHub\Services\AISuggestReplyService::suggestChat($params);
    
    echo "\nResultado do serviço:\n";
    if ($result['success']) {
        echo "✅ Sucesso!\n";
        echo "✅ Message: " . substr($result['message'] ?? 'N/A', 0, 200) . "...\n";
        echo "✅ Context: " . ($result['context_used'] ?? 'N/A') . "\n";
        echo "✅ Learned examples: " . ($result['learned_examples_count'] ?? 0) . "\n";
    } else {
        echo "❌ Erro: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exceção: " . $e->getMessage() . "\n";
    echo "❌ Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 3. Verifica se há problemas com a chave OpenAI
echo "\n3. VERIFICANDO CONFIGURAÇÃO OPENAI:\n";

// Verifica se a função getApiKey funciona (usando reflexão)
try {
    $reflection = new ReflectionClass('\PixelHub\Services\AISuggestReplyService');
    $method = $reflection->getMethod('getApiKey');
    $method->setAccessible(true);
    $apiKey = $method->invoke(null);
    
    if ($apiKey) {
        echo "✅ Chave OpenAI encontrada\n";
        echo "✅ Primeiros caracteres: " . substr($apiKey, 0, 10) . "...\n";
    } else {
        echo "❌ Chave OpenAI não encontrada\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao buscar chave: " . $e->getMessage() . "\n";
}

// 4. Testa se o contexto ecommerce está correto
echo "\n4. TESTE DO CONTEXTO ECOMMERCE:\n";

try {
    $context = \PixelHub\Services\AISuggestReplyService::getContext('ecommerce');
    if ($context) {
        echo "✅ Contexto ecommerce encontrado\n";
        echo "✅ Nome: " . $context['name'] . "\n";
        echo "✅ Ativo: " . ($context['is_active'] ? 'SIM' : 'NÃO') . "\n";
        
        // Verifica se contém instruções de proposta
        if (strpos($context['system_prompt'], 'OBJETIVO: ENVIAR PROPOSTA') !== false) {
            echo "✅ Contém instruções de proposta\n";
        } else {
            echo "❌ Não contém instruções de proposta\n";
        }
        
        // Verifica se contém valores R$ 197
        if (strpos($context['system_prompt'], 'R$ 197') !== false) {
            echo "✅ Contém valores R$ 197\n";
        } else {
            echo "❌ Não contém valores R$ 197\n";
        }
    } else {
        echo "❌ Contexto ecommerce não encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao buscar contexto: " . $e->getMessage() . "\n";
}

// 5. Testa se há exemplos de aprendizado
echo "\n5. TESTE DE EXEMPLOS DE APRENDIZADO:\n";

try {
    $examples = \PixelHub\Services\AISuggestReplyService::getLearnedExamples('ecommerce', 'send_proposal', 5);
    echo "Exemplos encontrados: " . count($examples) . "\n";
    
    if (count($examples) > 0) {
        foreach ($examples as $i => $example) {
            echo "\nExemplo " . ($i + 1) . ":\n";
            echo "Situação: " . substr($example['situation_summary'], 0, 80) . "...\n";
            echo "IA sugeriu: " . substr($example['ai_suggestion'], 0, 60) . "...\n";
            echo "Humano corrigiu: " . substr($example['human_response'], 0, 60) . "...\n";
        }
    } else {
        echo "❌ Nenhum exemplo encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao buscar exemplos: " . $e->getMessage() . "\n";
}

// 6. Teste a função de transcrição de áudios
echo "\n6. TESTE DE TRANSCRIÇÃO DE ÁUDIOS:\n";

$testHistory = [
    [
        'direction' => 'in',
        'message' => 'Olá, tudo bem?',
        'media' => []
    ],
    [
        'direction' => 'in',
        'message' => '',
        'media' => [
            [
                'media_type' => 'audio',
                'event_id' => 'test_event_123',
                'transcription' => null
            ]
        ]
    ]
];

// Testa se a função existe e funciona
if (method_exists('\PixelHub\Services\AISuggestReplyService', 'transcribeAudiosForContext')) {
    echo "✅ Função transcribeAudiosForContext existe\n";
    
    try {
        // Usa reflexão para chamar método privado
        $reflection = new ReflectionClass('\PixelHub\Services\AISuggestReplyService');
        $method = $reflection->getMethod('transcribeAudiosForContext');
        $method->setAccessible(true);
        
        $enhancedHistory = $method->invoke(null, $testHistory);
        
        echo "✅ Função executada com sucesso\n";
        echo "Histórico original: " . count($testHistory) . " mensagens\n";
        echo "Histórico enhanced: " . count($enhancedHistory) . " mensagens\n";
        
        // Verifica se houve transcrição
        foreach ($enhancedHistory as $msg) {
            if (strpos($msg['message'] ?? '', '[Áudio:') !== false) {
                echo "✅ Transcrição incluída: " . substr($msg['message'], 0, 50) . "...\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao executar transcrição: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Função transcribeAudiosForContext não encontrada\n";
}

// 7. Diagnóstico final
echo "\n=== DIAGNÓSTICO FINAL LOCAL ===\n";

$issues = [];
$sucess = [];

// Verifica cada componente
try {
    $reflection = new ReflectionClass('\PixelHub\Services\AISuggestReplyService');
    $method = $reflection->getMethod('getApiKey');
    $method->setAccessible(true);
    $apiKey = $method->invoke(null);
    if ($apiKey) $sucess[] = "Chave OpenAI configurada";
    else $issues[] = "Chave OpenAI não encontrada";
} catch (Exception $e) {
    $issues[] = "Erro na chave OpenAI: " . $e->getMessage();
}

try {
    $context = \PixelHub\Services\AISuggestReplyService::getContext('ecommerce');
    if ($context) $sucess[] = "Contexto ecommerce encontrado";
    else $issues[] = "Contexto ecommerce não encontrado";
} catch (Exception $e) {
    $issues[] = "Erro no contexto: " . $e->getMessage();
}

if (!empty($sucess)) {
    echo "✅ Componentes funcionando:\n";
    foreach ($sucess as $item) {
        echo "   - {$item}\n";
    }
}

if (!empty($issues)) {
    echo "❌ Problemas identificados:\n";
    foreach ($issues as $item) {
        echo "   - {$item}\n";
    }
}

echo "\n📋 CONCLUSÃO:\n";
if (empty($issues)) {
    echo "✅ Todos os componentes funcionam localmente\n";
    echo "✅ O problema provavelmente está na autenticação do endpoint\n";
    echo "✅ Teste no console do navegador (já autenticado)\n";
} else {
    echo "❌ Há problemas nos componentes que precisam ser corrigidos\n";
    echo "❌ Corrija os problemas acima antes de testar no navegador\n";
}

?>
