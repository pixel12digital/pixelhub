# Cron — Worker de Mensagens Agendadas

**Objetivo:** Enviar mensagens de follow-up agendadas automaticamente no horário correto.

## Configuração no Hostmidia

Adicione ao crontab:

```cron
# Worker de mensagens agendadas — a cada 5 minutos
*/5 * * * * cd /home/pixel12digital/hub.pixel12digital.com.br && php scripts/scheduled_messages_worker.php >> logs/scheduled-messages.log 2>&1
```

**Substitua** `/home/pixel12digital/hub.pixel12digital.com.br` pelo caminho real do projeto.

## Pré-requisitos

1. **Tabela exists**: Execute migration se necessário
```bash
php database/migrate.php
```

2. **Permissões**: Certifique-se que o diretório `logs/` existe e é gravável

## Fluxo

1. Worker roda a cada 5 minutos
2. Busca mensagens com `status = 'pending'` E `scheduled_at <= NOW()`
3. Para cada mensagem:
   - Busca telefone do lead/tenant
   - Envia via WhatsApp (CommunicationHubController)
   - Atualiza status para `sent` ou `failed`
4. Delay de 2 segundos entre envios

## Problema Conhecido (18/02/2026)

**Issue**: Mensagem da Viviane falhou porque `lead_id` estava NULL em `scheduled_messages`, mesmo tendo `opportunity_id`.

**Solução**: O worker foi corrigido para fazer fallback:
- Se `lead_id` estiver vazio, busca da `opportunity.lead_id`
- Se ainda não encontrar, marca como failed

## Logs

- **Sucesso**: `logs/scheduled-messages.log`
- **Erros**: Registrados na tabela `scheduled_messages.failed_reason`

## Teste Manual

```bash
# Testar worker
php scripts/scheduled_messages_worker.php

# Verificar mensagens pendentes
php -r "
require_once 'src/Core/DB.php';
PixelHub\Core\DB::getConnection()->query('SELECT id, scheduled_at, status FROM scheduled_messages WHERE status = \"pending\" ORDER BY scheduled_at')->fetchAll();
"
```

## Monitoramento

Verifique o log periodicamente:

```bash
tail -f logs/scheduled-messages.log
```

Status esperado:
- `[2026-02-18 HH:MM:SS] Scheduled Messages Worker - INICIANDO`
- `Encontradas X mensagem(ns) para enviar.`
- `✅ Enviada com sucesso!` ou `❌ Falha no envio`
