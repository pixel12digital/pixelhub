# Auditoria: Mídia WhatsApp não exibida na Thread
## Número: 5511965221349 (JP TRASLADOS TRANSPORTE EXECUTIVO!)

**Data:** 16/01/2026  
**Status:** ❌ Problema persistente após correções  
**Prioridade:** Alta

---

## 📋 Resumo Executivo

Foi identificado um evento de WhatsApp contendo áudio (PTT) codificado em base64 no campo `text` do payload. O áudio foi processado e salvo corretamente no banco de dados e sistema de arquivos, porém **não está sendo exibido na thread de comunicação** mesmo após implementação de correções.

---

## 🔍 Análise do Problema

### 1. Evento Identificado

**Event ID:** `fe23f980-c24b-4f8a-b378-99b4a1c2a2cc`  
**Data:** 2026-01-16 05:35:23  
**Tipo:** `whatsapp.inbound.message`  
**Número:** 5511965221349  
**Conversa ID:** 4

### 2. Estrutura do Payload

O payload contém:
- Campo `text` com **87.968 caracteres** de dados base64
- Dados decodificados: **65.976 bytes** de áudio OGG
- Header OGG detectado: `OggS` (confirmação de formato válido)

**Formato do payload:**
```json
{
  "spec_version": "1.0",
  "event": "message",
  "message": {
    "id": "...",
    "from": "5511965221349@c.us",
    "text": "T2dnUwACAAAAAAAAAAAA..." // 87.968 chars de base64
  }
}
```

### 3. Processamento Realizado

✅ **Mídia processada com sucesso:**
- Arquivo salvo: `storage/whatsapp-media/2026/01/16/f6528d90b33fe0db1a41f275ab9c8346.ogg`
- Tamanho: 64.43 KB (65.976 bytes)
- Registro criado na tabela `communication_media` (ID: 1)
- Tipo: `audio`
- MIME: `audio/ogg`

---

## 🔧 Correções Implementadas

### 1. Detecção de Áudio Base64 no WhatsAppMediaService

**Arquivo:** `src/Services/WhatsAppMediaService.php`

**Alteração:**
- Adicionada detecção de áudio codificado em base64 no campo `text`
- Método `processBase64Audio()` criado para processar esse formato
- Verifica header `OggS` para confirmar formato OGG válido

**Código adicionado:**
```php
// NOVA DETECÇÃO: Verifica se há áudio codificado em base64 no campo "text"
$text = $payload['text'] ?? $payload['message']['text'] ?? null;
if ($text && strlen($text) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text)) {
    $textCleaned = preg_replace('/\s+/', '', $text);
    $decoded = base64_decode($textCleaned, true);
    if ($decoded !== false && substr($decoded, 0, 4) === 'OggS') {
        $base64AudioData = $decoded;
        return self::processBase64Audio($event, $base64AudioData);
    }
}
```

### 2. Limpeza de Conteúdo Base64 no Controller

**Arquivo:** `src/Controllers/CommunicationHubController.php`

**Alteração:**
- Busca mídia processada mesmo quando há conteúdo no campo `text`
- Limpa conteúdo base64 quando mídia é detectada
- Previne exibição de dados brutos na interface

**Código adicionado:**
```php
// Busca informações da mídia processada (sempre verifica, mesmo se há conteúdo)
$mediaInfo = \PixelHub\Services\WhatsAppMediaService::getMediaByEventId($event['event_id']);

// Se encontrou mídia, limpa conteúdo se for base64
if ($mediaInfo && !empty($content)) {
    if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
        $textCleaned = preg_replace('/\s+/', '', $content);
        $decoded = base64_decode($textCleaned, true);
        if ($decoded !== false) {
            if (substr($decoded, 0, 4) === 'OggS' || strlen($decoded) > 1000) {
                $content = ''; // Limpa conteúdo base64
            }
        }
    }
}
```

### 3. Correção de URL da Mídia

**Arquivo:** `src/Services/WhatsAppMediaService.php`

