#!/bin/bash
# Pacote VPS: Patch connection.update para corrigir status falso "conectado"
# EXECUTAR: na VPS, após rodar o bloco de busca abaixo para encontrar o arquivo da rota

set -e

echo "=== BLOCO 0: Localizar rota /api/channels e handler de webhook ==="
echo "Execute primeiro e retorne a saida:"
echo ""
echo 'docker exec gateway-wrapper grep -rn "channels\|/channels\|getSessionStatus" /app/src --include="*.js" 2>/dev/null | head -80'
echo ""
echo 'docker exec gateway-wrapper grep -rn "connection\.update\|eventType\|webhook\|Received" /app/src --include="*.js" 2>/dev/null | head -60'
echo ""
echo "=== Apos ter o nome do arquivo da rota, execute o BLOCO 1 abaixo ==="

# BLOCO 1 - Descomente e ajuste ROUTE_FILE após saber o arquivo
# ROUTE_FILE="/app/src/routes/api.js"  # ou sessions.js, etc.
# docker exec gateway-wrapper sed -n '1,100p' "$ROUTE_FILE"
