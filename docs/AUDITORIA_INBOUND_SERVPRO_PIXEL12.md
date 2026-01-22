# Auditoria: Inbound ServPro → Pixel12 Digital (Não Aparece no Hub)

**Data:** 2025-01-XX  
**Cenário:** Mensagem do ServPro (final 4223) para Pixel12 Digital aparece no WhatsApp Web, mas não entra no Pixel Hub  
**Status:** Investigação (sem implementação)

---

## 📌 Resumo Executivo

### Problema Confirmado
- ✅ **Outbound funciona:** Pixel12 → 4699 (validado)
- ❌ **Inbound não funciona:** ServPro 4223 → Pixel12 Digital (mensagem não aparece no Hub)
- **Evidência:** Mensagem existe no WhatsApp Web, mas não aparece em:
  - Lista de conversas
  - Thread ao abrir ServPro
  - Banco de dados (`communication_events`)

### Conclusão Preliminar
**O problema NÃO é de UI, ordenação ou polling.**  
**O problema é de INGESTÃO do inbound (webhook) para este caso específico.**

O evento não está sendo salvo no banco, indicando que:
1. O webhook não está chegando ao Hub, OU
2. O webhook está chegando mas sendo descartado antes de salvar

---

## 🔍 Fluxo Completo do Inbound (Gateway → Hub → Banco)

### 1. Gateway (WPP Gateway)
**Responsabilidade:** Receber mensagem do WhatsApp e enviar webhook para o Hub

**Endpoint esperado:** `POST /api/whatsapp/webhook`

**O que deve acontecer:**
- Gateway recebe mensagem do WhatsApp
- Gateway identifica o canal (session.id / channel_id)
- Gateway faz POST para o Hub com payload contendo:
  - `event`: tipo de evento (ex: `"message"`)
  - `from`: número do remetente
  - `session.id` ou `channel`: identificador do canal
  - `message`: dados da mensagem

**Ponto de falha possível:**
- Gateway não está emitindo webhook para este canal específico
- Gateway não está identificando corretamente o canal Pixel12 Digital
- Webhook está sendo enviado para URL incorreta

---

### 2. Webhook Controller (`WhatsAppWebhookController::handle()`)

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php`  
**Rota:** `POST /api/whatsapp/webhook`

#### Fluxo de Processamento:

```
1. Recebe payload bruto (php://input)
2. Faz log detalhado: [WHATSAPP INBOUND RAW]
3. Valida secret (se configurado)
4. Valida JSON
5. Extrai event_type do payload
6. Mapeia event_type para tipo interno (mapEventType)
7. Extrai channel_id (múltiplas tentativas):
   - payload['channel']
   - payload['channelId']
   - payload['session']['id']
   - payload['session']['session']
   - payload['data']['session']['id']
   - payload['data']['session']['session']
   - payload['data']['channel']
8. Resolve tenant_id pelo channel_id (resolveTenantByChannel)
9. Chama EventIngestionService::ingest()
10. Retorna 200 OK
```

#### Pontos de Falha Identificados:

**A) Event Type não mapeado (linha 103-113)**
```php
$internalEventType = $this->mapEventType($eventType);
if (empty($internalEventType)) {
    // Retorna 200 mas não processa
    http_response_code(200);
    exit;
}
```
**Impacto:** Se o gateway enviar um `event_type` não mapeado, o evento é descartado silenciosamente.

**B) Channel ID não encontrado (linha 115-134)**
```php
$channelId = $payload['channel'] ?? $payload['channelId'] ?? ... ?? null;
if (!$channelId) {
    error_log('[WHATSAPP INBOUND RAW] AVISO: channel_id não encontrado...');
    // Continua processamento, mas tenant_id será NULL
}
```
**Impacto:** Se `channel_id` não for encontrado, `tenant_id` será `NULL`, mas o evento ainda é ingerido.

**C) Tenant ID não resolvido (linha 137-139)**
```php
$tenantId = $this->resolveTenantByChannel($channelId);
```
**Impacto:** Se `channel_id` não estiver cadastrado em `tenant_message_channels`, `tenant_id` será `NULL`.

**D) Exceção não capturada (linha 172-208)**
```php
catch (\RuntimeException $e) {
    // Loga e retorna 500
}
catch (\Exception $e) {
    // Loga e retorna 500
}
```
**Impacto:** Qualquer exceção não capturada quebra o fluxo, mas é logada.

#### Logs Esperados (se webhook chegar):

```
[WHATSAPP INBOUND RAW] Payload recebido: {...}
[WHATSAPP INBOUND RAW] Headers: {...}
[WHATSAPP INBOUND RAW] Payload completo (primeiros 2000 chars): ...
[WHATSAPP INBOUND RAW] Channel ID extraído: <channel_id ou NULL>
[WHATSAPP INBOUND RAW] Tenant ID resolvido: <tenant_id ou NULL>
```

**Se NÃO aparecer nenhum log `[WHATSAPP INBOUND RAW]`, o webhook não está chegando.**

---

### 3. Resolução de Tenant (`resolveTenantByChannel()`)

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php` (linha 239-274)

#### Fluxo:

```
1. Verifica se channelId está vazio → retorna NULL
2. Busca em tenant_message_channels:
   SELECT tenant_id 
   FROM tenant_message_channels 
   WHERE provider = 'wpp_gateway' 
   AND channel_id = ? 
   AND is_enabled = 1
3. Retorna tenant_id ou NULL
```

#### Pontos de Falha:

**A) Channel ID não cadastrado**
- Se o `channel_id` do payload não existir em `tenant_message_channels`, `tenant_id` será `NULL`
- O evento ainda é ingerido, mas sem `tenant_id`

