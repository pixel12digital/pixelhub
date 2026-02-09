# Investigação: UI exibe "Conectado" quando sessão está desconectada

**Objetivo:** Identificar por que a UI do gateway (wpp.pixel12digital.com.br:8443) mostra "Conectado" mesmo quando o dispositivo do usuário está desconectado, e propor correção.

**Cenário:** Usuário reportou "Sessão UI aparece conectado e meu dispositivo está desconectado". Mensagens não chegam ao Inbox. O status na UI é incoerente e inaceitável.

---

## 1. Onde o status "Conectado" vem

| Camada | Componente | Função |
|--------|------------|--------|
| WPPConnect | wppconnect-server | Mantém conexão com WhatsApp; emite estado da sessão |
| Gateway-wrapper | gateway-wrapper (Node.js) | Expõe API `/api/channels`; serve UI |
| UI | HTML/JS em :8443 | Exibe status retornado pela API |

**Hipótese:** O status "connected" vem do WPPConnect. Quando o dispositivo desconecta, o WPPConnect pode não atualizar o estado imediatamente (ou mantém cache).

---

## 2. Bloco VPS para investigar

**Onde rodar:** SSH da VPS (wpp.pixel12digital.com.br)

### BLOCO 1 — Origem do status e eventos de conexão

```bash
echo "=== 1) Onde o gateway-wrapper obtém o status 'connected'? ==="
docker exec gateway-wrapper grep -rn "connected\|status\|CONNECTED" /app/src --include="*.js" 2>/dev/null | head -40

echo ""
echo "=== 2) WPPConnect emite eventos de desconexão? (connection.update, disconnect) ==="
docker logs gateway-wrapper --since 24h 2>&1 | grep -iE "connection|disconnect|connected|close" | tail -30

echo ""
echo "=== 3) API /api/channels - estrutura da resposta ==="
curl -s -H "X-Gateway-Secret: d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54" "https://wpp.pixel12digital.com.br:8443/api/channels" | head -200
```

**Retornar:** Saída completa dos comandos 1, 2 e 3.

---

### BLOCO 2 — Rastrear fluxo status no código

```bash
echo "=== 1) gateway-wrapper: como obtém status do WPPConnect? ==="
docker exec gateway-wrapper grep -rn "getStatus\|session.*status\|channel.*status" /app/src --include="*.js" 2>/dev/null | head -30

echo ""
echo "=== 2) WPPConnect: existe evento de desconexão que o wrapper deveria escutar? ==="
docker exec wppconnect-server grep -rn "disconnect\|connection.*close\|connectionState" /app --include="*.js" 2>/dev/null | head -20
```

**Retornar:** Saída dos comandos.

---

## 2. Resultado BLOCO 1 (09/02)

### Comando 1: grep "connected|status"
**Saída:** vazia. Possível causa: path `/app/src` ou extensão `.js` incorretos.

### Comando 2: connection.update
**Achado:** `connection.update` é emitido — mas **apenas para ImobSites**, não para pixel12digital nas últimas 24h. ImobSites envia dezenas de `connection.update`; pixel12digital não aparece.

**Hipótese:** A sessão pixel12digital pode estar em estado "zombie": quando o dispositivo desconecta, o WPPConnect não emite `connection.update` (ou o wrapper não o recebe). O status fica "connected" por cache/stale.

### Comando 3: API /api/channels
**Resposta:** `{"channels":[{"id":"pixel12digital","status":"connected"},{"id":"imobsites","status":"connected"}],...}` — ambos "connected".

---

## 2.2 Resultado BLOCO 2 (09/02)

### Comando 1: Estrutura
**Código em:** `/app/src` — sessionManager.js, wppconnectAdapter.js, routes/sessions.js, etc.

### Comando 2: grep status/connected
**Saída:** vazia (grep pode precisar de `-E` ou padrão diferente).

### Comando 3: connection.update pixel12digital (72h)
**Achado crítico:** pixel12digital emitiu `connection.update` em **08/02** (12:00–12:06), mas **zero em 09/02**.

| Data | connection.update pixel12digital |
|------|-----------------------------------|
| 08/02 12:00–12:06 | ~20 eventos |
| 09/02 | Nenhum |

**Hipótese:** Após 08/02 12:06, o dispositivo desconectou. O WPPConnect parou de emitir `connection.update` para pixel12digital. O status ficou "connected" por cache — nenhum evento "disconnected" chegou.

---

## 3. BLOCO 2 — Busca ampliada e payload connection.update

