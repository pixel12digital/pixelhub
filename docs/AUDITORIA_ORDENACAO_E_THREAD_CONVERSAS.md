# Auditoria Técnica - Ordenação e Thread de Conversas

**Data da Auditoria:** 2026-01-15  
**Objetivo:** Diagnosticar problemas de ordenação da lista de conversas e renderização de mensagens no thread  
**Escopo:** Apenas diagnóstico. Nenhuma correção será implementada nesta etapa.

---

## 📌 Resumo Executivo do Problema

### Problema #1: Ordenação Incorreta da Lista de Conversas

**Cenário Observado:**
- Contato Charles Dietrich (final 4699) enviou mensagem às 11:10
- Mensagem chegou no WhatsApp e no sistema
- **Conversa não subiu para o topo da lista**
- Outras conversas mais antigas continuam acima

**Comportamento Esperado:**
- Lista ordenada por `last_message_at` DESC (mais recente primeiro)
- Independente de direção (inbound/outbound), canal ou tenant

### Problema #2: Badge de Nova Mensagem sem Renderização

**Cenário Observado (ServPro – final 4223):**
- Conversa exibe badge de mensagem nova (contador verde)
- Indica que webhook recebeu e conversa foi atualizada
- **Mensagem não aparece na área de conversação ao abrir o thread**
- Histórico exibido está incompleto

**Comportamento Esperado:**
- Se há badge de nova mensagem, a mensagem deve existir no banco
- Deve ser carregada normalmente no thread
- Badge deve refletir exatamente o que é renderizado

### Observação Crítica (Possível Causa Raiz)

Esses dois sintomas juntos apontam fortemente para problemas de **sincronização entre**:
- Estado da lista de conversas
- Estado do thread aberto
- Lógica de refresh após unificação das telas

Especialmente após:
- Remoção de reloads automáticos
- Introdução de polling inteligente
- Pausa de polling durante interação
- Reuso de dados em memória (state local / JS)

---

## 🔍 1. Atualização de `last_message_at`

### 1.1. Fluxos que Atualizam `last_message_at`

