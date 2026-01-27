#!/bin/bash
# Script para consultar timeouts do Nginx
# Execute na VPS do gateway

echo "=== CONSULTA DE TIMEOUTS DO NGINX ==="
echo ""

CONFIG_FILE="/etc/nginx/sites-available/whatsapp-multichannel"

# 1. Verificar se arquivo existe
if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Arquivo $CONFIG_FILE não encontrado!"
    echo ""
    echo "Procurando arquivos de configuração:"
    find /etc/nginx/sites-available -type f 2>/dev/null
    exit 1
fi

echo "✅ Arquivo: $CONFIG_FILE"
echo ""

# 2. Mostrar timeouts atuais
echo "1. TIMEOUTS CONFIGURADOS:"
echo "=========================="
TIMEOUTS=$(grep -n "proxy.*timeout" "$CONFIG_FILE" | grep -v "^#")
if [ -z "$TIMEOUTS" ]; then
    echo "⚠️  Nenhum timeout encontrado (usando padrão do Nginx: 60s)"
else
    echo "$TIMEOUTS"
fi
echo ""

# 3. Mostrar contexto (linhas ao redor dos timeouts)
echo "2. CONTEXTO (linhas ao redor):"
echo "=============================="
if grep -q "proxy.*timeout" "$CONFIG_FILE"; then
    grep -B 3 -A 3 "proxy.*timeout" "$CONFIG_FILE" | grep -v "^#" | head -20
else
    echo "Nenhum timeout encontrado para mostrar contexto"
fi
echo ""

# 4. Verificar se precisa atualizar
echo "3. ANÁLISE:"
echo "==========="
NEEDS_UPDATE=false
if grep -q "proxy.*timeout.*60s" "$CONFIG_FILE"; then
    echo "⚠️  Timeouts estão em 60s - RECOMENDADO: aumentar para 120s"
    NEEDS_UPDATE=true
elif grep -q "proxy.*timeout.*120s" "$CONFIG_FILE"; then
    echo "✅ Timeouts já estão em 120s - OK!"
else
    echo "⚠️  Timeouts não configurados - RECOMENDADO: adicionar com 120s"
    NEEDS_UPDATE=true
fi
echo ""

# 5. Mostrar estrutura do arquivo
echo "4. ESTRUTURA DO ARQUIVO:"
echo "========================="
echo "Linhas totais: $(wc -l < "$CONFIG_FILE")"
echo ""
echo "Blocos encontrados:"
grep -n "^server\|^location" "$CONFIG_FILE" | head -10
echo ""

# 6. Sugestão
if [ "$NEEDS_UPDATE" = true ]; then
    echo "5. PRÓXIMO PASSO:"
    echo "================="
    echo "Execute o script de atualização:"
    echo "  ./atualizar-timeout-nginx.sh"
    echo ""
    echo "Ou edite manualmente:"
    echo "  sudo nano $CONFIG_FILE"
fi

echo ""
echo "=== FIM ==="
