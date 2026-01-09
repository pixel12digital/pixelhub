# 📋 Passo a Passo: Verificar WhatsApp Gateway em Produção

**Status:** ✅ Código commitado e enviado para o GitHub  
**Commit:** `a8d12af` - feat: Adiciona verificação de WhatsApp Gateway em produção

---

## 🎯 Objetivo

Verificar se todos os arquivos do WhatsApp Gateway estão presentes em produção e se a funcionalidade está acessível no menu.

---

## 📝 Passo a Passo Completo

### **PASSO 1: Atualizar Código em Produção via cPanel**

1. **Acesse o cPanel da HostMídia**
   - URL: (acesse com suas credenciais)
   - Faça login no cPanel

2. **Vá em Git Version Control**
   - No menu superior, procure por **"Tools"** ou **"Ferramentas"**
   - Clique em **"Git™ Version Control"**
   - Você verá uma lista de repositórios

3. **Selecione o Repositório**
   - Procure pelo repositório: **`hub.pixel12digital.com.br`**
   - Clique em **"Manage Repository"** ou **"Gerenciar Repositório"**

4. **Atualizar do GitHub (Pull)**
   - Clique no botão **"Update from Remote"** 
   - (Ícone de nuvem com seta para baixo ☁️⬇️)
   - Isso vai fazer `git pull` do GitHub e baixar as últimas mudanças
   - **Aguarde a confirmação de sucesso**

5. **Verificar Requisitos para Deploy**
   
   O cPanel mostra dois requisitos que devem estar OK:
   - ✅ **A valid `.cpanel.yml` file exists** - Deve estar OK
   - ✅ **No uncommitted changes exist** - Deve estar OK após o pull
   
   ⚠️ **Se houver mudanças não commitadas no servidor:**
   - Opção 1: Descartar as mudanças (se não forem importantes)
   - Opção 2: Fazer commit das mudanças (se forem importantes)

6. **Fazer Deploy**
   - Clique no botão **"Deploy HEAD Commit"** 
   - (Botão verde com ícone de foguete 🚀)
   - **Aguarde a confirmação de sucesso**
   - Isso vai copiar os arquivos atualizados para o diretório de produção

---

### **PASSO 2: Verificar se a Rota de Verificação Funciona**

1. **Acesse a Rota de Verificação no Navegador**
   
   Abra seu navegador e acesse:
   ```
   https://hub.pixel12digital.com.br/settings/whatsapp-gateway/check
   ```

2. **O que você deve ver:**
   
   ✅ **Se tudo estiver OK:**
   - Página com título "🔍 Verificação WhatsApp Gateway - Produção"
   - Lista de verificações com ✅ (checkmarks verdes)
   - Mensagem: "✅ Todos os arquivos essenciais estão presentes!"
   - Resumo mostrando 0 erros
   
   ❌ **Se houver problemas:**
   - Lista de verificações com ❌ (X vermelhos)
   - Mensagem de erro indicando quais arquivos estão faltando
   - Resumo mostrando quantos erros foram encontrados

3. **Se a rota não funcionar (erro 404):**
   - Isso significa que o código ainda não foi atualizado
   - Volte ao **PASSO 1** e verifique se o deploy foi feito corretamente
   - Ou verifique se os arquivos foram realmente atualizados no servidor

---

### **PASSO 3: Verificar Arquivos Manualmente (Opcional)**

Se quiser verificar manualmente via cPanel File Manager ou SSH:

**Arquivos que devem existir:**
- [ ] `src/Controllers/WhatsAppGatewaySettingsController.php`
- [ ] `src/Controllers/WhatsAppGatewayTestController.php`
- [ ] `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`
- [ ] `views/settings/whatsapp_gateway.php`
- [ ] `views/settings/whatsapp_gateway_test.php`

**Verificar rotas em `public/index.php`:**
- [ ] Linha 509: `$router->get('/settings/whatsapp-gateway', ...)`
- [ ] Linha 511: `$router->post('/settings/whatsapp-gateway/test-connection', ...)`
- [ ] Linha 514: `$router->get('/settings/whatsapp-gateway/test', ...)`

**Verificar menu em `views/layout/main.php`:**
- [ ] Linha 470: Link para WhatsApp Gateway
- [ ] Linha 471: Link para "→ Testes & Logs"

