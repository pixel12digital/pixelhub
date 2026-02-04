# Investigação: Áudio novo de Renato (81642320) não exibido no Inbox

**Data:** 04/02/2026  
**Contato:** Renato Silva — 55 53 8164-2320  
**Sintoma:** Áudio enviado às 11:38 (1:50) aparece no WhatsApp mas não no Inbox do PixelHub.

---

## 1. Evidências

### 1.1 WhatsApp (cliente do usuário)
- Áudio de **1:50** às **11:38** em 03/02/2026
- Mensagem não lida (indicador verde)
- Áudio entregue normalmente no WhatsApp

### 1.2 Inbox PixelHub
- Áudio de **16 segundos** às **11:34** é exibido (evento 129276)
- Áudio de **1:50** às **11:38** **não aparece**

### 1.3 Banco de dados (remoto)
- **Apenas 1 evento** do contato entre 11:30 e 12:05: id=129276 às 11:34:37
- **Nenhum** evento às 11:38
- Evento 129276: tipo PTT, `communication_media` id=480, `file_size`=36.9 KB

---

## 2. Diagnóstico VPS (04/02/2026)

### 2.1 Configuração confirmada

| Componente | Configuração |
|------------|--------------|
| **WPPConnect** | `WEBHOOK_URL=http://gateway-wrapper:3000/v1/webhooks/receive` |
| **gateway-wrapper** | Recebe do WPPConnect; encaminha para `https://hub.pixel12digital.com.br/api/whatsapp/webhook` |

### 2.2 Eventos recebidos pelo gateway-wrapper (48h)

| eventType | Quantidade |
|-----------|------------|
| onpresencechanged | 16.956 |
| onupdatelabel | 2.076 |
| onack | 95 |
| status-find | 61 |
| **onmessage** | **53** |
| onselfmessage | 33 |
| onreactionmessage | 3 |

### 2.3 Conclusão do diagnóstico VPS

- O gateway-wrapper **recebe** `onmessage` (53 nas últimas 48h).
- O pipeline WPPConnect → gateway-wrapper → Hub está **funcionando**.
- O problema do áudio 11:38 **não está** na configuração nem no encaminhamento do gateway.

---

## 3. Conclusão da investigação

O áudio das 11:38 **nunca chegou ao sistema**. O fluxo quebrou **antes** do gateway-wrapper.

### 3.1 Onde o fluxo quebra

```
WhatsApp → WPPConnect (emite?) → gateway-wrapper → Webhook (HostMedia) → Banco
                    ↑
             Falha provável aqui
```

O WPPConnect **emite** `onMessage` internamente (vimos nos logs), mas o evento do áudio 11:38 **não foi enviado** ao webhook do gateway-wrapper. Nos logs do gateway, não há `onmessage` entre 11:34 e 12:00 em 03/02 — o primeiro `onmessage` do dia é às 18:47.

### 3.2 Causa raiz provável

**WPPConnect não enviou** o evento `onmessage` do áudio 11:38 ao webhook. Possíveis causas:

1. **Bug ou limitação** no WPPConnect para áudios longos (1:50 vs 16 s)
2. **Condição de corrida** — processamento do áudio 11:34 interferiu no 11:38
3. **Falha intermitente** na emissão/envio de webhook pelo WPPConnect

---

## 4. O que foi descartado

| Hipótese | Resultado |
|----------|-----------|
| Deduplicação (idempotency) | Descartada – cada mensagem tem `message_id` único |
| `mapEventType` rejeitando | Descartada – evento 11:34 com `event=message` foi processado |
| Arquivo ausente no storage | Descartada – o problema é que o evento não existe no banco |
| Filtro no Inbox | Descartada – a API retornaria o evento se existisse |
| **gateway-wrapper não recebe onmessage** | **Descartada** – recebe 53 onmessage em 48h; config correta |
| **WPPConnect não envia ao gateway** | **Descartada** – WEBHOOK_URL aponta para gateway-wrapper |

---

## 5. Resumo final

| Item | Status |
|------|--------|
| Áudio 11:34 (16 s) | Chegou, processado, exibido |
| Áudio 11:38 (1:50) | Não chegou ao sistema |
| WPPConnect | Emite onMessage; envia ao gateway (config OK) |
| gateway-wrapper | Recebe onmessage; encaminha ao Hub (53 em 48h) |
| **Causa provável** | **WPPConnect não enviou** o evento do áudio 11:38 ao webhook (bug/limitação/intermitência) |

### 5.2 Solução implementada (mínimo impacto)

**Problema:** O WPPConnect pode dar timeout ao enviar webhooks quando o Hub demora a responder (ex.: download de mídia).

**Solução:** Responder 200 ao webhook **antes** do processamento de mídia; processar mídia em background.

| Arquivo | Alteração |
|---------|-----------|
| `EventIngestionService.php` | Parâmetro `process_media_sync` (default true) para pular processamento de mídia síncrono |
| `WhatsAppWebhookController.php` | `process_media_sync => false` no ingest; após responder 200, `fastcgi_finish_request()` + processamento de mídia em background |

**Fluxo novo:**
1. Webhook recebe evento → ingest (sem mídia) → insert no banco → resolve conversa
2. Responde 200 imediatamente
3. `fastcgi_finish_request()` — envia resposta e libera conexão
4. Processa mídia em background (download, salva em `communication_media`)

**Impacto:** Nenhum na funcionalidade. O evento continua sendo salvo; a mídia é processada logo em seguida. O `serveMedia` já tenta reprocessar se o arquivo não existir.
