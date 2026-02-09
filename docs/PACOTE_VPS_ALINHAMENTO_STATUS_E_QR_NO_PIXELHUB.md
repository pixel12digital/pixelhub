# Pacote VPS: Alinhamento de Status e QR Code no Pixel Hub

**Objetivo:** Resolver duas divergências para que o usuário gerencie tudo pelo Pixel Hub, sem precisar acessar a VPS.

| Problema | Síntoma | Solução |
|----------|---------|---------|
| **Tarefa 1** | Celular desconectado, VPS mostra "Conectado" | Gateway atualizar sessionManager em connection.update |
| **Tarefa 2** | Pixel Hub não exibe QR; erro "Invalid QR code response" | Gateway retornar QR na API `/api/channels/{id}/qr` |

---

## Tarefa 1: Status desalinhado (Conectado vs Desconectado)

### Causa
- O WPPConnect mantém status "connected" em cache quando o dispositivo desconecta.
- O gateway não usa `connection.update` para atualizar o status local.
- `GET /api/channels` consulta o WPPConnect em tempo real → retorna status desatualizado.

### Solução (VPS)
Aplicar patch em `docs/PACOTE_VPS_PATCH_CONNECTION_UPDATE_STATUS.md`:
- Ao receber `connection.update` com estado de desconexão → `sessionManager.updateSessionStatus(sessionId, 'disconnected')`
- `GET /api/channels` priorizar o status do sessionManager quando for "disconnected"

### Bloco diagnóstico — Rodar primeiro

```bash
# [VPS]
echo "=== 1) Onde connection.update é tratado? ==="
docker exec gateway-wrapper grep -rn "connection\.update\|eventType\|event" /app/src/services --include="*.js" 2>/dev/null | head -60

echo ""
echo "=== 2) Estrutura api.js - GET /api/channels ==="
docker exec gateway-wrapper grep -n -B2 -A20 "channels\|getSessionStatus\|sessionManager" /app/src/routes/api.js 2>/dev/null | head -80

echo ""
echo "=== 3) sessionManager.updateSessionStatus ==="
docker exec gateway-wrapper grep -n "updateSessionStatus" /app/src --include="*.js" 2>/dev/null
```

**Retornar:** Saída completa para definir o ponto exato do patch.

---

## Tarefa 2: QR Code no Pixel Hub (gerar sem ir à VPS)

### Causa
- `GET /api/channels/{id}/qr` retorna erro "Invalid QR code response" ou `success: true` sem campo `qr`.
- O WPPConnect pode gerar o QR via callback assíncrono; o gateway não captura/repassa.

### Solução (VPS)
Aplicar patch conforme `docs/PACOTE_VPS_QR_CODE_NA_RESPOSTA_API.md`:
- Garantir que a rota `/qr` retorne `{ success: true, qr: "base64..." }` no body.

### Bloco diagnóstico — Rodar primeiro

```bash
# [VPS]
SESSION="pixel12digital"
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"

echo "=== 1) Resposta atual de GET /api/channels/$SESSION/qr ==="
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION/qr" 2>/dev/null | head -1000

echo ""
echo "=== 2) Rota /qr no gateway ==="
docker exec gateway-wrapper grep -rn "qr\|getQr\|getQRCode" /app/src --include="*.js" 2>/dev/null | head -50

echo ""
echo "=== 3) wppconnectAdapter - getQRCode ==="
docker exec gateway-wrapper grep -n -B2 -A40 "getQRCode\|getQr" /app/src/services/wppconnectAdapter.js 2>/dev/null | head -80
```

**Retornar:** Body da resposta do curl + saída dos greps.

---

## Critério de aceite

| Tarefa | Critério |
|--------|----------|
| **1** | Ao desconectar o celular, a UI do gateway e o Pixel Hub mostram "Desconectado" em até 1 min. |
| **2** | Ao clicar "Reconectar" no Pixel Hub, o QR code aparece no modal (imagem) sem abrir nova aba. |

---

## Dependências

