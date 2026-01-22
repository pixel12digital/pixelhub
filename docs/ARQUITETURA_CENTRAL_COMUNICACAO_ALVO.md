# Arquitetura Alvo: Central de Comunicação PixelHub

**Data:** 2026-01-09  
**Versão:** 1.0  
**Objetivo:** Definir como a Central de Comunicação deve funcionar no dia a dia, com arquitetura escalável, multi-tenant e multi-produto.

---

## 1. Conceitos Oficiais

### 1.1 Inbox

**Definição:** Interface única onde atendentes visualizam, triam e respondem todas as conversas de todos os canais (WhatsApp, e-mail, chat, redes sociais).

**Características:**
- **Unificada:** Uma única tela para todos os canais
- **Contextual:** Sempre mostra tenant, produto, módulo e histórico
- **Priorizada:** Ordenação inteligente por SLA, não lidas, prioridade
- **Filtrada:** Filtros por canal, time, status, atendente, tags

**Não é:**
- ❌ Um sistema separado para cada canal
- ❌ Uma lista de mensagens individuais
- ❌ Um chat simples

**É:**
- ✅ Um hub conversacional profissional
- ✅ Um CRM de comunicação
- ✅ Um centro de operações

---

### 1.2 Conversation vs Ticket vs Lead/Deal

#### **Conversation (Conversa)**

**Definição:** Thread persistente que agrupa todas as mensagens trocadas com um contato em um canal específico.

**Características:**
- Uma conversa = um contato + um canal + um tenant (opcional)
- Pode ter múltiplos participantes (contato, atendentes, bots)
- Tem status: `new`, `open`, `pending`, `closed`, `archived`
- Tem atribuição: `assigned_to` (user_id)
- Tem metadata: tags, notas internas, vínculos

**Exemplo:**
```
Conversation #123
- Contact: João Silva (5547999999999)
- Channel: WhatsApp (channel_id: "Pixel12 Digital")
- Tenant: NULL (contato não identificado como cliente)
- Status: open
- Assigned to: user_id 5 (Maria)
- Created: 2026-01-09 10:00:00
- Last message: 2026-01-09 15:30:00
```

#### **Ticket**

**Definição:** Caso de suporte/atendimento criado a partir de uma conversa ou independentemente.

**Relação com Conversation:**
- Uma conversa pode gerar um ticket
- Um ticket pode ter múltiplas conversas vinculadas
- Ticket tem SLA, prioridade, categoria
- Ticket pode ser fechado independentemente da conversa

**Exemplo:**
```
Ticket #456
- Title: "Problema com domínio expirado"
- Category: "Hospedagem"
- Priority: "high"
- SLA: 2 horas
- Status: "open"
- Related conversations: [123, 124]
- Related tenant: tenant_id 10
```

#### **Lead/Deal**

**Definição:** Oportunidade de venda ou contato comercial.

**Relação com Conversation:**
- Uma conversa pode gerar um lead
- Um lead pode ter múltiplas conversas
- Lead tem estágio no funil (qualificação, proposta, fechamento)
- Lead pode virar deal (negócio fechado)

**Exemplo:**
```
Lead #789
- Name: "João Silva"
- Phone: "5547999999999"
- Source: "WhatsApp - Conversa #123"
- Stage: "qualificacao"
- Value: R$ 2.000,00
- Related conversations: [123]
```

**Resumo:**
- **Conversation** = Thread de comunicação
- **Ticket** = Caso de suporte (pode ter conversas)
- **Lead/Deal** = Oportunidade de venda (pode ter conversas)

---

### 1.3 Identidade do Canal

**Definição:** Identificador único de um canal de comunicação (número WhatsApp, e-mail, widget chat, etc.).

**Estrutura:**
```php
ChannelAccount {
    id: int
    channel_type: 'whatsapp' | 'email' | 'webchat' | 'instagram' | 'facebook'
    provider: 'wpp_gateway' | 'smtp' | 'intercom' | 'zendesk'
    external_id: string  // Número WhatsApp, e-mail, etc.
    display_name: string  // "Pixel12 Digital", "suporte@empresa.com"
    tenant_id: int | null  // NULL = canal compartilhado
    product_id: int | null  // NULL = canal genérico
    is_enabled: boolean
    metadata: JSON  // Configurações específicas do canal
}
```

