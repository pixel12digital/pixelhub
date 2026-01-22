# GUIA RÁPIDO: Capturar Network (ANTES do Vínculo)

## O QUE VOCÊ PRECISA FAZER

### 1. Abrir Communication Hub no navegador
- Acesse: `http://localhost/painel.pixel12digital/communication-hub` (ou sua URL)
- Abra DevTools: `F12` ou `Ctrl+Shift+I`
- Vá para aba **Network**

### 2. Marcar "Preserve log"
- No Network tab, marque a opção **"Preserve log"** (mantém requisições após reload)

### 3. Capturar Listagem ANTES

**Passos:**
1. Acesse a lista de conversas (se não estiver já)
2. No Network, filtre por: `conversations-list`
3. Encontre a requisição: `GET /communication-hub/conversations-list`
4. Clique com botão direito → **Copy** → **Copy response**
5. Cole em um arquivo JSON e salve como: `evidencias/network/network-lista-antes.json`

**Campos a conferir no JSON:**
- Procure por `"threads"` ou `"incoming_leads"` (array)
- Encontre a conversa do "Victor" (final 9047)
- Anote:
  - `thread_id` (ex: `"whatsapp_15"`)
  - `conversation_id` (ex: `15`)
  - `contact` (ex: `"(11) 9047-XXXX"`)
  - `contact_name` (ex: `"Victor"`)
  - `tenant_id` (deve ser `null`)
  - `is_incoming_lead` (deve ser `true`)
  - Em qual array aparece: `threads` ou `incoming_leads`?

### 4. Capturar Detalhe ANTES

**Passos:**
1. Clique na conversa do "Victor" para abrir
2. No Network, filtre por: `thread-info` ou `thread-data`
3. Encontre a requisição: `GET /communication-hub/thread-info?thread_id=whatsapp_X`
4. Clique com botão direito → **Copy** → **Copy response**
5. Cole em um arquivo JSON e salve como: `evidencias/network/network-detalhe-antes.json`

**Campos a conferir no JSON:**
- `thread_id`
- `conversation_id`
- `contact` ou `contact_external_id`
- `tenant_id` (deve ser `null`)
- `is_incoming_lead` (se vier)

---

## RESUMO DO QUE SALVAR

- [ ] `evidencias/network/network-lista-antes.json` - Response de `GET /communication-hub/conversations-list`
- [ ] `evidencias/network/network-detalhe-antes.json` - Response de `GET /communication-hub/thread-info`

**IMPORTANTE:** Não execute o vínculo ainda! Aguarde instruções para a próxima fase.

