# Auditoria: Falha no Recebimento de Mensagens WhatsApp
**Data:** 2026-01-15  
**Cenário:** Mensagem recebida no WhatsApp (07:27 SP) não apareceu no Pixel Hub

---

## 1. Resumo Executivo

**Teste realizado:** Mensagem de teste enviada por Charles Dietrich (+55 47 9616-4699) às 07:27 (horário de SP) para o número conectado no Gateway (Pixel12 Digital). A mensagem foi confirmada como recebida no WhatsApp Web, mas não apareceu no Pixel Hub (nem na lista de conversas, nem em thread existente).

**Impacto:** Mensagens recebidas via WhatsApp não estão sendo exibidas no painel operacional, impedindo atendimento em tempo real.

**Hipótese principal (baseada em evidência):** A cadeia de processamento quebra em um dos seguintes pontos:
1. Webhook não chegou ao endpoint do Hub (problema de rede/VPS/Gateway)
2. Webhook chegou mas falhou na validação/parse do payload
3. Evento foi ingerido mas não foi criada/atualizada a conversa na tabela `conversations`
4. Conversa foi criada mas não aparece na UI devido a filtros ou problema de polling

**Abordagem:** Auditoria ponta-a-ponta focada no lado Pixel Hub primeiro, verificando logs, banco de dados e fluxo de código. Se evidência apontar que webhook não chegou, então investigar VPS/Gateway.

---

## 2. Linha do Tempo do Teste

### Evento no WhatsApp
- **Data/Hora Local:** 2026-01-15 07:27 (horário de São Paulo, UTC-3)
- **Data/Hora UTC:** 2026-01-15 10:27 UTC (assumindo UTC-3)
- **Origem:** Charles Dietrich (+55 47 9616-4699)
- **Destino:** Número conectado no Gateway (Pixel12 Digital)
- **Conteúdo:** "teste real 07:27 horário de SP"
- **Status no WhatsApp:** ✅ Mensagem aparece como recebida no WhatsApp Web

### Evento Esperado no Hub
1. **Webhook recebido** → `POST /api/whatsapp/webhook` (WhatsAppWebhookController::handle)
2. **Log HUB_WEBHOOK_IN** → Log padrão com eventType, channel_id, from, message_id, timestamp
3. **Validação/Parse** → Extração de eventType, channel_id, from/to, timestamp do payload
4. **Roteamento** → Resolução de tenant_id via `resolveTenantByChannel()`
5. **Ingestão** → `EventIngestionService::ingest()` → INSERT em `communication_events`
6. **Resolução de Conversa** → `ConversationService::resolveConversation()` → UPSERT em `conversations`
7. **Atualização de UI** → Polling via `/communication-hub/check-updates` ou `/communication-hub/messages/check`

### Onde a Cadeia Quebrou (a ser confirmado)
**Ponto de quebra provável:** Entre etapas 1-6 (webhook → persistência). Se evento chegou ao banco mas não aparece na UI, problema é em filtros/polling (etapa 7).

---

## 3. Checklist de Verificação Técnica

### (A) Chegada do Evento no Endpoint do Hub

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de logs)

**Evidência necessária:**
- Log `[HUB_WEBHOOK_IN]` no arquivo de logs do servidor (ex: `logs/pixelhub.log` ou logs do Apache/Nginx)
- Log `[WHATSAPP INBOUND RAW]` com payload completo
- Access log do servidor web mostrando `POST /api/whatsapp/webhook` com status 200

**Onde verificar:**
- Arquivo: `src/Controllers/WhatsAppWebhookController.php` (linhas 73-85)
- Log padrão: `[HUB_WEBHOOK_IN] eventType=%s channel_id=%s tenant_id=%s from=%s normalized_from=%s message_id=%s timestamp=%s correlationId=%s payload_hash=%s`
- Access log: Verificar logs do Apache/Nginx para requisições POST ao endpoint

**Query para verificar:**
```sql
-- Verificar se há eventos recentes no banco (se chegou até a ingestão)
SELECT 
    event_id, 
    event_type, 
    source_system, 
    tenant_id, 
    created_at,
    status,
    JSON_EXTRACT(payload, '$.from') as from_number,
    JSON_EXTRACT(payload, '$.message.from') as from_message
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'  -- Ajustar para UTC
AND created_at <= '2026-01-15 10:35:00'
ORDER BY created_at DESC;
```

---

### (B) Validação/Parse do Payload

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de logs)

