#!/bin/bash

echo "=== Script 05: Forçar Recarregamento da Configuração ==="
echo ""

GATEWAY_DIR="/opt/pixel12-whatsapp-gateway"
CONTAINER_NAME="gateway-wrapper"

echo "1. Verificando URL atual no .env..."
echo "----------------------------------------"
CURRENT_URL=$(grep "WEBHOOK_URL=" "$GATEWAY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'")
echo "   URL no .env: $CURRENT_URL"

echo ""
echo "2. Verificando qual URL o gateway está usando (pelos logs)..."
echo "----------------------------------------"
GATEWAY_URL=$(docker logs --tail 100 "$CONTAINER_NAME" 2>&1 | grep "Webhook delivered\|webhook.*url" | tail -1 | grep -oP 'url":"\K[^"]*' || echo "")
if [ -n "$GATEWAY_URL" ]; then
    echo "   URL usada pelo gateway: $GATEWAY_URL"
else
    echo "   ⚠️  Não foi possível determinar URL usada pelo gateway"
fi

echo ""
echo "3. Verificando se há configuração dentro do container..."
echo "----------------------------------------"
echo "   Verificando .env dentro do container..."
docker exec "$CONTAINER_NAME" cat /.env 2>/dev/null | grep "WEBHOOK_URL" || echo "   (não encontrado em /.env)"

echo ""
echo "   Verificando variáveis de ambiente do container..."
docker exec "$CONTAINER_NAME" env 2>/dev/null | grep -i "webhook" || echo "   (nenhuma variável encontrada)"

echo ""
echo "4. Verificando se o gateway lê o .env do host ou tem seu próprio..."
echo "----------------------------------------"
# Verifica se o .env está montado como volume
docker inspect "$CONTAINER_NAME" 2>/dev/null | grep -A 5 "Mounts" | grep -i "\.env\|env" || echo "   (não encontrado nos mounts)"

echo ""
echo "5. Verificando qual domínio funciona (hub vs painel)..."
echo "----------------------------------------"
echo "   Testando hub.pixel12digital.com.br..."
HUB_IP=$(dig +short hub.pixel12digital.com.br 2>/dev/null | head -1)
if [ -n "$HUB_IP" ]; then
    echo "   ✅ hub.pixel12digital.com.br resolve para: $HUB_IP"
else
    echo "   ❌ hub.pixel12digital.com.br não resolve"
fi

echo ""
echo "   Testando painel.pixel12digital.com.br..."
PAINEL_IP=$(dig +short painel.pixel12digital.com.br 2>/dev/null | head -1)
if [ -n "$PAINEL_IP" ]; then
    echo "   ✅ painel.pixel12digital.com.br resolve para: $PAINEL_IP"
else
    echo "   ❌ painel.pixel12digital.com.br não resolve"
fi

echo ""
echo "6. Verificando se ambos os domínios apontam para o mesmo IP..."
echo "----------------------------------------"
if [ -n "$HUB_IP" ] && [ -n "$PAINEL_IP" ]; then
    if [ "$HUB_IP" = "$PAINEL_IP" ]; then
        echo "   ✅ Ambos apontam para o mesmo IP: $HUB_IP"
        echo "   → Podemos usar hub.pixel12digital.com.br que funciona"
    else
        echo "   ⚠️  IPs diferentes:"
        echo "      hub.pixel12digital.com.br → $HUB_IP"
        echo "      painel.pixel12digital.com.br → $PAINEL_IP"
    fi
fi

echo ""
echo "7. Verificando se precisamos usar IP direto ou domínio alternativo..."
echo "----------------------------------------"
if [ -n "$HUB_IP" ]; then
    echo "   Testando conectividade direta ao IP do hub..."
    curl -s -w "\nHTTP_CODE:%{http_code}\n" --max-time 10 \
        -H "Host: hub.pixel12digital.com.br" \
        "https://$HUB_IP/api/whatsapp/webhook" 2>&1 | head -5
fi

echo ""
echo "8. Solução: Atualizar .env para usar hub.pixel12digital.com.br (que funciona)..."
echo "----------------------------------------"
WORKING_URL="https://hub.pixel12digital.com.br/api/whatsapp/webhook"

if [ "$CURRENT_URL" != "$WORKING_URL" ]; then
    echo "   Atualizando .env para usar URL que funciona..."
    cp "$GATEWAY_DIR/.env" "$GATEWAY_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)"
    sed -i "s|WEBHOOK_URL=.*|WEBHOOK_URL=$WORKING_URL|g" "$GATEWAY_DIR/.env"
    echo "   ✅ .env atualizado para: $WORKING_URL"
    
    # Verifica se foi atualizado
    NEW_URL=$(grep "WEBHOOK_URL=" "$GATEWAY_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    if [ "$NEW_URL" = "$WORKING_URL" ]; then
        echo "   ✅ Confirmação: URL atualizada com sucesso"
    fi
else
    echo "   ✅ .env já está usando a URL que funciona"
fi

echo ""
echo "9. Reiniciando gateway para aplicar mudanças..."
echo "----------------------------------------"
docker restart "$CONTAINER_NAME"
sleep 15

echo ""
echo "10. Verificando se gateway está usando a nova URL..."
echo "----------------------------------------"
echo "   Aguardando 10 segundos para gateway processar eventos..."
sleep 10

echo ""
echo "   Últimos logs de webhook (verificando URL usada)..."
docker logs --tail 30 "$CONTAINER_NAME" 2>&1 | grep -i "webhook.*delivered\|webhook.*url" | tail -5

echo ""
echo "=== Fim do Script 05 ==="
echo ""
echo "RESUMO:"
echo "1. Verificamos qual domínio funciona (hub vs painel)"
echo "2. Atualizamos .env para usar o domínio que funciona"
echo "3. Reiniciamos o gateway"
echo ""
echo "PRÓXIMO PASSO:"
echo "Envie esta saída e teste enviando uma mensagem para verificar se funciona"

