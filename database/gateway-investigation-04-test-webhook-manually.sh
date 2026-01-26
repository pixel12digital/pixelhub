#!/bin/bash

# ============================================
# Script 04: Testar Webhook Manualmente
# ============================================
# Execute este script no VPS do gateway
# Objetivo: Testar se o gateway consegue enviar webhook

echo "=== Script 04: Testar Webhook Manualmente ==="
echo ""
echo "Este script testa:"
echo "1. Se o gateway consegue fazer requisição HTTP para o webhook"
echo "2. Se o webhook está acessível do gateway"
echo "3. Resposta do webhook"
echo ""
echo "INSTRUÇÕES:"
echo "1. Execute este script no VPS do gateway"
echo "2. Você precisará da URL do webhook (ex: https://painel.pixel12digital.com.br/api/whatsapp/webhook)"
echo "3. Copie TODA a saída e me envie"
echo ""
echo "============================================"
echo ""

# URL do webhook (ajuste conforme necessário)
WEBHOOK_URL="${WEBHOOK_URL:-https://painel.pixel12digital.com.br/api/whatsapp/webhook}"

echo "1. Testando conectividade com o webhook..."
echo "----------------------------------------"
echo "URL do webhook: $WEBHOOK_URL"

# Testa se o webhook está acessível
CONNECTIVITY_TEST=$(curl -s -o /dev/null -w "HTTP_CODE:%{http_code}\nTIME:%{time_total}s\n" \
    --max-time 10 \
    "$WEBHOOK_URL" 2>&1)

HTTP_CODE=$(echo "$CONNECTIVITY_TEST" | grep "HTTP_CODE" | cut -d':' -f2)
TIME=$(echo "$CONNECTIVITY_TEST" | grep "TIME" | cut -d':' -f2)

if [ -n "$HTTP_CODE" ]; then
    echo "   ✅ Webhook está acessível"
    echo "   HTTP Code: $HTTP_CODE"
    echo "   Tempo de resposta: ${TIME}s"
else
    echo "   ❌ Webhook não está acessível"
    echo "   Erro: $CONNECTIVITY_TEST"
fi

echo ""
echo "2. Enviando payload de teste para o webhook..."
echo "----------------------------------------"

# Payload de teste (simula uma mensagem)
TEST_PAYLOAD='{
  "event": "message",
  "session": {
    "id": "pixel12digital",
    "name": "pixel12digital"
  },
  "message": {
    "id": "test_'$(date +%s)'",
    "from": "554796164699@c.us",
    "to": "554797309525@c.us",
    "text": "teste-manual-gateway",
    "timestamp": '$(date +%s)'
  },
  "raw": {
    "provider": "wppconnect",
    "payload": {
      "event": "message",
      "from": "554796164699@c.us",
      "to": "554797309525@c.us"
    }
  }
}'

echo "   Payload:"
echo "$TEST_PAYLOAD" | jq . 2>/dev/null || echo "$TEST_PAYLOAD"

echo ""
echo "   Enviando requisição..."

RESPONSE=$(curl -s -w "\n\nHTTP_CODE:%{http_code}\nTIME:%{time_total}s" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-Webhook-Secret: test" \
    --max-time 30 \
    -d "$TEST_PAYLOAD" \
    "$WEBHOOK_URL" 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d':' -f2)
TIME=$(echo "$RESPONSE" | grep "TIME" | cut -d':' -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d' | sed '/TIME/d')

echo ""
echo "   Resposta do webhook:"
echo "   HTTP Code: $HTTP_CODE"
echo "   Tempo: ${TIME}s"
echo "   Body:"
echo "$BODY" | head -20

if [ "$HTTP_CODE" = "200" ]; then
    echo ""
    echo "   ✅ Webhook respondeu com sucesso!"
else
    echo ""
    echo "   ⚠️  Webhook retornou HTTP $HTTP_CODE"
fi

echo ""
echo "3. Verificando DNS e resolução de domínio..."
echo "----------------------------------------"

DOMAIN=$(echo "$WEBHOOK_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')
echo "   Domínio: $DOMAIN"

DNS_RESULT=$(nslookup "$DOMAIN" 2>&1 | grep -A 2 "Name:" | head -3)
if [ -n "$DNS_RESULT" ]; then
    echo "   ✅ DNS resolvido:"
    echo "$DNS_RESULT" | sed 's/^/      /'
else
    echo "   ⚠️  Não foi possível resolver DNS"
fi

echo ""
echo "=== Fim do Script 04 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "Execute o Script 05 após me enviar a saída deste script"

