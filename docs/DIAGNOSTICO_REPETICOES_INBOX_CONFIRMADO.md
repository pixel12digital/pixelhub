# Diagnóstico Confirmado: Repetições no Inbox

**Data:** 05/02/2026  
**Evidência:** Script `database/diagnostico-duplicados-inbox.php` executado no banco real

---

## 1. Causa raiz confirmada

**Cada mensagem enviada gera 2 eventos no banco** — um do `send()` (pixelhub_operator) e outro do webhook (wpp_gateway). A idempotência falha porque **as chaves são diferentes**.

### Exemplo real (Alessandra, 14:46)

| # | event_id | source_system | idempotency_key |
|---|----------|---------------|-----------------|
| 1 | ed4ce3e7-... | pixelhub_operator | `whatsapp.outbound.message:fallback:555381106484:1770313560:68f6f4752bbeae2e05273af97e6bf17e` |
| 2 | 7681c670-... | wpp_gateway | `whatsapp.outbound.message:true_555381106484@c.us_3EB0F89033D5AD38F4DC5E` |

**Mesmo conteúdo** ("MerchantId e MerchantKey são as credenciais..."), **chaves diferentes** → 2 inserts.

---

## 2. Por que as chaves são diferentes

| Origem | Tem message_id? | Chave usada |
|--------|-----------------|-------------|
| **send()** | Não (gateway não retorna no send) | Fallback: `fallback:{to}:{tsBucket}:{contentHash}` |
| **Webhook** | Sim (em `key.id` ou similar) | `whatsapp.outbound.message:{message_id}` |

O webhook traz um ID no formato `true_555381106484@c.us_3EB0F89033D5AD38F4DC5E` (composite: fromMe_remoteJid_messageId). O `send()` não recebe esse ID na resposta do gateway, então usa o fallback.

**Resultado:** chaves distintas → idempotência não evita duplicata.

---

## 3. Mensagens afetadas (conversa Alessandra, 05/02)

| Conteúdo (resumo) | pixelhub_operator | wpp_gateway | Total |
|-------------------|-------------------|-------------|-------|
| "Boa tarde, vou precisar do MerchantId e MerchantKey." | ✓ | ✓ | 2 |
| "Pode inserir as informações diretamente no Gateway..." | ✓ | ✓ (imagem) | 2 |
| "MerchantId e MerchantKey são as credenciais..." | ✓ | ✓ | 2 |
| "Você encontra as duas em: minhaconta2.cielo.com.br..." | ✓ | ✓ | 2 |
| "Se tiver dúvidas, pode falar com o Luiz..." | ✓ | ✓ | 2 |

**Total:** 10 eventos para 5 mensagens lógicas = **100% de duplicação**.

---

## 4. Formato das chaves

### send() (fallback)
```
whatsapp.outbound.message:fallback:555381106484:1770313560:68f6f4752bbeae2e05273af97e6bf17e
                         ^^^^^^^^  ^^^^^^^^^^^ ^^^^^^^^^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
                         fixo      to E164     ts bucket  md5(conteúdo)
```

### Webhook (com message_id)
```
whatsapp.outbound.message:true_555381106484@c.us_3EB0F89033D5AD38F4DC5E
                         ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
                         ID extraído do payload (key.id ou similar)
```

O webhook **não passa** por `normalizeOutboundPayloadForIdempotency` de forma que unifique com o send, ou o ID extraído tem formato que o send nunca produz.

---

## 5. Próximos passos (correção)

1. **Garantir que send() e webhook usem a mesma chave:**
   - Opção A: Incluir no payload do send() o `message_id` retornado pelo gateway (se existir) nos campos que `calculateIdempotencyKey` lê.
   - Opção B: Fazer o webhook usar o fallback quando o ID for composite (ex.: `true_xxx@c.us_yyy`) e o send não tiver esse ID.
   - Opção C: Para outbound, **sempre** usar fallback (to+tsBucket+contentHash), ignorando message_id do webhook quando o formato for incompatível.

2. **Dedupe no frontend (mitigação):**
   - Aplicar a mesma lógica de dedupe de `appendInboxMessages` em `renderInboxMessages`, para não exibir duplicados mesmo quando a API retornar 2 eventos.

3. **Validar no gateway:**
   - Verificar se o gateway retorna `message_id` na resposta do send e em qual campo.
   - Verificar formato do ID no webhook (`key.id`, `message.key.id`, etc.).

---

## 6. Correção aplicada (05/02/2026)

### 6.1 Backend – EventIngestionService

**Arquivo:** `src/Services/EventIngestionService.php`

**Mudança:** Para `whatsapp.outbound.message`, **sempre priorizar** a chave de fallback (to+timestamp+content) sobre o `message_id`. Assim, send() e webhook passam a gerar a mesma chave e o webhook é descartado corretamente.

### 6.2 Frontend – renderInboxMessages

**Arquivo:** `views/layout/main.php`

**Mudança:** Dedupe antes de renderizar, usando a mesma lógica de `appendInboxMessages` (direction|content|timestamp para texto; outbound|audio|url|timestamp para áudio). Mitiga duplicatas antigas ainda presentes no banco.

---

## 7. Script de diagnóstico

```bash
php database/diagnostico-duplicados-inbox.php
```

---

**Fim do diagnóstico**
