# Erro: "Canal não encontrado" (CHANNEL_NOT_FOUND) ao enviar mensagem

## O que acontece

Ao tentar **enviar uma mensagem** pelo Painel de Comunicação (WhatsApp), aparece:

```
Erro ao enviar mensagem: HTTP 400: {
  "success": false,
  "error": "Canal não encontrado",
  "error_code": "CHANNEL_NOT_FOUND",
  "channel_id": "ImobSites"
}
```

## Por que acontece

O Painel usa o **gateway WPPConnect** para enviar mensagens. O fluxo é:

1. O Painel identifica o canal (ex: `ImobSites`) a partir da conversa ou do `channel_id` enviado.
2. Antes de enviar, o Painel consulta o gateway:  
   `GET {WPP_GATEWAY_BASE_URL}/api/channels/ImobSites`
3. Se o gateway responder **404** (sessão não existe), o Painel bloqueia o envio e devolve **"Canal não encontrado"** com `error_code: CHANNEL_NOT_FOUND`.

Ou seja: **o gateway WPPConnect não tem uma sessão com o nome exato que o Painel está usando** (no exemplo, `"ImobSites"`). O nome é **case-sensitive**.

---

## O que fazer

### 1. Ver o que está no banco (Pixel Hub)

Rode o script de diagnóstico:

```bash
php database/diagnostico-canal-imobsites.php
```

Ele mostra:

- Canais `ImobSites` (ou parecidos) em `tenant_message_channels`
- Conversas que usam esse `channel_id`
- Resumo da causa e o que fazer

### 2. Conferir no WPPConnect (gateway)

No servidor/instância do **WPPConnect**:

1. Liste as sessões (channels) disponíveis (painel ou API, ex: `GET /api/channels` ou equivalente).
2. Anote o **nome exato** da sessão do ImobSites (ex: `ImobSites`, `imobsites`, `Imob Sites`).
3. Verifique se a sessão existe e está **conectada**. Se não existir, **crie e conecte** a sessão ImobSites no gateway.

### 3. Alinhar o nome no Pixel Hub

O valor usado no Painel (vindo de `tenant_message_channels` e/ou `conversations`) tem que ser **idêntico** ao nome da sessão no WPPConnect.

- Se no gateway a sessão se chama **`imobsites`** (minúsculo), no banco deve estar **`imobsites`**, e não `ImobSites`.
- Se no gateway for **`ImobSites`**, no banco deve estar **`ImobSites`**.

Onde ajustar:

- **`tenant_message_channels`**:  
  - `channel_id` ou `session_id` (se existir) = nome exato da sessão no gateway.
- **`conversations`**:  
  - `channel_id` = mesmo nome, quando a conversa for dessa sessão.

### 4. Resumo rápido

| Situação | Ação |
|----------|------|
| Sessão ImobSites **não existe** no WPPConnect | Criar e conectar a sessão no gateway. |
| Sessão existe com **outro nome** (ex: `imobsites`) | Atualizar `channel_id`/`session_id` em `tenant_message_channels` (e `conversations` se necessário) para esse nome exato. |
| Sessão existe com o **mesmo nome** mas ainda dá 404 | Verificar URL do gateway (`WPP_GATEWAY_BASE_URL`), rede, e se a API do WPPConnect está retornando 404 para esse `channel_id`. |

---

## Script de diagnóstico

```bash
php database/diagnostico-canal-imobsites.php
```

Arquivo: `database/diagnostico-canal-imobsites.php`.

