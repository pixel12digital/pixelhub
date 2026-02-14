<?php
require __DIR__ . '/../src/Core/Env.php';
PixelHub\Core\Env::load(__DIR__ . '/../.env');

echo "=== Configurações OpenAI atuais ===\n";
echo "Modelo: " . (PixelHub\Core\Env::get('OPENAI_MODEL') ?: 'gpt-4.1-mini (padrão)') . "\n";
echo "Temperature: " . (PixelHub\Core\Env::get('OPENAI_TEMPERATURE') ?: '0.7 (padrão)') . "\n";
echo "Max Tokens: " . (PixelHub\Core\Env::get('OPENAI_MAX_TOKENS') ?: '800 (padrão)') . "\n";

echo "\n=== Estimativa de economia ===\n";
echo "Antes das otimizações:\n";
echo "- Histórico: 20 mensagens (~2000 tokens)\n";
echo "- KB: 3000 caracteres (~750 tokens)\n";
echo "- Exemplos: 5 (~500 tokens)\n";
echo "- Max output: 1000 tokens\n";
echo "- Modelo: gpt-4o-mini\n";
echo "Total médio por chamada: ~4250 tokens\n\n";

echo "Após otimizações:\n";
echo "- Histórico: 10 mensagens (~1000 tokens)\n";
echo "- KB: 1500 caracteres (~375 tokens)\n";
echo "- Exemplos: 3 (~300 tokens)\n";
echo "- Max output: 600 tokens\n";
echo "- Modelo: gpt-4.1-mini (~40% mais barato)\n";
echo "Total médio por chamada: ~2275 tokens\n\n";

echo "Economia estimada: ~46% menos tokens + 40% modelo mais barato = ~67% de redução de custo\n";
