# AUDITORIA - SISTEMA DE COBRANÇA AUTOMÁTICA
**Data:** 03/03/2026  
**Solicitante:** Análise do Cliente ID 14

---

## 🔴 PROBLEMAS CRÍTICOS IDENTIFICADOS

### 1. PLANEJADOR NÃO ESTÁ RODANDO
- **Sintoma:** Fila de envios (`billing_dispatch_queue`) completamente vazia nos últimos 7 dias
- **Causa:** Crons não configurados no servidor
- **Impacto:** NENHUMA cobrança automática está sendo enviada para NENHUM cliente

### 2. LOGS INEXISTENTES
- `logs/billing_dispatch.log` - NÃO EXISTE
- `logs/billing_worker.log` - NÃO EXISTE
- **Conclusão:** Os scripts nunca foram executados via cron

### 3. CLIENTE #14 SEM FATURAS
- Cliente tem `billing_auto_send = 1` (cobrança ativa)
- Cliente tem `asaas_customer_id = cus_000123603388` (vinculado ao Asaas)
- **PROBLEMA:** Tabela `invoices` tem 0 registros para este cliente
- **Causa:** Faturas nunca foram sincronizadas do Asaas

### 4. TODOS OS 22 CLIENTES ATIVOS SEM FATURAS
- 22 clientes têm `billing_auto_send = 1`
- **NENHUM** deles tem faturas na tabela `invoices`
- Isso explica por que não há envios (não há o que cobrar)

---

## 📊 ESTATÍSTICAS

### Clientes com Cobrança Automática Ativa
- **Total:** 22 clientes
- **Com modo teste:** 1 (Cliente #25)
- **Com faturas sincronizadas:** 0 ❌

### Canais Configurados
- `whatsapp`: 5 clientes
- `email`: 3 clientes  
- `both`: 14 clientes

### Regras de Disparo
- **Total:** 7 regras configuradas
- **Ativas:** 7 (todas ✅)
- Regras: pre_due (-3d), pre_due_1d (-1d), due_day (0d), overdue_1d (+1d), overdue_3d (+3d), overdue_7d (+7d), overdue_15d (+15d com repetição)

---

## 🔍 ANÁLISE DO CLIENTE #14

**Configuração:**
- Nome: (sem nome cadastrado)
- Cobrança automática: ✅ ATIVA
- Canal: `email` (não envia WhatsApp!)
- Modo teste: ❌ NÃO
- Asaas Customer ID: `cus_000123603388`

**Faturas:**
- Total: 0
- Pendentes: 0
- Vencidas: 0

**Envios:**
- Na fila (30 dias): 0
- Notificações enviadas (30 dias): 0

**Diagnóstico:**
O cliente #14 está configurado para receber cobranças automáticas, mas:
1. Não tem faturas sincronizadas do Asaas
2. Canal configurado é `email` (não WhatsApp), então mesmo que houvesse faturas, não receberia via WhatsApp
3. Sem faturas, o planejador não tem o que enfileirar

---

## ✅ AÇÕES CORRETIVAS NECESSÁRIAS

### 1. CONFIGURAR CRONS NO SERVIDOR (URGENTE)

**Planejador (executa 1x/dia às 08:00, seg-sex):**
```bash
0 8 * * 1-5 cd /home/usuario/hub.pixel12digital.com.br && php scripts/billing_auto_dispatch.php >> logs/billing_dispatch.log 2>&1
```

**Worker (executa a cada 5min, seg-sex, 08:00-11:55):**
```bash
*/5 8-11 * * 1-5 cd /home/usuario/hub.pixel12digital.com.br && php scripts/billing_queue_worker.php >> logs/billing_worker.log 2>&1
```

**Importante:** Ajustar o caminho `/home/usuario/hub.pixel12digital.com.br` para o caminho real no servidor HostMídia.

### 2. SINCRONIZAR FATURAS DO ASAAS

Para cada cliente com `billing_auto_send = 1`:

1. Acessar: `https://hub.pixel12digital.com.br/tenants/view?id={ID}&tab=financial`
2. Clicar no botão **"Sincronizar com Asaas"**
3. Verificar se faturas aparecem na lista

**Cliente #14 específico:**
- URL: https://hub.pixel12digital.com.br/tenants/view?id=14&tab=financial
- Verificar se o `asaas_customer_id` está correto
- Verificar se o cliente tem assinatura ativa no Asaas

### 3. CORRIGIR CANAL DO CLIENTE #14 (SE NECESSÁRIO)

Se o cliente #14 deve receber cobranças via **WhatsApp**:
1. Acessar a página do cliente
2. Aba "Cobrança Automática"
3. Alterar canal de `email` para `whatsapp` ou `both`

---

## 📋 CHECKLIST DE VERIFICAÇÃO PÓS-CORREÇÃO

Após implementar as correções, verificar:

- [ ] Crons configurados e rodando (verificar logs em `logs/billing_dispatch.log` e `logs/billing_worker.log`)
- [ ] Faturas sincronizadas para os 22 clientes ativos (query: `SELECT tenant_id, COUNT(*) FROM invoices WHERE tenant_id IN (SELECT id FROM tenants WHERE billing_auto_send = 1) GROUP BY tenant_id`)
- [ ] Fila de envios sendo populada (query: `SELECT COUNT(*) FROM billing_dispatch_queue WHERE scheduled_at >= CURDATE()`)
- [ ] Notificações sendo enviadas (query: `SELECT COUNT(*) FROM billing_notifications WHERE created_at >= CURDATE()`)

---

## 🎯 CAUSA RAIZ

**Por que o sistema não está funcionando?**

1. **Crons nunca foram configurados** → Planejador nunca rodou → Fila sempre vazia
2. **Faturas nunca foram sincronizadas** → Mesmo se o planejador rodasse, não teria o que enfileirar
3. **Combinação dos dois** → Sistema completamente inoperante

**Conclusão:** O sistema de cobrança automática foi implementado no código, mas **nunca foi ativado em produção**. É necessário:
- Configurar os crons
- Sincronizar faturas de todos os clientes ativos
- Monitorar logs para garantir que está funcionando

---

## 📝 SCRIPT DE AUDITORIA

Foi criado o script `scripts/audit_billing_report.php` que pode ser executado a qualquer momento para verificar o status do sistema:

```bash
php scripts/audit_billing_report.php
```

Este script verifica:
- Clientes com cobrança ativa
- Fila de envios
- Logs dos crons
- Faturas sincronizadas
- Análise específica de qualquer cliente

---

## 🔗 REFERÊNCIAS

- Memória do sistema: SYSTEM-RETRIEVED-MEMORY[d29fd348-294e-4548-915d-1544edc1b46d]
- Scripts: `billing_auto_dispatch.php`, `billing_queue_worker.php`
- Tabelas: `billing_dispatch_queue`, `billing_dispatch_rules`, `billing_notifications`, `invoices`
- Campos tenant: `billing_auto_send`, `billing_auto_channel`, `is_billing_test`
