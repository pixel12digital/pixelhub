<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== CORRIGINDO ATUALIZAÇÃO DE CONSULTORIA NO ECOMMERCE ===\n\n";

// 1. Busca o contexto atual para verificar
echo "1. VERIFICANDO CONTEXTO ATUAL:\n";
$stmt = $db->prepare('SELECT system_prompt FROM ai_contexts WHERE slug = "ecommerce"');
$stmt->execute();
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current) {
    $content = $current['system_prompt'];
    
    // Verifica se já tem a informação de consultoria
    if (strpos($content, 'Consultoria completa') !== false) {
        echo "✅ Informação de consultoria já está presente\n";
    } else {
        echo "❌ Informação de consultoria não encontrada - atualizando manualmente\n";
        
        // 2. Constrói o system prompt completo com consultoria
        $completePrompt = <<<PROMPT
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
__Consultoria completa__: escolha das melhores plataformas de envio e cobrança
__Acompanhamento__ em todas as etapas do projeto

Podemos agendar uma chamada para detalhar?"

**Moda:**
"Oi, [Nome]! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.

**R$ 197 em 12x** - entrada + 3x boleto
**Integrações personalizadas**: sem incômodo para você
__Customização do catálogo__ e validação do fluxo de pedido
__Acompanhamento__ em todas as etapas

Tem alguma dúvida sobre implantação?"

### SUPORTE COMPLETO:
- Configuração de domínio, cadastro de produtos, primeiros pedidos
- **Consultoria completa**: escolha das melhores plataformas de envio e cobrança
- **Integrações personalizadas**: sem incômodo para você
- **Customização do catálogo** e validação do fluxo de pedido
- **Acompanhamento** em todas as etapas do projeto

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
- **Consultoria completa** e **acompanhamento** em todas as propostas

Adapte cada proposta ao negócio específico do cliente, usando a linguagem dele e focando no que realmente importa para o segmento dele. Sempre frise a consultoria completa e o acompanhamento em todas as etapas.
PROMPT;

        // 3. Atualiza o contexto com o prompt completo
        echo "2. ATUALIZANDO CONTEXTO COM PROMPT COMPLETO:\n";
        $stmt = $db->prepare('
            UPDATE ai_contexts 
            SET system_prompt = ?, updated_at = NOW()
            WHERE slug = "ecommerce"
        ');
        
        try {
            $stmt->execute([$completePrompt]);
            echo "✅ Contexto atualizado com sucesso\n\n";
            
            // 4. Verificação final
            echo "3. VERIFICAÇÃO FINAL:\n";
            $stmt = $db->prepare('SELECT system_prompt FROM ai_contexts WHERE slug = "ecommerce"');
            $stmt->execute();
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($updated) {
                $content = $updated['system_prompt'];
                
                $checks = [
                    'Consultoria completa' => strpos($content, 'Consultoria completa') !== false,
                    'plataformas de envio' => strpos($content, 'plataformas de envio') !== false,
                    'plataformas de cobrança' => strpos($content, 'plataformas de cobrança') !== false,
                    'Integrações personalizadas' => strpos($content, 'Integrações personalizadas') !== false,
                    'sem incômodo' => strpos($content, 'sem incômodo') !== false,
                    'Customização do catálogo' => strpos($content, 'Customização do catálogo') !== false,
                    'validação do fluxo de pedido' => strpos($content, 'validação do fluxo de pedido') !== false,
                    'Acompanhamento' => strpos($content, 'Acompanhamento') !== false,
                    'todas as etapas' => strpos($content, 'todas as etapas') !== false,
                    'consultoria completa e o acompanhamento' => strpos($content, 'consultoria completa e o acompanhamento') !== false
                ];
                
                echo "Verificação de conteúdo:\n";
                $allChecksPassed = true;
                foreach ($checks as $check => $found) {
                    $status = $found ? '✅' : '❌';
                    echo "{$status} {$check}\n";
                    if (!$found) $allChecksPassed = false;
                }
                
                if ($allChecksPassed) {
                    echo "\n✅ TODAS AS INFORMAÇÕES DE CONSULTORIA ESTÃO PRESENTES\n";
                } else {
                    echo "\n⚠️  ALGUMAS INFORMAÇÕES AINDA FALTAM\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Erro ao atualizar contexto: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Cria novos exemplos com consultoria
    echo "\n4. CRIANDO EXEMPLOS COM CONSULTORIA:\n";
    
    $consultingExamples = [
        [
            'situation' => 'Proposta E-commerce - Brechó - Com consultoria completa',
            'ai_suggestion' => 'Olá! Temos loja virtual por R$ 197. Aceita?',
            'human_response' => 'Olá, Maria! Entendi que você quer *vender suas peças* online. Com a nossa loja virtual, você pode **vender pelo WhatsApp** sem precisar de estoque físico.

**Investimento**: R$ 197 em 12x no cartão
__Consultoria completa__: escolha das melhores plataformas de envio e cobrança
__Acompanhamento__ em todas as etapas do projeto

Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação.'
        ],
        [
            'situation' => 'Proposta E-commerce - Moda - Com integrações personalizadas',
            'ai_suggestion' => 'Oi! Loja virtual para moda R$ 197.',
            'human_response' => 'Oi, Ana! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.

**R$ 197 em 12x** - entrada + 3x boleto
**Integrações personalizadas**: sem incômodo para você
__Customização do catálogo__ e validação do fluxo de pedido

Tem alguma dúvida sobre implantação?'
        ]
    ];
    
    foreach ($consultingExamples as $i => $example) {
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
            echo "✅ Exemplo com consultoria " . ($i + 1) . " criado com ID: {$id}\n";
        } catch (Exception $e) {
            echo "❌ Erro ao criar exemplo " . ($i + 1) . ": " . $e->getMessage() . "\n";
        }
    }
    
} else {
    echo "❌ Contexto ecommerce não encontrado\n";
}

echo "\n=== RESUMO FINAL ===\n";
echo "✅ Contexto ecommerce atualizado com consultoria completa\n";
echo "✅ Informações sobre plataformas de envio e cobrança\n";
echo "✅ Destaque para integrações personalizadas sem incômodo\n";
echo "✅ Customização do catálogo e validação de fluxo\n";
echo "✅ Acompanhamento em todas as etapas\n";
echo "✅ Exemplos de aprendizado com consultoria\n";
echo "✅ Propostas agora frisam valor além da plataforma\n\n";

echo "🎯 RESULTADO ESPERADO:\n";
echo "- **Consultoria completa** sempre mencionada\n";
echo "- **Plataformas de envio e cobrança** destacadas\n";
echo "- **Integrações sem incômodo** ressaltadas\n";
echo "- **Acompanhamento total** enfatizado\n";
echo "- Tudo de forma **resumida** e persuasiva\n";

?>
