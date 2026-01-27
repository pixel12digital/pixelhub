#!/bin/bash
# Script de diagnóstico para VPS do gateway WhatsApp
# Execute na VPS onde está o gateway

echo "=== DIAGNÓSTICO: Gateway WhatsApp - Timeout de Áudio ==="
echo "Data: $(date)"
echo ""

# 1. Verifica timeouts do Nginx
echo "1. CONFIGURAÇÃO DO NGINX:"
if [ -f /etc/nginx/nginx.conf ]; then
    echo "   ✅ Arquivo nginx.conf encontrado"
    echo ""
    echo "   Timeouts no nginx.conf:"
    grep -i "timeout\|proxy_read_timeout\|proxy_connect_timeout\|proxy_send_timeout\|fastcgi_read_timeout" /etc/nginx/nginx.conf 2>/dev/null | grep -v "^#" | head -10
    echo ""
    echo "   Timeouts nos sites:"
    if [ -d /etc/nginx/sites-enabled ]; then
        grep -i "timeout\|proxy_read_timeout\|proxy_connect_timeout\|proxy_send_timeout" /etc/nginx/sites-enabled/* 2>/dev/null | grep -v "^#" | head -10
    fi
else
    echo "   ⚠️ Arquivo nginx.conf não encontrado"
fi
echo ""

# 2. Verifica processos do gateway
echo "2. PROCESSOS DO GATEWAY:"
if command -v pm2 &> /dev/null; then
    echo "   ✅ PM2 instalado"
    echo ""
    echo "   Processos ativos:"
    pm2 list
    echo ""
    echo "   Logs recentes (últimas 30 linhas com 'audio' ou 'timeout'):"
    pm2 logs --lines 100 --nostream 2>/dev/null | grep -i "audio\|timeout\|wppconnect" | tail -30
else
    echo "   ⚠️ PM2 não encontrado"
    echo "   Verificando processos Node.js manualmente:"
    ps aux | grep -i "node\|wpp\|whatsapp" | grep -v grep | head -5
fi
echo ""

# 3. Verifica uso de recursos
echo "3. RECURSOS DO SERVIDOR:"
echo "   CPU:"
top -bn1 | grep "Cpu(s)" | head -1
echo "   Memória:"
free -h | head -2
echo "   Espaço em disco:"
df -h / | tail -1
echo "   Load average:"
uptime
echo ""

# 4. Verifica logs do sistema relacionados a áudio/timeout
echo "4. LOGS DO SISTEMA (áudio/timeout):"
if [ -d "/var/log" ]; then
    echo "   Buscando logs recentes..."
    find /var/log -name "*.log" -type f -mtime -1 -exec grep -l "audio\|timeout\|504\|500" {} \; 2>/dev/null | head -5 | while read logfile; do
        echo "   - $logfile:"
        grep -i "audio\|timeout\|504\|500" "$logfile" 2>/dev/null | tail -5
        echo ""
    done
fi
echo ""

# 5. Verifica se o gateway está respondendo
echo "5. TESTE DE CONECTIVIDADE:"
GATEWAY_URL="${GATEWAY_URL:-https://wpp.pixel12digital.com.br}"
echo "   - Testando: $GATEWAY_URL"
if command -v curl &> /dev/null; then
    echo "   - Health check:"
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$GATEWAY_URL/api/health" 2>/dev/null)
    if [ "$HTTP_CODE" = "200" ]; then
        echo "     ✅ Gateway respondendo (HTTP $HTTP_CODE)"
    else
        echo "     ⚠️ Gateway retornou HTTP $HTTP_CODE"
    fi
    
    echo "   - Teste de timeout (simula requisição longa):"
    TIMEOUT_TEST=$(curl -s -o /dev/null -w "%{http_code}" --max-time 35 "$GATEWAY_URL/api/health" 2>/dev/null)
    echo "     HTTP Code após 35s: $TIMEOUT_TEST"
else
    echo "   ⚠️ curl não encontrado"
fi
echo ""

# 6. Verifica configurações do Node.js/WPPConnect
echo "6. CONFIGURAÇÃO DO NODE.JS/WPPCONNECT:"
if command -v node &> /dev/null; then
    echo "   - Versão Node.js: $(node --version)"
    
    # Procura por arquivos de configuração do gateway
    if [ -f "package.json" ]; then
        echo "   - package.json encontrado"
        echo "     Dependências relacionadas:"
        grep -i "wpp\|whatsapp\|timeout" package.json 2>/dev/null | head -5
    fi
    
    # Procura por arquivos .env ou config
    if [ -f ".env" ]; then
        echo "   - .env encontrado"
        echo "     Variáveis relacionadas a timeout:"
        grep -i "timeout\|TIMEOUT" .env 2>/dev/null | head -5
    fi
    
    # Procura por arquivos de configuração do WPPConnect
    if [ -f "wppconnect.config.js" ] || [ -f "config.js" ]; then
        echo "   - Arquivo de config encontrado"
        grep -i "timeout\|TIMEOUT" wppconnect.config.js config.js 2>/dev/null | head -5
    fi
else
    echo "   ⚠️ Node.js não encontrado"
fi
echo ""

# 7. Verifica processos relacionados ao WhatsApp
echo "7. PROCESSOS RELACIONADOS:"
ps aux | grep -i "whatsapp\|wpp\|node" | grep -v grep | head -10
echo ""

# 8. Verifica se há problemas de rede/conexão
echo "8. DIAGNÓSTICO DE REDE:"
if command -v netstat &> /dev/null; then
    echo "   - Conexões ativas na porta 80/443:"
    netstat -an | grep -E ":(80|443)" | grep ESTABLISHED | wc -l
    echo "   - Conexões em TIME_WAIT:"
    netstat -an | grep -E ":(80|443)" | grep TIME_WAIT | wc -l
fi
echo ""

# 9. Verifica se há fila de requisições pendentes
echo "9. REQUISIÇÕES RECENTES (últimas 24h):"
if [ -f "/var/log/nginx/access.log" ]; then
    echo "   - Requisições de áudio (últimas 10):"
    grep -i "audio\|/api/messages" /var/log/nginx/access.log 2>/dev/null | tail -10
    echo ""
    echo "   - Requisições com timeout (504/500):"
    grep -E " 504 | 500 " /var/log/nginx/access.log 2>/dev/null | tail -10
fi
echo ""

echo "=== FIM DO DIAGNÓSTICO ==="
echo ""
echo "RECOMENDAÇÕES:"
echo "1. Aumentar timeout do Nginx para pelo menos 120s:"
echo "   proxy_read_timeout 120s;"
echo "   proxy_connect_timeout 120s;"
echo "   proxy_send_timeout 120s;"
echo ""
echo "2. Verificar timeout do WPPConnect (deve ser > 30s)"
echo ""
echo "3. Verificar se o gateway aceita WebM/Opus ou apenas OGG/Opus"
echo ""
echo "4. Verificar logs do gateway para erros específicos de processamento de áudio"
