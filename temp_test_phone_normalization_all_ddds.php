<?php

require_once __DIR__ . '/src/Core/PhoneNormalizer.php';

use PixelHub\Core\PhoneNormalizer;

echo "=== TESTE DE NORMALIZAÇÃO DE TELEFONE - TODOS OS DDDs ===\n\n";

// Testes com diferentes DDDs
// REGRA: WhatsApp no Brasil hoje entrega no formato de 10 dígitos (DDD + 8 dígitos)
// Se número tem 11 dígitos (DDD + 9 + 8), adiciona 55 e remove o 9 → 12 dígitos finais
// Se número tem 13 dígitos (55 + DDD + 9 + 8), remove o 9 → 12 dígitos finais
$testCases = [
    // DDD 47 (Santa Catarina)
    ['input' => '47996346699', 'expected' => '+554796346699', 'description' => 'DDD 47 com 11 dígitos (9 extra) - deve remover o 9'],
    ['input' => '5547996346699', 'expected' => '+554796346699', 'description' => 'DDD 47 com 13 dígitos (55 + 9 extra) - deve remover o 9'],
    ['input' => '554796346699', 'expected' => '+554796346699', 'description' => 'DDD 47 com 12 dígitos (formato correto)'],
    ['input' => '4796346699', 'expected' => '+554796346699', 'description' => 'DDD 47 com 10 dígitos (sem 9) - formato correto'],
    
    // DDD 48 (Santa Catarina)
    ['input' => '48912345678', 'expected' => '+554812345678', 'description' => 'DDD 48 com 11 dígitos (9 extra) - deve remover o 9'],
    ['input' => '5548912345678', 'expected' => '+554812345678', 'description' => 'DDD 48 com 13 dígitos (55 + 9 extra) - deve remover o 9'],
    ['input' => '554812345678', 'expected' => '+554812345678', 'description' => 'DDD 48 com 12 dígitos (formato correto)'],
    ['input' => '4812345678', 'expected' => '+554812345678', 'description' => 'DDD 48 com 10 dígitos (sem 9) - formato correto'],
    
    // DDD 41 (Paraná)
    ['input' => '41987654321', 'expected' => '+554187654321', 'description' => 'DDD 41 com 11 dígitos (9 extra) - deve remover o 9'],
    ['input' => '5541987654321', 'expected' => '+554187654321', 'description' => 'DDD 41 com 13 dígitos (55 + 9 extra) - deve remover o 9'],
    ['input' => '554187654321', 'expected' => '+554187654321', 'description' => 'DDD 41 com 12 dígitos (formato correto)'],
    ['input' => '4187654321', 'expected' => '+554187654321', 'description' => 'DDD 41 com 10 dígitos (sem 9) - formato correto'],
    
    // DDD 11 (São Paulo)
    ['input' => '11999999999', 'expected' => '+551199999999', 'description' => 'DDD 11 com 11 dígitos (9 extra) - deve remover o 9'],
    ['input' => '5511999999999', 'expected' => '+551199999999', 'description' => 'DDD 11 com 13 dígitos (55 + 9 extra) - deve remover o 9'],
    ['input' => '551199999999', 'expected' => '+551199999999', 'description' => 'DDD 11 com 12 dígitos (formato correto)'],
    ['input' => '1199999999', 'expected' => '+551199999999', 'description' => 'DDD 11 com 10 dígitos (sem 9) - formato correto'],
    
    // DDD 21 (Rio de Janeiro)
    ['input' => '21988887777', 'expected' => '+552188887777', 'description' => 'DDD 21 com 11 dígitos (9 extra) - deve remover o 9'],
    ['input' => '5521988887777', 'expected' => '+552188887777', 'description' => 'DDD 21 com 13 dígitos (55 + 9 extra) - deve remover o 9'],
    ['input' => '552188887777', 'expected' => '+552188887777', 'description' => 'DDD 21 com 12 dígitos (formato correto)'],
    ['input' => '2188887777', 'expected' => '+552188887777', 'description' => 'DDD 21 com 10 dígitos (sem 9) - formato correto'],
    
    // DDD 85 (Ceará)
    ['input' => '85987654321', 'expected' => '+558587654321', 'description' => 'DDD 85 com 11 dígitos (9 extra) - deve remover o 9'],
    ['input' => '5585987654321', 'expected' => '+558587654321', 'description' => 'DDD 85 com 13 dígitos (55 + 9 extra) - deve remover o 9'],
    ['input' => '558587654321', 'expected' => '+558587654321', 'description' => 'DDD 85 com 12 dígitos (formato correto)'],
    ['input' => '8587654321', 'expected' => '+558587654321', 'description' => 'DDD 85 com 10 dígitos (sem 9) - formato correto'],
];

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    $result = PhoneNormalizer::toE164OrNull($test['input']);
    $status = ($result === $test['expected']) ? '✓ PASS' : '✗ FAIL';
    
    if ($result === $test['expected']) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo sprintf(
        "%s | %s\n   Input: %s\n   Expected: %s\n   Got: %s\n\n",
        $status,
        $test['description'],
        $test['input'],
        $test['expected'],
        $result ?? 'NULL'
    );
}

echo "\n=== RESUMO ===\n";
echo "Total: " . count($testCases) . " testes\n";
echo "Passou: $passed\n";
echo "Falhou: $failed\n";

if ($failed === 0) {
    echo "\n✓ TODOS OS TESTES PASSARAM!\n";
} else {
    echo "\n✗ ALGUNS TESTES FALHARAM!\n";
}
