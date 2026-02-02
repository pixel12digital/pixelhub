# Levantamento: Lista de Conversas do Painel de Comunicação (para replicar no Inbox)

**Objetivo:** Documentar exatamente a estrutura e o comportamento da lista de conversas do Painel de Comunicação, para que a mesma experiência possa ser replicada no Inbox sem alterar o Painel.

---

## 1. Estrutura geral do Painel

### 1.1 Cabeçalho da página
- Título: **"Painel de Comunicação"**
- Descrição: "Gerencie conversas, envie mensagens e responda clientes em tempo real."
- Link: **"Mostrar estatísticas"**

### 1.2 Filtros (acima da lista)
- **Canal:** dropdown — opções: Todos, WhatsApp, Chat Interno.
- **Sessão (WhatsApp):** dropdown — "Todas as sessões" + lista de sessões (visível só quando Canal = WhatsApp).
- **Cliente:** dropdown pesquisável — "Todos" + lista de clientes (tenants); valor enviado como `tenant_id`.
- **Status:** dropdown — Ativas, Arquivadas, Ignoradas, Todas.
- **Nova Conversa:** botão (ícone +) — abre modal para iniciar nova conversa (`openNewMessageModal()`).

Formulário submete via GET para `/communication-hub` com parâmetros: `channel`, `session_id`, `tenant_id`, `status`.

### 1.3 Área da lista
- **Cabeçalho fixo:** "Conversas" (h3).
- **Container rolável:** `.conversation-list-scroll` — contém duas seções (quando aplicável) e itens.
- **Conversa ativa (destaque):** O item da conversa selecionada recebe a classe **`.active`** (background `#e7f3ff`, borda `#007bff`). A comparação é por `data-thread-id === ConversationState.currentThreadId`. Ao clicar em um item ou ao re-renderizar a lista, o Painel atualiza essa classe nos itens.

---

## 2. Seção: Conversas não vinculadas

### 2.1 Quando aparece
- Quando existem conversas com `tenant_id` nulo (incoming leads) e filtro de status permite (ex.: Ativas).

### 2.2 Cabeçalho da seção
- **Título:** ícone de balão de chat + texto **"Conversas não vinculadas"**.
- **Badge:** número (ex.: 2) — quantidade de conversas não vinculadas.
- **Descrição:** "Conversas ainda não associadas a um cliente. Revise e vincule ou crie um novo."

### 2.3 Cada item (conversa não vinculada)
- **Classe:** `.conversation-item.incoming-lead-item`.
- **Atributos:** `data-thread-id`, `data-conversation-id`.
- **Clique na linha:** abre a conversa (`handleConversationClick(thread_id, channel)`).

**Conteúdo exibido:**
1. **Nome do contato** (linha 1, negrito) — `contact_name` ou "Contato Desconhecido".
2. **Telefone** (linha 2) — ícone de telefone + `contact` (número).
3. **Badge de não lidas** (se `unread_count` > 0) — número em verde.
4. **Três pontos (⋮)** — menu específico de *incoming lead* (ver 2.4).
5. **Botão "Vincular"** — ação principal; chama `openLinkTenantModal(conversation_id, contact_name)` (modal para vincular a cliente existente).
6. **Data/hora** (última atividade) — formato dd/mm HH:mm ou "Agora"; fuso Brasília.

**Menu dos três pontos (não vinculadas):**
- **Criar Cliente** — `openCreateTenantModal(conversation_id, contact_name, contact)`.
- **Ignorar** — `ignoreConversation(conversation_id, contact_name)` (oculto se filtro status = "ignored").
- **Excluir** — `deleteConversation(conversation_id, contact_name)` (estilo `.danger`, vermelho).

Botões ocultos mantidos para compatibilidade: Criar Cliente, Ignorar (chamando `rejectIncomingLead(conversation_id)`). O menu visível usa `ignoreConversation(conversation_id, contact_name)` para "Ignorar"; no contexto de lead também existe `rejectIncomingLead(conversation_id)` — ambas podem ser usadas conforme o fluxo (Painel usa as duas em pontos diferentes).

**Comportamento da seção quando não há mais leads:** A função `updateIncomingLeadsCount()` atualiza o badge e, se a quantidade for 0, oculta a seção inteira (`.unlinked-conversations-section` ou fallback `.incoming-leads-section`). O badge pode ser `.unlinked-conversations-badge` ou `.incoming-leads-badge`.

### 2.4 Separador
- Após a seção não vinculadas: linha/borda (`border-bottom`) e margem antes das conversas normais.

---

## 3. Seção: Conversas (vinculadas / normais)

