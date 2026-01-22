# Mapeamento WhatsApp e Proposta de Gerenciamento de Mensagens

## 📋 1. INVESTIGAÇÃO: O que já existe relacionado a WhatsApp

### ✅ Estrutura Existente

#### 1.1. Arquivos e Classes

**Service:**
- `src/Services/WhatsAppBillingService.php` - Service completo para gerenciar cobranças via WhatsApp
  - `normalizePhone()` - Normaliza telefones para formato wa.me
  - `suggestStageForInvoice()` - Sugere estágio de cobrança (pre_due, overdue_3d, overdue_7d)
  - `buildMessageForInvoice()` - Monta mensagem baseada no estágio (hardcoded)
  - `buildReminderMessageForTenant()` - Monta mensagem agregada para múltiplas faturas
  - `prepareNotificationForInvoice()` - Cria registro em billing_notifications

**Controller:**
- `src/Controllers/BillingCollectionsController.php` - Controller de cobranças
  - `showWhatsAppModal()` - Exibe modal/página para envio manual
  - `markWhatsAppSent()` - Marca mensagem como enviada
  - `getTenantReminderData()` - Retorna JSON com dados para cobrança agregada
  - `markTenantReminderSent()` - Marca cobrança agregada como enviada

**Views:**
- `views/billing_collections/whatsapp_modal.php` - Modal/página de envio manual
- `views/billing_collections/index.php` - Lista de cobranças com botões WhatsApp
- `views/billing_collections/overview.php` - Visão geral com cobrança agregada
- `views/tenants/view.php` - Aba financeira com histórico de notificações WhatsApp

#### 1.2. Banco de Dados

**Tabela: `billing_notifications`**
- ✅ **Existe e está ativa**
- **Campos principais:**
  - `id` (INT UNSIGNED, PK)
  - `tenant_id` (INT UNSIGNED, NOT NULL) - FK para tenants
  - `invoice_id` (INT UNSIGNED, NULL) - FK para billing_invoices (opcional)
  - `channel` (VARCHAR(30)) - 'whatsapp_web' (fixo)
  - `template` (VARCHAR(50)) - 'pre_due', 'overdue_3d', 'overdue_7d', 'bulk_reminder'
  - `status` (VARCHAR(30)) - 'prepared', 'sent_manual', 'opened', 'skipped', 'failed'
  - `message` (TEXT) - Mensagem completa enviada
  - `phone_raw` (VARCHAR(50), NULL) - Telefone original
  - `phone_normalized` (VARCHAR(30), NULL) - Telefone normalizado
  - `sent_at` (DATETIME, NULL) - Data/hora do envio
  - `created_at`, `updated_at` (DATETIME)
  - `last_error` (TEXT, NULL)

**Tabela: `billing_invoices`**
- Campos relacionados a WhatsApp:
  - `whatsapp_last_stage` (VARCHAR(50), NULL) - Último estágio enviado
  - `whatsapp_last_at` (DATETIME, NULL) - Data/hora do último envio
  - `whatsapp_total_messages` (INT UNSIGNED) - Contador de mensagens

**Migration:**
- `database/migrations/20251118_create_billing_notifications_table.php`
- `database/migrations/20251118_alter_billing_invoices_add_whatsapp_fields.php`

#### 1.3. Funcionalidades Existentes

**✅ O que já funciona:**

1. **Normalização de telefones:**
   - Remove caracteres não numéricos
   - Adiciona DDI 55 se necessário
   - Suporta celular (11 dígitos) e fixo (10 dígitos)

2. **Sugestão automática de estágio:**
   - `pre_due` - Fatura ainda não vencida
   - `overdue_3d` - Fatura vencida há 1-5 dias
   - `overdue_7d` - Fatura vencida há 6+ dias

3. **Geração de mensagens:**
   - Mensagens hardcoded no método `buildMessageForInvoice()`
   - Suporta variáveis: `{clientName}`, `{dueDate}`, `{amount}`
   - Mensagem agregada para múltiplas faturas

