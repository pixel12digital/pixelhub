# Resultado: Comparação ImobSites vs pixel12digital

**Data:** 2026-01-17  
**Comandos executados:** 3 blocos de comparação entre `ImobSites` e `pixel12digital`

---

## 📊 Resultados Obtidos

### BLOCO 1: Comparar emissão de onMessage (30 minutos)
**Resultado:** ❌ **Nenhum evento `onMessage` encontrado para nenhuma das sessões**

**Eventos encontrados:**
- Nenhum `onMessage` / `Emitting onMessage` para `ImobSites`
- Nenhum `onMessage` / `Emitting onMessage` para `pixel12digital`

**Conclusão:** Ambas as sessões **não estão emitindo** eventos `onMessage` nos últimos 30 minutos.

---

### BLOCO 2: Confirmar tipos de eventos (presence/ack vs message)
**Resultado:** ⚠️ **Apenas `onPresenceChanged` e `onAck`, nenhum `onMessage`**

**Eventos encontrados:**

**ImobSites:**
- ✅ `Emitting onPresenceChanged event` (3 ocorrências)
- ❌ Nenhum `onAck`
- ❌ Nenhum `onMessage`

**pixel12digital:**
- ✅ `Emitting onPresenceChanged event` (6 ocorrências)
- ✅ `Emitting onAck event` (3 ocorrências)
- ❌ Nenhum `onMessage`

**Conclusão:** Nenhuma das duas sessões está emitindo eventos `onMessage` nos últimos 30 minutos.

---

### BLOCO 3: Verificar autenticação e registro de listeners (2 horas)
**Resultado:** ✅ **Listeners registrados corretamente, sem erros**

**Eventos encontrados para `pixel12digital`:**

1. **Autenticação:**
   - ✅ `Authenticated` (4 ocorrências - 4 reconexões nas últimas 2 horas)

2. **Listeners registrados:**
   - ✅ `Registering onStateChange event` (4 ocorrências)
   - ✅ `Registering onMessage event` (4 ocorrências)
   - ✅ `Registering onAnyMessage event` (4 ocorrências) ⚠️ **IMPORTANTE**
   - ✅ `Registering onIncomingCall event` (4 ocorrências)
   - ✅ `Registering onAck event` (4 ocorrências)
   - ✅ `Registering onPresenceChanged event` (4 ocorrências)

3. **Erros:**
   - ❌ Nenhum erro ou falha encontrado nos logs

**Observação importante:**
- `pixel12digital` está registrando **`onAnyMessage`** além de `onMessage`
- `onAnyMessage` captura **todos os tipos de mensagens** (incluindo grupos, status, etc.)

---

## 🔍 Análise dos Resultados

### O que está funcionando:
1. ✅ Ambas as sessões têm listeners `onMessage` registrados corretamente
2. ✅ `pixel12digital` está autenticada e registrando todos os listeners necessários
3. ✅ Eventos `onPresenceChanged` e `onAck` estão sendo emitidos
4. ✅ Não há erros nos logs do WPPConnect

### O que não está funcionando:
1. ❌ **Nenhuma das duas sessões** está emitindo eventos `onMessage` nos últimos 30 minutos
2. ❌ Isso pode significar:
   - Não houve mensagens recebidas no período (mais provável)
   - Mensagens estão sendo capturadas por `onAnyMessage` ao invés de `onMessage` (precisa verificar)

---

## 🎯 Descoberta Importante

**`pixel12digital` está registrando `onAnyMessage`!**

Isso pode ser a chave do problema:
- `onAnyMessage` captura **todas as mensagens** (incluindo grupos, status, mensagens enviadas, etc.)
- `onMessage` captura apenas **mensagens de conversas individuais recebidas**
- Se as mensagens estão sendo capturadas por `onAnyMessage`, o gateway-wrapper pode não estar processando corretamente

---

## 📝 Próximos Passos

### 1. Verificar se `onAnyMessage` está sendo emitido

```bash
# Verificar emissão de onAnyMessage para pixel12digital
docker logs wppconnect-server --since 30m | grep -i "pixel12digital" | grep -iE "(onAnymessage|emitting onAnymessage)" | tail -30
```

**O que esperamos:**
- Se aparecer `Emitting onAnyMessage`: mensagens estão sendo capturadas por `onAnyMessage` (precisa ajustar gateway-wrapper para processar)
- Se não aparecer: não houve mensagens no período ou há outro problema

---

### 2. Verificar se `ImobSites` também registra `onAnyMessage`

```bash
# Verificar se ImobSites registra onAnyMessage
docker logs wppconnect-server --since 2h | grep -i "ImobSites" | grep -iE "(registering|onAnymessage|onmessage)" | tail -50
```

**O que esperamos:**
- Se `ImobSites` **não** registra `onAnyMessage`: pode ser diferença de configuração
- Se `ImobSites` **também** registra `onAnyMessage`: ambas estão configuradas igual

---

### 3. Verificar logs do gateway-wrapper para `onAnyMessage`

```bash
# Verificar se gateway-wrapper recebe onAnyMessage
docker logs gateway-wrapper --since 30m | grep -i "pixel12digital" | grep -iE "(onAnymessage|onmessage)" | tail -30
```

**O que esperamos:**
- Se aparecer `onAnyMessage`: gateway-wrapper está recebendo, mas pode não estar processando corretamente
- Se não aparecer: WPPConnect não está emitindo `onAnyMessage` ou não está chegando no gateway-wrapper

---

## ✅ Veredito Atual

**Situação:** Nenhuma das duas sessões (`ImobSites` e `pixel12digital`) está emitindo eventos `onMessage` nos últimos 30 minutos.

**Hipóteses:**
1. **Não houve mensagens recebidas** no período (mais provável)
2. **Mensagens estão sendo capturadas por `onAnyMessage`** ao invés de `onMessage` (precisa verificar)
3. **Padrão de busca não está capturando** (menos provável, pois outros eventos aparecem)

**Próximo passo:** Verificar se `onAnyMessage` está sendo emitido e processado corretamente.

