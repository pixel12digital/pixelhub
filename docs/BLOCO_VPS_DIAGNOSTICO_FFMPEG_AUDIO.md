# Bloco VPS — Diagnóstico ffmpeg/áudio (somente leitura)

**Objetivo:** Verificar se o gateway-wrapper tem ffmpeg e lógica de conversão WebM→OGG **antes** de qualquer alteração.  
**Risco:** Zero — apenas leitura, sem modificar nada.

---

## [VPS Gateway] Comandos para colar no terminal da VPS (wpp.pixel12digital.com.br, como root)

```bash
echo "=== 1) ffmpeg no HOST da VPS ==="
which ffmpeg 2>/dev/null || echo "ffmpeg NÃO encontrado no host"
ffmpeg -version 2>/dev/null | head -1 || echo "ffmpeg não executável"

echo ""
echo "=== 2) ffmpeg DENTRO do container gateway-wrapper ==="
docker exec gateway-wrapper which ffmpeg 2>/dev/null || echo "ffmpeg NÃO encontrado no container"
docker exec gateway-wrapper ffmpeg -version 2>/dev/null | head -1 || echo "ffmpeg não executável no container"

echo ""
echo "=== 3) O gateway tem código que converte WebM→OGG? (audio_mime, ffmpeg) ==="
docker exec gateway-wrapper grep -rn "audio_mime\|audio/webm\|ffmpeg\|convert.*webm\|webm.*ogg" /app --include="*.js" 2>/dev/null | head -30

echo ""
echo "=== 4) Dockerfile do gateway — ffmpeg está na imagem? ==="
docker exec gateway-wrapper cat /app/Dockerfile 2>/dev/null | head -40 || echo "Dockerfile não encontrado em /app"

echo ""
echo "=== 5) Variáveis de ambiente do gateway (conversão?) ==="
docker exec gateway-wrapper env 2>/dev/null | grep -iE "ffmpeg|convert|audio" | sort
```

---

## O que interpretar

| Resultado | Significado |
|-----------|-------------|
| ffmpeg no container = NÃO | O gateway **não** converte WebM→OGG hoje; depende do HostMedia |
| ffmpeg no container = SIM | O gateway **pode** converter; precisa confirmar se o código usa |
| grep não encontra audio_mime/ffmpeg | O gateway **não implementa** conversão; contrato não está atendido |
| grep encontra | O gateway **implementa** conversão; ffmpeg precisa estar no container |

---

## Próximo passo (só após ver o resultado)

- Se o gateway **não** tem ffmpeg nem código de conversão: instalar ffmpeg no HostMedia (pedir ao suporte) ou adicionar ao gateway (exige alterar Dockerfile do gateway).
- Se o gateway **tem** código mas **não** tem ffmpeg: instalar ffmpeg no container (rebuild da imagem ou volume mount).

**Não alterar nada até o Charles devolver a saída completa deste bloco.**
