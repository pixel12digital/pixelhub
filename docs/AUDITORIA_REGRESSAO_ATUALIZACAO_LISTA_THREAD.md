# Auditoria de Regressão - Atualização de Lista e Thread

**Data da Auditoria:** 2026-01-15  
**Objetivo:** Diagnosticar problemas específicos de ordenação da lista e renderização de mensagens no thread  
**Escopo:** Apenas diagnóstico. Nenhuma correção será implementada nesta etapa.

---

## 📌 Resumo Executivo

### Problema A: Ordenação da Lista Não Reflete Conversa Mais Recente

**Cenário:** Contato Charles (final 4699) enviou mensagem às 11:10, mas conversa não subiu para o topo da lista.

**Causa Raiz Identificada (95% de probabilidade):**
- `updateConversationListOnly()` está vazia e é chamada quando há conversa ativa
- Lista não é atualizada no DOM, resultando em ordenação incorreta

### Problema B: Badge Aparece Mas Mensagem Não Renderiza no Thread

**Cenário:** Conversa ServPro (final 4223) mostra badge de mensagem recebida, mas mensagem não aparece no thread.

**Causa Raiz Identificada (80% de probabilidade):**
- Badge vem de `conversations.unread_count` (atualizado por `resolveConversation()`)
- Thread busca de `communication_events` com filtro em PHP que pode falhar
- Possível race condition ou filtro que exclui mensagem

---

## 🔍 Problema A: Ordenação da Lista Não Atualiza

### Reprodução Lógica do Problema

**Fluxo Esperado:**
1. Mensagem inbound chega às 11:10 (Charles, final 4699)
2. `EventIngestionService::ingest()` insere evento em `communication_events`
3. `ConversationService::resolveConversation()` atualiza `conversations.last_message_at` e `conversations.updated_at`
4. Polling da lista detecta atualização via `checkUpdates()` (verifica `updated_at` ou `last_message_at`)
5. Frontend recebe `has_updates: true`
6. **Se há conversa ativa:** Chama `updateConversationListOnly()` → **FUNÇÃO VAZIA**
7. **Se não há conversa ativa:** `location.reload()` → Lista reordena

**Fluxo Real (Com Problema):**
1. ✅ Mensagem chega e é inserida em `communication_events`
2. ✅ `resolveConversation()` atualiza `conversations.last_message_at` e `updated_at`
3. ✅ Polling detecta atualização (`has_updates: true`)
4. ❌ **Como há conversa ativa, chama `updateConversationListOnly()` que não faz nada**
5. ❌ Lista não reordena no DOM
6. ❌ Usuário não vê conversa mais recente no topo

### Evidências do Código

#### Evidência #1: `updateConversationListOnly()` Está Vazia

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

**Prova:** Função existe mas apenas loga. Não atualiza DOM, não reordena lista, não atualiza badges.

#### Evidência #2: Função É Chamada Quando Há Conversa Ativa

**Arquivo:** `views/communication_hub/index.php`  
**Linhas:** 870-899

```javascript
if (result.success && result.has_updates) {
    console.log('[Hub] ✅ Atualizações detectadas!', {
        after_timestamp: HubState.lastUpdateTs,
        latest_update_ts: result.latest_update_ts
    });
    
    // CRÍTICO: NUNCA recarrega a página se houver conversa ativa
    // Atualiza apenas a lista via AJAX para preservar estado
    if (ConversationState.currentThreadId) {
        console.log('[Hub] Conversa ativa detectada, atualizando apenas lista (sem reload)');
        updateConversationListOnly();  // ← CHAMA FUNÇÃO VAZIA
    } else {
        // Só recarrega se não há conversa ativa
        if (!ConversationState.currentThreadId) {
            location.reload();  // ← Só executa se não há conversa ativa
        }
    }
}
```

**Prova:** Quando há conversa ativa (`ConversationState.currentThreadId` existe), chama `updateConversationListOnly()` ao invés de `location.reload()`.

#### Evidência #3: Endpoint `checkUpdates()` Verifica Corretamente

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `checkUpdates()`  
**Linhas:** 1277-1282

```php
$stmt = $db->prepare("
    SELECT MAX(GREATEST(COALESCE(c.updated_at, '1970-01-01'), COALESCE(c.last_message_at, '1970-01-01'))) as latest_update_ts
    FROM conversations c
    {$whereClause}
    LIMIT 1
");
```

**Prova:** Endpoint verifica `updated_at` OU `last_message_at`. Se `resolveConversation()` atualizar qualquer um, detecta atualização.

