# Bloco VPS — Sessão pixel12digital + mensagem 5511984078606

**Objetivo:**  
1. Entender por que a UI mostrava "desconectado" mas ao forçar QR Code a conexão foi estabelecida (sem exibir QR).  
2. Verificar se existe auto-reconexão e por que não está evitando perda de mensagens.  
3. Investigar se a mensagem de 5511984078606 (Adriana, 09/02 ~07:14) chegou ao gateway.

**Contexto:** Sessão pixel12digital em https://wpp.pixel12digital.com.br:8443/ui/sessoes/ aparecia desconectada; ao clicar em "QR Code" conectou sem exibir QR. Mensagem da Adriana não chegou ao Pixel Hub.

**Risco:** Zero — apenas leitura.

**Combinado:** Cursor prepara blocos VPS; Charles executa e retorna outputs. Código local e banco PixelHub o Cursor executa.

---

## BLOCO 1 — Estado real da sessão + logs da mensagem

**Onde rodar:** SSH da VPS (wpp.pixel12digital.com.br)

**Comandos (copiar/colar):**

```bash
echo "=== 1) Estado real da sessão pixel12digital (via API do gateway) ==="
GW_IP=$(docker inspect gateway-wrapper --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' 2>/dev/null)
[ -z "$GW_IP" ] && GW_IP="127.0.0.1"
echo "Gateway IP: $GW_IP"
curl -s "http://${GW_IP}:3000/api/channels/pixel12digital" 2>/dev/null || curl -s "http://localhost:3000/api/channels/pixel12digital" 2>/dev/null
echo ""
curl -s "http://${GW_IP}:3000/api/channels" 2>/dev/null | head -80 || curl -s "http://localhost:3000/api/channels" 2>/dev/null | head -80

echo ""
echo "=== 2) gateway-wrapper: eventos pixel12digital em 09/02 (mensagem 5511984078606) ==="
docker logs gateway-wrapper 2>&1 | grep -E "2026-02-09" | grep -i "pixel12digital" | head -50

echo ""
echo "=== 3) gateway-wrapper: onmessage para pixel12digital (últimas 72h) ==="
docker logs gateway-wrapper --since 72h 2>&1 | grep -i "onmessage" | grep -i "pixel12digital" | tail -30

echo ""
echo "=== 4) gateway-wrapper: alguma linha contendo 5511984078606 ou 11984078606 (72h) ==="
docker logs gateway-wrapper --since 72h 2>&1 | grep -E "5511984078606|11984078606" | tail -20
```

**Retornar:** Saída completa dos comandos 1, 2, 3 e 4.

---

**Após o Charles retornar:** Cursor analisa, consolida resumo e define próximo bloco (ex.: auto-reconnect, configuração da UI) ou patch local.

---

## RESULTADO BLOCO 1 (09/02/2026)

### Outputs recebidos

**1) API /channels:** Retornou `Missing X-Gateway-Secret header` — o bloco não passou o secret. Próximo bloco incluirá header.

**2) Eventos 09/02 pixel12digital:** 50+ linhas "Webhook delivered successfully" entre 00:00 e 00:14 UTC. O log não expõe `eventType` nem `sessionId` na linha; possível que sejam de outra sessão ou evento (presence, connection) — não necessariamente `onmessage`.

**3) onmessage pixel12digital (72h):** Último `onmessage` em **08/02 01:14:10 UTC** (correlationId c3755b91). Nenhum `onmessage` para pixel12digital em **09/02**.

**4) 5511984078606 / 11984078606:** Nenhuma ocorrência nos logs.

### Resumo

- Não houve `onmessage` para pixel12digital em 09/02; o último foi em 08/02 01:14 UTC.
- A mensagem da Adriana (07:14 BRT ≈ 10:14 UTC em 09/02) não aparece nos logs do gateway.
- Conclusão: a sessão pixel12digital estava **desconectada** quando a mensagem foi enviada; o WPPConnect não emitiu `onmessage` e a mensagem não chegou ao gateway.
- Ao clicar em "QR Code" na UI, a reconexão foi feita e a sessão voltou a funcionar.

### Hipótese sobre UI vs estado real

A UI em `:8443` refletia o estado real: sessão desconectada. O clique em "QR Code" provavelmente disparou um `GET /api/channels/pixel12digital/qr` ou similar, que forçou o WPPConnect a tentar reconectar; se a sessão ainda tinha token válido, reconectou sem mostrar QR.

### Próximo bloco (BLOCO 2)

