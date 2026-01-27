# Ação Imediata: Validação e Correção do channel_id

## ✅ Validações de Sintaxe Realizadas

1. **Método `validateGatewaySessionId()`**: ✅ Verificado - `error_log()` está ANTES do `return`, não dentro do array
2. **Todos os RETURN_POINT**: ✅ Verificado - `error_log()` está ANTES do `$this->json()`, não dentro do array
3. **Linter**: ✅ Nenhum erro encontrado

## 🔍 Próximas Ações Críticas

### 1. Validar que o código está rodando (STAMP)

**No log do servidor, procurar por:**
```
SEND_HANDLER_STAMP=15a1023
```

**Se NÃO aparecer:**
- Rota pode estar apontando para outro handler
- Deploy não refletiu no servidor
- OPcache segurando versão anterior

**Ação:**
```bash
# Limpar OPcache (se aplicável)
# Reiniciar PHP-FPM/Apache
# Verificar se o arquivo foi realmente atualizado no servidor
```

### 2. Executar Script de Fix do Tenant 25

**Comando:**
```bash
php database/fix-tenant-25-channel.php
```

**O script agora:**
- ✅ Detecta se já existe registro (tenant_id=25, provider=wpp_gateway)
- ✅ Se existir, faz **UPDATE** (não INSERT) para apontar para o canal correto
- ✅ Se não existir, faz INSERT
- ✅ Loga "ANTES" e "DEPOIS" do registro

**Verificar no output:**
- Se fez UPDATE ou INSERT
- Qual `channel_id` e `session_id` ficaram ativos para o tenant 25
- Se `is_enabled = 1`

### 3. Fazer 1 Tentativa de Envio e Coletar Logs

**No log do servidor, procurar por (na ordem):**

1. **STAMP:**
   ```
   SEND_HANDLER_STAMP=15a1023
   __FILE__: ...
   __LINE__: ...
   ```

2. **TRACE início:**
   ```
   TRACE channel_id INÍCIO
   TRACE: raw $_POST['channel_id'] = ...
   TRACE: trim($_POST['channel_id']) = ...
   TRACE: tenant_id recebido = ...
   TRACE: thread_id recebido = ...
   TRACE: originalChannelIdFromPost = ...
   ```

3. **RESOLUÇÃO (se sucesso):**
   ```
   RESOLUÇÃO CANAL SUCESSO
   RESOLUÇÃO: valor final de $channelId = ...
   RESOLUÇÃO: valor de $originalChannelIdFromPost = ...
   RESOLUÇÃO: channel.id = ...
   RESOLUÇÃO: channel.channel_id/slug = ...
   RESOLUÇÃO: channel.tenant_id = ...
   ```

4. **RETURN_POINT (se erro):**
   ```
   RETURN_POINT=A (ou B, C, D)
   RETURN_POINT=X: variável usada para channel_id no response = ...
   RETURN_POINT=X: origem da variável = ...
   ```

### 4. Buscar Origem do "Pixel12 Digital" no Response

**Se o response ainda vier com `"channel_id": "Pixel12 Digital"`:**

**Buscar no código:**
```bash
grep -r "Pixel12 Digital" src/
```

**Locais encontrados:**
- `src/Controllers/CommunicationHubController.php:710` - Query hardcoded (não é o problema)
- `src/Services/ProjectContractService.php` - Nome da empresa (não é o problema)
- Outros locais são apenas nomes da empresa, não channel_id

**Se o problema persistir após os logs:**
- Pode haver um handler global de exceção que transforma o channel_id
- Pode haver um "normalize" de resposta que troca channel_id por nome amigável
- Pode haver outro return point que não foi patchado

## 📋 Checklist de Validação

- [ ] Stamp `SEND_HANDLER_STAMP=15a1023` aparece no log do servidor?
- [ ] `__FILE__` no log corresponde ao arquivo esperado?
- [ ] Script `fix-tenant-25-channel.php` executado com sucesso?
- [ ] Script fez UPDATE ou INSERT?
- [ ] Qual `channel_id` e `session_id` ficaram ativos para tenant 25?
- [ ] Logs de TRACE mostram `originalChannelIdFromPost = pixel12digital`?
- [ ] Qual RETURN_POINT foi acionado (A, B, C ou D)?
- [ ] O `channel_id` no response ainda vem como "Pixel12 Digital"?

## 🎯 Resultado Esperado

Após executar o script e fazer 1 tentativa de envio, os logs devem mostrar:

1. **STAMP confirmando código certo**
2. **TRACE mostrando `originalChannelIdFromPost = pixel12digital`**
3. **RESOLUÇÃO mostrando canal encontrado OU RETURN_POINT mostrando qual variável está sendo usada**
4. **Response com `channel_id: "pixel12digital"` (não "Pixel12 Digital")**

## 📝 Enviar para Análise

**Enviar apenas este trecho do log do servidor:**
- Stamp + `__FILE__`
- TRACE início (raw/trim)
- RESOLUÇÃO (se sucesso) OU RETURN_POINT (se erro)
- Qual variável está sendo usada para `channel_id` no response

Com essas informações, será possível identificar exatamente:
- Onde está a origem do "Pixel12 Digital"
- Se o problema é vínculo de tenant ou handler errado/override
- Qual correção aplicar

