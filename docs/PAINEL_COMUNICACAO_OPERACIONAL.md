# Painel Operacional de Comunicação

**Data:** 2025-01-31  
**Objetivo:** Interface onde operadores enviam mensagens e gerenciam conversas em tempo real

---

## 🎯 Onde Acessar

### Menu Principal

No menu lateral do PixelHub, você encontrará:

**💬 Painel de Comunicação** (botão destacado em verde)

- **URL:** `/communication-hub`
- **Acesso:** Menu lateral (logo após "Clientes")
- **Permissão:** Apenas usuários internos (Pixel12)

---

## 📋 Funcionalidades

### 1. Visualizar Conversas Ativas

O painel mostra todas as conversas organizadas por:

- **WhatsApp:** Conversas via WPP Gateway
- **Chat Interno:** Conversas do sistema de pedidos
- **Email:** (Em desenvolvimento)

**Informações exibidas:**
- Nome do cliente
- Número/contato
- Quantidade de mensagens
- Última atividade
- Contador de não lidas (quando implementado)

### 2. Filtrar Conversas

Filtros disponíveis:
- **Canal:** Todos, WhatsApp, Chat Interno
- **Cliente:** Selecionar cliente específico
- **Status:** Ativas, Todas

### 3. Enviar Mensagens

#### Opção A: Nova Mensagem (Botão Flutuante)

1. Clique no botão verde flutuante (canto inferior direito) **✉️**
2. Selecione:
   - **Canal:** WhatsApp ou Chat
   - **Cliente:** Selecione o cliente
   - **Para:** Telefone (auto-preenchido se cliente tiver WhatsApp)
   - **Mensagem:** Digite sua mensagem
3. Clique em **Enviar**

#### Opção B: Responder Conversa Existente

1. Clique em uma conversa na lista
2. Visualize o histórico de mensagens
3. Digite sua mensagem no campo inferior
4. Pressione **Enter** ou clique em **Enviar**

---

## 🔄 Fluxo de Funcionamento

### Envio de Mensagem WhatsApp

1. **Operador envia mensagem** no painel
2. **Sistema cria evento** `whatsapp.outbound.message`
3. **EventRouterService** roteia para WhatsApp
4. **WhatsAppGatewayClient** envia via gateway
5. **Mensagem chega no WhatsApp** do cliente
6. **Cliente responde** no WhatsApp
7. **Gateway recebe** e envia webhook para PixelHub
8. **WhatsAppWebhookController** cria evento `whatsapp.inbound.message`
9. **Conversa aparece** no painel automaticamente

### Envio de Mensagem Chat Interno

1. **Operador envia mensagem** no painel
2. **Sistema cria evento** `chat.outbound.message`
3. **EventRouterService** roteia para chat
4. **ServiceChatService** adiciona mensagem ao thread
5. **Cliente vê mensagem** no chat do pedido
6. **Cliente responde** no chat
7. **ChatController** cria evento `chat.inbound.message`
8. **Conversa aparece** no painel

---

## 📊 Estatísticas do Painel

O painel exibe cards com métricas:

- **Conversas WhatsApp:** Total de conversas ativas via WhatsApp
- **Chats Internos:** Total de conversas de chat interno
- **Não Lidas:** Total de mensagens não lidas (em desenvolvimento)

---

## 🎨 Interface

### Layout

```
┌─────────────────────────────────────────┐
│  Painel de Comunicação                  │
├─────────────────────────────────────────┤
│  [Estatísticas: WhatsApp | Chat | ...] │
├──────────────┬──────────────────────────┤
│  Lista de    │  Área de Mensagens       │
│  Conversas   │  (vazia até selecionar)  │
│              │                          │
│  - Cliente 1 │                          │
│  - Cliente 2 │                          │
│  - Cliente 3 │                          │
└──────────────┴──────────────────────────┘
              [Botão ✉️ Nova Mensagem]
```

### Visualização de Conversa