**Regras:**
- Um canal pode atender múltiplos tenants (se `tenant_id = NULL`)
- Um canal pode atender múltiplos produtos (se `product_id = NULL`)
- Quando uma mensagem chega, o sistema resolve:
  1. Qual tenant? (por número, e-mail, ou contexto)
  2. Qual produto? (por regra de roteamento ou metadata)

**Exemplo:**
```
ChannelAccount #1
- channel_type: 'whatsapp'
- provider: 'wpp_gateway'
- external_id: '554797309525'
- display_name: 'Pixel12 Digital'
- tenant_id: NULL  // Compartilhado
- product_id: NULL  // Genérico
```

**Roteamento por Produto:**
Quando uma mensagem chega em um canal compartilhado, o sistema decide o produto por:
1. **Regra de roteamento:** Palavra-chave, origem, horário
2. **Contexto da conversa:** Se já existe conversa, usa produto dela
3. **Default:** Produto padrão do canal

---

### 1.4 Roteamento

**Definição:** Processo de decidir para onde uma conversa/mensagem deve ir.

**Tipos de Roteamento:**

#### **Por Produto**
- Mensagem → Detecta produto (palavra-chave, origem) → Roteia para time do produto

#### **Por Time**
- Mensagem → Time (Vendas/Suporte/Financeiro) → Roteia para fila do time

#### **Por Regra**
- Mensagem → Avalia regras (horário, palavra-chave, status do cliente) → Roteia conforme regra

#### **Por Horário**
- Mensagem → Fora do horário comercial → Fila de "Aguardando" ou Bot

#### **Round-Robin**
- Mensagem → Time → Distribui entre atendentes disponíveis (round-robin)

#### **Por Disponibilidade**
- Mensagem → Time → Atribui ao atendente com menor carga

#### **Por Prioridade**
- Mensagem → Prioridade alta → Atribui ao atendente sênior

**Implementação:**
```php
RoutingRule {
    id: int
    name: string
    priority: int  // Menor = maior prioridade
    conditions: JSON  // { channel, keywords, tenant_status, time_range }
    action: 'assign_to_team' | 'assign_to_user' | 'create_ticket' | 'send_to_bot'
    target: string  // team_id, user_id, bot_id, etc.
    is_enabled: boolean
}
```

---

## 2. Normalização de Mensagens

### 2.1 Modelo Único de Mensagem

Todos os canais devem normalizar mensagens para este formato:

```php
NormalizedMessage {
    // Identificação
    message_id: string (UUID)
    external_message_id: string  // ID do provedor (para dedupe)
    conversation_id: int  // FK para conversations
    
    // Tipo e Direção
    message_type: 'text' | 'image' | 'audio' | 'video' | 'file' | 'location' | 'interactive' | 'template'
    direction: 'inbound' | 'outbound'
    
    // Canal
    channel: 'whatsapp' | 'email' | 'webchat' | 'instagram' | 'facebook'
    channel_account_id: int  // FK para channel_accounts
    provider: 'wppconnect' | 'whapi' | 'smtp' | 'intercom'
    
    // Conteúdo
    content: {
        text: string | null
        subject: string | null  // Para e-mail
        body_html: string | null  // Para e-mail
        media_url: string | null  // URL da mídia
        media_type: string | null  // image/jpeg, audio/mpeg, etc.
        file_name: string | null
        file_size: int | null
        location: { lat, lng, name } | null
        interactive: JSON | null  // Botões, listas, etc.
    }
    
    // Contexto
    context: {
        campaign_id: int | null  // Campanha de marketing
        module: 'sales' | 'support' | 'billing' | 'marketing' | null
        tenant_id: int | null
        product_id: int | null
        utm_source: string | null
        utm_medium: string | null
        utm_campaign: string | null
    }
    
    // Metadados
    metadata: {
        sent_by: int | null  // user_id (se outbound)
        sent_by_name: string | null
        read_at: datetime | null
        delivered_at: datetime | null
        failed_at: datetime | null
        error_message: string | null
        raw_payload: JSON  // Payload original do provedor
    }
    
    // Timestamps
    created_at: datetime
    updated_at: datetime
}
```

### 2.2 Mapeamento por Canal

