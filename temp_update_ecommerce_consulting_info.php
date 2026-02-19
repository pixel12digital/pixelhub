<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== ADICIONANDO INFORMAÇÃO DE CONSULTORIA E ACOMPANHAMENTO ===\n\n";

// 1. Busca o contexto atual
echo "1. BUSCANDO CONTEXTO ECOMMERCE ATUAL:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "ecommerce"');
$stmt->execute();
$context = $stmt->fetch(PDO::FETCH_ASSOC);

if ($context) {
    echo "✅ Contexto encontrado: ID {$context['id']}\n";
    $currentPrompt = $context['system_prompt'];
    
    // 2. Adiciona a informação de consultoria ao suporte completo
    echo "\n2. ADICIONANDO INFORMAÇÃO DE CONSULTORIA:\n";
    
    $newSupportSection = <<<SUPPORT
### SUPORTE COMPLETO:
- Configuração de domínio, cadastro de produtos, primeiros pedidos
- **Consultoria completa**: escolha das melhores plataformas de envio e cobrança
- **Integrações personalizadas**: sem incômodo para você
- **Customização do catálogo** e validação do fluxo de pedido
- **Acompanhamento** em todas as etapas do projeto

### FRASE FINAL OBRIGATÓRIA:
Sempre terminar com: "Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação."
SUPPORT;

    // Substitui a seção de suporte completo
    $updatedPrompt = str_replace(
        "### SUPORTE COMPLETO:\n- Configuração de domínio, cadastro de produtos, primeiros pedidos",
        $newSupportSection,
        $currentPrompt
    );
    
    // 3. Atualiza o contexto
    echo "3. ATUALIZANDO CONTEXTO COM INFORMAÇÃO DE CONSULTORIA:\n";
    $stmt = $db->prepare('
        UPDATE ai_contexts 
        SET system_prompt = ?, updated_at = NOW()
        WHERE slug = "ecommerce"
    ');
    
    try {
        $stmt->execute([$updatedPrompt]);
        echo "✅ Contexto atualizado com sucesso\n\n";
        
        // 4. Verifica se foi atualizado
        $stmt = $db->prepare('SELECT system_prompt FROM ai_contexts WHERE slug = "ecommerce"');
        $stmt->execute();
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updated) {
            echo "4. VERIFICAÇÃO DA ATUALIZAÇÃO:\n";
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
                'todas as etapas' => strpos($content, 'todas as etapas') !== false
            ];
            
            echo "Verificação de conteúdo:\n";
            $allChecksPassed = true;
            foreach ($checks as $check => $found) {
                $status = $found ? '✅' : '❌';
                echo "{$status} {$check}\n";
                if (!$found) $allChecksPassed = false;
            }
            
            if ($allChecksPassed) {
                echo "\n✅ TODAS AS INFORMAÇÕES DE CONSULTORIA ADICIONADAS\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao atualizar contexto: " . $e->getMessage() . "\n";
    }
    
    // 5. Atualiza exemplos de aprendizado
    echo "\n5. ATUALIZANDO EXEMPLOS DE APRENDIZADO:\n";
    
    $updatedExamples = [
        [
            'situation' => 'Proposta E-commerce - Brechó - Cliente questionou valores',
            'ai_suggestion' => 'Olá! Temos uma loja virtual por R$ 197 em 12x. Aceita?',
            'human_response' => 'Olá, Maria! Entendi que você quer *vender suas peças* online. Com a nossa loja virtual, você pode **vender pelo WhatsApp** sem precisar de estoque físico.

**Investimento**: R$ 197 em 12x no cartão
**Condição especial**: Entrada + 3x boleto
__Consultoria completa__: escolha das melhores plataformas de envio e cobrança
__Acompanhamento__ em todas as etapas do projeto

Podemos agendar uma chamada ou se tiver qualquer dúvida é só me perguntar por aqui sobre implantação.'
        ],
        [
            'situation' => 'Proposta E-commerce - Moda - Cliente tem blog',
            'ai_suggestion' => 'Oi! Nossa loja virtual é ótima para moda. R$ 197 em 12x.',
            'human_response' => 'Oi, Ana! Vi que você tem _um blog de moda_. Nossa loja virtual é **perfeita para mostrar seus looks** com fotos profissionais.

**R$ 197 em 12x** - entrada + 3x boleto
**Integrações personalizadas**: sem incômodo para você
__Customização do catálogo__ e validação do fluxo de pedido
__Acompanhamento__ em todas as etapas

Tem alguma dúvida sobre implantação?'
        ]
    ];
    
    // Remove exemplos antigos
    $stmt = $db->prepare('
        DELETE FROM ai_learned_responses 
        WHERE context_slug = "ecommerce" 
        AND objective = "send_proposal"
        AND situation_summary LIKE "%Proposta E-commerce%"
    ');
    $stmt->execute();
    
    // Insere exemplos atualizados
    foreach ($updatedExamples as $i => $example) {
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
            echo "✅ Exemplo atualizado " . ($i + 1) . " criado com ID: {$id}\n";
        } catch (Exception $e) {
            echo "❌ Erro ao criar exemplo " . ($i + 1) . ": " . $e->getMessage() . "\n";
        }
    }
    
} else {
    echo "❌ Contexto ecommerce não encontrado\n";
}

echo "\n=== RESUMO DAS ATUALIZAÇÕES ===\n";
echo "✅ Informação de consultoria adicionada ao suporte completo\n";
echo "✅ Destaque para escolha de plataformas de envio e cobrança\n";
echo "✅ Menção a integrações personalizadas sem incômodo\n";
echo "✅ Customização do catálogo e validação de fluxo\n";
echo "✅ Acompanhamento em todas as etapas\n";
echo "✅ Exemplos de aprendizado atualizados\n";
echo "✅ Propostas agora frisam consultoria completa\n\n";

echo "🎯 RESULTADO ESPERADO NAS PROPOSTAS:\n";
echo "- Menção clara da **consultoria completa**\n";
echo "- Destaque para **plataformas de envio e cobrança**\n";
echo "- **Integrações personalizadas** sem incômodo\n";
echo "- **Customização do catálogo** e validação\n";
echo "- **Acompanhamento** em todas as etapas\n";
echo "- Tudo de forma **resumida** e persuasiva\n\n";

echo "📋 BENEFÍCIOS DAS ATUALIZAÇÕES:\n";
echo "- Transmite mais confiança e segurança\n";
echo "- Mostra valor além da plataforma técnica\n";
echo "- Diferencia da concorrência\n";
echo "- Aborda preocupações do cliente\n";
echo "- Fortalece proposta comercial\n";

?>
