# Raio-X Técnico: Painel de Comunicação - Bugs Pós-Unificação

**Data:** 2026-01-14  
**Versão:** Pós-unificação de telas (lista + thread)  
**Status:** 🔴 Bugs Críticos Identificados

---

## A) MAPA DO FLUXO ATUAL (Pós-Unificação)

### A.1) Carregamento da Lista de Conversas (Triagem)

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `updateConversationListOnly()` (linhas 1004-1100)  
**Endpoint Backend:** `GET /communication-hub/conversations-list`  
**Controller:** `CommunicationHubController::getConversationsList()` (linhas 1312-1363)

**Fluxo:**
1. Frontend chama `updateConversationListOnly()` quando detecta atualizações
2. Faz fetch para `/communication-hub/conversations-list?channel=X&status=Y`
3. Backend busca de `conversations` ordenado por `last_message_at DESC`
4. Frontend recebe JSON com array `threads[]` contendo:
   - `thread_id`, `last_activity`, `unread_count`, `message_count`
5. Frontend ordena novamente por `last_activity DESC` (linha 1058-1062)
6. Chama `renderConversationList()` para atualizar DOM
7. Preserva conversa ativa (não recarrega thread)

**Fonte de Verdade:** 
- **Backend:** `conversations.last_message_at` (atualizado por `ConversationService::updateConversationMetadata()`)
- **Frontend:** Estado local `ConversationState` + DOM renderizado

---

### A.2) Abertura/Restore da Conversa Ativa (thread_id)

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `loadConversation(threadId, channel)` (linhas 1327-1396)  
**Endpoint Backend:** `GET /communication-hub/thread-data?thread_id=X&channel=Y`  
**Controller:** `CommunicationHubController::getThreadData()` (linhas 700-850)

**Fluxo:**
1. Usuário clica em conversa OU URL tem `?thread_id=whatsapp_34`
2. `loadConversation()` é chamado
3. **LIMPA estado anterior:**
   - `ConversationState.lastTimestamp = null`
   - `ConversationState.lastEventId = null`
   - `ConversationState.messageIds.clear()`
4. Faz fetch para `/communication-hub/thread-data`
5. Backend busca mensagens via `getWhatsAppMessagesFromConversation()`
6. Frontend renderiza via `renderConversation()`
7. Chama `initializeConversationMarkers()` para setar `lastTimestamp`/`lastEventId`
8. Inicia polling via `startConversationPolling()`

**Fonte de Verdade:**
- **Backend:** `communication_events` filtrado por `contact_external_id` normalizado
- **Frontend:** `ConversationState.lastTimestamp` e `ConversationState.lastEventId` (inicializados após render)

---

### A.3) Polling / Check Updates / lastUpdateTs / after_timestamp

**Arquivo:** `views/communication_hub/index.php`  
**Funções:**
- `checkForListUpdates()` (linhas 850-900) - Polling da lista
- `checkForNewConversationMessages()` (linhas 1881-1922) - Polling do thread ativo

**Endpoints Backend:**
- `GET /communication-hub/check-updates?after_timestamp=X` → `checkUpdates()`
- `GET /communication-hub/messages/check?thread_id=X&after_timestamp=Y&after_event_id=Z` → `checkNewMessages()`

**Fluxo do Polling da Lista:**
1. `checkForListUpdates()` roda a cada 5 segundos
2. Verifica se `conversations.updated_at` ou `last_message_at` mudou
3. Se mudou E conversa ativa existe → chama `updateConversationListOnly()`
4. Se mudou E conversa ativa NÃO existe → recarrega página após 3s

**Fluxo do Polling do Thread:**
1. `checkForNewConversationMessages()` roda a cada 12 segundos (se thread ativo)
2. Usa `ConversationState.lastTimestamp` e `ConversationState.lastEventId`
3. Chama `/communication-hub/messages/check`
4. Backend busca eventos com `created_at > after_timestamp` E `event_id > after_event_id`
5. Se `has_new=true` → chama `/communication-hub/messages/new`
6. Adiciona mensagens via `onNewMessagesFromPanel()`

**Fonte de Verdade:**
- **Backend:** `communication_events.created_at` e `event_id`
- **Frontend:** `ConversationState.lastTimestamp` (string ISO) e `ConversationState.lastEventId` (UUID)

---

### A.4) Merge de Mensagens Recebidas no Estado Local

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `onNewMessagesFromPanel(messages)` (linhas 1680-1737)

**Fluxo:**
1. Recebe array de mensagens do endpoint `/communication-hub/messages/new`
2. Filtra duplicatas via `ConversationState.messageIds` (Set)
3. Adiciona mensagens ao DOM via `appendMessage()`
4. **Atualiza `ConversationState.lastTimestamp` e `lastEventId`** (linhas 1707-1708)
5. Se usuário está no final do scroll → auto-scroll
6. Se não está no final → mostra badge "X nova(s) mensagem(ns)"

**Fonte de Verdade:**
- **Backend:** `communication_events` ordenado por `created_at ASC`
- **Frontend:** `ConversationState.messageIds` (Set) + DOM `<div data-message-id="...">`

---

### A.5) Cálculo de "unread/badge"

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `renderConversationList()` (linhas 1107-1200)

**Badge na Lista:**
- Renderizado na linha 1189-1193: `if (unreadCount > 0)`
- `unreadCount` vem de `thread.unread_count` do backend
- Backend retorna `conversations.unread_count` (atualizado por `ConversationService::updateConversationMetadata()`)

