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

### BLOCO VPS AUTH-C — Por que `curl 127.0.0.1:8443/api/messages` retorna 401?

**Objetivo:** Saber se o 401 vem de (a) outro vhost sendo o default em 8443 quando não há `Host`, ou (b) do próprio vhost wpp tendo Basic Auth em `/api/`.

**Contexto:** O teste sem `Host` já retornou 401. O Hub chama `https://wpp.pixel12digital.com.br:8443/api/...` e envia `Host: wpp.pixel12digital.com.br`. Se com esse Host ainda der 401, o problema é no vhost wpp (location /api/ não está liberando). Se com Host der 200/400/404, o default em 8443 sem Host é outro vhost.

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-C1: curl COM Host wpp (simula o Hub) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\n" -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"

echo ""
echo "=== AUTH-C2: Quem escuta em 8443 e qual é default? ==="
grep -rn "listen.*8443" /etc/nginx/ 2>/dev/null

echo ""
echo "=== AUTH-C3: server_name e default_server na 8443 ==="
grep -rn "listen.*8443\|server_name\|default_server" /etc/nginx/conf.d/ 2>/dev/null
```

**Retorno esperado do Charles:**  
- **AUTH-C1:** um número (`http_code=401`, `http_code=200`, `http_code=400`, etc.).  
  - Se **401 com Host** → o vhost wpp está pedindo Basic Auth em `/api/` (location /api/ não está valendo ou há outro bloco).  
  - Se **200/400/404 com Host** → sem Host o default em 8443 é outro vhost; o Hub (que envia Host) está ok e o 401 do Hub tem outra causa.  
- **AUTH-C2 e AUTH-C3:** saída completa para ver se há outro `server` em 8443 e qual é o default.

---

### BLOCO VPS AUTH-D — Qual server block responde a Host: wpp na 8443? (sites-enabled)

**Objetivo:** AUTH-C1 deu 401 **com** Host wpp → o vhost que atende está pedindo Basic Auth em /api/. Pode ser o 00-wpp (location /api/ não valendo) ou **outro** vhost em **sites-enabled** que também escuta 8443 e tem server_name wpp. Este bloco descobre qual ficheiro está ativo e o que ele define.

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-D1: O que está em sites-enabled? ==="
ls -la /etc/nginx/sites-enabled/

echo ""
echo "=== AUTH-D2: Em cada enabled, há listen 8443 ou server_name wpp/pixel12? ==="
for f in /etc/nginx/sites-enabled/*; do
  [ -e "$f" ] || continue
  has=$(grep -lE "listen.*8443|server_name.*wpp\.|server_name.*pixel12" "$f" 2>/dev/null)
  if [ -n "$has" ]; then
    echo "--- $f (contém 8443 ou wpp/pixel12) ---"
    grep -n "listen\|server_name\|auth_basic\|location /api\|location /" "$f" 2>/dev/null | head -80
  fi
done

echo ""
echo "=== AUTH-D3: No 00-wpp (conf.d), ordem dos location e auth_basic ==="
grep -n "location\|auth_basic" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
```

**Retorno esperado do Charles:**  
- **AUTH-D1:** lista de symlinks/ficheiros em sites-enabled.  
- **AUTH-D2:** para cada ficheiro que tenha `listen 8443` ou `server_name` wpp/pixel12: trecho com listen, server_name, auth_basic e location.  
- **AUTH-D3:** numeração das linhas de location e auth_basic no 00-wpp, para confirmar que `location /api/` existe e vem antes de `location /`.

Com isso dá para ver se um **outro** vhost em sites-enabled está a capturar o Host wpp na 8443 (e a pedir Basic Auth em tudo) ou se é o 00-wpp que ainda aplica Basic Auth em /api/.

---

### BLOCO VPS AUTH-E — Conteúdo do ficheiro “wpp” em sites-enabled + config compilada

**Objetivo:** AUTH-D mostrou um ficheiro **wpp.pixel12digital.com.br** em sites-enabled (1 byte) e o 00-wpp com `location /api/` e `auth_basic off` na ordem certa, mas o curl continua 401. Precisamos de (1) o conteúdo desse ficheiro de 1 byte e (2) a **config compilada** que o Nginx usa para o server_name wpp na 8443, para ver se há outro bloco ou include a sobrescrever.

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-E1: Conteúdo do ficheiro wpp em sites-enabled (xxd + cat) ==="
ls -la /etc/nginx/sites-enabled/wpp.pixel12digital.com.br
xxd /etc/nginx/sites-enabled/wpp.pixel12digital.com.br
cat -A /etc/nginx/sites-enabled/wpp.pixel12digital.com.br

