# Plano de Otimização de Performance - Communication Hub

**Data:** 29/01/2026  
**Versão base:** v1.1.0-stable-media (commit 9bc7a96)  
**Status:** PLANEJAMENTO

---

## Ponto de Restauração

```bash
# Em caso de problemas, restaurar para:
git fetch --tags
git checkout v1.1.0-stable-media
curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"
```

---

## Princípios de Implementação

1. **Uma mudança por vez** - Cada fase é independente e testável
2. **Backward compatible** - Manter APIs existentes funcionando
3. **Feature flags** - Novas funcionalidades desativadas por padrão até validação
4. **Testes antes de deploy** - Validar localmente antes de produção
5. **Rollback fácil** - Cada fase tem seu próprio commit para rollback granular

---

## FASE 1: Paginação de Mensagens (Backend)

**Risco:** BAIXO  
**Impacto:** ALTO (redução de 70-90% no tempo de carregamento)  
**Tempo estimado:** 2-3 horas

### Objetivo
Carregar apenas as últimas 50 mensagens inicialmente, com paginação para mensagens antigas.

### Arquivos a modificar
- `src/Controllers/CommunicationHubController.php`
  - Método `getWhatsAppMessagesFromConversation()` (linhas ~3013-3500)

### Implementação detalhada

#### 1.1 Adicionar parâmetros de paginação (SEM quebrar chamadas existentes)

```php
// ANTES (linha ~3013)
public function getWhatsAppMessagesFromConversation($threadId, $channelId = null)

// DEPOIS - parâmetros opcionais mantêm compatibilidade
public function getWhatsAppMessagesFromConversation(
    $threadId, 
    $channelId = null,
    $limit = 50,           // Novo: limite de mensagens
    $beforeId = null       // Novo: cursor para paginação
)
```

#### 1.2 Modificar query SQL

```php
// ANTES (linha ~3463)
LIMIT 500

// DEPOIS - paginação com cursor
$paginationClause = '';
if ($beforeId) {
    $paginationClause = "AND ce.id < :beforeId";
}
// ...
ORDER BY ce.id DESC
LIMIT :limit
```

#### 1.3 Retornar metadados de paginação

```php
// Adicionar ao retorno
return [
    'messages' => $messages,
    'pagination' => [
        'has_more' => count($messages) === $limit,
        'oldest_id' => $messages ? end($messages)['id'] : null,
        'newest_id' => $messages ? reset($messages)['id'] : null
    ]
];
```

### Validação da Fase 1
- [ ] Carregar conversa existente - deve mostrar últimas 50 mensagens
- [ ] Scroll para cima deve carregar mais mensagens (se implementado frontend)
- [ ] Novas mensagens continuam aparecendo em tempo real
- [ ] Não há erros no error_log
- [ ] Performance: tempo de carregamento < 2s (antes: 5-10s)

### Rollback da Fase 1
```bash
git revert HEAD  # Reverte último commit
# OU
git checkout v1.1.0-stable-media -- src/Controllers/CommunicationHubController.php
```

---

## FASE 2: Lazy Loading de Mensagens (Frontend)

**Risco:** BAIXO  
**Impacto:** ALTO (redução de 50-70% no tamanho do HTML)  
**Tempo estimado:** 2-3 horas  
**Dependência:** Fase 1

### Objetivo
Implementar infinite scroll para carregar mensagens antigas ao rolar para cima.

### Arquivos a modificar
- `views/communication_hub/thread.php` (linhas ~61-171, ~283-292)

### Implementação detalhada

#### 2.1 Adicionar container com scroll detection

```html
<!-- ANTES -->
<div class="messages-container">
    <?php foreach ($messages as $msg): ?>
        <!-- renderiza todas -->
    <?php endforeach; ?>
</div>

<!-- DEPOIS -->
<div class="messages-container" id="messagesContainer" data-oldest-id="<?= $pagination['oldest_id'] ?>">
    <div id="loadMoreTrigger" class="load-more-trigger" style="display: none;">
        <span class="spinner"></span> Carregando mensagens anteriores...
    </div>
    <?php foreach ($messages as $msg): ?>
        <!-- renderiza apenas últimas 50 -->
    <?php endforeach; ?>
</div>
```

