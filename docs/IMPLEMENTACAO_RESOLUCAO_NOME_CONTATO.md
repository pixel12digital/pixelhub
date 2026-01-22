# Implementação: Resolução de Nome de Contato WhatsApp

## Objetivo

Quando houver nome disponível do WhatsApp (ex: "Victor"), exibir no lugar do placeholder "Contato Desconhecido", sem alterar rotas/endpoints/contrato do frontend.

## Contexto

- Já resolvemos LID → telefone
- Ainda aparece "Contato Desconhecido" porque não há `display_name` local
- Para o caso `169183207809126`, o WhatsApp mostra "Victor"

## Mudanças Implementadas

### 1. Nova Tabela: `wa_contact_names_cache`

**Arquivo:** `database/migrations/20260122_create_wa_contact_names_cache_table.php`

Tabela para cachear nomes de contatos extraídos de payloads ou obtidos via API do gateway.

**Estrutura:**
- `provider` (VARCHAR 50): Provider do WhatsApp (padrão: 'wpp_gateway')
- `session_id` (VARCHAR 100, NULLABLE): ID da sessão WhatsApp (opcional)
- `phone_e164` (VARCHAR 20): Número de telefone em formato E.164
- `display_name` (VARCHAR 255): Nome do contato (ex: Victor)
- `source` (VARCHAR 50): Origem do nome (payload, provider, manual)
- `updated_at`, `created_at`: Timestamps

**Índices:**
- `uk_provider_session_phone`: Unique key (provider, session_id, phone_e164)
- `idx_provider_phone`: Índice (provider, phone_e164) para fallback sem sessionId
- `idx_phone_e164`: Índice simples em phone_e164
- `idx_updated_at`: Índice para TTL (30 dias)

### 2. Funções em `ContactHelper.php`

#### `extractNameFromPayload($payload): ?string`

Extrai nome do contato de um payload de evento WhatsApp.

**Ordem de prioridade para buscar nome:**
1. `message.notifyName`
2. `raw.payload.notifyName`
3. `raw.payload.sender.verifiedName`
4. `raw.payload.sender.name`
5. `raw.payload.sender.formattedName`
6. `data.notifyName`
7. `notifyName`
8. `sender.name`
9. `sender.formattedName`
10. `sender.verifiedName`
11. `pushName`
12. `raw.payload.pushName`
13. `message.pushName`
14. `profileName`
15. `raw.payload.profileName`

**Normalização:**
- Trim e colapsa espaços múltiplos
- Remove emojis extremos (mantém apenas caracteres alfanuméricos, espaços e alguns caracteres especiais comuns)
- Limita tamanho a 60 caracteres
- Rejeita nomes muito curtos (< 2 caracteres)
- Rejeita strings que são apenas números

#### `normalizeDisplayName($name): ?string` (privada)

Normaliza nome para exibição, aplicando as regras acima.

#### `saveContactDisplayName($db, $provider, $sessionId, $phoneE164, $displayName, $source): void` (privada)

Salva nome do contato no cache `wa_contact_names_cache`.

**Comportamento:**
- Se `sessionId` disponível: insere/atualiza com chave única (provider, session_id, phone_e164)
- Se `sessionId` NULL: insere/atualiza sem session_id (usa apenas provider + phone_e164)
- Usa `ON DUPLICATE KEY UPDATE` para atualizar registros existentes

#### `resolveDisplayNameFromEvents($phoneE164, $sessionId): ?string` (privada)

Busca nome do contato nos eventos recentes (`communication_events`).

**Lógica:**
- Busca eventos do tipo `whatsapp.inbound.message` ou `whatsapp.outbound.message`
- Filtra por `from` ou `to` contendo o telefone
- Se `sessionId` disponível, filtra também por `channel_id` ou `sessionId`
- Ordena por `created_at DESC` e limita a 10 eventos
- Tenta extrair nome de cada evento usando `extractNameFromPayload()`
- Se encontrar, salva no cache e retorna

#### `resolveDisplayNameViaProvider($provider, $sessionId, $jidOrPhone): ?string` (privada)

Resolve nome do contato via API do provider (gateway).

**Endpoint:** `GET /api/{sessionId}/contact/{jidOrPhone}`

**Comportamento:**
- Apenas para `wpp_gateway`
- Requer `sessionId` (não funciona sem)
- Timeout: 5s connect, 10s total
- Autenticação via header `X-Gateway-Secret`
- Tenta extrair nome de múltiplos campos da resposta:
  - `name`, `displayName`, `notifyName`, `pushName`, `profileName`, `verifiedName`
  - `contact.name`, `contact.displayName`
  - `data.name`
