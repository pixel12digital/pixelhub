# Timeout Resilience - Sistema de Cobrança

**Data de Implementação:** 27/02/2026  
**Problema Resolvido:** Notificações de cobrança marcadas como "failed" apesar da mensagem ter sido enviada com sucesso

## Contexto do Problema

### Situação Identificada
- **Notificação ID 196** (Renato Silva da Silva Júnior, 2026-02-27 08:50:31) foi marcada como "failed"
- A mensagem **foi enviada com sucesso** e aparece no Inbox e no WhatsApp
- Causa raiz: **Timeout de 30s** na requisição ao gateway WhatsApp
- O gateway enviou a mensagem, mas não respondeu a tempo ao PixelHub

### Impacto
- Inconsistência entre status interno e realidade
- Mensagens aparecem como falhas quando na verdade foram enviadas
- Dificulta auditoria e rastreamento de cobranças

## Solução Implementada

### 1. Novo Status: `sent_uncertain`

**Migration:** `20260227_alter_billing_notifications_add_sent_uncertain_status.php`

Adicionado novo status ao ENUM de `billing_notifications.status`:
- `pending`: Pendente
- `sent`: Enviado com confirmação
- **`sent_uncertain`**: Enviado (confirmação pendente) - **NOVO**
- `failed`: Falha real

### 2. Timeout Resilience no BillingSenderService

**Arquivo:** `src/Services/BillingSenderService.php`

#### Lógica Implementada

Quando ocorre **timeout** (`error_code === 'TIMEOUT'`) no envio de mensagem de texto:

1. **Registra notificação como `sent_uncertain`**
   - Novo método: `recordUncertainNotification()`
   - Salva mensagem, erro e timestamp

2. **Cria evento no Inbox com `delivery_uncertain=true`**
   - Usa `EventIngestionService::ingest()`
   - Metadata inclui:
     - `delivery_uncertain: true`
     - `timeout_at: timestamp`
     - `billing_auto_send: true`
     - `invoice_id: ID da fatura`

3. **Marca como sucesso parcial**
   - `result['success'] = true`
   - `result['uncertain'] = true`
   - Mensagem aparece no Inbox normalmente

#### Código Relevante

```php
if ($errorCode === 'TIMEOUT') {
    // Registra como 'sent_uncertain'
    $notificationId = self::recordUncertainNotification(
        $db, $tenantId, $invoice['id'], 'whatsapp_inbox',
        $triggeredBy, $dispatchRuleId, $error, $messageBody
    );
    
    // Cria evento no Inbox com delivery_uncertain=true
    EventIngestionService::ingest([
        'event_type' => 'whatsapp.outbound.message',
        'source_system' => 'pixelhub_operator',
        'payload' => $timeoutEventPayload,
        'tenant_id' => $tenantId,
        'metadata' => [
            'delivery_uncertain' => true,
            'timeout_at' => date('Y-m-d H:i:s'),
            // ...
        ]
    ]);
    
    $result['success'] = true;
    $result['uncertain'] = true;
}
```

### 3. Atualização da View de Auditoria

**Arquivo:** `views/billing_collections/notifications_log.php`

#### Mudanças

1. **Filtro de Status**
   - Adicionada opção "Enviado (confirmação pendente)"

2. **Exibição na Tabela**
   - Badge laranja (`#fff3cd` background, `#856404` text)
   - Label: "Enviado (confirmação pendente)"
   - Background da linha: `#fffbf0` (amarelo claro)

3. **Visual**
   ```
   Status: [Enviado (confirmação pendente)]
   Cor: Laranja/Amarelo
   Destaque: Linha com fundo amarelo claro
   ```

## Arquitetura da Solução

### Fluxo Normal (Sem Timeout)

```
BillingSenderService
  → WhatsAppGatewayClient.sendText()
  → Gateway responde em < 30s
  → Success = true
  → Registra 'sent' em billing_notifications
  → Cria evento no Inbox
```

### Fluxo com Timeout Resilience

```
BillingSenderService
  → WhatsAppGatewayClient.sendText()
  → Gateway NÃO responde em 30s (timeout)
  → error_code = 'TIMEOUT'
  → Detecta timeout resilience
  → Registra 'sent_uncertain' em billing_notifications
  → Cria evento no Inbox com delivery_uncertain=true
  → Success = true (parcial)
  → Mensagem aparece no Inbox normalmente
```

### Fluxo com Erro Real

