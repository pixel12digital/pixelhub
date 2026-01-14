# Auditoria Completa - Fluxo de Envio de Mensagens WhatsApp

**Data da Auditoria:** 2026-01-14  
**Objetivo:** Mapear e diagnosticar problemas no fluxo de ENVIO de mensagens WhatsApp  
**Escopo:** Apenas ENVIO. Recebimento (webhook/ingestão) está validado e NÃO deve ser alterado.

---

## 1. Resumo Executivo

### 1.1. Problema Principal
O envio de mensagens WhatsApp está falhando em múltiplos pontos do sistema, retornando erros 404 e mensagens de "Canal não encontrado" ou "Sessão não está ativa", mesmo quando o teste de conexão nas configurações retorna sucesso.

### 1.2. Impacto
- **Ambiente:** Local e possivelmente produção
- **Telas Afetadas:** 
  - Thread de conversa (`/communication-hub?thread_id=whatsapp_35`)
  - Página de testes (`/settings/whatsapp-gateway/test`)
- **Canais Afetados:** "Pixel12 Digital", possivelmente outros

### 1.3. Erros Observados
1. **404 Not Found** no endpoint `/communication-hub/send`
2. **404 Not Found** no endpoint `/settings/whatsapp-gateway/test/send`
3. **CHANNEL_NOT_FOUND** com `channel_id: 0`
4. **"A sessão do WhatsApp não está ativa"** (404 do gateway)

### 1.4. Causa Raiz Provável
**Hipótese #1 (Alta Probabilidade - 70%):** Divergência entre o `channel_id` armazenado no banco (formato VARCHAR) e o formato esperado pelo gateway (string/nome do canal). O código está fazendo cast para `(int)`, convertendo nomes como "Pixel12 Digital" para 0.

**Hipótese #2 (Média Probabilidade - 20%):** O `channel_id` não está sendo passado corretamente do frontend para o backend, resultando em `channel_id: 0` ou `null`.

**Hipótese #3 (Baixa Probabilidade - 10%):** Problema de roteamento onde o endpoint não está sendo encontrado corretamente devido a problemas com BASE_PATH.

---

## 2. Cenário Atual e Reprodução

### 2.1. Ambiente de Falha
- **Local:** `localhost/painel.pixel12digital`
- **Produção:** Não confirmado (necessita validação)

### 2.2. Telas/Fluxos Afetados

#### 2.2.1. Thread de Conversa
- **URL:** `/communication-hub?thread_id=whatsapp_35&channel=whatsapp`
- **Canal:** "Pixel12 Digital" (ou canal associado à conversa)
- **Destinatário:** Número normalizado (ex: `554796164699`)
- **Ação do Usuário:**
  1. Abre thread de conversa
  2. Digita mensagem no campo de texto
  3. Clica em "Enviar"
  4. **Resultado:** Erro 404 ou "Canal não encontrado no gateway"

#### 2.2.2. Página de Testes
- **URL:** `/settings/whatsapp-gateway/test`
- **Canal:** "Pixel12 Digital [connected]" (dropdown)
- **Destinatário:** Campo de telefone (ex: `554796164699`)
- **Ação do Usuário:**
  1. Seleciona canal no dropdown
  2. Preenche telefone e mensagem
  3. Clica em "Enviar Mensagem de Teste"
  4. **Resultado:** Erro 404 ou "A sessão do WhatsApp não está ativa"

### 2.3. Comportamento Intermitente
- **Não observado:** O erro ocorre consistentemente em todas as tentativas de envio.

---

## 3. Erros Apresentados (Lista Completa)

### 3.1. Erro #1: 404 Not Found - Endpoint `/communication-hub/send`

**Mensagem ao Usuário:**
```
Erro: Canal não encontrado no gateway. Verifique se o canal está configurado corretamente.
```

**Status Code:** 404 Not Found

**Response Body:**
```json
{
  "success": false,
  "error": "Canal não encontrado no gateway. Verifique se o canal está configurado corretamente.",
  "error_code": "CHANNEL_NOT_FOUND",
  "channel_id": 0
}
```

**Evidência:**
- Network tab do DevTools mostra `POST http://localhost/painel.pixel12digital/communication-hub/send 404 (Not Found)`
- Response JSON mostra `channel_id: 0`, indicando que o channel_id não foi encontrado ou está incorreto

