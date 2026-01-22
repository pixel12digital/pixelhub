# Auditoria de Regressão - Unificação Lista + Thread WhatsApp

**Data da Auditoria:** 2026-01-15  
**Objetivo:** Diagnosticar regressões após unificação das telas (lista + thread na mesma página)  
**Escopo:** Apenas diagnóstico. Nenhuma correção será implementada nesta etapa.

---

## 📌 Resumo Executivo

### Problemas Identificados

1. **Ordenação incorreta da lista** - Conversa com mensagem mais recente não sobe para o topo
   - **Causa provável:** `updateConversationListOnly()` está vazia, então quando há conversa ativa, a lista não é atualizada
   - **Impacto:** Usuário não vê conversas mais recentes no topo

2. **Badge aparece mas mensagem não renderiza no thread** - Contador verde aparece, mas ao abrir conversa, mensagem não aparece
   - **Causa provável:** Desincronização entre `conversations.unread_count` (badge) e `communication_events` (thread)
   - **Impacto:** Usuário vê badge mas não encontra a mensagem

### Onde Está Quebrado

- **Frontend:** `updateConversationListOnly()` não atualiza DOM quando há conversa ativa
- **Backend:** Query do thread busca TODOS os eventos e filtra em PHP (pode perder mensagens)
- **Sincronização:** Polling da lista e do thread são independentes, podem detectar atualizações em momentos diferentes

---

## 📊 Matriz de Hipóteses com Probabilidade e Evidências

### Hipótese #1: `updateConversationListOnly()` Está Vazia (95%)

**Probabilidade:** Muito Alta (95%)  
**Impacto:** Crítico

**Evidência #1 - Código Fonte:**
```javascript
// views/communication_hub/index.php, linhas 1004-1016
async function updateConversationListOnly() {
    try {
        // Por enquanto, apenas loga que detectou atualização mas não recarrega
        // A lista será atualizada no próximo reload natural (quando usuário fechar conversa)
        // Ou podemos implementar atualização via AJAX completa no futuro
        console.log('[Hub] Lista atualizada (sem reload para preservar conversa ativa)');
        
        // Atualiza contadores visuais se necessário (badges de não lidas, etc)
        // Por enquanto, apenas mantém estado atual
    } catch (error) {
        console.error('[Hub] Erro ao atualizar lista:', error);
    }
}
```

**Evidência #2 - Chamada da Função:**
```javascript
// views/communication_hub/index.php, linhas 870-900
if (result.success && result.has_updates) {
    if (ConversationState.currentThreadId) {
        console.log('[Hub] Conversa ativa detectada, atualizando apenas lista (sem reload)');
        updateConversationListOnly();  // ← Chama função vazia
    } else {
        location.reload();  // ← Só recarrega se não há conversa ativa
    }
}
```

**Consequências:**
- Lista não reordena quando há conversa ativa
- Badge não atualiza
- Preview da última mensagem não atualiza
- Usuário precisa fechar conversa para ver atualizações

**Validação:**
- ✅ Confirmado: Função existe mas apenas loga
- ✅ Confirmado: Função é chamada quando há conversa ativa
- ❌ Não implementado: Atualização AJAX da lista

---

### Hipótese #2: Query do Thread Busca Todos os Eventos (85%)

**Probabilidade:** Alta (85%)  
**Impacto:** Médio

**Evidência #1 - Código Fonte:**
```php
// src/Controllers/CommunicationHubController.php, linhas 919-932
// Busca TODOS os eventos WhatsApp (tenant_id pode ser NULL)
// Filtra em PHP para garantir que pega todas as variações do contato
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$allEvents = $stmt->fetchAll();  // ← Busca TODOS os eventos

// Filtra eventos desta conversa pelo contact_external_id (normalizado)
$messages = [];
foreach ($allEvents as $event) {  // ← Filtra em PHP
    // ... lógica de filtro ...
}
```

**Problemas:**
1. **Performance:** Busca TODOS os eventos WhatsApp do sistema, não apenas da conversa
2. **Escalabilidade:** Com muitos eventos, pode ser lento e consumir muita memória
3. **Filtro em PHP:** Normalização pode falhar e perder mensagens
4. **Sem índice:** Não usa índice em `contact_external_id` ou `tenant_id`

