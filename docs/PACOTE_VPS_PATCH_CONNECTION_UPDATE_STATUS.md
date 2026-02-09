# Pacote VPS: Patch connection.update → sessionManager.updateSessionStatus

**Objetivo:** Quando o gateway receber `connection.update` com estado de desconexão, atualizar `sessionManager` para que a UI exiba o status correto.

**Contexto:** O payload de `connection.update` traz `connection.status` (ex: "available", "close"). Quando for "close" ou "disconnected", o gateway deve chamar `sessionManager.updateSessionStatus(sessionId, 'disconnected')`. O endpoint `GET /api/channels` já usa sessionManager como fallback; ao atualizar via evento, a UI refletirá o estado real.

**Limitação:** Se o WPPConnect parar de emitir eventos (sessão "zombie"), nenhum `connection.update` chegará. Nesse caso, o healthcheck do PixelHub (webhook_raw_logs) já detecta e força getQr(). Este patch cobre o caso em que o WPPConnect **emite** o evento de desconexão.

---

## 1. Onde o gateway recebe eventos do WPPConnect

O gateway recebe webhooks do WPPConnect e encaminha ao PixelHub. O ponto de recebimento está em `webhookDeliveryService.js` ou similar — quando um evento é "queued" para envio ao webhook, o gateway já tem o payload.

**Arquivos a inspecionar:**
- `src/services/webhookDeliveryService.js`
- `src/services/wppconnectWebhookConfig.js`
- `src/routes/webhooks.js`

**Comando para localizar:**

```bash
# [VPS]
docker exec gateway-wrapper grep -rn "connection\.update\|Webhook event queued\|Received webhook" /app/src --include="*.js" 2>/dev/null | head -40
```

---

## 2. Patch proposto

**Onde inserir:** No código que processa eventos recebidos do WPPConnect, antes ou após encaminhar ao webhook externo. Quando `eventType === 'connection.update'`:

1. Extrair `sessionId` do payload (session.id, session, channel)
2. Extrair `connection.status` ou `raw.payload.state` 
3. Se `status === 'close'` ou `status === 'disconnected'` ou `state === 'unavailable'` ou similar → `sessionManager.updateSessionStatus(sessionId, 'disconnected')`
4. Se `status === 'available'` ou `status === 'open'` → `sessionManager.updateSessionStatus(sessionId, 'connected')`

**Pseudocódigo:**

```javascript
// Quando receber evento do WPPConnect (no handler de webhook)
if (eventType === 'connection.update' && payload) {
  const sessionId = payload.session?.id || payload.session || payload.channel;
  const connStatus = payload.connection?.status || payload.raw?.payload?.state || '';
  const statusLower = String(connStatus).toLowerCase();
  
  if (sessionId) {
    if (statusLower === 'close' || statusLower === 'closed' || statusLower === 'disconnected' || statusLower === 'unavailable') {
      sessionManager.updateSessionStatus(sessionId, 'disconnected');
    } else if (statusLower === 'available' || statusLower === 'open' || statusLower === 'connected') {
      sessionManager.updateSessionStatus(sessionId, 'connected');
    }
  }
}
```

---

## 3. Estrutura do payload (referência)

```json
{
  "event": "connection.update",
  "session": { "id": "pixel12digital", "name": "pixel12digital" },
  "connection": { "status": "available" },
  "raw": {
    "payload": {
      "event": "onpresencechanged",
      "session": "pixel12digital",
      "state": "available"
    }
  }
}
```

Quando desconectar, esperar `connection.status` ou `state` com valor "close", "disconnected", "unavailable" etc.

---

## 4. Bloco para o Charles — localizar ponto de inserção

```bash
# [VPS] Encontrar onde eventos são processados
echo "=== Onde connection.update é tratado? ==="
docker exec gateway-wrapper grep -rn "connection\.update\|onpresencechanged\|eventType\|event" /app/src/services --include="*.js" 2>/dev/null | head -60

echo ""
echo "=== webhookDeliveryService - estrutura ==="
docker exec gateway-wrapper grep -n "sessionId\|eventType\|payload" /app/src/services/webhookDeliveryService.js 2>/dev/null | head -50

echo ""
echo "=== wppconnectWebhookConfig - como recebe eventos ==="
docker exec gateway-wrapper head -100 /app/src/services/wppconnectWebhookConfig.js 2>/dev/null
```

**Retornar:** Saída completa. Com isso definimos o arquivo e a linha exata para inserir o patch.

---

## 5. GET /api/channels — prioridade do status

O código atual em `api.js` chama `getSessionStatus` em tempo real e sobrescreve o sessionManager. Para que o patch funcione, o `GET /api/channels` poderia:

- **Opção A:** Usar primeiro `session.status` do sessionManager; só chamar getSessionStatus se for 'unknown'.
- **Opção B:** Chamar getSessionStatus mas, se retornar "connected", verificar se `sessionManager` tem `last_connection_update_at`; se não houver update há X horas, considerar "possivelmente desconectado".

O patch da seção 2 basta para o caso em que o WPPConnect **emite** connection.update com disconnect. Para o caso zombie (sem eventos), o healthcheck do PixelHub já resolve.

---

## 6. Referências

- `docs/INVESTIGACAO_UI_STATUS_CONECTADO_INCONSISTENTE.md`
- Payload real: `database/diagnostico-connection-update-payload.php` (connection.status: "available")
