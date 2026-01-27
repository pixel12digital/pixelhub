# Classificação do 401 e blocos VPS Auth (27/01/2026)

**Objetivo:** Classificar o 401 (Basic Auth vs Secret) com evidência única; decisão automática; blocos VPS somente leitura para o Charles.

**Contexto:** Timeout morreu ao corrigir porta para :8443. Agora o request vai para :8443, responde em ~2s e retorna 401/UNAUTHORIZED. O gargalo deixou de ser rede/timeout e passou a ser autenticação.

---

## 1. Próximo passo determinístico: 1 evidência

### 1.1 Rodada “1 evidência” com o request_id novo

Com o próximo erro UNAUTHORIZED (ou o último já ocorrido), o payload de erro do Hub deve trazer **obrigatoriamente** (implementado no HostMedia):

| Campo | Descrição |
|-------|-----------|
| **effective_url** | URL efetiva (com porta) |
| **primary_ip** | IP do servidor que respondeu |
| **http_code** | 401 |
| **content_type** | Tipo do body |
| **resp_headers_preview** | Só: `server`, `www-authenticate`, `via`, `cf-ray` (se existir), `date` |
| **body_preview** | Início do body (curto, ex.: 300 chars) |
| **request_id** | ID da requisição |
| **secret_sent** | `{ "present": bool, "len": int, "fingerprint": "8 chars hex" }` — **nunca** o valor puro |

**Regra:** não interpretar. Usar a evidência crua: “401 veio com **WWW-Authenticate: Basic …**” ou “401 veio **JSON** do gateway dizendo secret inválido”, etc.

---

## 2. Ajustes no HostMedia (implementados)

### 2.1 Fluxo de erro UNAUTHORIZED

No payload de erro (JSON ao frontend) entram:

- **resp_headers_preview** — somente: `server`, `www-authenticate`, `via`, `cf-ray` (se existir), `date`
- **body_preview** — curto (ex.: 300 chars), como em `gateway_html_error`
- **request_id** — mantido
- **secret_sent** — `{ "present": bool, "len": int, "fingerprint": "8 chars de SHA256" }` — sem expor o valor

### 2.2 Log seguro do secret no Hub

No retorno 401 do cliente (WhatsAppGatewayClient), já está incluído **secret_sent**:

- **secret_sent.present** — `true`/`false`
- **secret_sent.len** — tamanho em caracteres
- **secret_sent.fingerprint** — primeiros 8 caracteres do SHA256 em hex (nunca o valor puro)

Isso evita “eu acho que o Hub mandou” quando na prática mandou vazio ou com decrypt falhando.

---

## 3. Decisão automática depois de classificar o 401

### Cenário 1 — 401 com `WWW-Authenticate: Basic …`