#### Evidência #4: `updateConversationMetadata()` Atualiza Campos Corretamente

**Arquivo:** `src/Services/ConversationService.php`  
**Método:** `updateConversationMetadata()`  
**Linhas:** 568-583

```php
$stmt = $db->prepare("
    UPDATE conversations 
    SET last_message_at = ?,           // ← Timestamp da mensagem
        last_message_direction = ?,
        message_count = message_count + 1,
        unread_count = CASE 
            WHEN ? = 'inbound' THEN unread_count + 1 
            ELSE unread_count 
        END,
        status = CASE 
            WHEN status = 'closed' THEN 'open'
            ELSE status
        END,
        updated_at = ?                  // ← Sempre NOW()
    WHERE id = ?
");
```

**Prova:** `last_message_at` e `updated_at` são atualizados quando `resolveConversation()` é chamado e encontra conversa existente.

### Queries SQL para Validação

#### Query #1: Verificar Se `last_message_at` Foi Atualizado (Charles, final 4699)

```sql
-- Buscar conversa do Charles (final 4699)
SELECT 
    c.id,
    c.contact_external_id,
    c.contact_name,
    c.last_message_at,
    c.updated_at,
    c.unread_count,
    c.message_count,
    TIMESTAMPDIFF(SECOND, c.last_message_at, NOW()) as seconds_since_last_message
FROM conversations c
WHERE c.contact_external_id LIKE '%4699%'
   OR c.contact_name LIKE '%Charles%'
ORDER BY c.last_message_at DESC
LIMIT 5;
```

**O que verificar:**
- `last_message_at` deve ser próximo de 11:10 (data do problema)
- `updated_at` deve ser igual ou posterior a `last_message_at`
- Se ambos estão atualizados, problema é no frontend

#### Query #2: Verificar Eventos de Mensagem (Charles, final 4699)

```sql
-- Buscar eventos de mensagem do Charles
SELECT 
    ce.event_id,
    ce.event_type,
    ce.created_at,
    ce.tenant_id,
    JSON_EXTRACT(ce.payload, '$.from') as from_raw,
    JSON_EXTRACT(ce.payload, '$.message.from') as from_message,
    JSON_EXTRACT(ce.payload, '$.to') as to_raw,
    JSON_EXTRACT(ce.payload, '$.message.to') as to_message,
    JSON_EXTRACT(ce.payload, '$.message.timestamp') as message_timestamp,
    ce.status
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
  AND (
    JSON_EXTRACT(ce.payload, '$.from') LIKE '%4699%'
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%4699%'
    OR JSON_EXTRACT(ce.payload, '$.to') LIKE '%4699%'
    OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE '%4699%'
  )
  AND ce.created_at >= '2026-01-15 11:00:00'  -- Ajustar data
ORDER BY ce.created_at DESC
LIMIT 10;
```

**O que verificar:**
- Evento existe em `communication_events`
- `created_at` corresponde a 11:10
- `status` deve ser `queued` ou `processed`

#### Query #3: Verificar Se `resolveConversation()` Foi Chamado

```sql
-- Buscar logs no PHP error_log (via grep ou análise de logs)
-- Padrão esperado:
-- [DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=...
-- [DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=...
```

**O que verificar:**
- Se log de "CHAMANDO resolveConversation" existe
- Se log de "RETORNOU conversation_id" existe (não NULL)
- Se log de "RETORNOU NULL" existe (indica falha)

### Possíveis Causas com Probabilidade

#### Causa #1: `updateConversationListOnly()` Está Vazia (95%)

**Probabilidade:** Muito Alta (95%)  
**Evidência:** Código fonte confirma que função apenas loga, não atualiza DOM

**Por que:**
- Função foi criada como placeholder durante unificação
- Comentário no código: "Por enquanto, apenas loga... podemos implementar atualização via AJAX completa no futuro"
- Quando há conversa ativa, esta função é chamada ao invés de `location.reload()`

**Impacto:**
- Lista não reordena
- Badge não atualiza
- Preview da última mensagem não atualiza
- Usuário precisa fechar conversa para ver atualizações

#### Causa #2: `last_message_at` Não Está Sendo Atualizado (5%)

**Probabilidade:** Baixa (5%)  
**Evidência:** Código mostra que `updateConversationMetadata()` atualiza corretamente

**Por que (se ocorrer):**
- `resolveConversation()` retorna `NULL` (early return)
- `extractChannelInfo()` retorna `NULL`
- Exceção é capturada silenciosamente

**Como verificar:**
- Executar Query #1 acima
- Se `last_message_at` não está atualizado, problema é no backend
- Se `last_message_at` está atualizado, problema é no frontend (confirmado)

