# Sistema de Backups de Hospedagem - Implementado ✅

## 📋 Resumo

Sistema completo para gerenciar backups de sites WordPress e outros tipos de backup via URLs externas (principalmente Google Drive). O Pixel Hub funciona como um painel de controle centralizado, registrando apenas os links dos backups e metadados, sem armazenar os arquivos físicos de backup na hospedagem compartilhada.

**Mudança Importante (31/01/2025):** O sistema não armazena mais arquivos de backup no Pixel Hub. Todos os backups são registrados via URL externa, preferencialmente Google Drive.

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
  - Valores possíveis: `all_in_one_wp`, `site_zip`, `database_sql`, `compressed_archive`, `other_code`, `external_link`, `google_drive`
  - Para backups externos: `external_link` ou `google_drive`
  - Para backups antigos (arquivos locais): mantém tipos originais detectados por extensão
- `file_name` - VARCHAR(255) NOT NULL
- `file_size` - BIGINT UNSIGNED NULL (NULL para backups externos)
- `stored_path` - VARCHAR(500) NULL (NULL para backups externos, mantido para compatibilidade com backups antigos)
- `external_url` - VARCHAR(500) NULL (URL do backup externo, ex.: Google Drive)
- `storage_location` - VARCHAR(100) NULL (Onde está armazenado: `google_drive`, `onedrive`, `s3`, `outro`)
- `notes` - TEXT NULL
- `created_at` - DATETIME NULL

**Migration:** `20250131_alter_hosting_backups_add_external_url.php` adiciona os campos `external_url` e `storage_location`.

**Status:** ✅ Criada e atualizada

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

2. **`upload()`** - Registra backup via URL externa
   - Requer autenticação interna
   - **Agora aceita apenas URL externa** (não faz mais upload de arquivo)
   - Valida URL (deve começar com http:// ou https://, máximo 500 caracteres)
   - Detecta automaticamente o provedor de armazenamento pela URL (Google Drive, OneDrive, S3, etc.)
   - Define `type = 'external_link'` para novos backups externos
   - Define `storage_location` automaticamente (google_drive, onedrive, s3, outro)
   - Grava registro no banco com `external_url` preenchido e `stored_path = NULL`
   - Atualiza `backup_status` do hosting account
   - **Compatibilidade:** Backups antigos com `stored_path` continuam funcionando normalmente

3. **`download()`** - Download protegido de backup
   - Requer autenticação interna
   - Verifica existência do arquivo e responde 404 textual se não existir (caso comum após deploy sem arquivo físico)
   - Envia arquivo com headers corretos quando encontrado

4. **Uploads em partes (chunked)** – usado automaticamente quando o JS calcula que o arquivo é maior do que o limite suportado pelo PHP na requisição tradicional.
   - `chunkInit()` cria sessão em `storage/temp/chunks/{upload_id}` com metadados
   - `chunkUpload()` grava cada parte (`chunk_000000`, `chunk_000001`, ...) com validações robustas
   - `chunkComplete()` reúne todas as partes, valida tamanho final, e registra no banco usando `filesize()` real
   - **Chunk size:** 1MB (otimizado para ambientes compartilhados)
   - **Logs detalhados:** `logs/backup_upload.log` registra todo o fluxo
   - **Validações:** Chunks vazios são detectados, arquivo final é validado antes de criar registro no banco

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

### 3. Registrar Backup

1. No Google Drive (ou outro serviço), faça upload do arquivo de backup
2. Compartilhe o arquivo/pasta e obtenha o link compartilhável
3. No Pixel Hub, selecione o site/hospedagem
4. Cole a URL do backup no campo "URL do backup (Google Drive)"
5. (Opcional) Adicione notas sobre o backup
6. Clique em "Registrar Backup"

**Importante:** O Pixel Hub não armazena mais os arquivos de backup. Apenas registra o link e os metadados.

### 4. Visualizar e Acessar Backups

A lista mostra todos os backups do site:
- **Backups externos (novos):** Mostram botão "Abrir backup" que abre a URL externa em nova aba
- **Backups antigos (com arquivo local):** Continuam mostrando botão "Download" para baixar do servidor
- Tipo exibido como "Backup externo (link)" ou "Google Drive (link)" para novos backups
- Tamanho exibido como "—" para backups externos (file_size = NULL)

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
**Última Atualização:** 31/01/2025 - Migração para URLs externas (Google Drive)  
**Status:** ✅ Implementação Completa - Pronto para Uso

---

## 🔄 Mudanças Recentes

### Migração para URLs Externas (31/01/2025)

O sistema foi reestruturado para não armazenar mais arquivos de backup na hospedagem compartilhada. Agora funciona como um painel de controle centralizado:

**Mudanças principais:**
1. **Novos backups:** Registrados apenas via URL externa (principalmente Google Drive)
2. **Campos novos:** `external_url` e `storage_location` adicionados à tabela `hosting_backups`
3. **Tipo:** Novos backups usam `type = 'external_link'` ou `google_drive`
4. **Armazenamento:** `stored_path` e `file_size` são NULL para backups externos
5. **Interface:** Campo de upload de arquivo substituído por campo de URL
6. **Listagem:** Mostra botão "Abrir backup" para backups externos, "Download" para backups antigos

**Compatibilidade:**
- Backups antigos com `stored_path` e arquivo físico continuam funcionando normalmente
- Botão "Download" permanece disponível para backups antigos
- Todos os registros existentes são preservados

**Benefícios:**
- Não há mais problemas com upload de arquivos grandes (erros de chunk, arquivo 0 bytes)
- Não ocupa espaço na hospedagem compartilhada
- Links externos são mais confiáveis para backups grandes
- Pixel Hub funciona como "painel de controle" centralizado

### Auto-detecção de Tipo de Backup (25/01/2025)

O sistema detecta automaticamente o tipo de backup pela extensão do arquivo (para backups antigos):

- **.wpress** → `all_in_one_wp` (WordPress - All-in-One WP Migration)
- **.zip** → `site_zip` (Site completo)
- **.sql** → `database_sql` (Banco de dados)
- **.gz, .tgz, .tar, .bz2** → `compressed_archive` (Arquivo compactado)
- **Outros** → `other_code` (Arquivo de código/backup)

**Extensões permitidas (apenas para referência, não mais usadas para upload):** .wpress, .zip, .sql, .gz, .tgz, .tar, .bz2, .rar, .7z

A exibição do tipo na tabela foi atualizada para mostrar textos amigáveis, incluindo "Backup externo (link)" e "Google Drive (link)" para novos backups.

