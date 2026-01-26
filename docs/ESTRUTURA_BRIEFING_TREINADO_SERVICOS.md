# Estrutura de Briefing Treinado para Todos os Serviços da Agência

**Data:** 2025-01-07  
**Objetivo:** Definir arquitetura para sistema de coleta de briefing inteligente e treinado, adaptável a qualquer serviço cadastrado no catálogo.

---

## 🎯 Visão Geral

O sistema deve ser capaz de coletar briefing personalizado para qualquer serviço da agência usando IA, onde:
- Cada serviço define seus próprios campos obrigatórios e opcionais
- A IA é treinada/contextualizada dinamicamente baseada no `briefing_template` do serviço
- O sistema adapta perguntas, validações e fluxo de coleta conforme o tipo de serviço
- Tudo é escalável - adicionar novo serviço = apenas cadastrar no banco

---

## 📋 Estado Atual

### O Que Já Existe

1. **Tabela `services`** ✅
   - Campo `briefing_template` (JSON) - armazena estrutura do briefing
   - Campo `name`, `category`, `description`
   - Já usada em `service_orders` (pedidos públicos)

2. **IntelligentDataCollector** ✅
   - Método `getRequiredFieldsForBusinessCard()` - hardcoded para cartão de visita
   - Método `getMissingFields()` - determina o que falta coletar
   - Método `extractDataFromMessage()` - extração básica e com IA

3. **AIOrchestratorController** ✅
   - Método `buildIntelligentPrompt()` - constrói prompt para IA
   - Já corrigido para listar campos válidos explicitamente
   - Suporta `serviceType` como parâmetro

4. **Public Form (service_orders/public_form.php)** ✅
   - Interface conversacional de briefing
   - Processa respostas da IA
   - Salva dados coletados

### Limitações Atuais

1. **Hardcoded para Business Card**
   - `IntelligentDataCollector` só tem método específico para cartão de visita
   - Campos são definidos em código PHP, não no banco

2. **Briefing Template Não Utilizado**
   - O `briefing_template` existe na tabela `services` mas não é usado para guiar a coleta
   - Não há integração entre o template e o sistema de coleta

3. **Sem Suporte Multi-Serviço**
   - Não há como o sistema saber quais campos coletar para cada serviço automaticamente
   - Precisa adicionar método novo para cada tipo de serviço

---

## 🏗️ Arquitetura Proposta

### 1. Estrutura do `briefing_template` (JSON)

Cada serviço deve ter um `briefing_template` que define:

```json
{
  "service_name": "Cartão de Visita Profissional",
  "service_context": "Coletando dados para criação de cartão de visita profissional",
  "fields": {
    "client_data": {
      "required": true,
      "fields": {
        "name": {
          "label": "Nome completo",
          "type": "text",
          "required": true,
          "priority": 1,
          "validation": "min:3",
          "hint": "Nome que aparecerá no cartão de visita",
          "ai_extraction_hints": ["nome completo", "meu nome é", "eu sou"]
        },
        "cpf_cnpj": {
          "label": "CPF ou CNPJ",
          "type": "cpf_cnpj",
          "required": true,
          "priority": 2,
          "validation": "cpf_cnpj",
          "hint": "Necessário para cadastro no sistema de pagamentos"
        },
        "email": {
          "label": "Email",
          "type": "email",
          "required": true,
          "priority": 3,
          "validation": "email",
          "hint": "Para envio de faturas e comunicação"
        },
        "phone": {
          "label": "Telefone/Celular",
          "type": "phone",
          "required": false,
          "priority": 4,
          "validation": "phone",
          "hint": "Para contato (opcional mas recomendado)"
        }
      }
    },
    "service_specific": {
      "frente_info": {
        "label": "Informações da Frente do Cartão",
        "type": "textarea",
        "required": true,
        "priority": 5,
        "hint": "O que deve aparecer na frente do cartão",
        "ai_extraction_hints": ["frente", "anverso", "principal"]
      },
      "verso_info": {
        "label": "Informações do Verso",
        "type": "textarea",
        "required": false,
        "priority": 6,
        "hint": "O que deve aparecer no verso (opcional)"
      },
      "website": {
        "label": "Site ou Instagram",
        "type": "text",
        "required": false,
        "priority": 7,
        "hint": "Site ou rede social para aparecer no cartão"
      }
    }
  },
  "questions": [
    {
      "id": "q_style",
      "label": "Qual estilo você prefere?",
      "type": "select",
      "options": ["Moderno", "Clássico", "Minimalista", "Criativo"],
      "required": true,
      "priority": 8,
      "ai_extraction_hints": ["estilo", "visual", "gosto", "prefiro"]
    },
    {
      "id": "q_colors",
      "label": "Quais cores você gostaria?",
      "type": "text",
      "required": false,
      "priority": 9,
      "hint": "Ex: Azul e branco, ou deixe em aberto para sugestão"
    }
  ],
  "ai_context": {
    "system_prompt_addition": "Este é um cartão de visita profissional. O nome informado será o que aparecerá no cartão.",
    "conversation_starters": [
      "Vamos criar seu cartão de visita profissional!",
      "Preciso de algumas informações para criar seu cartão."
    ],
    "completion_message": "Perfeito! Todos os dados foram coletados. Vamos iniciar a criação do seu cartão!"
  }
}
```

