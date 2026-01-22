#!/bin/bash

###############################################################################
# Script: Desabilitar Server Block do Agentes na Porta 8443
# Objetivo: Permitir que nosso server block do gateway funcione
###############################################################################

AGENTES_FILE="/etc/nginx/sites-enabled/agentes_ssl_8443"
BACKUP_FILE="${AGENTES_FILE}.backup_$(date +%Y%m%d_%H%M%S)"

echo "Fazendo backup..."
cp "$AGENTES_FILE" "$BACKUP_FILE"

echo "Comentando server block HTTPS do agentes na porta 8443..."

# Comentar o server block HTTPS (porta 8443) do agentes
# Manter o server block HTTP (porta 8080) funcionando
sed -i '/# Configuração HTTPS na porta 8443/,/^}$/s/^/#/' "$AGENTES_FILE"

echo "Validando configuração..."
if nginx -t; then
    echo "✓ Sintaxe válida"
    echo "Recarregando Nginx..."
    systemctl reload nginx
    echo "✓ Nginx recarregado"
    echo ""
    echo "Teste agora:"
    echo "  curl -k -I https://wpp.pixel12digital.com.br:8443"
    echo ""
    echo "Deve retornar 401 (autenticação funcionando)!"
else
    echo "✗ Erro na sintaxe. Restaurando backup..."
    cp "$BACKUP_FILE" "$AGENTES_FILE"
    exit 1
fi

