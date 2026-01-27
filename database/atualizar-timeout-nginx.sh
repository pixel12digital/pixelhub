#!/bin/bash
# Script para atualizar timeouts do Nginx de 60s para 120s
# Execute na VPS do gateway

set -e  # Para em caso de erro

CONFIG_FILE="/etc/nginx/sites-available/whatsapp-multichannel"
BACKUP_FILE="${CONFIG_FILE}.backup.$(date +%Y%m%d_%H%M%S)"

echo "=== ATUALIZAÇÃO DE TIMEOUTS DO NGINX ==="
echo ""

# 1. Verificar se arquivo existe
if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ ERRO: Arquivo $CONFIG_FILE não encontrado!"
    echo ""
    echo "Procurando outros arquivos de configuração..."
    find /etc/nginx/sites-available -name "*whatsapp*" -o -name "*wpp*" 2>/dev/null
    exit 1
fi

echo "✅ Arquivo encontrado: $CONFIG_FILE"
echo ""

# 2. Verificar timeouts atuais
echo "1. TIMEOUTS ATUAIS:"
echo "==================="
grep -n "proxy.*timeout" "$CONFIG_FILE" | grep -v "^#" || echo "   Nenhum timeout encontrado (serão adicionados)"
echo ""

# 3. Fazer backup
echo "2. CRIANDO BACKUP:"
echo "=================="
cp "$CONFIG_FILE" "$BACKUP_FILE"
echo "✅ Backup criado: $BACKUP_FILE"
echo ""

# 4. Verificar se já tem timeouts configurados
HAS_TIMEOUTS=$(grep -c "proxy.*timeout.*60s" "$CONFIG_FILE" 2>/dev/null || echo "0")

if [ "$HAS_TIMEOUTS" -gt 0 ]; then
    echo "3. ATUALIZANDO TIMEOUTS EXISTENTES:"
    echo "===================================="
    # Atualiza timeouts de 60s para 120s
    sed -i 's/proxy_connect_timeout 60s/proxy_connect_timeout 120s/g' "$CONFIG_FILE"
    sed -i 's/proxy_send_timeout 60s/proxy_send_timeout 120s/g' "$CONFIG_FILE"
    sed -i 's/proxy_read_timeout 60s/proxy_read_timeout 120s/g' "$CONFIG_FILE"
    echo "✅ Timeouts atualizados de 60s para 120s"
else
    echo "3. ADICIONANDO TIMEOUTS:"
    echo "========================"
    
    # Verifica se há bloco location /
    if grep -q "location /" "$CONFIG_FILE"; then
        # Adiciona após a linha "location / {" ou dentro do bloco
        if grep -q "proxy_pass" "$CONFIG_FILE"; then
            # Adiciona após proxy_pass
            sed -i '/proxy_pass/a\        proxy_connect_timeout 120s;\n        proxy_send_timeout 120s;\n        proxy_read_timeout 120s;' "$CONFIG_FILE"
        else
            # Adiciona dentro do bloco location
            sed -i '/location \//a\        proxy_connect_timeout 120s;\n        proxy_send_timeout 120s;\n        proxy_read_timeout 120s;' "$CONFIG_FILE"
        fi
        echo "✅ Timeouts adicionados no bloco location /"
    else
        # Adiciona no bloco server
        if grep -q "server {" "$CONFIG_FILE"; then
            sed -i '/server {/a\    proxy_connect_timeout 120s;\n    proxy_send_timeout 120s;\n    proxy_read_timeout 120s;' "$CONFIG_FILE"
            echo "✅ Timeouts adicionados no bloco server"
        else
            echo "⚠️  Não foi possível determinar onde adicionar. Adicionando no final do arquivo."
            echo "" >> "$CONFIG_FILE"
            echo "    # Timeouts aumentados para suportar envio de áudio" >> "$CONFIG_FILE"
            echo "    proxy_connect_timeout 120s;" >> "$CONFIG_FILE"
            echo "    proxy_send_timeout 120s;" >> "$CONFIG_FILE"
            echo "    proxy_read_timeout 120s;" >> "$CONFIG_FILE"
        fi
    fi
fi

echo ""

# 5. Verificar timeouts após atualização
echo "4. TIMEOUTS APÓS ATUALIZAÇÃO:"
echo "============================="
grep -n "proxy.*timeout" "$CONFIG_FILE" | grep -v "^#"
echo ""

# 6. Testar configuração
echo "5. TESTANDO CONFIGURAÇÃO:"
echo "========================="
if nginx -t 2>&1; then
    echo "✅ Configuração válida!"
    echo ""
    
    # 7. Recarregar Nginx
    echo "6. RECARREGANDO NGINX:"
    echo "======================"
    if systemctl reload nginx 2>&1; then
        echo "✅ Nginx recarregado com sucesso!"
        echo ""
        echo "=== SUCESSO ==="
        echo "Timeouts atualizados para 120s"
        echo "Nginx recarregado"
        echo ""
        echo "Agora você pode testar o envio de áudio novamente!"
    else
        echo "❌ ERRO ao recarregar Nginx"
        echo "Revertendo alterações..."
        cp "$BACKUP_FILE" "$CONFIG_FILE"
        exit 1
    fi
else
    echo "❌ ERRO: Configuração inválida!"
    echo "Revertendo alterações..."
    cp "$BACKUP_FILE" "$CONFIG_FILE"
    echo "Backup restaurado: $CONFIG_FILE"
    exit 1
fi

echo ""
echo "Backup salvo em: $BACKUP_FILE"
echo "Para reverter, execute: cp $BACKUP_FILE $CONFIG_FILE && systemctl reload nginx"
