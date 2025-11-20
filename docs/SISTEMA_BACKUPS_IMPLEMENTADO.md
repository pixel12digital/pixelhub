# Sistema de Backups de Hospedagem - Implementado ✅

## 📋 Resumo

Sistema completo para gerenciar backups de sites WordPress e outros tipos de backup (arquivos .wpress, .zip, .sql, etc.) dentro do Pixel Hub. O sistema detecta automaticamente o tipo de backup pela extensão do arquivo.

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
  - Valores possíveis: `all_in_one_wp`, `site_zip`, `database_sql`, `compressed_archive`, `other_code`
  - **Auto-detectado pela extensão do arquivo** (não mais seleção manual)
- `file_name` - VARCHAR(255) NOT NULL
- `file_size` - BIGINT UNSIGNED NULL
- `stored_path` - VARCHAR(500) NOT NULL
- `notes` - TEXT NULL
- `created_at` - DATETIME NULL

**Status:** ✅ Criada

---

### 2. Estrutura de Armazenamento & Deploy

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
/storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file_name}
```
(Suporta múltiplos formatos: .wpress, .zip, .sql, .gz, .tgz, .tar, .bz2, .rar, .7z)

**Proteção:**
- `.htaccess` em `storage/` para negar acesso direto
- `.gitignore` ignora `/storage/tenants/` e `*.wpress`
- **Importante:** arquivos físicos permanecem apenas no servidor local em que foram enviados; deploys levam só código. É esperado que ambiente de produção tenha registros sem o arquivo correspondente se o backup foi feito em outro ambiente.

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
   - **Auto-detecta tipo de backup pela extensão** (.wpress, .zip, .sql, .gz, .tgz, .tar, .bz2, .rar, .7z)
   - Valida extensão permitida (tamanho máximo 2GB)
   - Salva arquivo no diretório correto
   - Grava registro no banco com tipo detectado automaticamente
   - Atualiza `backup_status` do hosting account

3. **`download()`** - Download protegido de backup
   - Requer autenticação interna
   - Verifica existência do arquivo e responde 404 textual se não existir (caso comum após deploy sem arquivo físico)
   - Envia arquivo com headers corretos quando encontrado

4. **Uploads em partes (chunked)** – usado automaticamente quando o JS calcula que o arquivo é maior do que o limite suportado pelo PHP na requisição tradicional.
   - `chunkInit()` cria sessão em `storage/temp/chunks/{upload_id}` com metadados
   - `chunkUpload()` grava cada parte (`chunk_000000`, `chunk_000001`, ...)
   - `chunkComplete()` reúne todas as partes, salva no destino final e registra no banco

**Status:** ✅ Implementado

#### 3.2. Rotas

**Localização:** `public/index.php`

```php
$router->get('/hosting/backups', 'HostingBackupController@index');
$router->get('/hosting/backups/logs', 'HostingBackupController@viewLogs');
$router->post('/hosting/backups/upload', 'HostingBackupController@upload');
$router->post('/hosting/backups/chunk-init', 'HostingBackupController@chunkInit');
$router->post('/hosting/backups/chunk-upload', 'HostingBackupController@chunkUpload');
$router->post('/hosting/backups/chunk-complete', 'HostingBackupController@chunkComplete');
$router->get('/hosting/backups/download', 'HostingBackupController@download');
```

**Status:** ✅ Adicionadas (todas protegidas, apenas internos). Não existe rota de exclusão de backups.

---

### 4. Views

#### 4.1. `views/hosting/backups.php`

**Funcionalidades:**
- Exibe informações do site (domínio, cliente, provedor, status)
- Mostra data de expiração da Hostinger (se houver)
- Formulário de upload com:
  - Campo file (accept múltiplos formatos: .wpress, .zip, .sql, etc.)
  - Campo notes (textarea)
  - Validação de tamanho máximo
  - **Tipo detectado automaticamente pela extensão** (sem seleção manual)
- Tabela de backups existentes com:
  - Data
  - Tipo (formatado de forma amigável: "WordPress (.wpress – All-in-One)", "Site completo (.zip)", etc.)
  - Nome do arquivo
  - Tamanho formatado
  - Notas
  - Link de download
- Mensagens de erro/sucesso

**Status:** ✅ Criada

#### 4.2. `views/tenants/view.php` (aba `Docs & Backups`)

- Aba dos clientes (`/tenants/view?id={id}&tab=docs_backups`) reutiliza os mesmos dados carregados por `TenantsController@show`
- Formulário aponta para `/hosting/backups/upload` com `redirect_to=tenant` para voltar à aba após o POST
- **Limitação atual:** não há JS de upload em partes nem barra de progresso nessa aba; uploads grandes utilizam apenas o POST tradicional e podem aparentar “carregar para sempre”
- Tabela lista backups com domínio, data, tipo, tamanho e notas, e gera links de download idênticos aos da tela dedicada

---

## 🔒 Segurança

1. **Autenticação:** Todas as rotas requerem `Auth::requireInternal()`
2. **Validação de arquivo:**
   - Extensões permitidas: .wpress, .zip, .sql, .gz, .tgz, .tar, .bz2, .rar, .7z
   - Tipo detectado automaticamente pela extensão
   - Tamanho máximo: 2GB
   - Nome de arquivo sanitizado
3. **Proteção de diretório:**
   - `.htaccess` nega acesso direto
   - Download apenas via rota protegida
4. **Logs e Auditoria:**
   - `pixelhub_log`/`error_log` registram cada passo do upload com prefixo `[HostingBackup]`
   - Tela `/hosting/backups/logs` filtra as últimas linhas de `logs/pixelhub.log` para cada site

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

1. Selecione o arquivo de backup (.wpress, .zip, .sql, ou outro formato suportado)
2. O sistema detecta automaticamente o tipo pela extensão
3. (Opcional) Adicione notas
4. Clique em "Enviar Backup"

### 4. Visualizar Backups

A lista mostra todos os backups do site com opção de download.

---

## 🎯 Próximos Passos (Pendentes)

1. **Experiência do cliente na aba `Docs & Backups`:**
   - Reutilizar o fluxo em chunks e barra de progresso da tela interna
   - Dar feedback visível durante uploads grandes

2. **Integridade dos arquivos:**
   - Verificar existência do arquivo antes de exibir "Download" ou sinalizar quando indisponível (deploys não levam `.wpress`)

3. **Exclusão segura:**
   - Implementar rota/ação para remover registro + arquivo físico (hoje inexistente)

4. **Melhorias futuras:**
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
**Última Atualização:** 25/01/2025 - Auto-detecção de tipo de backup por extensão  
**Status:** ✅ Implementação Completa - Pronto para Uso

---

## 🔄 Mudanças Recentes (25/01/2025)

### Auto-detecção de Tipo de Backup

O sistema agora detecta automaticamente o tipo de backup pela extensão do arquivo, eliminando a necessidade de seleção manual:

- **.wpress** → `all_in_one_wp` (WordPress - All-in-One WP Migration)
- **.zip** → `site_zip` (Site completo)
- **.sql** → `database_sql` (Banco de dados)
- **.gz, .tgz, .tar, .bz2** → `compressed_archive` (Arquivo compactado)
- **Outros** → `other_code` (Arquivo de código/backup)

**Extensões permitidas:** .wpress, .zip, .sql, .gz, .tgz, .tar, .bz2, .rar, .7z

A exibição do tipo na tabela foi atualizada para mostrar textos amigáveis como "WordPress (.wpress – All-in-One)" em vez do valor técnico do banco.

**Compatibilidade:** Registros antigos com `type = 'all_in_one_wp'` continuam funcionando normalmente.

