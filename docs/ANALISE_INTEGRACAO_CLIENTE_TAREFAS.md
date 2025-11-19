# Análise de Integração: Quadro Kanban e Tela de Detalhes do Cliente

**Data:** 2025-01-XX  
**Objetivo:** Investigar como o Quadro Kanban de Projetos & Tarefas e a tela de Detalhes do Cliente se relacionam atualmente, para planejar a integração futura de uma aba de tarefas no painel do cliente.

---

## 1. Arquivos e Caminhos Envolvidos

### 1.1. Quadro Kanban de Projetos & Tarefas

#### Controller
- **Arquivo:** `src/Controllers/TaskBoardController.php`
- **Método principal:** `board()`
- **Rota:** `GET /projects/board` (definida em `public/index.php`, linha 241)
- **Parâmetros de URL aceitos:**
  - `project_id` (opcional): Filtra por projeto específico
  - `tenant_id` (opcional): Filtra por cliente/tenant
  - `type` (opcional): Filtra por tipo ('interno' ou 'cliente')
  - `client_query` (opcional): Busca textual no nome do cliente

#### View
- **Arquivo:** `views/tasks/board.php`
- **Componente auxiliar:** `views/tasks/_task_card.php` (card individual de tarefa)
- **JavaScript:** Inline na view (funções `applyFilters()`, `openTaskDetail()`, etc.)

#### Services Utilizados
- **TaskService** (`src/Services/TaskService.php`):
  - `getAllTasks($projectId, $tenantId, $clientQuery)`: Método principal que busca tarefas com filtros
  - `getProjectSummary($projectId)`: Retorna resumo de tarefas por status para um projeto
- **ProjectService** (`src/Services/ProjectService.php`):
  - `getAllProjects($tenantId, $status, $type)`: Lista projetos com filtros opcionais

### 1.2. Tela de Detalhes do Cliente (Tenant)

#### Controller
- **Arquivo:** `src/Controllers/TenantsController.php`
- **Método principal:** `show()`
- **Rota:** `GET /tenants/view` (definida em `public/index.php`, linha 173)
- **Parâmetros de URL:**
  - `id` (obrigatório): ID do tenant/cliente
  - `tab` (opcional): Aba ativa ('overview', 'hosting', 'docs_backups', 'financial')

#### View
- **Arquivo:** `views/tenants/view.php`
- **Abas existentes:**
  1. Visão Geral (`overview`)
  2. Hospedagem & Sites (`hosting`)
  3. Docs & Backups (`docs_backups`)
  4. Financeiro (`financial`)

#### Dados Carregados
O controller `TenantsController::show()` atualmente carrega:
- Dados do tenant (nome, CPF/CNPJ, email, telefone, etc.)
- Contas de hospedagem (`hosting_accounts`)
- Backups (`hosting_backups`)
- Faturas (`billing_invoices`)
- Notificações WhatsApp (`billing_notifications`)
- **NÃO carrega tarefas ou projetos relacionados ao tenant**

---

## 2. Estrutura de Dados e Relações de Banco

### 2.1. Tabelas Principais

#### `tenants` (Clientes)
- **ID:** `id` (INT UNSIGNED, PRIMARY KEY)
- **Campos relevantes:** `id`, `name`, `cpf_cnpj`, `email`, `phone`, `status`
- **Migration:** `database/migrations/20251117_create_tenants_table.php`

#### `projects` (Projetos)
- **ID:** `id` (INT UNSIGNED, PRIMARY KEY)
- **Relação com tenant:** `tenant_id` (INT UNSIGNED, NULL permitido, FOREIGN KEY para `tenants.id`)
- **Campos relevantes:** `id`, `tenant_id`, `name`, `status`, `type` ('interno' ou 'cliente')
- **Migrations:**
  - `database/migrations/20251117_create_projects_table.php` (criação)
  - `database/migrations/20251123_alter_projects_add_type_and_visibility.php` (adiciona `type`)

#### `tasks` (Tarefas)
- **ID:** `id` (INT UNSIGNED, PRIMARY KEY)
- **Relação com projeto:** `project_id` (INT UNSIGNED, NOT NULL, FOREIGN KEY para `projects.id`)
- **Relação com tenant:** **INDIRETA** via `projects.tenant_id`
- **Campos relevantes:** `id`, `project_id`, `title`, `description`, `status`, `assignee`, `due_date`, `task_type`
- **Migration:** `database/migrations/20251123_create_tasks_table.php`

### 2.2. Relações entre Tabelas

```
tenants (1) ──< (N) projects (1) ──< (N) tasks
```