**Stacktrace/Logs:**
```
[CommunicationHub::send] Recebido: channel=whatsapp, threadId=whatsapp_35, tenantId=null, channelId=null, to=554796164699
[CommunicationHub::send] Channel_id encontrado (canal compartilhado): 0
```

### 3.2. Erro #2: 404 Not Found - Endpoint `/settings/whatsapp-gateway/test/send`

**Mensagem ao Usuário:**
```
Erro: A sessão do WhatsApp não está ativa.
```

**Status Code:** 404 Not Found

**Response Body:**
```json
{
  "success": false,
  "status": 404,
  "raw": {
    "success": false,
    "error": "A sessão do WhatsApp não está ativa.",
    "correlationId": "0d8e7ecc-2508-479e-a243-e6694621a7ee"
  },
  "error": "A sessão do WhatsApp não está ativa."
}
```

**Evidência:**
- Network tab mostra `POST http://localhost/painel.pixel12digital/settings/whatsapp-gateway/test/send 404 (Not Found)`
- O gateway está retornando 404 com mensagem de sessão inativa

**Stacktrace/Logs:**
```
[WhatsAppGatewayTest::sendTest] Verificando status do canal: 1
[WhatsAppGatewayTest::sendTest] Status check - success: NÃO, HTTP: 404, error: A sessão do WhatsApp não está ativa.
```

### 3.3. Erro #3: CHANNEL_NOT_FOUND com channel_id: 0

**Mensagem ao Usuário:**
```
Erro: Canal não encontrado no gateway. Verifique se o canal está configurado corretamente.
```

**Status Code:** 400 (Bad Request) ou 404 (Not Found)

**Response Body:**
```json
{
  "success": false,
  "error": "Canal não encontrado no gateway. Verifique se o canal está configurado corretamente.",
  "error_code": "CHANNEL_NOT_FOUND",
  "channel_id": 0
}
```

**Análise:**
- O `channel_id: 0` indica que:
  1. O channel_id não foi encontrado no banco, OU
  2. O channel_id foi convertido incorretamente (cast para int de uma string), OU
  3. O channel_id não foi passado do frontend

---

## 4. Fluxo Técnico Real (Mapa de Ponta a Ponta)

### 4.1. Endpoint do Pixel Hub

#### 4.1.1. Thread de Conversa
- **Endpoint:** `POST /communication-hub/send`
- **Rota:** `public/index.php` linha 510: `$router->post('/communication-hub/send', 'CommunicationHubController@send');`
- **Controller:** `src/Controllers/CommunicationHubController.php`
- **Método:** `send()` (linha 247)

#### 4.1.2. Página de Testes
- **Endpoint:** `POST /settings/whatsapp-gateway/test/send`
- **Rota:** `public/index.php` linha 519: `$router->post('/settings/whatsapp-gateway/test/send', 'WhatsAppGatewayTestController@sendTest');`
- **Controller:** `src/Controllers/WhatsAppGatewayTestController.php`
- **Método:** `sendTest()` (linha 253)

### 4.2. Fluxo Completo - Thread de Conversa

```
1. Frontend (views/communication_hub/thread.php)
   └─> sendMessage() (linha 511)
       └─> POST para: pixelhub_url('/communication-hub/send')
       └─> Body: FormData com channel, thread_id, to, message, tenant_id, channel_id

2. Router (public/index.php)
   └─> Rota: POST /communication-hub/send
   └─> Handler: CommunicationHubController@send

3. Controller (src/Controllers/CommunicationHubController.php)
   └─> send() (linha 247)
       ├─> Recebe: channel, threadId, to, message, tenantId, channelId
       ├─> Determina channel_id (PRIORIDADE):
       │   1. Usa channel_id fornecido diretamente (POST['channel_id'])
       │   2. Busca da conversa/thread (eventos)
       │   3. Busca do tenant (tenant_message_channels)
       │   4. Fallback: qualquer canal habilitado
       ├─> Normaliza telefone: WhatsAppBillingService::normalizePhone()
       ├─> Carrega configurações:
       │   - baseUrl: Env::get('WPP_GATEWAY_BASE_URL')
       │   - secret: GatewaySecret::getDecrypted()
       ├─> Cria cliente: new WhatsAppGatewayClient($baseUrl, $secret)
       ├─> Verifica status: $gateway->getChannel((string) $channelId)
       │   └─> Endpoint: GET {baseUrl}/api/channels/{channelId}
       ├─> Envia mensagem: $gateway->sendText($channelId, $phoneNormalized, $message)
       │   └─> Endpoint: POST {baseUrl}/api/messages
       │       └─> Body: { channel: $channelId, to: $phoneNormalized, text: $message }
       └─> Retorna JSON: { success, event_id, error }

4. Gateway Client (src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php)
   └─> request() (linha 161)
       ├─> URL: {baseUrl}{endpoint}
       ├─> Headers:
       │   - X-Gateway-Secret: {secret}
       │   - Content-Type: application/json
       │   - Accept: application/json
       └─> cURL request

5. WhatsApp Gateway (externo)
   └─> Processa requisição
   └─> Retorna resposta JSON
```

