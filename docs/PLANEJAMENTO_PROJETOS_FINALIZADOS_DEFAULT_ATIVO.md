# Planejamento: Tratativa de Projetos Finalizados na Tela Projetos & Tarefas

**Objetivo:** Alinhar a tela `/projects` com padrões de sistemas profissionais, ocultando projetos arquivados da vista principal por padrão.

**Status:** Planejamento e investigação — **não implementado**.

---

## 1. Pesquisa: Padrões em Sistemas Profissionais

### 1.1 Resumo por ferramenta

| Sistema | Padrão | Detalhe |
|---------|--------|---------|
| **Basecamp** | Arquivados fora da vista principal | Projetos arquivados não aparecem na lista principal. Acesso via link/seção dedicada no Dashboard. |
| **Trello** | Arquivados em seção separada | Cards/listas arquivados saem do board. Acesso via menu "More" → "Archived items". Não carregam na abertura do board (melhora performance). |
| **Asana** | Filtro "Incomplete" como default | Filtro para ocultar tarefas concluídas; opção "Save layout as default" para persistir. |
| **Adobe Workfront** | Filtro customizado default | Filtros padrão mostram só projetos em "planning or current status". Concluídos fora da vista principal. |
| **Microsoft Project** | Filtro por progresso | Filtro para exibir só "No Start" e "In Progress", ocultando concluídos. |

### 1.2 Padrão dominante

- **Vista principal:** exibe apenas projetos/tarefas ativos ou em andamento.
- **Arquivados/concluídos:** em área separada ou acessíveis por filtro explícito.
- **Default:** "Ativo" ou equivalente, não "Todos".

---

## 2. Estado Atual do PixelHub

### 2.1 Único ponto com status variável

A **única** tela que usa status variável (null = todos) é:

- **Rota:** `GET /projects`
- **Controller:** `ProjectController@index`
- **Arquivo:** `src/Controllers/ProjectController.php` (linhas 40-45)

```php
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
// ...
$projects = ProjectService::getAllProjects($tenantId, $status, $type);
```

Quando `status` é `null`, `ProjectService::getAllProjects` retorna **todos** os projetos (ativos + arquivados).

### 2.2 Demais usos de `getAllProjects` — sempre `'ativo'`

| Local | Chamada | Status |
|-------|---------|--------|
| `TenantsController` (view do cliente) | `getAllProjects($tenantId, 'ativo', 'cliente')` | Fixo ativo |
| `TaskBoardController` (quadro Kanban) | `getAllProjects($effectiveTenantId, 'ativo', $type)` | Fixo ativo |
| `TaskBoardController` (modal criação) | `getAllProjects(null, 'ativo', null)` | Fixo ativo |
| `TicketController` | `getAllProjects(..., 'ativo')` | Fixo ativo |
| `AgendaController` | `getAllProjects(null, 'ativo')` | Fixo ativo |
| `views/tasks/board.php` (JS) | `getAllProjects(null, 'ativo', null)` | Fixo ativo |

**Conclusão:** Só a tela `/projects` permite ver arquivados; o restante já trabalha apenas com projetos ativos.

### 2.3 Fluxo de filtros na view

- **Arquivo:** `views/projects/index.php`
- **Select Status (linhas 244-249):** opções `Todos` (value=""), `Ativo` (value="ativo"), `Arquivado` (value="arquivado")
- **JS `applyFilters()` (linhas 616-624):** monta URL com `status` só se preenchido; value vazio não envia parâmetro
- **Comportamento:** sem `status` na URL → controller usa `null` → lista todos

### 2.4 Links para `/projects`

| Origem | URL | Observação |
|--------|-----|------------|
| Menu lateral | `/projects` | Sem parâmetros |
| `tenants/view.php` | `/projects?type=cliente&tenant_id=X` | Sem status |
| `projects/show.php` (Voltar) | `/projects` ou `/projects?type=interno` | Sem status |
| Redirects após create/update/archive | `/projects?success=...` | Sem status |

Nenhum link passa `status` explicitamente.

---

## 3. Abordagem Recomendada (mais profissional)

### 3.1 Opção escolhida: **Filtro padrão "Ativo"**

- **Implementação:** quando `status` não vier na URL, tratar como `'ativo'`.
- **Vantagens:**
  - Alinhado a Basecamp, Trello, Asana, Workfront.
  - Mudança pequena e localizada.
  - Não exige nova tabela, preferências ou persistência.
  - Mantém filtro "Arquivado" e "Todos" para quem precisar.