```bash
# [VPS] Caminho correto do código
echo "=== 1) Estrutura do gateway-wrapper ==="
docker exec gateway-wrapper ls -la /app 2>/dev/null
docker exec gateway-wrapper find /app -name "*.js" -o -name "*.ts" 2>/dev/null | head -30

echo ""
echo "=== 2) Busca status/connected em todo /app ==="
docker exec gateway-wrapper grep -rn "status\|connected" /app --include="*.js" 2>/dev/null | head -50

echo ""
echo "=== 3) connection.update para pixel12digital (72h) ==="
docker logs gateway-wrapper --since 72h 2>&1 | grep "connection.update" | grep -i "pixel12digital" | tail -20

echo ""
echo "=== 4) Payload de um connection.update (ImobSites) — pegar eventId e buscar no webhook ==="
# Pegar um eventId recente de connection.update
docker logs gateway-wrapper --since 1h 2>&1 | grep "connection.update" | grep "ImobSites" | tail -1
```

**Retornar:** Saída completa. O item 4 ajuda a ver se o payload de connection.update traz estado (connected/disconnected).

---

## 3. BLOCO 3 — Código do gateway e payload connection.update

### 3.1 [VPS] Ler sessionManager e wppconnectAdapter

```bash
# Onde o gateway obtém status e como trata connection.update
echo "=== sessionManager.js - trechos relevantes ==="
docker exec gateway-wrapper cat /app/src/services/sessionManager.js | head -150

echo ""
echo "=== wppconnectAdapter.js - connection.update, status ==="
docker exec gateway-wrapper grep -n -A5 -B2 "connection\.update\|connectionUpdate\|getStatus\|status" /app/src/services/wppconnectAdapter.js | head -80

echo ""
echo "=== routes/sessions.js - lista de canais ==="
docker exec gateway-wrapper cat /app/src/routes/sessions.js
```

**Retornar:** Saída completa.

### 3.2 [HostMedia] Payload connection.update em webhook_raw_logs

Os `connection.update` são enviados ao PixelHub. Verificar se o payload traz estado (connected/disconnected):

```bash
cd /home/pixel12digital/hub.pixel12digital.com.br
php database/diagnostico-connection-update-payload.php --session=pixel12digital
```

**Retornar:** Saída completa (payload JSON). Isso revela se o evento traz `state`, `status`, `connected` ou similar.

---

## 4. Resultado BLOCO 3 — Código do gateway (09/02)

### sessionManager.js
- `updateSessionStatus(sessionId, status)` — atualiza status em `sessions.json`
- Sessões em `data/sessions.json` — status pode ser persistido

### routes/sessions.js — GET /:id/status
- Chama `wppconnectAdapter.getSessionStatus(sessionId)` — **status em tempo real do WPPConnect**
- Normaliza: `connected`, `disconnected`, `qr_required`
- Se `status` inclui "connected" ou "open" → `connected`; se "disconnected" ou "close" → `disconnected`

### Conclusão
O status vem de **getSessionStatus** (WPPConnect API). Quando o dispositivo desconecta, o WPPConnect **deveria** retornar "disconnected", mas está retornando "connected". O problema está no **WPPConnect** — Estado em cache ou não detecta desconexão.

### Solução proposta
O gateway poderia **usar connection.update** para atualizar o status localmente: quando o payload trazer `state: 'close'` ou similar, chamar `sessionManager.updateSessionStatus(sessionId, 'disconnected')`. O endpoint `/api/channels` passaria a preferir o status do sessionManager (atualizado por eventos) em vez de consultar o WPPConnect em tempo real.

---

## 5. Soluções possíveis

### A) Gateway usa connection.update para atualizar status

- O gateway ** já recebe** connection.update e encaminha ao webhook.
- **Adicionar:** Ao receber connection.update, ler o payload; se `state === 'close'` ou `connected === false`, chamar `sessionManager.updateSessionStatus(sessionId, 'disconnected')`.
- O endpoint `/api/channels` usar sessionManager como fonte de verdade (ou combinar com getSessionStatus).

### B) Indicador "última atividade" na UI

- Manter por sessão um `lastMessageReceivedAt` (ou `lastWebhookAt`).
- Na UI, exibir: "Conectado" + "Última mensagem: há X horas" (ou "Sem mensagens há Xh").
- Se `lastMessageReceivedAt` > 4h, exibir aviso: "Possivelmente desconectado - reconecte via QR Code".

### C) Polling de saúde (PixelHub healthcheck — já implementado)

- O healthcheck já detecta zombie via webhook_raw_logs e força getQr().

---

## 5. Dependência

A correção depende do acesso ao código do **gateway-wrapper** e **WPPConnect** na VPS. O Cursor não altera a VPS; o Charles executa os blocos e aplica os patches conforme a investigação.

---

## 6. Referências

- `docs/INVESTIGACAO_RENATO_81642320_INBOX.md` — contexto Renato
- `docs/CRON_HEALTHCHECK_SESSOES_WHATSAPP.md` — healthcheck detecta zombie via webhook_raw_logs
