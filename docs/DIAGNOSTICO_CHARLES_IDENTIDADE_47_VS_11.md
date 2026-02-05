# Diagnóstico: Charles Dietrich - Identidade 47 vs 11

## Hipótese confirmada: H2 (merge por nome com external_id diferente)

O sistema fazia **merge por nome** (`findConversationByContactName`) sem validar equivalência de `contact_external_id`. Isso associou a mensagem de Charles (47) à conversa antiga com número 11.

## Evidência

### Evento teste1310 (2026-02-05 13:12:07)
- **event_id:** 0c4efde7-92a1-4316-ad95-353e24966e31
- **conversation_id:** 140
- **Payload:**
  - `message.from`: **208989199560861@lid** (Charles 47)
  - `raw.payload.from`: **208989199560861@lid**
  - `raw.payload.sender.id`: **208989199560861@lid**
  - `raw.payload.notifyName`: Charles Dietrich

### Conversa 140 (antes da correção)
- **contact_external_id:** 5511940863773 (número 11 – incorreto)
- **contact_name:** Charles Dietrich
- **channel_id:** ImobSites

### Origem do número exibido
O Inbox exibe o telefone a partir de `conversations.contact_external_id` (via `ContactHelper::formatContactId`). Como a conversa tinha `contact_external_id=5511940863773`, exibia (11) 94086-3773 em vez de (47) 99616-4699.

## Correções aplicadas

### 1. ConversationService (patch mínimo)
- **findConversationByContactName:** passa a exigir mesmo `channel_id` e equivalência de `contact_external_id`
- **contactExternalIdsAreEquivalent:** novo método que valida equivalência (mesmo número, variação 9º dígito, ou mapeamento @lid→phone em `whatsapp_business_ids`)
- **Regra:** nunca fazer merge por nome quando os external_ids são diferentes

### 2. Script de correção retroativa
```bash
php database/fix-conversation-140-charles-identidade.php
```
- Atualiza `conversations.contact_external_id` para `208989199560861@lid`
- Cria mapeamento em `whatsapp_business_ids` para exibição (47) 99616-4699

## Regras de identidade (obrigatórias)

| Regra | Descrição |
|-------|-----------|
| Identidade | `external_id` (JID/telefone) + `channel_type` + `channel_id`/`tenant_id` |
| Nome | Apenas label; nunca usar para dedupe |
| Inbound | Interlocutor = sender (`from`/`remoteJid`) |
| Outbound | Interlocutor = destino (`to`) |

## Teste de validação

1. Enviar mensagem do número **47996164699** para o canal ImobSites (+55 47 9714-6908)
2. **Esperado:** card "Charles Dietrich" exibe **(47) 99616-4699**
3. **Não esperado:** (11) 94086-3773

## Garantia de não regressão

- `findEquivalentConversation` (9º dígito) e `findConversationByLidPhoneMapping` continuam ativos
- Apenas `findConversationByContactName` foi restrito com validação de equivalência e `channel_id`
- Multi-tenant e separação por canal preservados