**Fluxo de relacionamento:**
1. **Tenant → Projects:** Um tenant pode ter múltiplos projetos (`projects.tenant_id`)
2. **Project → Tasks:** Um projeto pode ter múltiplas tarefas (`tasks.project_id`)
3. **Tenant → Tasks:** Relação indireta via `projects.tenant_id`

**IMPORTANTE:**
- Tarefas **NÃO** têm referência direta a `tenant_id`
- Para buscar tarefas de um cliente, é necessário fazer JOIN: `tasks → projects → tenants`
- Projetos podem ser "internos" (`tenant_id = NULL` ou `type = 'interno'`)

### 2.3. Query Principal do Kanban

A query usada em `TaskService::getAllTasks()` é:

```sql
SELECT t.*, 
       p.name as project_name,
       p.tenant_id as project_tenant_id,
       t2.name as tenant_name,
       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done
FROM tasks t
INNER JOIN projects p ON t.project_id = p.id
LEFT JOIN tenants t2 ON p.tenant_id = t2.id
WHERE 1=1
  [AND t.project_id = ?]  -- Se projectId fornecido
  [AND p.tenant_id = ?]    -- Se tenantId fornecido
  [AND t2.name LIKE ?]    -- Se clientQuery fornecido
ORDER BY t.status ASC, t.`order` ASC, t.created_at ASC
```

**Observações:**
- Usa `INNER JOIN` com `projects` (tarefa sempre tem projeto)
- Usa `LEFT JOIN` com `tenants` (projeto pode ser interno, sem tenant)
- Filtro por tenant usa `p.tenant_id` (campo do projeto, não da tarefa)

---

## 3. Funcionamento dos Filtros no Kanban

### 3.1. Filtro "Cliente" (Dropdown)

#### Origem dos Dados
- **Fonte:** Query direta no controller `TaskBoardController::board()`:
  ```php
  $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
  $tenants = $stmt->fetchAll();
  ```
- **Localização:** Linha 49 de `src/Controllers/TaskBoardController.php`
- **Lista exibida:** Todos os tenants cadastrados, ordenados por nome

#### Aplicação do Filtro
- **Método:** Via requisição ao backend (não é filtro JavaScript)
- **Parâmetro URL:** `tenant_id` (ex: `/projects/board?tenant_id=5`)
- **Processamento:** 
  - Controller recebe `$_GET['tenant_id']`
  - Passa para `TaskService::getAllTasks(null, $tenantId, null)`
  - Service aplica filtro na query SQL: `AND p.tenant_id = ?`
- **Campo usado:** `projects.tenant_id` (não `tasks.tenant_id`, pois tarefas não têm referência direta)

#### JavaScript
- **Função:** `applyFilters()` (linha 553 de `views/tasks/board.php`)
- **Comportamento:** Ao mudar o select, recarrega a página com novo parâmetro na URL
- **Código:**
  ```javascript
  function applyFilters() {
      const tenantId = document.getElementById('filter_tenant').value;
      const params = new URLSearchParams();
      if (tenantId) params.append('tenant_id', tenantId);
      window.location.href = '/projects/board?' + params.toString();
  }
  ```

### 3.2. Filtro "Pesquisar por cliente"

#### Campo de Entrada
- **Elemento:** Input de texto (`id="filter_client_query"`)
- **Placeholder:** "Digite parte do nome do cliente..."
- **Localização:** Linha 354-359 de `views/tasks/board.php`

#### Aplicação
- **Método:** Via requisição ao backend (não é filtro JavaScript)
- **Parâmetro URL:** `client_query` (ex: `/projects/board?client_query=Silva`)
- **Processamento:**
  - Controller recebe `$_GET['client_query']`
  - Passa para `TaskService::getAllTasks(null, null, $clientQuery)`
  - Service aplica filtro na query SQL: `AND t2.name LIKE ?` (busca no nome do tenant)
- **Campo usado:** `tenants.name` (via JOIN)

#### JavaScript
- **Função:** `applyFilters()` (mesma função do filtro de cliente)
- **Comportamento:** Ao mudar o input (evento `onchange`), recarrega a página
- **Busca:** Case-insensitive, usando `LIKE '%termo%'`

### 3.3. Filtro "Projeto"

- **Origem:** `ProjectService::getAllProjects($tenantId, 'ativo', $type)`
- **Aplicação:** Via parâmetro `project_id` na URL
- **Campo usado:** `tasks.project_id`

### 3.4. Filtro "Tipo"

- **Valores:** 'interno' ou 'cliente'
- **Aplicação:** Via parâmetro `type` na URL
- **Campo usado:** `projects.type`

