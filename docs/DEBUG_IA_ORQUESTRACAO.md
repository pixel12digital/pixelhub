# Debug: IA não está Orquestrando

## Problemas Identificados

1. **Logs adicionados** para rastrear o fluxo completo
2. **Fallback melhorado** para usar IntelligentDataCollector mesmo quando IA falha
3. **Tratamento de erros** melhorado no frontend

## Como Debuggar

### 1. Verificar Console do Navegador

Abra o console (F12) e procure por logs que começam com `[IA]`:
- `[IA] Enviando mensagem para análise: ...`
- `[IA] Resposta HTTP recebida: ...`
- `[IA] Análise recebida: ...`
- `[IA] Erro ao chamar IA: ...`

### 2. Verificar Logs do Servidor

Verifique os logs do PHP/Apache. Procure por:
- `[AI Orchestrator] ===== NOVA REQUISIÇÃO =====`
- `[AI Orchestrator] API Key raw existe: ...`
- `[AI Orchestrator] Chamando OpenAI API...`
- `[AI Orchestrator] ERRO ao chamar OpenAI: ...`

### 3. Testar Endpoint Diretamente

Use o console do navegador para testar:

```javascript
fetch('/painel.pixel12digital/public/client-portal/orders/ai-orchestrate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        message: 'teste',
        history: [],
        formData: { client: {}, address: {}, briefing: {} },
        currentStep: 'greeting',
        serviceType: 'business_card'
    })
})
.then(r => r.json())
.then(data => console.log('Resposta:', data))
.catch(e => console.error('Erro:', e));
```

### 4. Verificar API Key

O sistema precisa:
1. Ter `OPENAI_API_KEY` no `.env`
2. A chave pode estar criptografada (se foi salva via interface)
3. O sistema descriptografa automaticamente

## Possíveis Problemas

### Problema 1: API Key não descriptografada
**Sintoma**: Logs mostram "API Key raw existe: SIM" mas "API Key descriptografada existe: NÃO"
**Solução**: Verifique se a chave foi salva via interface de configurações de IA

### Problema 2: Endpoint não encontrado
**Sintoma**: Erro 404 no console
**Solução**: Verifique se a rota está registrada em `public/index.php`

### Problema 3: CORS ou headers
**Sintoma**: Erro de CORS no console
**Solução**: O endpoint já envia `Content-Type: application/json`

### Problema 4: Erro na chamada OpenAI
**Sintoma**: Logs mostram "ERRO ao chamar OpenAI"
**Solução**: Verifique logs do servidor para detalhes do erro

## Checklist de Verificação

- [ ] API Key configurada no `.env` ou via interface
- [ ] Rota registrada em `public/index.php`
- [ ] Console do navegador mostra logs `[IA]`
- [ ] Logs do servidor mostram requisições chegando
- [ ] Não há erros de CORS
- [ ] Resposta da API é parseada corretamente

