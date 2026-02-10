# Diagnóstico: áudios não aparecem no Inbox + 404 "Conversa não encontrada"

**Situações:**
1. Áudios enviados por Charles Dietrich para **Pixel 12 Digital** e para **ImobSites** (ex.: 13:20, 13:33) **não aparecem** no Inbox.
2. Ao clicar em uma conversa no Inbox: **404** em `/communication-hub/thread-data?thread_id=whatsapp_158` e erro **"Conversa não encontrada"**.

**Correção no frontend (já aplicada):** quando o `thread-data` retorna 404 ou "Conversa não encontrada", o Inbox **atualiza a lista** automaticamente (para remover o item obsoleto, ex.: whatsapp_158) e exibe "Conversa não encontrada. Lista atualizada."

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

Sugestão: começar pelos logs do Hub no horário do envio (ex.: 13:18–13:25 ou 13:30–13:38). Se não houver nenhum `[HUB_WEBHOOK_IN]`, o próximo passo é conferir no gateway (VPS) se o webhook está configurado e sendo chamado para as duas sessões.

---

## 5. Por que dá 404 em thread-data (ex.: whatsapp_158)

- O Inbox lista conversas a partir da tabela **conversations** (`thread_id` = `whatsapp_{id}`).
- Ao clicar, o front chama **GET /communication-hub/thread-data?thread_id=whatsapp_158**, que busca **conversations.id = 158**.
- Se não existir linha com **id = 158**, o backend retorna **404 – Conversa não encontrada**.

Isso pode ocorrer se:
- A conversa foi **excluída** depois que a lista foi carregada.
- A lista estava **em cache** ou desatualizada e exibia um `thread_id` que nunca existiu ou já foi removido.
- Houve **merge/limpeza** de conversas e o id 158 deixou de existir.

Com a correção no frontend, ao receber 404 o Inbox **recarrega a lista** e o item que não existe mais deixa de aparecer.

---

## 6. Checklist definitivo (corrigir de vez)

| # | Onde | O que fazer |
|---|------|-------------|
| 1 | **VPS (gateway)** | Confirmar que a **URL do webhook** do Hub está configurada e é chamada para **todas** as sessões (Pixel 12 Digital e ImobSites). |
| 2 | **VPS** | Garantir que cada POST do webhook inclui **sessionId** (ou session.id / session.session) no payload com o nome da sessão. |
| 3 | **Hub (HostMídia)** | Verificar **logs PHP** no horário do teste: existem linhas `[HUB_WEBHOOK_IN]`? Se não, o gateway não está chegando ao Hub. |
| 4 | **Hub** | Se há `[HUB_WEBHOOK_IN]`, ver se em seguida há erro (403, 400, exceção) ou `INSERT REALIZADO`. |
| 5 | **Hub – banco** | Rodar as queries da seção 2.2 para o horário do teste: há eventos em **communication_events**? Há conversas em **conversations**? |
| 6 | **Hub** | Conferir **tenant_message_channels**: existem linhas com **channel_id** = Pixel 12 Digital e ImobSites (ou normalizado), **is_enabled = 1**? |
| 7 | **Frontend** | Após o deploy, ao clicar em conversa inexistente, a lista deve atualizar sozinha (correção já no código). |

Causa mais comum quando **nada** chega no Inbox: o gateway **não está enviando** o webhook para o Hub (URL errada, só uma sessão configurada, ou serviço do gateway não dispara para essas sessões).
