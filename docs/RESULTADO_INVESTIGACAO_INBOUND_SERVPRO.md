# Resultado da Investigação: Inbound ServPro → Pixel12 Digital

**Data:** 2026-01-14  
**Status:** ✅ **PROBLEMA IDENTIFICADO - NÃO É INGESTÃO**

---

## 🎯 Conclusão Principal

**O webhook ESTÁ chegando e os eventos ESTÃO sendo salvos corretamente.**

O problema **NÃO é de ingestão**, mas sim de **renderização/filtro na UI**.

---

## ✅ Evidências Coletadas

### 1. Eventos Estão Sendo Salvos

**Query executada:**
```sql
SELECT * FROM communication_events 
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
```

**Resultado:**
- ✅ **50 eventos inbound** encontrados nas últimas 24h
- ✅ **Múltiplos eventos do ServPro (4223)** identificados:
  - `from: "554796474223@c.us"`
  - `from: "5547996474223@c.us"` (com 9º dígito)
  - `from: "554796474223"` (sem @c.us)
- ✅ Todos com `tenant_id: 2` e `channel_id: "Pixel12 Digital"`

### 2. Conversa Existe e Foi Atualizada

**Query executada:**
```sql
SELECT * FROM conversations 
WHERE contact_external_id LIKE '%4223'
```

**Resultado:**
- ✅ **Conversa encontrada:**
  - `conversation_id: 34`
  - `contact_external_id: 554796474223`
  - `contact_name: ServPro`
  - `tenant_id: 2`
  - `last_message_at: 2026-01-14 13:21:47` ⚠️ **ATUALIZADO RECENTEMENTE**
  - `message_count: 22`
  - `unread_count: 0` ⚠️ **PROBLEMA: Deveria ter unread_count > 0**

### 3. Payload Completo Verificado

**Eventos do ServPro analisados:**
- ✅ Payload tem estrutura correta
- ✅ Campo `from` presente: `"554796474223@c.us"` ou `"554796474223"`
- ✅ Campo `message.text` presente com conteúdo
- ✅ Campo `session.id` presente: `"Pixel12 Digital"`
- ✅ Campo `event` presente: `"message"`

**Exemplo de payload válido:**
```json
{
    "event": "message",
    "session": {
        "id": "Pixel12 Digital"
    },
    "from": "554796474223@c.us",
    "message": {
        "id": "test_696691865b791",
        "from": "554796474223@c.us",
        "text": "Mensagem de teste do ServPro",
        "notifyName": "ServPro",
        "timestamp": 1768329606
    },
    "timestamp": 1768329606
}
```

### 4. Canal Está Cadastrado

**Query executada:**
```sql
SELECT * FROM tenant_message_channels 
WHERE provider = 'wpp_gateway'
```

**Resultado:**
- ✅ Canal encontrado:
  - `id: 1`
  - `tenant_id: 2`
  - `channel_id: Pixel12 Digital`
  - `is_enabled: SIM`
  - `webhook_configured: NÃO` ⚠️ (mas webhooks estão chegando mesmo assim)

---

## 🔴 Problemas Identificados

### Problema 1: `unread_count` está em 0

**Evidência:**
- Conversa tem `message_count: 22`
- Mas `unread_count: 0`
- Mensagens novas não estão incrementando `unread_count`

**Causa provável:**
- `ConversationService::updateConversationMetadata()` pode não estar incrementando `unread_count` corretamente
- Ou mensagens estão sendo marcadas como lidas automaticamente

**Impacto:**
- Badge não aparece (porque `unread_count = 0`)
- Conversa pode não aparecer no topo da lista (se ordenação depende de `unread_count`)

### Problema 2: Mensagens não aparecem no thread

**Evidência:**
- Eventos estão salvos em `communication_events`
- Conversa existe e foi atualizada
- Mas mensagens não aparecem na UI

**Causa provável:**
- Filtro de mensagens no `CommunicationHubController::getWhatsAppMessagesFromConversation()` pode estar excluindo mensagens
- Normalização de telefone pode estar falhando (variações: `554796474223`, `554796474223@c.us`, `5547996474223@c.us`)
- Query SQL pode não estar encontrando mensagens por problema de filtro

**Impacto:**
- Thread aparece vazio ou incompleto
- Usuário não vê mensagens recebidas