- **Significado:** Basic Auth (Nginx ou upstream).
- **Ação:**
  - Se a UI (/ui) deve continuar protegida, manter como está.
  - A **API** (/api/*) precisa:
    - **ou** ficar fora do Basic Auth e o Hub autenticar só via **X-Gateway-Secret**,  
    - **ou** o Hub enviar `Authorization: Basic ...` (pior opção: mistura credencial com tráfego interno).
  - **Recomendação:** Basic Auth só em /ui; /api/* liberado do Basic Auth e autenticação do Hub apenas por secret.

### Cenário 2 — 401 **sem** `WWW-Authenticate`, resposta do gateway (JSON/erro custom)

- **Significado:** Validação do **X-Gateway-Secret** (ou equivalente) no gateway.
- **Ação:**
  1. Confirmar no gateway (VPS) qual secret ele espera (variável/env/config).
  2. Garantir que o **WPP_GATEWAY_SECRET** do Hub, **após decrypt**, é **exatamente** esse valor.
  3. Retestar o envio de áudio.

---

## 4. Blocos VPS para o Charles (somente leitura, um por vez)

**Regra:** Cursor pede **um bloco por vez**; Charles cola, executa e devolve os outputs completos. Só depois disso o próximo bloco.

---

### BLOCO VPS AUTH-A — Basic Auth no caminho (:8443)

**Objetivo:** Saber se o 401 vem de `auth_basic` no Nginx (ou include).

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-A: auth_basic / auth_basic_user_file no vhost 8443 ==="
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"
grep -n "auth_basic\|auth_basic_user_file" "$CONF" 2>/dev/null || echo "Nenhum auth_basic em $CONF"

echo ""
echo "=== Includes desse server (se houver) ==="
grep -n "include" "$CONF" 2>/dev/null

echo ""
echo "=== Outros conf.d que mencionam wpp/8443 ==="
grep -l "8443\|wpp\.pixel12\|wpp.pixel12digital" /etc/nginx/conf.d/*.conf 2>/dev/null
for f in $(grep -l "8443\|wpp\.pixel12\|wpp.pixel12digital" /etc/nginx/conf.d/*.conf 2>/dev/null); do
  echo "--- $f ---"
  grep -n "auth_basic\|auth_basic_user_file\|listen.*8443\|server_name" "$f" 2>/dev/null
done
```

**Retorno esperado do Charles:**  
Linhas exatas onde aparece `auth_basic` (se existir) e o arquivo/caminho.  
Se não existir em nenhum deles, responder: “Nenhum auth_basic encontrado nos arquivos listados”.

---

### BLOCO VPS AUTH-B — App em 172.19.0.1:3000 e onde o secret é configurado

**Objetivo:** Ver qual processo/container atende a 3000 e onde o secret (X-Gateway-Secret ou equivalente) é configurado.

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-B1: Processo escutando em 3000 ==="
ss -tlnp 2>/dev/null | grep 3000 || netstat -tlnp 2>/dev/null | grep 3000

echo ""
echo "=== AUTH-B2: Docker (se houver) — containers com 3000/wpp/gateway ==="
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Ports}}' 2>/dev/null | grep -E "3000|wpp|gateway" || echo "Nenhum container encontrado com 3000/wpp/gateway"

echo ""
echo "=== AUTH-B3: PM2 — apps e env (sem colar valor de secret) ==="
pm2 list 2>/dev/null
pm2 env 0 2>/dev/null | grep -iE "secret|gateway|auth|token" | sed 's/=.*/=***/' 2>/dev/null || true

echo ""
echo "=== AUTH-B4: Nome das variáveis de auth/secret no diretório do app (não o valor) ==="
# Ajustar o caminho se o app estiver em outro diretório (ex.: /var/www/wpp-ui ou onde o PM2 aponta)
PM2_CWD=$(pm2 show 0 2>/dev/null | grep "exec cwd" | sed 's/.*cwd *: *//') || true
if [ -n "$PM2_CWD" ] && [ -d "$PM2_CWD" ]; then
  grep -rIn "X-Gateway-Secret\|Gateway-Secret\|GATEWAY_SECRET\|secret\|auth" "$PM2_CWD" --include="*.js" --include="*.ts" --include=".env" 2>/dev/null | head -30
else
  echo "PM2 cwd não encontrado; informar manualmente o diretório do app"
fi
```

**Regra de segurança:** Charles **não** cola o secret inteiro. Só informa: “existe/não existe”, tamanho e, se necessário, hash curto (ex.: 8 chars) — **nunca** o valor em texto puro.

**Retorno esperado do Charles:**  
Outputs de AUTH-B1 a AUTH-B4; para variáveis de secret, apenas “existe/não existe” e nome da variável, **sem** valor.

---

## 5. Sequência operacional (sem ambiguidade)

1. **Retestar áudio** uma vez com :8443 já ajustado no Hub.
2. **Capturar** do erro: `request_id` + payload completo (com **resp_headers_preview**, **body_preview**, **secret_sent**).
3. **Classificar:** Basic Auth (WWW-Authenticate: Basic) vs validação de Secret (401 sem WWW-Authenticate, JSON do gateway).
4. **Só então:**
   - **Se Basic Auth** → ajustar Nginx/upstream para liberar /api/* do Basic Auth (ou outra ação combinada).
   - **Se Secret** → alinhar o secret (gateway ↔ Hub) e retestar.
5. Se depois disso voltar **timeout/travar**, aí se retoma o plano D+E (logs por etapa + timeout por etapa no gateway).

---

## 6. Referência rápida

| Item | Onde |
|------|------|
| Cliente: retorno 401 com resp_headers_preview, body_preview, secret_sent | `WhatsAppGatewayClient::request()` |
| Controller: repasse no UNAUTHORIZED | `CommunicationHubController::send()` |
| Blocos VPS AUTH-A / AUTH-B | Este doc, seções 4.1 e 4.2 |
| Regra de triangulação | `.cursor/rules/regra-vps.mdc` |
