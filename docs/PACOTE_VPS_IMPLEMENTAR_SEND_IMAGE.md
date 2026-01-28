# Pacote de Execução VPS — Implementar envio de imagens no Gateway

**Data:** 28/01/2026  
**Objetivo:** Adicionar suporte a envio de imagens (`sendImageBase64`) no gateway WPPConnect.

**Contexto:** O gateway atualmente só suporta `sendMessage` (texto) e `sendVoiceBase64` (áudio). O Hub envia `type: image` com `base64`, mas o gateway ignora e não envia para o WhatsApp.

---

## Formato do Pacote (copiar/colar para o Charles)

**VPS – OBJETIVO:** Implementar método `sendImageBase64` no adapter e rota para processar `type: image`.  
**SERVIÇO:** gateway-wrapper (container Docker).  
**RISCO:** Médio (altera código do gateway, requer restart do container).  
**ROLLBACK:** Ver seção 5.

---

### 1) Pré-check (não muda nada)

**Comandos (copiar/colar e devolver os outputs):**

```bash
# 1. Confirmar que o arquivo existe
ls -la /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js

# 2. Ver última função sendVoiceBase64 (referência para implementar sendImageBase64)
grep -n "async sendVoiceBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js

# 3. Ver onde termina a função sendVoiceBase64 (para inserir depois)
sed -n '590,700p' /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js | head -80

# 4. Ver como a rota /api/messages processa áudio (referência)
grep -n -A 30 "type.*audio\|sendVoiceBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js | head -50
```

**O que retornar:** Saída dos comandos para confirmar estrutura do código.

---

### 2) Execução — Parte A: Adicionar método `sendImageBase64` no Adapter

**Comando (copiar/colar um bloco único):**

```bash
# Backup do arquivo original
cp /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js \
   /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js.bak.$(date +%Y%m%d_%H%M%S)

# Criar o patch com o método sendImageBase64
cat > /tmp/patch-sendImage.js << 'PATCH_EOF'

  /**
   * Send image message using base64
   * WPPConnect API: POST /api/{session}/send-image
   * @param {string} sessionId - Session identifier
   * @param {string} to - Recipient phone number
   * @param {string} base64Image - Base64 encoded image (with or without data URI prefix)
   * @param {string} caption - Optional caption for the image
   * @param {string} correlationId - Request correlation ID
   * @param {boolean} isGroup - Whether sending to a group
   * @returns {Promise<Object>} - Send result
   */
  async sendImageBase64(sessionId, to, base64Image, caption = '', correlationId, isGroup = false) {
    sessionId = __normalizeSessionId(sessionId);
    const token = await this.ensureToken(sessionId, correlationId);
    const headers = {
      'X-Correlation-ID': correlationId,
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    };

    // Accept raw base64 or data-uri (data:image/png;base64,...)
    let b64 = String(base64Image || '');
    const idx = b64.indexOf('base64,');
    if (idx !== -1) {
      b64 = b64.substring(idx + 7);
    }
    b64 = b64.trim();

    if (!b64 || b64.length < 100) {
      throw new Error('Invalid or empty base64 image data');
    }

    logger.debug('Sending image (base64)', {
      sessionId,
      url: `/api/${sessionId}/send-image`,
      to,
      hasCaption: !!caption,
      base64Length: b64.length
    });

    try {
      const response = await this.client.post(
        `/api/${sessionId}/send-image`,
        {
          phone: to,
          isGroup: isGroup,
          base64: b64,
          caption: caption || ''
        },
        { headers }
      );

      logger.info('Image sent successfully', {
        sessionId,
        to,
        correlationId,
        messageId: response.data?.response?.id || response.data?.id
      });

      return {
        success: true,
        messageId: response.data?.response?.id || response.data?.id,
        raw: response.data
      };
    } catch (error) {
      // If 401, invalidate token and retry once
      if (error.response?.status === 401) {
        logger.warn('Send image returned 401, invalidating token and retrying', { sessionId });
        this.invalidateToken(sessionId);
        const newToken = await this.ensureToken(sessionId, correlationId);
        const retryHeaders = { ...headers, 'Authorization': `Bearer ${newToken}` };
        
        try {
          const retryResponse = await this.client.post(
            `/api/${sessionId}/send-image`,
            {
              phone: to,
              isGroup: isGroup,
              base64: b64,
              caption: caption || ''
            },
            { headers: retryHeaders }
          );

          logger.info('Image sent successfully (after retry)', {
            sessionId,
            to,
            correlationId,
            messageId: retryResponse.data?.response?.id || retryResponse.data?.id
          });

          return {
            success: true,
            messageId: retryResponse.data?.response?.id || retryResponse.data?.id,
            raw: retryResponse.data
          };
        } catch (retryError) {
          logger.error('Send image failed after retry', {
            sessionId,
            to,
            error: retryError.message,
            status: retryError.response?.status
          });
          throw retryError;
        }
      }

      logger.error('Send image failed', {
        sessionId,
        to,
        error: error.message,
        status: error.response?.status,
        responseData: error.response?.data
      });

      throw new Error(`WPPConnect sendImage failed: ${error.response?.data?.message || error.message}`);
    }
  }
PATCH_EOF

echo "Patch criado em /tmp/patch-sendImage.js"
```