**Evidência necessária:**
- Log `[HUB_CHANNEL_ID]` mostrando se channel_id foi encontrado
- Log `[WHATSAPP INBOUND RAW]` mostrando estrutura do payload
- Se channel_id não foi encontrado, log `MISSING_CHANNEL_ID`

**Onde verificar:**
- Arquivo: `src/Controllers/WhatsAppWebhookController.php` (linhas 160-185)
- Log esperado: `[HUB_CHANNEL_ID] channel_id encontrado: {channel_id}` ou `[HUB_CHANNEL_ID] MISSING_CHANNEL_ID`

**Campos críticos a verificar:**
- `eventType`: Deve ser "message" (mapeado para "whatsapp.inbound.message")
- `channel_id`: Deve estar em `payload['channel']`, `payload['session']['id']`, `payload['data']['session']['id']`, etc.
- `from`: Deve estar em `payload['from']`, `payload['message']['from']`, `payload['data']['from']`
- `messageId`: Deve estar em `payload['id']`, `payload['messageId']`, `payload['message']['id']`

**Query para verificar:**
```sql
-- Verificar payload de eventos recentes (se chegou até a ingestão)
SELECT 
    event_id,
    event_type,
    JSON_EXTRACT(payload, '$.event') as raw_event_type,
    JSON_EXTRACT(payload, '$.channel') as channel_raw,
    JSON_EXTRACT(payload, '$.session.id') as session_id,
    JSON_EXTRACT(payload, '$.from') as from_raw,
    JSON_EXTRACT(payload, '$.message.from') as from_message,
    JSON_EXTRACT(payload, '$.id') as message_id_raw,
    created_at
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'
ORDER BY created_at DESC
LIMIT 10;
```

---

### (C) Roteamento (Resolução de Tenant)

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de logs e banco)

**Evidência necessária:**
- Log `[WHATSAPP INBOUND RAW] Tenant ID resolvido: {tenant_id}` ou `NULL`
- Log `[WHATSAPP INBOUND RAW] Channels disponíveis no banco: {channels}`
- Se tenant_id = NULL, verificar se channel_id existe em `tenant_message_channels`

**Onde verificar:**
- Arquivo: `src/Controllers/WhatsAppWebhookController.php` (linhas 345-380, método `resolveTenantByChannel`)
- Tabela: `tenant_message_channels` (verificar se channel_id existe e está habilitado)

**Query para verificar:**
```sql
-- Verificar canais disponíveis
SELECT 
    id,
    tenant_id,
    provider,
    channel_id,
    is_enabled,
    created_at
FROM tenant_message_channels
WHERE provider = 'wpp_gateway'
AND is_enabled = 1;

-- Verificar se evento foi ingerido com tenant_id NULL
SELECT 
    event_id,
    tenant_id,
    JSON_EXTRACT(metadata, '$.channel_id') as channel_id_from_metadata,
    created_at
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'
AND tenant_id IS NULL
ORDER BY created_at DESC;
```

**Regras de "Sem tenant":**
- Se `channel_id` não existe em `tenant_message_channels`, `tenant_id` fica NULL
- Eventos com `tenant_id = NULL` ainda devem criar conversas (com `tenant_id = NULL`)
- UI deve exibir conversas com "Sem tenant" se filtro permitir

---

### (D) Persistência (INSERT em communication_events)

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de banco)

**Evidência necessária:**
- Log `[HUB_MSG_SAVE] INSERT_ATTEMPT` antes do INSERT
- Log `[HUB_MSG_SAVE_OK]` após INSERT bem-sucedido (com id_pk)
- Log `[HUB_MSG_SAVE] INSERT_FAILED` se falhou (com erro SQL)
- Registro na tabela `communication_events` com `event_type = 'whatsapp.inbound.message'`

**Onde verificar:**
- Arquivo: `src/Services/EventIngestionService.php` (linhas 150-225)
- Tabela: `communication_events`

**Query para verificar:**
```sql
-- Verificar se evento foi persistido
SELECT 
    id,
    event_id,
    idempotency_key,
    event_type,
    source_system,
    tenant_id,
    status,
    created_at,
    JSON_EXTRACT(payload, '$.from') as from_number,
    JSON_EXTRACT(payload, '$.message.from') as from_message,
    JSON_EXTRACT(payload, '$.text') as message_text,
    JSON_EXTRACT(payload, '$.body') as message_body
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'
AND (
    JSON_EXTRACT(payload, '$.from') LIKE '%554796164699%'
    OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796164699%'
)
ORDER BY created_at DESC;

-- Verificar constraints que podem ter falhado
SHOW CREATE TABLE communication_events;
```

