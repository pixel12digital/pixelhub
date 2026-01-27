# Diretriz de execução + Pacote de Execução VPS para Charles

**Data:** 27/01/2026

---

## 0. Regra de Triangulação (obrigatória)

Qualquer necessidade de VPS segue o fluxo em **`docs/REGRA_TRIANGULACAO.md`**:

- **Só o Cursor** pede comandos para rodar na VPS ao Charles (não aceitar comandos do ChatGPT para ele executar).
- **Um bloco curto** de comandos por vez (copiar/colar); dizer **exatamente** quais outputs ele deve retornar; **esperar** a resposta antes do próximo bloco.
- **Após** ele retornar: (a) outputs relevantes, (b) resumo do que provam, (c) hipótese/caminho, (d) próximo bloco **ou** (e) patch no código local.
- **Código local/HostMedia/DB:** implementar localmente, commit e deploy; pedir VPS **só quando inevitável**.

---

## 1. Diretriz de execução (a partir de agora)

### 1.1 Implementação em código (local / HostMedia)

- Fazer todas as mudanças no código local (PixelHub) e, quando aplicável, no banco remoto.
- Entregar alterações com: **arquivos tocados**, **o que muda / o que não muda**, **logs adicionados**, **critério de aceite**.
- Depois: commit e fluxo normal para HostMedia.

### 1.2 Qualquer coisa na VPS (Gateway / WPPConnect)

- **Cursor não executa nada na VPS.**
- Quando precisar investigar/ajustar VPS, entregar ao Charles um **Pacote de Execução VPS** (copiar/colar) com:
  - pré-checks (comandos + o que ele deve retornar)
  - execução (comandos + arquivos tocados)
  - reload/restart (comandos)
  - verificação (comandos + saídas esperadas)
  - rollback (comandos)
  - critério de aceite

---

## 2. Patch no HostMedia / código local (implementado)

### 2.1 Arquivos tocados

| Arquivo | O que muda |
|---------|------------|
| `src/Controllers/CommunicationHubController.php` | request_id em log no início do envio; request_id em todas as respostas de erro (single, multi, exceção); log bytes_input + mime_detected no bloco de áudio; origin/reason opcionais no JSON de erro |
| `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` | setRequestId() e header X-Request-Id; log único por request: URL, total_time_s, http_code, X-Request-Id; base64 sanitizado (remove dataURL antes de enviar) + log quando remove prefixo |

### 2.2 O que não muda

- Lógica de envio (texto, áudio, canais).
- Detecção de timeout (mantida com regex/explícita, sem "30" solto).
- Fluxo de fallback WebM→gateway.
- Rotas e assinaturas públicas.

### 2.3 Logs adicionados

- **CommunicationHubController::send():**  
  - `[CommunicationHub::send][rid={requestId}] request_id={requestId} INÍCIO envio`  
  - `{logPrefix} bytes_input={N} mime_detected={detectedFormat}` (no bloco de áudio)
- **Resposta de erro (JSON):** sempre inclui `request_id` em erro single, multi e exceção.
- **WhatsAppGatewayClient::request():**  
  - `[WhatsAppGateway::request] URL={url} total_time_s={s} http_code={code} X-Request-Id={id}`  
- **WhatsAppGatewayClient::sendAudioBase64Ptt():**  
  - `base64 sanitized: removed dataURL prefix, raw_base64_len={N}` quando houver prefixo.

### 2.4 Critério de aceite (HostMedia)

- Qualquer falha 500 em `POST /communication-hub/send` inclui `request_id` no JSON de erro.
- Dá para buscar esse `request_id` nos logs (Hub e, depois, gateway).
- Gateway nunca recebe dataURL no body, só base64 cru (Hostmidia já faz strip; cliente faz strip de novo).

---

## 3. O que implementar no gateway (VPS) – spec para Charles

### D) Gateway: logs por etapa com X-Request-Id

Em **todas as rotas de envio de áudio**:

1. Ler `X-Request-Id` do header da requisição.
2. Prefixar **todas** as linhas de log desse request com esse ID (ex.: `[req=abc123def456] ...`).
3. Logar as etapas:
   - `received`
   - `sanitized_base64` (e tamanho em bytes)
   - `convert_start` / `convert_end` (se WebM→OGG)
   - `wppconnect_send_start` / `wppconnect_send_end`
   - `returned`

**Critério de aceite:** dado um `request_id` do erro no Hub, existe rastreio completo nos logs do gateway (todas as etapas acima com esse ID).

### E) Gateway: timeout interno por etapa (sem travar 46s)

Converter espera longa em **falha controlada**:

