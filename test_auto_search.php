<?php
/**
 * Script de teste para auto-search de oportunidades
 * 
 * Para testar:
 * 1. Acesse /opportunities no PixelHub
 * 2. Digite "9601" no campo de busca (deve disparar com 2+ dígitos)
 * 3. Digite "gle" no campo de busca (deve disparar com 3+ caracteres)
 * 4. Digite "@gmail" no campo de busca (deve buscar por e-mails)
 * 5. Teste o Enter (fallback)
 */

require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Services/OpportunityService.php';

use PixelHub\Services\OpportunityService;

echo "=== Teste Auto-Search Oportunidades ===\n\n";

// Teste 1: Busca por telefone (2+ dígitos)
echo "1. Teste busca por telefone '9601':\n";
$results = OpportunityService::list(['search' => '9601']);
echo "   Resultados: " . count($results) . " oportunidades\n";
foreach ($results as $opp) {
    echo "   - {$opp['name']} ({$opp['contact_name']})\n";
}
echo "\n";

// Teste 2: Busca por nome (3+ caracteres)
echo "2. Teste busca por nome 'gle':\n";
$results = OpportunityService::list(['search' => 'gle']);
echo "   Resultados: " . count($results) . " oportunidades\n";
foreach ($results as $opp) {
    echo "   - {$opp['name']} ({$opp['contact_name']})\n";
}
echo "\n";

// Teste 3: Busca por e-mail
echo "3. Teste busca por e-mail '@gmail':\n";
$results = OpportunityService::list(['search' => '@gmail']);
echo "   Resultados: " . count($results) . " oportunidades\n";
foreach ($results as $opp) {
    echo "   - {$opp['name']} ({$opp['contact_name']})\n";
}
echo "\n";

// Teste 4: Busca com menos caracteres (não deve retornar nada no auto-search)
echo "4. Teste busca com 'gl' (2 chars, não numérico):\n";
$results = OpportunityService::list(['search' => 'gl']);
echo "   Resultados: " . count($results) . " oportunidades (backend permite, mas frontend não dispara)\n";
echo "\n";

// Teste 5: Busca com 1 dígito (não deve disparar no frontend)
echo "5. Teste busca com '9' (1 dígito):\n";
$results = OpportunityService::list(['search' => '9']);
echo "   Resultados: " . count($results) . " oportunidades (backend permite, mas frontend não dispara)\n";
echo "\n";

echo "=== Fim dos Testes ===\n";
echo "\nRegras implementadas:\n";
echo "- Texto: dispara com ≥ 3 caracteres\n";
echo "- Numérico: dispara com ≥ 2 dígitos\n";
echo "- Debounce: 350ms\n";
echo "- Enter: funciona como fallback\n";
echo "- Busca por: nome, cliente, lead, e-mail, telefone (normalizado)\n";
