# Análise: Próxima Etapa de Implementação — Menu ⋮ (Três Pontos) no Inbox

**Tipo:** Ação investigativa (sem implementação)  
**Data:** 29/01/2026

---

## 1. Resumo

A **próxima etapa** de implementação, conforme documentação (`LEVANTAMENTO_LISTA_CONVERSAS_PAINEL_PARA_INBOX.md` e `DIVERGENCIAS_INBOX_VS_PAINEL.md`), é adicionar o **menu de três pontos (⋮)** e o **botão Vincular** nos itens da lista do Inbox, para paridade com o Painel de Comunicação.

---

## 2. Comparativo: O que o Painel tem vs o que o Inbox tem

### 2.1 Conversas não vinculadas (incoming leads)

| Elemento | Painel | Inbox |
|----------|--------|-------|
| Nome do contato | ✅ | ✅ |
| Telefone (ícone + número) | ✅ | ✅ |
| channel_id | ✅ | ✅ |
| Badge de não lidas | ✅ | ✅ |
| **Menu ⋮ (três pontos)** | ✅ | ❌ **Ausente** |
| **Botão Vincular** | ✅ | ❌ **Ausente** |
| Data/hora | ✅ | ✅ |
| data-conversation-id | ✅ | ❌ (não usado) |

**Menu do Painel (não vinculadas):**
- Criar Cliente → `openCreateTenantModal(conversation_id, contact_name, contact)`
- Ignorar → `ignoreConversation(conversation_id, contact_name)` (oculto se status = ignored)
- Excluir → `deleteConversation(conversation_id, contact_name)` (classe `.danger`)

---

### 2.2 Conversas vinculadas (threads normais)

| Elemento | Painel | Inbox |
|----------|--------|-------|
| Nome do contato | ✅ | ✅ |
| Telefone + channel_id | ✅ | ✅ |
| Link do tenant | ✅ | ✅ |
| Badge de não lidas | ✅ | ✅ |
| **Menu ⋮ (três pontos)** | ✅ | ❌ **Ausente** |
| Data/hora | ✅ | ✅ |
| data-conversation-id | ✅ (implícito em thread) | ❌ (não usado) |

**Menu do Painel (vinculadas) — varia por status:**
- **Ativa:** Arquivar, Ignorar
- **Arquivada:** Desarquivar, Ignorar
- **Ignorada:** Ativar, Arquivar
- **Comum a todas:** Editar nome, Alterar Cliente, Desvincular (se tem tenant), Excluir

---

## 3. O que o Inbox renderiza hoje (código)

### 3.1 Estrutura atual dos itens (renderInboxList)

**Não vinculados:**
```html
<div class="inbox-drawer-conversation" data-thread-id="..." data-channel="...">
  <div class="conv-name">nome + time + badge</div>
  <div class="conv-preview">ícone telefone + contact + channel_id</div>
</div>
```

**Vinculados:**
```html
<div class="inbox-drawer-conversation" data-thread-id="..." data-channel="...">
  <div class="conv-name">nome + time + badge</div>
  <div class="conv-preview">ícone + contact + channel_id + tenantLink</div>
</div>
```

**Ausente em ambos:** botão ⋮, dropdown, botão Vincular (leads), `data-conversation-id`.

---

## 4. O que o Painel renderiza (referência)

### 4.1 Incoming leads (Painel)

- `.incoming-lead-menu` com `.incoming-lead-menu-toggle` (botão ⋮)
- `.incoming-lead-menu-dropdown` com `.incoming-lead-menu-item` (Criar Cliente, Ignorar, Excluir)
- `.incoming-lead-actions` com `.incoming-lead-btn-primary` (Vincular)
- `data-conversation-id` no item
- Funções: `toggleIncomingLeadMenu(this)`, `closeIncomingLeadMenu(this)`

### 4.2 Threads normais (Painel)

- `.conversation-menu` com `.conversation-menu-toggle` (botão ⋮)
- `.conversation-menu-dropdown` com `.conversation-menu-item` (itens dinâmicos por status)
- `data-conversation-id` via `thread.conversation_id`
- Funções: `toggleConversationMenu(this)`, `closeConversationMenu(this)`

---

## 5. Dados disponíveis na API

A API `conversations-list` retorna:
- **incoming_leads:** `thread_id`, `channel`, `conversation_id`, `contact_name`, `contact`, `channel_id`, `unread_count`, `last_activity`
- **threads:** `thread_id`, `channel`, `conversation_id`, `contact_name`, `contact`, `channel_id`, `tenant_id`, `tenant_name`, `status`, `unread_count`, `last_activity`

