# 🔐 Alterar Usuário e Senha do Banco de Dados

## 📋 Objetivo

Alterar as credenciais do banco de dados MySQL para melhorar a segurança em produção:
- **Novo Usuário**: `admin_master`
- **Nova Senha**: Configure no arquivo `.env` como `ADMIN_MASTER_DB_PASSWORD` ou será solicitada durante a execução

---

## 🛠️ Método 1: Via Script SQL (Recomendado)

### Passo 1: Acessar o Servidor MySQL

Você pode executar o script SQL de três formas:

#### Opção A: Via phpMyAdmin
1. Acesse o phpMyAdmin do servidor remoto
2. Selecione o banco de dados ou vá na aba "SQL"
3. Cole o conteúdo do arquivo `database/alterar-usuario-senha.sql`
4. Execute

#### Opção B: Via cPanel
1. Acesse o cPanel
2. Vá em "MySQL Databases" ou "phpMyAdmin"
3. Execute o script SQL

#### Opção C: Via SSH
```bash
mysql -u root -p
# Digite a senha do root
# Cole e execute o conteúdo do script SQL
```

### Passo 2: Executar o Script

O script SQL (`database/alterar-usuario-senha.sql`) irá:
1. ✅ Criar o usuário `admin_master` com a senha configurada
2. ✅ Conceder todas as permissões no banco de dados
3. ✅ Aplicar as mudanças
4. ✅ Verificar se foi criado corretamente

**⚠️ IMPORTANTE**: Substitua `SUA_SENHA_SEGURA` no script SQL pela senha real antes de executar!

### Passo 3: Atualizar o .env (se existir)

Após executar o script SQL, atualize o arquivo `.env` na raiz do projeto:

```env
DB_USER=admin_master
DB_PASS=sua_senha_segura_aqui
ADMIN_MASTER_DB_PASSWORD=sua_senha_segura_aqui
```

### Passo 4: Testar a Conexão

Execute o script de teste:

```bash
php database/test-connection.php
```

---

## 🛠️ Método 2: Via Script PHP (Se tiver acesso root)

Se você tiver acesso root ao MySQL, pode executar o script PHP:

```bash
php database/alterar-usuario-senha.php
```

O script irá:
1. Solicitar credenciais de administrador (root)
2. Criar/atualizar o usuário automaticamente
3. Testar a conexão com o novo usuário

**⚠️ Atenção**: Este método só funciona se você tiver acesso root ao MySQL.

---

## 📝 Comandos SQL Manuais

Se preferir executar manualmente, use estes comandos:

```sql
-- Criar o novo usuário (SUBSTITUA SUA_SENHA_SEGURA pela senha real)
CREATE USER IF NOT EXISTS 'admin_master'@'%' IDENTIFIED BY 'SUA_SENHA_SEGURA';

-- Conceder permissões (SUBSTITUA nome_do_banco pelo nome real do banco)
GRANT ALL PRIVILEGES ON `nome_do_banco`.* TO 'admin_master'@'%';

-- Aplicar mudanças
FLUSH PRIVILEGES;

-- Verificar
SHOW GRANTS FOR 'admin_master'@'%';
```

---

## 🔒 Segurança Adicional (Opcional)

Para maior segurança, você pode restringir o acesso apenas ao seu IP:

```sql
-- Remover acesso de qualquer IP
DROP USER IF EXISTS 'admin_master'@'%';

-- Criar apenas para IP específico (SUBSTITUA SEU_IP e SUA_SENHA_SEGURA)
CREATE USER 'admin_master'@'SEU_IP' IDENTIFIED BY 'SUA_SENHA_SEGURA';
GRANT ALL PRIVILEGES ON `nome_do_banco`.* TO 'admin_master'@'SEU_IP';
FLUSH PRIVILEGES;
```

---

## ✅ Verificação

Após alterar o usuário, verifique:

1. **Usuário criado**:
   ```sql
   SELECT User, Host FROM mysql.user WHERE User = 'admin_master';
   ```

2. **Permissões**:
   ```sql
   SHOW GRANTS FOR 'admin_master'@'%';
   ```

3. **Conexão funcionando**:
   ```bash
   php database/test-connection.php
   ```

---

## 🗑️ Remover Usuário Antigo (Opcional)

**⚠️ IMPORTANTE**: Só remova o usuário antigo após confirmar que o novo está funcionando!

```sql
-- Verificar qual é o usuário antigo
SELECT User, Host FROM mysql.user WHERE User LIKE 'pixel12digital%';

-- Remover usuário antigo (ajuste o nome conforme necessário)
DROP USER IF EXISTS 'pixel12digital_pixelhub'@'%';
FLUSH PRIVILEGES;
```

---

## 📚 Arquivos Relacionados

- `database/alterar-usuario-senha.sql` - Script SQL completo
- `database/alterar-usuario-senha.php` - Script PHP automático
- `database/test-connection.php` - Testar conexão
- `config/database.php` - Configuração do banco

---

## ⚠️ Importante

- ✅ Sempre faça backup antes de alterar credenciais
- ✅ Teste a conexão antes de remover o usuário antigo
- ✅ Mantenha as credenciais seguras (não commite no Git)
- ✅ O arquivo `.env` já está no `.gitignore`

