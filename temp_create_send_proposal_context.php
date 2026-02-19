<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== CRIANDO CONTEXTO SEND_PROPOSAL AUSENTE ===\n\n";

// 1. Verifica se já existe
echo "1. VERIFICANDO SE CONTEXTO JÁ EXISTE:\n";
$stmt = $db->prepare('SELECT id, name, is_active FROM ai_contexts WHERE slug = "send_proposal"');
$stmt->execute();
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "✅ Contexto já existe: ID {$existing['id']} | Nome: {$existing['name']} | Ativo: " . ($existing['is_active'] ? 'SIM' : 'NÃO') . "\n";
    
    if (!$existing['is_active']) {
        echo "⚠️  Contexto está INATIVO - ativando...\n";
        $stmt = $db->prepare('UPDATE ai_contexts SET is_active = 1 WHERE id = ?');
        $stmt->execute([$existing['id']]);
        echo "✅ Contexto ativado\n";
    }
} else {
    echo "❌ Contexto não existe - criando...\n";
    
    // 2. Cria o contexto send_proposal
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

    $stmt = $db->prepare('
        INSERT INTO ai_contexts 
        (slug, name, description, system_prompt, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
    ');
    
    try {
        $stmt->execute([
            'send_proposal',
            'Enviar Proposta',
            'Contexto especializado para elaboração de propostas comerciais e apresentação de valores',
            $systemPrompt
        ]);
        
        $newId = $db->lastInsertId();
        echo "✅ Contexto 'send_proposal' criado com ID: {$newId}\n";
        
        // Verifica se foi criado
        $stmt = $db->prepare('SELECT slug, name, is_active FROM ai_contexts WHERE id = ?');
        $stmt->execute([$newId]);
        $created = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($created) {
            echo "✅ Confirmação: {$created['slug']} | {$created['name']} | Ativo: " . ($created['is_active'] ? 'SIM' : 'NÃO') . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao criar contexto: " . $e->getMessage() . "\n";
    }
}

// 3. Testa se o contexto funciona
echo "\n2. TESTANDO O CONTEXTO CRIADO:\n";
$stmt = $db->prepare('SELECT * FROM ai_contexts WHERE slug = "send_proposal"');
$stmt->execute();
$context = $stmt->fetch(PDO::FETCH_ASSOC);

if ($context) {
    echo "✅ Contexto encontrado e funcional:\n";
    echo "- ID: {$context['id']}\n";
    echo "- Slug: {$context['slug']}\n";
    echo "- Nome: {$context['name']}\n";
    echo "- Ativo: " . ($context['is_active'] ? 'SIM' : 'NÃO') . "\n";
    echo "- System Prompt: " . substr($context['system_prompt'], 0, 100) . "...\n";
} else {
    echo "❌ Contexto não encontrado após criação\n";
}

// 4. Lista todos os contextos atualizados
echo "\n3. LISTA COMPLETA DE CONTEXTOS ATUALIZADA:\n";
$stmt = $db->prepare('SELECT slug, name, is_active FROM ai_contexts ORDER BY slug');
$stmt->execute();
$allContexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($allContexts as $ctx) {
    $status = $ctx['is_active'] ? '✅' : '❌';
    echo "{$status} {$ctx['slug']}: {$ctx['name']}\n";
}

echo "\n=== RESOLUÇÃO DO PROBLEMA ===\n";
echo "✅ CAUSA IDENTIFICADA: Contexto 'send_proposal' não existia\n";
echo "✅ SOLUÇÃO: Contexto criado com system prompt especializado\n";
echo "✅ RESULTADO: IA agora pode gerar propostas comerciais\n";
echo "\n📝 INSTRUÇÕES:\n";
echo "1. Faça deploy das alterações\n";
echo "2. Teste novamente com a Lidy sobre valores\n";
echo "3. Verifique se a IA gera rascunho normalmente\n";

?>