**Possíveis falhas silenciosas:**
- Foreign key constraint em `tenant_id` (se tenant não existe)
- JSON inválido no payload
- Erro de charset/encoding
- Timeout de transação

---

### (E) Dedupe/Idempotência

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de logs e banco)

**Evidência necessária:**
- Log `[HUB_MSG_DROP] DROP_DUPLICATE` se mensagem foi descartada por duplicata
- Verificar se `idempotency_key` já existe na tabela

**Onde verificar:**
- Arquivo: `src/Services/EventIngestionService.php` (linhas 65-91)
- Tabela: `communication_events` (campo `idempotency_key` é UNIQUE)

**Query para verificar:**
```sql
-- Verificar se há eventos duplicados (mesmo idempotency_key)
SELECT 
    idempotency_key,
    COUNT(*) as count,
    GROUP_CONCAT(event_id) as event_ids,
    MIN(created_at) as first_created,
    MAX(created_at) as last_created
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'
GROUP BY idempotency_key
HAVING count > 1;

-- Verificar cálculo de idempotency_key (formato: source_system:event_type:external_id ou hash)
-- Para WhatsApp, external_id geralmente é messageId ou hash do payload
SELECT 
    event_id,
    idempotency_key,
    source_system,
    event_type,
    JSON_EXTRACT(payload, '$.id') as message_id,
    created_at
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'
ORDER BY created_at DESC
LIMIT 10;
```

**Possíveis problemas:**
- `idempotency_key` calculado incorretamente (colisão)
- Mensagem descartada por "já existe" quando na verdade é nova
- Chave baseada em campos que variam entre retries do gateway

---

### (F) Resolução de Conversa (UPSERT em conversations)

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de logs e banco)

**Evidência necessária:**
- Log `[DIAGNOSTICO] ConversationService::resolveConversation() - INICIADO`
- Log `[HUB_CONV_MATCH] FOUND_CONVERSATION` ou `[HUB_CONV_MATCH] CREATED_CONVERSATION`
- Log `[CONVERSATION UPSERT] updateConversationMetadata` ou `createConversation`
- Registro na tabela `conversations` com `contact_external_id` normalizado

**Onde verificar:**
- Arquivo: `src/Services/ConversationService.php` (linhas 29-161)
- Tabela: `conversations`

**Query para verificar:**
```sql
-- Verificar se conversa foi criada/atualizada
SELECT 
    id,
    conversation_key,
    channel_type,
    contact_external_id,
    contact_name,
    tenant_id,
    status,
    last_message_at,
    last_message_direction,
    message_count,
    unread_count,
    created_at,
    updated_at
FROM conversations
WHERE channel_type = 'whatsapp'
AND (
    contact_external_id LIKE '%554796164699%'
    OR contact_external_id LIKE '%554796164699%'  -- Com 9º dígito
)
ORDER BY last_message_at DESC, updated_at DESC
LIMIT 10;

-- Verificar conversas sem tenant (se tenant_id não foi resolvido)
SELECT 
    id,
    conversation_key,
    contact_external_id,
    tenant_id,
    last_message_at,
    created_at
FROM conversations
WHERE channel_type = 'whatsapp'
AND tenant_id IS NULL
AND last_message_at >= '2026-01-15 10:20:00'
ORDER BY last_message_at DESC;
```

**Possíveis problemas:**
- `contact_external_id` não foi normalizado corretamente (com/sem 9º dígito, com/sem @c.us)
- `conversation_key` não bate (variação de formato)
- Conversa criada mas `last_message_at` não foi atualizado
- Conversa criada mas `unread_count` não foi incrementado

---

### (G) Atualização de Lista / Polling

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de logs e API)

**Evidência necessária:**
- Log `[LOG TEMPORARIO] CommunicationHub::getConversationsList() - RETORNO FINAL`
- Log `[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - RESULTADO`
- Resposta da API `/communication-hub/conversations-list` contendo a conversa
- Resposta da API `/communication-hub/messages/check` indicando novas mensagens

**Onde verificar:**
- Arquivo: `src/Controllers/CommunicationHubController.php`
  - Método `getConversationsList()` (linhas 1329-1411)
  - Método `checkNewMessages()` (linhas 1493-1688)
- Endpoint: `GET /communication-hub/conversations-list?channel=whatsapp&status=active`
- Endpoint: `GET /communication-hub/messages/check?thread_id=whatsapp_{id}&after_timestamp=...`