**B) Channel desabilitado (`is_enabled = 0`)**
- Se o canal estiver desabilitado, não retorna `tenant_id`
- O evento ainda é ingerido, mas sem `tenant_id`

**C) Provider diferente**
- Se o `provider` não for `'wpp_gateway'`, não encontra o canal
- O evento ainda é ingerido, mas sem `tenant_id`

#### Logs Esperados:

```
[WHATSAPP INBOUND RAW] resolveTenantByChannel: buscando tenant_id para channel_id=<channel_id>
[WHATSAPP INBOUND RAW] Channels disponíveis no banco: [...]
[WHATSAPP INBOUND RAW] resolveTenantByChannel: resultado tenant_id=<tenant_id ou NULL>
```

---

### 4. Ingestão de Evento (`EventIngestionService::ingest()`)

**Arquivo:** `src/Services/EventIngestionService.php`

#### Fluxo:

```
1. Valida tabela communication_events existe
2. Valida campos obrigatórios (event_type, source_system, payload)
3. Gera event_id (UUID)
4. Calcula idempotency_key
5. Verifica idempotência (se já existe, retorna event_id existente)
6. Valida tenant_id (se fornecido, verifica se existe em tenants)
7. Serializa payload e metadata para JSON
8. INSERT INTO communication_events
9. Chama ConversationService::resolveConversation()
10. Retorna event_id
```

#### Pontos de Falha:

**A) Tabela não existe (linha 31-42)**
- Se `communication_events` não existir, lança `RuntimeException`
- **Impacto:** Webhook retorna 500, evento não é salvo

**B) Campos obrigatórios ausentes (linha 44-51)**
- Se `event_type`, `source_system` ou `payload` estiverem vazios, lança `InvalidArgumentException`
- **Impacto:** Webhook retorna 500, evento não é salvo

**C) Idempotência (linha 65-77)**
- Se evento já foi processado (mesma `idempotency_key`), retorna `event_id` existente
- **Impacto:** Evento duplicado é ignorado (comportamento esperado)

**D) Tenant ID inválido (linha 82-97)**
- Se `tenant_id` fornecido não existir em `tenants`, define como `NULL` e continua
- **Impacto:** Evento é salvo, mas sem `tenant_id`

**E) Erro de INSERT (linha 136-148)**
- Se houver erro de banco de dados, lança `RuntimeException`
- **Impacto:** Webhook retorna 500, evento não é salvo

**F) Exceção não capturada**
- Qualquer exceção não capturada quebra o fluxo
- **Impacto:** Webhook retorna 500, evento não é salvo

