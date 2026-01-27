# Próximo passo – Patch HostMedia + teste + blocos VPS

**Data:** 27/01/2026

---

## 1. Patch HostMedia (implementado no código local)

### 1.1 O que foi garantido

| Item | Estado |
|------|--------|
| **request_id no JSON de erro** | Todas as respostas de erro de `POST /communication-hub/send` incluem `request_id` (validação, gateway, exceção). |
| **X-Request-Id no header de resposta** | Hub envia `X-Request-Id: {requestId}` na resposta ao front. |
| **X-Request-Id ao gateway** | `WhatsAppGatewayClient::setRequestId($requestId)` é chamado antes de cada envio; o client envia header `X-Request-Id` na requisição ao gateway. |
| **Sanitização base64Ptt** | `WhatsAppGatewayClient::sendAudioBase64Ptt()` remove prefixo `data:*;base64,` antes de enviar; só base64 cru vai no body. |

### 1.2 Arquivos tocados

- `src/Controllers/CommunicationHubController.php`:  
  - `request_id` em todos os retornos de erro de `send()` (validação, THREAD_*, CHANNEL_NOT_FOUND, telefone inválido, canal não implementado).  
  - Header de resposta `X-Request-Id` (antes `X-Request-ID`).
- `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`: já estava correto (setRequestId, X-Request-Id, sanitização base64).

### 1.3 Critério de aceite (patch)

- Qualquer erro 4xx/5xx de `POST /communication-hub/send` traz `request_id` no body.
- O front pode exibir/mostrar `request_id` no body do erro.
- Próximo passo: **commit → deploy HostMedia** antes de qualquer novo teste.

---

## 2. Instruções para o Charles – Ação 2 (teste de aceite no Chrome)

**Objetivo:** Rodar o envio de áudio no Hub (Chrome) e devolver 3 evidências.

**O que fazer:**

1. Abrir o Hub no Chrome, ir na conversa, enviar um áudio de 4–10s.
2. Medir/anotar:
   - **Tempo total** até sucesso ou erro (ex.: 8s ou 46s).
   - **Body do erro** do `POST /communication-hub/send` em caso de falha: copiar o JSON (com `error_code`, `origin`, `reason`, **`request_id`**).
   - **Timestamp exato** do clique/envio (com fuso; ideal UTC).

**Interpretação:**

- Se sucesso em **&lt;15s**: encerrar como “proxy timeout” resolvido; só limpar pendências.
- Se ainda falhar em **~46s**: Nginx deixa de ser suspeito → foco no gateway/Node/WPPConnect; usar os blocos VPS abaixo e seguir com D+E no gateway.

---

## 3. BLOCO VPS 1 – Logs do gateway por tempo

**Usar só se o teste de aceite falhar (~46s).**

Pedir ao Charles rodar e devolver a saída destes comandos:

```bash
pm2 list
pm2 logs wpp-ui --lines 600 --nostream
```

**Objetivo:** Achar no log indício de: conversão ffmpeg, chamada sendVoiceBase64, erro de rede/timeout, stacktrace.

**Importante:** Pedir o **horário exato da falha** e que ele cole junto, pois o gateway ainda não loga X-Request-Id.

---

## 4. BLOCO VPS 2 – Nginx access/error no horário da falha

**Usar só se o BLOCO 1 não mostrar nada conclusivo.**

Pedir ao Charles rodar e devolver:

```bash
# Ajustar HORA_INICIO e HORA_FIM para o intervalo da falha (ex.: 14:50 a 14:52)
tail -n 500 /var/log/nginx/access.log
tail -n 500 /var/log/nginx/error.log
```

(Se os paths forem outros na srv817568, usar os paths reais de access.log e error.log do site do gateway.)

**Objetivo:** Confirmar se o 500 nasceu no upstream (gateway) e se houve upstream timeout/closed connection.

---

## 5. Ação obrigatória no gateway (VPS) – D+E

Enquanto D+E não forem implementados no gateway, o diagnóstico segue “cego”. Prioridade:

- **D:** Ler `X-Request-Id`; prefixar logs com `[req=<id>]`; logar etapas e duração (received → sanitized_base64 → convert_start/end → wppconnect_send_start/end → returned); devolver no response header `X-Request-Id` e no JSON `request_id`.
- **E:** Timeouts por etapa: ffmpeg convert 10–15s; WPPConnect sendVoiceBase64 30–60s (config); em caso de estouro devolver `error_code`, `origin: "gateway"`, `reason: "TIMEOUT_STAGE_CONVERT"` | `"TIMEOUT_STAGE_WPPCONNECT"`, `request_id`.

**Critério de aceite D+E:** Para um `request_id` do Hub, conseguir localizar nos `pm2 logs` a trilha completa e saber em qual etapa travou.

---

## 6. Resultado do teste de aceite (27/01/2026)

**Fonte:** console do Hub (Chrome), envio de áudio 4s (WebM/Opus, ~70KB).

### 6.1 Evidências coletadas

| Evidência | Valor |
|-----------|--------|
| **Tempo total até erro** | **~76s** (76077 ms, 76081 ms, 76117 ms) |
| **HTTP** | POST `/communication-hub/send` → **500** |
| **Body do erro** | `success: false`, `error_code: 'GATEWAY_HTML_ERROR'`, `origin: 'gateway'`, `reason: 'GATEWAY_HTML_ERROR'`, **`request_id: 'efec12e07f679c7c'`** |
| **Conteúdo real do gateway** | Resposta era HTML; título da página: **"504 Gateway Time-out"** |
| **Timestamp envio (UTC)** | `2026-01-27T13:21:01.903Z` (fetch iniciado) |
| **Timestamp fim (UTC)** | `2026-01-27T13:22:17.980Z` (fetch concluído) |

