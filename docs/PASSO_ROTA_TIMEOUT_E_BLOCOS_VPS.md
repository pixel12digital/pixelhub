# Rota real Hub → Gateway: Passo 1, Passo 2 e Blocos VPS

**Data:** 27/01/2026  
**Objetivo:** Fechar a rota real do Hub até o gateway com evidência (qual porta, qual IP) antes de qualquer nova rodada “no escuro” na VPS. Decisão automática com base no resultado.

**Referências:**  
`docs/AUDITORIA_INVESTIGACAO_COMPLETA_VPS.md`, `docs/REGRA_TRIANGULACAO.md`, `.cursor/rules/regra-vps.mdc`

---

## 0) Fechamento imediato (antes dos testes)

- **Commit + deploy no HostMedia** do patch: GATEWAY_HTML_ERROR + ROUTE com timeouts + áudio 120s + diagnostic-gateway-route.
- **Confirmar** que o endpoint existe e responde: **https://hub.pixel12digital.com.br/diagnostic-gateway-route.php**
- **Critério:** o endpoint retorna JSON; no próximo erro de envio o response do Hub inclui `request_id` e, se vier HTML, inclui `gateway_html_error`.

---

## Mensagem para o Charles (Passo 1 — uma coisa só)

> **Abra https://hub.pixel12digital.com.br/diagnostic-gateway-route.php e me mande o JSON completo.**

Assim que ele devolver o JSON, aplicar a tabela de decisão (seção 4) e responder com um próximo passo único (ajuste do WPP_GATEWAY_BASE_URL ou Passo 2).

---

## Estado consolidado (assumir como verdade)

| Item | Valor |
|------|--------|
| **Vhost ativo em :8443** | `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` |
| **Timeouts no vhost 8443** | `proxy_*_timeout 7d` no server → Nginx **não** explica 504 em ~46–76s |
| **Hipótese** | 504 HTML sugere request em outra porta/vhost ou upstream diferente do que já inspecionamos. Possível mismatch: Charles acessa UI em :8443, Hub pode chamar API por :443 (ou outro). |

---

## 1) O que já está implementado (HostMedia)

- **Log ROUTE** em `WhatsAppGatewayClient::request()`:  
  `request_id`, `effective_url`, `host`, `port`, `http_code`, `content_type`, `primary_ip`, `total_time_s`, `connect_timeout_s`, `total_timeout_s`
- **diagnostic-gateway-route.php:** compara `env_exact` (URL do .env) vs `env_8443` (variante :8443); GET `/` e GET `/api/health` para cada alvo.
- **GATEWAY_HTML_ERROR:** quando `content_type` é `text/html` ou body começa com `<`, retorno inclui `gateway_html_error` com `http_code`, `content_type`, `effective_url`, `primary_ip`, `request_id`, `body_preview`. Controller repassa no JSON de erro.
- **Timeouts:** áudio usa 120s no cliente; ROUTE loga `connect_timeout_s=10` e `total_timeout_s={valor}`.

---

## 2) Passo 1 (HostMedia) – Provar qual porta e qual IP atendem

**Objetivo:** Provar se `/api/health` responde em :443, em :8443 ou em nenhum dos dois, e qual IP atende cada um.

**Quem faz:** Charles (ou quem tiver acesso ao navegador do HostMedia / ao servidor onde o Hub está).

**Ação:**

1. Abrir no navegador (logado no mesmo domínio do Hub, se exigir):  
   **`https://hub.pixel12digital.com.br/diagnostic-gateway-route.php`**  
   (Ou a URL base do painel + `/diagnostic-gateway-route.php`.)

2. Copiar o **JSON completo** retornado e enviar ao Cursor.

**Retorno esperado:** JSON completo. Do JSON você extrai:

- **tests.env_exact.get_api_health** → `effective_url`, `primary_ip`, `http_code`, `content_type`, `timings.total`
- **tests.env_exact.get_root** → mesmos campos (para saber se está caindo em 401/redirect/UI etc.)
- **tests.env_8443.get_api_health** (se existir) → mesmos campos
- **tests.env_8443.get_root** (se existir) → mesmos campos

**Decisão automática após Passo 1:**

