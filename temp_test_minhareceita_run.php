<?php
// Simula exatamente o que runSearch faz para minhareceita
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Services\MinhaReceitaClient;
use PixelHub\Services\ProspectingService;
use PixelHub\Core\DB;

// Busca a receita mais recente de minhareceita
$db = DB::getConnection();
$stmt = $db->query("SELECT * FROM prospecting_recipes WHERE source='minhareceita' ORDER BY id DESC LIMIT 1");
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$recipe) {
    echo "ERRO: Nenhuma receita minhareceita encontrada\n";
    exit(1);
}

echo "Receita encontrada: ID={$recipe['id']}, Nome={$recipe['name']}\n";
echo "CNAE: {$recipe['cnae_code']}\n";
echo "CNAEs: {$recipe['cnaes']}\n";
echo "UF: {$recipe['state']}\n";
echo "Cidade: {$recipe['city']}\n";
echo "Keywords: {$recipe['keywords']}\n\n";

// Testa só o cliente com 3 resultados
echo "--- Testando MinhaReceitaClient com 3 resultados ---\n";
$client = new MinhaReceitaClient();

$cnaeCode = $recipe['cnae_code'] ?? '';
if (empty($cnaeCode) && !empty($recipe['cnaes'])) {
    $cnaes = json_decode($recipe['cnaes'], true);
    $cnaeCode = $cnaes[0]['code'] ?? '';
}

echo "CNAE code limpo: $cnaeCode\n";

try {
    $places = $client->searchByCnaeAndRegion($cnaeCode, '', null, 3, null, 5);
    echo "Sucesso! " . count($places) . " resultados\n";
    if (!empty($places)) {
        echo "Primeiro resultado: " . ($places[0]['name'] ?? 'N/A') . " — " . ($places[0]['cnpj'] ?? 'N/A') . "\n";
    }
} catch (\Throwable $e) {
    echo "ERRO: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Testa ProspectingService::runSearch
echo "\n--- Testando ProspectingService::runSearch com 5 resultados ---\n";
try {
    $result = ProspectingService::runSearch($recipe['id'], 5);
    echo "Sucesso!\n";
    echo "found={$result['found']}, new={$result['new']}, duplicates={$result['duplicates']}\n";
} catch (\Throwable $e) {
    echo "ERRO: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
