# Resumo: diagnóstico 401 Nginx e avanços/retrocessos (27/01/2026)

**Objetivo:** Resumo atualizado de descobertas, comandos, avanços/retrocessos, erros/acertos e próximos passos na investigação do 401 em `/api/messages` no gateway wpp.pixel12digital.com.br:8443.

---

## 1. Contexto e problema

| Item | Descrição |
|------|-----------|
| **Sintoma** | Após corrigir a porta do Hub para `:8443`, o envio de áudio passou a responder em ~2 s com **401 UNAUTHORIZED** e "Erro de autenticação com o gateway". |
| **Evidência** | Payload de erro do Hub traz **WWW-Authenticate: Basic realm="Acesso Restrito - Gateway WhatsApp"** e body HTML do Nginx → 401 vindo de **Basic Auth no Nginx**, não do app em 172.19.0.1:3000. |
| **Config em uso** | Nginx em `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` já tinha `location /api/ { auth_basic off; ... }` **antes** de `location /` com Basic Auth. Mesmo assim o request a `/api/messages` retornava 401. |

---

## 2. Comandos e blocos executados (VPS srv817568)

### 2.1 AUTH-C — curl com Host wpp + quem escuta 8443

**Comandos:** curl com `Host: wpp.pixel12digital.com.br` para `https://127.0.0.1:8443/api/messages`; `grep -rn "listen.*8443"`; `grep -rn "listen.*8443|server_name|default_server"` em conf.d.

**Resultado:** AUTH-C1 = **401** mesmo com Host wpp → o vhost que atende wpp na 8443 está a pedir Basic Auth em `/api/`. AUTH-C2 listou vários configs em sites-available com listen 8443; AUTH-C3 mostrou 00-wpp com default_server e server_name wpp.

### 2.2 AUTH-D — sites-enabled + ordem dos location no 00-wpp

**Comandos:** `ls -la /etc/nginx/sites-enabled/`; loop em cada enabled com grep em listen 8443 / server_name wpp; `grep -n "location|auth_basic"` no 00-wpp.

**Resultado:** AUTH-D1: sites-enabled tem `whatsapp-multichannel` (symlink) e ficheiro **wpp.pixel12digital.com.br** (1 byte). AUTH-D2: vazio (nenhum enabled tem listen 8443 nem server_name wpp). AUTH-D3: no 00-wpp a ordem está certa — `location /api/` (linha 39) com `auth_basic off` (40) vem **antes** de `location /` (59) com Basic Auth.

### 2.3 AUTH-E — Conteúdo do ficheiro wpp em sites-enabled + config compilada

**Comandos:** `xxd` e `cat -A` do ficheiro wpp; `grep -n "include.*sites-enabled|include.*conf.d"` no nginx.conf; `nginx -T | grep -B2 -A85 "listen 8443 ssl http2 default_server"`.

**Resultado:** AUTH-E1: ficheiro wpp tem só **0x0a** (newline) — não define server. AUTH-E2: **conf.d** é carregado **antes** de sites-enabled (linhas 59–60). AUTH-E3: config compilada está **certa** — um único server 8443 default_server, com `location /api/ { auth_basic off; ... }` antes de `location /` com Basic Auth.

### 2.4 AUTH-F — Primeira resposta e headers (redirect vs app 401)

**Comandos:** `curl -sk -i --max-redirs 0 ... /api/messages` com Host wpp e X-Gateway-Secret: FAKE_FOR_TEST; depois `curl -sk -o /dev/null -w "http_code=%{http_code}\nnum_redirects=%{num_redirects}\n" --max-redirs 0 ...`.

**Resultado:** AUTH-F1: **primeira** resposta é **401 do Nginx** — `HTTP/2 401`, `www-authenticate: Basic realm="Acesso Restrito - Gateway WhatsApp"`, `x-server-block: wpp-gateway`, body HTML "401 Authorization Required" nginx/1.24.0. AUTH-F2: `http_code=401`, `num_redirects=0`. Conclusão: **não** é redirect; **não** é 401 do app; é Nginx a pedir Basic Auth em `/api/messages` na primeira resposta.

### 2.5 AUTH-G — Patch `location ^~ /api/` (forçar precedência)

**Hipótese:** Em Nginx, dois `location` só de prefixo (`/` e `/api/`) não garantem “mais específico ganha”; a prioridade é por modificador. Solução testada: usar **`location ^~ /api/`** para dar prioridade explícita.

**Comandos:** backup do 00-wpp; `sed -i 's/\([[:space:]]*\)location \/api\/ /\1location ^~ \/api\/ /' "$CONF"`; `nginx -t && service nginx reload`; mesmo curl de verificação.

