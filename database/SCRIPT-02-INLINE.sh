#!/bin/bash

echo "=== Script 02: Verificar Configuração do Webhook no .env e Logs ==="
echo ""

GATEWAY_DIR="/opt/pixel12-whatsapp-gateway"

echo "1. Verificando arquivo .env do gateway..."
echo "----------------------------------------"
if [ -f "$GATEWAY_DIR/.env" ]; then
    echo "✅ Arquivo encontrado: $GATEWAY_DIR/.env"
    echo ""
    echo "Conteúdo relacionado a webhook (secrets ocultos):"
    grep -i "webhook\|WEBHOOK" "$GATEWAY_DIR/.env" 2>/dev/null | sed 's/\(.*SECRET.*\|.*PASSWORD.*\|.*TOKEN.*\)=.*/\1=***HIDDEN***/g' || echo "   (nenhuma linha encontrada)"
    echo ""
    echo "Todas as variáveis (secrets ocultos):"
    cat "$GATEWAY_DIR/.env" | sed 's/\(.*SECRET.*\|.*PASSWORD.*\|.*TOKEN.*\)=.*/\1=***HIDDEN***/g' | head -50
else
    echo "❌ Arquivo .env não encontrado em $GATEWAY_DIR/.env"
fi

echo ""
echo "2. Verificando logs relacionados a webhook (últimas 50 linhas)..."
echo "----------------------------------------"

LOG_FILES=(
    "$GATEWAY_DIR/wrapper/logs/combined.log"
    "$GATEWAY_DIR/wrapper/logs/error.log"
    "/var/log/wpp-ui.log"
    "/root/.pm2/logs/whatsapp-3000-error.log"
)

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ]; then
        echo ""
        echo "📄 Analisando: $log"
        echo "   Tamanho: $(du -h "$log" | cut -f1)"
        echo "   Últimas 30 linhas relacionadas a webhook:"
        grep -i "webhook" "$log" 2>/dev/null | tail -30 || echo "   (nenhuma linha encontrada)"
        echo ""
        echo "   Últimas 20 linhas com erro relacionado a webhook:"
        grep -i "webhook.*error\|error.*webhook\|webhook.*fail\|fail.*webhook" "$log" 2>/dev/null | tail -20 || echo "   (nenhuma linha encontrada)"
    fi
done

echo ""
echo "3. Verificando tentativas de envio de webhook (últimas 2 horas)..."
echo "----------------------------------------"

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ]; then
        echo ""
        echo "📄 $log:"
        # Busca por padrões de envio de webhook
        grep -i "post.*webhook\|send.*webhook\|webhook.*post\|webhook.*send\|calling.*webhook" "$log" 2>/dev/null | tail -20 || echo "   (nenhuma tentativa encontrada)"
    fi
done

echo ""
echo "4. Verificando configuração de webhook no código/config..."
echo "----------------------------------------"

# Procura arquivos de configuração JSON/YAML
CONFIG_FILES=(
    "$GATEWAY_DIR/config.json"
    "$GATEWAY_DIR/config/config.json"
    "$GATEWAY_DIR/wrapper/config.json"
    "$GATEWAY_DIR/package.json"
)

for config in "${CONFIG_FILES[@]}"; do
    if [ -f "$config" ]; then
        echo "✅ Encontrado: $config"
        if grep -qi "webhook" "$config" 2>/dev/null; then
            echo "   Conteúdo relacionado a webhook:"
            grep -i "webhook" "$config" 2>/dev/null | head -20
        fi
        echo ""
    fi
done

echo ""
echo "5. Verificando se o gateway está escutando na porta 3000..."
echo "----------------------------------------"
netstat -tulpn 2>/dev/null | grep ":3000\|:21465" || ss -tulpn 2>/dev/null | grep ":3000\|:21465" || echo "   Não foi possível verificar portas"

echo ""
echo "6. Testando API do gateway na porta 3000..."
echo "----------------------------------------"
echo "Testando http://localhost:3000/health..."
curl -s -w "\nHTTP_CODE:%{http_code}\n" --max-time 5 "http://localhost:3000/health" 2>&1 | head -10

echo ""
echo "Testando http://localhost:3000/api/webhooks..."
curl -s -w "\nHTTP_CODE:%{http_code}\n" --max-time 5 "http://localhost:3000/api/webhooks" 2>&1 | head -10

echo ""
echo "Testando http://localhost:3000/api/channels..."
curl -s -w "\nHTTP_CODE:%{http_code}\n" --max-time 5 "http://localhost:3000/api/channels" 2>&1 | head -10

echo ""
echo "=== Fim do Script 02 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "Envie esta saída completa para análise"

