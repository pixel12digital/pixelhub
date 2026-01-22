# Diagnóstico: Erro ao Enviar Áudio via WPPConnect

**Data:** 19/01/2026  
**Erro:** `WPPCONNECT_SEND_ERROR` - "Falha ao enviar áudio via WPPConnect"  
**Canal:** `pixel12digital`

---

## 🔴 Problema Identificado

Ao tentar enviar mensagens de áudio via WhatsApp, o sistema retorna erro 500 com a mensagem:
```json
{
  "success": false,
  "error": "Falha ao enviar áudio via WPPConnect. Verifique se a sessão está conectada e se o formato do áudio está correto (OGG/Opus).",
  "error_code": "WPPCONNECT_SEND_ERROR",
  "channel_id": "pixel12digital"
}
```

---

## ✅ Melhorias Implementadas

### 1. Validações Adicionais (`WhatsAppGatewayClient.php`)

- ✅ **Validação de tamanho:** Verifica se o áudio excede 16MB antes de enviar
- ✅ **Logs detalhados:** Registra tamanho do áudio e resposta completa do gateway
- ✅ **Timeout aumentado:** Requisições de áudio agora usam timeout de 60 segundos (antes 30s)
- ✅ **Mensagens de erro melhoradas:** Identifica erros específicos do WPPConnect

### 2. Melhor Tratamento de Erros (`CommunicationHubController.php`)

- ✅ **Detecção de erros específicos:**
  - `SESSION_DISCONNECTED`: Sessão desconectada
  - `WPPCONNECT_SEND_ERROR`: Erro no WPPConnect ao enviar áudio
  - `AUDIO_TOO_LARGE`: Áudio muito grande
- ✅ **Mensagens de erro mais descritivas** para o usuário
- ✅ **Logs detalhados** para diagnóstico

### 3. Script de Diagnóstico

- ✅ Criado `database/diagnostico-audio-wppconnect.php` para verificar status da sessão

---

## 🔍 Diagnóstico Necessário

O erro está vindo do **gateway WPPConnect**, não do código do painel. Para identificar a causa raiz, verifique:

### 1. Status da Sessão no Gateway

Execute no servidor do gateway:
```bash
# Verificar status da sessão pixel12digital
curl -H "X-Gateway-Secret: [SECRET]" \
  https://wpp.pixel12digital.com.br/api/channels/pixel12digital
```

**Verificar:**
- Se `status` ou `connection` está como `connected` ou `open`
- Se `connected` (boolean) está como `true`

### 2. Logs do Gateway WPPConnect

Verifique os logs do gateway no servidor:
```bash
# Logs do WPPConnect
docker logs wppconnect-server --since 10m | grep -i "pixel12digital\|sendVoiceBase64\|audio"
```

**Procurar por:**
- Erros relacionados a `sendVoiceBase64`
- Mensagens sobre formato de áudio
- Timeouts ou erros de conexão
- Problemas com a sessão `pixel12digital`

### 3. Formato do Áudio

O código valida que o áudio:
- ✅ É OGG/Opus (contém `OpusHead`)
- ✅ Tem tamanho mínimo de 2000 bytes
- ✅ Não excede 16MB

**Verificar no frontend:**
- Se o áudio está sendo gravado corretamente
- Se o formato está correto (WebM pode precisar ser convertido para OGG/Opus)

### 4. Logs do Painel

Verifique os logs do painel após tentar enviar áudio:
```bash
# Windows PowerShell
Get-Content logs\pixelhub.log -Tail 100 | Select-String -Pattern "sendAudioBase64Ptt|CommunicationHub::send"
```

**Procurar por:**
- `[WhatsAppGateway::sendAudioBase64Ptt]` - Logs do envio
- `[CommunicationHub::send]` - Logs do controller
- Resposta completa do gateway

---

## 🛠️ Possíveis Causas

### 1. Sessão Desconectada
**Sintoma:** Erro genérico "Erro ao enviar a mensagem"  
**Solução:** Reconectar a sessão `pixel12digital` no gateway

### 2. Formato de Áudio Incorreto
**Sintoma:** Erro ao processar o áudio no WPPConnect  
**Solução:** Garantir que o áudio está em formato OGG/Opus

### 3. Tamanho do Áudio
**Sintoma:** Timeout ou erro de processamento  
**Solução:** Reduzir duração/qualidade do áudio

### 4. Problema no Gateway WPPConnect
**Sintoma:** Erro interno no gateway  
**Solução:** Verificar logs do gateway e reiniciar se necessário

---

## 📋 Checklist de Verificação

- [ ] Sessão `pixel12digital` está conectada no gateway
- [ ] Logs do gateway mostram erro específico
- [ ] Formato do áudio está correto (OGG/Opus)
- [ ] Tamanho do áudio não excede 16MB
- [ ] Logs do painel mostram resposta completa do gateway
- [ ] Teste com mensagem de texto funciona (confirma que sessão está OK)

---

## 🔧 Próximos Passos

1. **Verificar status da sessão** usando o script de diagnóstico ou API do gateway
2. **Verificar logs do gateway** para erro específico do WPPConnect
3. **Testar envio de texto** para confirmar que a sessão funciona
4. **Verificar formato do áudio** no frontend (gravação)
5. **Revisar logs do painel** após tentar enviar áudio novamente

---

## 📝 Arquivos Modificados

1. `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`
   - Validação de tamanho
   - Timeout aumentado para áudio
   - Logs detalhados

2. `src/Controllers/CommunicationHubController.php`
   - Detecção de erros específicos
   - Mensagens de erro melhoradas
   - Logs detalhados

3. `database/diagnostico-audio-wppconnect.php`
   - Script de diagnóstico criado

---

## 💡 Notas Importantes

- O erro está vindo do **gateway WPPConnect**, não do código do painel
- As melhorias implementadas fornecem **melhor diagnóstico** e **mensagens mais claras**
- Os logs agora capturam **resposta completa do gateway** para análise
- O timeout foi aumentado para **60 segundos** para requisições de áudio

