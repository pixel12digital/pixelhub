# Passo a Passo: Diagnóstico do channel_id "Pixel12 Digital"

## Ordem Exata de Execução

### 1. Rodar o Fix do Tenant 25

```bash
php database/fix-tenant-25-channel.php
```

**Validar no output:**
- ✅ Se foi **UPDATE** (esperado se já existe registro com tenant_id=25, provider=wpp_gateway)
- ✅ Se foi **INSERT** (se não existia registro)
- ✅ O "ANTES / DEPOIS" mostrando:
  - `channel_id` (ANTES) → `channel_id` (DEPOIS)
  - `session_id` (ANTES) → `session_id` (DEPOIS)
  - `is_enabled` deve estar = 1

**Exemplo de output esperado:**
```
⚠️  Vínculo existente encontrado:
   ID: 123
   channel_id (ANTES): outro_canal
   session_id (ANTES): outro_session
   is_enabled: SIM
   tenant_id: 25

⚠️  ATENÇÃO: O canal vinculado (outro_canal) não corresponde ao esperado (pixel12digital)
  Atualizando para o canal correto...

✓ Registro atualizado!
   channel_id (DEPOIS): pixel12digital
   session_id (DEPOIS): pixel12digital
```

### 2. Fazer 1 Tentativa de Envio

1. Abrir o hub de comunicação no navegador
2. Selecionar a conversa `whatsapp_2`
3. Enviar uma mensagem simples (ex: "teste")
4. Observar o Network tab do DevTools

**O que observar:**
- Status code (400 ou 200?)
- Response JSON (qual `channel_id` vem?)

### 3. Coletar Logs do Servidor

**Opção A: Usar o script auxiliar (recomendado)**
```bash
php database/collect-send-logs.php [caminho-do-log]
```

**Opção B: Manualmente**
```bash
# No servidor, procurar por:
grep -A 100 "SEND_HANDLER_STAMP=15a1023" /var/log/php/error.log | tail -50
```

**O que coletar (do mesmo POST que retornou 400):**

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
   [CommunicationHub::send] TRACE: tenant_id recebido = ...
   [CommunicationHub::send] TRACE: thread_id recebido = ...
   [CommunicationHub::send] TRACE: originalChannelIdFromPost = ...
   ```

3. **RESOLUÇÃO (se sucesso) OU RETURN_POINT (se erro):**
   ```
   [CommunicationHub::send] ===== RESOLUÇÃO CANAL SUCESSO =====
   OU
   [CommunicationHub::send] ===== RETURN_POINT=X (CHANNEL_NOT_FOUND) =====
   [CommunicationHub::send] RETURN_POINT=X: variável usada para channel_id no response = ...
   ```

## Interpretação dos Resultados

### Caso 1: Stamp NÃO aparece

**Significa:** O código não está sendo executado em produção.

**Causas comuns:**
- Rota `/communication-hub/send` está batendo em outro handler
- Deploy não refletiu no servidor
- OPcache segurando versão anterior

**Ações:**
1. Confirmar roteamento:
   ```bash
   grep -r "communication-hub/send" public/index.php
   ```
   Deve mostrar: `$router->post('/communication-hub/send', 'CommunicationHubController@send');`

2. Confirmar que o arquivo do `__FILE__` esperado é o mesmo deployado:
   ```bash
   # Verificar timestamp do arquivo
   ls -la src/Controllers/CommunicationHubController.php
   ```

3. Se OPcache está ativo:
   ```bash
   # Limpar OPcache (temporário)
   # Ou fazer touch no arquivo para forçar reload
   touch src/Controllers/CommunicationHubController.php
   # Reiniciar PHP-FPM/Apache
   ```

### Caso 2: Stamp aparece, RETURN_POINT dispara com channel_id correto nos logs

**Exemplo:**
- Log diz: `channel_id no response = pixel12digital`
- Network tab mostra: `"channel_id": "Pixel12 Digital"`

**Significa:** O payload está sendo mutado fora do método `send()`.

**Possíveis locais:**
- `BaseController::json()` ou método comum que imprime JSON
- Middleware que transforma responses
- Handler global de erro

**Ações:**
1. Instrumentar o método que imprime JSON:
   ```php
   // Em Controller::json() ou método equivalente
   error_log("[Controller::json] Payload ANTES do json_encode: " . json_encode($data));
   echo json_encode($data);
   ```

2. Buscar globalmente:
   ```bash
   grep -r "channel_id" src/Core/Controller.php
   grep -r "CHANNEL_NOT_FOUND" src/
   ```

### Caso 3: Stamp aparece e RETURN_POINT indica falha por vínculo/escopo

**Exemplo:**
- Log diz: `validateGatewaySessionId: Canal não encontrado para tenant 25`
- Ou: `Canal encontrado mas tenant_id = 121` (outro tenant)

**Significa:** O vínculo não foi aplicado corretamente ou a validação não consulta a tabela certa.

**Ações:**
1. Verificar se o script de fix realmente atualizou:
   ```sql
   SELECT id, tenant_id, channel_id, session_id, is_enabled 
   FROM tenant_message_channels 
   WHERE tenant_id = 25 AND provider = 'wpp_gateway';
   ```

2. Verificar se `validateGatewaySessionId()` consulta `tenant_message_channels`:
   ```bash
   grep -A 20 "validateGatewaySessionId" src/Controllers/CommunicationHubController.php | grep "FROM"
   ```
   Deve mostrar: `FROM tenant_message_channels`

3. Se a validação usa outra tabela (ex.: `message_channels`), ajustar para usar `tenant_message_channels` ou fazer JOIN.

## Resultado Esperado (Sinal Verde)

Após rodar o script e com o handler correto em execução:

1. ✅ O erro **não deve mais ser CHANNEL_NOT_FOUND** (ideal: 200 OK)
2. ✅ Mesmo se der erro por outra razão, o JSON **não pode mais trazer "Pixel12 Digital"** em `channel_id`
3. ✅ O `channel_id` deve ser slug técnico: `pixel12digital`

## O que Enviar para Análise

**Enviar apenas:**
1. Output do `fix-tenant-25-channel.php` (UPDATE/INSERT, ANTES/DEPOIS)
2. Trecho do log do servidor contendo:
   - Stamp + `__FILE__`
   - TRACE (raw/trim, tenant_id, thread_id)
   - RETURN_POINT (se houve erro)
   - Variável usada para `channel_id` no response
3. Response JSON do Network tab (se ainda vier "Pixel12 Digital")

Com essas informações, será possível identificar exatamente:
- Qual dos 3 casos acima é
- Onde está a origem do "Pixel12 Digital"
- Qual ajuste mínimo destrava o problema