**Badge no Thread (novas mensagens enquanto scrollado):**
- Função `showNewMessagesBadge()` (linhas 1739-1745)
- Contador `ConversationState.newMessagesCount` (incrementado quando mensagem chega e usuário não está no final)

**Fonte de Verdade:**
- **Backend:** `conversations.unread_count` (incrementado em `ConversationService::updateConversationMetadata()` quando `direction='inbound'`)
- **Frontend:** `thread.unread_count` do JSON + `ConversationState.newMessagesCount` (local)

**Reset do Badge:**
- **Lista:** Não há reset automático quando abre conversa (precisa de `markConversationAsRead()`)
- **Thread:** Reset quando usuário faz scroll até o final (linha 1731)

---

### A.6) Regra de "Subir ao Topo" (Sorting)

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `updateConversationListOnly()` (linhas 1056-1067)

**Fluxo:**
1. Backend retorna threads ordenados por `last_message_at DESC`
2. Frontend ordena novamente por `last_activity DESC` (linha 1058-1062)
3. Chama `renderConversationList()` que recria todo o DOM
4. **Preserva conversa ativa** (linhas 1078-1090) - não recarrega thread

**Fonte de Verdade:**
- **Backend:** `conversations.last_message_at` (atualizado por `ConversationService::updateConversationMetadata()`)
- **Frontend:** `thread.last_activity` do JSON (mapeado de `last_message_at`)

**Problema Identificado:**
- `updateConversationListOnly()` preserva conversa ativa, mas **não atualiza o `last_activity` exibido na lista** se a conversa ativa recebeu mensagem
- A lista é re-renderizada, mas o `last_activity` pode estar desatualizado se o backend não retornou o valor mais recente

---

## B) HIPÓTESES DE CAUSA (Bem Específicas)

### B.1) Hipótese #1: `checkNewMessages()` Retorna `has_new=false` Incorretamente

**Sintoma:** 
- Charles (4699): Mensagem aparece no thread, mas não sobe ao topo e não recebe badge
- ServPro (4223): Badge aparece, mas mensagem não aparece no thread

**Onde pode estar o bug:**
- `CommunicationHubController::checkNewMessages()` (linhas 1492-1672)
- Query SQL usa `JSON_UNQUOTE(JSON_EXTRACT(...)) LIKE ?` mas pode não estar encontrando eventos
- Normalização de telefone pode divergir entre `checkNewMessages()` e `getWhatsAppMessagesFromConversation()`

**Como reproduzir:**
1. Abrir conversa do Charles (whatsapp_35)
2. Enviar mensagem do Charles para Pixel12
3. Verificar console: `checkForNewConversationMessages() - RESULTADO: has_new=false`
4. Verificar logs do servidor: `[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - RESULTADO: has_new=...`

**Como validar:**
```javascript
// Console do navegador
console.log('lastTimestamp:', ConversationState.lastTimestamp);
console.log('lastEventId:', ConversationState.lastEventId);
console.log('thread_id:', ConversationState.currentThreadId);
```

**Logs do servidor a verificar:**
```
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - QUERY RETORNOU: events_count=X
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - VALIDANDO EVENTO: event_id=..., from_normalized=..., expected=...
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - RESULTADO: has_new=..., matched_events=...
```

**Causa Provável:**
- Query SQL não está encontrando eventos porque:
  - `after_timestamp` está muito antigo (ex: `2026-01-14 13:57:50` quando há mensagem às `14:54`)
  - Normalização de telefone diverge (ex: backend normaliza `554796164699`, mas evento tem `554796164699@c.us`)
  - `JSON_UNQUOTE` pode não estar funcionando corretamente em todas as versões do MySQL

---

### B.2) Hipótese #2: `lastTimestamp` Não é Atualizado Quando Conversa é Carregada

**Sintoma:**
- Charles (4699): Mensagem aparece no thread, mas `checkNewMessages()` usa `lastTimestamp` antigo
- ServPro (4223): `lastTimestamp` pode estar `null` ou muito antigo

**Onde pode estar o bug:**
- `initializeConversationMarkers()` (linhas 1795-1816)
- Função busca última mensagem no DOM: `document.querySelector('[data-message-id]')`
- Se DOM não tem mensagens OU mensagens não têm `data-message-id`, `lastTimestamp` fica `null` ou usa `new Date().toISOString()`

**Como reproduzir:**
1. Abrir conversa do ServPro (whatsapp_34)
2. Verificar console: `ConversationState.lastTimestamp` deve ser timestamp da última mensagem
3. Se for `null` ou muito antigo → bug confirmado

**Como validar:**
```javascript
// Console do navegador (após carregar conversa)
const lastMsg = document.querySelector('[data-message-id]');
console.log('Última mensagem no DOM:', lastMsg?.getAttribute('data-timestamp'));
console.log('ConversationState.lastTimestamp:', ConversationState.lastTimestamp);
```

**Causa Provável:**
- `initializeConversationMarkers()` busca `[data-message-id]` mas mensagens podem não ter esse atributo
- Se não encontrar, usa `new Date().toISOString()` que é o timestamp atual (não o da última mensagem)
- Isso faz `checkNewMessages()` buscar eventos futuros (não existem)

---

### B.3) Hipótese #3: `unread_count` Não é Incrementado ou é Resetado Prematuramente

**Sintoma:**
- Charles (4699): Não recebe badge mesmo com mensagem inbound
- ServPro (4223): Recebe badge, mas mensagem não aparece (pode ser badge de mensagem antiga)