```
BillingSenderService
  → WhatsAppGatewayClient.sendText()
  → Erro real (não timeout)
  → Success = false
  → Registra 'failed' em billing_notifications
  → NÃO cria evento no Inbox
```

## Segurança e Validações

### ✅ Aplicado APENAS para:
- **Mensagens de texto** (não mídia)
- **Erro de timeout** (`error_code === 'TIMEOUT'`)
- Gateway provavelmente enviou mas não respondeu

### ❌ NÃO aplicado para:
- Erros reais (validação, permissão, etc.)
- Mídia (áudio/imagem/vídeo) - upload pode ter falhado
- Outros códigos de erro

## Otimizações Identificadas

### Timeout de 30s para Mensagens de Texto

**Análise do código:**
- `WhatsAppGatewayClient` usa timeout de 30s (configurável no construtor)
- Para **mensagens de texto**, 30s é muito tempo
- Para **áudio**, o timeout é aumentado para 120s (correto)

**Possíveis causas de timeout:**
1. Gateway VPS sobrecarregado
2. Rede lenta entre PixelHub (HostMídia) e Gateway (VPS Hostinger)
3. Processamento lento no WPPConnect Server
4. Nginx timeout no gateway (configurado para ~60s)

**Recomendações futuras:**
- Monitorar logs de timeout (`WHATSAPP_TIMEOUT` em `billing_dispatch.log`)
- Investigar performance do gateway se timeouts forem frequentes
- Considerar aumentar timeout do Nginx no gateway (atualmente ~60s)

## Logs e Monitoramento

### Logs Criados

1. **`WHATSAPP_TIMEOUT`**
   ```
   Timeout detectado - aplicando timeout resilience
   tenant_id, invoice_id, phone
   ```

2. **`INBOX_EVENT_TIMEOUT_CREATED`**
   ```
   Evento de timeout criado no Inbox
   tenant_id, invoice_id, phone
   ```

3. **`INBOX_EVENT_TIMEOUT_FAIL`**
   ```
   Erro ao criar evento de timeout (fallback)
   ```

### Auditoria

- Filtrar por status `sent_uncertain` na view de auditoria
- Badge laranja indica confirmação pendente
- Campo `last_error` mostra mensagem de timeout

## Testes Recomendados

### Cenário 1: Timeout Real
1. Simular timeout no gateway (delay > 30s)
2. Verificar se notificação é criada como `sent_uncertain`
3. Verificar se mensagem aparece no Inbox
4. Verificar badge laranja na auditoria

### Cenário 2: Erro Real
1. Simular erro de validação (número inválido)
2. Verificar se notificação é criada como `failed`
3. Verificar que mensagem NÃO aparece no Inbox

### Cenário 3: Sucesso Normal
1. Envio normal sem timeout
2. Verificar se notificação é criada como `sent`
3. Verificar se mensagem aparece no Inbox

## Arquivos Modificados

### Criados
- `database/migrations/20260227_alter_billing_notifications_add_sent_uncertain_status.php`
- `docs/TIMEOUT_RESILIENCE_BILLING.md` (este arquivo)

### Modificados
- `src/Services/BillingSenderService.php`
  - Adicionada lógica de timeout resilience (linhas 286-369)
  - Novo método `recordUncertainNotification()` (linhas 582-606)
- `views/billing_collections/notifications_log.php`
  - Filtro de status atualizado (linha 113)
  - Cores e labels atualizados (linha 168)
  - Background da linha atualizado (linha 205)

## Compatibilidade

### Banco de Dados
- ✅ Migration executada com sucesso
- ✅ ENUM atualizado sem quebrar registros existentes
- ✅ Retrocompatível com status antigos

### Código
- ✅ Não quebra fluxos existentes
- ✅ Apenas adiciona novo comportamento para timeout
- ✅ Logs adicionais para debug

## Conclusão

A implementação de **timeout resilience** resolve o problema de notificações marcadas como "failed" quando na verdade foram enviadas. O sistema agora:

1. ✅ Detecta timeouts automaticamente
2. ✅ Registra como `sent_uncertain` (não como `failed`)
3. ✅ Cria evento no Inbox para aparecer na conversa
4. ✅ Exibe status claro na auditoria ("Enviado (confirmação pendente)")
5. ✅ Mantém 100% de segurança (não aplica para erros reais ou mídia)

**Próximos passos:**
- Monitorar frequência de timeouts
- Investigar otimizações no gateway se necessário
- Considerar aumentar timeout do Nginx no gateway VPS
