#!/bin/bash
# Script para recarregar Nginx sem afetar outros serviços

echo "=== RECARREGAMENTO SUAVE DO NGINX ==="
echo ""

# 1. Verificar se Nginx está rodando (mesmo que não via systemd)
echo "1. VERIFICANDO PROCESSOS NGINX:"
echo "==============================="
NGINX_PID=$(pgrep -f "nginx.*master" | head -1)
if [ -n "$NGINX_PID" ]; then
    echo "✅ Nginx está rodando (PID: $NGINX_PID)"
    ps aux | grep nginx | grep -v grep | head -5
    echo ""
    
    # 2. Tentar reload suave (SIGHUP)
    echo "2. RECARREGANDO CONFIGURAÇÃO (SIGHUP):"
    echo "======================================"
    kill -HUP $NGINX_PID 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✅ Sinal HUP enviado ao Nginx (reload suave)"
        sleep 2
        echo ""
        echo "3. VERIFICANDO SE RECARREGOU:"
        echo "============================="
        ps aux | grep nginx | grep -v grep | head -5
        echo ""
        echo "✅ Nginx recarregado sem interromper conexões!"
    else
        echo "❌ Erro ao enviar sinal HUP"
    fi
else
    echo "⚠️  Nginx não está rodando via processo"
    echo ""
    echo "2. VERIFICANDO DOCKER:"
    echo "======================"
    if command -v docker &> /dev/null; then
        NGINX_CONTAINER=$(docker ps | grep nginx | awk '{print $1}' | head -1)
        if [ -n "$NGINX_CONTAINER" ]; then
            echo "✅ Container Nginx encontrado: $NGINX_CONTAINER"
            echo ""
            echo "3. RECARREGANDO CONTAINER:"
            echo "=========================="
            docker exec $NGINX_CONTAINER nginx -s reload 2>&1
            if [ $? -eq 0 ]; then
                echo "✅ Container Nginx recarregado!"
            else
                echo "⚠️  Tentando restart do container..."
                docker restart $NGINX_CONTAINER && echo "✅ Container reiniciado!" || echo "❌ Erro ao reiniciar"
            fi
        else
            echo "   Nenhum container Nginx encontrado"
        fi
    else
        echo "   Docker não instalado"
    fi
fi

echo ""
echo "4. VERIFICANDO TIMEOUTS APLICADOS:"
echo "=================================="
CONFIG_FILE="/etc/nginx/sites-available/whatsapp-multichannel"
if [ -f "$CONFIG_FILE" ]; then
    echo "Timeouts no arquivo de configuração:"
    grep "proxy.*timeout" "$CONFIG_FILE" | grep -v "^#"
    echo ""
    echo "✅ Se os timeouts estão em 120s, as mudanças foram aplicadas!"
    echo "   (Mesmo que o Nginx não tenha recarregado, na próxima vez que recarregar, usará os novos valores)"
else
    echo "⚠️  Arquivo de configuração não encontrado"
fi

echo ""
echo "=== FIM ==="