### 3.2 Opções descartadas (para esta fase)

| Opção | Motivo |
|-------|--------|
| Seção separada colapsável | Mais complexa; filtro padrão já resolve o principal. |
| Link "Ver X arquivados" | Exige contagem e layout extra; filtro atual já cobre o caso. |
| Persistência de preferência | Exige `user_preferences` e mais lógica; ganho marginal no MVP. |

---

## 4. Plano de Implementação (quando for executar)

### 4.1 Alterações necessárias

| Arquivo | Alteração |
|---------|-----------|
| `src/Controllers/ProjectController.php` | Linha 41: trocar `null` por `'ativo'` quando `$_GET['status']` estiver vazio. |
| `views/projects/index.php` | Linhas 246-248: marcar `Ativo` como `selected` quando `$selectedStatus === 'ativo'` **ou** `$selectedStatus === null`. |

### 4.2 Detalhes técnicos

**ProjectController.php (linha 41):**

```php
// ANTES:
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

// DEPOIS:
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : 'ativo';
```

**views/projects/index.php (linhas 246-248):**

```php
// Garantir que "Ativo" apareça selecionado quando status for null ou 'ativo'
<option value="">Todos</option>
<option value="ativo" <?= (($selectedStatus ?? 'ativo') === 'ativo') ? 'selected' : '' ?>>Ativo</option>
<option value="arquivado" <?= ($selectedStatus === 'arquivado') ? 'selected' : '' ?>>Arquivado</option>
```

Ou, de forma mais explícita, manter a lógica no controller e na view usar `$selectedStatus` já com valor `'ativo'` quando for o default.

### 4.3 Ordem das opções no select (opcional)

- **Atual:** Todos | Ativo | Arquivado
- **Sugestão:** Ativo | Arquivado | Todos — priorizando o mais usado.
- **Decisão:** pode ficar como está; o importante é o valor default.

---

## 5. Análise de Riscos e Duplicidades

### 5.1 Onde NÃO alterar

- `ProjectService::getAllProjects` — assinatura e lógica permanecem iguais.
- `TenantsController`, `TaskBoardController`, `TicketController`, `AgendaController` — já usam `'ativo'`.
- `views/projects/_project_actions.php` — sem alteração.
- Rotas e redirects — continuam sem passar `status`; o default no controller resolve.

### 5.2 Pontos de atenção

| Cenário | Comportamento esperado |
|---------|------------------------|
| Usuário arquiva projeto | Redirect para `/projects?success=archived` → lista só ativos → projeto some da lista (comportamento desejado). |
| Usuário quer ver arquivados | Seleciona "Arquivado" ou "Todos" no filtro. |
| Link de tenant "Ver projetos" | `/projects?type=cliente&tenant_id=X` → default ativo → mostra só projetos ativos do cliente. |
| Projeto arquivado: botão "Voltar" | Vai para `/projects` → lista ativos; projeto não aparece. Usuário pode usar filtro "Arquivado" se quiser. |

### 5.3 Compatibilidade com Painel de Comunicação

O Painel de Comunicação (`/communication-hub`) já usa filtro de status (Ativas, Arquivadas, Ignoradas, Todas). O padrão lá pode ser diferente; não há dependência direta com `/projects`. A mudança em projetos não afeta o Painel.

---

## 6. Critério de Aceite

- [ ] Ao acessar `/projects` sem parâmetro `status`, a lista exibe apenas projetos ativos.
- [ ] O select de Status mostra "Ativo" selecionado nesse caso.
- [ ] Ao escolher "Arquivado", a lista exibe apenas projetos arquivados.
- [ ] Ao escolher "Todos", a lista exibe ativos e arquivados.
- [ ] Após arquivar um projeto, o redirect mostra a lista de ativos sem o projeto arquivado.
- [ ] Links existentes (menu, tenant, show) continuam funcionando como esperado.

---

## 7. Referências

- Basecamp: [Archiving & Deleting](https://classic.basecamp-help.com/article/545-archiving-deleting)
- Trello: [View archived cards](https://support.atlassian.com/trello/docs/archiving-and-deleting-cards)
- Asana: [Hide Completed Tasks](https://forum.asana.com/t/hide-completed-tasks/155821)
- Adobe Workfront: [Filtered Project List](https://experienceleaguecommunities.adobe.com/t5/workfront-questions/filtered-project-list/td-p/556633)