#### Fluxo A: Inbound Webhook (Mensagem Recebida)

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php`

1. **Webhook recebe evento** (`handle()`)
   - Extrai `event_type` do payload
   - Mapeia para evento interno (ex: `whatsapp.inbound.message`)
   - Resolve `tenant_id` pelo `channel_id`
   - Chama `EventIngestionService::ingest()`

2. **Ingestão do evento** (`EventIngestionService::ingest()`)
   - Grava evento em `communication_events`
   - Status inicial: `queued`
   - Retorna `event_id`

3. **Roteamento do evento** (`EventRouterService::route()`)
   - Busca regras de roteamento
   - Para eventos de mensagem, **NÃO atualiza conversa diretamente**
   - Apenas roteia para canais (WhatsApp, chat, email)

4. **Resolução de conversa** (`ConversationService::resolveConversation()`)
   - **CRÍTICO:** Este método é chamado **após** a ingestão?
   - Verifica se evento é de mensagem (`isMessageEvent()`)
   - Extrai informações do canal (`extractChannelInfo()`)
   - Gera chave única da conversa (`generateConversationKey()`)
   - Busca conversa existente (`findByKey()`)
   - **Se encontrou:** Chama `updateConversationMetadata()`
   - **Se não encontrou:** Chama `createConversation()`

5. **Atualização de metadados** (`ConversationService::updateConversationMetadata()`)
   ```php
   UPDATE conversations 
   SET last_message_at = ?,  // ← Extraído do payload
       last_message_direction = ?,
       message_count = message_count + 1,
       unread_count = CASE 
           WHEN ? = 'inbound' THEN unread_count + 1 
           ELSE unread_count 
       END,
       updated_at = ?  // ← Sempre NOW()
   WHERE id = ?
   ```

**Ponto Crítico #1:** `last_message_at` é atualizado com `extractMessageTimestamp()`, que:
- Tenta extrair de `payload.message.timestamp` (Unix timestamp)
- Tenta extrair de `payload.timestamp`
- Tenta extrair de `payload.raw.payload.t` (formato WhatsApp)
- **Fallback:** `NOW()` se não conseguir extrair

**Ponto Crítico #2:** `updated_at` sempre usa `NOW()`, mas `last_message_at` usa timestamp da mensagem. Se o timestamp da mensagem for antigo (ex: mensagem atrasada), `last_message_at` pode ser menor que `updated_at`.

#### Fluxo B: Outbound Send (Mensagem Enviada)

**Arquivo:** `src/Controllers/CommunicationHubController.php`

1. **Usuário envia mensagem** (`send()`)
   - Valida canal, thread, mensagem
   - Resolve `channel_id` (prioridade: fornecido → thread → tenant → fallback)
   - Normaliza telefone
   - Envia via `WhatsAppGatewayClient::sendText()`

2. **Cria evento de envio** (`EventIngestionService::ingest()`)
   ```php
   EventIngestionService::ingest([
       'event_type' => 'whatsapp.outbound.message',
       'source_system' => 'pixelhub_operator',
       'payload' => [
           'to' => $phoneNormalized,
           'message' => ['to' => $phoneNormalized, 'text' => $message, 'timestamp' => time()],
           'text' => $message,
           'timestamp' => time(),  // ← Unix timestamp atual
           'channel_id' => $channelId
       ],
       'tenant_id' => $tenantId,
       'metadata' => [...]
   ]);
   ```

3. **Resolução de conversa** (`ConversationService::resolveConversation()`)
   - Mesmo fluxo do inbound
   - **Diferença:** `direction = 'outbound'`
   - `unread_count` **NÃO** é incrementado

4. **Atualização de metadados** (`ConversationService::updateConversationMetadata()`)
   - `last_message_at` atualizado com timestamp do envio
   - `unread_count` permanece inalterado

**Ponto Crítico #3:** Para outbound, o timestamp é `time()` (agora), então `last_message_at` sempre será atual. Mas se o evento for processado de forma assíncrona, pode haver delay.

### 1.2. Onde `last_message_at` é Atualizado

#### Tabela `conversations`
- **Campo:** `last_message_at` (DATETIME)
- **Atualizado em:**
  - `ConversationService::createConversation()` (INSERT)
  - `ConversationService::updateConversationMetadata()` (UPDATE)

#### Tabela `communication_events`
- **Campo:** `created_at` (DATETIME)
- **Não atualiza `conversations.last_message_at` diretamente**
- Apenas armazena o evento

**Ponto Crítico #4:** `ConversationService::resolveConversation()` **deve ser chamado** após cada ingestão de evento de mensagem. Se não for chamado automaticamente, `last_message_at` não será atualizado.

### 1.3. Fluxos Onde Mensagem Entra, Mas `last_message_at` Não é Atualizado

**Hipótese #1 (Alta Probabilidade - 80%):** `ConversationService::resolveConversation()` não está sendo chamado automaticamente após `EventIngestionService::ingest()`.

**Evidência:**
- `EventRouterService::route()` não chama `ConversationService::resolveConversation()`
- Não há listener/observer que chame após ingestão
- Não há trigger no banco que atualize `conversations`

**Hipótese #2 (Média Probabilidade - 15%):** `extractMessageTimestamp()` retorna timestamp incorreto ou NULL, e o fallback `NOW()` não está sendo aplicado corretamente.

**Hipótese #3 (Baixa Probabilidade - 5%):** Race condition onde múltiplas mensagens chegam simultaneamente e a última não atualiza `last_message_at` corretamente.

---

## 🔍 2. Query de Ordenação da Lista de Conversas

### 2.1. Query Atual Usada para Montar a Lista

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppThreadsFromConversations()`

```php
SELECT 
    c.id,
    c.conversation_key,
    c.channel_type,
    c.contact_external_id,
    c.contact_name,
    c.tenant_id,
    c.status,
    c.assigned_to,
    c.last_message_at,  // ← Campo usado para ordenação
    c.last_message_direction,
    c.message_count,
    c.unread_count,
    c.created_at,
    COALESCE(t.name, 'Sem tenant') as tenant_name,
    u.name as assigned_to_name
FROM conversations c
LEFT JOIN tenants t ON c.tenant_id = t.id
LEFT JOIN users u ON c.assigned_to = u.id
WHERE c.channel_type = 'whatsapp'
  AND c.tenant_id = ?  -- Se filtro aplicado
  AND c.status NOT IN ('closed', 'archived')  -- Se status = 'active'
ORDER BY c.last_message_at DESC, c.created_at DESC  // ← ORDENAÇÃO
LIMIT 100
```