#### 2.2 JavaScript para infinite scroll

```javascript
// Adicionar ao final do arquivo
(function() {
    const container = document.getElementById('messagesContainer');
    const trigger = document.getElementById('loadMoreTrigger');
    let isLoading = false;
    let hasMore = <?= json_encode($pagination['has_more']) ?>;
    
    if (!hasMore) return;
    trigger.style.display = 'block';
    
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isLoading && hasMore) {
            loadMoreMessages();
        }
    }, { threshold: 0.1 });
    
    observer.observe(trigger);
    
    async function loadMoreMessages() {
        isLoading = true;
        const oldestId = container.dataset.oldestId;
        
        try {
            const response = await fetch(
                `/api/communication-hub/messages?thread_id=<?= $threadId ?>&before_id=${oldestId}&limit=50`
            );
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                // Prepend mensagens antigas
                const html = data.messages.map(renderMessage).join('');
                trigger.insertAdjacentHTML('afterend', html);
                container.dataset.oldestId = data.pagination.oldest_id;
                hasMore = data.pagination.has_more;
            } else {
                hasMore = false;
            }
            
            if (!hasMore) {
                trigger.style.display = 'none';
                observer.disconnect();
            }
        } catch (error) {
            console.error('Erro ao carregar mensagens:', error);
        } finally {
            isLoading = false;
        }
    }
})();
```

### Validação da Fase 2
- [ ] Página carrega com últimas 50 mensagens
- [ ] Scroll para cima carrega mais mensagens automaticamente
- [ ] Mensagens antigas aparecem na ordem correta
- [ ] Indicador de "carregando" aparece durante fetch
- [ ] Não carrega mais quando chega ao início da conversa

### Rollback da Fase 2
```bash
git checkout v1.1.0-stable-media -- views/communication_hub/thread.php
```

---

## FASE 3: Otimização de Queries SQL

**Risco:** MÉDIO  
**Impacto:** ALTO (redução de 70-85% no número de queries)  
**Tempo estimado:** 3-4 horas

### Objetivo
Eliminar N+1 queries e otimizar consultas com JSON.

### Arquivos a modificar
- `src/Controllers/CommunicationHubController.php`
- Banco de dados (índices)

### Implementação detalhada

#### 3.1 Criar índices no banco de dados

```sql
-- Executar no banco de dados
-- ATENÇÃO: Executar em horário de baixo uso

-- Índice para busca por thread
CREATE INDEX idx_comm_events_thread_channel 
ON communication_events (thread_id, channel_id, created_at DESC);

-- Índice para busca por evento_id
CREATE INDEX idx_comm_media_event_id 
ON communication_media (event_id);

-- Índice funcional para JSON (MySQL 5.7+)
-- NOTA: Testar antes pois pode ser custoso
ALTER TABLE communication_events 
ADD COLUMN from_number VARCHAR(50) GENERATED ALWAYS AS (
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from'))
) STORED,
ADD INDEX idx_from_number (from_number);
```

#### 3.2 Eliminar N+1 na resolução de channel_id

```php
// ANTES: Query para cada thread sem channel_id
foreach ($threadsWithoutChannel as $thread) {
    $channelId = $this->resolveChannelId($thread['thread_id']);
}

// DEPOIS: Uma única query com JOIN
$sql = "
    SELECT DISTINCT t.thread_id, 
           COALESCE(t.channel_id, ce.channel_id, m.channel_id) as resolved_channel_id
    FROM threads t
    LEFT JOIN communication_events ce ON ce.thread_id = t.thread_id AND ce.channel_id IS NOT NULL
    LEFT JOIN metadata m ON m.thread_id = t.thread_id
    WHERE t.thread_id IN (:threadIds)
    GROUP BY t.thread_id
";
```

