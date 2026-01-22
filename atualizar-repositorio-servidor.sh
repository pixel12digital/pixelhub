#!/bin/bash

###############################################################################
# Script: Atualizar Repositório Git no Servidor
# 
# Este script resolve o erro de deploy no cPanel após force push
# 
# Como usar:
# 1. Faça upload deste arquivo para: /home/pixel12digital/hub.pixel12digital.com.br/
# 2. No File Manager do cPanel, clique com botão direito no arquivo
# 3. Selecione "Change Permissions" e marque "Execute" (755)
# 4. Clique com botão direito novamente e selecione "Execute"
# 
# OU via terminal do File Manager:
# chmod +x atualizar-repositorio-servidor.sh
# ./atualizar-repositorio-servidor.sh
###############################################################################

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Atualizar Repositório Git no Servidor${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Verificar se estamos no diretório correto
REPO_DIR="/home/pixel12digital/hub.pixel12digital.com.br"
CURRENT_DIR=$(pwd)

if [ "$CURRENT_DIR" != "$REPO_DIR" ]; then
    echo -e "${YELLOW}⚠️  Diretório atual: $CURRENT_DIR${NC}"
    echo -e "${YELLOW}⚠️  Mudando para: $REPO_DIR${NC}"
    cd "$REPO_DIR" || {
        echo -e "${RED}❌ ERRO: Não foi possível acessar $REPO_DIR${NC}"
        exit 1
    }
fi

echo -e "${BLUE}[1/5] Verificando se é um repositório Git...${NC}"
if [ ! -d ".git" ]; then
    echo -e "${RED}❌ ERRO: Não é um repositório Git!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Repositório Git encontrado${NC}"

echo ""
echo -e "${BLUE}[2/5] Verificando estado atual...${NC}"
git status --short
echo ""

echo -e "${BLUE}[3/5] Atualizando referências remotas...${NC}"
git fetch origin
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERRO ao fazer fetch${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Fetch concluído${NC}"

echo ""
echo -e "${BLUE}[4/5] Resetando para origin/main...${NC}"
echo -e "${YELLOW}⚠️  Isso irá sobrescrever mudanças locais (se houver)${NC}"
git reset --hard origin/main
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERRO ao fazer reset${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Reset concluído${NC}"

echo ""
echo -e "${BLUE}[5/5] Verificando resultado...${NC}"
echo ""
echo -e "${GREEN}Status do repositório:${NC}"
git status

echo ""
echo -e "${GREEN}Últimos commits:${NC}"
git log --oneline -5

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✅ Atualização concluída com sucesso!${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "${YELLOW}Próximos passos:${NC}"
echo "  1. Volte ao cPanel Git Version Control"
echo "  2. Tente fazer deploy novamente"
echo "  3. O erro de 'diverging branches' deve estar resolvido"
echo ""