#### **WhatsApp (WPP Gateway)**
```php
// Payload recebido
{
    "event": "message",
    "channel": "Pixel12 Digital",
    "message": {
        "id": "false_5547999999999@c.us_ABC123",
        "from": "5547999999999@c.us",
        "text": "Olá, preciso de ajuda",
        "timestamp": 1767989246
    }
}

// Normalizado para
{
    "message_type": "text",
    "direction": "inbound",
    "channel": "whatsapp",
    "provider": "wppconnect",
    "external_message_id": "false_5547999999999@c.us_ABC123",
    "content": {
        "text": "Olá, preciso de ajuda"
    },
    "metadata": {
        "raw_payload": { ... }
    }
}
```

#### **E-mail (SMTP)**
```php
// Payload recebido (exemplo)
{
    "from": "cliente@example.com",
    "to": "suporte@empresa.com",
    "subject": "Dúvida sobre fatura",
    "body_html": "<p>Olá...</p>",
    "body_text": "Olá..."
}

// Normalizado para
{
    "message_type": "text",
    "direction": "inbound",
    "channel": "email",
    "provider": "smtp",
    "external_message_id": "email_123456",
    "content": {
        "text": "Olá...",
        "subject": "Dúvida sobre fatura",
        "body_html": "<p>Olá...</p>"
    }
}
```

---

## 3. Regras de Multi-Tenant (Não Negociáveis)

### 3.1 Isolamento por Tenant

**Regra:** Um atendente de um tenant NUNCA pode ver conversas de outro tenant, exceto se explicitamente autorizado.

**Implementação:**
```sql
-- Todas as queries devem incluir filtro de tenant
SELECT * FROM conversations 
WHERE tenant_id = ? 
AND (assigned_to = ? OR assigned_to IS NULL)
```

**Exceções:**
- Usuários com `is_internal = 1` podem ver todos os tenants
- Usuários com permissão específica podem ver múltiplos tenants

### 3.2 Atendente Multi-Tenant

**Regra:** Um atendente pode operar múltiplos tenants, mas deve alternar contexto explicitamente.

**Implementação:**
```php
// Tabela: user_tenant_permissions
user_tenant_permissions {
    id: int
    user_id: int
    tenant_id: int
    role: 'viewer' | 'agent' | 'admin'
    is_active: boolean
}

// Na Inbox, atendente seleciona tenant ativo
// Todas as queries filtram por tenant_id selecionado
```

**UI:**
- Top bar mostra tenant ativo
- Dropdown para alternar tenant
- Filtros e buscas respeitam tenant ativo

### 3.3 Canal Multi-Produto

**Regra:** Um número de WhatsApp pode atender múltiplos produtos, mas cada conversa pertence a um produto específico.

**Implementação:**
```php
// Tabela: conversations
conversations {
    ...
    product_id: int | null  // NULL = produto genérico
    ...
}

// Roteamento decide product_id na primeira mensagem
// Conversas subsequentes mantêm mesmo product_id
```

**Decisão de Produto:**
1. **Primeira mensagem:** Regra de roteamento decide produto
2. **Mensagens subsequentes:** Usa `product_id` da conversa existente
3. **Mudança manual:** Atendente pode alterar `product_id` (com auditoria)

### 3.4 Prevenção de Vazamento de Dados

**Regras:**
1. **Queries sempre filtram por tenant_id** (exceto admins)
2. **Foreign keys garantem integridade** (tenant_id em todas as tabelas)
3. **Logs de auditoria** registram acesso a dados de outros tenants
4. **Validação em todas as APIs** verifica permissão antes de retornar dados

**Exemplo:**
```php
// Controller sempre valida
public function getConversation(int $conversationId): void
{
    $conversation = ConversationService::findById($conversationId);
    
    // Valida acesso
    if (!$this->canAccessTenant($conversation['tenant_id'])) {
        throw new ForbiddenException('Acesso negado');
    }
    
    // Retorna dados
    $this->json($conversation);
}
```

---

## 4. Roteamento e Filas

### 4.1 Estrutura de Filas

**Filas por Time:**
```
Inbox
├── Vendas
│   ├── Novas (não atribuídas)
│   ├── Em atendimento (atribuídas)
│   └── Pendentes (aguardando resposta)
├── Suporte
│   ├── Novas
│   ├── Em atendimento
│   └── Pendentes
└── Financeiro
    ├── Novas
    ├── Em atendimento
    └── Pendentes
```