---

## 4. Comparação de IDs: Kanban vs Tela do Cliente

### 4.1. ID Usado no Kanban

- **Fonte:** Parâmetro `tenant_id` na URL (`$_GET['tenant_id']`)
- **Tipo:** `tenants.id` (INT UNSIGNED)
- **Uso:** Filtro na query: `AND p.tenant_id = ?`
- **Exemplo:** `/projects/board?tenant_id=5`

### 4.2. ID Usado na Tela do Cliente

- **Fonte:** Parâmetro `id` na URL (`$_GET['id']`)
- **Tipo:** `tenants.id` (INT UNSIGNED)
- **Uso:** Busca direta: `SELECT * FROM tenants WHERE id = ?`
- **Exemplo:** `/tenants/view?id=5`

### 4.3. Conclusão

✅ **Os IDs são COMPATÍVEIS:**
- Ambos usam `tenants.id`
- O mesmo ID pode ser usado em ambas as telas
- Exemplo: Se `tenant_id=5` no Kanban filtra tarefas do cliente ID 5, então `/tenants/view?id=5` mostra os detalhes do mesmo cliente

**Compatibilidade confirmada:** O filtro de cliente no Kanban e a tela de detalhes do cliente usam o mesmo identificador (`tenants.id`).

---

## 5. Relacionamento Atual: Tela do Cliente com Tarefas/Projetos

### 5.1. Verificação no Controller

Analisando `TenantsController::show()` (linhas 19-147), **NÃO existe** nenhuma consulta relacionada a:
- Tabela `projects`
- Tabela `tasks`
- Service `TaskService`
- Service `ProjectService`

### 5.2. Dados Carregados Atualmente

O controller carrega apenas:
- Dados do tenant
- `hosting_accounts` (WHERE `tenant_id = ?`)
- `hosting_backups` (via JOIN com `hosting_accounts`)
- `billing_invoices` (WHERE `tenant_id = ?`)
- `billing_notifications` (WHERE `tenant_id = ?`)

### 5.3. Conclusão

❌ **Não existe relacionamento atual entre a tela do cliente e tarefas/projetos.**

A tela de detalhes do cliente não exibe ou consulta informações sobre:
- Projetos do cliente
- Tarefas dos projetos do cliente
- Resumo de atividades/tarefas

---

## 6. Possibilidades de Reaproveitamento

### 6.1. Services Disponíveis

#### TaskService
- ✅ `getAllTasks($projectId, $tenantId, $clientQuery)`: **JÁ SUPORTA filtro por `tenantId`**
- ✅ Retorna tarefas agrupadas por status (formato ideal para Kanban ou lista)
- ✅ Inclui JOINs necessários (tasks → projects → tenants)
- ✅ Retorna dados formatados: `project_name`, `tenant_name`, `checklist_total`, `checklist_done`

#### ProjectService
- ✅ `getAllProjects($tenantId, $status, $type)`: **JÁ SUPORTA filtro por `tenantId`**
- ✅ Retorna projetos com `tenant_name` já incluído

### 6.2. Viabilidade de Reaproveitamento

✅ **É TOTALMENTE VIÁVEL reaproveitar os services existentes.**

**Motivos:**
1. `TaskService::getAllTasks()` já aceita `$tenantId` como parâmetro
2. A query já faz os JOINs necessários (tasks → projects → tenants)
3. O formato de retorno (agrupado por status) pode ser usado tanto no Kanban quanto em uma lista na aba do cliente
4. Não é necessário criar novas queries ou services

### 6.3. Abordagens Possíveis

#### Abordagem A: Endpoint Dedicado (Mais Complexa)

**Criar rota/endpoint específico:**
- Rota: `GET /tenants/{id}/tasks` ou `GET /clients/{id}/tasks`
- Controller: Novo método em `TenantsController` ou `TaskBoardController`
- Retorno: JSON com tarefas do cliente

**Vantagens:**
- Separação clara de responsabilidades
- Pode ser consumido via AJAX (carregamento assíncrono)
- Reutilizável por outras partes do sistema

**Desvantagens:**
- Requer criação de nova rota
- Requer endpoint JSON adicional
- Mais código para manter

#### Abordagem B: Reaproveitamento Direto do Service (Mais Simples) ⭐ **RECOMENDADA**

**Carregar diretamente no controller existente:**
- Modificar `TenantsController::show()` para carregar tarefas quando `$activeTab === 'tasks'`
- Usar `TaskService::getAllTasks(null, $tenantId, null)` diretamente
- Passar dados para a view como já é feito com outros dados