echo ""
echo "=== AUTH-E2: Ordem de include em nginx.conf (sites-enabled vs conf.d) ==="
grep -n "include.*sites-enabled\|include.*conf.d" /etc/nginx/nginx.conf

echo ""
echo "=== AUTH-E3: Config compilada — bloco server 8443 default_server ==="
nginx -T 2>/dev/null | grep -B2 -A85 "listen 8443 ssl http2 default_server"
```

**Retorno esperado do Charles:**  
- **AUTH-E1:** saída de xxd e cat -A do ficheiro wpp (para ver o byte exacto).  
- **AUTH-E2:** linhas do nginx.conf com include de sites-enabled e conf.d (ordem de carga).  
- **AUTH-E3:** trecho da config compilada do server que usa 8443 default_server, para confirmar locations e auth_basic.

Se o ficheiro wpp tiver conteúdo relevante (ex.: outro server ou include), ou se na config compilada aparecer outro bloco para wpp/8443, isso explica o 401. Caso contrário, o próximo passo é garantir que `location /api/` tem precedência (ex.: usar `location ^~ /api/` para forçar prefix e parar search).

---

### BLOCO VPS AUTH-F — Primeira resposta e headers (redirect vs app 401)

**Objetivo:** A config compilada está certa: `location /api/` tem `auth_basic off`. O 401 pode ser (a) **redirect**: o app devolve 302, o curl segue e o 401 vem do segundo pedido (ex.: GET / ou /ui/ com Basic Auth); (b) **app**: o backend em 172.19.0.1:3000 devolve 401 por secret inválido, sem `WWW-Authenticate: Basic`. Este bloco pede a **primeira** resposta (**sem** seguir redirects) e os headers, para distinguir.

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-F1: Primeira resposta apenas (--max-redirs 0), com headers ==="
curl -sk -i --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages" 2>&1 | head -30

echo ""
echo "=== AUTH-F2: Código da primeira resposta (sem redirect) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\nnum_redirects=%{num_redirects}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"
```

**Retorno esperado do Charles:**  
- **AUTH-F1:** primeiras linhas da resposta (status + headers).  
  - Se aparecer `HTTP/1.1 302` e `Location: ...` → a primeira resposta é redirect; o 401 que víamos antes vinha do pedido seguido (ex.: / ou /ui/ com Basic Auth).  
  - Se aparecer `HTTP/1.1 401` e `WWW-Authenticate: Basic` → Nginx está a pedir Basic Auth em /api/ (contra a config compilada; vale checar de novo o vhost).  
  - Se aparecer `HTTP/1.1 401` **sem** `WWW-Authenticate: Basic` → o 401 é do **app** (secret inválido); aí o Nginx está OK e o Hub deve usar o secret correcto.  
- **AUTH-F2:** `http_code` e `num_redirects` da primeira resposta (confirmar 0 redirects quando usamos --max-redirs 0).

---

### BLOCO VPS AUTH-G — Patch: `location ^~ /api/` (forçar precedência sobre `location /`)

**Causa raiz (AUTH-F):** A primeira resposta a POST /api/messages é 401 do Nginx com `WWW-Authenticate: Basic`. Dois `location` só de prefixo (`/` e `/api/`) não garantem “mais específico ganha”; em Nginx a prioridade é por modificador. **Solução:** usar `location ^~ /api/` para dar prioridade explícita ao prefixo `/api/` sobre `location /`.

**Objetivo:** Alterar em `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` a linha `location /api/` para `location ^~ /api/`, testar, recarregar e verificar com o mesmo curl.

**Comandos (copiar só o bloco):**

```bash
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"

echo "=== AUTH-G1: Backup ==="
sudo cp "$CONF" "${CONF}.bak.$(date +%Y%m%d_%H%M%S)"
ls -la "${CONF}.bak."* 2>/dev/null | tail -1

echo ""
echo "=== AUTH-G2: Aplicar patch (location /api/ -> location ^~ /api/) ==="
sudo sed -i 's/\([[:space:]]*\)location \/api\/ /\1location ^~ \/api\/ /' "$CONF"
grep -n "location.*/api/" "$CONF"

echo ""
echo "=== AUTH-G3: nginx -t e reload ==="
sudo nginx -t && sudo service nginx reload

echo ""
echo "=== AUTH-G4: Verificação — curl /api/messages (esperado: 200 ou 400, não 401) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"
```