**Query para verificar:**
```sql
-- Verificar se conversa aparece na query da lista (simular query do controller)
SELECT 
    c.id,
    c.conversation_key,
    c.contact_external_id,
    c.tenant_id,
    c.status,
    c.last_message_at,
    c.unread_count,
    COALESCE(t.name, 'Sem tenant') as tenant_name
FROM conversations c
LEFT JOIN tenants t ON c.tenant_id = t.id
WHERE c.channel_type = 'whatsapp'
AND c.status NOT IN ('closed', 'archived')
AND (
    c.contact_external_id LIKE '%554796164699%'
    OR c.contact_external_id LIKE '%554796164699%'
)
ORDER BY c.last_message_at DESC, c.created_at DESC
LIMIT 100;
```

**Possíveis problemas:**
- Filtro `status = 'active'` excluindo conversas com `status = 'new'`
- Filtro de `tenant_id` excluindo conversas com `tenant_id = NULL`
- `last_message_at` fora da janela de `after_timestamp` (timezone)
- Ordenação incorreta (conversa aparece mas não no topo)

---

### (H) Filtros da UI

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de código e testes)

**Evidência necessária:**
- Verificar filtros aplicados na view `communication_hub/index.php`
- Verificar se filtro de canal está correto (Canal=WhatsApp)
- Verificar se filtro de status está correto (Status=Ativas)
- Verificar se filtro de cliente está correto (Cliente=Todos)

**Onde verificar:**
- Arquivo: `views/communication_hub/index.php`
- Arquivo: `src/Controllers/CommunicationHubController.php` (método `index()`, linhas 29-126)

**Filtros críticos:**
- `channel = 'whatsapp'` ou `channel = 'all'`
- `status = 'active'` (deve incluir `status = 'new'` e `status = 'open'`)
- `tenant_id = null` (deve mostrar conversas sem tenant se filtro permitir)

**Possíveis problemas:**
- Filtro `status = 'active'` não inclui `status = 'new'`
- Filtro de `tenant_id` não permite `NULL`
- JavaScript não está fazendo polling corretamente
- Cache do navegador mostrando lista antiga

---

### (I) Timezone

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de timestamps)

**Evidência necessária:**
- Comparar `created_at` do evento com `last_message_at` da conversa
- Verificar se `last_message_at` está em UTC ou timezone local
- Verificar se `after_timestamp` do polling está em timezone correto

**Query para verificar:**
```sql
-- Comparar timestamps
SELECT 
    ce.event_id,
    ce.created_at as event_created_at,
    c.id as conversation_id,
    c.last_message_at as conversation_last_message_at,
    TIMESTAMPDIFF(SECOND, ce.created_at, c.last_message_at) as diff_seconds
FROM communication_events ce
LEFT JOIN conversations c ON c.contact_external_id LIKE CONCAT('%', JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '%')
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.created_at >= '2026-01-15 10:20:00'
AND (
    JSON_EXTRACT(ce.payload, '$.from') LIKE '%554796164699%'
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%554796164699%'
)
ORDER BY ce.created_at DESC;
```

**Possíveis problemas:**
- `last_message_at` salvo com timezone incorreto (UTC vs SP)
- `after_timestamp` do polling em timezone diferente
- Mensagem salva com timestamp futuro (aparece depois da janela de busca)

---

### (J) Normalização de Número

**Status:** ⚠️ **INCONCLUSIVO** (requer verificação de normalização)

**Evidência necessária:**
- Verificar se `contact_external_id` na conversa está normalizado (E.164)
- Verificar se número no payload está com/sem 9º dígito
- Verificar se número está com/sem sufixo @c.us, @lid, etc.

**Onde verificar:**
- Arquivo: `src/Services/ConversationService.php` (método `extractChannelInfo`, linhas 183-371)
- Arquivo: `src/Services/PhoneNormalizer.php` (se existir)

**Query para verificar:**
```sql
-- Verificar normalização de números
SELECT 
    id,
    contact_external_id,
    LENGTH(contact_external_id) as length,
    SUBSTRING(contact_external_id, 1, 2) as country_code,
    SUBSTRING(contact_external_id, 3, 2) as ddd,
    SUBSTRING(contact_external_id, 5) as number_part
FROM conversations
WHERE channel_type = 'whatsapp'
AND (
    contact_external_id LIKE '%554796164699%'
    OR contact_external_id LIKE '%554796164699%'
)
ORDER BY updated_at DESC;

-- Verificar eventos com números não normalizados
SELECT 
    event_id,
    JSON_EXTRACT(payload, '$.from') as from_raw,
    JSON_EXTRACT(payload, '$.message.from') as from_message,
    created_at
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= '2026-01-15 10:20:00'
AND (
    JSON_EXTRACT(payload, '$.from') LIKE '%554796164699%'
    OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796164699%'
)
ORDER BY created_at DESC;
```

