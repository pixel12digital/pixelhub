# Diagnóstico: Áudio não recebido no Inbox às 11:38 de 53 81642320

**Data:** 04/02/2026  
**Contato:** 555381642320 (55 53 81 642320 - Renato Silva)

---

## 1. Resumo executivo

O áudio **foi recebido** pelo sistema e está registrado no banco. O evento existe em `communication_events` (id=129276) e em `communication_media` (id=480). Porém:

1. **Horário:** O evento está em **11:34:37** (03/02/2026), não 11:38. Pode ser o mesmo áudio (diferença de ~4 min) ou outro que não chegou.
2. **Arquivo ausente:** O arquivo de áudio (`e0359e18d009f9371819c0a6d65edf7c.ogg`) **não existe** no storage local. O registro em `communication_media` aponta para um path que não está presente em `storage/whatsapp-media/tenant-2/2026/02/03/`.

---

## 2. Evidências

### 2.1 Evento no banco

| Campo | Valor |
|-------|-------|
| communication_events.id | 129276 |
| event_id (UUID) | 9023e3c4-1494-4f0a-9d4f-e01b728f4eda |
| conversation_id | 109 |
| status | processed |
| created_at | 2026-02-03 11:34:37 |
| tipo (payload) | ptt (áudio) |

### 2.2 Mídia

| Campo | Valor |
|-------|-------|
| communication_media.id | 480 |
| media_type | audio |
| stored_path | whatsapp-media/tenant-2/2026/02/03/e0359e18d009f9371819c0a6d65edf7c.ogg |
| file_exists (local) | **NÃO** |

### 2.3 Lista de mensagens da conversa

O evento **está incluído** na query que a API do Inbox usa para listar mensagens da conversa 109. Ou seja, a mensagem seria retornada ao frontend.

---

## 3. Possíveis causas do “não recebido”

### 3.1 Arquivo de áudio ausente (mais provável)

- O download da mídia via gateway pode ter falhado.
- O arquivo pode existir em produção (HostMedia) e não no ambiente local.
- Se a URL de mídia retornar 404, o Inbox pode exibir o item como áudio, mas sem reprodução, ou ocultá-lo.

### 3.2 Diferença de horário (11:34 vs 11:38)

- Se o usuário enviou **dois** áudios (11:34 e 11:38), o segundo pode não ter chegado.
- Nenhum outro evento desse contato foi encontrado entre 11:34 e 12:05 em 03/02.

### 3.3 Ambiente local vs produção

- O banco pode ser de produção e o storage local.
- Em produção, o arquivo pode existir e o áudio ser reproduzido normalmente.

---

## 4. Ações recomendadas

### 4.1 Verificar em produção (HostMedia)

1. Conferir se o arquivo existe em `storage/whatsapp-media/tenant-2/2026/02/03/e0359e18d009f9371819c0a6d65edf7c.ogg`.
2. Abrir a conversa 109 no Inbox em produção e ver se o áudio aparece e reproduz.

### 4.2 Se o arquivo não existir em produção

1. Revisar logs do `WhatsAppMediaService::processMediaFromEvent()` no horário do evento (11:34–11:35).
2. Verificar se o gateway retornou o áudio corretamente em `downloadMedia()`.
3. Conferir permissões e espaço em disco em `storage/whatsapp-media/`.

### 4.3 Scripts de diagnóstico

- `database/diagnostico-audio-81642320-1138.php` – eventos do contato
- `database/check-audio-inbound-geral.php` – áudios inbound gerais
- `database/check-evento-129276.php` – detalhes do evento 129276

---

## 5. Conclusão

O áudio **foi recebido e está no banco**. O evento está na conversa 109 e seria retornado pela API do Inbox. O problema provável é a **ausência do arquivo de áudio no storage**, o que pode impedir a reprodução ou a exibição correta no Inbox. Verificar em produção se o arquivo existe e se o áudio é exibido corretamente.

---

## 6. Investigação adicional (04/02/2026)

### 6.1 Verificação do storage local

