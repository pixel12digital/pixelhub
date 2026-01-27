# Diagnóstico Automático - Pronto para Execução

## ✅ Instrumentação Completa Implementada

### 1. Handler Corrigo Confirmado
- ✅ Rota: `POST /communication-hub/send` → `CommunicationHubController@send`
- ✅ Stamp: `SEND_HANDLER_STAMP=15a1023` + `__FILE__` + `__LINE__`
- ✅ TRACE completo: raw/trim do `channel_id`, `tenant_id`, `thread_id`
- ✅ RETURN_POINT: Tags exclusivos (A, B, C, D) antes de cada retorno CHANNEL_NOT_FOUND

### 2. Método json() Instrumentado
- ✅ Loga payload final ANTES de `json_encode()`
- ✅ Loga especificamente o `channel_id` se presente
- ✅ Loga JSON final (primeiros 500 chars) para detectar mutações

### 3. Endpoint de Diagnóstico Automático
- ✅ `GET /diagnostic-channel-fix.php` - Apenas diagnóstico
- ✅ `POST /diagnostic-channel-fix.php` - Aplica fix automaticamente

## 🔄 Fluxo de Diagnóstico Automático

### Passo 1: Aplicar Fix Automaticamente

**Via navegador ou curl:**
```bash
# Aplicar fix
curl -X POST http://localhost/painel.pixel12digital/diagnostic-channel-fix.php

# Ou apenas diagnóstico
curl http://localhost/painel.pixel12digital/diagnostic-channel-fix.php
```

**O endpoint:**
1. Verifica se tenant 25 existe
2. Verifica vínculo atual
3. Busca canais disponíveis similares a pixel12digital
4. Se POST: Aplica fix (UPDATE ou INSERT conforme necessário)
5. Valida vínculo final
6. Retorna JSON completo com diagnóstico

### Passo 2: Usuário Faz Teste de Envio

O usuário apenas:
- Abre o Communication Hub
- Seleciona conversa `whatsapp_2`
- Clica em enviar uma mensagem

### Passo 3: Análise Automática dos Logs

**Procurar no log do servidor (últimas 100 linhas):**

1. **STAMP:**
   ```
   [CommunicationHub::send] ===== SEND_HANDLER_STAMP=15a1023 =====
   [CommunicationHub::send] __FILE__: ...
   [CommunicationHub::send] __LINE__: ...
   ```

2. **TRACE:**
   ```
   [CommunicationHub::send] ===== TRACE channel_id INÍCIO =====
   [CommunicationHub::send] TRACE: raw $_POST['channel_id'] = ...
   [CommunicationHub::send] TRACE: trim($_POST['channel_id']) = ...
   ```

3. **RETURN_POINT (se erro):**
   ```
   [CommunicationHub::send] ===== RETURN_POINT=X (CHANNEL_NOT_FOUND) =====
   [CommunicationHub::send] RETURN_POINT=X: variável usada para channel_id no response = ...
   ```

4. **PAYLOAD FINAL (método json()):**
   ```
   [Controller::json] ===== PAYLOAD FINAL ANTES json_encode =====
   [Controller::json] channel_id no payload: '...'
   [Controller::json] Payload completo: ...
   [Controller::json] JSON final (primeiros 500 chars): ...
   ```

## 📊 Interpretação dos Resultados

### Caso 1: Stamp NÃO aparece
**Causa:** Handler errado, deploy não refletiu, OPcache

**Ação automática:**
- Verificar roteamento em `public/index.php`
- Verificar timestamp do arquivo `CommunicationHubController.php`
- Se OPcache: fazer `touch` no arquivo + reiniciar PHP

### Caso 2: Stamp aparece, logs mostram `channel_id = pixel12digital`, mas Network mostra "Pixel12 Digital"
**Causa:** Mutação no método `json()` ou middleware

**Evidência nos logs:**
- `[Controller::json] channel_id no payload: 'pixel12digital'` (correto)
- Mas Network tab mostra `"channel_id": "Pixel12 Digital"` (mutado)

**Ação:** Verificar se há middleware ou handler global transformando o response

### Caso 3: Stamp aparece, RETURN_POINT indica falha por vínculo
**Causa:** Vínculo não aplicado ou validação consulta tabela errada

**Evidência nos logs:**
- `validateGatewaySessionId: Canal não encontrado para tenant 25`
- Ou: `Canal encontrado mas tenant_id = 121`

**Ação automática:**
- Executar `POST /diagnostic-channel-fix.php` novamente
- Verificar se `validateGatewaySessionId()` consulta `tenant_message_channels`

## 🎯 Entregável Final

Após o teste de envio, retornar:

1. **Stamp apareceu?** (sim/não) + `__FILE__` real
2. **Qual RETURN_POINT disparou?** (ou "nenhum, enviou OK")
3. **Qual foi o channel_id final usado no response?** (e de onde veio)
4. **Causa raiz conclusiva:**
   - (A) handler errado/opcache/deploy
   - (B) mutação no json() / handler global
   - (C) vínculo tenant↔canal / validação por tenant
5. **Correção aplicada** (o que foi ajustado)
6. **Resultado do novo teste** (HTTP 200 ou novo erro)

## 🚀 Execução Imediata

1. **Aplicar fix automaticamente:**
   ```bash
   curl -X POST http://localhost/painel.pixel12digital/diagnostic-channel-fix.php
   ```

2. **Usuário faz teste:** Clica em enviar mensagem

3. **Coletar logs automaticamente:**
   ```bash
   # Últimas 200 linhas do log
   tail -200 /var/log/php/error.log | grep -A 50 "SEND_HANDLER_STAMP"
   ```

4. **Análise automática:** Comparar logs com casos acima

## 📝 Notas Importantes

- Todo o diagnóstico é automático
- Usuário só precisa clicar em enviar
- Logs capturam tudo necessário
- Endpoint de fix pode ser chamado automaticamente
- Não requer acesso manual ao banco ou logs

