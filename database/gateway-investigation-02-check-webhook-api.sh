#!/bin/bash

# ============================================
# Script 02: Verificar Webhook via API do Gateway
# ============================================
# Execute este script no VPS do gateway
# Objetivo: Verificar configuração do webhook via API

echo "=== Script 02: Verificar Webhook via API ==="
echo ""
echo "Este script verifica:"
echo "1. Configuração do webhook via API do gateway"
echo "2. Status do webhook"
echo "3. Histórico de tentativas de envio"
echo ""
echo "INSTRUÇÕES:"
echo "1. Execute este script no VPS do gateway"
echo "2. Você precisará da URL base do gateway e token de API (se necessário)"
echo "3. Copie TODA a saída e me envie"
echo ""
echo "============================================"
echo ""

# Configurações (ajuste conforme necessário)
GATEWAY_BASE_URL="${GATEWAY_BASE_URL:-http://localhost:8080}"
API_TOKEN="${API_TOKEN:-}"

echo "1. Testando conectividade com o gateway..."
echo "----------------------------------------"
echo "URL base: $GATEWAY_BASE_URL"

# Testa health check
HEALTH_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$GATEWAY_BASE_URL/health" 2>&1)
HTTP_CODE=$(echo "$HEALTH_RESPONSE" | grep "HTTP_CODE" | cut -d':' -f2)
BODY=$(echo "$HEALTH_RESPONSE" | sed '/HTTP_CODE/d')

if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Gateway está respondendo (HTTP $HTTP_CODE)"
    echo "   Resposta: $BODY"
else
    echo "⚠️  Gateway retornou HTTP $HTTP_CODE"
    echo "   Resposta: $BODY"
fi

echo ""
echo "2. Verificando configuração de webhooks..."
echo "----------------------------------------"

# Tenta diferentes endpoints de webhook
WEBHOOK_ENDPOINTS=(
    "/api/webhooks"
    "/api/webhook"
    "/webhooks"
    "/webhook"
    "/api/channels/webhook"
)

for endpoint in "${WEBHOOK_ENDPOINTS[@]}"; do
    echo "   Testando: $GATEWAY_BASE_URL$endpoint"
    
    if [ -n "$API_TOKEN" ]; then
        RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -H "Authorization: Bearer $API_TOKEN" \
            -H "Content-Type: application/json" \
            "$GATEWAY_BASE_URL$endpoint" 2>&1)
    else
        RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -H "Content-Type: application/json" \
            "$GATEWAY_BASE_URL$endpoint" 2>&1)
    fi
    
    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d':' -f2)
    BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "201" ]; then
        echo "   ✅ Endpoint encontrado (HTTP $HTTP_CODE)"
        echo "   Resposta: $BODY" | head -20
    elif [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "403" ]; then
        echo "   ⚠️  Endpoint requer autenticação (HTTP $HTTP_CODE)"
    elif [ "$HTTP_CODE" = "404" ]; then
        echo "   ❌ Endpoint não encontrado (HTTP $HTTP_CODE)"
    else
        echo "   ⚠️  Resposta HTTP $HTTP_CODE"
    fi
    echo ""
done

echo ""
echo "3. Verificando canais configurados..."
echo "----------------------------------------"

CHANNELS_ENDPOINTS=(
    "/api/channels"
    "/api/sessions"
    "/channels"
    "/sessions"
)

for endpoint in "${CHANNELS_ENDPOINTS[@]}"; do
    echo "   Testando: $GATEWAY_BASE_URL$endpoint"
    
    if [ -n "$API_TOKEN" ]; then
        RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -H "Authorization: Bearer $API_TOKEN" \
            "$GATEWAY_BASE_URL$endpoint" 2>&1)
    else
        RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            "$GATEWAY_BASE_URL$endpoint" 2>&1)
    fi
    
    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d':' -f2)
    BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo "   ✅ Endpoint encontrado (HTTP $HTTP_CODE)"
        echo "   Resposta: $BODY" | head -30
        break
    fi
done

echo ""
echo "=== Fim do Script 02 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "Execute o Script 03 após me enviar a saída deste script"