**Filas Especiais:**
- **Prioritárias:** Conversas com prioridade alta
- **SLA Estourando:** Conversas próximas de estourar SLA
- **Bots:** Conversas sendo atendidas por bot

### 4.2 Regras de Roteamento

**Estrutura:**
```php
RoutingRule {
    id: int
    name: string  // "Vendas - Palavra-chave 'orçamento'"
    priority: int  // 1 = maior prioridade
    is_enabled: boolean
    
    // Condições (AND entre condições, OR entre regras)
    conditions: {
        channels: ['whatsapp', 'email'] | null  // null = todos
        keywords: ['orçamento', 'preço', 'valor'] | null
        tenant_status: ['active', 'inactive'] | null
        time_range: { start: '09:00', end: '18:00' } | null
        product_id: int | null
        contact_tags: ['vip', 'premium'] | null
    }
    
    // Ação
    action: {
        type: 'assign_to_team' | 'assign_to_user' | 'create_ticket' | 'send_to_bot'
        target: int  // team_id, user_id, bot_id
        priority: 'low' | 'normal' | 'high' | 'urgent'
        sla_minutes: int  // Tempo de primeira resposta
    }
}
```

**Exemplos:**

**Regra 1: Vendas - Palavra-chave**
```json
{
    "name": "Vendas - Palavra-chave 'orçamento'",
    "priority": 1,
    "conditions": {
        "keywords": ["orçamento", "preço", "valor", "quanto custa"]
    },
    "action": {
        "type": "assign_to_team",
        "target": 1,  // team_id: Vendas
        "priority": "high",
        "sla_minutes": 30
    }
}
```

**Regra 2: Suporte - Cliente Ativo**
```json
{
    "name": "Suporte - Cliente Ativo",
    "priority": 2,
    "conditions": {
        "tenant_status": ["active"],
        "keywords": ["problema", "erro", "não funciona"]
    },
    "action": {
        "type": "assign_to_team",
        "target": 2,  // team_id: Suporte
        "priority": "normal",
        "sla_minutes": 60
    }
}
```

**Regra 3: Financeiro - Fora do Horário**
```json
{
    "name": "Financeiro - Fora do Horário",
    "priority": 3,
    "conditions": {
        "time_range": { "start": "18:00", "end": "09:00" },
        "keywords": ["fatura", "pagamento", "vencimento"]
    },
    "action": {
        "type": "send_to_bot",
        "target": 1,  // bot_id: Bot Financeiro
        "priority": "normal"
    }
}
```

### 4.3 Atribuição (Assignment)

**Métodos:**

#### **Round-Robin**
```php
// Distribui entre atendentes disponíveis em ordem
$agents = TeamService::getAvailableAgents($teamId);
$nextAgent = RoundRobinService::getNext($teamId);
assignConversation($conversationId, $nextAgent['user_id']);
```

#### **Por Disponibilidade**
```php
// Atribui ao atendente com menor carga
$agents = TeamService::getAvailableAgents($teamId);
$leastBusy = min($agents, fn($a) => $a['active_conversations']);
assignConversation($conversationId, $leastBusy['user_id']);
```

#### **Por Prioridade**
```php
// Atribui ao atendente sênior se prioridade alta
if ($priority === 'urgent') {
    $seniorAgent = TeamService::getSeniorAgent($teamId);
    assignConversation($conversationId, $seniorAgent['user_id']);
} else {
    // Round-robin normal
}
```

### 4.4 Reatribuição e Transferência

**Reatribuição:**
- Atendente pode transferir conversa para outro atendente
- Atendente pode transferir para outro time
- Sistema registra trilha de auditoria

**Trilha de Auditoria:**
```php
conversation_assignments {
    id: int
    conversation_id: int
    assigned_to: int | null  // user_id
    assigned_by: int  // user_id que atribuiu
    team_id: int | null
    reason: string | null  // "Transferido de Vendas para Suporte"
    created_at: datetime
}
```

---

## 5. Operação do Atendente (Fluxo Real)

### 5.1 Um Dia de Uso

#### **08:00 - Login**
1. Atendente faz login
2. Sistema carrega Inbox com filtros padrão
3. Mostra conversas não lidas, prioritárias, SLA estourando