**Ponto Crítico #5:** A query usa `last_message_at DESC`, que é correto. Mas se `last_message_at` não está sendo atualizado (ver seção 1.3), a ordenação será incorreta.

### 2.2. Ordenação no Frontend

**Arquivo:** `views/communication_hub/index.php`

**No PHP (servidor):**
```php
// Combina e ordena por última atividade
$allThreads = array_merge($whatsappThreads ?? [], $chatThreads ?? []);
if (!empty($allThreads)) {
    usort($allThreads, function($a, $b) {
        $timeA = strtotime($a['last_activity'] ?? '1970-01-01');
        $timeB = strtotime($b['last_activity'] ?? '1970-01-01');
        return $timeB <=> $timeA; // Mais recente primeiro
    });
}
```

**Ponto Crítico #6:** O PHP ordena por `last_activity`, que vem de `last_message_at` ou `created_at` (fallback). Se `last_message_at` não está atualizado, a ordenação será incorreta.

**No JavaScript (cliente):**
- Não há ordenação adicional no cliente
- A lista é renderizada na ordem recebida do servidor

### 2.3. Cache, State ou Memoização no Frontend

**Arquivo:** `views/communication_hub/index.php` (JavaScript)

**State Global:**
```javascript
const HubState = {
    lastUpdateTs: null,  // Timestamp da última atualização detectada
    pollingInterval: null,
    isPageVisible: true,
    isUserInteracting: false,
    lastInteractionTime: null,
    interactionTimeout: null
};
```

**Polling da Lista:**
```javascript
async function checkForListUpdates() {
    // Verifica se há atualizações após lastUpdateTs
    // Se houver, recarrega página OU atualiza lista via AJAX
}
```

**Ponto Crítico #7:** Se `checkForListUpdates()` detecta atualização mas **não recarrega a lista** (quando há conversa ativa), a ordenação pode ficar desatualizada.

**Código Relevante:**
```javascript
if (ConversationState.currentThreadId) {
    console.log('[Hub] Conversa ativa detectada, atualizando apenas lista (sem reload)');
    updateConversationListOnly();  // ← Esta função está vazia!
} else {
    location.reload();  // ← Só recarrega se não há conversa ativa
}
```

**Ponto Crítico #8:** `updateConversationListOnly()` está implementada mas **não faz nada**:
```javascript
async function updateConversationListOnly() {
    // Por enquanto, apenas loga que detectou atualização mas não recarrega
    // A lista será atualizada no próximo reload natural (quando usuário fechar conversa)
    console.log('[Hub] Lista atualizada (sem reload para preservar conversa ativa)');
}
```

**Conclusão:** Se há conversa ativa e uma nova mensagem chega, a lista **não é atualizada**, resultando em ordenação incorreta.

### 2.4. Mudanças Após Unificação das Telas

**Antes da Unificação:**
- Lista e thread eram telas separadas
- Cada tela tinha seu próprio polling
- Reload completo ao detectar atualização

**Depois da Unificação:**
- Lista e thread na mesma tela
- Polling inteligente que pausa durante interação
- **NÃO recarrega lista se há conversa ativa** (para preservar estado)

**Impacto:** A decisão de não recarregar a lista quando há conversa ativa pode estar causando a ordenação incorreta.

---

## 🔍 3. Fonte de Dados do Badge vs Fonte de Dados do Thread