**Resultado:** AUTH-G1: backup criado. AUTH-G2: linha alterada para `location ^~ /api/ {`. AUTH-G3: `nginx -t` ok, reload ok. AUTH-G4: **http_code=401** — **continua 401** após o patch.

---

## 3. Avanços e retrocessos

| Avanço | Detalhe |
|--------|---------|
| **Rota e porta** | Hub passou a usar `:8443`; o request vai para o vhost certo e responde em ~2 s (não timeout). |
| **Classificação do 401** | Ficou claro que o 401 é **Basic Auth do Nginx** (WWW-Authenticate: Basic, body HTML nginx), não do app. |
| **Config em disco e compilada** | O ficheiro 00-wpp e a config compilada (`nginx -T`) mostram `location /api/ { auth_basic off; ... }` **antes** de `location /`. |
| **Sem redirect** | AUTH-F provou que a primeira resposta já é 401; não é efeito de seguir redirect para `/` ou `/ui/`. |
| **Ficheiro wpp em sites-enabled** | Tem 1 byte (newline); não define outro server; não explica o 401. |
| **Ordem de include** | conf.d antes de sites-enabled; o server 8443 default_server vem do 00-wpp. |

| Retrocesso / bloqueio | Detalhe |
|----------------------|---------|
| **location /api/ não “ganha”** | Mesmo com `location /api/` e `auth_basic off` antes de `location /`, o pedido a `/api/messages` recebe 401 do Nginx. |
| **Patch ^~ não resolveu** | Trocar para `location ^~ /api/` foi aplicado, reload feito, e o curl a `/api/messages` continua a devolver **401**. |
| **Causa ainda em aberto** | Não está claro por que o Nginx aplica Basic Auth a um request que, pela documentação e pela config compilada, deveria cair em `location /api/` com `auth_basic off`. |

---

## 4. Erros e acertos

| Tipo | Descrição |
|------|-----------|
| **Acerto** | Sequência de blocos (AUTH-C → AUTH-D → AUTH-E → AUTH-F) foi correta para ir eliminando hipóteses (outro vhost, redirect, 401 do app). |
| **Acerto** | Uso de `--max-redirs 0` e `-i` no curl permitiu ver que a primeira resposta é 401 do Nginx, não de redirect. |
| **Acerto** | Consulta a docs/Stack Overflow sobre prioridade de `location` levou ao teste do modificador `^~`. |
| **Retrocesso** | Aplicar `location ^~ /api/` **não** removeu o 401 em AUTH-G4 — causa raiz ainda não é “só” falta de modificador no prefixo. |

---

## 5. Identificação de erros e diagnósticos

### 5.1 O que está confirmado

1. **Quem responde:** Nginx (server nginx/1.24.0, x-server-block: wpp-gateway, body HTML padrão 401).
2. **Onde:** vhost que escuta 8443 default_server, server_name wpp.pixel12digital.com.br (00-wpp).
3. **O quê:** Pedido `POST /api/messages` com `Host: wpp.pixel12digital.com.br` recebe 401 com WWW-Authenticate: Basic na **primeira** resposta, sem redirects.
4. **Config em disco e compilada:** `location /api/` (depois `location ^~ /api/`) com `auth_basic off` existe e aparece antes de `location /` na config compilada.

### 5.2 O que ainda não está explicado

1. **Por que o Nginx devolve 401 para `/api/messages`** mesmo com `location ^~ /api/ { auth_basic off; ... }` ativo após reload.
2. Se existe **include**, **map**, **if** ou outro mecanismo (dentro do server ou de um include) que aplique Basic Auth depois da decisão de location.
3. Se o pedido está a ser servido por **outro** server block (por exemplo outro ficheiro que define listen 8443 e que não foi listado em AUTH-C/AUTH-D).
4. Se há particularidade de **HTTP/2** ou do **request URI** (normalização, encoding) que faça o match cair em `location /` em vez de `location ^~ /api/`.

---

## 6. Próximos passos recomendados

### 6.1 Imediatos (VPS)

1. **Confirmar config compilada pós-AUTH-G**  
   Rodar na VPS:
   ```bash
   nginx -T 2>/dev/null | grep -B2 -A90 "listen 8443 ssl http2 default_server"
   ```
   Verificar se no bloco activo aparece **`location ^~ /api/`** e **`auth_basic off`** e se não existe outro `location` ou directiva que force Basic Auth para `/api/`.

