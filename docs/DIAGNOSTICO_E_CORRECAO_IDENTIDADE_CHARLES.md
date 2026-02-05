# Diagnóstico e Correção: Identidade Charles Dietrich (47 vs 11)

## Hipótese confirmada: H2

**Problema:** Conversa 140 exibia (11) 94086-3773 em vez de (47) 99616-4699.

**Causa:** `findConversationByContactName` fazia merge por **nome** sem validar equivalência de `external_id`. Mensagem com `from=208989199560861@lid` (Charles 47) foi associada a conversa existente com `contact_external_id=5511940863773` (Charles 11) por terem o mesmo `contact_name`.

## Evidência

| Campo | Valor no payload (teste1310) | Valor na conversa 140 (errado) |
|-------|------------------------------|--------------------------------|
| from / remoteJid | 208989199560861@lid | - |
| contact_external_id | - | 5511940863773 |
| notifyName | Charles Dietrich | Charles Dietrich |

**Regra violada:** Contato resolvido por nome, ignorando que `208989199560861@lid` ≠ `5511940863773`.

## Correções aplicadas

### 1. ConversationService (patch mínimo)

- **findConversationByContactName:** Passa a exigir `channel_id` igual (mesmo canal).
- **contactExternalIdsAreEquivalent:** Novo método. Só aceita match por nome quando os IDs são equivalentes (mesmo número, variação 9º dígito ou mapeamento @lid→phone em `whatsapp_business_ids`).
- **Fluxo:** Se `findConversationByContactName` encontrar conversa mas os IDs não forem equivalentes, o match é rejeitado e uma nova conversa é criada.

### 2. Script de correção para conversa 140

```bash
php database/fix-conversation-140-charles-identidade.php
```

- Atualiza `contact_external_id` de `5511940863773` para `208989199560861@lid`.
- Cria mapeamento em `whatsapp_business_ids` para exibir (47) 99616-4699.

## Regras de identidade (obrigatórias)

1. **Identidade:** `external_id` (JID/telefone) + `channel_type` + `channel_id`/`tenant_id`. Nome é apenas label.
2. **Inbound:** interlocutor = sender (`from`/`remoteJid`).
3. **Outbound:** interlocutor = `to` (destino).
4. **Merge por nome:** Só quando `external_id`s forem equivalentes.

## Teste de validação

1. Enviar mensagem do número 47996164699 para o canal +55 47 9714-6908 (ImobSites).
2. No Inbox, o card "Charles Dietrich" deve exibir **(47) 99616-4699**.
3. A conversa deve estar associada ao `contact_id` correto, sem misturar histórico com DDD 11.

## Garantia de não regressão

- `findEquivalentConversation` (9º dígito) e `findConversationByLidPhoneMapping` continuam ativos.
- Apenas o match por nome foi restrito com checagem de equivalência e `channel_id`.
- Multi-tenant e separação por canal preservados.
