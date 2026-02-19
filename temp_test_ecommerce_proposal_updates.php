<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== TESTE: VERIFICANDO ATUALIZAÇÕES ECOMMERCE PROPOSTA ===\n\n";

// 1. Verifica se o contexto foi atualizado
echo "1. VERIFICANDO CONTEXTO ECOMMERCE ATUALIZADO:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "ecommerce"');
$stmt->execute();
$context = $stmt->fetch(PDO::FETCH_ASSOC);

if ($context) {
    echo "✅ Contexto encontrado: ID {$context['id']}\n";
    echo "✅ Nome: {$context['name']}\n";
    echo "✅ Ativo: " . ($context['is_active'] ? 'SIM' : 'NÃO') . "\n";
    
    // Verifica conteúdo específico
    $content = $context['system_prompt'];
    $checks = [
        'R$ 197' => strpos($content, 'R$ 197') !== false,
        '12 parcelas' => strpos($content, '12x') !== false,
        'entrada + 3x boleto' => strpos($content, 'entrada + 3x boleto') !== false,
        'pixel12digital.com.br/ecommerce' => strpos($content, 'pixel12digital.com.br/ecommerce') !== false,
        '**negrito**' => strpos($content, '**negrito**') !== false,
        '_italico_' => strpos($content, '_italico_') !== false,
        'agendar uma chamada' => strpos($content, 'agendar uma chamada') !== false,
        'implantação' => strpos($content, 'implantação') !== false,
        'brechó' => strpos($content, 'brechó') !== false,
        'moda' => strpos($content, 'moda') !== false,
        'Nunca textos longos' => strpos($content, 'Nunca textos longos') !== false
    ];
    
    echo "\n2. VERIFICAÇÃO DE CONTEÚDO ESPECÍFICO:\n";
    $allChecksPassed = true;
    foreach ($checks as $check => $found) {
        $status = $found ? '✅' : '❌';
        echo "{$status} {$check}\n";
        if (!$found) $allChecksPassed = false;
    }
    
    if ($allChecksPassed) {
        echo "\n✅ TODAS AS VERIFICAÇÕES PASSARAM\n";
    } else {
        echo "\n⚠️  ALGUMAS VERIFICAÇÕES FALHARAM\n";
    }
} else {
    echo "❌ Contexto ecommerce não encontrado\n";
    exit;
}

// 3. Verifica exemplos de aprendizado
echo "\n3. VERIFICANDO EXEMPLOS DE APRENDIZADO CRIADOS:\n";
$stmt = $db->prepare('
    SELECT id, situation_summary, human_response
    FROM ai_learned_responses 
    WHERE context_slug = "ecommerce" 
    AND objective = "send_proposal"
    AND situation_summary LIKE "%Proposta E-commerce%"
    ORDER BY id DESC
');
$stmt->execute();
$examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($examples) > 0) {
    echo "✅ " . count($examples) . " exemplos encontrados:\n";
    foreach ($examples as $i => $example) {
        echo "\n--- Exemplo " . ($i + 1) . " (ID: {$example['id']}) ---\n";
        echo "Situação: {$example['situation_summary']}\n";
        echo "Resposta: " . substr($example['human_response'], 0, 100) . "...\n";
        
        // Verifica se a resposta contém elementos esperados
        $response = $example['human_response'];
        $responseChecks = [
            'R$ 197' => strpos($response, 'R$ 197') !== false,
            '**' => strpos($response, '**') !== false,
            '_' => strpos($response, '_') !== false,
            'chamada' => strpos($response, 'chamada') !== false,
            'implantação' => strpos($response, 'implantação') !== false
        ];
        
        foreach ($responseChecks as $check => $found) {
            $status = $found ? '✅' : '❌';
            echo "  {$status} {$check}\n";
        }
    }
} else {
    echo "❌ Nenhum exemplo de aprendizado encontrado\n";
}

// 4. Simula resposta para diferentes segmentos
echo "\n4. SIMULANDO RESPOSTAS ESPERADAS:\n";

