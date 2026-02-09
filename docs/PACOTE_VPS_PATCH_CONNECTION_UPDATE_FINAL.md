# Pacote VPS: Patch connection.update → status correto no Pixel Hub

**Objetivo:** Quando o gateway receber `connection.update` com estado de desconexão, atualizar `sessionManager`. Em `GET /api/channels`, priorizar o status do sessionManager quando for `disconnected`, para que o Pixel Hub exiba o status correto.

**Contexto:** O WPPConnect retorna `status: "connected"` mesmo com dispositivo desconectado. O evento `connection.update` com `state: "close"` indica desconexão real. O gateway recebe esse evento e encaminha ao webhook, mas não atualiza o sessionManager.

---

## Arquitetura

| Componente | Onde está |
|------------|-----------|
| **Pixel Hub** | Repositório + servidor HostMedia |
| **Gateway WhatsApp (WPPConnect)** | VPS Hotnger (container `gateway-wrapper`) |

O script de patch está no repositório do Pixel Hub. O **Charles** (ou responsável pela VPS Hotnger) precisa **copiar o script para a VPS** e executá-lo lá — o gateway não está no mesmo servidor do Pixel Hub.

---

## Resultado do diagnóstico (E, F, G)

Da saída do Charles:

- **api.js:** `sessionManager` linha 14; rota `GET /api/channels` linha 451; `getSessionStatus` linha 470; `updateSessionStatus` linha 491
- **Fluxo atual:** Para cada sessão, chama `getSessionStatus` (WPPConnect) e sobrescreve sessionManager com o resultado. Se WPPConnect retorna "connected" (stale), o sessionManager fica com "connected"
- **webhookDeliveryService:** Envia eventos para o webhook externo; não é o ponto de recebimento. O recebimento está em `index.js` (receiveWebhookHandler)

---

## Bloco H — Pré-check (localizar ponto exato)

**Retornar:** Saída completa.

```bash
echo "=== H1) Onde Received webhook / Webhook event queued? ==="
docker exec gateway-wrapper grep -rn "Received webhook\|Webhook event queued\|eventType" /app/src --include="*.js" 2>/dev/null | head -30

echo ""
echo "=== H2) index.js - receiveWebhookHandler (linhas 1-200) ==="
docker exec gateway-wrapper sed -n '1,200p' /app/src/index.js 2>/dev/null

echo ""
echo "=== H3) sessionManager - estrutura completa ==="
docker exec gateway-wrapper cat /app/src/services/sessionManager.js 2>/dev/null

echo ""
echo "=== H4) api.js - rota GET channels (linhas 448-530) ==="
docker exec gateway-wrapper sed -n '448,530p' /app/src/routes/api.js 2>/dev/null
```

---

## Bloco I — Aplicar patch na VPS Hotnger

O Pixel Hub está no HostMedia; o gateway está na VPS Hotnger. O script precisa rodar **na VPS**.

### Opção A — Script já está na VPS (se o projeto gateway foi clonado lá)

```bash
cd /opt/pixel12-whatsapp-gateway   # ou o caminho onde o wrapper/gateway está
bash docs/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh
```

### Opção B — Copiar o script do HostMedia para a VPS

**srv817568** é o hostname **interno** da VPS. Do HostMedia use o **IP ou hostname externo** (ex.: IP do painel Hotnger ou `wpp.pixel12digital.com.br`):

```bash
# No HostMedia (pasta do projeto painel.pixel12digital)
# Substituir HOST_VPS pelo IP ou hostname real (ex.: 123.45.67.89 ou wpp.pixel12digital.com.br)
scp docs/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh root@HOST_VPS:/tmp/
```

**Alternativa — enviar via pipe SSH** (se tiver chave configurada):
```bash
# No HostMedia
cat docs/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh | ssh root@HOST_VPS "cat > /tmp/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh"
```

**Na VPS** (após o arquivo estar em /tmp/):
```bash
bash /tmp/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh
```

### Opção C — Colar na VPS (recomendado, comando único)

Abra o arquivo `docs/VPS_COLAR_NA_VPS.txt` e copie **todo o bloco** entre as linhas `cat > /tmp/...` e `PATCH_END` (incluindo essas duas linhas). Cole no terminal da VPS e pressione Enter. Depois execute:

```bash
bash /tmp/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh
```

### Opção D — Comando único (criar e rodar na VPS)

Na VPS, execute:

