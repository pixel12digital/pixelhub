# Deploy em Produção - Correção do Erro 500 em /hosting/view

**Data:** 25/01/2025  
**Status:** ✅ Código commitado e pronto para deploy

---

## 📋 Resumo

O erro 500 em `/hosting/view` e `/hosting/edit` foi corrigido. O código está commitado no GitHub e precisa ser deployado em produção via cPanel.

---

## 🔧 Correções Aplicadas

1. **Conflito de assinatura de método resolvido:**
   - Método `view()` renomeado para `show()` no `HostingController`
   - Rota atualizada de `HostingController@view` para `HostingController@show`

2. **Tratamento de erros melhorado:**
   - `display_errors` agora usa `Env::isDebug()` (não hardcoded)
   - Erros são logados e exibidos corretamente em modo debug

3. **Script de verificação criado:**
   - `public/check-hosting-endpoint.php` - Verifica se tudo está funcionando

---

## 🚀 Passos para Deploy em Produção

### 1. Acessar cPanel Git Version Control

1. Acesse o cPanel da HostMídia
2. Vá em **Tools** → **Git™ Version Control**
3. Clique em **Manage Repository** para o repositório `hub.pixel12digital.com.br`

### 2. Atualizar do Remote (Pull)

1. Clique no botão **"Update from Remote"** (ícone de nuvem com seta para baixo)
2. Isso vai fazer `git pull` do GitHub
3. Aguarde a confirmação de sucesso

### 3. Verificar Requisitos para Deploy

O cPanel mostra dois requisitos que devem estar OK:

- ✅ **A valid `.cpanel.yml` file exists** - O arquivo existe e está correto
- ✅ **No uncommitted changes exist** - Após o pull, não deve haver mudanças locais

**Se houver mudanças não commitadas no servidor:**
- Opção 1: Descartar as mudanças (se não forem importantes)
- Opção 2: Fazer commit das mudanças (se forem importantes)

### 4. Fazer Deploy

1. Após garantir que os requisitos estão OK, clique em **"Deploy HEAD Commit"**
2. Aguarde a confirmação de sucesso
3. O deploy vai copiar os arquivos para o diretório de produção

### 5. Verificar se Funcionou

#### 5.1. Acessar Script de Verificação

Acesse no navegador:
```
https://hub.pixel12digital.com.br/public/check-hosting-endpoint.php
```

O script vai verificar:
- ✅ Se o método `show()` existe no `HostingController`
- ✅ Se a rota está configurada corretamente
- ✅ Se a conexão com o banco está OK
- ✅ Se há contas de hospedagem no banco

#### 5.2. Testar na Interface

1. Acesse o painel: `https://hub.pixel12digital.com.br`
2. Vá em **Clientes** → Selecione um cliente
3. Clique na aba **"Hospedagem & Sites"**
4. Clique no botão **"Ver"** de uma conta de hospedagem
5. **Verifique se o modal abre com:**
   - ✅ Resumo (Plano, Valor, Provedor, Vencimentos)
   - ✅ Status (Hospedagem e Domínio)
   - ✅ Credenciais de Acesso (Painel de Hospedagem e Admin do Site)
   - ✅ Ações Rápidas (se URLs estiverem configuradas)

#### 5.3. Verificar Console do Navegador

1. Abra o console do navegador (F12)
2. Vá na aba **Network** (Rede)
3. Clique no botão **"Ver"** novamente
4. Verifique a requisição para `/hosting/view?id=X`
5. **Deve retornar:**
   - Status: `200 OK`
   - Content-Type: `application/json`
   - Body: JSON com `success: true` e dados completos

---

## 🔍 Troubleshooting

### Problema: Modal não abre / Erro 500

**Sintomas:**
- Modal aparece "Carregando..." e depois mostra erro
- Console mostra `500 Internal Server Error`

**Soluções:**

1. **Verificar se o código foi atualizado:**
   ```bash
   # No servidor (via SSH ou cPanel Terminal)
   cd /home/pixel12digital/hub.pixel12digital.com.br
   git log --oneline -1
   # Deve mostrar: 373a0ea fix: Ajusta display_errors...
   ```

2. **Verificar se o método show() existe:**
   ```bash
   grep -n "public function show" src/Controllers/HostingController.php
   # Deve retornar a linha do método
   ```

3. **Verificar se a rota está correta:**
   ```bash
   grep -n "HostingController@show" public/index.php
   # Deve retornar a linha da rota
   ```

4. **Verificar logs de erro:**
   - Acesse `logs/pixelhub.log` no servidor
   - Procure por erros relacionados a `HostingController@show`

### Problema: Modal abre mas não mostra credenciais

**Sintomas:**
- Modal abre mas campos de credenciais aparecem como "Não informado"

**Solução:**
- Isso é normal se as credenciais não foram preenchidas no formulário de edição
- Edite a conta de hospedagem e preencha as credenciais

### Problema: Deploy não funciona no cPanel

**Sintomas:**
- Botão "Deploy HEAD Commit" está desabilitado
- Mensagem: "The system cannot deploy"

**Soluções:**

1. **Verificar arquivo .cpanel.yml:**
   - O arquivo deve existir na raiz do repositório
   - Deve ter a estrutura correta (já está commitado)

2. **Verificar mudanças não commitadas:**
   - No cPanel, veja se há mudanças locais no servidor
   - Se houver, faça commit ou descarte

3. **Fazer pull manual:**
   - Use o botão "Update from Remote" primeiro
   - Depois tente o deploy novamente

---

## 📝 Arquivos Modificados

Os seguintes arquivos foram modificados e precisam estar em produção:

1. `public/index.php` - Rota atualizada e tratamento de erros
2. `src/Controllers/HostingController.php` - Método renomeado para `show()`
3. `src/Core/Router.php` - Tratamento de erros melhorado
4. `public/check-hosting-endpoint.php` - Script de verificação (novo)

---

## ✅ Checklist de Deploy

- [ ] Acessar cPanel Git Version Control
- [ ] Clicar em "Update from Remote"
- [ ] Verificar que não há mudanças não commitadas
- [ ] Clicar em "Deploy HEAD Commit"
- [ ] Aguardar confirmação de sucesso
- [ ] Acessar script de verificação: `/public/check-hosting-endpoint.php`
- [ ] Testar botão "Ver" na interface
- [ ] Verificar console do navegador (F12)
- [ ] Confirmar que modal abre com todos os dados

---

## 📞 Suporte

Se após seguir todos os passos o problema persistir:

1. Acesse o script de verificação e copie o resultado
2. Verifique os logs em `logs/pixelhub.log`
3. Verifique o console do navegador (F12) para erros JavaScript
4. Documente o erro encontrado

---

**Última atualização:** 25/01/2025  
**Commit:** `373a0ea` - fix: Ajusta display_errors para usar Env::isDebug() e adiciona script de verificação para produção

