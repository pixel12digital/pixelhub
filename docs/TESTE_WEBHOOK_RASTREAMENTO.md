# Teste de Webhook com Rastreamento por payload_hash

## Objetivo

Disparar um webhook de teste controlado e rastrear toda a jornada da mensagem através dos logs usando o `payload_hash`.

---

## PASSO 1 — Monitorar Logs (Hub)

### Windows (PowerShell)

**Opção 1: Script PowerShell (Recomendado)**
```powershell
.\monitor-logs.ps1
```

**Opção 2: Comando Manual**
```powershell
# Monitora logs do projeto
Get-Content logs\pixelhub.log -Wait -Tail 0 | Select-String -Pattern "HUB_WEBHOOK_IN|HUB_MSG_SAVE_OK|HUB_MSG_DROP|HUB_CONV_MATCH|HUB_MSG_DIRECTION|HUB_CHANNEL_ID|HUB_PHONE_NORM|INCOMING_MSG"

# Ou monitora log do Apache (se error_log() vai para lá)
Get-Content C:\xampp\apache\logs\error.log -Wait -Tail 0 | Select-String -Pattern "HUB_"
```

**Opção 3: Usando tail (se tiver Git Bash ou WSL)**
```bash
tail -f logs/pixelhub.log | grep -i "HUB_WEBHOOK_IN\|HUB_MSG_SAVE_OK\|HUB_MSG_DROP\|HUB_CONV_MATCH\|HUB_MSG_DIRECTION\|HUB_CHANNEL_ID\|HUB_PHONE_NORM\|INCOMING_MSG"
```

### Linux/Mac

```bash
# Se for app com logs em arquivo
tail -f logs/pixelhub.log | egrep -i "HUB_WEBHOOK_IN|HUB_MSG_SAVE_OK|HUB_MSG_DROP|HUB_CONV_MATCH|HUB_MSG_DIRECTION|HUB_CHANNEL_ID|HUB_PHONE_NORM|INCOMING_MSG"

# Ou se error_log() vai para syslog
tail -f /var/log/apache2/error.log | egrep -i "HUB_"
```

### Docker

```bash
docker logs -f --tail=200 <container_do_hub> | egrep -i "HUB_WEBHOOK_IN|HUB_MSG_SAVE_OK|HUB_MSG_DROP|HUB_CONV_MATCH|HUB_MSG_DIRECTION|HUB_CHANNEL_ID|HUB_PHONE_NORM|INCOMING_MSG"
```

---

## PASSO 2 — Disparar Webhook de Teste

### Opção 1: Script PHP (Recomendado)

```bash
# Gera payload_hash aleatório
php test-webhook.php

# Ou usa payload_hash específico para rastrear
php test-webhook.php a1b2c3d4
```

### Opção 2: cURL Manual

```bash
curl -X POST http://localhost/painel.pixel12digital/public/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: SEU_SECRET_AQUI" \
  -d '{
    "event": "message",
    "from": "554796164699@c.us",
    "message": {
      "id": "3EB0123456789ABCDEF",
      "from": "554796164699@c.us",
      "body": "Mensagem de teste",
      "timestamp": 1705234567
    },
    "session": {
      "id": "whatsapp_35"
    },
    "channel": "whatsapp_35"
  }'
```

### Opção 3: Postman/Insomnia

1. **Método:** POST
2. **URL:** `http://localhost/painel.pixel12digital/public/api/whatsapp/webhook`
3. **Headers:**
   - `Content-Type: application/json`
   - `X-Webhook-Secret: SEU_SECRET_AQUI` (se configurado)
4. **Body (JSON):**
```json
{
  "event": "message",
  "from": "554796164699@c.us",
  "message": {
    "id": "3EB0123456789ABCDEF",
    "from": "554796164699@c.us",
    "body": "Mensagem de teste",
    "timestamp": 1705234567,
    "notifyName": "Teste Webhook"
  },
  "session": {
    "id": "whatsapp_35"
  },
  "channel": "whatsapp_35",
  "timestamp": 1705234567
}
```

---

## PASSO 3 — Rastrear pelo payload_hash

### Calcular payload_hash do payload enviado

O `payload_hash` é calculado como: `substr(md5(json_encode($payload)), 0, 8)`

**Exemplo:**
```php
$payload = ['event' => 'message', 'from' => '554796164699@c.us', ...];
$payloadHash = substr(md5(json_encode($payload)), 0, 8);
echo $payloadHash; // Ex: a1b2c3d4
```

### Filtrar logs por payload_hash

**PowerShell:**
```powershell
Get-Content logs\pixelhub.log | Select-String "payload_hash=a1b2c3d4"
```

**Linux/Mac:**
```bash
grep "payload_hash=a1b2c3d4" logs/pixelhub.log
```

**Ou filtrar todos os logs relacionados:**
```bash
grep -E "payload_hash=a1b2c3d4|message_id=3EB0123456789ABCDEF" logs/pixelhub.log
```

---

## Sequência Esperada de Logs

Quando você disparar o webhook, deve ver esta sequência nos logs:

1. **`[HUB_WEBHOOK_IN]`** - Entrada do webhook
   - Deve conter: `payload_hash=XXXX`, `from=554796164699@c.us`, `channel_id=whatsapp_35`

