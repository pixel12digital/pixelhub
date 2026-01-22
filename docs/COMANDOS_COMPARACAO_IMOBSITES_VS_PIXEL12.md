# Comandos: Comparação ImobSites vs pixel12digital no WPPConnect

**Objetivo:** Comparar emissão de `onMessage` entre `ImobSites` (funciona) e `pixel12digital` (não funciona) para identificar diferenças na configuração/sessão.

---

## Comandos para executar no VPS

### BLOCO 1: Comparar emissão de onMessage (janela maior - 30 minutos)

```bash
# 1. Comparar emissão de onMessage (janela maior)
docker logs wppconnect-server --since 30m \
| egrep -i "(ImobSites|pixel12digital)" \
| egrep -i "(onmessage|emitting onmessage|registering onmessage|message)" \
| tail -n 250
```

**O que esperamos:**
- `ImobSites`: deve aparecer `Emitting onMessage` quando mensagens chegam
- `pixel12digital`: não deve aparecer `Emitting onMessage`

---

### BLOCO 2: Confirmar tipos de eventos (presence/ack vs message)

```bash
# 2. Confirmar que pixel12digital só tem presence/ack (e ver se ImobSites tem message)
docker logs wppconnect-server --since 30m \
| egrep -i "(ImobSites|pixel12digital)" \
| egrep -i "(onpresencechanged|onack|onmessage)" \
| tail -n 250
```

**O que esperamos:**
- `ImobSites`: deve aparecer `onpresencechanged`, `onack`, **e** `onmessage`
- `pixel12digital`: deve aparecer apenas `onpresencechanged` e `onack`, **não** `onmessage`

---

### BLOCO 3: Verificar autenticação e registro de listeners

```bash
# 3. Ver "Authenticated" + "listener registrado" por sessão (pra ver se tem diferença)
docker logs wppconnect-server --since 2h \
| egrep -i "(ImobSites|pixel12digital)" \
| egrep -i "(authenticated|registering|listener|hook|webhook|callback|error|fail)" \
| tail -n 300
```

**O que esperamos:**
- Ambas as sessões devem ter `Authenticated`
- Ambas as sessões devem ter `Registering onMessage event` ou similar
- Verificar se há diferenças na configuração de webhook/callback
- Verificar se há erros específicos para `pixel12digital`

---

## 🎯 Interpretação dos Resultados (Veredito Rápido)

### Cenário 1: ImobSites tem onMessage, pixel12digital não tem

**Sintomas:**
- `ImobSites` aparece com `onMessage` nos logs
- `pixel12digital` **não** aparece com `onMessage` nos logs

**Conclusão:** ✅ **100% confirmado** - Problema é configuração/sessão específica do `pixel12digital` no WPPConnect.

**Causa provável:**
- Listener não funcionando para `pixel12digital` (mesmo registrado)
- Filtro ou condição bloqueando eventos `onMessage` para `pixel12digital`
- Diferença na inicialização/configuração da sessão `pixel12digital`

---

### Cenário 2: Nenhuma sessão tem onMessage

**Sintomas:**
- `ImobSites` **não** aparece com `onMessage` nos logs
- `pixel12digital` **não** aparece com `onMessage` nos logs

**Conclusão:** ⚠️ **Filtro de log/pattern está errado** ou evento está com outro nome no log.

**Próximo passo:**
- Verificar nomes alternativos do evento (ex: `message`, `onAnyMessage`, `message.ack`, etc.)
- Ajustar filtro `egrep` para incluir outros padrões
- Verificar formato exato dos logs do WPPConnect

---

### Cenário 3: Ambas as sessões têm onMessage

**Sintomas:**
- `ImobSites` aparece com `onMessage` nos logs
- `pixel12digital` **também** aparece com `onMessage` nos logs

**Conclusão:** ⚠️ **Problema não está na emissão** de `onMessage` no WPPConnect.

**Próximo passo:**
- Verificar se eventos `onMessage` de `pixel12digital` estão chegando no gateway-wrapper
- Verificar se há problema no roteamento/filtro entre WPPConnect e gateway-wrapper
- Verificar se gateway-wrapper está processando eventos `onMessage` de `pixel12digital` corretamente

---

## 📝 Após executar os comandos

**Compartilhe os resultados** para identificarmos:
1. Se `ImobSites` tem `onMessage` e `pixel12digital` não (confirma problema de config/sessão)
2. Se há diferenças na autenticação/registro de listeners entre as sessões
3. Se há erros específicos para `pixel12digital` nos logs
4. Qual é a causa raiz (configuração, listener, filtro, erro)