#### Logs Esperados:

```
[EventIngestion] Evento ingerido: whatsapp.inbound.message (event_id: ..., trace_id: ..., tenant_id: ...)
[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=..., event_type=..., tenant_id=...
```

**Se aparecer log de ingestão mas não aparecer no banco, houve erro de INSERT.**

---

### 5. Resolução de Conversa (`ConversationService::resolveConversation()`)

**Arquivo:** `src/Services/ConversationService.php`

#### Fluxo:

```
1. Verifica se é evento de mensagem (isMessageEvent)
2. Extrai informações do canal (extractChannelInfo)
3. Se extractChannelInfo retornar NULL → retorna NULL (early return)
4. Gera conversation_key
5. Busca conversa existente (findByKey)
6. Se encontrou → atualiza metadados (updateConversationMetadata)
7. Se não encontrou → busca conversa equivalente (findEquivalentConversation)
8. Se não encontrou → busca por contato apenas (findConversationByContactOnly)
9. Se não encontrou → cria nova conversa (createConversation)
10. Retorna conversa
```

#### Pontos de Falha Críticos:

**A) extractChannelInfo() retorna NULL (linha 52-61)**
```php
$channelInfo = self::extractChannelInfo($eventData);
if (!$channelInfo) {
    error_log('[CONVERSATION UPSERT] ERRO: extractChannelInfo retornou NULL...');
    return null; // EARLY RETURN - não cria/atualiza conversa
}
```
**Impacto:** Se `extractChannelInfo()` retornar `NULL`, a conversa não é criada/atualizada, mas o evento ainda é salvo em `communication_events`.

**Motivos possíveis para `extractChannelInfo()` retornar NULL:**
1. `event_type` não começa com `whatsapp.`, `email.` ou `webchat.`
2. `contact_external_id` não pode ser extraído do payload
3. Normalização de telefone falha

**B) extractChannelIdFromPayload() não encontra channel_id (linha 379-410)**
- Tenta múltiplas localizações no payload
- Se não encontrar, retorna `NULL`
- **Impacto:** Conversa é criada/atualizada, mas sem `channel_id`

**C) createConversation() falha (linha 484-538)**
- Se tabela `conversations` não existir, retorna `NULL`
- Se houver erro de INSERT, retorna `NULL`
- **Impacto:** Evento é salvo, mas conversa não é criada

#### Logs Esperados:

```
[DIAGNOSTICO] ConversationService::resolveConversation() - INICIADO: event_type=..., from=..., to=...
[CONVERSATION UPSERT] extractChannelInfo: INICIANDO - event_type=..., has_payload=...
[CONVERSATION UPSERT] extractChannelInfo: channelType detectado=...
[CONVERSATION UPSERT] extractChannelInfo: WhatsApp inbound - contactExternalId raw: ...
[CONVERSATION UPSERT] extractChannelInfo: contactExternalId normalizado: ...
[CONVERSATION UPSERT] Iniciando resolução de conversa: {...}
[CONVERSATION UPSERT] Conversa existente encontrada: conversation_id=...
[CONVERSATION UPSERT] updateConversationMetadata: last_message_at atualizado para ...
```

**Se aparecer log de `extractChannelInfo` mas não aparecer log de "Iniciando resolução", `extractChannelInfo()` retornou NULL.**

---

## 🔬 Queries SQL para Diagnóstico

### 1. Verificar se evento foi salvo em `communication_events`

```sql
-- Busca eventos inbound recentes (últimas 24h)
SELECT 
    event_id,
    event_type,
    source_system,
    tenant_id,
    created_at,
    JSON_EXTRACT(payload, '$.from') as from_number,
    JSON_EXTRACT(payload, '$.to') as to_number,
    JSON_EXTRACT(payload, '$.session.id') as session_id,
    JSON_EXTRACT(payload, '$.channel') as channel,
    JSON_EXTRACT(metadata, '$.channel_id') as metadata_channel_id
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC
LIMIT 50;
```

**Se não retornar nenhum registro, o evento não foi salvo (webhook não chegou ou foi descartado antes de salvar).**