**Onde pode estar o bug:**
- `ConversationService::updateConversationMetadata()` (linhas 543-647)
- Incremento de `unread_count` só acontece se `direction='inbound'` (linha 580)
- Reset pode acontecer em `markConversationAsRead()` (não encontrado no código atual)

**Como reproduzir:**
1. Verificar no banco: `SELECT unread_count, last_message_at FROM conversations WHERE id IN (34, 35)`
2. Enviar mensagem inbound
3. Verificar se `unread_count` incrementou
4. Verificar se `last_message_at` atualizou

**Como validar:**
```sql
-- Query para verificar unread_count e last_message_at
SELECT 
    id,
    contact_external_id,
    unread_count,
    last_message_at,
    last_message_direction,
    updated_at
FROM conversations
WHERE id IN (34, 35)
ORDER BY last_message_at DESC;
```

**Logs do servidor a verificar:**
```
[DIAGNOSTICO] ConversationService::updateConversationMetadata() - UPDATE EXECUTADO: unread_count: X -> Y
```

**Causa Provável:**
- `unread_count` está sendo incrementado corretamente no backend
- Mas frontend não está refletindo porque `updateConversationListOnly()` não atualiza badge se conversa está ativa
- Ou `markConversationAsRead()` está sendo chamado automaticamente quando abre conversa (não encontrado no código)

---

### B.4) Hipótese #4: Ordenação da Lista Não Atualiza `last_activity` da Conversa Ativa

**Sintoma:**
- Charles (4699): Mensagem aparece no thread, mas conversa não sobe ao topo
- Backend retorna `last_activity=2026-01-14 14:29:43` mas mensagem mais recente é `14:55`

**Onde pode estar o bug:**
- `updateConversationListOnly()` (linhas 1004-1100)
- Backend pode estar retornando `last_message_at` desatualizado
- Ou frontend está preservando `last_activity` antigo da conversa ativa

**Como reproduzir:**
1. Abrir conversa do Charles (whatsapp_35)
2. Enviar mensagem do Charles para Pixel12
3. Verificar console: `updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=...`
4. Verificar se `last_activity` do Charles está atualizado

**Como validar:**
```javascript
// Console do navegador (após updateConversationListOnly)
const threads = await fetch('/communication-hub/conversations-list?channel=whatsapp&status=active').then(r => r.json());
const charles = threads.threads.find(t => t.thread_id === 'whatsapp_35');
console.log('Charles last_activity:', charles?.last_activity);
```

**Causa Provável:**
- Backend está retornando `last_message_at` desatualizado porque:
  - `ConversationService::updateConversationMetadata()` não está sendo chamado quando mensagem chega
  - Ou está sendo chamado, mas `last_message_at` não está sendo atualizado corretamente
- Frontend está ordenando corretamente, mas backend não está retornando valor atualizado

---

### B.5) Hipótese #5: Normalização de Telefone Diverge Entre Métodos

**Sintoma:**
- ServPro (4223): Badge aparece, mas mensagem não aparece no thread
- `checkNewMessages()` pode não estar encontrando eventos porque normalização diverge

**Onde pode estar o bug:**
- `CommunicationHubController::checkNewMessages()` (linha 1520-1533)
- `CommunicationHubController::getWhatsAppMessagesFromConversation()` (linha 910-923)
- Normalização pode ser diferente entre os dois métodos

**Como reproduzir:**
1. Verificar logs do servidor para ServPro (4223):
   - `[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - NORMALIZACAO: contact_external_id_original=..., normalized=...`
   - `[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - NORMALIZACAO: contact_external_id_original=..., normalized=...`
2. Comparar se `normalized` é igual nos dois métodos

**Como validar:**
```php
// Adicionar log temporário em checkNewMessages() e getWhatsAppMessagesFromConversation()
error_log('NORMALIZACAO CHECK: ' . $normalizedContact);
error_log('NORMALIZACAO THREAD: ' . $normalizedContactExternalId);
```

**Causa Provável:**
- Normalização está correta, mas query SQL não está encontrando porque:
  - `JSON_UNQUOTE` pode não estar funcionando
  - Ou padrões LIKE não estão capturando todas as variações (`@c.us`, `@lid`, etc.)

---

## C) PLANO DE CORREÇÃO

### C.1) Abordagem #1: Corrigir Inicialização de `lastTimestamp` (MAIS SEGURA)

**Impacto:** Baixo - Apenas ajusta como `lastTimestamp` é inicializado  
**Risco:** Baixo - Não altera lógica principal  
**O que pode quebrar:** Nada (apenas melhora detecção)

**Mudanças:**
1. Em `initializeConversationMarkers()`, garantir que `lastTimestamp` seja sempre o timestamp da última mensagem renderizada
2. Se não encontrar mensagens no DOM, buscar do backend via endpoint `/communication-hub/thread-data` e usar `messages[last].timestamp`
3. Adicionar fallback: se `lastTimestamp` for `null`, usar `new Date(0).toISOString()` para buscar todas as mensagens

**Instrumentação:**
```javascript
// Adicionar em initializeConversationMarkers()
console.log('[FIX] initializeConversationMarkers - lastTimestamp:', ConversationState.lastTimestamp);
console.log('[FIX] initializeConversationMarkers - lastEventId:', ConversationState.lastEventId);
console.log('[FIX] initializeConversationMarkers - messages_count:', document.querySelectorAll('[data-message-id]').length);
```

---