**Evidência #2 - Query Incremental (Polling):**
```php
// src/Controllers/CommunicationHubController.php, linhas 1637-1649
// Busca eventos incrementais (limitado para não sobrecarregar)
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id
    FROM communication_events ce
    {$whereClause}  // ← Filtro incremental por timestamp
    ORDER BY ce.created_at ASC, ce.event_id ASC
    LIMIT 100
");
```

**Problema:** Mesmo na query incremental, busca até 100 eventos e filtra em PHP. Se houver muitas mensagens de outras conversas, pode perder mensagens da conversa atual.

**Consequências:**
- Mensagens podem não aparecer se filtro em PHP falhar
- Performance degrada com muitos eventos
- Race condition: mensagem pode existir mas não ser encontrada pelo filtro

**Validação:**
- ✅ Confirmado: Query busca todos os eventos
- ✅ Confirmado: Filtro é feito em PHP
- ❌ Não otimizado: Não usa índice ou filtro SQL por contato

---

### Hipótese #3: `ConversationService::resolveConversation()` É Chamado, Mas Pode Falhar Silenciosamente (70%)

**Probabilidade:** Média (70%)  
**Impacto:** Crítico

**Evidência #1 - Código Fonte (CHAMA resolveConversation):**
```php
// src/Services/EventIngestionService.php, linhas 161-203
// Etapa 1: Resolve conversa (incremental, não quebra se falhar)
error_log(sprintf(
    '[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=%s, event_type=%s, tenant_id=%s',
    $eventId,
    $eventType,
    $tenantId ?: 'NULL'
));

try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation([
        'event_type' => $eventType,
        'source_system' => $sourceSystem,
        'tenant_id' => $tenantId,
        'payload' => $payload,
        'metadata' => !empty($eventData['metadata']) ? $eventData['metadata'] : null,
    ]);
    
    if ($conversation) {
        error_log(sprintf(
            '[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=%d, conversation_key=%s',
            $conversation['id'],
            $conversation['conversation_key'] ?? 'NULL'
        ));
    } else {
        error_log(sprintf(
            '[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU NULL para event_id=%s',
            $eventId
        ));
    }
} catch (\Exception $e) {
    // Não quebra fluxo se resolver conversa falhar
    error_log("[EventIngestion] Erro ao resolver conversa (não crítico): " . $e->getMessage());
}
```

**Evidência #2 - Possíveis Falhas Silenciosas:**
```php
// src/Services/ConversationService.php, linhas 44-47
if (!$eventType || !self::isMessageEvent($eventType)) {
    error_log('[DIAGNOSTICO] ConversationService::resolveConversation() - EARLY RETURN: não é evento de mensagem');
    return null;  // ← Retorna NULL silenciosamente
}
```

```php
// src/Services/ConversationService.php, linhas 52-61
$channelInfo = self::extractChannelInfo($eventData);
if (!$channelInfo) {
    error_log('[CONVERSATION UPSERT] ERRO: extractChannelInfo retornou NULL. Event data: ' . json_encode([...]));
    return null;  // ← Retorna NULL se não conseguir extrair channel info
}
```

**Consequências:**
- Se `resolveConversation()` retornar `NULL`, `last_message_at` não é atualizado
- Se `extractChannelInfo()` falhar, conversa não é atualizada
- Erros são logados mas não quebram o fluxo (por design)
- Badge pode aparecer (se `unread_count` foi atualizado antes) mas mensagem não aparece no thread

**Validação:**
- ✅ Confirmado: `resolveConversation()` É CHAMADO em `EventIngestionService::ingest()`
- ✅ Confirmado: Pode retornar `NULL` silenciosamente
- ✅ Confirmado: Exceções são capturadas e logadas, mas não quebram fluxo
- ❓ Necessário verificar logs: Se `resolveConversation()` está retornando `NULL` para mensagens inbound

---

### Hipótese #4: Race Condition Entre Badge e Thread (60%)

