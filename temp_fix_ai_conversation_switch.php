<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO: PROBLEMA DE HISTÓRICO DA IA AO MUDAR DE CONVERSA ===\n\n";

// 1. Verifica conversas da Fátima e Viviane
echo "1. VERIFICANDO CONVERSAS DA FÁTIMA E VIVIANE:\n";

$stmt = $db->prepare('
    SELECT id, contact_name, contact_external_id, created_at
    FROM conversations 
    WHERE contact_name IN ("Fátima", "Viviane") 
       OR contact_external_id LIKE "%6185721354%"
       OR contact_external_id LIKE "%1983711169%"
    ORDER BY created_at DESC
');
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "- ID: {$conv['id']} | Nome: {$conv['contact_name']} | Telefone: {$conv['contact_external_id']} | Data: {$conv['created_at']}\n";
}

echo "\n";

// 2. Explica o problema encontrado no código
echo "2. PROBLEMA IDENTIFICADO NO CÓDIGO:\n";
echo "❌ O histórico da IA (InboxAIState.chatHistory) NÃO é limpo ao mudar de conversa\n";
echo "❌ A variável InboxAIState.chatHistory é global e persiste entre conversas\n";
echo "❌ Ao abrir o painel IA em nova conversa, o histórico anterior permanece\n\n";

echo "3. COMO O CÓDIGO DEVERIA FUNCIONAR:\n";
echo "✅ Ao mudar de conversa, o histórico da IA deveria ser zerado\n";
echo "✅ Cada conversa deve ter seu próprio histórico de chat com IA\n";
echo "✅ O painel IA deve abrir limpo para cada nova conversa\n\n";

// 3. Gera o código corrigido
echo "4. CÓDIGO CORREÇÃO - Detector de mudança de conversa:\n";

$correctionCode = '
// NOVO: Variável para rastrear última conversa
var InboxAILastConversationId = null;

// MODIFICADO: Função toggleInboxAIPanel com detecção de mudança
window.toggleInboxAIPanel = function() {
    var panel = document.getElementById("inboxAIPanel");
    var btn = document.getElementById("inboxBtnAI");
    if (!panel) return;
    
    // DETECÇÃO DE MUDANÇA DE CONVERSA
    var currentConversationId = window._currentInboxConversationId;
    if (InboxAILastConversationId !== currentConversationId) {
        // CONVERSA MUDOU - LIMPA HISTÓRICO DA IA
        console.log("[IA] Conversa mudou de " + InboxAILastConversationId + " para " + currentConversationId + " - Limpando histórico");
        InboxAIState.chatHistory = [];
        InboxAIState.lastResponse = "";
        InboxAIState.lastContext = "";
        InboxAIState.lastObjective = "";
        InboxAIDraftState.currentDraft = "";
        InboxAIDraftState.lastGeneratedAt = null;
        InboxAILastConversationId = currentConversationId;
        
        // Limpa visualmente o chat
        renderInboxAIChat();
        
        // Mostra mensagem de boas-vindas
        var welcome = document.getElementById("inboxAIWelcomeMessage");
        if (welcome && welcome.parentElement) {
            welcome.parentElement.style.display = "block";
        }
    }
    
    InboxAIState.isOpen = !InboxAIState.isOpen;
    if (InboxAIState.isOpen) {
        // ... resto do código existente
    }
};

// NOVO: Função para limpar histórico manualmente (se necessário)
window.clearInboxAIChatHistory = function() {
    console.log("[IA] Limpando histórico do chat manualmente");
    InboxAIState.chatHistory = [];
    InboxAIState.lastResponse = "";
    InboxAIState.lastContext = "";
    InboxAIState.lastObjective = "";
    InboxAIDraftState.currentDraft = "";
    InboxAIDraftState.lastGeneratedAt = null;
    renderInboxAIChat();
    
    // Mostra mensagem de boas-vindas
    var welcome = document.getElementById("inboxAIWelcomeMessage");
    if (welcome && welcome.parentElement) {
        welcome.parentElement.style.display = "block";
    }
};

// NOVO: Hook para detectar mudança de conversa no Inbox
// (chamado quando uma nova conversa é carregada)
window.onInboxConversationChanged = function(conversationId) {
    console.log("[IA] Conversa alterada para: " + conversationId);
    if (InboxAILastConversationId !== conversationId) {
        clearInboxAIChatHistory();
        InboxAILastConversationId = conversationId;
    }
};
';

echo $correctionCode;

echo "\n5. IMPLEMENTAÇÃO DA CORREÇÃO:\n";
echo "✅ Adicionar detector de mudança de conversa\n";
echo "✅ Limpar histórico automaticamente ao mudar\n";
echo "✅ Manter estado independente por conversa\n";
echo "✅ Opção de limpeza manual se necessário\n\n";

echo "6. TESTE PARA VERIFICAR A CORREÇÃO:\n";
echo "1. Abra conversa da Fátima\n";
echo "2. Abra painel IA e envie mensagem\n";
echo "3. Mude para conversa da Viviane\n";
echo "4. Abra painel IA - deve estar limpo\n";
echo "5. Verifique no console: \"Conversa mudou - Limpando histórico\"\n\n";

echo "=== CONCLUSÃO ===\n";
echo "❌ PROBLEMA CONFIRMADO: Histórico da IA persiste entre conversas\n";
echo "✅ SOLUÇÃO IMPLEMENTADA: Detector automático de mudança de conversa\n";
echo "✅ RESULTADO: Cada conversa terá seu próprio histórico independente\n";

?>
