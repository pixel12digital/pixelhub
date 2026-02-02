# Investigação: Tela de Detalhes do Projeto

**Objetivo:** Analisar a tela `/projects/show` e sugerir otimizações com base no que temos e no comportamento de sistemas profissionais. **Apenas investigação — sem implementações.**

**Rota:** `GET /projects/show?id=X`  
**Arquivo:** `views/projects/show.php`

---

## 1. Pontos levantados pelo usuário

| Ponto | Situação atual | Sugestão |
|-------|----------------|----------|
| **Ícones coloridos** | Emojis (📊, 📝, 🔗, 📋, 📂, ✓, ↩, 🔐, 💡) e badges coloridos (azul, verde, cinza) | Tornar monocromáticos; ícones em cinza neutro |
| **Botões chamativos** | Azul, verde, cinza sólidos — competem por atenção | Estilo outline/ghost; cores neutras; hierarquia visual mais suave |
| **Slug** | Exibido em "Informações Básicas" | Remover — pouco valor para o usuário final |
| **Dica abaixo** | Caixa amarela com dica sobre credenciais | Remover — informação redundante (link já existe nos botões) |
| **Tarefas atrasadas** | Não exibidas | Avaliar exibir resumo ou lista de tarefas em atraso |

---

## 2. O que temos hoje

### 2.1 Estrutura da tela

- **Breadcrumb:** Projetos & Tarefas / Nome do projeto
- **Header:** Título, badges (Interno/Cliente, Ativo/Arquivado), link "Acessar Projeto", botão Voltar
- **Informações Básicas:** Slug, Prioridade, Prazo, Criado em, Cliente (se houver)
- **Descrição / Notas Técnicas:** Texto em bloco (quando existe)
- **Ações:** + Nova tarefa, Ver Quadro Kanban, Ver Todos os Projetos, Concluir e Arquivar / Desarquivar, Ver Credenciais
- **Dica:** Caixa amarela sobre credenciais e "Minha Infraestrutura"

### 2.2 Elementos visuais

- Bordas azuis nas seções (`border-left: 4px solid #023A8D`)
- Títulos em azul (`#023A8D`)
- Badges: Interno (cinza), Cliente (azul), Ativo (verde), Arquivado (cinza)
- Botões: verde (+ Nova tarefa), azul (Quadro), cinza (outros), verde (Credenciais)

---

## 3. Tarefas atrasadas — o que sugerir?

### 3.1 Padrões de mercado

| Sistema | O que mostram na tela do projeto |
|---------|----------------------------------|
| **Asana** | Lista de tarefas do projeto; filtros (atrasadas, em andamento, concluídas); indicador de atraso nos cards |
| **Jira** | Backlog/sprint; issues com status; filtro "Overdue" |
| **Trello** | Cards nas listas; cards atrasados com badge de data vencida |
| **Monday.com** | Itens do board; coluna de data com indicador visual de atraso |
| **ClickUp** | Lista de tarefas; filtro "Overdue"; badge vermelho em itens atrasados |

**Padrão:** A tela de detalhes do projeto costuma mostrar as tarefas vinculadas, com destaque para as atrasadas.

### 3.2 O que o PixelHub já tem

- **TaskService::getAllTasks()** — retorna tarefas por projeto
- **TaskService::getProjectSummary()** — contagem por status (backlog, em_andamento, aguardando_cliente, concluida)
- Campo `due_date` em `tasks`; lógica de atraso já usada no quadro Kanban (`_task_card.php`)

### 3.3 Sugestões para tarefas atrasadas

1. **Resumo em cards:** Bloco "Tarefas do Projeto" com contadores: Total, Em andamento, **Atrasadas (X)** — link para o quadro filtrado.
2. **Lista compacta:** Seção "Tarefas em atraso" com até 5–10 itens (título, prazo, link para o quadro).
3. **Só indicador:** Badge ou número "X atrasadas" ao lado de "Ver Quadro Kanban", sem lista.

**Recomendação:** Opção 1 ou 3 — baixo esforço, alto valor. A opção 2 exige mais layout e manutenção.

---

## 4. Sugestões gerais de otimização (sem implementar)

### 4.1 Visual — ícones e cores

