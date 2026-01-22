# Arquitetura Assíncrona - WhatsApp Gateway

## 📋 Visão Geral

O WhatsApp Gateway segue uma arquitetura **event-driven** (orientada a eventos) com comunicação assíncrona entre o gateway e o PixelHub.

---

## 🔄 Fluxo de Comunicação

### 1. Envio de Mensagem (Outbound)

**Fluxo:**
```
PixelHub → Gateway → WhatsApp (envio)
     ↓
  ACK Imediato
     ↓
  correlationId (identificador principal)
```

**Retorno Síncrono:**
- ✅ `success: true/false`
- ✅ `correlationId` - Identificador principal para rastreamento
- ✅ `status` - Status HTTP da requisição
- ⚠️ `message_id` - **null** (não disponível no retorno síncrono)
- ⚠️ `event_id` - **null** (não disponível no retorno síncrono)

**Por que `message_id` e `event_id` são null?**
- O WhatsApp (WPPConnect/Baileys) **não retorna** o ID da mensagem de forma síncrona
- O ID só é conhecido após confirmação assíncrona via webhook
- O `correlationId` é o identificador principal para rastreamento inicial

---

### 2. Confirmação de Entrega (Webhook)

**Fluxo:**
```
WhatsApp → Gateway → PixelHub Webhook
     ↓
  Evento Assíncrono
     ↓
  message_id disponível
  event_id gerado
```

**Quando ocorre:**
- ✅ Mensagem entregue ao WhatsApp (`message.ack`)
- ✅ Mensagem lida pelo destinatário
- ✅ Mensagem recebida (inbound)

**Campos disponíveis após webhook:**
- ✅ `message_id` - ID único da mensagem no WhatsApp
- ✅ `event_id` - ID único do evento no PixelHub
- ✅ `correlationId` - Vincula com o envio original

---

## 🔌 Endpoints do Sistema

### Envio de Mensagem

**Endpoint:** `POST /api/messages/send`

**Resposta Síncrona:**
```json
{
  "success": true,
  "status": 200,
  "correlationId": "abc123xyz",
  "message_id": null,    // ← null é esperado!
  "event_id": null,      // ← null é esperado!
  "raw": { ... }
}
```

**Importante:**
- `message_id` e `event_id` só existem após confirmação assíncrona
- Use `correlationId` para rastrear o envio inicial
- Aguarde webhook para obter `message_id` e `event_id` finais

---

### Recebimento de Webhook (Real)

**Endpoint:** `POST /api/whatsapp/webhook`

**Este endpoint:**
- ✅ Recebe eventos reais do gateway
- ✅ Valida assinatura do webhook (se configurado)
- ✅ Insero evento na tabela `communication_events`
- ✅ Retorna sempre JSON, mesmo em erro

**Payload do Gateway:**
```json
{
  "event": "message",
  "channel_id": "channel123",
  "message": {
    "id": "msg_abc123",
    "from": "5511999999999",
    "text": "Mensagem recebida",
    "timestamp": 1234567890
  }
}
```

**Resposta do PixelHub:**
```json
{
  "success": true,
  "event_id": "evt_xyz789",
  "code": "SUCCESS"
}
```

---

### Simulação de Webhook (Testes)

**Endpoint:** `POST /settings/whatsapp-gateway/test/webhook`

**IMPORTANTE - Este endpoint é APENAS para testes internos:**
- ❌ **NÃO** valida assinatura real do gateway
- ❌ **NÃO** requer mensagem real enviada ao WhatsApp
- ✅ Apenas valida payload mínimo
- ✅ Insere evento fake na tabela de eventos
- ✅ Retorna sempre JSON, mesmo em erro

**Uso:**
- Testar fluxo de recebimento sem depender do WhatsApp real
- Verificar se eventos são inseridos corretamente
- Validar interface de visualização de eventos

**Diferenças do Webhook Real:**

| Aspecto | Webhook Real | Simulação |
|---------|--------------|-----------|
| Valida Assinatura | ✅ Sim | ❌ Não |
| Requer WhatsApp Real | ✅ Sim | ❌ Não |
| Insere Evento | ✅ Sim | ✅ Sim |
| `source_system` | `wpp_gateway` | `pixelhub_test` |
| `metadata.test` | ❌ Não | ✅ Sim |
| `metadata.simulated` | ❌ Não | ✅ Sim |

---

## 📊 Padrão de Resposta JSON

### Todos os Endpoints Retornam SEMPRE JSON

