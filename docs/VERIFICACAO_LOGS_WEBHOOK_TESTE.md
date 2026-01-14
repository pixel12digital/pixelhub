# Verificação de Logs - Teste de Webhook

## Contexto

**Teste realizado:** 2026-01-14 21:35:40 (horário do gateway)  
**correlation_id:** `9858a507-cc4c-4632-8f92-462535eab504`  
**event_id (gateway):** `90c9089f-520b-4617-b72a-ce880c75739c`  
**message_id:** `gwtest-123`

**Status:**
- ✅ Gateway recebeu o webhook e retornou HTTP 200
- ✅ Gateway enfileirou o evento com sucesso
- ✅ Gateway entregou ao Hub (HTTP 200)
- ❌ Evento **NÃO** encontrado no banco do Hub

## Diagnóstico

### Eventos Encontrados no Banco

1. **Evento de 19:11:21** (UTC)
   - `event_id`: `96000e09-0a56-4d3d-b91e-ae1a2d75c6c3`
   - `correlation_id`: `9858a507-cc4c-4632-8f92-462535eab504` (mesmo do teste)
   - `message_id`: `gwtest-123`
   - `status`: `queued`
   - ⚠️ **Este é um evento anterior, não o do teste às 21:35**

2. **Evento de 18:35:31** (UTC)
   - `event_id`: `563837d4-18bb-4d0b-a515-051a7928d705`
   - `correlation_id`: `NULL`
   - `message_id`: `gwtest-123`
   - `status`: `queued`

### Conclusão

O evento do teste (21:35:40) **não foi salvo no banco**, apesar do gateway ter retornado HTTP 200 indicando entrega bem-sucedida.

## Scripts de Verificação

### 1. Verificação Local (PHP)

```bash
php check-hub-logs.php
```

Busca no arquivo de log local por:
- `correlation_id` do teste
- `HUB_WEBHOOK_IN` próximo ao horário
- `HUB_MSG_SAVE_OK` / `HUB_MSG_DROP`
- Erros/exceções

### 2. Verificação no Servidor (Linux/Bash)

```bash
# Opção 1: Script automatizado
bash check-hub-logs-server.sh

# Opção 2: Comando direto
docker logs --since 21:30 gateway-hub 2>&1 | \
  grep -i "9858a507-cc4c-4632-8f92-462535eab504\|HUB_WEBHOOK_IN\|HUB_MSG_SAVE\|HUB_MSG_DROP" | \
  tail -50
```

### 3. Verificação no Servidor (Windows/PowerShell)

```powershell
# Opção 1: Script automatizado
.\check-hub-logs-server.ps1

# Opção 2: Comando direto
docker logs --since 21:30 gateway-hub 2>&1 | Select-String -Pattern "9858a507-cc4c-4632-8f92-462535eab504|HUB_WEBHOOK_IN|HUB_MSG_SAVE|HUB_MSG_DROP" | Select-Object -Last 50
```

## O Que Procurar nos Logs

### 1. Entrada do Webhook (`HUB_WEBHOOK_IN`)

Deve aparecer algo como:
```
[HUB_WEBHOOK_IN] eventType=message channel_id=Pixel12 Digital tenant_id=NULL from=5599999999999 normalized_from=+5559999999999 message_id=gwtest-123 timestamp=... correlationId=9858a507-cc4c-4632-8f92-462535eab504 payload_hash=...
```

### 2. Processamento da Mensagem

- **`HUB_MSG_SAVE_OK`**: Evento salvo com sucesso
- **`HUB_MSG_DROP`**: Evento descartado (duplicado, inválido, etc.)

### 3. Possíveis Erros

- Exceções não capturadas
- Erros de validação
- Erros de banco de dados
- Timeout ou problemas de conexão

## Próximos Passos

1. **Executar verificação no servidor** usando os scripts acima
2. **Analisar os logs** para identificar:
   - Se o webhook chegou ao Hub (`HUB_WEBHOOK_IN`)
   - Se houve erro na ingestão
   - Se o evento foi descartado (`HUB_MSG_DROP`)
3. **Verificar timezone** - o horário pode estar em UTC no servidor
4. **Verificar se há múltiplos containers** do Hub rodando

## Comandos Úteis

### Verificar containers do Hub
```bash
docker ps -a | grep -i hub
```

### Ver logs em tempo real
```bash
docker logs -f gateway-hub
```

### Ver logs de um período específico
```bash
docker logs --since "2026-01-14T21:30:00" --until "2026-01-14T21:40:00" gateway-hub
```

### Verificar timezone do container
```bash
docker exec gateway-hub date
docker exec gateway-hub cat /etc/timezone
```

