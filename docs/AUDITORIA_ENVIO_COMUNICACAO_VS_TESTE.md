# AUDITORIA EXAUSTIVA — ENVIO WhatsApp (Pixel Hub ↔ Gateway WPPConnect)

**Data:** 2026-01-16  
**Objetivo:** Entender por que o envio funciona na aba "Configurações > Teste" (envio por canal), mas falha no painel "Comunicação" ao enviar dentro de uma conversa selecionada (triade: tenant/canal/thread/conversa).

**Status:** 🔴 CRÍTICO — Erro 500 no painel de Comunicação, funcionando em Configurações > Teste

---

## 1. RESUMO EXECUTIVO

- ✅ **Configurações > Teste:** Funciona corretamente — mensagens enviadas com sucesso
- ❌ **Painel de Comunicação:** Erro 500 Internal Server Error ao enviar mensagem
- 🔍 **Problema Principal:** Requisição POST `/communication-hub/send` não está chegando ao PHP (sem logs)
- 📊 **Evidência:** Nenhum log `[CommunicationHub::send]` aparece nos logs do PHP após tentativa de envio
- 🎯 **Hipótese Principal:** Requisição pode estar falhando antes de chegar ao controller (Apache/router) OU há um erro fatal silencioso

---

## 2. MAPA DO FLUXO "CONFIGURAÇÕES > TESTE" (FUNCIONA ✅)

### 2.1. Rota e Controller

**Rota:** `POST /settings/whatsapp-gateway/test/send`  
**Controller:** `WhatsAppGatewayTestController::sendTest()`  
**Arquivo:** `src/Controllers/WhatsAppGatewayTestController.php` (linhas 253-505)

### 2.2. Fluxo Completo

1. **Frontend (JavaScript):**
   - Arquivo: `views/settings/whatsapp_gateway_test.php`
   - Função: `sendTestMessage()` (aproximadamente linha 1364)
   - Endpoint chamado: `POST /painel.pixel12digital/settings/whatsapp-gateway/test/send`
   - Payload enviado:
     ```javascript
     {
       channel_id: string,  // ex: "pixel12digital"
       phone: string,       // ex: "554796164699"
       message: string,     // texto da mensagem
       tenant_id: number    // opcional
     }
     ```

2. **Backend - Validação Inicial:**
   - Linha 255: `Auth::requireInternal()` — verifica autenticação
   - Linha 256: Define header `Content-Type: application/json`
   - Linhas 262-270: Extrai e valida parâmetros do `$_POST`
   - Linhas 272-282: Valida campos obrigatórios (channel_id, phone, message)

3. **Backend - Normalização:**
   - Linha 287: Normaliza telefone usando `WhatsAppBillingService::normalizePhone()`
   - Linhas 290-295: Valida telefone normalizado

4. **Backend - Configuração Gateway:**
   - Linha 300: Obtém configurações via `$this->getGatewayConfig()`
   - Retorna: `['baseUrl' => string, 'secret' => string]`
   - Linha 310: Instancia `WhatsAppGatewayClient($baseUrl, $secretDecrypted)`

5. **Backend - Verificação de Status (NÃO-BLOQUEANTE):**
   - Linha 314: `$gateway->getChannel($channelId)`
   - Linhas 331-392: Verifica se sessão está conectada
   - **Se desconectado:** Retorna erro 400 com `SESSION_DISCONNECTED` (linhas 376-387)
   - **Se conectado ou falha verificação:** Continua para envio

6. **Backend - Envio via Gateway:**
   - Linha 402: `$gateway->sendText($channelId, $phoneNormalized, $message, $metadata)`
   - Endpoint chamado: `POST {baseUrl}/api/messages`
   - Payload enviado:
     ```json
     {
       "channel": "pixel12digital",
       "to": "554796164699",
       "text": "mensagem aqui",
       "metadata": {
         "test": true,
         "sent_by": user_id,
         "sent_by_name": "Nome"
       }
     }
     ```
   - Headers:
     ```
     X-Gateway-Secret: {secret descriptografado}
     Content-Type: application/json
     Accept: application/json
     ```

7. **Backend - Tratamento de Resposta:**
   - Linhas 418-497: Processa resposta do gateway
   - Se sucesso:
     - Extrai `correlationId` e `message_id` do `raw`
     - Registra evento via `EventIngestionService::ingest()` (linhas 441-460)
     - Retorna JSON com `success: true`, `event_id`, `message_id`, `correlationId`
   - Se erro:
     - Retorna JSON com `success: false`, `error`, `status`, `correlationId`

### 2.3. Evidências de Funcionamento

**Logs esperados (quando funciona):**
```
[WhatsAppGatewayTest::sendTest] ===== INÍCIO VALIDAÇÃO =====
[WhatsAppGatewayTest::sendTest] channel_id (após trim): 'pixel12digital'
[WhatsAppGatewayTest::sendTest] ✅ Validações básicas passaram
[WhatsAppGatewayTest::sendTest] ✅ Sessão conectada - permitindo envio
[WhatsAppGatewayTest::sendTest] Resultado do gateway: {"success":true,"status":200,...}
```

**Resposta HTTP esperada:**
```json
{
  "success": true,
  "status": 200,
  "raw": {...},
  "correlationId": "...",
  "message_id": null,
  "event_id": "...",
  "error": null
}
```

---

## 3. MAPA DO FLUXO "COMUNICAÇÃO > CONVERSA" (FALHA ❌)

### 3.1. Rota e Controller

**Rota:** `POST /communication-hub/send`  
**Controller:** `CommunicationHubController::send()`  
**Arquivo:** `src/Controllers/CommunicationHubController.php` (linhas 290-1078)

### 3.2. Fluxo Completo (Teórico)

