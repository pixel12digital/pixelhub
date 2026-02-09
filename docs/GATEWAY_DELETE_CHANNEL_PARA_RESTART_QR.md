# Gateway: DELETE /api/channels/{id} para Restart e QR

**Objetivo:** O Pixel Hub usa `DELETE /api/channels/{id}` + `POST /api/channels` para forçar reinício da sessão quando o QR não é gerado (sessão zombie ou desconectada).

**Contexto:** Ao clicar "Reconectar" no Pixel Hub, o fluxo é:
1. `GET /api/channels/{id}/qr` com retry (5 tentativas)
2. Se não obtiver QR: `DELETE /api/channels/{id}` → `POST /api/channels` → `GET .../qr` (8 tentativas)

**Verificação:** Se o gateway não expõe DELETE, o restart falha silenciosamente e o QR continua sem aparecer.

```bash
# [VPS] Verificar se DELETE existe
SESSION="pixel12digital"
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"

curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br/api/channels/$SESSION" 2>/dev/null | head -50
```

**Referência:** `docs/SOLUCAO_QR_CODE_IMOBSITES.md` — fluxo DELETE + create documentado.
