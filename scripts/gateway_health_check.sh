#!/bin/bash
# Gateway Health Check - Relatório completo de estabilidade das sessões
# Uso: ./gateway_health_check.sh

echo "🔍 GATEWAY HEALTH CHECK - RELATÓRIO COMPLETO"
echo "=========================================="
echo "Data: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# 1. API Gateway Status
echo "📡 1) API GATEWAY STATUS"
echo "------------------------"
API_RESPONSE=$(curl -s -H "X-Gateway-Secret: d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54" "https://wpp.pixel12digital.com.br:8443/api/channels")
TOTAL_SESSIONS=$(echo "$API_RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('total', 0))" 2>/dev/null || echo "0")
echo "Total de sessões na API: $TOTAL_SESSIONS"
echo "$API_RESPONSE" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    for ch in data.get('channels', []):
        print(f"  - {ch['id']}: {ch['status']}")
except:
    print('  Erro ao parsear resposta')
"
echo ""

# 2. Sessions.json Status
echo "📋 2) SESSIONS.JSON STATUS"
echo "-------------------------"
if docker exec gateway-wrapper cat /app/src/data/sessions.json > /dev/null 2>&1; then
    echo "sessions.json encontrado:"
    docker exec gateway-wrapper cat /app/src/data/sessions.json | python3 -c "
import sys, json
try:
    sessions = json.load(sys.stdin)
    print(f'Total: {len(sessions)}')
    for s in sessions:
        print(f'  - {s[\"session_id\"]}: {s[\"status\"]} (criado: {s[\"created_at\"][:10]})')
except Exception as e:
    print(f'Erro: {e}')
"
else
    echo "❌ sessions.json não encontrado"
fi
echo ""

# 3. User Data Directories
echo "📁 3) USER DATA DIRECTORIES"
echo "--------------------------"
echo "Diretórios em userDataDir:"
docker exec wppconnect-server ls -la /usr/src/wpp-server/userDataDir/ 2>/dev/null | grep "^d" | awk '{print "  " $9 " (" $5 " bytes, " $6 " " $7 " " $8 ")"}' || echo "  Erro ao listar diretórios"
echo ""

# 4. Tokens Files
echo "🔑 4) TOKENS FILES"
echo "------------------"
echo "Arquivos de tokens:"
docker exec wppconnect-server ls -la /usr/src/wpp-server/tokens/ 2>/dev/null | grep -E "\.data\.json$" | awk '{print "  " $9 " (" $5 " bytes, " $6 " " $7 " " $8 ")"}' || echo "  Nenhum token encontrado"
echo ""

# 5. Processos Chromium
echo "🔄 5) PROCESSOS CHROMIUM"
echo "-----------------------"
echo "Processos Chromium ativos:"
docker exec wppconnect-server ps aux | grep -E "(chromium|chrome)" | grep -v grep | wc -l | xargs echo "  Total:"
docker exec wppconnect-server ps aux | grep -E "(chromium|chrome)" | grep -v grep | awk '{print "  PID " $2 ": " $11 " (CPU: " $3 "%, MEM: " $4 "%)"}' | head -10
echo ""

# 6. Consumo de Memória
echo "💾 6) CONSUMO DE MEMÓRIA"
echo "-----------------------"
echo "Memória utilizada pelos containers:"
docker stats --no-stream --format "table {{.Container}}\t{{.MemUsage}}\t{{.CPUPerc}}" | grep -E "(wppconnect|gateway-wrapper)" || echo "  Erro ao obter stats"
echo ""

# 7. Logs Recentes (últimas 10 linhas relevantes)
echo "📝 7) LOGS RECENTES"
echo "-------------------"
echo "WPPConnect - últimas 10 linhas:"
docker logs wppconnect-server --tail 10 2>/dev/null | grep -E "(ERROR|WARN|Session|State|QR)" | sed 's/^/  /' || echo "  Sem logs relevantes"
echo ""
echo "Gateway-wrapper - últimas 10 linhas:"
docker logs gateway-wrapper --tail 10 2>/dev/null | grep -E "(ERROR|WARN|Session|Channels)" | sed 's/^/  /' || echo "  Sem logs relevantes"
echo ""

# 8. Verificação de Anomalias
echo "⚠️  8) VERIFICAÇÃO DE ANOMALIAS"
echo "-----------------------------"