#### **08:05 - Primeira Conversa**
1. Atendente vê conversa nova na fila "Novas"
2. Clica na conversa → Abre thread
3. Lê histórico de mensagens
4. Clica "Assumir" → Conversa vai para "Em atendimento"
5. Sistema registra: `assigned_to = user_id`, `first_response_at = NOW()`

#### **08:10 - Respondendo**
1. Atendente digita resposta
2. Clica em "Templates" → Seleciona template
3. Personaliza mensagem
4. Clica "Enviar"
5. Sistema:
   - Envia mensagem via gateway
   - Cria registro em `messages`
   - Atualiza `conversations.last_message_at`
   - Atualiza SLA

#### **08:15 - Criando Vínculos**
1. Atendente identifica que contato é cliente existente
2. Clica "Vincular" → Seleciona tenant
3. Sistema vincula: `conversations.tenant_id = tenant_id`
4. Atendente cria tarefa: "Verificar domínio expirado"
5. Sistema cria tarefa vinculada à conversa

#### **08:30 - Finalizando**
1. Atendente resolve problema
2. Clica "Marcar como Resolvido"
3. Sistema:
   - Atualiza `conversations.status = 'closed'`
   - Registra `conversations.closed_at = NOW()`
   - Registra `conversations.closed_by = user_id`
   - Move para "Resolvidas"

#### **09:00 - Follow-up Automático**
1. Sistema detecta conversa fechada há 24h
2. Bot envia mensagem: "Problema foi resolvido?"
3. Se contato responder, conversa reabre automaticamente

### 5.2 Funcionalidades da Inbox

#### **Notas Internas**
```php
conversation_notes {
    id: int
    conversation_id: int
    user_id: int
    note: text
    is_internal: boolean  // true = só atendentes veem
    created_at: datetime
}
```

**Uso:**
- Atendente adiciona nota: "Cliente mencionou problema com domínio"
- Outros atendentes veem nota ao abrir conversa
- Notas não são enviadas ao contato

#### **Tags**
```php
conversation_tags {
    id: int
    conversation_id: int
    tag: string  // 'urgent', 'vip', 'billing', 'technical'
    created_by: int
    created_at: datetime
}
```

**Uso:**
- Atendente marca conversa com tag "urgent"
- Filtros podem buscar por tag
- Tags ajudam na priorização

#### **Templates de Resposta**
```php
message_templates {
    id: int
    name: string
    content: text
    variables: JSON  // {name, tenant_name, etc.}
    team_id: int | null  // null = global
    is_active: boolean
}
```

**Uso:**
- Atendente clica "Templates"
- Seleciona template: "Saudação Padrão"
- Sistema preenche variáveis: `{name}` → "João Silva"
- Atendente personaliza e envia

#### **Atalhos**
- `Ctrl+K`: Busca rápida
- `Ctrl+R`: Responder
- `Ctrl+T`: Transferir
- `Ctrl+N`: Nova nota
- `Ctrl+F`: Finalizar

#### **Anexos**
```php
message_attachments {
    id: int
    message_id: int
    file_name: string
    file_path: string
    file_size: int
    mime_type: string
    created_at: datetime
}
```

**Uso:**
- Atendente anexa imagem/documento
- Sistema armazena em `storage/conversations/{conversation_id}/`
- Contato recebe mídia via gateway

#### **Marcação "Resolvido"**
- Atendente marca como resolvido
- Sistema fecha conversa
- Bot pode enviar follow-up automático

#### **SLA / Tempo de Primeira Resposta**
```php
conversations {
    ...
    sla_minutes: int  // 30, 60, 120
    first_response_at: datetime | null
    first_response_by: int | null
    sla_status: 'ok' | 'warning' | 'breach'  // Calculado
    ...
}
```

**Cálculo:**
```php
$slaStatus = 'ok';
$elapsed = now() - $conversation['created_at'];
if ($elapsed > $conversation['sla_minutes']) {
    $slaStatus = 'breach';
} elseif ($elapsed > $conversation['sla_minutes'] * 0.8) {
    $slaStatus = 'warning';
}
```

---

## 6. Observabilidade e Confiabilidade

### 6.1 Idempotência

**Regra:** Mensagens duplicadas devem ser ignoradas.