2. **Incluir auth só onde é necessário (inversão de lógica)**  
   Em vez de “auth em `location /` e desligar em `location /api/`”, usar:
   - **Sem** auth_basic ao nível do server.
   - `location = /` e `location /` que **exigem** Basic Auth: manter `auth_basic "..."` e `auth_basic_user_file ...` apenas aí.
   - `location ^~ /api/`: manter `auth_basic off` e o proxy.
   Garantir que não há nenhum `auth_basic` ou `auth_basic_user_file` ao nível do server (e que nenhum include global injecta auth nesse server).

3. **Procurar includes e auth no server**  
   No 00-wpp, procurar qualquer `include` dentro do bloco `server` e, na config compilada, ver se há `auth_basic` ou `auth_basic_user_file` aplicados ao mesmo bloco ou a um contexto pai desse server.

### 6.2 Se o 401 persistir

4. **Confirmar qual server block atende o request**  
   Com um request real (por exemplo repetir o curl com Host e path iguais ao do Hub), usar `nginx -T` e comparar todos os blocos `listen 8443` e respectivos `server_name`. Garantir que o único que pode atender `Host: wpp.pixel12digital.com.br` na 8443 é o do 00-wpp.

5. **Testar com HTTP/1.1**  
   `curl -sk --http1.1 ...` para descartar efeito específico de HTTP/2 na escolha de location.

6. **Log de debug do Nginx (temporário)**  
   Se possível, definir `error_log ... debug` para esse vhost, repetir o curl e ver nos logs qual `location` o Nginx escolheu para o request.

### 6.3 BLOCO AUTH-H (obrigatório antes de novo patch)

**Antes de qualquer novo patch**, executar o **BLOCO AUTH-H** (somente leitura) para **provar**:

- **Objetivo A:** bloco completo do `server{}` que atende 8443 para Host wpp (todos os `location` e `include` dentro do server).
- **Objetivo B:** se existe qualquer outro `listen 8443` em `nginx -T`, com ficheiro de origem.
- **Objetivo C:** todas as ocorrências de `auth_basic`, `auth_basic_user_file`, `satisfy`, `auth_request` com contexto e origem.

**Critério de aceite:** O Cursor consegue apontar em **1 frase**:  
*“O request cai no server X e no location Y; o Basic Auth está a ser aplicado por Z (diretiva/arquivo).”*

O BLOCO AUTH-H está em `docs/CLASSIFICACAO_401_E_BLOCOS_VPS_AUTH.md`. Sem o retorno do AUTH-H, não aplicar mais patches “no arquivo certo” — já falhou duas vezes.

### 6.4 Depois do AUTH-H — caminhos 2.1 / 2.2 / 2.3

Se o AUTH-H confirmar que o server é o correto e mesmo assim `/api` não “vence”:

| Caminho | Descrição | Critério de aceite |
|--------|-----------|---------------------|
| **2.1 Marcação de location** | Patch mínimo reversível: `add_header X-Location-Match "api"` em `location ^~ /api/` e `add_header X-Location-Match "root"` em `location /`. Curl -i mostra qual location respondeu junto do 401. | Se o header indicar `/api` e mesmo assim vier Basic → auth_basic off está a ser sobrescrito. Se indicar `/` ou `/ui` → o request não está a fazer match em `/api` (reescrita/normalização). |
| **2.2 Inversão de lógica de auth** | Nenhum Basic Auth ao nível do server; Basic só nos locations de UI (`/ui/`, talvez `location = /`); `/api/` explicitamente sem Basic. | curl em `/api/health` e `/api/messages` **não** tem WWW-Authenticate: Basic. |
| **2.3 Debug de seleção de location** | Só se 2.1 e 2.2 falharem. Log temporário (ex.: `error_log ... debug`) para ver qual location foi escolhido. | Log mostra o location usado para o request a `/api/messages`. |

Quem analisar o retorno do AUTH-H decide qual dos caminhos (2.1 / 2.2 / 2.3) é o próximo passo único.

### 6.4.1 Conclusão do AUTH-H (após retorno do Charles)

**Frase única (critério de aceite):**

