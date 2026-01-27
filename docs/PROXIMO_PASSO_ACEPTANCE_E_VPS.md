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
