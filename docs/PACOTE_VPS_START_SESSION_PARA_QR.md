# Pacote VPS: start-session para gerar QR

**Objetivo:** O gateway retorna "Session not started. Please, use the /start-session route" em getQr para sessões desconectadas. O Pixel Hub precisa chamar start-session antes de getQr.

**Diagnóstico (09/02):** getQr (1ª) retorna esse erro para imobsites. Após delete+create, getQr (após create) retorna success=true mas sem campo qr no body.

---

## Verificação: o gateway tem start-session?

```bash
# [VPS]
SESSION="imobsites"
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"

echo "=== POST /api/channels/$SESSION/start-session ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" -H "Content-Type: application/json" \
  "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION/start-session" 2>/dev/null | head -100
```

**Retornar:** Código HTTP e body da resposta.

---

## Se o gateway não tiver a rota

O gateway-wrapper precisa expor `POST /api/channels/:id/start-session` que proxy para o WPPConnect `POST /api/{session}/start-session`.

**Referência WPPConnect:** https://wppconnect-team.github.io/swagger/wppconnect-server/

---

## Pixel Hub

O Pixel Hub já chama `startSession()` quando getQr retorna "Session not started". Se o gateway retornar 404, o fluxo continua com delete+create+getQr.
