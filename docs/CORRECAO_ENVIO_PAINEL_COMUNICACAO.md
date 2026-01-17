# Correção: Envio de Mensagens no Painel de Comunicação

**Data:** 2026-01-16  
**Problema:** Erro 500 ao tentar enviar mensagens no painel de comunicação  
**Solução:** Correção na lógica de resolução de `channel_id` e inicialização de `$targetChannels`

---

## Problemas Identificados e Corrigidos

### 1. **Bug: `$targetChannels` sendo resetado após ser definido**

**Problema:**
- Quando havia um `threadId`, o código definia `$targetChannels` corretamente na linha 393
- Mas depois resetava para `[]` na linha 416, apagando o valor definido
- Isso causava erro 500 porque o código tentava buscar o canal novamente

**Correção:**
- `$targetChannels` agora é inicializado no início do método (linha 329)
- Quando há `threadId` e o canal é encontrado, `$targetChannels` é definido e não é mais resetado

### 2. **Garantia: Uso do mesmo canal que recebeu a mensagem**

**Implementação:**
- Quando há `threadId`, o código sempre usa o `channel_id` da thread (fonte da verdade)
- Ignora qualquer `channel_id` enviado pelo frontend
- Valida que o canal existe e está habilitado antes de definir `$targetChannels`
- Retorna erro explícito se o canal não estiver disponível

---

## Fluxo Corrigido

```
1. Usuário clica em enviar mensagem no painel
   ↓
2. Frontend envia: channel=whatsapp, thread_id=whatsapp_5, message=...
   ↓
3. Backend recebe thread_id
   ↓
4. Busca conversation pelo ID (5)
   ↓
5. Extrai channel_id da conversation (ex: pixel12digital)
   ↓
6. Valida que canal existe e está habilitado
   ↓
7. Define targetChannels = [pixel12digital] ✅
   ↓
8. Verifica status do canal (connected/disconnected)
   ↓
9. Envia mensagem usando o mesmo canal que recebeu ✅
```

---

## Arquivos Modificados

### `src/Controllers/CommunicationHubController.php`

**Mudanças:**
1. **Linha 329:** Inicialização de `$targetChannels = []` no início
2. **Linhas 375-403:** Validação imediata do canal da thread e definição de `$targetChannels`
3. **Linhas 415-419:** Removida lógica que resetava `$targetChannels`

**Garantias:**
- ✅ `$targetChannels` sempre inicializado antes de ser usado
- ✅ Canal da thread sempre usado quando disponível
- ✅ Erros explícitos quando canal não está disponível
- ✅ Mesmo canal que recebeu é usado para enviar

---

## Teste Realizado

### Script de Teste: `test-send-charles-direct.php`

**Objetivo:** Simular envio como usuário real para Charles Dietrich (554796164699)

**Processo:**
1. Busca conversa pelo telefone
2. Identifica `channel_id` da conversa
3. Simula requisição POST com os mesmos dados do frontend
4. Chama `CommunicationHubController::send()` diretamente

**Status:** Script criado, aguardando execução para verificar erros

---

## Próximos Passos

1. **Testar no painel de comunicação:**
   - Abrir conversa do Charles Dietrich
   - Enviar uma mensagem
   - Verificar se é enviada pelo mesmo canal que recebeu

2. **Verificar logs se houver erro:**
   - Procurar por `[CommunicationHub::send]` nos logs
   - Verificar se `targetChannels` está sendo definido corretamente
   - Verificar se o canal está sendo validado

3. **Confirmar que recebimento não foi afetado:**
   - Enviar mensagem para o sistema
   - Verificar se é recebida e processada corretamente
   - Confirmar que `channel_id` está sendo persistido corretamente

---

## Garantias de Não Regressão

### ✅ Recebimento de Mensagens (NÃO ALTERADO)

- `WhatsAppWebhookController` - ✅ INTACTO
- `EventIngestionService` - ✅ INTACTO  
- `ConversationService::extractChannelIdFromPayload()` - ✅ INTACTO
- `ConversationService::updateConversationMetadata()` - ✅ INTACTO

### ✅ Apenas Envio Foi Modificado

- `CommunicationHubController::send()` - ✅ CORRIGIDO
- Lógica de resolução de `channel_id` - ✅ MELHORADA
- Validação de canal - ✅ ADICIONADA

---

## Logs para Debug

Se houver erro 500, verificar logs por:

```
[CommunicationHub::send] Usando channel_id da thread (fonte da verdade): {channel_id}
[CommunicationHub::send] Canal da thread validado e adicionado ao targetChannels: {channel_id}
[CommunicationHub::send] Canais alvo para envio: {channels}
[CommunicationHub::send] EXCEPTION: {erro}
```

---

## Conclusão

As correções garantem que:
1. ✅ O mesmo canal que recebeu a mensagem será usado para enviar
2. ✅ `$targetChannels` não será mais resetado incorretamente
3. ✅ Erros serão explícitos e informativos
4. ✅ Recebimento de mensagens não foi afetado

**Status:** ✅ Correções aplicadas, aguardando teste no painel de comunicação