### 3.1 Quando aparece
- Quando existem conversas com ou sem tenant (threads normais), conforme filtros.

### 3.2 Cada item (conversa normal)
- **Classe:** `.conversation-item` (+ `.conversation-archived` ou `.conversation-ignored` conforme status).
- **Atributos:** `data-thread-id`, `data-conversation-id`.
- **Clique na linha:** abre a conversa (`handleConversationClick(thread_id, channel)`).

**Conteúdo exibido:**
1. **Nome do contato** (linha 1, negrito) — `contact_name` ou `tenant_name` ou "Cliente".
2. **Linha 2 (dados do canal e cliente):**
   - **WhatsApp:** ícone telefone + número (`contact`) + "• " + `channel_id` (ex.: pixel12digital); ou "Chat Interno" se canal interno.
   - **Nome do tenant (cliente):**  
     - Se tem `tenant_id` e `tenant_name`: **link** "• {tenant_name}" → `pixelhub_url('/tenants/view?id=' + tenant_id)`, `onclick="event.stopPropagation();"`, estilo sublinhado pontilhado, cor #023A8D, título "Clique para ver detalhes do cliente".  
     - Se não tem tenant: texto "• Sem tenant" (opacidade menor).
3. **Badge de não lidas** (se `unread_count` > 0 e não é a conversa selecionada) — número em verde.
4. **Três pontos (⋮)** — menu da conversa (ver 3.3).
5. **Data/hora** (última atividade) — alinhada à direita; formato dd/mm HH:mm ou "Agora"; fuso Brasília.

**Menu dos três pontos (vinculadas / por status):**

- **Se status = ativa (ou vazio):**
  - Arquivar — `archiveConversation(conversation_id, contact_name)`.
  - Ignorar — `ignoreConversation(conversation_id, contact_name)`.
- **Se status = arquivada:**
  - Desarquivar — `reactivateConversation(...)`.
  - Ignorar — `ignoreConversation(...)`.
- **Se status = ignorada:**
  - Ativar — `reactivateConversation(...)`.
  - Arquivar — `archiveConversation(...)`.

**Itens comuns a todas as conversas normais:**
- **Editar nome** — `openEditContactNameModal(conversation_id, contact_name)`.
- **Alterar Cliente** — `openChangeTenantModal(conversation_id, contact_name, tenant_id, tenant_name)`.
- **Desvincular** — `unlinkConversation(conversation_id, contact_name)` — **só aparece se `tenant_id` não vazio**.
- **Excluir** — `deleteConversation(conversation_id, contact_name)` (classe `.danger`).

**Aparência por status (conversas normais):**
- **Arquivada:** classe `.conversation-archived` — opacidade 0.7, borda esquerda 3px cor `#f59e0b`. O CSS usa `::after { content: attr(data-status-label); }` (no código atual o HTML não define `data-status-label`, então o label não aparece; o visual é só borda e opacidade).
- **Ignorada:** classe `.conversation-ignored` — opacidade 0.6, borda esquerda 3px cor `#9ca3af`.

**Preview da última mensagem:** No Painel **não** há preview do conteúdo da última mensagem na lista — apenas data/hora da última atividade. Há um comentário TODO no PHP para "Buscar preview da última mensagem". O Inbox hoje exibe `last_message_preview`; na replicação pode-se alinhar (sem preview, como o Painel) ou manter preview (decisão de produto).

---

## 4. Estados vazios

- **Nenhuma conversa e nenhum lead:** mensagem "Nenhuma conversa encontrada" + texto explicativo.

---

## 5. Dados por item (backend / API)

- **Incoming lead (não vinculada):** `thread_id`, `channel`, `conversation_id`, `contact_name`, `contact` (número), `channel_id` (opcional), `unread_count`, `last_activity`.
- **Conversa normal:** `thread_id`, `channel`, `conversation_id`, `contact_name`, `contact`, `channel_id`, `tenant_id`, `tenant_name`, `status` (active | archived | ignored), `unread_count`, `last_activity`, `message_count`, etc.

A lista do Painel é alimentada por: (1) renderização PHP inicial (`$incoming_leads`, `$threads`); (2) atualização via JS com `renderConversationList(threads, incomingLeads, incomingLeadsCount)` usando dados do endpoint **GET `/communication-hub/conversations-list`** com parâmetros: `channel`, `tenant_id`, `status`, `session_id` (opcional). A resposta inclui: `threads`, `incoming_leads`, `incoming_leads_count`.

**Formatação de data na lista:** Função JS **`formatDateBrasilia(dateStr)`** — retorno "Agora" se inválido ou `dateStr === 'now'`; senão formata em fuso `America/Sao_Paulo`, formato dd/mm HH:mm (sem ano). Timestamps da tabela `conversations` são tratados como UTC.

