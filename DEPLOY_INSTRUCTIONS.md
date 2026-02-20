# Deploy em Produção - Correção Erro 500 /opportunities

## Commit realizado
- **Hash**: `be54cab`
- **Mensagem**: Fix: Corrige erro 500 em /opportunities no servidor
- **Arquivo modificado**: `src/Core/Auth.php`

## O que foi corrigido
Adicionada verificação `!headers_sent()` no método de redirecionamento do `Auth::requireInternal()` para prevenir erro de "Cannot modify header information" quando os headers já foram enviados.

## Instruções de Deploy

### 1. Acessar o servidor de produção
```bash
ssh usuario@hub.pixel12digital.com.br
```

### 2. Navegar para o diretório do projeto
```bash
cd ~/hub.pixel12digital.com.br
```

### 3. Fazer pull das alterações
```bash
git pull origin main
```

### 4. Verificar se o arquivo foi atualizado
```bash
# Verificar se a correção está presente
grep -A 3 "!headers_sent()" src/Core/Auth.php
```

### 5. Limpar cache (se necessário)
```bash
# Limpar cache do PHP/OPcache se estiver ativo
sudo service php-fpm reload
# ou
sudo service apache2 reload
```

### 6. Testar o acesso
Acessar no navegador:
```
https://hub.pixel12digital.com.br/opportunities
```

## Verificação pós-deploy
- [ ] A página `/opportunities` carrega sem erro 500
- [ ] O login/redirecionamento funciona corretamente
- [ ] Demais funcionalidades do CRM operacionais

## Rollback (se necessário)
Caso algo dê errado:
```bash
git checkout 4eef5d9~1  # Volta para o commit anterior
```

## Arquivos de debug (podem ser removidos)
Os seguintes arquivos de debug foram criados localmente e NÃO devem ser enviados para produção:
- `debug_opportunities.php`
- `check_500_error.php`
- `fix_server_auth.php`
- `final_test_opportunities.php`
- `diagnose_server_error.php`

## Resumo técnico
- **Problema**: Erro 500 causado por tentativa de redirecionamento com `header()` após envio de headers
- **Solução**: Verificação `!headers_sent()` com fallback para JavaScript redirect
- **Impacto**: Mínimo - apenas melhora o tratamento de redirecionamento