### 3.1. Como o Badge é Calculado

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppThreadsFromConversations()`

```php
'unread_count' => (int) $conv['unread_count']
```

**Fonte:** Campo `unread_count` da tabela `conversations`

**Atualização:**
- Incrementado em `ConversationService::updateConversationMetadata()` quando `direction = 'inbound'`
- Zerado em `CommunicationHubController::markConversationAsRead()` quando thread é aberto

### 3.2. Como o Thread Carrega Mensagens

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Método:** `getWhatsAppMessagesFromConversation()`

```php
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
```

**Depois filtra em PHP:**
- Normaliza `contact_external_id` da conversa
- Compara com `from`/`to` de cada evento
- Filtra por `tenant_id` se ambos tiverem

**Ponto Crítico #9:** O thread busca **TODOS** os eventos e filtra em PHP. Se houver muitos eventos, pode ser lento e pode perder mensagens se a normalização falhar.

### 3.3. Possibilidade de Badge Atualizado, Mas Thread Não Refaz Fetch

**Cenário:**
1. Mensagem inbound chega via webhook
2. `ConversationService::resolveConversation()` atualiza `unread_count` e `last_message_at`
3. Badge na lista mostra contador verde
4. Usuário abre thread
5. Thread carrega mensagens via `getWhatsAppMessagesFromConversation()`
6. **Se a mensagem não está em `communication_events` ainda** (race condition), não aparece

**Ponto Crítico #10:** Se `EventIngestionService::ingest()` e `ConversationService::resolveConversation()` executam em momentos diferentes, pode haver janela onde:
- `conversations.unread_count` está atualizado
- `communication_events` ainda não tem o evento

**Evidência:** Não há transação que garanta atomicidade entre:
- Inserção em `communication_events`
- Atualização em `conversations`

### 3.4. Polling do Thread vs Polling da Lista

**Polling da Lista:**
- Verifica `conversations.updated_at` ou `conversations.last_message_at`
- Intervalo: 12 segundos
- Pausa durante interação do usuário

**Polling do Thread:**
- Verifica `communication_events.created_at` após `lastTimestamp`
- Intervalo: 12 segundos
- Pausa quando página não está visível

**Ponto Crítico #11:** Se a lista detecta atualização mas o thread não (por timing diferente), o badge pode aparecer mas a mensagem não.

---

## 🔍 4. Polling / Refresh Após Unificação

### 4.1. Polling da Lista

**Arquivo:** `views/communication_hub/index.php`

```javascript
function startListPolling() {
    HubState.pollingInterval = setInterval(() => {
        if (HubState.isPageVisible && !HubState.isUserInteracting) {
            const timeSinceInteraction = HubState.lastInteractionTime 
                ? Date.now() - HubState.lastInteractionTime 
                : Infinity;
            
            if (timeSinceInteraction > 5000) {  // 5 segundos sem interação
                checkForListUpdates();
            }
        }
    }, 12000);  // A cada 12 segundos
}
```

**Condições para Executar:**
- Página visível (`isPageVisible = true`)
- Usuário não está interagindo (`isUserInteracting = false`)
- Última interação há mais de 5 segundos

**Ação ao Detectar Atualização:**
```javascript
if (ConversationState.currentThreadId) {
    // Conversa ativa → NÃO recarrega, apenas atualiza lista (mas função está vazia!)
    updateConversationListOnly();
} else {
    // Sem conversa ativa → Recarrega página
    location.reload();
}
```

### 4.2. Polling do Thread

**Arquivo:** `views/communication_hub/index.php`

```javascript
function startConversationPolling() {
    ConversationState.pollingInterval = setInterval(() => {
        if (ConversationState.isPageVisible && ConversationState.currentThreadId) {
            checkForNewConversationMessages();
        }
    }, 12000);  // A cada 12 segundos
}
```

**Condições para Executar:**
- Página visível
- Há thread ativo (`currentThreadId` não é null)

**Ação ao Detectar Nova Mensagem:**
```javascript
async function checkForNewConversationMessages() {
    // 1. Verifica se há novas mensagens (check leve)
    const checkResponse = await fetch('/communication-hub/messages/check?...');
    
    if (result.has_new) {
        // 2. Busca novas mensagens
        const fetchResponse = await fetch('/communication-hub/messages/new?...');
        // 3. Adiciona ao painel
        onNewMessagesFromPanel(fetchResult.messages);
    }
}
```

### 4.3. Cenários Onde Lista Recebe Update, Mas Thread Não Refaz Fetch

**Cenário #1: Thread Aberto, Nova Mensagem Chega**
1. Lista detecta atualização via `checkForListUpdates()`
2. Como há thread ativo, chama `updateConversationListOnly()` (que não faz nada)
3. Thread faz polling independente via `checkForNewConversationMessages()`
4. **Se o polling do thread não detectar** (por timing), mensagem não aparece

**Cenário #2: Usuário Interagindo, Nova Mensagem Chega**
1. `isUserInteracting = true`
2. Polling da lista é pausado
3. Polling do thread também pode estar pausado (se página não visível)
4. Mensagem chega mas não é detectada até interação terminar

**Cenário #3: Race Condition Entre Lista e Thread**
1. Lista detecta atualização e atualiza badge
2. Thread ainda não fez fetch (aguardando próximo intervalo)
3. Usuário abre thread antes do fetch
4. Thread carrega mensagens antigas (antes da nova)
5. Nova mensagem só aparece no próximo polling

### 4.4. Flags de `isUserInteracting`

**Arquivo:** `views/communication_hub/index.php`

```javascript
function markUserInteraction() {
    HubState.isUserInteracting = true;
    HubState.lastInteractionTime = Date.now();
    
    // Marca como não interagindo após 2 segundos de inatividade
    HubState.interactionTimeout = setTimeout(() => {
        HubState.isUserInteracting = false;
    }, 2000);
}
```

**Eventos que Marcam Interação:**
- `mousedown`
- `keydown`
- `click`
- `focus` (em elementos interativos)

**Ponto Crítico #12:** Se usuário está digitando, `isUserInteracting` fica `true` e polling é pausado. Mensagens podem não aparecer até parar de digitar por 2 segundos.

---

## 🔍 5. Diferença Entre Inbound e Outbound no Refresh

### 5.1. Fluxo de Atualização Visual para Inbound

1. **Webhook recebe** → `WhatsAppWebhookController::handle()`
2. **Ingestão** → `EventIngestionService::ingest()`
3. **Roteamento** → `EventRouterService::route()` (não atualiza conversa)
4. **Resolução de conversa** → `ConversationService::resolveConversation()` (se chamado)
5. **Atualização de metadados** → `updateConversationMetadata()`
   - `last_message_at` atualizado
   - `unread_count` incrementado
6. **Polling detecta** → `checkForListUpdates()` vê `updated_at` ou `last_message_at` mudou
7. **Badge atualizado** → Lista mostra contador verde
8. **Thread detecta** → `checkForNewConversationMessages()` busca novas mensagens

**Ponto Crítico #13:** Se `ConversationService::resolveConversation()` não é chamado automaticamente, `unread_count` e `last_message_at` não são atualizados.

### 5.2. Fluxo de Atualização Visual para Outbound

1. **Usuário envia** → `CommunicationHubController::send()`
2. **Envio via gateway** → `WhatsAppGatewayClient::sendText()`
3. **Cria evento** → `EventIngestionService::ingest()` (com `timestamp = time()`)
4. **Resolução de conversa** → `ConversationService::resolveConversation()` (se chamado)
5. **Atualização de metadados** → `updateConversationMetadata()`
   - `last_message_at` atualizado
   - `unread_count` **NÃO** incrementado
6. **Mensagem otimista** → Frontend adiciona mensagem imediatamente (sem esperar polling)
7. **Confirmação** → `confirmSentMessageFromPanel()` busca mensagem confirmada

**Ponto Crítico #14:** Para outbound, há **mensagem otimista** no frontend, então aparece imediatamente. Para inbound, depende do polling.

### 5.3. Existe `if` que Trata Apenas Outbound para Refresh do Thread?

**Não encontrado.** O código trata inbound e outbound da mesma forma no thread:
```php
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
```

### 5.4. Existe `if` que Trata Apenas Inbound para Atualizar Badge?

**Sim.** Em `ConversationService::updateConversationMetadata()`:
```php
unread_count = CASE 
    WHEN ? = 'inbound' THEN unread_count + 1 
    ELSE unread_count 