**Alteração:**
- URL da mídia agora usa caminho relativo em vez de `localhost`
- Melhora compatibilidade com diferentes ambientes

**Antes:**
```php
return "http://localhost/communication-hub/media?path=...";
```

**Depois:**
```php
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
return $basePath . '/communication-hub/media?path=' . urlencode($storedPath);
```

### 4. Correção de Warning PHP

**Arquivo:** `src/Services/WhatsAppMediaService.php`

**Alteração:**
- Corrigido warning sobre `SERVER_PORT` não definido
- Adicionada verificação `isset()` antes de usar

---

## ✅ Verificações Realizadas

### 1. Banco de Dados

**Tabela `communication_media`:**
```sql
SELECT * FROM communication_media WHERE event_id = 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc';
```

**Resultado:**
- ✅ Registro existe (ID: 1)
- ✅ `stored_path`: `whatsapp-media/2026/01/16/f6528d90b33fe0db1a41f275ab9c8346.ogg`
- ✅ `media_type`: `audio`
- ✅ `mime_type`: `audio/ogg`
- ✅ `file_size`: 65976 bytes

### 2. Sistema de Arquivos

**Caminho:** `storage/whatsapp-media/2026/01/16/f6528d90b33fe0db1a41f275ab9c8346.ogg`

**Resultado:**
- ✅ Arquivo existe fisicamente
- ✅ Tamanho correto: 65.976 bytes
- ✅ Formato válido (header OGG confirmado)

### 3. Estrutura da Mensagem Retornada

**Teste realizado:** `database/testar-thread-completo.php`

**Resultado:**
```json
{
  "id": "fe23f980-c24b-4f8a-b378-99b4a1c2a2cc",
  "direction": "inbound",
  "content": "",  // ✅ Vazio (base64 removido)
  "timestamp": "2026-01-16 05:35:23",
  "media": {      // ✅ Mídia presente
    "id": 1,
    "event_id": "fe23f980-c24b-4f8a-b378-99b4a1c2a2cc",
    "media_type": "audio",
    "mime_type": "audio/ogg",
    "stored_path": "whatsapp-media/2026/01/16/f6528d90b33fe0db1a41f275ab9c8346.ogg",
    "file_name": "f6528d90b33fe0db1a41f275ab9c8346.ogg",
    "file_size": 65976,
    "url": "/communication-hub/media?path=whatsapp-media%2F2026%2F01%2F16%2Ff6528d90b33fe0db1a41f275ab9c8346.ogg"
  }
}
```

**Status:** ✅ Estrutura correta

### 4. View PHP (Renderização Inicial)

**Arquivo:** `views/communication_hub/thread.php` (linhas 78-104)

**Código:**
```php
<?php if (!empty($msg['media']) && !empty($msg['media']['url'])): ?>
    <?php
    $media = $msg['media'];
    $mediaType = strtolower($media['media_type'] ?? 'unknown');
    $mimeType = strtolower($media['mime_type'] ?? '');
    ?>
    <?php if (strpos($mimeType, 'audio/') === 0 || in_array($mediaType, ['audio', 'voice'])): ?>
        <div style="margin-bottom: 8px; padding: 12px; background: rgba(0,0,0,0.05); border-radius: 8px;">
            <audio controls style="width: 100%;">
                <source src="<?= htmlspecialchars($media['url']) ?>" type="<?= htmlspecialchars($media['mime_type']) ?>">
                Seu navegador não suporta o elemento de áudio.
            </audio>
        </div>
    <?php endif; ?>
<?php endif; ?>
```

**Status:** ✅ Código correto

### 5. JavaScript (Mensagens Dinâmicas)

**Arquivo:** `views/communication_hub/thread.php` (linhas 253-286)

**Código:**
```javascript
let mediaHtml = '';
if (message.media && message.media.url) {
    const media = message.media;
    const mediaType = (media.media_type || '').toLowerCase();
    const mimeType = (media.mime_type || '').toLowerCase();
    
    if (mimeType.startsWith('audio/') || mediaType === 'audio' || mediaType === 'voice') {
        mediaHtml = `<div style="margin-bottom: 8px; padding: 12px; background: rgba(0,0,0,0.05); border-radius: 8px;">
            <audio controls style="width: 100%;">
                <source src="${escapeHtml(media.url)}" type="${escapeHtml(media.mime_type || 'audio/ogg')}">
                Seu navegador não suporta o elemento de áudio.
            </audio>
        </div>`;
    }
}
```

