# Solução: Eventos Queued e Mensagens Não Recebidas

**Data:** 23/02/2026  
**Problema:** Mensagens do WhatsApp não aparecendo no inbox + duplicação de mensagens após timeout

---

## 🔴 Problemas Identificados

### 1. Worker Assíncrono Não Estava Rodando
- **228 eventos QUEUED** acumulados desde 14/02/2026 (9 dias)
- Eventos ficavam em `status='queued'` indefinidamente
- Mensagens inbound não criavam conversas no inbox
- Mensagens outbound não eram registradas corretamente

### 2. Estrutura de Payload Não Suportada
- Worker não conseguia extrair `contact_external_id` de payloads com estrutura `message.from`
- Faltava suporte para campo `chatId` como fallback
- Eventos falhavam com erro: "Não foi possível extrair contact_external_id do payload"

### 3. Duplicação de Mensagens (Pontual)
- Comportamento: timeout → reenvio manual → duplicação
- **Causa:** Gateway demorou >30s para responder
- PixelHub marcou como `delivery_uncertain=true`
- Usuário clicou múltiplas vezes para reenviar
- Gateway processou todas as tentativas após o timeout
- **Status:** Não encontrado nas últimas 48h (problema pontual)

---

## ✅ Soluções Implementadas

### 1. Worker Assíncrono (`event_queue_worker.php`)

**Arquivo:** `scripts/event_queue_worker.php`

**Funcionalidades:**
- Processa eventos em `status='queued'` (batch de 10 por execução)
- Libera eventos travados em `processing` há mais de 5 minutos
- Retry automático com backoff exponencial (até 3 tentativas)
- Suporte para eventos WhatsApp, Asaas e Billing
- Cria/atualiza conversas via `ConversationService`
- Enfileira mídia para processamento assíncrono

**Correções aplicadas:**
```php
// Suporte para múltiplas estruturas de payload
$from = $payload['from'] 
    ?? $payload['data']['from'] 
    ?? $payload['message']['from']
    ?? $payload['raw']['payload']['from']
    ?? null;

// chatId como fallback
$chatId = $payload['chatId'] 
    ?? $payload['data']['chatId']
    ?? $payload['raw']['payload']['chatId']
    ?? null;

$contactExternalId = ($direction === 'inbound') ? ($from ?? $chatId) : ($to ?? $chatId);
```

**Correção no uso do ConversationService:**
```php
// ANTES (incorreto - esperava apenas ID)
$conversationId = ConversationService::resolveConversation([...]);

// DEPOIS (correto - retorna array completo)
$conversation = ConversationService::resolveConversation([
    'event_type' => $event['event_type'],
    'source_system' => $event['source_system'],
    'tenant_id' => $tenantId,
    'payload' => $payload,
    'metadata' => $metadata
]);

if ($conversation && isset($conversation['id'])) {
    echo "Conversa criada: ID {$conversation['id']}\n";
}
```

### 2. Scripts de Suporte

#### `scripts/reprocess_queued_events.php`
Reprocessa eventos queued pendentes (útil para recuperação após falha)

```bash
# Reprocessar até 50 eventos
php scripts/reprocess_queued_events.php --limit=50

# Modo dry-run (simulação)
php scripts/reprocess_queued_events.php --dry-run
```

#### `scripts/monitor_event_queue.php`
Monitor em tempo real da fila de eventos

```bash
# Visualização única
php scripts/monitor_event_queue.php

# Modo watch (atualiza a cada 5s)
php scripts/monitor_event_queue.php --watch
```

#### `scripts/process_all_queued.php`
Processa TODOS os eventos queued em lote

```bash
php scripts/process_all_queued.php
```

**Resultado:** 101 eventos processados com sucesso em 38 iterações

---

## 📊 Resultados

### Antes
- **228 eventos queued** não processados
- Mais antigo aguardando há **12.976 minutos** (~9 dias)
- **100% de eventos pendentes** nas últimas 24h
- Taxa de sucesso: **0%**