| Condição | Ação |
|----------|------|
| `/api/health` só “funciona” em **:8443** (http_code 200 em env_8443.get_api_health) | Ajustar `WPP_GATEWAY_BASE_URL` para incluir `:8443` (ex.: `https://wpp.pixel12digital.com.br:8443`) e **redeploy**. |
| `/api/health` só funciona em **:443** (http_code 200 em env_exact.get_api_health e env_exact já usa 443) | Remover/evitar `:8443` no `WPP_GATEWAY_BASE_URL` e **redeploy**. |
| **Nenhum** responde (404/401/HTML em ambos) | Não ir para VPS ainda; ajustar o diagnóstico para bater num endpoint de health que exista de verdade na API (objetivo: provar rota/porta/IP, não “inventar health”). |

**Critério de conclusão:** Com esse JSON fica determinado qual porta o .env está usando, qual IP o cURL do HostMedia atinge em cada alvo e se `/api/health` responde em algum deles.

---

## 3) Passo 2 (HostMedia) – Teste de áudio + evidência mínima

**Quando:** Depois de aplicar (ou não) o ajuste do `WPP_GATEWAY_BASE_URL` conforme a decisão do Passo 1.

**Objetivo:** Fechar “para onde o Hub foi” (URL/porta/IP), “quanto tempo esperou” (total_time_s) e “o que recebeu” (content_type + HTML vs JSON).

**Quem faz:** Charles (ou quem tiver acesso ao Hub e aos logs do HostMedia).

**Ação:** Charles faz um **envio de áudio** (Chrome, 4–10s) no Hub e devolve **duas coisas**:

**(A) Do erro/sucesso no Hub (payload de resposta):**
- `request_id`
- Se existir: **gateway_html_error completo** (`http_code`, `content_type`, `effective_url`, `primary_ip`, `body_preview`)

**(B) Do log do HostMedia (linha única ROUTE do request):**
- A linha **`[WhatsAppGateway::request] ROUTE …`** correspondente ao **mesmo** `request_id`.

**Critério de conclusão:** Com isso você fecha “para onde o Hub foi” (URL/porta/IP), “quanto tempo esperou” (total_time_s) e “o que recebeu” (content_type + HTML vs JSON).

### Script para capturar a linha ROUTE com precisão

Use **`public/capture-route-log.php`** depois de cada teste de áudio em produção:

1. **Configurar (uma vez):** no `.env` do Hub, defina um segredo:
   ```env
   ROUTE_LOG_CAPTURE_TOKEN=uma_frase_secreta_qualquer
   ```
   (Ou deixe vazio: a variável vazia desabilita a exigência de token.)

2. **Rodar o teste:** envie um áudio (4–10s) pelo Hub e anote o **request_id** da resposta (erro ou sucesso).

3. **Capturar o log:** logo em seguida, abra no navegador:
   ```
   https://hub.pixel12digital.com.br/capture-route-log.php?token=uma_frase_secreta_qualquer&request_id=REQUEST_ID_REAL
   ```
   **Importante:** troque **REQUEST_ID_REAL** pelo `request_id` que veio na resposta do erro (no console: "Dados do erro: Object" → abra o Object e copie o campo `request_id`; ex.: `9ae2f5866699a63c`). Não use o texto literal "REQUEST_ID_ANOTADO" ou "REQUEST_ID_DA_RESPOSTA" na URL.
   (Se não definiu `ROUTE_LOG_CAPTURE_TOKEN`, use só `?request_id=...`.)

4. **Resultado:** o script devolve JSON com:
   - `lines`: array de linhas do `pixelhub.log` que contêm **ROUTE** (e opcionalmente o `request_id` informado), das mais recentes para as mais antigas;
   - `count`, `log_path`, `request_id_filter`.

5. **Entregar:** copie o JSON completo (ou só o conteúdo de `lines`) e envie a quem estiver analisando. A linha ROUTE contém `effective_url`, `port`, `primary_ip`, `http_code`, `content_type`, `total_time_s`, `connect_timeout_s`, `total_timeout_s`.

**Observação:** o script lê as **últimas 2 MB** de `logs/pixelhub.log`. Se o Hub escrever em outro arquivo (ex.: error_log do Apache), essa linha pode não aparecer — nesse caso use o error log do domínio do Hub, como antes.

---

## 4) Decisão automática (sem debate)

| Condição | Ação |
|----------|------|
| **Hub chama :443** e o diagnóstico mostra que a API “útil” está em **:8443** | Ajustar `WPP_GATEWAY_BASE_URL` no .env para incluir a porta correta (ex.: `https://wpp.pixel12digital.com.br:8443`) e **retestar** áudio. |
| **Hub chama :8443** e `primary_ip` = **212.85.11.238** | Request está chegando na VPS certa; próximo foco é o **upstream real** (172.19.0.1:3000) e logs por etapa no gateway. Acionar **BLOCO VPS B** só depois de **BLOCO VPS A** confirmar que o request aparece no vhost certo. |
| **primary_ip ≠ 212.85.11.238** | Estamos falando com outro host; **parar** e corrigir DNS/rota antes de mexer em timeout ou código. |

