# Bloco VPS — Logs e atualizações WPPConnect (áudio 11:38 não recebido)

**Objetivo:** Verificar logs do WPPConnect/gateway no horário do incidente (03/02 11:38) e versões/atualizações.  
**Risco:** Zero — apenas leitura.  
**Contexto:** Áudio 11:38 do 81642320 nunca chegou ao gateway-wrapper; WPPConnect não emitiu `onmessage`.

---

## [VPS Gateway] Comando 1 — Versão do WPPConnect e gateway-wrapper

**Rodar em:** SSH da VPS (wpp.pixel12digital.com.br)

```bash
echo "=== 1) Versão WPPConnect (wppconnect-server) ==="
docker exec wppconnect-server cat /app/package.json 2>/dev/null | grep -E '"name"|"version"' | head -5

echo ""
echo "=== 2) Versão gateway-wrapper ==="
docker exec gateway-wrapper cat /app/package.json 2>/dev/null | grep -E '"name"|"version"' | head -5

echo ""
echo "=== 3) Imagens Docker (tags/datas) ==="
docker images | grep -E "wppconnect|gateway"
```

**Retornar:** saída completa.

---

## [VPS Gateway] Comando 2 — Logs no horário do incidente

**Nota:** 11:38 BRT = 14:38 UTC. Ajuste a data conforme o dia do incidente.

**Rodar em:** SSH da VPS (wpp.pixel12digital.com.br)

```bash
echo "=== 1) gateway-wrapper: 04/Feb entre 14:30 e 14:45 UTC (11:30-11:45 BRT) ==="
docker logs gateway-wrapper 2>&1 | grep -E "2026-02-04" | grep -E "14:3|14:4" | head -80

echo ""
echo "=== 2) gateway-wrapper: eventos onmessage de pixel12digital hoje ==="
docker logs gateway-wrapper 2>&1 | grep -E "2026-02-04" | grep -i "onmessage" | grep "pixel12digital" | head -30
```

**Retornar:** saída completa.

---

## [VPS Gateway] Comando 3 — Verificar atualizações do WPPConnect

**Rodar em:** SSH da VPS (wpp.pixel12digital.com.br)

```bash
echo "=== 1) Repo/origem da imagem wppconnect ==="
docker inspect wppconnect-server --format '{{.Config.Image}}' 2>/dev/null
docker inspect wppconnect-server --format '{{.Created}}' 2>/dev/null

echo ""
echo "=== 2) Última versão no npm (wppconnect-server) ==="
npm view wppconnect-server version 2>/dev/null || echo "npm não disponível ou pacote não encontrado"

echo ""
echo "=== 3) Última versão wppconnect (lib) ==="
npm view wppconnect version 2>/dev/null || echo "npm não disponível"
```

**Retornar:** saída completa.

---

## Ordem sugerida

1. Comando 1 (versões)
2. Comando 2 (logs — pode retornar vazio)
3. Comando 3 (atualizações)
