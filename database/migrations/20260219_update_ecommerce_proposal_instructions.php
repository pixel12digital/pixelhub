<?php

use PixelHub\Core\DB;
use PixelHub\Core\Migration;

class UpdateEcommerceProposalInstructions extends Migration
{
    public function up()
    {
        $db = DB::getConnection();
        
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

Podemos agendar uma chamada para detalhar?"

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

        $stmt = $db->prepare("
            UPDATE ai_contexts 
            SET system_prompt = ?, updated_at = NOW()
            WHERE slug = 'ecommerce'
        ");
        
        $stmt->execute([$newSystemPrompt]);
        
        echo "✅ Contexto ecommerce atualizado com instruções específicas de proposta\n";
        
        // Cria exemplos de aprendizado
        $examples = [
            [
                'context_slug' => 'ecommerce',
                'objective' => 'send_proposal',
                'situation_summary' => 'Proposta E-commerce - Brechó - Cliente questionou valores',
                'ai_suggestion' => 'Olá! Temos uma loja virtual por R$ 197 em 12x. Aceita?',
                'human_response' => 'Olá, Maria! Entendi que você quer *vender suas peças* online. Com a nossa loja virtual, você pode **vender pelo WhatsApp** sem precisar de estoque físico.

**Investimento**: R$ 197 em 12x no cartão
**Condição especial**: Entrada + 3x boleto
__Suporte completo__: Do domínio até as primeiras vendas

Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação.'
            ],
            [
                'context_slug' => 'ecommerce',
                'objective' => 'send_proposal',
                'situation_summary' => 'Proposta E-commerce - Moda - Cliente tem blog',
                'ai_suggestion' => 'Oi! Nossa loja virtual é ótima para moda. R$ 197 em 12x.',
                'human_response' => 'Oi, Ana! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.

**R$ 197 em 12x** - entrada + 3x boleto
Configuramos tudo: domínio, produtos, primeiros pedidos
__Tudo funcional__ do primeiro dia

Tem alguma dúvida sobre implantação?'
            ]
        ];
        
        foreach ($examples as $example) {
            $stmt = $db->prepare("
                INSERT INTO ai_learned_responses 
                (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                $example['context_slug'],
                $example['objective'],
                $example['situation_summary'],
                $example['ai_suggestion'],
                $example['human_response']
            ]);
        }
        
        echo "✅ Exemplos de aprendizado criados para ecommerce\n";
    }

    public function down()
    {
        $db = DB::getConnection();
        
        // Remove exemplos de aprendizado
        $stmt = $db->prepare("
            DELETE FROM ai_learned_responses 
            WHERE context_slug = 'ecommerce' 
            AND objective = 'send_proposal'
            AND situation_summary LIKE '%Proposta E-commerce%'
        ");
        $stmt->execute();
        
        // Restaura system prompt original (simplificado)
        $originalPrompt = "Você é um assistente da Pixel12 Digital, especialista em e-commerce. Ajude a criar respostas personalizadas para clientes de lojas virtuais.";
        
        $stmt = $db->prepare("
            UPDATE ai_contexts 
            SET system_prompt = ?, updated_at = NOW()
            WHERE slug = 'ecommerce'
        ");
        
        $stmt->execute([$originalPrompt]);
        
        echo "❌ Contexto ecommerce restaurado para versão original\n";
    }
}