**Agora inserir o método após `sendVoiceBase64`:**

```bash
# Encontrar a linha onde termina sendVoiceBase64 (procurar o fechamento da função)
# A função sendVoiceBase64 começa na linha 590, precisamos encontrar onde termina

# Primeiro, ver o número da linha onde deve ser inserido
# (após o fechamento do método sendVoiceBase64)
LINE_NUM=$(awk '/async sendVoiceBase64/,/^  }$/ {last=NR} END {print last}' \
  /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js)

echo "Inserir após a linha: $LINE_NUM"

# Inserir o patch após essa linha
sed -i "${LINE_NUM}r /tmp/patch-sendImage.js" \
  /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js

# Verificar se foi inserido
grep -n "async sendImageBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js
```

---

### 3) Execução — Parte B: Atualizar rota /api/messages para processar imagens

**Comando (copiar/colar um bloco único):**

```bash
# Backup do arquivo de rotas
cp /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js \
   /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js.bak.$(date +%Y%m%d_%H%M%S)

# Ver onde está o handler de áudio para inserir o de imagem logo após
grep -n "type.*audio\|sendVoiceBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js | head -5
```

**Agora criar e aplicar o patch para a rota:**

```bash
# Criar patch para adicionar handler de imagem
# (deve ser inserido ANTES do else que chama sendMessage para texto)

cat > /tmp/patch-route-image.js << 'ROUTE_PATCH_EOF'
      // ===== IMAGE HANDLING =====
      } else if (type === 'image' && base64) {
        logger.info('Processing image message', {
          channel,
          to,
          hasCaption: !!caption,
          base64Length: base64?.length || 0,
          correlationId
        });

        // Clean base64 (remove data URI prefix if present)
        let cleanBase64 = String(base64 || '');
        const idx = cleanBase64.indexOf('base64,');
        if (idx !== -1) {
          cleanBase64 = cleanBase64.substring(idx + 7);
        }
        cleanBase64 = cleanBase64.trim();

        if (!cleanBase64 || cleanBase64.length < 100) {
          return res.status(400).json({
            success: false,
            error: 'Invalid or empty base64 image data',
            correlationId
          });
        }

        const result = await wppconnectAdapter.sendImageBase64(
          channel,
          to,
          cleanBase64,
          caption || text || '', // Use caption or text as caption
          correlationId,
          false // isGroup
        );

        return res.json({
          success: true,
          messageId: result.messageId,
          correlationId,
          raw: result.raw
        });
ROUTE_PATCH_EOF

echo "Patch de rota criado em /tmp/patch-route-image.js"

# Agora inserir o patch no arquivo api.js
# Precisa ser inserido após o bloco de áudio, antes do else final (texto)

# Encontrar a linha onde termina o bloco de áudio e começa o else
AUDIO_END=$(grep -n "sendVoiceBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js | tail -1 | cut -d: -f1)
echo "Bloco de áudio termina perto da linha: $AUDIO_END"

# Procurar o "} else {" ou "else {" após o bloco de áudio (é o handler de texto)
INSERT_LINE=$(awk -v start="$AUDIO_END" 'NR > start && /^\s*\} else \{|\s*else \{/ {print NR; exit}' \
  /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js)

echo "Inserir antes da linha: $INSERT_LINE"

# Se encontrou a linha, inserir o patch
if [ -n "$INSERT_LINE" ] && [ "$INSERT_LINE" -gt 0 ]; then
  # Inserir antes do else (que é o handler de texto)
  sed -i "$((INSERT_LINE-1))r /tmp/patch-route-image.js" \
    /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
  echo "Patch inserido com sucesso"
else
  echo "ERRO: Não foi possível determinar onde inserir o patch"
  echo "Inserção manual necessária"
fi

# Verificar se foi inserido
grep -n "type === 'image'" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
```

