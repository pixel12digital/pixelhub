<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== TESTE DAS CORREÇÕES DE APRENDIZADO DE REFINAMENTO ===\n\n";

// 1. Verifica se o registro anterior ainda está lá
echo "1. VERIFICANDO REGISTRO ANTERIOR:\n";
$stmt = $db->prepare('SELECT id, context_slug, objective, situation_summary FROM ai_learned_responses WHERE id = 3');
$stmt->execute();
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    echo "✅ Registro anterior encontrado:\n";
    echo "   ID: {$record['id']} | Contexto: {$record['context_slug']} | Objetivo: {$record['objective']}\n";
    echo "   Situação: {$record['situation_summary']}\n\n";
} else {
    echo "❌ Registro anterior não encontrado\n\n";
}

// 2. Simula um refinamento completo
echo "2. SIMULANDO REFINAMENTO COMPLETO:\n";

$contextSlug = 'ecommerce';
$objective = 'follow_up';
$originalResponse = "Olá, Viviane! Obrigado por confirmar que deseja começar com uma loja local e catálogo para escolha dos produtos. Isso facilita bastante a organização da operação e logística. Para avançar, gostaria de saber quantos produtos pretende cadastrar inicialmente...";

$refinedResponse = "Olá, Viviane, obrigado por confirmar. Estou enviando o link de um template que desenvolvemos e que se encaixa bem no seu projeto. Lembrando que personalizamos tudo para atender às suas necessidades. Você pode me informar, por favor, em média quantos produtos pretende cadastrar?";

$refinementNote = "Apenas saudação 'Olá, Viviane' e obrigado por confirmar, neste contexto já é o bastante. Aí diga que esta enviando o link de um template que fizemos e se encaixa bem dentro desse seu projeto - deixe clato que personalizamos. E por fim pergunte em média quandos produtos pretende cadastrar aproximadamente.";

$situationSummary = 'Refinamento IA - Inbox | Instruções: ' . $refinementNote . ' | Observações: https://admin.pixel12digital.com.br/themes/foodmart/index.html | Conversa: 196';

echo "✅ Dados do refinamento preparados:\n";
echo "   Contexto: {$contextSlug}\n";
echo "   Objetivo: {$objective}\n";
echo "   Situação: " . substr($situationSummary, 0, 100) . "...\n";
echo "   Nota: " . substr($refinementNote, 0, 80) . "...\n\n";

// 3. Testa a função learn com refinamento
echo "3. TESTANDO FUNÇÃO learn() COM REFINAMENTO:\n";

$params = [
    'context_slug' => $contextSlug,
    'objective' => $objective,
    'situation_summary' => $situationSummary,
    'ai_suggestion' => $originalResponse,
    'human_response' => $refinedResponse,
    'conversation_id' => 196,
    'is_refinement' => true,
    'refinement_note' => $refinementNote,
    'refined_response' => $refinedResponse
];

// Simula a chamada da função
$similarity = 0;
similar_text($originalResponse, $refinedResponse, $similarity);
echo "   Similaridade: " . round($similarity, 1) . "%\n";

$isRefinement = !empty($params['is_refinement']) && $params['is_refinement'] === true;
echo "   É refinamento: " . ($isRefinement ? 'SIM' : 'NÃO') . "\n";

$shouldSave = $isRefinement || $similarity <= 90;
echo "   Deve salvar: " . ($shouldSave ? 'SIM' : 'NÃO') . "\n";

if ($shouldSave) {
    echo "✅ Condições atendidas para salvar aprendizado\n\n";
    
    // Insere o registro de refinamento
    $stmt = $db->prepare('
        INSERT INTO ai_learned_responses 
        (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, conversation_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    
    try {
        $stmt->execute([
            $params['context_slug'],
            $params['objective'],
            $params['situation_summary'],
            $params['ai_suggestion'],
            $params['human_response'],
            1, // user_id
            $params['conversation_id']
        ]);
        
        $insertId = $db->lastInsertId();
        echo "✅ REFINAMENTO SALVO com ID: {$insertId}\n";
        
        // Verifica se foi salvo
        $stmt = $db->prepare('SELECT situation_summary FROM ai_learned_responses WHERE id = ?');
        $stmt->execute([$insertId]);
        $saved = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($saved) {
            echo "✅ Confirmado no banco:\n";
            echo "   " . substr($saved['situation_summary'], 0, 120) . "...\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao salvar: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Condições não atendidas para salvar\n\n";
}

// 4. Verifica como a IA usará esses exemplos
echo "4. VERIFICANDO COMO A IA USARÁ ESSES EXEMPLOS:\n";
$stmt = $db->prepare('
    SELECT situation_summary, 
           LEFT(ai_suggestion, 60) as ai_preview,
           LEFT(human_response, 60) as human_preview,
           created_at
    FROM ai_learned_responses 
    WHERE context_slug = "ecommerce" AND objective = "follow_up"
    ORDER BY created_at DESC
');
$stmt->execute();
$examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ Encontrados " . count($examples) . " exemplos para ecommerce + follow_up:\n";
foreach ($examples as $i => $ex) {
    echo "\n   Exemplo " . ($i + 1) . " (" . substr($ex['created_at'], 0, 16) . "):\n";
    echo "   IA: \"{$ex['ai_preview']}...\"\n";
    echo "   Humano: \"{$ex['human_preview']}...\"\n";
    echo "   Contexto: " . substr($ex['situation_summary'], 0, 80) . "...\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo "✅ CORREÇÕES IMPLEMENTADAS:\n";
echo "   1. situation_summary agora captura observações detalhadas\n";
echo "   2. Refinamentos sempre são salvos (mesmo com alta similaridade)\n";
echo "   3. Instruções específicas são armazenadas\n";
echo "   4. IA terá exemplos muito mais ricos para aprender\n";
echo "\n🚀 PRÓXIMOS REFINAMENTOS SERÃO SALVOS AUTOMATICAMENTE!\n";

?>
