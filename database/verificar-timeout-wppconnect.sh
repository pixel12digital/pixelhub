#!/bin/bash
# Script para verificar timeout do WPPConnect na VPS

echo "=== VERIFICANDO TIMEOUT DO WPPCONNECT ==="
echo ""

# Procura arquivos de configuração do WPPConnect
echo "1. Procurando arquivos de configuração:"
find /usr/src -name "*.js" -o -name "*.json" -o -name ".env" 2>/dev/null | grep -E "(wpp|server|config)" | head -10

echo ""
echo "2. Verificando variáveis de ambiente relacionadas a timeout:"
if [ -f /usr/src/wpp-server/.env ]; then
    echo "   .env encontrado:"
    grep -i "timeout\|TIMEOUT" /usr/src/wpp-server/.env 2>/dev/null || echo "   Nenhuma variável de timeout encontrada"
fi

if [ -f /usr/src/wpp-ui/.env ]; then
    echo "   .env do wpp-ui encontrado:"
    grep -i "timeout\|TIMEOUT" /usr/src/wpp-ui/.env 2>/dev/null || echo "   Nenhuma variável de timeout encontrada"
fi

echo ""
echo "3. Verificando código JavaScript por timeouts:"
if [ -d /usr/src/wpp-server ]; then
    echo "   Buscando em /usr/src/wpp-server:"
    grep -r "timeout.*30\|30000\|setTimeout.*30" /usr/src/wpp-server --include="*.js" 2>/dev/null | head -5 || echo "   Nenhum timeout de 30s encontrado"
fi

if [ -d /usr/src/wpp-ui ]; then
    echo "   Buscando em /usr/src/wpp-ui:"
    grep -r "timeout.*30\|30000\|setTimeout.*30" /usr/src/wpp-ui --include="*.js" 2>/dev/null | head -5 || echo "   Nenhum timeout de 30s encontrado"
fi

echo ""
echo "=== FIM ==="
