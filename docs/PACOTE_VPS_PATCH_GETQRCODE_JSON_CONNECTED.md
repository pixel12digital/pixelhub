# Pacote VPS: Patch getQRCode — tratar JSON quando sessão está CONNECTED

**Objetivo:** Ao clicar "Reconectar" no Pixel Hub com sessão em estado inconsistente (WPPConnect diz CONNECTED mas dispositivo desconectado), em vez de retornar 500 com "Invalid QR code response from server", o gateway deve retornar 200 com `{ success: true, qr: null, status: "CONNECTED", message: "..." }` para que o Pixel Hub exiba mensagem orientando o usuário.

**Contexto:** O `wppconnectAdapter.getQRCode` chama `qrcode-session` como fallback. Quando a sessão está CONNECTED, o WPPConnect retorna JSON (sem PNG). O adapter atual lança `"Invalid QR code response from server"` quando o JSON não tem `message` ou é inesperado. O patch trata `status: "CONNECTED"` e retorna payload estruturado em vez de lançar erro.

---

## 1. Arquivo a alterar

**Arquivo:** `/app/src/services/wppconnectAdapter.js` (dentro do container `gateway-wrapper`)

**Caminho na VPS:** O gateway roda em container Docker. O código pode estar montado em disco (ex.: `/opt/pixel12-whatsapp-gateway/`) ou só dentro do container. Verificar onde o código fonte está para editar e rebuild/restart.

---

## 2. Trecho atual (linhas ~369–386)

Quando `qrcode-session` retorna `content-type: application/json` ou `text/`:

```javascript
        // Se voltou JSON/texto, tenta ler mensagem
        if (contentType.includes("application/json") || contentType.includes("text/")) {
          const decoded = buffer.toString("utf8");
          try {
            const parsed = JSON.parse(decoded);
            if (parsed?.message) {
              logger.warn("QR code endpoint returned error message", { sessionId, message: parsed.message });
              if (String(parsed.status || "").toUpperCase() === "INITIALIZING") {
                return { qr_base64: null, status: "INITIALIZING" };
              }
              throw new Error(parsed.message);
            }
          } catch (_) {}
          throw new Error("Invalid QR code response from server");
        }
```

**Problema:** Se o JSON tiver `status: "CONNECTED"` (ou não tiver `message`), cai no `throw new Error("Invalid QR code response from server")`.

---

## 3. Patch proposto

**Substituir** o bloco inteiro acima por:

```javascript
        // Se voltou JSON/texto, tenta ler mensagem e status
        if (contentType.includes("application/json") || contentType.includes("text/")) {
          const decoded = buffer.toString("utf8");
          let parsed;
          try {
            parsed = JSON.parse(decoded);
          } catch (_) {
            throw new Error("Invalid QR code response from server");
          }
          const msg = parsed?.message || parsed?.error || parsed?.msg || "";
          const status = String(parsed?.status || "").toUpperCase();

          if (status === "INITIALIZING") {
            return { qr_base64: null, status: "INITIALIZING" };
          }
          if (status === "CONNECTED" || /connected|já conectad|already connected/i.test(msg)) {
            logger.warn("QR code endpoint: session already connected", { sessionId, message: msg });
            return { qr_base64: null, status: "CONNECTED", message: msg || "Session is connected. Restart session to generate new QR code." };
          }
          if (msg) {
            logger.warn("QR code endpoint returned error message", { sessionId, message: msg });
            throw new Error(msg);
          }
          throw new Error("Invalid QR code response from server");
        }
```

**Mudanças:**
- Separa `JSON.parse` do tratamento de status (parse falha → erro genérico).
- Usa `parsed.error` e `parsed.msg` como fallback de mensagem.
- Se `status === "CONNECTED"` ou mensagem indica conexão → retorna `{ qr_base64: null, status: "CONNECTED", message }` em vez de lançar.
- Mantém erro genérico quando não houver mensagem útil.

---

## 4. Rota API — compatibilidade

A rota `/api/channels/:id/qr` (api.js linhas ~676–708) provavelmente faz:
- Se `getQRCode` retorna `{ qr_base64, base64 }` → retorna `{ success: true, qr: base64 }`.
- Se `getQRCode` lança → passa para `next(error)` → 500.

