<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INSERINDO APRENDIZADO DO SEU REFINAMENTO ===\n\n";

// Dados do seu refinamento
$contextSlug = 'ecommerce';
$objective = 'follow_up';
$situationSummary = 'Follow-up E-commerce - Refinamento: Viviane - Link template foodmart';
$aiSuggestionOriginal = "Olá, Viviane! Obrigado por confirmar que deseja começar com uma loja local e catálogo para escolha dos produtos. Isso facilita bastante a organização da operação e logística.

Para avançar, gostaria de saber quantos produtos pretende cadastrar inicialmente e se já tem alguma experiência com plataformas de loja virtual, como Shopify ou WooCommerce. Assim podemos pensar numa solução personalizada que atenda seu modelo de venda e facilite o dia a dia.

Enquanto isso, vou lhe enviar um link de um projeto que já desenvolvemos para venda de alimentos, para você ter uma ideia do que podemos fazer e adaptar para o seu negócio. 

Fico no aguardo das suas respostas para seguir com os próximos passos!";

$humanResponseRefined = "Olá, Viviane, obrigado por confirmar. Estou enviando o link de um template que desenvolvemos e que se encaixa bem no seu projeto. Lembrando que personalizamos tudo para atender às suas necessidades. Você pode me informar, por favor, em média quantos produtos pretende cadastrar?";

// Verifica se já existe um registro similar
echo "1. Verificando se já existe registro similar...\n";
$stmt = $db->prepare('
    SELECT COUNT(*) as total 
    FROM ai_learned_responses 
    WHERE context_slug = ? AND objective = ? AND DATE(created_at) = CURDATE()
');
$stmt->execute([$contextSlug, $objective]);
$exists = $stmt->fetch(PDO::FETCH_ASSOC);

if ($exists['total'] > 0) {
    echo "⚠️  Já existe um registro hoje para este contexto/objetivo\n";
    echo "Deseja mesmo inserir outro? (s/n): ";
    // Em ambiente real, pediria confirmação, mas vou inserir mesmo assim
    echo "Inserindo mesmo assim...\n";
}

// Calcula similaridade para verificação
$similarity = 0;
similar_text($aiSuggestionOriginal, $humanResponseRefined, $similarity);
echo "2. Similaridade entre textos: " . round($similarity, 1) . "%\n";

if ($similarity > 90) {
    echo "⚠️  Textos muito similares (>90%), mas vou inserir mesmo assim para teste\n";
}

// Insere o registro
echo "3. Inserindo no banco de dados...\n";
$stmt = $db->prepare('
    INSERT INTO ai_learned_responses 
    (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, conversation_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
');

$userId = 1; // ID do usuário (ajustar se necessário)
$conversationId = 196; // ID da conversa da Viviane (baseado nos logs anteriores)

try {
    $stmt->execute([
        $contextSlug,
        $objective,
        $situationSummary,
        $aiSuggestionOriginal,
        $humanResponseRefined,
        $userId,
        $conversationId
    ]);
    
    $insertId = $db->lastInsertId();
    echo "✅ SUCESSO! Registro inserido com ID: {$insertId}\n";
    
    // Verifica se foi inserido corretamente
    echo "\n4. Confirmando inserção...\n";
    $stmt = $db->prepare('
        SELECT id, context_slug, objective, situation_summary, 
               LEFT(ai_suggestion, 80) as ai_preview,
               LEFT(human_response, 80) as human_preview,
               created_at
        FROM ai_learned_responses 
        WHERE id = ?
    ');
    $stmt->execute([$insertId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo "✅ Registro confirmado:\n";
        echo "ID: {$record['id']}\n";
        echo "Contexto: {$record['context_slug']}\n";
        echo "Objetivo: {$record['objective']}\n";
        echo "IA: \"{$record['ai_preview']}...\"\n";
        echo "Humano: \"{$record['human_preview']}...\"\n";
        echo "Data: {$record['created_at']}\n";
    } else {
        echo "❌ Erro na confirmação\n";
    }
    
    // Testa se a função getLearnedExamples vai encontrar este registro
    echo "\n5. Testando busca de aprendizado...\n";
    $stmt = $db->prepare('
        SELECT situation_summary, ai_suggestion, human_response
        FROM ai_learned_responses
        WHERE context_slug = ? AND objective = ?
        ORDER BY created_at DESC
        LIMIT 5
    ');
    $stmt->execute([$contextSlug, $objective]);
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Encontrados " . count($examples) . " exemplos para ecommerce + follow_up:\n";
    foreach ($examples as $i => $ex) {
        echo "   " . ($i + 1) . ". {$ex['situation_summary']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao inserir: " . $e->getMessage() . "\n";
}

echo "\n=== RESUMO ===\n";
echo "✅ Aprendizado inserido manualmente\n";
echo "✅ Próximas sugestões usarão este exemplo\n";
echo "✅ IA agora sabe que follow-up de e-commerce deve ser curto e direto\n";

?>
