# Relatório de Diagnóstico: Carregamento Lento e Loading Infinito (Status = Ignoradas)

**Data:** 2026-01-28  
**Escopo:** Análise técnica sem implementação. Objetivo: identificar causas do loading prolongado e da aba “girando” ao filtrar por **Status = Ignoradas** (e risco futuro com **Ativas** em alto volume).

---

## 1. Requests ao abrir Ignoradas / Não vinculadas

### 1.1 Requests que rodam no carregamento

| # | Request | Quando | Observação |
|---|---------|--------|------------|
| 1 | **GET** `/communication-hub?channel=whatsapp&session_id=&tenant_id=0&status=ignored` | Carregamento inicial (navegação ou F5) | **Request principal**. Resposta HTML só termina quando o PHP termina todo o `index()`. Enquanto isso, a aba fica em “carregando”. |
| 2 | **GET** `/communication-hub/check-updates?after_timestamp=...&status=ignored` | ~5s após DOMContentLoaded, depois em intervalos (15s sem conversa ativa, 5s com conversa ativa) | Polling da lista. Não bloqueia “fim do carregamento” da página. |
| 3 | **GET** `/communication-hub/conversations-list?...` | Apenas quando há **atualizações** detectadas pelo check-updates (e há conversa ativa) ou quando `updateConversationListOnly()` é chamado | Atualização AJAX da lista. Não é chamado no load inicial. |

No **carregamento inicial** só há **um** request HTTP que importa para a demora e para a aba girando: o **GET do próprio `/communication-hub`**. Todo o tempo de resposta é o tempo do PHP (backend).

### 1.2 Request “pendurado” (pending)?

- **Sim, na prática.** O request que fica “pendente” é o **GET `/communication-hub`**. O navegador mostra a aba carregando até receber a resposta completa desse request. Se o backend demorar 10s, a aba fica 10s girando. Não é um request que fica “pending” por rede; é um request **lento** no servidor.

### 1.3 Loop de polling criando requests em sequência?

- **Há polling, mas não é a causa do loading infinito.**  
  - `startListPolling()` agenda o primeiro `checkForListUpdates()` com **setTimeout** (5s).  
  - Depois usa **setTimeout** (não setInterval direto) para reagendar o próximo check, com intervalo base de **15s** (sem conversa ativa) ou **5s** (com conversa ativa), com backoff até 2 min.  
  - Ou seja: há um “loop” de polling, mas ele **começa depois** do carregamento da página. O “loading infinito” que você vê é o **tempo até a primeira resposta do GET `/communication-hub`**, não o polling.

### 1.4 Promise/evento impedindo o “fim” do carregamento?

- **Não.** O “fim” do carregamento da página (evento `load`) ocorre quando o documento do **GET `/communication-hub`** termina de ser recebido. Nada no front (promise, evento) segura isso. O que segura é **só o backend demorar** para enviar a resposta.

**Conclusão (frontend):** A lentidão e a sensação de “loading infinito” vêm do **tempo de resposta do GET `/communication-hub`** (backend). Polling e demais requests não impedem o “fim” do load; só começam depois.

---

## 2. Backend / Query

### 2.1 Listagem traz dados demais (sem paginação/limite)?

- **Há limite, mas fixo e único.**  
  - `getWhatsAppThreadsFromConversations()` usa **`LIMIT 100`** na query principal de `conversations`.  
  - Ou seja: sempre são trazidas **até 100** conversas (para o status/canal/sessão selecionados). Não há paginação nem “cursor”; é sempre a mesma janela (as 100 “mais recentes” por `last_message_at`/`created_at`).  
  - Para **Ignoradas**, se houver centenas de conversas com `status = 'ignored'`, a query ainda só retorna 100, mas o custo pode ser alto se a **seleção** dessas 100 for cara (índices, filtros, ver seção 2.3).

### 2.2 Existe N+1?

- **Não na listagem principal.**  
  - A lista de conversas vem de **uma** query principal (`conversations` + LEFT JOIN `tenants` + LEFT JOIN `users`).  
  - Resolução de @lid: feita em **lote** (`ContactHelper::resolveLidPhonesBatch()`), tipicamente 1–2 queries adicionais, não uma por conversa.  
  - Resolução de `channel_id` ausente: `resolveMissingChannelIds()` é chamada **uma vez** por página, mas internamente faz **várias** queries (até 4–5) quando há muitas conversas com `channel_id` NULL:  
    - 1x `SHOW TABLES LIKE 'communication_events'`  
    - 1x SELECT em `communication_events` (por `conversation_id` no metadata)  
    - Opcional: 1x SELECT em `communication_events` por `contact_external_id` (JSON_EXTRACT)  
    - 1x SELECT em `conversations` (DISTINCT contact_external_id, channel_id)  
    - 1x SELECT em `tenant_message_channels`  
  - Ou seja: não é N+1 “clássico” (1 query por linha), mas há **múltiplas queries auxiliares** por request, que podem ser pesadas em cenários **Ignoradas** com muitos registros com `channel_id` NULL.  
  - **Chat:** `getChatThreads()` usa uma **subquery correlacionada** por thread: `(SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id = ct.id)`. Isso equivale a N acessos (um por thread), até 50 threads. Pode ser custoso se `chat_messages` for grande.

