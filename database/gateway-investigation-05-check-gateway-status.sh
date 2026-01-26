#!/bin/bash

# ============================================
# Script 05: Verificar Status do Gateway
# ============================================
# Execute este script no VPS do gateway
# Objetivo: Verificar status geral do gateway

echo "=== Script 05: Verificar Status do Gateway ==="
echo ""
echo "Este script verifica:"
echo "1. Status do serviço do gateway"
echo "2. Processos em execução"
echo "3. Uso de recursos (CPU, memória)"
echo "4. Portas em uso"
echo ""
echo "INSTRUÇÕES:"
echo "1. Execute este script no VPS do gateway"
echo "2. Copie TODA a saída e me envie"
echo ""
echo "============================================"
echo ""

echo "1. Verificando processos do gateway..."
echo "----------------------------------------"
ps aux | grep -E "wpp|whatsapp|gateway|node|npm" | grep -v grep || echo "   Nenhum processo encontrado"

echo ""
echo "2. Verificando status do serviço (systemd)..."
echo "----------------------------------------"
if command -v systemctl &> /dev/null; then
    systemctl list-units --type=service | grep -i "wpp\|whatsapp\|gateway" || echo "   Nenhum serviço encontrado"
    
    # Tenta status de serviços comuns
    for service in wpp-gateway whatsapp-gateway gateway; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            echo ""
            echo "   ✅ Serviço '$service' está ativo"
            systemctl status "$service" --no-pager -l | head -15
        fi
    done
else
    echo "   systemctl não disponível (não é systemd)"
fi

echo ""
echo "3. Verificando portas em uso..."
echo "----------------------------------------"
if command -v netstat &> /dev/null; then
    netstat -tulpn | grep -E "LISTEN|wpp|whatsapp|gateway" | head -10
elif command -v ss &> /dev/null; then
    ss -tulpn | grep -E "LISTEN|wpp|whatsapp|gateway" | head -10
else
    echo "   netstat/ss não disponível"
fi

echo ""
echo "4. Verificando uso de recursos..."
echo "----------------------------------------"
echo "   CPU e Memória:"
ps aux | grep -E "wpp|whatsapp|gateway" | grep -v grep | awk '{print "   PID: "$2" | CPU: "$3"% | MEM: "$4"% | CMD: "$11" "$12" "$13" "$14" "$15}'

echo ""
echo "5. Verificando espaço em disco..."
echo "----------------------------------------"
df -h | grep -E "Filesystem|/$|/opt|/var" | head -5

echo ""
echo "6. Verificando últimas linhas de log do sistema..."
echo "----------------------------------------"
if [ -f /var/log/syslog ]; then
    echo "   /var/log/syslog (últimas 5 linhas relacionadas a gateway):"
    grep -i "wpp\|whatsapp\|gateway" /var/log/syslog 2>/dev/null | tail -5 || echo "   (nenhuma linha encontrada)"
fi

if [ -f /var/log/messages ]; then
    echo ""
    echo "   /var/log/messages (últimas 5 linhas relacionadas a gateway):"
    grep -i "wpp\|whatsapp\|gateway" /var/log/messages 2>/dev/null | tail -5 || echo "   (nenhuma linha encontrada)"
fi

echo ""
echo "=== Fim do Script 05 ==="
echo ""
echo "ANÁLISE COMPLETA!"
echo "Envie todas as saídas dos 5 scripts para análise"