### Problema 3: Conversa pode não aparecer na lista

**Evidência:**
- Conversa existe no banco
- `last_message_at` está atualizado
- Mas pode não aparecer na lista da UI

**Causa provável:**
- Filtro por `tenant_id` pode estar excluindo
- Ordenação pode estar incorreta
- Query de lista pode ter problema

**Impacto:**
- Conversa não aparece na lista lateral
- Usuário não consegue acessar a conversa

---

## 🔍 Próximos Passos de Investigação

### 1. Verificar `unread_count`

**Query:**
```sql
-- Verificar se unread_count está sendo atualizado
SELECT 
    c.id,
    c.contact_external_id,
    c.unread_count,
    c.message_count,
    c.last_message_at,
    c.last_message_direction,
    COUNT(ce.event_id) as eventos_inbound_recentes
FROM conversations c
LEFT JOIN communication_events ce ON (
    JSON_EXTRACT(ce.payload, '$.from') LIKE CONCAT('%', REPLACE(c.contact_external_id, '@c.us', ''), '%')
    AND ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
)
WHERE c.id = 34
GROUP BY c.id;
```

**Verificar:**
- Se `eventos_inbound_recentes` > 0 mas `unread_count = 0`
- Se `last_message_direction` está como `'inbound'`

### 2. Verificar Mensagens no Thread

**Query:**
```sql
-- Verificar se mensagens estão sendo encontradas pela query do thread
SELECT 
    ce.event_id,
    ce.created_at,
    JSON_EXTRACT(ce.payload, '$.from') as from_raw,
    JSON_EXTRACT(ce.payload, '$.message.from') as from_message,
    JSON_EXTRACT(ce.payload, '$.message.text') as text
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
AND (
    JSON_EXTRACT(ce.payload, '$.from') LIKE '%4223%'
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%4223%'
)
AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
ORDER BY ce.created_at ASC
LIMIT 50;
```

**Verificar:**
- Se query encontra as mensagens
- Se normalização está funcionando corretamente

### 3. Verificar Filtros da Lista

**Query:**
```sql
-- Verificar se conversa aparece na query da lista
SELECT 
    c.id,
    c.conversation_key,
    c.contact_external_id,
    c.tenant_id,
    c.last_message_at,
    c.unread_count,
    c.status
FROM conversations c
WHERE c.channel_type = 'whatsapp'
AND c.tenant_id = 2
AND c.status NOT IN ('closed', 'archived')
ORDER BY c.last_message_at DESC
LIMIT 100;
```

**Verificar:**
- Se conversa do ServPro aparece nesta query
- Se ordenação está correta

---

## 📋 Resumo Executivo

### ✅ O que está funcionando:
1. Webhook está chegando ao Hub
2. Eventos estão sendo salvos em `communication_events`
3. Conversa está sendo criada/atualizada em `conversations`
4. Payload tem estrutura correta
5. Canal está cadastrado e habilitado

### ❌ O que não está funcionando:
1. `unread_count` não está sendo incrementado (badge não aparece)
2. Mensagens podem não estar aparecendo no thread (filtro/normalização)
3. Conversa pode não estar aparecendo na lista (filtro/ordenação)

### 🎯 Causa Raiz Provável:
**NÃO é problema de ingestão.**  
**É problema de:**
- Atualização de `unread_count` em `ConversationService::updateConversationMetadata()`
- Filtro/normalização de mensagens em `CommunicationHubController::getWhatsAppMessagesFromConversation()`
- Query/filtro de lista em `CommunicationHubController::getWhatsAppThreadsFromConversations()`

---

## 🔧 Recomendações

1. **Investigar `updateConversationMetadata()`:**
   - Verificar se `unread_count` está sendo incrementado corretamente
   - Verificar se `last_message_direction` está sendo setado como `'inbound'`

2. **Investigar filtro de mensagens:**
   - Verificar se normalização de telefone está funcionando para todas as variações
   - Verificar se query SQL está encontrando mensagens

3. **Investigar query de lista:**
   - Verificar se conversa aparece na query
   - Verificar se ordenação está correta

---

**Documento criado em:** 2026-01-14  
**Próxima ação:** Investigar problemas de renderização/filtro na UI

