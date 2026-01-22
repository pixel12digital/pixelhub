# Ajustes do Modal WhatsApp - Fase 2

## Diagnóstico do Problema da Roberta

**Caso identificado: CASO A - Registro existe no banco**

O script de verificação confirmou:
- ✅ Registro existe em `whatsapp_generic_logs` (ID: 2)
- ✅ `sent_at` está preenchido corretamente (2025-11-21 09:57:55)
- ✅ `WhatsAppHistoryService::getTimelineByTenant(2)` retorna o registro corretamente

**Conclusão:** O problema não é no salvamento nem na leitura. A página simplesmente precisa ser recarregada após o envio para mostrar o novo registro.

## Ajustes Implementados

### 1. Correção na Query do WhatsAppHistoryService
**Arquivo:** `src/Services/WhatsAppHistoryService.php`

Adicionado filtro `AND sent_at IS NOT NULL` na query de `whatsapp_generic_logs` para garantir consistência com `billing_notifications`.

```php
WHERE tenant_id = ?
AND sent_at IS NOT NULL
ORDER BY sent_at DESC
```

### 2. Fechamento Automático do Modal
**Arquivo:** `views/tenants/whatsapp_modal.php`

- Modal fecha automaticamente após 1 segundo quando o log é salvo com sucesso
- Modal permanece aberto em caso de erro para permitir correção/reenvio

```javascript
if (data.success) {
    // ... abre WhatsApp Web ...
    showSuccess('Contato registrado com sucesso!');
    setTimeout(() => {
        closeWhatsAppModal();
    }, 1000);
} else {
    // Modal permanece aberto
    showError('Aviso: Não foi possível registrar o log...');
}
```

### 3. Uso Sempre do WhatsApp Web
**Arquivos modificados:**
- `views/tenants/whatsapp_modal.php`
- `src/Services/WhatsAppTemplateService.php`

Todas as URLs foram alteradas de `https://wa.me/` para `https://web.whatsapp.com/send?phone=...&text=...`

**Formato final da URL:**
```
https://web.whatsapp.com/send?phone=5562985489901&text=Mensagem%20codificada
```

Isso garante que sempre abre o WhatsApp Web no navegador, nunca o app instalado.

## Arquivos Modificados

1. **src/Services/WhatsAppHistoryService.php**
   - Adicionado filtro `sent_at IS NOT NULL` na query de generic logs

2. **views/tenants/whatsapp_modal.php**
   - Função `openWhatsApp()`: Fechamento automático após sucesso
   - Função `openWhatsApp()`: Uso de `web.whatsapp.com` em vez de `wa.me`
   - Função `startWithoutTemplate()`: Uso de `web.whatsapp.com`

3. **src/Services/WhatsAppTemplateService.php**
   - Método `buildWhatsAppLink()`: Retorna URL do WhatsApp Web

4. **database/check-roberta-whatsapp-log.php** (novo)
   - Script de diagnóstico para verificar logs

## Critérios de Aceite

✅ **Modal fecha automaticamente após envio bem-sucedido**
✅ **Modal permanece aberto em caso de erro**
✅ **Sempre usa WhatsApp Web (web.whatsapp.com)**
✅ **Timeline e último contato funcionam corretamente** (após recarregar a página)

## Observação Importante

A página precisa ser recarregada manualmente após o envio para mostrar o novo registro na timeline. Isso é comportamento esperado, pois a view é renderizada no servidor.

**Sugestão futura:** Implementar atualização via AJAX da timeline após envio bem-sucedido.

