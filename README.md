# Pixel Hub - Painel Central da Pixel12 Digital

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Requisitos](#requisitos)
4. [Instalação](#instalação)
5. [Estrutura do Projeto](#estrutura-do-projeto)
6. [Core Classes](#core-classes)
7. [Controllers e Rotas](#controllers-e-rotas)
8. [Services](#services)
9. [Banco de Dados](#banco-de-dados)
10. [Views e Templates](#views-e-templates)
11. [Integrações Externas](#integrações-externas)
12. [Segurança](#segurança)
13. [Fluxos Principais](#fluxos-principais)
14. [Desenvolvimento](#desenvolvimento)

---

## 🎯 Visão Geral

O **Pixel Hub** é um painel administrativo centralizado desenvolvido em PHP puro (sem frameworks) para gerenciar:

- **Clientes (Tenants)**: Cadastro completo com dados de cobrança
- **Hospedagem**: Contas de hospedagem, planos e backups
- **Financeiro**: Integração com Asaas para cobranças e faturas
- **Cobranças via WhatsApp**: Sistema automatizado de envio
- **Projetos & Tarefas**: Sistema Kanban para gestão de projetos internos e de clientes
- **Infraestrutura**: Acessos e links de servidores/ferramentas

### Tecnologias

- **Backend**: PHP 8.0+ (puro, sem frameworks)
- **Banco de Dados**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Autenticação**: Sessões PHP com hash de senha
- **Padrão**: PSR-4 (autoload), MVC simplificado

---

## 🏗️ Arquitetura

### Padrão Arquitetural

O sistema segue um padrão **MVC simplificado**:

```
Request → Router → Controller → Service → Database
                    ↓
                  View (PHP)
```

### Fluxo de Requisição

1. **Bootstrap** (`public/index.php`):
   - Inicia sessão
   - Carrega autoload (Composer ou manual)
   - Define `BASE_PATH` (suporta subpastas)
   - Carrega variáveis de ambiente (`.env`)
   - Configura timezone e logs
   - Normaliza URI
   - Cria Router e registra rotas
   - Despacha requisição

2. **Router** (`src/Core/Router.php`):
   - Match de rotas (GET/POST)
   - Suporte a parâmetros dinâmicos `{id}`
   - Executa middlewares (se configurados)
   - Resolve handler (Controller@method ou Closure)

3. **Controller** (`src/Controllers/*`):
   - Valida autenticação/autorização
   - Processa dados da requisição
   - Chama Services para lógica de negócio
   - Renderiza View ou retorna JSON

4. **Service** (`src/Services/*`):
   - Lógica de negócio isolada
   - Acesso ao banco de dados
   - Validações e transformações
   - Métodos estáticos (stateless)

5. **View** (`views/*`):
   - Templates PHP com output buffering
   - Layouts reutilizáveis
   - Helpers globais (`pixelhub_url()`)

---

## 📦 Requisitos

- **PHP**: >= 8.0
- **MySQL/MariaDB**: >= 5.7 ou >= 10.2
- **Extensões PHP**:
  - PDO
  - PDO_MySQL
  - OpenSSL (para criptografia)
  - Session
  - JSON
- **Servidor Web**: Apache/Nginx (configurado para `public/` como document root)
- **Composer** (opcional, para autoload PSR-4)

---

## 🚀 Instalação

### 1. Clone/Download do Projeto

```bash
cd C:\xampp\htdocs\painel.pixel12digital
```

### 2. Configuração do Ambiente

Crie o arquivo `.env` na raiz do projeto:

```env
# Banco de Dados
DB_HOST=localhost
DB_NAME=paine.pixel12digital
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# Aplicação
APP_DEBUG=true
APP_ENV=local
TIMEZONE=America/Sao_Paulo

# Credenciais Admin Padrão (ALTERE EM PRODUÇÃO!)
ADMIN_EMAIL=admin@pixel12.test
ADMIN_PASSWORD=[ALTERE_ESTA_SENHA_EM_PRODUCAO]

# Asaas (API de Pagamentos)
ASAAS_API_KEY=sua_chave_api
ASAAS_API_URL=https://www.asaas.com/api/v3
ASAAS_WEBHOOK_TOKEN=seu_token_webhook

# WhatsApp (Opcional)
WHATSAPP_API_URL=
WHATSAPP_API_KEY=

# Infraestrutura (Opcional)
INFRA_VIEW_PIN=1234
```

### 3. Instalar Dependências (Opcional)

```bash
composer install
```

> **Nota**: O sistema funciona sem Composer usando autoload manual.

### 4. Executar Migrations

```bash
php database/migrate.php
```

Este comando:
- Cria a tabela `migrations` (controle de versão)
- Executa todas as migrations em ordem cronológica
- Registra migrations executadas

### 5. Executar Seed Inicial

```bash
php database/seed.php
```

Cria:
- Usuário admin padrão
- Tenant de exemplo (opcional)

### 6. Configurar Servidor Web

#### Apache

Configure o VirtualHost apontando para `public/`:

```apache
<VirtualHost *:80>
    ServerName painel.pixel12digital.local
    DocumentRoot "C:/xampp/htdocs/painel.pixel12digital/public"
    
    <Directory "C:/xampp/htdocs/painel.pixel12digital/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name painel.pixel12digital.local;
    root /path/to/painel.pixel12digital/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### 7. Acessar o Sistema

```
http://localhost/painel.pixel12digital/public/
```

**Credenciais padrão (desenvolvimento):**
- Email: `admin@pixel12.test`
- Senha: Configure no arquivo `.env` (padrão de desenvolvimento: `123456` - **ALTERE EM PRODUÇÃO!**)

---

## 📁 Estrutura do Projeto

```
painel.pixel12digital/
├── config/                    # Configurações
│   ├── asaas.php             # Configuração da API Asaas
│   └── database.php          # Configuração do banco de dados
│
├── database/                  # Migrations e Seeds
│   ├── migrations/           # Migrations do banco (ordem cronológica)
│   │   ├── 20251117_*.php   # Migrations iniciais
│   │   ├── 20251123_*.php   # Migrations de Projetos & Tarefas
│   │   └── ...
│   ├── seeds/                # Seeds de dados iniciais
│   │   └── SeedInitialData.php
│   ├── migrate.php          # Script de execução de migrations
│   ├── seed.php             # Script de execução de seeds
│   └── check-tables.php     # Script de verificação de tabelas
│
├── docs/                     # Documentação adicional
│   ├── CORRECAO_LINKS_FORMULARIOS.md
│   ├── INSTRUCOES_BACKUPS.md
│   ├── MAPEAMENTO_COBRANCAS_E_PROPOSAL.md
│   └── ...
│
├── logs/                     # Logs da aplicação
│   └── pixelhub.log
│
├── public/                   # Ponto de entrada (Document Root)
│   ├── index.php            # Bootstrap principal
│   └── debug-logs.php       # Debug de logs
│
├── src/                      # Código fonte (PSR-4)
│   ├── Core/                 # Classes core do sistema
│   │   ├── Auth.php         # Autenticação e autorização
│   │   ├── Controller.php   # Classe base para controllers
│   │   ├── CryptoHelper.php # Criptografia (AES-256)
│   │   ├── DB.php           # Conexão PDO (singleton)
│   │   ├── Env.php          # Carregamento de .env
│   │   ├── MoneyHelper.php  # Formatação de valores monetários
│   │   ├── Router.php       # Sistema de rotas
│   │   └── Storage.php      # Gerenciamento de arquivos
│   │
│   ├── Controllers/         # Controllers (MVC)
│   │   ├── AsaasWebhookController.php
│   │   ├── AuthController.php
│   │   ├── BillingCollectionsController.php
│   │   ├── DashboardController.php
│   │   ├── HostingBackupController.php
│   │   ├── HostingController.php
│   │   ├── HostingPlanController.php
│   │   ├── OwnerShortcutsController.php
│   │   ├── ProjectController.php
│   │   ├── TaskBoardController.php
│   │   ├── TaskChecklistController.php
│   │   └── TenantsController.php
│   │
│   ├── Services/            # Services (lógica de negócio)
│   │   ├── AsaasBillingService.php
│   │   ├── AsaasClient.php
│   │   ├── AsaasConfig.php
│   │   ├── AsaasPlanMapper.php
│   │   ├── OwnerShortcutsService.php
│   │   ├── ProjectService.php
│   │   ├── TaskChecklistService.php
│   │   ├── TaskService.php
│   │   └── WhatsAppBillingService.php
│   │
│   └── Models/              # Models (vazio - acesso direto via Services)
│
├── storage/                  # Armazenamento de arquivos
│   └── tenants/             # Backups e arquivos por tenant
│
├── views/                    # Templates PHP
│   ├── auth/
│   │   └── login.php
│   ├── billing_collections/
│   │   ├── index.php
│   │   ├── overview.php
│   │   └── whatsapp_modal.php
│   ├── dashboard/
│   │   └── index.php
│   ├── hosting/
│   │   ├── backups.php
│   │   ├── form.php
│   │   └── index.php
│   ├── hosting_plans/
│   │   ├── form.php
│   │   └── index.php
│   ├── layout/
│   │   ├── auth.php         # Layout de autenticação
│   │   └── main.php         # Layout principal (master)
│   ├── owner_shortcuts/
│   │   └── index.php
│   ├── projects/
│   │   └── index.php        # Lista de projetos
│   ├── tasks/
│   │   ├── _task_card.php   # Partial: card de tarefa
│   │   └── board.php        # Quadro Kanban
│   └── tenants/
│       ├── form.php
│       ├── index.php
│       └── view.php
│
├── .env                      # Variáveis de ambiente (não versionado)
├── .env.example             # Exemplo de .env
├── composer.json            # Dependências Composer
├── ENV_CREDENTIALS.md       # Documentação de credenciais
└── README.md                # Este arquivo
```

---

## 🔧 Core Classes

### `PixelHub\Core\Router`

Sistema de roteamento simples e eficiente.

**Métodos principais:**
- `get(string $path, $handler)` - Registra rota GET
- `post(string $path, $handler)` - Registra rota POST
- `dispatch(string $method, string $path)` - Despacha requisição
- `resolve()` - Resolve rota atual (legado)

**Suporte:**
- Parâmetros dinâmicos: `/tasks/{id}`
- Wildcards: `/admin/*`
- Handlers: `Controller@method` ou `Closure`

**Exemplo:**
```php
$router->get('/tasks/{id}', 'TaskBoardController@show');
// Resolve: /tasks/123 → TaskBoardController::show()
```

### `PixelHub\Core\Auth`

Gerenciamento de autenticação e autorização.

**Métodos principais:**
- `login(string $email, string $password): ?array` - Autentica usuário
- `logout(): void` - Encerra sessão
- `user(): ?array` - Retorna usuário logado
- `check(): bool` - Verifica se está autenticado
- `isInternal(): bool` - Verifica se é usuário interno
- `requireAuth(): void` - Exige autenticação (redireciona se não)
- `requireInternal(): void` - Exige usuário interno (403 se não)

**Armazenamento:** Sessão PHP (`$_SESSION['pixelhub_user']`)

### `PixelHub\Core\DB`

Conexão PDO singleton com MySQL.

**Métodos principais:**
- `getConnection(): PDO` - Retorna conexão única
- `closeConnection(): void` - Fecha conexão (útil para testes)

**Configuração:** Via `config/database.php` e variáveis `.env`

### `PixelHub\Core\Env`

Carregamento de variáveis de ambiente do arquivo `.env`.

**Métodos principais:**
- `load(): void` - Carrega `.env`
- `get(string $key, $default = null)` - Obtém variável
- `isDebug(): bool` - Verifica se está em modo debug

### `PixelHub\Core\Controller`

Classe base abstrata para todos os controllers.

**Métodos principais:**
- `view(string $view, array $data = [])` - Renderiza view PHP
- `json(array $data, int $statusCode = 200)` - Retorna JSON
- `redirect(string $path)` - Redireciona (usa `pixelhub_url()`)

**Exemplo:**
```php
class MyController extends Controller {
    public function index() {
        $this->view('my.index', ['data' => $items]);
    }
}
```

### `PixelHub\Core\CryptoHelper`

Criptografia AES-256 para dados sensíveis (senhas de acesso).

**Métodos principais:**
- `encrypt(string $data): string` - Criptografa
- `decrypt(string $encrypted): string` - Descriptografa

**Uso:** Senhas de acessos em `owner_shortcuts`

### `PixelHub\Core\MoneyHelper`

Formatação de valores monetários (BRL).

**Métodos principais:**
- `format(float $value): string` - Formata como "R$ 1.234,56"
- `parse(string $value): float` - Converte string para float

### `PixelHub\Core\Storage`

Gerenciamento de uploads e armazenamento de arquivos.

**Métodos principais:**
- `store(string $path, $content)` - Armazena arquivo
- `get(string $path)` - Obtém arquivo
- `delete(string $path)` - Remove arquivo

---

## 🎮 Controllers e Rotas

### Rotas Públicas

| Método | Rota | Controller | Método | Descrição |
|--------|------|------------|--------|-----------|
| GET | `/` | Closure | - | Redireciona para `/dashboard` ou `/login` |
| GET | `/login` | AuthController | loginForm | Exibe formulário de login |
| POST | `/login` | AuthController | login | Processa login |
| GET | `/logout` | AuthController | logout | Encerra sessão |

### Rotas Protegidas (Requer Autenticação)

#### Dashboard
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/dashboard` | DashboardController | index |

#### Clientes (Tenants)
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/tenants` | TenantsController | index |
| GET | `/tenants/create` | TenantsController | create |
| POST | `/tenants/store` | TenantsController | store |
| GET | `/tenants/edit` | TenantsController | edit |
| POST | `/tenants/update` | TenantsController | update |
| POST | `/tenants/delete` | TenantsController | delete |
| GET | `/tenants/view` | TenantsController | show |
| POST | `/tenants/sync-billing` | TenantsController | syncBilling |

#### Hospedagem
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/hosting` | HostingController | index |
| GET | `/hosting/create` | HostingController | create |
| POST | `/hosting/store` | HostingController | store |
| GET | `/hosting/backups` | HostingBackupController | index |
| POST | `/hosting/backups/upload` | HostingBackupController | upload |
| GET | `/hosting/backups/download` | HostingBackupController | download |

#### Planos de Hospedagem
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/hosting-plans` | HostingPlanController | index |
| GET | `/hosting-plans/create` | HostingPlanController | create |
| POST | `/hosting-plans/store` | HostingPlanController | store |
| GET | `/hosting-plans/edit` | HostingPlanController | edit |
| POST | `/hosting-plans/update` | HostingPlanController | update |
| POST | `/hosting-plans/toggle-status` | HostingPlanController | toggleStatus |

#### Financeiro / Cobranças
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/billing/overview` | BillingCollectionsController | overview |
| GET | `/billing/collections` | BillingCollectionsController | index |
| GET | `/billing/whatsapp-modal` | BillingCollectionsController | showWhatsAppModal |
| POST | `/billing/whatsapp-sent` | BillingCollectionsController | markWhatsAppSent |
| GET | `/billing/tenant-reminder` | BillingCollectionsController | getTenantReminderData |
| POST | `/billing/tenant-reminder-sent` | BillingCollectionsController | markTenantReminderSent |
| POST | `/billing/sync-all-from-asaas` | BillingCollectionsController | syncAllFromAsaas |

#### Projetos & Tarefas (Apenas Internos)
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/projects` | ProjectController | index |
| POST | `/projects/store` | ProjectController | store |
| POST | `/projects/update` | ProjectController | update |
| POST | `/projects/archive` | ProjectController | archive |
| GET | `/projects/board` | TaskBoardController | board |
| POST | `/tasks/store` | TaskBoardController | store |
| POST | `/tasks/update` | TaskBoardController | update |
| POST | `/tasks/move` | TaskBoardController | move |
| GET | `/tasks/{id}` | TaskBoardController | show |
| POST | `/tasks/checklist/add` | TaskChecklistController | add |
| POST | `/tasks/checklist/toggle` | TaskChecklistController | toggle |
| POST | `/tasks/checklist/update` | TaskChecklistController | update |
| POST | `/tasks/checklist/delete` | TaskChecklistController | delete |

#### Infraestrutura (Apenas Internos)
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| GET | `/owner/shortcuts` | OwnerShortcutsController | index |
| POST | `/owner/shortcuts/store` | OwnerShortcutsController | store |
| POST | `/owner/shortcuts/update` | OwnerShortcutsController | update |
| POST | `/owner/shortcuts/delete` | OwnerShortcutsController | delete |
| GET/POST | `/owner/shortcuts/password` | OwnerShortcutsController | getPassword |

#### Webhooks
| Método | Rota | Controller | Método |
|--------|------|------------|--------|
| POST | `/webhook/asaas` | AsaasWebhookController | handle |

---

## 🛠️ Services

Services contêm a lógica de negócio isolada. Todos os métodos são **estáticos** (stateless).

### `ProjectService`

Gerencia projetos (Projetos & Tarefas).

**Métodos:**
- `getAllProjects(?int $tenantId, ?string $status, ?string $type, ?int $customerVisible): array`
- `findProject(int $id): ?array`
- `createProject(array $data): int`
- `updateProject(int $id, array $data): bool`
- `archiveProject(int $id): bool`
- `getProjectOptionsForSelect(): array`

**Campos do projeto:**
- `name` (VARCHAR 150)
- `tenant_id` (FK opcional)
- `description` (TEXT)
- `status` ('ativo' | 'arquivado')
- `priority` ('baixa' | 'media' | 'alta' | 'critica')
- `type` ('interno' | 'cliente')
- `is_customer_visible` (TINYINT 0/1)
- `template` (VARCHAR 50, ex: 'migracao_wp')
- `due_date` (DATE)

### `TaskService`

Gerencia tarefas do Kanban.

**Métodos:**
- `getAllTasks(?int $projectId, ?int $tenantId): array` - Retorna agrupado por status
- `getTasksByProject(int $projectId): array` - Tarefas de um projeto
- `createTask(array $data): int` - Cria tarefa (aplica template se houver)
- `updateTask(int $id, array $data): bool`
- `moveTask(int $id, string $newStatus, ?int $newOrder): bool` - Move entre colunas
- `findTask(int $id): ?array`
- `getProjectSummary(int $projectId): array` - Resumo de contagens por status

**Status de tarefas:**
- `backlog`
- `em_andamento`
- `aguardando_cliente`
- `concluida`

**Template automático:**
Se `projects.template = 'migracao_wp'`, ao criar tarefa, cria checklist com 8 itens padrão.

### `TaskChecklistService`

Gerencia checklist de tarefas.

**Métodos:**
- `getItemsByTask(int $taskId): array`
- `addItem(int $taskId, string $label): int`
- `toggleItem(int $id, bool $done): bool`
- `updateLabel(int $id, string $label): bool`
- `deleteItem(int $id): bool`

### `AsaasBillingService`

Integração com API Asaas (pagamentos).

**Métodos:**
- `syncCustomerFromAsaas(string $asaasCustomerId): array`
- `syncInvoicesFromAsaas(int $tenantId): array`
- `createInvoice(array $data): array`
- `sendWhatsAppReminder(int $invoiceId): bool`

### `AsaasClient`

Cliente HTTP para API Asaas.

**Métodos:**
- `get(string $endpoint): array`
- `post(string $endpoint, array $data): array`
- `put(string $endpoint, array $data): array`
- `delete(string $endpoint): bool`
- `findCustomersByCpfCnpj(string $cpfCnpj): array`

### `WhatsAppBillingService`

Envio de mensagens WhatsApp para cobranças.

**Métodos:**
- `sendInvoiceReminder(int $invoiceId): bool`
- `sendTenantReminder(int $tenantId): bool`

### `OwnerShortcutsService`

Gerencia acessos e links de infraestrutura.

**Métodos:**
- `getAll(): array`
- `findById(int $id): ?array`
- `create(array $data): int`
- `update(int $id, array $data): bool`
- `delete(int $id): bool`
- `getDecryptedPassword(int $id): string` - Requer PIN
- `getCategoryLabels(): array`

**Categorias:**
- hospedagem, vps, afiliados, dominios, banco, ferramenta, outros

---

## 🗄️ Banco de Dados

### Sistema de Migrations

**Execução:**
```bash
php database/migrate.php
```

**Funcionamento:**
1. Cria tabela `migrations` (controle de versão)
2. Lista arquivos em `database/migrations/`
3. Ordena por nome (cronológico)
4. Executa apenas migrations não registradas
5. Registra execução na tabela `migrations`

**Nomenclatura:**
```
YYYYMMDD_nome_da_migration.php
```

**Estrutura da classe:**
```php
class CreateTableName {
    public function up(PDO $db): void {
        // Cria/altera tabela
    }
    
    public function down(PDO $db): void {
        // Reverte alteração
    }
}
```

### Tabelas Principais

#### `users`
Usuários do sistema (admin e internos).

**Campos:**
- `id` (PK)
- `name`, `email`
- `password_hash` (bcrypt)
- `is_internal` (TINYINT) - 1 = usuário Pixel12, 0 = cliente
- `created_at`, `updated_at`

#### `tenants`
Clientes da agência.

**Campos principais:**
- `id` (PK)
- `name`, `email`, `phone`
- `cpf_cnpj`, `document`
- `person_type` ('fisica' | 'juridica')
- `asaas_customer_id` (UNIQUE) - ID no Asaas
- `status` ('active' | 'inactive')
- `internal_notes` (TEXT)
- Campos de cobrança (endereço, etc.)

#### `projects`
Projetos (internos e de clientes).

**Campos:**
- `id` (PK)
- `tenant_id` (FK, nullable) - NULL = projeto interno
- `name` (VARCHAR 150)
- `description` (TEXT)
- `status` ('ativo' | 'arquivado')
- `priority` ('baixa' | 'media' | 'alta' | 'critica')
- `type` ('interno' | 'cliente')
- `is_customer_visible` (TINYINT) - 0 = só interno, 1 = pode aparecer para cliente
- `template` (VARCHAR 50, nullable) - Ex: 'migracao_wp'
- `due_date` (DATE)
- `created_by`, `updated_by` (FK users)
- `created_at`, `updated_at`

**Índices:**
- `idx_tenant_id`
- `idx_status`
- `idx_type` (se necessário)

#### `tasks`
Tarefas do Kanban.

**Campos:**
- `id` (PK)
- `project_id` (FK, NOT NULL)
- `title` (VARCHAR 200)
- `description` (TEXT)
- `status` ('backlog' | 'em_andamento' | 'aguardando_cliente' | 'concluida')
- `order` (INT) - Ordem dentro da coluna
- `assignee` (VARCHAR 150) - Nome/email do responsável
- `due_date` (DATE)
- `created_by` (FK users)
- `created_at`, `updated_at`

**Índices:**
- `idx_project_id`
- `idx_status_project_order` (status, project_id, order)

#### `task_checklists`
Checklist de tarefas.

**Campos:**
- `id` (PK)
- `task_id` (FK, NOT NULL)
- `label` (VARCHAR 255)
- `is_done` (TINYINT 0/1)
- `order` (INT)
- `created_at`, `updated_at`

**Índices:**
- `idx_task_id`
- `idx_task_order` (task_id, order)

#### `hosting_accounts`
Contas de hospedagem.

**Campos:**
- `id` (PK)
- `tenant_id` (FK)
- `domain`
- `hosting_plan_id` (FK)
- `backup_status` ('completo' | 'pendente' | 'erro')
- `last_backup_at` (DATETIME)
- Campos de acesso (cPanel, FTP, etc.)

#### `hosting_plans`
Planos de hospedagem.

**Campos:**
- `id` (PK)
- `name`
- `amount` (DECIMAL)
- `billing_cycle` ('monthly' | 'annual')
- `is_active` (TINYINT)

#### `billing_invoices`
Faturas/cobranças (sincronizadas do Asaas).

**Campos:**
- `id` (PK)
- `tenant_id` (FK)
- `asaas_invoice_id` (UNIQUE)
- `amount` (DECIMAL)
- `due_date` (DATE)
- `status` ('pending' | 'paid' | 'overdue' | 'cancelled')
- `whatsapp_sent_at` (DATETIME)
- `is_deleted` (TINYINT)

#### `owner_shortcuts`
Acessos e links de infraestrutura.

**Campos:**
- `id` (PK)
- `category` (VARCHAR 50)
- `label` (VARCHAR 150)
- `url` (VARCHAR 255)
- `username` (VARCHAR 150)
- `password_encrypted` (TEXT) - AES-256
- `notes` (TEXT)
- `is_favorite` (TINYINT)
- `created_at`, `updated_at`

#### `asaas_webhook_logs`
Logs de webhooks do Asaas.

**Campos:**
- `id` (PK)
- `event_type` (VARCHAR 50)
- `payload` (TEXT JSON)
- `processed` (TINYINT)
- `created_at`

### Relacionamentos

```
users (1) ──→ (N) projects.created_by
users (1) ──→ (N) projects.updated_by
users (1) ──→ (N) tasks.created_by

tenants (1) ──→ (N) projects
tenants (1) ──→ (N) hosting_accounts
tenants (1) ──→ (N) billing_invoices

projects (1) ──→ (N) tasks
tasks (1) ──→ (N) task_checklists

hosting_plans (1) ──→ (N) hosting_accounts
```

---

## 🎨 Views e Templates

### Sistema de Views

**Renderização:**
- Controllers usam `$this->view('nome.view', $data)`
- Views são arquivos PHP em `views/`
- Notação com ponto: `projects.index` → `views/projects/index.php`
- Output buffering para capturar conteúdo

### Layouts

#### `views/layout/main.php`
Layout principal (master) com:
- Header (azul #023A8D)
- Sidebar (menu lateral)
- Content area
- Paleta de cores: Azul #023A8D, Laranja #F7931E

#### `views/layout/auth.php`
Layout de autenticação (login).

### Partials

Partials são incluídos via `include`:
- `views/tasks/_task_card.php` - Card de tarefa no Kanban

### Helpers Globais

**`pixelhub_url(string $path): string`**
Gera URL absoluta considerando `BASE_PATH`.

```php
pixelhub_url('/dashboard') // → /painel.pixel12digital/public/dashboard
```

---

## 🔌 Integrações Externas

### Asaas (API de Pagamentos)

**Configuração:**
- API Key via `.env` (`ASAAS_API_KEY`)
- URL base: `https://www.asaas.com/api/v3`
- Webhook token para validação

**Endpoints utilizados:**
- `GET /customers` - Lista clientes
- `POST /customers` - Cria cliente
- `GET /payments` - Lista pagamentos
- `POST /payments` - Cria pagamento
- `GET /subscriptions` - Lista assinaturas

**Webhook:**
- Rota: `POST /webhook/asaas`
- Eventos: `PAYMENT_CREATED`, `PAYMENT_UPDATED`, `PAYMENT_CONFIRMED`
- Logs em `asaas_webhook_logs`

**Service:** `AsaasClient`, `AsaasBillingService`

### WhatsApp (Opcional)

**Configuração:**
- URL e API Key via `.env`
- Service: `WhatsAppBillingService`

**Uso:**
- Envio de lembretes de cobrança
- Notificações de faturas vencidas

---

## 🔒 Segurança

### Autenticação

- **Método:** Sessões PHP
- **Hash de senha:** `password_hash()` (bcrypt)
- **Verificação:** `password_verify()`
- **Sessão:** Armazenada em `$_SESSION['pixelhub_user']`
- **Timeout:** Gerenciado pelo PHP (configurável)

### Autorização

**Níveis:**
1. **Público:** `/login`, `/logout`
2. **Autenticado:** Todas as rotas exceto login
3. **Interno:** Rotas que exigem `Auth::requireInternal()`

**Verificação:**
```php
Auth::requireAuth();        // Exige login
Auth::requireInternal();    // Exige usuário interno (is_internal = 1)
```

### Criptografia

**Dados sensíveis:**
- Senhas de acesso (`owner_shortcuts.password_encrypted`)
- Método: AES-256 via `CryptoHelper`
- PIN de visualização: `INFRA_VIEW_PIN` (opcional)

### Validação

- **Input:** Validação em Services antes de inserir/atualizar
- **SQL Injection:** Protegido via PDO Prepared Statements
- **XSS:** `htmlspecialchars()` em todas as saídas
- **CSRF:** Não implementado (considerar para produção)

### Logs

- **Aplicação:** `logs/pixelhub.log`
- **PHP Errors:** `error_log()` (configurável)
- **Debug:** Ativado via `APP_DEBUG=true` no `.env`

---

## 🔄 Fluxos Principais

### 1. Fluxo de Autenticação

```
1. Usuário acessa /login
2. AuthController::loginForm() → views/auth/login.php
3. Usuário submete formulário → POST /login
4. AuthController::login() valida credenciais
5. Auth::login() verifica no banco
6. Se válido: salva em sessão, redireciona para /dashboard
7. Se inválido: retorna erro
```

### 2. Fluxo de Cobrança (Asaas)

```
1. Webhook Asaas → POST /webhook/asaas
2. AsaasWebhookController::handle() valida token
3. Processa evento (PAYMENT_CREATED, etc.)
4. Atualiza billing_invoices
5. Log em asaas_webhook_logs
```

### 3. Fluxo de Projeto & Tarefa

```
1. Criar Projeto:
   - POST /projects/store
   - ProjectController::store()
   - ProjectService::createProject()
   - Redireciona para /projects

2. Criar Tarefa:
   - POST /tasks/store
   - TaskBoardController::store()
   - TaskService::createTask()
   - Se projeto.template = 'migracao_wp': cria checklist automático
   - Retorna JSON

3. Mover Tarefa (Kanban):
   - POST /tasks/move
   - TaskBoardController::move()
   - TaskService::moveTask() - reajusta ordens
   - Retorna JSON
```

### 4. Fluxo de Checklist

```
1. Adicionar Item:
   - POST /tasks/checklist/add
   - TaskChecklistController::add()
   - TaskChecklistService::addItem()
   - Retorna JSON

2. Marcar/Desmarcar:
   - POST /tasks/checklist/toggle
   - TaskChecklistController::toggle()
   - TaskChecklistService::toggleItem()
   - Retorna JSON
```

---

## 💻 Desenvolvimento

### Comandos Úteis

```bash
# Executar migrations
php database/migrate.php

# Verificar tabelas
php database/check-tables.php

# Executar seed
php database/seed.php

# Ver logs
tail -f logs/pixelhub.log
```

### Debug

**Ativar modo debug:**
```env
APP_DEBUG=true
```

**Logs:**
- Aplicação: `logs/pixelhub.log`
- PHP: `error_log()` (configurável)

**Debug de rotas:**
Logs automáticos em `public/index.php` quando `APP_DEBUG=true`

### Adicionar Nova Rota

1. Editar `public/index.php`
2. Adicionar: `$router->get('/nova-rota', 'Controller@method');`
3. Criar método no Controller
4. Criar view (se necessário)

### Adicionar Nova Migration

1. Criar arquivo: `database/migrations/YYYYMMDD_nome.php`
2. Implementar classe com métodos `up()` e `down()`
3. Executar: `php database/migrate.php`

### Padrões de Código

- **PSR-4:** Namespace `PixelHub\`
- **Nomenclatura:**
  - Classes: PascalCase
  - Métodos: camelCase
  - Arquivos: PascalCase para classes
- **Services:** Métodos estáticos
- **Controllers:** Herdam de `Controller`
- **Views:** PHP puro com output buffering

---

## 📊 Estatísticas do Sistema

- **Controllers:** 12
- **Services:** 9
- **Core Classes:** 8
- **Migrations:** 25+
- **Tabelas:** 15+
- **Rotas:** 50+

---

## 🎨 Paleta de Cores

- **Azul Principal:** `#023A8D` (azul marinho)
- **Laranja Secundário:** `#F7931E` (laranja vibrante)
- **Uso:** Azul para elementos estruturais, laranja para destaques e ações

---

## 📝 Notas Importantes

1. **BASE_PATH:** Sistema detecta automaticamente se está em subpasta
2. **Autoload:** Funciona com ou sem Composer
3. **Sessões:** Requer `session_start()` (feito no bootstrap)
4. **Timezone:** Configurado para `America/Sao_Paulo`
5. **Charset:** UTF-8 (utf8mb4 no banco)
6. **Senhas:** Bcrypt com `password_hash()`
7. **Criptografia:** AES-256 para dados sensíveis

---

## 🔗 Links Úteis

- **Documentação Asaas:** https://painel.radioweb.app.br/docs/api/ (conforme memória)
- **Logs:** `logs/pixelhub.log`
- **Configurações:** `.env` e `config/`

---

## 📞 Suporte

Para dúvidas ou problemas, consulte:
- Logs em `logs/pixelhub.log`
- Documentação em `docs/`
- Código-fonte com comentários inline

---

**Última atualização:** Novembro 2025  
**Versão:** 1.0.0
