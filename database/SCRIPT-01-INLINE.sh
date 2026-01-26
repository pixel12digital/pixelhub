#!/bin/bash

# ============================================
# Script 01: Verificar Configuração do Webhook
# ============================================
# COPIE E COLE ESTE SCRIPT DIRETAMENTE NO TERMINAL DO VPS

echo "=== Script 01: Verificar Configuração do Webhook ==="
echo ""

# Verifica se está no diretório correto (ajuste conforme necessário)
GATEWAY_DIR="${GATEWAY_DIR:-/opt/wpp-gateway}"
if [ ! -d "$GATEWAY_DIR" ]; then
    echo "⚠️  Diretório do gateway não encontrado em: $GATEWAY_DIR"
    echo "   Procurando em outros locais comuns..."
    echo ""
    
    # Procura em locais comuns
    COMMON_DIRS=(
        "/opt/wpp-gateway"
        "/opt/gateway"
        "/var/www/gateway"
        "/home/*/gateway"
        "/root/gateway"
        "."
    )
    
    for dir in "${COMMON_DIRS[@]}"; do
        if [ -d "$dir" ]; then
            echo "   ✅ Encontrado: $dir"
            GATEWAY_DIR="$dir"
            break
        fi
    done
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
    "/root/.env"
    "/root/config.json"
)

FOUND_CONFIG=false
for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ Encontrado: $file"
        FOUND_CONFIG=true
        # Mostra apenas linhas relacionadas a webhook (sem expor secrets completos)
        if grep -qi "webhook\|WEBHOOK" "$file" 2>/dev/null; then
            echo "   Conteúdo relacionado a webhook:"
            grep -i "webhook\|WEBHOOK" "$file" 2>/dev/null | sed 's/\(secret\|SECRET\|password\|PASSWORD\|token\|TOKEN\)=.*/\1=***HIDDEN***/g' | head -20 || echo "   (não encontrado)"
        fi
        echo ""
    fi
done

if [ "$FOUND_CONFIG" = false ]; then
    echo "   ⚠️  Nenhum arquivo de configuração encontrado nos locais padrão"
    echo "   Procurando em todo o sistema..."
    find /opt /var/www /home /root -maxdepth 3 -name ".env" -o -name "config.json" 2>/dev/null | head -10
fi

echo ""
echo "2. Verificando processos do gateway..."
echo "----------------------------------------"
ps aux | grep -i "wpp\|whatsapp\|gateway\|node\|npm" | grep -v grep || echo "   Nenhum processo encontrado"

echo ""
echo "3. Verificando logs recentes relacionados a webhook..."
echo "----------------------------------------"
LOG_FILES=(
    "$GATEWAY_DIR/logs/app.log"
    "$GATEWAY_DIR/logs/webhook.log"
    "$GATEWAY_DIR/logs/error.log"
    "./logs/app.log"
    "./logs/webhook.log"
    "./logs/error.log"
    "/var/log/wpp-gateway/*.log"
)

FOUND_LOGS=false
for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ] || [ -n "$(ls $log 2>/dev/null)" ]; then
        echo "✅ Log encontrado: $log"
        FOUND_LOGS=true
        echo "   Últimas 10 linhas relacionadas a webhook:"
        grep -i "webhook" "$log" 2>/dev/null | tail -10 || echo "   (nenhuma linha encontrada)"
        echo ""
    fi
done

if [ "$FOUND_LOGS" = false ]; then
    echo "   ⚠️  Nenhum log encontrado nos locais padrão"
    echo "   Procurando logs em todo o sistema..."
    find /opt /var/log /home /root -maxdepth 4 -name "*.log" -type f 2>/dev/null | grep -i "wpp\|gateway\|whatsapp" | head -10
fi

echo ""
echo "4. Verificando se há API do gateway acessível..."
echo "----------------------------------------"
# Tenta encontrar URL do gateway em variáveis de ambiente
if [ -n "$GATEWAY_URL" ]; then
    GATEWAY_URL_FOUND="$GATEWAY_URL"
elif [ -n "$WPP_GATEWAY_BASE_URL" ]; then
    GATEWAY_URL_FOUND="$WPP_GATEWAY_BASE_URL"
else
    GATEWAY_URL_FOUND=""
fi

if [ -n "$GATEWAY_URL_FOUND" ]; then
    echo "   URL encontrada em variável de ambiente: $GATEWAY_URL_FOUND"
    echo "   Testando conectividade..."
    curl -s -o /dev/null -w "   Status HTTP: %{http_code}\n" "$GATEWAY_URL_FOUND/health" 2>/dev/null || echo "   ❌ Não foi possível conectar"
else
    echo "   ⚠️  URL do gateway não encontrada em variáveis de ambiente"
    echo "   Tentando localhost:8080..."
    curl -s -o /dev/null -w "   Status HTTP: %{http_code}\n" "http://localhost:8080/health" 2>/dev/null || echo "   ❌ Não foi possível conectar"
fi

echo ""
echo "5. Verificando variáveis de ambiente relacionadas a webhook..."
echo "----------------------------------------"
env | grep -i "webhook\|gateway\|wpp" | sed 's/\(.*SECRET.*\|.*PASSWORD.*\|.*TOKEN.*\)=.*/\1=***HIDDEN***/g' || echo "   Nenhuma variável encontrada"

echo ""
echo "6. Verificando se há Docker containers rodando..."
echo "----------------------------------------"
if command -v docker &> /dev/null; then
    docker ps | grep -i "wpp\|gateway\|whatsapp" || echo "   Nenhum container relacionado encontrado"
else
    echo "   Docker não está instalado ou não está no PATH"
fi

echo ""
echo "=== Fim do Script 01 ==="
echo ""
echo "PRÓXIMO PASSO:"
echo "Envie esta saída completa para análise"

