# Código para implementar na VPS — Conversão WebM→OGG

**Arquivo:** `/opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js`  
**Objetivo:** Converter áudio WebM para OGG/Opus quando o HostMedia envia `audio_mime: "audio/webm"`.

---

## 1. Imports (adicionar no topo do arquivo, se não existirem)

```javascript
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const os = require('os');
```

---

## 2. Função de conversão (inserir antes da rota POST /api/messages)

```javascript
/**
 * Converte áudio WebM (base64) para OGG/Opus via ffmpeg.
 * Contrato: CONTRATO_AUDIO_GATEWAY_HOSTMIDIA.md
 * @param {string} base64Webm - Base64 do WebM
 * @param {string} requestId - X-Request-Id para logs
 * @returns {Promise<{ok: boolean, base64?: string, error?: string, stderr?: string}>}
 */
async function convertWebMToOggBase64(base64Webm, requestId = '') {
  const prefix = requestId ? `[req=${requestId}] ` : '';
  const tmpDir = os.tmpdir();
  const id = `pixelhub_audio_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
  const webmPath = path.join(tmpDir, `${id}.webm`);
  const oggPath = path.join(tmpDir, `${id}.ogg`);

  try {
    const buf = Buffer.from(base64Webm, 'base64');
    if (buf.length < 100) {
      return { ok: false, error: 'WebM too small', stderr: '' };
    }
    fs.writeFileSync(webmPath, buf);
    (logger || console).info(`${prefix}convert_start webm_size=${buf.length}`);

    const cmd = `ffmpeg -y -i ${webmPath} -c:a libopus -b:a 32k -ar 16000 ${oggPath} 2>&1`;
    const result = await new Promise((resolve) => {
      exec(cmd, { timeout: 15000 }, (err, stdout, stderr) => {
        try {
          fs.unlinkSync(webmPath);
        } catch (e) {}
        if (err) {
          resolve({ ok: false, stderr: (stderr || stdout || err.message || '').slice(0, 500) });
          return;
        }
        if (!fs.existsSync(oggPath) || fs.statSync(oggPath).size < 100) {
          resolve({ ok: false, stderr: (stderr || '').slice(0, 500) });
          return;
        }
        const oggBuf = fs.readFileSync(oggPath);
        try { fs.unlinkSync(oggPath); } catch (e) {}
        resolve({ ok: true, base64: oggBuf.toString('base64'), stderr: '' });
      });
    });

    if (result.ok) {
      (logger || console).info(`${prefix}convert_end ogg_size=${result.base64.length}`);
      return result;
    }
    (logger || console).warn(`${prefix}convert_failed stderr_preview=${result.stderr}`);
    return result;
  } catch (e) {
    try { fs.unlinkSync(webmPath); } catch (_) {}
    try { fs.unlinkSync(oggPath); } catch (_) {}
    (logger || console).error(`${prefix}convert_exception ${e.message}`);
    return { ok: false, error: e.message, stderr: '' };
  }
}
```

---

## 3. Lógica na rota de áudio (inserir ANTES de chamar sendVoiceBase64)

**Onde:** Dentro do bloco que trata `type === 'audio'` ou `type === "audio"`.

**Variáveis esperadas do body:** `base64Ptt`, `audio_mime`, `channel`, `to`.  
**Header:** `X-Request-Id` (para logs).

**Código a inserir:**

```javascript
// === CONVERSÃO WEBM→OGG (contrato HostMedia) ===
const requestId = req.headers['x-request-id'] || '';
let base64ParaEnviar = base64Ptt; // ou como o body estiver nomeado

if (audio_mime && String(audio_mime).toLowerCase().includes('webm')) {
  (logger || console).info(`[req=${requestId}] audio_received audio_mime=${audio_mime} base64_len=${base64Ptt?.length || 0}`);
  
  const convertResult = await convertWebMToOggBase64(base64Ptt, requestId);
  
  if (convertResult.ok) {
    base64ParaEnviar = convertResult.base64;
    (logger || console).info(`[req=${requestId}] convert_ok using_ogg`);
  } else {
    (logger || console).warn(`[req=${requestId}] convert_failed reason=${convertResult.error || 'FFMPEG_FAILED'}`);
    return res.status(400).json({
      success: false,
      error: 'Conversão WebM→OGG falhou',
      error_code: 'AUDIO_CONVERT_FAILED',
      origin: 'gateway',
      reason: convertResult.error || 'FFMPEG_FAILED',
      stderr_preview: (convertResult.stderr || '').slice(0, 500)
    });
  }
}

