# Configuração do Gateway WhatsApp: VPS e Projeto (Hostmidia)

**Data:** 26/01/2026  
**Objetivo:** Referência única para deploy do gateway na VPS e do projeto PixelHub no hostmidia.

---

## 1. Arquitetura

```
[Navegador] → [Projeto PixelHub - Hostmidia] → [Gateway WPP - VPS] → [WhatsApp]
                      ↓                                    ↓
               PHP, Apache/Nginx,              Node (PM2), Nginx
               MySQL, storage/                wpp.pixel12digital.com.br
```

- **Gateway (VPS):** `https://wpp.pixel12digital.com.br` — recebe/envia mensagens e mantém sessões WhatsApp.
- **Projeto (Hostmidia):** aplicação PHP (Painel) que consome a API do gateway e armazena mídias em `storage/whatsapp-media/`.

---

## 2. VPS (Gateway)

### 2.1 Nginx

- **Arquivo:** `/etc/nginx/sites-available/whatsapp-multichannel` (ou equivalente em `sites-enabled`).
- **Timeouts mínimos** (obrigatórios para áudio):
  ```nginx
  proxy_connect_timeout 120s;
  proxy_send_timeout 120s;
  proxy_read_timeout 120s;
  ```
- **Recarregar sem derrubar conexões:** `kill -HUP $(cat /var/run/nginx.pid)` ou `nginx -s reload`.

### 2.2 Gateway (Node/PM2)

- **Processo:** em geral `wpp-ui` ou nome do app no PM2.
- **Porta interna:** ex.: 3100 (localhost).
- **Nginx:** proxy reverso para `http://127.0.0.1:3100` (ou porta configurada).

### 2.3 Scripts de diagnóstico (no repositório)

- `database/diagnostico-gateway-audio-vps.sh` — timeouts, PM2, recursos, conectividade.
- `database/consultar-timeout-nginx.sh` — conferir timeouts do Nginx.
- `database/atualizar-timeout-nginx.sh` — ajustar timeouts.
- `database/reload-nginx-suave.sh` — reload suave do Nginx.

---

## 3. Projeto no Hostmidia

### 3.1 Variáveis de ambiente (.env)

| Variável | Uso |
|----------|-----|
| `WPP_GATEWAY_BASE_URL` | URL do gateway (ex.: `https://wpp.pixel12digital.com.br`) |
| `WPP_GATEWAY_SECRET` | Chave para header `X-Gateway-Secret` (criptografada pelo painel) |

### 3.2 Timeouts (envio de áudio)

- **PHP:** `set_time_limit(120)` e `max_execution_time` 120s para requests de áudio.
- **Cliente HTTP (cURL):** 90s para `sendAudioBase64Ptt` (em `WhatsAppGatewayClient`).

### 3.3 Áudio: WebM → OGG

O WhatsApp exige **OGG/Opus** para voice. O painel faz:

1. **Frontend:** se o navegador suportar `audio/ogg;codecs=opus` (ex.: Firefox), converte WebM→OGG antes de enviar.
2. **Backend:** se ainda chegar WebM, converte com **ffmpeg** antes de chamar o gateway.

Requisito no servidor (hostmidia): **ffmpeg** no PATH quando há áudios gravados em WebM (ex.: Chrome). Sem ffmpeg, apenas navegadores que já gravam em OGG (ex.: Firefox) enviam voice com sucesso.

### 3.4 Endpoint de mídia

- **URL:** `GET /communication-hub/media?path=whatsapp-media/...`
- **Autenticação:** requer sessão interna (`Auth::requireInternal()`).
- **Armazenamento:** arquivos em `storage/whatsapp-media/` (ex.: `whatsapp-media/tenant-1/2026/01/16/xxx.ogg`).
- **Segurança:** path deve começar com `whatsapp-media`, não pode conter `..` e o arquivo servido deve estar dentro de `storage/`.
- **Reprocessamento:** se o arquivo não existir mas houver registro em `communication_media`, o backend tenta reprocessar a mídia a partir do evento antes de responder 404.

---

## 4. Roteiro de testes (fluxo completo)

### 4.1 Texto

1. Abrir Central de Comunicação, escolher conversa WhatsApp.
2. Digitar mensagem e enviar.
3. Verificar entrega e resposta do gateway (sem 500/404).

### 4.2 Áudio

1. Gravar áudio (4–10 s) no painel.
2. Enviar.
3. **Chrome:** checar se o backend converte WebM→OGG (ffmpeg no servidor) ou se aparece erro orientando uso de Firefox/ffmpeg.
4. **Firefox:** esperar envio direto em OGG.
5. Confirmar que não há timeout (Nginx 120s, PHP 120s, cURL 90s) e que a mensagem aparece no WhatsApp.

### 4.3 Mídias (imagem, PDF, documento)

1. Enviar imagem/PDF/documento pelo painel (quando a UI de anexos estiver disponível).
2. Verificar se o gateway aceita e se a mensagem chega no WhatsApp.
3. Receber mídia pelo WhatsApp e abrir no painel: acessar `media.url` (ex.: `/communication-hub/media?path=whatsapp-media/...`) com sessão logada e conferir se o arquivo abre (imagem/áudio/PDF).

### 4.4 Verificação do endpoint de mídia

1. Obter uma URL de mídia de uma mensagem (ex.: `media.url` na resposta da thread).
2. Acessar essa URL com o mesmo navegador (sessão ativa).
3. Esperar 200 e exibição/download do arquivo; 403 para path inválido; 404 se arquivo e reprocessamento falharem.

**Script de apoio:** `database/testar-endpoint-media.php` — confere arquivo físico, URL gerada e existência do método `serveMedia()` (requer uma mídia no banco; altere o `event_id` na query se necessário).

---

## 5. Referências no repositório

| Documento | Conteúdo |
|-----------|----------|
| `docs/RESUMO_TIMEOUT_AUDIO_COMPLETO.md` | Timeouts Nginx, áudio, conversão WebM→OGG |
| `docs/CHECKLIST_WHATSAPP_GATEWAY_PRODUCAO.md` | Checklist de arquivos e rotas do gateway |
| `docs/FASE1_WPP_GATEWAY.md` | Arquitetura e eventos do gateway |
| `.cursor/skills/whatsapp-integration/SKILL.md` | Uso do `WhatsAppGatewayClient` e fluxos |

---

## 6. Resumo rápido

| Onde | O quê |
|------|--------|
| **VPS** | Nginx com proxy 120s; PM2 com app do gateway; porta interna (ex.: 3100). |
| **Hostmidia** | `WPP_GATEWAY_BASE_URL` e `WPP_GATEWAY_SECRET`; PHP 120s e cURL 90s para áudio; ffmpeg para WebM→OGG; `GET /communication-hub/media?path=...` serve arquivos em `storage/whatsapp-media/`. |
