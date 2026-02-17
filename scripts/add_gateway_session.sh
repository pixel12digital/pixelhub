#!/bin/bash
# Script para adicionar sessão ao gateway-wrapper sessions.json
# Uso: ./add_gateway_session.sh NOME_DA_SESSAO

set -e

SESSION_ID="$1"
GATEWAY_SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
GATEWAY_URL="https://wpp.pixel12digital.com.br:8443"

if [ -z "$SESSION_ID" ]; then
    echo "❌ Erro: Nome da sessão não fornecido"
    echo "Uso: $0 NOME_DA_SESSAO"
    echo "Exemplo: $0 ImobSites"
    exit 1
fi

echo "📋 Adicionando sessão: $SESSION_ID"
echo ""

# 1. Verificar se sessão existe no WPPConnect
echo "1️⃣ Verificando se sessão existe no WPPConnect..."
if docker exec wppconnect-server ls /usr/src/wpp-server/userDataDir/ | grep -q "^${SESSION_ID}$"; then
    echo "   ✅ Sessão encontrada no WPPConnect"
else
    echo "   ⚠️  Sessão não encontrada no WPPConnect (será criada no gateway-wrapper mesmo assim)"
fi
echo ""

# 2. Backup do sessions.json
echo "2️⃣ Criando backup de sessions.json..."
docker exec gateway-wrapper cp /app/src/data/sessions.json /app/src/data/sessions.json.bak
echo "   ✅ Backup criado: sessions.json.bak"
echo ""

# 3. Adicionar sessão ao sessions.json
echo "3️⃣ Adicionando sessão ao sessions.json..."
docker exec gateway-wrapper sh -c "cat /app/src/data/sessions.json | python3 -c \"
import sys, json
from datetime import datetime

sessions = json.load(sys.stdin)

# Verifica se sessão já existe
if any(s['session_id'] == '$SESSION_ID' for s in sessions):
    print('⚠️  Sessão já existe no sessions.json')
    sys.exit(1)

# Adiciona nova sessão
new_session = {
    'session_id': '$SESSION_ID',
    'created_at': datetime.utcnow().isoformat() + 'Z',
    'updated_at': datetime.utcnow().isoformat() + 'Z',
    'status': 'disconnected'
}
sessions.append(new_session)

print(json.dumps(sessions, indent=2))
\" > /tmp/sessions_new.json && mv /tmp/sessions_new.json /app/src/data/sessions.json"

if [ $? -eq 0 ]; then
    echo "   ✅ Sessão adicionada ao sessions.json"
else
    echo "   ⚠️  Sessão já existia no sessions.json"
fi
echo ""

# 4. Reiniciar gateway-wrapper
echo "4️⃣ Reiniciando gateway-wrapper..."
docker restart gateway-wrapper > /dev/null 2>&1
echo "   ✅ Gateway-wrapper reiniciado"
echo ""

# 5. Aguardar inicialização
echo "5️⃣ Aguardando inicialização (5 segundos)..."
sleep 5
echo "   ✅ Pronto"
echo ""

# 6. Verificar se sessão aparece na API
echo "6️⃣ Verificando API do gateway..."
RESPONSE=$(curl -s -H "X-Gateway-Secret: $GATEWAY_SECRET" "$GATEWAY_URL/api/channels")
TOTAL=$(echo "$RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('total', 0))" 2>/dev/null || echo "0")

if echo "$RESPONSE" | grep -q "\"id\": \"$SESSION_ID\""; then
    echo "   ✅ Sessão $SESSION_ID aparece na API"
    echo "   📊 Total de sessões: $TOTAL"
else
    echo "   ❌ Sessão $SESSION_ID NÃO aparece na API"
    echo "   📊 Total de sessões: $TOTAL"
    echo ""
    echo "Resposta da API:"
    echo "$RESPONSE" | python3 -m json.tool
    exit 1
fi
echo ""

# 7. Mostrar sessões registradas
echo "7️⃣ Sessões registradas no gateway-wrapper:"
docker exec gateway-wrapper cat /app/src/data/sessions.json | python3 -c "
import sys, json
sessions = json.load(sys.stdin)
for s in sessions:
    print(f\"   - {s['session_id']} ({s['status']})\")
"
echo ""

echo "✅ Sessão $SESSION_ID adicionada com sucesso!"
echo ""
echo "📝 Próximos passos:"
echo "   1. Acesse o Pixel Hub: https://hub.pixel12digital.com.br/settings/whatsapp-gateway"
echo "   2. Clique em 'Atualizar' para ver a sessão"
echo "   3. Clique em 'Reconectar' para gerar QR code"
echo "   4. Escaneie o QR com WhatsApp"
