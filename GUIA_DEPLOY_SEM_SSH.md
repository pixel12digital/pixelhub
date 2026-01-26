# 🚀 Guia Completo: Deploy em Produção SEM SSH

**Objetivo:** Atualizar produção para ficar igual ao código local, sem acesso SSH.

---

## 📋 Pré-requisitos

✅ Código local commitado e pushado para GitHub  
✅ Acesso ao cPanel da HostMídia  
✅ Repositório Git configurado no cPanel

---

## 🎯 Método 1: Via cPanel Git Version Control (RECOMENDADO)

Este é o método oficial e mais seguro do cPanel.

### Passo 1: Verificar que o código está no GitHub

No seu ambiente local, certifique-se de que tudo está commitado e pushado:

```powershell
git status
git log --oneline -1
git push origin main
```

### Passo 2: Acessar cPanel Git Version Control

1. Acesse o **cPanel** da HostMídia
2. Vá em **Tools** → **Git™ Version Control**
3. Clique em **Manage Repository** para o repositório `hub.pixel12digital.com.br`

### Passo 3: Atualizar do Remote (Pull)

1. Clique no botão **"Update from Remote"** (ícone de nuvem com seta para baixo ⬇️)
2. Isso vai executar `git fetch` e `git pull` do GitHub
3. Aguarde a confirmação de sucesso
4. **Verifique o hash do commit** - deve ser igual ao seu local: `c189200ca8d0f3418e864df82a9dcca1212b4eeb`

### Passo 4: Verificar Requisitos para Deploy

O cPanel mostra dois requisitos que devem estar OK:

- ✅ **A valid `.cpanel.yml` file exists** - O arquivo existe na raiz do repositório
- ✅ **No uncommitted changes exist** - Após o pull, não deve haver mudanças locais

**Se houver mudanças não commitadas no servidor:**

**Opção A - Descartar mudanças (se não forem importantes):**
- Use o script PHP `atualizar-repositorio.php` (veja Método 2 abaixo)
- Ou use o Terminal do cPanel (se disponível) para executar: `git reset --hard origin/main`

**Opção B - Fazer commit das mudanças (se forem importantes):**
- Use o Terminal do cPanel para fazer commit
- Depois faça push e merge no GitHub

### Passo 5: Fazer Deploy

1. Após garantir que os requisitos estão OK, clique em **"Deploy HEAD Commit"**
2. Aguarde a confirmação de sucesso
3. O deploy vai executar o `.cpanel.yml` e copiar os arquivos para produção

### Passo 6: Verificar Deploy

1. Acesse: `https://hub.pixel12digital.com.br/public/verificar-deploy.php`
2. Verifique se todos os itens estão com ✓
3. Teste a aplicação normalmente

---

## 🔧 Método 2: Via Script PHP (Quando há divergências)

Use este método quando o cPanel não consegue fazer pull devido a divergências ou mudanças locais no servidor.

### Passo 1: Fazer Upload do Script

1. O arquivo `atualizar-repositorio.php` já existe no projeto
2. Faça upload dele para: `/home/pixel12digital/hub.pixel12digital.com.br/`
3. Ou acesse via FTP e coloque na raiz do projeto

### Passo 2: Executar o Script

1. Acesse via navegador: `https://hub.pixel12digital.com.br/atualizar-repositorio.php`
2. O script vai:
   - Verificar o repositório Git
   - Mostrar o estado atual
   - Fazer `git fetch origin`
   - Executar `git reset --hard origin/main` (sobrescreve mudanças locais)
   - Mostrar o resultado final

### Passo 3: Voltar ao cPanel para Deploy

1. Após o script executar com sucesso, volte ao cPanel
2. Agora os requisitos devem estar OK
3. Clique em **"Deploy HEAD Commit"**

### Passo 4: Remover o Script (IMPORTANTE!)

⚠️ **SEGURANÇA:** Após usar, **DELETE** o arquivo `atualizar-repositorio.php` do servidor!

---

## 🖥️ Método 3: Via Terminal do cPanel (Se disponível)

Se o seu cPanel tiver acesso ao Terminal, você pode executar comandos Git diretamente.

### Passo 1: Acessar Terminal

1. No cPanel, vá em **Advanced** → **Terminal**
2. Navegue até o diretório do projeto:
   ```bash
   cd /home/pixel12digital/hub.pixel12digital.com.br
   ```

### Passo 2: Atualizar Repositório

```bash
# Verificar estado atual
git status

# Buscar atualizações do GitHub
git fetch origin

# Verificar diferenças
git log HEAD..origin/main --oneline

# Resetar para origin/main (sobrescreve mudanças locais)
git reset --hard origin/main

# Verificar resultado
git status
git log --oneline -1
```

### Passo 3: Fazer Deploy via cPanel

1. Volte ao Git Version Control no cPanel
2. Clique em **"Deploy HEAD Commit"**

---

## 📊 Comparação dos Métodos

