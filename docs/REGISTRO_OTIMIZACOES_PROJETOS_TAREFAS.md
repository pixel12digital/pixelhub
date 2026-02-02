# Registro de Otimizações — Projetos & Tarefas

**Período:** Nov/2025 – Fev/2026  
**Escopo:** Lista de projetos, detalhes do projeto, Quadro Kanban

---

## Lista de Projetos (`/projects`)

- Filtro padrão **Ativo** (projetos arquivados ocultos)
- Alinhamento dos filtros corrigido
- Link "Ver projetos arquivados" quando a lista está vazia
- Botão **Arquivar** em cinza (antes vermelho)

---

## Detalhes do Projeto (`/projects/show`)

- **Removido:** Slug, dica amarela
- **Visual:** Ícones monocromáticos, badges neutros, bordas/títulos em cinza
- **Botões:** Estilo outline, mais discretos
- **Novo:** Resumo de tarefas (Total, Em andamento, Atrasadas, Concluídas)
- **Novo:** Link "Ver X tarefas atrasadas" quando houver
- **Novo:** Botão "+ Nova tarefa" (abre quadro com modal)
- **Descrição:** Colapsada por padrão (clique para expandir)
- **Botões da seção Tarefas:** Padding e cantos arredondados padronizados

---

## Quadro Kanban (`/projects/board`)

- **Nova tarefa a partir do projeto:** Botão na tela de detalhes
- **Contadores** nas colunas (ex.: Backlog (18))
- **Indicador de atraso:** Borda vermelha e fundo rosado em tarefas vencidas
- **Arquivar projeto** no quadro (quando filtrado por projeto)
- **Atalhos:** N (nova tarefa), Esc (fechar), Ctrl+Enter (salvar)
- **Dropdown:** Opção "+ Criar novo projeto"
- **Breadcrumb:** Projetos > Projeto > Quadro
- **Link:** Relatório de Produtividade no header

---

## Backend

- `TaskService::getProjectSummary()` — inclui contagem de tarefas atrasadas
- `ProjectController::archive()` — suporte a `redirect_to` customizado
