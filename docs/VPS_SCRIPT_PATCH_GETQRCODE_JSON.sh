#!/bin/bash
# Pacote VPS: Patch getQRCode — tratar JSON quando sessão está CONNECTED
# Copiar apenas os comandos abaixo (não este cabeçalho) e colar no terminal da VPS

set -e

echo "=== 1) Backup do wppconnectAdapter.js ==="
docker cp gateway-wrapper:/app/src/services/wppconnectAdapter.js /tmp/wppconnectAdapter.js.bak.$(date +%Y%m%d_%H%M%S)
ls -la /tmp/wppconnectAdapter.js.bak.* 2>/dev/null | tail -1

echo ""
echo "=== 2) Criar script de patch ==="
cat > /tmp/patch-getqrcode-json.js << 'ENDOFSCRIPT'
const fs = require('fs');
const path = '/app/src/services/wppconnectAdapter.js';
let c = fs.readFileSync(path, 'utf8');
const old = `        // Se voltou JSON/texto, tenta ler mensagem
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
        }`;
const newBlock = `        // Se voltou JSON/texto, tenta ler mensagem e status
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
        }`;
if (!c.includes(old)) {
  console.error('ERRO: Bloco OLD nao encontrado no arquivo.');
  process.exit(1);
}
fs.writeFileSync(path, c.replace(old, newBlock));
console.log('Patch aplicado com sucesso.');
ENDOFSCRIPT

docker cp /tmp/patch-getqrcode-json.js gateway-wrapper:/tmp/patch-getqrcode-json.js

echo ""
echo "=== 3) Aplicar patch ==="
docker exec gateway-wrapper node /tmp/patch-getqrcode-json.js

echo ""
echo "=== 4) Reiniciar gateway ==="
docker restart gateway-wrapper

echo ""
echo "=== 5) Aguardar 5s e verificar ==="
sleep 5
docker ps --filter name=gateway-wrapper --format '{{.Status}}'

echo ""
echo "=== 6) Teste API /qr ==="
SESSION="pixel12digital"
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION/qr" 2>/dev/null | head -200

echo ""
echo "=== Patch concluido ==="