**Vantagens:**
- ✅ **Mais simples:** Apenas adicionar algumas linhas no controller existente
- ✅ **Sem novas rotas:** Usa a mesma estrutura já estabelecida
- ✅ **Consistência:** Segue o mesmo padrão das outras abas (hospedagem, financeiro)
- ✅ **Reaproveitamento total:** Usa exatamente o mesmo service e query do Kanban
- ✅ **Manutenibilidade:** Alterações no `TaskService` beneficiam ambas as telas

**Desvantagens:**
- Carregamento síncrono (mas aceitável, pois é apenas uma aba)
- Dados carregados mesmo se a aba não for visualizada (mas pode ser otimizado com lazy loading se necessário)

### 6.4. Recomendação Técnica

⭐ **Recomendação: Abordagem B (Reaproveitamento Direto)**

**Justificativa:**
1. **Simplicidade:** Menos código, menos pontos de falha
2. **Consistência:** Segue o padrão arquitetural já estabelecido (todas as abas carregam dados no controller)
3. **Manutenibilidade:** Uma única fonte de verdade (`TaskService::getAllTasks()`)
4. **Performance:** Query já otimizada, com índices apropriados
5. **Flexibilidade:** Se no futuro precisar de endpoint JSON, pode criar sem quebrar a implementação atual

**Implementação sugerida:**
```php
// Em TenantsController::show(), após carregar outros dados:

// Busca tarefas do tenant (apenas se necessário para a aba de tarefas)
$tasks = [];
if ($activeTab === 'tasks') {
    $tasks = TaskService::getAllTasks(null, $tenantId, null);
}

// Passa para a view
$this->view('tenants.view', [
    'tenant' => $tenant,
    'tasks' => $tasks,  // Novo
    // ... outros dados
]);
```

**Na view (`views/tenants/view.php`):**
- Adicionar nova aba "Tarefas/Atividades" (`tab=tasks`)
- Renderizar tarefas agrupadas por status (similar ao Kanban, mas em formato de lista)
- Opcional: Link para o Kanban completo com filtro pré-aplicado

---

## 7. Resumo Executivo

### 7.1. Arquivos Envolvidos

| Componente | Arquivo | Responsabilidade |
|------------|---------|------------------|
| **Kanban Controller** | `src/Controllers/TaskBoardController.php` | Gerencia quadro Kanban |
| **Kanban View** | `views/tasks/board.php` | Renderiza Kanban com filtros |
| **Task Service** | `src/Services/TaskService.php` | Lógica de negócio de tarefas |
| **Project Service** | `src/Services/ProjectService.php` | Lógica de negócio de projetos |
| **Tenant Controller** | `src/Controllers/TenantsController.php` | Gerencia detalhes do cliente |
| **Tenant View** | `views/tenants/view.php` | Renderiza painel do cliente |

### 7.2. Estrutura de Dados

```
tenants (id)
    └── projects (tenant_id) → id
            └── tasks (project_id)
```

**Relação:** `tasks` → `projects` → `tenants` (indireta)

### 7.3. Filtros do Kanban

| Filtro | Campo Usado | Aplicação |
|--------|-------------|-----------|
| Cliente (dropdown) | `projects.tenant_id` | Backend (query SQL) |
| Pesquisar por cliente | `tenants.name` (LIKE) | Backend (query SQL) |
| Projeto | `tasks.project_id` | Backend (query SQL) |
| Tipo | `projects.type` | Backend (query SQL) |

### 7.4. Compatibilidade de IDs

✅ **Compatível:** Ambos usam `tenants.id`

### 7.5. Relacionamento Atual

❌ **Não existe:** Tela do cliente não carrega tarefas/projetos

### 7.6. Recomendação

⭐ **Abordagem B:** Reaproveitar `TaskService::getAllTasks()` diretamente no `TenantsController::show()`

**Complexidade:** Baixa  
**Esforço:** Mínimo (apenas adicionar código, sem criar novas rotas)  
**Manutenibilidade:** Alta (reaproveitamento total)

---

## 8. Próximos Passos (Sugestão)

1. ✅ **Análise concluída** (este documento)
2. ⏳ Adicionar aba "Tarefas/Atividades" na view `tenants/view.php`
3. ⏳ Modificar `TenantsController::show()` para carregar tarefas quando `tab=tasks`
4. ⏳ Renderizar tarefas em formato de lista (similar ao Kanban, mas adaptado)
5. ⏳ Adicionar link para Kanban completo com filtro pré-aplicado
6. ⏳ Testar integração

---

**Fim do Relatório**