| Alteração | Motivo |
|-----------|--------|
| Remover emojis; usar ícones SVG ou texto | Emojis variam por sistema; SVG monocromático é mais consistente |
| Títulos de seção em cinza (#4b5563) em vez de azul | Reduz ruído visual; azul só para links e ações principais |
| Bordas das seções em cinza (#9ca3af) | Alinhamento com estilo monocromático |

### 4.2 Botões — mais discretos

| Alteração | Motivo |
|-----------|--------|
| Estilo outline (borda + fundo transparente) | Menos competição visual; padrão em interfaces limpas |
| Uma ação primária (ex.: Ver Quadro) em destaque leve | Hierarquia clara |
| Demais ações em cinza neutro | Conteúdo do projeto em foco |

### 4.3 Conteúdo

| Alteração | Motivo |
|-----------|--------|
| Remover Slug | Campo técnico; pouco uso pelo usuário |
| Remover Dica amarela | Redundante; credenciais já têm botão dedicado |

### 4.4 Tarefas do projeto

| Alteração | Motivo |
|-----------|--------|
| Adicionar resumo de tarefas (total, em andamento, atrasadas) | Contexto rápido; alinhado a Asana, Jira |
| Link "Ver X tarefas atrasadas" quando houver | Ação direta para correção |

### 4.5 Ordem sugerida de implementação

1. Remover Slug e Dica (rápido, baixo risco)
2. Ajustar botões para estilo mais discreto
3. Ícones monocromáticos
4. Resumo de tarefas (incluindo atrasadas)

---

## 5. Referências no código

| Componente | Arquivo |
|------------|---------|
| View do projeto | `views/projects/show.php` |
| TaskService | `src/Services/TaskService.php` — `getAllTasks()`, `getProjectSummary()` |
| ProjectController | `src/Controllers/ProjectController.php` — `show()` |
| Task card (indicador atraso) | `views/tasks/_task_card.php` |

---

## 6. Resumo

| Categoria | Ação sugerida |
|-----------|----------------|
| **Remover** | Slug, Dica amarela |
| **Ajustar** | Botões mais discretos (outline/ghost) |
| **Visual** | Ícones monocromáticos; títulos e bordas em tons neutros |
| **Adicionar** | Resumo de tarefas (total, em andamento, atrasadas) com link para o quadro |

**Próximo passo:** Validar com o usuário e priorizar; depois implementar em etapas.

---

## 7. Implementações sugeridas em detalhes

### 7.1 Remoções (baixo esforço)

#### 7.1.1 Remover Slug
- **Arquivo:** `views/projects/show.php`
- **Ação:** Excluir o bloco inteiro:
  ```php
  <div class="info-item">
      <strong>Slug</strong>
      <span><?= htmlspecialchars($project['slug'] ?? '-') ?></span>
  </div>
  ```
- **Impacto:** Nenhum; slug continua no banco para uso técnico (URLs, APIs).

#### 7.1.2 Remover Dica amarela
- **Arquivo:** `views/projects/show.php`
- **Ação:** Excluir o bloco:
  ```php
  <!-- Aviso sobre Credenciais -->
  <div style="background: #fff3cd; ...">
      <strong>💡 Dica:</strong>
      <p>...</p>
  </div>
  ```
- **Impacto:** Nenhum; o botão "Ver Credenciais" já leva ao destino.

---

### 7.2 Ícones monocromáticos

#### 7.2.1 Substituir emojis por texto ou SVG
- **Onde:** Títulos de seção (📊, 📝), botões (📋, 📂, ✓, ↩, 🔐), link (🔗)
- **Opção A — Só texto:** Remover emojis e manter apenas o texto (ex.: "Informações Básicas", "Ver Quadro Kanban").
- **Opção B — SVG inline:** Usar ícones SVG com `fill="currentColor"` e `color: #6b7280` no pai, por exemplo:
  ```html
  <h3 style="color: #4b5563; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
      </svg>
      Informações Básicas
  </h3>
  ```
- **Recomendação:** Opção A (mais simples); Opção B se quiser manter ícones visuais.

#### 7.2.2 Badges monocromáticos
- **Atual:** Interno (cinza), Cliente (azul), Ativo (verde), Arquivado (cinza)
- **Sugestão:** Manter semântica, mas com tons mais neutros:
  - Interno: `#6b7280` (cinza)
  - Cliente: `#4b5563` (cinza escuro) — ou manter azul suave `#3b82f6` se quiser diferenciar
  - Ativo: `#059669` (verde mais suave) ou `#6b7280` (neutro)
  - Arquivado: `#9ca3af` (cinza claro)
- **CSS:** Ajustar `.badge-interno`, `.badge-cliente`, `.badge-ativo`, `.badge-arquivado` no `<style>` da view.

---

### 7.3 Botões mais discretos

#### 7.3.1 Estilo outline/ghost
- **Padrão:** Borda + fundo transparente; hover com fundo leve
- **CSS sugerido:**
  ```css
  .action-buttons a,
  .action-buttons button {
      padding: 8px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      border: 1px solid #d1d5db;
      background: #f9fafb;
      color: #4b5563;
      text-decoration: none;
  }
  .action-buttons a:hover,
  .action-buttons button:hover {
      background: #f3f4f6;
      border-color: #9ca3af;
  }
  ```
- **Ação primária (Ver Quadro / + Nova tarefa):** Borda azul suave, texto azul:
  ```css
  .action-buttons .btn-primary {
      border-color: #3b82f6;
      color: #2563eb;
      background: transparent;
  }
  .action-buttons .btn-primary:hover {
      background: #eff6ff;
  }
  ```

#### 7.3.2 Hierarquia
- **Primária:** "Ver Quadro Kanban" e "+ Nova tarefa" — borda azul
- **Secundárias:** "Ver Todos os Projetos", "Concluir e Arquivar", "Ver Credenciais" — cinza outline
- **Voltar:** Manter discreto (já está em cinza)

---

### 7.4 Seções — cores neutras

#### 7.4.1 Bordas e títulos
- **Atual:** `border-left: 4px solid #023A8D`, `color: #023A8D` nos h3
- **Sugestão:**
  - Bordas: `#9ca3af` ou `#d1d5db`
  - Títulos: `#4b5563` ou `#374151`

---

### 7.5 Resumo de tarefas (incluindo atrasadas)

#### 7.5.1 Backend — contagem de atrasadas
- **Arquivo:** `src/Services/TaskService.php`
- **Novo método (ou extensão de getProjectSummary):**
  ```php
  public static function getProjectSummaryWithOverdue(int $projectId): array
  {
      $summary = self::getProjectSummary($projectId);
      $db = DB::getConnection();
      $stmt = $db->prepare("
          SELECT COUNT(*) as overdue
          FROM tasks
          WHERE project_id = ? AND deleted_at IS NULL
            AND status != 'concluida'
            AND due_date IS NOT NULL
            AND due_date < DATE('now', 'localtime')
      ");
      $stmt->execute([$projectId]);
      $summary['overdue'] = (int) ($stmt->fetch()['overdue'] ?? 0);
      return $summary;
  }
  ```
- **Alternativa:** Incluir `overdue` diretamente em `getProjectSummary()` com um `SUM(CASE WHEN ...)` na query existente.

#### 7.5.2 Controller
- **Arquivo:** `src/Controllers/ProjectController.php`
- **No método `show()`:** Chamar o resumo com atrasadas e passar para a view:
  ```php
  $taskSummary = TaskService::getProjectSummaryWithOverdue($id);
  $this->view('projects.show', [
      'project' => $project,
      'taskSummary' => $taskSummary,
  ]);
  ```

#### 7.5.3 View — bloco "Tarefas do Projeto"
- **Posição:** Entre "Descrição" e "Ações Rápidas" (ou antes das ações)
- **Layout:** Grid de cards (como no Quadro Kanban):
  ```
  ┌─────────────────────────────────────────────────────────────┐
  │ Tarefas do Projeto                                          │
  ├─────────────┬─────────────┬─────────────┬───────────────────┤
  │ Total       │ Em andamento│ Atrasadas   │ Concluídas        │
  │ 42          │ 5           │ 2           │ 35                │
  └─────────────┴─────────────┴─────────────┴───────────────────┘
  [Ver Quadro Kanban]  [Ver 2 tarefas atrasadas] (se overdue > 0)
  ```
- **HTML/CSS:** Reutilizar o padrão do bloco "Resumo do Projeto" do `board.php` (linhas 848–884).
- **Link "Ver X atrasadas":** `href="/projects/board?project_id=X&filter=overdue"` — exige filtro no quadro ou, no início, só link para o quadro com projeto já filtrado (o usuário filtra manualmente). Alternativa simples: sempre link para o quadro filtrado por projeto.

---

### 7.6 Ordem de implementação sugerida

| # | Implementação | Esforço | Arquivos |
|---|---------------|---------|----------|
| 1 | Remover Slug | 5 min | show.php |
| 2 | Remover Dica | 5 min | show.php |
| 3 | Botões outline | 15 min | show.php (CSS + classes) |
| 4 | Títulos/bordas neutras | 10 min | show.php (CSS) |
| 5 | Remover emojis (só texto) | 10 min | show.php |
| 6 | Badges monocromáticos | 10 min | show.php (CSS) |
| 7 | Resumo de tarefas + atrasadas | 45 min | TaskService, ProjectController, show.php |

**Total estimado:** ~1h30.

---

### 7.7 Referências de sistemas profissionais

| Sistema | Padrão na tela de projeto |
|---------|---------------------------|
| **Asana** | Títulos em cinza; botões outline; resumo de tarefas com filtros; sem slug |
| **Jira** | Layout limpo; ícones monocromáticos; backlog com contadores |
| **Linear** | Estilo minimalista; poucas cores; hierarquia clara |
| **Notion** | Conteúdo em destaque; ações em menu ou botões discretos |
