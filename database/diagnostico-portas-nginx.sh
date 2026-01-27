#!/bin/bash
# Script para diagnosticar conflito de portas do Nginx

echo "=== DIAGNÓSTICO DE PORTAS ==="
echo ""

# 1. Verificar processos usando portas 80, 443, 8443
echo "1. PROCESSOS USANDO PORTAS 80, 443, 8443:"
echo "==========================================="
for port in 80 443 8443; do
    echo "Porta $port:"
    lsof -i :$port 2>/dev/null || netstat -tulpn | grep ":$port " || ss -tulpn | grep ":$port " || echo "   Nenhum processo encontrado (pode precisar de sudo)"
    echo ""
done

# 2. Verificar se há Nginx rodando
echo "2. PROCESSOS NGINX:"
echo "==================="
ps aux | grep nginx | grep -v grep || echo "   Nenhum processo Nginx encontrado"
echo ""

# 3. Verificar se há Docker rodando
echo "3. CONTAINERS DOCKER:"
echo "===================="
if command -v docker &> /dev/null; then
    docker ps 2>/dev/null | head -10 || echo "   Docker não está rodando ou não há containers"
else
    echo "   Docker não instalado"
fi
echo ""

# 4. Verificar se há outro servidor web
echo "4. PROCESSOS DE SERVIDOR WEB:"
echo "============================="
ps aux | grep -E "apache|httpd|nginx|caddy|traefik" | grep -v grep || echo "   Nenhum servidor web encontrado"
echo ""

# 5. Verificar qual porta o gateway está realmente usando
echo "5. VERIFICANDO CONECTIVIDADE DO GATEWAY:"
echo "========================================"
GATEWAY_URL="https://wpp.pixel12digital.com.br"
echo "Testando: $GATEWAY_URL"
curl -s -o /dev/null -w "HTTP Code: %{http_code}\n" --max-time 5 "$GATEWAY_URL/api/health" 2>/dev/null || echo "   Não conseguiu conectar"
echo ""

echo "=== FIM ==="