2. **`[HUB_PHONE_NORM]`** - Normalização do telefone
   - Deve mostrar: `raw_from=554796164699@c.us normalized_from=554796164699`

3. **`[HUB_CHANNEL_ID]`** - Identificação do canal
   - Deve mostrar: `channel_id encontrado: whatsapp_35`

4. **`[HUB_MSG_DIRECTION]`** - Direção da mensagem
   - Deve mostrar: `computed=received source=webhook`

5. **`[HUB_CONV_MATCH]`** - Match de conversa
   - Pode mostrar: `FOUND_CONVERSATION` ou `CREATED_CONVERSATION`

6. **`[HUB_MSG_SAVE]`** - Tentativa de persistência
   - Deve mostrar: `INSERT_ATTEMPT` com event_id e message_id

7. **`[HUB_MSG_SAVE_OK]`** - Persistência bem-sucedida
   - Deve mostrar: `id_pk=XXX conversation_id=XXX created_at=...`

8. **`[INCOMING_MSG]`** - Atualização da UI (se aplicável)
   - Deve mostrar: `action=append` ou `action=listOnly`

---

## Verificações

### ✅ Checklist de Sucesso

- [ ] `[HUB_WEBHOOK_IN]` aparece com payload_hash correto
- [ ] `[HUB_PHONE_NORM]` normaliza o telefone corretamente
- [ ] `[HUB_CHANNEL_ID]` encontra o channel_id
- [ ] `[HUB_MSG_DIRECTION]` marca como `received`
- [ ] `[HUB_CONV_MATCH]` encontra ou cria conversa
- [ ] `[HUB_MSG_SAVE_OK]` persiste a mensagem
- [ ] Nenhum `[HUB_MSG_DROP]` aparece (mensagem não foi descartada)

### ❌ Problemas Comuns

**Problema:** `[HUB_CHANNEL_ID] MISSING_CHANNEL_ID`
- **Causa:** Payload não contém channel_id
- **Solução:** Verificar se payload tem `channel`, `channelId` ou `session.id`

**Problema:** `[HUB_MSG_DROP] DROP_DUPLICATE`
- **Causa:** Mensagem já foi processada (mesma idempotency_key)
- **Solução:** Usar message_id diferente no payload de teste

**Problema:** `[HUB_CONV_MATCH] ERROR: Falha ao criar nova conversa`
- **Causa:** Erro no banco de dados ou tabela não existe
- **Solução:** Verificar se tabela `conversations` existe e está acessível

**Problema:** `[HUB_MSG_SAVE] INSERT_FAILED`
- **Causa:** Erro ao inserir no banco
- **Solução:** Verificar logs de erro SQL e estrutura da tabela `communication_events`

---

## Exemplo Completo

```bash
# Terminal 1: Monitora logs
.\monitor-logs.ps1

# Terminal 2: Dispara webhook
php test-webhook.php a1b2c3d4

# Terminal 3: Filtra logs específicos
Get-Content logs\pixelhub.log | Select-String "payload_hash=a1b2c3d4"
```

**Saída esperada no Terminal 1:**
```
[HUB_WEBHOOK_IN] eventType=message channel_id=whatsapp_35 tenant_id=2 from=554796164699@c.us normalized_from=554796164699 message_id=3EB0123456789ABCDEF timestamp=1705234567 correlationId=NULL payload_hash=a1b2c3d4
[HUB_PHONE_NORM] raw_from=554796164699@c.us normalized_from=554796164699 normalized_thread_id_candidate=554796164699
[HUB_CHANNEL_ID] channel_id encontrado: whatsapp_35
[HUB_MSG_DIRECTION] computed=received source=webhook event_type=whatsapp.inbound.message
[HUB_CONV_MATCH] Query: findByKey conversation_key=whatsapp_2_554796164699 channel_type=whatsapp contact=554796164699 tenant_id=2
[HUB_CONV_MATCH] FOUND_CONVERSATION id=123 conversation_key=whatsapp_2_554796164699
[HUB_MSG_SAVE] INSERT_ATTEMPT event_id=uuid-123 message_id=3EB0123456789ABCDEF event_type=whatsapp.inbound.message tenant_id=2 channel_id=whatsapp_35 direction=received
[HUB_MSG_SAVE_OK] event_id=uuid-123 id_pk=789 message_id=3EB0123456789ABCDEF conversation_id=123 channel_id=whatsapp_35 created_at=2026-01-14 15:30:45 direction=received
```

---

## Troubleshooting

### Logs não aparecem

1. **Verificar se arquivo de log existe:**
   ```powershell
   Test-Path logs\pixelhub.log
   ```

2. **Verificar permissões:**
   ```powershell
   # Criar diretório se não existir
   New-Item -ItemType Directory -Path logs -Force
   ```

3. **Verificar se error_log() está funcionando:**
   ```php
   error_log("TESTE: Log funcionando");
   ```

### Webhook retorna erro

1. **Verificar URL:**
   - Deve ser: `http://localhost/painel.pixel12digital/public/api/whatsapp/webhook`

2. **Verificar secret (se configurado):**
   - Deve estar em `.env`: `PIXELHUB_WHATSAPP_WEBHOOK_SECRET`

3. **Verificar rota:**
   - Verificar se rota está registrada em `public/index.php` ou router

---

**Última atualização:** 2026-01-14