1. **Frontend (JavaScript):**
   - Arquivo: `views/communication_hub/index.php`
   - Função: `sendMessageFromPanel(e)` (aproximadamente linha 1951)
   - Endpoint chamado: `POST /painel.pixel12digital/communication-hub/send`
   - Payload enviado:
     ```javascript
     {
       channel: "whatsapp",
       channel_id: "pixel12digital",  // do thread.channel_id
       thread_id: "whatsapp_5",
       to: "5511940863773",           // do thread.contact
       message: string,               // texto da mensagem
       tenant_id: number              // do thread.tenant_id
     }
     ```

2. **Backend - Validação Inicial:**
   - Linha 292-296: Define header `Content-Type: application/json` **ANTES** de qualquer output
   - Linhas 298-301: Limpa output buffers
   - Linha 304: `Auth::requireInternal()` — verifica autenticação
   - Linhas 306-315: Extrai parâmetros do `$_POST`
   - Linhas 320-327: Logs iniciais detalhados

3. **Backend - Resolução de Canal (LÓGICA COMPLEXA):**
   - Linhas 367-369: Inicializa `$targetChannels = []`
   - **PRIORIDADE 1:** Se `threadId` presente (linhas 373-442):
     - Extrai `conversationId` de `threadId` (ex: `whatsapp_5` → `5`)
     - Busca conversation: `SELECT tenant_id, channel_id, contact_external_id FROM conversations WHERE id = ?`
     - Se `tenant_id` NULL, tenta resolver via `resolveTenantByChannelId()`
     - **CRÍTICO:** Usa `channel_id` da conversation (ignora `channel_id` do frontend)
     - Valida que canal existe e está habilitado:
       ```sql
       SELECT channel_id, gateway_secret, base_url
       FROM tenant_message_channels
       WHERE provider = 'wpp_gateway'
       AND is_enabled = 1
       AND channel_id = ?
       ```
     - Define `$targetChannels = [$foundChannelId]`
   - **PRIORIDADE 2:** Se `forwardToAll` (linhas 449-466): Busca todos os canais habilitados
   - **PRIORIDADE 3:** Se `channelIdsArray` (linhas 468-513): Valida canais fornecidos
   - **PRIORIDADE 4:** Fallback (linhas 516-709): Busca canal genérico habilitado

4. **Backend - Validação Final:**
   - Linhas 714-723: Verifica que `$targetChannels` não está vazio
   - Linha 728: Normaliza telefone usando `WhatsAppBillingService::normalizePhone()`

5. **Backend - Configuração Gateway:**
   - Linhas 734-756: Tenta obter credenciais específicas do canal (base_url, gateway_secret)
   - Linhas 758-764: Fallback para credenciais globais (`WPP_GATEWAY_BASE_URL`, `GatewaySecret::getDecrypted()`)
   - Linha 786: Instancia `WhatsAppGatewayClient($baseUrl, $secret)`

6. **Backend - Envio (loop por canal):**
   - Linha 821: Itera sobre `$targetChannels`
   - Linha 835: Verifica status do canal (`$gateway->getChannel($targetChannelId)`)
   - Linhas 916-931: Se bloqueado (sessão desconectada, 401, 404), pula canal
   - Linha 937: `$gateway->sendText($targetChannelId, $phoneNormalized, $message, $metadata)`
   - Linhas 962-1020: Processa resultado

### 3.3. Problema Identificado: Requisição Não Chega ao Backend

**Evidência 1: Nenhum Log Aparece**
```
❌ Nenhum log [CommunicationHub::send] ===== INÍCIO MÉTODO ===== aparece
❌ Nenhum log Router::dispatch: Buscando rota POST /communication-hub/send
❌ Nenhum log 🔍 POST /communication-hub/send DETECTADO
```

**Evidência 2: Frontend Mostra Erro 500**
```
POST http://localhost/painel.pixel12digital/communication-hub/send 500 (Internal Server Error)
```

**Evidência 3: Rota Está Registrada**
```php
// public/index.php linha 553
$router->post('/communication-hub/send', 'CommunicationHubController@send');
```

**Conclusão:** A requisição está falhando **antes** de chegar ao método `send()` ou há um erro fatal silencioso que impede a execução dos logs.

---

## 4. COMPARATIVO LADO A LADO

| Aspecto | Configurações > Teste ✅ | Comunicação ❌ |
|---------|-------------------------|----------------|
| **Rota** | `POST /settings/whatsapp-gateway/test/send` | `POST /communication-hub/send` |
| **Controller** | `WhatsAppGatewayTestController::sendTest()` | `CommunicationHubController::send()` |
| **Autenticação** | `Auth::requireInternal()` (linha 255) | `Auth::requireInternal()` (linha 304) |
| **Headers** | `Content-Type: application/json` (após Auth) | `Content-Type: application/json` (ANTES de Auth) |
| **Output Buffer** | Não limpa explicitamente | Limpa explicitamente (linhas 298-301) |
| **Parâmetros Recebidos** | `channel_id`, `phone`, `message`, `tenant_id` | `channel`, `channel_id`, `thread_id`, `to`, `message`, `tenant_id` |
| **Resolução de Canal** | Direto do `$_POST['channel_id']` | Lógica complexa: prioriza `conversations.channel_id` |
| **Validação de Canal** | Não valida existência no banco antes de enviar | Valida existência e status no banco |
| **Configuração Gateway** | `getGatewayConfig()` (helper) | Busca do banco OU globais |
| **Verificação de Status** | Verifica antes de enviar (não-bloqueante) | Verifica antes de enviar (pode bloquear) |
| **Tratamento de Erro** | Try/catch simples | Try/catch múltiplos com logs detalhados |
| **Logs** | Extensivos (linhas 258-417) | Extensivos (linhas 320-960+) |
| **Endpoint Gateway** | `POST {baseUrl}/api/messages` | `POST {baseUrl}/api/messages` (igual) |
| **Payload Gateway** | `{channel, to, text, metadata}` | `{channel, to, text, metadata}` (igual) |
| **Headers Gateway** | `X-Gateway-Secret`, `Content-Type`, `Accept` | `X-Gateway-Secret`, `Content-Type`, `Accept` (igual) |

