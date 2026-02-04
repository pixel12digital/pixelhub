# Diagnóstico: Inbox — duplicação de mensagem enviada + scroll inicial

**Data:** 29/01/2026  
**Atualizado:** 29/01/2026 — causa raiz backend confirmada

---

## 1. Duplicação de mensagem enviada

### 1.1 Causa raiz: backend (persistência)

**O backend grava 2 eventos distintos no banco para cada mensagem enviada.** A duplicação visual ocorre porque a API `thread-data` e `messages/new` retornam ambos os eventos, e o frontend exibe cada um (cada um tem `event_id` diferente).

| Origem | Arquivo | Momento | source_system | payload |
|--------|---------|---------|---------------|---------|
| **1** | `CommunicationHubController::send()` | Após envio bem-sucedido ao gateway | `pixelhub_operator` | `{ to, timestamp, channel_id, type, message }` — **sem** `message_id` |
| **2** | `WhatsAppWebhookController::handle()` | Webhook `message.sent` / `message` (fromMe) do gateway | `wpp_gateway` | Payload bruto do gateway (estrutura diferente) |

**Por que a idempotência não evita duplicação**

`EventIngestionService::calculateIdempotencyKey()` usa:

```
idempotency_key = sourceSystem + ":" + eventType + ":" + (externalId OU hash do payload)
```

- **source_system diferente** → `pixelhub_operator` vs `wpp_gateway` → chaves diferentes.
- **Payload interno** não inclui `message_id` no payload (está só em `metadata`); `calculateIdempotencyKey` só olha o payload.
- **Payload do webhook** tem estrutura diferente (raw, event, message.key.id, etc.).
- Resultado: duas chaves distintas → dois inserts em `communication_events`.

**Evidência:** `docs/INVESTIGACAO_INBOX_ROBSON_4234.md` (6.2) — "2 eventos outbound duplicados" na resposta da API.

### 1.2 Correções de frontend (já aplicadas)

Mesmo com deduplicação no frontend, se o backend retornar 2 eventos com `event_id` diferentes, ambos são exibidos. A deduplicação por `data-msg-id` só evita o mesmo evento ser inserido duas vezes.

- **Arquivo:** `views/layout/main.php`
- **Função:** `appendInboxMessages` (~linha 3733)
- Inclusão de `data-msg-id` em cada `.msg`.
- Fallback para mensagens sem `id`: chave composta `direction|content|timestamp` em `data-dedupe-key`.
- **Áudio (02/2026):** `msgId` inclui `msg.media?.event_id`; dedupe por `outbound|audio|media.url|timestamp`.

### 1.3 Correção aplicada (29/01/2026)

**Opção A — Idempotência unificada para outbound**

1. **CommunicationHubController::send()**  
   - Inclusão de `id` e `message_id` no payload do evento quando `$result['message_id']` existe (resposta do gateway).

2. **EventIngestionService::calculateIdempotencyKey()**  
   - Para `whatsapp.outbound.message`, uso de chave `whatsapp.outbound.message:{message_id}` (sem `source_system`).  
   - Extração de `message_id` de: `payload.id`, `payload.message_id`, `payload.message.key.id`, `payload.message.id`, `metadata.message_id`.  
   - Assim, o evento do send interno e o do webhook geram a mesma chave e o segundo é descartado.

**Observação:** Se o gateway não retornar `message_id` (ex.: WPPConnect em alguns casos), a deduplicação não ocorre e pode haver duplicação. O gateway deveria sempre retornar o ID da mensagem.

### 1.4 Correção adicional (04/02/2026) — Fallback quando message_id vazio

**Problema:** O gateway (WPPConnect) frequentemente não retorna `message_id`. A correção de 29/01 não evitava duplicação nesses casos.

**Solução:** Em `EventIngestionService::calculateIdempotencyKey()`, para `whatsapp.outbound.message` sem `message_id`, usar chave composta:
```
whatsapp.outbound.message:fallback:{to_normalizado}:{timestamp_bucket_60s}:{content_hash}
```

- `to`: extraído de `payload.to` (send) ou `payload.raw.payload.key.remoteJid` (webhook)
- `timestamp_bucket`: `floor(ts/60)*60` (janela de 60s)
- `content_hash`: md5 dos primeiros 500 chars do texto

Assim, send() e onselfmessage geram a mesma chave e o segundo é descartado.

---

## 2. Scroll inicial ao abrir conversa

### Comportamento esperado

- Ao abrir uma conversa, o scroll deve ir para o final (mensagem mais recente).
- O scroll deve ocorrer após o DOM estar pronto, evitando posicionamento incorreto.

### Onde era feito

- **Arquivo:** `views/layout/main.php`
- **Funções:** `renderInboxMessages` e `appendInboxMessages`
- **Problema:** `container.scrollTop = container.scrollHeight` era executado logo após `innerHTML`, antes do layout estar estável.

### Correção aplicada

- Uso de `requestAnimationFrame` duplo em `renderInboxMessages` para garantir que o scroll ocorra após o layout.
- Uso de `requestAnimationFrame` em `appendInboxMessages` para o scroll após inserção de novas mensagens.

### Nota sobre “primeira não lida”

O `thread-data` não retorna `last_read_message_id` nem `unread_from`. Existe apenas `unread_count` no nível da conversa. Por isso, o scroll sempre vai para o final. Para rolar até a primeira mensagem não lida, seria necessário suporte no backend.
