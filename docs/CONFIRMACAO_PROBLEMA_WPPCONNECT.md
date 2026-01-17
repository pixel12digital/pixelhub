# Confirmação: Problema no WPPConnect - Não emite onMessage para pixel12digital

**Data:** 2026-01-17 14:54  
**Teste realizado:** Mensagem enviada para `pixel12digital` e logs verificados imediatamente

---

## 📊 Resultados do Teste

### Comando 1: Verificar emissão de onMessage no WPPConnect
**Resultado:** ❌ **Nenhum evento `onMessage` emitido**

**O que encontramos:**
- ✅ `onPresenceChanged` sendo emitido: `Emitting onPresenceChanged event (1 registered)` (6 ocorrências)
- ❌ **Nenhum** `Emitting onMessage` encontrado

---

### Comando 2: Verificar recebimento no gateway-wrapper
**Resultado:** ❌ **Nenhum evento `onMessage` recebido**

**O que encontramos:**
- ✅ `onpresencechanged` sendo recebido do WPPConnect: 6 eventos
- ✅ Eventos convertidos para `connection.update` corretamente
- ✅ Webhook entregue ao painel com sucesso (status 200)
- ❌ **Nenhum** evento `onmessage` recebido

---

### Comando 3: TODOS os eventos no WPPConnect
**Resultado:** Apenas `onPresenceChanged` sendo emitido

**Eventos encontrados:**
```
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
```

**Eventos NÃO encontrados:**
- ❌ `Emitting onMessage`
- ❌ `Emitting onAnyMessage`
- ❌ Qualquer evento relacionado a mensagens recebidas

---

### Comando 4: TODOS os eventos no gateway-wrapper
**Resultado:** Apenas `onpresencechanged` sendo recebido

**Eventos encontrados:**
- ✅ `Received webhook event from WPPConnect` - `eventType: onpresencechanged` (6 ocorrências)
- ✅ `Webhook event queued` - `eventType: connection.update` (6 ocorrências)
- ✅ `Webhook delivered successfully` - status 200 (6 ocorrências)

**Eventos NÃO encontrados:**
- ❌ `Received webhook event from WPPConnect` - `eventType: onmessage`
- ❌ `Webhook event queued` - `eventType: whatsapp.inbound.message`

---

## 🔍 Análise Final

### O que está funcionando:
1. ✅ Listener `onMessage` registrado para `pixel12digital` no WPPConnect
2. ✅ Sessão autenticada e conectada
3. ✅ Eventos `onPresenceChanged` sendo emitidos e recebidos
4. ✅ Gateway-wrapper recebendo eventos do WPPConnect
5. ✅ Webhook do painel entregando eventos com sucesso (200 OK)

### O que não está funcionando:
1. ❌ **WPPConnect NÃO está emitindo eventos `onMessage`** para `pixel12digital`
2. ❌ Mesmo após enviar mensagem de teste, nenhum `Emitting onMessage` aparece nos logs
3. ❌ Gateway-wrapper não recebe `onMessage` porque o WPPConnect não está emitindo

---

## ✅ Confirmação do Problema

**Problema identificado:** O WPPConnect **não está detectando/emitindo eventos `onMessage`** para a sessão `pixel12digital`, apesar de:
- Ter o listener registrado (`Registering onMessage event`)
- Estar autenticado e conectado
- Receber outros eventos (`onPresenceChanged`)

**Evidência:** Mensagem de teste enviada, mas nenhum `Emitting onMessage` apareceu nos logs do WPPConnect nos últimos 2 minutos.

---

## 🎯 Causa Raiz

O problema **não está** no:
- ❌ Gateway-wrapper (está funcionando corretamente)
- ❌ Webhook do painel (está entregando eventos com sucesso)
- ❌ Listener registrado (foi registrado corretamente)

O problema **está** no:
- ✅ **WPPConnect não detecta/emite `onMessage` para `pixel12digital`**

**Possíveis causas:**
1. **Listener não está funcionando** (registrado mas não executando quando mensagens chegam)
2. **Filtro bloqueando eventos** (alguma condição impedindo emissão de `onMessage` para `pixel12digital`)
3. **Sessão não está recebendo mensagens** (problema de conexão/sincronização com WhatsApp Web)
4. **Versão/configuração do WPPConnect** (diferença entre `ImobSites` e `pixel12digital`)

---

## 📝 Próximos Passos

1. **Verificar se `ImobSites` está recebendo `onMessage`:**
   - Comparar logs recentes de `ImobSites` para confirmar se também não está recebendo ou se funciona normalmente

2. **Verificar configuração da sessão `pixel12digital` no WPPConnect:**
   - Verificar se há diferenças na inicialização/configuração entre `ImobSites` e `pixel12digital`
   - Verificar se há filtros ou condições bloqueando eventos para `pixel12digital`

3. **Reiniciar sessão `pixel12digital`:**
   - Desconectar e reconectar a sessão para forçar re-registro dos listeners

4. **Verificar logs completos do WPPConnect:**
   - Verificar se há erros ou warnings relacionados a `pixel12digital` que possam explicar o problema

