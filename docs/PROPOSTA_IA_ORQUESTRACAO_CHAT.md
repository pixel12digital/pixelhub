# Proposta: Orquestração com IA para Chat de Pedidos

## Objetivo

Transformar o chat atual em um sistema inteligente que:
1. **Detecta erros automaticamente** nos dados informados
2. **Permite correções** de forma natural durante a conversa
3. **Valida dados em tempo real** com feedback contextual
4. **Oferece uma experiência conversacional** mais natural e fluida

---

## Problemas Atuais

1. **Chat rígido e sequencial**: Se o usuário erra um dado, não há como corrigir sem recomeçar
2. **Validação limitada**: Apenas valida formato, não detecta erros lógicos ou dados inconsistentes
3. **Sem contexto**: Não entende intenções como "corrigir", "alterar", "voltar"
4. **Feedback pobre**: Mensagens de erro genéricas sem sugestões

---

## Arquitetura Proposta

### 1. Camada de Orquestração com IA

```
┌─────────────────────────────────────────┐
│   Interface de Chat (Frontend)          │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│   Orchestrator (LLM - GPT-4/Claude)     │
│   - Analisa contexto da conversa        │
│   - Identifica intenções do usuário     │
│   - Decide próximos passos               │
│   - Detecta erros e inconsistências     │
└──────────────┬──────────────────────────┘
               │
       ┌───────┴────────┐
       ▼                ▼
┌──────────────┐  ┌──────────────┐
│  Validador   │  │  Gerenciador │
│  de Dados    │  │  de Estado   │
└──────────────┘  └──────────────┘
```

### 2. Componentes Principais

#### A. **Orquestrador Inteligente (IA)**
- **Responsabilidade**: Entender o contexto da conversa e tomar decisões
- **Input**: Histórico de mensagens + estado atual do formulário
- **Output**: Próxima ação (perguntar, validar, corrigir, avançar)

#### B. **Validador Inteligente**
- **Validação tradicional**: CPF, CNPJ, CEP, email
- **Validação contextual**: 
  - "Nome muito curto, está completo?"
  - "Este email parece incorreto, confirma?"
  - "CPF não confere, pode verificar?"

#### C. **Gerenciador de Estado**
- Mantém histórico completo da conversa
- Rastreia dados coletados
- Permite voltar e corrigir qualquer etapa

---

## Fluxo Proposto

### Exemplo 1: Correção de Dado

```
Bot: Qual seu email?
User: test@email
Bot: Este email parece estar incompleto. Pode verificar? (geralmente termina com .com, .com.br, etc.)
User: ah sim, é test@email.com.br
Bot: ✓ Email confirmado: test@email.com.br
     Próxima pergunta...
```

### Exemplo 2: Correção Posterior

```
Bot: [Após coletar vários dados]
     Vou resumir o que coletamos:
     - Nome: João Silva
     - Email: joao@email.com
     - CPF: 123.456.789-00
     ...
     Está tudo correto?
User: O email está errado, é joao.silva@email.com
Bot: Entendi! Vou corrigir o email.
     Email atualizado para: joao.silva@email.com
     Continuando...
```

### Exemplo 3: Detecção de Inconsistência

```
Bot: Qual seu CPF?
User: 123.456.789-10
Bot: Verificando... Este CPF não é válido (dígitos verificadores incorretos).
     Pode verificar novamente?
User: 123.456.789-00
Bot: ✓ CPF válido confirmado!
```

---

## Implementação Técnica

### Opção 1: API Externa (OpenAI/Anthropic)

```javascript
async function orchestrateChat(userMessage, conversationHistory, formData) {
    const prompt = `
Você é um assistente virtual que coleta dados para pedidos de serviços.

Estado atual do formulário:
${JSON.stringify(formData, null, 2)}

Histórico da conversa:
${conversationHistory.map(m => `${m.role}: ${m.content}`).join('\n')}

Última mensagem do usuário: "${userMessage}"

Analise:
1. Qual a intenção do usuário? (informar dado, corrigir, confirmar, etc.)
2. O dado informado está correto?
3. Qual deve ser a próxima ação?

Responda em JSON:
{
    "intention": "inform|correct|confirm|error",
    "validation": {
        "valid": true/false,
        "errors": ["erro1", "erro2"],
        "suggestions": ["sugestão1"]
    },
    "action": "next_question|ask_correction|confirm_data|show_summary",
    "response": "mensagem para o usuário",
    "updateData": {"field": "value"} // se houver correção
}
    `;

    const response = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${API_KEY}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            model: 'gpt-4',
            messages: [{ role: 'user', content: prompt }],
            temperature: 0.3
        })
    });

    return await response.json();
}
```

### Opção 2: Modelo Local (Ollama/Llama)

```javascript
async function orchestrateWithLocalModel(userMessage, context) {
    const response = await fetch('http://localhost:11434/api/generate', {
        method: 'POST',
        body: JSON.stringify({
            model: 'llama2',
            prompt: buildPrompt(userMessage, context),
            stream: false
        })
    });
    
    return await response.json();
}
```

---

## Melhorias Específicas

### 1. Validação Inteligente

```javascript
function intelligentValidation(field, value, formData) {
    const validations = {
        email: {
            format: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            suggestions: (val) => {
                if (!val.includes('@')) return 'Falta o @ no email';
                if (!val.includes('.')) return 'Email parece incompleto, falta o domínio';
                if (val.length < 5) return 'Email muito curto';
            }
        },
        cpf: {
            validator: validarCPF,
            suggestions: (val, isValid) => {
                if (!isValid) {
                    return 'CPF inválido. Verifique os dígitos informados.';
                }
            }
        },
        name: {
            minLength: 3,
            suggestions: (val) => {
                if (val.length < 3) return 'Nome muito curto. Pode informar o nome completo?';
                if (!val.includes(' ')) return 'Informe nome e sobrenome, por favor.';
            }
        }
    };
    
    return validations[field]?.suggestions(value);
}
```

