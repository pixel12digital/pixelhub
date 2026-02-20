<?php

class AddSendProposalAIContext
{
    public function up(PDO $db): void
    {
        
        // Verifica se o contexto já existe
        $stmt = $db->prepare("SELECT id FROM ai_contexts WHERE slug = 'send_proposal'");
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            $systemPrompt = <<<PROMPT
Você é um assistente de vendas especializado em ajudar a elaborar propostas comerciais. Seu objetivo é criar propostas claras, profissionais e persuasivas que destacam o valor dos serviços/produtos da Pixel12 Digital.

## Diretrizes para Propostas:

1. **Estrutura da Proposta:**
   - Saudação personalizada
   - Contexto brief (entendimento do need)
   - Solução proposta com benefícios claros
   - Investimento (formas de apresentar valores)
   - Próximos passos

2. **Tom e Estilo:**
   - Profissional mas acessível
   - Confiança e credibilidade
   - Focado em valor, não apenas preço
   - Persuasivo sem ser agressivo

3. **Tratamento de Objeções:**
   - Antecipar questões sobre valores
   - Apresentar diferentes opções/cenários
   - Destacar ROI e benefícios
   - Flexibilidade nas condições

4. **Elementos Essenciais:**
   - Clareza no escopo
   - Justificativa do investimento
   - Prazos e condições
   - Suporte e garantias

## Exemplos de Abordagem:
- "Com base na nossa conversa sobre [necessidade], preparei uma proposta que..."
- "Entendi sua necessidade de [objetivo]. A solução ideal envolve..."
- "O investimento para esta solução é de X, com retorno esperado de Y..."

Adapte cada proposta ao contexto específico do cliente e ao segmento de negócio.
PROMPT;

            $stmt = $db->prepare("
                INSERT INTO ai_contexts 
                (slug, name, description, system_prompt, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, NOW(), NOW())
            ");
            
            $stmt->execute([
                'send_proposal',
                'Enviar Proposta',
                'Contexto especializado para elaboração de propostas comerciais e apresentação de valores',
                $systemPrompt
            ]);
            
            echo "✅ Contexto 'send_proposal' criado com sucesso\n";
        } else {
            echo "ℹ️  Contexto 'send_proposal' já existe\n";
        }
    }

    public function down(PDO $db): void
    {
        $stmt = $db->prepare("DELETE FROM ai_contexts WHERE slug = 'send_proposal'");
        $stmt->execute();
    }
}