**Possíveis problemas:**
- Número salvo com sufixo @c.us (ex: "554796164699@c.us")
- Número salvo sem 9º dígito quando deveria ter (ou vice-versa)
- Normalização inconsistente entre evento e conversa
- Número não encontrado na busca por LIKE devido a formatação diferente

---

## 4. Hipóteses Prováveis (Ordenadas por Probabilidade)

### Hipótese 1: Webhook não chegou ao Hub (Gateway não enviou)

**Por que faz sentido:**
- Se não há log `[HUB_WEBHOOK_IN]` no servidor, webhook não chegou
- Gateway pode estar com problema de conectividade ou configuração
- VPS pode estar bloqueando requisições do gateway

**Como confirmar/refutar:**
1. Verificar logs do servidor web (Apache/Nginx) para requisições POST ao endpoint
2. Verificar logs do PHP (`logs/pixelhub.log` ou `error_log`) procurando por `[HUB_WEBHOOK_IN]`
3. Verificar se há eventos no banco com `created_at` próximo ao horário do teste
4. Se não há evidência de chegada, problema está no Gateway/VPS

**Qual seria o fix:**
- Verificar configuração do webhook no Gateway
- Verificar firewall/iptables na VPS
- Verificar DNS/resolução de nome do endpoint
- Adicionar monitoramento de webhooks (healthcheck)

---

### Hipótese 2: Evento foi ingerido mas conversa não foi criada/atualizada

**Por que faz sentido:**
- Se há evento em `communication_events` mas não há conversa em `conversations`, problema está na resolução de conversa
- `ConversationService::resolveConversation()` pode ter falhado silenciosamente
- `contact_external_id` pode não ter sido extraído corretamente do payload

**Como confirmar/refutar:**
1. Verificar se há evento em `communication_events` com `event_type = 'whatsapp.inbound.message'` e `created_at` próximo ao horário
2. Verificar se há conversa correspondente em `conversations` com `contact_external_id` normalizado
3. Verificar logs `[DIAGNOSTICO] ConversationService::resolveConversation()` para ver se método foi chamado
4. Verificar se `extractChannelInfo()` retornou `null` (log `[CONVERSATION UPSERT] ERRO: extractChannelInfo retornou NULL`)

**Qual seria o fix:**
- Corrigir extração de `contact_external_id` do payload (suportar mais formatos)
- Adicionar fallback para normalização de número
- Garantir que `resolveConversation()` não falhe silenciosamente (logar erros)
- Adicionar job de reconciliação para criar conversas de eventos órfãos

---

### Hipótese 3: Conversa foi criada mas não aparece na UI (filtros/polling)

**Por que faz sentido:**
- Se há conversa no banco mas não aparece na lista, problema está na query da UI ou no polling
- Filtros podem estar excluindo conversas com `status = 'new'` ou `tenant_id = NULL`
- Polling pode não estar detectando atualizações

**Como confirmar/refutar:**
1. Verificar se há conversa em `conversations` com `contact_external_id` normalizado e `last_message_at` atualizado
2. Executar query manual da lista (simular `getConversationsList()`) e verificar se conversa aparece
3. Verificar logs `[LOG TEMPORARIO] CommunicationHub::getConversationsList() - RETORNO FINAL`
4. Verificar se filtros na UI estão corretos (Canal=WhatsApp, Status=Ativas, Cliente=Todos)

**Qual seria o fix:**
- Ajustar filtro de status para incluir `status = 'new'`
- Ajustar filtro de tenant para permitir `tenant_id = NULL` quando filtro = "Todos"
- Corrigir polling para usar `after_timestamp` correto
- Adicionar refresh manual na UI

---

### Hipótese 4: Mensagem foi descartada por deduplicação (idempotência)

**Por que faz sentido:**
- Se `idempotency_key` foi calculado incorretamente, mensagem pode ser descartada como duplicata
- Gateway pode estar enviando webhook múltiplas vezes (retry)
- Chave de idempotência pode estar baseada em campos que variam

**Como confirmar/refutar:**
1. Verificar logs `[HUB_MSG_DROP] DROP_DUPLICATE` para ver se mensagem foi descartada
2. Verificar se há evento com `idempotency_key` duplicado na tabela
3. Verificar cálculo de `idempotency_key` (formato: `source_system:event_type:external_id` ou hash)
4. Comparar `idempotency_key` de eventos recentes para ver se há padrão de colisão

