#!/bin/bash

# Script para gerar QR code da sessão imobsites
# Uso: ./gerar_qr_imobsites.sh [SECRET]

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configurações
SESSION="imobsites"
BASE_URL="https://wpp.pixel12digital.com.br:8443"

# Obter secret do parâmetro ou prompt
if [ -z "$1" ]; then
    echo -e "${YELLOW}Gateway Secret não fornecido.${NC}"
    echo "Digite o Gateway Secret (ou pressione Enter para tentar obter do arquivo):"
    read -s SECRET
    if [ -z "$SECRET" ]; then
        # Tentar obter do arquivo .htpasswd ou variável de ambiente
        if [ -f "/etc/nginx/.htpasswd_wpp.pixel12digital.com.br" ]; then
            echo -e "${YELLOW}Tentando obter secret de outras fontes...${NC}"
        fi
        echo -e "${RED}Erro: Gateway Secret é obrigatório${NC}"
        echo "Uso: $0 [SECRET]"
        echo "Ou defina: export GATEWAY_SECRET='seu_secret'"
        exit 1
    fi
else
    SECRET="$1"
fi

echo -e "${GREEN}=== Gerador de QR Code - Sessão: $SESSION ===${NC}\n"

# 1. Verificar status da sessão
echo -e "${YELLOW}[1/3] Verificando status da sessão...${NC}"
STATUS_RESPONSE=$(curl -k -s -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "$BASE_URL/api/channels/$SESSION")

if [ $? -ne 0 ]; then
    echo -e "${RED}Erro ao conectar com o gateway${NC}"
    exit 1
fi

echo "$STATUS_RESPONSE" | jq '.' 2>/dev/null || echo "$STATUS_RESPONSE"
echo ""

# 2. Gerar QR code
echo -e "${YELLOW}[2/3] Gerando QR code...${NC}"
QR_RESPONSE=$(curl -k -s -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "$BASE_URL/api/channels/$SESSION/qr")

if [ $? -ne 0 ]; then
    echo -e "${RED}Erro ao gerar QR code${NC}"
    exit 1
fi

# Verificar se tem QR code na resposta
QR_CODE=$(echo "$QR_RESPONSE" | jq -r '.qr // .qrcode // .data // empty' 2>/dev/null)

if [ -z "$QR_CODE" ] || [ "$QR_CODE" = "null" ]; then
    echo -e "${YELLOW}Resposta da API:${NC}"
    echo "$QR_RESPONSE" | jq '.' 2>/dev/null || echo "$QR_RESPONSE"
    echo -e "\n${YELLOW}Não foi possível extrair QR code da resposta.${NC}"
    echo "Tente:"
    echo "  1. Verificar se a sessão existe"
    echo "  2. Desconectar e reconectar a sessão"
    echo "  3. Verificar logs do gateway"
else
    # Tentar salvar QR code como imagem se for base64
    if [[ "$QR_CODE" =~ ^data:image ]]; then
        # Remove prefixo data:image/...;base64,
        BASE64_DATA=$(echo "$QR_CODE" | sed 's/^data:image\/[^;]*;base64,//')
        echo "$BASE64_DATA" | base64 -d > "/tmp/qrcode_${SESSION}.png" 2>/dev/null
        if [ -f "/tmp/qrcode_${SESSION}.png" ]; then
            echo -e "${GREEN}QR code salvo em: /tmp/qrcode_${SESSION}.png${NC}"
            echo "Para visualizar no servidor, você pode:"
            echo "  - Usar um cliente SCP/SFTP para baixar o arquivo"
            echo "  - Ou converter para ASCII art (se disponível)"
        fi
    elif [[ "$QR_CODE" =~ ^[A-Za-z0-9+/=]+$ ]]; then
        # Parece ser base64 puro
        echo "$QR_CODE" | base64 -d > "/tmp/qrcode_${SESSION}.png" 2>/dev/null
        if [ -f "/tmp/qrcode_${SESSION}.png" ]; then
            echo -e "${GREEN}QR code salvo em: /tmp/qrcode_${SESSION}.png${NC}"
        else
            echo -e "${YELLOW}QR code (base64):${NC}"
            echo "$QR_CODE" | head -c 100
            echo "..."
        fi
    else
        echo -e "${YELLOW}QR code (texto):${NC}"
        echo "$QR_CODE"
    fi
fi

echo ""

# 3. Mostrar instruções
echo -e "${GREEN}[3/3] Próximos passos:${NC}"
echo "1. Acesse a UI: https://wpp.pixel12digital.com.br:8443/ui/sessoes/$SESSION"
echo "2. Ou escaneie o QR code gerado acima"
echo "3. No WhatsApp: Menu (3 pontos) → Aparelhos conectados → Conectar um aparelho"
echo ""

# 4. Verificar se conectou (aguardar alguns segundos)
echo -e "${YELLOW}Verificando status novamente em 5 segundos...${NC}"
sleep 5

FINAL_STATUS=$(curl -k -s -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "$BASE_URL/api/channels/$SESSION")

CONNECTION=$(echo "$FINAL_STATUS" | jq -r '.connection // .status // "unknown"' 2>/dev/null)

if [ "$CONNECTION" = "connected" ] || [ "$CONNECTION" = "open" ]; then
    echo -e "${GREEN}✅ Sessão conectada!${NC}"
elif [ "$CONNECTION" = "disconnected" ] || [ "$CONNECTION" = "waiting_qr" ]; then
    echo -e "${YELLOW}⏳ Aguardando escaneamento do QR code...${NC}"
else
    echo -e "${YELLOW}Status: $CONNECTION${NC}"
fi

echo ""
echo -e "${GREEN}Script concluído!${NC}"

