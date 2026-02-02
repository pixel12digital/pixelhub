# Investigação: Mensagens não exibidas no Inbox (Robson 4234)

**Data:** 29/01/2026  
**Contexto:** Usuário reporta que, na conversa com Robson (tel. final 4234), no Inbox:
1. **Mensagem enviada (13:28)** – "testa novamente Robson no app e no computador" – não aparece (mas aparece no WhatsApp)
2. **Imagem recebida (13:29)** – não consegue visualizar
3. **Áudio recebido (13:29)** – placeholder aparece, mas não carrega e não dá play

**Objetivo:** Investigar causas sem implementar correções. Garantir diagnóstico antes de alterar código.

---

## 1. Fluxo de dados (resumo)

### 1.1 Mensagens exibidas no Inbox

| Etapa | Componente | Descrição |
|-------|------------|-----------|
| 1 | `loadInboxConversation()` | Carrega conversa via `GET /communication-hub/thread-data?thread_id=X&channel=whatsapp` |
| 2 | `getThreadData()` | Chama `getWhatsAppMessages()` → `getWhatsAppMessagesFromConversation()` |
| 3 | Backend | Busca eventos em `communication_events` + mídia em `communication_media` |
| 4 | Frontend | `renderInboxMessages()` exibe mensagens com `media.url` para img/audio |

### 1.2 Polling de novas mensagens

| Etapa | Componente | Descrição |
|-------|------------|-----------|
| 1 | `fetchInboxNewMessages()` | Chama `GET /communication-hub/messages/new?thread_id=X&after_timestamp=Y` |
| 2 | `getNewMessages()` | Usa `getWhatsAppMessagesIncremental()` |
| 3 | Frontend | `appendInboxMessages()` adiciona novas mensagens ao DOM |

### 1.3 Envio de mensagem (outbound)

| Etapa | Componente | Descrição |
|-------|------------|-----------|
| 1 | `sendInboxMessage()` | POST para `/communication-hub/send` |
| 2 | Backend | Envia ao gateway; cria evento via `EventIngestionService::ingest()` com `event_type=whatsapp.outbound.message` |
| 3 | Frontend | Mantém mensagem otimista (não recarrega). Se poll retornar outbound, remove otimista e exibe a real |

---

## 2. Hipóteses por sintoma

### 2.1 Mensagem enviada (13:28) não aparece

| # | Hipótese | Onde verificar |
|---|----------|----------------|
| H1 | Evento outbound não foi salvo no banco | `communication_events` com `event_type=whatsapp.outbound.message`, `payload.to` contendo 4234, `created_at` ~13:28 |
| H2 | Evento salvo mas filtrado na query | `getWhatsAppMessagesFromConversation()` – normalização de `contact_external_id` vs `payload.to`; outbound pode ter `conversation_id=NULL` (não é passado no ingest) |
| H3 | Mensagem otimista removida antes da real chegar | `appendInboxMessages()` remove otimistas quando `hasOutbound=true`. Se poll retornou só inbound (imagem/áudio), otimista permanece. Se usuário recarregou ou trocou de conversa, `thread-data` é buscado de novo – aí depende de H1/H2 |
| H4 | `thread_id` ou `conversation_id` incorreto | Conversa Robson pode ter `thread_id=whatsapp_N` diferente do esperado |

### 2.2 Imagem recebida (13:29) não visualiza

| # | Hipótese | Onde verificar |
|---|----------|----------------|
| H5 | Evento de imagem não existe no banco | `communication_events` com `type=image` ou `message.imageMessage`, `payload.from` com 4234, ~13:29 |
| H6 | Webhook de imagem não chegou ou não processou | Logs do gateway/VPS; tabela `communication_events` |
| H7 | Mídia não foi baixada/armazenada | `communication_media` com `event_id` do evento de imagem; arquivo em `storage/whatsapp-media/` |
| H8 | `media.url` incorreto ou 404 | Resposta de `thread-data` ou `messages/new` – campo `media.url`; requisição no Network do navegador |
| H9 | Tipo de mídia não reconhecido | Frontend espera `media_type=image` ou `sticker`. Backend pode retornar outro tipo |

### 2.3 Áudio recebido (13:29) – placeholder, sem play

| # | Hipótese | Onde verificar |
|---|----------|----------------|
| H10 | Evento de áudio existe mas mídia não carregou | `communication_media` com `media_type=audio`; arquivo existe em disco |
| H11 | URL da mídia retorna 404 ou erro | `GET /communication-hub/media?path=...` – status HTTP |
| H12 | Formato de áudio incompatível com navegador | `mime_type` (ex: `audio/ogg` vs `audio/webm`); navegador pode não reproduzir |
| H13 | Áudio não foi convertido/baixado | Gateway envia WebM; backend deve converter para OGG ou baixar via API |

---

## 3. Script de diagnóstico

Executar no Hostmidia (ou ambiente com acesso ao banco):

```bash
php database/diagnostico-inbox-robson-4234.php
```

O script `database/diagnostico-inbox-robson-4234.php` deve:

