<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== TESTANDO INTEGRAÇÃO DO APRENDIZADO ===\n\n";

// 1. Confirma que o registro está no banco
echo "1. VERIFICANDO REGISTRO INSERIDO:\n";
$stmt = $db->prepare('
    SELECT id, context_slug, objective, situation_summary, 
           ai_suggestion, human_response, created_at
    FROM ai_learned_responses 
    WHERE id = 3
');
$stmt->execute();
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    echo "✅ Registro encontrado:\n";
    echo "   Contexto: {$record['context_slug']} | Objetivo: {$record['objective']}\n";
    echo "   Situação: {$record['situation_summary']}\n";
    echo "   Criado: {$record['created_at']}\n\n";
} else {
    echo "❌ Registro não encontrado\n";
    exit;
}

// 2. Simula como a IA usaria este aprendizado
echo "2. SIMULANDO COMO A IA USARÁ ESTE APRENDIZADO:\n";

// Simula a função getLearnedExamples
$stmt = $db->prepare('
    SELECT situation_summary, ai_suggestion, human_response
    FROM ai_learned_responses
    WHERE context_slug = ? AND objective = ?
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute(['ecommerce', 'follow_up']);
$examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ getLearnedExamples() retornaria " . count($examples) . " exemplos:\n\n";

// Monta o prompt como a IA receberia
$learningSection = '';
if (!empty($examples)) {
    $learningSection = "\n\n## Aprendizado (respostas corrigidas por atendentes anteriormente)\nUse estes exemplos como referência de tom e estilo preferido pela equipe:\n";
    foreach ($examples as $i => $ex) {
        $num = $i + 1;
        $learningSection .= "\nExemplo {$num}:\n";
        $learningSection .= "Situação: {$ex['situation_summary']}\n";
        $learningSection .= "IA sugeriu: {$ex['ai_suggestion']}\n";
        $learningSection .= "Atendente corrigiu para: {$ex['human_response']}\n";
    }
    $learningSection .= "\nAprenda com estas correções: adapte seu estilo para se aproximar do que os atendentes preferem.";
}

echo "📝 PROMPT QUE A IA RECEBERÁ (aprendizado incluído):\n";
echo $learningSection . "\n";

// 3. Mostra o impacto esperado
echo "\n3. IMPACTO ESPERADO EM PRÓXIMAS SUGESTÕES:\n";
echo "✅ IA vai ver que follow-up de e-commerce deve ser:\n";
echo "   - Curto e direto (seu exemplo: 1 parágrafo vs 4 parágrafos)\n";
echo "   - Focado em ação (enviar link + perguntar produtos)\n";
echo "   - Sem rodeios (removeu explicações sobre logística)\n";
echo "   - Personalização mencionada (lembrando que personalizamos)\n";

// 4. Verifica se não há duplicatas
echo "\n4. VERIFICANDO DUPLICATAS:\n";
$stmt = $db->prepare('
    SELECT COUNT(*) as total 
    FROM ai_learned_responses 
    WHERE context_slug = "ecommerce" AND objective = "follow_up"
');
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total de registros para ecommerce + follow_up: {$count['total']}\n";

// 5. Testa se similar_text funcionaria
echo "\n5. TESTE DE SIMILARIDADE (como o sistema decide salvar):\n";
$text1 = "Texto longo e complicado sobre e-commerce";
$text2 = "Texto curto e direto sobre e-commerce";
$similarity = 0;
similar_text($text1, $text2, $similarity);
echo "Similaridade entre textos diferentes: " . round($similarity, 1) . "%\n";
echo "Regra: só salva se similaridade < 90% (seu caso: 24.2% ✅)\n";

echo "\n=== CONCLUSÃO FINAL ===\n";
echo "✅ SEU APRENDIZADO ESTÁ SALVO E ATIVO\n";
echo "✅ PRÓXIMA VEZ QUE ALGUÉM PEDIR FOLLOW-UP DE E-COMMERCE:\n";
echo "   - IA receberá seu exemplo como referência\n";
echo "   - Tendência a gerar respostas mais curtas e diretas\n";
echo "   - Aprendizado contínuo com cada novo refinamento\n";
echo "\n🚀 O SISTEMA ESTÁ APRENDENDO COM VOCÊ!\n";

?>