**Qual seria o fix:**
- Ajustar cálculo de `idempotency_key` para usar campos estáveis (messageId do WhatsApp)
- Adicionar log detalhado quando mensagem é descartada (incluir motivo)
- Adicionar métrica de mensagens descartadas vs. ingeridas
- Revisar lógica de deduplicação para não descartar mensagens legítimas

---

### Hipótese 5: Normalização de número falhou (9º dígito, @c.us, etc.)

**Por que faz sentido:**
- Número pode estar sendo salvo com formatação diferente (com/sem 9º dígito, com/sem @c.us)
- Busca por LIKE pode não encontrar número devido a variação
- Normalização inconsistente entre evento e conversa

**Como confirmar/refutar:**
1. Verificar `contact_external_id` na conversa (deve estar em E.164, apenas dígitos)
2. Verificar número no payload do evento (pode estar com @c.us, @lid, etc.)
3. Verificar se normalização está removendo sufixos corretamente
4. Comparar número normalizado do evento com `contact_external_id` da conversa

**Qual seria o fix:**
- Garantir que `PhoneNormalizer::toE164OrNull()` remove sufixos antes de normalizar
- Adicionar suporte para variação do 9º dígito (buscar conversa equivalente)
- Usar busca mais robusta (LIKE com padrões múltiplos)
- Adicionar log de normalização para debug

---

## 5. Testes Mínimos Reproduzíveis (MVR)

### Teste 1: Mensagem recebida → aparece no banco em X segundos

**Objetivo:** Verificar se webhook chegou e evento foi persistido.

**Passos:**
1. Enviar mensagem de teste do WhatsApp para o número conectado
2. Aguardar 5 segundos
3. Executar query:
```sql
SELECT 
    event_id,
    event_type,
    source_system,
    tenant_id,
    status,
    created_at,
    JSON_EXTRACT(payload, '$.from') as from_number,
    JSON_EXTRACT(payload, '$.text') as message_text
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
ORDER BY created_at DESC
LIMIT 1;
```

**Critério de sucesso:**
- Query retorna 1 registro
- `event_type = 'whatsapp.inbound.message'`
- `status = 'queued'` ou `status = 'processed'`
- `from_number` contém o número de origem
- `message_text` contém o texto da mensagem

**Se falhar:** Webhook não chegou ou falhou na ingestão. Verificar logs do servidor.

---

### Teste 2: Mensagem recebida → aparece na UI sem refresh

**Objetivo:** Verificar se conversa foi criada/atualizada e aparece na lista.

**Passos:**
1. Abrir Pixel Hub → Communication Hub
2. Filtrar: Canal=WhatsApp, Status=Ativas, Cliente=Todos
3. Enviar mensagem de teste do WhatsApp
4. Aguardar 10 segundos (polling automático)
5. Verificar se conversa aparece na lista (sem refresh manual)

**Critério de sucesso:**
- Conversa aparece na lista dentro de 10 segundos
- Conversa mostra número de origem correto
- Conversa mostra "Sem tenant" ou nome do tenant (se mapeado)
- Contador de não lidas incrementa

**Se falhar:** Verificar se conversa existe no banco (Teste 1) e se filtros estão corretos.

---

### Teste 3: Mensagem recebida → cai no tenant/canal correto (ou "Sem tenant" consistente)

**Objetivo:** Verificar se roteamento de tenant está correto.

**Passos:**
1. Verificar qual `channel_id` está configurado em `tenant_message_channels` para o número de teste
2. Enviar mensagem de teste do WhatsApp
3. Executar query:
```sql
SELECT 
    ce.event_id,
    ce.tenant_id as event_tenant_id,
    c.id as conversation_id,
    c.tenant_id as conversation_tenant_id,
    c.contact_external_id,
    tmc.tenant_id as channel_tenant_id,
    tmc.channel_id
FROM communication_events ce
LEFT JOIN conversations c ON c.contact_external_id LIKE CONCAT('%', JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '%')
LEFT JOIN tenant_message_channels tmc ON JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = tmc.channel_id
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
ORDER BY ce.created_at DESC
LIMIT 1;
```

**Critério de sucesso:**
- `event_tenant_id` = `channel_tenant_id` (se channel mapeado) ou `NULL` (se não mapeado)
- `conversation_tenant_id` = `event_tenant_id` (consistente)
- Se `tenant_id = NULL`, UI mostra "Sem tenant" consistentemente

