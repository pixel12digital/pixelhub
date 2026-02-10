# Histórico de agentes (chats) – QR Code e Gateway WhatsApp

Resumo do que foi identificado em sessões anteriores (docs e diagnósticos) e correções aplicadas para o QR ficar perpetuamente carregando.

---

## 1. Documentação / conclusões anteriores consultadas

| Doc | Conclusão principal |
|-----|----------------------|
| **CONCLUSAO_PROBLEMA_QR_CODE_IMOBSITES.md** | Gateway criava sessão só localmente, sem chamar WPPConnect. |
| **PROBLEMA_IDENTIFICADO_QR_CODE_IMOBSITES.md** | Causa raiz: WPPConnect falha ao abrir o browser (Code: 21) ao criar sessão nova; pixel12digital funcionava por já estar conectada. |
| **RESUMO_PROBLEMA_QR_CODE_IMOBSITES.md** | Gateway-wrapper não propagava criação para o WPPConnect. |
| **SOLUCAO_FINAL_QR_CODE_IMOBSITES.md** | Deletar e recriar sessão; conferir se a API retorna `.qr`, `.qrcode` ou `.data`. |
| **PLANO_DIAGNOSTICO_QR_CODE.md** | Fluxo: diagnóstico na HostMedia → Network no navegador → logs PHP; patch VPS para getQRCode quando status CONNECTED. |
| **PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md** | Patch na VPS no `wppconnectAdapter.js`: tratar JSON com `status: "CONNECTED"` e retornar 200 com `qr: null` + mensagem em vez de 500. |
| **PACOTE_VPS_QR_CODE_NA_RESPOSTA_API.md** | Garantir que `GET /api/channels/{id}/qr` retorne o QR em base64 no body para o Pixel Hub exibir. |

---

## 2. Causas prováveis do “QR perpetuamente carregando”

1. **Timeout PHP em `sessionsCreate`**  
   Create + getQrWithRetry (5× com sleep 2s) + eventual tryRestartAndGetQr pode passar de 30s (limite padrão PHP). O script era interrompido e o front podia ficar esperando resposta que nunca chegava.

2. **Requisição HTTP sem limite no frontend**  
   O fetch de create/reconnect não tinha timeout; se o servidor ou o gateway demorasse (ou travasse), o modal ficava em “Gerando QR code... Aguarde.” sem fim.

3. **Gateway não retornando QR**  
   Casos já documentados: sessão CONNECTED “zumbi” (WPPConnect não gera QR), ou resposta sem campo `qr`/`base64` (ver PACOTE_VPS_QR_CODE_NA_RESPOSTA_API e patch getQRCode na VPS).

4. **Erro no WPPConnect (ex.: browser Code 21)**  
   Em outros contextos (ex.: imobsites) o QR não era gerado porque o WPPConnect não conseguia abrir o browser; o Hub continuava em loading até timeout ou erro.

---

## 3. Correções aplicadas no Pixel Hub (código local)

- **WhatsAppGatewaySettingsController::sessionsCreate**  
  - `@set_time_limit(90)` para não matar o script no meio do create + retries + restart.

- **views/settings/whatsapp_gateway.php**  
  - **Create:** `AbortController` + timeout 85s no fetch de `/sessions/create`; em caso de timeout (AbortError) ou outro erro, exibir mensagem no modal (e “Tentar novamente”) em vez de ficar carregando para sempre.  
  - **Reconnect:** Mesmo esquema de timeout 85s no fetch de `/sessions/reconnect`; mensagem clara em caso de AbortError.  
  - **createSessionPollQr:** Timeout 85s em cada poll; após esgotar tentativas, exibir erro (incluindo timeout) em vez de continuar em loading.

Com isso, o QR passa a ter tempo suficiente no backend para ser gerado e, no front, o usuário deixa de ficar preso em “carregando” indefinidamente: ou vê o QR ou vê uma mensagem de erro/timeout e pode tentar de novo.

---

## 4. Se o QR ainda não aparecer

1. **Diagnóstico no Hub**  
   Configurações > WhatsApp Gateway > **Diagnóstico (Debug)** > “Diagnóstico QR Code” > Executar e enviar o resultado.

2. **Script na HostMedia**  
   `php scripts/diagnostico_qr_code_gateway.php pixel12digital` (ou o nome da sessão) e enviar a saída.

3. **VPS (um comando por vez)**  
   Ver qual resposta o gateway devolve para o QR:
   ```bash
   SESSION="pixel12digital"
   SECRET="$(grep -E "GATEWAY_SECRET|WPP_GATEWAY_SECRET" /opt/pixel12-whatsapp-gateway/.env 2>/dev/null | tail -1 | cut -d= -f2-)"
   curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels/$SESSION/qr"
   ```
   Se vier 500 ou “Invalid QR code response”, aplicar o patch em **PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md**. Se vier 200 sem campo `qr`/base64, seguir **PACOTE_VPS_QR_CODE_NA_RESPOSTA_API.md**.

---

**Atualização:** Fevereiro 2026 – Timeout PHP em create, timeouts e tratamento de erro no frontend para evitar loading infinito.
