<?php
/**
 * Script de correção: Atualiza objetivos do contexto Financeiro
 * Rode no servidor: php fix_financeiro_objectives.php
 */

require 'vendor/autoload.php';
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load(__DIR__);
$db = \PixelHub\Core\DB::getConnection();

echo "=== CORREÇÃO: Objetivos do Contexto Financeiro ===\n\n";

// Verifica estado atual
$stmt = $db->query("SELECT slug, name, allowed_objectives FROM ai_contexts WHERE slug = 'financeiro'");
$current = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estado ATUAL:\n";
echo "Slug: " . $current['slug'] . "\n";
echo "Nome: " . $current['name'] . "\n";
echo "allowed_objectives: " . ($current['allowed_objectives'] ?: 'NULL') . "\n\n";

// Atualiza para os novos objetivos
$now = date('Y-m-d H:i:s');
$db->exec("
    UPDATE ai_contexts 
    SET allowed_objectives = JSON_ARRAY('atendimento_financeiro', 'cobranca'),
        updated_at = '{$now}'
    WHERE slug = 'financeiro'
");

echo "✅ Atualização aplicada!\n\n";

// Verifica estado após atualização
$stmt = $db->query("SELECT slug, name, allowed_objectives FROM ai_contexts WHERE slug = 'financeiro'");
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estado DEPOIS:\n";
echo "Slug: " . $updated['slug'] . "\n";
echo "Nome: " . $updated['name'] . "\n";
echo "allowed_objectives: " . ($updated['allowed_objectives'] ?: 'NULL') . "\n";

$decoded = json_decode($updated['allowed_objectives'], true);
echo "Objetivos decodificados:\n";
foreach ($decoded as $obj) {
    echo "  - {$obj}\n";
}

echo "\n✅ Correção concluída com sucesso!\n";
echo "\nAgora recarregue a página no navegador (Ctrl+Shift+R) para ver os novos objetivos.\n";
