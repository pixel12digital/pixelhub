# Auditoria Completa: Resolução de Identificadores WhatsApp no PixelHub

**Data:** 2025-01-22  
**Objetivo:** Mapear todos os pontos onde identificadores WhatsApp (@lid, @c.us, @s.whatsapp.net) são resolvidos para números de telefone

---

## 1. RESUMO EXECUTIVO

### 1.1. Funções Principais de Resolução

| Função | Arquivo | Linha | Descrição |
|--------|---------|-------|-----------|
| `resolveLidPhone()` | `src/Core/ContactHelper.php` | 274 | Resolve um único @lid para telefone |
| `resolveLidPhonesBatch()` | `src/Core/ContactHelper.php` | 424 | Resolve múltiplos @lid em lote (otimizado) |
| `resolveLidPhoneFromEvents()` | `src/Core/ContactHelper.php` | 174 | Busca telefone nos eventos recentes (fallback) |
| `extractPhoneFromPayload()` | `src/Core/ContactHelper.php` | 123 | Extrai telefone do payload de eventos |
| `formatContactId()` | `src/Core/ContactHelper.php` | 34 | Formata ID para exibição (usa resolução se disponível) |

### 1.2. Tabelas de Cache/Mapeamento

| Tabela | Propósito | Estrutura |
|--------|-----------|-----------|
| `whatsapp_business_ids` | Mapeamento persistente @lid → telefone | `business_id` (UNIQUE), `phone_number`, `tenant_id` (opcional) |
| `wa_pnlid_cache` | Cache temporário de resoluções via API | `provider`, `session_id`, `pnlid`, `phone_e164`, `updated_at` (TTL: 30 dias) |

---

## 2. FLUXO COMPLETO DE RESOLUÇÃO

### 2.1. Função `resolveLidPhone()`

**Localização:** `src/Core/ContactHelper.php:274`

**Fluxo:**
```
1. Validação: Verifica se contactId contém '@lid'
   └─ Se não contém '@lid', retorna NULL

2. Extração: Remove '@lid' para obter lidId
   └─ Exemplo: "56083800395891@lid" → lidId = "56083800395891"
   └─ businessId = "56083800395891@lid"

3. PRIORIDADE 1: Consulta whatsapp_business_ids (mapeamento persistente)
   └─ Query: SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ?
   └─ Se encontrado, retorna phone_number
   └─ Se não encontrado, continua para próxima prioridade

4. PRIORIDADE 2: Consulta wa_pnlid_cache (cache temporário)
   └─ Requisitos: sessionId e provider devem estar disponíveis
   └─ Query: SELECT phone_e164 FROM wa_pnlid_cache 
             WHERE provider = ? AND session_id = ? AND pnlid = ? 
             AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
   └─ Se encontrado, retorna phone_e164
   └─ Se não encontrado, continua para próxima prioridade

5. PRIORIDADE 3: Busca nos eventos recentes (resolveLidPhoneFromEvents)
   └─ Busca últimos 10 eventos com esse contactId
   └─ Extrai telefone do payload usando extractPhoneFromPayload()
   └─ Se encontrado:
      ├─ Cria mapeamento em whatsapp_business_ids (INSERT IGNORE)
      ├─ Salva no cache wa_pnlid_cache (se tiver sessionId)
      └─ Retorna telefone encontrado
   └─ Se não encontrado, retorna NULL
```

**Parâmetros:**
- `$contactId` (string): Identificador do contato (ex: "56083800395891@lid")
- `$sessionId` (string|null): ID da sessão WhatsApp (opcional)
- `$provider` (string|null): Provider padrão (padrão: 'wpp_gateway')

**Retorno:**
- `string|null`: Número de telefone em formato E.164 (ex: "554796474223") ou null

### 2.2. Função `resolveLidPhonesBatch()`

**Localização:** `src/Core/ContactHelper.php:424`

