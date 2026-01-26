#!/bin/bash

echo "=== Script 03: Corrigir URL do Webhook e Testar Conectividade ==="
echo ""

GATEWAY_DIR="/opt/pixel12-whatsapp-gateway"
CORRECT_WEBHOOK_URL="https://painel.pixel12digital.com.br/api/whatsapp/webhook"

echo "1. Verificando URL atual do webhook..."
echo "----------------------------------------"
CURRENT_URL=$(grep "WEBHOOK_URL=" "$GATEWAY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'")
echo "   URL atual: $CURRENT_URL"
echo "   URL correta: $CORRECT_WEBHOOK_URL"

if [ "$CURRENT_URL" != "$CORRECT_WEBHOOK_URL" ]; then
    echo ""
    echo "   ⚠️  URL está INCORRETA!"
    echo ""
    echo "2. Corrigindo URL do webhook no .env..."
    echo "----------------------------------------"
    
    # Faz backup do .env
    cp "$GATEWAY_DIR/.env" "$GATEWAY_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)"
    echo "   ✅ Backup criado"
    
    # Corrige a URL
    sed -i "s|WEBHOOK_URL=.*|WEBHOOK_URL=$CORRECT_WEBHOOK_URL|g" "$GATEWAY_DIR/.env"
    echo "   ✅ URL corrigida no .env"
    
    # Verifica se foi corrigido
    NEW_URL=$(grep "WEBHOOK_URL=" "$GATEWAY_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    echo "   Nova URL: $NEW_URL"
    
    if [ "$NEW_URL" = "$CORRECT_WEBHOOK_URL" ]; then
        echo "   ✅ URL corrigida com sucesso!"
    else
        echo "   ❌ Erro ao corrigir URL"
    fi
else
    echo "   ✅ URL já está correta"
fi

echo ""
echo "3. Testando conectividade do gateway para o webhook correto..."
echo "----------------------------------------"
echo "   Testando: $CORRECT_WEBHOOK_URL"

# Testa conectividade básica
echo ""
echo "   Teste 1: Conectividade básica (timeout 30s)..."
RESPONSE1=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME:%{time_total}s" --max-time 30 "$CORRECT_WEBHOOK_URL" 2>&1)
HTTP_CODE1=$(echo "$RESPONSE1" | grep "HTTP_CODE" | cut -d':' -f2)
TIME1=$(echo "$RESPONSE1" | grep "TIME" | cut -d':' -f2)
BODY1=$(echo "$RESPONSE1" | sed '/HTTP_CODE/d' | sed '/TIME/d' | head -5)

echo "   HTTP Code: $HTTP_CODE1"
echo "   Tempo: ${TIME1}s"
if [ -n "$BODY1" ]; then
    echo "   Resposta: $BODY1"
fi

echo ""
echo "   Teste 2: Enviando payload de teste (POST)..."
TEST_PAYLOAD='{"event":"message","session":{"id":"pixel12digital"},"message":{"text":"teste-conectividade"}}'
RESPONSE2=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME:%{time_total}s" \
    -X POST \
    -H "Content-Type: application/json" \
    --max-time 30 \
    -d "$TEST_PAYLOAD" \
    "$CORRECT_WEBHOOK_URL" 2>&1)
HTTP_CODE2=$(echo "$RESPONSE2" | grep "HTTP_CODE" | cut -d':' -f2)
TIME2=$(echo "$RESPONSE2" | grep "TIME" | cut -d':' -f2)
BODY2=$(echo "$RESPONSE2" | sed '/HTTP_CODE/d' | sed '/TIME/d' | head -10)

echo "   HTTP Code: $HTTP_CODE2"
echo "   Tempo: ${TIME2}s"
if [ -n "$BODY2" ]; then
    echo "   Resposta: $BODY2"
fi

echo ""
echo "4. Verificando DNS e resolução de domínio..."
echo "----------------------------------------"
DOMAIN=$(echo "$CORRECT_WEBHOOK_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')
echo "   Domínio: $DOMAIN"

DNS_RESULT=$(nslookup "$DOMAIN" 2>&1 | grep -A 2 "Name:" | head -3)
if [ -n "$DNS_RESULT" ]; then
    echo "   ✅ DNS resolvido:"
    echo "$DNS_RESULT" | sed 's/^/      /'
else
    echo "   ⚠️  Não foi possível resolver DNS"
fi

echo ""
echo "5. Verificando se precisa reiniciar o gateway..."
echo "----------------------------------------"
echo "   O gateway precisa ser reiniciado para aplicar a nova URL."
echo "   Verificando como o gateway está rodando..."

# Verifica se está em Docker
if docker ps | grep -q "gateway-wrapper"; then
    echo "   ✅ Gateway está rodando em Docker"
    CONTAINER_NAME=$(docker ps | grep "gateway-wrapper" | awk '{print $NF}')
    echo "   Container: $CONTAINER_NAME"
    echo ""
    echo "   Para reiniciar, execute:"
    echo "   docker restart $CONTAINER_NAME"
elif ps aux | grep -q "node.*src/index.js"; then
    echo "   ✅ Gateway está rodando como processo Node.js"
    PID=$(ps aux | grep "node.*src/index.js" | grep -v grep | awk '{print $2}' | head -1)
    echo "   PID: $PID"
    echo ""
    echo "   Para reiniciar, você pode:"
    echo "   1. Matar o processo: kill $PID"
    echo "   2. Reiniciar o serviço (se houver systemd)"
elif command -v pm2 &> /dev/null; then
    echo "   ✅ Gateway pode estar rodando via PM2"
    echo ""
    echo "   Para reiniciar, execute:"
    echo "   pm2 restart whatsapp-gateway"
else
    echo "   ⚠️  Não foi possível determinar como o gateway está rodando"
fi

echo ""
echo "=== Fim do Script 03 ==="
echo ""
echo "RESUMO:"
echo "1. URL do webhook foi corrigida (se necessário)"
echo "2. Testes de conectividade foram executados"
echo "3. Próximo passo: Reiniciar o gateway para aplicar as mudanças"
echo ""
echo "PRÓXIMO PASSO:"
echo "Reinicie o gateway e me envie a saída deste script"

