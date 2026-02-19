<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICAÇÃO CONCRETA DOS REFINAMENTOS DA FÁTIMA ===\n\n";

// 1. Busca registros recentes de aprendizado
echo "1. REGISTROS DE APRENDIZADO DAS ÚLTIMAS HORAS:\n";
$stmt = $db->prepare('
    SELECT id, context_slug, objective, situation_summary, 
           LEFT(ai_suggestion, 100) as ai_preview,
           LEFT(human_response, 100) as human_preview,
           created_at
    FROM ai_learned_responses 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
');
$stmt->execute();
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($recent) > 0) {
    foreach ($recent as $i => $record) {
        echo "\n--- Registro " . ($i + 1) . " (ID: {$record['id']}) ---\n";
        echo "Contexto: {$record['context_slug']} | Objetivo: {$record['objective']}\n";
        echo "Situação: {$record['situation_summary']}\n";
        echo "IA sugeriu: \"{$record['ai_preview']}...\"\n";
        echo "Humano corrigiu: \"{$record['human_preview']}...\"\n";
        echo "Data: {$record['created_at']}\n";
    }
} else {
    echo "❌ Nenhum registro encontrado nas últimas 2 horas\n";
}

// 2. Busca especificamente por menções à Fátima
echo "\n2. BUSCA ESPECÍFICA POR MENÇÕES À FÁTIMA:\n";
$stmt = $db->prepare('
    SELECT id, situation_summary, ai_suggestion, human_response, created_at
    FROM ai_learned_responses 
    WHERE situation_summary LIKE "%Fátima%" 
       OR ai_suggestion LIKE "%Fátima%" 
       OR human_response LIKE "%Fátima%"
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute();
$fatimaRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($fatimaRecords) > 0) {
    echo "✅ Encontrados " . count($fatimaRecords) . " registros mencionando Fátima:\n";
    foreach ($fatimaRecords as $i => $record) {
        echo "\n--- Fátima Registro " . ($i + 1) . " ---\n";
        echo "ID: {$record['id']} | Data: {$record['created_at']}\n";
        echo "Situação: " . substr($record['situation_summary'], 0, 100) . "...\n";
        echo "IA: " . substr($record['ai_suggestion'], 0, 80) . "...\n";
        echo "Humano: " . substr($record['human_response'], 0, 80) . "...\n";
    }
} else {
    echo "❌ Nenhum registro encontrado mencionando Fátima\n";
}

// 3. Busca por refinamentos sobre apresentação e brevidade
echo "\n3. BUSCA POR REFINAMENTOS SOBRE APRESENTAÇÃO E BREVIDADE:\n";
$stmt = $db->prepare('
    SELECT id, situation_summary, ai_suggestion, human_response, created_at
    FROM ai_learned_responses 
    WHERE situation_summary LIKE "%apresentaç%" 
       OR situation_summary LIKE "%brevidade%"
       OR situation_summary LIKE "%naturalidade%"
       OR ai_suggestion LIKE "%Charles da Pixel12%"
       OR human_response LIKE "%Charles da Pixel12%"
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute();
$apresentationRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($apresentationRecords) > 0) {
    echo "✅ Encontrados " . count($apresentationRecords) . " registros sobre apresentação:\n";
    foreach ($apresentationRecords as $i => $record) {
        echo "\n--- Apresentação Registro " . ($i + 1) . " ---\n";
        echo "ID: {$record['id']} | Data: {$record['created_at']}\n";
        echo "Situação: " . substr($record['situation_summary'], 0, 100) . "...\n";
        echo "IA: " . substr($record['ai_suggestion'], 0, 80) . "...\n";
        echo "Humano: " . substr($record['human_response'], 0, 80) . "...\n";
    }
} else {
    echo "❌ Nenhum registro encontrado sobre apresentação/brevidade\n";
}

// 4. Verifica se existe contexto de brechó/segundo uso
echo "\n4. BUSCA POR CONTEXTO DE BRECHÓ/SEGMENTO:\n";
$stmt = $db->prepare('
    SELECT id, situation_summary, ai_suggestion, human_response, created_at
    FROM ai_learned_responses 
    WHERE situation_summary LIKE "%brechó%" 
       OR ai_suggestion LIKE "%brechó%"
       OR human_response LIKE "%brechó%"
       OR situation_summary LIKE "%segmento%"
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute();
$brechoRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($brechoRecords) > 0) {
    echo "✅ Encontrados " . count($brechoRecords) . " registros sobre brechó/segmento:\n";
    foreach ($brechoRecords as $i => $record) {
        echo "\n--- Brechó Registro " . ($i + 1) . " ---\n";
        echo "ID: {$record['id']} | Data: {$record['created_at']}\n";
        echo "Situação: " . substr($record['situation_summary'], 0, 100) . "...\n";
        echo "IA: " . substr($record['ai_suggestion'], 0, 80) . "...\n";
        echo "Humano: " . substr($record['human_response'], 0, 80) . "...\n";
    }
} else {
    echo "❌ Nenhum registro encontrado sobre brechó/segmento\n";
}