### 6.2 Patch em produção

- O front recebeu `request_id` no body do erro → patch HostMedia (request_id + X-Request-Id + base64) está **em produção**.

### 6.3 503 em paralelo

- Enquanto o POST `/send` ficou ~76s em curso, as requisições GET `/communication-hub/messages/check` e `/communication-hub/check-updates` passaram a retornar **503** com corpo HTML (`<!DOCTYPE`), gerando `Unexpected token '<', "<!DOCTYPE "... is not valid JSON"`.
- Indica que, durante o envio longo, o Hub (ou o proxy na frente) ficou indisponível/sobrecarregado para outras requisições.

---

## 7. Diagnóstico e próximo passo

| O que o teste prova | Conclusão |
|--------------------|-----------|
| **504 depois de ~76s** | Algum proxy na cadeia **Hub → Gateway** (ou na frente do Hub) ainda está com timeout em torno de **60s**. O 504 (“Gateway Time-out”) é a página típica do Nginx quando o upstream não responde a tempo. |
| **76s e não 46s** | O PHP no Hub esperou até receber a resposta (HTML 504) do gateway/proxy; o tempo maior inclui latência até o front. O corte ocorreu no proxy/upstream, não no front. |
| **request_id presente** | Correlação pronta para quando D+E estiverem no gateway; até lá usamos horário (ex.: 13:21–13:22 UTC) para procurar nos logs. |

**Próximo passo recomendado:**

1. **BLOCO VPS 1** — No horário **13:21–13:22 UTC** (27/01/2026), pedir ao Charles: `pm2 list` + `pm2 logs wpp-ui --lines 600 --nostream`, e colar a saída. Objetivo: ver se o request chegou ao gateway, se houve convert/sendVoiceBase64 e onde parou.
2. **BLOCO VPS 2** — Se o BLOCO 1 não for conclusivo: `tail` do `access.log` e `error.log` do Nginx no mesmo intervalo, para confirmar upstream timeout/504.
3. **Checar timeout na frente do Hub** — Se o 504 vier do **HostMedia** (proxy reverso na frente de hub.pixel12digital.com.br), esse proxy precisa de timeout ≥ 120s para o backend; caso contrário o Hub nunca “espera” o gateway até o fim.
4. **D+E no gateway** — Seguir prioridade para parar de depender de horário e usar `request_id` nos logs (ex.: `efec12e07f679c7c`).

---

## 8. Resultado dos blocos VPS (27/01/2026)

### 8.1 BLOCO VPS 1 – pm2 list + pm2 logs wpp-ui

| Saída | Resultado |
|-------|-----------|
| **pm2 list** | wpp-ui (id 0), online, uptime 6D, pid 427984. |
| **pm2 logs wpp-ui --lines 600 --nostream** | Só linhas “WPP UI rodando em http://127.0.0.1:3100”; error.log vazio. Nenhum rastro de POST, ffmpeg, sendVoiceBase64 ou stacktrace. |

**Conclusão:** Sem evidência de que o request de áudio tenha sido processado (ou sequer logado) pelo processo wpp-ui.

### 8.2 BLOCO VPS 2 – Nginx logs do gateway

| Comando / arquivo | Resultado |
|-------------------|-----------|
| **date** | `Tue Jan 27 13:33:06 UTC 2026` → VPS em **UTC**. |
| **grep "27/Jan/2026"** em `wpp.pixel12digital.com.br_access.log.1` e `_error.log.1` | **Nenhuma linha.** |
| **grep "27/Jan/2026:10:2"** (idem) | **Nenhuma linha.** |

O acesso de **hoje** (27 Jan) cairia em `wpp.pixel12digital.com.br_access.log` (e error), que estão **vazios** (0 bytes). O `.1` é rotação de antes de 27/Jan.

**Conclusão:** Na VPS **não há registro** do POST de áudio de ~13:21 UTC no Nginx do gateway (vhost wpp.pixel12digital.com.br). Ou o request **não chegou** a esse servidor, ou o tráfego do Hub não passa por esse vhost/log.

### 8.3 Diagnóstico pós-VPS

| Evidência | Conclusão |
|-----------|-----------|
| Nenhuma linha de 27/Jan nos logs do gateway (Nginx + pm2) | O 504 “Gateway Time-out” que o Hub recebeu **não** foi gerado pelo Nginx nem pelo wpp-ui **nesta VPS** no horário da falha. |
| Front recebeu 504 em ~76s; body HTML “504 Gateway Time-out” | O timeout ocorreu **antes** do gateway na srv817568: em algum proxy/CDN **à frente** (ex.: HostMedia, ou proxy reverso na frente de wpp.pixel12digital.com.br) com timeout ~60s. Esse proxy corta e devolve 504 ao Hub enquanto o Hub ainda espera o gateway. |

**Próximo passo recomendado (HostMedia / infra):**

1. **Confirmar onde está o timeout de ~60s** entre Hub e gateway:  
   - Se o Hub chama `WPP_GATEWAY_BASE_URL` (ex.: wpp.pixel12digital.com.br) passando por um proxy reverso no HostMedia (ou outro proxy/CDN), esse proxy precisa de **timeout ≥ 120s** para o upstream (gateway).  
   - Se for 60s, o proxy devolve 504 antes do gateway responder, e o request pode nem chegar à srv817568 (ou chega e o proxy desiste de esperar).

2. **Garantir que o tráfego Hub → gateway** realmente chegue à srv817568 (DNS de wpp.pixel12digital.com.br, ausência de proxy intermediário com 60s, etc.).

3. **D+E no gateway** — Mantém prioridade para, quando o request passar a chegar e for logado, correlacionar por `request_id` e identificar a etapa que trava.