### 2.3 Filtros/joins caros e índices que podem faltar

**Query principal (conversations):**

```sql
SELECT c.*, t.name, t.phone, u.name as assigned_to_name
FROM conversations c
LEFT JOIN tenants t ON c.tenant_id = t.id
LEFT JOIN users u ON c.assigned_to = u.id
WHERE c.channel_type = 'whatsapp' AND c.status = 'ignored'  -- (+ tenant_id, channel_id se filtrados)
ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.created_at DESC
LIMIT 100
```

**Índices existentes (migrations):**  
`idx_channel_type`, `idx_status`, `idx_tenant`, `idx_last_message_at`, `idx_created_at`, `idx_channel_id`.

**Pontos caros e índices recomendados (só apontar, sem aplicar):**

| Ponto | Observação | Índice sugerido |
|-------|------------|------------------|
| Filtro `channel_type` + `status` | Hoje dois índices separados; o otimizador pode fazer merge ou full scan. | **Composto:** `(channel_type, status)` ou `(channel_type, status, last_message_at)` para cobrir WHERE + ORDER. |
| Ordenação | `ORDER BY COALESCE(c.last_message_at, c.created_at) DESC` | Índice que já suporte o filtro + ordenação evita filesort. Ex.: `(channel_type, status, last_message_at DESC)`. |
| `resolveMissingChannelIds` | Queries em `communication_events` com `JSON_EXTRACT(ce.metadata, '$.conversation_id')` e `JSON_EXTRACT(ce.payload, '$.from')` | Não há índice em JSON; são full scans na tabela de eventos. Índice em `(event_type, created_at)` ajuda a limitar o conjunto; índice em coluna gerada a partir de `metadata->>'$.conversation_id'` (se o SGBD suportar) ajudaria. |
| Contagem de incoming leads | `COUNT(*)` em `conversations` com filtros channel_type, is_incoming_lead, status | Índice composto que inclua `is_incoming_lead` e `status` (ex.: `(channel_type, is_incoming_lead, status)`) pode ajudar. |

**Resumo:** O custo tende a vir da **combinação** de: (1) falta de índice composto ideal para `channel_type + status + ordenação`; (2) trabalho em `communication_events` (JSON) em `resolveMissingChannelIds`; (3) subquery correlacionada em `getChatThreads`.

---

## 3. Riscos quando “Ativas” crescer muito

- **Mesmo limite de 100:** Com muitas ativas, continuam sendo listadas só as 100 “mais recentes”. O custo da query pode subir com o tamanho da tabela se o plano usar scan ou merge de índices.  
- **Ordenação e filtro:** Sem índice composto adequado, o volume de linhas em `conversations` (ativas + ignoradas + arquivadas) aumenta e o **filesort** ou o uso de índices piora.  
- **Resolução de @lid e channel_id:** Quanto mais conversas com @lid ou `channel_id` NULL nas 100 retornadas, mais trabalho em `resolveLidPhonesBatch` e `resolveMissingChannelIds` (tocando em `communication_events` e outras tabelas).  
- **Estatísticas no mesmo request:** As métricas (pendentes para responder, novas hoje, mais antigo pendente) são calculadas **em PHP** sobre o mesmo array de até 100 threads já carregados. Não há queries extras para isso, mas o array pode crescer em complexidade (mais campos, mais processamento).  
- **Polling:** Com muitos usuários e abas abertas, o número de chamadas a `check-updates` e eventualmente `conversations-list` cresce; o backend precisa responder a essas chamadas de forma leve (o `check-updates` hoje é uma query agregada simples, que tende a escalar melhor que a listagem completa).

---

## 4. Estratégia recomendada (alto nível) para escalar

**Objetivos:** performance, consistência, não travar a UI.

1. **Paginação / “cursor” no backend**  
   - Não retornar “sempre as últimas 100” em uma única resposta.  
   - Oferecer um endpoint de listagem que aceite `limit` (ex.: 20 ou 30) e `cursor` (ex.: último `last_message_at` ou `id` da última conversa).  
   - Ordenação feita no SQL com índice adequado; evitar ordenar grandes conjuntos em PHP.

