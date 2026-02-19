<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== ESTRUTURA DA TABELA AI_CONTEXTS ===\n";
$stmt = $db->prepare('DESCRIBE ai_contexts');
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== CONTEXTOS CADASTRADOS ===\n";
$stmt = $db->prepare('SELECT id, name, slug, description, is_active, sort_order FROM ai_contexts ORDER BY sort_order ASC, name ASC');
$stmt->execute();
$contexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contexts as $ctx) {
    echo "ID: {$ctx['id']} | Slug: {$ctx['slug']} | Nome: {$ctx['name']} | Ativo: " . ($ctx['is_active'] ? 'SIM' : 'NÃO') . "\n";
    echo "  Descrição: " . ($ctx['description'] ?: 'N/A') . "\n";
    echo "---\n";
}

echo "\n=== ESTRUTURA DA TABELA AI_LEARNED_RESPONSES ===\n";
$stmt = $db->prepare('DESCRIBE ai_learned_responses');
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== EXEMPLOS DE APRENDIZADO (últimos 10) ===\n";
$stmt = $db->prepare('SELECT context_slug, objective, situation_summary, created_at FROM ai_learned_responses ORDER BY created_at DESC LIMIT 10');
$stmt->execute();
$learned = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($learned as $item) {
    echo "Contexto: {$item['context_slug']} | Objetivo: {$item['objective']}\n";
    echo "Situação: " . substr($item['situation_summary'], 0, 100) . "...\n";
    echo "Data: {$item['created_at']}\n";
    echo "---\n";
}
?>
