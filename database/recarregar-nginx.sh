#!/bin/bash
# Script para recarregar/reiniciar Nginx após atualização de timeouts

echo "=== RECARREGANDO NGINX ==="
echo ""

# Verificar status do Nginx
echo "1. STATUS DO NGINX:"
echo "==================="
systemctl status nginx --no-pager -l | head -10 || echo "Nginx não está rodando"
echo ""

# Verificar se está ativo
if systemctl is-active --quiet nginx; then
    echo "✅ Nginx está ativo"
    echo ""
    echo "2. RECARREGANDO NGINX:"
    echo "======================"
    systemctl reload nginx && echo "✅ Nginx recarregado com sucesso!" || systemctl restart nginx && echo "✅ Nginx reiniciado com sucesso!"
else
    echo "⚠️  Nginx não está ativo, iniciando..."
    echo ""
    echo "2. INICIANDO NGINX:"
    echo "==================="
    systemctl start nginx && echo "✅ Nginx iniciado com sucesso!" || echo "❌ Erro ao iniciar Nginx"
fi

echo ""
echo "3. VERIFICANDO STATUS FINAL:"
echo "============================"
systemctl status nginx --no-pager -l | head -5

echo ""
echo "=== FIM ==="