**Probabilidade:** Média (60%)  
**Impacto:** Médio

**Evidência #1 - Fonte do Badge:**
```php
// src/Controllers/CommunicationHubController.php, linhas 660-661
'unread_count' => (int) $conv['unread_count']  // ← Campo da tabela conversations
```

**Evidência #2 - Fonte do Thread:**
```php
// src/Controllers/CommunicationHubController.php, linhas 919-932
// Busca de communication_events
SELECT ce.event_id, ce.event_type, ce.created_at, ce.payload, ce.metadata, ce.tenant_id
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
```

**Evidência #3 - Atualização de Badge:**
```php
// src/Services/ConversationService.php, linhas 573-576
unread_count = CASE 
    WHEN ? = 'inbound' THEN unread_count + 1 
    ELSE unread_count 
END
```

**Cenário de Race:**
1. Mensagem inbound chega via webhook
2. `EventIngestionService::ingest()` insere em `communication_events` (status: `queued`)
3. `ConversationService::resolveConversation()` atualiza `conversations.unread_count` e `last_message_at`
4. Polling da lista detecta atualização em `conversations.updated_at`
5. Badge aparece na lista
6. Usuário abre thread
7. Thread busca mensagens de `communication_events`
8. **Se a query do thread não encontrar a mensagem** (por filtro, normalização, ou timing), mensagem não aparece

**Consequências:**
- Badge mostra contador, mas mensagem não aparece no thread
- Usuário fica confuso (vê badge mas não encontra mensagem)
- Pode ser temporário (mensagem aparece no próximo polling)

**Validação:**
- ✅ Confirmado: Badge vem de `conversations.unread_count`
- ✅ Confirmado: Thread vem de `communication_events`
- ✅ Confirmado: Atualizações são feitas em tabelas diferentes
- ❓ Necessário verificar: Se há janela onde badge existe mas mensagem não está disponível

---

## 🔄 Fluxo Real Executado Hoje

### Fluxo A: Inbound (Mensagem Recebida)

```
1. WhatsAppWebhookController::handle()
   ├─ Recebe payload do gateway
   ├─ Mapeia evento (ex: 'message' → 'whatsapp.inbound.message')
   ├─ Resolve tenant_id pelo channel_id
   └─ Chama EventIngestionService::ingest()

2. EventIngestionService::ingest()
   ├─ Valida campos obrigatórios
   ├─ Gera event_id (UUID)
   ├─ Calcula idempotency_key
   ├─ Verifica duplicação
   ├─ Insere em communication_events (status: 'queued')
   └─ Chama ConversationService::resolveConversation()  ← PONTO DE ATUALIZAÇÃO

3. ConversationService::resolveConversation()
   ├─ Verifica se é evento de mensagem (early return se não for)
   ├─ Extrai channelInfo (pode retornar NULL)
   ├─ Gera conversation_key
   ├─ Busca conversa existente (findByKey)
   ├─ Se encontrou: updateConversationMetadata()
   │  ├─ Atualiza last_message_at (do timestamp da mensagem)
   │  ├─ Incrementa unread_count (se inbound)
   │  ├─ Incrementa message_count
   │  └─ Atualiza updated_at (NOW())
   └─ Se não encontrou: createConversation()

4. EventRouterService::route() (NÃO é chamado automaticamente)
   └─ Apenas roteia eventos para canais (não atualiza conversa)
```

**Ponto Crítico:** `resolveConversation()` é chamado, mas pode retornar `NULL` silenciosamente se:
- Evento não é de mensagem
- `extractChannelInfo()` retorna `NULL`
- Exceção é capturada

### Fluxo B: Polling da Lista

```
1. startListPolling() (a cada 12 segundos)
   ├─ Verifica se página está visível
   ├─ Verifica se usuário não está interagindo (última interação > 5s)
   └─ Chama checkForListUpdates()

2. checkForListUpdates()
   ├─ GET /communication-hub/check-updates?after_timestamp=...
   ├─ Backend verifica conversations.updated_at ou last_message_at > timestamp
   └─ Retorna { has_updates: bool, latest_update_ts: string }

3. Se has_updates = true:
   ├─ Se ConversationState.currentThreadId existe:
   │  └─ Chama updateConversationListOnly()  ← FUNÇÃO VAZIA
   └─ Se não há thread ativo:
      └─ location.reload()  ← Recarrega página completa
```

