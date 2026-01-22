# Troubleshooting - Conexão Supabase

## 🔍 Checklist de Verificação

### 1. Verificar Extensão PHP

O Supabase usa PostgreSQL, então você precisa da extensão `pdo_pgsql` habilitada:

```bash
# Verificar se está instalada
php -m | grep pgsql

# Se não estiver, instale:
# Windows (XAMPP): Edite php.ini e descomente:
extension=pdo_pgsql
extension=pgsql
```

### 2. Verificar Credenciais no .env

No arquivo `.env` do seu projeto Bolt, verifique:

```env
# Exemplo de configuração Supabase
DATABASE_URL=postgresql://[user]:[password]@[host]:5432/[database]?sslmode=require

# Ou separado:
DB_HOST=db.[projeto].supabase.co
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.[projeto]
DB_PASS=[sua_senha]
DB_DRIVER=pgsql
```

**Onde encontrar no Supabase:**
1. Acesse seu projeto no Supabase
2. Vá em **Settings** → **Database**
3. Copie a **Connection String** ou os dados individuais

### 3. Verificar Whitelist de IPs

No Supabase:
1. Vá em **Settings** → **Database**
2. Verifique **Connection Pooling** e **IP Whitelist**
3. Adicione seu IP local ou use `0.0.0.0/0` para desenvolvimento (⚠️ apenas em dev!)

### 4. Verificar String de Conexão no Bolt

No Bolt, a conexão geralmente é configurada em `app/config/config.yml` ou via `.env`:

```yaml
database:
    driver: postgres
    host: db.[projeto].supabase.co
    port: 5432
    dbname: postgres
    user: postgres.[projeto]
    password: [senha]
    charset: utf8mb4
```

### 5. Testar Conexão Manualmente

Crie um arquivo de teste `test_connection.php`:

```php
<?php
try {
    $host = 'db.[seu-projeto].supabase.co';
    $port = 5432;
    $dbname = 'postgres';
    $user = 'postgres.[seu-projeto]';
    $password = '[sua-senha]';
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexão com Supabase estabelecida com sucesso!";
    
    // Testar query
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "\nVersão PostgreSQL: " . $version;
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage();
}
```

### 6. Erros Comuns

**Erro: "could not connect to server"**
- Verifique se o host está correto (deve ser `db.[projeto].supabase.co`)
- Verifique se a porta 5432 está aberta no firewall
- Verifique whitelist de IPs no Supabase

**Erro: "password authentication failed"**
- Verifique usuário e senha no painel Supabase
- Certifique-se de usar o usuário completo: `postgres.[projeto]`

**Erro: "SSL connection required"**
- Adicione `?sslmode=require` na string de conexão
- Ou configure `sslmode=require` no DSN

**Erro: "extension pdo_pgsql not found"**
- Instale a extensão PostgreSQL no PHP
- Reinicie o servidor web (Apache no XAMPP)

### 7. Verificar Logs

- **Supabase:** Vá em **Logs** → **Postgres Logs** para ver tentativas de conexão
- **PHP:** Verifique `error_log` do PHP
- **Bolt:** Verifique logs do framework

## 📝 Próximos Passos

1. ✅ Verificar extensão PDO_PGSQL
2. ✅ Verificar credenciais no .env
3. ✅ Testar conexão manualmente
4. ✅ Verificar whitelist de IPs
5. ✅ Configurar SSL na conexão
6. ✅ Registrar credenciais em "Acessos Rápidos" após resolver

## 🔒 Segurança

⚠️ **Importante:**
- Nunca commite o arquivo `.env` no Git
- Use variáveis de ambiente em produção
- Mantenha credenciais em "Acessos Rápidos" (criptografadas)
- Use Connection Pooling do Supabase em produção

