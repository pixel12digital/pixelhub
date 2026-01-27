# Contrato de áudio: Hostmidia ↔ Gateway (VPS)

**Data:** 26/01/2026  
**Objetivo:** Áudio funcionar no Chrome sem depender de ffmpeg no Hostmidia; conversão WebM→OGG pode ser feita no gateway.

---

## 1. Contrato normalizado (POST /api/messages, type=audio)

Quando o Hostmidia envia áudio, o payload pode incluir:

| Campo      | Obrigatório | Descrição |
|-----------|-------------|-----------|
| `channel` | sim         | ID do canal |
| `to`      | sim         | Número destino (E.164) |
| `type`    | sim         | `"audio"` |
| `base64Ptt` | sim       | Áudio em base64 (OGG/Opus **ou** WebM/Opus) |
| `audio_mime` | não      | Ex.: `"audio/webm"` ou `"audio/ogg;codecs=opus"`. Quando `audio/webm`, o **gateway** deve converter para OGG/Opus antes de enviar ao WPPConnect. |
| `is_voice` | não       | `true` = voice note (PTT). Default `true` quando `audio_mime` é enviado. |
| `metadata` | não       | Metadados opcionais (sent_by, etc.) |

**Regra no Hostmidia:**

- Se o áudio já for OGG/Opus: envia só `base64Ptt` (sem `audio_mime`).
- Se for WebM e a conversão no Hostmidia falhar (EXEC_DISABLED, FFMPEG_*): envia `base64Ptt` (WebM) + `audio_mime: "audio/webm"` + `is_voice: true` e o **gateway** converte.

---

## 2. Correlation ID (obrigatório para diagnóstico)

- O Hostmidia envia o header **`X-Request-Id`** em toda requisição ao gateway (valor = request_id do `CommunicationHubController::send()`).
- O **gateway (VPS) deve** ler esse header e logar o mesmo ID em **cada etapa** do processamento de áudio:
  - `received` (request entrou)
  - `decode` (se houver decode de payload)
  - `convert` (se WebM→OGG)
  - `sendVoiceBase64` (chamada ao WPPConnect)
  - `returned` (resposta enviada)
- Assim, quando o usuário reportar “500 às 11:57”, basta filtrar os logs do PM2 por esse request-id para ver em qual etapa parou.

---

## 3. Comportamento esperado no Gateway (VPS)

1. Se vier `audio_mime === "audio/webm"` (ou equivalente) ou o conteúdo for WebM (ex.: header EBML):
   - Converter para OGG/Opus com ffmpeg (ex.: `-c:a libopus -b:a 32k -ar 16000`).
   - Logar: tempo de conversão, exit code, stderr (preview), tamanho input/output.
2. Se já for `audio/ogg` ou `audio/ogg;codecs=opus`: não converter; repassar ao WPPConnect.
3. Em todas as etapas acima, logar o **X-Request-Id** recebido no header (ver seção 2).
4. Em caso de falha na conversão: responder com erro estruturado, ex.:
   - `error_code`: `AUDIO_CONVERT_FAILED`
   - `origin`: `gateway`
   - `reason`: `FFMPEG_NOT_FOUND` | `FFMPEG_FAILED` | `TIMEOUT` | etc.
   - `stderr_preview`: primeiros ~500 chars do stderr (apenas para diagnóstico).

---

## 4. Fallback no Hostmidia

1. **Tentativa 1:** converter WebM→OGG no Hostmidia (ffmpeg via `exec`).  
   - Se `disable_functions` incluir `exec` ou ffmpeg falhar → vai para 2.
2. **Tentativa 2 (fallback):** enviar WebM ao gateway com `audio_mime: "audio/webm"`, `is_voice: true`.  
   - O gateway converte na VPS e envia ao WPPConnect.
3. Só retorna `AUDIO_CONVERT_FAILED` ao usuário quando:
   - a conversão no Hostmidia falhou **e**
   - (o gateway não suporta `audio_mime`/conversão **ou** o gateway também falhou).

---

## 5. Diagnóstico no Hostmidia

- **`GET /diagnostic-audio-env.php`**  
  - Resposta JSON com: `exec_available`, `shell_exec_available`, `proc_open_available`, `ffmpeg_in_path`, `recommendation` (`hostmidia_convert` ou `gateway_convert`).  
  - Use para saber se a conversão local é possível ou se o fluxo deve depender do gateway.

---

## 6. Respostas de erro estruturadas (Hostmidia → cliente)

Quando a conversão falha no Hostmidia (sem fallback) ou no gateway após fallback:

- `error_code`: `AUDIO_CONVERT_FAILED` ou códigos já existentes (ex.: `WPPCONNECT_SEND_ERROR`, `TIMEOUT`).
- `origin`: `hostmidia` ou `gateway`.
- `reason`: `EXEC_DISABLED` | `FFMPEG_FAILED` | `FFMPEG_OUTPUT_INVALID` | `TEMP_WRITE_FAILED` | `OGG_READ_FAILED` | etc.
- `stderr_preview`: capado (ex.: 500 chars), só para diagnóstico.
