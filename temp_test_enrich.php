<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\ProspectingService;

echo "=== TESTE DE ENRIQUECIMENTO GOOGLE MAPS ===\n\n";

try {
    // Testa com o ID 113 (KRUG TECIDOS)
    $resultId = 113;
    
    echo "Buscando resultado ID: $resultId\n";
    
    $data = ProspectingService::searchGoogleMapsForEnrichment($resultId);
    
    echo "\n✅ SUCESSO!\n\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