#### Causa #3: Polling Não Detecta Atualização (0%)

**Probabilidade:** Muito Baixa (0%)  
**Evidência:** Endpoint `checkUpdates()` verifica `updated_at` OU `last_message_at`

**Por que (improvável):**
- Se `updated_at` ou `last_message_at` foram atualizados, endpoint detecta
- Query usa `MAX(GREATEST(...))` que pega o maior valor

---

## 🔍 Problema B: Badge Aparece Mas Mensagem Não Renderiza

### Reprodução Lógica do Problema

**Fluxo Esperado:**
1. Mensagem inbound chega (ServPro, final 4223)
2. `EventIngestionService::ingest()` insere em `communication_events` (status: `queued`)
3. `ConversationService::resolveConversation()` atualiza `conversations.unread_count` (+1)
4. Polling da lista detecta atualização
5. Badge aparece na lista (contador verde)
6. Usuário abre thread
7. Thread busca mensagens de `communication_events` filtrado por contato
8. Mensagem aparece no thread
9. Badge some (marcado como lido)

**Fluxo Real (Com Problema):**
1. ✅ Mensagem chega e é inserida em `communication_events`
2. ✅ `resolveConversation()` atualiza `conversations.unread_count` (+1)
3. ✅ Polling detecta atualização
4. ✅ Badge aparece na lista
5. ❌ **Usuário abre thread**
6. ❌ **Thread busca mensagens mas não encontra** (filtro em PHP falha ou limite)
7. ❌ Mensagem não aparece no thread
8. ❌ Badge continua aparecendo (não foi marcado como lido)

### Evidências do Código

#### Evidência #1: Badge Vem de `conversations.unread_count`

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppThreadsFromConversations()`  
**Linha:** 660

```php
'unread_count' => (int) $conv['unread_count']  // ← Campo da tabela conversations
```

**Prova:** Badge é calculado a partir de `conversations.unread_count`, que é atualizado por `resolveConversation()`.

#### Evidência #2: Thread Busca de `communication_events` Com Filtro em PHP

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppMessagesFromConversation()`  
**Linhas:** 919-952

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
    
    // Verifica se tenant_id bate (se ambos tiverem tenant_id definido)
    if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
        continue;  // ← Pode excluir mensagem se tenant_id não bater
    }
    
    // ... adiciona mensagem ...
}
```

**Problemas Identificados:**
1. **Busca TODOS os eventos:** Não filtra por contato na query SQL
2. **Filtro em PHP:** Normalização pode falhar
3. **Comparação de tenant_id:** Pode excluir mensagem se não bater
4. **Sem limite:** Se houver muitos eventos, pode ser lento e consumir muita memória

#### Evidência #3: Query Incremental Também Filtra em PHP

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppMessagesIncremental()`  
**Linhas:** 1637-1672

```php
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
    LIMIT 100  // ← Limite de 100 eventos
");
$stmt->execute($params);
$allEvents = $stmt->fetchAll();

// Filtra eventos desta conversa (mesma lógica do método original)
$messages = [];
foreach ($allEvents as $event) {
    // ... mesmo filtro em PHP ...
}
```

**Problema:** Mesmo na query incremental, busca até 100 eventos e filtra em PHP. Se houver muitas mensagens de outras conversas, pode não incluir mensagem da conversa atual.

#### Evidência #4: `checkNewMessages()` Também Filtra em PHP

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `checkNewMessages()`  
**Linhas:** 1366-1394

```php
// Check leve: busca apenas event_id e payload mínimo (só para filtrar por contato)
// Limite baixo: só precisa verificar se existe pelo menos 1
$stmt = $db->prepare("
    SELECT ce.event_id, ce.payload
    FROM communication_events ce
    {$whereClause}
    ORDER BY ce.created_at ASC, ce.event_id ASC
    LIMIT 20  // ← Limite de 20 eventos
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Filtra rapidamente para verificar se há mensagens desta conversa
$hasNew = false;
foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    // ... filtra em PHP ...
    if ($isFromThisContact || $isToThisContact) {
        $hasNew = true;
        break;
    }
}
```

**Problema:** Limite de 20 eventos. Se houver muitas mensagens de outras conversas, pode não encontrar mensagem da conversa atual.

### Queries SQL para Validação

#### Query #1: Verificar Se Mensagem Existe em `communication_events` (ServPro, final 4223)