- **Conversão ffmpeg:** timeout 10–15 s.
- **Chamada WPPConnect (sendVoiceBase64):** timeout 30–60 s (configurável).

Em timeout, retornar erro estruturado com:

- `error_code`
- `origin: "gateway"`
- `reason: "TIMEOUT_STAGE_CONVERT"` ou `"TIMEOUT_STAGE_WPPCONNECT"` (ou semelhante)
- `request_id` (o mesmo recebido no header)

---

## 4. Pacote de Execução VPS para o Charles (copiar/colar)

**VPS – OBJETIVO:** Diagnosticar onde o timeout de ~46s ocorre (Nginx vs gateway vs WPPConnect) e, quando existir spec D+E no gateway, validar logs por X-Request-Id e timeouts por etapa.  
**SERVIÇO:** nginx (proxy do gateway) + PM2 (app do gateway Node).  
**RISCO:** baixo no pré-check e na verificação (só leitura); médio se houver alteração de config/reload.  
**ROLLBACK:** ver seção 4.4.

---

### 4.1 Pré-check (não muda nada)

**Comandos (copiar/colar um bloco por vez):**

```bash
# A) Site habilitado e timeouts do Nginx
ls -la /etc/nginx/sites-enabled/ | grep -E 'wpp|whatsapp|gateway'
grep -r 'proxy_.*timeout\|proxy_connect\|proxy_send\|proxy_read' /etc/nginx/sites-enabled/ 2>/dev/null | grep -v '^#'

# B) Teste de config do Nginx
sudo nginx -t

# C) PM2 e app do gateway
pm2 list
# Troque APP pelo nome/id do app do gateway nas próximas linhas:
pm2 describe APP

# D) FFmpeg e libopus
ffmpeg -version 2>&1 | head -5
ffmpeg -version 2>&1 | grep -i opus

# E) Logs do gateway no intervalo do erro (ajuste --lines e horário)
pm2 logs APP --lines 400 --nostream
```

**O que o Charles deve te retornar:** saída completa de A, B, C, D e E + **horário exato do 500** (ex.: 2026-01-27 11:56:55 UTC).

---

### 4.2 Execução (só se precisar alterar config)

**Comandos (só executar se for realmente alterar algo):**

```bash
# Backup do site do gateway (troque SITE pelo nome do arquivo em sites-available)
sudo cp /etc/nginx/sites-available/SITE /etc/nginx/sites-available/SITE.bak.$(date +%Y%m%d_%H%M%S)

# Editar timeouts (ex.: 60 → 120) – fazer manualmente ou com sed conforme necessidade
# sudo nano /etc/nginx/sites-available/SITE
```

**Arquivos tocados:**  
- `/etc/nginx/sites-available/<site-do-gateway>` — se for alterar timeouts (ex.: proxy_*_timeout 120s).

---

### 4.2.1 BLOCO 3 – Aumentar timeouts do proxy (60s → 120s) no whatsapp-multichannel

**Objetivo:** No site do gateway (`/etc/nginx/sites-available/whatsapp-multichannel`), trocar os três `proxy_*_timeout` de **60s** para **120s**, testar e recarregar o Nginx. **Um bloco único:** copiar/colar tudo no terminal na VPS.

> **⚠️ COPIAR SOMENTE O BLOCO DE COMANDOS**  
> Colar no terminal da VPS **apenas** as linhas que estão dentro do quadro abaixo (de `# === BLOCO 3` até `echo "BLOCO 3 concluído."`).  
> **NÃO** colar o resto do documento (títulos, tabelas, texto em markdown). Quem colar o texto do doc inteiro verá erros "command not found" e "syntax error".  
> Alternativa: copiar o conteúdo do arquivo **`docs/bloco3-timeout-nginx.sh`** deste repositório.

**Comandos (copiar/colar apenas estas linhas):**

```bash
# === BLOCO 3: Backup, 60s→120s, nginx -t, reload ===
FILE=/etc/nginx/sites-available/whatsapp-multichannel
sudo cp "$FILE" "${FILE}.bak.$(date +%Y%m%d_%H%M%S)"
echo "--- Backup criado ---"
ls -la "${FILE}.bak."* 2>/dev/null | tail -1
sudo sed -i -e 's/proxy_connect_timeout 60s;/proxy_connect_timeout 120s;/' -e 's/proxy_send_timeout 60s;/proxy_send_timeout 120s;/' -e 's/proxy_read_timeout 60s;/proxy_read_timeout 120s;/' "$FILE"
echo "--- Linhas com timeouts após alteração ---"
grep -n 'proxy_connect_timeout\|proxy_send_timeout\|proxy_read_timeout' "$FILE"
echo "--- nginx -t ---"
sudo nginx -t
echo "--- Reload Nginx ---"
sudo nginx -s reload 2>/dev/null || sudo systemctl reload nginx 2>/dev/null || sudo service nginx reload
echo "BLOCO 3 concluído."
```

