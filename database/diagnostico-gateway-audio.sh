#!/bin/bash
# Script de diagnóstico para VPS do gateway
# Verifica configurações de timeout e status do gateway

echo "=== DIAGNÓSTICO: Gateway WhatsApp - Envio de Áudio ==="
echo ""

# 1. Verifica timeouts do Nginx
echo "1. CONFIGURAÇÃO DO NGINX:"
if [ -f /etc/nginx/nginx.conf ]; then
    echo "   - Arquivo nginx.conf encontrado"
    echo "   - Timeouts configurados:"
    grep -i "timeout\|proxy_read_timeout\|proxy_connect_timeout\|proxy_send_timeout" /etc/nginx/nginx.conf /etc/nginx/sites-enabled/* 2>/dev/null | grep -v "^#" | head -10
else
    echo "   ⚠️ Arquivo nginx.conf não encontrado"
fi
echo ""

# 2. Verifica timeouts do Node.js/WPPConnect (se aplicável)
echo "2. PROCESSOS DO GATEWAY:"
if command -v pm2 &> /dev/null; then
    echo "   - PM2 instalado"
    echo "   - Processos ativos:"
    pm2 list
    echo ""
    echo "   - Logs recentes (últimas 50 linhas):"
    pm2 logs --lines 50 --nostream 2>/dev/null | tail -20
else
    echo "   ⚠️ PM2 não encontrado"
fi
echo ""

# 3. Verifica uso de recursos
echo "3. RECURSOS DO SERVIDOR:"
echo "   - CPU:"
top -bn1 | grep "Cpu(s)" | head -1
echo "   - Memória:"
free -h | head -2
echo "   - Espaço em disco:"
df -h / | tail -1
echo ""

# 4. Verifica logs do gateway (últimas 100 linhas com "audio" ou "timeout")
echo "4. LOGS RECENTES (áudio/timeout):"
if [ -d "/var/log" ]; then
    find /var/log -name "*.log" -type f -exec grep -l "audio\|timeout\|WPPConnect" {} \; 2>/dev/null | head -5 | while read logfile; do
        echo "   - $logfile:"
        grep -i "audio\|timeout\|wppconnect" "$logfile" 2>/dev/null | tail -5
    done
fi
echo ""

# 5. Verifica se o gateway está respondendo
echo "5. TESTE DE CONECTIVIDADE:"
GATEWAY_URL="${GATEWAY_URL:-https://wpp.pixel12digital.com.br}"
echo "   - Testando: $GATEWAY_URL"
if command -v curl &> /dev/null; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$GATEWAY_URL/api/health" 2>/dev/null)
    if [ "$HTTP_CODE" = "200" ]; then
        echo "   ✅ Gateway respondendo (HTTP $HTTP_CODE)"
    else
        echo "   ⚠️ Gateway retornou HTTP $HTTP_CODE"
    fi
else
    echo "   ⚠️ curl não encontrado"
fi
echo ""

# 6. Verifica configurações do Node.js (se aplicável)
echo "6. CONFIGURAÇÃO DO NODE.JS:"
if command -v node &> /dev/null; then
    echo "   - Versão Node.js: $(node --version)"
    if [ -f "package.json" ]; then
        echo "   - package.json encontrado"
        grep -i "timeout\|wppconnect" package.json 2>/dev/null | head -5
    fi
fi
echo ""

# 7. Verifica processos relacionados ao WhatsApp
echo "7. PROCESSOS RELACIONADOS:"
ps aux | grep -i "whatsapp\|wpp\|node" | grep -v grep | head -5
echo ""

echo "=== FIM DO DIAGNÓSTICO ==="
echo ""
echo "PRÓXIMOS PASSOS:"
echo "1. Verifique se o timeout do Nginx está configurado para pelo menos 120s"
echo "2. Verifique se o WPPConnect tem timeout configurado para mais de 30s"
echo "3. Verifique os logs do gateway para erros específicos de áudio"
