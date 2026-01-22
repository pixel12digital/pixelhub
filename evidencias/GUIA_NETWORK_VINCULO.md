# GUIA RÁPIDO: Executar Vínculo e Capturar Network

## O QUE VOCÊ PRECISA FAZER

### 1. Preparar Network Tab
- Certifique-se de que o **Network tab** está aberto no DevTools
- Marque **"Preserve log"** (se ainda não estiver marcado)

### 2. Abrir Modal de Vínculo
- Na conversa do "Victor", clique no botão **"Vincular"**
- O modal "Vincular a Cliente Existente" deve abrir
- **NÃO confirme ainda**

### 3. Selecionar Tenant
- No modal, selecione um tenant/cliente
- **Anote qual tenant você selecionou:** ID e Nome

### 4. Executar Vínculo e Capturar

**Passos:**
1. **Antes de clicar "Vincular":** Verifique que o Network tab está visível
2. Clique em **"Vincular"**
3. Aguarde a mensagem "Conversa vinculada ao cliente com sucesso"
4. No Network, filtre por: `link-tenant`
5. Encontre a requisição: `POST /communication-hub/incoming-lead/link-tenant`
6. **Request:**
   - Clique com botão direito → **Copy** → **Copy request payload**
   - Salve como: `evidencias/network/network-vinculo-request.json`
7. **Response:**
   - Clique na requisição → Aba **Response**
   - Copie o JSON completo
   - Salve como: `evidencias/network/network-vinculo-response.json`

**Campos a conferir no Request:**
```json
{
  "conversation_id": 15,  // Deve ser o ID da conversa A
  "tenant_id": 42         // Deve ser o tenant selecionado
}
```

**Campos a conferir no Response:**
```json
{
  "success": true,
  "tenant_id": 42,
  "tenant_name": "Nome do Cliente",
  "conversation_id": 15,
  "message": "Conversa vinculada ao cliente com sucesso"
}
```

---

## RESUMO DO QUE SALVAR

- [ ] `evidencias/network/network-vinculo-request.json` - Request payload do POST
- [ ] `evidencias/network/network-vinculo-response.json` - Response do POST
- [ ] **Anotar:** Qual tenant foi selecionado (ID e Nome)

**IMPORTANTE:** Após capturar, **NÃO recarregue a página ainda**. Aguarde instruções para executar as queries DEPOIS.

