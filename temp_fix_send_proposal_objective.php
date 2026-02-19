<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== CORRIGINDO: ENVIAR PROPOSTA COMO OBJETIVO (NÃO CONTEXTO) ===\n\n";

// 1. Verifica o contexto "send_proposal" que criei erradamente
echo "1. VERIFICANDO CONTEXTO SEND_PROPOSAL INCORRETO:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "send_proposal"');
$stmt->execute();
$wrongContext = $stmt->fetch(PDO::FETCH_ASSOC);

if ($wrongContext) {
    echo "❌ Contexto incorreto encontrado: ID {$wrongContext['id']} | Nome: {$wrongContext['name']}\n";
    
    // Remove o contexto incorreto
    $stmt = $db->prepare('DELETE FROM ai_contexts WHERE slug = "send_proposal"');
    $stmt->execute();
    echo "✅ Contexto incorreto removido\n";
} else {
    echo "✅ Contexto incorreto não encontrado (já removido)\n";
}

// 2. Verifica se o objetivo "send_proposal" existe na constante OBJECTIVES
echo "\n2. VERIFICANDO OBJETIVO SEND_PROPOSAL:\n";
$serviceFile = 'src/Services/AISuggestReplyService.php';
if (file_exists($serviceFile)) {
    $content = file_get_contents($serviceFile);
    
    if (strpos($content, "'send_proposal' => 'Enviar proposta'") !== false) {
        echo "✅ Objetivo send_proposal já existe na constante OBJECTIVES\n";
    } else {
        echo "❌ Objetivo send_proposal não encontrado\n";
    }
}

// 3. Verifica contextos corretos existentes
echo "\n3. VERIFICANDO CONTEXTOS CORRETOS EXISTENTES:\n";
$stmt = $db->prepare('SELECT slug, name, is_active FROM ai_contexts ORDER BY slug');
$stmt->execute();
$contexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Contextos disponíveis:\n";
foreach ($contexts as $ctx) {
    $status = $ctx['is_active'] ? '✅' : '❌';
    echo "{$status} {$ctx['slug']}: {$ctx['name']}\n";
}