### 2. Extensão do `IntelligentDataCollector`

**Novos métodos necessários:**

```php
/**
 * Carrega campos necessários baseado no briefing_template do serviço
 */
public static function getFieldsFromServiceTemplate(int $serviceId): array

/**
 * Carrega campos necessários baseado no briefing_template JSON direto
 */
public static function parseBriefingTemplate(string $templateJson): array

/**
 * Retorna campos faltantes baseado em template de serviço
 */
public static function getMissingFieldsFromTemplate(
    array $collectedData, 
    array $templateFields
): array

/**
 * Gera prompt contextualizado para IA baseado no serviço
 */
public static function buildPromptFromService(
    array $template,
    array $collectedData,
    array $missingFields
): string
```

### 3. Integração no Fluxo de Coleta

**Fluxo proposto:**

1. Cliente acessa `/client-portal/orders/{order_id}`
2. Sistema identifica o `service_id` do pedido
3. Busca `briefing_template` do serviço no banco
4. `IntelligentDataCollector::parseBriefingTemplate()` converte JSON em estrutura de campos
5. `getMissingFieldsFromTemplate()` identifica o que falta
6. `AIOrchestratorController` recebe:
   - `briefing_template` (JSON)
   - `missingFields` (array estruturado)
   - `serviceContext` (string do template)
7. `buildIntelligentPrompt()` usa o template para:
   - Listar campos válidos dinamicamente
   - Contextualizar mensagens do bot
   - Personalizar validações
8. IA recebe prompt completo e contextualizado
9. Coleta segue fluxo normal, mas adaptado ao serviço

### 4. Estrutura de Dados Coletados

Os dados coletados devem ser armazenados em `service_orders.briefing_data`:

```json
{
  "client_data": {
    "name": "João Silva",
    "email": "joao@email.com",
    "phone": "(11) 99999-9999",
    "cpf_cnpj": "123.456.789-00"
  },
  "service_specific": {
    "frente_info": "Nome, telefone, email",
    "verso_info": "Mapa de localização",
    "website": "@joaosilva"
  },
  "questions": {
    "q_style": "Moderno",
    "q_colors": "Azul e branco"
  },
  "collected_at": "2025-01-07T15:30:00Z"
}
```

---

## 🔄 Adaptação do `AIOrchestratorController`

### Modificações Necessárias

1. **Receber `briefing_template` no método `analyzeWithAI()`**
   ```php
   private function analyzeWithAI(
       string $userMessage,
       array $history,
       array $formData,
       string $currentStep,
       ?string $currentQuestion,
       string $serviceType = 'business_card',
       ?array $briefingTemplate = null  // NOVO
   ): array
   ```

2. **Usar template para construir prompt**
   - Se `$briefingTemplate` fornecido, usar para:
     - Extrair campos válidos
     - Adicionar contexto do serviço
     - Personalizar mensagens
   - Se não fornecido, usar fallback atual (backward compatibility)

3. **Método auxiliar para extrair campos do template**
   ```php
   private function extractFieldsFromTemplate(array $template): array
   {
       // Percorre template.fields e template.questions
       // Retorna array no formato esperado por buildIntelligentPrompt
   }
   ```

---

## 📊 Exemplo: Novo Serviço "Criação de Logo"

### Template no Banco (`services.briefing_template`)

