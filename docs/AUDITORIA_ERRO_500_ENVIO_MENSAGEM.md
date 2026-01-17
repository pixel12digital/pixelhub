# Auditoria: Erro 500 ao Enviar Mensagem

**Data:** 2026-01-16  
**Problema:** Mensagem não está sendo enviada mesmo com `channel_id` correto (`pixel12digital`)  
**Status:** `channel_id` agora está correto, mas ainda ocorre erro 500

---

## ✅ Correções Já Aplicadas

1. **`getWhatsAppThreadInfo()` corrigido:**
   - Agora prioriza `channel_id` da tabela `conversations` (fonte da verdade)
   - Usa mesma lógica de `extractChannelIdFromPayload()` ao buscar dos eventos
   - Rejeita valores incorretos como "ImobSites"

2. **`channel_id` agora está correto:**
   - Payload mostra `channel_id: pixel12digital` (correto)
   - Não está mais usando "ImobSites"

---

## 🔍 Problemas Identificados na Auditoria

### 1. **Falta de Tratamento de Exceções na Chamada `getChannel()`**

**Localização:** `src/Controllers/CommunicationHubController.php:748`

```php
// Valida se a sessão está conectada antes de enviar (NÃO-BLOQUEANTE)
$channelInfo = $gateway->getChannel($targetChannelId);
```

**Problema:**
- Se `$gateway->getChannel()` lançar uma exceção (ex: `RuntimeException` se secret não configurado), o código não está dentro de um try-catch específico
- A exceção só é capturada pelo catch geral no final do método (linha 939)
- Isso pode causar erro 500 sem log detalhado do ponto exato

**Risco:** MÉDIO - O método `request()` do `WhatsAppGatewayClient` não lança exceções, mas o construtor pode lançar `RuntimeException` se o secret não estiver configurado.

---

### 2. **Falta de Validação se `$channelInfo` é Array**

**Localização:** `src/Controllers/CommunicationHubController.php:750-754`

```php
$statusCode = $channelInfo['status'] ?? 'N/A';
$shouldBlockSend = false;
$blockReason = null;

if (!$channelInfo['success']) {
```

**Problema:**
- Se `$channelInfo` não for um array (ex: retornar `null` ou lançar exceção), acessar `$channelInfo['status']` ou `$channelInfo['success']` causará erro PHP
- Isso resultaria em erro 500

**Risco:** BAIXO - O método `getChannel()` sempre retorna um array, mas é bom ter validação defensiva.

---

### 3. **Falta de Tratamento de Exceções na Chamada `sendText()`**

**Localização:** `src/Controllers/CommunicationHubController.php:803`

```php
$result = $gateway->sendText($targetChannelId, $phoneNormalized, $message, [
    'sent_by' => Auth::user()['id'] ?? null,
    'sent_by_name' => Auth::user()['name'] ?? null
]);
```

**Problema:**
- Se `sendText()` lançar exceção (improvável, mas possível), só será capturada pelo catch geral
- Não há tratamento específico para erros de rede/timeout

**Risco:** BAIXO - O método `request()` trata erros de cURL e retorna array com `success: false`.

---

### 4. **Possível Problema: `$targetChannelId` Vazio ou Null**

**Localização:** `src/Controllers/CommunicationHubController.php:734-748`

**Cenário:**
- Se `$targetChannels` estiver vazio após todas as validações, o `foreach` não executa
- Mas há validação na linha 655 que retorna erro 400 se `$targetChannels` estiver vazio
- **PORÉM:** Se `$targetChannels` contiver um valor vazio ou null, o `foreach` executará com `$targetChannelId = null` ou `$targetChannelId = ''`

**Risco:** MÉDIO - Pode causar erro 500 se `$targetChannelId` for vazio/null ao chamar `getChannel('')` ou `sendText('', ...)`.

---

### 5. **Possível Problema: Gateway Secret Não Configurado**

**Localização:** `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php:27-28`

```php
if (empty($this->secret)) {
    throw new \RuntimeException('WPP_GATEWAY_SECRET não configurado');
}
```