### 2. Verificar se conversa foi criada/atualizada

```sql
-- Busca conversas do ServPro (final 4223)
SELECT 
    id,
    conversation_key,
    channel_type,
    channel_id,
    contact_external_id,
    contact_name,
    tenant_id,
    last_message_at,
    unread_count,
    message_count,
    created_at,
    updated_at
FROM conversations
WHERE contact_external_id LIKE '%4223'
OR contact_external_id LIKE '%554796164223%'
ORDER BY last_message_at DESC
LIMIT 10;
```

**Se não retornar nenhum registro ou `last_message_at` estiver desatualizado, a conversa não foi criada/atualizada.**

### 3. Verificar canais cadastrados

```sql
-- Lista todos os canais WhatsApp cadastrados
SELECT 
    id,
    tenant_id,
    provider,
    channel_id,
    is_enabled,
    created_at,
    updated_at
FROM tenant_message_channels
WHERE provider = 'wpp_gateway'
ORDER BY id DESC;
```

**Verificar se existe canal com `channel_id` correspondente ao payload do webhook.**

### 4. Verificar eventos por tenant

```sql
-- Busca eventos do tenant Pixel12 Digital (assumindo tenant_id conhecido)
SELECT 
    ce.event_id,
    ce.event_type,
    ce.tenant_id,
    ce.created_at,
    JSON_EXTRACT(ce.payload, '$.from') as from_number,
    JSON_EXTRACT(ce.metadata, '$.channel_id') as channel_id
FROM communication_events ce
WHERE ce.tenant_id = <TENANT_ID_PIXEL12>
AND ce.event_type = 'whatsapp.inbound.message'
AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
ORDER BY ce.created_at DESC;
```

**Substituir `<TENANT_ID_PIXEL12>` pelo ID real do tenant.**

### 5. Verificar logs de erro do PHP

```sql
-- Se houver tabela de logs (ajustar conforme estrutura real)
-- Ou verificar error_log do PHP diretamente
```

**Verificar logs do servidor para:**
- `[WHATSAPP INBOUND RAW]` - indica que webhook chegou
- `[EventIngestion]` - indica que evento foi ingerido
- `[CONVERSATION UPSERT]` - indica que resolução de conversa foi tentada
- `[DIAGNOSTICO]` - logs temporários de diagnóstico

---

## 🎯 Hipóteses e Probabilidades

### Hipótese 1: Gateway não está enviando webhook (ALTA - 80%)

**Evidências:**
- Mensagem aparece no WhatsApp Web (prova que chegou ao WhatsApp)
- Nenhum log `[WHATSAPP INBOUND RAW]` aparece
- Nenhum registro em `communication_events`

**Possíveis causas:**
1. Canal Pixel12 Digital não está configurado para emitir webhooks inbound
2. Gateway está filtrando inbound por canal/sessão
3. Webhook está sendo enviado para URL incorreta
4. Gateway não está conectado para receber inbound deste canal específico

**Como verificar:**
- Verificar logs do gateway (se acessível)
- Verificar configuração de webhook por canal no gateway
- Testar envio manual de webhook para o endpoint

---

### Hipótese 2: Webhook chega mas é descartado no Hub (MÉDIA - 60%)

**Evidências:**
- Logs `[WHATSAPP INBOUND RAW]` aparecem, mas evento não é salvo
- Ou logs aparecem mas `extractChannelInfo()` retorna NULL

**Possíveis causas:**
1. `event_type` não mapeado → `mapEventType()` retorna NULL
2. `channel_id` não encontrado no payload → `tenant_id` fica NULL
3. `extractChannelInfo()` retorna NULL → conversa não é criada/atualizada
4. Exceção não capturada quebra o fluxo antes de salvar

**Como verificar:**
- Verificar logs `[WHATSAPP INBOUND RAW]` para ver payload recebido
- Verificar se `event_type` está mapeado em `mapEventType()`
- Verificar se `channel_id` está presente no payload
- Verificar se `extractChannelInfo()` está retornando NULL

---

### Hipótese 3: Evento é salvo mas conversa não é criada/atualizada (MÉDIA - 50%)