**Fluxo:**
```
1. Validação: Verifica se há dados para processar
   └─ Se vazio, retorna array vazio

2. Extração em lote: Coleta todos os lidId e businessId únicos
   └─ Para cada item: extrai lidId e cria businessId
   └─ Agrupa sessionIds únicos

3. PRIORIDADE 1: Consulta whatsapp_business_ids em lote
   └─ Query: SELECT business_id, phone_number FROM whatsapp_business_ids 
             WHERE business_id IN (?, ?, ...)
   └─ Cria mapa [lidId => phone_number] para resultados encontrados

4. PRIORIDADE 2: Consulta wa_pnlid_cache em lote (apenas não resolvidos)
   └─ Query: SELECT pnlid, phone_e164, session_id FROM wa_pnlid_cache 
             WHERE provider = ? AND session_id IN (?, ...) AND pnlid IN (?, ...)
             AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
   └─ Adiciona ao mapa apenas para pnlid não resolvidos anteriormente

5. PRIORIDADE 3: Busca nos eventos (apenas para não resolvidos, limitado a 50)
   └─ Para cada item não resolvido, chama resolveLidPhoneFromEvents()
   └─ Adiciona ao mapa se encontrado

6. Retorna mapa completo [lidId => phoneE164]
```

**Parâmetros:**
- `$lidData` (array): Array de arrays com ['contactId' => string, 'sessionId' => ?string]
- `$provider` (string): Provider padrão (padrão: 'wpp_gateway')

**Retorno:**
- `array`: Mapa [lidId => phoneE164] ou [lidBusinessId => phoneE164]

**Otimização:**
- Evita problema N+1 de queries
- Máximo 2 queries principais (whatsapp_business_ids + wa_pnlid_cache)
- Busca em eventos apenas para não resolvidos (limitado a 50 itens)

### 2.3. Função `resolveLidPhoneFromEvents()`

**Localização:** `src/Core/ContactHelper.php:174`

**Fluxo:**
```
1. Validação: Verifica se contactId contém '@lid'
   └─ Se não contém, retorna NULL

2. Busca eventos recentes:
   └─ Query: SELECT payload FROM communication_events 
             WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
             AND (JSON_EXTRACT(payload, '$.from') LIKE ? OR JSON_EXTRACT(payload, '$.to') LIKE ? ...)
             AND (se tiver sessionId: filtro por channel_id)
             ORDER BY created_at DESC LIMIT 10

3. Extração de telefone:
   └─ Para cada evento, chama extractPhoneFromPayload()
   └─ Busca em: payload.raw.payload.sender.formattedName
   └─ Remove caracteres não numéricos e normaliza para E.164

4. Se encontrado:
   └─ Cria mapeamento em whatsapp_business_ids (INSERT IGNORE)
   └─ Salva no cache wa_pnlid_cache (se tiver sessionId)
   └─ Retorna telefone encontrado

5. Se não encontrado, retorna NULL
```

---

## 3. ENDPOINTS QUE APLICAM RESOLUÇÃO

### 3.1. Listagem de Conversas

**Endpoint:** `GET /communication-hub/conversations-list`  
**Controller:** `CommunicationHubController::getConversationsList()`  
**Arquivo:** `src/Controllers/CommunicationHubController.php:2831`

**Fluxo:**
```
1. Busca conversas da tabela conversations
2. OTIMIZAÇÃO: Resolve todos os @lid em lote ANTES do loop
   └─ Coleta todos os @lid que precisam ser resolvidos
   └─ Chama ContactHelper::resolveLidPhonesBatch() uma única vez
   └─ Cria mapa [lidId => phoneE164]

3. Para cada conversa no loop:
   └─ Prioridade 1: Se tem tenant_id e tenant_phone, usa tenant_phone
   └─ Prioridade 2: Se é @lid, busca no mapa pré-carregado (O(1))
   └─ Chama ContactHelper::formatContactId() com telefone resolvido
   └─ Retorna 'contact' formatado para o frontend
```

**Linhas relevantes:**
- 1401-1424: Coleta e resolução em lote
- 1429-1438: Uso do mapa na formatação

**O que o frontend recebe:**
- Campo `contact`: String formatada (ex: "(47) 9647-4223" ou "ID WhatsApp: 5608 3800 3958 91")
- Campo `contact_name`: Nome do contato (se disponível)
- Campo `tenant_phone`: Telefone do tenant (se vinculado)

### 3.2. Detalhe da Conversa (Thread Info)

**Endpoint:** `GET /communication-hub/thread-info?thread_id=X`  
**Controller:** `CommunicationHubController::getWhatsAppThreadInfo()`  
**Arquivo:** `src/Controllers/CommunicationHubController.php:2600`

