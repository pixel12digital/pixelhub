# Diagnóstico: mensagens de Charles para ImobSites não entregues

**Contexto:** Mensagens enviadas por Charles Dietrich para a sessão ImobSites (texto 12:56 e áudio 5s 12:54) não apareceram no Hub.

## Correções aplicadas no código (10/fev)

1. **ConversationService – rejeição de ImobSites removida**
   - Em `updateConversationMetadata` o valor `imobsites` estava na lista de channel_id “incorretos”, então o Hub **não atualizava** a conversa com o canal e as mensagens inbound do ImobSites podiam ficar sem thread/canal correto.
   - Esse bloqueio foi removido; ImobSites é tratado como sessão válida.

2. **ConversationService – extractChannelIdFromPayload**
   - Quando o channel_id vinha de metadata e era “imobsites”, o código tentava “corrigir” e, se não achasse outro sessionId no payload, **retornava NULL**, perdendo o canal.
   - Essa lógica especial para imobsites foi removida; o channel_id vindo de payload ou metadata é aceito.

3. **CommunicationHubController – getWhatsAppThreadInfo**
   - Em dois pontos o `metadata.channel_id = 'ImobSites'` era rejeitado como “incorreto”, o que podia fazer a thread não exibir o canal correto.
   - Essa rejeição foi removida.

4. **ConversationService – canal sem tenant**
   - Quando o webhook não resolve `tenant_id` (ex.: canal não cadastrado ou desabilitado), agora é usada `resolveChannelAccountIdByChannelOnly(channel_id)` para achar o canal só pelo sessionId (ex.: ImobSites) e assim criar/atualizar a conversa com o canal certo.

## O que verificar se ainda faltar mensagem

### 1. Gateway (VPS) envia webhook para ImobSites

- O gateway deve enviar **todos** os eventos da sessão ImobSites para a URL do webhook do Hub.
- No payload deve vir **sessionId** (ou equivalente) com valor `ImobSites` (ou o mesmo nome configurado no gateway), por exemplo em:
  - `payload.sessionId`
  - `payload.session.id` ou `payload.session.session`
  - `payload.data.session.id` ou `payload.data.session.session`
- Se o gateway só enviar para uma sessão ou não incluir sessionId para ImobSites, as mensagens não serão associadas ao canal no Hub.

### 2. Canal ImobSites no Hub

- Em **Configurações → WhatsApp Gateway**, a sessão **ImobSites** deve aparecer e estar **conectada**.
- No banco, deve existir um registro em `tenant_message_channels` com:
  - `provider = 'wpp_gateway'`
  - `channel_id` igual a `ImobSites` (ou normalizado, ex.: sem espaços / minúsculo, conforme a busca)
  - `is_enabled = 1`
- Query de verificação (ver também `database/queries-diagnostico-channel-id.sql`):

```sql
SELECT id, tenant_id, channel_id, is_enabled
FROM tenant_message_channels
WHERE provider = 'wpp_gateway'
  AND (LOWER(REPLACE(TRIM(channel_id), ' ', '')) = 'imobsites' OR channel_id = 'ImobSites');
```

### 3. Logs do Hub (horário das mensagens, ex.: 12:54–12:56)

- **Webhook recebido:**  
  `[HUB_CHANNEL_ID_EXTRACTION] channel_id=ImobSites` (ou similar)  
  Se aparecer `INBOUND_MISSING_CHANNEL_ID`, o payload não trouxe sessionId/channelId.

- **Tenant resolvido:**  
  `[WHATSAPP INBOUND RAW] Tenant ID resolvido:` (deve ser o ID do tenant ou pelo menos o canal encontrado depois da correção).

- **Conversa/canal:**  
  `[CONVERSATION UPSERT]` e `resolveChannelAccountIdByChannelOnly` (quando tenant era null e o canal foi resolvido só pelo channel_id).

- **Rejeição antiga (não deve mais ocorrer):**  
  `REJEITADO channel_id incorreto=imobsites` ou `metadata.channel_id='ImobSites' rejeitado`.

### 4. Conversa Charles Dietrich + ImobSites

- Conferir se existe conversa com `channel_id = 'ImobSites'` e o contato de Charles:

```sql
SELECT id, conversation_key, channel_id, tenant_id, contact_external_id, contact_name, last_message_at
FROM conversations
WHERE channel_type = 'whatsapp'
  AND (LOWER(REPLACE(TRIM(channel_id), ' ', '')) = 'imobsites' OR contact_name LIKE '%Charles%')
ORDER BY last_message_at DESC
LIMIT 20;
```

- Se a conversa existir mas `channel_id` estiver NULL ou errado, as próximas mensagens inbound (com as correções acima) devem passar a preencher/corrigir o canal.

## Resumo

- **Causa raiz tratada no código:** ImobSites era tratado como channel_id “incorreto” em vários pontos; isso foi removido e foi adicionada a resolução de canal só pelo `channel_id` quando o tenant não vem no webhook.
- **Próximos passos:** Garantir que o gateway envia webhook para ImobSites com sessionId no payload e que o canal ImobSites está cadastrado e habilitado no Hub; depois reenviar uma mensagem de teste de Charles para ImobSites e checar os logs e a conversa no Hub.
