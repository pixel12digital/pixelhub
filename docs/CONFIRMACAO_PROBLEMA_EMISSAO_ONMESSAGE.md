# Confirmação: WPPConnect não emite onMessage para pixel12digital

**Data:** 2026-01-17  
**Teste realizado:** Mensagem de teste enviada para `pixel12digital` e logs verificados imediatamente

---

## 📊 Resultado do Teste em Tempo Real

### Comando executado:
```bash
docker logs wppconnect-server --since 1m | grep -i "pixel12digital" | grep -iE "(onmessage|onAnymessage|emitting)" | tail -20
```

### Resultado obtido:
```
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug:    [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
```

**O que encontramos:**
- ✅ `onPresenceChanged` sendo emitido (2 ocorrências)
- ❌ **Nenhum** `Emitting onMessage` encontrado
- ❌ **Nenhum** `Emitting onAnyMessage` encontrado

---

## 🔴 Confirmação do Problema

**Problema identificado:** O WPPConnect **NÃO está emitindo eventos `onMessage` ou `onAnyMessage`** para `pixel12digital`, mesmo quando mensagens são recebidas.

**Evidências:**
1. ✅ Listener `onMessage` registrado corretamente (`Registering onMessage event`)
2. ✅ Listener `onAnyMessage` registrado corretamente (`Registering onAnyMessage event`)
3. ✅ Sessão autenticada (`Authenticated`)
4. ✅ Eventos `onPresenceChanged` funcionando normalmente
5. ❌ **Mensagem de teste enviada, mas nenhum evento `onMessage` ou `onAnyMessage` foi emitido**

---

## 🎯 Causa Raiz

O problema está na **detecção/emissão de eventos de mensagem** pelo WPPConnect para a sessão `pixel12digital`:
- Listeners estão registrados corretamente
- Sessão está autenticada e conectada
- Outros eventos funcionam (`onPresenceChanged`, `onAck`)
- **Mas eventos de mensagem não são emitidos**

---

## 📝 Possíveis Causas

### 1. Listener não está funcionando apesar de registrado
**Sintoma:** Listener registrado mas não executando quando mensagens chegam.

**Possíveis causas:**
- Listener foi registrado mas perdido após reconexão
- Listener registrado em instância incorreta da sessão
- Bug no WPPConnect relacionado ao registro de listeners

---

### 2. Filtro ou condição bloqueando eventos de mensagem
**Sintoma:** Mensagens chegam mas eventos são filtrados/bloqueados antes de serem emitidos.

**Possíveis causas:**
- Configuração específica da sessão `pixel12digital` que bloqueia eventos
- Filtro no código do WPPConnect que impede emissão para `pixel12digital`
- Diferença de configuração entre `ImobSites` e `pixel12digital`

---

### 3. Problema de sincronização/conexão com WhatsApp Web
**Sintoma:** Sessão parece conectada mas não está recebendo eventos de mensagem.

**Possíveis causas:**
- Sessão não está totalmente sincronizada com WhatsApp Web
- Conexão com WhatsApp Web está incompleta (apenas status, não mensagens)
- Problema de cache/estado da sessão no WPPConnect

---

## 🔧 Soluções Recomendadas

### Solução 1: Reiniciar sessão `pixel12digital`
**Ação:** Desconectar e reconectar a sessão `pixel12digital` no WPPConnect para forçar re-registro de todos os listeners.

**Como fazer:**
1. Desconectar sessão `pixel12digital` no gateway UI
2. Aguardar alguns segundos
3. Reconectar sessão `pixel12digital`
4. Verificar se listeners são re-registrados
5. Testar envio de mensagem novamente

---

### Solução 2: Verificar configuração da sessão no WPPConnect
**Ação:** Comparar configuração completa da sessão `pixel12digital` com `ImobSites` para identificar diferenças.

**Como fazer:**
1. Verificar arquivos de configuração da sessão no WPPConnect
2. Comparar parâmetros de inicialização entre `ImobSites` e `pixel12digital`
3. Verificar se há filtros ou condições específicas para `pixel12digital`

---

### Solução 3: Verificar versão/configuração do WPPConnect
**Ação:** Verificar se há diferenças na versão ou configuração do WPPConnect que afetam emissão de eventos.

**Como fazer:**
1. Verificar versão do WPPConnect em uso
2. Verificar configurações globais do WPPConnect
3. Verificar se há atualizações disponíveis ou bugs conhecidos

---

## ✅ Status da Investigação

- [x] Listeners registrados corretamente
- [x] Sessão autenticada e conectada
- [x] Eventos `onPresenceChanged` funcionando
- [x] Teste em tempo real realizado (mensagem enviada)
- [x] **Problema confirmado:** WPPConnect não emite `onMessage`/`onAnyMessage` para `pixel12digital`
- [ ] **PRÓXIMO PASSO:** Reiniciar sessão `pixel12digital` e testar novamente

---

## 📋 Resumo para Compartilhar

**Problema confirmado:** WPPConnect não está emitindo eventos `onMessage` ou `onAnyMessage` para `pixel12digital`, mesmo quando mensagens são recebidas.

**Evidências:**
- Listener registrado: ✅
- Sessão autenticada: ✅
- Mensagem de teste enviada: ✅
- Evento `onMessage` emitido: ❌
- Evento `onAnyMessage` emitido: ❌

**Causa raiz:** Problema na detecção/emissão de eventos de mensagem pelo WPPConnect para `pixel12digital`.

**Solução recomendada:** Reiniciar sessão `pixel12digital` para forçar re-registro de listeners e testar novamente.