**Fluxo:**
```
1. Resolve thread_id para conversation_id
2. Busca conversa da tabela conversations
3. Resolve @lid se necessário:
   └─ Se tem tenant_id e tenant_phone, usa tenant_phone
   └─ Se é @lid, chama ContactHelper::resolveLidPhone()
   └─ Se encontrou telefone, GARANTE que está salvo no mapeamento:
      ├─ Verifica se existe em whatsapp_business_ids
      ├─ Se não existe, cria mapeamento
      └─ Salva também no cache wa_pnlid_cache

4. Chama ContactHelper::formatContactId() com telefone resolvido
5. Retorna informações do thread para o frontend
```

**Linhas relevantes:**
- 2657-2707: Resolução e garantia de mapeamento

**O que o frontend recebe:**
- Campo `contact`: String formatada
- Campo `tenant_name`: Nome do tenant (se vinculado)
- Campo `channel_id`: ID da sessão WhatsApp

### 3.3. Mensagens da Conversa

**Endpoint:** `GET /communication-hub/messages?thread_id=X`  
**Controller:** `CommunicationHubController::getWhatsAppMessagesFromConversation()`  
**Arquivo:** `src/Controllers/CommunicationHubController.php:1904`

**Fluxo:**
```
1. Busca conversa para pegar contact_external_id e channel_id
2. Se contact_external_id é número (não @lid):
   └─ Busca @lid mapeado para esse número em whatsapp_business_ids
   └─ Adiciona @lid encontrados aos padrões de busca

3. Normaliza contact_external_id:
   └─ Se é @lid: mantém como está
   └─ Se é @c.us ou @s.whatsapp.net: remove sufixo e normaliza para E.164
   └─ Se é número puro: normaliza para E.164

4. Busca eventos com múltiplos padrões:
   └─ Padrão com @lid (se aplicável)
   └─ Padrão com número normalizado
   └─ Variações com/sem 9º dígito (para números BR)

5. Para cada mensagem encontrada:
   └─ Normaliza sender usando normalizeSender() (função interna)
   └─ Se sender é @lid, tenta resolver via cache/API
   └─ Formata para exibição
```

**Linhas relevantes:**
- 1922-1938: Busca @lid mapeado para número
- 1940-1954: Criação de remote_key
- 1976-2007: Padrões de busca (incluindo @lid)
- 1860-1894: Função normalizeSender() (resolve @lid via cache/API)

**O que o frontend recebe:**
- Array de mensagens com campo `from` normalizado
- Campo `from_display`: String formatada para exibição

### 3.4. Vinculação de Cliente

**Endpoint:** `POST /communication-hub/conversation/link-tenant`  
**Controller:** `CommunicationHubController::linkConversationToTenant()`  
**Arquivo:** `src/Controllers/CommunicationHubController.php:3790`

**Fluxo:**
```
1. Valida conversation_id e tenant_id
2. Busca conversa e verifica se é incoming_lead
3. Atualiza conversa:
   └─ UPDATE conversations SET tenant_id = ?, is_incoming_lead = 0 WHERE id = ?

NOTA: Este endpoint NÃO faz resolução de @lid.
      A resolução já deve ter sido feita anteriormente na listagem/detalhe.
```

**Linhas relevantes:**
- 3838-3845: Atualização do tenant_id

**O que o frontend recebe:**
- `success`: boolean
- `tenant_id`: ID do tenant vinculado
- `tenant_name`: Nome do tenant
- `conversation_id`: ID da conversa

---

## 4. TRATAMENTO DE DIFERENTES TIPOS DE IDENTIFICADORES

### 4.1. @lid (WhatsApp Business ID)

**Onde é tratado:**
- ✅ `ContactHelper::resolveLidPhone()` - Resolução principal
- ✅ `ContactHelper::resolveLidPhonesBatch()` - Resolução em lote
- ✅ `ContactHelper::resolveLidPhoneFromEvents()` - Fallback via eventos
- ✅ `ConversationService::extractChannelInfo()` - Durante criação/atualização de conversas

**Fluxo de resolução:**
1. Consulta `whatsapp_business_ids` (mapeamento persistente)
2. Consulta `wa_pnlid_cache` (cache temporário, TTL: 30 dias)
3. Busca nos eventos recentes (extrai do payload)

**Fallback:**
- Se não encontrar mapeamento, usa @lid como `contact_external_id` (arquitetura remote_key)
- Frontend exibe como "ID WhatsApp: XXXX XXXX XXXX" se não houver resolução

### 4.2. @c.us e @s.whatsapp.net (JID Numérico)