**Formato Padrão de Sucesso:**
```json
{
  "success": true,
  "code": "SUCCESS",
  "data": { ... },
  "message": "Operação realizada com sucesso"
}
```

**Formato Padrão de Erro:**
```json
{
  "success": false,
  "error": "Descrição do erro",
  "code": "ERROR_CODE",
  "message": "Mensagem adicional (opcional)"
}
```

**Códigos de Erro Comuns:**
- `UNAUTHORIZED` - Não autenticado
- `VALIDATION_ERROR` - Dados inválidos
- `INVALID_JSON` - JSON malformado
- `INVALID_SECRET` - Secret inválido
- `MISSING_EVENT_TYPE` - Tipo de evento ausente
- `INTERNAL_ERROR` - Erro interno do servidor

**Garantias:**
- ✅ **Nunca** retorna texto puro ou HTML
- ✅ **Sempre** retorna JSON válido
- ✅ **Sempre** define `Content-Type: application/json; charset=utf-8`
- ✅ **Sempre** limpa output buffer antes de enviar

---

## 🔍 Rastreamento de Mensagens

### Usando correlationId

O `correlationId` é o identificador principal para rastreamento inicial:

```javascript
// 1. Envia mensagem
const response = await sendMessage(...);
const correlationId = response.correlationId;

// 2. Aguarda webhook
// O webhook retornará o mesmo correlationId
// permitindo vincular envio com confirmação

// 3. Busca evento pelo correlationId
const event = await findEventByCorrelationId(correlationId);
// Agora temos: message_id e event_id
```

### Buscando Eventos

**Buscar por correlationId:**
```sql
SELECT * FROM communication_events 
WHERE JSON_EXTRACT(metadata, '$.correlation_id') = 'abc123xyz'
ORDER BY created_at DESC;
```

**Buscar por message_id:**
```sql
SELECT * FROM communication_events 
WHERE JSON_EXTRACT(payload, '$.message.id') = 'msg_abc123'
ORDER BY created_at DESC;
```

---

## ⚠️ Pontos Importantes

### 1. Comportamento Assíncrono

- ✅ `message_id` e `event_id` são **assíncronos**
- ✅ `correlationId` é **síncrono** (retornado imediatamente)
- ✅ Use `correlationId` para rastreamento inicial
- ✅ Aguarde webhook para obter IDs finais

### 2. Simulação vs Real

- ✅ **Simulação**: Apenas para testes internos
- ✅ **Real**: Requer WhatsApp real e valida assinatura
- ❌ Não confie na simulação para produção
- ❌ Não use simulação para mensagens reais

### 3. Tratamento de Erros

- ✅ Todos os endpoints retornam JSON, mesmo em erro
- ✅ Frontend deve tratar `success === false`
- ✅ Frontend deve capturar erros de parse JSON
- ✅ Sempre exibir código de erro quando disponível

---

## 🧪 Exemplos de Uso

### Exemplo 1: Envio e Rastreamento

```javascript
// Envia mensagem
const response = await fetch('/api/messages/send', {
  method: 'POST',
  body: JSON.stringify({
    channel_id: 'channel123',
    to: '5511999999999',
    text: 'Olá!'
  })
});

const data = await response.json();

if (data.success) {
  console.log('Correlation ID:', data.correlationId);
  console.log('Message ID:', data.message_id); // null (esperado)
  
  // Aguarda webhook (assíncrono)
  // O webhook retornará o message_id real
}
```

### Exemplo 2: Simulação de Webhook

```javascript
// Simula webhook (apenas testes)
const response = await fetch('/settings/whatsapp-gateway/test/webhook', {
  method: 'POST',
  body: new FormData(form)
});

const data = await response.json();

if (data.success) {
  console.log('Event ID:', data.event_id);
  console.log('Mensagem:', data.message);
} else {
  console.error('Erro:', data.error);
  console.error('Código:', data.code);
}
```

---

## 📝 Checklist de Integração

Ao integrar com o WhatsApp Gateway:

- [ ] Usar `correlationId` para rastreamento inicial
- [ ] Não depender de `message_id` no retorno síncrono
- [ ] Aguardar webhook para obter IDs finais
- [ ] Tratar erros sempre verificando `success === false`
- [ ] Capturar erros de parse JSON no frontend
- [ ] Não usar simulação em produção
- [ ] Validar que todos os endpoints retornam JSON

---

**Última atualização:** Janeiro 2025

