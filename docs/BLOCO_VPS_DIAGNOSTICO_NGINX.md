# Bloco VPS — Diagnóstico Nginx (rota /api para gateway)

**Objetivo:** Verificar como o Nginx roteia requisições para o gateway-wrapper (ex.: `/api/messages`).  
**Risco:** Zero — apenas leitura, sem modificar nada.

---

## [VPS Gateway] Comando 1 — Localizar config do wpp.pixel12digital

**Rodar em:** SSH da VPS (wpp.pixel12digital.com.br)

```bash
grep -r "wpp.pixel12digital" /etc/nginx/ 2>/dev/null | head -20
```

**Retornar:** saída completa do comando (arquivos e linhas encontradas).

---

## [HostMedia] Comando de referência (não é VPS)

**Rodar em:** SSH da HostMedia (hub.pixel12digital.com.br)

```bash
grep -E "WPP_GATEWAY|GATEWAY_BASE" .env 2>/dev/null || grep -E "WPP_GATEWAY|GATEWAY_BASE" /home/pixel12digital/hub.pixel12digital.com.br/.env 2>/dev/null
```

**Retornar:** valores de `WPP_GATEWAY_BASE_URL` e `WPP_GATEWAY_SECRET` (para confirmar para onde o PixelHub envia).

---

**Não alterar nada até o Charles devolver a saída completa.**