| Método | Quando Usar | Vantagens | Desvantagens |
|--------|-------------|-----------|--------------|
| **cPanel Git** | Situação normal | Oficial, seguro, simples | Pode falhar com divergências |
| **Script PHP** | Divergências ou mudanças locais | Funciona via web, resolve divergências | Precisa ser removido após uso |
| **Terminal** | Se disponível | Controle total | Requer conhecimento de comandos Git |

---

## 🔍 Verificação Pós-Deploy

### 1. Script de Verificação Automática

Acesse: `https://hub.pixel12digital.com.br/public/verificar-deploy.php`

O script verifica:
- ✅ Se os arquivos foram atualizados
- ✅ Se os métodos corretos existem
- ✅ Se as rotas estão configuradas

### 2. Verificação Manual

1. **Verificar hash do commit:**
   - No cPanel Git Version Control, veja o hash do HEAD
   - Deve ser: `c189200ca8d0f3418e864df82a9dcca1212b4eeb`

2. **Testar funcionalidades:**
   - Acesse o painel normalmente
   - Teste as funcionalidades que foram alteradas
   - Verifique o console do navegador (F12) para erros

3. **Verificar logs:**
   - Acesse `logs/pixelhub.log` via FTP ou File Manager
   - Procure por erros recentes

---

## ⚠️ Troubleshooting

### Problema: "Update from Remote" falha

**Sintomas:**
- Erro ao fazer pull
- Mensagem de "diverging branches"

**Solução:**
1. Use o Método 2 (Script PHP) para fazer reset
2. Depois tente "Update from Remote" novamente

### Problema: "Deploy HEAD Commit" está desabilitado

**Sintomas:**
- Botão não clicável
- Mensagem: "The system cannot deploy"

**Soluções:**

1. **Verificar arquivo .cpanel.yml:**
   - Deve existir na raiz do repositório
   - Estrutura correta:
     ```yaml
     ---
     deployment:
       tasks:
         - export DEPLOYPATH=/home/pixel12digital/hub.pixel12digital.com.br
         - /bin/cp -R * $DEPLOYPATH/ 2>/dev/null || true
         - /bin/chmod -R 755 $DEPLOYPATH/storage 2>/dev/null || true
         - /bin/chmod -R 755 $DEPLOYPATH/public/assets 2>/dev/null || true
     ```

2. **Verificar mudanças não commitadas:**
   - Use o script PHP para fazer reset
   - Ou use o Terminal para: `git reset --hard origin/main`

3. **Verificar permissões:**
   - Certifique-se de que o Git tem permissão para escrever no diretório

### Problema: Deploy funciona mas código não atualiza

**Sintomas:**
- Deploy conclui com sucesso
- Mas o código em produção não muda

**Soluções:**

1. **Verificar caminho no .cpanel.yml:**
   - O `DEPLOYPATH` deve estar correto
   - Deve apontar para o diretório de produção

2. **Verificar permissões:**
   - O usuário do Git precisa ter permissão de escrita
   - Verifique via File Manager se os arquivos foram atualizados

3. **Limpar cache:**
   - Limpe o cache do navegador (Ctrl+F5)
   - Verifique se há cache no servidor (OPcache, etc.)

---

## ✅ Checklist Completo de Deploy

### Antes do Deploy
- [ ] Código local commitado
- [ ] Push feito para GitHub
- [ ] Hash do commit local anotado: `c189200ca8d0f3418e864df82a9dcca1212b4eeb`

### Durante o Deploy
- [ ] Acessado cPanel Git Version Control
- [ ] Clicado em "Update from Remote"
- [ ] Verificado que hash do servidor = hash local
- [ ] Verificado requisitos (`.cpanel.yml` existe, sem mudanças locais)
- [ ] Clicado em "Deploy HEAD Commit"
- [ ] Aguardado confirmação de sucesso

### Após o Deploy
- [ ] Acessado script de verificação: `/public/verificar-deploy.php`
- [ ] Verificado hash do commit em produção
- [ ] Testado funcionalidades alteradas
- [ ] Verificado console do navegador (F12)
- [ ] Verificado logs (`logs/pixelhub.log`)
- [ ] Removido script PHP temporário (se usado)

---

## 📝 Notas Importantes

1. **Sempre faça backup antes de deploy:**
   - O cPanel pode ter backup automático
   - Ou use o File Manager para fazer backup manual

2. **Horário de deploy:**
   - Prefira horários de menor tráfego
   - Avise usuários se necessário

3. **Monitoramento:**
   - Após deploy, monitore logs por alguns minutos
   - Verifique se não há erros no console do navegador

4. **Rollback:**
   - Se algo der errado, você pode fazer rollback:
     - No cPanel Git Version Control, escolha um commit anterior
     - Clique em "Deploy HEAD Commit" novamente

---

## 🎯 Resumo Rápido (Fluxo Ideal)

```
1. Local: git push origin main
2. cPanel: "Update from Remote" ⬇️
3. cPanel: Verificar requisitos ✅
4. cPanel: "Deploy HEAD Commit" 🚀
5. Verificar: /public/verificar-deploy.php ✅
```

---

**Última atualização:** 2025-01-22  
**Hash do commit atual:** `c189200ca8d0f3418e864df82a9dcca1212b4eeb`

