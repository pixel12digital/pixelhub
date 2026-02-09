# Diagnóstico: Por que pixel12digital aparece "Conectado" quando não está?

**Objetivo:** Localizar exatamente de onde vem o status "connected" falso para pixel12digital.

---

## Fluxo do status (resumo)

```
Pixel Hub (sessionsList)
    → GET /api/channels (gateway)
        → gateway retorna { channels: [{ id, status: "connected" }] }
            → gateway obtém status de: sessionManager OU getSessionStatus(WPPConnect)
```

O Pixel Hub **só repassa** o que o gateway retorna. A origem do status falso está no **gateway** (VPS).

---

## Bloco diagnóstico — Rodar na VPS (Charles)

Copiar e colar o bloco abaixo no terminal da VPS. **Retornar a saída completa.**

```bash
echo "=========================================="
echo "DIAGNÓSTICO: ORIGEM DO STATUS 'CONECTADO'"
echo "=========================================="
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"
SESSION="pixel12digital"

echo ""
echo "=== 1) O que o gateway retorna em GET /api/channels? ==="
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels" 2>/dev/null | head -500

echo ""
echo "=== 2) O que o gateway retorna em GET /api/channels/$SESSION? ==="
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION" 2>/dev/null | head -200

echo ""
echo "=== 3) Onde o gateway define o status? (channels, getSessionStatus, sessionManager) ==="
docker exec gateway-wrapper grep -rn "channels\|getSessionStatus\|sessionManager\|status" /app/src/routes --include="*.js" 2>/dev/null | head -60

echo ""
echo "=== 4) Implementação da rota GET /api/channels ==="
docker exec gateway-wrapper grep -n -B2 -A30 "get.*channels\|router\.get.*channels" /app/src/routes/*.js 2>/dev/null | head -80

echo ""
echo "=== 5) sessionManager - como armazena/retorna status? ==="
docker exec gateway-wrapper cat /app/src/services/sessionManager.js 2>/dev/null | head -80

echo ""
echo "=== 6) wppconnectAdapter - getSessionStatus ==="
docker exec gateway-wrapper grep -n -B2 -A25 "getSessionStatus" /app/src/services/wppconnectAdapter.js 2>/dev/null | head -60
```

---

## O que a saída vai revelar

| Saída | Significado |
|-------|-------------|
| 1 e 2 | Exatamente o que o gateway retorna ao Pixel Hub (status "connected" está aqui) |
| 3–6 | Código do gateway: se usa sessionManager, getSessionStatus, e a ordem de prioridade |

**Hipótese:** O gateway chama `getSessionStatus` (WPPConnect) e retorna esse valor. O WPPConnect mantém "connected" em cache quando o dispositivo desconecta e não emite `connection.update` (ou o gateway não trata).

**Correção:** O gateway precisa (a) tratar `connection.update` para atualizar sessionManager quando desconectar, e (b) priorizar sessionManager sobre getSessionStatus quando houver divergência.

---

## Resultado do diagnóstico (saída recebida)

- **GET /api/channels:** Retorna `status: "connected"` para pixel12digital e imobsites.
- **getSessionStatus:** Chama WPPConnect `GET /api/{sessionId}/status-session` e retorna o status.
- **sessionManager:** Usa `sessions.json`; tem `addSession`, `getAllSessions`; estrutura com `session_id`, `metadata`.
- **Rotas:** O grep em `/app/src/routes` retornou vazio — a rota pode estar em outro path.

**Conclusão:** O status vem do WPPConnect (`status-session`). O WPPConnect retorna "connected" mesmo com o dispositivo desconectado.

---

## Bloco adicional — Localizar rota e webhook

Rodar e retornar a saída:

```bash
echo "=== 7) Onde está a rota channels? ==="
docker exec gateway-wrapper grep -rn "channels\|getSessionStatus" /app/src --include="*.js" 2>/dev/null | head -80

echo ""
echo "=== 8) Onde eventos/webhook são recebidos? ==="
docker exec gateway-wrapper grep -rn "connection\.update\|eventType\|webhook\|Received" /app/src --include="*.js" 2>/dev/null | head -60
```

---

## Próximo passo

Com a saída do bloco 7 e 8:
1. Identificar o arquivo da rota GET /api/channels
2. Identificar onde inserir o handler de connection.update
3. Montar o patch: sessionManager.updateSessionStatus + prioridade no GET /api/channels