#### 3.3 Remover COUNT(*) desnecessário

```php
// ANTES (linha ~4530)
$countSql = "SELECT COUNT(*) as total FROM communication_events WHERE ...";
$total = $stmt->fetch()['total'];
if ($total > 0) {
    // busca eventos
}

// DEPOIS - usa EXISTS ou LIMIT 1
$sql = "SELECT 1 FROM communication_events WHERE ... LIMIT 1";
$hasNew = $stmt->fetch() !== false;
if ($hasNew) {
    // busca eventos
}
```

### Validação da Fase 3
- [ ] Medir tempo de query ANTES e DEPOIS
- [ ] Verificar que índices foram criados: `SHOW INDEX FROM communication_events`
- [ ] Testar carregamento de threads com muitas conversas
- [ ] Verificar error_log para erros de SQL

### Rollback da Fase 3
```bash
# Código
git checkout v1.1.0-stable-media -- src/Controllers/CommunicationHubController.php

# Índices (se necessário remover)
DROP INDEX idx_comm_events_thread_channel ON communication_events;
DROP INDEX idx_comm_media_event_id ON communication_media;
ALTER TABLE communication_events DROP COLUMN from_number;
```

---

## FASE 4: Otimização de Mídia

**Risco:** BAIXO  
**Impacto:** MÉDIO (redução de 60-80% no carregamento de mídia)  
**Tempo estimado:** 2-3 horas

### Objetivo
Implementar thumbnails para imagens e lazy loading adequado.

### Arquivos a modificar
- `src/Services/WhatsAppMediaService.php`
- `views/communication_hub/thread.php`

### Implementação detalhada

#### 4.1 Gerar thumbnails ao salvar imagens

```php
// Em WhatsAppMediaService.php, após salvar imagem original
private static function generateThumbnail($originalPath, $maxWidth = 300): ?string
{
    $info = getimagesize($originalPath);
    if (!$info) return null;
    
    list($width, $height) = $info;
    if ($width <= $maxWidth) return null; // Não precisa thumbnail
    
    $ratio = $maxWidth / $width;
    $newWidth = $maxWidth;
    $newHeight = (int)($height * $ratio);
    
    $thumbPath = str_replace('.', '_thumb.', $originalPath);
    
    // Criar thumbnail usando GD
    $source = imagecreatefromstring(file_get_contents($originalPath));
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    $ext = pathinfo($originalPath, PATHINFO_EXTENSION);
    if ($ext === 'png') {
        imagepng($thumb, $thumbPath, 8);
    } else {
        imagejpeg($thumb, $thumbPath, 80);
    }
    
    imagedestroy($source);
    imagedestroy($thumb);
    
    return $thumbPath;
}
```

#### 4.2 Usar thumbnails na view

```html
<!-- ANTES -->
<img src="<?= $media['url'] ?>" loading="lazy">

<!-- DEPOIS -->
<img 
    src="<?= $media['thumbnail_url'] ?? $media['url'] ?>" 
    data-full-src="<?= $media['url'] ?>"
    loading="lazy"
    onclick="openFullImage(this)"
>
```

#### 4.3 Lazy loading em áudios e vídeos

```html
<!-- Áudio: não carregar até clicar no play -->
<audio preload="none" data-src="<?= $media['url'] ?>">
    <source data-src="<?= $media['url'] ?>" type="<?= $media['mime_type'] ?>">
</audio>

<!-- Vídeo: poster com thumbnail -->
<video 
    preload="none" 
    poster="<?= $media['thumbnail_url'] ?>"
    data-src="<?= $media['url'] ?>"
>
</video>
```

### Validação da Fase 4
- [ ] Imagens grandes mostram thumbnail (< 50KB)
- [ ] Clicar na imagem abre versão completa
- [ ] Áudios não carregam até clicar play
- [ ] Vídeos mostram poster antes de carregar

### Rollback da Fase 4
```bash
git checkout v1.1.0-stable-media -- src/Services/WhatsAppMediaService.php views/communication_hub/thread.php
```

