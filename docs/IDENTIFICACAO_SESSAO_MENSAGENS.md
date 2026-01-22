# Identificação de Sessão nas Mensagens WhatsApp

## Como o Sistema Identifica Qual Sessão Recebeu/Enviou Cada Mensagem

### 1. Armazenamento do Channel ID

Quando uma mensagem é recebida via webhook do WhatsApp Gateway, o sistema:

1. **Extrai o `channel_id` do payload** (linha 40-47 do `WhatsAppWebhookController.php`):
   - Tenta múltiplos caminhos: `payload['channel']`, `payload['channelId']`, `payload['session']['id']`, etc.
   - Armazena no campo `metadata` do evento na tabela `communication_events`

2. **Armazena no evento**:
   ```php
   'metadata' => [
       'channel_id' => $channelId,  // Ex: "pixel12digital", "ImobSites"
       'raw_event_type' => $eventType
   ]
   ```

### 2. Recuperação do Channel ID nas Mensagens

Quando as mensagens são buscadas para exibição (`getWhatsAppMessagesFromConversation`):

1. **Extrai do payload ou metadata**:
   ```php
   $eventChannelId = $payload['channel_id'] 
       ?? $payload['channel'] 
       ?? $payload['session']['id'] 
       ?? $eventMetadata['channel_id'] ?? null
       ?? $sessionId; // Fallback: usa channel_id da conversa
   ```

2. **Inclui no array de mensagens**:
   ```php
   $messages[] = [
       'id' => $event['event_id'],
       'direction' => $direction,
       'content' => $content,
       'channel_id' => $eventChannelId, // Identifica qual sessão
       // ...
   ];
   ```

### 3. Exibição na Interface

O `channel_id` é exibido como um **badge no topo de cada mensagem**:

- **Formato**: Texto em maiúsculas, pequeno, acima do conteúdo
- **Exemplo**: "PIXEL12DIGITAL" ou "IMOBSITES"
- **Estilo**: Fonte 10px, cor #666, peso 600, espaçamento de letras

### 4. Como Outros Sistemas Gerenciam (Referência: Kommo CRM)

**Kommo CRM:**
- Cada canal (WhatsApp Business API) tem um ID único
- Mensagens são agrupadas por canal + contato
- Interface mostra badge/ícone indicando o canal
- Permite filtrar conversas por canal específico

**Zendesk:**
- Cada "channel" (WhatsApp, SMS, Email) tem identificação visual
- Badge colorido indica o canal de origem
- Histórico mostra qual canal foi usado em cada interação

**HubSpot:**
- Cada integração de WhatsApp tem um nome/ID
- Mensagens mostram qual "source" (canal) foi usado
- Permite múltiplos canais WhatsApp por conta

### 5. Casos de Uso no Pixel Hub

**Cenário 1: Múltiplas Sessões WhatsApp**
- Cliente pode ter mensagens em "Pixel12 Digital" e "ImobSites"
- Cada mensagem mostra qual sessão recebeu/enviou
- Facilita identificar qual número WhatsApp foi usado

**Cenário 2: Sessão Compartilhada (Pixel Hub Central)**
- Sessão "pixel12digital" é do sistema central
- Mensagens mostram "PIXEL12DIGITAL" como channel_id
- Permite distinguir mensagens do sistema vs. mensagens de clientes específicos

**Cenário 3: Filtragem por Sessão**
- Futuro: Filtrar conversas por `channel_id` específico
- Exibir apenas mensagens de uma sessão específica
- Relatórios por sessão/canal

### 6. Estrutura de Dados

**Tabela `communication_events`:**
```sql
- event_id (UUID)
- event_type (ex: 'whatsapp.inbound.message')
- payload (JSON) - contém dados da mensagem
- metadata (JSON) - contém channel_id
```

**Tabela `conversations`:**
```sql
- channel_id (VARCHAR) - sessão principal da conversa
- channel_account_id (INT) - FK para tenant_message_channels
```

**Tabela `tenant_message_channels`:**
```sql
- channel_id (VARCHAR) - ID da sessão no gateway
- tenant_id (INT) - Cliente associado (NULL = sistema)
- provider (VARCHAR) - 'wpp_gateway'
```

### 7. Melhorias Futuras

1. **Badge Colorido por Sessão**: Cada sessão teria uma cor única
2. **Filtro por Sessão**: Filtrar conversas/mensagens por `channel_id`
3. **Estatísticas por Sessão**: Relatórios de mensagens por sessão
4. **Mapeamento de Nomes**: "pixel12digital" → "Pixel12 Digital" (mais amigável)

### 8. Exemplo Prático

**Mensagem recebida:**
- Payload: `{ "channel": "pixel12digital", "message": { "from": "554796474223@c.us", "text": "Olá" } }`
- Armazenado: `metadata.channel_id = "pixel12digital"`
- Exibido: Badge "PIXEL12DIGITAL" acima da mensagem

**Mensagem enviada:**
- Enviada via: `channel_id = "ImobSites"`
- Armazenado: `metadata.channel_id = "ImobSites"`
- Exibido: Badge "IMOBSITES" acima da mensagem