### 4.3. Fluxo Completo - Página de Testes

```
1. Frontend (views/settings/whatsapp_gateway_test.php)
   └─> Form submit (linha 26)
       └─> POST para: /settings/whatsapp-gateway/test/send
       └─> Body: FormData com channel_id, phone, message, tenant_id

2. Router (public/index.php)
   └─> Rota: POST /settings/whatsapp-gateway/test/send
   └─> Handler: WhatsAppGatewayTestController@sendTest

3. Controller (src/Controllers/WhatsAppGatewayTestController.php)
   └─> sendTest() (linha 253)
       ├─> Recebe: channel_id, phone, message, tenant_id
       ├─> Normaliza telefone: WhatsAppBillingService::normalizePhone()
       ├─> Carrega configurações:
       │   - baseUrl: getGatewayConfig()['baseUrl']
       │   - secret: getGatewayConfig()['secret']
       ├─> Cria cliente: new WhatsAppGatewayClient($baseUrl, $secret)
       ├─> Verifica status: $gateway->getChannel($channelId)
       │   └─> Endpoint: GET {baseUrl}/api/channels/{channelId}
       ├─> Envia mensagem: $gateway->sendText($channelId, $phoneNormalized, $message)
       │   └─> Endpoint: POST {baseUrl}/api/messages
       └─> Retorna JSON: { success, status, error }

4. Gateway Client (mesmo do fluxo anterior)
```

### 4.4. Resolução do Canal (channel_id)

#### 4.4.1. Formato no Banco de Dados
- **Tabela:** `tenant_message_channels`
- **Campo:** `channel_id VARCHAR(100)`
- **Formato:** String (ex: "Pixel12 Digital", "ImobSites", "channel_123")
- **Migration:** `database/migrations/20250201_create_tenant_message_channels_table.php`

#### 4.4.2. Como é Determinado

**No CommunicationHubController::send():**
```php
// PRIORIDADE 1: Usa channel_id fornecido diretamente (vem da thread)
if ($channelId) {
    $channelId = (int) $_POST['channel_id']; // ⚠️ PROBLEMA: Cast para int
}

// PRIORIDADE 2: Busca da conversa/thread
if (!$channelId && $threadId) {
    // Busca channel_id dos eventos da conversa
    $channelId = (int) $payload['channel_id']; // ⚠️ PROBLEMA: Cast para int
}

// PRIORIDADE 3: Busca do tenant
if (!$channelId && $tenantId) {
    $channelData = $db->query("SELECT channel_id FROM tenant_message_channels ...");
    $channelId = (int) $channelData['channel_id']; // ⚠️ PROBLEMA: Cast para int
}

// PRIORIDADE 4: Fallback (qualquer canal habilitado)
if (!$channelId) {
    $channelData = $db->query("SELECT channel_id FROM tenant_message_channels ...");
    $channelId = (int) $channelData['channel_id']; // ⚠️ PROBLEMA: Cast para int
}
```

**⚠️ PROBLEMA CRÍTICO:** O código está fazendo `(int) $channelId`, convertendo strings como "Pixel12 Digital" para `0`.

**No WhatsAppGatewayTestController::sendTest():**
```php
$channelId = trim($_POST['channel_id'] ?? ''); // ✅ Mantém como string
// ...
$gateway->getChannel($channelId); // ✅ Passa string
$gateway->sendText($channelId, ...); // ✅ Passa string
```