### 4.1. Diferenças Críticas

1. **Ordem de Headers vs Auth:**
   - **Teste:** Define header após `Auth::requireInternal()`
   - **Comunicação:** Define header ANTES de `Auth::requireInternal()`
   - **Impacto:** Se `Auth::requireInternal()` faz redirect ou exit, pode causar "headers already sent"

2. **Limpeza de Output Buffer:**
   - **Teste:** Não limpa explicitamente
   - **Comunicação:** Limpa explicitamente (pode mascarar erros)

3. **Resolução de Canal:**
   - **Teste:** Usa `channel_id` diretamente do POST
   - **Comunicação:** Lógica complexa com múltiplas prioridades — pode ter bug na resolução

4. **Validação de Canal:**
   - **Teste:** Não valida no banco antes de enviar
   - **Comunicação:** Valida no banco e pode bloquear se canal não encontrado

---

## 5. ESTRUTURA DO BANCO DE DADOS (REQUER VERIFICAÇÃO)

### 5.1. Tabelas Envolvidas

1. **`conversations`**
   - Campos relevantes: `id`, `tenant_id`, `channel_id`, `contact_external_id`, `last_message_at`
   - Relacionamento: `tenant_id` → `tenants.id`, `channel_id` → `tenant_message_channels.channel_id`
   - **Chave Primária:** `id` (INT AUTO_INCREMENT)
   - **Índices:** Verificar se há índices em `tenant_id`, `channel_id`, `contact_external_id`

2. **`tenant_message_channels`**
   - Campos relevantes: `id`, `tenant_id`, `channel_id`, `provider`, `is_enabled`, `gateway_secret`, `base_url`
   - Filtro: `provider = 'wpp_gateway' AND is_enabled = 1`
   - **Chave Primária:** `id` (INT AUTO_INCREMENT)
   - **Índices:** Verificar se há índice único em `channel_id` ou `(provider, channel_id)`

3. **`communication_events`**
   - Campos relevantes: `event_id`, `event_type`, `tenant_id`, `payload`, `metadata`, `created_at`
   - Eventos: `whatsapp.inbound.message`, `whatsapp.outbound.message`
   - **Chave Primária:** `event_id` (VARCHAR/UUID)
   - **Índices:** Verificar índices em `tenant_id`, `event_type`, `created_at`

### 5.2. Diferenças na Obtenção de Credenciais

**Teste (WhatsAppGatewayTestController::sendTest):**
- Linha 300: Usa `$this->getGatewayConfig()` (helper do controller)
- `getGatewayConfig()` (linhas 25-83):
  - Lê `WPP_GATEWAY_SECRET` de `Env::get()`
  - Detecta se está criptografado (base64 longo)
  - Descriptografa usando `CryptoHelper::decrypt()` se necessário
  - Lê `WPP_GATEWAY_BASE_URL` de `Env::get()`
  - Retorna: `['secret' => string, 'baseUrl' => string]`

**Comunicação (CommunicationHubController::send):**
- Linhas 760-775: Tenta obter credenciais do banco primeiro (canal específico)
- Query busca `base_url` e `gateway_secret` de `tenant_message_channels`
- Linha 759: Define `$secret = null`
- Linha 784: Fallback para `GatewaySecret::getDecrypted()` se `$secret` vazio
- Linha 781: Fallback para `Env::get('WPP_GATEWAY_BASE_URL')` se `$baseUrl` vazio

**Diferença Crítica:**
- **Teste:** Sempre usa secret de `.env` (via `getGatewayConfig()`)
- **Comunicação:** Tenta usar secret do banco primeiro, depois fallback para `.env`
- **Impacto:** Se `gateway_secret` no banco estiver NULL ou incorreto, pode causar erro ao instanciar `WhatsAppGatewayClient`

### 5.2. Queries Críticas no Fluxo de Comunicação

**Query 1: Busca Conversation (linha 378)**
```sql
SELECT tenant_id, channel_id, contact_external_id
FROM conversations
WHERE id = ?
```

**Query 2: Valida Canal da Conversation (linhas 404-414)**
```sql
SELECT channel_id, gateway_secret, base_url
FROM tenant_message_channels
WHERE provider = 'wpp_gateway'
AND is_enabled = 1
AND (channel_id = ? OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?)))
LIMIT 1
```

**Possíveis Problemas:**
- `conversations.channel_id` pode estar NULL
- `conversations.channel_id` pode não corresponder exatamente a `tenant_message_channels.channel_id` (case-sensitive ou espaços)
- Múltiplos canais habilitados para o mesmo tenant

### 5.3. Queries Recomendadas para Verificação

```sql
-- 1. Verificar conversations sem channel_id
SELECT id, tenant_id, channel_id, contact_external_id
FROM conversations
WHERE channel_id IS NULL OR channel_id = '';

-- 2. Verificar conversations com channel_id que não existe em tenant_message_channels
SELECT c.id, c.channel_id, c.tenant_id
FROM conversations c
LEFT JOIN tenant_message_channels tmc ON (
    tmc.provider = 'wpp_gateway' 
    AND tmc.is_enabled = 1
    AND (tmc.channel_id = c.channel_id OR LOWER(TRIM(tmc.channel_id)) = LOWER(TRIM(c.channel_id)))
)
WHERE c.channel_id IS NOT NULL
AND tmc.id IS NULL;

-- 3. Verificar variações de channel_id (case/espaços)
SELECT DISTINCT channel_id
FROM tenant_message_channels
WHERE provider = 'wpp_gateway';

-- 4. Verificar conversa específica (thread_id = whatsapp_5)
SELECT id, tenant_id, channel_id, contact_external_id, last_message_at
FROM conversations
WHERE id = 5;

-- 5. Verificar canais habilitados
SELECT id, tenant_id, channel_id, is_enabled, provider
FROM tenant_message_channels
WHERE provider = 'wpp_gateway'
AND is_enabled = 1;
```