- **Tarefa 1:** Código do gateway-wrapper (Node.js) na VPS.
- **Tarefa 2:** Código do gateway-wrapper; WPPConnect pode precisar estar saudável (Chromium não travado).

---

## Resultado diagnóstico (09/02)

### Tarefa 1
- `connection.update` não encontrado em services — gateway não trata esse evento.
- `GET /api/channels` chama `getSessionStatus` (WPPConnect) e **sobrescreve** o sessionManager com o valor retornado. Se WPPConnect retorna "connected" (errado), o gateway grava "connected".
- **Próximo passo:** Localizar onde o gateway **recebe** webhooks do WPPConnect (pode estar em `routes/`).

### Tarefa 2
- Resposta da API: `{"success":false,"error":"WPPConnect getQRCode failed: Invalid QR code response from server"}`
- `wppconnectAdapter.getQRCode` tenta: (1) `status-session` → se status=QRCODE, extrai base64; (2) fallback `qrcode-session` → PNG binário.
- Quando a sessão está em estado inconsistente (WPPConnect diz CONNECTED mas dispositivo desconectado), nenhum retorna QR válido → erro.

---

## Bloco 3 — Localizar rota /qr e origem do erro

```bash
# [VPS]
echo "=== 1) _handleError no wppconnectAdapter (origem do erro) ==="
docker exec gateway-wrapper grep -n -B2 -A15 "_handleError\|getQRCode failed" /app/src/services/wppconnectAdapter.js 2>/dev/null | head -80

echo ""
echo "=== 2) Trecho getQRCode completo - tratamento de resposta ==="
docker exec gateway-wrapper sed -n '336,440p' /app/src/services/wppconnectAdapter.js 2>/dev/null
```

**Retornar:** Saída completa.

---

## Bloco 4 — Conferir status-session (WPPConnect não tem curl, usar host)

```bash
# [VPS] WPPConnect expõe 21465; tentar do host
SESSION="pixel12digital"
curl -s "http://127.0.0.1:21465/api/$SESSION/status-session" 2>/dev/null | head -50

# Se 21465 não estiver no host, usar gateway (que faz proxy)
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION" 2>/dev/null | head -30
```

**Retornar:** Saída de ambos.

---

## Resultado Bloco 3 (09/02)

- **Invalid QR code:** grep vazio — o erro pode vir do WPPConnect ou de `_handleError` no adapter.
- **Rota /qr:** Linhas 676–708 do api.js — chama `getQRCode`, espera `qr_base64`/`base64`/`qr`/`qrcode`, retorna JSON. Em erro, repassa `next(error)`.
- **Webhooks:** grep vazio em routes — webhooks podem estar em outro path ou arquivo.

---

## Resultado Blocos 4–6 (09/02)

- **_handleError:** Linha 1128 — usa `error.response?.data?.message` ou `error.message`. O erro "Invalid QR code response from server" é lançado dentro de `getQRCode` quando `qrcode-session` retorna JSON sem `message` ou com `status: CONNECTED`.
- **Restart:** Nenhum endpoint de restart encontrado no gateway. WPPConnect pode ter restart em outro container.
- **qr via gateway:** 500 com `"WPPConnect getQRCode failed: Invalid QR code response from server"`.
- **Ordem do patch:** Tratar JSON com `status: CONNECTED` no adapter — retornar `{ qr_base64: null, status: "CONNECTED", message }` em vez de lançar erro.

**Pacote criado:** `docs/PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md` — patch completo para o Charles aplicar na VPS.

---

## Referências

- `docs/PACOTE_VPS_PATCH_CONNECTION_UPDATE_STATUS.md` — detalhes do patch de status
- `docs/PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md` — patch getQRCode (tratar JSON CONNECTED)
- `docs/PACOTE_VPS_QR_CODE_NA_RESPOSTA_API.md` — detalhes do patch de QR
- `docs/INVESTIGACAO_UI_STATUS_CONECTADO_INCONSISTENTE.md` — investigação original