### C.2) Abordagem #2: Forçar Atualização de `last_activity` na Lista Quando Conversa Ativa Recebe Mensagem

**Impacto:** Médio - Altera lógica de `updateConversationListOnly()`  
**Risco:** Médio - Pode causar flicker na lista  
**O que pode quebrar:** Ordenação pode piscar se atualizar muito rápido

**Mudanças:**
1. Em `updateConversationListOnly()`, após receber threads do backend, verificar se conversa ativa está na lista
2. Se estiver, buscar `last_message_at` atualizado do backend via endpoint separado
3. Atualizar `last_activity` do thread ativo antes de ordenar
4. Garantir que conversa ativa suba ao topo se recebeu mensagem

**Instrumentação:**
```javascript
// Adicionar em updateConversationListOnly()
const activeThread = result.threads.find(t => t.thread_id === activeThreadId);
if (activeThread) {
    console.log('[FIX] updateConversationListOnly - Active thread last_activity:', activeThread.last_activity);
    // Buscar atualizado se necessário
}
```

---

### C.3) Abordagem #3: Corrigir Query SQL em `checkNewMessages()` para Usar Mesma Normalização

**Impacto:** Médio - Altera query SQL  
**Risco:** Médio - Pode afetar performance se query ficar mais lenta  
**O que pode quebrar:** Nada (apenas melhora detecção)

**Mudanças:**
1. Garantir que `checkNewMessages()` use exatamente a mesma normalização de `getWhatsAppMessagesFromConversation()`
2. Adicionar mais padrões LIKE para capturar variações (`@c.us`, `@lid`, com/sem 9º dígito)
3. Testar `JSON_UNQUOTE` em todas as versões do MySQL

**Instrumentação:**
```php
// Adicionar em checkNewMessages()
error_log('[FIX] checkNewMessages - Normalized contact: ' . $normalizedContact);
error_log('[FIX] checkNewMessages - Contact patterns: ' . json_encode($contactPatterns));
error_log('[FIX] checkNewMessages - SQL WHERE: ' . $whereClause);
error_log('[FIX] checkNewMessages - Events found: ' . count($events));
```

---

### C.4) Abordagem #4: Adicionar `markConversationAsRead()` Quando Conversa é Aberta

**Impacto:** Alto - Adiciona nova funcionalidade  
**Risco:** Alto - Pode resetar badge antes de mensagens aparecerem  
**O que pode quebrar:** Badge pode sumir antes de usuário ver mensagem

**Mudanças:**
1. Criar endpoint `POST /communication-hub/mark-read?thread_id=X`
2. Chamar endpoint quando `loadConversation()` completa E mensagens são renderizadas
3. Resetar `unread_count` no backend
4. Atualizar badge na lista via `updateConversationListOnly()`

**Instrumentação:**
```javascript
// Adicionar em loadConversation() após renderConversation()
if (result.thread.unread_count > 0) {
    console.log('[FIX] loadConversation - Marking as read, unread_count:', result.thread.unread_count);
    await fetch('/communication-hub/mark-read?thread_id=' + threadId);
}
```

---

### C.5) Abordagem Recomendada (Combinação)

**Prioridade:** Abordagem #1 + #2 + #3 (em ordem)

1. **Primeiro:** Corrigir `initializeConversationMarkers()` para garantir `lastTimestamp` correto
2. **Segundo:** Melhorar `updateConversationListOnly()` para atualizar `last_activity` da conversa ativa
3. **Terceiro:** Validar e corrigir query SQL em `checkNewMessages()` se necessário

**Por quê:**
- Abordagem #1 resolve problema de `checkNewMessages()` retornar `has_new=false`
- Abordagem #2 resolve problema de conversa não subir ao topo
- Abordagem #3 garante que normalização está correta

---

## D) RELAÇÃO EXAUSTIVA DO QUE JÁ FOI FEITO (Pós-Unificação)

### D.1) Mudanças Realizadas

**Arquivo:** `src/Controllers/CommunicationHubController.php`
- ✅ Adicionado `getConversationsList()` para retornar lista via AJAX (linhas 1312-1363)
- ✅ Adicionado `checkUpdates()` para verificar atualizações sem recarregar (linhas 1445-1481)
- ✅ Adicionado `checkNewMessages()` para verificar novas mensagens do thread ativo (linhas 1492-1672)
- ✅ Adicionado `getNewMessages()` para buscar mensagens incrementais (linhas 1679-1829)
- ✅ Corrigido `getWhatsAppMessagesFromConversation()` para usar normalização robusta (linhas 890-1080)
- ✅ Corrigido `getWhatsAppMessagesIncremental()` para usar normalização robusta (linhas 1875-2069)
- ✅ **CORREÇÃO APLICADA (commit 15e9476):** Queries SQL agora usam `JSON_UNQUOTE(JSON_EXTRACT(...))` para LIKE funcionar corretamente
  - **Problema:** `checkNewMessages()` retornava `has_new=false` mesmo com mensagens novas
  - **Causa:** Query usando `JSON_EXTRACT()` com `LIKE`, mas o retorno vinha com aspas (ex: `"554796164699@c.us"`), quebrando o match
  - **Solução:** Substituído por `JSON_UNQUOTE(JSON_EXTRACT(...))` nas queries com `LIKE`
  - **Aplicado em 3 métodos:**
    - `checkNewMessages()` (linhas 1579-1582)
    - `getWhatsAppMessagesIncremental()` (linhas 1930-1933)
    - `getWhatsAppMessagesFromConversation()` (linhas 970-973)

