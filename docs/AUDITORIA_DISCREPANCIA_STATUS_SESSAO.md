# Auditoria: Discrepância no Status da Sessão WhatsApp

**Data:** 2026-01-16  
**Problema:** Canal mostra "connected" na listagem, mas teste de envio diz "sessão não está ativa"  
**Status:** Investigando discrepância entre verificação de status

---

## 🔍 Análise do Comportamento

### 1. **Listagem de Canais (Teste de Conexão)**
- **Localização:** `settings/whatsapp-gateway` (teste de conexão)
- **Resultado:** ✅ `pixel12digital` mostra status `[connected]`
- **Método:** `GET /api/channels` retorna lista com status de cada canal

### 2. **Teste de Envio**
- **Localização:** `settings/whatsapp-gateway/test` (teste de envio)
- **Resultado:** ❌ Erro: "A sessão do WhatsApp não está ativa"
- **Método:** `GET /api/channels/{channelId}` verifica status específico antes de enviar

### 3. **Envio Real (Communication Hub)**
- **Localização:** `communication-hub` (envio de mensagem)
- **Resultado:** ❌ Erro 500
- **Método:** Mesma verificação do teste, mas com tratamento diferente

---

## 🔎 Comparação de Código

### WhatsAppGatewayTestController::sendTest() (linhas 299-322)

```php
if ($channelInfo['success']) {
    $channelData = $channelInfo['raw'] ?? [];
    $sessionStatus = $channelData['status'] ?? $channelData['connection'] ?? null;
    $isConnected = ($sessionStatus === 'connected' || $sessionStatus === 'open' || $channelData['connected'] ?? false);
    
    if (!$isConnected) {
        // Retorna erro 400 imediatamente
        $this->json([
            'success' => false,
            'error' => 'A sessão do WhatsApp não está ativa...',
            'error_code' => 'SESSION_DISCONNECTED'
        ], 400);
        return;
    }
}
```

### CommunicationHubController::send() (linhas 771-779)

```php
} else {
    $channelData = $channelInfo['raw'] ?? [];
    $sessionStatus = $channelData['status'] ?? $channelData['connection'] ?? null;
    $isConnected = ($sessionStatus === 'connected' || $sessionStatus === 'open' || $channelData['connected'] ?? false);
    
    if (!$isConnected) {
        $shouldBlockSend = true;
        $blockReason = "Sessão desconectada";
    }
}
```

**Diferença:** O `CommunicationHubController` apenas marca para bloquear, mas não retorna erro imediatamente. Continua o loop e adiciona ao array de erros.

---

## 🎯 Problemas Identificados

### 1. **Estrutura de Dados do Gateway Pode Ser Diferente**

**Hipótese:** O endpoint `GET /api/channels` (listagem) pode retornar estrutura diferente de `GET /api/channels/{channelId}` (canal específico).

**Verificação necessária:**
- Comparar estrutura de resposta de `listChannels()` vs `getChannel()`
- Verificar se o campo `status` ou `connection` está em locais diferentes

### 2. **Falta de Validação Defensiva**

**Problema:** Ambos os métodos assumem que `$channelInfo['raw']` sempre existe e tem a estrutura esperada.

**Risco:** Se o gateway retornar estrutura diferente, `$channelData['status']` pode ser `null` mesmo que a sessão esteja conectada.

### 3. **Possível Problema: Status no List vs Status no Get**

**Cenário possível:**
- `GET /api/channels` retorna: `[{channel: "pixel12digital", status: "connected"}]`
- `GET /api/channels/pixel12digital` retorna: `{channel: "pixel12digital", connection: "disconnected"}` ou estrutura diferente

**Evidência:** O teste de conexão mostra "connected", mas o teste de envio diz "não está ativa".

---

## 🔧 Recomendações

### 1. **Adicionar Log Detalhado da Resposta do Gateway**

```php
// Após getChannel()
error_log("[DEBUG] channelInfo completo: " . json_encode($channelInfo, JSON_PRETTY_PRINT));
error_log("[DEBUG] channelData: " . json_encode($channelData ?? [], JSON_PRETTY_PRINT));
error_log("[DEBUG] sessionStatus: " . ($sessionStatus ?? 'NULL'));
error_log("[DEBUG] isConnected: " . ($isConnected ? 'true' : 'false'));
```

### 2. **Verificar Estrutura Real da Resposta**

Adicionar validação para verificar TODOS os campos possíveis:

```php
$channelData = $channelInfo['raw'] ?? [];
$sessionStatus = null;

// Tenta múltiplos caminhos
if (isset($channelData['status'])) {
    $sessionStatus = $channelData['status'];
} elseif (isset($channelData['connection'])) {
    $sessionStatus = $channelData['connection'];
} elseif (isset($channelData['session']['status'])) {
    $sessionStatus = $channelData['session']['status'];
} elseif (isset($channelData['data']['status'])) {
    $sessionStatus = $channelData['data']['status'];
}

$isConnected = (
    $sessionStatus === 'connected' || 
    $sessionStatus === 'open' || 
    $sessionStatus === 'authenticated' ||
    ($channelData['connected'] ?? false) === true
);
```

### 3. **Comparar Respostas dos Endpoints**

Criar script de teste para comparar:
- `GET /api/channels` → estrutura retornada
- `GET /api/channels/pixel12digital` → estrutura retornada
- Verificar diferenças na estrutura de dados

### 4. **Adicionar Fallback Mais Tolerante**

Se não conseguir determinar o status, permitir tentar enviar (com aviso):

```php
if ($channelInfo['success'] && !$isConnected) {
    // Bloqueia apenas se CERTEZA que está desconectado
    error_log("[WARNING] Status não conectado detectado, mas tentando enviar mesmo assim");
    // Não bloqueia - deixa o gateway decidir
}
```

---

## 📋 Checklist de Diagnóstico

- [ ] Verificar logs do servidor para ver estrutura exata de `$channelInfo['raw']`
- [ ] Comparar resposta de `listChannels()` vs `getChannel('pixel12digital')`
- [ ] Verificar se há diferença entre os endpoints do gateway
- [ ] Testar envio mesmo com status "não conectado" para ver resposta real do gateway
- [ ] Verificar se o gateway retorna erro diferente quando tenta enviar com sessão desconectada

---

## 🎯 Próximos Passos

1. **Adicionar logs detalhados** na verificação de status
2. **Testar envio direto** sem verificação prévia (para ver erro real do gateway)
3. **Comparar estruturas** de resposta dos endpoints
4. **Ajustar validação** baseado na estrutura real retornada

---

## 💡 Observação Importante

O fato de o teste de conexão mostrar "connected" mas o teste de envio dizer "não está ativa" sugere que:
- **OU** os endpoints retornam estruturas diferentes
- **OU** há um problema de timing (sessão desconecta entre as verificações)
- **OU** a verificação de status está lendo o campo errado

A solução requer verificar a estrutura REAL retornada pelo gateway em ambos os casos.

