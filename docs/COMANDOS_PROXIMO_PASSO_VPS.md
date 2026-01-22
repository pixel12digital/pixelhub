# Comandos: Próximo Passo - Diagnóstico WPPConnect

## Objetivo

Comparar `ImobSites` (funciona) vs `pixel12digital` (não funciona) e verificar emissão de `onMessage` no WPPConnect.

---

## Comandos para executar no VPS

### 1. Comparar onMessage entre ImobSites e pixel12digital no gateway-wrapper

```bash
docker logs gateway-wrapper --since 10m | grep -i "onmessage" | grep -iE "(ImobSites|pixel12digital)" | tail -30
```

**O que esperamos:**
- `ImobSites`: deve aparecer eventos `onMessage` recebidos do WPPConnect
- `pixel12digital`: não deve aparecer nenhum evento `onMessage`

---

### 2. Verificar se WPPConnect está emitindo onMessage para pixel12digital

```bash
docker logs wppconnect-server --since 10m | grep -i "pixel12digital" | grep -iE "(Emitting onMessage|onMessage|message)" | tail -30
```

**O que esperamos:**
- Se aparecer `Emitting onMessage`: WPPConnect está emitindo, mas não chega no gateway-wrapper
- Se não aparecer: WPPConnect não está emitindo eventos `onMessage` para `pixel12digital`

---

### 3. Comparar emissão de onMessage: ImobSites vs pixel12digital no WPPConnect

```bash
docker logs wppconnect-server --since 10m | grep -iE "Emitting onMessage|onMessage" | grep -iE "(ImobSites|pixel12digital)" | tail -30
```

**O que esperamos:**
- `ImobSites`: deve aparecer `Emitting onMessage` quando mensagens chegam
- `pixel12digital`: não deve aparecer `Emitting onMessage`

---

### 4. Verificar todos os eventos recebidos pelo gateway-wrapper (últimos 5 min)

```bash
docker logs gateway-wrapper --since 5m | grep -iE "(Received webhook event|onmessage|onpresencechanged|onack)" | grep -iE "(ImobSites|pixel12digital)" | tail -40
```

**O que esperamos:**
- `ImobSites`: deve aparecer `onMessage`, `onpresencechanged`, `onack`
- `pixel12digital`: deve aparecer apenas `onpresencechanged` e `onack`, **não** `onMessage`

---

### 5. Verificar configuração de webhook/listeners no WPPConnect (últimas 2 horas)

```bash
docker logs wppconnect-server --since 2h | grep -iE "(Registering onMessage|webhook|listener)" | grep -iE "(ImobSites|pixel12digital)" | tail -30
```

**O que esperamos:**
- Ambas as sessões devem ter `Registering onMessage event`
- Verificar se há diferenças na configuração de webhook

---

## Interpretação dos resultados

### Cenário 1: WPPConnect emite onMessage, mas gateway-wrapper não recebe

**Sintomas:**
- `docker logs wppconnect-server` mostra `Emitting onMessage` para `pixel12digital`
- `docker logs gateway-wrapper` não mostra `onMessage` recebido para `pixel12digital`

**Causa provável:** Problema na comunicação entre WPPConnect e gateway-wrapper (webhook interno, roteamento, filtro).

---

### Cenário 2: WPPConnect não emite onMessage para pixel12digital

**Sintomas:**
- `docker logs wppconnect-server` **não** mostra `Emitting onMessage` para `pixel12digital`
- `ImobSites` mostra `Emitting onMessage` normalmente

**Causa provável:** Configuração específica da sessão `pixel12digital` no WPPConnect (listener não funcionando, filtro, condição que bloqueia eventos).

---

### Cenário 3: Listener não registrado corretamente para pixel12digital

**Sintomas:**
- `docker logs wppconnect-server` não mostra `Registering onMessage event` para `pixel12digital`
- Ou mostra apenas uma vez (durante inicialização) mas não em reconexões

**Causa provável:** Listener não sendo registrado após autenticação/reconexão para `pixel12digital`.

---

## Após executar os comandos

**Compartilhe os resultados** para identificarmos:
1. Em qual ponto o `onMessage` está quebrando (WPPConnect vs gateway-wrapper)
2. Se há diferenças entre `ImobSites` e `pixel12digital`
3. Qual é a causa raiz (configuração, listener, roteamento)

