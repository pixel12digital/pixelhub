# DIAGNÓSTICO COMPLETO: Webhook não gravando mensagens

**Data:** 17/01/2026  
**Status:** 🔴 PROBLEMA IDENTIFICADO  
**Prioridade:** Crítica

---

## 🎯 PROBLEMA

Mensagens enviadas via WhatsApp (ex: "Envio0907" às 09:08 de hoje) aparecem no WhatsApp Web mas **não estão sendo gravadas no banco de dados**.

**Evidência:**
- ✅ WhatsApp Web mostra mensagem "Envio0907" às 09:08
- ❌ Banco de dados não tem nenhum evento `whatsapp.inbound.message` hoje
- ✅ Webhook está recebendo requests (56 eventos `connection.update` hoje)

---

## 🔍 DIAGNÓSTICO SISTEMÁTICO

### ETAPA 1: Webhook está recebendo requests ✅

**Resultado:** Webhook está funcionando e recebendo requests.

- Total de eventos hoje: 56
- Tipos de eventos: apenas `whatsapp.connection.update`
- **Problema:** Nenhum evento `whatsapp.inbound.message` ou `whatsapp.outbound.message`

### ETAPA 2: Mapeamento de eventos ✅

**Resultado:** Mapeamento está correto.

**Código:** `src/Controllers/WhatsAppWebhookController.php` linha 391-405

```php
private function mapEventType(string $gatewayEventType): ?string
{
    $mapping = [
        'message' => 'whatsapp.inbound.message',  // ✅ Mapeado corretamente
        'message.ack' => 'whatsapp.delivery.ack',
        'connection.update' => 'whatsapp.connection.update',  // ✅ Funcionando
        'message.sent' => 'whatsapp.outbound.message',
        'message_sent' => 'whatsapp.outbound.message',
        'sent' => 'whatsapp.outbound.message',
        'status' => 'whatsapp.delivery.status',
    ];
    return $mapping[$gatewayEventType] ?? null;
}
```

**Fluxo:**
1. Linha 39: Extrai `$eventType = $payload['event'] ?? $payload['type'] ?? null;`
2. Linha 146: Re-extrai `$eventType` (redundante mas ok)
3. Linha 158: Mapeia via `mapEventType($eventType)`
4. **Se mapear para null (linha 159-168):** Webhook responde 200 mas **NÃO GRAVA**

### ETAPA 3: EventIngestionService ✅

**Resultado:** Service está correto e não tem validações bloqueando.

- Localização: `src/Services/EventIngestionService.php`
- Validações: Apenas campos obrigatórios (event_type, source_system, payload)
- **Não bloqueia eventos de mensagem**

### ETAPA 4: Resolução de tenant_id ✅

**Resultado:** Canais estão habilitados corretamente.

**Canais encontrados:**
- ID: 1 | tenant_id: 2 | channel: ImobSites | enabled: 1
- ID: 3 | tenant_id: 2 | channel: Pixel12 Digital | enabled: 1
- ID: 4 | tenant_id: 121 | channel: pixel12digital | enabled: 1 ✅

**Resolução:**
- `resolveTenantByChannel('pixel12digital')` retorna `tenant_id=121` ✅

### ETAPA 5: Validação de webhook secret ⚠️

**Status:** Precisa verificar.

- Linha 121-133: Valida secret se configurado
- Se secret não bater: retorna 403 e não processa
- **AÇÃO:** Verificar se gateway está enviando secret correto

---

## 🚨 PROBLEMA IDENTIFICADO

### CAUSA RAIZ

**Eventos 'message' não estão chegando no webhook OU estão chegando mas sendo ignorados antes do mapeamento.**

**Possíveis causas:**

1. **Gateway não está enviando webhook para eventos 'message'** ⚠️
   - Gateway pode estar configurado para enviar apenas `connection.update`
   - Gateway pode ter webhook desabilitado para `message`

2. **Webhook está rejeitando eventos 'message' antes do mapeamento** ⚠️
   - Validação de secret pode estar falhando
   - Payload pode estar inválido
   - JSON pode estar malformado

3. **Eventos 'message' estão sendo ignorados após mapeamento** ⚠️
   - `mapEventType()` pode estar retornando `null` para eventos 'message'
   - Webhook responde 200 mas não grava (linha 159-168)