```json
{
  "service_name": "Criação de Logo",
  "service_context": "Coletando informações para criação de logo profissional",
  "fields": {
    "client_data": {
      "required": true,
      "fields": {
        "name": { "label": "Nome da empresa/pessoa", "type": "text", "required": true, "priority": 1 },
        "email": { "label": "Email", "type": "email", "required": true, "priority": 2 }
      }
    },
    "service_specific": {
      "business_description": {
        "label": "Descrição do negócio",
        "type": "textarea",
        "required": true,
        "priority": 3,
        "hint": "O que sua empresa/pessoa faz?"
      },
      "target_audience": {
        "label": "Público-alvo",
        "type": "text",
        "required": false,
        "priority": 4,
        "hint": "Quem são seus clientes?"
      }
    }
  },
  "questions": [
    {
      "id": "q_style_preference",
      "label": "Estilo visual preferido",
      "type": "select",
      "options": ["Moderno", "Vintage", "Minimalista", "Lúdico", "Corporativo"],
      "required": true,
      "priority": 5
    },
    {
      "id": "q_colors_preference",
      "label": "Cores preferidas (ou deixe em aberto)",
      "type": "text",
      "required": false,
      "priority": 6
    },
    {
      "id": "q_reference_logos",
      "label": "Tem algum logo de referência que gosta?",
      "type": "textarea",
      "required": false,
      "priority": 7,
      "hint": "Descreva ou mencione links"
    }
  ],
  "ai_context": {
    "system_prompt_addition": "Este é um projeto de criação de logo. É importante entender o negócio e o público-alvo para criar algo adequado.",
    "conversation_starters": [
      "Vamos criar o logo da sua marca!",
      "Preciso conhecer melhor seu negócio para criar o logo ideal."
    ]
  }
}
```

### Como Funciona

1. **Sistema carrega template** → `parseBriefingTemplate()`
2. **Extrai campos válidos** → `extractFieldsFromTemplate()`
3. **Lista campos no prompt da IA:**
   ```
   CAMPOS VÁLIDOS DISPONÍVEIS:
     - 'name': Nome da empresa/pessoa
     - 'email': Email
     - 'business_description': Descrição do negócio
     - 'target_audience': Público-alvo
     - 'q_style_preference': Estilo visual preferido
     - 'q_colors_preference': Cores preferidas
     - 'q_reference_logos': Tem algum logo de referência que gosta?
   
   CAMPOS VÁLIDOS GLOBAIS (sempre podem ser usados):
     - 'name': Nome completo (ou 'nome_completo')
     - 'email': Email
     - 'phone': Telefone/Celular
     - 'cpf_cnpj': CPF ou CNPJ
     - 'cep': CEP
   ```
4. **IA coleta dados** seguindo a estrutura do template
5. **Dados salvos** em `service_orders.briefing_data`

---

## 🔧 Implementação Técnica (Futura)

### Fase 1: Extensão do IntelligentDataCollector

1. Adicionar método `parseBriefingTemplate(string $json): array`
2. Adicionar método `getFieldsFromServiceTemplate(int $serviceId): array`
3. Modificar `getMissingFields()` para aceitar template como parâmetro alternativo
4. Manter métodos antigos para backward compatibility

### Fase 2: Integração no AIOrchestratorController

1. Modificar `analyzeWithAI()` para aceitar `briefingTemplate`
2. Criar método `extractFieldsFromTemplate()` 
3. Modificar `buildIntelligentPrompt()` para usar template quando disponível
4. Adicionar contexto do serviço ao prompt da IA

### Fase 3: Atualização do Frontend

1. Modificar `public_form.php` para:
   - Carregar `briefing_template` do serviço via AJAX
   - Passar template para o controller da IA
   - Adaptar interface se necessário (tipos de input diferentes)

### Fase 4: Cadastro de Serviços

1. Interface de edição de `briefing_template` no cadastro de serviços
2. Validador JSON para garantir estrutura correta
3. Preview do fluxo de briefing
4. Testes com diferentes tipos de serviço

---

## 📝 Checklist de Campos do Template

### Campos Obrigatórios no Template

- `service_name` (string) - Nome do serviço
- `fields` (object) - Estrutura de campos
  - `client_data` (object, opcional) - Dados do cliente
  - `service_specific` (object, opcional) - Campos específicos do serviço
- `questions` (array, opcional) - Perguntas adicionais

### Campos Opcionais

- `service_context` (string) - Contexto para a IA
- `ai_context` (object) - Configurações de IA
  - `system_prompt_addition` (string)
  - `conversation_starters` (array)
  - `completion_message` (string)

### Estrutura de um Campo Individual

