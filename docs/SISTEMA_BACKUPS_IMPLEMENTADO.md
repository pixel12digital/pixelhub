# Sistema de Backups de Hospedagem - Implementado ✅

## 📋 Resumo

Sistema completo para gerenciar backups de sites WordPress (arquivos .wpress do All-in-One WP Migration) dentro do Pixel Hub.

---

## ✅ Implementações Realizadas

### 1. Banco de Dados

#### 1.1. Tabela `hosting_accounts`

**Migration:** `20251117_create_hosting_accounts_table.php`

**Campos principais:**
- `id` - INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `tenant_id` - INT UNSIGNED NOT NULL
- `domain` - VARCHAR(255) NOT NULL
- `current_provider` - VARCHAR(50) NOT NULL DEFAULT 'hostinger'
- `hostinger_expiration_date` - DATE NULL
- `decision` - VARCHAR(50) NOT NULL DEFAULT 'pendente'
  - Valores: `pendente`, `migrar_pixel`, `hostinger_afiliado`, `encerrar`
- `backup_status` - VARCHAR(50) NOT NULL DEFAULT 'nenhum'
  - Valores: `nenhum`, `completo`
- `migration_status` - VARCHAR(50) NOT NULL DEFAULT 'nao_iniciada'
  - Valores: `nao_iniciada`, `em_andamento`, `concluida`
- `created_at`, `updated_at` - DATETIME NULL

**Status:** ✅ Criada

#### 1.2. Tabela `hosting_backups`

**Migration:** `20251117_create_hosting_backups_table.php`

**Campos:**
- `id` - INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `hosting_account_id` - INT UNSIGNED NOT NULL
- `type` - VARCHAR(50) NOT NULL DEFAULT 'all_in_one_wp'
- `file_name` - VARCHAR(255) NOT NULL
- `file_size` - BIGINT UNSIGNED NULL
- `stored_path` - VARCHAR(500) NOT NULL
- `notes` - TEXT NULL
- `created_at` - DATETIME NULL

**Status:** ✅ Criada

---

### 2. Estrutura de Armazenamento

#### 2.1. Classe `Storage.php`

**Localização:** `src/Core/Storage.php`

**Métodos:**
- `getTenantBackupDir(int $tenantId, int $hostingAccountId): string`
  - Retorna: `/storage/tenants/{tenant_id}/backups/{hosting_account_id}/`
- `ensureDirExists(string $path): void`
  - Cria diretório se não existir (com permissões 0755)
- `generateSafeFileName(string $originalName): string`
  - Remove caracteres perigosos e limita tamanho
- `formatFileSize(int $bytes): string`
  - Formata tamanho em formato legível (B, KB, MB, GB, TB)

**Status:** ✅ Implementado

#### 2.2. Estrutura de Diretórios

**Padrão de caminho:**
```
/storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file_name}.wpress
```

**Proteção:**
- `.htaccess` em `storage/` para negar acesso direto
- `.gitignore` atualizado para ignorar `/storage/tenants/` e `*.wpress`

**Status:** ✅ Criado

---

### 3. Controller e Rotas

#### 3.1. `HostingBackupController`

**Localização:** `src/Controllers/HostingBackupController.php`

**Métodos:**

1. **`index()`** - Lista backups de um hosting account
   - Requer autenticação interna
   - Recebe `hosting_id` via GET
   - Busca dados do hosting account e lista backups

2. **`upload()`** - Processa upload de backup
   - Requer autenticação interna
   - Valida arquivo (.wpress, tamanho máximo 2GB)
   - Salva arquivo no diretório correto
   - Grava registro no banco
   - Atualiza `backup_status` do hosting account

3. **`download()`** - Download protegido de backup
   - Requer autenticação interna
   - Verifica existência do arquivo
   - Envia arquivo com headers corretos

**Status:** ✅ Implementado

#### 3.2. Rotas

**Localização:** `public/index.php`

```php
$router->get('/hosting/backups', 'HostingBackupController@index');
$router->post('/hosting/backups/upload', 'HostingBackupController@upload');
$router->get('/hosting/backups/download', 'HostingBackupController@download');
```

**Status:** ✅ Adicionadas (todas protegidas, apenas internos)

---

### 4. Views

#### 4.1. `views/hosting/backups.php`

**Funcionalidades:**
- Exibe informações do site (domínio, cliente, provedor, status)
- Mostra data de expiração da Hostinger (se houver)
- Formulário de upload com:
  - Campo file (accept=".wpress")
  - Campo notes (textarea)
  - Validação de tamanho máximo
- Tabela de backups existentes com:
  - Data
  - Tipo
  - Nome do arquivo
  - Tamanho formatado
  - Notas
  - Link de download
- Mensagens de erro/sucesso

**Status:** ✅ Criada

---

## 🔒 Segurança

1. **Autenticação:** Todas as rotas requerem `Auth::requireInternal()`
2. **Validação de arquivo:**
   - Extensão .wpress obrigatória
   - Tamanho máximo: 2GB
   - Nome de arquivo sanitizado
3. **Proteção de diretório:**
   - `.htaccess` nega acesso direto
   - Download apenas via rota protegida

---

## 📝 Como Usar

### 1. Cadastrar Hosting Account

Primeiro, cadastre o site na tabela `hosting_accounts`:

```sql
INSERT INTO hosting_accounts 
(tenant_id, domain, current_provider, hostinger_expiration_date, decision)
VALUES 
(1, 'exemplo.com.br', 'hostinger', '2025-12-31', 'pendente');
```

### 2. Acessar Página de Backups

Acesse:
```
http://localhost/painel.pixel12digital/public/hosting/backups?hosting_id=1
```

### 3. Fazer Upload

1. Selecione o arquivo .wpress
2. (Opcional) Adicione notas
3. Clique em "Enviar Backup"

### 4. Visualizar Backups

A lista mostra todos os backups do site com opção de download.

---

## 🎯 Próximos Passos (Opcional)

1. **Criar view de listagem de hosting accounts** (`views/hosting/index.php`)
   - Listar todos os sites
   - Botão "Backups" em cada linha
   - Filtros por status, provedor, etc.

2. **Melhorias futuras:**
   - Compressão automática
   - Agendamento de backups
   - Notificações de expiração
   - Integração com API de hospedagem

---

## 📊 Estrutura de Arquivos Criados

```
database/migrations/
  ├── 20251117_create_hosting_accounts_table.php
  └── 20251117_create_hosting_backups_table.php

src/Core/
  └── Storage.php

src/Controllers/
  └── HostingBackupController.php

views/hosting/
  └── backups.php

storage/
  ├── .gitkeep
  ├── .htaccess
  └── tenants/ (criado automaticamente)
```

---

**Data da Implementação:** 17/11/2025  
**Status:** ✅ Implementação Completa - Pronto para Uso