---

## 6. CONTRATO DO GATEWAY (VPS)

### 6.1. Endpoint de Envio

**URL Base:** `https://wpp.pixel12digital.com.br` (ou valor de `WPP_GATEWAY_BASE_URL`)  
**Endpoint:** `POST /api/messages`  
**Arquivo:** `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` (linha 98)

### 6.2. Autenticação

**Header:** `X-Gateway-Secret: {secret descriptografado}`  
**Fonte do Secret:**
- Fluxo Teste: `WhatsAppGatewayTestController::getGatewayConfig()['secret']`
- Fluxo Comunicação: `GatewaySecret::getDecrypted()` ou `tenant_message_channels.gateway_secret`

### 6.3. Payload Enviado

```json
{
  "channel": "pixel12digital",
  "to": "5511940863773",
  "text": "mensagem aqui",
  "metadata": {
    "sent_by": 1,
    "sent_by_name": "Nome do Usuário"
  }
}
```

### 6.4. Resposta Esperada

**Sucesso (200):**
```json
{
  "id": "...",
  "correlationId": "...",
  "status": "sent",
  ...
}
```

**Erro (400/401/404/500):**
```json
{
  "error": "mensagem de erro",
  "status": "...",
  ...
}
```

**Normalização no Cliente:**
- `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` linhas 100-115
- Extrai `message_id` e `correlationId` do `raw`
- Retorna estrutura padronizada: `{success, status, raw, message_id, correlationId, error}`

### 6.5. Verificação de Status do Canal

**Endpoint:** `GET /api/channels/{channelId}`  
**Arquivo:** `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` (linha 61)  
**Resposta Esperada:**
```json
{
  "channel": {
    "id": "pixel12digital",
    "status": "connected",
    ...
  }
}
```

**Campos Verificados (ambos os fluxos):**
- `channel.status` (prioridade)
- `channel.connection`
- `connected` (boolean)
- Outros campos de fallback

---

## 7. REPRODUÇÃO E CAPTURA

### 7.1. Caso Funcionando (Configurações > Teste)

**Ação:**
1. Acessar `/settings/whatsapp-gateway/test`
2. Selecionar canal: `pixel12digital`
3. Inserir telefone: `554796164699`
4. Inserir mensagem: "Teste"
5. Clicar em "Enviar"

**Resultado:** ✅ Sucesso  
**Logs Capturados:**
```
[WhatsAppGatewayTest::sendTest] ===== INÍCIO VALIDAÇÃO =====
[WhatsAppGatewayTest::sendTest] ✅ Validações básicas passaram
[WhatsAppGatewayTest::sendTest] ✅ Sessão conectada - permitindo envio
[WhatsAppGateway::request] POST /api/messages - HTTP 200
[WhatsAppGatewayTest::sendTest] Resultado do gateway: {"success":true,...}
```

**Resposta HTTP:**
```
Status: 200 OK
Body: {"success":true,"status":200,"raw":{...},"correlationId":"...","message_id":null,"event_id":"..."}
```

### 7.2. Caso Falhando (Comunicação)

**Ação:**
1. Acessar `/communication-hub?thread_id=whatsapp_5&channel=whatsapp`
2. Inserir mensagem no textarea
3. Clicar em "Enviar" ou pressionar Enter

**Resultado:** ❌ Erro 500 Internal Server Error  
**Logs Capturados:**
```
❌ NENHUM LOG [CommunicationHub::send]
❌ NENHUM LOG Router::dispatch POST /communication-hub/send
❌ NENHUM LOG 🔍 POST /communication-hub/send DETECTADO
```

**Resposta HTTP:**
```
Status: 500 Internal Server Error
Body: (vazio ou HTML de erro)
```

**Console JavaScript:**
```javascript
POST http://localhost/painel.pixel12digital/communication-hub/send 500 (Internal Server Error)
```

### 7.3. Comparação dos Requests

**Request Funcionando (Teste):**
```
POST /painel.pixel12digital/settings/whatsapp-gateway/test/send
Content-Type: application/x-www-form-urlencoded

channel_id=pixel12digital&phone=554796164699&message=Teste&tenant_id=121
```

**Request Falhando (Comunicação):**
```
POST /painel.pixel12digital/communication-hub/send
Content-Type: application/x-www-form-urlencoded

channel=whatsapp&channel_id=pixel12digital&thread_id=whatsapp_5&to=5511940863773&message=Teste&tenant_id=121
```

**Diferença Principal:** A requisição de Comunicação não está chegando ao backend PHP (sem logs).

---

## 8. HIPÓTESES E CAUSAS PROVÁVEIS

### 8.1. Hipótese 1: Erro Fatal Silencioso (ALTA PROBABILIDADE)

**Descrição:** Um erro fatal do PHP está ocorrendo antes dos logs serem escritos, possivelmente durante:
- Autoload de classes
- Inicialização do router
- Definição de constantes/variáveis de ambiente

**Evidências:**
- Nenhum log aparece, nem mesmo do `index.php` (linha 292)
- Erro 500 genérico
- Rota está registrada corretamente

**Como Confirmar:**
1. Verificar logs do Apache (`C:\xampp\apache\logs\error.log`)
2. Verificar se há erros de sintaxe PHP
3. Verificar se `display_errors` está habilitado
4. Adicionar `error_reporting(E_ALL)` no início do `index.php`