4. **Geração de link wa.me:**
   - Link formatado: `https://wa.me/{phone}?text={encoded_message}`
   - Botão "Abrir WhatsApp Web" no modal

5. **Histórico de envios:**
   - Registro em `billing_notifications`
   - Exibição na aba Financeiro do cliente
   - Filtros por estágio na Central de Cobranças

6. **Integração com faturas:**
   - Atualização automática de `whatsapp_last_stage`, `whatsapp_last_at`, `whatsapp_total_messages`

#### 1.4. Rotas Existentes

```php
GET  /billing/whatsapp-modal?invoice_id={id}&redirect_to={tenant|collections}
POST /billing/whatsapp-sent
GET  /billing/tenant-reminder?tenant_id={id}  // JSON
POST /billing/tenant-reminder-sent
```

---

## ❌ O que NÃO existe

1. **Sistema de templates editáveis:**
   - As mensagens estão hardcoded no `WhatsAppBillingService`
   - Não há interface para criar/editar templates
   - Não há tabela de templates no banco

2. **Variáveis dinâmicas:**
   - As variáveis são substituídas manualmente no código
   - Não há sistema de placeholders configuráveis

3. **Templates para outros contextos:**
   - Apenas templates de cobrança existem
   - Não há templates para: migração de hospedagem, abandono de carrinho, avisos gerais, etc.

4. **Gestão centralizada:**
   - Não há menu/configuração para gerenciar templates
   - Não há preview/teste de templates

---

## 📋 2. PROPOSTA: Sistema de Gerenciamento de Templates WhatsApp

### 2.1. Arquitetura Proposta

#### Estrutura de Banco de Dados

**Nova tabela: `whatsapp_templates`**

```sql
CREATE TABLE whatsapp_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,  -- Ex: 'cobranca_pre_due', 'migracao_hospedagem'
    name VARCHAR(255) NOT NULL,         -- Nome amigável: 'Cobrança - Pré-vencimento'
    category VARCHAR(50) NOT NULL,      -- 'cobranca', 'hospedagem', 'geral', etc.
    content TEXT NOT NULL,               -- Template com variáveis {nome}, {valor}, etc.
    variables JSON NULL,                 -- Lista de variáveis disponíveis: ['nome', 'valor', 'vencimento']
    is_active TINYINT(1) DEFAULT 1,
    is_system TINYINT(1) DEFAULT 0,      -- Templates do sistema (não podem ser deletados)
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_category (category),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Alteração na tabela `billing_notifications`:**
- O campo `template` já existe e armazena o código do template
- Pode continuar usando os mesmos valores atuais: 'pre_due', 'overdue_3d', 'overdue_7d', 'bulk_reminder'

#### Estrutura de Arquivos

```
src/
├── Controllers/
│   └── WhatsAppTemplatesController.php  (NOVO)
├── Services/
│   ├── WhatsAppBillingService.php       (MODIFICAR - usar templates do banco)
│   └── WhatsAppTemplateService.php      (NOVO)
views/
└── whatsapp_templates/
    ├── index.php                        (NOVO - Lista de templates)
    ├── form.php                         (NOVO - Criar/editar template)
    └── preview.php                      (NOVO - Preview/teste de template)
```

### 2.2. Localização no Menu

**Menu: Configurações → Mensagens WhatsApp**

```
Configurações
├── Financeiro
│   └── Categorias de Contratos
├── Mensagens WhatsApp          ← NOVO
│   ├── Templates
│   └── Histórico de Envios
└── Infraestrutura
    └── Provedores de Hospedagem