**Onde é tratado:**
- ✅ `ConversationService::extractChannelInfo()` - Linha 329-347
- ✅ `CommunicationHubController::normalizePhoneE164()` - Normalização interna
- ✅ `CommunicationHubController::getWhatsAppMessagesFromConversation()` - Busca de mensagens

**Fluxo de tratamento:**
```
1. Detecta se termina com @c.us ou @s.whatsapp.net
2. Remove sufixo: "554796474223@c.us" → "554796474223"
3. Extrai apenas dígitos
4. Normaliza para E.164 usando PhoneNormalizer::toE164OrNull()
5. Usa número normalizado como contact_external_id
```

**Diferença do @lid:**
- ❌ NÃO consulta tabelas de mapeamento
- ❌ NÃO faz resolução via API
- ✅ Extrai número diretamente do JID (já contém o telefone)

**Exemplo:**
```php
// ConversationService::extractChannelInfo() - Linha 329
if (strpos($rawFrom, '@c.us') !== false || strpos($rawFrom, '@s.whatsapp.net') !== false) {
    $digitsOnly = preg_replace('/@.*$/', '', $rawFrom);
    $digitsOnly = preg_replace('/[^0-9]/', '', $digitsOnly);
    if (strlen($digitsOnly) >= 10) {
        $contactExternalId = PhoneNormalizer::toE164OrNull($digitsOnly);
    }
}
```

### 4.3. Número Puro (E.164)

**Onde é tratado:**
- ✅ `PhoneNormalizer::toE164OrNull()` - Normalização
- ✅ `ContactHelper::formatPhoneNumber()` - Formatação para exibição
- ✅ Todos os endpoints que processam contact_external_id

**Fluxo:**
- Usado diretamente, sem necessidade de resolução
- Normalizado para E.164 quando necessário
- Formatado para exibição: "(47) 9647-4223"

---

## 5. PONTOS ONDE A RESOLUÇÃO FALHA

### 5.1. Quando @lid não está mapeado

**Cenário:**
- Evento recebido com @lid que não existe em `whatsapp_business_ids`
- Cache `wa_pnlid_cache` não contém o mapeamento
- Eventos recentes não contêm telefone no payload (formattedName ausente)

**Comportamento atual:**
- ✅ Sistema usa @lid como `contact_external_id` (não quebra)
- ✅ Frontend exibe como "ID WhatsApp: XXXX XXXX XXXX"
- ⚠️ Usuário não vê número real até que:
  - Um evento futuro contenha formattedName
  - Mapeamento seja criado manualmente
  - Resolução via API seja implementada

**Onde falha:**
- `ContactHelper::resolveLidPhone()` retorna NULL
- `ContactHelper::formatContactId()` exibe ID formatado (não telefone)

### 5.2. Quando sessionId não está disponível

**Cenário:**
- Conversa criada sem `channel_id` (sessionId)
- Evento não contém sessionId no payload

**Comportamento atual:**
- ✅ Consulta `whatsapp_business_ids` funciona (não depende de sessionId)
- ❌ Consulta `wa_pnlid_cache` é pulada (requer sessionId)
- ✅ Busca em eventos funciona (não requer sessionId)

**Onde falha:**
- Cache `wa_pnlid_cache` não é consultado
- Resolução via API não é possível (requer sessionId)

### 5.3. Quando eventos não contêm formattedName

**Cenário:**
- Payload do evento não contém `sender.formattedName`
- Payload não contém `message.sender.formattedName`

**Comportamento atual:**
- ❌ `extractPhoneFromPayload()` retorna NULL
- ❌ `resolveLidPhoneFromEvents()` não encontra telefone
- ✅ Sistema continua funcionando com @lid (não quebra)

**Onde falha:**
- Fallback via eventos não funciona
- Mapeamento automático não é criado

### 5.4. Quando frontend recebe apenas ID cru

**Cenários identificados:**

1. **Listagem de conversas sem resolução:**
   - Se `resolveLidPhonesBatch()` retornar mapa vazio
   - Frontend recebe `contact: "ID WhatsApp: 5608 3800 3958 91"`

2. **Detalhe da conversa sem resolução:**
   - Se `resolveLidPhone()` retornar NULL
   - Frontend recebe `contact: "ID WhatsApp: 5608 3800 3958 91"`

3. **Mensagens com sender @lid não resolvido:**
   - Se `normalizeSender()` não conseguir resolver
   - Frontend recebe `from_display: "ID WhatsApp: 5608 3800 3958 91"`

