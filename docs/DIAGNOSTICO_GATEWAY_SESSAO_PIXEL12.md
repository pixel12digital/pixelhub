# Diagnóstico: Gateway - Sessão pixel12digital não recebe onMessage

## ✅ Conclusão do ChatGPT (Validação)

**Problema identificado:** Configuração específica da sessão `pixel12digital`, não é problema de banco/código geral.

**Evidências:**
- ✅ `ImobSites` recebe `onMessage` normalmente no gateway-wrapper
- ❌ `pixel12digital` não recebe `onMessage` no gateway-wrapper
- ✅ Webhook do painel funciona (teste manual passou)
- ✅ WPPConnect registra listeners `onMessage` para `pixel12digital`
- ❌ WPPConnect não emite eventos `onMessage` para `pixel12digital`

**Hipótese principal:** Discrepância no nome/ID da sessão (`pixel12digital` vs `Pixel12 Digital`) ou configuração de webhook/filtro específica para essa sessão.

---

## 🎯 Comandos para Diagnóstico no VPS

### 1. Verificar nome exato da sessão

```bash
# Verificar como a sessão está cadastrada no gateway-wrapper
docker logs gateway-wrapper --since 30m | grep -iE "(pixel12|Pixel12)" | grep -iE "(session|webhook|config)" | tail -30

# Verificar todas as ocorrências do nome da sessão
docker logs gateway-wrapper --since 1h | grep -iE "pixel12.*Digital|Pixel12.*Digital" | tail -20

# Comparar: ImobSites vs pixel12digital (como aparecem nos logs)
docker logs gateway-wrapper --since 1h | grep -iE "(ImobSites|pixel12digital)" | grep -i "onmessage" | tail -20
```

### 2. Logs maiores (10 min) para capturar eventos

```bash
# WPPConnect: buscar qualquer evento relacionado a pixel12digital
docker logs wppconnect-server --since 10m | grep -i "pixel12digital" | tail -30

# Gateway-wrapper: buscar onMessage/onmessage para pixel12digital
docker logs gateway-wrapper --since 10m | grep -iE "pixel12digital.*onmessage|onmessage.*pixel12digital" | tail -20

# Comparar: ImobSites (funciona) vs pixel12digital (não funciona)
docker logs gateway-wrapper --since 10m | grep -i "onmessage" | grep -iE "(ImobSites|pixel12digital)" | tail -30
```

### 3. Comparar configuração ImobSites vs pixel12digital

```bash
# Ver como ImobSites está configurado (funciona)
docker logs gateway-wrapper --since 1h | grep -i "ImobSites" | grep -iE "(webhook|config|registering|onmessage)" | tail -20

# Ver como pixel12digital está configurado (não funciona)
docker logs gateway-wrapper --since 1h | grep -i "pixel12digital" | grep -iE "(webhook|config|registering|onmessage)" | tail -20

# Buscar diferenças na inicialização das sessões
docker logs gateway-wrapper --since 2h | grep -iE "(Session.*created|webhook.*configured|Registering.*event)" | grep -iE "(ImobSites|pixel12digital)" | tail -30
```

---

## 🔍 O que procurar nos logs

1. **Nome da sessão:**
   - `pixel12digital` (sem espaços, minúsculas)
   - `Pixel12 Digital` (com espaços, maiúsculas)
   - `pixel12digital_121` (com sufixo)

2. **Eventos onMessage:**
   - `ImobSites`: Deve aparecer `Emitting onMessage` e `onMessage` no gateway-wrapper
   - `pixel12digital`: Não aparece `onMessage` no gateway-wrapper

3. **Configuração de webhook:**
   - Verificar se há URL de webhook diferente entre as sessões
   - Verificar se há filtros específicos para `pixel12digital`

---

## 📝 Próximos passos (após diagnóstico)

**Se nome da sessão estiver inconsistente:**
- Padronizar: usar sempre `pixel12digital` (sem espaços, minúsculas)
- Atualizar configuração no gateway-wrapper
- Reiniciar sessão

**Se webhook não estiver configurado para pixel12digital:**
- Configurar webhook para `pixel12digital` igual ao `ImobSites`
- Verificar URL de webhook no código/ambiente

**Se houver filtro bloqueando eventos:**
- Remover filtro ou ajustar para incluir `pixel12digital`

---

## ✅ Status Atual

- [x] Webhook do painel testado e funcionando
- [x] WPPConnect registra listeners `onMessage`
- [x] ImobSites funciona normalmente
- [ ] `pixel12digital` não recebe `onMessage` no gateway-wrapper
- [ ] **AGUARDANDO:** Diagnóstico de logs no VPS para identificar causa raiz

