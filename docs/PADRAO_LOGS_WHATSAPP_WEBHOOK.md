# Padrão de Logs - WhatsApp Webhook (Pixel Hub)

## Objetivo

Garantir rastreabilidade completa de todas as mensagens recebidas via webhook, desde a entrada até a exibição na UI, com logs padronizados e facilmente filtráveis.

## Padrões de Log Implementados

### 1. HUB_WEBHOOK_IN - Entrada do Webhook

**Quando:** No início do handler do webhook, ANTES de qualquer validação.

**Formato:**
```
[HUB_WEBHOOK_IN] eventType={eventType} channel_id={channel_id} tenant_id={tenant_id} from={from} normalized_from={normalized_from} message_id={message_id} timestamp={timestamp} correlationId={correlationId} payload_hash={payload_hash}
```

**Campos:**
- `eventType`: Tipo do evento (ex: `message`, `message.ack`)
- `channel_id`: ID do canal (session.id do gateway)
- `tenant_id`: ID do tenant (se resolvido, senão NULL)
- `from`: Telefone bruto do payload
- `normalized_from`: Telefone normalizado (E.164)
- `message_id`: ID da mensagem (se existir)
- `timestamp`: Timestamp da mensagem
- `correlationId`: ID de correlação (se existir)
- `payload_hash`: Hash curto (8 chars) do JSON para deduplicação

**Exemplo:**
```
[HUB_WEBHOOK_IN] eventType=message channel_id=whatsapp_35 tenant_id=2 from=554796164699@c.us normalized_from=554796164699 message_id=3EB0123456789ABCDEF timestamp=1705234567 correlationId=NULL payload_hash=a1b2c3d4
```

---

### 2. HUB_PHONE_NORM - Normalização de Telefone

**Quando:** Toda vez que um telefone é normalizado.

**Formato:**
```
[HUB_PHONE_NORM] raw_from={raw_from} normalized_from={normalized_from} normalized_thread_id_candidate={normalized_thread_id_candidate} [reason={reason}]
```

**Campos:**
- `raw_from`: Telefone original (com @c.us, etc)
- `normalized_from`: Telefone normalizado (E.164) ou NULL
- `normalized_thread_id_candidate`: Mesmo que normalized_from (para match de thread)
- `reason`: Motivo quando normalized_from é NULL (opcional)

**Exemplos:**
```
[HUB_PHONE_NORM] raw_from=554796164699@c.us normalized_from=554796164699 normalized_thread_id_candidate=554796164699
[HUB_PHONE_NORM] raw_from=123 normalized_from=NULL reason=less_than_10_digits
```

---

### 3. HUB_CHANNEL_ID - Identificação de Canal

**Quando:** Ao extrair e validar channel_id do payload.

**Formato:**
```
[HUB_CHANNEL_ID] {status} [channel_id={channel_id}]
```

**Status:**
- `channel_id encontrado: {channel_id}` - Quando encontrado
- `MISSING_CHANNEL_ID` - Quando não encontrado (com detalhes do payload)

**Exemplos:**
```
[HUB_CHANNEL_ID] channel_id encontrado: whatsapp_35
[HUB_CHANNEL_ID] MISSING_CHANNEL_ID - channel_id não encontrado no payload. Payload keys: event, from, message
```

---

### 4. HUB_CONV_MATCH - Match de Conversa

**Quando:** Durante a resolução de conversa (find/create).

**Formatos:**

**Query:**
```
[HUB_CONV_MATCH] Query: {query_type} {params}
```

**Resultado:**
```
[HUB_CONV_MATCH] {result} [id={conversation_id}] [reason={reason}]
```

**Resultados possíveis:**
- `FOUND_CONVERSATION id={id} conversation_key={key}`
- `FOUND_EQUIVALENT_CONVERSATION id={id} original_contact={contact1} new_contact={contact2} reason=9th_digit_variation`
- `FOUND_SHARED_CONVERSATION id={id} reason=updating_shared_with_channel_account_id`
- `CREATED_CONVERSATION id={id} conversation_key={key}`
- `ERROR: Falha ao criar nova conversa conversation_key={key}`