2. **Infinite scroll ou “carregar mais” no front**  
   - Primeira carga: só os primeiros N itens (ex.: 20).  
   - Ao rolar (ou botão “Carregar mais”), pedir a próxima “página” com o cursor.  
   - Assim o primeiro paint é rápido e a aba deixa de girar por muito tempo.

3. **“Carregar só o necessário para o card”**  
   - Na primeira página, trazer apenas os campos usados nos cards da lista (id, contact_name, last_message_at, unread_count, tenant_name, etc.).  
   - Evitar trazer payloads ou dados pesados até o usuário abrir a conversa (thread).

4. **Pré-cálculos / cache de última atividade**  
   - A coluna `last_message_at` (e eventualmente `updated_at`) já é a “última atividade”. Manter atualizada no write path (webhook/ingest) e usar só isso na listagem, sem recalcular no read.  
   - Estatísticas (pendentes, novas hoje, mais antigo pendente) podem ser:  
     - Calculadas em background (job) e guardadas em tabela/cache, ou  
     - Servidas por um endpoint dedicado, com cache curto (ex.: 30s), para não pesar no mesmo request da listagem.

5. **Evitar sort em memória**  
   - Ordenação deve ser no SQL (`ORDER BY ... LIMIT n`) com índice que suporte o filtro + ordenação.  
   - Não fazer `usort()` em PHP com centenas de itens quando isso puder ser feito no banco com cursor.

6. **Limites e tempo máximo**  
   - Manter limite máximo por request (ex.: 50).  
   - Opcional: timeout no backend para o endpoint de listagem (ex.: 3s); se estourar, retornar parcial ou 503 e deixar o front mostrar “carregar mais” ou nova tentativa.

7. **Consistência**  
   - Cursor baseado em `(last_message_at, id)` para evitar pular/duplicar itens quando novas mensagens chegam entre uma página e outra.  
   - Polling (check-updates) continua leve; ao detectar atualização, o front pode atualizar só a primeira “página” ou invalidar e recarregar só a parte visível.

8. **Não travar a UI**  
   - Primeira resposta rápida (poucos itens).  
   - Restante dos dados (e estatísticas) por demanda ou em segundo plano.  
   - Loading states por seção (lista vs. stats), não um único loading geral que segura toda a página.

---

## 5. Auditoria da tela de conversas (vinculadas / listagem)

### 5.1 Contagens e estatísticas

- **Incoming leads:** 1 query `COUNT(*)` em `conversations` com filtros (channel_type, is_incoming_lead, status).  
- **Pendentes / Novas hoje / Mais antigo pendente:** calculados **em PHP** sobre o array `$normalThreads` (já carregado para a lista). Não há queries extras para esses três; porém, esse array é o mesmo das 100 conversas. Ou seja: as estatísticas refletem só essas 100, não o universo total (ex.: “pendentes para responder” é só entre as 100 listadas).

### 5.2 Dados redundantes

- A mesma listagem é usada para: (1) exibir a lista, (2) calcular as 3 estatísticas. Não há duplicação de request, mas há **duplicação de lógica**: se no futuro as estatísticas precisarem do total real (não só das 100), será preciso queries adicionais ou cache.  
- `getConversationsList()` (AJAX) **replica** a lógica do `index()`: chama `getWhatsAppThreads()` e `getChatThreads()`, mescla, ordena, separa leads. Ou seja, quando o polling dispara “atualizar lista”, o backend refaz um caminho pesado similar ao do load inicial.  
- **Filtro de sessão:** No `index()`, `session_id` é lido do GET e passado para `getWhatsAppThreads()`. Em `getConversationsList()` **não** é lido `session_id` do GET; portanto, ao atualizar a lista via AJAX, o filtro de sessão pode não ser aplicado (bug de consistência).

### 5.3 Chamadas repetidas

- **Polling:** `check-updates` é chamado a cada 15s (ou 5s com conversa ativa). É uma query leve (MAX de timestamp).  
- **conversations-list:** Só é chamado quando `check-updates` retorna `has_updates: true` e há conversa ativa, ou em outros fluxos que chamem `updateConversationListOnly()`. Ou seja, não há loop contínuo de conversations-list; mas quando é chamado, é um request pesado (mesma carga que o load inicial da listagem).

### 5.4 Trabalho desnecessário