### 4.5. Normalização do Destinatário

**Classe:** `src/Services/WhatsAppBillingService.php`
**Método:** `normalizePhone()`

**Formato Esperado:** `5511999999999` (DDI + DDD + número, sem caracteres especiais)
**Formato Suportado:** Aceita vários formatos e normaliza para o padrão acima.

### 4.6. Endpoints do Gateway

#### 4.6.1. Verificação de Status
- **Método:** `GET`
- **Endpoint:** `{baseUrl}/api/channels/{channelId}`
- **Headers:**
  - `X-Gateway-Secret: {secret}`
  - `Content-Type: application/json`
- **Resposta Esperada:**
  ```json
  {
    "id": "Pixel12 Digital",
    "name": "Pixel12 Digital",
    "status": "connected",
    "connected": true
  }
  ```

#### 4.6.2. Envio de Mensagem
- **Método:** `POST`
- **Endpoint:** `{baseUrl}/api/messages`
- **Headers:**
  - `X-Gateway-Secret: {secret}`
  - `Content-Type: application/json`
- **Body:**
  ```json
  {
    "channel": "Pixel12 Digital",
    "to": "5511999999999",
    "text": "Mensagem de teste"
  }
  ```

---

## 5. Configurações Efetivas (Fonte Única)

### 5.1. WPP_GATEWAY_BASE_URL

**Fonte:** `.env` → `Env::get('WPP_GATEWAY_BASE_URL')`

**Valor Padrão:** `https://wpp.pixel12digital.com.br`

**Validação:**
- Deve começar com `http://` ou `https://`
- Se inválido, usa valor padrão

**Uso:**
- `CommunicationHubController::send()`: linha 391
- `WhatsAppGatewayTestController::sendTest()`: via `getGatewayConfig()`
- `WhatsAppGatewayClient::__construct()`: linha 22

### 5.2. WPP_GATEWAY_SECRET

**Fonte Única:** `src/Services/GatewaySecret::getDecrypted()`

**Fluxo:**
1. Lê de `.env`: `Env::get('WPP_GATEWAY_SECRET')`
2. Descriptografa: `CryptoHelper::decrypt($secretRaw)`
3. Se falhar, usa valor raw (pode não estar criptografado)

**Uso no Request:**
- **Header:** `X-Gateway-Secret: {secret}`
- **Classe:** `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`
- **Método:** `request()` linha 174

**Logs Temporários:**
- Preview: `{primeiros 4 chars}...{últimos 4 chars} (len={tamanho})`
- Não expõe secret completo

### 5.3. Channel ID

**Formato no Banco:** `VARCHAR(100)` (string)
**Exemplos:** "Pixel12 Digital", "ImobSites", "channel_123"

**Como Vai no Request ao Gateway:**
- **Campo:** `channel` (no body JSON)
- **Valor:** String do channel_id (ex: "Pixel12 Digital")
- **Endpoint:** `POST /api/messages`

**⚠️ PROBLEMA:** O código está convertendo para `(int)`, transformando strings em `0`.

---

## 6. Evidências (Provas do que está Acontecendo)

### 6.1. Exemplo #1: Tentativa de Envio pela Thread

**Timestamp:** 2026-01-14 10:43:00

**Request Recebido no Hub:**
```
POST /communication-hub/send
Content-Type: application/x-www-form-urlencoded

channel=whatsapp
thread_id=whatsapp_35
to=554796164699
message=teste de envio 10:43
tenant_id=
channel_id=
```

**Logs do Backend:**
```
[CommunicationHub::send] Recebido: channel=whatsapp, threadId=whatsapp_35, tenantId=null, channelId=null, to=554796164699
[CommunicationHub::send] gateway_base_url: https://wpp.pixel12digital.com.br
[CommunicationHub::send] canal selecionado: id=0, nome=N/A
[CommunicationHub::send] Channel_id encontrado (canal compartilhado): 0
```

**Request Montado para o Gateway:**
```
GET https://wpp.pixel12digital.com.br/api/channels/0
Headers:
  X-Gateway-Secret: {secret_preview}
  Content-Type: application/json
```

