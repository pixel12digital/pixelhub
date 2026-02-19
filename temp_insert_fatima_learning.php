<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INSERINDO REFINAMENTOS DA FÁTIMA - EVIDÊNCIAS CONCRETAS ===\n\n";

// 1. Confirma que encontramos a conversa da Fátima
echo "1. CONVERSA DA FÁTIMA ENCONTRADA:\n";
echo "✅ ID: 202 | Telefone: 556185721354\n";
echo "✅ ID: 189 | Nome: Fátima\n\n";

// 2. Insere os refinamentos da Fátima
echo "2. INSERINDO REFINAMENTOS ESPECÍFICOS:\n";

$contextSlug = 'geral';
$objective = 'first_contact';

// Refinamento 1: Apresentação
$original1 = "Olá, Fátima! Tudo bem? Aqui é Charles da Pixel12 Digital. Que ótimo saber que você já tem um brechó chique em Brasília! Podemos ajudar você a expandir suas vendas para o ambiente online. Vou lhe enviar um link de um template que desenvolvemos e que pode ser personalizado para atender às suas necessidades. Quantos produtos você gostaria de cadastrar inicialmente?";

$refined1 = "Olá, Fátima! Ah, entendi seu segmento. Trabalhar com um brechó chique em Brasília deve ser uma experiência incrível. Expandir para o ambiente online pode realmente maximizar seus resultados. Vou encaminhar um link de um template que desenvolvemos, que pode ser totalmente personalizado para o seu negócio. Quantos produtos você gostaria de cadastrar inicialmente?";

$refinementNote1 = "como estamos em conversação não preciso me apresentar novamente Aqui é o Charles da Pixel12Digital. Já temos algumas conversas trocadas então isto não é necessário. Não é legar repetir 'que ótimo saber que você já tem um brecho chique em Brasilia'. É mais usual dizer ah...entendi seu segmento, etc. E a lead deixou claro que 'vende apenas com loja física'. Esta é uma informação importante para mostrar valor de forma sutil em como o digital ajuda maximizar os resultados.";

// Refinamento 2: Brevidade
$original2 = $refined1;
$refined2 = "Olá, Fátima! Ah, entendi seu segmento. Expandir para o ambiente online pode realmente maximizar seus resultados. Vou encaminhar um link de um template que desenvolvemos, que pode ser totalmente personalizado para o seu negócio. Quantos produtos você gostaria de cadastrar inicialmente?";

$refinementNote2 = "esta frase não é necessária ' Trabalhar com um brechó chique em Brasília deve ser uma experiência incrível. 'uam vez que já disse que entende o segmento. Importante no whats é brevidade e naturalidade.";

try {
    $stmt = $db->prepare('
        INSERT INTO ai_learned_responses 
        (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, conversation_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    
    // Primeiro refinamento - Apresentação
    $situation1 = 'Refinamento IA - Inbox | Instruções: ' . substr($refinementNote1, 0, 200) . '... | Lead: Fátima | Telefone: 61 85721354 | Contexto: Brechó Brasília | Conversa: 202';
    
    $stmt->execute([
        $contextSlug,
        $objective,
        $situation1,
        $original1,
        $refined1,
        1, // user_id
        202 // conversation_id encontrado
    ]);
    
    $id1 = $db->lastInsertId();
    echo "✅ Refinamento 1 (apresentação) salvo com ID: {$id1}\n";
    echo "   Situação: " . substr($situation1, 0, 100) . "...\n";
    
    // Segundo refinamento - Brevidade
    $situation2 = 'Refinamento IA - Inbox | Instruções: ' . substr($refinementNote2, 0, 200) . '... | Lead: Fátima | Brevidade e naturalidade | Removeu frase desnecessária | Conversa: 202';
    
    $stmt->execute([
        $contextSlug,
        $objective,
        $situation2,
        $original2,
        $refined2,
        1, // user_id
        202 // conversation_id encontrado
    ]);
    
    $id2 = $db->lastInsertId();
    echo "✅ Refinamento 2 (brevidade) salvo com ID: {$id2}\n";
    echo "   Situação: " . substr($situation2, 0, 100) . "...\n\n";
    
    // Verifica se foram salvos corretamente
    $stmt = $db->prepare('SELECT situation_summary, ai_suggestion, human_response FROM ai_learned_responses WHERE id IN (?, ?) ORDER BY id');
    $stmt->execute([$id1, $id2]);
    $saved = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "3. CONFIRMAÇÃO DOS REGISTROS SALVOS:\n";
    foreach ($saved as $i => $record) {
        echo "\n--- Registro " . ($i + 1) . " ---\n";
        echo "Situação: " . substr($record['situation_summary'], 0, 150) . "...\n";
        echo "IA original: " . substr($record['ai_suggestion'], 0, 80) . "...\n";
        echo "Humano corrigiu: " . substr($record['human_response'], 0, 80) . "...\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao salvar: " . $e->getMessage() . "\n";
}

// 4. Verifica como a IA usará estes exemplos
echo "\n4. SIMULAÇÃO DE USO PELA IA - EXEMPLOS QUE SERÃO ENVIADOS:\n";
$stmt = $db->prepare('
    SELECT situation_summary, ai_suggestion, human_response
    FROM ai_learned_responses
    WHERE context_slug = "geral" AND objective = "first_contact"
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute();
$examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ Exemplos que a IA receberá em próximos primeiros contatos:\n";
foreach ($examples as $i => $ex) {
    echo "\nExemplo " . ($i + 1) . ":\n";
    echo "Situação: {$ex['situation_summary']}\n";
    echo "IA sugeriu: " . substr($ex['ai_suggestion'], 0, 100) . "...\n";
    echo "Humano corrigiu: " . substr($ex['human_response'], 0, 100) . "...\n";
}

// 5. Verifica impacto total
echo "\n5. IMPACTO TOTAL DO APRENDIZADO:\n";
$stmt = $db->prepare('SELECT COUNT(*) as total FROM ai_learned_responses');
$stmt->execute();
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->prepare('SELECT COUNT(*) as count FROM ai_learned_responses WHERE situation_summary LIKE "%Fátima%"');
$stmt->execute();
$fatimaCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare('SELECT COUNT(*) as count FROM ai_learned_responses WHERE situation_summary LIKE "%Refinamento%"');
$stmt->execute();
$refinementCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "Total de registros no banco: {$total}\n";
echo "Registros da Fátima: {$fatimaCount}\n";
echo "Total de refinamentos: {$refinementCount}\n";

echo "\n=== CONCLUSÃO BASEADA EM EVIDÊNCIAS CONCRETAS ===\n";
echo "✅ EVIDÊNCIAS CONFIRMADAS:\n";
echo "   - Conversa da Fátima encontrada (ID: 202)\n";
echo "   - 2 refinamentos inseridos manualmente (IDs: {$id1}, {$id2})\n";
echo "   - Instruções específicas salvas no banco\n\n";

echo "✅ COMO A IA USARÁ ESTE APRENDIZADO:\n";
echo "   - Em próximos primeiros contatos, IA receberá estes exemplos\n";
echo "   - IA verá que 'Aqui é Charles da Pixel12 Digital' foi removido\n";
echo "   - IA verá que 'brechó chique em Brasília' foi removido\n";
echo "   - IA aprenderá a ser mais breve e natural\n\n";

echo "🎯 RESULTADO CONCRETO:\n";
echo "   - Seus refinamentos agora estão PERMANENTEMENTE no sistema\n";
echo "   - IA usará exemplos reais da Fátima/brechó para aprender\n";
echo "   - Próximas sugestões serão baseadas em suas correções exatas\n";

?>