### 2. Sistema de Correção

```javascript
class ConversationManager {
    constructor() {
        this.history = [];
        this.formData = {};
        this.currentStep = 'greeting';
    }

    async processMessage(userMessage) {
        // Adiciona ao histórico
        this.history.push({ role: 'user', content: userMessage });
        
        // Analisa com IA
        const analysis = await this.orchestrator.analyze(userMessage, this.history, this.formData);
        
        // Processa ação
        switch (analysis.intention) {
            case 'correct':
                this.updateField(analysis.updateData);
                return this.generateCorrectionResponse(analysis);
            
            case 'inform':
                const validation = this.validateField(analysis.field, analysis.value);
                if (!validation.valid) {
                    return this.generateValidationError(validation);
                }
                this.updateField({ [analysis.field]: analysis.value });
                return this.generateNextQuestion();
            
            case 'confirm':
                return this.showSummaryAndConfirm();
        }
    }

    updateField(updates) {
        Object.assign(this.formData, updates);
        this.saveToHiddenFields();
    }

    canGoBack() {
        return this.history.length > 0;
    }
}
```

### 3. Interface de Correção

```javascript
function addCorrectionInterface() {
    // Botão para ver resumo e corrigir
    const summaryBtn = document.createElement('button');
    summaryBtn.textContent = '📋 Ver resumo e corrigir';
    summaryBtn.onclick = () => {
        showSummaryModal();
    };
    chatContainer.appendChild(summaryBtn);
}

function showSummaryModal() {
    const modal = document.createElement('div');
    modal.className = 'correction-modal';
    modal.innerHTML = `
        <h3>Resumo dos Dados</h3>
        <div class="summary-item" data-field="name">
            <strong>Nome:</strong> ${formData.client.name}
            <button onclick="correctField('name')">✏️ Corrigir</button>
        </div>
        <div class="summary-item" data-field="email">
            <strong>Email:</strong> ${formData.client.email}
            <button onclick="correctField('email')">✏️ Corrigir</button>
        </div>
        <!-- ... outros campos ... -->
    `;
    document.body.appendChild(modal);
}

function correctField(fieldName) {
    // Volta para a pergunta específica
    // Permite reescrever o valor
    askQuestion(fieldName, true); // true = modo correção
}
```

---

## Fases de Implementação

### Fase 1: Validação Inteligente (Imediata)
- ✅ Validação de CPF/CNPJ já implementada
- 🔄 Adicionar validações contextuais
- 🔄 Mensagens de erro mais úteis
- 🔄 Sugestões automáticas

### Fase 2: Sistema de Correção Básico (Curto Prazo)
- 🔄 Botão "Ver resumo"
- 🔄 Permitir corrigir campos já preenchidos
- 🔄 Histórico de alterações

### Fase 3: Orquestração com IA (Médio Prazo)
- 🔄 Integração com API de IA (OpenAI/Anthropic)
- 🔄 Análise de intenções
- 🔄 Detecção automática de erros
- 🔄 Sugestões contextuais

### Fase 4: Experiência Completa (Longo Prazo)
- 🔄 Modelo fine-tuned para o domínio
- 🔄 Correções em linguagem natural
- 🔄 Validação cruzada de dados
- 🔄 Personalização baseada em histórico

---

## Exemplo de Prompt para IA

```
Você é um assistente virtual profissional da Pixel12Digital que coleta informações para criar pedidos de serviços.

REGRAS:
1. Seja amigável mas profissional
2. Valide todos os dados antes de aceitar
3. Se detectar erro, explique claramente e sugira correção
4. Permita que o usuário corrija qualquer informação anterior
5. Confirme dados importantes antes de avançar

DADOS COLETADOS ATÉ AGORA:
${JSON.stringify(formData, null, 2)}

PERGUNTAS RESTANTES:
- ${pendingQuestions.join('\n- ')}

ÚLTIMA MENSAGEM DO USUÁRIO:
"${userMessage}"

Analise e responda:
1. O que o usuário quer fazer? (informar dado, corrigir, confirmar)
2. Se informou um dado, está correto?
3. Qual a próxima ação?

Formato de resposta (JSON):
{
    "intention": "inform|correct|confirm|question",
    "field": "nome_do_campo",
    "value": "valor_informado",
    "validation": {
        "valid": true/false,
        "error": "mensagem de erro se houver",
        "suggestion": "sugestão de correção"
    },
    "nextAction": "ask_next|ask_correction|show_summary",
    "message": "mensagem para o usuário"
}
```

---

## Benefícios

1. **Experiência do Usuário**: Chat mais natural e fluido
2. **Menos Erros**: Validação inteligente reduz dados incorretos
3. **Correções Fáceis**: Não precisa recomeçar se errar
4. **Maior Conversão**: Processo menos frustrante = mais completos
5. **Escalabilidade**: IA pode lidar com casos especiais automaticamente

---

## Custos Estimados

### OpenAI GPT-4
- ~$0.03 por conversa completa
- Para 1000 conversas/mês: ~$30/mês

### Anthropic Claude
- ~$0.015 por conversa
- Para 1000 conversas/mês: ~$15/mês

### Modelo Local (Ollama)
- Sem custo adicional (infraestrutura própria)
- Requer servidor dedicado

---

## Recomendação

**Começar com Fase 1 e 2** (validação inteligente + correções básicas) antes de investir em IA completa. Isso já resolve 80% dos problemas de UX.

Depois, avaliar necessidade real de IA baseado no volume e casos de uso específicos.