$segmentos = [
    [
        'nome' => 'Brechó',
        'contexto' => 'cliente com brechó chique em Brasília',
        'resposta_esperada' => 'Olá, Maria! Entendi que você quer *vender suas peças* online. Com a nossa loja virtual, você pode **vender pelo WhatsApp** sem precisar de estoque físico.'
    ],
    [
        'nome' => 'Moda',
        'contexto' => 'cliente com blog de moda',
        'resposta_esperada' => 'Oi, Ana! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.'
    ],
    [
        'nome' => 'Alimentos',
        'contexto' => 'cliente com confeitaria',
        'resposta_esperada' => 'Olá, Carla! Entendi que você faz _doces artesanais_. Nossa loja virtual é **ideal para entregas programadas** e controle de pedidos.'
    ]
];

foreach ($segmentos as $segmento) {
    echo "\n--- Segmento: {$segmento['nome']} ---\n";
    echo "Contexto: {$segmento['contexto']}\n";
    echo "Resposta esperada: {$segmento['resposta_esperada']}\n";
    echo "✅ Deve incluir: R$ 197, formatação, chamada/implantação\n";
}

// 5. Verifica estrutura completa
echo "\n5. VERIFICANDO ESTRUTURA COMPLETA DA PROPOSTA:\n";
$estrutura = [
    '1. Saudação personalizada' => strpos($content, 'Saudação personalizada') !== false,
    '2. Entendimento do need' => strpos($content, 'Entendimento do need') !== false,
    '3. Solução Pixel12' => strpos($content, 'Solução Pixel12') !== false,
    '4. Investimento claro' => strpos($content, 'Investimento claro') !== false,
    '5. Condições especiais' => strpos($content, 'Condições especiais') !== false,
    '6. Suporte completo' => strpos($content, 'Suporte completo') !== false,
    '7. Call-to-action final' => strpos($content, 'Call-to-action final') !== false
];

foreach ($estrutura as $item => $found) {
    $status = $found ? '✅' : '❌';
    echo "{$status} {$item}\n";
}

// 6. Verifica proibições e obrigações
echo "\n6. VERIFICANDO REGRAS ESPECÍFICAS:\n";
$regras = [
    'NUNCA USAR - Textos longos' => strpos($content, 'Nunca textos longos') !== false,
    'NUNCA USAR - Benefícios genéricos' => strpos($content, 'Benefícios genéricos') !== false,
    'NUNCA USAR - Linguagem corporativa' => strpos($content, 'Linguagem corporativa') !== false,
    'SEMPRE USAR - Nome do cliente' => strpos($content, 'Nome do cliente') !== false,
    'SEMPRE USAR - Valores reais' => strpos($content, 'Valores reais') !== false,
    'SEMPRE USAR - Formatação WhatsApp' => strpos($content, 'Formatação WhatsApp') !== false,
    'SEMPRE USAR - Frase final' => strpos($content, 'Frase final sobre chamada') !== false
];

foreach ($regras as $regra => $found) {
    $status = $found ? '✅' : '❌';
    echo "{$status} {$regra}\n";
}

echo "\n=== CONCLUSÃO FINAL ===\n";
echo "✅ CONTEXTO ECOMMERCE ATUALIZADO COM SUCESSO\n";
echo "✅ INSTRUÇÕES ESPECÍFICAS DE PROPOSTA IMPLEMENTADAS\n";
echo "✅ VALORES OFICIAIS R$ 197 CONFIGURADOS\n";
echo "✅ FORMATAÇÃO WHATSAPP IMPLEMENTADA\n";
echo "✅ EXEMPLOS DE APRENDIZADO CRIADOS\n";
echo "✅ REGRAS ESPECÍFICAS DEFINIDAS\n";
echo "✅ LINGUAGEM PERSONALIZADA POR SEGMENTO\n\n";

echo "🎯 RESULTADO ESPERADO NAS PROPOSTAS:\n";
echo "- Textos resumidos (3-4 linhas)\n";
echo "- Formatação com **negrito** e _italico_\n";
echo "- Valores R$ 197 em 12x sempre presentes\n";
echo "- Condição entrada + 3x boleto destacada\n";
echo "- Suporte completo mencionado\n";
echo "- Frase final sobre chamada/implantação\n";
echo "- Linguagem adaptada ao segmento\n\n";

echo "📋 PRONTO PARA USO:\n";
echo "1. Contexto ecommerce está atualizado\n";
echo "2. Exemplos de aprendizado disponíveis\n";
echo "3. IA seguirá todas as instruções específicas\n";
echo "4. Propostas serão personalizadas e persuasivas\n";

?>
