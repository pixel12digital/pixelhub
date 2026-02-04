# Pacote VPS – Diagnóstico: por que onMessage não chega ao gateway-wrapper

**VPS – OBJETIVO:** Identificar por que eventos `onmessage` do WPPConnect não chegam ao gateway-wrapper (apenas `onpresencechanged` aparece nos logs).  
**SERVIÇO:** gateway-wrapper + wppconnect-server (somente leitura, diagnóstico).  
**RISCO:** Nenhum — apenas consulta de configuração e código.

---

## Contexto

- WPPConnect **emite** `onMessage` (vimos nos logs).
- gateway-wrapper **recebe** apenas `onpresencechanged` → `connection.update`.
- Áudio 11:38 de 81642320 nunca chegou ao banco.

**Hipótese:** O WPPConnect pode não estar enviando `onMessage` para o webhook do gateway-wrapper, ou o gateway-wrapper filtra/ignora.

---

## BLOCO 1 – Configuração do gateway-wrapper (webhook de entrada)

**Comandos (copiar/colar):**

```bash
echo "=== 1.1) Variáveis de ambiente do gateway-wrapper ==="
docker exec gateway-wrapper env 2>/dev/null | grep -iE "webhook|url|callback|event|onmessage" | sort

echo ""
echo "=== 1.2) Porta e rota de webhook no gateway-wrapper ==="
docker exec gateway-wrapper ls -la /app/ 2>/dev/null | head -20
docker exec gateway-wrapper find /app -name "*.js" -path "*route*" 2>/dev/null | head -10
docker exec gateway-wrapper find /app -name "*.js" | xargs grep -l "webhook\|onmessage\|onMessage" 2>/dev/null | head -5

echo ""
echo "=== 1.3) Trecho de código que registra/recebe webhook do WPPConnect ==="
docker exec gateway-wrapper grep -rn "onpresencechanged\|onmessage\|Received webhook" /app --include="*.js" 2>/dev/null | head -30
```

**Retorne:** Saída completa dos comandos acima.

---

## BLOCO 2 – Como o WPPConnect envia eventos ao gateway-wrapper

**Comandos (copiar/colar):**

```bash
echo "=== 2.1) IP/host do gateway-wrapper (para WPPConnect chamar) ==="
docker inspect gateway-wrapper --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' 2>/dev/null
docker network inspect bridge 2>/dev/null | grep -A5 "gateway-wrapper\|wppconnect" | head -20

echo ""
echo "=== 2.2) Configuração de webhook no WPPConnect (env ou arquivo) ==="
docker exec wppconnect-server env 2>/dev/null | grep -iE "webhook|url|callback|gateway" | sort
docker exec wppconnect-server find / -name "*.env" -o -name "*.json" 2>/dev/null | head -10
docker exec wppconnect-server cat /app/.env 2>/dev/null | grep -iE "webhook|url|callback" || echo "Arquivo .env não encontrado ou sem matches"

echo ""
echo "=== 2.3) Onde WPPConnect registra/usa webhook para enviar eventos ==="
docker exec wppconnect-server grep -rn "webhook\|emit.*message\|onMessage" /app --include="*.js" 2>/dev/null | head -40
```

**Retorne:** Saída completa dos comandos acima.

---

## BLOCO 3 – Verificar se há evento onmessage em 03/02 ~11:38

**Comandos (copiar/colar):**

```bash
echo "=== 3.1) gateway-wrapper: eventos onmessage para pixel12digital (48h) ==="
docker logs gateway-wrapper --since 48h 2>&1 | grep -i "onmessage" | grep -i "pixel12digital" | tail -20

echo ""
echo "=== 3.2) gateway-wrapper: TODOS os eventType recebidos (últimas 48h) ==="
docker logs gateway-wrapper --since 48h 2>&1 | grep "Received webhook event" | grep "pixel12digital" | sed 's/.*eventType":"\([^"]*\)".*/\1/' | sort | uniq -c | sort -rn
```

**Retorne:** Saída dos comandos. O 3.2 mostra a contagem por tipo de evento (onpresencechanged, onmessage, etc.).

---

## Critério de sucesso do diagnóstico

- Identificar se o gateway-wrapper **recebe** `onmessage` de alguma sessão.
- Identificar se o WPPConnect está configurado para **enviar** `onmessage` ao webhook do gateway-wrapper.
- Se não houver config de webhook para onmessage no WPPConnect → causa provável encontrada.

---

## Rollback

Não aplicável — apenas leitura.