Objetivos:
1. Ver status da sessão via API (com `X-Gateway-Secret`).
2. Identificar tipo dos webhooks de 09/02 (para saber se são de pixel12digital e qual eventType).
3. Checar se há lógica de auto-reconnect no gateway-wrapper.

---

## BLOCO 2 — Status com secret + tipo dos webhooks + auto-reconnect

**Onde rodar:** SSH da VPS (wpp.pixel12digital.com.br)

**Pré-requisito:** Obter o secret do gateway: `docker exec gateway-wrapper env 2>/dev/null | grep -iE 'GATEWAY_SECRET|X_GATEWAY'`  
Use o valor na variável `SECRET` abaixo (substitua `SEU_SECRET_AQUI`).

**Comandos (copiar/colar):**

```bash
# Defina o secret (obtido do comando acima). Se não tiver, deixe vazio e pule 1a/1b.
SECRET="SEU_SECRET_AQUI"
GW_IP=$(docker inspect gateway-wrapper --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' 2>/dev/null)
[ -z "$GW_IP" ] && GW_IP="127.0.0.1"

echo "=== 1a) Status da sessão pixel12digital (com X-Gateway-Secret) ==="
[ -n "$SECRET" ] && curl -s -H "X-Gateway-Secret: $SECRET" "http://${GW_IP}:3000/api/channels/pixel12digital" || echo "(SECRET vazio - pule)"

echo ""
echo "=== 1b) Lista de canais (com secret) ==="
[ -n "$SECRET" ] && curl -s -H "X-Gateway-Secret: $SECRET" "http://${GW_IP}:3000/api/channels" || echo "(SECRET vazio - pule)"

echo ""
echo "=== 2) Tipo dos webhooks 09/02 - evento 5f0a7081 (Received + Delivered) ==="
docker logs gateway-wrapper 2>&1 | grep "5f0a7081-907d-4a8c-936a-c3a55c7bed6c"

echo ""
echo "=== 3) Tipos de evento recebidos em 09/02 (contagem por eventType) ==="
docker logs gateway-wrapper 2>&1 | grep -E "2026-02-09" | grep "Received webhook" | grep -oE '"eventType":"[^"]+"' | sort | uniq -c | sort -rn

echo ""
echo "=== 4) gateway-wrapper: há auto-reconnect ou keep-alive? ==="
docker exec gateway-wrapper grep -rn "reconnect\|keep.alive\|auto.reconnect\|disconnect" /app --include="*.js" 2>/dev/null | head -25
```

**Retornar:** Saída completa dos comandos 1a, 1b, 2, 3 e 4.

---

## RESULTADO BLOCO 2 (09/02/2026)

### Outputs recebidos

**1a) Status pixel12digital:** `{"success":true,"channel":{"id":"pixel12digital","name":"pixel12digital","status":"connected"}}` — sessão **conectada** (após reconexão manual via UI).

**1b) Lista de canais:** pixel12digital e imobsites ambos `status: connected`.

**2) Evento 5f0a7081:** `sessionId: "ImobSites"`, `eventType: "connection.update"` — evento de **ImobSites**, não pixel12digital; tipo `connection.update`.

**3) Tipos de evento em 09/02:**
- 435× `onpresencechanged`
- 17× `status-find`
- **0× `onmessage`**

**4) Auto-reconnect no gateway-wrapper:** Nenhum match (grep vazio). O gateway-wrapper **não implementa** auto-reconnect — é apenas proxy.

### Conclusões finais

1. **Mensagem 5511984078606:** A sessão pixel12digital estava desconectada quando a mensagem foi enviada; não houve `onmessage` em 09/02. A mensagem foi perdida no WhatsApp/WPPConnect.
2. **UI vs estado real:** A UI refletia o estado correto (desconectado). O clique em "QR Code" disparou reconexão; como o token ainda era válido, conectou sem exibir QR.
3. **Auto-reconnect:** O gateway-wrapper não tem lógica de reconexão. O comportamento de reconexão está no WPPConnect (container `wppconnect-server`) ou na camada de UI.
4. **Webhooks 09/02:** Os webhooks entregues eram majoritariamente `onpresencechanged` e `status-find` (ImobSites e possivelmente outras sessões), não `onmessage`.

### Recomendações

- **Evitar perda de mensagens:** Investigar no WPPConnect (`wppconnect-server`) se existe auto-reconnect/keep-alive e por que a sessão pixel12digital desconectou entre 08/02 01:14 e 09/02.
- **Alternativa:** Implementar healthcheck/cron que, ao detectar sessão desconectada, chame `GET /api/channels/{id}/qr` para forçar tentativa de reconexão (como o clique manual na UI).