```

**Rotas propostas:**
```php
GET  /settings/whatsapp-templates           // Lista de templates
GET  /settings/whatsapp-templates/create    // Criar template
GET  /settings/whatsapp-templates/edit      // Editar template
POST /settings/whatsapp-templates/store    // Salvar novo
POST /settings/whatsapp-templates/update    // Atualizar existente
POST /settings/whatsapp-templates/delete    // Deletar (se não for system)
GET  /settings/whatsapp-templates/preview   // Preview com dados de teste
```

### 2.3. Funcionalidades Propostas

#### 2.3.1. Gestão de Templates

**Lista de Templates (`/settings/whatsapp-templates`):**
- Tabela com: Código, Nome, Categoria, Status, Ações
- Filtros por categoria
- Botões: Criar, Editar, Preview, Duplicar, Deletar (se não for system)

**Formulário de Template (`/settings/whatsapp-templates/form`):**
- Campo: Código (único, slug)
- Campo: Nome (amigável)
- Campo: Categoria (select: cobrança, hospedagem, geral)
- Campo: Conteúdo (textarea grande com preview)
- Lista de variáveis disponíveis (ajuda contextual)
- Checkbox: Ativo
- Botão: Preview (abre modal com dados de teste)

**Preview de Template:**
- Permite testar template com dados fictícios
- Mostra resultado final com variáveis substituídas
- Botão: "Copiar mensagem" / "Abrir WhatsApp Web"

#### 2.3.2. Sistema de Variáveis

**Variáveis padrão disponíveis:**
- `{nome}` ou `{clientName}` - Nome do cliente
- `{nomeFantasia}` - Nome fantasia (se PJ)
- `{valor}` ou `{amount}` - Valor formatado (R$ 1.234,56)
- `{vencimento}` ou `{dueDate}` - Data de vencimento (dd/mm/yyyy)
- `{diasAtraso}` - Dias de atraso (para cobranças)
- `{dominio}` - Domínio do site
- `{linkFatura}` - Link da fatura (se aplicável)
- `{descricao}` - Descrição da fatura/serviço

**Variáveis customizadas:**
- Permitir adicionar variáveis customizadas por template
- Armazenar em JSON no campo `variables`

#### 2.3.3. Migração dos Templates Atuais

**Templates do sistema (is_system = 1):**
1. `cobranca_pre_due` - "Cobrança - Pré-vencimento"
2. `cobranca_overdue_3d` - "Cobrança - Vencido +3 dias"
3. `cobranca_overdue_7d` - "Cobrança - Vencido +7 dias"
4. `cobranca_bulk_reminder` - "Cobrança - Lembrete Agregado"

**Migration inicial:**
- Criar tabela `whatsapp_templates`
- Inserir os 4 templates do sistema com conteúdo atual do `WhatsAppBillingService`
- Manter compatibilidade: código do template = valor atual do campo `template` em `billing_notifications`

#### 2.3.4. Integração com WhatsAppBillingService

**Modificar `WhatsAppBillingService::buildMessageForInvoice()`:**
```php
// ANTES (hardcoded):
return "Oi {$clientName}, tudo bem? 😊\n\n...";

