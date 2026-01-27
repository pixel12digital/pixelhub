# Auditoria completa – Timeout de áudio WhatsApp (até 27/01/2026)

**Data:** 27/01/2026  
**Escopo:** Consolidação de todos os resultados colhidos na investigação do 504 Gateway Time-out (~46–76s) no envio de áudio; diagnóstico do que está certo e do que não está; próximos passos determinísticos.

**Referências:**  
`docs/PASSO_ROTA_TIMEOUT_E_BLOCOS_VPS.md`, `docs/AUDITORIA_INVESTIGACAO_COMPLETA_VPS.md`, `docs/AUDITORIA_ULTIMOS_PASSOS_TIMEOUT_VPS.md`, `docs/RESUMO_TIMEOUT_AUDIO_COMPLETO.md`, `docs/CONTRATO_AUDIO_GATEWAY_HOSTMIDIA.md`, `.cursor/rules/regra-vps.mdc`

---

## 0. Ponto em que estamos

### Estado atual (o que está provado)

| Item | Conclusão |
|------|-----------|
| **VPS/Nginx (8443)** | Descartado como causa do 504 por timeout de proxy — o vhost ativo em :8443 já tem `proxy_*_timeout 7d` e aponta para 172.19.0.1:3000. |
| **Porta “certa” Hub → Gateway** | **:8443.** Passo 1: :443 `/api/health` = 404; :8443 `/api/health` = 401 → rota existe. |
| **HostMedia** | Já tem instrumentação: ROUTE log + GATEWAY_HTML_ERROR + timeout áudio 120s + endpoints de diagnóstico. |

### Gargalo atual (o que está impedindo avançar)

- **Passo 2 inconclusivo:** o capture-route-log em produção não está devolvendo linhas `[WhatsAppGateway::request] ROUTE …`; a resposta com 50 linhas de “Router/Rota/Bypass” indica:
  - deploy não aplicado (endpoint antigo/errado), ou
  - endpoint lendo arquivo de log errado, ou
  - filtro estrito não ativo na versão em produção.
- **Sem essa linha ROUTE atrelada a um request_id** não dá para fechar: effective_url, porta, primary_ip, total_time_s do request de áudio que gera o 504.

### Regra de triangulação (a partir de agora)

- **Charles não lê docs.** O Cursor pede **uma ação por vez**, com **retorno esperado explícito**.
- Cada rodada do Cursor termina com: **Evidência** (valores/linhas exatas) + **Hipótese** (1 frase) + **Próximo passo único** (determinístico).

---

## 1. Resumo executivo

| Item | Status |
|------|--------|
| **Problema** | Envio de áudio 4–10s retorna **504 Gateway Time-out** (ou 500 WPPCONNECT_TIMEOUT) em ~46–76s. |
| **Causa Nginx na VPS** | **Descartada.** O vhost ativo em 8443 já tem `proxy_*_timeout 7d`. |
| **Porta correta para o Hub** | **:8443.** Diagnóstico Passo 1 mostrou: em :443 `/api/health` → 404; em :8443 → 401 (rota existe). |
| **Decisão Passo 1** | Ajustar `WPP_GATEWAY_BASE_URL` para `https://wpp.pixel12digital.com.br:8443` e redeploy. |
| **Evidência Passo 2 (ROUTE)** | **Inconclusiva.** Última chamada ao capture-route-log devolveu 50 linhas de Router/Rota/Bypass; **nenhuma** com `[WhatsAppGateway::request] ROUTE`. |
| **Provar deploy** | Endpoints de diagnóstico passam a devolver `script_stamp`, `hostname`, `cwd`; o capture ainda `log_source`, `log_source_mtime`, `log_source_size` — para eliminar dúvida de versão/endpoint/arquivo de log. |

---

## 2. O que está certo

### 2.1 VPS (srv817568, 212.85.11.238)