**O que o Charles deve colar de volta:**

1. O trecho **"--- Backup criado ---"** e a linha do `ls` (caminho do arquivo `.bak.YYYYMMDD_HHMMSS`).
2. O trecho **"--- Linhas com timeouts após alteração ---"** e as 3 linhas exibidas pelo `grep` (devem mostrar `120s`).
3. O trecho **"--- nginx -t ---"** e a saída de `nginx -t` (esperado: `syntax is ok` e `test is successful`).
4. O trecho **"--- Reload Nginx ---"** e a linha `BLOCO 3 concluído.`

Se algum comando falhar, colar a saída de erro completa e parar antes do próximo passo.

**Se apenas o reload falhar** (ex.: `kill -HUP` mostrou "Usage" porque o pid file está noutro caminho), executar **só** o reload com um destes (copiar/colar uma linha):

```bash
sudo nginx -s reload
```
ou, se o sistema usar systemd:

```bash
sudo systemctl reload nginx
```
ou:

```bash
sudo service nginx reload
```

Depois confirmar com `sudo nginx -t` e testar de novo o envio de áudio.

---

### 4.3 Reload / Restart

**Comandos (copiar/colar):**

```bash
# Reload do Nginx (na srv817568 usar: sudo service nginx reload)
sudo nginx -t && (sudo nginx -s reload 2>/dev/null || sudo systemctl reload nginx 2>/dev/null || sudo service nginx reload)

# Restart do gateway (só se precisar; troque APP)
pm2 restart APP
```

---

### 4.4 Verificação

**Comandos:**

```bash
# Confirmar Nginx aplicado
sudo nginx -t
grep -r 'proxy_.*timeout' /etc/nginx/sites-enabled/ 2>/dev/null | grep -v '^#'

# Confirmar PM2 ativo
pm2 list
pm2 logs APP --lines 50 --nostream
```

**Saídas esperadas:**  
- `nginx -t`: syntax is ok.  
- `pm2 list`: app do gateway em status online.

**Critério de sucesso:**  
- Envio de áudio 4–10s no Hub conclui em **menos de 15s**, sem 500.  
- Quando o gateway tiver D+E implementados: dado um `request_id` do erro no Hub, os logs do gateway mostram todas as etapas (received → … → returned) com esse ID.

---

### 4.5 Rollback

**Comandos:**

```bash
# Restaurar backup do Nginx (use o nome real do backup criado em 4.2)
sudo cp /etc/nginx/sites-available/SITE.bak.YYYYMMDD_HHMMSS /etc/nginx/sites-available/SITE
# Reload do Nginx (na srv817568: sudo service nginx reload)
sudo nginx -t && (sudo nginx -s reload 2>/dev/null || sudo systemctl reload nginx 2>/dev/null || sudo service nginx reload)

# Revertir gateway (se tiver alterado código e feito restart)
# pm2 restart APP  -- após reverter o código no repo
```

**Arquivos que voltam:** o que foi editado em 4.2 (ex.: site Nginx).

---

### 4.6 Critério de aceite do Pacote

- **Pré-check:** Charles devolve todas as saídas + horário exato do 500.
- **Execução/Reload:** se feitos, Nginx aplicado e gateway reiniciado sem erro.
- **Verificação:** Nginx ok, PM2 ok; após implementação D+E no gateway, envio < 15s e logs rastreáveis por `request_id`.

---

## 5. Resumo da entrega

| Item | Onde | Status |
|------|------|--------|
| **A)** request_id em logs + resposta de erro; X-Request-Id no gateway; logs URL/tempo/status/requestId no client | HostMedia (commits locais) | Implementado |
| **B)** base64 sanitizado; log bytes_input e mime_detected | HostMedia | Implementado |
| **C)** Timeout detection (regex, sem "30" solto) | HostMedia | Já estava; mantido |
| **D)** Gateway: logs por etapa com X-Request-Id | VPS (Charles) | Spec neste doc (seção 3) |
| **E)** Gateway: timeout interno por etapa | VPS (Charles) | Spec neste doc (seção 3) |
| **Pacote de Execução VPS** | Para Charles | Seção 4 (comandos copiar/colar, outputs esperados, rollback, critério de aceite) |