**Response do Gateway:**
```
HTTP 404 Not Found
{
  "success": false,
  "error": "Canal não encontrado"
}
```

**Resposta Final ao Front:**
```json
{
  "success": false,
  "error": "Canal não encontrado no gateway. Verifique se o canal está configurado corretamente.",
  "error_code": "CHANNEL_NOT_FOUND",
  "channel_id": 0
}
```

### 6.2. Exemplo #2: Tentativa de Envio pela Página de Testes

**Timestamp:** 2026-01-14 10:37:00

**Request Recebido no Hub:**
```
POST /settings/whatsapp-gateway/test/send
Content-Type: multipart/form-data

channel_id=1
phone=554796164699
message=teste 10:37
tenant_id=2
```

**Logs do Backend:**
```
[WhatsAppGatewayTest::sendTest] Verificando status do canal: 1
[WhatsAppGatewayTest::sendTest] Status check - success: NÃO, HTTP: 404, error: A sessão do WhatsApp não está ativa.
```

**Request Montado para o Gateway:**
```
GET https://wpp.pixel12digital.com.br/api/channels/1
Headers:
  X-Gateway-Secret: {secret_preview}
  Content-Type: application/json
```

**Response do Gateway:**
```
HTTP 404 Not Found
{
  "success": false,
  "error": "A sessão do WhatsApp não está ativa."
}
```

**Resposta Final ao Front:**
```json
{
  "success": false,
  "status": 404,
  "error": "A sessão do WhatsApp não está ativa.",
  "raw": {
    "success": false,
    "error": "A sessão do WhatsApp não está ativa."
  }
}
```

### 6.3. Exemplo #3: Tentativa com channel_id Correto (Hipótese)

**Timestamp:** 2026-01-14 10:45:00 (simulado)

**Request Recebido no Hub:**
```
POST /communication-hub/send
channel_id=Pixel12 Digital
```

**Logs do Backend (esperado):**
```
[CommunicationHub::send] Recebido: channelId=Pixel12 Digital
[CommunicationHub::send] Channel_id encontrado: Pixel12 Digital
```

**Request Montado para o Gateway:**
```
GET https://wpp.pixel12digital.com.br/api/channels/Pixel12%20Digital
```

**Response do Gateway (esperado):**
```
HTTP 200 OK
{
  "id": "Pixel12 Digital",
  "name": "Pixel12 Digital",
  "status": "connected",
  "connected": true
}
```

### 6.4. Bloco de Logs Organizados (Copiar/Colar)

```
=== LOG DIAGNÓSTICO ENVIO - 2026-01-14 10:43:00 ===
[CommunicationHub::send] Recebido: channel=whatsapp, threadId=whatsapp_35, tenantId=null, channelId=null, to=554796164699
[CommunicationHub::send] gateway_base_url: https://wpp.pixel12digital.com.br
[CommunicationHub::send] secret configurado: SIM - Preview: xxxx...yyyy (len=32)
[CommunicationHub::send] endpoint verificar status: https://wpp.pixel12digital.com.br/api/channels/0
[WhatsAppGateway::request] URL: https://wpp.pixel12digital.com.br/api/channels/0
[WhatsAppGateway::request] Header X-Gateway-Secret configurado: SIM - Preview: xxxx...yyyy (len=32)
[WhatsAppGateway::request] Response HTTP Status: 404
[WhatsAppGateway::request] Response body: {"success":false,"error":"Canal não encontrado"}
[CommunicationHub::send] check status - HTTP: 404, success: NÃO
[CommunicationHub::send] check status - body (resumido): {"success":false,"error":"Canal não encontrado"}
[CommunicationHub::send] ERRO: Canal não encontrado no gateway. Verifique se o canal está configurado corretamente.
=== FIM LOG DIAGNÓSTICO ===
```

---

## 7. Hipóteses de Causa Raiz (com Probabilidade)

### 7.1. Hipótese #1: Cast Incorreto de channel_id para int (70% de probabilidade)

**Por que é plausível:**
- O código faz `(int) $channelId` em múltiplos pontos
- O `channel_id` no banco é `VARCHAR(100)` (string)
- Strings como "Pixel12 Digital" convertidas para `(int)` resultam em `0`
- O erro mostra `channel_id: 0` consistentemente

