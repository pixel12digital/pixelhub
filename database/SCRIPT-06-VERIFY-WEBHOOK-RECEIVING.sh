#!/bin/bash

echo "=== Script 06: Verificar se Webhook está Recebendo Mensagens ==="
echo ""

CONTAINER_NAME="gateway-wrapper"
WEBHOOK_URL="https://hub.pixel12digital.com.br/api/whatsapp/webhook"

echo "1. Verificando logs recentes de webhook entregues com sucesso..."
echo "----------------------------------------"
echo "   Últimas 20 entregas de webhook (últimos 5 minutos):"
docker logs --since 5m "$CONTAINER_NAME" 2>&1 | grep "Webhook delivered successfully" | tail -20

echo ""
echo "2. Verificando tentativas de webhook que falharam..."
echo "----------------------------------------"
echo "   Últimas 20 falhas de webhook (últimos 5 minutos):"
docker logs --since 5m "$CONTAINER_NAME" 2>&1 | grep -i "webhook.*fail\|webhook.*error\|webhook.*timeout" | tail -20

echo ""
echo "3. Verificando eventos recebidos do WPPConnect..."
echo "----------------------------------------"
echo "   Últimos 10 eventos recebidos (últimos 2 minutos):"
docker logs --since 2m "$CONTAINER_NAME" 2>&1 | grep "Received webhook event from WPPConnect" | tail -10

echo ""
echo "4. Verificando eventos enfileirados para envio..."
echo "----------------------------------------"
echo "   Últimos 10 eventos enfileirados (últimos 2 minutos):"
docker logs --since 2m "$CONTAINER_NAME" 2>&1 | grep "Webhook event queued" | tail -10

echo ""
echo "5. Estatísticas de webhook (últimos 10 minutos)..."
echo "----------------------------------------"
TOTAL_SENT=$(docker logs --since 10m "$CONTAINER_NAME" 2>&1 | grep -c "Webhook delivered successfully")
TOTAL_FAILED=$(docker logs --since 10m "$CONTAINER_NAME" 2>&1 | grep -c "Webhook delivery failed")
TOTAL_QUEUED=$(docker logs --since 10m "$CONTAINER_NAME" 2>&1 | grep -c "Webhook event queued")

echo "   Webhooks entregues com sucesso: $TOTAL_SENT"
echo "   Webhooks que falharam: $TOTAL_FAILED"
echo "   Eventos enfileirados: $TOTAL_QUEUED"

if [ "$TOTAL_FAILED" -gt 0 ]; then
    echo ""
    echo "   ⚠️  Há webhooks falhando! Verificando erros..."
    docker logs --since 10m "$CONTAINER_NAME" 2>&1 | grep "Webhook delivery failed" | tail -5
fi

echo ""
echo "6. Testando conectividade atual com o webhook..."
echo "----------------------------------------"
echo "   Testando: $WEBHOOK_URL"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME:%{time_total}s" \
    -X POST \
    -H "Content-Type: application/json" \
    --max-time 30 \
    -d '{"event":"test","message":{"text":"teste-conectividade-script"}}' \
    "$WEBHOOK_URL" 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d':' -f2)
TIME=$(echo "$RESPONSE" | grep "TIME" | cut -d':' -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d' | sed '/TIME/d' | head -10)

echo "   HTTP Code: $HTTP_CODE"
echo "   Tempo: ${TIME}s"
if [ -n "$BODY" ]; then
    echo "   Resposta: $BODY"
fi

echo ""
echo "7. Verificando URL configurada no .env..."
echo "----------------------------------------"
GATEWAY_DIR="/opt/pixel12-whatsapp-gateway"
CURRENT_URL=$(grep "WEBHOOK_URL=" "$GATEWAY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'")
echo "   URL no .env: $CURRENT_URL"

if [ "$CURRENT_URL" = "$WEBHOOK_URL" ]; then
    echo "   ✅ URL está correta!"
else
    echo "   ⚠️  URL está diferente! Corrigindo..."
    cp "$GATEWAY_DIR/.env" "$GATEWAY_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)"
    sed -i "s|WEBHOOK_URL=.*|WEBHOOK_URL=$WEBHOOK_URL|g" "$GATEWAY_DIR/.env"
    echo "   ✅ URL corrigida. Reinicie o gateway para aplicar."
fi

echo ""
echo "=== Fim do Script 06 ==="
echo ""
echo "CONCLUSÃO:"
echo "O gateway está configurado para enviar webhooks para: $WEBHOOK_URL"
echo "Verifique se o webhook está recebendo e processando as mensagens corretamente."
echo ""
echo "PRÓXIMO PASSO:"
echo "Verifique no sistema (hub.pixel12digital.com.br) se as mensagens estão chegando"

