#!/bin/bash
# Script para encontrar a configuração do Nginx do gateway

echo "=== PROCURANDO CONFIGURAÇÃO DO NGINX ==="
echo ""

# 1. Listar todos os arquivos em sites-enabled
echo "1. Arquivos em /etc/nginx/sites-enabled/:"
ls -la /etc/nginx/sites-enabled/ 2>/dev/null || echo "   Diretório não encontrado"

echo ""
echo "2. Conteúdo dos arquivos em sites-enabled:"
for file in /etc/nginx/sites-enabled/*; do
    if [ -f "$file" ]; then
        echo "   === $file ==="
        cat "$file" | head -20
        echo ""
    fi
done

echo ""
echo "3. Procurando por 'wpp.pixel12digital' ou 'proxy' em sites-enabled:"
grep -r "wpp.pixel12digital\|proxy_read_timeout\|proxy_connect_timeout" /etc/nginx/sites-enabled/ 2>/dev/null || echo "   Nenhuma configuração encontrada"

echo ""
echo "4. Verificando nginx.conf principal:"
if [ -f /etc/nginx/nginx.conf ]; then
    echo "   Arquivo encontrado, procurando timeouts:"
    grep -i "timeout\|proxy" /etc/nginx/nginx.conf | grep -v "^#" | head -10
else
    echo "   Arquivo não encontrado"
fi

echo ""
echo "5. Procurando em sites-available:"
if [ -d /etc/nginx/sites-available ]; then
    ls -la /etc/nginx/sites-available/
    echo ""
    echo "   Procurando configurações:"
    grep -r "wpp.pixel12digital\|proxy_read_timeout" /etc/nginx/sites-available/ 2>/dev/null | head -10
fi

echo ""
echo "=== FIM ==="