```bash
# Baixar o script do repo (se o repo estiver em um URL acessível)
# Exemplo com curl (ajustar URL conforme o repo):
# curl -sL "https://raw.githubusercontent.com/SEU_ORG/painel.pixel12digital/main/docs/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh" -o /tmp/patch.sh

# Ou: criar o arquivo manualmente e colar o conteúdo de docs/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh
# Depois:
chmod +x /tmp/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh
bash /tmp/VPS_SCRIPT_PATCH_CONNECTION_UPDATE.sh
```

---

### I.1 Handler connection.update no receiveWebhookHandler (se aplicar manual)

**Onde:** Logo após o gateway receber o evento e antes/depois de enfileirar. Inserir no bloco que processa `rawEvent` ou `eventType`.

**Lógica a inserir:**

```javascript
// connection.update: atualizar sessionManager para status correto na UI
if ((eventType === 'connection.update' || (rawEvent && rawEvent.event === 'connection.update')) && (rawEvent || payload)) {
  const ev = rawEvent || payload;
  const sessionId = ev?.session?.id || ev?.session || ev?.sessionId || ev?.channel;
  const connStatus = ev?.connection?.status || ev?.raw?.payload?.state || ev?.state || '';
  const statusLower = String(connStatus).toLowerCase();
  if (sessionId) {
    if (['close', 'closed', 'disconnected', 'unavailable'].includes(statusLower)) {
      sessionManager.updateSessionStatus(sessionId, 'disconnected');
      logger.info('Session status updated from connection.update', { sessionId, status: 'disconnected' });
    } else if (['available', 'open', 'connected'].includes(statusLower)) {
      sessionManager.updateSessionStatus(sessionId, 'connected');
    }
  }
}
```

**IMPORTANTE:** Garantir que `sessionManager` e `logger` estejam no escopo (require no topo do index.js).

### I.2 Priorizar sessionManager em GET /api/channels

**Onde:** Na rota `GET /api/channels`, no loop que percorre `sessions`, antes de chamar `getSessionStatus`.

**Lógica atual (conceito):**
```javascript
const statusResult = await wppconnectAdapter.getSessionStatus(sessionId, ...);
const status = statusResult?.status || 'unknown';
sessionManager.updateSessionStatus(sessionId, status);
```

**Alterar para:**
```javascript
// Priorizar status do sessionManager se for disconnected (evento connection.update)
const storedSession = sessionManager.getSession(sessionId);
const storedStatus = storedSession?.status || storedSession?.metadata?.status;
let status;
if (storedStatus === 'disconnected') {
  status = 'disconnected';
} else {
  const statusResult = await wppconnectAdapter.getSessionStatus(sessionId, ...);
  status = statusResult?.status || storedStatus || 'unknown';
}
sessionManager.updateSessionStatus(sessionId, status);
```

**Nota:** Se `getSession` não retornar `status` no objeto, usar a chave correta (ex.: `metadata.status`). A saída do Bloco H3 dirá a estrutura exata.

---

## Bloco J — Reload / Restart

```bash
# Se o gateway roda via PM2
pm2 restart gateway-wrapper 2>/dev/null || true

# Se roda via Docker
docker restart gateway-wrapper 2>/dev/null || true

# Se aplicou patch em arquivos montados e há wrapper
cd /opt/pixel12-whatsapp-gateway 2>/dev/null && pm2 restart all 2>/dev/null || docker restart gateway-wrapper
```

---

## Bloco K — Verificação

```bash
SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels" 2>/dev/null | head -200
```

**Esperado após patch:** Se pixel12digital tiver recebido `connection.update` com `state: "close"` antes do teste, o status deve aparecer como `disconnected`. Caso contrário, continua `connected` (WPPConnect não emitiu o evento).

---

## Rollback

Restaurar backup dos arquivos alterados (index.js, api.js) e reiniciar o gateway.

---

## Critério de aceite

1. Quando o WPPConnect enviar `connection.update` com `state: "close"` para uma sessão, o `GET /api/channels` deve retornar `status: "disconnected"` para essa sessão.
2. O Pixel Hub exibe "Desconectado" (ou equivalente) na tela de Configurações > WhatsApp Gateway.
3. Sessões zombie (sem `connection.update`) continuam mostrando "Conectado" até o usuário clicar em Reconectar.

---

## Referências

- `docs/PACOTE_VPS_PATCH_CONNECTION_UPDATE_STATUS.md`
- `docs/DIAGNOSTICO_STATUS_FALSO_CONECTADO.md`
- `docs/INVESTIGACAO_UI_STATUS_CONECTADO_INCONSISTENTE.md`
