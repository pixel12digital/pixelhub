# Plano Estratégico — Confiabilidade do Inbox WhatsApp

**Objetivo:** Eliminar perda de mensagens e mídias para que o Inbox seja confiável sem verificação manual no WhatsApp.

**Escopo:** Implementações locais (PixelHub/Hostmidia). Sem alterações na VPS.

---

## Fase 1 — Ganhos rápidos (1–2 dias) ✅ IMPLEMENTADO

### 1.1 Retry de mídia mais frequente
| Item | Descrição |
|------|-----------|
| **O quê** | Rodar `reprocessar_midias_pendentes.php` a cada 15 ou 30 minutos em vez de 1x/dia |
| **Onde** | Cron no Hostmidia |
| **Esforço** | Muito baixo |
| **Critério de aceite** | Cron configurado; mídias pendentes processadas em até 30 min |

**Cron sugerido:**
```cron
# A cada 30 min
*/30 * * * * cd /caminho/do/pixelhub && php scripts/reprocessar_midias_pendentes.php --days=3 --limit=100
```

---

### 1.2 Persistir payload bruto do webhook ✅ IMPLEMENTADO
| Item | Descrição |
|------|-----------|
| **O quê** | Antes de qualquer lógica, gravar o payload completo em `webhook_raw_logs` |
| **Por quê** | Rastrear o que chegou; permitir reprocessamento manual se a ingestão falhar |
| **Onde** | `WhatsAppWebhookController::handle()` — primeira linha após ler `php://input` |
| **Esforço** | Baixo |
| **Tabela** | `webhook_raw_logs` (id, received_at, event_type, payload_hash, payload_json, processed, created_at) |

**Critério de aceite:** Todo webhook recebido gera registro; possível reprocessar manualmente a partir do payload salvo.

---

## Fase 2 — Fila de jobs para mídia (3–5 dias) ✅ IMPLEMENTADO

### 2.1 Decisão de infraestrutura
| Opção | Prós | Contras |
|-------|------|---------|
| **A) Tabela no MySQL** | Sem nova dependência; já existe MySQL | Worker em cron; menos eficiente que Redis |
| **B) Redis + Bull/Similar** | Mais robusto; retry nativo | Exige Redis no Hostmidia |
| **C) Fila em arquivo (SQLite)** | Simples; sem novo serviço | Menos escalável |

**Recomendação:** Opção A (tabela MySQL) para começar — menor risco e sem nova infra.

---

### 2.2 Fluxo com fila
```
Webhook chega → Persiste evento em communication_events → Enfileira job "processar_mídia" → Responde 200
Worker (cron a cada 1 min) → Consome fila → Chama WhatsAppMediaService::processMediaFromEvent()
```

| Componente | Descrição |
|------------|-----------|
| **Tabela** | `media_process_queue` (id, event_id, status, attempts, last_attempt_at, created_at) |
| **Inserção** | Após `EventIngestionService::ingest()` para eventos inbound com mídia |
| **Worker** | Script `scripts/worker_processar_midias.php` que roda a cada 1 min via cron |
| **Retry** | Até 3 tentativas com backoff; depois marca como failed |

**Critério de aceite:** Mídia de eventos inbound processada em até 2 min; falhas registradas para análise.

**Implementação:** `MediaProcessQueueService`, `scripts/worker_processar_midias.php`, `docs/CRON_WORKER_MIDIAS.md`

---

## Fase 3 — Auditoria e monitoramento (2–3 dias)

### 3.1 Dashboard ou relatório de saúde
| Item | Descrição |
|------|-----------|
| **O quê** | Página ou script que mostra: eventos sem mídia (últimos 7 dias), webhooks com erro, fila pendente |
| **Onde** | Nova rota `/diagnostic/inbox-health` ou script `scripts/diagnostico-inbox-saude.php` |
| **Métricas** | Contagem de eventos inbound; eventos sem communication_media; jobs em fila; jobs failed |

**Critério de aceite:** Visibilidade clara de "quantas mídias pendentes" e "quantos jobs falharam".

---

### 3.2 Log estruturado para perda de mensagens
| Item | Descrição |
|------|-----------|
| **O quê** | Quando `HUB_MSG_DROP` (dedupe) ou ingestão falhar, logar com hash do conteúdo para rastreamento |
| **Onde** | `EventIngestionService::ingest()` |
| **Exemplo** | `[HUB_MSG_DROP] idempotency_key=X message_id=Y content_hash=abc123` |

**Critério de aceite:** Possível correlacionar "mensagem não apareceu" com logs.

---

## Fase 4 — Refinamentos (opcional)

### 4.1 Processar mídia antes de responder 200 (alternativa à fila)
- **Risco:** Timeout se download demorar
- **Quando considerar:** Se fila não for viável e a maioria das mídias for pequena

### 4.2 Alertas
- E-mail ou notificação quando fila de mídia > 50 ou jobs failed > 10 nas últimas 24h

---

## Resumo de fases e prioridade

| Fase | Itens | Prioridade | Esforço |
|------|-------|------------|---------|
| **1** | Retry frequente + payload bruto | Alta | 1–2 dias |
| **2** | Fila de jobs para mídia | Alta | 3–5 dias |
| **3** | Dashboard/auditoria + logs | Média | 2–3 dias |
| **4** | Refinamentos/alertas | Baixa | sob demanda |

---

## Ordem sugerida de implementação

1. **Semana 1:** Fase 1.1 (cron) + Fase 1.2 (payload bruto)
2. **Semana 2:** Fase 2 (fila de jobs)
3. **Semana 3:** Fase 3 (dashboard e logs)

---

## Critérios de sucesso

- [ ] Nenhuma mensagem de texto perdida (payload bruto registra tudo)
- [ ] Mídias processadas em até 5 min (fila + worker)
- [ ] Placeholder visível quando mídia falhar após retries
- [ ] Visibilidade de pendências e falhas via dashboard/script
