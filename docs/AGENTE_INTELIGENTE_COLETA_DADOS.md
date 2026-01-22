# Agente Inteligente de Coleta de Dados

## Objetivo

Criar um agente de IA que coleta informações de forma **eficiente e inteligente**, sem cansar o usuário com perguntas desnecessárias.

## Dados Necessários

### Para Cadastro no Asaas (Obrigatórios)
1. **Nome completo** (obrigatório)
2. **CPF ou CNPJ** (obrigatório)
3. **Email** (obrigatório - para envio de faturas)
4. **Telefone** (opcional mas recomendado)
5. **Endereço completo** (opcional mas recomendado - para notas fiscais)

### Para Cartão de Visita (Específicos)
1. **Nome** (já coletado para Asaas)
2. **Telefone** (já coletado para Asaas)
3. **Email** (já coletado para Asaas - opcional no cartão)
4. **Endereço** (opcional no cartão)
5. **Site/Instagram** (opcional)
6. **Informações da Frente** (obrigatório - o que aparece na frente)
7. **Informações do Verso** (opcional - o que aparece no verso)

## Como Funciona

### 1. IntelligentDataCollector
Serviço que:
- Sabe exatamente quais campos são necessários
- Determina quais campos ainda faltam
- Prioriza campos por importância
- Extrai dados de mensagens do usuário

### 2. AIOrchestratorController
Controller que:
- Recebe mensagens do usuário
- Usa IA (OpenAI) para entender intenções
- Extrai múltiplos dados de uma única mensagem
- Detecta quando usuário quer corrigir algo
- Gera respostas contextuais e diretas

### 3. Fluxo Inteligente

```
Usuário envia mensagem
    ↓
IA analisa: intenção + dados extraídos
    ↓
Sistema verifica: o que ainda falta?
    ↓
Se faltam dados: pergunta APENAS o próximo prioritário
Se está completo: pede confirmação
Se usuário quer corrigir: permite imediatamente
```

## Características do Agente

### ✅ Eficiente
- Não faz perguntas desnecessárias
- Coleta múltiplos dados de uma vez se o usuário fornecer
- Pula campos opcionais se não forem críticos

### ✅ Inteligente
- Entende linguagem natural
- Detecta intenções implícitas ("preciso corrigir", "quero mudar")
- Extrai dados mesmo com formatação diferente

### ✅ Contextual
- Sabe o que já foi coletado
- Sabe o que ainda falta
- Adapta perguntas ao contexto

### ✅ Tolerante a Erros
- Aceita dados em formatos variados
- Corrige automaticamente quando possível
- Permite correções a qualquer momento

## Exemplos de Uso

### Exemplo 1: Coleta Rápida
```
Bot: Qual seu nome completo?
User: Charles Dietrich Wutzke
Bot: ✓ Nome: Charles Dietrich Wutzke
     Qual seu CPF ou CNPJ?
User: 034.547.699-90
Bot: ✓ CPF confirmado
     Qual seu email?
User: charles@email.com e meu telefone é (47) 99616-4699
Bot: ✓ Email: charles@email.com
     ✓ Telefone: (47) 99616-4699
     [Avança automaticamente - coletou 2 dados de uma vez]
```

### Exemplo 2: Correção
```
Bot: Qual seu email?
User: preciso corrigir meu nome
Bot: Entendi! Vou te ajudar a corrigir o nome.
     Qual seu nome completo?
User: Charles Wutzke (corrigido)
Bot: ✓ Nome atualizado: Charles Wutzke
     Qual seu email?
```

### Exemplo 3: Múltiplos Dados
```
Bot: Qual seu nome completo?
User: Meu nome é João Silva, CPF 123.456.789-00, email joao@email.com
Bot: ✓ Nome: João Silva
     ✓ CPF: 123.456.789-00
     ✓ Email: joao@email.com
     [Pula direto para próximo campo necessário]
```

## Configuração

### 1. Variável de Ambiente
Adicione no `.env`:
```
OPENAI_API_KEY=sk-...
```

### 2. Uso no Frontend
```javascript
// Chama o orquestrador
const response = await fetch('/client-portal/orders/ai-orchestrate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        message: userMessage,
        history: conversationHistory,
        formData: currentFormData,
        currentStep: currentStep,
        serviceType: 'business_card'
    })
});

const analysis = await response.json();
// analysis.analysis contém:
// - intention: intenção detectada
// - field: campo a ser preenchido/corrigido
// - extractedFields: múltiplos campos extraídos
// - action: próxima ação
// - response: mensagem para o usuário
```

## Benefícios

1. **Reduz tempo de preenchimento** - coleta múltiplos dados de uma vez
2. **Menos frustração** - permite correções a qualquer momento
3. **Mais conversões** - processo mais rápido = menos abandono
4. **Melhor experiência** - agente entende contexto e intenções

## Próximos Passos

1. ✅ Implementar IntelligentDataCollector
2. ✅ Implementar AIOrchestratorController
3. ⏳ Integrar no frontend do chat
4. ⏳ Testar com usuários reais
5. ⏳ Ajustar prompts baseado em feedback

