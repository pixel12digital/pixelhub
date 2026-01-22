# Problema: Discrepância de Timestamp - Conversa Aguiar 21/01 14:18

## Problema Identificado

A conversa do tenant "Aguiar" aparece no sistema com data/hora **21/01 14:18**, mas não existe no histórico do WhatsApp neste horário.

## Causa Raiz

**Problema de conversão de timezone:**

1. O timestamp do webhook do WhatsApp é `1769015931` (Unix timestamp)
2. Quando convertido diretamente, resulta em `2026-01-21 18:18:51` (horário UTC)
3. O sistema está armazenando `2026-01-21 14:18:51` (horário local, UTC-4)
4. **Diferença: 4 horas** (14400 segundos)

### Detalhes Técnicos

**Evento analisado:**
- Event ID: `88a09fc9-74bb-4713-8260-0d7f766ddb9a`
- Tipo: `whatsapp.inbound.message`
- Criado em: `2026-01-21 14:18:45` (horário do servidor)
- Timestamp do payload: `1769015931`
- Timestamp convertido (UTC): `2026-01-21 18:18:51`
- `last_message_at` armazenado: `2026-01-21 14:18:51` (horário local)

**Função responsável:**
- `ConversationService::extractMessageTimestamp()` (linha 1273)
- A função converte o timestamp Unix diretamente usando `date()`, que usa o timezone do servidor PHP

## Por Que Não Aparece no WhatsApp?

O usuário verifica o histórico do WhatsApp no horário local (14:18), mas a mensagem real foi enviada/recebida às **18:18 UTC**, que corresponde a **14:18 no horário local**. 

Porém, há uma discrepância porque:
- O sistema está armazenando o timestamp convertido para o timezone do servidor
- Mas o WhatsApp pode estar usando um timezone diferente ou o timestamp pode estar incorreto

## Soluções Possíveis

### Opção 1: Armazenar timestamp em UTC e converter na exibição (RECOMENDADO)

**Vantagens:**
- Mantém consistência com timestamps do WhatsApp
- Facilita comparações e ordenações
- Evita problemas de conversão

**Implementação:**
1. Modificar `extractMessageTimestamp()` para sempre retornar UTC
2. Converter para timezone local apenas na exibição (frontend)

### Opção 2: Ajustar timezone do servidor PHP

**Vantagens:**
- Solução rápida
- Não requer mudanças no código

**Desvantagens:**
- Pode afetar outras partes do sistema
- Não resolve o problema de forma definitiva

### Opção 3: Usar timestamp do `created_at` do evento

**Vantagens:**
- Usa o horário real de recebimento do webhook
- Mais preciso para rastreamento

**Desvantagens:**
- Não reflete o horário real da mensagem no WhatsApp
- Pode ter delay se o webhook chegar tarde

## Recomendação

**Implementar Opção 1**: Armazenar timestamps em UTC e converter apenas na exibição.

### Mudanças Necessárias

1. **Modificar `ConversationService::extractMessageTimestamp()`:**
   ```php
   // Usar UTC explicitamente
   date_default_timezone_set('UTC');
   $datetime = date('Y-m-d H:i:s', $timestamp);
   ```

2. **Converter na exibição (frontend):**
   - JavaScript já faz conversão de timezone automaticamente ao criar `Date` objects
   - Verificar se está usando corretamente

3. **Verificar timezone do servidor:**
   ```php
   echo date_default_timezone_get(); // Deve retornar 'UTC' ou ajustar
   ```

## Scripts de Diagnóstico

- `database/check-aguiar-timestamp-detail.php` - Analisa timestamp específico
- `database/diagnose-aguiar-timestamp-21jan.php` - Diagnóstico completo
- `database/diagnose-aguiar-messages.php` - Análise geral da conversa

## Solução Implementada

**Correção aplicada em `ConversationService::extractMessageTimestamp()`:**

A função agora converte timestamps Unix explicitamente para UTC antes de armazenar no banco de dados. Isso garante que:
- Todos os timestamps sejam armazenados de forma consistente
- O frontend possa converter para o timezone local na exibição
- Não haja discrepâncias entre diferentes timezones do servidor

**Mudanças:**
- Salva timezone atual antes de converter
- Define UTC explicitamente para conversão
- Restaura timezone original após conversão
- Aplica mesmo tratamento ao fallback (NOW())

## Próximos Passos

1. ✅ Identificar problema (CONCLUÍDO)
2. ✅ Implementar correção (armazenar em UTC) (CONCLUÍDO)
3. ⏳ Testar com novas mensagens
4. ⏳ Verificar se timestamps antigos precisam ser corrigidos
5. ⏳ Validar com usuário

## Nota Importante

**Timestamps antigos não serão corrigidos automaticamente.** Apenas novas mensagens recebidas após esta correção terão timestamps em UTC. Se necessário, pode-se criar um script de migração para corrigir timestamps antigos, mas isso deve ser feito com cuidado para não afetar outras funcionalidades.