```sql
-- Buscar eventos de mensagem do ServPro
SELECT 
    ce.event_id,
    ce.event_type,
    ce.created_at,
    ce.tenant_id,
    ce.status,
    JSON_EXTRACT(ce.payload, '$.from') as from_raw,
    JSON_EXTRACT(ce.payload, '$.message.from') as from_message,
    JSON_EXTRACT(ce.payload, '$.to') as to_raw,
    JSON_EXTRACT(ce.payload, '$.message.to') as to_message,
    JSON_EXTRACT(ce.payload, '$.message.text') as message_text,
    JSON_EXTRACT(ce.payload, '$.message.timestamp') as message_timestamp
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
  AND (
    JSON_EXTRACT(ce.payload, '$.from') LIKE '%4223%'
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%4223%'
    OR JSON_EXTRACT(ce.payload, '$.to') LIKE '%4223%'
    OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE '%4223%'
  )
ORDER BY ce.created_at DESC
LIMIT 10;
```

**O que verificar:**
- Mensagem existe em `communication_events`
- `created_at` corresponde ao momento do problema
- `status` deve ser `queued` ou `processed`
- Formato do telefone no payload (pode ter `@c.us`, `@lid`, etc)

#### Query #2: Verificar Conversa Correspondente (ServPro, final 4223)

```sql
-- Buscar conversa do ServPro
SELECT 
    c.id,
    c.conversation_key,
    c.contact_external_id,
    c.contact_name,
    c.tenant_id,
    c.unread_count,
    c.last_message_at,
    c.updated_at,
    c.message_count
FROM conversations c
WHERE c.contact_external_id LIKE '%4223%'
   OR c.contact_name LIKE '%ServPro%'
ORDER BY c.last_message_at DESC
LIMIT 5;
```

**O que verificar:**
- `unread_count` > 0 (badge aparece)
- `last_message_at` corresponde ao momento do problema
- `contact_external_id` normalizado (sem `@c.us`, `@lid`, etc)
- `tenant_id` corresponde ao evento

#### Query #3: Simular Filtro do Thread (Verificar Se Mensagem Seria Encontrada)

```sql
-- Simular filtro que o thread faz
-- 1. Buscar contact_external_id da conversa
SELECT contact_external_id FROM conversations WHERE id = ?;  -- ID da conversa ServPro

-- 2. Normalizar (remover @c.us, @lid, etc)
-- Exemplo: '554796474223@c.us' → '554796474223'

-- 3. Buscar eventos que correspondem ao contato normalizado
SELECT 
    ce.event_id,
    ce.event_type,
    ce.created_at,
    REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.from'), '@c.us', ''), '@lid', '') as from_normalized,
    REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.message.from'), '@c.us', ''), '@lid', '') as from_message_normalized,
    REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.to'), '@c.us', ''), '@lid', '') as to_normalized,
    REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.message.to'), '@c.us', ''), '@lid', '') as to_message_normalized
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
  AND ce.created_at >= '2026-01-15 00:00:00'  -- Ajustar data
ORDER BY ce.created_at DESC
LIMIT 100;

-- 4. Comparar manualmente se from/to normalizado bate com contact_external_id da conversa
```

**O que verificar:**
- Se telefone normalizado do evento bate com `contact_external_id` da conversa
- Se há diferenças de formato que impedem match
- Se `tenant_id` do evento bate com `tenant_id` da conversa

#### Query #4: Verificar Se Há Muitos Eventos de Outras Conversas (Pode Causar Limite)

```sql
-- Contar eventos WhatsApp no período do problema
SELECT 
    COUNT(*) as total_events,
    COUNT(DISTINCT 
        REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.from'), '@c.us', ''), '@lid', '')
    ) as unique_contacts
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
  AND ce.created_at >= '2026-01-15 00:00:00'  -- Ajustar data
  AND ce.created_at <= '2026-01-15 23:59:59';
```

**O que verificar:**
- Se há muitos eventos (> 100), query incremental pode não incluir mensagem
- Se há muitos contatos diferentes, filtro em PHP pode ser lento

### Possíveis Causas com Probabilidade

#### Causa #1: Filtro em PHP Falha (Normalização ou Comparação) (70%)

**Probabilidade:** Alta (70%)  
**Evidência:** Query busca todos os eventos e filtra em PHP

**Por que:**
- Normalização pode falhar se formato do telefone for diferente
- Comparação de `tenant_id` pode excluir mensagem se não bater
- Limite de 100 eventos pode não incluir mensagem se houver muitas outras

**Cenários Específicos:**
1. **Normalização falha:**
   - Evento tem: `554796474223@c.us`
   - Conversa tem: `554796474223`
   - Normalização remove `@c.us` → Match OK
   - **Mas se evento tem formato diferente, pode falhar**