---

### **PASSO 4: Verificar se o Menu Aparece**

1. **Acesse o Painel**
   ```
   https://hub.pixel12digital.com.br
   ```

2. **Faça Login** (se necessário)

3. **Navegue até o Menu**
   - No menu lateral esquerdo, procure por **"Configurações"**
   - Clique para expandir (se não estiver expandido)
   - Procure pela seção **"INTEGRAÇÕES"**
   - Você deve ver:
     - ✅ **WhatsApp Gateway** (link principal)
     - ✅ **→ Testes & Logs** (submenu)

4. **Se o menu não aparecer:**
   - **Limpe o cache do navegador**: 
     - Pressione `Ctrl + F5` (Windows/Linux)
     - Ou `Cmd + Shift + R` (Mac)
   - **Limpe o cache do PHP** (se possível):
     - Via SSH: `php -r "opcache_reset();"` (se tiver acesso)
     - Ou reinicie o servidor web via cPanel
   - **Verifique permissões dos arquivos**:
     - Arquivos: 644
     - Diretórios: 755

---

### **PASSO 5: Testar Funcionalidade**

1. **Acesse a Página de Configurações**
   - Clique em **"WhatsApp Gateway"** no menu
   - Ou acesse diretamente: `https://hub.pixel12digital.com.br/settings/whatsapp-gateway`

2. **Verifique se a Página Carrega**
   - Deve aparecer o formulário de configurações
   - Campos:
     - URL base do gateway
     - Secret do Gateway
     - URL do Webhook (Opcional)
     - Secret do Webhook (Opcional)
   - Botões:
     - "Salvar Configurações"
     - "Testar Conexão"
     - "Cancelar"

3. **Se a página não carregar:**
   - Verifique o console do navegador (F12) para erros
   - Verifique os logs do servidor
   - Confirme que todos os arquivos estão presentes (volte ao PASSO 2)

---

### **PASSO 6: Verificar Logs (Se Necessário)**

Se houver problemas, verifique os logs:

**Via cPanel File Manager:**
1. Navegue até a pasta `logs/` na raiz do projeto
2. Abra o arquivo `pixelhub.log`
3. Procure por erros relacionados a:
   - `WhatsAppGatewaySettingsController`
   - `whatsapp-gateway`
   - `Router`

**Via SSH (se tiver acesso):**
```bash
cd /home/pixel12digital/hub.pixel12digital.com.br
tail -f logs/pixelhub.log | grep -i "whatsapp"
```

---

## ✅ Checklist Final

Antes de considerar a verificação completa:

- [ ] Código foi atualizado via cPanel Git (PASSO 1)
- [ ] Deploy foi executado com sucesso (PASSO 1)
- [ ] Rota `/settings/whatsapp-gateway/check` funciona e mostra 0 erros (PASSO 2)
- [ ] Menu "WhatsApp Gateway" aparece em **Configurações → INTEGRAÇÕES** (PASSO 4)
- [ ] Página `/settings/whatsapp-gateway` carrega corretamente (PASSO 5)
- [ ] Cache foi limpo (navegador e servidor, se necessário)

---

## 🐛 Troubleshooting Rápido

### Problema: Menu não aparece
**Solução:** Limpe cache do navegador (Ctrl+F5) e verifique se os arquivos estão em produção

### Problema: Erro 404 ao acessar `/settings/whatsapp-gateway/check`
**Solução:** Verifique se o deploy foi feito corretamente no PASSO 1

### Problema: Erro 500 ao acessar a página
**Solução:** Verifique logs em `logs/pixelhub.log` e confirme que todos os controllers existem

### Problema: Controller não encontrado
**Solução:** Verifique se os arquivos em `src/Controllers/` estão presentes e têm permissões corretas

---

## 📞 Próximos Passos Após Verificação

Se tudo estiver OK:

1. Configure a **Base URL** do gateway (ex: `https://wpp.pixel12digital.com.br`)
2. Configure o **Secret** do gateway (será criptografado automaticamente)
3. Teste a conexão usando o botão **"Testar Conexão"**
4. Configure o **Webhook** (opcional)

---

**Última atualização:** Janeiro 2025  
**Commit relacionado:** `a8d12af`