**Arquivo:** `views/communication_hub/index.php`
- ✅ Adicionado `updateConversationListOnly()` para atualizar lista sem reload (linhas 1004-1100)
- ✅ Adicionado `checkForListUpdates()` para polling da lista (linhas 850-900)
- ✅ Adicionado `checkForNewConversationMessages()` para polling do thread (linhas 1881-1922)
- ✅ Adicionado `onNewMessagesFromPanel()` para merge de mensagens (linhas 1680-1737)
- ✅ Adicionado `ConversationState` para gerenciar estado do thread ativo (linhas 1312-1322)
- ✅ Adicionado ordenação explícita por `last_activity DESC` em `updateConversationListOnly()` (linhas 1058-1062)
- ✅ Adicionado logs temporários `[LOG TEMPORARIO]` em várias funções

**Arquivo:** `src/Services/ConversationService.php`
- ✅ `updateConversationMetadata()` incrementa `unread_count` para inbound (linha 580)
- ✅ `updateConversationMetadata()` atualiza `last_message_at` com timestamp da mensagem (linha 576)
- ✅ Adicionado logs `[DIAGNOSTICO]` para rastrear atualizações

**Arquivo:** `src/Services/EventIngestionService.php`
- ✅ `ingest()` chama `ConversationService::resolveConversation()` automaticamente (linha 171)
- ✅ Adicionado logs `[DIAGNOSTICO]` para rastrear ingestão

---

### D.2) Testes Realizados

**Números Testados:**
- ✅ Charles (4699) - `whatsapp_35`
- ✅ ServPro (4223) - `whatsapp_34`

**Canais Testados:**
- ✅ WhatsApp - Canal "Pixel12 Digital" (tenant_id=2)

**Endpoints Testados:**
- ✅ `GET /communication-hub/conversations-list` - Retorna lista ordenada
- ✅ `GET /communication-hub/check-updates` - Detecta atualizações
- ✅ `GET /communication-hub/messages/check` - Verifica novas mensagens
- ✅ `GET /communication-hub/messages/new` - Busca mensagens incrementais

**Horários dos Testes:**
- 2026-01-14 14:26 - ServPro enviou mensagem
- 2026-01-14 14:28 - Charles enviou mensagem
- 2026-01-14 14:44 - ServPro enviou mensagem
- 2026-01-14 14:54 - Charles enviou mensagem

---

### D.3) O Que Foi Corrigido

**Issue #1: Coluna `channel_id` na tabela `conversations`**
- ✅ Adicionada coluna `channel_id` via migration
- ✅ `ConversationService::updateConversationMetadata()` atualiza `channel_id` para eventos inbound

**Issue #2: Normalização de Telefone**
- ✅ Implementada normalização robusta que remove `@c.us`, `@lid`, etc.
- ✅ Suporte para variação com/sem 9º dígito (números BR)
- ✅ Aplicada em `getWhatsAppMessagesFromConversation()`, `getWhatsAppMessagesIncremental()`, `checkNewMessages()`

**Issue #3: Query SQL com `JSON_EXTRACT` e `LIKE`**
- ✅ Corrigido para usar `JSON_UNQUOTE(JSON_EXTRACT(...))` antes de `LIKE`
- ✅ Aplicado em todas as queries que buscam por telefone no payload JSON

**Issue #4: Ordenação da Lista**
- ✅ Backend ordena por `last_message_at DESC`
- ✅ Frontend ordena novamente por `last_activity DESC` antes de renderizar

---

### D.4) O Que Ainda Está Pendente / Comportamentos Inconsistentes

**Pendente #1: `checkNewMessages()` Retorna `has_new=false` Incorretamente**
- ✅ **CORRIGIDO (commit 15e9476):** Query SQL agora usa `JSON_UNQUOTE(JSON_EXTRACT(...))` para LIKE funcionar
- ⚠️ **PENDENTE VALIDAÇÃO:** Confirmar se `has_new=true` agora funciona corretamente em produção
- ⚠️ **POSSÍVEL CAUSA RESIDUAL:** `lastTimestamp` pode estar desatualizado ou `null` quando conversa é carregada

**Pendente #2: Badge Não Aparece para Charles (4699)**
- ❌ `unread_count` pode estar sendo incrementado no backend
- ❌ Mas frontend não está refletindo porque `updateConversationListOnly()` não atualiza badge se conversa está ativa
- ❌ Ou `markConversationAsRead()` está sendo chamado automaticamente (não encontrado no código)

**Pendente #3: Conversa Não Sobe ao Topo (Charles 4699)**
- ❌ Backend retorna `last_activity=2026-01-14 14:29:43` mas mensagem mais recente é `14:55`
- ❌ `updateConversationListOnly()` preserva conversa ativa, mas não atualiza `last_activity` exibido
- ❌ Ou backend não está retornando `last_message_at` atualizado

**Pendente #4: Mensagem Não Aparece no Thread (ServPro 4223)**
- ❌ Badge aparece (indica que `unread_count` foi incrementado)
- ❌ Mas mensagem não aparece no thread quando conversa é aberta
- ❌ Pode ser problema de normalização ou query SQL em `getWhatsAppMessagesFromConversation()`

---

## E) CHECKLIST DE TESTES PARA FECHAR O BUG

### E.1) Caso de Teste: Charles (4699) - Conversa Fechada

**Pré-condições:**
- Conversa do Charles (whatsapp_35) está na lista, mas não está aberta
- `unread_count = 0` no banco
- `last_message_at` é timestamp da última mensagem conhecida