**Arquivos afetados:**
- `views/communication_hub/index.php` - Exibe `contact` e `from_display`

---

## 6. ESTRUTURA DAS TABELAS

### 6.1. whatsapp_business_ids

**Migration:** `database/migrations/20260113_create_whatsapp_business_ids_table.php`

```sql
CREATE TABLE whatsapp_business_ids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id VARCHAR(100) NOT NULL COMMENT 'ID interno do WhatsApp Business (ex: 10523374551225@lid)',
    phone_number VARCHAR(20) NOT NULL COMMENT 'Número de telefone real em formato E.164 (ex: 554796474223)',
    tenant_id INT UNSIGNED NULL COMMENT 'ID do tenant (opcional, para isolamento)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_business_id (business_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

**Uso:**
- Mapeamento persistente @lid → telefone
- Criado automaticamente quando telefone é encontrado em eventos
- Pode ser criado manualmente via scripts

### 6.2. wa_pnlid_cache

**Estrutura (inferida do código):**

```sql
CREATE TABLE wa_pnlid_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    pnlid VARCHAR(50) NOT NULL COMMENT 'ID sem @lid (ex: 56083800395891)',
    phone_e164 VARCHAR(20) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_provider_session_pnlid (provider, session_id, pnlid),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

**Uso:**
- Cache temporário de resoluções via API
- TTL: 30 dias (consultas verificam `updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)`)
- Criado quando resolução via API é bem-sucedida

---

## 7. FLUXO COMPLETO: ID RECEBIDO → FUNÇÃO → QUERY → RETORNO

### 7.1. Exemplo: Listagem de Conversas

```
1. EVENTO RECEBIDO:
   └─ Payload: { "from": "56083800395891@lid", "to": "554797146908@c.us", ... }
   └─ Evento salvo em communication_events

2. CONVERSATION SERVICE:
   └─ ConversationService::resolveConversation()
   └─ extractChannelInfo() detecta @lid
   └─ Consulta whatsapp_business_ids: SELECT phone_number FROM whatsapp_business_ids WHERE business_id = '56083800395891@lid'
   └─ Se não encontrado, usa @lid como contact_external_id
   └─ Cria/atualiza conversa em conversations

3. LISTAGEM (getConversationsList):
   └─ Busca conversas: SELECT * FROM conversations WHERE ...
   └─ Coleta @lid que precisam resolução: ["56083800395891@lid"]
   └─ resolveLidPhonesBatch():
      ├─ Query 1: SELECT business_id, phone_number FROM whatsapp_business_ids WHERE business_id IN ('56083800395891@lid')
      ├─ Query 2: SELECT pnlid, phone_e164 FROM wa_pnlid_cache WHERE provider = 'wpp_gateway' AND session_id IN (...) AND pnlid IN ('56083800395891')
      └─ Retorna mapa: ["56083800395891" => "554796474223"]

4. FORMATAÇÃO:
   └─ Para cada conversa: formatContactId("56083800395891@lid", "554796474223")
   └─ Retorna: "(47) 9647-4223"

5. FRONTEND RECEBE:
   └─ { "contact": "(47) 9647-4223", "contact_name": "Servpro", ... }
```

### 7.2. Exemplo: Detalhe da Conversa

```
1. REQUEST:
   └─ GET /communication-hub/thread-info?thread_id=whatsapp_17

2. RESOLUÇÃO:
   └─ getWhatsAppThreadInfo() busca conversa
   └─ Detecta contact_external_id = "56083800395891@lid"
   └─ resolveLidPhone("56083800395891@lid", "imobsites", "wpp_gateway"):
      ├─ Query 1: SELECT phone_number FROM whatsapp_business_ids WHERE business_id = '56083800395891@lid'
      ├─ Se encontrado: retorna "554796474223"
      ├─ Se não encontrado: Query 2: SELECT phone_e164 FROM wa_pnlid_cache WHERE ...
      └─ Se não encontrado: busca em eventos (resolveLidPhoneFromEvents)

3. GARANTIA DE MAPEAMENTO:
   └─ Se encontrou telefone, verifica se está em whatsapp_business_ids
   └─ Se não está, cria: INSERT IGNORE INTO whatsapp_business_ids (business_id, phone_number, tenant_id) VALUES (...)
   └─ Salva no cache: INSERT INTO wa_pnlid_cache (...) ON DUPLICATE KEY UPDATE ...

4. FORMATAÇÃO:
   └─ formatContactId("56083800395891@lid", "554796474223")
   └─ Retorna: "(47) 9647-4223"

5. FRONTEND RECEBE:
   └─ { "contact": "(47) 9647-4223", "tenant_name": "Servpro", ... }
```