**Status:** ✅ Código correto

---

## ❌ Problema Persistente

### Sintomas

1. ✅ Mídia processada e salva corretamente
2. ✅ Registro criado no banco de dados
3. ✅ Mensagem retornada com estrutura correta
4. ✅ View e JavaScript configurados corretamente
5. ❌ **Áudio não aparece na interface da thread**

### ⚠️ CAUSA IDENTIFICADA: Endpoint Requer Autenticação

**Problema Crítico:**
- O método `serveMedia()` requer `Auth::requireInternal()`
- Isso significa que o endpoint `/communication-hub/media` **só funciona com sessão ativa**
- Quando o navegador tenta carregar o áudio, pode estar recebendo **401/403** em vez do arquivo
- O elemento `<audio>` existe no DOM, mas o `src` falha ao carregar

**Evidência:**
```php
public function serveMedia(): void
{
    Auth::requireInternal(); // ⚠️ REQUER AUTENTICAÇÃO
    // ...
}
```

### Possíveis Causas

#### 1. Endpoint de Mídia não Acessível ⚠️ **PROVÁVEL CAUSA**

**Problema Identificado:**
- Endpoint requer `Auth::requireInternal()`
- Pode retornar **401/403** se sessão não estiver ativa
- Navegador pode estar fazendo requisição sem cookies de sessão

**Verificar:**
- URL: `/communication-hub/media?path=whatsapp-media%2F2026%2F01%2F16%2Ff6528d90b33fe0db1a41f275ab9c8346.ogg`
- Status HTTP: Deve retornar **200 OK** (não 401/403)
- Content-Type: Deve ser `audio/ogg`
- Headers: Verificar se cookies de sessão estão sendo enviados

**Teste manual (com sessão ativa):**
1. Abrir DevTools → Network
2. Acessar thread no navegador (com sessão ativa)
3. Verificar requisição para `/communication-hub/media`
4. Verificar status e headers da resposta

**Teste via curl (com cookies):**
```bash
# Primeiro fazer login e salvar cookies
curl -c cookies.txt -X POST "http://[DOMINIO]/login" -d "email=..." -d "password=..."

# Depois acessar mídia com cookies
curl -b cookies.txt -I "http://[DOMINIO]/communication-hub/media?path=whatsapp-media%2F2026%2F01%2F16%2Ff6528d90b33fe0db1a41f275ab9c8346.ogg"
```

#### 2. BASE_PATH não Definido Corretamente

**Verificar:**
- Constante `BASE_PATH` definida quando a URL é gerada
- Função `pixelhub_url()` disponível no contexto
- URL relativa vs absoluta

**Possível problema:**
- URL gerada como `/communication-hub/media?...` (relativa)
- Mas `BASE_PATH` pode ser `/painel.pixel12digital`
- Resultado: URL incorreta

#### 3. Mensagem não Incluída no Carregamento Inicial

**Verificar:**
- Se a mensagem está sendo retornada no método `thread()`
- Se está sendo passada para a view corretamente
- Se o JavaScript está processando mensagens iniciais

**Possível problema:**
- Mensagem carregada via polling, mas não no carregamento inicial
- JavaScript só processa novas mensagens, não as existentes

#### 4. Cache do Navegador

**Verificar:**
- Cache de JavaScript/CSS
- Cache de requisições AJAX
- Service Workers

#### 5. Condição de Renderização

**Verificar:**
- Se `!empty($msg['media'])` está retornando `true`
- Se `!empty($msg['media']['url'])` está retornando `true`
- Se a condição `strpos($mimeType, 'audio/') === 0` está sendo satisfeita

**Possível problema:**
- `$msg['media']` pode estar `null` ou estrutura diferente
- `$msg['media']['url']` pode estar vazio ou `null`