**Implementação:**
```php
// Tabela: messages
messages {
    ...
    external_message_id: string UNIQUE  // ID do provedor
    ...
}

// Antes de inserir
$existing = MessageService::findByExternalId($externalMessageId);
if ($existing) {
    return $existing['message_id'];  // Retorna existente
}
// Insere nova
```

**Dedupe por:**
- `external_message_id` (ID do provedor)
- `channel_account_id + external_message_id` (composto)

### 6.2 Retry com Backoff

**Regra:** Falhas devem ser retentadas com backoff exponencial.

**Implementação:**
```php
message_send_attempts {
    id: int
    message_id: int
    attempt_number: int
    status: 'pending' | 'success' | 'failed'
    error_message: text | null
    next_retry_at: datetime | null
    created_at: datetime
}

// Backoff: 1min, 2min, 4min, 8min, 16min (max)
$nextRetryAt = now() + (pow(2, $attemptNumber) * 60);
```

### 6.3 Dead-Letter Queue (DLQ)

**Regra:** Mensagens que falharam após N tentativas vão para DLQ.

**Implementação:**
```php
dead_letter_queue {
    id: int
    message_id: int
    conversation_id: int
    failure_reason: text
    attempt_count: int
    last_attempt_at: datetime
    created_at: datetime
}

// Após 5 tentativas, move para DLQ
if ($attemptCount >= 5) {
    DeadLetterQueueService::add($messageId, $failureReason);
    // Notifica admin
}
```

### 6.4 Métricas

**Métricas Obrigatórias:**
```php
communication_metrics {
    id: int
    metric_name: string
    metric_value: decimal
    metric_unit: string  // 'count', 'seconds', 'percentage'
    tenant_id: int | null
    time_bucket: datetime  // Agregado por hora/dia
    created_at: datetime
}
```

**Métricas Coletadas:**
- `messages_per_minute`: Mensagens recebidas por minuto
- `average_response_time`: Tempo médio de primeira resposta (segundos)
- `sla_breach_rate`: Taxa de conversas que estouraram SLA (%)
- `duplicate_rate`: Taxa de mensagens duplicadas (%)
- `failure_rate`: Taxa de falhas no envio (%)
- `conversation_resolution_time`: Tempo médio para resolver conversa (horas)

**Dashboard:**
- Gráfico de mensagens ao longo do tempo
- SLA compliance por time
- Top atendentes (por resolução, tempo de resposta)
- Canais mais usados

### 6.5 Logs Correlacionados

**Regra:** Todos os logs devem incluir `correlation_id` para rastreamento.

**Estrutura:**
```php
// Log format
{
    "timestamp": "2026-01-09T10:00:00Z",
    "level": "info",
    "correlation_id": "abc123",
    "conversation_id": 123,
    "tenant_id": 10,
    "user_id": 5,
    "message": "Mensagem enviada com sucesso",
    "metadata": {
        "channel": "whatsapp",
        "message_id": "msg_456"
    }
}
```

**Uso:**
- Buscar todos os logs de uma conversa: `correlation_id = 'abc123'`
- Rastrear fluxo completo: webhook → ingestão → roteamento → envio

---

## 7. Expansão para Novos Canais

### 7.1 Princípio: Conector, Não Core

**Regra:** Adicionar novo canal não deve mudar o core do sistema.

**Implementação:**
1. **Criar Connector:**
   ```php
   // src/Connectors/EmailConnector.php
   class EmailConnector {
       public function receiveWebhook(array $payload): NormalizedMessage {
           // Normaliza para formato único
       }
       
       public function sendMessage(NormalizedMessage $message): Result {
           // Envia via SMTP
       }
   }
   ```

2. **Registrar Connector:**
   ```php
   // config/connectors.php
   return [
       'whatsapp' => WhatsAppConnector::class,
       'email' => EmailConnector::class,
       'webchat' => WebChatConnector::class,
   ];
   ```

3. **Core não muda:**
   - Tabela `messages` continua igual
   - Tabela `conversations` continua igual
   - Inbox continua igual
   - Apenas adiciona novo connector

### 7.2 Exemplo: Adicionar E-mail

**Passos:**
1. Criar `EmailConnector`
2. Criar endpoint `/api/email/webhook`
3. Registrar em `config/connectors.php`
4. Adicionar `channel_account` com `channel_type = 'email'`
5. Pronto! E-mails aparecem na Inbox automaticamente

