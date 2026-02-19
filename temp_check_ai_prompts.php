<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== PROMPTS DE SISTEMA POR CONTEXTO ===\n";
$stmt = $db->prepare('SELECT slug, name, system_prompt, knowledge_base FROM ai_contexts WHERE is_active = 1 ORDER BY sort_order ASC');
$stmt->execute();
$contexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contexts as $ctx) {
    echo "\n--- CONTEXTO: {$ctx['name']} ({$ctx['slug']}) ---\n";
    echo "System Prompt:\n{$ctx['system_prompt']}\n";
    
    if (!empty($ctx['knowledge_base'])) {
        echo "\nBase de Conhecimento:\n{$ctx['knowledge_base']}\n";
    } else {
        echo "\nBase de Conhecimento: (vazia)\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
}

echo "\n=== OBJETIVOS DISPONÍVEIS ===\n";
$objectives = [
    'first_contact' => 'Primeiro contato',
    'qualify' => 'Qualificar lead',
    'schedule_call' => 'Agendar call/reunião',
    'answer_question' => 'Responder dúvida',
    'follow_up' => 'Follow-up',
    'send_proposal' => 'Enviar proposta',
    'close_deal' => 'Fechar negócio',
    'support' => 'Suporte técnico',
    'billing' => 'Questão financeira',
];

foreach ($objectives as $key => $label) {
    echo "- {$key}: {$label}\n";
}

echo "\n=== COMO O APRENDIZADO FUNCIONA (ANÁLISE DO CÓDIGO) ===\n";
echo "1. getLearnedExamples() busca exemplos POR contexto + objetivo\n";
echo "2. Exemplos são limitados a 5 mais recentes\n";
echo "3. São injetados no system prompt como 'Aprendizado'\n";
echo "4. IA recebe exemplos reais de correções humanas\n";
echo "5. Aprende o tom e estilo preferido pela equipe\n";
?>