**Evidências:**
- Registro existe em `communication_events`
- Mas não existe/atualiza em `conversations`

**Possíveis causas:**
1. `extractChannelInfo()` retorna NULL → early return
2. `contact_external_id` não pode ser extraído/normalizado
3. `createConversation()` falha silenciosamente
4. `updateConversationMetadata()` não atualiza `last_message_at`

**Como verificar:**
- Verificar se evento existe em `communication_events`
- Verificar logs `[CONVERSATION UPSERT]` para ver onde falha
- Verificar se `extractChannelInfo()` está retornando NULL

---

### Hipótese 4: Tenant ID não resolvido (MÉDIA - 50%)

**Evidências:**
- Evento é salvo com `tenant_id = NULL`
- Conversa é criada/atualizada, mas sem `tenant_id`
- Filtros por tenant não mostram a conversa

**Possíveis causas:**
1. `channel_id` do payload não existe em `tenant_message_channels`
2. Canal está desabilitado (`is_enabled = 0`)
3. `provider` não é `'wpp_gateway'`

**Como verificar:**
- Verificar se `channel_id` do payload existe em `tenant_message_channels`
- Verificar se canal está habilitado
- Verificar logs `[WHATSAPP INBOUND RAW] resolveTenantByChannel`

---

## 📋 Checklist de Validação

### 1. Verificar se webhook está chegando
- [ ] Verificar logs do servidor para `[WHATSAPP INBOUND RAW]`
- [ ] Se não aparecer, webhook não está chegando → **Hipótese 1**

### 2. Verificar payload recebido
- [ ] Se logs aparecerem, verificar payload completo
- [ ] Verificar `event_type` no payload
- [ ] Verificar `channel_id` / `session.id` no payload
- [ ] Verificar `from` no payload

### 3. Verificar mapeamento de evento
- [ ] Verificar se `event_type` está mapeado em `mapEventType()`
- [ ] Se não estiver mapeado, evento é descartado → **Hipótese 2**

### 4. Verificar resolução de tenant
- [ ] Verificar logs `[WHATSAPP INBOUND RAW] resolveTenantByChannel`
- [ ] Verificar se `channel_id` existe em `tenant_message_channels`
- [ ] Se não existir, `tenant_id` será NULL → **Hipótese 4**

### 5. Verificar ingestão de evento
- [ ] Verificar se evento foi salvo em `communication_events` (Query 1)
- [ ] Se não foi salvo, houve erro de INSERT → **Hipótese 2**

### 6. Verificar resolução de conversa
- [ ] Verificar logs `[CONVERSATION UPSERT]`
- [ ] Verificar se `extractChannelInfo()` retornou NULL
- [ ] Se retornou NULL, conversa não é criada/atualizada → **Hipótese 3**

### 7. Verificar criação/atualização de conversa
- [ ] Verificar se conversa existe/foi atualizada (Query 2)
- [ ] Se não existe/atualizada, `createConversation()` ou `updateConversationMetadata()` falhou → **Hipótese 3**

---

## 🔧 Próximos Passos (Sem Implementação)

1. **Verificar logs do servidor:**
   - Buscar por `[WHATSAPP INBOUND RAW]` nas últimas 24h
   - Se não aparecer, webhook não está chegando → investigar gateway

2. **Executar queries SQL:**
   - Query 1: Verificar se evento foi salvo
   - Query 2: Verificar se conversa foi criada/atualizada
   - Query 3: Verificar canais cadastrados

3. **Simular webhook manualmente:**
   - Fazer POST para `/api/whatsapp/webhook` com payload de teste
   - Verificar se é processado corretamente

4. **Verificar configuração do gateway:**
   - Verificar se canal Pixel12 Digital está configurado para emitir webhooks
   - Verificar URL do webhook configurada no gateway

---

## 📝 Notas Finais

- **Nenhuma implementação deve ser feita nesta etapa**
- **Foco em diagnóstico e documentação**
- **Todas as evidências devem ser coletadas antes de propor correções**
- **Logs temporários já existem e devem ser monitorados**

---

**Documento criado em:** 2025-01-XX  
**Próxima revisão:** Após coleta de evidências

