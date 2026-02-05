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
| `--delay=30` | 30 | Segundos antes da 1ª tentativa (evita race: gateway demora a disponibilizar mídia) |

## Fluxo

1. Webhook recebe mensagem inbound com mídia → enfileira em `media_process_queue`
2. Worker roda a cada minuto → **aguarda 30s** antes da 1ª tentativa (gateway/CDN pode demorar)
3. Consome fila → baixa e salva mídia
4. Se download falhar (stored_path vazio), **retry** em vez de marcar como done
5. Até 3 tentativas por job; 1 minuto entre tentativas
6. Falhas permanentes ficam em `status=failed` para análise

## Complemento

O script `reprocessar_midias_pendentes.php` continua útil como retry de longo prazo (eventos antigos sem mídia). Sugestão: rodar ambos.

- **Worker:** cron a cada 1 min — eventos novos
- **Reprocessar:** cron a cada 30 min — eventos antigos que falharam