**Ponto Crítico:** Quando há conversa ativa, `updateConversationListOnly()` é chamada mas não faz nada. Lista não é atualizada.

### Fluxo C: Polling do Thread

```
1. startConversationPolling() (a cada 12 segundos)
   ├─ Verifica se página está visível
   ├─ Verifica se há thread ativo
   └─ Chama checkForNewConversationMessages()

2. checkForNewConversationMessages()
   ├─ GET /communication-hub/messages/check?thread_id=...&after_timestamp=...
   ├─ Backend busca eventos após timestamp
   ├─ Filtra em PHP por contact_external_id
   └─ Retorna { has_new: bool }

3. Se has_new = true:
   ├─ GET /communication-hub/messages/new?thread_id=...&after_timestamp=...
   ├─ Backend busca eventos incrementais (LIMIT 100)
   ├─ Filtra em PHP por contact_external_id
   └─ Retorna { messages: [...] }

4. onNewMessagesFromPanel(messages)
   ├─ Filtra mensagens já existentes (por event_id)
   ├─ Adiciona novas mensagens ao DOM
   └─ Atualiza marcadores (lastTimestamp, lastEventId)
```

**Ponto Crítico:** Query busca até 100 eventos e filtra em PHP. Se houver muitas mensagens de outras conversas, pode perder mensagens da conversa atual.

---

## 🔍 Provas: Logs, Trechos de Código, Endpoints e Queries

### Prova #1: `updateConversationListOnly()` Está Vazia

**Arquivo:** `views/communication_hub/index.php`  
**Linhas:** 1004-1016

```javascript
async function updateConversationListOnly() {
    try {
        // Por enquanto, apenas loga que detectou atualização mas não recarrega
        // A lista será atualizada no próximo reload natural (quando usuário fechar conversa)
        // Ou podemos implementar atualização via AJAX completa no futuro
        console.log('[Hub] Lista atualizada (sem reload para preservar conversa ativa)');
        
        // Atualiza contadores visuais se necessário (badges de não lidas, etc)
        // Por enquanto, apenas mantém estado atual
    } catch (error) {
        console.error('[Hub] Erro ao atualizar lista:', error);
    }
}
```

**Evidência:** Função existe mas apenas loga. Não atualiza DOM, não reordena lista, não atualiza badges.

### Prova #2: `resolveConversation()` É Chamado em `ingest()`

**Arquivo:** `src/Services/EventIngestionService.php`  
**Linhas:** 161-203

```php
// Etapa 1: Resolve conversa (incremental, não quebra se falhar)
error_log(sprintf(
    '[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=%s, event_type=%s, tenant_id=%s',
    $eventId,
    $eventType,
    $tenantId ?: 'NULL'
));

try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation([
        'event_type' => $eventType,
        'source_system' => $sourceSystem,
        'tenant_id' => $tenantId,
        'payload' => $payload,
        'metadata' => !empty($eventData['metadata']) ? $eventData['metadata'] : null,
    ]);
    
    if ($conversation) {
        error_log(sprintf(
            '[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=%d, conversation_key=%s',
            $conversation['id'],
            $conversation['conversation_key'] ?? 'NULL'
        ));
    } else {
        error_log(sprintf(
            '[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU NULL para event_id=%s',
            $eventId
        ));
    }
} catch (\Exception $e) {
    // Não quebra fluxo se resolver conversa falhar
    error_log("[EventIngestion] Erro ao resolver conversa (não crítico): " . $e->getMessage());
}
```

**Evidência:** `resolveConversation()` É CHAMADO, mas pode retornar `NULL` ou lançar exceção silenciosamente.

### Prova #3: Query do Thread Busca Todos os Eventos

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Linhas:** 919-932

