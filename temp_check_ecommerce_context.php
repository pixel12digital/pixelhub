<?php
require_once __DIR__ . '/vendor/autoload.php';

$db = \PixelHub\Core\DB::getConnection();
$stmt = $db->prepare('SELECT name, slug, system_prompt, knowledge_base FROM ai_contexts WHERE slug = ? LIMIT 1');
$stmt->execute(['ecommerce']);
$ctx = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ctx) {
    echo "Nome: {$ctx['name']}\n\n";
    echo "System Prompt:\n" . str_repeat('=', 80) . "\n{$ctx['system_prompt']}\n" . str_repeat('=', 80) . "\n\n";
    echo "Knowledge Base:\n" . str_repeat('=', 80) . "\n" . ($ctx['knowledge_base'] ?: '(vazio)') . "\n" . str_repeat('=', 80);
} else {
    echo 'Contexto ecommerce não encontrado';
}