**Correção Candidata:**
- Habilitar `display_errors` temporariamente
- Adicionar try/catch global no `index.php`
- Verificar autoload de classes

### 8.2. Hipótese 2: Headers Already Sent (ALTA PROBABILIDADE) ⭐⭐

**Descrição:** O método `CommunicationHubController::send()` define header **ANTES** de `Auth::requireInternal()`, que pode fazer `exit` se não autenticado. Se houver output anterior (erros, warnings, whitespace), pode causar "headers already sent".

**Evidências:**
- ✅ Teste define header **APÓS** `Auth::requireInternal()` (linha 256)
- ❌ Comunicação define header **ANTES** de `Auth::requireInternal()` (linhas 292-296)
- ⚠️ Comunicação limpa output buffer explicitamente (pode mascarar problema)
- 📊 Se `Auth::requireInternal()` faz `exit` sem limpar buffer, pode gerar erro silencioso

**Código Comparativo:**

**Teste (FUNCIONA):**
```php
// Linha 255
Auth::requireInternal(); // ← Auth PRIMEIRO
// Linha 256
header('Content-Type: application/json'); // ← Header DEPOIS
```

**Comunicação (FALHA):**
```php
// Linhas 292-296
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8'); // ← Header PRIMEIRO
}
while (ob_get_level() > 0) {
    @ob_end_clean();
}
// Linha 304
Auth::requireInternal(); // ← Auth DEPOIS (pode fazer exit)
```

**Como Confirmar:**
1. **Verificar se `Auth::requireInternal()` está fazendo exit silencioso:**
   - Adicionar log antes e depois de `Auth::requireInternal()`
   - Verificar se há redirect/exit no método `requireInternal()`

2. **Verificar se há whitespace/output antes:**
   - Verificar arquivos incluídos antes de `CommunicationHubController::send()`
   - Procurar por `?>` seguido de whitespace em arquivos PHP

3. **Testar invertendo ordem:**
   - Mover `Auth::requireInternal()` para ANTES da definição de header
   - Verificar se erro desaparece

**Correção Candidata:**
```php
// Em CommunicationHubController::send() - linhas 292-304
// ANTES (ORDEM ATUAL):
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
while (ob_get_level() > 0) {
    @ob_end_clean();
}
Auth::requireInternal();

// DEPOIS (ORDEM CORRIGIDA - igual ao Teste):
Auth::requireInternal(); // ← Auth PRIMEIRO (garante que se falhar, faz exit limpo)
while (ob_get_level() > 0) {
    @ob_end_clean();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8'); // ← Header DEPOIS
}
```

### 8.3. Hipótese 3: Auth::requireInternal() Faz Exit Silencioso (ALTA PROBABILIDADE) ⭐⭐

**Descrição:** O método `Auth::requireInternal()` pode estar fazendo `exit` silencioso se:
1. Usuário não está autenticado
2. Usuário não é interno
3. Para requisições JSON, retorna JSON com 401/403 e faz `exit`

**Evidências:**
- `Auth::requireInternal()` (linhas 122-153) verifica autenticação PRIMEIRO
- Se não autenticado, chama `requireAuth()` que faz `exit` (linha 124)
- `requireAuth()` (linhas 85-117) verifica se é requisição JSON
- Para JSON, limpa output buffer e retorna JSON 401, depois `exit` (linhas 96-106)
- Se não é JSON, faz redirect com `exit` (linhas 109-115)

**Código Relevante (`src/Core/Auth.php`):**
```php
public static function requireInternal(): void
{
    self::requireAuth(); // ← Pode fazer exit aqui se não autenticado
    
    if (!self::isInternal()) {
        // Verifica se é requisição JSON
        $isJsonRequest = (
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );
        
        if ($isJsonRequest) {
            // Limpa output buffer e retorna JSON 403, depois exit
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Acesso negado. Apenas usuários internos podem acessar esta área.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit; // ← EXIT aqui!
        }
    }
}
```

**Problema Potencial:**
- `CommunicationHubController::send()` define header **ANTES** de chamar `Auth::requireInternal()`
- Se `Auth::requireInternal()` tentar definir header novamente (para retornar 401/403), pode causar "headers already sent"
- Isso pode gerar um erro fatal silencioso que não aparece nos logs

**Como Confirmar:**
1. **Verificar se usuário está autenticado:**
   - Adicionar log antes de `Auth::requireInternal()`:
     ```php
     error_log("[CommunicationHub::send] ANTES Auth::requireInternal() - user: " . json_encode(Auth::user()));
     ```

2. **Verificar se é requisição JSON detectada:**
   - Adicionar log em `Auth::requireInternal()` para ver se está entrando no bloco JSON

3. **Testar com usuário autenticado:**
   - Garantir que usuário está logado e é interno
   - Tentar enviar mensagem novamente

**Correção Candidata:**
- **OPÇÃO 1:** Mover `Auth::requireInternal()` para ANTES de definir header (recomendado)
- **OPÇÃO 2:** Modificar `Auth::requireInternal()` para não definir headers se já foram definidos
- **OPÇÃO 3:** Verificar autenticação ANTES de chamar `Auth::requireInternal()` e retornar erro explicitamente

### 8.4. Hipótese 4: Problema no Router (MÉDIA PROBABILIDADE)

**Descrição:** O router pode não estar encontrando a rota POST ou há um erro durante o dispatch.

**Evidências:**
- ❌ Logs do router não aparecem para POST `/communication-hub/send`
- ✅ Logs aparecem para GET `/communication-hub/*`
- ✅ Rota está registrada corretamente em `public/index.php` linha 553

**Como Confirmar:**
1. **Adicionar log no início do `Router::dispatch()` para TODOS os métodos POST:**
   - Modificar `Router::dispatch()` para logar TODOS os POST antes de buscar rota
   - Verificar se a requisição está chegando ao router

