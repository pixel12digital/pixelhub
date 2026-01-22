# GUIA RÁPIDO: Capturar Network DEPOIS do Vínculo

## O QUE VOCÊ PRECISA FAZER

### 1. Recarregar Página
- Após executar as queries DEPOIS, recarregue a página do Communication Hub
- **Mantenha "Preserve log" marcado** no Network tab

### 2. Capturar Listagem DEPOIS

**Passos:**
1. A página deve recarregar automaticamente após o vínculo
2. No Network, filtre por: `conversations-list`
3. Encontre a **nova** requisição: `GET /communication-hub/conversations-list`
4. Clique com botão direito → **Copy** → **Copy response**
5. Cole em um arquivo JSON e salve como: `evidencias/network/network-lista-depois.json`

**Campos a conferir no JSON:**
- Procure por `"threads"` ou `"incoming_leads"` (array)
- Encontre a conversa do "Victor" (final 9047)
- **Verificar:**
  - A conversa aparece em `threads` ou `incoming_leads`?
  - O `thread_id` mudou? (comparar com antes)
  - O `tenant_id` está correto? (deve ser o selecionado)
  - O `is_incoming_lead` mudou? (deve ser `false`)
  - Apareceu uma conversa duplicada? (dois `thread_id` diferentes)

### 3. Se Aparecer Duplicada

**Se você encontrar duas conversas "Victor" na lista:**
- Anote os dois `thread_id` (ex: `whatsapp_15` e `whatsapp_17`)
- Anote os dois `conversation_id`
- Anote os dois `tenant_id` (são diferentes?)
- Anote os dois `contact` (números diferentes?)

---

## RESUMO DO QUE SALVAR

- [ ] `evidencias/network/network-lista-depois.json` - Response de `GET /communication-hub/conversations-list`

**IMPORTANTE:** Compare com `network-lista-antes.json` para identificar mudanças.

