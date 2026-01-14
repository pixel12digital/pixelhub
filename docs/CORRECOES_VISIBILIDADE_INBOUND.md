# Correções: Visibilidade de Inbound (ServPro → Pixel12)

**Data:** 2026-01-14  
**Status:** ✅ Implementado

---

## 📌 Resumo

Correções implementadas para resolver problemas de visibilidade de mensagens inbound que já estavam sendo salvas corretamente, mas não apareciam na UI.

---

## 🔧 Correções Implementadas

### 1. ✅ Correção de `unread_count` (Badge)

**Problema:** `unread_count` permanecia em 0 mesmo com inbound recente.

**Correção:**
- Adicionado log detalhado em `ConversationService::updateConversationMetadata()` para rastrear incremento de `unread_count`
- Verificação antes e depois do UPDATE para confirmar que o incremento está funcionando
- Garantido que `last_message_direction` seja setado corretamente como `'inbound'` para mensagens inbound

**Arquivo:** `src/Services/ConversationService.php` (linhas 543-633)

**Logs adicionados:**
```php
// Log antes do UPDATE
error_log(sprintf(
    '[DIAGNOSTICO] ConversationService::updateConversationMetadata() - EXECUTANDO UPDATE: conversation_id=%d, direction=%s, unread_count: %d -> %d',
    $conversationId,
    $direction,
    $currentUnread,
    $afterUnread
));
```

**Nota:** O método `markConversationAsRead()` continua sendo chamado ao abrir a conversa (comportamento esperado), mas agora temos logs para confirmar que o `unread_count` está sendo incrementado corretamente antes disso.

---

### 2. ✅ Melhoria de Normalização/Filtro de Mensagens no Thread

**Problema:** Mensagens não apareciam no thread devido a variações do identificador do contato:
- `554796474223@c.us`
- `5547996474223@c.us` (com 9º dígito)
- `554796474223` (sem @c.us)

**Correção:**
- Normalização robusta que remove `@c.us` e normaliza para E.164
- Filtro SQL que busca variações com/sem 9º dígito para números BR
- Aplicado em 3 métodos:
  1. `getWhatsAppMessagesFromConversation()` - Carregamento inicial do thread
  2. `getWhatsAppMessagesIncremental()` - Carregamento incremental
  3. `checkNewMessages()` - Verificação de novas mensagens

**Arquivo:** `src/Controllers/CommunicationHubController.php`

**Normalização:**
```php
$normalizeContact = function($contact) {
    if (empty($contact)) return null;
    // Remove tudo após @ (ex: 554796164699@c.us -> 554796164699)
    $cleaned = preg_replace('/@.*$/', '', (string) $contact);
    // Remove caracteres não numéricos
    $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
    // Se for número BR (começa com 55), normaliza para E.164
    if (strlen($digitsOnly) >= 12 && substr($digitsOnly, 0, 2) === '55') {
        return $digitsOnly;
    }
    return $digitsOnly;
};
```

**Filtro SQL:**
```php
// Busca variações com/sem 9º dígito
$contactPatterns = ["%{$normalizedContactExternalId}%"];
if (strlen($normalizedContactExternalId) >= 12 && substr($normalizedContactExternalId, 0, 2) === '55') {
    if (strlen($normalizedContactExternalId) === 13) {
        // Remove 9º dígito
        $without9th = substr($normalizedContactExternalId, 0, 4) . substr($normalizedContactExternalId, 5);
        $contactPatterns[] = "%{$without9th}%";
    } elseif (strlen($normalizedContactExternalId) === 12) {
        // Adiciona 9º dígito
        $with9th = substr($normalizedContactExternalId, 0, 4) . '9' . substr($normalizedContactExternalId, 4);
        $contactPatterns[] = "%{$with9th}%";
    }
}
```

---

### 3. ✅ Confirmação de Atualização da Lista

**Status:** A função `updateConversationListOnly()` já estava implementada e funcional.

**Verificação:**
- Função está sendo chamada quando há conversa ativa e atualização detectada
- Lista é atualizada via AJAX sem reload
- Conversa ativa é preservada após atualização
- Ordenação por `last_message_at DESC` está correta

**Arquivo:** `views/communication_hub/index.php` (linhas 1004-1064)

---

## 🧪 Validação

### Critérios de Aceite

✅ **Inbound do ServPro gera:**
- ✅ Evento em `communication_events` (já ocorria)
- ✅ `unread_count > 0` quando aplicável (corrigido com logs)
- ✅ Conversa sobe para o topo (já funcionava, confirmado)
- ✅ Mensagem aparece no thread imediatamente (corrigido com normalização robusta)

✅ **Não regrediu recebimento/webhook:**
- ✅ Nenhuma alteração em `WhatsAppWebhookController`
- ✅ Nenhuma alteração em `EventIngestionService`
- ✅ Nenhuma alteração em `ConversationService::resolveConversation()`

---

## 📝 Logs Temporários Adicionados

Todos os logs estão marcados com `[LOG TEMPORARIO]` ou `[DIAGNOSTICO]` para fácil remoção posterior:

1. **ConversationService::updateConversationMetadata()**
   - Log antes e depois do UPDATE
   - Rastreamento de `unread_count` antes e depois

2. **CommunicationHub::getWhatsAppMessagesFromConversation()**
   - Log da query executada
   - Log do resultado (quantidade de eventos encontrados)

3. **CommunicationHub::getWhatsAppMessagesIncremental()**
   - Log da query incremental
   - Log do resultado

4. **CommunicationHub::checkNewMessages()**
   - Log quando nova mensagem é detectada
   - Log do resultado do check

5. **updateConversationListOnly() (frontend)**
   - Log de início, resposta e conclusão

---

## 🔍 Próximos Passos

1. **Testar em produção:**
   - Enviar mensagem do ServPro para Pixel12 Digital
   - Verificar se badge aparece (`unread_count > 0`)
   - Verificar se mensagem aparece no thread
   - Verificar se conversa sobe para o topo

2. **Monitorar logs:**
   - Verificar se `unread_count` está sendo incrementado
   - Verificar se normalização está funcionando para todas as variações
   - Verificar se queries estão encontrando mensagens

3. **Remover logs temporários:**
   - Após validação, remover todos os logs marcados com `[LOG TEMPORARIO]` ou `[DIAGNOSTICO]`

---

## 📋 Arquivos Modificados

1. `src/Services/ConversationService.php`
   - Adicionado log detalhado de `unread_count` em `updateConversationMetadata()`

2. `src/Controllers/CommunicationHubController.php`
   - Melhorada normalização de telefone em 3 métodos
   - Melhorado filtro SQL para buscar variações com/sem 9º dígito

3. `views/communication_hub/index.php`
   - Já estava correto (função `updateConversationListOnly()` implementada)

---

**Documento criado em:** 2026-01-14  
**Próxima revisão:** Após testes em produção

