# 🔐 Alterar Usuário e Senha do Banco - Via cPanel

## ⚠️ Problema

Mesmo logado como admin no phpMyAdmin, você pode receber o erro:
```
#1227 - Acesso negado. Você precisa o privilégio CREATE USER para essa operação
```

Isso acontece porque o phpMyAdmin pode usar um usuário limitado, mesmo que você seja admin no cPanel.

---

## ✅ Solução: Usar cPanel MySQL Databases

A forma mais fácil e garantida é usar o próprio cPanel para criar o usuário:

### Passo 1: Acessar MySQL Databases

1. Faça login no cPanel
2. Procure por **"MySQL Databases"** ou **"MySQL Database Wizard"**
3. Clique para abrir

### Passo 2: Criar Novo Usuário

Na seção **"Add New User"** (ou "Adicionar Novo Usuário"):

- **Username**: `admin_master`
- **Password**: Configure uma senha segura (ou use `ADMIN_MASTER_DB_PASSWORD` do arquivo `.env`)
- Clique em **"Create User"** (ou "Criar Usuário")

### Passo 3: Adicionar Usuário ao Banco

Na seção **"Add User To Database"** (ou "Adicionar Usuário ao Banco"):

1. Selecione o usuário: `admin_master`
2. Selecione o banco: `pixel12digital_pixelhub`
3. Clique em **"Add"** (ou "Adicionar")

### Passo 4: Definir Permissões

Na tela de permissões que abrir:

- Marque **"ALL PRIVILEGES"** (ou "Todas as Permissões")
- Ou marque todas as permissões individualmente
- Clique em **"Make Changes"** (ou "Fazer Alterações")

### Passo 5: Verificar (Opcional)

Depois de criar via cPanel, você pode verificar no phpMyAdmin executando:

```sql
-- Verificar se o usuário foi criado
SELECT User, Host FROM mysql.user WHERE User = 'admin_master';

-- Verificar permissões
SHOW GRANTS FOR 'admin_master'@'%';
```

---

## 🔄 Alternativa: Se o Usuário Já Existir

Se o usuário `admin_master` já existir e você só quiser alterar a senha:

### Via cPanel:
1. Vá em **"MySQL Databases"**
2. Procure por **"Current Users"** (Usuários Atuais)
3. Clique em **"Change Password"** ao lado do usuário `admin_master`
4. Digite a nova senha segura (ou use `ADMIN_MASTER_DB_PASSWORD` do arquivo `.env`)
5. Confirme

### Via phpMyAdmin (se tiver permissão):
```sql
-- SUBSTITUA SUA_SENHA_SEGURA pela senha real
ALTER USER 'admin_master'@'%' IDENTIFIED BY 'SUA_SENHA_SEGURA';
FLUSH PRIVILEGES;
```

---

## ✅ Próximos Passos

Após criar o usuário via cPanel:

1. ✅ O usuário `admin_master` estará criado
2. ✅ Terá todas as permissões no banco `pixel12digital_pixelhub`
3. ✅ A senha será `Los@ngo#081081`

**Não é necessário atualizar o `.env`** se o banco for remoto e as credenciais forem gerenciadas diretamente no servidor.

---

## 📝 Notas

- O cPanel sempre tem permissões completas para criar usuários MySQL
- É a forma mais segura e garantida
- Não depende de privilégios do phpMyAdmin
- Funciona mesmo se o phpMyAdmin tiver limitações

