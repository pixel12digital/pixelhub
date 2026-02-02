# Diagnóstico: Inbox — duplicação de mensagem enviada + scroll inicial

**Data:** 29/01/2026

---

## 1. Duplicação de mensagem enviada

### Onde acontece

**Na renderização (frontend), não na persistência.** O backend salva 1 registro por envio. A duplicação visual ocorre porque:

1. **Optimistic UI** adiciona a mensagem ao DOM antes do envio (`sendInboxMessage`).
2. **Polling** (5s) chama `fetchInboxNewMessages` → `appendInboxMessages` com as mensagens retornadas pela API.
3. Quando o poll retorna a mensagem enviada, `appendInboxMessages` remove a otimista e adiciona a real.
4. **Causa da duplicação:** `appendInboxMessages` pode ser chamada duas vezes com a mesma mensagem (race entre polls ou API retornando duplicata), ou a otimista não é removida em algum fluxo e a real é adicionada em seguida.

### Arquivo/trecho

- **Arquivo:** `views/layout/main.php`
- **Função:** `appendInboxMessages` (~linha 3733)
- **Problema:** Não havia deduplicação por `event_id`; mensagens idênticas eram adicionadas mais de uma vez.

### Correção aplicada

- Inclusão de `data-msg-id` em cada `.msg` (em `renderInboxMessages` e `appendInboxMessages`).
- Antes de inserir, checagem se já existe `.msg[data-msg-id="X"]` no container; em caso positivo, a mensagem é ignorada.
- Fallback para mensagens sem `id`: chave composta `direction|content|timestamp` em `data-dedupe-key`.
- **Áudio (02/2026):** `msgId` passa a incluir `msg.media?.event_id`. Para outgoing audio sem id, dedupe por `outbound|audio|media.url|timestamp`. Guard contra `fetchInboxNewMessages` concorrente. Optimistic recebe `data-msg-id` do `event_id` retornado pelo send.

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
