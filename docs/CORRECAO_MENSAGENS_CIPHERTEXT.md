# Correção: Mensagens Criptografadas Não Decodificadas no Inbox

**Data**: 26/02/2026  
**Problema**: Mensagens inbound aparecem no Inbox mas sem conteúdo de texto  
**Conversa afetada**: Kelly Costa (ID 482, +55 41 9505-9936)  
**Mensagem esperada**: "Olá! Sou Kelly . Tenho uma loja de Moda & Acessórios..." (221 chars)

## Diagnóstico Completo

### Webhooks Recebidos do WPPConnect

O WPPConnect enviou **3 webhooks** para a mesma mensagem (message_id: `false_65803193995481@lid_ACAE2BABE023A6D8AF75356A7E13B61A`):

1. **Webhook #48216** (04:02:03.xxx) - ❌ PROBLEMA
   - Tipo: `ciphertext` (subtype: `fanout`)
   - Body: **vazio**
   - Chegou **primeiro**

2. **Webhook #48219** (04:02:03.xxx) - ✅ TEM O TEXTO
   - Tipo: `chat`
   - Body: "Olá! Sou Kelly . Tenho uma loja de Moda & Acessórios..." (221 chars)
   - Chegou **depois**

3. **Webhook #48220** (04:02:04.xxx) - ✅ TEM O TEXTO (duplicado)
   - Tipo: `chat`
   - Body: "Olá! Sou Kelly . Tenho uma loja de Moda & Acessórios..." (221 chars)
   - Chegou **por último**

### Evento Criado no PixelHub

Apenas **1 evento** foi criado em `communication_events`:

- **Evento #191084** - processou o webhook `ciphertext` (vazio)
- Os webhooks `chat` foram **bloqueados pela deduplicação**

### Causa Raiz

**Problema de Ordem + Deduplicação:**

1. O `EventIngestionService` usa **deduplicação por `idempotency_key`** baseada no `message_id`
2. Todos os 3 webhooks têm o **mesmo `message_id`**
3. O webhook `ciphertext` (vazio) chegou **primeiro** → criou evento #191084
4. Os webhooks `chat` (com texto) chegaram **depois** → **bloqueados** (idempotency_key duplicada)
5. Resultado: Inbox mostra mensagem vazia

**Por que o WPPConnect envia múltiplos webhooks?**

O WPPConnect pode enviar webhooks duplicados quando:
- Mensagem é recebida antes da descriptografia E2E completar (`ciphertext`)
- Mensagem é descriptografada depois e reenviada (`chat`)
- Sincronização de dispositivos gera eventos duplicados

### Outros Eventos da Conversa

Além do evento vazio, foram recebidos:

2. **Evento #191085** - `notification_template` (cartão de contato) - Body vazio
3. **Evento #191086** - `e2e_notification` (notificação E2E) - Body vazio
4. **Evento #191087** - `whatsapp.outbound.message` (resposta automática Pixel12) - ✅ OK

## Solução Implementada

Adicionado **filtro no WhatsAppWebhookController** (linhas 194-221) para **ignorar tipos de mensagem não-textuais ANTES da deduplicação**:

```php
// ─── FILTRO: Ignora tipos de mensagem não-textuais ───
// IMPORTANTE: Este filtro roda ANTES da deduplicação por idempotency_key
// Isso garante que webhooks 'ciphertext' (vazios) sejam ignorados e não bloqueiem
// os webhooks 'chat' (com texto) que chegam depois
if ($eventType === 'message') {
    $messageType = $payload['raw']['payload']['type'] ?? $payload['type'] ?? null;
    $skipTypes = [
        'ciphertext',           // Mensagem criptografada não decodificada
        'notification_template', // Notificação de sistema (cartão de contato, etc)
        'e2e_notification',     // Notificação de criptografia E2E
        'protocol',             // Mensagens de protocolo WhatsApp
        'revoked',              // Mensagens apagadas
    ];
    
    if (in_array($messageType, $skipTypes, true)) {
        // Responde 200 mas NÃO processa (não chega no EventIngestionService)
        exit;
    }
}
```

### Como a Solução Resolve o Problema

**Fluxo ANTES da correção:**
1. Webhook `ciphertext` (vazio) → processado → cria evento #191084
2. Webhook `chat` (com texto) → **bloqueado** (idempotency_key duplicada)
3. Resultado: Inbox mostra mensagem vazia ❌

**Fluxo DEPOIS da correção:**
1. Webhook `ciphertext` (vazio) → **filtrado e ignorado** (200 OK, mas não processa)
2. Webhook `chat` (com texto) → processado → cria evento com texto ✅
3. Webhook `chat` duplicado → bloqueado (idempotency_key duplicada - normal)
4. Resultado: Inbox mostra mensagem completa ✅

### Tipos Filtrados

- **`ciphertext`**: Mensagens criptografadas que o WPPConnect não conseguiu descriptografar (problema de sincronização de chaves E2E)
- **`notification_template`**: Notificações de sistema (cartões de contato, etc)
- **`e2e_notification`**: Notificações de criptografia E2E
- **`protocol`**: Mensagens de protocolo WhatsApp
- **`revoked`**: Mensagens apagadas

## Impacto

### Antes da Correção
- Webhook `ciphertext` (vazio) processado primeiro bloqueava webhook `chat` (com texto)
- Mensagens vazias apareciam no Inbox
- Atendente via conversa mas sem conteúdo
- Texto da mensagem existia no webhook mas não era exibido

### Depois da Correção
- Webhooks `ciphertext` são ignorados antes da deduplicação
- Webhooks `chat` (com texto) são processados normalmente
- Apenas mensagens com conteúdo real aparecem no Inbox
- Gateway recebe 200 OK (não tenta reenviar)

## Problema de Criptografia

O evento `ciphertext` indica que a **sessão WhatsApp perdeu as chaves de criptografia E2E**. Isso pode acontecer quando:

1. Sessão foi desconectada e reconectada
2. WhatsApp atualizou e resetou chaves
3. Dispositivo foi trocado/resetado
4. Sincronização de chaves falhou

### Recomendação

Se mensagens `ciphertext` aparecerem com frequência nos logs:
1. Verificar status da sessão no gateway
2. Considerar reconectar a sessão WhatsApp
3. Monitorar logs para padrão de falhas

## Arquivos Modificados

- `src/Controllers/WhatsAppWebhookController.php` (linhas 194-221)

## Logs

Mensagens filtradas geram log:
```
[WEBHOOK_FILTER] Ignorando mensagem tipo "ciphertext" (não-textual) - from=65803193995481@lid
```

## Teste

Para testar, enviar mensagem de um novo contato e verificar:
1. Apenas mensagens de texto aparecem no Inbox
2. Notificações de sistema não aparecem
3. Logs mostram filtros aplicados