---

### 4) Reinício do Container

**Comandos:**

```bash
# Reiniciar o container do gateway
docker restart gateway-wrapper

# Aguardar 10 segundos
sleep 10

# Verificar se está rodando
docker ps | grep gateway-wrapper

# Ver logs do container (últimas 30 linhas)
docker logs gateway-wrapper --tail 30
```

**O que retornar:** Status do container e logs de inicialização.

---

### 5) Verificação

**Comandos (copiar/colar e devolver os outputs):**

```bash
# A) Verificar se o método foi adicionado
grep -n "async sendImageBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js

# B) Verificar se a rota processa imagem
grep -n "type === 'image'" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js

# C) Testar endpoint com curl (deve retornar erro de base64 inválido, não 500)
curl -sk -X POST \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: $(cat /opt/pixel12-whatsapp-gateway/.env | grep GATEWAY_SECRET | cut -d= -f2)" \
  -d '{"channel":"pixel12digital","to":"5547999999999","type":"image","base64":"test","text":"teste"}' \
  "http://127.0.0.1:3000/api/messages" | head -100

# D) Ver logs do gateway para erros
docker logs gateway-wrapper --tail 50 2>&1 | grep -i "error\|image\|sendImage"
```

**Critério de sucesso:**
- **(A)** Método `sendImageBase64` existe no adapter
- **(B)** Rota processa `type === 'image'`
- **(C)** Endpoint retorna erro de validação (não 500 interno)
- **(D)** Sem erros críticos nos logs

---

### 6) Rollback (se algo der errado)

**Comandos:**

```bash
# Restaurar arquivos de backup
ADAPTER_BACKUP=$(ls -t /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js.bak.* 2>/dev/null | head -1)
ROUTE_BACKUP=$(ls -t /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js.bak.* 2>/dev/null | head -1)

if [ -n "$ADAPTER_BACKUP" ]; then
  cp "$ADAPTER_BACKUP" /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js
  echo "Adapter restaurado de $ADAPTER_BACKUP"
fi

if [ -n "$ROUTE_BACKUP" ]; then
  cp "$ROUTE_BACKUP" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
  echo "Rotas restauradas de $ROUTE_BACKUP"
fi

# Reiniciar container
docker restart gateway-wrapper
sleep 10
docker ps | grep gateway-wrapper
```

---

### 7) Teste Final no Hub

Após executar o pacote com sucesso:
1. Acesse o Hub: `https://hub.pixel12digital.com.br/communication-hub`
2. Abra uma conversa
3. Cole uma imagem (Ctrl+V) ou use o botão de anexo
4. Envie
5. Verifique se a imagem chegou no WhatsApp

---

## Referências

| O quê | Onde |
|-------|------|
| Adapter WPPConnect | `/opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js` |
| Rotas API | `/opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js` |
| Container gateway | `gateway-wrapper` |
| Logs do container | `docker logs gateway-wrapper` |