**Se falhar:** Verificar resolução de tenant em `resolveTenantByChannel()` e mapeamento de canais.

---

## 6. Conclusão: Para Onde Seguir Agora

### Se evidência aponta que Hub nem recebeu request:

**Ação:** Voltar para VPS/Gateway com testes direcionados.

**Próximos passos:**
1. Verificar logs do Gateway (se acessível) para ver se webhook foi enviado
2. Verificar logs da VPS (Apache/Nginx) para ver se requisição chegou
3. Verificar firewall/iptables na VPS
4. Verificar configuração do webhook no Gateway (URL, secret, etc.)
5. Testar endpoint manualmente (curl POST para `/api/whatsapp/webhook`)

**Arquivos a verificar:**
- Logs do Apache/Nginx (access.log, error.log)
- Logs do Gateway (se disponível)
- Configuração do webhook no Gateway

---

### Se Hub recebeu, mas não persistiu/mostrou:

**Ação:** Focar em Pixel Hub (backend/persistência/UI/polling).

**Próximos passos:**
1. Verificar logs `[HUB_WEBHOOK_IN]` e `[HUB_MSG_SAVE]` para identificar onde quebrou
2. Verificar se evento está em `communication_events` (se sim, problema é na resolução de conversa)
3. Verificar se conversa está em `conversations` (se sim, problema é na UI/polling)
4. Executar queries de diagnóstico (seções C-J do checklist)
5. Corrigir problema identificado e re-testar

**Arquivos a verificar:**
- `logs/pixelhub.log` ou logs do PHP
- Tabela `communication_events`
- Tabela `conversations`
- Código de `ConversationService` e `CommunicationHubController`

---

### Se logs não existem ou são insuficientes:

**Ação:** Propor logs mínimos a serem adicionados (sem implementar ainda).

**Logs necessários:**
1. **HUB_WEBHOOK_IN** (já existe, linha 74 de WhatsAppWebhookController.php) ✅
2. **HUB_MSG_SAVE** (já existe, linhas 157-204 de EventIngestionService.php) ✅
3. **HUB_CONV_MATCH** (já existe, linhas 79-159 de ConversationService.php) ✅
4. **HUB_UI_POLL** (adicionar em CommunicationHubController::getConversationsList, linha ~1393) ⚠️
5. **HUB_MSG_DROP** (já existe, linha 75 de EventIngestionService.php) ✅

**Pontos exatos no código para adicionar logs:**
- `src/Controllers/CommunicationHubController.php:1393` - Adicionar log `[HUB_UI_POLL]` antes de retornar lista
- `src/Controllers/CommunicationHubController.php:1511` - Adicionar log `[HUB_UI_CHECK]` antes de verificar novas mensagens

---

## 7. Anexos

### Estrutura de Tabelas Relevantes

**communication_events:**
- `event_id` (UUID único)
- `idempotency_key` (chave de deduplicação)
- `event_type` (ex: 'whatsapp.inbound.message')
- `source_system` (ex: 'wpp_gateway')
- `tenant_id` (pode ser NULL)
- `payload` (JSON com dados do evento)
- `metadata` (JSON com metadados)
- `status` ('queued', 'processing', 'processed', 'failed')
- `created_at` (timestamp de criação)

**conversations:**
- `id` (PK)
- `conversation_key` (chave única: `{channel_type}_{channel_account_id}_{contact_external_id}`)
- `channel_type` ('whatsapp', 'email', etc.)
- `channel_account_id` (FK para tenant_message_channels, pode ser NULL)
- `contact_external_id` (número normalizado E.164)
- `contact_name` (nome do contato)
- `tenant_id` (FK para tenants, pode ser NULL)
- `status` ('new', 'open', 'pending', 'closed', 'archived')
- `last_message_at` (timestamp da última mensagem)
- `last_message_direction` ('inbound', 'outbound')
- `message_count` (contador de mensagens)
- `unread_count` (contador de não lidas)

**tenant_message_channels:**
- `id` (PK)
- `tenant_id` (FK para tenants)
- `provider` ('wpp_gateway')
- `channel_id` (ID do canal no gateway)
- `is_enabled` (boolean)

---

### Formato Esperado do Payload do Webhook

```json
{
  "event": "message",
  "from": "554796164699@c.us",
  "message": {
    "from": "554796164699@c.us",
    "id": "3EB0...",
    "timestamp": 1705315200,
    "text": "teste real 07:27 horário de SP"
  },
  "session": {
    "id": "channel_id_aqui"
  },
  "channel": "channel_id_aqui",
  "timestamp": 1705315200
}
```

