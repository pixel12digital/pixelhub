# Implementação de Atualização AJAX da Timeline WhatsApp

## Objetivo

Atualizar automaticamente a timeline e o campo "Último contato WhatsApp" após envio bem-sucedido de mensagem, sem necessidade de recarregar a página.

## Arquivos Modificados

### 1. `src/Controllers/TenantsController.php`

**Novo método:** `getWhatsAppTimelineAjax()`

Endpoint AJAX que retorna a timeline atualizada em JSON:

```php
GET /tenants/whatsapp-timeline-ajax?tenant_id=2
```

**Resposta JSON:**
```json
{
  "success": true,
  "timeline": [...],
  "lastContact": {...} | null
}
```

### 2. `public/index.php`

**Nova rota adicionada:**
```php
$router->get('/tenants/whatsapp-timeline-ajax', 'TenantsController@getWhatsAppTimelineAjax');
```

### 3. `views/tenants/view.php`

**Modificações:**

1. **IDs adicionados aos elementos HTML:**
   - `id="last-whatsapp-contact"` no campo "Último contato WhatsApp"
   - `id="whatsapp-timeline-container"` no container da timeline

2. **Script JavaScript adicionado:**
   - `updateWhatsAppTimeline(tenantId)` - Função principal que busca dados via AJAX
   - `updateLastContact(lastContact)` - Atualiza o campo "Último contato"
   - `updateTimelineTable(timeline)` - Atualiza a tabela de histórico
   - `formatDateTime(dateTimeString)` - Formata data/hora
   - `escapeHtml(text)` - Escapa HTML para segurança
   - `window.currentTenantId` - Variável global com ID do tenant

### 4. `views/tenants/whatsapp_modal.php`

**Modificação na função `openWhatsApp()`:**

Após salvar o log com sucesso, chama `updateWhatsAppTimeline()`:

```javascript
if (data.success) {
    // ... abre WhatsApp Web ...
    showSuccess('Contato registrado com sucesso!');
    
    // Atualiza timeline via AJAX
    if (typeof updateWhatsAppTimeline === 'function' && typeof window.currentTenantId !== 'undefined') {
        updateWhatsAppTimeline(window.currentTenantId);
    }
    
    // Fecha modal após 1 segundo
    setTimeout(() => {
        closeWhatsAppModal();
    }, 1000);
}
```

## Fluxo de Funcionamento

1. **Usuário envia mensagem pelo modal**
   - Clica em "Abrir WhatsApp Web"
   - Modal faz POST para `/tenants/whatsapp-generic-log`

2. **Log é salvo com sucesso**
   - Backend retorna `{success: true}`
   - Modal abre WhatsApp Web
   - Modal chama `updateWhatsAppTimeline(window.currentTenantId)`

3. **Atualização via AJAX**
   - JavaScript faz GET para `/tenants/whatsapp-timeline-ajax?tenant_id=X`
   - Backend retorna timeline atualizada
   - JavaScript atualiza:
     - Campo "Último contato WhatsApp"
     - Tabela "Histórico WhatsApp"

4. **Modal fecha automaticamente**
   - Após 1 segundo, modal fecha
   - Usuário vê timeline atualizada sem recarregar página

## Benefícios

✅ **UX melhorada:** Timeline atualiza instantaneamente após envio
✅ **Sem reload:** Não precisa recarregar a página manualmente
✅ **Feedback imediato:** Usuário vê o novo registro na timeline
✅ **Consistência:** Dados sempre atualizados após envio

## Segurança

- Endpoint AJAX requer autenticação (`Auth::requireInternal()`)
- Validação de `tenant_id` no backend
- Escape de HTML no JavaScript para prevenir XSS
- Validação de dados antes de atualizar DOM

## Compatibilidade

- Funciona apenas na aba "Visão Geral" (`activeTab === 'overview'`)
- Se o script não estiver disponível, modal funciona normalmente (graceful degradation)
- Se `window.currentTenantId` não estiver definido, atualização não ocorre (sem erro)

## Testes

Para testar:

1. Abrir painel do cliente (aba Visão Geral)
2. Clicar em "WhatsApp"
3. Enviar mensagem (com ou sem template)
4. Verificar que:
   - Modal fecha automaticamente
   - Campo "Último contato WhatsApp" é atualizado
   - Tabela "Histórico WhatsApp" mostra o novo registro
   - Tudo acontece sem recarregar a página