2. **Comparação de tenant_id exclui:**
   - Evento tem: `tenant_id = 5`
   - Conversa tem: `tenant_id = 5`
   - Match OK
   - **Mas se evento tem `tenant_id = NULL` e conversa tem `tenant_id = 5`, código aceita (fallback)**
   - **Se evento tem `tenant_id = 5` e conversa tem `tenant_id = NULL`, código aceita (atualização)**
   - **Se evento tem `tenant_id = 5` e conversa tem `tenant_id = 6`, código EXCLUI**

3. **Limite de 100 eventos:**
   - Se houver 150 eventos no período, query incremental busca apenas 100
   - Se mensagem está no evento #120, não será incluída

#### Causa #2: Race Condition Entre Badge e Thread (20%)

**Probabilidade:** Média (20%)  
**Evidência:** Badge vem de `conversations`, thread vem de `communication_events`

**Por que:**
1. `EventIngestionService::ingest()` insere em `communication_events` (status: `queued`)
2. `ConversationService::resolveConversation()` atualiza `conversations.unread_count`
3. Polling da lista detecta atualização em `conversations.updated_at`
4. Badge aparece
5. Usuário abre thread imediatamente
6. Thread busca mensagens de `communication_events`
7. **Se evento ainda não está disponível (transação não commitou), mensagem não aparece**

**Como verificar:**
- Verificar se evento existe em `communication_events` quando badge aparece
- Verificar timing entre inserção e atualização

#### Causa #3: `resolveConversation()` Retorna NULL (10%)

**Probabilidade:** Baixa (10%)  
**Evidência:** Código mostra que `resolveConversation()` pode retornar `NULL` silenciosamente

**Por que:**
- `extractChannelInfo()` retorna `NULL` (não consegue extrair informações do canal)
- Evento não é de mensagem (early return)
- Exceção é capturada silenciosamente

**Como verificar:**
- Verificar logs: `[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU NULL`
- Se log existe, `unread_count` não foi atualizado (badge não deveria aparecer)

---

## 💡 Soluções Possíveis

### Correção Mínima (Rápida, Baixo Risco)

#### Solução #1: Implementar `updateConversationListOnly()` Básica

**Arquivo:** `views/communication_hub/index.php`  
**Função:** `updateConversationListOnly()`

**Implementação:**
```javascript
async function updateConversationListOnly() {
    try {
        // Busca lista atualizada via endpoint existente
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
        
        if (newList) {
            const currentList = document.querySelector('.conversation-list-scroll');
            const activeThreadId = ConversationState.currentThreadId;
            const scrollPosition = currentList.scrollTop;
            
            // Atualiza DOM
            currentList.innerHTML = newList.innerHTML;
            
            // Restaura scroll
            currentList.scrollTop = scrollPosition;
            
            // Restaura conversa ativa
            if (activeThreadId) {
                document.querySelectorAll('.conversation-item').forEach(item => {
                    if (item.dataset.threadId === activeThreadId) {
                        item.classList.add('active');
                    }
                });
            }
            
            console.log('[Hub] Lista atualizada (sem reload)');
        }
    } catch (error) {
        console.error('[Hub] Erro ao atualizar lista:', error);
    }
}
```

**Riscos:**
- ⚠️ Baixo risco: Apenas atualiza DOM, não mexe em backend
- ⚠️ Pode causar flicker se HTML for grande
- ✅ Não afeta recebimento/webhook

**Validação:**
- Testar se lista reordena quando há conversa ativa
- Testar se badge atualiza
- Testar se scroll é preservado

