# Correção: Bug na Central de Cobranças - Contagem Duplicada de Faturas

**Data:** 20/11/2025  
**Status:** ✅ Corrigido  
**Arquivos Alterados:**
- `src/Controllers/BillingCollectionsController.php` (método `overview()`)
- `views/billing_collections/whatsapp_modal.php`
- `views/billing_collections/overview.php`
- `views/tenants/whatsapp_modal.php`
- `views/tenants/view.php`
- `views/tenants/_table_rows.php`

---

## 📋 Problema Identificado

### Sintoma
Após clicar em "Salvar/Marcar como enviado" no fluxo de cobrança via WhatsApp, a Central de Cobranças (`/billing/overview`) passava a exibir valores incorretos para o cliente:

- **Valor em Atraso:** R$ 3.405,60 (incorreto)
- **Qtd Faturas Vencidas:** 63 (incorreto)

O cliente em questão (Carlos Rodrigo Machado Patrício, tenant_id = 44) tinha apenas algumas faturas em atraso, não 63.

### Contexto
- Cliente foi arquivado como "somente financeiro" para resolver duplicidade (havia "Africa Cargo Logística" + "Carlos")
- A Central de Cobranças continuava mostrando o cliente (comportamento esperado)
- O problema ocorria especificamente após marcar uma cobrança como enviada

---

## 🔍 Causa Raiz

### Análise da Query

A query da Central de Cobranças (`BillingCollectionsController::overview()`) tinha um problema crítico no `LEFT JOIN`:

```sql
FROM tenants t
LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
LEFT JOIN billing_notifications bn ON t.id = bn.tenant_id AND bn.status = 'sent_manual'
```

**O Problema:**
Quando um tenant tinha múltiplas notificações (`billing_notifications`), o `LEFT JOIN` criava múltiplas linhas para cada fatura. Por exemplo:

- Tenant tem **5 faturas** em atraso
- Tenant tem **10 notificações** (sent_manual)
- O JOIN cria **5 × 10 = 50 linhas**
- `COUNT()` conta **50 faturas** em vez de **5**

### Por que acontecia após "Salvar contato"?

Ao clicar em "Salvar/Marcar como enviado" (`markWhatsAppSent()`), o sistema:
1. Cria/atualiza um registro em `billing_notifications`
2. Atualiza `whatsapp_last_at` na fatura

Isso aumenta o número de notificações, multiplicando ainda mais as linhas no JOIN.

---

## ✅ Solução Implementada

### 1. Correção da Query do Overview

**Antes:**
```sql
LEFT JOIN billing_notifications bn ON t.id = bn.tenant_id AND bn.status = 'sent_manual'
-- ...
MAX(bn.sent_at) as last_notification_sent
```

**Depois:**
```sql
-- Usa subquery para evitar multiplicação de linhas
(SELECT MAX(sent_at) FROM billing_notifications WHERE tenant_id = t.id AND status = 'sent_manual') as last_notification_sent
```

**Mudanças:**
- Removido o `LEFT JOIN` com `billing_notifications`
- Usado `COUNT(DISTINCT ...)` para contagem de faturas (proteção adicional)
- Último contato via subquery (não multiplica linhas)

### 2. Ajuste de UX - Links WhatsApp em Nova Aba

Todos os links de WhatsApp agora abrem em nova aba com `target="_blank"` e `rel="noopener noreferrer"`:

**Arquivos ajustados:**
- `views/billing_collections/whatsapp_modal.php` - Link do modal
- `views/billing_collections/overview.php` - Link no modal agregado
- `views/tenants/whatsapp_modal.php` - `window.open()` com parâmetros de segurança
- `views/tenants/view.php` - Links diretos de telefone
- `views/tenants/_table_rows.php` - Links diretos de telefone

**Benefício:**
- Usuário não perde a tela do Pixel Hub ao abrir WhatsApp
- Pode voltar facilmente para marcar como enviado
- Melhor experiência de uso

---

## 🧪 Como Testar

### 1. Teste da Contagem Correta

1. Acesse `/billing/overview`
2. Anote os valores para um cliente específico (ex: tenant_id = 44)
3. Acesse `/tenants/view?id=44&tab=financial`
4. Clique em "Cobrar" em uma fatura
5. Clique em "Salvar / Marcar como Enviado"
6. Volte para `/billing/overview`
7. **Verificar:** Os valores devem permanecer corretos (não devem aumentar)

