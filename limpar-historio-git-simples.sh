#!/bin/bash
# Script simples para limpar histÃ³rico do Git removendo chave do Asaas
# ATENÃ‡ÃƒO: Este script reescreve o histÃ³rico do Git!

echo "========================================"
echo "LIMPEZA DE HISTÃ“RICO DO GIT"
echo "========================================"
echo ""

# Chave do Asaas que precisa ser removida
CHAVE_ASAAS='[CHAVE_REMOVIDA_POR_SEGURANCA]'
PLACEHOLDER='ASAAS_API_KEY_FROM_ENV'

echo "Este script irÃ¡:"
echo "1. Substituir a chave do Asaas por placeholder em todo o histÃ³rico"
echo "2. Reescrever todos os commits que contÃªm a chave"
echo "3. Exigir force push para atualizar o repositÃ³rio remoto"
echo ""

read -p "Deseja continuar? (digite 'SIM' para confirmar): " confirmacao
if [ "$confirmacao" != "SIM" ]; then
    echo "OperaÃ§Ã£o cancelada."
    exit 1
fi

echo ""
echo "Criando backup do repositÃ³rio..."
BACKUP_DIR="backup-git-$(date +%Y%m%d-%H%M%S)"
git clone . "$BACKUP_DIR" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "âœ“ Backup criado em: $BACKUP_DIR"
else
    echo "âš  Aviso: NÃ£o foi possÃ­vel criar backup completo"
fi

echo ""
echo "Iniciando limpeza do histÃ³rico..."
echo "Isso pode demorar alguns minutos..."
echo ""

# Escapa a chave para uso no sed
CHAVE_ESCAPADA=$(echo "$CHAVE_ASAAS" | sed 's/[[\.*^$()+?{|]/\\&/g')

# Usa git filter-branch para substituir a chave em todo o histÃ³rico
git filter-branch --force --tree-filter "
    find . -type f -name '*.php' | while read file; do
        if [ -f \"\$file\" ]; then
            sed -i \"s|$CHAVE_ESCAPADA|$PLACEHOLDER|g\" \"\$file\" 2>/dev/null || true
        fi
    done
" --prune-empty --tag-name-filter cat -- --all

if [ $? -eq 0 ]; then
    echo ""
    echo "âœ“ HistÃ³rico limpo com sucesso!"
    echo ""
    echo "PrÃ³ximos passos:"
    echo "1. Verifique as alteraÃ§Ãµes: git log --all"
    echo "2. Se estiver satisfeito, force push: git push --force --all"
    echo "3. Force push tags: git push --force --tags"
    echo ""
    echo "âš  ATENÃ‡ÃƒO: Force push reescreve o histÃ³rico remoto!"
    echo "   Certifique-se de que ninguÃ©m mais estÃ¡ trabalhando no repositÃ³rio."
else
    echo ""
    echo "âœ— Erro ao limpar histÃ³rico. Verifique os logs acima."
    echo "   O backup estÃ¡ em: $BACKUP_DIR"
fi

echo ""
echo "Script concluÃ­do."