---

## 5) Quando acionar a VPS

**Somente após** Passo 1 e Passo 2 provarem:

- qual porta o Hub usa,
- qual IP atende,
- qual `effective_url` está sendo chamado.

Até lá, **nenhum** bloco de comando na VPS é executado para “timeout/nginx” genérico.

---

## 6) Blocos VPS (um por vez, aguardar retorno)

Regra: **um bloco por vez** (A → retorno do Charles → B, se fizer sentido).

### BLOCO VPS A – Diagnóstico orientado por rota

**Objetivo:** Saber qual vhost atende a porta que o HostMedia está usando (443 vs 8443 vs outra) e se o request do `request_id` aparece nos logs desse vhost.

**Comandos para o Charles colar (e retornar outputs completos):**

```bash
echo "=== A1) Porta que o Hub usa (informar após Passo 2) ==="
# Charles: substituir 8443 por 443 ou outro se o Passo 2 mostrar outra porta
PORT=8443

echo "=== A2) Vhost que atende essa porta ==="
nginx -T 2>/dev/null | grep -E "configuration file|listen.*${PORT}|server_name " | head -60

echo "=== A3) Arquivo de config e paths de log desse vhost ==="
grep -l "listen.*${PORT}" /etc/nginx/conf.d/*.conf /etc/nginx/sites-enabled/* 2>/dev/null || true
for f in $(grep -l "listen.*${PORT}" /etc/nginx/conf.d/*.conf /etc/nginx/sites-enabled/* 2>/dev/null); do
  echo "--- $f ---"
  grep -E "listen |server_name |access_log|error_log" "$f" 2>/dev/null
done

echo "=== A4) Últimas linhas do access_log do vhost 8443 (exemplo) ==="
# Ajustar path se A3 mostrar outro arquivo
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_access.log 2>/dev/null || echo "Arquivo não encontrado"
```

**Retorno esperado do Charles:**  
Outputs completos de A2, A3 e A4 + confirmação explícita: **“request do request_id X apareceu / não apareceu no access_log”** (usando o `request_id` e o horário do Passo 2).

---

### BLOCO VPS B – Upstream real (172.19.0.1:3000)

**Quando usar:** Só se o BLOCO A tiver mostrado que o request **chega** no vhost certo e mesmo assim estoura timeout.

**Objetivo:** Coletar evidência do processo/container que atende 172.19.0.1:3000 e onde está o gargalo (conversão, WPPConnect, fila, deadlock), para então implementar D+E (logs por etapa + timeout por etapa) no gateway.

**Comandos para o Charles colar (e retornar outputs completos):**

```bash
echo "=== B1) Processo escutando em 3000 ==="
ss -tlnp | grep 3000 || netstat -tlnp 2>/dev/null | grep 3000

echo "=== B2) Se for Docker, container e imagem ==="
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Ports}}' 2>/dev/null | grep -E "3000|wpp|gateway" || true

echo "=== B3) PM2 list (gateway/wpp-ui) ==="
pm2 list 2>/dev/null

echo "=== B4) Últimas linhas do log do processo gateway (exemplo) ==="
# Ajustar conforme nome do app no PM2 ou caminho do log do container
pm2 logs wpp-ui --lines 30 --nostream 2>/dev/null || echo "Ajustar comando conforme PM2/container"
```

**Retorno esperado do Charles:**  
Outputs de B1–B4 para que o Cursor decida o próximo passo (ex.: onde injetar logs com `[req=<request_id>]` e timeouts por etapa, conforme spec em `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` seções 3.D e 3.E).

---

## 7) Entregável pós-execução (para o usuário)

Após **cada** rodada (HostMedia ou VPS), o Cursor devolve:

1. **Evidência (linhas/valores):**  
   `request_id`, `effective_url`, `port`, `primary_ip`, `http_code`, `content_type`, `total_time_s`
2. **Hipótese atualizada:** uma frase.
3. **Próximo passo único:** ex.: “corrigir WPP_GATEWAY_BASE_URL e retestar” ou “executar BLOCO VPS B com request_id X”.

Sem listas genéricas e sem “o que conferir”: a próxima ação deve ser **determinística**.