**Nada muda em:**
- ❌ Tabela `messages`
- ❌ Tabela `conversations`
- ❌ Inbox UI
- ❌ Roteamento
- ❌ Atribuição

---

## 8. Perguntas Objetivas (Respostas Diretas)

### 8.1 Onde eu irei receber as mensagens no Hub?

**Resposta:** Na tela `/communication-hub` (Inbox Unificada).

**Entidade:** Tabela `conversations` (threads de conversa) + `messages` (mensagens individuais).

**Fluxo:**
1. Mensagem chega via webhook → `communication_events` (evento)
2. Worker processa → Cria/atualiza `conversations` e `messages`
3. Inbox exibe conversas ordenadas por SLA, não lidas, prioridade
4. Atendente clica na conversa → Vê thread completa

**Evidência:** `CommunicationHubController::index()` já existe, precisa ser aprimorado.

---

### 8.2 Como uma mensagem vira "conversa" e como se mantém o histórico?

**Resposta:** Via tabela `conversations` que agrupa mensagens.

**Processo:**
1. **Primeira mensagem:**
   - Worker detecta evento `whatsapp.inbound.message`
   - Busca `conversation` por `conversation_key = "whatsapp_{tenant_id}_{contact_id}"`
   - Se não existe, cria nova `conversation`
   - Cria `message` vinculada à `conversation`

2. **Mensagens subsequentes:**
   - Worker busca `conversation` existente
   - Adiciona `message` à mesma `conversation`
   - Atualiza `conversations.last_message_at` e `conversations.message_count`

**Histórico:**
- Todas as mensagens ficam em `messages` com `conversation_id`
- Query: `SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC`
- Thread sempre mostra histórico completo

**Evidência:** Precisa implementar (P0.2, P0.4).

---

### 8.3 Como identificamos o contato (por telefone/email) e como lidamos com duplicidade?

**Resposta:** Via tabela `contacts` com dedupe por `external_id + channel_type`.

**Processo:**
1. **Identificação:**
   - Mensagem chega com `from = "5547999999999@c.us"`
   - Worker busca `contact` por `external_id = "5547999999999"` e `channel_type = "whatsapp"`
   - Se não existe, cria novo `contact`
   - Se existe, usa `contact_id` existente

2. **Dedupe:**
   - UNIQUE KEY em `contacts(external_id, channel_type)`
   - Mesmo telefone em múltiplas conversas = mesmo `contact_id`
   - Histórico unificado por contato

3. **Identificação como Cliente:**
   - Atendente pode vincular `contact.tenant_id`
   - Sistema sugere match por telefone/e-mail
   - Uma vez vinculado, todas as conversas futuras usam `tenant_id`

**Evidência:** Precisa implementar (P0.3).

---

### 8.4 Como vinculamos conversa ao módulo certo (vendas/suporte/financeiro)?

**Resposta:** Via roteamento automático + atribuição manual.

**Processo Automático:**
1. **Roteamento:**
   - Worker avalia `routing_rules` baseado em:
     - Palavras-chave na mensagem
     - Status do tenant
     - Horário
     - Canal
   - Regra decide: `action.type = "assign_to_team"` → `team_id = 1` (Vendas)
   - Conversa é atribuída ao time

2. **Atribuição Manual:**
   - Atendente pode transferir conversa para outro time
   - Sistema registra em `conversation_assignments` (auditoria)

**Campo em `conversations`:**
- `assigned_to` (user_id) — Atendente específico
- `team_id` (derivado de `assigned_to`) — Time (Vendas/Suporte/Financeiro)
- `module` (derivado de `team_id`) — Módulo (sales/support/billing)

**Evidência:** `EventRouterService` existe, precisa integrar com `conversations` (P1.2).

---

### 8.5 Como escalamos para muitos números e muitas mensagens sem travar (fila, workers, limites)?

**Resposta:** Sistema de fila (Redis) + workers assíncronos + rate limiting.

**Arquitetura:**
1. **Fila:**
   - Webhook recebe → Insere evento em `communication_events` (status: `queued`)
   - Worker assíncrono processa eventos em background
   - Não trava webhook (resposta imediata HTTP 200)