### 7.3. Exemplo: Mensagens da Conversa

```
1. REQUEST:
   └─ GET /communication-hub/messages?thread_id=whatsapp_17

2. BUSCA CONVERSA:
   └─ Busca conversa: SELECT contact_external_id, channel_id FROM conversations WHERE id = 17
   └─ contact_external_id = "554796474223" (número, não @lid)

3. BUSCA @lid MAPEADO (se aplicável):
   └─ Query: SELECT business_id FROM whatsapp_business_ids WHERE phone_number = '554796474223'
   └─ Encontra: "56083800395891@lid"
   └─ Adiciona aos padrões de busca

4. BUSCA EVENTOS:
   └─ Query: SELECT * FROM communication_events 
             WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
             AND (JSON_EXTRACT(payload, '$.from') LIKE '%554796474223%' 
                  OR JSON_EXTRACT(payload, '$.from') LIKE '%56083800395891@lid%' ...)
   └─ Retorna eventos

5. NORMALIZAÇÃO DE SENDER:
   └─ Para cada evento, normaliza sender:
      ├─ Se sender é @lid: resolveLidPhone() ou consulta cache
      ├─ Se sender é @c.us: remove sufixo e normaliza
      └─ Se sender é número: usa diretamente

6. FORMATAÇÃO:
   └─ formatContactId(sender, resolvedPhone)
   └─ Retorna string formatada

7. FRONTEND RECEBE:
   └─ [{ "from": "554796474223", "from_display": "(47) 9647-4223", "text": "...", ... }, ...]
```

---

## 8. CONCLUSÕES E RECOMENDAÇÕES

### 8.1. Pontos Fortes

✅ **Resolução em lote otimizada:** `resolveLidPhonesBatch()` evita problema N+1  
✅ **Múltiplas camadas de cache:** whatsapp_business_ids (persistente) + wa_pnlid_cache (temporário)  
✅ **Fallback robusto:** Busca em eventos quando cache não tem resultado  
✅ **Arquitetura remote_key:** Sistema funciona mesmo sem resolução (usa @lid como identidade)  
✅ **Tratamento de JID numérico:** @c.us e @s.whatsapp.net são extraídos diretamente  

### 8.2. Pontos Fracos

⚠️ **Resolução via API não implementada:** `normalizeSender()` tem função `resolvePnLidViaProvider()` mas não está completa  
⚠️ **Dependência de formattedName:** Fallback via eventos depende de campo que pode não existir  
⚠️ **Frontend recebe ID cru quando resolução falha:** Exibe "ID WhatsApp: XXXX" ao invés de número  
⚠️ **Cache não é consultado sem sessionId:** wa_pnlid_cache requer sessionId, mas pode não estar disponível  

### 8.3. Recomendações

1. **Implementar resolução via API:**
   - Completar função `resolvePnLidViaProvider()` em `CommunicationHubController`
   - Integrar com gateway WhatsApp para resolver @lid via API

2. **Melhorar fallback:**
   - Buscar telefone em mais campos do payload (não apenas formattedName)
   - Implementar busca recursiva mais robusta

3. **Melhorar tratamento de erro:**
   - Quando resolução falha, tentar buscar em mais fontes
   - Logar falhas para análise posterior

4. **Otimizar cache:**
   - Permitir consulta de wa_pnlid_cache sem sessionId (usar apenas provider + pnlid)
   - Implementar limpeza automática de cache expirado

5. **Documentação:**
   - Documentar formato esperado dos payloads
   - Criar guia de troubleshooting para resolução de @lid

---

## 9. ARQUIVOS RELEVANTES

### 9.1. Core/Helpers
- `src/Core/ContactHelper.php` - Funções principais de resolução
- `src/Services/PhoneNormalizer.php` - Normalização de telefones

### 9.2. Controllers
- `src/Controllers/CommunicationHubController.php` - Endpoints do Communication Hub
- `src/Services/ConversationService.php` - Resolução de conversas

### 9.3. Migrations
- `database/migrations/20260113_create_whatsapp_business_ids_table.php` - Tabela de mapeamento

### 9.4. Views
- `views/communication_hub/index.php` - Interface do Communication Hub

---

**Fim do Relatório**

