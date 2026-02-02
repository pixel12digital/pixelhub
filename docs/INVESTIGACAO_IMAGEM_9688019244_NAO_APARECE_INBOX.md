# Investigação: Por que a mensagem do 96 8801 9244 não aparece no Inbox?

**Data:** 02/02/2026  
**Contexto:** Usuário reporta que imagem enviada do número 96 8801 9244 foi "enviada e recebida com sucesso" no WhatsApp Web, mas não aparece no Inbox do Pixel Hub.

---

## 1. Observações dos prints

### Print WhatsApp Web
- Conversa com **+55 96 8801-9244** (conta comercial)
- Mensagem com imagem às **12:19** exibe: *"Não foi possível carregar a mensagem. Use seu celular para acessá-la. Clique para atualizar"*
- Ou seja: a mensagem existe, mas o **conteúdo da imagem falhou ao carregar** no WhatsApp Web (possível mídia expirada ou problema no próprio WhatsApp)

### Print Pixel Hub Inbox
- Inbox aberto com lista de conversas
- **96 8801 9244 não aparece** na lista visível
- Área de conteúdo mostra "Selecione uma conversa para começar"

---

## 2. Diagnóstico executado

Script: `php database/diagnostico-9688019244.php`

### Resultados

| Verificação | Resultado |
|-------------|-----------|
| **Conversa existe?** | ✓ Sim. `conversations.id=129`, `contact_external_id=559688019244` |
| **tenant_id** | NULL (conversa não vinculada a cliente) |
| **is_incoming_lead** | SIM (conversa tratada como lead não vinculado) |
| **channel_id** | pixel12digital |
| **status** | new |
| **last_message_at** | 2026-02-02 12:48:33 |
| **Eventos de mensagem** | 1 evento inbound (09:48:30), vinculado à conversa 129 |
| **Eventos de imagem** | Nenhum encontrado |
| **Dentro do LIMIT 100?** | ✓ Sim (entre as mais recentes) |

---

## 3. Causa identificada

### 3.1. Por que a conversa não aparece na lista do Inbox?

**Causa raiz:** O Inbox exibe apenas `result.threads` (conversas vinculadas a tenant). Conversas em `result.incoming_leads` **não são exibidas**.

**Fluxo:**

1. A API `GET /communication-hub/conversations-list` retorna:
   - `threads` = conversas com `tenant_id` preenchido (ou `is_incoming_lead = 0`)
   - `incoming_leads` = conversas com `tenant_id` NULL (ou `is_incoming_lead = 1`)

2. A conversa 96 8801 9244 tem `tenant_id = NULL` → é classificada como **incoming lead**.

3. O Inbox usa apenas `result.threads`:
   ```javascript
   InboxState.conversations = result.threads || [];
   renderInboxList(InboxState.conversations);
   ```

4. Como a conversa está em `incoming_leads`, ela **não entra** em `threads` e **não é renderizada** no Inbox.

**Conclusão:** A conversa existe no banco e na API, mas fica em `incoming_leads`. O Inbox atual não mostra essa seção.

---

### 3.2. Sobre a mensagem com imagem

- **Nenhum evento de imagem** foi encontrado para este número nos últimos 7 dias.
- O evento às 09:48:30 é provavelmente a mensagem estruturada (CNPJ, instituição, etc.), não a imagem.
- A mensagem com imagem às 12:19 no WhatsApp Web:
  - Se foi **enviada pelo negócio** (via WhatsApp Web) **para** 96 8801 9244: o gateway pode não enviar webhook para mensagens outbound enviadas por outros clientes (WhatsApp Web).
  - Se foi **enviada pelo cliente** (96 8801 9244) **para** o negócio: o webhook deveria ter sido disparado; a ausência de evento sugere que não chegou ao Pixel Hub ou que o tipo não foi registrado como `image`.

---

## 4. Possíveis causas (resumo)

| # | Causa | Evidência |
|---|-------|-----------|
| 1 | **Inbox não exibe incoming leads** | Confirmado. Conversa em `incoming_leads`, Inbox usa só `threads`. |
| 2 | Imagem enviada via WhatsApp Web (não Pixel Hub) | Gateway pode não enviar webhook de outbound para mensagens de outros clientes. |
| 3 | Imagem não carregou no WhatsApp Web | Print mostra "Não foi possível carregar a mensagem" – problema pode estar no WhatsApp, não no Pixel Hub. |
| 4 | Webhook não recebeu evento de imagem | Nenhum evento `type=image` encontrado para este número. |

---

## 5. Soluções recomendadas (para implementação futura)

### 5.1. Exibir incoming leads no Inbox (principal)

- Incluir `result.incoming_leads` na lista do Inbox, em seção separada (ex.: "Conversas não vinculadas").
- Ou unificar `threads` e `incoming_leads` em uma única lista, com indicador visual de "não vinculado".
- Referência: `docs/LEVANTAMENTO_LISTA_CONVERSAS_PAINEL_PARA_INBOX.md` (Painel de Comunicação já exibe incoming leads).

### 5.2. Vincular a conversa a um tenant

- Se a conversa for vinculada a um cliente (tenant), ela passará para `threads` e aparecerá no Inbox.
- Ação manual: usar "Vincular" ou "Alterar Cliente" no Painel de Comunicação (quando a conversa estiver visível lá).

### 5.3. Webhooks de mensagens outbound

- Verificar se o gateway envia webhooks para mensagens enviadas via WhatsApp Web.
- Se não enviar, avaliar configuração do gateway ou uso de API de envio pelo Pixel Hub para garantir registro das mensagens.

---

## 6. Referências

| Arquivo | Trecho |
|---------|--------|
| `views/layout/main.php` | ~2392–2395: `InboxState.conversations = result.threads` |
| `src/Controllers/CommunicationHubController.php` | ~4314–4346: separação `threads` vs `incoming_leads` |
| `database/diagnostico-9688019244.php` | Script de diagnóstico |
| `docs/LEVANTAMENTO_LISTA_CONVERSAS_PAINEL_PARA_INBOX.md` | Estrutura da lista no Painel (inclui incoming leads) |

---

*Documento gerado em 02/02/2026. Investigação baseada em consulta ao banco e análise do código. Sem implementações.*
