# Resumo dos últimos passos – Timeout de áudio e autenticação gateway (27/01/2026)

**Data:** 27/01/2026  
**Objetivo:** Resumir em ordem o que fizemos, o que provamos e em que ponto paramos.

---

## 1. Problema inicial

- Envio de áudio (4–10 s) no Hub retornava **500** com **WPPCONNECT_TIMEOUT** ou **504 Gateway Time-out** em ~46–76 s.
- Textos funcionavam; o gargalo era o fluxo de áudio até o gateway WPP (wpp.pixel12digital.com.br).

---

## 2. O que já estava provado antes desta rodada

| Item | Conclusão |
|------|-----------|
| **VPS/Nginx (porta 8443)** | Não é causa do 504 por timeout. O vhost ativo em :8443 (`/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`) tem `proxy_*_timeout 7d` e encaminha para 172.19.0.1:3000. |
| **Porta “certa” do Hub para o gateway** | **:8443.** O diagnóstico (Passo 1) mostrou: em **:443** `/api/health` → 404; em **:8443** `/api/health` → 401 (rota existe, exige autenticação). |
| **Instrumentação no Hub** | ROUTE log, GATEWAY_HTML_ERROR, timeout de áudio 120 s, `diagnostic-gateway-route.php` e `capture-route-log.php` com `script_stamp`, `hostname`, `cwd`, e no capture também `log_source`, `log_source_mtime`, `log_source_size`. |

---

## 3. Últimos passos que demos (nesta sessão)

### 3.1 Console do usuário: envio de áudio → 500 em ~68 s

- **POST** `/communication-hub/send` → **500**
- **request_id:** `40f2e239160836df`
- **error_code:** WPPCONNECT_TIMEOUT
- **Tempo até o erro:** ~67 940 ms

Foi dada a URL para coletar evidência do que o Hub realmente chamou:

```
https://hub.pixel12digital.com.br/capture-route-log.php?token=SEU_TOKEN&request_id=40f2e239160836df
```

### 3.2 Resposta do capture-route-log (Passo 2 fechado)

O usuário chamou o endpoint e trouxe o JSON. As linhas **ROUTE** desse `request_id` mostraram:

| Campo | Valor |
|-------|--------|
| **effective_url** (envio que falhou) | `https://wpp.pixel12digital.com.br/api/messages` |
| **port** | **443** |
| **primary_ip** | 212.85.11.238 |
| **http_code** | 500 |
| **total_time_s** | 55,14 |

Conclusão: o Hub estava chamando o gateway **sem** a porta **8443** (porta implícita 443). Isso fechou o **Cenário B**: `WPP_GATEWAY_BASE_URL` em produção ainda estava **sem** `:8443`.

### 3.3 Ajuste da URL do gateway no .env

- **Arquivo correto:** `/home/pixel12digital/hub.pixel12digital.com.br/.env`  
  (é o da aplicação que serve `hub.pixel12digital.com.br`, não `/home/pixel12digital/hub/.env`.)
- **Alteração feita:**  
  `WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br:8443`

### 3.4 Novo teste de áudio após o ajuste

- **POST** `/communication-hub/send` (áudio) → **400 Bad Request**
- **Mensagem ao usuário:** “Erro de autenticação com o gateway”
- **error_code:** UNAUTHORIZED
- **request_id:** `e375e81f28b1eb1f`
- **Tempo até o erro:** ~1 982 ms

Interpretação: a requisição **passou** a ir para **:8443**, o gateway respondeu **rápido** (~2 s), mas devolveu **401 Unauthorized**. O controller do Hub traduz 401 em “Erro de autenticação com o gateway” e `error_code: UNAUTHORIZED`.

---

## 4. O que isso prova

| Antes (porta 443) | Depois (porta 8443) |
|-------------------|----------------------|
| 500/504 em ~55–68 s | 400 em ~2 s |
| Timeout / backend errado em 443 | Resposta imediata do gateway em 8443 |
| effective_url sem :8443 | Rota certa; problema deixou de ser “onde” e passou a ser “autenticação” |

Ou seja:
- **Rota e porta:** corretas com `WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br:8443`.
- **Problema atual:** o gateway em :8443 exige autenticação e está recusando o que o Hub envia.

---

## 5. Como o Hub autentica no gateway

- O Hub envia o header **`X-Gateway-Secret`** com o valor de **WPP_GATEWAY_SECRET** (descriptografado por `GatewaySecret::getDecrypted()`).
- O gateway em :8443 valida esse header e responde **401** quando o valor não confere com o que ele espera.

---

## 6. Próximo passo único

Alinhar o **secret** nos dois lados:

1. **No gateway (VPS)**  
   Ver em qual variável de ambiente ou config (Node/WPPConnect/PM2) está o secret que ele usa para validar o header (por exemplo, “API secret” ou “X-Gateway-Secret”).

2. **No Hub**  
   No `.env` em **`/home/pixel12digital/hub.pixel12digital.com.br/.env`**, garantir que **WPP_GATEWAY_SECRET** contém **exatamente** o mesmo valor que o gateway usa para validar (em texto puro ou no formato criptografado que o Hub já utiliza, conforme for o caso).

3. **Testar de novo**  
   Enviar áudio outra vez; o esperado é que o gateway aceite (sem 401) e o fluxo prossiga (sucesso ou outro erro não relacionado à autenticação).

---

## 7. Resumo em uma frase

Corrigimos a **porta** (443 → 8443) via `WPP_GATEWAY_BASE_URL` e **confirmamos** que o Hub passa a falar com o gateway certo em :8443; o bloqueio atual é **autenticação** — o **WPP_GATEWAY_SECRET** do Hub precisa ser o mesmo que o gateway (VPS) espera no header **X-Gateway-Secret**.