### 2. Teste do Script de Diagnóstico

Execute o script de diagnóstico:
```bash
php database/diagnose-billing-overview-bug.php
```

O script mostra:
- Faturas reais vs. contagem do overview
- Análise do JOIN (quantas linhas são geradas)
- Verificação de duplicatas
- Comparação entre query antiga e corrigida

### 3. Teste de Links WhatsApp

1. Em qualquer tela com link de WhatsApp:
   - Clique no link/botão
   - **Verificar:** Abre em nova aba
   - **Verificar:** Pixel Hub permanece na aba atual

---

## 📊 Query Corrigida (Completa)

```sql
SELECT 
    t.id as tenant_id,
    t.name as tenant_name,
    t.person_type,
    t.nome_fantasia,
    t.phone,
    t.billing_status,
    
    -- Valor em atraso
    COALESCE(SUM(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_overdue,
    
    -- Qtd faturas vencidas (usa COUNT DISTINCT para evitar duplicação)
    COUNT(DISTINCT CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.id END) as qtd_invoices_overdue,
    
    -- Valor vencendo hoje
    COALESCE(SUM(CASE WHEN bi.due_date = CURDATE() AND bi.status = 'pending' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_due_today,
    
    -- Valor vencendo próximos 7 dias
    COALESCE(SUM(CASE WHEN bi.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                      AND bi.status = 'pending' 
                      AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) 
                 THEN bi.amount ELSE 0 END), 0) as total_due_next_7d,
    
    -- Último contato WhatsApp (da tabela billing_invoices)
    MAX(bi.whatsapp_last_at) as last_whatsapp_contact,
    
    -- Último contato via billing_notifications (usando subquery para evitar JOIN multiplicador)
    (SELECT MAX(sent_at) FROM billing_notifications WHERE tenant_id = t.id AND status = 'sent_manual') as last_notification_sent
    
FROM tenants t
LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
WHERE t.status = 'active'
GROUP BY t.id, t.name, t.person_type, t.nome_fantasia, t.phone, t.billing_status
```

---

## 🔐 Segurança

### Links WhatsApp

Todos os links agora incluem `rel="noopener noreferrer"` para:
- Prevenir `window.opener` attacks
- Melhorar privacidade (não envia referrer)
- Seguir boas práticas de segurança web

---

## 📝 Notas Técnicas

### Por que COUNT(DISTINCT) foi adicionado?

Embora a remoção do JOIN já resolva o problema, `COUNT(DISTINCT ...)` foi adicionado como proteção adicional caso:
- Futuras alterações reintroduzam JOINs problemáticos
- Haja necessidade de JOINs adicionais que possam multiplicar linhas

### Impacto em Performance

A subquery para `last_notification_sent` é executada uma vez por tenant. Para melhorar performance futuramente, pode-se:
- Adicionar índice em `(tenant_id, status, sent_at)` na tabela `billing_notifications`
- Considerar materialização/cache se necessário

### Clientes Arquivados

A query continua filtrando apenas `t.status = 'active'`. Clientes arquivados (`is_archived = 1`) não aparecem na Central de Cobranças, mesmo que tenham faturas em aberto.

**Nota:** Se necessário incluir clientes arquivados "somente financeiro" (`is_financial_only = 1`), ajustar o WHERE:
```sql
WHERE t.status = 'active' OR (t.is_archived = 1 AND t.is_financial_only = 1)
```

---

## ✅ Checklist de Validação

- [x] Query corrigida não multiplica linhas
- [x] Contagem de faturas está correta
- [x] Valores em atraso estão corretos
- [x] Links WhatsApp abrem em nova aba
- [x] `rel="noopener noreferrer"` adicionado
- [x] Script de diagnóstico criado
- [x] Documentação completa

---

## 🚀 Próximos Passos (Opcional)

1. **Monitoramento:** Adicionar log quando contagem parecer suspeita
2. **Índices:** Adicionar índice em `billing_notifications(tenant_id, status, sent_at)`
3. **Cache:** Considerar cache da Central de Cobranças se performance for crítica
4. **Testes Automatizados:** Criar testes unitários para validar contagem

---

**Autor:** Auto (Cursor AI)  
**Revisão:** Pendente

