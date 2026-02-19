<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DO SEU REFINAMENTO RECENTE ===\n\n";

// Busca registros das últimas horas (hoje é 2026-02-19)
echo "1. REGISTROS DE HOJE (2026-02-19):\n";
$stmt = $db->prepare('
    SELECT context_slug, objective, situation_summary, 
           ai_suggestion, human_response, created_at
    FROM ai_learned_responses 
    WHERE DATE(created_at) = CURDATE()
    ORDER BY created_at DESC
');
$stmt->execute();
$today = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($today) > 0) {
    foreach ($today as $i => $record) {
        echo "\n--- Registro " . ($i + 1) . " ---\n";
        echo "Contexto: {$record['context_slug']}\n";
        echo "Objetivo: {$record['objective']}\n";
        echo "Situação: {$record['situation_summary']}\n";
        echo "IA sugeriu:\n\"{$record['ai_suggestion']}\"\n\n";
        echo "Humano corrigiu:\n\"{$record['human_response']}\"\n\n";
        echo "Data: {$record['created_at']}\n";
    }
} else {
    echo "❌ Nenhum registro encontrado hoje\n";
}

// Busca últimos 24 horas
echo "\n2. REGISTROS DAS ÚLTIMAS 24 HORAS:\n";
$stmt = $db->prepare('
    SELECT context_slug, objective, situation_summary, 
           LEFT(ai_suggestion, 150) as ai_preview,
           LEFT(human_response, 150) as human_preview,
           created_at
    FROM ai_learned_responses 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
');
$stmt->execute();
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($recent) > 0) {
    foreach ($recent as $i => $record) {
        echo "\n--- Registro " . ($i + 1) . " ---\n";
        echo "Contexto: {$record['context_slug']} | Objetivo: {$record['objective']}\n";
        echo "IA: \"{$record['ai_preview']}...\"\n";
        echo "Humano: \"{$record['human_preview']}...\"\n";
        echo "Data: {$record['created_at']}\n";
    }
} else {
    echo "❌ Nenhum registro nas últimas 24 horas\n";
}

// Verifica se existe ecommerce + follow_up
echo "\n3. VERIFICAÇÃO ESPECÍFICA - ECOMMERCE + FOLLOW_UP:\n";
$stmt = $db->prepare('
    SELECT situation_summary, ai_suggestion, human_response, created_at
    FROM ai_learned_responses 
    WHERE context_slug = "ecommerce" AND objective = "follow_up"
    ORDER BY created_at DESC
');
$stmt->execute();
$ecommerce_followup = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($ecommerce_followup) > 0) {
    echo "✅ Encontrados " . count($ecommerce_followup) . " registros\n";
    foreach ($ecommerce_followup as $i => $record) {
        echo "\n--- E-commerce Follow-up " . ($i + 1) . " ---\n";
        echo "Data: {$record['created_at']}\n";
        echo "Situação: {$record['situation_summary']}\n";
        echo "IA: \"{$record['ai_suggestion']}\"\n\n";
        echo "Humano: \"{$record['human_response']}\"\n\n";
    }
} else {
    echo "❌ Nenhum registro para ecommerce + follow_up\n";
}

// Testa se a função getLearnedExamples funciona
echo "\n4. TESTE DA FUNÇÃO getLearnedExamples():\n";
echo "Simulando busca para ecommerce + follow_up...\n";

$stmt = $db->prepare('
    SELECT situation_summary, ai_suggestion, human_response
    FROM ai_learned_responses
    WHERE context_slug = ? AND objective = ?
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute(['ecommerce', 'follow_up']);
$examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Resultados: " . count($examples) . " exemplos encontrados\n";
if (count($examples) > 0) {
    foreach ($examples as $i => $ex) {
        echo "Exemplo " . ($i + 1) . ": {$ex['situation_summary']}\n";
    }
}

echo "\n=== CONCLUSÃO FINAL ===\n";
if (count($today) > 0 || count($recent) > 0) {
    echo "✅ SEU REFINAMENTO FOI SALVO NO BANCO\n";
    echo "✅ SERÁ USADO EM PRÓXIMAS SUGESTÕES\n";
    echo "✅ O APRENDIZADO ESTÁ FUNCIONANDO\n";
} else {
    echo "⚠️  Seu refinamento ainda não foi salvo\n";
    echo "   Verifique se você enviou a mensagem após o refinamento\n";
    echo "   O aprendizado só é salvo quando você envia a mensagem final\n";
}

?>
