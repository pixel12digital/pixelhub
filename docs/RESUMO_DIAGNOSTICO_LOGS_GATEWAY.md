# Resumo: Diagnóstico dos Logs do Gateway

**Data:** 2026-01-17 14:37  
**Status:** 🔴 **PROBLEMA IDENTIFICADO**

---

## 📥 O que analisamos

Executamos comandos no VPS para verificar logs do `gateway-wrapper` filtrados por `pixel12digital` (últimas 2 horas):
- Eventos recebidos do WPPConnect
- Configuração de sessão e webhook
- Comparação com `ImobSites` (sessão que funciona)

---

## ✅ O que está funcionando

1. **Webhook do painel:**
   - ✅ Status 200 OK
   - ✅ Latência ~400-1000ms (normal)
   - ✅ URL: `https://hub.pixel12digital.com.br/api/whatsapp/webhook`

2. **Sessão pixel12digital:**
   - ✅ Sessão criada/verificada no gateway-wrapper
   - ✅ Recebendo eventos `onpresencechanged` do WPPConnect
   - ✅ Convertendo corretamente para `connection.update`

---

## ❌ O que não está funcionando

**Problema crítico:** O WPPConnect **não está emitindo eventos `onMessage`** para `pixel12digital`.

**Evidências nos logs:**
- ✅ Aparece: `onpresencechanged` → `connection.update`
- ❌ Não aparece: `onMessage` / `onmessage`
- ❌ Não aparece: eventos de mensagem recebida

**Comparação esperada:**
- `ImobSites` (funciona): Recebe `onMessage` do WPPConnect → gateway-wrapper entrega webhook
- `pixel12digital` (não funciona): Recebe apenas `onpresencechanged`, **não recebe `onMessage`**

---

## 🔍 Próximos passos

1. **Comparar logs ImobSites vs pixel12digital:**
   ```bash
   docker logs gateway-wrapper --since 10m | grep -i "onmessage" | grep -iE "(ImobSites|pixel12digital)"
   ```

2. **Verificar se WPPConnect está emitindo onMessage:**
   ```bash
   docker logs wppconnect-server --since 10m | grep -i "pixel12digital.*Emitting onMessage"
   ```

3. **Se WPPConnect não emitir:**
   - Verificar configuração do listener `onMessage` no WPPConnect para `pixel12digital`
   - Verificar se há filtros ou condições bloqueando eventos
   - Comparar código/config entre `ImobSites` e `pixel12digital`

---

## 📊 Conclusão atual

**Hipótese principal:** O WPPConnect server não está emitindo eventos `onMessage` para a sessão `pixel12digital`, apesar de:
- Registrar o listener (`Registering onMessage event`)
- Estar autenticado (`Authenticated`)
- Receber outros eventos (`onpresencechanged`)

**Causa provável:** Configuração específica da sessão `pixel12digital` no WPPConnect (não é problema do gateway-wrapper ou do webhook do painel).

---

## 📝 Status da investigação

- [x] Executamos análise dos logs do gateway-wrapper
- [x] Validamos webhook do painel (funciona)
- [x] Identificamos problema: falta de `onMessage` do WPPConnect
- [ ] Precisamos comparar ImobSites vs pixel12digital nos logs
- [ ] Precisamos verificar emissão de `onMessage` no WPPConnect
- [ ] Precisamos identificar causa raiz no WPPConnect

