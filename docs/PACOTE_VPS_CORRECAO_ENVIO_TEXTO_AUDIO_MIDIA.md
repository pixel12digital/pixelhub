# Pacote de Execução VPS — Correção envio texto, áudio e mídia (liberar /api do Basic Auth)

**Data:** 27/01/2026  
**Objetivo:** Permitir enviar **textos, áudio e mídias/imagens** pelo Hub **sem bloqueio** (sem 401 Basic Auth em `/api/*`).

**Contexto:** Após rollback na VPS, a config do Nginx voltou a exigir Basic Auth em todas as rotas. O Hub chama o gateway em `https://wpp.pixel12digital.com.br:8443/api/...`; com Basic Auth em `/api/*` o Nginx retorna 401 e o Hub traduz em 400 "Erro de autenticação com o gateway". A correção é **reaplicar na VPS** a config que libera `/api/*` do Basic Auth.

---

## Formato do Pacote (copiar/colar para o Charles)

**VPS – OBJETIVO:** Liberar `/api/*` do Basic Auth para que o Hub consiga enviar texto, áudio e mídia sem 401.  
**SERVIÇO:** nginx (vhost 8443 – `00-wpp.pixel12digital.com.br.conf`).  
**RISCO:** Médio (altera config Nginx e faz reload).  
**ROLLBACK:** Ver seção 4.

---

### 1) Pré-check (não muda nada)

**Comandos (copiar/colar e devolver os outputs):**

```bash
# 1. Conferir se o vhost existe
ls -la /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# 2. Ver se já existe location /api/
grep -n "location.*/api/\|location /ui/\|location / \|location = /" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf | head -20

# 3. Ver se há "return 302 /ui/" (necessário para o patch mínimo)
grep -n "return 302 /ui/" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
```

**O que retornar:** Saída dos três comandos (lista do arquivo, linhas das locations, linha do `return 302 /ui/` se existir).

---

### 2) Execução — Opção A (recomendada): script AUTH-J

O script **AUTH-J** (`docs/bloco-charles-auth-j-inversao-logica.sh`) faz: (1) backup; (2) insere `location /ui/` com Basic Auth; (3) **remove** Basic Auth de `location /`. Com isso, `/api/*` passa a ser atendido por `location /` **sem** auth — texto, áudio e mídia deixam de dar 401.

**Requisito:** O ficheiro de config deve ter a linha `location / {` (o catch-all). Se o pré-check mostrou essa linha, **basta** executar o AUTH-J.

**Comando:** Copiar **todo** o conteúdo do ficheiro `docs/bloco-charles-auth-j-inversao-logica.sh` e colar no terminal (como root).

**Se o pré-check mostrou que existe `location = /` com `return 302 /ui/` mas *não* existe `location ^~ /api/`:** pode aplicar antes o bloco da Opção B (inserir `location ^~ /api/` de forma explícita) e depois o AUTH-J; ou **apenas** o AUTH-J — neste último caso, como o AUTH-J tira auth de `location /`, `/api/` já fica liberado.

---

### 2) Execução — Opção B: só patch mínimo “/api/ sem Basic Auth”

Use se o pré-check mostrou `return 302 /ui/` e **não** existe `location /api/`. Este bloco insere `location ^~ /api/ { auth_basic off; ... }` logo após o fechamento do bloco `location = /`.

**Comandos (copiar/colar um bloco único):**

```bash
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"
if grep -q "location /api/" "$CONF"; then echo "AVISO: location /api/ já existe. Pode pular para AUTH-J ou verificação."; exit 0; fi
sudo cp "$CONF" "${CONF}.bak.$(date +%Y%m%d_%H%M%S)"
INSERT_AFTER=$(awk '/return 302 \/ui\//{found=1; next} found && /^[[:space:]]*\}[[:space:]]*$/{print NR; exit}' "$CONF" 2>/dev/null || true)
[ -z "$INSERT_AFTER" ] && INSERT_AFTER=$(awk '/return 302 \/ui\//{found=1; next} found && /^    \}$/{print NR; exit}' "$CONF" 2>/dev/null || true)
if [ -z "$INSERT_AFTER" ]; then echo "ERRO: Não encontrou fechamento de location = / com return 302 /ui/"; exit 1; fi
PATCH=$(mktemp)
cat > "$PATCH" << 'EOF'

    location ^~ /api/ {
        auth_basic off;

        proxy_pass http://172.19.0.1:3000;
        proxy_http_version 1.1;

        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;

        proxy_buffering off;
        proxy_cache off;
    }

EOF
sudo sed -i "${INSERT_AFTER}r $PATCH" "$CONF"
rm -f "$PATCH"
grep -n "location /api/" "$CONF"
```

**Arquivo tocado:** `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` — inserção do bloco `location ^~ /api/ { auth_basic off; ... }`.

---

### 3) Reinício/Reload

**Comandos:**

```bash
sudo nginx -t && sudo service nginx reload
```

**O que retornar:** Saída de `nginx -t` e a mensagem de reload do nginx.

---

### 4) Verificação

**Comandos (copiar/colar e devolver os outputs):**

```bash
# A) /api/messages sem Basic Auth — esperado: 200 ou 400, NÃO 401
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"

# B) /ui/ deve pedir Basic Auth — esperado: 401 sem credenciais
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 \
  "https://127.0.0.1:8443/ui/" -H "Host: wpp.pixel12digital.com.br"
```

**Critério de sucesso:**
- **(A)** `http_code=200` ou `http_code=400` (nunca 401) → `/api/` está liberado do Basic Auth.
- **(B)** `http_code=401` → UI continua protegida.
- **No Hub:** testar envio de **texto**, **áudio** e **imagem/mídia** e confirmar que não aparece mais “Erro de autenticação com o gateway” (400 por 401).

---

### 5) Rollback (se algo der errado)

**Comandos:**

```bash
BACKUP=$(ls -t /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf.bak.* 2>/dev/null | head -1)
if [ -n "$BACKUP" ]; then sudo cp "$BACKUP" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf; sudo nginx -t && sudo service nginx reload; echo "Rollback feito de $BACKUP"; else echo "Nenhum backup encontrado."; fi
```

**O que volta:** Config anterior (com Basic Auth em tudo, como estava após o rollback).

---

### 6) Critério de aceite (resumo)

- **VPS:** Após executar Opção A (ou B + AUTH-J), reload e verificações:
  - `curl` em `https://127.0.0.1:8443/api/messages` retorna 200 ou 400 (nunca 401).
  - `curl` em `https://127.0.0.1:8443/ui/` retorna 401 sem credenciais.
- **Hub:** Envio de **texto**, **áudio** e **imagem/mídia** funciona sem mensagem de “Erro de autenticação com o gateway”.

---

## Referências

| O quê | Onde |
|-------|------|
| Script AUTH-J (inversão de lógica: /ui/ com auth, / e /api/ sem auth) | `docs/bloco-charles-auth-j-inversao-logica.sh` |
| Patch mínimo “/api/ sem Basic Auth” | `docs/patch-nginx-liberar-api-basic-auth.sh`, `docs/bloco-charles-patch-nginx-api.sh` |
| Config Nginx na VPS | `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` |
| Resumo 401 e próximos passos | `docs/RESUMO_401_NGINX_DIAGNOSTICO_27JAN2026.md` |
| Regra operacional / formato do pacote | `docs/REGRA_OPERACIONAL_VPS.md`, `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` |
