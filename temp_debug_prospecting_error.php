<?php
/**
 * Script de diagnóstico para investigar erros ao salvar resultados do Google Maps
 */

require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\ProspectingService;

// ID da receita que está dando erro (ajustar conforme necessário)
$recipeId = 1; // Trocar pelo ID da receita "Corretores de Imóveis em Blumenau"

echo "=== DIAGNÓSTICO DE ERROS - PROSPECÇÃO GOOGLE MAPS ===\n\n";

// 1. Verificar estrutura da tabela
echo "1. Estrutura da tabela prospecting_results:\n";
$db = DB::getConnection();
$columns = $db->query("DESCRIBE prospecting_results")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "   - {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

// 2. Verificar receita
echo "\n2. Dados da receita:\n";
$recipe = ProspectingService::findRecipeById($recipeId);
if ($recipe) {
    echo "   - ID: {$recipe['id']}\n";
    echo "   - Nome: {$recipe['name']}\n";
    echo "   - Source: " . ($recipe['source'] ?? 'google_maps') . "\n";
    echo "   - Tenant ID: " . ($recipe['tenant_id'] ?? 'NULL') . "\n";
} else {
    echo "   ERRO: Receita não encontrada!\n";
    exit(1);
}

// 3. Simular inserção de um resultado de teste
echo "\n3. Testando inserção de resultado:\n";
try {
    $testData = [
        'recipe_id' => $recipeId,
        'tenant_id' => $recipe['tenant_id'] ?? null,
        'google_place_id' => 'TEST_' . uniqid(),
        'name' => 'Empresa Teste',
        'address' => 'Rua Teste, 123',
        'city' => 'Blumenau',
        'state' => 'SC',
        'phone' => null,
        'website' => null,
        'rating' => null,
        'user_ratings_total' => null,
        'lat' => -26.9194,
        'lng' => -49.0661,
        'google_types' => json_encode(['real_estate_agency']),
    ];
    
    $stmt = $db->prepare("
        INSERT INTO prospecting_results
            (recipe_id, tenant_id, google_place_id, name, address, city, state, phone, website,
             rating, user_ratings_total, lat, lng, google_types, source, status, found_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'google_maps', 'new', NOW(), NOW())
    ");
    
    $stmt->execute([
        $testData['recipe_id'],
        $testData['tenant_id'],
        $testData['google_place_id'],
        $testData['name'],
        $testData['address'],
        $testData['city'],
        $testData['state'],
        $testData['phone'],
        $testData['website'],
        $testData['rating'],
        $testData['user_ratings_total'],
        $testData['lat'],
        $testData['lng'],
        $testData['google_types'],
    ]);
    
    echo "   ✓ Inserção de teste bem-sucedida!\n";
    echo "   - ID inserido: " . $db->lastInsertId() . "\n";
    
    // Limpar teste
    $db->prepare("DELETE FROM prospecting_results WHERE google_place_id = ?")->execute([$testData['google_place_id']]);
    echo "   ✓ Registro de teste removido\n";
    
} catch (Exception $e) {
    echo "   ✗ ERRO ao inserir: " . $e->getMessage() . "\n";
    echo "   - Código: " . $e->getCode() . "\n";
    echo "   - SQL State: " . ($stmt->errorInfo()[0] ?? 'N/A') . "\n";
}

// 4. Verificar últimos erros de inserção
echo "\n4. Verificando últimos resultados salvos:\n";
$recent = $db->query("
    SELECT id, recipe_id, name, source, status, found_at 
    FROM prospecting_results 
    WHERE recipe_id = $recipeId 
    ORDER BY id DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($recent)) {
    echo "   Nenhum resultado encontrado para esta receita.\n";
} else {
    foreach ($recent as $r) {
        echo "   - ID {$r['id']}: {$r['name']} (source: {$r['source']}, status: {$r['status']})\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