| Item | Evidência / conclusão |
|------|------------------------|
| **Vhost ativo em 8443** | `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` (confirmado por BLOCO B' na auditoria VPS). |
| **Timeouts Nginx** | `proxy_connect_timeout 7d`, `proxy_send_timeout 7d`, `proxy_read_timeout 7d` no server. Nginx **não** é causa do 504 em ~60s. |
| **Backend** | `proxy_pass http://172.19.0.1:3000;` (rede Docker). |
| **Logs do vhost 8443** | `wpp.pixel12digital.com.br_access.log`, `wpp.pixel12digital.com.br_error.log`. |
| **Reload Nginx na srv817568** | Deve ser feito com `sudo service nginx reload` (pid file vazio invalida `nginx -s reload` e `kill -HUP`). |

### 2.2 Código HostMedia (PixelHub) – implementado

| Item | Onde | Status |
|------|------|--------|
| **Log ROUTE** | `WhatsAppGatewayClient::request()` | Loga `[WhatsAppGateway::request] ROUTE` com `request_id`, `effective_url`, `host`, `port`, `http_code`, `content_type`, `primary_ip`, `total_time_s`, `connect_timeout_s`, `total_timeout_s`. |
| **GATEWAY_HTML_ERROR** | `WhatsAppGatewayClient` + `CommunicationHubController` | Quando `content_type` é `text/html` ou body começa com `<`, retorno inclui `gateway_html_error` (http_code, content_type, effective_url, primary_ip, request_id, body_preview). Controller repassa no JSON de erro. |
| **Timeouts áudio** | `WhatsAppGatewayClient` | Áudio usa 120s no cliente; ROUTE loga os timeouts efetivos. |
| **diagnostic-gateway-route.php** | `public/` | Compara `env_exact` (URL do .env) vs `env_8443` (variante :8443); GET `/` e GET `/api/health` por alvo. Devolve `script_stamp`, `hostname`, `cwd` (prova de deploy). |
| **capture-route-log.php** | `public/` | Filtra **apenas** linhas com `[WhatsAppGateway::request] ROUTE` (evita “Rota”, “Router” etc.). Lê o **mesmo** arquivo que `pixelhub_log` em `index.php` usa: `public/../logs/pixelhub.log`. Token via `ROUTE_LOG_CAPTURE_TOKEN`; opcional `request_id`. Devolve `script_stamp`, `hostname`, `cwd`, `log_source`, `log_source_mtime`, `log_source_size`. |

### 2.3 Passo 1 – Resultado do diagnóstico de rota

| Fonte | Resultado |
|-------|-----------|
| **Charles** | Abriu `https://hub.pixel12digital.com.br/diagnostic-gateway-route.php` e devolveu o JSON. |
| **:443** | `/api/health` → **404**; rota “útil” da API **não** está em 443. |
| **:8443** | `/api/health` → **401** (rota existe, falta auth). |
| **Decisão** | Hub deve usar **:8443**. Ajustar `WPP_GATEWAY_BASE_URL` para `https://wpp.pixel12digital.com.br:8443` e redeploy. |

### 2.4 Arquivos em sites-available (VPS)

As alterações de 60s→120s em `wpp_ssl_8443` e `wpp_443_proxy_to_9443` **não** afetam o tráfego em 8443, pois o vhost ativo é o de `conf.d/00-wpp.pixel12digital.com.br.conf`. Esse fato está documentado e **não** invalida a conclusão: o vhost ativo já tem 7d.

---

## 3. O que não está certo / inconclusivo

### 3.1 Passo 2 – Captura da linha ROUTE

| Observação | Diagnóstico |
|------------|-------------|
| **Última saída do script** | 50 linhas em `lines`; **todas** são “Router”, “Rota encontrada”, “Bypass Check”, “Router Setup” — **nenhuma** contém `[WhatsAppGateway::request] ROUTE`. |
| **Comportamento esperado** | Com o filtro estrito `[WhatsAppGateway::request] ROUTE`, o script **não** deveria incluir linhas de Router. Ou seja: ou (a) o deploy em produção ainda usa versão antiga do script (filtro por “ROUTE”/“Rota”), ou (b) o script em produção é outro endpoint (ex.: “últimas N linhas” genérico). |
| **Se o filtro estrito já estiver em produção** | Então `count` deveria ser 0 e `lines` vazio quando não há nenhuma chamada HTTP ao gateway no trecho lido; as 50 linhas de Router indicam que **a resposta veio de um script que não aplica** o filtro estrito. |
| **Ação** | Garantir que o **capture-route-log.php** atual (filtro por `[WhatsAppGateway::request] ROUTE`) está em produção; depois repetir Passo 2 com request_id real. |

### 3.2 Evidência “para onde o Hub foi” no último teste de áudio

- Ainda **não** há uma linha ROUTE associada a um `request_id` de erro de áudio.
- Por isso não está fechado: effective_url, port, primary_ip e total_time_s **do** request de áudio que gerou 504.
- O fechamento depende de: (1) deploy correto do script; (2) novo envio de áudio; (3) uso do `request_id` da resposta no capture-route-log.

### 3.3 WPP_GATEWAY_BASE_URL em produção

- A decisão do Passo 1 é usar **:8443**.
- É necessário **confirmar** que no HostMedia (produção) o `.env` está com `WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br:8443` e que o deploy já foi feito após essa alteração.

---

## 4. Cronologia consolidada

| Fase | O que foi feito | Resultado |
|------|------------------|-----------|
| **RESUMO_TIMEOUT (26/01)** | Diagnóstico inicial; timeouts 60s no Nginx; alteração para 120s em whatsapp-multichannel; reload. | O vhost editado **não** era o que atende 8443. |
| **AUDITORIA_ULTIMOS_PASSOS** | BLOCO 1–3 na VPS; alteração em sites-available; reload com `service nginx reload`. | Reload ok; vhost em sites-available não é o ativo em 8443. |
| **AUDITORIA_INVESTIGACAO_COMPLETA_VPS** | BLOCO B': leitura de `conf.d/00-wpp.pixel12digital.com.br.conf`. | Vhost ativo em 8443 tem **proxy_*_timeout 7d** → Nginx descartado. |
| **Passo 1** | Charles abriu diagnostic-gateway-route.php. | :443 → 404 em /api/health; :8443 → 401. Decisão: Hub usar :8443. |
| **Código local** | GATEWAY_HTML_ERROR, log ROUTE com timeouts, áudio 120s, diagnostic-gateway-route, capture-route-log com filtro estrito. | Implementado no repositório. |
| **Passo 2 (tentativa)** | Chamada ao capture-route-log (sem request_id ou com request_id). | Retorno: 50 linhas de Router/Rota/Bypass; **zero** linhas ROUTE → inconclusivo. |

---

## 5. Tabela de decisão (relembre)

| Condição | Ação |
|----------|------|
| Hub chama :443 e o diagnóstico mostra API útil em :8443 | Ajustar `WPP_GATEWAY_BASE_URL` para `https://wpp.pixel12digital.com.br:8443` e **retestar** áudio. |
| Hub chama :8443 e `primary_ip` = **212.85.11.238** | Request na VPS certa; próximo foco é upstream 172.19.0.1:3000 e logs por etapa no gateway. BLOCO VPS B só depois do BLOCO VPS A confirmar request no vhost certo. |
| `primary_ip` ≠ 212.85.11.238 | Corrigir DNS/rota **antes** de qualquer mudança de timeout. |
| Nenhuma linha ROUTE em pixelhub.log | Considerar error_log do Apache (domínio hub) e garantir que o cliente gateway escreve no canal que o Hub lê (ex.: pixelhub_log → pixelhub.log). |

---

## 6. Próximos passos (ordem determinística, sem novas rodadas “no escuro”)

### 6.1 Passo “provar deploy” (Cursor, sem VPS)

**Objetivo:** Tirar a dúvida de versão/endpoint/arquivo de log.

**O que foi implementado:** Em cada endpoint de diagnóstico (`diagnostic-gateway-route.php` e `capture-route-log.php`), o JSON passa a trazer:

- **script_stamp** — ex.: `diagnostic-gateway-route.php.m1738...t2026-01-27T...` (m = filemtime do script, t = horário da requisição)
- **hostname** — hostname do servidor
- **cwd** — diretório de trabalho

No **capture-route-log.php** ainda:

- **log_source** — caminho exato do arquivo que o capture está lendo
- **log_source_mtime** e **log_source_size** — para mostrar que é um arquivo vivo

**Critério de aceite:** Charles abre os dois endpoints e o Cursor confere se `script_stamp` e `log_source` batem com o que se espera. Se `log_source` **não** for o arquivo onde o `WhatsAppGatewayClient` escreve (via `pixelhub_log` em `index.php` → `public/../logs/pixelhub.log`), o Cursor ajusta antes de qualquer novo teste.

### 6.2 Passo “validar onde o ROUTE está sendo gravado” (Cursor, sem VPS)

**Objetivo:** Garantir que a linha `[WhatsAppGateway::request] ROUTE` vai para um log que o capture lê.

**Ação do Cursor:** Garantir que o `capture-route-log.php` lê o **mesmo** destino que `pixelhub_log` em `index.php` (já está: `public/../logs/pixelhub.log`). O `WhatsAppGatewayClient` usa `pixelhub_log()` quando a requisição passa por `index.php` (rotas normais do Hub), então a linha ROUTE deve aparecer em `logs/pixelhub.log`.

**Critério de aceite:** Fazer um hit que dispare chamada ao gateway (ex.: envio de áudio ou um GET que use o cliente). Em seguida, o capture deve devolver **pelo menos 1 linha** com `[WhatsAppGateway::request] ROUTE`, `request_id`, `effective_url`, `primary_ip`.

### 6.3 Só depois disso: rodada Passo 2 (Cursor pede ao Charles)

O Cursor envia **uma mensagem por vez** ao Charles, com **retorno esperado explícito**.

#### Bloco para o Charles – Passo 2 (copiar/colar)

> **Passo 2 – Evidência de rota no envio de áudio**
>
> 1. No Hub (Chrome), faça **um** envio de áudio de 4–10 segundos.
> 2. Da resposta do Hub (erro ou sucesso), copie e me envie:
>    - **request_id**
>    - Se tiver: **gateway_html_error** completo (http_code, content_type, effective_url, primary_ip, body_preview).
> 3. Logo em seguida, abra no navegador (substitua TOKEN e o request_id real que você anotou):
>    `https://hub.pixel12digital.com.br/capture-route-log.php?token=TOKEN&request_id=REQUEST_ID_ANOTADO`
> 4. Me envie o **JSON completo** que essa página retornar (em especial `lines`, `count`, `script_stamp`, `log_source`).

**Retorno esperado do Charles:**  
- `request_id`  
- (se houver) `gateway_html_error` completo  
- JSON do capture-route-log, contendo pelo menos uma linha em `lines` com `[WhatsAppGateway::request] ROUTE` e esse mesmo `request_id`, com effective_url, primary_ip, http_code, content_type, total_time_s.

**Critério de sucesso do Passo 2:**  
O capture devolve **≥ 1 linha** `[WhatsAppGateway::request] ROUTE` com o mesmo `request_id`, contendo effective_url (com porta), primary_ip, http_code, content_type, total_time_s.

### 6.4 Decisão automática após Passo 2 (sem discussão)

| Cenário | Condição | Próximo passo |
|---------|----------|----------------|
| **A** | effective_url tem :8443 e primary_ip = **212.85.11.238** | Rota e VPS corretas. Se ainda vier 504/HTML, causa é upstream 172.19.0.1:3000 (gateway/Node/WPPConnect). Cursor aciona rodada VPS: primeiro logs do Nginx do vhost 8443 no timestamp do request_id; depois logs do processo/container da 3000. |
| **B** | effective_url mostra :443 (ou sem porta) ou porta diferente | `WPP_GATEWAY_BASE_URL` em produção ainda errado (ou há override). Cursor corrige .env em produção (via fluxo de deploy) e repete Passo 2. |
| **C** | primary_ip ≠ 212.85.11.238 | DNS/rota errada ou resolução diferente no HostMedia. Corrigir rota/DNS **antes** de mexer em gateway/timeout. |
| **D** | count = 0 e nenhuma linha ROUTE mesmo após “provar deploy” | O log não está sendo escrito onde o capture lê, ou a linha não está sendo emitida no fluxo do áudio. Cursor ajusta o ponto de log para garantir que ROUTE seja emitido sempre (inclusive para áudio) e no canal certo. |

### 6.5 Só após Passo 2 fechado – VPS (Regra de Triangulação)

- **BLOCO VPS A:** Confirmar vhost que atende a porta usada pelo Hub e se o request do `request_id` aparece no access_log desse vhost (comandos em `docs/PASSO_ROTA_TIMEOUT_E_BLOCOS_VPS.md`, seção 6).
- **BLOCO VPS B:** Só se o BLOCO A tiver mostrado request no vhost certo; foco em processo em 3000, Docker/PM2 e logs do gateway.

Nenhum bloco VPS deve ser executado para “timeout/nginx” genérico antes de Passo 1 e Passo 2 provarem porta, IP e effective_url.

---

## 7. Entregáveis por rodada

Após cada rodada (HostMedia ou VPS), o Cursor deve devolver:

1. **Evidência:** `request_id`, `effective_url`, `port`, `primary_ip`, `http_code`, `content_type`, `total_time_s` (quando disponíveis).
2. **Hipótese atualizada:** uma frase.
3. **Próximo passo único:** ex.: “corrigir WPP_GATEWAY_BASE_URL e retestar” ou “executar BLOCO VPS A com request_id X”.

---

## 8. Referência rápida – Arquivos e endpoints

| Item | Caminho / URL |
|------|-------------------------------|
| Contrato áudio + Correlation ID | `docs/CONTRATO_AUDIO_GATEWAY_HOSTMIDIA.md` |
| Passo 1+2 + decisão + blocos VPS | `docs/PASSO_ROTA_TIMEOUT_E_BLOCOS_VPS.md` |
| Auditoria VPS (vhost 8443, 7d) | `docs/AUDITORIA_INVESTIGACAO_COMPLETA_VPS.md` |
| Diagnóstico de rota | `https://hub.pixel12digital.com.br/diagnostic-gateway-route.php` |
| Captura log ROUTE | `https://hub.pixel12digital.com.br/capture-route-log.php?token=...&request_id=...` |
| Cliente gateway (ROUTE, GATEWAY_HTML_ERROR) | `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` |
| Controller (gateway_html_error no JSON) | `src/Controllers/CommunicationHubController.php` |
| Regra VPS / triangulação | `.cursor/rules/regra-vps.mdc`, `docs/REGRA_TRIANGULACAO.md` |

---

**Estado final desta auditoria:** Implementados `script_stamp`, `hostname`, `cwd` e (no capture) `log_source`, `log_source_mtime`, `log_source_size` nos endpoints de diagnóstico. O capture lê o mesmo arquivo que `pixelhub_log` em `index.php` (`public/../logs/pixelhub.log`). Próximo passo determinístico: **deploy dessa base**, depois **Passo “provar deploy”** (Charles abre os endpoints → Cursor confere script_stamp e log_source), em seguida **rodada Passo 2** com o bloco para o Charles (áudio → request_id → capture com request_id → JSON completo). Quando esse retorno vier, decide-se se entra em VPS (upstream 3000) ou se ainda é correção de rota/env/log.