**Evidência:**
- Logs mostram `Channel_id encontrado (canal compartilhado): 0`
- Response mostra `"channel_id": 0`
- O gateway retorna 404 para `/api/channels/0`

**Como confirmar:**
1. Verificar logs do backend quando `channel_id` é determinado
2. Verificar se o valor no banco é string (ex: "Pixel12 Digital")
3. Adicionar log antes do cast: `error_log("channel_id antes do cast: " . var_export($channelId, true));`

**Arquivos Afetados:**
- `src/Controllers/CommunicationHubController.php` (linhas 277, 331, 352, 372)

### 7.2. Hipótese #2: channel_id Não Passado do Frontend (20% de probabilidade)

**Por que é plausível:**
- O `channel_id` pode não estar sendo incluído no FormData
- O campo hidden pode não estar sendo renderizado corretamente
- O `thread['channel_id']` pode estar vazio/null

**Evidência:**
- Request mostra `channel_id=` (vazio)
- Logs mostram `channelId=null`

**Como confirmar:**
1. Verificar HTML renderizado: `<input type="hidden" name="channel_id" value="...">`
2. Verificar FormData no console: `console.log(Array.from(formData.entries()));`
3. Verificar se `$thread['channel_id']` está sendo passado para a view

**Arquivos Afetados:**
- `views/communication_hub/thread.php` (linha 92)
- `src/Controllers/CommunicationHubController.php` (método `thread()`)

### 7.3. Hipótese #3: Problema de Roteamento/BASE_PATH (10% de probabilidade)

**Por que é plausível:**
- O erro 404 pode ser do router, não do gateway
- Problemas com BASE_PATH podem causar rotas não encontradas

**Evidência:**
- Erro 404 no endpoint do Pixel Hub (não do gateway)
- URL pode estar incorreta

**Como confirmar:**
1. Verificar se a rota está registrada: `grep -r "communication-hub/send" public/index.php`
2. Verificar logs do router: `Router::dispatch: 404 - Rota não encontrada`
3. Testar URL diretamente: `curl -X POST http://localhost/painel.pixel12digital/communication-hub/send`

**Arquivos Afetados:**
- `public/index.php` (roteamento)
- `src/Core/Router.php` (dispatch)

---

## 8. Propostas de Solução (Mínimas e Seguras)

### 8.1. Solução #1: Remover Cast para int do channel_id (CRÍTICA)

**O que muda:**
- **Arquivo:** `src/Controllers/CommunicationHubController.php`
- **Método:** `send()` (linhas 277, 331, 352, 372)
- **Alteração:** Remover `(int)` e manter como string

**Código Antes:**
```php
$channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? (int) $_POST['channel_id'] : null;
// ...
$channelId = (int) $payload['channel_id'];
// ...
$channelId = (int) $channelData['channel_id'];
```

**Código Depois:**
```php
$channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? trim($_POST['channel_id']) : null;
// ...
$channelId = trim($payload['channel_id'] ?? '');
// ...
$channelId = trim($channelData['channel_id'] ?? '');
```

**Por que não impacta recebimento:**
- O recebimento usa webhook e não depende do `channel_id` do envio
- Apenas o fluxo de envio é afetado

**Como validar:**
1. Enviar mensagem pela thread
2. Verificar logs: `channel_id` deve ser string (ex: "Pixel12 Digital")
3. Verificar request ao gateway: `GET /api/channels/Pixel12%20Digital`
4. Confirmar que mensagem é enviada com sucesso

### 8.2. Solução #2: Garantir channel_id no Frontend (PREVENTIVA)

**O que muda:**
- **Arquivo:** `src/Controllers/CommunicationHubController.php`
- **Método:** `thread()` (linha ~1088)
- **Alteração:** Garantir que `$thread['channel_id']` sempre tenha valor

**Código:**
```php
// No método thread(), após buscar channel_id:
if (empty($thread['channel_id'])) {
    // Busca canal do tenant ou fallback
    $channelStmt = $db->prepare("
        SELECT channel_id 
        FROM tenant_message_channels 
        WHERE tenant_id = ? AND provider = 'wpp_gateway' AND is_enabled = 1
        LIMIT 1
    ");
    $channelStmt->execute([$thread['tenant_id'] ?? 0]);
    $channelData = $channelStmt->fetch();
    if ($channelData) {
        $thread['channel_id'] = $channelData['channel_id'];
    }
}
```