**Escapamento:** O Painel usa **`escapeHtml(text)`** (JS) e **`htmlspecialchars(..., ENT_QUOTES)`** (PHP) em nomes e textos exibidos para evitar XSS. Na replicação no Inbox, aplicar o mesmo padrão.

---

## 6. Funções e modais referenciados (Painel)

| Função / Modal | Uso |
|----------------|-----|
| `handleConversationClick(threadId, channel)` | Abre a conversa no painel direito. |
| `toggleIncomingLeadMenu(btn)` / `closeIncomingLeadMenu(el)` | Abre/fecha menu ⋮ das não vinculadas. |
| `toggleConversationMenu(btn)` / `closeConversationMenu(el)` | Abre/fecha menu ⋮ das vinculadas. |
| `openLinkTenantModal(conversationId, contactName)` | Modal "Vincular a Cliente Existente" (lista de clientes, vincular). |
| `openCreateTenantModal(conversationId, contactName, contactPhone)` | Modal criar novo cliente a partir do lead. |
| `openChangeTenantModal(conversationId, contactName, currentTenantId, currentTenantName)` | Modal alterar cliente vinculado. |
| `openEditContactNameModal(conversationId, currentName)` | Modal editar nome do contato. |
| `unlinkConversation(conversationId, contactName)` | Desvincula conversa (tenant_id = null, is_incoming_lead = 1). |
| `archiveConversation`, `reactivateConversation`, `ignoreConversation` | Mudam status (archived / active / ignored). |
| `deleteConversation(conversationId, contactName)` | Exclui conversa (confirmação antes). |
| `openNewMessageModal()` | Nova conversa. |
| `rejectIncomingLead(conversationId)` | Ignorar lead (usado nos botões ocultos de incoming lead). |
| `updateIncomingLeadsCount()` | Atualiza o badge da seção "Conversas não vinculadas" e oculta a seção se count === 0. |

**Fechamento dos menus:** Listener global de clique fecha todos os dropdowns (`.incoming-lead-menu-dropdown.show`, `.conversation-menu-dropdown.show`) quando o clique **não** é dentro de `.incoming-lead-menu` ou `.conversation-menu`. Ao abrir um menu (toggle), os outros são fechados antes — mesmo comportamento para os dois tipos de menu.

**Modal Nova Mensagem:** Ao clicar em "Nova Conversa" abre o modal `#new-message-modal` com: Canal (WhatsApp / Chat Interno), Sessão WhatsApp (visível só se Canal = WhatsApp, lista de sessões), Cliente (tenant), destinatário (número ou usuário conforme canal). Submit chama `sendNewMessage(event)`. O Inbox pode reutilizar esse modal (se na mesma página) ou abrir o Painel em nova aba/rota para "Nova Conversa".

URLs usadas no Painel (ex.: `pixelhub_url('/tenants/view?id=' + tenant_id)`, endpoints de API como `/communication-hub/conversation/unlink`, `/communication-hub/incoming-lead/link-tenant`, etc.) devem ser as mesmas quando as ações forem replicadas no Inbox (mesmo backend).

---

## 7. O que o Inbox tem hoje (lista)

- Filtro único: **Status** (Ativas, Todas, Arquivadas) — dropdown.
- **Uma única lista plana** de conversas: nome, hora, badge de não lidas, preview da última mensagem.
- Clique no item: `loadInboxConversation(thread_id, channel)`.
- **Não tem:** seção "Conversas não vinculadas", três pontos, Vincular, Criar Cliente, Ignorar, Excluir, nome do tenant/link, Editar nome, Alterar Cliente, Desvincular, filtros Canal/Sessão/Cliente, botão Nova Conversa.

---

## 8. Checklist para replicar no Inbox (mesma experiência)