---

## FASE 5: Otimização de Polling

**Risco:** BAIXO  
**Impacto:** MÉDIO (redução de 50-70% em requisições)  
**Tempo estimado:** 1-2 horas

### Objetivo
Implementar exponential backoff no polling quando não há atividade.

### Arquivos a modificar
- `views/communication_hub/thread.php` (JavaScript)

### Implementação detalhada

```javascript
// ANTES
const HUB_POLLING_MS = 10000; // Fixo 10s

// DEPOIS - Exponential backoff
class AdaptivePolling {
    constructor(minInterval = 5000, maxInterval = 60000) {
        this.minInterval = minInterval;
        this.maxInterval = maxInterval;
        this.currentInterval = minInterval;
        this.consecutiveEmpty = 0;
    }
    
    onNewMessages() {
        // Reset para intervalo mínimo quando há atividade
        this.consecutiveEmpty = 0;
        this.currentInterval = this.minInterval;
    }
    
    onNoNewMessages() {
        // Aumenta intervalo gradualmente
        this.consecutiveEmpty++;
        if (this.consecutiveEmpty > 3) {
            this.currentInterval = Math.min(
                this.currentInterval * 1.5,
                this.maxInterval
            );
        }
    }
    
    getInterval() {
        return this.currentInterval;
    }
}

const polling = new AdaptivePolling(5000, 60000);

// No loop de polling
setInterval(() => {
    checkNewMessages().then(hasNew => {
        if (hasNew) {
            polling.onNewMessages();
        } else {
            polling.onNoNewMessages();
        }
    });
}, polling.getInterval());
```

### Validação da Fase 5
- [ ] Polling começa em 5s
- [ ] Sem atividade, aumenta para 10s, 15s, 30s, 60s
- [ ] Nova mensagem reseta para 5s
- [ ] Console mostra intervalo atual (debug)

### Rollback da Fase 5
```bash
git checkout v1.1.0-stable-media -- views/communication_hub/thread.php
```

---

## Ordem de Implementação Recomendada

```
FASE 1 (Backend paginação)
    ↓
  [TESTE]
    ↓
FASE 2 (Frontend lazy load) 
    ↓
  [TESTE]
    ↓
FASE 3 (SQL otimização) ←── Pode ser paralelo com Fase 4/5
    ↓
  [TESTE]
    ↓
FASE 4 (Mídia thumbnails)
    ↓
  [TESTE]
    ↓
FASE 5 (Polling otimizado)
    ↓
  [TESTE FINAL]
    ↓
  [DEPLOY]
```

---

## Checklist de Validação Final

Após todas as fases:

### Funcionalidades (não devem quebrar)
- [ ] Carregar lista de conversas
- [ ] Abrir conversa específica
- [ ] Enviar mensagem de texto
- [ ] Receber mensagem de texto em tempo real
- [ ] Enviar áudio
- [ ] Receber áudio e reproduzir
- [ ] Enviar imagem
- [ ] Receber imagem e visualizar
- [ ] Enviar vídeo
- [ ] Receber vídeo e reproduzir
- [ ] Buscar conversas
- [ ] Filtrar por status
- [ ] Nova conversa

### Performance (devem melhorar)
- [ ] Tempo de carregamento inicial < 2s (meta)
- [ ] Tamanho do HTML < 500KB (meta)
- [ ] Queries SQL < 20 por página (meta)
- [ ] Polling não excede 1 req/min quando inativo

---

## Monitoramento Pós-Deploy

```php
// Adicionar logging de performance temporário
$startTime = microtime(true);
// ... código ...
$elapsed = microtime(true) - $startTime;
if ($elapsed > 2.0) {
    error_log("[PERF] Slow operation: {$elapsed}s - " . __METHOD__);
}
```

---

## Contato para Problemas

Em caso de problemas após deploy:
1. Verificar error_log
2. Restaurar: `git checkout v1.1.0-stable-media`
3. Limpar cache: `curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"`