**Nota:** Formato pode variar dependendo do Gateway. Verificar logs `[WHATSAPP INBOUND RAW]` para formato real.

---

## 8. 🔴 DESCOBERTA CRÍTICA: Fila não está sendo consumida

**Data da descoberta:** 2026-01-15  
**Status:** ✅ **CONFIRMADO - CAUSA RAIZ IDENTIFICADA**

### Evidência

**Query executada no banco remoto do Pixel Hub:**
```sql
SELECT
  event_id,
  event_type,
  source_system,
  tenant_id,
  status,
  retry_count,
  max_retries,
  next_retry_at,
  created_at,
  updated_at,
  processed_at,
  LEFT(error_message, 180) AS error_preview
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
ORDER BY created_at DESC
LIMIT 5;
```

**Resultado dos últimos 5 eventos:**
- **Todos os 5 eventos** têm:
  - `status = 'queued'`
  - `retry_count = 0`
  - `next_retry_at = NULL`
  - `updated_at = created_at` (nunca foram atualizados)
  - `processed_at = NULL`

**Estatísticas gerais:**
```sql
SELECT
  status,
  COUNT(*) AS total,
  MIN(created_at) AS oldest,
  MAX(created_at) AS newest
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
GROUP BY status
ORDER BY total DESC;
```

**Resultado:**
- `queued`: **154 eventos** (mais antigo: 2026-01-08 22:38:57, mais recente: 2026-01-15 12:48:21)
- `processed`: **3 eventos** (todos em 2026-01-13)

### Análise do Código

**O que encontramos:**

1. **`EventIngestionService::ingest()`** apenas **insere** eventos com status `'queued'`:
   - Linha 173: `INSERT INTO communication_events ... VALUES (..., 'queued', ...)`
   - Não processa eventos, apenas os ingere na fila

2. **`EventRouterService::route()`** atualiza status para `'processing'` e depois `'processed'`:
   - Linha 45: `EventIngestionService::updateStatus($normalizedEvent['event_id'], 'processing');`
   - Linha 69: `EventIngestionService::updateStatus($normalizedEvent['event_id'], 'processed');`
   - **MAS:** Este método só é chamado quando um evento é **roteado**, não há um worker que busca eventos `'queued'` e os processa

3. **Não existe worker/processador de fila:**
   - Não há cron job
   - Não há script agendado
   - Não há endpoint que processa eventos `'queued'`
   - Não há worker assíncrono

### Conclusão

**Causa raiz confirmada:** Não há nenhum processo (worker, cron job, ou script agendado) que consome a fila de eventos `'queued'` da tabela `communication_events`. 

Os eventos são inseridos com status `'queued'` pelo `EventIngestionService::ingest()`, mas nunca são processados porque:
- Não há código que busca eventos com `status = 'queued'`
- Não há código que atualiza `status` de `'queued'` para `'processing'`
- Não há código que chama `EventRouterService::route()` para eventos da fila

### Impacto

- **154 eventos** aguardando processamento desde 2026-01-08
- Mensagens WhatsApp recebidas não aparecem no painel porque eventos não são processados
- Conversas não são criadas/atualizadas porque `resolveConversation()` nunca é chamado para eventos da fila

### Solução Necessária

**Opção 1: Worker/Queue Processor (Recomendado)**
Criar um script PHP que:
1. Busca eventos com `status = 'queued'` e `next_retry_at IS NULL OR next_retry_at <= NOW()`
2. Atualiza status para `'processing'`
3. Chama `EventRouterService::route()` ou `ConversationService::resolveConversation()`
4. Atualiza status para `'processed'` ou `'failed'` conforme resultado
5. Executa via cron job a cada X segundos/minutos

**Opção 2: Processamento Síncrono (Temporário)**
Modificar `EventIngestionService::ingest()` para processar eventos imediatamente após inserção (não recomendado para produção, pode causar timeouts).

**Opção 3: Webhook Processa Imediatamente**
Modificar `WhatsAppWebhookController` para processar evento imediatamente após ingestão (similar à Opção 2).

### Scripts Existentes (Apenas para Processamento Manual)

- `database/process-queued-event.php` - Processa um evento específico manualmente
- `database/process-latest-servpro-event.php` - Processa evento ServPro específico

**Nota:** Estes scripts são apenas para diagnóstico/manutenção manual, não são executados automaticamente.

---

**Fim do documento de auditoria.**