Quando você clica em uma conversa:

- **Header:** Nome do cliente e contato
- **Área de Mensagens:** Histórico completo (scroll automático)
- **Campo de Envio:** Textarea + botão Enviar

**Cores:**
- Mensagens **enviadas** (outbound): Fundo verde claro (#dcf8c6)
- Mensagens **recebidas** (inbound): Fundo branco

---

## ⚙️ Configuração Necessária

### Para WhatsApp Funcionar

1. **Configurar Channel por Tenant:**
   - Cada cliente precisa ter um `channel_id` configurado
   - Tabela: `tenant_message_channels`
   - Provider: `wpp_gateway`

2. **Conectar WhatsApp:**
   - Criar channel no gateway
   - Obter QR code
   - Conectar WhatsApp Business

3. **Configurar Webhook:**
   - Webhook do channel deve apontar para `/api/whatsapp/webhook`
   - Gateway envia eventos para PixelHub

### Variáveis de Ambiente

```env
WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br
WPP_GATEWAY_SECRET=seu_secret
PIXELHUB_WHATSAPP_WEBHOOK_URL=https://painel.pixel12digital.com.br/api/whatsapp/webhook
```

---

## 🚀 Próximos Passos (Melhorias Futuras)

### P1 - Funcionalidades Essenciais
- [ ] Contador de mensagens não lidas
- [ ] Notificações em tempo real (WebSocket ou polling)
- [ ] Busca de conversas
- [ ] Marcar como lida/não lida

### P2 - Funcionalidades Avançadas
- [ ] Envio de mídia (imagens, documentos)
- [ ] Templates rápidos
- [ ] Respostas automáticas (IA)
- [ ] Transferência de conversa entre operadores
- [ ] Tags e categorização

### P3 - Integrações
- [ ] Email (envio e recebimento)
- [ ] SMS
- [ ] Outros canais

---

## 📝 Exemplos de Uso

### Exemplo 1: Enviar WhatsApp para Cliente

1. Acesse **Painel de Comunicação**
2. Clique no botão **✉️** (canto inferior direito)
3. Selecione:
   - Canal: **WhatsApp**
   - Cliente: **João Silva**
   - Para: (auto-preenchido com telefone do cliente)
   - Mensagem: "Olá João, tudo bem? Passando para confirmar..."
4. Clique em **Enviar**
5. Mensagem é enviada via gateway e aparece no WhatsApp do cliente

### Exemplo 2: Responder Mensagem Recebida

1. Cliente envia mensagem no WhatsApp
2. Mensagem aparece automaticamente no painel (após webhook)
3. Clique na conversa do cliente
4. Visualize a mensagem recebida
5. Digite sua resposta e envie
6. Cliente recebe resposta no WhatsApp

---

## 🔍 Diferença entre "Central de Eventos" e "Painel de Comunicação"

### Central de Eventos (`/settings/communication-events`)
- **Propósito:** Monitoramento e auditoria
- **Foco:** Visualizar todos os eventos do sistema
- **Uso:** Análise, debug, rastreamento
- **Público:** Administradores, desenvolvedores

### Painel de Comunicação (`/communication-hub`)
- **Propósito:** Operação diária
- **Foco:** Enviar mensagens e responder clientes
- **Uso:** Atendimento, comunicação ativa
- **Público:** Operadores, atendentes

---

## 🆘 Troubleshooting

### "Nenhuma conversa encontrada"
- Verifique se há eventos de comunicação no banco
- Confirme que webhooks estão configurados
- Verifique se channels estão conectados

### "Erro ao enviar mensagem"
- Verifique se o tenant tem channel configurado
- Confirme que o gateway está acessível
- Verifique logs em `logs/pixelhub.log`

### "Mensagens não aparecem"
- Verifique se webhook está recebendo eventos
- Confirme que eventos estão sendo criados
- Verifique filtros aplicados

---

**Documento criado em:** 2025-01-31  
**Versão:** 1.0

