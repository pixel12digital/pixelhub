#!/bin/bash
# Script para inserir conversão WebM→OGG no gateway (api.js)
# Copiar para a VPS e executar: bash VPS_SCRIPT_PATCH_WEBM_OGG.sh

set -e
API_JS="/opt/pixel12-whatsapp-gateway/wrapper/src/routes/api.js"
BACKUP="${API_JS}.bak.$(date +%Y%m%d_%H%M%S)"

echo "=== Patch WebM→OGG no gateway ==="
echo "Arquivo: $API_JS"

[ ! -f "$API_JS" ] && { echo "ERRO: Arquivo não encontrado."; exit 1; }

# Backup
echo "1) Backup..."
cp "$API_JS" "$BACKUP"
echo "   OK: $BACKUP"

# Verificar se já aplicado
if grep -q "convertWebMToOggBase64" "$API_JS"; then
  echo ""
  echo "Patch já aplicado. Rollback: cp $BACKUP $API_JS"
  exit 0
fi

# Criar arquivo temporário com a função
TMPFUNC=$(mktemp)
cat > "$TMPFUNC" << 'ENDFUNC'

/**
 * Converte áudio WebM (base64) para OGG/Opus via ffmpeg.
 * Contrato: CONTRATO_AUDIO_GATEWAY_HOSTMIDIA.md
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
        try { fs.unlinkSync(webmPath); } catch (e) {}
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

ENDFUNC

# 2) Adicionar imports no início (após a primeira linha) - usa head/tail para evitar bug do sed
echo "2) Inserindo imports..."
if ! grep -q "require('fs')" "$API_JS"; then
  TMP_IMPORTS=$(mktemp)
  {
    head -1 "$API_JS"
    echo "const fs = require('fs');"
    echo "const path = require('path');"
    echo "const { exec } = require('child_process');"
    echo "const os = require('os');"
    tail -n +2 "$API_JS"
  } > "$TMP_IMPORTS"
  mv "$TMP_IMPORTS" "$API_JS"
  echo "   OK: imports adicionados"
else
  echo "   OK: imports já existem"
fi

# 3) Inserir função antes da rota de messages
echo "3) Inserindo função convertWebMToOggBase64..."
# Encontrar linha que contém router.post ou app.post com messages
LINHA=$(grep -n "router\.post\|app\.post" "$API_JS" | head -1 | cut -d: -f1)
[ -z "$LINHA" ] && LINHA=$(grep -n "post.*messages\|/messages" "$API_JS" | head -1 | cut -d: -f1)
[ -z "$LINHA" ] && LINHA=15

sed -i "${LINHA}r $TMPFUNC" "$API_JS"
rm -f "$TMPFUNC"
echo "   OK: função inserida antes da linha $LINHA"

# 4) Substituir base64Ptt por base64ParaEnviar na chamada sendVoiceBase64
#    e adicionar bloco de conversão antes (via sed simples)
echo "4) Aplicando lógica na rota de áudio..."

# Trocar base64Ptt por base64ParaEnviar na chamada sendVoiceBase64 (apenas na linha que tem sendVoiceBase64)
sed -i '/sendVoiceBase64/s/base64Ptt/base64ParaEnviar/g' "$API_JS"

# Inserir bloco de conversão após "if (type === 'audio')"
awk '
  /type\s*===?\s*["'\'']audio["'\'']/ && !done {
    print
    print "  const requestId = req.headers[\"x-request-id\"] || \"\";"
    print "  (logger || console).info(\"[req=\" + requestId + \"] audio_received\");"
    print "  let base64ParaEnviar = base64Ptt;"
    print "  if (audio_mime && String(audio_mime).toLowerCase().includes(\"webm\")) {"
    print "    const convertResult = await convertWebMToOggBase64(base64Ptt, requestId);"
    print "    if (convertResult.ok) base64ParaEnviar = convertResult.base64;"
    print "    else return res.status(400).json({ success: false, error: \"Conversão WebM→OGG falhou\", error_code: \"AUDIO_CONVERT_FAILED\", origin: \"gateway\", reason: convertResult.error || \"FFMPEG_FAILED\", stderr_preview: (convertResult.stderr || \"\").slice(0, 500) });"
    print "  }"
    done = 1
    next
  }
  { print }
' "$API_JS" > "${API_JS}.tmp" && mv "${API_JS}.tmp" "$API_JS"

echo "   OK: lógica de conversão aplicada"

echo ""
echo "=== Concluído ==="
echo ""
echo "Revisar: nano $API_JS"
echo "Restart: docker restart gateway-wrapper"
echo "Logs:    docker logs gateway-wrapper --tail 30"
echo ""
echo "Rollback: cp $BACKUP $API_JS && docker restart gateway-wrapper"