---

## 🔬 Próximos Passos de Investigação

### 1. Debug no Navegador

**Ações:**
1. Abrir DevTools (F12)
2. Aba Console: Verificar erros JavaScript
3. Aba Network: Verificar requisições para `/communication-hub/media`
4. Aba Elements: Inspecionar HTML da mensagem

**Verificar:**
- Se o elemento `<audio>` está sendo criado
- Se a URL está correta no atributo `src`
- Se há erros de CORS ou 404

### 2. Debug no Backend

**Adicionar logs temporários:**

**Em `CommunicationHubController::getWhatsAppMessagesFromConversation()`:**
```php
error_log("[DEBUG] Mensagem com mídia: " . json_encode($message, JSON_PRETTY_PRINT));
```

**Em `views/communication_hub/thread.php`:**
```php
<?php if (!empty($msg['media'])): ?>
    <!-- DEBUG -->
    <div style="background: yellow; padding: 5px; font-size: 10px;">
        DEBUG: media presente = <?= var_export($msg['media'], true) ?>
    </div>
<?php endif; ?>
```

### 3. Teste Direto do Endpoint

**Criar script de teste:**
```php
// database/testar-endpoint-media.php
$url = '/communication-hub/media?path=whatsapp-media%2F2026%2F01%2F16%2Ff6528d90b33fe0db1a41f275ab9c8346.ogg';
// Testar acesso direto
```

### 4. Verificar Rotas

**Verificar se a rota está registrada:**
- Rota: `/communication-hub/media`
- Método: `GET`
- Controller: `CommunicationHubController::serveMedia()`

---

## 📊 Checklist de Diagnóstico

- [ ] Endpoint `/communication-hub/media` acessível
- [ ] Arquivo físico existe e é legível
- [ ] Permissões de arquivo corretas (644 ou 755)
- [ ] BASE_PATH definido corretamente
- [ ] URL gerada corretamente (com BASE_PATH)
- [ ] Mensagem retornada com `media.url` preenchido
- [ ] View PHP recebe `$msg['media']` corretamente
- [ ] Condição `!empty($msg['media']['url'])` retorna `true`
- [ ] Elemento `<audio>` sendo criado no DOM
- [ ] Navegador suporta OGG (ou converter para MP3)
- [ ] Sem erros de CORS
- [ ] Sem bloqueios de segurança do navegador

---

## 🛠️ Scripts de Diagnóstico Criados

1. **`database/verificar-midia-5511965221349-final.php`**
   - Verifica mídia no banco remoto
   - Mostra estatísticas de processamento

2. **`database/testar-thread-completo.php`**
   - Simula retorno completo da thread
   - Valida estrutura da mensagem

3. **`database/simular-mensagem-thread.php`**
   - Simula montagem da mensagem
   - Verifica limpeza de conteúdo base64

4. **`database/verificar-renderizacao-inicial.php`**
   - Testa condições de renderização da view
   - Valida estrutura esperada

5. **`database/processar-audio-base64-5511965221349.php`**
   - Processa áudio do evento existente
   - Extrai e salva arquivo

---

## 📝 Conclusão

**Status Atual:**
- ✅ Backend: Processamento funcionando corretamente
- ✅ Banco de Dados: Dados corretos
- ✅ Sistema de Arquivos: Arquivo salvo corretamente
- ✅ Estrutura de Dados: Mensagem montada corretamente
- ❌ Frontend: Áudio não aparece na interface

**Hipótese Principal:**
O problema está na **renderização ou acesso ao endpoint de mídia**, não no processamento dos dados. Possíveis causas:
1. URL incorreta (BASE_PATH não aplicado)
2. Endpoint não acessível
3. Mensagem não incluída no carregamento inicial da thread
4. Cache do navegador

**Recomendação:**
Realizar debug no navegador (DevTools) para identificar exatamente onde o fluxo está falhando.

---

**Documento gerado em:** 16/01/2026  
**Última atualização:** 16/01/2026  
**Versão:** 1.0

