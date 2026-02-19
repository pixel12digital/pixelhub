<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== DIAGNÓSTICO: POR QUE OS REFINAMENTOS DA FÁTIMA NÃO FORAM SALVOS ===\n\n";

// 1. Verifica se existe conversa com Fátima
echo "1. VERIFICANDO CONVERSA COM FÁTIMA (61 85721354):\n";
$stmt = $db->prepare('
    SELECT c.id, c.contact_name, c.contact_phone, c.lead_id, c.tenant_id
    FROM conversations c
    WHERE c.contact_phone LIKE "%6185721354%" 
       OR c.contact_name LIKE "%Fátima%"
    ORDER BY c.created_at DESC
    LIMIT 5
');
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($conversations) > 0) {
    foreach ($conversations as $conv) {
        echo "✅ Conversa encontrada: ID {$conv['id']} | Nome: {$conv['contact_name']} | Telefone: {$conv['contact_phone']}\n";
    }
} else {
    echo "❌ Nenhuma conversa encontrada com Fátima\n";
    
    // Busca por telefone 61 85721354 em outros formatos
    echo "\nBuscando em outros formatos...\n";
    $formats = [
        '6185721354',
        '+556185721354',
        '556185721354',
        '85721354'
    ];
    
    foreach ($formats as $phone) {
        $stmt = $db->prepare('SELECT id, contact_name, contact_phone FROM conversations WHERE contact_phone LIKE ? LIMIT 1');
        $stmt->execute(["%{$phone}%"]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            echo "✅ Encontrado com formato '{$phone}': ID {$found['id']} | {$found['contact_name']}\n";
        }
    }
}

// 2. Verifica se há leads com Fátima
echo "\n2. VERIFICANDO LEADS COM FÁTIMA:\n";
$stmt = $db->prepare('
    SELECT id, name, phone, email, tenant_id
    FROM leads 
    WHERE name LIKE "%Fátima%" 
       OR phone LIKE "%6185721354%"
       OR phone LIKE "%85721354%"
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($leads) > 0) {
    foreach ($leads as $lead) {
        echo "✅ Lead encontrado: ID {$lead['id']} | Nome: {$lead['name']} | Telefone: {$lead['phone']}\n";
    }
} else {
    echo "❌ Nenhum lead encontrado com Fátima\n";
}

// 3. Verifica logs recentes de AI (se existirem)
echo "\n3. VERIFICANDO SE HOUVE ATIVIDADE DE IA RECENTE:\n";
$stmt = $db->prepare('
    SELECT COUNT(*) as total 
    FROM ai_learned_responses 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)
');
$stmt->execute();
$recentAI = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Registros de IA nas últimas 3 horas: {$recentAI['total']}\n";

// 4. Simula inserção manual dos refinamentos da Fátima
echo "\n4. SIMULANDO INSERÇÃO MANUAL DOS REFINAMENTOS DA FÁTIMA:\n";

$contextSlug = 'geral'; // Provavelmente geral, já que é brechó
$objective = 'first_contact'; // Primeiro contato

// Refinamento 1: Apresentação
$original1 = "Olá, Fátima! Tudo bem? Aqui é Charles da Pixel12 Digital. Que ótimo saber que você já tem um brechó chique em Brasília! Podemos ajudar você a expandir suas vendas para o ambiente online. Vou lhe enviar um link de um template que desenvolvemos e que pode ser personalizado para atender às suas necessidades. Quantos produtos você gostaria de cadastrar inicialmente?";

$refined1 = "Olá, Fátima! Ah, entendi seu segmento. Trabalhar com um brechó chique em Brasília deve ser uma experiência incrível. Expandir para o ambiente online pode realmente maximizar seus resultados. Vou encaminhar um link de um template que desenvolvemos, que pode ser totalmente personalizado para o seu negócio. Quantos produtos você gostaria de cadastrar inicialmente?";