```php
// Busca TODOS os eventos WhatsApp (tenant_id pode ser NULL)
// Filtra em PHP para garantir que pega todas as variações do contato
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$allEvents = $stmt->fetchAll();  // ← Busca TODOS os eventos do sistema

// Filtra eventos desta conversa pelo contact_external_id (normalizado)
$messages = [];
foreach ($allEvents as $event) {
    $payload = json_decode($event['payload'], true);
    $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
    $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
    
    // Normaliza para comparar
    $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
    $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
    
    // Verifica se é desta conversa (inbound ou outbound)
    $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContactExternalId;
    $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContactExternalId;
    
    if (!$isFromThisContact && !$isToThisContact) {
        continue;  // ← Filtra em PHP
    }
    // ...
}
```

**Evidência:** Query busca TODOS os eventos WhatsApp do sistema e filtra em PHP. Não usa índice ou filtro SQL.

### Prova #4: Endpoint de Check de Atualizações

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `checkUpdates()`  
**Linhas:** 1242-1304

```php
public function checkUpdates(): void
{
    // ...
    $stmt = $db->prepare("
        SELECT MAX(GREATEST(COALESCE(c.updated_at, '1970-01-01'), COALESCE(c.last_message_at, '1970-01-01'))) as latest_update_ts
        FROM conversations c
        {$whereClause}
        LIMIT 1
    ");
    // ...
}
```

**Evidência:** Endpoint verifica `conversations.updated_at` ou `last_message_at`. Se `resolveConversation()` não atualizar, não detecta atualização.

### Prova #5: Endpoint de Check de Novas Mensagens

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `checkNewMessages()`  
**Linhas:** 1315-1404

```php
public function checkNewMessages(): void
{
    // ...
    // Query leve: verifica existência sem carregar payload completo
    $stmt = $db->prepare("
        SELECT ce.event_id, ce.payload
        FROM communication_events ce
        {$whereClause}
        ORDER BY ce.created_at ASC, ce.event_id ASC
        LIMIT 20
    ");
    // ...
    // Filtra rapidamente para verificar se há mensagens desta conversa
    foreach ($events as $event) {
        $payload = json_decode($event['payload'], true);
        // ... filtra em PHP ...
    }
}
```

**Evidência:** Endpoint busca até 20 eventos e filtra em PHP. Se houver muitas mensagens de outras conversas, pode não encontrar mensagem da conversa atual.

---

## ✅ Respostas às Perguntas Objetivas

### A) Inbound Atualiza Conversa (Metadados) Sempre?

**Resposta:** NÃO. Depende de `ConversationService::resolveConversation()` retornar uma conversa.

**Ponto do Fluxo:**
- **Webhook:** `WhatsAppWebhookController::handle()` → `EventIngestionService::ingest()`
- **Ingestão:** `EventIngestionService::ingest()` → `ConversationService::resolveConversation()` (linha 171)
- **Roteamento:** `EventRouterService::route()` NÃO é chamado automaticamente (não atualiza conversa)

**Chamada Garantida:**
- ✅ SIM, `resolveConversation()` é chamado em `EventIngestionService::ingest()` (linha 171)
- ❌ NÃO é garantido que retorne conversa (pode retornar `NULL`)

**Onde Deveria Estar:**
- Já está no lugar certo: `EventIngestionService::ingest()` (linha 171)
- Problema: Pode retornar `NULL` silenciosamente se:
  - Evento não é de mensagem
  - `extractChannelInfo()` retorna `NULL`
  - Exceção é capturada

**Evidência:**
```php
// src/Services/EventIngestionService.php, linha 171
$conversation = \PixelHub\Services\ConversationService::resolveConversation([...]);

// src/Services/ConversationService.php, linha 44
if (!$eventType || !self::isMessageEvent($eventType)) {
    return null;  // ← Early return
}

// src/Services/ConversationService.php, linha 54
if (!$channelInfo) {
    return null;  // ← Early return
}
```

---

### B) Por Que a Lista Não Reordena Quando Há Thread Aberto?

**Resposta:** Porque `updateConversationListOnly()` está vazia e não atualiza o DOM.