Com o patch, `getQRCode` pode retornar `{ qr_base64: null, status: "CONNECTED", message }` sem lançar. **Recomendado:** a rota repassar `status` e `message` no JSON de sucesso para que o Pixel Hub exiba a mensagem correta. Ex.: `{ success: true, channel, qr: null, status: "CONNECTED", message: "..." }`. O Pixel Hub já trata `qr === null` e usa `message` quando disponível.

---

## 5. Pacote de Execução VPS (formato REGRA_OPERACIONAL_VPS)

**VPS – OBJETIVO:** Aplicar patch no `wppconnectAdapter.js` para tratar JSON com `status: CONNECTED` no getQRCode.  
**SERVIÇO:** gateway-wrapper (Node.js via PM2 ou Docker).  
**RISCO:** Médio — altera código do adapter; rollback = reverter arquivo e reiniciar.  
**ROLLBACK:** Restaurar backup do arquivo e reiniciar o gateway.

---

### 5.1 Pré-check (não muda nada)

**Comandos (copiar/colar):**

```bash
echo "=== 1) Onde está o código do gateway? ==="
ls -la /opt/pixel12-whatsapp-gateway/src/services/wppconnectAdapter.js 2>/dev/null || docker exec gateway-wrapper ls -la /app/src/services/wppconnectAdapter.js 2>/dev/null

echo ""
echo "=== 2) Trecho atual (JSON no qrcode-session) — linhas 369-390 ==="
docker exec gateway-wrapper sed -n '369,390p' /app/src/services/wppconnectAdapter.js 2>/dev/null

echo ""
echo "=== 3) PM2/Docker — como reiniciar o gateway? ==="
pm2 list 2>/dev/null | head -10
docker ps --format '{{.Names}}' | grep -E 'gateway|wpp' 2>/dev/null
```

**Você me retorna:** Saída completa. Com isso confirmamos o caminho do arquivo e o trecho exato a substituir.

---

### 5.2 Execução (patch)

**Se o código estiver em disco** (ex.: `/opt/pixel12-whatsapp-gateway/`):

1. Fazer backup do arquivo.
2. Editar `src/services/wppconnectAdapter.js` e aplicar o patch da seção 3 no bloco indicado.
3. Se o gateway roda em container com volume montado, o restart do container já reflete as mudanças.

**Se o código estiver só dentro do container:**

O script `docs/VPS_SCRIPT_PATCH_GETQRCODE_JSON.sh` aplica o patch via `docker cp` + `docker exec`. Copiar o conteúdo do script e colar no terminal (bloco único).

**Arquivos tocados:**
- `src/services/wppconnectAdapter.js` — bloco de tratamento de JSON no getQRCode (linhas ~369–386).

---

### 5.3 Reinício/Reload

**Comandos (ajustar conforme ambiente):**

```bash
# Se gateway roda via PM2
pm2 restart gateway   # ou nome/id do app

# Se gateway roda via Docker
docker restart gateway-wrapper
```

---

### 5.4 Verificação

**Comandos:**

```bash
SESSION="pixel12digital"
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"

echo "=== Resposta de GET /api/channels/$SESSION/qr (com sessão CONNECTED) ==="
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION/qr" 2>/dev/null
```

**Critério de sucesso:**
- Em vez de 500 com `"Invalid QR code response from server"`, a API retorna **200** com JSON contendo `success: true`, `qr: null` (ou ausente), `status: "CONNECTED"` e `message` explicativa.
- O Pixel Hub pode exibir a mensagem orientando o usuário.

---

### 5.5 Rollback

```bash
# Restaurar backup (ajustar caminho e nome do backup)
sudo cp /opt/pixel12-whatsapp-gateway/src/services/wppconnectAdapter.js.bak.YYYYMMDD_HHMMSS /opt/pixel12-whatsapp-gateway/src/services/wppconnectAdapter.js

# Reiniciar gateway
pm2 restart gateway   # ou docker restart gateway-wrapper
```

---

## 6. Próximo passo (Pixel Hub)

Após o patch, o controlador `WhatsAppGatewaySettingsController` e a view devem tratar:
- `response.status === "CONNECTED"` e `response.qr` vazio → exibir: "Sessão conectada. Para reconectar, reinicie a sessão primeiro." (com link ou botão para restart, se existir).

---

## 7. Referências

- `docs/PACOTE_VPS_ALINHAMENTO_STATUS_E_QR_NO_PIXELHUB.md` — contexto geral
- `docs/PACOTE_VPS_QR_CODE_NA_RESPOSTA_API.md` — spec da API de QR
- `docs/REGRA_OPERACIONAL_VPS.md` — formato do pacote