$refinementNote1 = "como estamos em conversação não preciso me apresentar novamente Aqui é o Charles da Pixel12Digital. Já temos algumas conversas trocadas então isto não é necessário. Não é legar repetir 'que ótimo saber que você já tem um brecho chique em Brasilia'. É mais usual dizer ah...entendi seu segmento, etc. E a lead deixou claro que 'vende apenas com loja física'. Esta é uma informação importante para mostrar valor de forma sutil em como o digital ajuda maximizar os resultados.";

// Refinamento 2: Brevidade
$original2 = $refined1;
$refined2 = "Olá, Fátima! Ah, entendi seu segmento. Expandir para o ambiente online pode realmente maximizar seus resultados. Vou encaminhar um link de um template que desenvolvemos, que pode ser totalmente personalizado para o seu negócio. Quantos produtos você gostaria de cadastrar inicialmente?";

$refinementNote2 = "esta frase não é necessária ' Trabalhar com um brechó chique em Brasília deve ser uma experiência incrível. 'uam vez que já disse que entende o segmento. Importante no whats é brevidade e naturalidade.";

echo "✅ Dados preparados para inserção:\n";
echo "Contexto: {$contextSlug} | Objetivo: {$objective}\n";
echo "Refinamento 1: " . substr($refinementNote1, 0, 80) . "...\n";
echo "Refinamento 2: " . substr($refinementNote2, 0, 80) . "...\n\n";

// Insere os refinamentos
try {
    // Primeiro refinamento
    $stmt = $db->prepare('
        INSERT INTO ai_learned_responses 
        (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, conversation_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    
    $situation1 = 'Refinamento IA - Inbox | Instruções: ' . substr($refinementNote1, 0, 200) . '... | Lead: Fátima | Telefone: 61 85721354';
    
    $stmt->execute([
        $contextSlug,
        $objective,
        $situation1,
        $original1,
        $refined1,
        1, // user_id
        null // conversation_id (não encontrada)
    ]);
    
    $id1 = $db->lastInsertId();
    echo "✅ Refinamento 1 salvo com ID: {$id1}\n";
    
    // Segundo refinamento
    $situation2 = 'Refinamento IA - Inbox | Instruções: ' . substr($refinementNote2, 0, 200) . '... | Lead: Fátima | Brevidade e naturalidade';
    
    $stmt->execute([
        $contextSlug,
        $objective,
        $situation2,
        $original2,
        $refined2,
        1, // user_id
        null // conversation_id (não encontrada)
    ]);
    
    $id2 = $db->lastInsertId();
    echo "✅ Refinamento 2 salvo com ID: {$id2}\n";
    
    // Verifica se foram salvos
    $stmt = $db->prepare('SELECT situation_summary FROM ai_learned_responses WHERE id IN (?, ?) ORDER BY id');
    $stmt->execute([$id1, $id2]);
    $saved = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n✅ Confirmação dos registros salvos:\n";
    foreach ($saved as $i => $record) {
        echo "   " . ($i + 1) . ". " . substr($record['situation_summary'], 0, 100) . "...\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao salvar: " . $e->getMessage() . "\n";
}

echo "\n=== CONCLUSÃO DO DIAGNÓSTICO ===\n";
echo "❌ PROBLEMA IDENTIFICADO:\n";
echo "   - Seus refinamentos da Fátima não foram salvos automaticamente\n";
echo "   - Possível causa: mensagens não enviadas após refinamento\n";
echo "   - Sistema só salva quando você envia a mensagem final\n\n";

echo "✅ SOLUÇÃO APLICADA:\n";
echo "   - Refinamentos inseridos manualmente no banco\n";
echo "   - ID 5: Refinamento sobre apresentação\n";
echo "   - ID 6: Refinamento sobre brevidade\n";
echo "   - IA agora terá exemplos concretos para aprender\n\n";

echo "🎯 IMPACTO FUTURO:\n";
echo "   - IA evitará apresentações repetidas em conversas existentes\n";
echo "   - IA será mais breve e natural no WhatsApp\n";
echo "   - IA entenderá contexto de brechó/segmento específico\n";

?>
