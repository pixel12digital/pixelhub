# Diagnóstico: áudios 13:20 (Charles → Pixel 12 Digital e ImobSites) não aparecem no Inbox

**Situação:** Áudios enviados às 13:20 por Charles Dietrich para Pixel 12 Digital e para ImobSites não aparecem no Inbox do Hub.

---

## 1. Onde as mensagens entram no sistema

1. **Gateway (VPS)** recebe do WhatsApp e envia **POST** para a URL do webhook do Hub.
2. **Hub** recebe em `WhatsAppWebhookController`, extrai `channel_id` e `tenant_id`, chama **EventIngestionService::ingest**.
3. Ingestão grava em **communication_events** e chama **ConversationService::resolveConversation** para criar/atualizar linha em **conversations**.
4. O **Inbox** lista as conversas a partir da tabela **conversations**.

Se nada aparece, o problema está em (1) o gateway não enviar, (2) o Hub não receber/rejeitar, ou (3) a ingestão/resolução não criar conversa.

---

## 2. O que verificar no Hub (HostMídia)

### 2.1 Logs do PHP (por volta de 13:20)

Procurar no `error_log` do servidor (ou log que receba `error_log()` do PHP):

- **`[HUB_WEBHOOK_IN]`**  
  Se **não** aparecer nenhuma linha nesse horário → o gateway **não está chamando** o webhook do Hub (ou a requisição não chega ao PHP).

- **`[HUB_WEBHOOK_IN] eventType=message ...`**  
  Se aparecer → o Hub **recebeu** o evento. A partir daí verificar:
  - `channel_id` preenchido (Pixel 12 Digital / ImobSites)?
  - Linhas seguintes: `[WEBHOOK INSTRUMENTADO] ANTES DE INGERIR` e `INSERT REALIZADO` ou alguma mensagem de **erro/exceção**.

- **403 / Invalid webhook secret**  
  Gateway está enviando, mas o secret (header) não confere com `PIXELHUB_WHATSAPP_WEBHOOK_SECRET` no Hub.

- **400 / Invalid JSON, MISSING_EVENT_TYPE**  
  Payload ou tipo de evento inválido; o gateway pode estar enviando formato diferente.

- **`[HUB_MSG_DROP] DROP_DUPLICATE`**  
  Evento foi considerado duplicado e não gerou conversa nova (improvável para primeiro áudio).

- Qualquer **stack trace** ou **Exception** após `[HUB_WEBHOOK_IN]` indica falha na ingestão ou na resolução de conversa.

### 2.2 Banco de dados (Hub)

Rodar no banco do Hub (ajustar intervalo se necessário; exemplo para 13:15–13:25):

```sql
-- Eventos recebidos por volta de 13:20 (ajuste o fuso horário do servidor)
SELECT 
  ce.id,
  ce.event_id,
  ce.event_type,
  ce.tenant_id,
  ce.created_at,
  JSON_UNQUOTE(ce.metadata->>'$.channel_id') AS channel_id,
  LEFT(ce.payload, 300) AS payload_preview
FROM communication_events ce
WHERE ce.created_at >= '2026-02-10 13:15:00'
  AND ce.created_at <= '2026-02-10 13:25:00'
  AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
ORDER BY ce.created_at DESC;
```

- Se **não houver linhas** nesse intervalo → nenhum evento de mensagem foi gravado; ou o gateway não enviou, ou o Hub rejeitou antes de inserir (ver logs acima).
- Se **houver linhas** → verificar se `channel_id` está correto (Pixel 12 Digital / ImobSites) e se existe conversa correspondente:

```sql
-- Conversas criadas/atualizadas por volta de 13:20
SELECT 
  c.id,
  c.conversation_key,
  c.channel_id,
  c.contact_external_id,
  c.contact_name,
  c.tenant_id,
  c.last_message_at,
  c.created_at
FROM conversations c
WHERE c.channel_type = 'whatsapp'
  AND (c.last_message_at >= '2026-02-10 13:15:00' OR c.created_at >= '2026-02-10 13:15:00')
ORDER BY GREATEST(COALESCE(c.last_message_at, '1970-01-01'), COALESCE(c.created_at, '1970-01-01')) DESC
LIMIT 20;
```

Se houver eventos mas **nenhuma conversa** nesse horário, o problema está na resolução de conversa (ConversationService) ou em filtros/status que excluem a conversa da lista do Inbox.

---

## 3. Gateway (VPS) – com o Charles

O gateway precisa:

- Enviar **POST** para a URL do webhook do Hub para **cada** sessão (Pixel 12 Digital e ImobSites).
- Incluir no payload o **sessionId** (ou equivalente) com o nome da sessão, para o Hub preencher `channel_id`.
- Se houver secret configurado no Hub, enviar o header correto (ex.: `X-Webhook-Secret` ou `X-Gateway-Secret`).

Se o gateway só enviar para uma sessão ou não incluir o sessionId, as mensagens da outra sessão nunca chegam ou chegam sem canal e podem não gerar conversa no Inbox.

---

## 4. Resumo

| O que verificar | O que significa |
|-----------------|-----------------|
| Nenhum `[HUB_WEBHOOK_IN]` às 13:20 | Gateway não está chamando o Hub ou requisição não chega. |
| `[HUB_WEBHOOK_IN]` existe mas não há INSERT / há erro depois | Problema na ingestão ou na resolução de conversa (ver exceção nos logs). |
| Eventos na tabela `communication_events` mas sem conversa em `conversations` | Resolução de conversa não está criando/atualizando a linha. |
| Conversa existe no banco mas não aparece no Inbox | Filtro de status/sessão/tenant na listagem do Hub (ex.: `status`, `session_id`, `tenant_id`). |

Sugestão: começar pelos logs do Hub no horário 13:18–13:22. Se não houver nenhum `[HUB_WEBHOOK_IN]`, o próximo passo é conferir no gateway (VPS) se o webhook está configurado e sendo chamado para as duas sessões.