END
```

Apenas mensagens inbound incrementam `unread_count`.

---

## 📊 Hipóteses Técnicas (com Grau de Probabilidade)

### Hipótese #1: `ConversationService::resolveConversation()` Não é Chamado Automaticamente (90%)

**Probabilidade:** Alta (90%)  
**Impacto:** Crítico

**Evidência:**
- `EventRouterService::route()` não chama `ConversationService::resolveConversation()`
- Não há listener/observer após `EventIngestionService::ingest()`
- Não há trigger no banco

**Consequências:**
- `last_message_at` não é atualizado → ordenação incorreta
- `unread_count` não é incrementado → badge não aparece (ou aparece incorretamente)
- Mensagem existe em `communication_events`, mas conversa não é atualizada

**Validação:**
- Verificar se há chamada a `ConversationService::resolveConversation()` após ingestão
- Verificar logs de `[CONVERSATION UPSERT]` após recebimento de mensagem

### Hipótese #2: `updateConversationListOnly()` Está Vazia (85%)

**Probabilidade:** Alta (85%)  
**Impacto:** Médio

**Evidência:**
- Função existe mas apenas loga, não atualiza lista
- Quando há conversa ativa, lista não é recarregada

**Consequências:**
- Lista fica desatualizada quando há conversa aberta
- Ordenação incorreta até fechar conversa e recarregar

**Validação:**
- Verificar implementação de `updateConversationListOnly()`
- Testar se lista atualiza quando há conversa ativa

### Hipótese #3: Race Condition Entre Ingestão e Resolução (70%)

**Probabilidade:** Média (70%)  
**Impacto:** Médio

**Evidência:**
- Não há transação que garanta atomicidade
- `communication_events` e `conversations` são atualizados separadamente

**Consequências:**
- Badge pode aparecer antes da mensagem estar disponível no thread
- Thread pode não encontrar mensagem se buscar muito cedo

**Validação:**
- Verificar timing entre inserção em `communication_events` e atualização em `conversations`
- Verificar se há janela onde badge existe mas mensagem não

### Hipótese #4: Polling Pausado Durante Interação (60%)

**Probabilidade:** Média (60%)  
**Impacto:** Baixo

**Evidência:**
- `isUserInteracting` pausa polling
- Timeout de 2 segundos para marcar como não interagindo

**Consequências:**
- Mensagens podem não aparecer imediatamente se usuário está digitando
- Delay de até 2 segundos + intervalo de polling (12s) = até 14 segundos

**Validação:**
- Testar se mensagens aparecem durante digitação
- Verificar timing de atualização

### Hipótese #5: `extractMessageTimestamp()` Retorna Timestamp Incorreto (40%)

**Probabilidade:** Baixa (40%)  
**Impacto:** Baixo

**Evidência:**
- Múltiplas fontes de timestamp no payload
- Fallback para `NOW()` se não encontrar

**Consequências:**
- `last_message_at` pode ser incorreto se timestamp do payload estiver errado
- Ordenação pode ser afetada se timestamps estiverem fora de ordem

**Validação:**
- Verificar logs de `extractMessageTimestamp()`
- Comparar timestamps no payload vs `last_message_at` no banco

---

## 📁 Arquivos/Métodos Candidatos a Ajuste

### Arquivo: `src/Services/EventIngestionService.php`
**Método:** `ingest()`
**Ação:** Adicionar chamada a `ConversationService::resolveConversation()` após inserir evento de mensagem

### Arquivo: `src/Services/EventRouterService.php`
**Método:** `route()`
**Ação:** Adicionar chamada a `ConversationService::resolveConversation()` após rotear evento de mensagem

### Arquivo: `views/communication_hub/index.php`
**Função:** `updateConversationListOnly()`
**Ação:** Implementar atualização AJAX da lista sem recarregar página

### Arquivo: `src/Services/ConversationService.php`
**Método:** `extractMessageTimestamp()`
**Ação:** Melhorar extração de timestamp e validação

### Arquivo: `src/Controllers/CommunicationHubController.php`
**Método:** `getWhatsAppMessagesFromConversation()`
**Ação:** Otimizar query para não buscar todos os eventos (usar índice em `created_at` e filtro por contato)

---

## 🔄 O Que Mudou Após a Unificação das Telas

### Antes da Unificação
- Lista e thread eram telas separadas
- Cada tela tinha reload completo ao detectar atualização
- Polling independente para cada tela
- Sem preservação de estado entre telas

### Depois da Unificação
- Lista e thread na mesma tela (2 colunas)
- Polling inteligente que pausa durante interação
- **NÃO recarrega lista se há conversa ativa** (para preservar estado)
- Função `updateConversationListOnly()` criada mas não implementada
- Reuso de dados em memória (`ConversationState`, `HubState`)

### Regressões Prováveis
1. **Lista não atualiza quando há conversa ativa**
   - Função `updateConversationListOnly()` está vazia
   - Resultado: ordenação incorreta

2. **Polling pausado durante interação**
   - Mensagens podem não aparecer imediatamente
   - Resultado: badge aparece mas mensagem não

3. **Falta de sincronização entre lista e thread**
   - Lista detecta atualização mas thread não (ou vice-versa)
   - Resultado: estado inconsistente

---

## ✅ Checklist de Validação Futura (Quando Formos Corrigir)

### Validação #1: `ConversationService::resolveConversation()` é Chamado
- [ ] Verificar se há chamada após `EventIngestionService::ingest()`
- [ ] Verificar logs de `[CONVERSATION UPSERT]` após recebimento de mensagem
- [ ] Testar se `last_message_at` é atualizado quando mensagem chega

### Validação #2: Ordenação da Lista
- [ ] Enviar mensagem para conversa antiga
- [ ] Verificar se conversa sobe para o topo da lista
- [ ] Verificar se `last_message_at` está correto no banco
- [ ] Verificar se query SQL ordena corretamente

### Validação #3: Badge vs Mensagem no Thread
- [ ] Receber mensagem inbound
- [ ] Verificar se badge aparece na lista
- [ ] Abrir thread imediatamente
- [ ] Verificar se mensagem aparece no thread
- [ ] Verificar se mensagem existe em `communication_events`

### Validação #4: Polling Durante Interação
- [ ] Abrir thread
- [ ] Começar a digitar mensagem
- [ ] Enviar mensagem de teste para o mesmo contato (outro dispositivo)
- [ ] Verificar se mensagem aparece após parar de digitar
- [ ] Verificar timing de atualização

### Validação #5: Race Condition
- [ ] Enviar múltiplas mensagens rapidamente
- [ ] Verificar se todas aparecem no thread
- [ ] Verificar se badge reflete número correto
- [ ] Verificar se `last_message_at` está na última mensagem

### Validação #6: Inbound vs Outbound
- [ ] Enviar mensagem outbound → verificar se aparece imediatamente
- [ ] Receber mensagem inbound → verificar se aparece no polling
- [ ] Verificar se `unread_count` só incrementa para inbound
- [ ] Verificar se `last_message_at` atualiza para ambos

---

## 🎯 Conclusão

### Problemas Identificados

1. **`ConversationService::resolveConversation()` provavelmente não é chamado automaticamente**
   - Impacto: `last_message_at` e `unread_count` não são atualizados
   - Solução: Adicionar chamada após ingestão de eventos de mensagem

2. **`updateConversationListOnly()` está vazia**
   - Impacto: Lista não atualiza quando há conversa ativa
   - Solução: Implementar atualização AJAX da lista

3. **Falta de sincronização entre lista e thread**
   - Impacto: Badge pode aparecer mas mensagem não
   - Solução: Garantir que ambos usem mesma fonte de dados e timing

4. **Polling pausado durante interação**
   - Impacto: Mensagens podem não aparecer imediatamente
   - Solução: Revisar lógica de pausa ou reduzir timeout

### Próximos Passos (Quando Implementar Correções)

1. **Adicionar chamada a `ConversationService::resolveConversation()` após ingestão**
   - Local: `EventIngestionService::ingest()` ou `EventRouterService::route()`
   - Garantir que seja chamado para todos os eventos de mensagem

2. **Implementar `updateConversationListOnly()`**
   - Buscar lista atualizada via AJAX
   - Atualizar DOM sem recarregar página
   - Preservar conversa ativa

3. **Otimizar query de mensagens do thread**
   - Usar índice em `created_at`
   - Filtrar por contato na query (não em PHP)
   - Reduzir quantidade de dados buscados

4. **Revisar lógica de polling**
   - Considerar reduzir timeout de interação (2s → 1s)
   - Considerar polling mais frequente durante thread ativo
   - Garantir sincronização entre lista e thread

---

**Fim da Auditoria Técnica**

