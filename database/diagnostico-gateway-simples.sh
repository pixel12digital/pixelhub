#!/bin/bash
# Script simplificado - execute direto na VPS do gateway

echo "=== DIAGNÓSTICO RÁPIDO: Gateway WhatsApp ==="
echo ""

# 1. Timeouts do Nginx
echo "1. TIMEOUTS DO NGINX:"
if [ -f /etc/nginx/sites-enabled/default ] || [ -f /etc/nginx/sites-enabled/* ]; then
    echo "   Timeouts configurados:"
    grep -h "proxy_read_timeout\|proxy_connect_timeout\|proxy_send_timeout" /etc/nginx/sites-enabled/* 2>/dev/null | grep -v "^#" || echo "   ⚠️ Nenhum timeout encontrado (pode estar usando padrão de 60s)"
else
    echo "   ⚠️ Arquivos de configuração não encontrados"
fi
echo ""

# 2. Processos PM2
echo "2. PROCESSOS DO GATEWAY (PM2):"
if command -v pm2 &> /dev/null; then
    pm2 list
    echo ""
    echo "   Logs recentes com 'timeout' ou 'audio':"
    pm2 logs --lines 50 --nostream 2>/dev/null | grep -i "timeout\|audio" | tail -10 || echo "   Nenhum log encontrado"
else
    echo "   ⚠️ PM2 não encontrado"
fi
echo ""

# 3. Teste de conectividade
echo "3. TESTE DO GATEWAY:"
curl -s -o /dev/null -w "   HTTP Code: %{http_code}\n   Tempo total: %{time_total}s\n" --max-time 10 https://wpp.pixel12digital.com.br/api/health 2>/dev/null || echo "   ⚠️ Erro ao conectar"
echo ""

# 4. Recursos do servidor
echo "4. RECURSOS:"
echo "   Load: $(uptime | awk -F'load average:' '{print $2}')"
echo "   Memória: $(free -h | grep Mem | awk '{print $3 "/" $2}')"
echo ""

echo "=== FIM ==="
