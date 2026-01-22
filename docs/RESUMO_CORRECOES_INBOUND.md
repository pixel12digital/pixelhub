# Resumo das Correções: Mensagens Inbound Não Chegam no Sistema

## Problema Reportado

**Situação:**
- ✅ Mensagens encaminhadas do número **554796164699** funcionam (chegam em ImobSites e Pixel12 Digital)
- ❌ Mensagens enviadas diretamente (como usuário real) de outro número não chegam no Pixel Hub
- ✅ WhatsApp recebe as mensagens, mas o sistema Pixel Hub não processa

**Números mencionados:**
- Funciona: 554796164699 (Charles)
- Não funciona: Provavelmente 554796474223 (ServPro) ou outro número

## Correções Implementadas

### 1. Melhoria na Extração do Campo `from` no Webhook

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php`

**Antes:**
```php
$from = $payload['from'] ?? $payload['message']['from'] ?? $payload['data']['from'] ?? null;
```

**Depois:**
```php
$from = $payload['from'] 
    ?? $payload['message']['from'] 
    ?? $payload['data']['from']
    ?? $payload['raw']['payload']['from']
    ?? $payload['message']['key']['remoteJid']  // NOVO
    ?? $payload['data']['key']['remoteJid']     // NOVO
    ?? $payload['raw']['payload']['key']['remoteJid']  // NOVO
    ?? $payload['message']['key']['participant']  // NOVO (para grupos)
    ?? $payload['data']['key']['participant']     // NOVO
    ?? null;
```

### 2. Melhoria na Extração do Campo `from` no ConversationService

**Arquivo:** `src/Services/ConversationService.php`

Adicionados múltiplos caminhos de busca, incluindo:
- `payload['message']['key']['remoteJid']` - Estrutura comum do WhatsApp Web API
- `payload['data']['key']['remoteJid']`
- `payload['raw']['payload']['key']['remoteJid']`
- `payload['message']['key']['participant']` - Para grupos
- E outros caminhos alternativos

### 3. Busca Recursiva de Fallback

**Nova função:** `ConversationService::findPhoneOrJidRecursively()`

Quando nenhum caminho padrão funciona, a função busca recursivamente por campos que podem conter número/JID:
- Procura por chaves: `from`, `to`, `remoteJid`, `participant`, `author`, `jid`, `phone`, `number`, `sender`
- Busca em toda a estrutura do payload (até 5 níveis de profundidade)

### 4. Logs Melhorados

Adicionados logs detalhados em pontos críticos:
- Qual caminho foi usado para extrair o `from`
- Quando busca recursiva é usada
- Payload completo quando não encontra `from` (primeiros 800 chars)

## Fluxo de Processamento

1. **Webhook recebe mensagem** → `WhatsAppWebhookController::handle()`
   - Extrai `from` de múltiplos caminhos
   - Loga: `[HUB_WEBHOOK_IN]`

2. **EventIngestionService** → Grava evento no banco
   - Loga: `[HUB_MSG_SAVE]` ou `[HUB_MSG_SAVE_OK]`

3. **ConversationService::resolveConversation()** → Processa conversa
   - Extrai `from` novamente (pode ter estrutura diferente do webhook)
   - Normaliza número (remove @c.us, converte para E.164, etc.)
   - Cria/atualiza conversa no banco
   - Loga: `[CONVERSATION UPSERT] extractChannelInfo`

## Como Testar

### 1. Envie uma mensagem do número problemático

Envie uma mensagem diretamente (não encaminhada) do número que não está funcionando para uma das sessões (ImobSites ou Pixel12 Digital).

### 2. Verifique os logs

Procure pelos seguintes logs (arquivo: `logs/pixelhub.log` ou logs do PHP):

```
[HUB_WEBHOOK_IN] ... from=... normalized_from=...
[CONVERSATION UPSERT] extractChannelInfo: WhatsApp inbound - rawFrom: ...
```

### 3. Execute scripts de diagnóstico

```bash
php database/quick-check-inbound-format.php
php database/diagnose-inbound-number-format.php
```

### 4. Verifique no banco

Execute query para verificar se evento foi criado:

```sql
SELECT id, event_type, created_at, 
       JSON_EXTRACT(payload, '$.from') as from_field,
       JSON_EXTRACT(metadata, '$.channel_id') as channel_id
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC
LIMIT 10;
```

## Possíveis Problemas Restantes

Se após as correções ainda não funcionar, pode ser:

1. **Estrutura completamente diferente do payload:**
   - O número pode estar em um campo que não estamos verificando
   - Pode ser um formato de número diferente (sem @c.us, sem sufixo, etc.)

2. **Webhook não está chegando:**
   - Gateway pode não estar enviando webhook para o Pixel Hub
   - URL do webhook pode estar incorreta

3. **Filtro/Validação bloqueando:**
   - Alguma validação pode estar rejeitando o evento
   - Pode ser problema de tenant_id não resolvido

## Próximos Passos

1. **Teste enviando uma mensagem** do número problemático
2. **Compartilhe os logs** com os prefixos:
   - `[HUB_WEBHOOK_IN]`
   - `[CONVERSATION UPSERT]`
   - `[WEBHOOK INSTRUMENTADO]`
3. **Execute os scripts de diagnóstico** e compartilhe os resultados

Com essas informações, poderemos identificar exatamente onde está o problema e corrigi-lo.








