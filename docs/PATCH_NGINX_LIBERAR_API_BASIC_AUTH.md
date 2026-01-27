# Patch Nginx: liberar /api/* do Basic Auth (vhost 8443)

**Data:** 27/01/2026  
**Contexto:** Basic Auth no Nginx em :8443 bloqueia o Hub; o Hub chama `/api/messages`, `/api/channels` etc. e recebe 401 antes de chegar ao app em 172.19.0.1:3000.  
**Objetivo:** Manter Basic Auth para navegação humana (/ui/, /) e **liberar** apenas `/api/*` do Basic Auth.

**Arquivo na VPS:** `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`

---

## 1. Situação atual (Passo 1 — já confirmada pelo Charles)

- **location = /** — auth_basic on, redirect 302 → /ui/
- **location /** — auth_basic on, proxy_pass 172.19.0.1:3000 — **atende tudo** (incluindo /api/*), por isso o Hub leva 401
- **location /.well-known/acme-challenge/** — sem auth

Não existe `location /api/`. Tudo que não é `/` nem `.well-known` cai em `location /` e exige Basic Auth.

---

## 2. Patch mínimo

Inserir um **novo** bloco `location /api/` **entre** `location = /` e `location /`, com **auth_basic off** e o mesmo proxy do `location /`. O Nginx escolhe o match mais específico; `/api/*` passa a cair nesse bloco e não no `location /`.

**Trecho a inserir** (logo após o `}` do `location = /`, ou seja, depois da linha que hoje contém `return 302 /ui/;` e o `}`):

```nginx
    location /api/ {
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

```

Ou seja: o arquivo passa a ter, nesta ordem:

1. `location = /` { ... }  
2. **`location /api/` { auth_basic off; ... }**  ← novo  
3. `location /` { auth_basic on; ... }  
4. `location /.well-known/acme-challenge/` { ... }

---

## 3. Aplicar via script (recomendado)

### Opção A — Bloco único para colar no terminal da VPS

O Charles **não precisa** criar o arquivo manualmente. Basta colar **todo** o conteúdo do arquivo **`docs/bloco-charles-patch-nginx-api.sh`** no terminal da VPS (como root ou com `sudo bash`). Esse bloco:

1. Cria o script em `~/patch-nginx-liberar-api-basic-auth.sh`
2. Dá permissão de execução e roda `sudo bash ~/patch-nginx-liberar-api-basic-auth.sh`

**Arquivo:** `docs/bloco-charles-patch-nginx-api.sh` — copiar **inteiro** e colar no terminal.

### Opção B — Script já no repositório

Se o Charles tiver o arquivo **`docs/patch-nginx-liberar-api-basic-auth.sh`** na máquina dele:

1. Copiar o conteúdo para a VPS em `~/patch-nginx-liberar-api-basic-auth.sh` (ex.: colar no nano ou upload).
2. Executar: `sudo bash ~/patch-nginx-liberar-api-basic-auth.sh`

**Critério:** O script só altera o arquivo se ainda não existir `location /api/`. Se já existir, termina sem erro.

---

## 4. Pacote de execução manual para o Charles (Passo 2 + Passo 3)

**O Cursor manda este bloco único ao Charles.** Charles cola no terminal da VPS, executa e devolve a saída completa.

### 3.1 Backup, patch, teste e reload

```bash
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"

echo "=== Backup com timestamp ==="
sudo cp "$CONF" "${CONF}.bak.$(date +%Y%m%d_%H%M%S)"
ls -la "${CONF}.bak."* 2>/dev/null | tail -1

echo ""
echo "=== Inserir location /api/ ANTES do bloco 'location /' (após 'location = /') ==="
echo "Usar edição manual ou o sed abaixo."
echo "O sed insere o bloco logo após a linha que contém 'return 302 /ui/;' (primeira ocorrência no arquivo)."
echo ""

# Cria o bloco a ser inserido em um arquivo temp
TMP_BLOCK=$(mktemp)
cat > "$TMP_BLOCK" << 'ENDBLOCK'

    location /api/ {
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

ENDBLOCK

# Encontra a linha do "}" que fecha "location = /" (linha 37 no output do Charles: após "return 302 /ui/;")
# Inserir após a linha que contém "    }" e vem depois de "return 302 /ui/;"
# Abordagem: inserir após a primeira "    }" que existe após "location = /" — no arquivo atual isso é a linha que fecha "location = /"
sudo sed -i '/return 302 \/ui\//,/^    }$/{
  /^    }$/r '"$TMP_BLOCK"'
  /^    }$/q
}' "$CONF" 2>/dev/null

# Alternativa se o sed acima falhar: aviso para editar à mão
if ! grep -q "location /api/" "$CONF"; then
  echo "AVISO: sed não inseriu o bloco. Inserir manualmente:"
  echo "  Abra $CONF"
  echo "  Após o bloco 'location = /' { ... } (após a linha com 'return 302 /ui/;' e o '}' seguinte)"
  echo "  Cole o conteúdo de location /api/ (auth_basic off + proxy_pass ...) antes do 'location /'"
  cat "$TMP_BLOCK"
fi
rm -f "$TMP_BLOCK"

echo ""
echo "=== Conferir se location /api/ existe ==="
grep -n "location /api/" "$CONF"

echo ""
echo "=== Testar config Nginx ==="
sudo nginx -t

echo ""
echo "=== Reload Nginx (srv817568: service, não systemd) ==="
sudo service nginx reload

echo ""
echo "=== Fim. Se nginx -t e reload foram OK, retestar áudio pelo Hub. ==="
```

**Nota:** O `sed` acima pode não funcionar em todas as versões (BSD vs GNU, regex). Se após rodar o bloco `grep "location /api/" "$CONF"` não mostrar nada, o Charles deve **inserir à mão** o bloco `location /api/` entre o `}` do `location = /` e o `location /`, conforme a seção 2, e depois rodar só:

```bash
sudo nginx -t && sudo service nginx reload
```

---

## 5. Inserção manual (se o script não puder ser usado)

1. Abrir no editor: `sudo nano /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`
2. Localizar o bloco:

   ```nginx
    location = / {
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
        return 302 /ui/;
    }

    location / {
   ```

3. **Entre** o `}` do `location = /` e o `location /`, colar:

   ```nginx
    location /api/ {
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

   ```

4. Salvar, depois: `sudo nginx -t && sudo service nginx reload`

---

## 6. Critério de aceite

| Etapa | Critério |
|-------|----------|
| **nginx -t** | Saída: `syntax is ok` e `test is successful` |
| **service nginx reload** | Sem mensagem de erro |
| **Passo 4 (Hub)** | Charles faz 1 envio de áudio (4–10 s). **Esperado:** não haver mais 401 com `WWW-Authenticate: Basic`. Sucesso ou outro erro (ex.: do app/secret), e tempo até a resposta. Se ainda vier 401 Basic, o patch não está ativo ou o path não é /api/*. |

---

## 7. Rollback (se precisar)

```bash
# Restaurar o backup mais recente
sudo cp /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf.bak.YYYYMMDD_HHMMSS /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
sudo nginx -t && sudo service nginx reload
```

Substituir `YYYYMMDD_HHMMSS` pelo timestamp do backup gerado no Passo 2.