// 5. Simula como a IA usaria estes exemplos
echo "\n5. SIMULAÇÃO DE USO DOS EXEMPLOS PELA IA:\n";

// Busca todos os exemplos para contextos relevantes
$stmt = $db->prepare('
    SELECT situation_summary, ai_suggestion, human_response
    FROM ai_learned_responses
    WHERE context_slug IN ("geral", "ecommerce", "sites") 
       AND objective IN ("first_contact", "follow_up")
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute();
$examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ Exemplos que a IA receberia em próximos contatos:\n";
foreach ($examples as $i => $ex) {
    echo "\nExemplo " . ($i + 1) . ":\n";
    echo "Situação: {$ex['situation_summary']}\n";
    echo "IA sugeriu: " . substr($ex['ai_suggestion'], 0, 100) . "...\n";
    echo "Humano corrigiu: " . substr($ex['human_response'], 0, 100) . "...\n";
}

// 6. Verifica se há padrões de aprendizado específicos
echo "\n6. ANÁLISE DE PADRÕES DE APRENDIZADO:\n";

$totalRecords = 0;
$patterns = [
    'apresentacao' => 0,
    'brevidade' => 0,
    'segmento' => 0,
    'refinamento' => 0
];

$stmt = $db->prepare('SELECT COUNT(*) as total FROM ai_learned_responses');
$stmt->execute();
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->prepare('
    SELECT COUNT(*) as count FROM ai_learned_responses 
    WHERE situation_summary LIKE "%apresentaç%" 
       OR ai_suggestion LIKE "%Charles da Pixel12%"
');
$stmt->execute();
$patterns['apresentacao'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare('
    SELECT COUNT(*) as count FROM ai_learned_responses 
    WHERE situation_summary LIKE "%brevidade%" 
       OR situation_summary LIKE "%naturalidade%"
');
$stmt->execute();
$patterns['brevidade'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare('
    SELECT COUNT(*) as count FROM ai_learned_responses 
    WHERE situation_summary LIKE "%segmento%" 
       OR situation_summary LIKE "%entendi seu segmento%"
');
$stmt->execute();
$patterns['segmento'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare('
    SELECT COUNT(*) as count FROM ai_learned_responses 
    WHERE situation_summary LIKE "%Refinamento%"
');
$stmt->execute();
$patterns['refinamento'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "Total de registros no banco: {$totalRecords}\n";
echo "Padrões identificados:\n";
echo "- Apresentação: {$patterns['apresentacao']} registros\n";
echo "- Brevidade/Naturalidade: {$patterns['brevidade']} registros\n";
echo "- Segmento: {$patterns['segmento']} registros\n";
echo "- Refinamentos: {$patterns['refinamento']} registros\n";

echo "\n=== CONCLUSÃO BASEADA EM EVIDÊNCIAS ===\n";
if (count($recent) > 0) {
    echo "✅ EVIDÊNCIAS ENCONTRADAS:\n";
    echo "   - " . count($recent) . " registros nas últimas 2 horas\n";
    echo "   - " . count($fatimaRecords) . " registros mencionando Fátima\n";
    echo "   - " . count($apresentationRecords) . " registros sobre apresentação\n";
    echo "   - " . count($brechoRecords) . " registros sobre brechó/segmento\n";
    echo "\n✅ IMPACTO NO APRENDIZADO:\n";
    echo "   - IA terá exemplos concretos para evitar apresentações repetidas\n";
    echo "   - IA aprenderá a ser mais breve e natural\n";
    echo "   - IA entenderá contexto de brechó/segmento específico\n";
} else {
    echo "❌ EVIDÊNCIAS NÃO ENCONTRADAS:\n";
    echo "   - Nenhum registro recente de refinamento\n";
    echo "   - Possível problema no salvamento automático\n";
    echo "   - Verificar se as mensagens foram enviadas após refinamento\n";
}

?>