> O request cai no **server** do ficheiro `00-wpp.pixel12digital.com.br.conf` (listen 8443 default_server, server_name wpp) e, pela evidência do 401 com WWW-Authenticate: Basic, está a ser atendido pelo **location /** em vez do **location ^~ /api/**; o Basic Auth está a ser aplicado pelas directivas **auth_basic** e **auth_basic_user_file** dentro desse **location /** no mesmo ficheiro.

**Provas:** (A) Só o 00-wpp escuta 8443; não há includes nem outro server nesse bloco. (B) Só o 00-wpp tem listen 8443. (C) No 00-wpp, auth_basic/auth_basic_user_file estão em `location = /`, em `location ^~ /api/` (auth_basic off) e em `location /`. O request a `/api/messages` **deveria** fazer match em `location ^~ /api/`, mas o 401 indica que **está a cair em location /** — ou o match não está a escolher ^~ /api/, ou há reescrita/normalização antes do match.

**Próximo passo único:** **Caminho 2.1 — Marcação de location** (BLOCO AUTH-I): adicionar um header distinto em cada location e repetir o curl para **provar** qual location responde ao 401.

**Resultado do AUTH-I (27/01/2026):** O curl -i **não** mostrou o header **X-Location-Match** na resposta 401 (nem "api" nem "root"). O Nginx devolve 401 na fase de auth **antes** de executar add_header, pelo que o 2.1 não permitiu provar qual location respondeu. **Decisão:** aplicar **Caminho 2.2 — Inversão de lógica** (BLOCO AUTH-J): Basic Auth só em `location = /` e em `location /ui/`; `location /` e `/api/` sem Basic Auth.

### 6.5 Hub / produto

7. **Usar `effective_url` no próximo 401**  
   O controller já repassa `effective_url` no payload de 401. No próximo 401 em produção, confirmar que a URL usada é de facto `https://wpp.pixel12digital.com.br:8443/api/...` e não outra porta/path.

8. **Alinhar WPP_GATEWAY_SECRET (após Nginx liberar /api)**  
   Quando o Nginx parar de exigir Basic Auth em `/api/*`, o próximo “gate” volta a ser o app em 172.19.0.1:3000 a validar X-Gateway-Secret. Aí faz sentido validar WPP_GATEWAY_SECRET do Hub vs secret do gateway e seguir D+E (logs por etapa).

### 6.6 Resultado AUTH-K e estado actual

**AUTH-K:** HTTP/2 e HTTP/1.1 **ambos** devolvem **401** para curl local a /api/messages. Não é efeito de HTTP/2. O config já tem `location ^~ /api/` e `location /` com auth_basic off; o curl local continua 401. **Opções:** (1) Retestar do Hub (envio de áudio real); (2) Caminho 2.3 — error_log debug para ver qual location o Nginx escolhe.

---

## 7. Referência rápida de blocos e ficheiros

| Bloco / acção | Onde está | Propósito |
|---------------|------------|-----------|
| AUTH-C | CLASSIFICACAO_401_E_BLOCOS_VPS_AUTH.md | curl com Host wpp; quem escuta 8443 |
| AUTH-D | idem | sites-enabled; ordem location no 00-wpp |
| AUTH-E | idem | conteúdo ficheiro wpp; config compilada |
| AUTH-F | idem | primeira resposta e headers (redirect vs app) |
| AUTH-G | idem | patch `location ^~ /api/`; verificação curl |
| **AUTH-H** | idem | **provar server{}, location{} e origem do Basic Auth (somente leitura)** — obrigatório antes de novo patch |
| **AUTH-I** | idem | **Caminho 2.1 — Marcação de location**: add_header X-Location-Match em /api/ e em /; curl -i. Resultado: 401 sem header (add_header não corre em 401 do auth). |
| **AUTH-J** | bloco-charles-auth-j-inversao-logica.sh | **Caminho 2.2 — Inversão de lógica** (location /ui/ + auth só em =/ e /ui/) |
| **AUTH-K** | CLASSIFICACAO_401_E_BLOCOS_VPS_AUTH.md | Testar HTTP/1.1 vs HTTP/2. Resultado: ambos 401 — não é HTTP/2. |
| Patch scripts | patch-nginx-liberar-api-basic-auth.sh, bloco-charles-patch-nginx-api.sh | já usam `location ^~ /api/` |
| Resumo anterior | RESUMO_ULTIMOS_PASSOS_TIMEOUT_E_AUTH_27JAN2026.md | porta 443→8443; primeiro 401 após ajuste |

---

## 8. Resumo em uma frase

O 401 em `/api/messages` é **Basic Auth do Nginx** (WWW-Authenticate: Basic, body HTML nginx), na primeira resposta, sem redirects; a config em disco e compilada mostra `location /api/` (depois `location ^~ /api/`) com `auth_basic off` antes de `location /`, mas o patch **`location ^~ /api/`** não removeu o 401; os próximos passos são confirmar a config compilada pós-patch, inverter a lógica para que auth exista só nos locations que precisam, e procurar includes/auth ao nível do server ou em conflito com o match de `/api/`.