2. **Workers:**
   - Múltiplos workers processam fila em paralelo
   - Cada worker processa N eventos por vez (ex: 10)
   - Workers escalam horizontalmente (adicionar mais workers = mais throughput)

3. **Rate Limiting:**
   - Gateway limita requisições (ex: 100 req/min)
   - Worker respeita limites do gateway
   - Retry com backoff se rate limit atingido

4. **Limites:**
   - `communication_events` tem índices otimizados
   - `messages` tem índices para queries rápidas
   - Partição de dados por tenant (futuro)

**Evidência:** Precisa implementar (P1.1).

---

### 8.6 Como o atendente alterna entre produtos sem confundir contexto?

**Resposta:** Via seleção de contexto no Top Bar + filtros automáticos.

**Processo:**
1. **Top Bar mostra contexto:**
   - Tenant ativo: "Pixel12 Digital"
   - Produto ativo: "CFC" (dropdown)
   - Usuário: "Maria"

2. **Filtros automáticos:**
   - Inbox filtra por `tenant_id` selecionado
   - Inbox filtra por `product_id` selecionado (se selecionado)
   - Todas as queries incluem filtros

3. **Alternância:**
   - Atendente clica dropdown "Produto" → Seleciona "Imobiliário"
   - Inbox recarrega com filtro `product_id = 2`
   - Conversas de outros produtos não aparecem

4. **Contexto persistente:**
   - Seleção salva em sessão/cookie
   - Próximo login mantém último contexto

**Evidência:** Precisa implementar (P1.3).

---

### 8.7 Como garantimos auditoria total (quem respondeu, quando, por qual canal)?

**Resposta:** Via tabelas de auditoria + logs correlacionados.

**Tabelas:**
1. **`conversation_assignments`** — Histórico de atribuições
   ```sql
   conversation_id, assigned_to, assigned_by, team_id, reason, created_at
   ```

2. **`messages`** — Todas as mensagens com `sent_by` (user_id)
   ```sql
   message_id, conversation_id, direction, sent_by, created_at
   ```

3. **`conversation_notes`** — Notas internas com `user_id`
   ```sql
   conversation_id, user_id, note, created_at
   ```

4. **`audit_logs`** — Ações do sistema
   ```sql
   action_type, user_id, entity_type, entity_id, details, created_at
   ```

**Logs:**
- Todos os logs incluem `correlation_id` (vincula eventos relacionados)
- Busca: `SELECT * FROM audit_logs WHERE correlation_id = 'abc123'`

**Evidência:** Precisa implementar (P1.3, P1.10).

---

### 8.8 Como fica pronto para e-mail/chat/rede social sem refazer tudo?

**Resposta:** Via arquitetura de Connectors (conectores) que normalizam para formato único.

**Princípio:** Core não muda, apenas adiciona novo connector.

**Processo:**
1. **Criar Connector:**
   ```php
   // src/Connectors/EmailConnector.php
   class EmailConnector {
       public function receiveWebhook(array $payload): NormalizedMessage {
           // Normaliza e-mail para formato único
           return [
               'message_type' => 'text',
               'direction' => 'inbound',
               'channel' => 'email',
               'content' => ['text' => $payload['body']],
               ...
           ];
       }
   }
   ```

2. **Registrar Connector:**
   ```php
   // config/connectors.php
   'email' => EmailConnector::class,
   ```

3. **Criar Endpoint:**
   ```php
   // EmailWebhookController.php (similar ao WhatsAppWebhookController)
   $connector = new EmailConnector();
   $normalized = $connector->receiveWebhook($payload);
   EventIngestionService::ingest($normalized);
   ```

4. **Pronto!**
   - E-mails aparecem na Inbox automaticamente
   - Mesma tabela `conversations` e `messages`
   - Mesma UI, mesmos filtros, mesma atribuição

**O que NÃO muda:**
- ❌ Tabela `messages` (continua igual)
- ❌ Tabela `conversations` (continua igual)
- ❌ Inbox UI (continua igual)
- ❌ Roteamento (continua igual)
- ❌ Atribuição (continua igual)

**Evidência:** Arquitetura já prevê isso (seção 7 deste documento).

---

**Próximo Passo:** Ver documento `GAPS_E_BACKLOG_CENTRAL_COMUNICACAO.md` para priorização e esforço.

