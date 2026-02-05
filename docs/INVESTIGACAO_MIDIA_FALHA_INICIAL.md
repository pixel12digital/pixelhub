# Investigação: Mídia falha na 1ª tentativa mas funciona no retry

## Sintoma

Áudios PTT apareciam como "[Mídia]" no Inbox em vez de tocar. O reprocessamento manual (`processar-evento-midia.php`) funcionava.

## Causa raiz identificada

1. **Worker marcava como done mesmo quando o download falhava**  
   O `processMediaFromEvent()` retorna um array com `stored_path` vazio em caso de falha (não lança exceção). O worker tratava qualquer retorno não-exception como sucesso e marcava o job como `done`, impedindo retries.

2. **Possível race condition com o gateway**  
   O WPP Connect / WhatsApp CDN pode demorar alguns segundos para disponibilizar a mídia. Tentativas imediatas (segundos após o webhook) podem falhar; tentativas posteriores (30s–2min depois) tendem a funcionar.

## Correções implementadas

### 1. Worker: só marca como done quando a mídia foi realmente baixada

**Arquivo:** `scripts/worker_processar_midias.php`

- Se o evento tem mídia (ptt/audio/image/video/document/sticker) e o resultado tem `stored_path` vazio → **retry** (até 3 tentativas) ou marca como `failed` se esgotou.
- Se não tem mídia ou o download deu certo → marca como `done` normalmente.

### 2. Atraso de 30s antes da 1ª tentativa

**Arquivo:** `src/Services/MediaProcessQueueService.php`

- Novo parâmetro `minSecondsBeforeFirstAttempt` (padrão 30) em `fetchPending()`.
- Jobs só entram na fila de processamento após `created_at + 30 segundos`, dando tempo para o gateway disponibilizar a mídia.

### 3. Parâmetro `--delay` no worker

- Permite ajustar o atraso da 1ª tentativa: `--delay=30` (padrão), `--delay=0` para desativar.

## Fluxo atualizado

1. Webhook recebe evento com mídia → enfileira em `media_process_queue`.
2. Worker roda a cada minuto.
3. **Primeira tentativa:** só após 30s do `created_at` do job.
4. Se o download falhar → `resetToPending` (retry) ou `markFailed` após 3 tentativas.
5. Entre tentativas: 1 minuto (backoff).
6. `reprocessar_midias_pendentes.php` continua como retry de longo prazo (eventos antigos sem mídia).

## Critério de aceite

- Novos áudios/imagens inbound devem aparecer como reproduzíveis no Inbox sem necessidade de reprocessamento manual.
- Em caso de falha transitória no gateway, o worker deve fazer retry automático.
- Jobs que falharem definitivamente ficam em `status=failed` para análise.
