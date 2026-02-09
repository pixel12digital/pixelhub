# Implementação: Sessões WhatsApp no Pixel Hub

**Objetivo:** Permitir gerenciar sessões WhatsApp (criar, reconectar, ver status real) diretamente em `/settings/whatsapp-gateway`, sem depender da UI da VPS.

**Status:** ✅ Implementado (2025-02)

---

## 1. O que já existe

| Componente | Status |
|------------|--------|
| `WhatsAppGatewayClient::listChannels()` | ✅ |
| `WhatsAppGatewayClient::getQr()` | ✅ |
| `WhatsAppGatewayClient::createChannel()` | ✅ |
| `WhatsAppGatewayClient::getChannel()` | ✅ |
| `GET /settings/whatsapp-gateway/test/channels` | ✅ Já retorna canais do gateway |
| Healthcheck com `getQr()` para zombie | ✅ |

---

## 2. O que implementar

### 2.1 Novo endpoint: `POST /settings/whatsapp-gateway/sessions/{channelId}/reconnect`

- Chama `getQr($channelId)`
- Retorna `{ success, qr_base64?, error }` para exibir QR na UI

### 2.2 Novo endpoint: `POST /settings/whatsapp-gateway/sessions/create`

- Body: `{ channel_id: string }`
- Chama `createChannel($channelId)`
- Retorna `{ success, qr_base64?, error }` para exibir QR na UI

### 2.3 Novo endpoint: `GET /settings/whatsapp-gateway/sessions` (ou enriquecer `listChannels`)

- Lista canais do gateway
- Enriquece com `last_activity_at` de `webhook_raw_logs` (última mensagem)
- Retorna: `[{ id, status, last_activity_at, is_zombie? }]`

### 2.4 UI na página `/settings/whatsapp-gateway`

- Seção **"Sessões WhatsApp"** (aba ou card abaixo do formulário)
- Lista de sessões com:
  - Nome/id
  - Status (Conectado / Desconectado / QR pendente)
  - Última atividade (se houver)
  - Botão "Reconectar" → chama reconnect → exibe QR
  - Botão "Reconectar" para zombies (sem atividade há 4h+)
- Formulário "Nova sessão" → nome do canal → chama create → exibe QR

---

## 3. Estrutura de arquivos

```
src/Controllers/
  WhatsAppGatewaySettingsController.php  → adicionar métodos: sessions, reconnect, createSession

views/settings/
  whatsapp_gateway.php                   → adicionar seção Sessões (HTML + JS)

public/index.php
  → rotas: GET /settings/whatsapp-gateway/sessions
           POST /settings/whatsapp-gateway/sessions
           POST /settings/whatsapp-gateway/sessions/{id}/reconnect
```

---

## 4. Dependências

- **Nenhuma alteração na VPS** — tudo via API do gateway (já existente)
- **Banco** — `webhook_raw_logs` já existe para `last_activity_at`
- **Código local** — apenas PHP + HTML/JS no Pixel Hub

---

## 5. Esforço estimado

| Tarefa | Complexidade | Tempo |
|--------|--------------|-------|
| Novos endpoints (reconnect, create) | Baixa | 1h |
| Enriquecer listChannels com last_activity | Média | 30min |
| UI (cards de sessão + modais QR) | Média | 2h |
| Testes | Baixa | 30min |

**Total:** ~4h de desenvolvimento

---

## 6. Referências

- `docs/INVESTIGACAO_UI_STATUS_CONECTADO_INCONSISTENTE.md`
- `scripts/healthcheck_whatsapp_sessions.php` — lógica de zombie
- `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`