**Exemplos:**
```
[HUB_CONV_MATCH] Query: findByKey conversation_key=whatsapp_2_554796164699 channel_type=whatsapp contact=554796164699 tenant_id=2
[HUB_CONV_MATCH] FOUND_CONVERSATION id=123 conversation_key=whatsapp_2_554796164699
[HUB_CONV_MATCH] CREATED_CONVERSATION id=456 conversation_key=whatsapp_2_5547999887766 channel_type=whatsapp contact=5547999887766
```

---

### 5. HUB_MSG_DROP - Deduplicação de Mensagens

**Quando:** Quando uma mensagem é descartada por duplicação.

**Formato:**
```
[HUB_MSG_DROP] DROP_DUPLICATE reason={reason} idempotency_key={key} existing_event_id={event_id} message_id={message_id} payload_hash={payload_hash}
```

**Reasons:**
- `idempotency_key_match` - Mensagem já processada (mesma idempotency_key)

**Exemplo:**
```
[HUB_MSG_DROP] DROP_DUPLICATE reason=idempotency_key_match idempotency_key=wpp_gateway:whatsapp.inbound.message:3EB0123456789ABCDEF existing_event_id=uuid-123 message_id=3EB0123456789ABCDEF payload_hash=a1b2c3d4
```

---

### 6. HUB_MSG_DIRECTION - Direção da Mensagem

**Quando:** Ao determinar se mensagem é inbound ou outbound.

**Formato:**
```
[HUB_MSG_DIRECTION] computed={direction} source={source} event_type={event_type}
```

**Valores:**
- `direction`: `received` (inbound) ou `outbound`
- `source`: `webhook` (veio do webhook) ou `send_api` (veio da API de envio)
- `event_type`: Tipo do evento interno (ex: `whatsapp.inbound.message`)

**Exemplo:**
```
[HUB_MSG_DIRECTION] computed=received source=webhook event_type=whatsapp.inbound.message
```

---

### 7. HUB_MSG_SAVE - Persistência de Mensagens

**Quando:** Antes e depois de salvar mensagem no banco.

**Formatos:**

**Tentativa de Insert:**
```
[HUB_MSG_SAVE] INSERT_ATTEMPT event_id={event_id} message_id={message_id} event_type={event_type} tenant_id={tenant_id} channel_id={channel_id} direction={direction}
```

**Sucesso:**
```
[HUB_MSG_SAVE_OK] event_id={event_id} id_pk={id_pk} message_id={message_id} conversation_id={conversation_id} channel_id={channel_id} created_at={created_at} direction={direction}
```

**Erro:**
```
[HUB_MSG_SAVE] INSERT_FAILED event_id={event_id} message_id={message_id} error={error_message} sql_state={sql_state}
```

**Exemplos:**
```
[HUB_MSG_SAVE] INSERT_ATTEMPT event_id=uuid-123 message_id=3EB0123456789ABCDEF event_type=whatsapp.inbound.message tenant_id=2 channel_id=whatsapp_35 direction=received
[HUB_MSG_SAVE_OK] event_id=uuid-123 id_pk=789 message_id=3EB0123456789ABCDEF conversation_id=123 channel_id=whatsapp_35 created_at=2026-01-14 15:30:45 direction=received
[HUB_MSG_SAVE] INSERT_FAILED event_id=uuid-123 message_id=3EB0123456789ABCDEF error=Duplicate entry sql_state=23000
```

---

### 8. INCOMING_MSG - Atualização da UI

**Quando:** Quando uma nova mensagem chega e é processada na UI.

**Formato:**
```
[INCOMING_MSG] thread={thread_id} activeThread={active_thread_id} action={action} message_id={message_id}
```

**Actions:**
- `append` - Mensagem adicionada ao thread ativo (aparece na tela)
- `listOnly` - Mensagem apenas atualiza lista (thread não está aberto)

**Exemplo:**
```
[INCOMING_MSG] thread=123 activeThread=123 action=append message_id=3EB0123456789ABCDEF
[INCOMING_MSG] thread=123 activeThread=456 action=listOnly message_id=3EB0123456789ABCDEF
```

---

## Como Filtrar Logs

### Por Tipo de Log
```bash
# Apenas entrada de webhook
grep "\[HUB_WEBHOOK_IN\]" /var/log/apache2/error.log

# Apenas match de conversa
grep "\[HUB_CONV_MATCH\]" /var/log/apache2/error.log

# Apenas mensagens descartadas
grep "\[HUB_MSG_DROP\]" /var/log/apache2/error.log

# Apenas persistência
grep "\[HUB_MSG_SAVE" /var/log/apache2/error.log
```

