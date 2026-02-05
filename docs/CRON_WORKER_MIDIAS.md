# Cron — Worker de mídias WhatsApp

**Objetivo:** Processar mídias de eventos inbound em até ~2 minutos via fila assíncrona.

## Pré-requisito

Execute a migration antes de ativar o cron:

```bash
php database/migrate.php
```

Isso cria a tabela `media_process_queue`.

## Configuração no Hostmidia

Adicione ao crontab:

```cron
# Worker de mídias — a cada 1 minuto
* * * * * cd /caminho/para/pixelhub && php scripts/worker_processar_midias.php --limit=20 >> /var/log/pixelhub-worker-midias.log 2>&1
```

**Substitua** `/caminho/para/pixelhub` pelo caminho real do projeto.

## Parâmetros

| Parâmetro | Padrão | Descrição |
|-----------|--------|-----------|
| `--limit=20` | 20 | Máximo de jobs por execução |
| `--backoff=1` | 1 | Minutos entre tentativas de retry |

## Fluxo

1. Webhook recebe mensagem inbound com mídia → enfileira em `media_process_queue`
2. Worker roda a cada minuto → consome fila → baixa e salva mídia
3. Até 3 tentativas por job; 1 minuto entre tentativas
4. Falhas permanentes ficam em `status=failed` para análise

## Complemento

O script `reprocessar_midias_pendentes.php` continua útil como retry de longo prazo (eventos antigos sem mídia). Sugestão: rodar ambos.

- **Worker:** cron a cada 1 min — eventos novos
- **Reprocessar:** cron a cada 30 min — eventos antigos que falharam
