# Pacote VPS: QR Code na resposta da API

**Objetivo:** Garantir que `GET /api/channels/{id}/qr` retorne o QR code em base64 no body da resposta, para que o Pixel Hub exiba o QR na interface (sem precisar ir à VPS).

**Contexto:** O usuário quer gerenciar tudo no Pixel Hub. Ao clicar "Reconectar", o gateway retorna `success: true` mas sem o campo `qr` — o Pixel Hub não consegue exibir. O WPPConnect gera o QR via callback `catchQR`; o gateway precisa capturar e retornar no response.

---

## 1. Bloco para o Charles — Localizar rota /qr

```bash
# [VPS]
echo "=== 1) Rota /qr no gateway ==="
docker exec gateway-wrapper grep -rn "qr\|/qr\|getQr\|getQRCode" /app/src --include="*.js" 2>/dev/null | head -50

echo ""
echo "=== 2) api.js ou routes — estrutura da rota channels ==="
docker exec gateway-wrapper grep -n -B2 -A15 "qr\|channels.*qr" /app/src/routes/api.js 2>/dev/null | head -80

echo ""
echo "=== 3) wppconnectAdapter — getQRCode ou getQr ==="
docker exec gateway-wrapper grep -n -B2 -A30 "getQRCode\|getQr\|qr" /app/src/services/wppconnectAdapter.js 2>/dev/null | head -100
```

**Retornar:** Saída completa. Com isso definimos onde o gateway chama WPPConnect e como retorna a resposta.

---

## 2. Problema esperado

O WPPConnect pode retornar o QR de duas formas:
- **Síncrono:** A API REST retorna o QR no body (ex: `{ qr: "base64...", success: true }`)
- **Assíncrono:** O `catchQR` é chamado quando o QR é gerado; a requisição HTTP pode retornar antes

Se for assíncrono, o gateway precisa:
1. Chamar o endpoint que dispara a geração do QR
2. Aguardar o callback/evento com o QR (ex: via Promise com timeout)
3. Incluir o QR no response

---

## 3. Patch sugerido (após inspeção)

**Hipótese A — WPPConnect retorna QR no response, gateway não repassa:**
- Ajustar o handler da rota para incluir `qr` ou `base64` no JSON de retorno.

**Hipótese B — WPPConnect usa callback:**
- O gateway precisa registrar um listener para `catchQR` e resolver a Promise quando o QR chegar.
- Ou: o gateway usa um endpoint diferente (ex: `start` + poll) que retorna o QR.

**Exemplo de resposta esperada pelo Pixel Hub:**
```json
{
  "success": true,
  "qr": "iVBORw0KGgoAAAANSUhEUgAA...",
  "message": "QR code gerado"
}
```

Ou com data URL:
```json
{
  "success": true,
  "qr": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "message": "QR code gerado"
}
```

O Pixel Hub aceita: `qr` como base64 cru, `data:image/png;base64,XXX` ou URL.

---

## 4. Bloco diagnóstico — Ver resposta atual do /qr

```bash
# [VPS] Substituir SESSION e SECRET pelos valores reais
SESSION="pixel12digital"
SECRET="$(grep GATEWAY_SECRET /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | cut -d= -f2)"

echo "=== Resposta completa de GET /api/channels/$SESSION/qr ==="
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION/qr" | head -500
```

**Retornar:** Body completo da resposta. Isso mostra se o gateway já retorna o QR em algum campo.

---

## 5. Referências

- WPPConnect: `catchQR:(base64Qrimg, asciiQR, attempts, urlCode)` — `base64Qrimg` é o base64
- Pixel Hub: `extractQrFromResponse()` procura `qr`, `qrcode`, `base64Qrimg`, `base64`, `data`, etc.
- `docs/SCRIPT_CORRECAO_LOCK_CHROMIUM.sh` — espera `.qr`, `.qrcode`, `.data`
