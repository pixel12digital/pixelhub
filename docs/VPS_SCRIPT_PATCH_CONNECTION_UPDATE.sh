#!/bin/bash
# Pacote VPS: Patch connection.update para corrigir status falso "conectado"
# Executar na VPS. Uso: bash VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh

set -e
CONTAINER="gateway-wrapper"
BACKUP="/tmp/gateway_conn_patch_$(date +%Y%m%d_%H%M%S)"

echo "=== Patch connection.update ==="

# 1) Backup
echo "1) Backup..."
docker cp "$CONTAINER:/app/src/index.js" "$BACKUP.index.js" 2>/dev/null || { echo "ERRO: docker cp index.js"; exit 1; }
docker cp "$CONTAINER:/app/src/routes/api.js" "$BACKUP.api.js" 2>/dev/null || { echo "ERRO: docker cp api.js"; exit 1; }
echo "   OK: $BACKUP.*"

# 2) Patch index.js: adicionar sessionManager e handler connection.update
echo "2) Patch index.js..."
docker exec "$CONTAINER" node -e '
const fs = require("fs");
const p = "/app/src/index.js";
let c = fs.readFileSync(p, "utf8");

if (c.includes("Session status updated from connection.update")) {
  console.log("   (ja aplicado)");
  process.exit(0);
}

// Adicionar require sessionManager apos webhookDeliveryService
c = c.replace(
  "const webhookDeliveryService = require(\"./services/webhookDeliveryService\");",
  "const webhookDeliveryService = require(\"./services/webhookDeliveryService\");\nconst sessionManager = require(\"./services/sessionManager\");"
);

// Inserir handler connection.update apos eventType = message.failed, antes de buildHubPayload
const before = "    } else if (rawEvent.event === \"message.failed\") {\n      eventType = \"message.failed\";\n    }\n    // buildHubPayload";
const after = `    } else if (rawEvent.event === "message.failed") {
      eventType = "message.failed";
    }
    // connection.update: atualizar sessionManager para status correto na UI
    if (eventType === "connection.update" && sessionId) {
      const connStatus = rawEvent.connection?.status || rawEvent.raw?.payload?.state || rawEvent.state || "";
      const statusLower = String(connStatus).toLowerCase();
      if (["close", "closed", "disconnected", "unavailable"].includes(statusLower)) {
        sessionManager.updateSessionStatus(sessionId, "disconnected");
        logger.info("Session status updated from connection.update", { sessionId, status: "disconnected" });
      } else if (["available", "open", "connected"].includes(statusLower)) {
        sessionManager.updateSessionStatus(sessionId, "connected");
      }
    }
    // buildHubPayload`;

if (!c.includes(before.split("\n")[0])) {
  console.error("   ERRO: trecho nao encontrado em index.js");
  process.exit(1);
}
c = c.replace(before, after);
fs.writeFileSync(p, c);
console.log("   OK");
'

# 3) Patch api.js: priorizar disconnected do sessionManager
echo "3) Patch api.js..."
docker exec "$CONTAINER" node -e '
const fs = require("fs");
const p = "/app/src/routes/api.js";
let c = fs.readFileSync(p, "utf8");

if (c.includes("priorizar disconnected do sessionManager")) {
  console.log("   (ja aplicado)");
  process.exit(0);
}

// Envolver o bloco try/catch do getSessionStatus em: if (status !== "disconnected")
const oldTry = `        // Try to get updated status from WPPConnect (non-blocking)
        try {
          const statusResult = await wppconnectAdapter.getSessionStatus(
            sessionId,
            req.correlationId || uuidv4()
          );
          
          const rawStatus = statusResult.status || statusResult.state || statusResult.connectionState;
          
          if (rawStatus) {
            const statusLower = rawStatus.toLowerCase();
            if (statusLower.includes("connected") || statusLower === "open") {
              status = "connected";
            } else if (statusLower.includes("disconnected") || statusLower === "close" || statusLower === "closed") {
              status = "disconnected";
            } else if (statusLower.includes("qr") || statusLower === "qr_required" || 
                       statusLower.includes("initializing") || statusLower.includes("starting")) {
              status = "qr_required";
            } else {
              status = statusLower;
            }
            
            // Update status in session manager
            sessionManager.updateSessionStatus(sessionId, status);
          }
        } catch (error) {
          // If we can'\''t get updated status, use existing status
          // This is non-critical, so we just log and continue
          logger.debug("Could not fetch updated status for channel", {
            sessionId,
            error: error.message,
            correlationId: req.correlationId
          });
        }`;

const newTry = `        // Get status - priorizar disconnected do sessionManager (connection.update)
        // Se ja temos disconnected, nao sobrescrever com getSessionStatus (WPPConnect pode retornar stale)
        if (status !== "disconnected") {
        try {
          const statusResult = await wppconnectAdapter.getSessionStatus(
            sessionId,
            req.correlationId || uuidv4()
          );
          
          const rawStatus = statusResult.status || statusResult.state || statusResult.connectionState;
          
          if (rawStatus) {
            const statusLower = rawStatus.toLowerCase();
            if (statusLower.includes("connected") || statusLower === "open") {
              status = "connected";
            } else if (statusLower.includes("disconnected") || statusLower === "close" || statusLower === "closed") {
              status = "disconnected";
            } else if (statusLower.includes("qr") || statusLower === "qr_required" || 
                       statusLower.includes("initializing") || statusLower.includes("starting")) {
              status = "qr_required";
            } else {
              status = statusLower;
            }
            
            // Update status in session manager
            sessionManager.updateSessionStatus(sessionId, status);
          }
        } catch (error) {
          // If we can'\''t get updated status, use existing status
          // This is non-critical, so we just log and continue
          logger.debug("Could not fetch updated status for channel", {
            sessionId,
            error: error.message,
            correlationId: req.correlationId
          });
        }
        }`;

c = c.replace(oldTry, newTry);
if (c === fs.readFileSync(p, "utf8")) {
  console.error("   ERRO: trecho nao encontrado em api.js");
  process.exit(1);
}
fs.writeFileSync(p, c);
console.log("   OK");
'

# 4) Restart
echo "4) Reiniciando gateway..."
docker restart "$CONTAINER" 2>/dev/null || true

echo ""
echo "=== Concluido ==="
echo "Backup: $BACKUP.index.js e $BACKUP.api.js"
echo "Rollback: docker cp $BACKUP.index.js $CONTAINER:/app/src/index.js && docker cp $BACKUP.api.js $CONTAINER:/app/src/routes/api.js && docker restart $CONTAINER"