**Retorno esperado do Charles:**  
- **AUTH-G1:** confirmação do backup.  
- **AUTH-G2:** linha alterada deve mostrar `location ^~ /api/`.  
- **AUTH-G3:** `nginx -t` ok e reload feito.  
- **AUTH-G4:** `http_code=200` ou `http_code=400` (resposta do app), **não** 401. Se ainda 401, colar a saída completa de AUTH-G2 e do trecho de `location` do ficheiro.

**Rollback (se necessário):**
```bash
BACKUP=$(ls -t /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf.bak.* 2>/dev/null | head -1)
sudo cp "$BACKUP" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
sudo service nginx reload
```

---

### BLOCO VPS AUTH-H — Provar server{}, location{} e origem do Basic Auth (somente leitura)

**Objetivo:** Parar de tentar “mais um patch” e **provar** qual `server{}` e qual `location{}` estão a ser usados para `/api/messages`, e **onde** o Basic Auth é definido. Somente leitura — nada é alterado.

**Objetivo A** — Provar o `server{}` real que atende 8443 para Host wpp  
**Objetivo B** — Provar se existe qualquer outro `server`/`listen 8443`  
**Objetivo C** — Provar onde o Basic Auth é definido (todas as ocorrências de auth_basic, auth_basic_user_file, satisfy, auth_request, com contexto e arquivo de origem).

**Critério de aceite do AUTH-H:** Com os retornos do Charles, o Cursor consegue apontar em **1 frase**:  
*“O request cai no server X e no location Y; o Basic Auth está a ser aplicado por Z (diretiva/arquivo).”*

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-H Objetivo A: bloco completo do server 8443 wpp (trecho do 00-wpp em nginx -T) ==="
nginx -T 2>/dev/null | sed -n '/# configuration file.*00-wpp\.pixel12digital\.com\.br\.conf/,$p' | head -200

echo ""
echo "=== AUTH-H Objetivo B: todos listen 8443 com arquivo de origem ==="
nginx -T 2>/dev/null | awk '/# configuration file \//{f=$0} /listen.*8443/{print f; print; print "---"}' 

echo ""
echo "=== AUTH-H Objetivo C: auth_basic, auth_basic_user_file, satisfy, auth_request com contexto (2 linhas antes/depois) ==="
echo "(Cruzar números de linha com o trecho A para ver em qual arquivo/server/location está cada ocorrência.)"
nginx -T 2>/dev/null | grep -n -B2 -A2 "auth_basic\|auth_basic_user_file\|satisfy\|auth_request"

**Retorno esperado do Charles:**  
- **A:** trecho completo do `nginx -T` correspondente ao ficheiro `00-wpp.pixel12digital.com.br.conf` (todos os `location` e qualquer `include` dentro do server).  
- **B:** cada bloco que contém `listen 8443`, precedido da linha `# configuration file /path` (arquivo de origem).  
- **C:** todas as linhas que contêm `auth_basic`, `auth_basic_user_file`, `satisfy` ou `auth_request`, com 2 linhas antes/depois e indicação do ficheiro de origem (ou, na alternativa, numeração de linha e contexto para cruzar com o trecho de A).  

Com isso o Cursor deve conseguir escrever a frase única: *“O request cai no server X e no location Y; o Basic Auth está a ser aplicado por Z (diretiva/arquivo).”*

---

### BLOCO VPS AUTH-I — Caminho 2.1: Marcação de location (provar qual location responde ao 401)

**Contexto:** O AUTH-H confirmou que o server é o 00-wpp (único em 8443), que tem `location ^~ /api/ { auth_basic off; ... }` e `location / { auth_basic ... }`. O 401 com WWW-Authenticate: Basic indica que o request está a cair em **location /** em vez de **location ^~ /api/**. Este bloco **prova** qual location responde, adicionando um header distinto em cada um (reversível).

**Objetivo:** Em `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`, adicionar `add_header X-Location-Match "api" always;` em `location ^~ /api/` e `add_header X-Location-Match "root" always;` em `location /` (o que tem auth_basic). O `always` faz o header aparecer também em respostas 401. Depois: nginx -t, reload, e curl -i para ver qual header vem junto do 401.

**Comandos (copiar só o bloco):**

```bash
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"

echo "=== AUTH-I1: Backup ==="
sudo cp "$CONF" "${CONF}.bak.$(date +%Y%m%d_%H%M%S)"
ls -la "${CONF}.bak."* 2>/dev/null | tail -1

echo ""
echo "=== AUTH-I2: Adicionar X-Location-Match em location ^~ /api/ e em location / (00-wpp) ==="
sudo sed -i '/auth_basic off;/a\        add_header X-Location-Match "api" always;' "$CONF"
sudo sed -i '/auth_basic_user_file \/etc\/nginx\/.htpasswd_wpp\.pixel12digital\.com\.br;/a\        add_header X-Location-Match "root" always;' "$CONF"
grep -n "X-Location-Match\|location.*/api/\|location /" "$CONF" | head -20

