<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== DEBUG: POR QUE RASCUNHO DA PROPOSTA NÃO ESTÁ SENDO GERADO ===\n\n";

// 1. Verifica se o endpoint está funcionando
echo "1. VERIFICANDO ENDPOINT /API/AI/SUGGEST-CHAT:\n";

$testPayload = [
    'context_slug' => 'ecommerce',
    'objective' => 'send_proposal',
    'attendant_note' => 'Teste de proposta para Lidy sobre valores',
    'conversation_id' => null,
    'ai_chat_messages' => [],
    'user_prompt' => 'gere uma resposta inicial baseada no contexto e histórico da conversa',
    'thread_id' => null,
    'lead_id' => null,
    'contact_id' => null,
    'thread_messages' => []
];

echo "Payload de teste:\n";
foreach ($testPayload as $key => $value) {
    echo "- {$key}: " . (is_array($value) ? 'array(' . count($value) . ')' : $value) . "\n";
}

// 2. Simula chamada ao endpoint
echo "\n2. SIMULANDO CHAMADA AO ENDPOINT:\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/painel.pixel12digital/api/ai/suggest-chat');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Erro cURL: {$curlError}\n";
} else {
    echo "✅ HTTP Status: {$httpCode}\n";
    echo "Resposta: " . substr($response, 0, 500) . "...\n";
    
    if ($httpCode !== 200) {
        echo "❌ Endpoint retornou erro HTTP {$httpCode}\n";
    } else {
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                echo "✅ Endpoint funcionou corretamente\n";
                echo "✅ Message: " . substr($data['message'] ?? 'N/A', 0, 100) . "...\n";
                echo "✅ Mode: " . ($data['mode'] ?? 'N/A') . "\n";
            } else {
                echo "❌ Erro no endpoint: " . ($data['error'] ?? 'Erro desconhecido') . "\n";
            }
        } else {
            echo "❌ Resposta inválida (JSON inválido)\n";
        }
    }
}

// 3. Verifica logs de erro recentes
echo "\n3. VERIFICANDO LOGS DE ERRO RECENTES:\n";

$logFile = 'logs/pixelhub.log';
if (file_exists($logFile)) {
    // Busca por logs das últimas horas
    $recentLogs = shell_exec('powershell -Command "Get-Content -Path ' . $logFile . ' | Select-String -Pattern \"AI SUGGEST-CHAT\" | Select-Object -Last 10"');
    
    if ($recentLogs) {
        echo "✅ Logs recentes encontrados:\n";
        echo substr($recentLogs, 0, 1000) . "...\n";
    } else {
        echo "❌ Nenhum log de AI SUGGEST-CHAT encontrado\n";
    }
} else {
    echo "❌ Arquivo de log não encontrado: {$logFile}\n";
}

// 4. Verifica se há problemas com o contexto ecommerce
echo "\n4. VERIFICANDO CONTEXTO ECOMMERCE:\n";

$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "ecommerce"');
$stmt->execute();
$context = $stmt->fetch(PDO::FETCH_ASSOC);

if ($context) {
    echo "✅ Contexto ecommerce encontrado\n";
    echo "✅ Nome: {$context['name']}\n";
    echo "✅ Ativo: " . ($context['is_active'] ? 'SIM' : 'NÃO') . "\n";
    echo "✅ System prompt: " . substr($context['system_prompt'], 0, 100) . "...\n";
    
    // Verifica se contém instruções de proposta
    if (strpos($context['system_prompt'], 'OBJETIVO: ENVIAR PROPOSTA') !== false) {
        echo "✅ Contém instruções de proposta\n";
    } else {
        echo "❌ Não contém instruções de proposta\n";
    }
} else {
    echo "❌ Contexto ecommerce não encontrado\n";
}

// 5. Verifica se há exemplos de aprendizado
echo "\n5. VERIFICANDO EXEMPLOS DE APRENDIZADO:\n";

$stmt = $db->prepare('
    SELECT COUNT(*) as total 
    FROM ai_learned_responses 
    WHERE context_slug = "ecommerce" 
    AND objective = "send_proposal"
');
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Exemplos de aprendizado (ecommerce + send_proposal): {$count['total']}\n";

// 6. Verifica se há problemas com a chave da API OpenAI
echo "\n6. VERIFICANDO CONFIGURAÇÃO DA API OPENAI:\n";

if (function_exists('env')) {
    $apiKey = env('OPENAI_API_KEY');
    if ($apiKey) {
        echo "✅ Chave OpenAI configurada\n";
        echo "✅ Primeiros caracteres: " . substr($apiKey, 0, 10) . "...\n";
    } else {
        echo "❌ Chave OpenAI não configurada\n";
    }
} else {
    echo "❌ Função env() não disponível\n";
}

// 7. Diagnóstico final
echo "\n=== DIAGNÓSTICO FINAL ===\n";

$possibleIssues = [
    'Endpoint não responde' => $httpCode !== 200,
    'Contexto ecommerce não encontrado' => !$context,
    'Contexto sem instruções de proposta' => $context && strpos($context['system_prompt'], 'OBJETIVO: ENVIAR PROPOSTA') === false,
    'Chave OpenAI não configurada' => !function_exists('env') || !env('OPENAI_API_KEY'),
    'Logs de erro presentes' => isset($recentLogs) && strpos($recentLogs, 'ERROR') !== false
];

echo "Possíveis problemas identificados:\n";
foreach ($possibleIssues as $issue => $hasProblem) {
    $status = $hasProblem ? '❌' : '✅';
    echo "{$status} {$issue}\n";
}

echo "\n📋 RECOMENDAÇÕES:\n";
echo "1. Abra o DevTools no navegador (F12)\n";
echo "2. Vá para a aba Console\n";
echo "3. Clique em 'Gerar rascunho' no Inbox\n";
echo "4. Verifique se há erros no console\n";
echo "5. Verifique se há logs de '[IA DEBUG]'\n";
echo "6. Verifique se a requisição /api/ai/suggest-chat está sendo feita\n";
echo "7. Verifique o status da resposta\n\n";

echo "🔍 COMANDOS PARA DEBUG NO CONSOLE:\n";
echo "// Verificar variáveis globais\n";
echo "console.log('Conversation ID:', window._currentInboxConversationId);\n";
echo "console.log('Messages:', window._currentInboxMessages);\n";
echo "console.log('Thread:', window._currentInboxThread);\n\n";

echo "// Testar chamada manual\n";
echo "fetch('/api/ai/suggest-chat', {\n";
echo "  method: 'POST',\n";
echo "  headers: {'Content-Type': 'application/json'},\n";
echo "  body: JSON.stringify({\n";
echo "    context_slug: 'ecommerce',\n";
echo "    objective: 'send_proposal',\n";
echo "    attendant_note: 'Teste',\n";
echo "    conversation_id: null,\n";
echo "    thread_messages: []\n";
echo "  })\n";
echo "}).then(r => r.json()).then(console.log);\n";

?>
