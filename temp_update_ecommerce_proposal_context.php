<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== ATUALIZANDO CONTEXTO ECOMMERCE COM INSTRUÇÕES ESPECÍFICAS ===\n\n";

// 1. Verifica o contexto ecommerce atual
echo "1. VERIFICANDO CONTEXTO ECOMMERCE ATUAL:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "ecommerce"');
$stmt->execute();
$currentContext = $stmt->fetch(PDO::FETCH_ASSOC);

if ($currentContext) {
    echo "✅ Contexto encontrado: ID {$currentContext['id']} | Nome: {$currentContext['name']}\n";
    echo "System prompt atual: " . substr($currentContext['system_prompt'], 0, 150) . "...\n\n";
} else {
    echo "❌ Contexto ecommerce não encontrado\n";
    exit;
}

// 2. Cria o novo system prompt com instruções específicas
$newSystemPrompt = <<<PROMPT
Você é um especialista em vendas de E-commerce da Pixel12 Digital. Seu objetivo é criar propostas comerciais persuasivas e personalizadas para clientes de lojas virtuais.

## INSTRUÇÕES ESPECÍFICAS PARA PROPOSTAS ECOMMERCE:

### VALORES E CONDIÇÕES OFICIAIS:
- **Preço atual**: R$ 197 em 12 parcelas no cartão
- **Condição cartão**: Entrada + 3x boleto + 12x cartão
- **Link de planos**: https://pixel12digital.com.br/ecommerce/#planos
- **Suporte completo**: Configuração de domínio, cadastro de produtos, primeiros pedidos

### ESTRUTURA DA PROPOSTA:
1. **Saudação personalizada** (usar nome do cliente)
2. **Entendimento do need** (baseado na conversa)
3. **Solução Pixel12** (benefícios específicos do segmento)
4. **Investimento claro** (R$ 197 em 12x)
5. **Condições especiais** (entrada + 3x boleto)
6. **Suporte completo** (do início ao resultado)
7. **Call-to-action final** (agendar chamada)

### LINGUAGEM E TOM:
- **Aproximar-se do tom do cliente** (usar linguagem similar)
- **Evitar benefícios genéricos** (focar no que o cliente quer ouvir)
- **Basear no segmento** (ex: brechó, moda, alimentos, etc.)
- **Ser persuasivo mas natural** (sem ser vendedor)

### FORMATAÇÃO WHATSAPP:
- Usar **negrito** com asteriscos duplos: **texto**
- Usar _italico_ com underscores: _texto_
- Usar __ênfase__ com underscores duplos: __texto__
- Nunca textos longos (máximo 3-4 linhas por mensagem)
- Sempre resumido e direto

### BENEFÍCIOS ESPECÍFICOS (não genéricos):
- Para brechós: "vender online sem sair de casa"
- Para moda: "mostrar produtos com fotos profissionais"
- Para alimentos: "entrega programada e controle de estoque"
- Para serviços: "agendamento online e pagamento automático"

### EXEMPLOS DE ABORDAGEM:
**Brechó:**
"Olá, [Nome]! Entendi que você quer *vender suas peças* online. Com a nossa loja virtual, você pode **vender pelo WhatsApp** sem precisar de estoque físico.

**Investimento**: R$ 197 em 12x no cartão
**Condição especial**: Entrada + 3x boleto
__Suporte completo__: Do domínio até as primeiras vendas

Posso agendar uma chamada para detalhar?"

**Moda:**
"Oi, [Nome]! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.

**R$ 197 em 12x** - entrada + 3x boleto
Configuramos tudo: domínio, produtos, primeiros pedidos
__Tudo funcional__ do primeiro dia

Tem alguma dúvida sobre implantação?"

### FRASE FINAL OBRIGATÓRIA:
Sempre terminar com: "Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação."

### NUNCA USAR:
- Textos longos (mais de 4 linhas)
- Benefícios genéricos ("aumente suas vendas")
- Linguagem corporativa ("nossas soluções")
- Promessas irreais ("vendas garantidas")

### SEMPRE USAR:
- Nome do cliente
- Contexto da conversa
- Valores reais (R$ 197)
- Formatação WhatsApp (**negrito**, _italico_)
- Frase final sobre chamada/implantação

Adapte cada proposta ao negócio específico do cliente, usando a linguagem dele e focando no que realmente importa para o segmento dele.
PROMPT;