O Inbox já recebe `conversation_id` em ambos; falta apenas usá-lo no HTML e nas ações.

---

## 6. Desafios técnicos para implementar o menu

### 6.1 Contexto do Inbox

O Inbox está em `main.php` (layout global) e aparece em **qualquer página** do sistema:
- `/communication-hub` — Painel e Inbox na mesma página
- `/projects/show?id=3`, `/tenants`, `/billing`, etc. — Inbox visível, Painel não

### 6.2 Modais e funções do Painel

As ações do menu dependem de:
- **Modais:** `#new-message-modal`, `#link-tenant-modal`, `#create-tenant-modal`, `#change-tenant-modal`, `#edit-contact-name-modal`, etc.
- **Funções globais:** `openLinkTenantModal`, `openCreateTenantModal`, `ignoreConversation`, `deleteConversation`, `archiveConversation`, `reactivateConversation`, `openEditContactNameModal`, `openChangeTenantModal`, `unlinkConversation`

Esses modais e funções estão definidos em `views/communication_hub/index.php`. Quando o usuário **não** está em `/communication-hub`, eles **não existem** no DOM.

### 6.3 Opções de implementação

| Opção | Descrição | Prós | Contras |
|-------|-----------|------|---------|
| **A** | Reusar modais/funções quando na página do Painel | Sem duplicação | Só funciona em `/communication-hub` |
| **B** | Abrir Painel em nova aba com parâmetros (ex: `?action=link&conv_id=123`) | Funciona em qualquer página | Fluxo mais indireto |
| **C** | Replicar modais e funções no `main.php` (layout global) | Funciona em qualquer página | Duplicação, manutenção em dois lugares |
| **D** | Incluir condicionalmente o bloco de modais do Painel no `main.php` quando o Inbox está presente | Reuso de código | Requer refatorar modais para serem carregáveis fora do hub |

---

## 7. Checklist do LEVANTAMENTO (status)

- [x] Filtros: Canal, Sessão, Cliente, Status; botão Nova Conversa
- [x] Seção "Conversas não vinculadas" (cabeçalho, badge, descrição)
- [x] Itens não vinculados: nome, telefone, channel_id, badge, data
- [ ] **Itens não vinculados: menu ⋮, botão Vincular**
- [x] Separador entre não vinculadas e vinculadas
- [x] Itens vinculados: nome, telefone, channel_id, link tenant, badge, data
- [ ] **Itens vinculados: menu ⋮**
- [ ] Menus: mesmo conteúdo e mesmas chamadas
- [ ] Modais: Vincular, Criar Cliente, Alterar Cliente, Editar nome
- [x] APIs: mesmos endpoints (já usados pelo Painel)
- [x] Dados: incoming_leads e threads separados
- [ ] Estilos: menu dropdown, botão Vincular
- [x] Conversa ativa (classe .active)
- [ ] Menus: fechar ao clicar fora; fechar outros ao abrir
- [ ] Atualização do badge de não vinculadas após ações

---

## 8. Conclusão

A **próxima etapa** é implementar o **menu ⋮** e o **botão Vincular** no Inbox. A principal decisão é **onde** as ações serão executadas:

1. **Se o usuário está em `/communication-hub`:** é possível reusar modais e funções do Painel.
2. **Se o usuário está em outra página:** é necessário definir estratégia (B, C ou D acima).

Recomendação: começar pela **Opção A** (reuso quando na página do Painel) e, em seguida, para outras páginas, adotar a **Opção B** (abrir Painel em nova aba com parâmetros) como fallback, evitando duplicar modais e lógica.

---

---

## 9. Implementação realizada (29/01/2026)

- **Menu ⋮** e **botão Vincular** adicionados em conversas não vinculadas.
- **Menu ⋮** adicionado em conversas vinculadas (Arquivar/Ignorar/Desarquivar/Ativar conforme status; Editar nome; Alterar Cliente; Desvincular; Excluir).
- Funções wrapper (`inboxOpenLinkTenantModal`, etc.) que reutilizam as do Painel quando existem, ou abrem o Painel em nova aba como fallback.
- CSS dos menus adicionado em `main.php` (escopo `.inbox-drawer`).
- Fechamento do menu ao clicar fora.

---

*Documento de análise — implementação concluída em 29/01/2026.*