**Por que não impacta recebimento:**
- Apenas adiciona informação à view, não altera lógica de recebimento

**Como validar:**
1. Verificar HTML renderizado: `<input type="hidden" name="channel_id" value="Pixel12 Digital">`
2. Verificar FormData no console
3. Confirmar que `channel_id` está presente no request

### 8.3. Solução #3: Melhorar Logs de Diagnóstico (TEMPORÁRIA)

**O que muda:**
- **Arquivo:** `src/Controllers/CommunicationHubController.php`
- **Método:** `send()`
- **Alteração:** Adicionar logs antes e depois de determinar `channel_id`

**Código:**
```php
// LOG TEMPORÁRIO: Antes de determinar channel_id
error_log("[CommunicationHub::send] channel_id do POST: " . var_export($_POST['channel_id'] ?? null, true));
error_log("[CommunicationHub::send] channel_id após cast: " . var_export($channelId, true));
error_log("[CommunicationHub::send] channel_id do banco (raw): " . var_export($channelData['channel_id'] ?? null, true));
```

**⚠️ IMPORTANTE:** Remover após validação completa.

---

## 9. Checklist de Validação (Produção)

### 9.1. Pré-Deploy
- [ ] Remover todos os logs temporários marcados com `LOG TEMPORÁRIO`
- [ ] Testar envio pela thread de conversa
- [ ] Testar envio pela página de testes
- [ ] Verificar logs do backend (channel_id deve ser string)
- [ ] Verificar request ao gateway (channel deve ser string)
- [ ] Confirmar que mensagem é enviada com sucesso

### 9.2. Pós-Deploy
- [ ] Monitorar logs de erro por 24h
- [ ] Validar que recebimento continua funcionando (webhook)
- [ ] Testar envio em produção com canal real
- [ ] Verificar se não há regressões em outras funcionalidades

### 9.3. Validação de Recebimento (NÃO ALTERAR)
- [ ] Confirmar que webhook continua recebendo mensagens
- [ ] Confirmar que ingestão de eventos continua funcionando
- [ ] Confirmar que threads são atualizadas corretamente

---

## 10. Observações Importantes

### 10.1. Logs Temporários
Todos os logs marcados com `LOG TEMPORÁRIO` devem ser removidos após validação completa. Localizações:
- `src/Controllers/CommunicationHubController.php` (linhas 389-406, 423-438, 486-503)
- `src/Controllers/WhatsAppGatewayTestController.php` (linhas 281-304, 289-313)
- `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` (linhas 165-214)

### 10.2. Não Alterar Recebimento
- **Webhook:** `src/Controllers/WhatsAppWebhookController.php`
- **Ingestão:** `src/Services/EventIngestionService.php`
- **Threads:** Lógica de atualização de threads

### 10.3. Formato do channel_id
- **Banco:** `VARCHAR(100)` (string)
- **Gateway:** Espera string (ex: "Pixel12 Digital")
- **Código:** Deve manter como string, nunca fazer cast para `int`

### 10.4. Prioridades de Resolução do channel_id
1. **POST['channel_id']** (fornecido diretamente)
2. **Eventos da conversa** (payload dos eventos)
3. **Tenant** (tenant_message_channels por tenant_id)
4. **Fallback** (qualquer canal habilitado)

---

## 11. Arquivos de Referência

### 11.1. Controllers
- `src/Controllers/CommunicationHubController.php` - Envio pela thread
- `src/Controllers/WhatsAppGatewayTestController.php` - Envio pela página de testes
- `src/Controllers/WhatsAppGatewaySettingsController.php` - Configurações

### 11.2. Services
- `src/Services/GatewaySecret.php` - Fonte única do secret
- `src/Services/WhatsAppBillingService.php` - Normalização de telefone

### 11.3. Integrations
- `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` - Cliente HTTP do gateway

### 11.4. Views
- `views/communication_hub/thread.php` - Thread de conversa
- `views/settings/whatsapp_gateway_test.php` - Página de testes

### 11.5. Routing
- `public/index.php` - Rotas do sistema

---

**Fim do Documento de Auditoria**

