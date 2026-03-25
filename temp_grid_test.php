<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}
\PixelHub\Core\Env::load(__DIR__ . '/');

use PixelHub\Services\GooglePlacesClient;
use PixelHub\Core\DB;

$client = new GooglePlacesClient();

echo "=== TESTE GRADE GEOGRÁFICA - Lojas em Blumenau ===\n\n";

// 1. Busca bbox de Blumenau via Nominatim
echo "1. Buscando bounding box de Blumenau SC via Nominatim...\n";
$bbox = GooglePlacesClient::fetchCityBbox('Blumenau', 'SC');
if (!$bbox) {
    die("ERRO: Nominatim não retornou bbox.\n");
}
echo "   BBox: south={$bbox['south']}, north={$bbox['north']}, west={$bbox['west']}, east={$bbox['east']}\n";
$latSpan = round(($bbox['north'] - $bbox['south']) * 111.32, 1);
$lngSpan = round(($bbox['east']  - $bbox['west'])  * 111.32 * cos(deg2rad(($bbox['north']+$bbox['south'])/2)), 1);
echo "   Dimensões aprox: {$latSpan}km (N-S) × {$lngSpan}km (L-O)\n\n";

// 2. Gera grade 3×3
echo "2. Gerando grade 3×3...\n";
$cells3 = GooglePlacesClient::generateGrid($bbox, 3);
echo "   Células geradas: " . count($cells3) . "\n";
echo "   Raio de cada célula: " . $cells3[0]['radius'] . "m\n\n";

// 3. Testa busca DIRETA (sem locationRestriction) — como na Fase 1
echo "3. Busca DIRETA 'loja Blumenau SC' (sem locationRestriction, max 60)...\n";
$directResults = $client->textSearch('loja Blumenau SC', 60);
echo "   Resultados: " . count($directResults) . "\n\n";

// 4. Testa busca COM locationRestriction — keyword SIMPLES sem cidade
echo "4. Testando células da grade com keyword SIMPLES 'loja' (sem nome da cidade)...\n";
$totalGrid = 0;
$seenIds = [];
foreach ($directResults as $r) { $seenIds[$r['google_place_id']] = true; }

foreach (array_slice($cells3, 0, 3) as $i => $cell) {
    $restriction = ['lat' => $cell['lat'], 'lng' => $cell['lng'], 'radius' => $cell['radius']];
    $batch = $client->textSearch('loja', 60, $restriction);
    $newInCell = 0;
    foreach ($batch as $r) {
        if (!isset($seenIds[$r['google_place_id']])) {
            $seenIds[$r['google_place_id']] = true;
            $newInCell++;
        }
    }
    $totalGrid += $newInCell;
    echo "   Célula " . ($i+1) . " (lat={$cell['lat']}, lng={$cell['lng']}, r={$cell['radius']}m): " . count($batch) . " total, {$newInCell} novos\n";
}
echo "\n";

// 5. Testa com keyword "loja Blumenau" (com cidade mas sem estado)
echo "5. Testando mesmas células com 'loja Blumenau'...\n";
$seenIds2 = [];
foreach ($directResults as $r) { $seenIds2[$r['google_place_id']] = true; }
$totalGrid2 = 0;

foreach (array_slice($cells3, 0, 3) as $i => $cell) {
    $restriction = ['lat' => $cell['lat'], 'lng' => $cell['lng'], 'radius' => $cell['radius']];
    $batch = $client->textSearch('loja Blumenau', 60, $restriction);
    $newInCell = 0;
    foreach ($batch as $r) {
        if (!isset($seenIds2[$r['google_place_id']])) {
            $seenIds2[$r['google_place_id']] = true;
            $newInCell++;
        }
    }
    $totalGrid2 += $newInCell;
    echo "   Célula " . ($i+1) . ": " . count($batch) . " total, {$newInCell} novos\n";
}
echo "\n";

// 6. Estimativa total
echo "=== ESTIMATIVA DE RESULTADOS TOTAIS ===\n";
echo "   Fase 1 (direta):           " . count($directResults) . " resultados\n";
$avgPerCell = $totalGrid > 0 ? round($totalGrid / 3) : '(0 com keyword simples)';
$avgPerCell2 = $totalGrid2 > 0 ? round($totalGrid2 / 3) : '(0 com keyword+cidade)';
echo "   Teste grade 3×3 keyword simples: ~{$avgPerCell} novos/célula × 9 = ~" . (is_int($avgPerCell) ? $avgPerCell * 9 : '?') . " potencial\n";
echo "   Teste grade 3×3 keyword+cidade:  ~{$avgPerCell2} novos/célula × 9 = ~" . (is_int($avgPerCell2) ? $avgPerCell2 * 9 : '?') . " potencial\n";
$total3x3 = is_int($avgPerCell) ? count($directResults) + $avgPerCell * 9 : count($directResults);
echo "   TOTAL estimado (grade 3×3): ~{$total3x3} resultados únicos\n";
echo "   TOTAL estimado (grade 4×4): ~" . (count($directResults) + (is_int($avgPerCell) ? $avgPerCell * 16 : 0)) . " resultados únicos\n";
echo "   TOTAL estimado (grade 5×5): ~" . (count($directResults) + (is_int($avgPerCell) ? $avgPerCell * 25 : 0)) . " resultados únicos\n";
echo "\nAtenção: limitação real depende da densidade de lojas cadastradas no Google Maps para a cidade.\n";
