<?php
// Script para debug em produção - rode no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/enrich_debug.log');

require 'vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\ProspectingService;

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG ENRIQUECIMENTO GOOGLE MAPS (PRODUÇÃO) ===\n\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo "IP do servidor: " . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "\n\n";

try {
    // Busca primeiro resultado da receita Witmarsum
    $db = DB::getConnection();
    $stmt = $db->prepare("SELECT id, name, city, state FROM prospecting_results WHERE recipe_id = 7 AND source = 'minhareceita' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception("Nenhum resultado encontrado para testar");
    }
    
    echo "Testando com:\n";
    echo "  ID: {$result['id']}\n";
    echo "  Nome: {$result['name']}\n";
    echo "  Cidade: {$result['city']}\n";
    echo "  Estado: {$result['state']}\n\n";
    
    echo "Buscando no Google Maps...\n\n";
    
    $data = ProspectingService::searchGoogleMapsForEnrichment($result['id']);
    
    echo "✅ SUCESSO!\n\n";
    echo "Confiança: {$data['confidence_label']} ({$data['confidence']}%)\n\n";
    echo "Dados encontrados:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}