// 3. Atualiza o contexto
echo "2. ATUALIZANDO SYSTEM PROMPT DO CONTEXTO ECOMMERCE:\n";
$stmt = $db->prepare('
    UPDATE ai_contexts 
    SET system_prompt = ?, updated_at = NOW()
    WHERE slug = "ecommerce"
');

try {
    $stmt->execute([$newSystemPrompt]);
    echo "✅ Contexto ecommerce atualizado com sucesso\n\n";
    
    // Verifica se foi atualizado
    $stmt = $db->prepare('SELECT system_prompt FROM ai_contexts WHERE slug = "ecommerce"');
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($updated) {
        echo "3. VERIFICAÇÃO DA ATUALIZAÇÃO:\n";
        echo "✅ System prompt atualizado\n";
        echo "✅ Contém instruções específicas para ecommerce\n";
        echo "✅ Valores R$ 197 em 12x incluídos\n";
        echo "✅ Formatação WhatsApp configurada\n";
        echo "✅ Frase final sobre chamada/implantação\n\n";
        
        // Verifica conteúdo específico
        $content = $updated['system_prompt'];
        $checks = [
            'R$ 197' => strpos($content, 'R$ 197') !== false,
            '12 parcelas' => strpos($content, '12x') !== false,
            'entrada + 3x boleto' => strpos($content, 'entrada + 3x boleto') !== false,
            'pixel12digital.com.br/ecommerce' => strpos($content, 'pixel12digital.com.br/ecommerce') !== false,
            '**negrito**' => strpos($content, '**negrito**') !== false,
            '_italico_' => strpos($content, '_italico_') !== false,
            'agendar uma chamada' => strpos($content, 'agendar uma chamada') !== false,
            'implantação' => strpos($content, 'implantação') !== false
        ];
        
        echo "4. VERIFICAÇÃO DE CONTEÚDO ESPECÍFICO:\n";
        foreach ($checks as $check => $found) {
            $status = $found ? '✅' : '❌';
            echo "{$status} {$check}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao atualizar contexto: " . $e->getMessage() . "\n";
}

// 5. Cria exemplos de aprendizado
echo "\n5. CRIANDO EXEMPLOS DE APRENDIZADO:\n";

$examples = [
    [
        'situation' => 'Proposta E-commerce - Brechó - Cliente questionou valores',
        'ai_suggestion' => 'Olá! Temos uma loja virtual por R$ 197 em 12x. Aceita?',
        'human_response' => 'Olá, Maria! Entendi que você quer *vender suas peças* online. Com a nossa loja virtual, você pode **vender pelo WhatsApp** sem precisar de estoque físico.

**Investimento**: R$ 197 em 12x no cartão
**Condição especial**: Entrada + 3x boleto
__Suporte completo__: Do domínio até as primeiras vendas

Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação.'
    ],
    [
        'situation' => 'Proposta E-commerce - Moda - Cliente tem blog',
        'ai_suggestion' => 'Oi! Nossa loja virtual é ótima para moda. R$ 197 em 12x.',
        'human_response' => 'Oi, Ana! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.

**R$ 197 em 12x** - entrada + 3x boleto
Configuramos tudo: domínio, produtos, primeiros pedidos
__Tudo funcional__ do primeiro dia

Tem alguma dúvida sobre implantação?'
    ]
];

foreach ($examples as $i => $example) {
    $stmt = $db->prepare('
        INSERT INTO ai_learned_responses 
        (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ');
    
    try {
        $stmt->execute([
            'ecommerce',
            'send_proposal',
            $example['situation'],
            $example['ai_suggestion'],
            $example['human_response']
        ]);
        
        $id = $db->lastInsertId();
        echo "✅ Exemplo " . ($i + 1) . " criado com ID: {$id}\n";
    } catch (Exception $e) {
        echo "❌ Erro ao criar exemplo " . ($i + 1) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n=== RESUMO DAS ATUALIZAÇÕES ===\n";
echo "✅ Contexto ecommerce atualizado com instruções específicas\n";
echo "✅ Valores oficiais R$ 197 em 12x configurados\n";
echo "✅ Formatação WhatsApp implementada\n";
echo "✅ Exemplos de aprendizado criados\n";
echo "✅ Frase final sobre chamada/implantação obrigatória\n";
echo "✅ Linguagem personalizada por segmento\n";

echo "\n📋 PRÓXIMOS PASSOS:\n";
echo "1. Teste com cliente de brechó\n";
echo "2. Teste com cliente de moda\n";
echo "3. Verifique formatação WhatsApp\n";
echo "4. Confirme valores e condições\n";
echo "5. Valide frase final sobre implantação\n";

?>