**Confirmação:**
- ✅ `updateConversationListOnly()` existe (linha 1004)
- ✅ Está vazia/placeholder (apenas loga, não atualiza DOM)

**Trecho que Decide:**
```javascript
// views/communication_hub/index.php, linhas 870-900
if (result.success && result.has_updates) {
    if (ConversationState.currentThreadId) {
        console.log('[Hub] Conversa ativa detectada, atualizando apenas lista (sem reload)');
        updateConversationListOnly();  // ← Chama função vazia
    } else {
        location.reload();  // ← Só recarrega se não há conversa ativa
    }
}
```

**Impacto:**
1. **Reorder:** Lista não reordena porque DOM não é atualizado
2. **Preview:** Preview da última mensagem não atualiza
3. **Unread Count:** Badge não atualiza (contador verde)

**Evidência:**
```javascript
// views/communication_hub/index.php, linhas 1004-1016
async function updateConversationListOnly() {
    try {
        // Por enquanto, apenas loga que detectou atualização mas não recarrega
        console.log('[Hub] Lista atualizada (sem reload para preservar conversa ativa)');
        // Por enquanto, apenas mantém estado atual
    } catch (error) {
        console.error('[Hub] Erro ao atualizar lista:', error);
    }
}
```

---

### C) Badge vs Thread: Por Que o Contador Aparece, Mas a Mensagem Não?

**Resposta:** Desincronização entre `conversations.unread_count` (badge) e `communication_events` (thread).

**Fonte do Badge:**
```php
// src/Controllers/CommunicationHubController.php, linha 660
'unread_count' => (int) $conv['unread_count']  // ← Campo da tabela conversations
```

**Fonte do Thread:**
```php
// src/Controllers/CommunicationHubController.php, linhas 919-932
SELECT ce.event_id, ce.event_type, ce.created_at, ce.payload, ce.metadata, ce.tenant_id
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
ORDER BY ce.created_at ASC
// Depois filtra em PHP por contact_external_id
```

**Janela/Race Possível:**
1. `EventIngestionService::ingest()` insere em `communication_events` (status: `queued`)
2. `ConversationService::resolveConversation()` atualiza `conversations.unread_count` e `last_message_at`
3. Polling da lista detecta atualização em `conversations.updated_at`
4. Badge aparece na lista
5. Usuário abre thread
6. Thread busca mensagens de `communication_events`
7. **Se a query não encontrar a mensagem** (por filtro, normalização, ou timing), mensagem não aparece

**Verificação do Thread:**
- ✅ Faz "check leve" (`checkNewMessages()`) e depois "fetch new" (`getNewMessages()`)
- ✅ Usa `after_timestamp`/`lastTimestamp` corretamente
- ❌ Pode estar filtrando fora por:
  - Normalização de contato (remove `@c.us`, `@lid`, etc)
  - Comparação de `tenant_id` (se ambos tiverem, deve bater)
  - Limite de 100 eventos (se houver muitas mensagens de outras conversas)

**Exemplo Real (Query para Verificar):**
```sql
-- Buscar evento que não apareceu no thread
SELECT 
    ce.event_id,
    ce.event_type,
    ce.created_at,
    ce.tenant_id,
    JSON_EXTRACT(ce.payload, '$.from') as from_raw,
    JSON_EXTRACT(ce.payload, '$.message.from') as from_message,
    JSON_EXTRACT(ce.payload, '$.to') as to_raw,
    JSON_EXTRACT(ce.payload, '$.message.to') as to_message
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
  AND ce.created_at > '2026-01-15 11:00:00'  -- Ajustar timestamp
ORDER BY ce.created_at DESC
LIMIT 10;

-- Verificar conversa correspondente
SELECT 
    c.id,
    c.contact_external_id,
    c.tenant_id,
    c.unread_count,
    c.last_message_at,
    c.updated_at
FROM conversations c
WHERE c.contact_external_id LIKE '%4223%'  -- ServPro
   OR c.contact_external_id LIKE '%4699%'  -- Charles
ORDER BY c.last_message_at DESC;
```