**Passos:**
1. Enviar mensagem do Charles para Pixel12 Digital
2. Aguardar 5 segundos (polling da lista)
3. Verificar se badge aparece na lista
4. Verificar se conversa sobe ao topo
5. Clicar na conversa
6. Verificar se mensagem aparece no thread
7. Verificar se badge desaparece

**Validações:**
- [ ] Badge aparece na lista (`unread_count > 0`)
- [ ] Conversa sobe ao topo (`last_activity` é o mais recente)
- [ ] Mensagem aparece no thread
- [ ] Badge desaparece após abrir conversa
- [ ] `unread_count` no banco é resetado para 0

**Logs a Verificar:**
```javascript
// Console do navegador
[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=whatsapp_35
[LOG TEMPORARIO] renderConversationList() - PRIMEIRO: thread_id=whatsapp_35, unread_count=1
```

```sql
-- Query no banco
SELECT unread_count, last_message_at FROM conversations WHERE id = 35;
```

---

### E.2) Caso de Teste: Charles (4699) - Conversa Aberta

**Pré-condições:**
- Conversa do Charles (whatsapp_35) está aberta no thread
- `ConversationState.lastTimestamp` é timestamp da última mensagem renderizada
- `ConversationState.lastEventId` é event_id da última mensagem renderizada

**Passos:**
1. Enviar mensagem do Charles para Pixel12 Digital
2. Aguardar 12 segundos (polling do thread)
3. Verificar se mensagem aparece no thread automaticamente
4. Verificar se conversa sobe ao topo na lista
5. Verificar se badge aparece (se usuário não está no final do scroll)

**Validações:**
- [ ] Mensagem aparece no thread automaticamente (sem reload)
- [ ] `ConversationState.lastTimestamp` é atualizado
- [ ] Conversa sobe ao topo na lista
- [ ] Badge aparece se usuário não está no final do scroll

**Logs a Verificar:**
```javascript
// Console do navegador
[LOG TEMPORARIO] checkForNewConversationMessages() - RESULTADO: success=true, has_new=true
[LOG TEMPORARIO] checkForNewConversationMessages() - FETCH RESULTADO: messages_count=1
[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=whatsapp_35
```

```php
// Logs do servidor
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - RESULTADO: has_new=true, matched_events=1
```

---

### E.3) Caso de Teste: ServPro (4223) - Conversa Fechada

**Pré-condições:**
- Conversa do ServPro (whatsapp_34) está na lista, mas não está aberta
- `unread_count = 0` no banco
- `last_message_at` é timestamp da última mensagem conhecida

**Passos:**
1. Enviar mensagem do ServPro para Pixel12 Digital
2. Aguardar 5 segundos (polling da lista)
3. Verificar se badge aparece na lista
4. Verificar se conversa sobe ao topo
5. Clicar na conversa
6. Verificar se mensagem aparece no thread
7. Verificar se badge desaparece

**Validações:**
- [ ] Badge aparece na lista (`unread_count > 0`)
- [ ] Conversa sobe ao topo (`last_activity` é o mais recente)
- [ ] **CRÍTICO:** Mensagem aparece no thread
- [ ] Badge desaparece após abrir conversa
- [ ] `unread_count` no banco é resetado para 0

**Logs a Verificar:**
```javascript
// Console do navegador
[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=whatsapp_34
```

```php
// Logs do servidor
[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - QUERY RETORNOU: events_count=X
[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - NORMALIZACAO: normalized=554796474223
```

---

### E.4) Caso de Teste: ServPro (4223) - Conversa Aberta

**Pré-condições:**
- Conversa do ServPro (whatsapp_34) está aberta no thread
- `ConversationState.lastTimestamp` é timestamp da última mensagem renderizada
- `ConversationState.lastEventId` é event_id da última mensagem renderizada

**Passos:**
1. Enviar mensagem do ServPro para Pixel12 Digital
2. Aguardar 12 segundos (polling do thread)
3. Verificar se mensagem aparece no thread automaticamente
4. Verificar se conversa sobe ao topo na lista
5. Verificar se badge aparece (se usuário não está no final do scroll)

**Validações:**
- [ ] **CRÍTICO:** Mensagem aparece no thread automaticamente (sem reload)
- [ ] `ConversationState.lastTimestamp` é atualizado
- [ ] Conversa sobe ao topo na lista
- [ ] Badge aparece se usuário não está no final do scroll

**Logs a Verificar:**
```javascript
// Console do navegador
[LOG TEMPORARIO] checkForNewConversationMessages() - RESULTADO: success=true, has_new=true
[LOG TEMPORARIO] checkForNewConversationMessages() - FETCH RESULTADO: messages_count=1
```

```php
// Logs do servidor
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - NORMALIZACAO: normalized=554796474223
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - QUERY RETORNOU: events_count=1
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - RESULTADO: has_new=true, matched_events=1
```

---

### E.5) Caso de Teste: Conversa Ativa Diferente

**Pré-condições:**
- Conversa do Charles (whatsapp_35) está aberta no thread
- Conversa do ServPro (whatsapp_34) está na lista, mas não está aberta

**Passos:**
1. Enviar mensagem do ServPro para Pixel12 Digital
2. Aguardar 5 segundos (polling da lista)
3. Verificar se badge aparece na lista do ServPro
4. Verificar se ServPro sobe ao topo (acima do Charles)
5. Verificar se Charles continua aberto no thread (não deve fechar)