4. **Problema no código que foi alterado recentemente** 🔴
   - Usuário disse que **tudo estava funcionando antes das correções de envio**
   - Alguma alteração recente pode ter quebrado o recebimento

---

## 🔧 AÇÕES IMEDIATAS

### 1. Verificar logs do webhook

**Buscar logs `[HUB_WEBHOOK_IN]` para ver se eventos 'message' estão chegando:**

```bash
# Linux/Mac
grep "HUB_WEBHOOK_IN.*message" /var/log/php/error.log

# Windows (PowerShell)
Select-String -Path "C:\xampp\apache\logs\error.log" -Pattern "HUB_WEBHOOK_IN.*message"
```

**O que procurar:**
- `eventType=message` nos logs
- Se não encontrar: eventos 'message' não estão chegando
- Se encontrar: eventos estão chegando mas sendo ignorados

### 2. Verificar logs `[WHATSAPP INBOUND RAW]`

**Buscar logs de payload recebido:**

```bash
grep "WHATSAPP INBOUND RAW" /var/log/php/error.log | grep -i message
```

**O que procurar:**
- Payload com `event=message`
- Estrutura do payload
- Se payload está válido

### 3. Verificar se webhook secret está bloqueando

**Verificar resposta HTTP:**
- Se eventos 'message' estão retornando 403: secret está bloqueando
- Se eventos 'message' estão retornando 200: secret está ok, mas evento não é gravado

### 4. Testar webhook manualmente

**Criar script de teste:** `database/testar-webhook-manual.php`

**Enviar payload de teste:**
```json
{
  "event": "message",
  "session": {
    "id": "pixel12digital"
  },
  "message": {
    "from": "554796474223@c.us",
    "to": "554797309525@c.us",
    "text": "Envio0907",
    "id": "test_123"
  }
}
```

**Verificar:**
- HTTP code (deve ser 200)
- Response body (deve ter `success: true`)
- Se evento foi gravado no banco

### 5. Comparar código atual vs código anterior

**Verificar git diff (se disponível):**
```bash
git diff HEAD~5 src/Controllers/WhatsAppWebhookController.php
```

**O que procurar:**
- Mudanças na validação de `eventType`
- Mudanças na ordem das validações
- Mudanças no `mapEventType()`
- Mudanças que podem estar bloqueando eventos 'message'

---

## 📊 PRÓXIMOS PASSOS

### Prioridade 1: Verificar logs
1. Buscar logs `[HUB_WEBHOOK_IN]` para eventos 'message'
2. Verificar se eventos estão chegando
3. Se não encontrar: problema no gateway (não está enviando)
4. Se encontrar: problema no webhook (ignorando eventos)

### Prioridade 2: Testar webhook manualmente
1. Executar `database/testar-webhook-manual.php`
2. Verificar se evento é gravado
3. Se não gravar: investigar código do webhook
4. Se gravar: problema na comunicação gateway ↔ webhook

### Prioridade 3: Verificar alterações recentes
1. Comparar código atual vs código anterior
2. Identificar mudanças que podem ter quebrado recebimento
3. Reverter mudanças se necessário

---

## ✅ CHECKLIST DE VALIDAÇÃO

- [ ] Logs `[HUB_WEBHOOK_IN]` mostram eventos 'message' chegando?
- [ ] Logs `[WHATSAPP INBOUND RAW]` mostram payload com `event=message`?
- [ ] Webhook secret está configurado corretamente?
- [ ] Teste manual do webhook funciona (grava evento)?
- [ ] Código `mapEventType()` está mapeando 'message' corretamente?
- [ ] EventIngestionService está recebendo chamadas para eventos 'message'?
- [ ] Há erros/exceções nos logs relacionados a eventos 'message'?

---

## 🎯 CONCLUSÃO

**Status atual:**
- ✅ Webhook está recebendo requests
- ✅ Mapeamento de eventos está correto
- ✅ EventIngestionService está correto
- ✅ Resolução de tenant_id está correta
- ❌ **Eventos 'message' não estão chegando ou estão sendo ignorados**

**Próxima ação:** Verificar logs e testar webhook manualmente para identificar onde eventos 'message' estão sendo bloqueados.

---

**Documento gerado em:** 17/01/2026  
**Última atualização:** 17/01/2026  
**Versão:** 1.0