```json
{
  "label": "Texto exibido ao usuário",
  "type": "text|email|phone|textarea|select|cpf_cnpj|cep",
  "required": true|false,
  "priority": 1-99,
  "validation": "min:3|email|cpf_cnpj|phone",
  "hint": "Dica/ajuda para o usuário",
  "ai_extraction_hints": ["palavra1", "palavra2"],
  "options": ["opção1", "opção2"] // apenas para type="select"
}
```

---

## 🎓 Treinamento da IA

### Contexto Adicionado Dinamicamente

O prompt da IA será enriquecido com:

1. **Nome do Serviço**
   ```
   CONTEXTO DO SERVIÇO: Você está coletando dados para [service_name].
   ```

2. **Contexto Específico**
   ```
   [ai_context.system_prompt_addition]
   ```

3. **Campos Válidos Listados Dinamicamente**
   ```
   CAMPOS VÁLIDOS DISPONÍVEIS:
     - 'campo1': Label do campo
     - 'campo2': Label do campo
   ```

4. **Dicas de Extração**
   - Se campo tem `ai_extraction_hints`, incluir no prompt
   - Exemplo: "Para extrair 'nome', procure por: 'meu nome é', 'eu sou', 'nome completo'"

### Exemplo de Prompt Final

```
Você é um assistente virtual PROFISSIONAL e EFICIENTE que coleta dados para pedidos de serviços.

CONTEXTO DO SERVIÇO: Você está coletando dados para criação de logo profissional.
Este é um projeto de criação de logo. É importante entender o negócio e o público-alvo para criar algo adequado.

CAMPOS VÁLIDOS DISPONÍVEIS:
  - 'name': Nome da empresa/pessoa
  - 'email': Email
  - 'business_description': Descrição do negócio
  - 'target_audience': Público-alvo
  - 'q_style_preference': Estilo visual preferido
  - 'q_colors_preference': Cores preferidas

CAMPOS VÁLIDOS GLOBAIS (sempre podem ser usados):
  - 'name': Nome completo (ou 'nome_completo')
  - 'email': Email
  - 'phone': Telefone/Celular
  - 'cpf_cnpj': CPF ou CNPJ
  - 'cep': CEP

REGRAS DE MAPEAMENTO DE CAMPOS:
[... resto do prompt ...]
```

---

## 🚀 Benefícios da Arquitetura

1. **Escalabilidade**
   - Adicionar novo serviço = apenas cadastrar no banco
   - Sem necessidade de alterar código

2. **Consistência**
   - Todos os serviços seguem o mesmo fluxo
   - Interface unificada

3. **Flexibilidade**
   - Cada serviço pode ter campos completamente diferentes
   - Perguntas personalizadas
   - Validações específicas

4. **Manutenibilidade**
   - Template em JSON = fácil de editar
   - Não precisa de deploy para alterar briefing

5. **Inteligência Adaptável**
   - IA é contextualizada para cada serviço
   - Prompt dinâmico baseado no template

---

## 🔍 Pontos de Atenção

### 1. Validação do Template
- Validar estrutura JSON antes de salvar
- Verificar campos obrigatórios
- Validar tipos de campos suportados

### 2. Backward Compatibility
- Manter métodos antigos funcionando
- Se `briefing_template` não fornecido, usar fallback atual

### 3. Performance
- Cache de templates se necessário
- Otimizar parsing de JSON

### 4. Segurança
- Validar e sanitizar dados extraídos pela IA
- Não confiar cegamente na extração

### 5. Testes
- Testar com diferentes serviços
- Validar extração de múltiplos campos
- Testar validações específicas

---

## 📚 Referências

- **IntelligentDataCollector**: `src/Services/IntelligentDataCollector.php`
- **AIOrchestratorController**: `src/Controllers/AIOrchestratorController.php`
- **ServiceService**: `src/Services/ServiceService.php`
- **Public Form**: `views/service_orders/public_form.php`
- **Tabela Services**: `database/migrations/20250107_create_services_table.php`

---

**Próximos Passos (quando implementar):**
1. Criar método `parseBriefingTemplate()` no `IntelligentDataCollector`
2. Modificar `AIOrchestratorController` para aceitar e usar template
3. Atualizar `public_form.php` para carregar template
4. Criar interface de edição de template no cadastro de serviços
5. Testar com 2-3 serviços diferentes
6. Documentar exemplos de templates para cada categoria de serviço
















