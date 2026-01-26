#!/bin/bash

# ============================================
# Script 03: Verificar Logs do Gateway
# ============================================
# Execute este script no VPS do gateway
# Objetivo: Verificar logs relacionados a webhooks

echo "=== Script 03: Verificar Logs do Gateway ==="
echo ""
echo "Este script verifica:"
echo "1. Logs recentes relacionados a webhooks"
echo "2. Tentativas de envio de webhook"
echo "3. Erros relacionados a webhook"
echo ""
echo "INSTRUÇÕES:"
echo "1. Execute este script no VPS do gateway"
echo "2. Copie TODA a saída e me envie"
echo ""
echo "============================================"
echo ""

GATEWAY_DIR="${GATEWAY_DIR:-/opt/wpp-gateway}"

echo "1. Procurando arquivos de log..."
echo "----------------------------------------"

# Procura logs em locais comuns
find "$GATEWAY_DIR" . -type f -name "*.log" 2>/dev/null | head -20 | while read logfile; do
    if [ -f "$logfile" ]; then
        SIZE=$(du -h "$logfile" | cut -f1)
        echo "   📄 $logfile ($SIZE)"
    fi
done

echo ""
echo "2. Verificando logs recentes relacionados a webhook (últimas 2 horas)..."
echo "----------------------------------------"

LOG_FILES=(
    "$GATEWAY_DIR/logs/app.log"
    "$GATEWAY_DIR/logs/webhook.log"
    "$GATEWAY_DIR/logs/error.log"
    "$GATEWAY_DIR/logs/gateway.log"
    "./logs/app.log"
    "./logs/webhook.log"
    "./logs/error.log"
    "./logs/gateway.log"
    "/var/log/wpp-gateway/*.log"
)

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ] || [ -n "$(ls $log 2>/dev/null)" ]; then
        echo ""
        echo "   📄 Analisando: $log"
        echo "   ----------------------------------------"
        
        # Busca linhas relacionadas a webhook nas últimas 2 horas
        if command -v journalctl &> /dev/null; then
            # Sistema com systemd
            journalctl -u wpp-gateway* --since "2 hours ago" 2>/dev/null | grep -i "webhook" | tail -20 || echo "   (nenhuma linha encontrada)"
        else
            # Logs em arquivo
            if [ -f "$log" ]; then
                # Tenta buscar por timestamp (ajuste conforme formato do log)
                grep -i "webhook" "$log" 2>/dev/null | tail -30 || echo "   (nenhuma linha encontrada)"
            fi
        fi
    fi
done

echo ""
echo "3. Verificando erros recentes..."
echo "----------------------------------------"

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ]; then
        ERROR_COUNT=$(grep -i "error\|failed\|exception" "$log" 2>/dev/null | wc -l)
        if [ "$ERROR_COUNT" -gt 0 ]; then
            echo "   📄 $log: $ERROR_COUNT linhas com erro"
            echo "   Últimos 5 erros:"
            grep -i "error\|failed\|exception" "$log" 2>/dev/null | tail -5 | sed 's/^/      /'
        fi
    fi
done

echo ""
echo "4. Verificando tentativas de envio de webhook..."
echo "----------------------------------------"

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ]; then
        WEBHOOK_ATTEMPTS=$(grep -i "webhook.*send\|send.*webhook\|posting.*webhook" "$log" 2>/dev/null | tail -10)
        if [ -n "$WEBHOOK_ATTEMPTS" ]; then
            echo "   📄 $log:"
            echo "$WEBHOOK_ATTEMPTS" | sed 's/^/      /'
        fi
    fi
done

echo ""
echo "=== Fim do Script 03 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "Execute o Script 04 após me enviar a saída deste script"