- **Logs temporários:** Vários `error_log('[LOG TEMPORARIO] ...')` no fluxo de listagem e em `getConversationsList()`. Em produção, isso gera I/O e pode impactar um pouco em requests muito frequentes.  
- **Ordenação dupla:** Backend ordena por `last_activity`; o front faz `sort()` de novo no array antes de renderizar. Redundante; um único sort (no backend) seria suficiente.  
- **getChatThreads:** Faz `SHOW TABLES LIKE 'chat_threads'` em todo request. Poderia ser cacheado (uma vez por request ou por processo).

---

## 6. Entrega: resumo executivo

### 6.1 Prováveis causas (top 3)

1. **Tempo de resposta do GET `/communication-hub` (backend)**  
   - Todo o HTML só é enviado depois que o PHP termina `index()`.  
   - Inclui: 1 query principal (até 100 conversas) + getWhatsAppSessions (cache ou 1–2 queries) + getChatThreads (1 query + subquery por thread) + tenants + count incoming leads + resolução em lote de @lid + resolveMissingChannelIds (até 4–5 queries quando há muitos `channel_id` NULL).  
   - Para **status=ignored**, pode haver muitas conversas com `channel_id` NULL (ex.: não vinculadas), acionando bastante lógica em `resolveMissingChannelIds` e queries em `communication_events` com JSON.

2. **Falta de índice composto ideal**  
   - Filtro `channel_type + status` e ordenação por `last_message_at`/`created_at` podem gerar filesort ou uso subótimo de índices quando a tabela `conversations` cresce.

3. **Custo de `resolveMissingChannelIds` e subquery em getChatThreads**  
   - Muitas conversas ignoradas sem `channel_id` → várias queries em `communication_events` (incluindo JSON_EXTRACT).  
   - Subquery correlacionada em `getChatThreads` (COUNT por thread) pode ser pesada se houver muitos threads/mensagens.

### 6.2 Como confirmar (medir)

- **Network (DevTools):**  
  - Abrir aba Network, filtrar por “communication-hub”.  
  - Recarregar com `status=ignored`.  
  - Ver o tempo do **GET** `/communication-hub` (Time / Duration). Esse valor é o tempo de backend + rede; na prática, dominado pelo backend.

- **Logs (servidor):**  
  - Medir tempo entre início e fim do `index()` (log no início do método e antes de `$this->view(...)`).  
  - Ou usar ferramenta de profiling (Xdebug, Tideways, etc.) no request GET `/communication-hub?status=ignored`.

- **SQL:**  
  - Habilitar `general_log` ou usar `EXPLAIN` na query principal de `conversations` com `WHERE channel_type = 'whatsapp' AND status = 'ignored'` e na query de `check-updates`.  
  - Verificar se há “Using filesort” ou “Using temporary” e quantas linhas são examinadas.  
  - Contar quantas conversas com `status = 'ignored'` têm `channel_id` IS NULL (isso indica carga de `resolveMissingChannelIds`).

### 6.3 Riscos se “Ativas” crescer muito

- O mesmo padrão (uma request pesada que traz até 100 itens + resoluções + estatísticas no mesmo request) será usado para Ativas.  
- Com dezenas de milhares de conversas ativas, a **seleção** das 100 mais recentes pode ficar mais lenta sem índice composto adequado.  
- Resolução de @lid e de `channel_id` continuará proporcional ao número de conversas “problemáticas” nessas 100.  
- Polling em si tende a escalar melhor (check-updates é leve), mas qualquer chamada a `conversations-list` será tão pesada quanto o load inicial.  
- Estatísticas continuarão refletindo só as 100 listadas, não o total real de pendentes/novas/mais antigo.

### 6.4 Estratégia recomendada (alto nível)

- **Listagem paginada com cursor** no backend (limit + cursor por `last_message_at`/id).  
- **Primeira tela rápida:** carregar só os primeiros 20–30 itens; “carregar mais” ou infinite scroll para o restante.  
- **Índice composto** em `conversations` para (channel_type, status, last_message_at) — ou equivalente — para evitar filesort e reduzir tempo da query principal.  
- **Estatísticas** por endpoint/cache separado, ou job em background, em vez de calcular no mesmo request da listagem.  
- **Reduzir trabalho auxiliar:** menos queries em `communication_events` (persistir `channel_id` sempre que possível; evitar resolver em massa a cada listagem); considerar cache para `getWhatsAppSessions` e para “tabela existe” onde for seguro.  
- **Consistência de filtros:** incluir `session_id` (e demais filtros) no GET de `conversations-list` para que a lista AJAX respeite os mesmos filtros da página.

---

*Documento gerado com base no código em `CommunicationHubController.php`, `views/communication_hub/index.php`, migrations e `ContactHelper.php`. Sem alteração de código; apenas análise e recomendações.*