2. **Verificar se `matchPath()` está funcionando para POST:**
   - Adicionar log em `matchPath()` para ver se está fazendo match correto

3. **Testar rota POST manualmente com curl:**
   ```bash
   curl -X POST http://localhost/painel.pixel12digital/communication-hub/send \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "channel=whatsapp&channel_id=pixel12digital&thread_id=whatsapp_5&to=5511940863773&message=teste"
   ```

**Correção Candidata:**
- Adicionar logs extensivos no `Router::dispatch()` para POST (já implementado parcialmente)
- Verificar normalização do path (`rtrim`, etc.)
- Adicionar try/catch no `Router::executeHandler()` para capturar erros fatais

### 8.5. Hipótese 5: Content-Type Não Detectado Como JSON (ALTA PROBABILIDADE) ⭐⭐⭐

**Descrição:** O frontend envia `Content-Type: application/x-www-form-urlencoded`, mas `Auth::requireInternal()` verifica se é JSON via `CONTENT_TYPE` ou `HTTP_ACCEPT`. Se não detectar como JSON, pode fazer redirect (exit) que causa erro 500.

**Evidências:**
- **Frontend Comunicação** (linha 2003): `'Content-Type': 'application/x-www-form-urlencoded'`
- **Frontend Teste** (linha 312): Não define `Content-Type` explicitamente (deixa fetch definir como `multipart/form-data`)
- **Auth::requireInternal()** (linhas 128-132) verifica:
  ```php
  $isJsonRequest = (
      (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
      (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
      (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  );
  ```
- Se `$isJsonRequest = false` e usuário não é interno, faz `exit` com HTML 403 (linhas 148-151)
- Isso pode causar erro 500 se headers já foram definidos antes

**Como Confirmar:**
1. **Adicionar log em `Auth::requireInternal()` (linha 132):**
   ```php
   error_log("[Auth::requireInternal] HTTP_ACCEPT: " . ($_SERVER['HTTP_ACCEPT'] ?? 'N/A'));
   error_log("[Auth::requireInternal] CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
   error_log("[Auth::requireInternal] HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'N/A'));
   error_log("[Auth::requireInternal] isJsonRequest: " . ($isJsonRequest ? 'SIM' : 'NÃO'));
   ```

2. **Verificar se frontend está enviando `Accept: application/json`:**
   - Modificar frontend para enviar header `Accept: application/json`

3. **Testar enviando com `Accept: application/json`:**
   - Modificar fetch para incluir `'Accept': 'application/json'`

**Correção Candidata:**
- **OPÇÃO 1 (Recomendado):** Modificar frontend para enviar header `Accept: application/json`:
  ```javascript
  const response = await fetch(sendUrl, {
      method: 'POST',
      headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json', // ← ADICIONAR
          'X-Requested-With': 'XMLHttpRequest' // ← ADICIONAR (garantir)
      },
      body: new URLSearchParams(formData)
  });
  ```

- **OPÇÃO 2:** Modificar `Auth::requireInternal()` para também aceitar `Content-Type: application/x-www-form-urlencoded` como requisição AJAX se vier de XMLHttpRequest

### 8.6. Hipótese 6: Erro na Lógica de Resolução de Canal (BAIXA PROBABILIDADE)

**Descrição:** A lógica complexa de resolução de canal pode estar causando um erro fatal (ex: variável não definida, SQL error).

**Evidências:**
- ❌ Nenhum log aparece, então não chegou a executar
- ⚠️ Lógica é muito mais complexa que no Teste (linhas 367-709)
- ⚠️ Múltiplas queries SQL que podem falhar

**Como Confirmar:**
- Se a requisição chegar ao método, os logs mostrarão onde está falhando
- Verificar queries SQL executadas

**Correção Candidata:**
- Simplificar lógica de resolução
- Adicionar validações defensivas
- Usar try/catch específicos para cada query

---

## 9. CORREÇÕES CANDIDATAS (NÃO IMPLEMENTAR AGORA)

### 9.1. Correção 1: Adicionar Headers Accept e X-Requested-With no Frontend ⭐⭐⭐

**Prioridade:** CRÍTICA  
**Arquivo:** `views/communication_hub/index.php`  
**Alteração (linhas 1996-2006):**
```javascript
// ANTES:
const response = await fetch(sendUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams(formData)
});

// DEPOIS:
const response = await fetch(sendUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json', // ← ADICIONAR
        'X-Requested-With': 'XMLHttpRequest' // ← ADICIONAR
    },
    body: new URLSearchParams(formData)
});
```

**Justificativa:** Garante que `Auth::requireInternal()` detecte a requisição como JSON/AJAX e retorne JSON 403 ao invés de fazer redirect (exit) que causa erro 500.

### 9.2. Correção 2: Mover Headers Para Após Auth ⭐⭐

**Prioridade:** ALTA  
**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Alteração (linhas 292-304):**
```php
// ANTES (linhas 292-304):
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
while (ob_get_level() > 0) {
    @ob_end_clean();
}
Auth::requireInternal();

// DEPOIS:
Auth::requireInternal(); // Move para primeiro (garante exit limpo se falhar)
while (ob_get_level() > 0) {
    @ob_end_clean();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
```

**Justificativa:** Alinha com o padrão do fluxo de Teste e evita problemas com "headers already sent". Garante que se `Auth::requireInternal()` fizer exit, não terá tentado definir header antes.

### 9.3. Correção 3: Adicionar Try/Catch Global no Router

**Prioridade:** ALTA  
**Arquivo:** `src/Core/Router.php`  
**Alteração (linha 103):**
```php
// No método dispatch(), adicionar try/catch antes de executeHandler()
try {
    $this->executeHandler($route['handler']);
} catch (\Throwable $e) {
    error_log("[Router] FATAL: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Erro interno'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
```

