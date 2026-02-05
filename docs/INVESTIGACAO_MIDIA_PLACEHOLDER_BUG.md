# Investigação: [Mídia] no Inbox — Bug do Sistema

**Data:** 05/02/2026  
**Contexto:** No WhatsApp as mensagens aparecem corretamente (áudios com player). No Inbox do PixelHub aparece "[Mídia]" em vez do player. **É um bug do sistema** — o placeholder não existe no WhatsApp.

---

## 1. Confirmação: bug do PixelHub

O texto "[Mídia]" é um **placeholder do frontend** do Inbox, exibido quando:
- `media` é `null` ou `media.url` está vazio, **e**
- `content` está vazio

O WhatsApp não usa esse rótulo; no PixelHub ele indica que o backend retornou uma mensagem sem mídia e sem texto.

---

## 2. Evidências do diagnóstico

### 2.1 Duplicata send + webhook (antes da correção)

Para o áudio das 16:14, existem **2 eventos** no banco:

| event_id | source_system    | created_at | Observação                    |
|----------|------------------|------------|-------------------------------|
| 34fd713f | **wpp_gateway**  | 16:14:44   | Webhook chegou primeiro       |
| 9a3ccd19 | **pixelhub_operator** | 16:14:48 | send() criou 4s depois |

Ambos têm `idempotency_key` com fallback. Antes da correção de idempotência, os dois eram persistidos.

### 2.2 Mídia em tenants diferentes

| event_id | tenant (path) | communication_media |
|----------|---------------|---------------------|
| 34fd713f | tenant-2      | SIM                 |
| 9a3ccd19 | tenant-36     | SIM                 |
| 812a22e7 | tenant-36     | SIM                 |

O mesmo áudio lógico aparece em dois tenants (2 e 36), o que sugere múltiplas conversas ou mapeamento incorreto.

### 2.3 Arquivos inexistentes no disco local

Todos os `stored_path` apontam para arquivos que **não existem** no ambiente local. O banco é compartilhado entre dev e produção; o storage pode existir só em produção.

---

## 3. Hipóteses para o [Mídia]

### H1: Evento duplicado sem mídia (mais provável)

Um dos eventos duplicados (provavelmente o do webhook) pode:
- não ter registro em `communication_media`, ou
- ter estrutura de payload que impede o backend de montar `media` corretamente.

Nesse caso, a API retornaria uma mensagem com `media = null` e `content = ''` → o frontend exibe "[Mídia]".

### H2: URL de mídia retorna 404

Se o backend retorna `media.url` mas o arquivo não existe no servidor, a requisição retorna 404. O frontend ainda mostraria o player; o áudio só não tocaria. Para aparecer "[Mídia]", seria necessário um `onerror` que substitua o player por esse placeholder — o que não está implementado hoje.

### H3: Tipo de mídia não detectado

Se `payload.type` e `raw.payload.type` não forem detectados, `hasMediaIndicator` fica falso e o backend não busca mídia. O evento 34fd713f tem `raw.payload.type = ptt`, então o tipo é detectado. Para esse evento, H3 é improvável.

---

## 4. Conclusão

**Sim, é um bug do sistema PixelHub.** O placeholder "[Mídia]" é específico do Inbox e indica falha na representação da mensagem no backend ou na montagem da resposta da API.

**Causa mais provável:** evento duplicado (webhook + send) criado antes da correção de idempotência, em que um dos eventos não tem mídia associada ou não é tratado corretamente pelo backend, gerando uma mensagem sem `media` e sem `content`.

**Mitigação já em produção:** a correção de idempotência (priorizar fallback) evita novas duplicatas. O dedupe no frontend reduz a chance de exibir duplicatas antigas.

---

## 5. Próximos passos (investigação adicional)

1. Simular a resposta da API `thread-data` para a conversa e inspecionar quais mensagens vêm com `media` vazio.
2. Verificar se o evento 34fd713f (wpp_gateway) está na lista de eventos retornados e qual `media` ele recebe.
3. Em produção, confirmar se os arquivos existem em `storage/whatsapp-media/` e se a URL de mídia retorna 200.

---

**Fim da investigação**
