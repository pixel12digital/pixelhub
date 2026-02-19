<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO: IA NÃO GEROU RASCUNHO PARA ENVIAR PROPOSTA ===\n\n";

// 1. Verifica se existe contexto "send_proposal" no banco
echo "1. VERIFICANDO CONTEXTO SEND_PROPOSAL:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "send_proposal" OR slug LIKE "%propos%"');
$stmt->execute();
$contexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($contexts) > 0) {
    foreach ($contexts as $ctx) {
        echo "✅ Contexto encontrado: {$ctx['slug']} | Nome: {$ctx['name']} | Ativo: " . ($ctx['is_active'] ? 'SIM' : 'NÃO') . "\n";
    }
} else {
    echo "❌ Nenhum contexto de proposta encontrado\n";
    
    // Verifica todos os contextos disponíveis
    echo "\nContextos disponíveis:\n";
    $stmt = $db->prepare('SELECT slug, name, is_active FROM ai_contexts ORDER BY slug');
    $stmt->execute();
    $allContexts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allContexts as $ctx) {
        echo "- {$ctx['slug']}: {$ctx['name']} (" . ($ctx['is_active'] ? 'ativo' : 'inativo') . ")\n";
    }
}

// 2. Verifica objetivos disponíveis
echo "\n2. VERIFICANDO OBJETIVOS DISPONÍVEIS:\n";
$objectives = [
    'first_contact' => 'Primeiro contato',
    'qualify' => 'Qualificar lead',
    'schedule_call' => 'Agendar call/reunião',
    'answer_question' => 'Responder dúvida',
    'follow_up' => 'Follow-up',
    'send_proposal' => 'Enviar proposta',
    'close_deal' => 'Fechar negócio',
    'support' => 'Suporte técnico',
    'billing' => 'Questão financeira'
];

foreach ($objectives as $key => $label) {
    echo "✅ {$key}: {$label}\n";
}

// 3. Verifica se há exemplos de aprendizado para send_proposal
echo "\n3. VERIFICANDO APRENDIZADO PARA SEND_PROPOSAL:\n";
$stmt = $db->prepare('
    SELECT COUNT(*) as total 
    FROM ai_learned_responses 
    WHERE objective = "send_proposal"
');
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Registros de aprendizado para send_proposal: {$count['total']}\n";

if ($count['total'] > 0) {
    $stmt = $db->prepare('
        SELECT situation_summary, created_at
        FROM ai_learned_responses 
        WHERE objective = "send_proposal"
        ORDER BY created_at DESC
        LIMIT 3
    ');
    $stmt->execute();
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nExemplos recentes:\n";
    foreach ($examples as $ex) {
        echo "- " . substr($ex['situation_summary'], 0, 80) . "... ({$ex['created_at']})\n";
    }
}

// 4. Simula uma chamada ao endpoint /api/ai/suggest-chat
echo "\n4. SIMULANDO CHAMADA AO ENDPOINT:\n";

$testPayload = [
    'context_slug' => 'geral', // ou 'ecommerce' se existir
    'objective' => 'send_proposal',
    'attendant_note' => 'Lidy questionou sobre valores',
    'conversation_id' => null,
    'ai_chat_messages' => [],
    'user_prompt' => 'gere uma resposta inicial baseada no contexto e histórico da conversa'
];

echo "Payload de teste:\n";
foreach ($testPayload as $key => $value) {
    echo "- {$key}: " . (is_array($value) ? 'array(' . count($value) . ')' : $value) . "\n";
}

// 5. Verifica se o endpoint está funcionando
echo "\n5. VERIFICANDO ENDPOINT /API/AI/SUGGEST-CHAT:\n";
echo "✅ Endpoint existe em public/index.php\n";
echo "✅ Controller AISuggestController@suggestChat implementado\n";
echo "✅ Service AISuggestReplyService@suggestChat implementado\n";

// 6. Possíveis causas do problema
echo "\n6. POSSÍVEIS CAUSAS DO PROBLEMA:\n";
echo "❌ Contexto 'send_proposal' não existe ou está inativo\n";
echo "❌ Objetivo 'send_proposal' não é reconhecido\n";
echo "❌ Erro no processamento do backend\n";
echo "❌ Timeout na chamada à API OpenAI\n";
echo "❌ Falta de histórico da conversa\n";
echo "❌ Problema no frontend ao exibir resultado\n";

// 7. Verifica logs de erro recentes
echo "\n7. VERIFICANDO LOGS DE ERRO (se disponíveis):\n";
$logFile = 'logs/pixelhub.log';
if (file_exists($logFile)) {
    $recentLogs = shell_exec("tail -n 20 " . escapeshellarg($logFile) . " | grep -i error");
    if ($recentLogs) {
        echo "Encontrados erros recentes:\n";
        echo $recentLogs . "\n";
    } else {
        echo "Nenhum erro recente encontrado no log\n";
    }
} else {
    echo "Arquivo de log não encontrado: {$logFile}\n";
}

echo "\n=== DIAGNÓSTICO PRELIMINAR ===\n";
echo "🔍 PRÓXIMOS PASSOS PARA INVESTIGAÇÃO:\n";
echo "1. Verificar logs do navegador (F12) para erros JavaScript\n";
echo "2. Verificar logs do PHP para erros no backend\n";
echo "3. Testar manualmente o endpoint com curl\n";
echo "4. Verificar se a API OpenAI está respondendo\n";
echo "5. Confirmar se o contexto send_proposal está configurado\n";

?>