**Justificativa:** Captura erros fatais antes que quebrem silenciosamente.

### 9.4. Correção 4: Simplificar Lógica de Resolução de Canal

**Prioridade:** MÉDIA  
**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Alteração:** Refatorar linhas 367-709 para:
1. Sempre buscar conversation primeiro (se `threadId` presente)
2. Usar `channel_id` da conversation diretamente
3. Remover lógica de fallback complexa

**Justificativa:** Reduz pontos de falha e facilita debug.

---

## 10. PRÓXIMOS TESTES RECOMENDADOS (ORDEM EXATA)

### 10.1. Teste 1: Verificar Content-Type da Requisição (PRIORIDADE MÁXIMA) ⭐⭐⭐

**Objetivo:** Confirmar se `Auth::requireInternal()` está detectando a requisição como JSON

**Ação:**
1. Adicionar log em `Auth::requireInternal()` (linha 128):
   ```php
   error_log("[Auth::requireInternal] HTTP_ACCEPT: " . ($_SERVER['HTTP_ACCEPT'] ?? 'N/A'));
   error_log("[Auth::requireInternal] CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
   error_log("[Auth::requireInternal] HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'N/A'));
   error_log("[Auth::requireInternal] isJsonRequest: " . ($isJsonRequest ? 'SIM' : 'NÃO'));
   ```

2. Tentar enviar mensagem no painel de Comunicação
3. Verificar logs para ver se está detectando como JSON

**Resultado Esperado:**
- Se não detectar como JSON, `Auth::requireInternal()` pode estar fazendo redirect (exit) que causa erro 500
- Se detectar como JSON, deve retornar 403 JSON (não 500)

**Correção Candidata:**
- Modificar `Auth::requireInternal()` para detectar requisições AJAX também via `HTTP_X_REQUESTED_WITH: xmlhttprequest`
- OU modificar frontend para enviar `Content-Type: application/json` e `Accept: application/json`

### 10.2. Teste 2: Verificar Logs do Apache (PRIORIDADE ALTA) ⭐⭐

**Objetivo:** Capturar erros PHP fatais que não aparecem nos logs do PHP

**Ação:**
1. Abrir `C:\xampp\apache\logs\error.log`
2. Limpar o arquivo ou anotar última linha
3. Tentar enviar mensagem no painel de Comunicação
4. Verificar se há novos erros no log

**Resultado Esperado:**
- Se houver erro fatal do PHP, aparecerá no log do Apache
- Pode mostrar "headers already sent", "Call to undefined function", etc.

### 10.3. Teste 3: Habilitar Display Errors Temporariamente (PRIORIDADE ALTA) ⭐⭐

**Objetivo:** Ver erro na tela ao invés de 500 genérico

