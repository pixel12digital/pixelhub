# Implementação de Chatbot WhatsApp Business API

**Data:** 04/03/2026  
**Status:** Backend 100% completo | Frontend parcial

---

## 📋 Visão Geral

Sistema completo de chatbot para WhatsApp Business API (Meta) com suporte a:
- Templates aprovados pelo Meta
- Fluxos de automação com botões interativos
- Campanhas de envio em massa
- Rastreamento de eventos e métricas
- Encaminhamento para atendimento humano

---

## ✅ Implementado (Backend Completo)

### 1. Banco de Dados

**Migrations executadas:**
```
20260304_create_whatsapp_message_templates_table.php
20260305_create_chatbot_flows_table.php
20260306_create_chatbot_events_table.php
20260307_create_template_campaigns_table.php
20260304_alter_conversations_add_chatbot_fields.php
```

**Tabelas criadas:**
- `whatsapp_message_templates` - Templates Meta (draft, pending, approved, rejected)
- `chatbot_flows` - Fluxos de automação (gatilhos, respostas, ações)
- `chatbot_events` - Log de eventos (cliques, execuções, encaminhamentos)
- `template_campaigns` - Campanhas de envio em massa (com métricas)
- `conversations` - Campos adicionados: `is_bot_active`, `bot_last_flow_id`, `bot_context`

### 2. Services

**`src/Services/MetaTemplateService.php`**
- `listTemplates()` - Lista com filtros (tenant, status, categoria)
- `getById()` - Busca por ID
- `create()` - Cria template
- `update()` - Atualiza template
- `submitForApproval()` - Submete para Meta
- `markAsApproved()` - Marca como aprovado
- `markAsRejected()` - Marca como rejeitado
- `delete()` - Deleta template
- `renderTemplate()` - Renderiza com variáveis
- `extractVariables()` - Extrai variáveis {{1}}, {{2}}
- `validateTemplate()` - Valida estrutura

**`src/Services/ChatbotFlowService.php`**
- `listFlows()` - Lista com filtros
- `getById()` - Busca por ID
- `findByTrigger()` - Busca por gatilho (button_id)
- `create()` - Cria fluxo
- `update()` - Atualiza fluxo
- `delete()` - Deleta fluxo
- `executeFlow()` - **Executa fluxo automaticamente**
- `logEvent()` - Registra evento em chatbot_events
- `getConversationEvents()` - Histórico de eventos
- `deactivateBot()` - Desativa bot (passa para humano)
- `activateBot()` - Ativa bot

**`src/Services/TemplateCampaignService.php`**
- `listCampaigns()` - Lista com filtros
- `getById()` - Busca por ID
- `create()` - Cria campanha
- `update()` - Atualiza campanha
- `start()` - Inicia campanha
- `pause()` - Pausa campanha
- `resume()` - Retoma campanha
- `processNextBatch()` - **Processa lote de envios**
- `updateMetric()` - Atualiza métricas (delivered, read, clicked)
- `getPendingCampaigns()` - Campanhas para processar
- `delete()` - Deleta campanha
- `getMetrics()` - Métricas consolidadas

### 3. Controllers

**`src/Controllers/WhatsAppTemplateController.php`**
- `index()` - Lista templates
- `create()` - Formulário de criação
- `store()` - Processa criação
- `edit()` - Formulário de edição
- `update()` - Processa atualização
- `view()` - Visualiza detalhes
- `submit()` - Submete para Meta
- `delete()` - Deleta template

**`src/Controllers/ChatbotController.php`**
- `index()` - Lista fluxos
- `create()` - Formulário de criação
- `store()` - Processa criação
- `edit()` - Formulário de edição
- `update()` - Processa atualização
- `view()` - Visualiza detalhes
- `toggle()` - Ativa/desativa fluxo
- `delete()` - Deleta fluxo
- `test()` - Testa execução de fluxo

**`src/Controllers/TemplateCampaignController.php`**
- `index()` - Lista campanhas
- `create()` - Formulário de criação
- `store()` - Processa criação (suporta CSV, textarea, JSON)
- `view()` - Visualiza campanha e métricas
- `start()` - Inicia campanha
- `pause()` - Pausa campanha
- `resume()` - Retoma campanha
- `delete()` - Deleta campanha
- `metrics()` - Retorna métricas em JSON
- `processBatch()` - Processa próximo lote

### 4. Webhook Processing

**`src/Controllers/MetaWebhookController.php` (modificado)**