// DEPOIS (do banco):
$template = WhatsAppTemplateService::getByCode('cobranca_pre_due');
$message = WhatsAppTemplateService::render($template, [
    'clientName' => $clientName,
    'dueDate' => $dueDateFormatted,
    'amount' => $amountFormatted,
]);
return $message;
```

**Novo Service: `WhatsAppTemplateService`:**
```php
class WhatsAppTemplateService {
    public static function getByCode(string $code): ?array
    public static function render(array $template, array $variables): string
    public static function getAvailableVariables(string $category): array
    public static function validateTemplate(string $content, array $variables): array
}
```

### 2.4. Casos de Uso Adicionais

**Templates para outros contextos:**

1. **Migração de Hospedagem:**
   - Código: `hospedagem_migracao`
   - Variáveis: `{nome}`, `{dominio}`, `{dataMigracao}`, `{novoProvedor}`

2. **Aviso de Expiração:**
   - Código: `hospedagem_expiracao`
   - Variáveis: `{nome}`, `{dominio}`, `{dataExpiracao}`, `{diasRestantes}`

3. **Abandono de Carrinho:**
   - Código: `vendas_abandono_carrinho`
   - Variáveis: `{nome}`, `{produto}`, `{valor}`, `{linkCarrinho}`

4. **Avisos Gerais:**
   - Código: `geral_aviso`
   - Variáveis: `{nome}`, `{mensagem}` (customizável)

### 2.5. Interface do Usuário

**Lista de Templates:**
- Cards ou tabela com preview do template
- Badge de categoria
- Indicador de template do sistema (não pode deletar)
- Botão rápido: "Usar este template" (abre modal de envio)

**Editor de Template:**
- Editor de texto com contador de caracteres
- Painel lateral com lista de variáveis disponíveis
- Preview em tempo real (opcional, pode ser botão)
- Validação: verifica se variáveis usadas existem

**Modal de Envio Rápido:**
- Seleciona template
- Seleciona cliente (ou usa contexto atual)
- Preenche variáveis automaticamente (quando possível)
- Permite editar mensagem final
- Botões: Copiar, Abrir WhatsApp Web, Enviar (se API futura)

---

## 📋 3. PLANO DE IMPLEMENTAÇÃO

### Fase 1: Estrutura Base (Sem alterar funcionalidade atual)
1. ✅ Criar migration para tabela `whatsapp_templates`
2. ✅ Criar seed com templates do sistema (migrar do código atual)
3. ✅ Criar `WhatsAppTemplateService` básico
4. ✅ Criar `WhatsAppTemplatesController` com CRUD
5. ✅ Criar views básicas (lista, formulário)

### Fase 2: Integração (Manter compatibilidade)
1. ✅ Modificar `WhatsAppBillingService` para usar templates do banco
2. ✅ Manter fallback para templates hardcoded (se template não encontrado)
3. ✅ Testar que funcionalidade atual continua funcionando

### Fase 3: Melhorias (Novas funcionalidades)
1. ✅ Adicionar preview de templates
2. ✅ Adicionar validação de variáveis
3. ✅ Adicionar templates para outros contextos
4. ✅ Adicionar modal de envio rápido

### Fase 4: Refinamento (Opcional)
1. ⏳ Editor WYSIWYG (opcional)
2. ⏳ Histórico de versões de templates
3. ⏳ Estatísticas de uso de templates
4. ⏳ Exportar/importar templates

---

## 📋 4. CONSIDERAÇÕES TÉCNICAS

### Compatibilidade
- Manter compatibilidade com código existente
- Templates do sistema não podem ser deletados
- Campo `template` em `billing_notifications` continua usando código do template

### Segurança
- Validar variáveis antes de renderizar
- Sanitizar conteúdo de templates (escapar HTML se necessário)
- Restringir edição de templates do sistema (apenas conteúdo, não código)

### Performance
- Cache de templates em memória (opcional)
- Índices no banco para busca rápida

### Extensibilidade
- Sistema preparado para futura integração com API oficial do WhatsApp
- Templates podem ser usados por outros canais (email, SMS) no futuro

---

## ✅ CONCLUSÃO

**O que já existe:**
- ✅ Sistema funcional de envio manual via WhatsApp Web
- ✅ Normalização de telefones
- ✅ Geração de links wa.me
- ✅ Histórico de envios em `billing_notifications`
- ✅ Integração com faturas e clientes

**O que falta:**
- ❌ Sistema de templates editáveis
- ❌ Interface de gestão de templates
- ❌ Suporte a variáveis dinâmicas configuráveis
- ❌ Templates para contextos além de cobrança

**Proposta:**
- ✅ Criar tabela `whatsapp_templates`
- ✅ Criar interface de gestão em "Configurações → Mensagens WhatsApp"
- ✅ Migrar templates atuais para o banco
- ✅ Modificar `WhatsAppBillingService` para usar templates do banco
- ✅ Adicionar suporte a novos contextos (migração, avisos, etc.)

**Próximos passos:**
1. Aprovar proposta
2. Implementar Fase 1 (estrutura base)
3. Testar compatibilidade
4. Implementar Fases 2 e 3

