# 🔧 Resolver Erro de Deploy no cPanel

## ❌ Erro no cPanel

```
fatal: Not possible to fast-forward, aborting.
hint: Diverging branches can't be fast-forwarded
```

## 🔍 Causa

O histórico do Git foi **reescrito** (force push) localmente, mas o servidor (cPanel) ainda tem o histórico antigo. Quando o cPanel tenta fazer pull, detecta que as branches divergiram.

## ✅ Soluções

### Opção 1: Reset no Servidor (RECOMENDADO)

**Via SSH no servidor:**

```bash
# 1. Conectar ao servidor via SSH
ssh usuario@servidor

# 2. Navegar até o diretório do repositório
cd /home/pixel12digital/hub.pixel12digital.com.br

# 3. Verificar estado atual
git status
git log --oneline -5

# 4. Fazer fetch para atualizar referências remotas
git fetch origin

# 5. Resetar branch local para o remoto (sobrescreve local)
git reset --hard origin/main

# 6. Verificar que está atualizado
git log --oneline -5
```

**Depois disso, o deploy no cPanel deve funcionar normalmente.**

### Opção 2: Via cPanel File Manager

Se não tiver acesso SSH:

1. **Acesse File Manager no cPanel**
2. **Navegue até**: `/home/pixel12digital/hub.pixel12digital.com.br`
3. **Abra terminal** (se disponível) ou use **Git Version Control**

**No Git Version Control do cPanel:**
- Vá em "Pull or Deploy"
- Selecione "Reset to Remote Branch"
- Ou use "Hard Reset" para `origin/main`

### Opção 3: Re-clonar no Servidor

**Se as opções acima não funcionarem:**

```bash
# 1. Fazer backup do diretório atual
cd /home/pixel12digital
mv hub.pixel12digital.com.br hub.pixel12digital.com.br.backup

# 2. Clonar novamente
git clone https://github.com/pixel12digital/pixelhub.git hub.pixel12digital.com.br

# 3. Verificar
cd hub.pixel12digital.com.br
git log --oneline -5
```

### Opção 4: Configurar cPanel para Force Pull

**No cPanel Git Version Control:**

1. Vá em "Pull or Deploy"
2. Antes de fazer pull, configure:
   - **Branch**: `main`
   - **Force Pull**: Ative esta opção (se disponível)
   - Ou use "Reset to Remote" antes de fazer pull

## 📋 Passo a Passo Detalhado (SSH)

### 1. Conectar ao Servidor

```bash
ssh root@seu-servidor
# ou
ssh usuario@seu-servidor
```

### 2. Navegar até o Diretório

```bash
cd /home/pixel12digital/hub.pixel12digital.com.br
```

### 3. Verificar Estado

```bash
# Ver branch atual
git branch

# Ver commits locais
git log --oneline -5

# Ver commits remotos
git fetch origin
git log origin/main --oneline -5
```

### 4. Resetar para o Remoto

```bash
# Resetar completamente para o remoto
git reset --hard origin/main

# OU se quiser manter mudanças locais (não recomendado neste caso)
git reset --soft origin/main
```

### 5. Verificar

```bash
# Deve mostrar o commit mais recente
git log --oneline -1

# Deve mostrar "Your branch is up to date with 'origin/main'"
git status
```

### 6. Testar Deploy no cPanel

Depois do reset, volte ao cPanel e tente fazer deploy novamente.

## ⚠️ Importante

**Após fazer reset no servidor:**
- ✅ O servidor terá o histórico atualizado
- ✅ Deploy no cPanel deve funcionar
- ⚠️ Qualquer mudança local no servidor será perdida (se houver)

## 🔄 Se o Problema Persistir

**Verificar configurações do Git no servidor:**

```bash
# Ver configuração remota
git remote -v

# Ver branch atual
git branch -a

# Verificar se está no branch correto
git checkout main

# Forçar atualização
git fetch --all
git reset --hard origin/main
```

## 📝 Comandos Rápidos (Copy/Paste)

```bash
cd /home/pixel12digital/hub.pixel12digital.com.br && \
git fetch origin && \
git reset --hard origin/main && \
git status
```

---

**Status**: Erro de deploy causado por histórico reescrito
**Solução**: Reset no servidor para sincronizar com remoto

