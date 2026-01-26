#!/bin/bash

echo "=== Script 04: Verificar DNS e Reiniciar Gateway ==="
echo ""

WEBHOOK_DOMAIN="painel.pixel12digital.com.br"
CORRECT_WEBHOOK_URL="https://painel.pixel12digital.com.br/api/whatsapp/webhook"

echo "1. Verificando resolução DNS do domínio..."
echo "----------------------------------------"
echo "   Domínio: $WEBHOOK_DOMAIN"

# Testa com diferentes métodos
echo ""
echo "   Teste 1: nslookup..."
nslookup "$WEBHOOK_DOMAIN" 2>&1 | head -10

echo ""
echo "   Teste 2: dig..."
dig "$WEBHOOK_DOMAIN" +short 2>&1 | head -5

echo ""
echo "   Teste 3: getent hosts..."
getent hosts "$WEBHOOK_DOMAIN" 2>&1

echo ""
echo "   Teste 4: ping (1 pacote)..."
ping -c 1 "$WEBHOOK_DOMAIN" 2>&1 | head -5

echo ""
echo "2. Verificando se o domínio está acessível via HTTPS..."
echo "----------------------------------------"
echo "   Testando: $CORRECT_WEBHOOK_URL"

# Testa conectividade HTTPS
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME:%{time_total}s\nSSL_VERIFY:%{ssl_verify_result}" \
    --max-time 30 \
    --insecure \
    "$CORRECT_WEBHOOK_URL" 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d':' -f2)
TIME=$(echo "$RESPONSE" | grep "TIME" | cut -d':' -f2)
SSL_VERIFY=$(echo "$RESPONSE" | grep "SSL_VERIFY" | cut -d':' -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d' | sed '/TIME/d' | sed '/SSL_VERIFY/d' | head -5)

echo "   HTTP Code: $HTTP_CODE"
echo "   Tempo: ${TIME}s"
echo "   SSL Verify: $SSL_VERIFY"
if [ -n "$BODY" ]; then
    echo "   Resposta: $BODY"
fi

echo ""
echo "3. Verificando configuração DNS do sistema..."
echo "----------------------------------------"
echo "   /etc/resolv.conf:"
cat /etc/resolv.conf 2>/dev/null | head -10 || echo "   (não encontrado)"

echo ""
echo "4. Testando IP diretamente (se DNS falhar)..."
echo "----------------------------------------"
# Tenta descobrir o IP do domínio
IP=$(dig +short "$WEBHOOK_DOMAIN" 2>/dev/null | head -1)
if [ -n "$IP" ]; then
    echo "   IP encontrado: $IP"
    echo "   Testando conectividade direta ao IP..."
    curl -s -w "\nHTTP_CODE:%{http_code}\n" --max-time 10 \
        -H "Host: $WEBHOOK_DOMAIN" \
        "https://$IP/api/whatsapp/webhook" 2>&1 | head -5
else
    echo "   ⚠️  Não foi possível obter IP do domínio"
fi

echo ""
echo "5. Reiniciando gateway..."
echo "----------------------------------------"
echo "   Reiniciando container gateway-wrapper..."

docker restart gateway-wrapper

if [ $? -eq 0 ]; then
    echo "   ✅ Gateway reiniciado com sucesso!"
    echo ""
    echo "   Aguardando 10 segundos para o gateway inicializar..."
    sleep 10
    
    echo ""
    echo "   Verificando se o gateway está rodando..."
    docker ps | grep "gateway-wrapper" || echo "   ⚠️  Gateway não está rodando"
    
    echo ""
    echo "   Verificando logs recentes do gateway..."
    docker logs --tail 20 gateway-wrapper 2>&1 | grep -i "webhook\|error\|started" | head -10
else
    echo "   ❌ Erro ao reiniciar gateway"
fi

echo ""
echo "6. Verificando se webhook está funcionando após reiniciar..."
echo "----------------------------------------"
echo "   Aguardando mais 5 segundos..."
sleep 5

echo ""
echo "   Verificando logs de webhook (últimas 10 linhas)..."
docker logs --tail 50 gateway-wrapper 2>&1 | grep -i "webhook" | tail -10 || echo "   (nenhuma linha encontrada)"

echo ""
echo "=== Fim do Script 04 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "1. Verifique se o DNS está funcionando corretamente"
echo "2. Se DNS não funcionar, pode ser necessário:"
echo "   - Configurar DNS no /etc/resolv.conf"
echo "   - Verificar firewall/rede"
echo "   - Usar IP direto ao invés de domínio"
echo ""
echo "Envie esta saída completa para análise"