**Motivo do Filtro Excluir:**
- Normalização pode falhar se formato do telefone for diferente
- Comparação de `tenant_id` pode excluir se não bater
- Limite de 100 eventos pode não incluir mensagem se houver muitas outras

---

## 💡 Recomendações de Correção (Apenas Proposta)

### Correções Backend (Metadados/resolveConversation)

#### 1. Garantir que `resolveConversation()` Sempre Atualize Conversa

**Problema:** `resolveConversation()` pode retornar `NULL` silenciosamente.

**Proposta:**
- Adicionar logs mais detalhados quando retorna `NULL`
- Verificar se `extractChannelInfo()` está falhando
- Considerar fallback quando `extractChannelInfo()` retorna `NULL`

**Arquivo:** `src/Services/ConversationService.php`

#### 2. Otimizar Query do Thread

**Problema:** Query busca TODOS os eventos e filtra em PHP.

**Proposta:**
- Adicionar filtro SQL por `contact_external_id` (usando JSON_EXTRACT ou índice)
- Adicionar filtro SQL por `tenant_id` se disponível
- Usar query incremental desde o início (não buscar todos)

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppMessagesFromConversation()`

**Exemplo de Query Otimizada:**
```sql
SELECT ce.event_id, ce.event_type, ce.created_at, ce.payload, ce.metadata, ce.tenant_id
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
  AND (
    JSON_EXTRACT(ce.payload, '$.from') = ? 
    OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
    OR JSON_EXTRACT(ce.payload, '$.to') = ?
    OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
  )
  AND (ce.tenant_id = ? OR ce.tenant_id IS NULL OR ? IS NULL)