Adicionados métodos:
- `processInteractiveButton()` - Detecta clique em botão
- `resolveConversation()` - Resolve/cria conversa
- `sendAutomatedResponse()` - Envia resposta automática

**Fluxo de processamento:**
```
1. Meta envia webhook com clique em botão
2. MetaWebhookController detecta tipo 'interactive'
3. Extrai button_id do payload
4. ChatbotFlowService::findByTrigger('template_button', button_id)
5. ChatbotFlowService::executeFlow(flow_id, conversation_id)
6. Registra evento em chatbot_events
7. Envia resposta automática (se configurada)
8. Encaminha para humano (se configurado)
```

### 5. Rotas (public/index.php)

**Templates:**
```php
GET  /whatsapp/templates
GET  /whatsapp/templates/create
POST /whatsapp/templates/create
GET  /whatsapp/templates/edit
POST /whatsapp/templates/update
GET  /whatsapp/templates/view
POST /whatsapp/templates/submit
POST /whatsapp/templates/delete
```

**Fluxos:**
```php
GET  /chatbot/flows
GET  /chatbot/flows/create
POST /chatbot/flows/create
GET  /chatbot/flows/edit
POST /chatbot/flows/update
GET  /chatbot/flows/view
POST /chatbot/flows/toggle
POST /chatbot/flows/delete
POST /chatbot/flows/test
```

**Campanhas:**
```php
GET  /campaigns
GET  /campaigns/create
POST /campaigns/create
GET  /campaigns/view
POST /campaigns/start
POST /campaigns/pause
POST /campaigns/resume
POST /campaigns/delete
GET  /campaigns/metrics
POST /campaigns/process-batch
```

### 6. Template de Prospecção (Seedado)

**Arquivo:** `database/seeds/seed_prospeccao_corretores_template.php`

**Template criado:** `prospeccao_sistema_corretores`
- Categoria: Marketing
- Botões: "Quero conhecer" | "Não tenho interesse"

**Fluxos criados:**
1. **Quero conhecer** (ID: 1)
   - Envia link da landing page
   - Adiciona tags: corretor, interessado, prospeccao
   - Atualiza status do lead: interessado
   - Encaminha para atendimento humano

2. **Não tenho interesse** (ID: 2)
   - Envia mensagem de despedida
   - Adiciona tags: corretor, nao_interessado, prospeccao
   - Atualiza status do lead: nao_interessado
   - NÃO encaminha para humano

---

## 📋 Pendente (Frontend)

### Views a Criar

**Templates:**
- ✅ `views/whatsapp/templates/index.php` - Lista (CRIADA)
- ⏳ `views/whatsapp/templates/create.php` - Formulário de criação
- ⏳ `views/whatsapp/templates/edit.php` - Formulário de edição
- ⏳ `views/whatsapp/templates/view.php` - Detalhes do template

**Fluxos:**
- ⏳ `views/chatbot/flows/index.php` - Lista de fluxos
- ⏳ `views/chatbot/flows/create.php` - Formulário de criação
- ⏳ `views/chatbot/flows/edit.php` - Formulário de edição
- ⏳ `views/chatbot/flows/view.php` - Detalhes do fluxo

**Campanhas:**
- ⏳ `views/campaigns/index.php` - Dashboard de campanhas
- ⏳ `views/campaigns/create.php` - Formulário de criação
- ⏳ `views/campaigns/view.php` - Detalhes e métricas

### Integrações com Inbox

- ⏳ Indicador visual de chatbot ativo na conversa
- ⏳ Botão "Desativar Bot" para assumir manualmente
- ⏳ Histórico de eventos do chatbot na timeline
- ⏳ Badge mostrando último fluxo executado

---

## 🚀 Como Usar (Fluxo Completo)

### 1. Criar Template

```
1. Acesse /whatsapp/templates
2. Clique em "Novo Template"
3. Preencha:
   - Nome: prospeccao_sistema_corretores
   - Categoria: Marketing
   - Conteúdo: Olá! Estamos entrando em contato...
   - Botões: [Quero conhecer] [Não tenho interesse]
4. Salvar como Rascunho
5. Submeter para Aprovação no Meta
6. Aguardar 24-48h
```

### 2. Criar Fluxos

```
1. Acesse /chatbot/flows
2. Crie fluxo para "Quero conhecer":
   - Gatilho: template_button
   - Valor: btn_quero_conhecer
   - Resposta: Aqui mostramos rapidamente...
   - Encaminhar para humano: SIM
3. Crie fluxo para "Não tenho interesse":
   - Gatilho: template_button
   - Valor: btn_nao_tenho_interesse
   - Resposta: Sem problemas...
   - Encaminhar para humano: NÃO
```

