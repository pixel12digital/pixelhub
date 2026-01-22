#!/bin/bash

###############################################################################
# Script: Testar Gateway WhatsApp Completo
# Execute: ssh root@212.85.11.238
# Depois: chmod +x testar_gateway_completo.sh && ./testar_gateway_completo.sh
###############################################################################

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

DOMAIN="wpp.pixel12digital.com.br"
PORT="8443"
# IMPORTANTE: Configure o usuário no arquivo .env ou substitua abaixo
USER="${WPP_GATEWAY_USER:-[CONFIGURE_USUARIO_AQUI]}"

echo -e "${GREEN}=========================================="
echo "Teste Completo do Gateway WhatsApp"
echo "==========================================${NC}"
echo ""

# 1. Verificar se está escutando na porta 8443
echo -e "${BLUE}[1/7] Verificando porta 8443...${NC}"
if ss -tlnp | grep -q ":8443"; then
    echo -e "${GREEN}✓ Porta 8443 está sendo escutada${NC}"
    ss -tlnp | grep :8443
else
    echo -e "${RED}✗ Porta 8443 NÃO está sendo escutada${NC}"
fi
echo ""

# 2. Verificar certificado SSL
echo -e "${BLUE}[2/7] Verificando certificado SSL...${NC}"
CERT_DATES=$(openssl s_client -connect ${DOMAIN}:${PORT} -servername ${DOMAIN} < /dev/null 2>/dev/null | openssl x509 -noout -dates 2>/dev/null)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Certificado válido${NC}"
    echo "$CERT_DATES"
else
    echo -e "${YELLOW}⚠ Não foi possível verificar certificado (pode ser normal)${NC}"
fi
echo ""

# 3. Testar acesso sem autenticação (deve dar 401)
echo -e "${BLUE}[3/7] Testando acesso SEM autenticação (deve retornar 401)...${NC}"
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://${DOMAIN}:${PORT})
if [ "$HTTP_CODE" = "401" ]; then
    echo -e "${GREEN}✓ Retornou 401 Unauthorized (correto - autenticação funcionando)${NC}"
elif [ "$HTTP_CODE" = "200" ]; then
    echo -e "${YELLOW}⚠ Retornou 200 (autenticação pode não estar funcionando)${NC}"
else
    echo -e "${YELLOW}⚠ Retornou código: $HTTP_CODE${NC}"
fi
echo ""

# 4. Testar acesso com autenticação (deve dar 200)
echo -e "${BLUE}[4/7] Testando acesso COM autenticação...${NC}"
echo -e "${YELLOW}Digite a senha quando solicitado:${NC}"
read -sp "Senha: " PASSWORD
echo ""
HTTP_CODE_AUTH=$(curl -k -s -o /dev/null -w "%{http_code}" -u "${USER}:${PASSWORD}" https://${DOMAIN}:${PORT})
if [ "$HTTP_CODE_AUTH" = "200" ]; then
    echo -e "${GREEN}✓ Retornou 200 OK (autenticação funcionando!)${NC}"
elif [ "$HTTP_CODE_AUTH" = "401" ]; then
    echo -e "${RED}✗ Retornou 401 (senha incorreta ou autenticação não configurada)${NC}"
else
    echo -e "${YELLOW}⚠ Retornou código: $HTTP_CODE_AUTH${NC}"
fi
echo ""

# 5. Verificar logs de acesso (últimas 10 linhas)
echo -e "${BLUE}[5/7] Últimas 10 linhas do access.log...${NC}"
if [ -f "/var/log/nginx/${DOMAIN}_access.log" ]; then
    tail -10 /var/log/nginx/${DOMAIN}_access.log
else
    echo -e "${YELLOW}⚠ Arquivo de log não encontrado${NC}"
fi
echo ""

# 6. Verificar logs de erro (últimas 10 linhas)
echo -e "${BLUE}[6/7] Últimas 10 linhas do error.log...${NC}"
if [ -f "/var/log/nginx/${DOMAIN}_error.log" ]; then
    tail -10 /var/log/nginx/${DOMAIN}_error.log
else
    echo -e "${YELLOW}⚠ Arquivo de log não encontrado${NC}"
fi
echo ""

# 7. Verificar status do Nginx
echo -e "${BLUE}[7/7] Status do Nginx...${NC}"
if systemctl is-active --quiet nginx; then
    echo -e "${GREEN}✓ Nginx está rodando${NC}"
else
    echo -e "${RED}✗ Nginx NÃO está rodando${NC}"
fi
echo ""

# Resumo
echo -e "${GREEN}=========================================="
echo "Resumo do Teste"
echo "==========================================${NC}"
echo ""
echo "Domínio: ${DOMAIN}"
echo "Porta: ${PORT}"
echo "Usuário: ${USER}"
echo ""
echo "Status:"
echo "  - Porta 8443: $(ss -tlnp | grep -q ":8443" && echo '✓ Escutando' || echo '✗ Não escutando')"
echo "  - Sem auth: $(curl -k -s -o /dev/null -w "%{http_code}" https://${DOMAIN}:${PORT})"
echo "  - Com auth: $(curl -k -s -o /dev/null -w "%{http_code}" -u "${USER}:${PASSWORD}" https://${DOMAIN}:${PORT})"
echo ""
echo -e "${BLUE}Para testar no navegador:${NC}"
echo "https://${DOMAIN}:${PORT}"
echo ""
echo -e "${BLUE}Para monitorar logs em tempo real:${NC}"
echo "tail -f /var/log/nginx/${DOMAIN}_access.log"
echo "tail -f /var/log/nginx/${DOMAIN}_error.log"
echo ""