### Por Message ID
```bash
# Rastrear uma mensagem específica
grep "message_id=3EB0123456789ABCDEF" /var/log/apache2/error.log
```

### Por Thread/Conversation
```bash
# Rastrear uma conversa específica
grep "thread=123\|conversation_id=123" /var/log/apache2/error.log
```

### Por Telefone
```bash
# Rastrear um número específico
grep "normalized_from=554796164699\|contact=554796164699" /var/log/apache2/error.log
```

### Fluxo Completo de uma Mensagem
```bash
# Buscar todos os logs de uma mensagem (do webhook até a UI)
grep -E "\[HUB_WEBHOOK_IN\].*message_id=3EB0123456789ABCDEF|\[HUB_MSG_SAVE.*message_id=3EB0123456789ABCDEF|\[HUB_CONV_MATCH\].*|\[INCOMING_MSG\].*message_id=3EB0123456789ABCDEF" /var/log/apache2/error.log
```

---

## Critérios de Pronto (Definition of Done)

✅ **Nenhuma mensagem é descartada sem log explícito**
- Todo descarte gera log `[HUB_MSG_DROP]` com motivo

✅ **Mensagens não migram para conversa errada**
- Todo match de conversa gera log `[HUB_CONV_MATCH]` com query e resultado

✅ **Direção inbound/outbound correta**
- Todo evento gera log `[HUB_MSG_DIRECTION]` com direção computada

✅ **UI atualiza thread ativo em tempo real**
- Toda mensagem processada na UI gera log `[INCOMING_MSG]` com action (append/listOnly)

✅ **Número com 9º dígito e sem 9º dígito não conflitam**
- Normalização gera log `[HUB_PHONE_NORM]` com raw e normalized
- Match de conversa tenta equivalente quando necessário

---

## Arquivos Modificados

1. **src/Controllers/WhatsAppWebhookController.php**
   - Adicionado log `[HUB_WEBHOOK_IN]` no início
   - Adicionado log `[HUB_CHANNEL_ID]` para validação de canal

2. **src/Services/PhoneNormalizer.php**
   - Adicionado log `[HUB_PHONE_NORM]` antes/depois da normalização

3. **src/Services/ConversationService.php**
   - Adicionado log `[HUB_CONV_MATCH]` em todas as queries e resultados

4. **src/Services/EventIngestionService.php**
   - Adicionado log `[HUB_MSG_DROP]` para deduplicação
   - Adicionado log `[HUB_MSG_DIRECTION]` para direção
   - Adicionado log `[HUB_MSG_SAVE]` e `[HUB_MSG_SAVE_OK]` para persistência

5. **views/communication_hub/index.php**
   - Adicionado log `[INCOMING_MSG]` na função `onNewMessagesFromPanel()`

---

## Testes Recomendados

### Teste A - Mensagem Real WhatsApp > Hub
1. Enviar mensagem pelo WhatsApp para número conectado
2. Verificar logs:
   - `[HUB_WEBHOOK_IN]` deve aparecer
   - `[HUB_CONV_MATCH]` deve mostrar FOUND ou CREATED
   - `[HUB_MSG_SAVE_OK]` deve aparecer
   - `[INCOMING_MSG]` deve aparecer com action=append ou listOnly

### Teste B - Número Problemático
1. Enviar mensagem de número que falha (ex: 46999)
2. Verificar logs:
   - `[HUB_PHONE_NORM]` deve mostrar normalização
   - `[HUB_CONV_MATCH]` deve mostrar se encontrou conversa equivalente
   - Se houver `[HUB_MSG_DROP]`, verificar motivo

### Teste C - Multi-canal
1. Enviar mensagem em 2 canais diferentes
2. Verificar logs:
   - `[HUB_CHANNEL_ID]` deve mostrar channel_id diferente
   - `[HUB_CONV_MATCH]` deve criar conversas separadas (não misturar)

---

## Notas Importantes

- Todos os logs usam `error_log()` do PHP (vão para o log do servidor web)
- Logs do frontend (JavaScript) usam `console.log()` e aparecem no console do navegador
- Padrões de log são consistentes e facilmente filtráveis com grep
- Logs não incluem dados sensíveis (apenas IDs e hashes)