ORDER BY ce.created_at ASC
LIMIT 500
```

### Correções Frontend (updateConversationListOnly/reorder/thread fetch)

#### 1. Implementar `updateConversationListOnly()`

**Problema:** Função está vazia.

**Proposta:**
- Buscar lista atualizada via AJAX (`GET /communication-hub`)
- Atualizar DOM sem recarregar página
- Preservar conversa ativa
- Reordenar lista por `last_message_at`
- Atualizar badges (`unread_count`)

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `updateConversationListOnly()`

**Exemplo de Implementação:**
```javascript
async function updateConversationListOnly() {
    try {
        // Busca lista atualizada
        const url = '<?= pixelhub_url('/communication-hub') ?>?' + 
                   new URLSearchParams({
                       channel: '<?= $filters['channel'] ?? 'all' ?>',
                       tenant_id: '<?= $filters['tenant_id'] ?? '' ?>',
                       status: '<?= $filters['status'] ?? 'active' ?>'
                   });
        const response = await fetch(url);
        const html = await response.text();
        
        // Extrai apenas a lista de conversas do HTML
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newList = doc.querySelector('.conversation-list-scroll');
        
        // Atualiza DOM preservando scroll e conversa ativa
        const currentList = document.querySelector('.conversation-list-scroll');
        if (newList && currentList) {
            const activeThreadId = ConversationState.currentThreadId;
            currentList.innerHTML = newList.innerHTML;
            
            // Restaura conversa ativa
            if (activeThreadId) {
                document.querySelectorAll('.conversation-item').forEach(item => {
                    if (item.dataset.threadId === activeThreadId) {
                        item.classList.add('active');
                    }
                });
            }
        }
    } catch (error) {
        console.error('[Hub] Erro ao atualizar lista:', error);
    }
}
```

#### 2. Melhorar Sincronização Entre Lista e Thread

**Problema:** Polling independente pode detectar atualizações em momentos diferentes.

**Proposta:**
- Quando thread detecta nova mensagem, também atualiza badge na lista
- Quando lista detecta atualização, verifica se thread precisa atualizar
- Compartilhar estado entre lista e thread (usar mesmo `lastUpdateTs`)

**Arquivo:** `views/communication_hub/index.php`

### Mitigações de Race (Se Aplicável)

#### 1. Garantir Atomicidade Entre `communication_events` e `conversations`

**Problema:** Atualizações são feitas em tabelas diferentes, pode haver race condition.

**Proposta:**
- Usar transação para garantir atomicidade
- Ou usar trigger no banco para atualizar `conversations` automaticamente

**Arquivo:** `src/Services/EventIngestionService.php` ou migration

#### 2. Melhorar Filtro do Thread para Não Perder Mensagens

**Problema:** Filtro em PHP pode falhar e perder mensagens.

**Proposta:**
- Mover filtro para SQL (usar JSON_EXTRACT)
- Adicionar índice em `payload` (se possível)
- Considerar usar campo separado `contact_external_id` em `communication_events`

**Arquivo:** `src/Controllers/CommunicationHubController.php`

---

## ✅ Checklist de Validação (Após Fix, Sem Regressão do Recebimento)

### Validação #1: Inbound Atualiza Conversa

- [ ] Enviar mensagem inbound via webhook
- [ ] Verificar logs: `[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation`
- [ ] Verificar logs: `[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=...`
- [ ] Verificar banco: `conversations.last_message_at` foi atualizado
- [ ] Verificar banco: `conversations.unread_count` foi incrementado
- [ ] Verificar banco: `conversations.updated_at` foi atualizado

### Validação #2: Lista Reordena Quando Há Thread Aberto

- [ ] Abrir conversa (thread ativo)
- [ ] Enviar mensagem inbound para outra conversa
- [ ] Aguardar polling (12 segundos)
- [ ] Verificar: Lista reordena sem recarregar página
- [ ] Verificar: Conversa ativa permanece aberta
- [ ] Verificar: Badge atualiza na lista

### Validação #3: Badge e Thread Sincronizados

- [ ] Receber mensagem inbound
- [ ] Verificar: Badge aparece na lista
- [ ] Abrir thread imediatamente
- [ ] Verificar: Mensagem aparece no thread
- [ ] Verificar: Badge some após abrir thread
- [ ] Verificar: `conversations.unread_count` foi zerado

### Validação #4: Query do Thread Não Perde Mensagens

- [ ] Criar múltiplas conversas com mensagens
- [ ] Abrir thread de uma conversa específica
- [ ] Verificar: Apenas mensagens dessa conversa aparecem
- [ ] Verificar: Todas as mensagens aparecem (não perde nenhuma)
- [ ] Verificar: Performance aceitável (query rápida)

### Validação #5: Recebimento Não Regrediu

- [ ] Enviar mensagem inbound via webhook
- [ ] Verificar: Webhook retorna 200 OK
- [ ] Verificar: Evento é inserido em `communication_events`
- [ ] Verificar: Conversa é atualizada (ou criada)
- [ ] Verificar: Nenhum erro nos logs
- [ ] Verificar: Mensagem aparece no thread

### Validação #6: Performance

- [ ] Testar com 100+ conversas
- [ ] Testar com 1000+ mensagens
- [ ] Verificar: Query do thread é rápida (< 1s)
- [ ] Verificar: Polling não sobrecarrega servidor
- [ ] Verificar: Frontend não trava

---

## 📝 Conclusão

### Problemas Confirmados

1. ✅ **`updateConversationListOnly()` está vazia** - Evidência: código fonte (linhas 1004-1016)
2. ✅ **Query do thread busca todos os eventos** - Evidência: código fonte (linhas 919-932)
3. ✅ **`resolveConversation()` é chamado, mas pode falhar silenciosamente** - Evidência: código fonte (linhas 171-203)

### Problemas Não Confirmados (Necessário Verificar Logs)

1. ❓ **`resolveConversation()` está retornando `NULL` para mensagens inbound?**
   - Verificar logs: `[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU NULL`
   - Verificar se `extractChannelInfo()` está falhando

2. ❓ **Há race condition entre badge e thread?**
   - Verificar se mensagem existe em `communication_events` quando badge aparece
   - Verificar se filtro do thread está excluindo mensagem

### Próximos Passos

1. **Verificar logs de produção** para confirmar se `resolveConversation()` está retornando `NULL`
2. **Executar queries SQL** para verificar se há mensagens que não aparecem no thread
3. **Implementar correções** baseadas nas recomendações acima
4. **Validar** usando checklist acima

---

**Fim da Auditoria de Regressão**

