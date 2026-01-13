# 🎯 Instruções: Diagnóstico Mensagem ServPro

## ⚡ Execução Rápida

### 1. Envie uma mensagem de teste
Do número ServPro (554796474223) para o WhatsApp da sessão "Pixel12 Digital"
- Anote o texto exato (ex: "TESTE SERVPRO 17:21:33")
- Anote o horário exato

### 2. Execute o diagnóstico

**Opção A - Script PHP:**
```bash
php database/diagnose-servpro-simple.php
```

**Opção B - Queries SQL:**
Abra `database/queries-diagnostico-servpro.sql` e execute as queries na ordem.

### 3. Envie os resultados

O script/query retornará 10 itens. Envie todos:

1. **event_id:** (UUID)
2. **event_type:** (whatsapp.inbound.message ou whatsapp.outbound.message)
3. **channel_id:** (ex: "Pixel12 Digital")
4. **tenant_id:** (número ou NULL)
5. **conversation_id:** (número ou NENHUMA)
6. **last_message_at:** (timestamp)
7. **unread_count:** (número)
8. **last_message_direction:** (inbound ou outbound)
9. **endpoint_updates:** (has_updates=true ou false)
10. **conclusão:** (A) classificação vs (B) matching vs (C) polling

---

## 📁 Arquivos Criados

1. **`database/diagnose-servpro-simple.php`** - Script de diagnóstico automático
2. **`database/queries-diagnostico-servpro.sql`** - Queries SQL manuais
3. **`docs/GUIA_DIAGNOSTICO_SERVPRO.md`** - Guia completo de diagnóstico
4. **`docs/INSTRUCOES_DIAGNOSTICO_SERVPRO.md`** - Este arquivo

---

## 🔍 O que o Diagnóstico Verifica

### (A) Classificação Inbound/Outbound
- Se o evento foi classificado corretamente como `whatsapp.inbound.message`
- Se foi classificado como `outbound`, explica por que `unread_count` não incrementou

### (B) Matching de Conversa
- Se a conversa do ServPro foi atualizada
- Se `unread_count` incrementou
- Se outra conversa (Charles) foi atualizada incorretamente (heurística do 9º dígito)

### (C) Polling/UI
- Se o endpoint de updates retorna `has_updates=true`
- Se a conversa do ServPro está incluída no resultado

---

## ⚠️ Problemas Mais Prováveis

### 1. Classificação Errada (80% de chance)
**Sintoma:** `event_type = 'whatsapp.outbound.message'`  
**Causa:** `WhatsAppWebhookController::mapEventType()` não verifica `fromMe`  
**Correção:** Adicionar verificação de `fromMe` no payload

### 2. Matching Indevido (15% de chance)
**Sintoma:** Conversa do Charles atualizada ao invés do ServPro  
**Causa:** Heurística do 9º dígito muito agressiva  
**Correção:** Restringir equivalência quando já existe match exato

### 3. Polling Não Reflete (5% de chance)
**Sintoma:** Banco correto, mas UI não atualiza  
**Causa:** Filtros ou timestamp no endpoint  
**Correção:** Ajustar filtros em `checkUpdates()`

---

## 📝 Próximo Passo

**Execute o diagnóstico e envie os 10 itens.**  
Com esses dados, será gerado o diagnóstico fechado e o prompt de correção exato.

