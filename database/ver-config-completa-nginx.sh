#!/bin/bash
# Ver configuração completa do Nginx

echo "=== CONFIGURAÇÃO COMPLETA DO NGINX ==="
echo ""

# 1. Ver arquivo whatsapp-multichannel completo
echo "1. ARQUIVO whatsapp-multichannel (COMPLETO):"
echo "=========================================="
cat /etc/nginx/sites-available/whatsapp-multichannel
echo ""

# 2. Verificar se há configuração para wpp.pixel12digital.com.br em sites-available
echo "2. ARQUIVOS EM sites-available:"
ls -la /etc/nginx/sites-available/ | grep -i "wpp\|whatsapp"
echo ""

# 3. Procurar por todas as configurações de proxy e timeout
echo "3. TODAS AS CONFIGURAÇÕES DE TIMEOUT:"
grep -r "proxy_read_timeout\|proxy_connect_timeout\|proxy_send_timeout" /etc/nginx/ 2>/dev/null
echo ""

# 4. Verificar nginx.conf principal
echo "4. NGINX.CONF PRINCIPAL (seções relevantes):"
grep -A 5 -B 5 "proxy\|timeout" /etc/nginx/nginx.conf 2>/dev/null | head -30
echo ""

echo "=== FIM ==="