**Problema:**
- Se o secret não estiver configurado, o construtor lança `RuntimeException`
- Isso acontece na linha 722: `$gateway = new WhatsAppGatewayClient($baseUrl, $secret);`
- A exceção só é capturada pelo catch geral (linha 939)

**Risco:** MÉDIO - Se o secret não estiver configurado, causará erro 500.

---

### 6. **Possível Problema: Erro 404 na Mídia (Não Relacionado ao Envio)**

**Console mostra:**
```
media:1  Failed to load resource: the server responded with a status of 404 (Not Found)
```

**Problema:**
- Erro 404 ao carregar mídia (provavelmente imagem da mensagem)
- Não está relacionado ao envio de mensagem, mas pode confundir o diagnóstico

**Risco:** BAIXO - Não afeta o envio de mensagens.

---

## 🎯 Recomendações de Correção

### Prioridade ALTA:

1. **Adicionar validação defensiva antes de usar `$channelInfo`:**
   ```php
   $channelInfo = $gateway->getChannel($targetChannelId);
   
   // Validação defensiva
   if (!is_array($channelInfo)) {
       error_log("[CommunicationHub::send] ERRO: getChannel retornou valor inválido para {$targetChannelId}");
       $sendResults[] = [
           'channel_id' => $targetChannelId,
           'success' => false,
           'error' => 'Erro ao verificar status do canal',
           'error_code' => 'CHANNEL_CHECK_ERROR'
       ];
       continue;
   }
   ```

2. **Adicionar validação de `$targetChannelId` antes do loop:**
   ```php
   foreach ($targetChannels as $targetChannelId) {
       // Validação: garante que channel_id não está vazio
       if (empty($targetChannelId) || trim($targetChannelId) === '') {
           error_log("[CommunicationHub::send] AVISO: Canal vazio ignorado no loop");
           continue;
       }
       
       $targetChannelId = trim($targetChannelId);
       // ... resto do código
   }
   ```

### Prioridade MÉDIA:

3. **Adicionar try-catch específico para chamadas do gateway:**
   ```php
   try {
       $channelInfo = $gateway->getChannel($targetChannelId);
   } catch (\RuntimeException $e) {
       error_log("[CommunicationHub::send] ERRO: Falha ao verificar canal: " . $e->getMessage());
       $sendResults[] = [
           'channel_id' => $targetChannelId,
           'success' => false,
           'error' => 'Erro ao verificar status do canal: ' . $e->getMessage(),
           'error_code' => 'GATEWAY_ERROR'
       ];
       continue;
   }
   ```

4. **Verificar se secret está configurado antes de criar gateway:**
   ```php
   $secret = GatewaySecret::getDecrypted();
   if (empty($secret)) {
       error_log("[CommunicationHub::send] ERRO: WPP_GATEWAY_SECRET não configurado");
       $this->json([
           'success' => false,
           'error' => 'Configuração do gateway não encontrada',
           'error_code' => 'GATEWAY_NOT_CONFIGURED'
       ], 500);
       return;
   }
   ```

---

## 📋 Checklist de Verificação

- [ ] Verificar logs do servidor para identificar exceção exata
- [ ] Verificar se `WPP_GATEWAY_SECRET` está configurado no `.env`
- [ ] Verificar se `$targetChannels` contém valores válidos (não vazios)
- [ ] Verificar se gateway está acessível (testar conexão)
- [ ] Verificar se `pixel12digital` existe em `tenant_message_channels` e está habilitado
- [ ] Verificar logs do gateway para ver se a requisição chegou

---

## 🔍 Próximos Passos

1. **Verificar logs do servidor** para identificar a exceção exata que está causando o erro 500
2. **Aplicar validações defensivas** recomendadas acima
3. **Testar envio** após correções
4. **Monitorar logs** para confirmar que o problema foi resolvido

---

## 📝 Notas

- O `channel_id` agora está correto (`pixel12digital`)
- O problema não é mais a identificação do canal
- O erro 500 provavelmente está relacionado a:
  - Exceção não tratada na chamada do gateway
  - Secret não configurado
  - Canal vazio/null sendo passado para o gateway
  - Erro de conexão com o gateway

