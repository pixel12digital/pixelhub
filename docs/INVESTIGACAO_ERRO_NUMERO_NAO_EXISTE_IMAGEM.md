# Investigação: Erro "O número não existe" ao enviar imagem (mensagem entregue)

**Data:** 29/01/2026  
**Contexto:** Usuário enviou imagem para (87) 9988-4234 (Robson). O sistema retornou erro "WPPConnect sendImageBase64 failed: O número 558799884234 não existe", mas a mensagem foi **corretamente entregue** no WhatsApp do cliente.

---

## 1. Fluxo do envio de imagem

```
PixelHub (CommunicationHubController)
    → gateway->sendImage(channelId, to, base64, ...)
        → WhatsAppGatewayClient::request('POST', '/api/messages', payload)
            → Gateway Wrapper (VPS) - rota /api/messages
                → wppconnectAdapter.sendImageBase64(sessionId, to, base64, caption, ...)
                    → WPPConnect Server: POST /api/{session}/send-image
                        → WPPConnect/Baileys → WhatsApp
```

---

## 2. Origem do erro

### 2.1 Onde o erro é gerado

O texto **"WPPConnect sendImageBase64 failed: O número 558799884234 não existe"** é montado no **gateway wrapper** (VPS), no arquivo `wppconnectAdapter.js`:

```javascript
// docs/PACOTE_VPS_IMPLEMENTAR_SEND_IMAGE.md (linha 167)
throw new Error(`WPPConnect sendImage failed: ${error.response?.data?.message || error.message}`);
```

A parte **"O número 558799884234 não existe"** vem de:
- `error.response?.data?.message` (resposta HTTP do WPPConnect server), ou
- `error.message` (mensagem da exceção)

Ou seja: o **WPPConnect server** (ou a biblioteca subjacente) retorna essa mensagem em português.

### 2.2 Como o PixelHub recebe o erro

No `WhatsAppGatewayClient::request()`:
- O gateway wrapper retorna HTTP 4xx/5xx com body JSON contendo `error` ou `message`
- O método `request()` decodifica o JSON e extrai: `$decoded['error'] ?? $decoded['message'] ?? ...`
- Retorna `['success' => false, 'error' => '...']` para o controller
- O controller propaga o erro ao frontend, que exibe em `alert()` ou similar

---

## 3. Causa provável do falso negativo

A mensagem foi entregue, mas o gateway retornou erro. Hipóteses:

### 3.1 Validação pós-envio (mais provável)

O WPPConnect/Baileys pode:
1. **Enviar a mensagem** para o WhatsApp (sucesso)
2. **Executar alguma validação** (ex.: checar se o número está no WhatsApp, `onWhatsApp`, etc.)
3. **Falhar na validação** e lançar exceção
4. O HTTP response retorna erro, mesmo com a mensagem já enviada

A checagem "número existe" pode falhar por:
- Número não está na lista de contatos do celular conectado
- Cache/estado desatualizado
- API do WhatsApp retorna sucesso no envio mas falha em outra consulta

### 3.2 Ordem de operações no WPPConnect

Algumas implementações fazem:
1. Validação do número **antes** do envio
2. Se falhar → lança "número não existe"
3. Em paralelo ou em outro fluxo, a mensagem pode ser enviada (race condition)

### 3.3 Bug no WPPConnect/Baileys

Possível bug em que a mensagem é enviada com sucesso, mas a função retorna/relança um erro de outro contexto (ex.: validação, timeout, etc.).

### 3.4 Formato do número

O número `558799884234` tem 12 dígitos (55 + 87 + 99884234). Celulares no Brasil costumam ter 9 dígitos após o DDD. Pode haver:
- Tratamento especial para números com 8 dígitos (fixo)
- Validação que considera o número “inválido” e retorna erro, mesmo com o WhatsApp aceitando o envio

---

## 4. Onde investigar na VPS

Para confirmar a causa, o Charles pode executar na VPS:

### 4.1 Logs do gateway no momento do envio

```bash
# Logs do container no horário do envio
docker logs gateway-wrapper --since "2026-02-05T08:00:00" --until "2026-02-05T09:00:00" 2>&1 | grep -i "sendImage\|558799884234\|não existe\|numero"
```

### 4.2 Código do sendImageBase64 no adapter

```bash
# Ver implementação atual do sendImageBase64
grep -n -A 80 "async sendImageBase64" /opt/pixel12-whatsapp-gateway/wrapper/src/services/wppconnectAdapter.js
```

### 4.3 Resposta do WPPConnect server

Verificar se o WPPConnect server (container/serviço que o adapter chama) retorna JSON com `message` ou `error` contendo "O número X não existe". A mensagem em português sugere que vem de:
- WPPConnect server configurado em PT-BR, ou
- Camada de tradução/tratamento de erro no wrapper

### 4.4 Buscar a string no código da VPS

```bash
# Procurar origem da mensagem "não existe"
grep -r "não existe\|nao existe\|does not exist" /opt/pixel12-whatsapp-gateway/ 2>/dev/null || echo "Não encontrado no wrapper"
```

---

## 5. Conclusão

| Item | Conclusão |
|------|-----------|
| **Origem do erro** | Gateway wrapper (VPS), que repassa a mensagem do WPPConnect server |
| **Mensagem original** | "O número 558799884234 não existe" (em português) |
| **Causa provável** | Validação ou checagem no WPPConnect que falha **depois** (ou em paralelo) ao envio bem-sucedido |
| **Evidência** | Mensagem entregue no WhatsApp do cliente |

**Próximos passos sugeridos:**
1. Executar os comandos de diagnóstico na VPS (seção 4)
2. Verificar se o WPPConnect server faz validação de número antes/depois do envio
3. Avaliar desabilitar ou ajustar essa validação para envio de imagens, se for redundante
4. Ou tratar o erro como aviso quando houver confirmação de entrega (ex.: webhook `message.sent`)