- Normaliza o nome antes de retornar
- Logs discretos apenas quando falhar (exceto 404, que é esperado)

#### `resolveDisplayName($phoneE164, $sessionId, $provider, $tenantName): ?string` (pública)

Função principal para resolver nome do contato.

**Ordem de prioridade:**
1. **Nome do tenant vinculado** (se fornecido e não for "Sem tenant")
2. **Nome cacheado** em `wa_contact_names_cache`:
   - Primeiro tenta com `sessionId` (se disponível)
   - Fallback: tenta sem `sessionId` (apenas provider + phone_e164)
   - Filtra por `updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)` (TTL 30 dias)
3. **Nome extraído dos eventos** recentes via `resolveDisplayNameFromEvents()`
4. **Nome via API do provider** via `resolveDisplayNameViaProvider()` (apenas se `sessionId` disponível)

**Retorno:**
- Nome normalizado ou `null` se não encontrado

### 3. Ajustes em `CommunicationHubController.php`

#### `getConversationsList()`

**Mudanças:**
- Antes de montar o array `$threads`, coleta conversas sem `contact_name` e sem `tenant_id`
- Para cada conversa, resolve o telefone primeiro (usando lógica existente de LID)
- Se não é LID, tenta normalizar como telefone direto
- Resolve nomes em lote (limitado a 10 conversas para performance)
- Aplica nome resolvido como fallback quando `contact_name` está vazio

**Lógica de prioridade no resultado:**
1. `contact_name` da conversa (se existir)
2. `tenant_name` (se existir e não for "Sem tenant")
3. Nome resolvido em lote via `ContactHelper::resolveDisplayName()`
4. `null` (frontend faz fallback para "Contato Desconhecido")

**Logs temporários:**
- `[NAME_RESOLVE]` quando resolve nome com sucesso

#### `getWhatsAppThreadInfo()`

**Mudanças:**
- Após resolver telefone (usando lógica existente), resolve nome se `contact_name` estiver vazio
- Se não é LID, tenta normalizar `contact_external_id` como telefone direto
- Chama `ContactHelper::resolveDisplayName()` com telefone resolvido
- Aplica nome resolvido como fallback

**Lógica de prioridade no resultado:**
1. `contact_name` da conversa (se existir)
2. `tenant_name` (se existir e não for "Sem tenant")
3. Nome resolvido via `ContactHelper::resolveDisplayName()`
4. `null` (frontend faz fallback para "Contato Desconhecido")

**Logs temporários:**
- `[NAME_RESOLVE]` quando resolve nome com sucesso

## Endpoints Afetados

### 1. `GET /api/communication-hub/conversations` (Listagem)

**Antes:**
- `contact_name` podia estar vazio → frontend mostrava "Contato Desconhecido"

**Depois:**
- `contact_name` pode ser preenchido com nome resolvido de payloads/cache/provider
- Se não encontrar, continua `null` → frontend mantém fallback "Contato Desconhecido"

### 2. `GET /api/communication-hub/thread/{threadId}` (Detalhe)

**Antes:**
- `contact_name` podia estar vazio → frontend mostrava "Contato Desconhecido"

**Depois:**
- `contact_name` pode ser preenchido com nome resolvido de payloads/cache/provider
- Se não encontrar, continua `null` → frontend mantém fallback "Contato Desconhecido"

## Performance

### Otimizações

1. **Cache persistente:** Nomes encontrados são salvos em `wa_contact_names_cache` (TTL 30 dias)
2. **Batch limitado:** Na listagem, resolve no máximo 10 nomes por requisição
3. **Prioridade de cache:** Busca primeiro no cache antes de consultar eventos ou API
4. **Fallback sem sessionId:** Cache funciona mesmo quando `sessionId` não está disponível
5. **Sem N+1:** Resolução em lote na listagem (máximo 10 itens)

### Limites

- **Listagem:** Máximo 10 conversas sem nome por requisição (evita sobrecarga)
- **API do provider:** Timeout curto (5s connect, 10s total)
- **Cache TTL:** 30 dias (nomes antigos são ignorados)

## Logs

### Logs Temporários (para troubleshooting)

**Ativar/Desativar:**
- Logs estão ativos por padrão
- Para desativar, comentar as linhas com `error_log()` que contêm `[NAME_RESOLVE]`

**Formato:**
```
[NAME_RESOLVE] conversation_id=123, contact_external_id=169183207809126@lid, resolved_name=Victor
[NAME_RESOLVE] thread_info conversation_id=123, contact_external_id=169183207809126@lid, phone=55169183207809126, resolved_name=Victor
```