#### Solução #2: Adicionar Filtro SQL Básico no Thread

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppMessagesFromConversation()`

**Implementação:**
```php
// Adicionar filtro SQL por contato (melhoria básica)
$normalizedContactForSQL = preg_replace('/@.*$/', '', $contactExternalId);
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
      AND (
        JSON_EXTRACT(ce.payload, '$.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
      )
    ORDER BY ce.created_at ASC
");
$stmt->execute([
    "%{$normalizedContactForSQL}%",
    "%{$normalizedContactForSQL}%",
    "%{$normalizedContactForSQL}%",
    "%{$normalizedContactForSQL}%"
]);
$filteredEvents = $stmt->fetchAll();

// Filtra em PHP apenas para validação final (não precisa buscar todos)
```

**Riscos:**
- ⚠️ Médio risco: Muda query do thread, pode afetar performance
- ⚠️ `JSON_EXTRACT` pode ser lento em tabelas grandes
- ✅ Não afeta recebimento/webhook

**Validação:**
- Testar se mensagens aparecem corretamente
- Testar performance com muitos eventos
- Testar se não perde mensagens

### Correção Robusta (Completa, Médio Risco)

#### Solução #3: Endpoint Dedicado para Atualizar Lista (AJAX)

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getConversationsList()` (novo)

**Implementação:**
```php
public function getConversationsList(): void
{
    Auth::requireInternal();
    header('Content-Type: application/json');
    
    // Reutiliza lógica de getWhatsAppThreadsFromConversations()
    $db = DB::getConnection();
    $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
    $status = $_GET['status'] ?? 'active';
    
    $threads = $this->getWhatsAppThreadsFromConversations($db, $tenantId, $status);
    
    $this->json([
        'success' => true,
        'threads' => $threads
    ]);
}
```

**Frontend:**
```javascript
async function updateConversationListOnly() {
    try {
        const url = '<?= pixelhub_url('/communication-hub/conversations-list') ?>?' + 
                   new URLSearchParams({
                       channel: '<?= $filters['channel'] ?? 'all' ?>',
                       tenant_id: '<?= $filters['tenant_id'] ?? '' ?>',
                       status: '<?= $filters['status'] ?? 'active' ?>'
                   });
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.threads) {
            // Renderiza lista atualizada
            renderConversationList(result.threads);
            
            // Restaura conversa ativa
            if (ConversationState.currentThreadId) {
                document.querySelectorAll('.conversation-item').forEach(item => {
                    if (item.dataset.threadId === ConversationState.currentThreadId) {
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

**Riscos:**
- ⚠️ Médio risco: Novo endpoint, precisa testar
- ✅ Não afeta recebimento/webhook
- ✅ Mais eficiente que buscar HTML completo

#### Solução #4: Otimizar Query do Thread com Índice e Filtro SQL

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppMessagesFromConversation()`

**Implementação:**
```php
// 1. Normalizar contact_external_id
$normalizedContact = preg_replace('/@.*$/', '', $contactExternalId);

// 2. Buscar eventos com filtro SQL otimizado
$where = [
    "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
];
$params = [];

// Filtro por contato (usando LIKE para pegar variações)
$where[] = "(
    JSON_EXTRACT(ce.payload, '$.from') LIKE ?
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
    OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
    OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
)";
$params[] = "%{$normalizedContact}%";
$params[] = "%{$normalizedContact}%";
$params[] = "%{$normalizedContact}%";
$params[] = "%{$normalizedContact}%";

// Filtro por tenant_id (se disponível)
if ($tenantId) {
    $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
    $params[] = $tenantId;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id
    FROM communication_events ce
    {$whereClause}
    ORDER BY ce.created_at ASC
    LIMIT 500
");
$stmt->execute($params);
$filteredEvents = $stmt->fetchAll();

// 3. Validação final em PHP (apenas para garantir)
$messages = [];
foreach ($filteredEvents as $event) {
    // ... validação final ...
}
```

**Riscos:**
- ⚠️ Médio risco: Muda query do thread, pode afetar performance
- ⚠️ `JSON_EXTRACT` com `LIKE` pode ser lento
- ✅ Não afeta recebimento/webhook
- ✅ Reduz quantidade de dados buscados

### Otimização (Melhorias de Performance, Baixo Risco)

#### Solução #5: Adicionar Campo `contact_external_id` em `communication_events`

**Arquivo:** Migration (novo)

**Implementação:**
```sql
ALTER TABLE communication_events 
ADD COLUMN contact_external_id VARCHAR(50) NULL AFTER tenant_id,
ADD INDEX idx_contact_external_id (contact_external_id),
ADD INDEX idx_tenant_contact (tenant_id, contact_external_id);

-- Popular campo existente
UPDATE communication_events ce
SET ce.contact_external_id = REPLACE(REPLACE(
    COALESCE(
        JSON_EXTRACT(ce.payload, '$.from'),
        JSON_EXTRACT(ce.payload, '$.message.from'),
        JSON_EXTRACT(ce.payload, '$.to'),
        JSON_EXTRACT(ce.payload, '$.message.to')
    ),
    '@c.us', ''
), '@lid', '')
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
  AND ce.contact_external_id IS NULL;
```

**Backend:**
```php
// Em EventIngestionService::ingest(), após inserir evento:
// Extrair e normalizar contact_external_id
$contactExternalId = self::extractContactFromPayload($payload, $eventType);
if ($contactExternalId) {
    $normalizedContact = preg_replace('/@.*$/', '', $contactExternalId);
    // Atualizar campo contact_external_id
    $updateStmt = $db->prepare("
        UPDATE communication_events 
        SET contact_external_id = ? 
        WHERE event_id = ?
    ");
    $updateStmt->execute([$normalizedContact, $eventId]);
}
```

**Query Otimizada:**
```php
$stmt = $db->prepare("
    SELECT ce.event_id, ce.event_type, ce.created_at, ce.payload, ce.metadata, ce.tenant_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND ce.contact_external_id = ?
      AND (ce.tenant_id = ? OR ce.tenant_id IS NULL OR ? IS NULL)
    ORDER BY ce.created_at ASC
    LIMIT 500
");
$stmt->execute([$normalizedContact, $tenantId, $tenantId]);
```

**Riscos:**
- ⚠️ Baixo risco: Adiciona campo, não remove nada
- ⚠️ Migration precisa popular campo existente
- ✅ Melhora performance drasticamente
- ✅ Não afeta recebimento/webhook

---

## ⚠️ Riscos de Regressão

### Risco #1: Quebrar Recebimento/Webhook (CRÍTICO)

**Probabilidade:** Baixa (se seguir restrições)  
**Impacto:** Crítico

**Mitigação:**
- ✅ **NÃO alterar:** `WhatsAppWebhookController::handle()`
- ✅ **NÃO alterar:** `EventIngestionService::ingest()` (apenas adicionar campo se Solução #5)
- ✅ **NÃO alterar:** `ConversationService::resolveConversation()` (apenas melhorar logs)
- ✅ **Testar:** Enviar mensagem inbound e verificar se aparece

**Checklist de Validação:**
- [ ] Webhook recebe mensagem e retorna 200 OK
- [ ] Evento é inserido em `communication_events`
- [ ] `resolveConversation()` é chamado
- [ ] Conversa é atualizada ou criada
- [ ] Nenhum erro nos logs

### Risco #2: Performance Degradar (Query do Thread)

**Probabilidade:** Média (se usar `JSON_EXTRACT` com `LIKE`)  
**Impacto:** Médio

**Mitigação:**
- ✅ Usar índice em `created_at` (já existe)
- ✅ Adicionar limite na query (já existe: LIMIT 500)
- ✅ Considerar Solução #5 (campo dedicado com índice)
- ✅ Monitorar tempo de resposta do endpoint

**Checklist de Validação:**
- [ ] Query do thread executa em < 1 segundo
- [ ] Não causa timeout
- [ ] Não sobrecarrega banco de dados

### Risco #3: Perder Mensagens (Filtro Mais Restritivo)

**Probabilidade:** Baixa (se testar bem)  
**Impacto:** Crítico

**Mitigação:**
- ✅ Manter validação final em PHP (não confiar apenas em SQL)
- ✅ Testar com diferentes formatos de telefone
- ✅ Testar com `tenant_id` NULL e não NULL
- ✅ Comparar resultados antes/depois da mudança

**Checklist de Validação:**
- [ ] Todas as mensagens aparecem no thread
- [ ] Mensagens de outros contatos não aparecem
- [ ] Funciona com `tenant_id` NULL
- [ ] Funciona com diferentes formatos de telefone

### Risco #4: UI Flicker ou Estado Perdido

**Probabilidade:** Baixa (se implementar corretamente)  
**Impacto:** Baixo

**Mitigação:**
- ✅ Preservar scroll da lista
- ✅ Preservar conversa ativa
- ✅ Usar transição suave (opcional)
- ✅ Testar em diferentes navegadores

**Checklist de Validação:**
- [ ] Lista atualiza sem flicker
- [ ] Scroll é preservado
- [ ] Conversa ativa permanece aberta
- [ ] Badge atualiza corretamente

---

## ✅ Checklist de Validação (Focado em Não Quebrar Recebimento)

### Validação #1: Recebimento Não Regrediu (OBRIGATÓRIO)

- [ ] **Enviar mensagem inbound via webhook**
  - [ ] Webhook retorna 200 OK
  - [ ] Payload é recebido corretamente
  - [ ] Nenhum erro nos logs do webhook

- [ ] **Verificar ingestão do evento**
  - [ ] Evento é inserido em `communication_events` (status: `queued`)
  - [ ] `event_id` é gerado (UUID)
  - [ ] `idempotency_key` é calculado corretamente
  - [ ] Nenhum erro nos logs de `EventIngestionService::ingest()`

- [ ] **Verificar resolução de conversa**
  - [ ] Log: `[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation`
  - [ ] Log: `[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=...` (não NULL)
  - [ ] Conversa é atualizada ou criada em `conversations`
  - [ ] `last_message_at` é atualizado
  - [ ] `unread_count` é incrementado (se inbound)
  - [ ] `updated_at` é atualizado

- [ ] **Verificar se mensagem aparece no thread**
  - [ ] Abrir thread da conversa
  - [ ] Mensagem aparece na lista de mensagens
  - [ ] Timestamp está correto
  - [ ] Conteúdo está correto

### Validação #2: Ordenação da Lista Funciona

- [ ] **Sem conversa ativa**
  - [ ] Fechar todas as conversas
  - [ ] Enviar mensagem inbound para conversa antiga
  - [ ] Aguardar polling (12 segundos)
  - [ ] Lista recarrega (`location.reload()`)
  - [ ] Conversa sobe para o topo

- [ ] **Com conversa ativa**
  - [ ] Abrir uma conversa (thread ativo)
  - [ ] Enviar mensagem inbound para outra conversa
  - [ ] Aguardar polling (12 segundos)
  - [ ] Lista atualiza sem recarregar página (`updateConversationListOnly()`)
  - [ ] Conversa sobe para o topo
  - [ ] Conversa ativa permanece aberta
  - [ ] Badge atualiza na lista

### Validação #3: Badge e Thread Sincronizados

- [ ] **Receber mensagem inbound**
  - [ ] Verificar: Badge aparece na lista (`unread_count > 0`)
  - [ ] Abrir thread imediatamente
  - [ ] Verificar: Mensagem aparece no thread
  - [ ] Verificar: Badge some após abrir (`unread_count = 0`)

- [ ] **Verificar se não há race condition**
  - [ ] Receber mensagem inbound
  - [ ] Verificar banco: `conversations.unread_count` foi incrementado
  - [ ] Verificar banco: Evento existe em `communication_events`
  - [ ] Abrir thread
  - [ ] Verificar: Mensagem aparece (não há race)

### Validação #4: Query do Thread Não Perde Mensagens

- [ ] **Testar com múltiplas conversas**
  - [ ] Criar 5+ conversas com mensagens
  - [ ] Abrir thread de uma conversa específica
  - [ ] Verificar: Apenas mensagens dessa conversa aparecem
  - [ ] Verificar: Todas as mensagens aparecem (não perde nenhuma)

- [ ] **Testar com diferentes formatos de telefone**
  - [ ] Mensagem com `@c.us`
  - [ ] Mensagem com `@lid`
  - [ ] Mensagem sem sufixo
  - [ ] Verificar: Todas aparecem no thread

- [ ] **Testar com tenant_id NULL e não NULL**
  - [ ] Conversa com `tenant_id = NULL`
  - [ ] Conversa com `tenant_id = 5`
  - [ ] Verificar: Mensagens aparecem corretamente

### Validação #5: Performance Aceitável

- [ ] **Query do thread**
  - [ ] Executa em < 1 segundo
  - [ ] Não causa timeout
  - [ ] Não sobrecarrega banco

- [ ] **Atualização da lista**
  - [ ] Executa em < 2 segundos
  - [ ] Não causa flicker
  - [ ] Não trava UI

---

## 📝 Conclusão

### Problemas Confirmados

1. ✅ **`updateConversationListOnly()` está vazia** - Evidência: código fonte (linhas 1004-1016)
2. ✅ **Query do thread busca todos os eventos** - Evidência: código fonte (linhas 919-932)
3. ✅ **Filtro em PHP pode falhar** - Evidência: normalização e comparação em PHP

### Problemas Não Confirmados (Necessário Verificar Dados)

1. ❓ **`last_message_at` está sendo atualizado para Charles (4699)?**
   - Executar Query #1 acima
   - Se atualizado, problema é no frontend (confirmado)
   - Se não atualizado, problema é no backend

2. ❓ **Mensagem do ServPro (4223) existe em `communication_events`?**
   - Executar Query #1 do Problema B acima
   - Se existe, problema é no filtro do thread
   - Se não existe, problema é no recebimento (improvável)

3. ❓ **Filtro do thread está excluindo mensagem?**
   - Executar Query #3 do Problema B acima
   - Comparar telefone normalizado do evento com `contact_external_id` da conversa
   - Verificar se `tenant_id` está causando exclusão

### Próximos Passos

1. **Executar queries SQL** para confirmar estado do banco
2. **Verificar logs** para confirmar se `resolveConversation()` está sendo chamado
3. **Implementar correções** baseadas nas soluções acima
4. **Validar** usando checklist acima (sem regressão do recebimento)

---

**Fim da Auditoria de Regressão**

