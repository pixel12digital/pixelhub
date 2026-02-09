# Pacote VPS — Implementar conversão WebM→OGG no Gateway

**Data:** 05/02/2026  
**Objetivo:** Quando o HostMedia envia `audio_mime: "audio/webm"`, o gateway deve converter WebM→OGG com ffmpeg antes de chamar sendVoiceBase64.

**Contexto:** HostMedia não tem ffmpeg no PATH → envia WebM com audio_mime. O gateway tem ffmpeg instalado mas não tem código que use. Resultado: cliente vê "Este áudio não está mais disponível".

**Referência:** `docs/CONTRATO_AUDIO_GATEWAY_HOSTMIDIA.md`

---

## Formato do Pacote (copiar/colar para o Charles)

**VPS – OBJETIVO:** Adicionar lógica de conversão WebM→OGG na rota de áudio quando `audio_mime === "audio/webm"`.  
**SERVIÇO:** gateway-wrapper (container Docker).  
**RISCO:** Médio (altera código, requer restart).  
**ROLLBACK:** Ver seção 5.

---

### 1) Pré-check (não muda nada)

**Comandos (copiar/colar e devolver os outputs):**

```bash
# 1. Ver como a rota /api/messages processa type=audio
grep -n -B 2 -A 50 "type.*===.*'audio'\|type === 'audio'" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js | head -80

# 2. Ver se já existe referência a audio_mime ou base64Ptt
grep -n "audio_mime\|base64Ptt\|base64" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js | head -30

# 3. Confirmar ffmpeg no container
docker exec gateway-wrapper which ffmpeg
docker exec gateway-wrapper ffmpeg -version 2>&1 | head -1

# 4. Ver imports no topo do api.js (fs, path, child_process?)
head -50 /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
```

**O que retornar:** Saída dos 4 blocos para confirmar estrutura.

---

### 2) Execução — Backup e patch

**Comando (copiar/colar um bloco único):**

```bash
# Backup
cp /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js \
   /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js.bak.$(date +%Y%m%d_%H%M%S)
```

**O que retornar:** Confirmação de que o backup foi criado.

---

### 3) Implementação

O Charles deve editar `/opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js` e adicionar:

**A) No topo (após os requires existentes), adicionar se não existir:**
```javascript
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const os = require('os');
```

**B) Função auxiliar de conversão (inserir antes da rota POST /api/messages ou no início do arquivo, após os requires):**
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
    logger.info(`${prefix}convert_start webm_size=${buf.length}`);

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
      logger.info(`${prefix}convert_end ogg_size=${result.base64.length}`);
      return result;
    }
    logger.warn(`${prefix}convert_failed stderr_preview=${result.stderr}`);
    return result;
  } catch (e) {
    try { fs.unlinkSync(webmPath); } catch (_) {}
    try { fs.unlinkSync(oggPath); } catch (_) {}
    logger.error(`${prefix}convert_exception ${e.message}`);
    return { ok: false, error: e.message, stderr: '' };
  }
}
```

**C) Na rota que processa `type === 'audio'`**, antes de chamar `sendVoiceBase64`:

- Ler `audio_mime` e `base64Ptt` do body.
- Se `audio_mime === 'audio/webm'` (ou equivalente):
  1. Chamar `convertWebMToOggBase64(base64Ptt, requestId)`.
  2. Se `result.ok`, usar `result.base64` no lugar de `base64Ptt` para `sendVoiceBase64`.
  3. Se `!result.ok`, responder com erro estruturado:
     ```json
     {
       "success": false,
       "error": "Conversão WebM→OGG falhou",
       "error_code": "AUDIO_CONVERT_FAILED",
       "origin": "gateway",
       "reason": "FFMPEG_FAILED",
       "stderr_preview": "..."
     }
     ```
- Se `audio_mime` não for webm ou não existir: usar `base64Ptt` direto (comportamento atual).

**D) Logar X-Request-Id** em cada etapa (received, convert_start, convert_end, sendVoiceBase64, returned).

---

### 4) Restart e verificação

```bash
docker restart gateway-wrapper
sleep 5
docker ps | grep gateway-wrapper
docker logs gateway-wrapper --tail 20
```

**O que retornar:** Confirmação de que o container subiu e não há erros de sintaxe nos logs.

---

### 5) Rollback (se necessário)

```bash
BACKUP=$(ls -t /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js.bak.* 2>/dev/null | head -1)
if [ -n "$BACKUP" ]; then
  cp "$BACKUP" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
  docker restart gateway-wrapper
  echo "Rollback feito de $BACKUP"
fi
```

---

### 6) Critério de aceite

- Enviar áudio gravado no Chrome (WebM) pelo PixelHub.
- O áudio deve chegar ao celular do destinatário e tocar normalmente.
- Se a conversão falhar, o gateway deve retornar JSON com `error_code: AUDIO_CONVERT_FAILED`, `origin: gateway`.

---

## Alternativa: bloco único de pré-check

Se o Charles preferir um único bloco para começar:

```bash
echo "=== Estrutura atual da rota de áudio ==="
grep -n -B 2 -A 60 "type.*===.*'audio'" /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js 2>/dev/null | head -100

echo ""
echo "=== Imports no api.js ==="
head -30 /opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js
```

**Retornar:** Saída completa para o Cursor montar o patch exato (linhas e trechos a modificar).