- [ ] **Filtros:** Canal, Sessão (WhatsApp), Cliente (pesquisável), Status; botão Nova Conversa (ou link para Painel).
- [ ] **Seção "Conversas não vinculadas":** cabeçalho com ícone + título + badge de quantidade + descrição.
- [ ] **Itens não vinculados:** nome, telefone, channel_id (opcional), badge não lidas, **menu ⋮** (Criar Cliente, Ignorar, Excluir), **botão Vincular**, data/hora.
- [ ] **Separador** entre não vinculadas e vinculadas.
- [ ] **Itens vinculados:** nome, telefone, channel_id, **link "• {tenant_name}"** para `/tenants/view?id={tenant_id}` (sem propagar clique para abrir conversa), badge não lidas, **menu ⋮** (Arquivar/Ignorar ou Desarquivar/Ativar conforme status; Editar nome; Alterar Cliente; Desvincular se tiver tenant; Excluir), data/hora.
- [ ] **Menus:** mesmo conteúdo e mesmas chamadas de função (ou funções adaptadas que chamem os mesmos endpoints).
- [ ] **Modais:** Vincular, Criar Cliente, Alterar Cliente, Editar nome — podem ser os mesmos do Painel (globais) ou cópias no contexto do Inbox.
- [ ] **APIs:** usar os mesmos endpoints (link-tenant, create-tenant, change-tenant, update-contact-name, unlink, archive, reactivate, ignore, delete) para não quebrar regras de negócio.
- [ ] **Dados:** Inbox precisa receber da API `incoming_leads` e `threads` separados (ou lista com flag is_incoming_lead / tenant_id) para montar as duas seções e o link do tenant.
- [ ] **Estilos:** classes/estilos equivalentes para seção não vinculadas, badge, botão Vincular, menu dropdown, link do tenant, item danger (Excluir), item ativo (`.active`), itens arquivados/ignorados (borda e opacidade), para visual alinhado ao Painel.
- [ ] **Conversa ativa:** Marcar o item selecionado com classe equivalente a `.active` (background e borda) e atualizar ao clicar/re-renderizar.
- [ ] **Menus:** Fechar ao clicar fora; fechar outros menus ao abrir um (dois sistemas: `.incoming-lead-menu` + `.incoming-lead-menu-dropdown`, `.conversation-menu` + `.conversation-menu-dropdown`).
- [ ] **Atualização do badge de não vinculadas:** Após vincular/criar cliente/ignorar/excluir, chamar lógica equivalente a `updateIncomingLeadsCount()` (atualizar número e ocultar seção se zero).
- [ ] **Formatação de data:** Usar equivalente a `formatDateBrasilia` (fuso America/Sao_Paulo, dd/mm HH:mm, "Agora" se inválido).
- [ ] **Preview na lista:** Decidir se Inbox mantém preview da última mensagem ou remove para igualar ao Painel (Painel não exibe preview hoje).

---

## 9. Observações técnicas

- No Painel a lista é renderizada em PHP na carga e depois atualizada em JS com `renderConversationList`. No Inbox a lista é só JS (`renderInboxList`). Para replicar, o Inbox precisará de uma função análoga a `renderConversationList` que monte as duas seções e os dois tipos de item (incoming lead vs thread normal) com os mesmos elementos e handlers.
- O link do tenant usa `pixelhub_url('/tenants/view?id=' + tenant_id)` e `onclick="event.stopPropagation();"` para não disparar `handleConversationClick` ao clicar no link.
- As funções globais do Painel (openLinkTenantModal, openCreateTenantModal, etc.) podem ser reutilizadas no Inbox se o Inbox estiver na mesma página/domínio; caso contrário, será preciso expor as mesmas ações (por exemplo, abrindo o Painel em uma aba com parâmetros ou replicando os modais no Inbox).
- **Dois sistemas de menu:** (1) **Incoming lead:** `.incoming-lead-menu`, `.incoming-lead-menu-toggle`, `.incoming-lead-menu-dropdown`, `.incoming-lead-menu-item`, `.incoming-lead-menu-item.danger`; (2) **Conversa normal:** `.conversation-menu`, `.conversation-menu-toggle`, `.conversation-menu-dropdown`, `.conversation-menu-item`, `.conversation-menu-item.danger`. Estrutura paralela; o dropdown recebe a classe `.show` para ficar visível.
- **Renderização dinâmica (JS):** Em `renderConversationList` há um trecho PHP literal `<?php if (($filters['status'] ?? '') !== 'ignored'): ?>` dentro do loop de leads — isso é avaliado uma vez na carga da página, então o botão "Ignorar" no menu de leads (quando a lista é atualizada por JS) pode aparecer ou sumir conforme o filtro inicial. Na replicação no Inbox, tratar a condição "Ignorar" no menu de não vinculadas com base no status atual (ex.: não mostrar "Ignorar" quando o filtro já for "Ignoradas").

Documento gerado a partir do código em `views/communication_hub/index.php` (lista PHP + `renderConversationList`) e `views/layout/main.php` (Inbox atual). Revisado para incluir: conversa ativa (.active), estilos arquivada/ignorada, formatDateBrasilia, escapeHtml, fechamento dos menus, updateIncomingLeadsCount, rejectIncomingLead, modal Nova Mensagem, API conversations-list, preview da última mensagem, data-status-label (CSS), e checklist ampliado.