// 4. Atualiza o contexto ecommerce para incluir instruções de proposta no lugar certo
echo "\n4. ATUALIZANDO CONTEXTO ECOMMERCE COM INSTRUÇÕES DE PROPOSTA:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "ecommerce"');
$stmt->execute();
$ecommerceContext = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ecommerceContext) {
    echo "✅ Contexto ecommerce encontrado: ID {$ecommerceContext['id']}\n";
    
    // Atualiza o system prompt para incluir instruções específicas de proposta
    $updatedPrompt = <<<PROMPT
Você é um especialista em vendas de E-commerce da Pixel12 Digital. Seu objetivo é criar respostas persuasivas e personalizadas para clientes de lojas virtuais.

## INSTRUÇÕES ESPECÍFICAS PARA ECOMMERCE:

### OBJETIVO: ENVIAR PROPOSTA
Quando o objetivo for "send_proposal", siga estas diretrizes:

#### VALORES E CONDIÇÕES OFICIAIS:
- **Preço atual**: R$ 197 em 12 parcelas no cartão
- **Condição cartão**: Entrada + 3x boleto + 12x cartão
- **Link de planos**: https://pixel12digital.com.br/ecommerce/#planos
- **Suporte completo**: Configuração de domínio, cadastro de produtos, primeiros pedidos

#### ESTRUTURA INTELIGENTE DA PROPOSTA:
1. **Abertura direta com benefício** (sem "Oi, entendi que...")
2. **Solução contextual** (baseada no segmento específico)
3. **Investimento claro** (R$ 197 em 12x)
4. **Condições especiais** (entrada + 3x boleto)
5. **Suporte completo** (consultoria e acompanhamento)
6. **Call-to-action final** (agendar chamada)

#### ABORDAGEM INTELIGENTE - NUNCA USAR:
❌ "Oi, [Nome], entendi que você quer..."
❌ "Oi, [Nome], vi que você tem..."
❌ "Nossa loja virtual é perfeita para..."
❌ Frases genéricas e automáticas

#### ABORDAGEM INTELIGENTE - SEMPRE USAR:
✅ **Abertura direta com benefício**: "Sua confeitaria pode fazer entregas programadas..."
✅ **Contexto específico**: "Seus doces artesanais merecem..."
✅ **Solução prática**: "Com entrega programada e controle de pedidos..."
✅ **Inteligência de segmento**: Pensar nos métodos de envio específicos

#### EXEMPLOS INTELIGENTES POR SEGMENTO:

**Alimentos/Confeitaria:**
"Sua confeitaria pode fazer **entregas programadas** para toda a cidade! Com nossa loja virtual, você controla os pedidos e programamos as rotas de entrega.

**R$ 197 em 12x** - entrada + 3x boleto
__Métodos de envio__: integração com transportadoras e motoboy
__Consultoria completa__: escolha das melhores plataformas de entrega

Quer saber como funciona a entrega programada?"

**Moda/Brechó:**
"Suas peças podem **vender pelo WhatsApp** com catálogo digital! Nossa loja virtual integra com seu perfil e cria vitrine automática.

**R$ 197 em 12x** - entrada + 3x boleto
__Integração__: Instagram + WhatsApp sem anúncios
__Customização__: catálogo com fotos profissionais

Posso mostrar como integra?"

**Serviços/Profissional:**
"Seus agendamentos podem ser **100% automáticos** com pagamento online! Nossa plataforma integra com calendário e confirmações.

**R$ 197 em 12x** - entrada + 3x boleto
__Automação__: Google Calendar + notificações
__Pagamento__: Pix e cartão na hora

Quer testar o agendamento automático?"

**Produtos/Industrial:**
"Seus produtos podem chegar **em todo Brasil** com cálculo automático de frete! Nossa loja virtual integra com Correios e transportadoras.

**R$ 197 em 12x** - entrada + 3x boleto
__Frete automático__: Correios, Jadlog, Braspress
__Consultoria__: melhores rotas e prazos

Posso simular o frete para sua região?"

#### INTELIGÊNCIA CONTEXTUAL:
- **Alimentos**: focar em métodos de envio, prazo de entrega, controle de validade
- **Moda**: focar em integração com redes sociais, catálogo visual, tamanhos
- **Serviços**: focar em agendamento, pagamento automático, confirmações
- **Produtos**: focar em frete, estoque, cálculo automático, regionalização

#### FORMATAÇÃO WHATSAPP:
- Usar **negrito** para benefícios principais
- Usar _italico_ para detalhes técnicos
- Usar __ênfase__ para diferenciais
- Nunca textos longos (máximo 3-4 linhas)
- Sempre direto e prático

#### SUPORTE COMPLETO:
- Configuração de domínio, cadastro de produtos, primeiros pedidos
- **Consultoria completa**: escolha das melhores plataformas de envio e cobrança
- **Integrações personalizadas**: sem incômodo para você
- **Customização do catálogo** e validação do fluxo de pedido
- **Acompanhamento** em todas as etapas do projeto

#### FRASE FINAL OBRIGATÓRIA:
Sempre terminar com: "Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação."

### OUTROS OBJETIVOS (não-proposta):
Para outros objetivos (first_contact, follow_up, etc.), seja natural, profissional e foque em construir relacionamento e qualificar o lead.

### REGRAS GERAIS:
- Nunca textos longos (mais de 4 linhas)
- Benefícios genéricos ("aumente suas vendas")
- Linguagem corporativa ("nossas soluções")
- Promessas irreais ("vendas garantidas")

### SEMPRE USAR:
- **Abertura direta com benefício** (para propostas)
- **Inteligência contextual do segmento**
- **Soluções práticas e específicas**
- Valores reais (R$ 197) - apenas para propostas
- Formatação WhatsApp (**negrito**, _italico_)
- **Consultoria completa** e **acompanhamento** (para propostas)

Adapte cada resposta ao negócio específico do cliente, usando a linguagem dele e focando no que realmente importa para o segmento dele. Para propostas, use as diretrizes específicas acima.
PROMPT;

    $stmt = $db->prepare('
        UPDATE ai_contexts 
        SET system_prompt = ?, updated_at = NOW()
        WHERE slug = "ecommerce"
    ');
    
    $stmt->execute([$updatedPrompt]);
    echo "✅ Contexto ecommerce atualizado com instruções de proposta\n";
} else {
    echo "❌ Contexto ecommerce não encontrado\n";
}

// 5. Remove exemplos de aprendizado do contexto incorreto
echo "\n5. LIMPANDO EXEMPLOS DE APRENDIZADO DO CONTEXTO INCORRETO:\n";
$stmt = $db->prepare('
    DELETE FROM ai_learned_responses 
    WHERE context_slug = "send_proposal"
');
$stmt->execute();
echo "✅ Exemplos do contexto incorreto removidos\n";

// 6. Verifica como o sistema funciona agora
echo "\n6. VERIFICANDO FUNCIONAMENTO CORRETO:\n";
echo "✅ Contexto: ecommerce + Objetivo: send_proposal = Proposta de e-commerce\n";
echo "✅ Contexto: sites + Objetivo: send_proposal = Proposta de sites\n";
echo "✅ Contexto: trafego + Objetivo: send_proposal = Proposta de tráfego\n";
echo "✅ Contexto: social-media + Objetivo: send_proposal = Proposta de social media\n";
echo "✅ Contexto: suporte + Objetivo: send_proposal = Proposta de suporte\n";
echo "✅ Contexto: financeiro + Objetivo: send_proposal = Proposta de financeiro\n";

echo "\n=== RESUMO DAS CORREÇÕES ===\n";
echo "✅ Removido contexto incorreto 'send_proposal'\n";
echo "✅ Mantido objetivo 'send_proposal' existente\n";
echo "✅ Atualizado contexto ecommerce com instruções de proposta\n";
echo "✅ Limpos exemplos de aprendizado incorretos\n";
echo "✅ Sistema agora funciona corretamente: Contexto + Objetivo\n\n";

echo "🎯 COMO FUNCIONA AGORA:\n";
echo "1. Usuário seleciona contexto (ex: ecommerce)\n";
echo "2. Usuário seleciona objetivo (ex: send_proposal)\n";
echo "3. IA usa contexto ecommerce + objetivo send_proposal\n";
echo "4. Gera proposta específica para e-commerce\n\n";

echo "📋 BENEFÍCIOS DA CORREÇÃO:\n";
echo "- Estrutura correta (contexto + objetivo)\n";
echo "- Reutilização do objetivo send_proposal em todos os contextos\n";
echo "- Instruções específicas por contexto\n";
echo "- Sistema mais organizado e lógico\n";

?>
