# Diagnóstico: [Mídia] às 16:14 — Resultado

**Data:** 05/02/2026  
**Script:** `database/diagnostico-midia-1614.php`

---

## 1. Eventos encontrados (16:00–16:30, conversa Alessandra)

| # | event_id | Hora | Direção | payload.type | raw.payload.type | communication_media | file_exists |
|---|----------|------|---------|--------------|------------------|--------------------|-------------|
| 1 | caea0c83 | 16:07 | IN | NULL | chat | NÃO | - |
| 2 | 34fd713f | 16:14 | OUT | NULL | **ptt** | SIM | **NÃO** |
| 3 | 9a3ccd19 | 16:14 | OUT | audio | NULL | SIM | **NÃO** |
| 4 | 812a22e7 | 16:15 | OUT | audio | NULL | SIM | **NÃO** |
| 5 | 51ba1cb6 | 16:16 | OUT | NULL | **ptt** | SIM | **NÃO** |

---

## 2. Achados principais

### 2.1 Arquivos de áudio inexistentes no disco

Todos os áudios têm registro em `communication_media` com `stored_path` preenchido, mas **nenhum arquivo existe** no disco local:

- `whatsapp-media/tenant-2/2026/02/05/1b05bed37255f8f09ac3f25abf7a8781.ogg`
- `whatsapp-media/tenant-36/2026/02/05/21e0d16e078200c54670c653ed5c5b0f.ogg`
- `whatsapp-media/tenant-36/2026/02/05/7209c850e2ca12054be1fafb1c851203.ogg`
- `whatsapp-media/tenant-2/2026/02/05/9a65c1d3bea9b0a40beafe001b0838b8.ogg`

**Possíveis causas:**
- Banco compartilhado entre dev e produção, mas **storage não** (arquivos só em produção)
- Falha no download/gravação no momento do processamento
- Arquivos removidos ou em outro diretório

### 2.2 Evento 1 (16:07) — tipo `chat`

O evento `caea0c83` é mensagem de texto ("Charles eu tenho que procurar...") com `raw.payload.type = chat`. Não é áudio e não gera `[Mídia]`.

### 2.3 Por que aparece `[Mídia]`?

O placeholder `[Mídia]` é exibido quando:
- `media` é `null` ou `media.url` está vazio, **e**
- `content` está vazio

Se o backend retorna `media` com `url`, o frontend mostra o player de áudio. Se o arquivo não existe, a URL retorna 404 e o áudio não toca, mas o player continua visível.

**Hipótese mais provável:** algum evento está chegando sem `media` (ou sem `media.url`). Possíveis motivos:
1. **Evento duplicado** (antes da correção de idempotência): um dos eventos pode não ter registro em `communication_media`
2. **Ordem de processamento:** o evento é exibido antes de `communication_media` ser preenchido
3. **`payload.type` em caminho diferente:** se o tipo não for detectado em `payload.type` ou `raw.payload.type`, `hasMediaIndicator` fica falso e o backend não busca mídia

---

## 3. Próximos passos sugeridos

1. **Em produção (Hostmidia):** verificar se os arquivos existem em `storage/whatsapp-media/tenant-2/` e `tenant-36/`.
2. **Se os arquivos existirem em produção:** o `[Mídia]` pode ser de um evento duplicado sem mídia (antes da correção).
3. **Se os arquivos não existirem:** investigar falha no download/gravação em `WhatsAppMediaService` ou no worker de mídia.
4. **Ampliar detecção de tipo:** incluir `payload.message.type` e `payload.data.type` na checagem de `hasMediaIndicator` no `CommunicationHubController`.

---

## 4. Comando para rodar o diagnóstico

```bash
php database/diagnostico-midia-1614.php
```

---

**Fim do diagnóstico**
