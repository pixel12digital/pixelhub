# Cron — Retry de mídias pendentes

**Objetivo:** Processar mídias que falharam no download em até 30 minutos (em vez de 1x/dia).

## Configuração no Hostmidia

Adicione ao crontab do servidor onde o PixelHub roda:

```cron
# Retry de mídias WhatsApp — a cada 30 minutos
*/30 * * * * cd /caminho/para/pixelhub && php scripts/reprocessar_midias_pendentes.php --days=3 --limit=100 >> /var/log/pixelhub-retry-midias.log 2>&1
```

**Substitua:**
- `/caminho/para/pixelhub` pelo caminho real do projeto (ex: `/home/hostmidia/public_html/painel.pixel12digital`)

## Parâmetros

| Parâmetro | Padrão | Descrição |
|-----------|--------|-----------|
| `--days=3` | 7 | Últimos N dias para buscar eventos sem mídia |
| `--limit=100` | 50 | Máximo de eventos por execução |

## Verificar

```bash
# Rodar manualmente
php scripts/reprocessar_midias_pendentes.php --days=3 --limit=20

# Ver log (se configurou redirecionamento)
tail -f /var/log/pixelhub-retry-midias.log
```