**Logs de erro (sempre ativos, discretos):**
- `[ContactHelper::saveContactDisplayName]` - Erro ao salvar nome no cache
- `[ContactHelper::resolveDisplayNameViaProvider]` - Erro ao consultar API do provider (exceto 404)

## Testes

### Casos de Teste

1. **@c.us / @s.whatsapp.net:** Deve continuar igual (não passa pelo provider)
2. **@lid já mapeado em whatsapp_business_ids:** Não chama provider para telefone, mas pode chamar para nome
3. **@lid sem sessionId mas com cache antigo em wa_contact_names_cache:** Deve resolver nome
4. **@lid sem nada (sem cache e sem eventos úteis):** Provider deve tentar (se tiver sessionId), e se falhar, continua exibindo "Contato Desconhecido"
5. **Contato com tenant vinculado:** Nome do tenant tem prioridade (não sobrescreve)
6. **Listagem de conversas:** Deve manter performance (batch mantém 2-3 queries + provider apenas para poucos casos, com limite)

### Exemplo: Caso do Victor

**Cenário:**
- Contact ID: `169183207809126@lid`
- Telefone resolvido: `55169183207809126`
- Nome no WhatsApp: "Victor"

**Fluxo:**
1. Listagem busca telefone via `resolveLidPhonesBatch()` → encontra `55169183207809126`
2. Listagem verifica `contact_name` → está vazio
3. Listagem resolve nome via `ContactHelper::resolveDisplayName()`:
   - Busca no cache `wa_contact_names_cache` → não encontra
   - Busca nos eventos recentes → encontra "Victor" em um payload
   - Salva no cache
   - Retorna "Victor"
4. Frontend recebe `contact_name: "Victor"` → exibe "Victor" ao invés de "Contato Desconhecido"

## Arquivos Alterados

1. `database/migrations/20260122_create_wa_contact_names_cache_table.php` (NOVO)
2. `src/Core/ContactHelper.php`:
   - `extractNameFromPayload()` (NOVO)
   - `normalizeDisplayName()` (NOVO, privada)
   - `saveContactDisplayName()` (NOVO, privada)
   - `resolveDisplayNameFromEvents()` (NOVO, privada)
   - `resolveDisplayNameViaProvider()` (NOVO, privada)
   - `resolveDisplayName()` (NOVO, pública)
   - Ajuste em `tableExists()` para incluir `wa_contact_names_cache`
3. `src/Controllers/CommunicationHubController.php`:
   - `getConversationsList()`: Resolução de nome em lote
   - `getWhatsAppThreadInfo()`: Resolução de nome individual

## Origem do Nome

O nome pode vir de:

1. **Payload de eventos** (`source: 'payload'`):
   - Extraído de `communication_events` usando `extractNameFromPayload()`
   - Campos: `notifyName`, `verifiedName`, `pushName`, `formattedName`, etc.

2. **Cache** (`source: 'payload'` ou `'provider'`):
   - Armazenado em `wa_contact_names_cache`
   - TTL: 30 dias
   - Busca rápida (índices otimizados)

3. **API do provider** (`source: 'provider'`):
   - Endpoint: `GET /api/{sessionId}/contact/{jidOrPhone}`
   - Apenas quando `sessionId` disponível
   - Timeout curto (5s connect, 10s total)

4. **Tenant vinculado** (prioridade máxima):
   - Nome do tenant no sistema (CRM)
   - Não é sobrescrito por nomes do WhatsApp

## Como Desativar Logs Temporários

Para desativar os logs de `[NAME_RESOLVE]`, comentar as seguintes linhas:

**Em `src/Controllers/CommunicationHubController.php`:**

1. Na função `getConversationsList()`, linha ~1520:
```php
// Comentar:
// error_log(sprintf(
//     '[NAME_RESOLVE] conversation_id=%d, contact_external_id=%s, resolved_name=%s',
//     $conv['id'] ?? 0,
//     $conv['contact_external_id'] ?? 'NULL',
//     $displayName
// ));
```

2. Na função `getWhatsAppThreadInfo()`, linha ~2800:
```php
// Comentar:
// error_log(sprintf(
//     '[NAME_RESOLVE] thread_info conversation_id=%d, contact_external_id=%s, phone=%s, resolved_name=%s',
//     $conversationId,
//     $conversation['contact_external_id'] ?? 'NULL',
//     $realPhone,
//     $displayName
// ));
```

**Nota:** Logs de erro (ex: `[ContactHelper::saveContactDisplayName]`) devem permanecer ativos para troubleshooting.

