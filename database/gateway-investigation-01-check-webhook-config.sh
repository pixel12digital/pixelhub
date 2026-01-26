#!/bin/bash

# ============================================
# Script 01: Verificar Configuração do Webhook
# ============================================
# Execute este script no VPS do gateway
# Objetivo: Verificar se o webhook está configurado corretamente

echo "=== Script 01: Verificar Configuração do Webhook ==="
echo ""
echo "Este script verifica:"
echo "1. Se o webhook está configurado no gateway"
echo "2. Qual URL está configurada"
echo "3. Se há secret configurado"
echo ""
echo "INSTRUÇÕES:"
echo "1. Execute este script no VPS do gateway"
echo "2. Copie TODA a saída e me envie"
echo ""
echo "============================================"
echo ""

# Verifica se está no diretório correto (ajuste conforme necessário)
GATEWAY_DIR="${GATEWAY_DIR:-/opt/wpp-gateway}"
if [ ! -d "$GATEWAY_DIR" ]; then
    echo "⚠️  Diretório do gateway não encontrado em: $GATEWAY_DIR"
    echo "   Ajuste a variável GATEWAY_DIR ou navegue até o diretório do gateway"
    echo ""
fi

# 1. Verifica arquivos de configuração comuns
echo "1. Procurando arquivos de configuração..."
echo "----------------------------------------"

CONFIG_FILES=(
    "$GATEWAY_DIR/.env"
    "$GATEWAY_DIR/config.json"
    "$GATEWAY_DIR/config/config.json"
    "$GATEWAY_DIR/config/webhook.json"
    "./.env"
    "./config.json"
)

for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ Encontrado: $file"
        # Mostra apenas linhas relacionadas a webhook (sem expor secrets completos)
        if grep -qi "webhook\|WEBHOOK" "$file" 2>/dev/null; then
            echo "   Conteúdo relacionado a webhook:"
            grep -i "webhook\|WEBHOOK" "$file" 2>/dev/null | sed 's/\(secret\|SECRET\|password\|PASSWORD\)=.*/\1=***HIDDEN***/g' || echo "   (não encontrado)"
        fi
    fi
done

echo ""
echo "2. Verificando processos do gateway..."
echo "----------------------------------------"
ps aux | grep -i "wpp\|whatsapp\|gateway" | grep -v grep || echo "   Nenhum processo encontrado"

echo ""
echo "3. Verificando logs recentes relacionados a webhook..."
echo "----------------------------------------"
LOG_FILES=(
    "$GATEWAY_DIR/logs/app.log"
    "$GATEWAY_DIR/logs/webhook.log"
    "$GATEWAY_DIR/logs/error.log"
    "./logs/app.log"
    "./logs/webhook.log"
)

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ]; then
        echo "✅ Log encontrado: $log"
        echo "   Últimas 10 linhas relacionadas a webhook:"
        grep -i "webhook" "$log" 2>/dev/null | tail -10 || echo "   (nenhuma linha encontrada)"
        echo ""
    fi
done

echo ""
echo "4. Verificando se há API do gateway acessível..."
echo "----------------------------------------"
# Tenta encontrar URL do gateway em variáveis de ambiente ou arquivos
GATEWAY_URL=$(grep -h "GATEWAY_URL\|BASE_URL\|API_URL" "${CONFIG_FILES[@]}" 2>/dev/null | head -1 | cut -d'=' -f2 | tr -d '"' | tr -d "'" | tr -d ' ')

if [ -n "$GATEWAY_URL" ]; then
    echo "   URL encontrada: $GATEWAY_URL"
    echo "   Testando conectividade..."
    curl -s -o /dev/null -w "   Status HTTP: %{http_code}\n" "$GATEWAY_URL/health" 2>/dev/null || echo "   ❌ Não foi possível conectar"
else
    echo "   ⚠️  URL do gateway não encontrada nos arquivos de configuração"
fi

echo ""
echo "=== Fim do Script 01 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "Execute o Script 02 após me enviar a saída deste script"