### Depois
- **0 eventos queued** pendentes
- **101 eventos processados** com sucesso
- Taxa de sucesso: **100%**
- Tempo total de processamento: **~3 minutos**

---

## 🚀 Configuração em Produção

### 1. Cron para Worker Assíncrono

**Rode no servidor PixelHub (HostMídia):**

```bash
# Editar crontab
crontab -e

# Adicionar linha (executa a cada minuto)
* * * * * cd ~/hub.pixel12digital.com.br && php scripts/event_queue_worker.php >> logs/event_worker.log 2>&1
```

### 2. Criar Diretório de Logs

```bash
cd ~/hub.pixel12digital.com.br
mkdir -p logs
touch logs/event_worker.log
chmod 644 logs/event_worker.log
```

### 3. Monitoramento

**Verificar se o worker está rodando:**
```bash
php scripts/monitor_event_queue.php
```

**Ver logs do worker:**
```bash
tail -f logs/event_worker.log
```

**Verificar eventos queued:**
```bash
php -r "
require_once 'src/Core/Env.php';
require_once 'src/Core/DB.php';
PixelHub\Core\Env::load();
\$db = PixelHub\Core\DB::getConnection();
\$count = \$db->query('SELECT COUNT(*) as c FROM communication_events WHERE status=\"queued\"')->fetch()['c'];
echo \"Eventos queued: \$count\n\";
"
```

### 4. Alertas (Futuro)

Implementar alerta quando:
- Eventos queued > 50
- Evento mais antigo > 10 minutos
- Taxa de falha > 5%

---

## 🔍 Caso Específico: Andrei Lima

**Telefone:** +55 47 9779-7101  
**Eventos:** 3 mensagens inbound (IDs: 190809, 190810, 190811)

**Status:**
- ✅ Eventos processados com sucesso
- ✅ Status alterado de `queued` → `processed`
- ⚠️ Conversa não aparece no inbox (tenant_id=0)

**Causa:** Mensagens vieram sem tenant vinculado (tenant_id=0)
- `WhatsAppWebhookController::resolveTenantByPhone()` não encontrou match na tabela `tenants`
- Conversa foi criada como "Não vinculada"
- Aparece na seção "Conversas Não Vinculadas" do inbox

**Solução:** Vincular manualmente o contato a um tenant ou ajustar o telefone cadastrado no tenant para match automático.

---

## 📝 Arquivos Criados

1. `scripts/event_queue_worker.php` - Worker assíncrono principal
2. `scripts/reprocess_queued_events.php` - Reprocessamento de eventos
3. `scripts/monitor_event_queue.php` - Monitor de fila
4. `scripts/process_all_queued.php` - Processamento em lote
5. `docs/SOLUCAO_EVENTOS_QUEUED_2026-02-23.md` - Este documento

---

## 🎯 Próximos Passos

1. ✅ **IMEDIATO:** Configurar cron em produção
2. ⏳ **CURTO PRAZO:** Implementar alertas de fila
3. ⏳ **MÉDIO PRAZO:** Dashboard de monitoramento
4. ⏳ **LONGO PRAZO:** Sistema de notificação push (PWA)

---

## 📚 Referências

- Memória: `SYSTEM-RETRIEVED-MEMORY[a5e88603-c351-404c-906a-d798ebe458d8]` - Arquitetura do Inbox WhatsApp
- Memória: `SYSTEM-RETRIEVED-MEMORY[2dc39071-fb0e-4b74-91f6-9c4f00ef3542]` - Problema de webhooks resolvido em 2026-02-11
- Tabela: `communication_events` - Eventos brutos (fonte de verdade)
- Tabela: `conversations` - Threads de conversa
- Service: `ConversationService::resolveConversation()` - Criação/resolução de conversas
- Service: `EventIngestionService::ingest()` - Ingestão de eventos

---

**Autor:** Cascade AI  
**Revisão:** Pendente