echo ""
echo "=== AUTH-I3: nginx -t e reload ==="
sudo nginx -t && sudo service nginx reload

echo ""
echo "=== AUTH-I4: curl -i (esperado: ver X-Location-Match no 401 — api ou root?) ==="
curl -sk -i --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages" 2>&1 | head -25
```

**Retorno esperado do Charles:**  
- **AUTH-I4:** primeiras linhas da resposta (status + headers). O importante é o valor de **X-Location-Match**:  
  - **X-Location-Match: root** → o request está a cair em **location /**; o path `/api/messages` não está a fazer match em `location ^~ /api/` (reescrita, normalização ou comportamento inesperado). Próximo passo: investigar por que o match cai em / ou aplicar **Caminho 2.2** (inversão de lógica: Basic só em /ui/, nada em / ou em server).  
  - **X-Location-Match: api** → o request está a cair em **location ^~ /api/** e mesmo assim vem 401; então `auth_basic off` está a ser sobrescrito. Próximo passo: procurar o que re-aplica Basic Auth ou aplicar **Caminho 2.2**.

**Rollback (remover os headers de diagnóstico):**
```bash
sudo sed -i '/add_header X-Location-Match "api" always;/d' "$CONF"
sudo sed -i '/add_header X-Location-Match "root" always;/d' "$CONF"
sudo nginx -t && sudo service nginx reload
```

**Resultado AUTH-I (27/01/2026):** O curl -i **não** mostrou o header X-Location-Match na resposta 401. O Nginx pode devolver 401 na fase de auth **antes** de executar add_header, pelo que o 2.1 não provou qual location respondeu. **Próximo passo:** aplicar **Caminho 2.2 — Inversão de lógica** (BLOCO AUTH-J).

---

### BLOCO VPS AUTH-J — Caminho 2.2: Inversão de lógica de auth

**Contexto:** O AUTH-I não mostrou X-Location-Match no 401 (add_header pode não correr em respostas 401 do auth_basic). Em vez de depender do match de location, **inverte-se a lógica**: Basic Auth **só** em `location = /` e em `location /ui/`; `location /` e `location ^~ /api/` ficam **sem** Basic Auth. Assim `/api/*` deixa de receber Basic Auth independentemente da ordem de match.

**Objetivo:** (1) Remover os headers X-Location-Match (rollback AUTH-I). (2) Inserir `location /ui/ { auth_basic ...; proxy_pass ... }` **antes** de `location /`. (3) Em `location /`, remover auth_basic e auth_basic_user_file (ficar só proxy).

**Comandos:** Usar o script `docs/bloco-charles-auth-j-inversao-logica.sh`. Copiar **todo** o conteúdo do ficheiro e colar no terminal da VPS (como root). O script faz backup, remove X-Location-Match, insere /ui/ com auth, remove auth de location /, nginx -t, reload e curl de verificação.

**Critério de aceite:** AUTH-J7 (curl /api/messages) deve devolver **200 ou 400**, não 401. AUTH-J8 (curl /ui/) deve devolver **401** (UI continua protegida).

**Rollback:** Restaurar do backup mais recente (copiar/colar este bloco):
```bash
BACKUP=$(ls -t /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf.bak.* 2>/dev/null | head -1)
sudo cp "$BACKUP" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
sudo service nginx reload
```

**Se AUTH-J7 continuar 401** após correr o script (ex.: o sed em AUTH-J4 não alterou o `location /` por diferença de formato), usar o **AUTH-J-FIX** abaixo.

---

### BLOCO VPS AUTH-J-FIX — Forçar auth_basic off só no location / (2.ª ocorrência)

**Quando usar:** AUTH-J já foi executado (existe `location /ui/`), mas AUTH-J7 continua **401**. O sed por intervalo pode não ter casado; este bloco altera **apenas a 3.ª ocorrência** de `auth_basic "Acesso Restrito..."` e de `auth_basic_user_file ... wpp...` (que pertence ao `location /`), deixando o `location = /` e o `location /ui/` com auth.

**Comandos (copiar só o bloco):**

```bash
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"

echo "=== AUTH-J-FIX1: Estado actual de auth_basic no ficheiro ==="
grep -n "auth_basic\|auth_basic_user_file" "$CONF"

echo ""
echo "=== AUTH-J-FIX2: Substituir 3.ª ocorrência de auth_basic por auth_basic off (é a do location /) ==="
awk '/auth_basic "Acesso Restrito - Gateway WhatsApp";/{c++; if(c==3) $0="        auth_basic off;"} 1' "$CONF" > /tmp/ngx_fix.conf && sudo cp /tmp/ngx_fix.conf "$CONF" && rm -f /tmp/ngx_fix.conf

echo ""
echo "=== AUTH-J-FIX3: Apagar 3.ª ocorrência de auth_basic_user_file (wpp) ==="
awk '/auth_basic_user_file \/etc\/nginx\/.htpasswd_wpp\.pixel12digital\.com\.br;/{c++; if(c==3) next} 1' "$CONF" > /tmp/ngx_fix.conf && sudo cp /tmp/ngx_fix.conf "$CONF" && rm -f /tmp/ngx_fix.conf

echo ""
echo "=== AUTH-J-FIX4: Conferir e recarregar ==="
grep -n "auth_basic\|auth_basic_user_file" "$CONF"
sudo nginx -t && sudo service nginx reload

echo ""
echo "=== AUTH-J-FIX5: curl /api/messages (esperado: 200 ou 400) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"
```

**Nota:** Se o sed de “3.ª ocorrência” falhar na sua versão, use antes o **intervalo** com padrões mais largos (ex.: `[[:space:]]*`). Em último caso, editar à mão: no bloco `location / { ... }` (o que faz proxy para 172.19.0.1:3000 e não é /ui/), trocar `auth_basic "Acesso Restrito - Gateway WhatsApp";` por `auth_basic off;` e apagar a linha `auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;`.

**Retorno esperado:** AUTH-J-FIX5 deve mostrar **http_code=200** ou **http_code=400**, não 401.

**Resultado AUTH-J-FIX (27/01/2026):** AUTH-J-FIX1 mostrou só **2** ocorrências de "Acesso Restrito..." (linhas 34 e 60) e **2** de auth_basic off (40, 80). Ou seja, o `location /` **já tinha** auth_basic off (linha 80) — o AUTH-J tinha corrigido. Ainda assim o curl a /api/messages devolve **401**. Conclusão: o pedido **não** está a cair em `location ^~ /api/` nem em `location /`; pode haver rewrite/normalização do path ou efeito de HTTP/2. Próximo passo: **AUTH-K** — testar com HTTP/1.1.

---

### BLOCO VPS AUTH-K — Testar HTTP/1.1 vs HTTP/2 (mesmo path e host)

**Quando usar:** O config já tem `location ^~ /api/` e `location /` com auth_basic off, mas o curl a /api/messages continua **401**. Pode ser efeito de HTTP/2 (curl usa HTTP/2 por defeito em https). Este bloco repete o mesmo pedido **forçando HTTP/1.1** e compara o código.

**Comandos (copiar só o bloco):**

```bash
echo "=== AUTH-K1: curl com HTTP/2 (default) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"

echo ""
echo "=== AUTH-K2: curl com HTTP/1.1 explícito ==="
curl -sk --http1.1 -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"
```

**Retorno esperado:** Se AUTH-K2 for **200 ou 400** e AUTH-K1 for **401**, o problema é específico de HTTP/2. Se ambos forem 401, não é HTTP/2.

**Resultado AUTH-K (27/01/2026):** AUTH-K1 e AUTH-K2 **ambos 401**. Não é efeito de HTTP/2. O config já tem `location ^~ /api/` e `location /` com auth_basic off; o curl local a /api/messages continua 401 com ambos os protocolos. **Próximos passos possíveis:** (1) **Caminho 2.3** — `error_log ... debug` temporário para ver qual location o Nginx escolhe; (2) **Retestar do Hub** — enviar áudio real; o PHP/cURL do Hub pode comportar-se de forma diferente do curl local (SNI, rede, etc.) e o 401 pode já não ocorrer.

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
| Blocos VPS AUTH-A / AUTH-B / … / AUTH-H | Este doc (AUTH-H: provar server/location e origem do Basic Auth, somente leitura) |
| Regra de triangulação | `.cursor/rules/regra-vps.mdc` |