// Usar base64ParaEnviar (em vez de base64Ptt) na chamada sendVoiceBase64
```

---

## 4. Exemplo de bloco completo (rota de áudio)

Se a rota hoje está assim (exemplo genérico):

```javascript
if (type === 'audio') {
  // ... validações ...
  const result = await wppconnectAdapter.sendVoiceBase64(channel, to, base64Ptt);
  // ...
}
```

**Substituir por:**

```javascript
if (type === 'audio') {
  const requestId = req.headers['x-request-id'] || '';
  (logger || console).info(`[req=${requestId}] audio_received channel=${channel} to=${to}`);
  
  let base64ParaEnviar = base64Ptt;
  
  if (audio_mime && String(audio_mime).toLowerCase().includes('webm')) {
    const convertResult = await convertWebMToOggBase64(base64Ptt, requestId);
    if (convertResult.ok) {
      base64ParaEnviar = convertResult.base64;
    } else {
      return res.status(400).json({
        success: false,
        error: 'Conversão WebM→OGG falhou',
        error_code: 'AUDIO_CONVERT_FAILED',
        origin: 'gateway',
        reason: convertResult.error || 'FFMPEG_FAILED',
        stderr_preview: (convertResult.stderr || '').slice(0, 500)
      });
    }
  }
  
  (logger || console).info(`[req=${requestId}] sendVoiceBase64 start`);
  const result = await wppconnectAdapter.sendVoiceBase64(channel, to, base64ParaEnviar);
  (logger || console).info(`[req=${requestId}] sendVoiceBase64 returned`);
  // ... resto do tratamento ...
}
```

---

## 5. Ajustes possíveis

| Se o gateway usa... | Trocar `logger` por... |
|---------------------|------------------------|
| `logger.info`       | Manter `(logger \|\| console)` |
| Só `console.log`    | Usar `console.log` / `console.warn` / `console.error` |
| Winston/Pino        | Usar o logger existente (ex.: `log.info`) |

| Se o body usa...    | Trocar `base64Ptt` por... |
|---------------------|---------------------------|
| `base64Ptt`         | Manter |
| `base64`            | Usar `base64` |
| Outro nome          | Ajustar em todos os trechos |

---

## 6. Script automático (copiar para VPS e executar)

O arquivo `docs/VPS_SCRIPT_PATCH_WEBM_OGG.sh` contém um script que aplica o patch automaticamente.

**Na VPS:**

```bash
# 1. Copiar o conteúdo do script para a VPS (ou fazer upload do arquivo)
# 2. Salvar como patch.sh e executar:
chmod +x patch.sh
bash patch.sh

# 3. Revisar o resultado e reiniciar
nano /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
docker restart gateway-wrapper
docker logs gateway-wrapper --tail 30
```

**Ou criar o script direto na VPS:**

```bash
nano /root/patch_webm_ogg.sh
# Colar o conteúdo de VPS_SCRIPT_PATCH_WEBM_OGG.sh
# Ctrl+O, Enter, Ctrl+X
chmod +x /root/patch_webm_ogg.sh
bash /root/patch_webm_ogg.sh
```

---

## 7. Comandos manuais (se preferir)

**Backup:**
```bash
cp /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js \
   /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js.bak.$(date +%Y%m%d_%H%M%S)
```

**Restart após editar:**
```bash
docker restart gateway-wrapper
sleep 5
docker ps | grep gateway-wrapper
docker logs gateway-wrapper --tail 30
```

**Rollback:**
```bash
BACKUP=$(ls -t /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js.bak.* 2>/dev/null | head -1)
[ -n "$BACKUP" ] && cp "$BACKUP" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js && docker restart gateway-wrapper && echo "Rollback: $BACKUP"
```

---

## 8. Critério de aceite

- Áudio gravado no Chrome (WebM) enviado pelo PixelHub chega ao celular e toca normalmente.
- Se a conversão falhar, o gateway retorna JSON com `error_code: AUDIO_CONVERT_FAILED`, `origin: gateway`.