**Validações:**
- [ ] Badge aparece na lista do ServPro
- [ ] ServPro sobe ao topo (acima do Charles)
- [ ] Charles continua aberto no thread (não fecha)
- [ ] `updateConversationListOnly()` preserva conversa ativa (Charles)

**Logs a Verificar:**
```javascript
// Console do navegador
[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=whatsapp_34
[LOG TEMPORARIO] updateConversationListOnly() - CONCLUIDO: lista atualizada, conversa ativa preservada=SIM
```

---

### E.6) Verificação de Timestamps e thread_id

**Queries SQL para Validar:**

```sql
-- Verificar last_message_at e unread_count
SELECT 
    id,
    contact_external_id,
    unread_count,
    last_message_at,
    last_message_direction,
    updated_at
FROM conversations
WHERE id IN (34, 35)
ORDER BY last_message_at DESC;

-- Verificar eventos recentes
SELECT 
    event_id,
    event_type,
    created_at,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_field,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as message_from
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC
LIMIT 10;
```

**Endpoints para Validar:**

```bash
# Verificar lista de conversas
curl "https://hub.pixel12digital.com.br/communication-hub/conversations-list?channel=whatsapp&status=active" \
  -H "Cookie: ..."

# Verificar thread do Charles
curl "https://hub.pixel12digital.com.br/communication-hub/thread-data?thread_id=whatsapp_35&channel=whatsapp" \
  -H "Cookie: ..."

# Verificar se há novas mensagens
curl "https://hub.pixel12digital.com.br/communication-hub/messages/check?thread_id=whatsapp_35&after_timestamp=2026-01-14+14%3A55%3A35&after_event_id=f3feeb35-0dff-4ddb-8da0-d3c47890262b" \
  -H "Cookie: ..."
```

---

## F) INSTRUMENTAÇÃO SUGERIDA (Logs Temporários)

### F.1) Console do Navegador (Copiar e Colar)

```javascript
// Adicionar em initializeConversationMarkers() após linha 1814
console.log('[FIX] initializeConversationMarkers - lastTimestamp:', ConversationState.lastTimestamp);
console.log('[FIX] initializeConversationMarkers - lastEventId:', ConversationState.lastEventId);
console.log('[FIX] initializeConversationMarkers - messages_count:', document.querySelectorAll('[data-message-id]').length);
const lastMsg = document.querySelector('[data-message-id]');
if (lastMsg) {
    console.log('[FIX] initializeConversationMarkers - last_msg_timestamp:', lastMsg.getAttribute('data-timestamp'));
    console.log('[FIX] initializeConversationMarkers - last_msg_id:', lastMsg.getAttribute('data-message-id'));
}

// Adicionar em updateConversationListOnly() após linha 1028
const activeThread = result.threads.find(t => t.thread_id === activeThreadId);
if (activeThread) {
    console.log('[FIX] updateConversationListOnly - Active thread last_activity:', activeThread.last_activity);
    console.log('[FIX] updateConversationListOnly - Active thread unread_count:', activeThread.unread_count);
}

// Adicionar em checkForNewConversationMessages() após linha 1900
console.log('[FIX] checkForNewConversationMessages - checkUrl:', checkUrl);
console.log('[FIX] checkForNewConversationMessages - lastTimestamp:', ConversationState.lastTimestamp);
console.log('[FIX] checkForNewConversationMessages - lastEventId:', ConversationState.lastEventId);
```

### F.2) Logs do Servidor (PHP error_log)

```php
// Adicionar em checkNewMessages() após linha 1607
error_log('[FIX] checkNewMessages - Normalized contact: ' . $normalizedContact);
error_log('[FIX] checkNewMessages - Contact patterns: ' . json_encode($contactPatterns));
error_log('[FIX] checkNewMessages - SQL WHERE: ' . $whereClause);
error_log('[FIX] checkNewMessages - SQL params: ' . json_encode($params));
error_log('[FIX] checkNewMessages - Events found: ' . count($events));

// Adicionar em getConversationsList() após linha 1350
$charlesThread = array_filter($allThreads, fn($t) => strpos($t['thread_id'] ?? '', '35') !== false);
$servproThread = array_filter($allThreads, fn($t) => strpos($t['thread_id'] ?? '', '34') !== false);
if (!empty($charlesThread)) {
    $charles = reset($charlesThread);
    error_log('[FIX] getConversationsList - Charles: thread_id=' . $charles['thread_id'] . ', last_activity=' . $charles['last_activity'] . ', unread_count=' . $charles['unread_count']);
}
if (!empty($servproThread)) {
    $servpro = reset($servproThread);
    error_log('[FIX] getConversationsList - ServPro: thread_id=' . $servpro['thread_id'] . ', last_activity=' . $servpro['last_activity'] . ', unread_count=' . $servpro['unread_count']);
}
```

---

## CONCLUSÃO

### Correção Aplicada (commit 15e9476)

**O que foi corrigido:**
- ✅ Query SQL em `checkNewMessages()`, `getWhatsAppMessagesIncremental()` e `getWhatsAppMessagesFromConversation()` agora usa `JSON_UNQUOTE(JSON_EXTRACT(...))` antes de `LIKE`
- ✅ Isso resolve o problema de `has_new=false` incorretamente quando há mensagens novas

**O que essa correção resolve:**
- ✅ Thread ativo que não atualizava porque `has_new=false` travava a chamada do `/messages/new`
- ✅ Polling do thread agora deve detectar novas mensagens corretamente

**O que essa correção NÃO resolve sozinha:**