# Sessões fantasma
echo "Verificando sessões fantasma..."
API_SESSIONS=$(echo "$API_RESPONSE" | python3 -c "import sys, json; data=json.load(sys.stdin); print(' '.join([ch['id'] for ch in data.get('channels', [])]))" 2>/dev/null || echo "")
USER_DIRS=$(docker exec wppconnect-server ls /usr/src/wpp-server/userDataDir/ 2>/dev/null | tr '\n' ' ' || echo "")

if [ -n "$USER_DIRS" ]; then
    for dir in $USER_DIRS; do
        if ! echo "$API_SESSIONS" | grep -q "$dir"; then
            echo "  ⚠️  Sessão fantasma encontrada: $dir (userDataDir existe mas não está na API)"
        fi
    done
fi

# Tokens sem userDataDir
echo "Verificando tokens sem userDataDir..."
TOKEN_FILES=$(docker exec wppconnect-server ls /usr/src/wpp-server/tokens/*.data.json 2>/dev/null 2>/dev/null | xargs -n1 basename 2>/dev/null | sed 's/.data.json$//' | tr '\n' ' ' || echo "")
if [ -n "$TOKEN_FILES" ]; then
    for token in $TOKEN_FILES; do
        if ! docker exec wppconnect-server test -d "/usr/src/wpp-server/userDataDir/$token" 2>/dev/null; then
            echo "  ⚠️  Token sem userDataDir: $token"
        fi
    done
fi

# Loops de auto-close
echo "Verificando loops de auto-close..."
AUTO_CLOSE_COUNT=$(docker logs wppconnect-server --since=1h 2>/dev/null | grep -c "auto.*close\|browser.*already.*running\|qrReadError" || echo "0")
if [ "$AUTO_CLOSE_COUNT" -gt 5 ]; then
    echo "  ⚠️  Possível loop detectado: $AUTO_CLOSE_COUNT ocorrências de auto-close/browser errors"
else
    echo "  ✅ Sem loops de auto-close detectados ($AUTO_CLOSE_COUNT ocorrências)"
fi

# Crescimento de processos
echo "Verificando crescimento de processos Chromium..."
CHROMIUM_COUNT=$(docker exec wppconnect-server ps aux | grep -E "(chromium|chrome)" | grep -v grep | wc -l)
if [ "$CHROMIUM_COUNT" -gt 10 ]; then
    echo "  ⚠️  Muitos processos Chromium: $CHROMIUM_COUNT (possível crescimento anormal)"
else
    echo "  ✅ Processos Chromium estáveis: $CHROMIUM_COUNT"
fi

echo ""

# 9. Resumo da Saúde
echo "🏥 9) RESUMO DA SAÚDE"
echo "--------------------"
HEALTH_SCORE=100

# Verificações críticas
if [ "$TOTAL_SESSIONS" -ne 2 ]; then
    echo "  ❌ API não retorna 2 sessões (esperado: 2, encontrado: $TOTAL_SESSIONS)"
    HEALTH_SCORE=$((HEALTH_SCORE - 30))
else
    echo "  ✅ API retorna 2 sessões"
fi

if [ "$CHROMIUM_COUNT" -gt 10 ]; then
    echo "  ❌ Muitos processos Chromium ($CHROMIUM_COUNT)"
    HEALTH_SCORE=$((HEALTH_SCORE - 20))
else
    echo "  ✅ Processos Chromium estáveis"
fi

if [ "$AUTO_CLOSE_COUNT" -gt 5 ]; then
    echo "  ❌ Loops de auto-close detectados"
    HEALTH_SCORE=$((HEALTH_SCORE - 25))
else
    echo "  ✅ Sem loops detectados"
fi

# Status final
echo ""
echo "🎯 SAÚDE GERAL DO SISTEMA: $HEALTH_SCORE/100"
if [ $HEALTH_SCORE -ge 80 ]; then
    echo "🟢 SISTEMA SAUDÁVEL"
elif [ $HEALTH_SCORE -ge 60 ]; then
    echo "🟡 SISTEMA COM ALGUMAS ANOMALIAS"
else
    echo "🔴 SISTEMA COM PROBLEMAS CRÍTICOS"
fi

echo ""
echo "=========================================="
echo "Relatório concluído em $(date '+%Y-%m-%d %H:%M:%S')"