**Ação:**
1. Adicionar no início de `public/index.php` (ANTES de qualquer output):
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', '1');
   ini_set('log_errors', '1');
   ```

2. Tentar enviar mensagem no painel de Comunicação
3. Ver erro na tela (não apenas 500 genérico)

**Resultado Esperado:**
- Mostrará erro exato na tela
- Pode mostrar "headers already sent", "Fatal error", etc.

**IMPORTANTE:** Remover após diagnóstico!

### 10.4. Teste 4: Adicionar Log no Router para TODOS os POST (PRIORIDADE ALTA) ⭐⭐

**Objetivo:** Confirmar se a requisição está chegando ao router

**Ação:**
1. Modificar `Router::dispatch()` (linha 70) para logar TODOS os POST:
   ```php
   if ($method === 'POST') {
       error_log("[Router::dispatch] 🔍 POST REQUEST: path={$path}, REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
   }
   ```

2. Tentar enviar mensagem no painel de Comunicação
3. Verificar se log aparece

**Resultado Esperado:**
- Se log aparecer, requisição está chegando ao router
- Se log não aparecer, requisição está falhando antes (Apache/whitespace)

### 10.5. Teste 5: Testar Rota POST Manualmente (PRIORIDADE MÉDIA) ⭐

**Objetivo:** Isolar problema do navegador vs backend

**Ação:**
1. Usar curl/Postman para fazer POST direto:
   ```bash
   curl -X POST http://localhost/painel.pixel12digital/communication-hub/send \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -H "Cookie: {cookie de sessão}" \
     -d "channel=whatsapp&channel_id=pixel12digital&thread_id=whatsapp_5&to=5511940863773&message=teste"
   ```

2. Ver resposta e logs

**Resultado Esperado:**
- Se funcionar, problema é no JavaScript
- Se falhar igual, problema é no backend

### 10.6. Teste 6: Verificar Banco de Dados (PRIORIDADE MÉDIA) ⭐

**Objetivo:** Confirmar integridade da triade (conversation/channel/tenant)

**Ação:**
1. Executar queries da seção 5.3:
   ```sql
   -- Query 1: Verificar conversation_id=5
   SELECT id, tenant_id, channel_id, contact_external_id, last_message_at
   FROM conversations
   WHERE id = 5;
   
   -- Query 2: Verificar canal existe e está habilitado
   SELECT id, tenant_id, channel_id, provider, is_enabled, base_url, gateway_secret IS NOT NULL as has_secret
   FROM tenant_message_channels
   WHERE provider = 'wpp_gateway'
   AND is_enabled = 1
   AND (channel_id = 'pixel12digital' OR LOWER(TRIM(channel_id)) = LOWER(TRIM('pixel12digital')));
   ```

2. Verificar se dados estão consistentes

**Resultado Esperado:**
- Conversation deve ter `channel_id` correto
- Canal deve existir em `tenant_message_channels` e estar `is_enabled = 1`

### 10.7. Teste 7: Comparar Requisições HTTP (PRIORIDADE BAIXA)

**Objetivo:** Identificar diferenças sutis entre requisições

**Ação:**
1. Capturar requisição funcionando (Teste) com DevTools Network
2. Capturar requisição falhando (Comunicação) com DevTools Network
3. Comparar:
   - Headers (especialmente `Content-Type`, `Accept`, `Cookie`)
   - Body (payload exato)
   - URL completa

**Resultado Esperado:**
- Identificar diferenças que possam estar causando o problema

---

## 11. CHECKLIST DE ARQUIVOS ANALISADOS

- [x] `src/Controllers/WhatsAppGatewayTestController.php` (linhas 253-505)
- [x] `src/Controllers/CommunicationHubController.php` (linhas 290-1078)
- [x] `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` (completo)
- [x] `public/index.php` (rotas e bootstrap)
- [x] `src/Core/Router.php` (linhas 70-239)
- [x] `src/Core/Controller.php` (método json())
- [x] `views/settings/whatsapp_gateway_test.php` (função sendTestMessage)
- [x] `views/communication_hub/index.php` (função sendMessageFromPanel)

---

## 12. CHECKLIST DE PONTOS DE DIVERGÊNCIA ENCONTRADOS

- [x] **Ordem de Headers vs Auth:** Teste define após, Comunicação define antes
- [x] **Limpeza de Output Buffer:** Teste não limpa, Comunicação limpa
- [x] **Resolução de Canal:** Teste usa direto, Comunicação usa lógica complexa
- [x] **Validação de Canal:** Teste não valida no banco, Comunicação valida
- [x] **Tratamento de Erro:** Comunicação tem mais try/catch aninhados
- [x] **Configuração Gateway:** Teste usa helper, Comunicação busca do banco

---

## 13. CONCLUSÃO

O problema principal é que a requisição POST `/communication-hub/send` **não está chegando ao backend PHP** (nenhum log aparece), indicando um erro fatal silencioso ou um problema no router/Apache.

**Causa Mais Provável (Ranking):**

1. **⭐⭐⭐ Content-Type não detectado como JSON/AJAX** (ALTA PROBABILIDADE)
   - Frontend envia `Content-Type: application/x-www-form-urlencoded`
   - Frontend **NÃO** envia `Accept: application/json` nem `X-Requested-With: XMLHttpRequest`
   - `Auth::requireInternal()` não detecta como requisição JSON
   - `Auth::requireInternal()` tenta fazer redirect (exit) que causa erro 500
   - Headers já foram definidos antes, causando "headers already sent"

2. **⭐⭐ Headers já definidos antes de Auth** (ALTA PROBABILIDADE)
   - `CommunicationHubController::send()` define header **ANTES** de `Auth::requireInternal()`
   - `Auth::requireInternal()` pode fazer `exit` sem limpar headers
   - Causa "headers already sent" silencioso

3. **⭐ Erro fatal silencioso no Router** (MÉDIA PROBABILIDADE)
   - Router pode estar falhando antes de executar handler
   - Erro de autoload/sintaxe
   - Erro no `executeHandler()`

**Ação Imediata Recomendada (Ordem de Prioridade):**

1. **PRIORIDADE 1:** Modificar frontend para enviar `Accept: application/json` e `X-Requested-With: XMLHttpRequest` (Correção 9.1)
2. **PRIORIDADE 2:** Mover definição de headers para APÓS `Auth::requireInternal()` (Correção 9.2)
3. **PRIORIDADE 3:** Verificar logs do Apache e habilitar `display_errors` (Testes 10.2 e 10.3)

---

## 14. TRÊS CORREÇÕES CANDIDATAS MAIS FORTES (SOMENTE PROPOSTAS)

### 14.1. Correção A: Adicionar Headers Accept e X-Requested-With no Frontend ⭐⭐⭐

**Arquivo:** `views/communication_hub/index.php` (linha 2000)  
**Impacto:** CRÍTICO — Garante que `Auth::requireInternal()` detecte requisição como JSON/AJAX  
**Esforço:** BAIXO — Mudança simples no fetch  
**Risco:** NENHUM — Não afeta funcionalidade existente  

**Código:**
```javascript
// Modificar fetch para incluir headers JSON/AJAX
const response = await fetch(sendUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json', // ← ADICIONAR
        'X-Requested-With': 'XMLHttpRequest' // ← ADICIONAR
    },
    body: new URLSearchParams(formData)
});
```

### 14.2. Correção B: Mover Headers Para Após Auth ⭐⭐

**Arquivo:** `src/Controllers/CommunicationHubController.php` (linhas 292-304)  
**Impacto:** ALTO — Alinha com padrão do Teste e evita "headers already sent"  
**Esforço:** BAIXO — Reordenar código  
**Risco:** BAIXO — Não muda lógica, apenas ordem  

**Código:**
```php
// Reordenar: Auth PRIMEIRO, headers DEPOIS
Auth::requireInternal(); // ← Move para primeiro
while (ob_get_level() > 0) {
    @ob_end_clean();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8'); // ← Move para depois
}
```

### 14.3. Correção C: Adicionar Try/Catch Global no Router ⭐

**Arquivo:** `src/Core/Router.php` (linha 103)  
**Impacto:** MÉDIO — Captura erros fatais antes de quebrar silenciosamente  
**Esforço:** BAIXO — Adicionar try/catch  
**Risco:** BAIXO — Apenas adiciona tratamento de erro  

**Código:**
```php
// No Router::dispatch(), antes de executeHandler()
try {
    $this->executeHandler($route['handler']);
} catch (\Throwable $e) {
    error_log("[Router] FATAL: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Erro interno'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
```

---

**Próximos Passos:** Executar testes da seção 10 na ordem especificada e coletar evidências adicionais. Se nenhuma das correções resolver, seguir com diagnóstico mais profundo baseado nos resultados dos testes.