1. **Conversa não sobe ao topo / badge não aparece (4699 - Charles)**
   - Mesmo com mensagem chegando no thread, a lista pode não refletir `last_activity` / `unread_count` da conversa ativa
   - Causa: Comportamento de "preservar conversa ativa" no `updateConversationListOnly()` atualiza a lista sem recarregar a thread, e pode ficar com `last_activity`/badge desatualizado

2. **Badge aparece, mas ao clicar não vê a mensagem (4223 - ServPro)**
   - Se o badge incrementa (metadados de conversa atualizados), mas a thread não carrega mensagem, isso costuma cair em:
     - `lastTimestamp` / `lastEventId` inicializados errado ao abrir a conversa (ex.: `null`/timestamp atual) → polling incremental "pula" mensagens
     - Normalização/padrões divergentes entre o que a lista usa e o que o carregamento da thread usa

---

### Próximos Passos Objetivos (Ordem de Menor Risco)

**Passo A — Validar imediatamente se o `has_new` virou `true` quando deve**
- Com a conversa aberta, mandar mensagem e verificar se o endpoint `/communication-hub/messages/check` responde `has_new=true`
- Isso confirma que o fix está "batendo" no gargalo certo (polling do thread)
- **Validação:** Verificar logs do console: `[LOG TEMPORARIO] checkForNewConversationMessages() - RESULTADO: success=true, has_new=true`

**Passo B — Garantir que `initializeConversationMarkers()` pega o "último timestamp real"**
- Quando abre a conversa, o fluxo zera `ConversationState.lastTimestamp`/`lastEventId` e depois depende do "marker" pós-render para reiniciar polling corretamente
- Se isso falhar, você vê exatamente o caso "badge existe, mas thread parece vazio/atrasado"
- **Validação:** Verificar logs do console após carregar conversa: `[FIX] initializeConversationMarkers - lastTimestamp: ...` (deve ser timestamp da última mensagem, não `null` ou timestamp atual)

**Passo C — Forçar atualização do "topo + badge" da conversa ativa**
- A lista é atualizada via `updateConversationListOnly()` e ordenada por `last_activity`; se o backend retornar desatualizado ou se o frontend não refletir a conversa ativa corretamente, ela não sobe
- **Validação:** Verificar logs do console: `[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=...` (deve ser a conversa que recebeu mensagem mais recente)

**Esses 3 passos são justamente a "combinação recomendada" do Raio-X (1: markers → 2: lista/topo → 3: validar query SQL).**

---

### Checklist Rápido de Validação Pós-Fix

**Use exatamente os 2 casos (4699 e 4223) e rode:**

**(1) Conversa fechada: mensagem chega → badge aparece → sobe ao topo → clicar mostra mensagem**
- Enviar mensagem do Charles (4699) ou ServPro (4223) para Pixel12 Digital
- Aguardar 5 segundos (polling da lista)
- ✅ Badge aparece na lista (`unread_count > 0`)
- ✅ Conversa sobe ao topo (`last_activity` é o mais recente)
- ✅ Clicar na conversa mostra mensagem no thread
- ✅ Badge desaparece após abrir conversa

**(2) Conversa aberta: mensagem chega → `/messages/check` dá `has_new=true` → `/messages/new` retorna `messages_count>=1` → DOM atualiza sem refresh**
- Abrir conversa do Charles (4699) ou ServPro (4223)
- Enviar mensagem para Pixel12 Digital
- Aguardar 12 segundos (polling do thread)
- ✅ Console mostra: `[LOG TEMPORARIO] checkForNewConversationMessages() - RESULTADO: success=true, has_new=true`
- ✅ Console mostra: `[LOG TEMPORARIO] checkForNewConversationMessages() - FETCH RESULTADO: messages_count=1`
- ✅ Mensagem aparece no thread automaticamente (sem reload)
- ✅ `ConversationState.lastTimestamp` é atualizado

**(3) Conversa ativa diferente: manter thread do Charles aberta, receber msg no ServPro → ServPro sobe com badge sem derrubar thread do Charles**
- Abrir conversa do Charles (whatsapp_35)
- Enviar mensagem do ServPro (4223) para Pixel12 Digital
- Aguardar 5 segundos (polling da lista)
- ✅ Badge aparece na lista do ServPro
- ✅ ServPro sobe ao topo (acima do Charles)
- ✅ Charles continua aberto no thread (não fecha)
- ✅ `updateConversationListOnly()` preserva conversa ativa (Charles)

---

**Problemas Críticos Identificados:**
1. ✅ `checkNewMessages()` retorna `has_new=false` incorretamente → **CORRIGIDO (commit 15e9476)**
2. ⚠️ `lastTimestamp` pode estar `null` ou desatualizado quando conversa é carregada → **PENDENTE (Passo B)**
3. ⚠️ `unread_count` pode não estar sendo refletido no frontend quando conversa está ativa → **PENDENTE (Passo C)**
4. ⚠️ `last_activity` da conversa ativa pode não estar sendo atualizado na lista → **PENDENTE (Passo C)**

**Próximos Passos:**
1. ✅ Validar Passo A (confirmar que `has_new=true` funciona)
2. Implementar Passo B (corrigir `initializeConversationMarkers()`)
3. Implementar Passo C (forçar atualização de `last_activity` na lista)
4. Executar checklist de validação (1, 2, 3 acima)
5. Remover logs temporários após validação

---

**Documento gerado em:** 2026-01-14  
**Versão:** 1.0  
**Autor:** Análise Técnica Automatizada

