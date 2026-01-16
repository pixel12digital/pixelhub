# Diagnóstico: Problema com Interpretação do Número de Origem

## Problema Identificado

Quando uma mensagem é enviada como usuário real (não encaminhada):
- ✅ WhatsApp recebe a mensagem
- ❌ Pixel Hub NÃO processa/recebe a mensagem no sistema

**Observação importante:**
- ✅ Mensagens encaminhadas do número **554796164699** funcionam (chegam em ImobSites e Pixel12 Digital)
- ❌ Mensagens enviadas diretamente de outro número (provavelmente **554796474223** ou outro) não chegam no Pixel Hub

## Causa Raiz Suspeita

O problema está na **interpretação do número de origem** no payload do webhook. Diferentes números ou tipos de envio podem vir em **formatos diferentes** no payload, e o sistema pode não estar extraindo corretamente o campo `from` para alguns formatos.

## Correções Implementadas

### 1. Melhorias no `ConversationService::extractChannelInfo()`

**Antes:** Buscava apenas alguns caminhos específicos:
```php
$rawFrom = $payload['message']['from'] 
    ?? $payload['from'] 
    ?? $payload['data']['from'] 
    ?? $payload['raw']['payload']['from']
    ?? $payload['raw']['from'] ?? null;
```

**Depois:** Busca em múltiplos caminhos, incluindo estruturas do WhatsApp Web API:
```php
$rawFrom = $payload['message']['from'] 
    ?? $payload['from'] 
    ?? $payload['data']['from'] 
    ?? $payload['raw']['payload']['from']
    ?? $payload['raw']['from']
    // Caminhos alternativos: message.key.remoteJid (comum no WhatsApp Web API)
    ?? $payload['message']['key']['remoteJid']
    ?? $payload['data']['key']['remoteJid']
    ?? $payload['raw']['payload']['key']['remoteJid']
    // Para grupos: message.key.participant
    ?? $payload['message']['key']['participant']
    ?? $payload['data']['key']['participant']
    ?? $payload['raw']['payload']['key']['participant']
    ?? null;
```

### 2. Busca Recursiva de Fallback

Adicionada função `findPhoneOrJidRecursively()` que busca recursivamente por campos que podem conter número/JID quando a estrutura não segue os padrões esperados.

Esta função procura por chaves comuns como:
- `from`, `to`, `remoteJid`, `participant`, `author`, `jid`, `phone`, `number`, `sender`

### 3. Logs Melhorados

Adicionados logs mais detalhados para:
- Identificar qual caminho foi usado para extrair o `from`
- Mostrar payload completo quando não encontra `from`
- Facilitar diagnóstico de problemas futuros

## Scripts de Diagnóstico Criados

### `database/diagnose-inbound-number-format.php`
Compara eventos do número que funciona (554796164699) vs números que não funcionam, mostrando:
- Formato do campo `from` em cada caso
- Estrutura do payload
- Diferenças encontradas

### `database/test-inbound-payload-structure.php`
Analisa estrutura completa de payloads inbound recentes, testando todos os caminhos possíveis para extrair `from`.

## Próximos Passos para Investigação

1. **Execute o diagnóstico:**
   ```bash
   php database/diagnose-inbound-number-format.php
   php database/test-inbound-payload-structure.php
   ```

2. **Envie uma mensagem do número problemático** e verifique:
   - Se o webhook chegou (logs do `WhatsAppWebhookController`)
   - Qual foi a estrutura do payload recebido
   - Se o `from` foi extraído corretamente

3. **Compare os logs:**
   - Busque logs com `[CONVERSATION UPSERT] extractChannelInfo`
   - Verifique se o erro é `failed_missing_from` ou outro
   - Compare estrutura de payload entre números que funcionam vs que não funcionam

## Possíveis Causas

1. **Formato diferente do número:**
   - Número que funciona: vem como `554796164699@c.us`
   - Número problemático: vem em formato diferente (sem `@c.us`, em outro campo, etc.)

2. **Estrutura diferente do payload:**
   - Mensagens encaminhadas vs mensagens diretas podem ter estrutura diferente
   - WhatsApp Business vs WhatsApp comum pode ter campos diferentes

3. **Campo em local diferente:**
   - O número pode estar em `payload['message']['key']['remoteJid']` ao invés de `payload['message']['from']`
   - Pode estar em `payload['data']` ou outra estrutura aninhada

## Como Verificar se a Correção Funcionou

1. Envie uma mensagem do número problemático
2. Verifique os logs para ver:
   ```
   [CONVERSATION UPSERT] extractChannelInfo: Encontrado via busca recursiva: ...
   ```
3. Verifique se uma conversa foi criada/atualizada no banco
4. Verifique se a mensagem aparece no painel de comunicação