1. Buscar conversa com contato terminando em 4234 (ex: 558799884234, 8799884234)
2. Listar `conversation_id`, `thread_id` (whatsapp_{id}), `contact_external_id`, `tenant_id`, `channel_id`
3. Listar eventos (inbound + outbound) das últimas 24h para essa conversa, com:
   - `event_id`, `event_type`, `created_at`, `type` (image/audio/text)
4. Para cada evento de mídia: verificar se existe em `communication_media` e se o arquivo existe em `storage/whatsapp-media/`
5. Simular o que `getWhatsAppMessagesFromConversation()` retornaria (ou chamar o endpoint e logar)

---

## 4. Comandos úteis (VPS / Charles)

### 4.1 Verificar resposta do thread-data

```bash
# Substituir THREAD_ID pelo whatsapp_{conversation_id} da conversa Robson
curl -s -b "cookies.txt" "https://hub.pixel12digital.com.br/communication-hub/thread-data?thread_id=THREAD_ID&channel=whatsapp" | jq '.messages[] | {direction, content: .content[0:50], media: .media.url, timestamp}'
```

### 4.2 Verificar se media retorna 200

```bash
# Pegar uma media.url da resposta acima e testar
curl -I -b "cookies.txt" "https://hub.pixel12digital.com.br/communication-hub/media?path=whatsapp-media%2F..."
```

### 4.3 Logs do backend

Procurar por:
- `[CommunicationHub::getThreadData]` – quantidade de mensagens retornadas
- `[CommunicationHub::send]` – se outbound foi persistido
- `[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation` – eventos encontrados/filtrados

---

## 5. Próximos passos (após diagnóstico)

1. **Executar** `diagnostico-inbox-robson-4234.php` e anotar resultados
2. **Conferir** resposta real de `thread-data` e `messages/new` no Network (F12)
3. **Testar** `media.url` manualmente (curl ou navegador)
4. **Documentar** qual hipótese foi confirmada
5. **Só então** propor patch (ex.: correção de normalização, ajuste de webhook, fallback de mídia)

---

## 6. Resultado do diagnóstico (29/01/2026)

### 6.1 Execução do script `diagnostico-thread-data-robson.php`

O script simula a chamada real ao `getThreadData()` e captura a resposta JSON.

### 6.2 Conclusão: a API retorna os dados corretamente

| Item | Status na resposta da API |
|------|---------------------------|
| **Mensagem enviada (13:27:59)** "Testa novamente Robson no app e no computador" | ✓ **PRESENTE** (2 eventos outbound duplicados) |
| **Imagem (13:29:28)** | ✓ **PRESENTE** – `media.url` = `/painel.pixel12digital/communication-hub/media?path=whatsapp-media%2Ftenant-2%2F2026%2F02%2F02%2Fa7c66eb9c50ea35df2fb4c72db744850.jpg` |
| **Áudio (13:29:43)** | ✓ **PRESENTE** – `media.url` = `/painel.pixel12digital/communication-hub/media?path=whatsapp-media%2Ftenant-2%2F2026%2F02%2F02%2F61cbf001538c1eceed4ededba2853101.ogg` |

### 6.3 Causa provável: frontend (Inbox) ou URL de mídia

Como o backend retorna tudo certo, o problema tende a estar em:

1. **URL de mídia relativa**  
   A API devolve `media.url` como `/painel.pixel12digital/communication-hub/media?path=...`.  
   Em produção (ex.: `hub.pixel12digital.com.br`), o `BASE_PATH` pode ser outro.  
   Se o Inbox está em `hub.pixel12digital.com.br/tenants`, o navegador pode resolver a URL de forma incorreta.

2. **Arquivos de mídia inexistentes no disco**  
   O script `diagnostico-inbox-robson-4234.php` indicou `file_exists=❌` para os arquivos em `storage/whatsapp-media/tenant-2/2026/02/02/`.  
   Se o ambiente local não tiver esses arquivos (por exemplo, banco remoto e storage local), o `serveMedia` retornará 404.

3. **Renderização no Inbox**  
   Se a API retorna os dados, mas o Inbox não exibe, pode haver erro de parsing ou de renderização no JavaScript.

### 6.4 Próximos passos recomendados

1. **No navegador (F12 → Network):**  
   - Abrir a conversa do Robson no Inbox  
   - Conferir a resposta de `thread-data` e se as mensagens vêm no JSON  
   - Conferir as requisições para `communication-hub/media?path=...` e o status (200 ou 404)

2. **Na Hostmidia (produção):**  
   - Rodar `diagnostico-inbox-robson-4234.php` para verificar se os arquivos existem em `storage/whatsapp-media/tenant-2/2026/02/02/`

3. **Se as mídias retornarem 404:**  
   - Verificar se o webhook baixou e salvou os arquivos  
   - Avaliar reprocessamento via `WhatsAppMediaService::processMediaFromEvent()`

---

*Documento criado em 29/01/2026. Atualizado com resultado do diagnóstico em 29/01/2026.*