- **Pasta `tenant-2/2026/02/03/`:** Não existe no ambiente local.
- **Arquivos em `tenant-2`:** Apenas `2026/01/17/` e `2026/01/20/` com áudios.
- **Conclusão:** O arquivo `e0359e18d009f9371819c0a6d65edf7c.ogg` nunca foi salvo neste ambiente, ou o banco é remoto (produção) e o storage é local.

### 6.2 Fluxo técnico

1. **Webhook** → Evento ingerido em `communication_events` (id=129276).
2. **EventIngestionService** → Chama `WhatsAppMediaService::processMediaFromEvent()`.
3. **WhatsAppMediaService** → Detecta PTT em `raw.payload`, extrai `mediaId`, chama `downloadMedia()` no gateway.
4. **Se download OK** → Salva em `storage/whatsapp-media/tenant-2/2026/02/03/` e grava em `communication_media`.
5. **Se download falha** → Grava `communication_media` com `stored_path = NULL` (não com path preenchido).

O fato de existir `stored_path` preenchido indica que, em algum momento, o download e o `file_put_contents` foram bem-sucedidos. Possibilidades:
- **Produção:** Arquivo foi salvo no servidor HostMedia; local não tem o arquivo.
- **Falha posterior:** Arquivo foi removido (limpeza, disco cheio, etc.).

### 6.3 Comportamento no Inbox

- A API retorna o evento com `media.url` apontando para `/communication-hub/media?path=...`.
- O frontend (`thread.php`) renderiza o player de áudio quando `media.url` existe.
- Ao reproduzir, o navegador faz `GET` nessa URL → `serveMedia()` tenta servir o arquivo.
- **Se o arquivo não existe:** `serveMedia()` tenta reprocessar via `processMediaFromEvent()`. Se o gateway ainda tiver a mídia, o download pode funcionar. Caso contrário, retorna 404 e o áudio não toca.

### 6.4 Hipótese sobre 11:34 vs 11:38

- O único evento de áudio desse contato no período é às **11:34:37**.
- Se o usuário enviou às 11:38, pode ser:
  - **Diferença de timezone** (DB em UTC, usuário em BRT).
  - **Mesmo áudio** com diferença de alguns minutos (envio vs processamento).
  - **Dois áudios:** o das 11:38 nunca chegou (webhook/gateway).

### 6.5 Verificação no banco remoto (04/02/2026)

Script executado: `php database/verificar-audio-81642320-remoto.php`

| Item | Resultado |
|------|-----------|
| **Conexão** | DB_HOST=r225us.hmservers.net, DATABASE=pixel12digital_pixelhub (banco remoto) |
| **Evento 129276** | ✅ Existe — tipo PTT, 11:34:37, conversation_id=109 |
| **Mídia 480** | ✅ Existe — stored_path preenchido, **file_size=36.9 KB** |
| **Arquivo local** | ❌ Não existe (storage local ≠ servidor de produção) |

**Conclusão:** O `file_size` preenchido (36.9 KB) indica que o arquivo **foi salvo com sucesso** no momento do processamento. O download e o `file_put_contents` funcionaram. O arquivo deve existir no **storage do servidor HostMedia** (produção), onde o webhook foi processado.

**Horário:** Apenas 1 evento entre 11:30 e 12:05 — às **11:34:37**. Não há registro de áudio às 11:38 (pode ser o mesmo áudio com diferença de tempo, ou o segundo nunca chegou).

### 6.6 Próximos passos sugeridos

| # | Ação | Responsável |
|---|------|-------------|
| 1 | Em produção (HostMedia): verificar se `storage/whatsapp-media/tenant-2/2026/02/03/e0359e18d009f9371819c0a6d65edf7c.ogg` existe | Charles/DevOps |
| 2 | Se existir: usuário acessando Inbox localmente com banco remoto — o storage local não tem o arquivo; usar produção para ver o áudio | — |
| 3 | Se não existir em produção: buscar logs `[WhatsAppMediaService]` no horário 11:34–11:35 de 03/02 | Charles |
| 4 | Verificar no gateway (VPS) se há logs de webhook/download para esse evento | Charles (Regra Triangulação) |