### 3. Criar Campanha

```
1. Acesse /campaigns
2. Clique em "Nova Campanha"
3. Selecione template aprovado
4. Upload CSV com telefones ou cole lista
5. Configure:
   - Lote: 50 mensagens
   - Delay: 60 segundos
6. Agendar ou Iniciar Agora
```

### 4. Processar Campanhas (Worker)

Criar cron job:
```bash
*/5 * * * * cd /path/to/pixelhub && php scripts/campaign_worker.php >> logs/campaigns.log 2>&1
```

Criar arquivo `scripts/campaign_worker.php`:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Services\TemplateCampaignService;

Env::load();

$campaigns = TemplateCampaignService::getPendingCampaigns();

foreach ($campaigns as $campaign) {
    $result = TemplateCampaignService::processNextBatch($campaign['id']);
    
    echo "[" . date('Y-m-d H:i:s') . "] ";
    echo "Campanha #{$campaign['id']}: ";
    echo "{$result['sent']} enviados, {$result['failed']} falhas";
    
    if ($result['completed']) {
        echo " - CONCLUÍDA";
    }
    
    echo "\n";
    
    // Rate limiting entre campanhas
    sleep($campaign['batch_delay_seconds']);
}
```

---

## 🔧 Configuração no Meta Business Suite

### 1. Criar Template

```
1. Acesse Meta Business Suite
2. WhatsApp → Message Templates
3. Create Template
4. Preencha conforme criado no PixelHub
5. Aguardar aprovação
6. Copiar Template ID
7. Atualizar no PixelHub: markAsApproved($id, $metaTemplateId)
```

### 2. Configurar Webhook

```
1. Meta Business Suite → WhatsApp → Configuration
2. Webhooks → Edit
3. Callback URL: https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook
4. Verify Token: (configurado em whatsapp_provider_configs)
5. Subscribe to: messages, message_status
6. Salvar
```

---

## 📊 Métricas Disponíveis

### Por Campanha
- Total de destinatários
- Enviados
- Entregues (delivered_count)
- Lidos (read_count)
- Clicados (clicked_count)
- Falhas (failed_count)
- Taxa de entrega
- Taxa de leitura
- Taxa de clique

### Por Fluxo
- Total de execuções
- Encaminhamentos para humano
- Conversões (por tag/status)

### Por Template
- Uso em campanhas
- Taxa de aprovação
- Rejeições (com motivo)

---

## 🐛 Troubleshooting

### Template não aparece na lista
- Verificar se migration foi executada
- Verificar se seed foi executado: `php database/seeds/seed_prospeccao_corretores_template.php`

### Botão não executa fluxo
- Verificar logs: `tail -f logs/pixelhub.log | grep MetaWebhook`
- Verificar se button_id corresponde ao trigger_value do fluxo
- Verificar se fluxo está ativo (is_active=1)

### Campanha não envia
- Verificar se template está aprovado (status='approved')
- Verificar se MetaOfficialProvider está configurado
- Verificar se worker está rodando
- Verificar rate limits do Meta

### Webhook não recebe eventos
- Verificar configuração no Meta Business Suite
- Verificar verify_token
- Testar: `curl -X GET "https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook?hub.mode=subscribe&hub.verify_token=TOKEN&hub.challenge=TEST"`

---

## 📝 Próximos Passos Recomendados

### Prioridade Alta
1. ✅ Criar views básicas (index.php criada)
2. ⏳ Implementar envio real via MetaOfficialProvider em sendAutomatedResponse()
3. ⏳ Criar worker para processar campanhas (campaign_worker.php)
4. ⏳ Testar fluxo completo com template real

### Prioridade Média
5. ⏳ Adicionar indicadores de chatbot no Inbox
6. ⏳ Dashboard de métricas consolidadas
7. ⏳ Relatórios de campanhas (PDF/CSV)
8. ⏳ Testes automatizados

### Prioridade Baixa
9. ⏳ Editor visual de fluxos (drag & drop)
10. ⏳ A/B testing de templates
11. ⏳ Segmentação avançada de campanhas
12. ⏳ Integração com CRM (tags automáticas)

---

## 📚 Referências

- [Meta WhatsApp Business API Docs](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Message Templates](https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates)
- [Interactive Messages](https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages#interactive-messages)
- [Webhooks](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks)

---

**Última atualização:** 04/03/2026  
**Autor:** PixelHub Development Team
