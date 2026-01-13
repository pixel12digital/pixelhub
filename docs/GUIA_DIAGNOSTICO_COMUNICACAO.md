# Guia: Como Usar o Diagnóstico de Comunicação

## 📋 Informações Necessárias para Teste

### 1. Thread ID (Obrigatório)

O **Thread ID** identifica a conversa/conversation que você quer diagnosticar. Existem dois formatos:

#### Formato Novo (Recomendado): `whatsapp_{conversation_id}`

Onde `conversation_id` é o ID da tabela `conversations`.

**Como encontrar:**
1. Acesse o **Communication Hub** (`/communication-hub`)
2. Veja a lista de conversas ativas
3. Cada conversa tem um ID (ex: `1`, `2`, `3`)
4. O Thread ID será: `whatsapp_1`, `whatsapp_2`, `whatsapp_3`, etc.

**Ou via SQL:**
```sql
SELECT id, conversation_key, contact_external_id, tenant_id 
FROM conversations 
WHERE channel_type = 'whatsapp' 
ORDER BY id DESC 
LIMIT 10;
```

Use o `id` encontrado: `whatsapp_{id}`

#### Formato Antigo: `whatsapp_{tenant_id}_{from}`

Onde:
- `tenant_id` = ID do cliente na tabela `tenants`
- `from` = Número do WhatsApp (ex: `5511999999999`)

**Exemplo:** `whatsapp_5_5511999999999`

---

### 2. Mensagem de Teste (Opcional)

**Quando preencher:**
- ✅ **Deixe vazio** se quiser apenas **resolver o canal** (Teste 1)
- ✅ **Preencha** se quiser fazer **dry-run** (Teste 2) ou **envio real** (Teste 3)

**Exemplo de mensagem:**
```
Olá! Esta é uma mensagem de teste do diagnóstico de comunicação.
```

---

## 🧪 Como Executar os Testes

### Teste 1: Resolver Canal (Apenas Diagnóstico)

**Quando usar:** Para investigar por que `channel_id = 0` está sendo enviado.

**Passos:**
1. Preencha apenas o **Thread ID** (ex: `whatsapp_1`)
2. Deixe a **Mensagem de Teste** vazia
3. Clique em **🔍 Resolver Canal**

**O que retorna:**
- `thread.channel_id` (como veio do banco)
- `channel_id_input` (se veio de request)
- `normalized_channel_id` (após normalização: 0/"0"/"" → null)
- **Regra vencedora** (qual caminho foi usado para encontrar o canal)
- **Motivo de falha** (se não encontrou canal)
- **Detalhes** (JSON paths tentados, payload keys, etc.)

---

### Teste 2: Dry-run Envio (Simulação)

**Quando usar:** Para verificar se o envio funcionaria sem realmente enviar.

**Passos:**
1. Preencha o **Thread ID** (ex: `whatsapp_1`)
2. Preencha a **Mensagem de Teste** (ex: `Teste de diagnóstico`)
3. Clique em **🧪 Dry-run Envio**

**O que retorna:**
- Canal final selecionado
- **Validações** que rodariam e quais **bloqueariam** o envio
- **Ponto de aborto** (onde o código pararia se `channel_id = 0`)
- Payload final sanitizado (sem dados sensíveis)

---

### Teste 3: Enviar Real (Controlado)

**⚠️ ATENÇÃO:** Isso envia uma mensagem REAL via WhatsApp!

**Quando usar:** Para testar o envio completo após confirmar que tudo está OK nos testes anteriores.

**Passos:**
1. Preencha o **Thread ID** (ex: `whatsapp_1`)
2. Preencha a **Mensagem de Teste** (ex: `Teste de diagnóstico`)
3. Clique em **⚠️ Enviar Real (Controlado)**
4. **Confirme** o envio no popup

**O que retorna:**
- Tudo do Teste 1 e Teste 2
- **Status do provider** (sent/failed)
- **ID externo** (message_id do gateway)
- **Request/Response** sanitizados

---

## 📊 Entendendo o Relatório

### Trace ID
Cada execução gera um `trace_id` único no formato:
```
diag_20260113_104530_abc123
```

Use este ID para:
- Comparar testes A/B
- Buscar nos logs do servidor
- Rastrear problemas específicos

### Passos do Diagnóstico
Cada passo mostra:
- **Descrição**: O que foi executado
- **Resultado**: `success`, `found`, `not_found`, `would_block`, etc.
- **Tempo**: Quanto tempo levou (em ms)
- **Dados**: Detalhes expandíveis (clique em "Ver dados")

### Regras de Resolução de Canal

O sistema tenta encontrar o `channel_id` nesta ordem:

1. **PRIORIDADE 1:** `channel_id` fornecido diretamente (vem da thread)
2. **PRIORIDADE 2:** Busca `channel_id` dos eventos da conversa usando `thread_id`
3. **PRIORIDADE 3:** Busca canal do tenant (`tenant_message_channels`)
4. **PRIORIDADE 4:** Fallback para canal compartilhado (qualquer canal habilitado)

---

## 🔍 Exemplos Práticos

### Exemplo 1: Investigar channel_id = 0

**Problema:** Mensagens não estão sendo enviadas, erro "channel_id = 0"

**Solução:**
1. Execute **Teste 1** com o `thread_id` problemático
2. Veja no relatório:
   - Qual regra foi usada (ou se falhou)
   - Se `normalized_channel_id` é `null`
   - Qual foi o motivo de falha
3. Corrija o problema baseado no relatório
4. Execute **Teste 2** para confirmar que agora funcionaria
5. Execute **Teste 3** para enviar de verdade

---

### Exemplo 2: Validar antes de enviar

**Situação:** Quer enviar uma mensagem importante, mas não tem certeza se vai funcionar

**Solução:**
1. Execute **Teste 2** (Dry-run) primeiro
2. Verifique se todas as validações passaram
3. Se `would_block = false`, execute **Teste 3** para enviar

---

### Exemplo 3: Comparar dois threads

**Situação:** Um thread funciona, outro não. Quer comparar.

**Solução:**
1. Execute **Teste 1** no thread que funciona
2. Copie o relatório (botão "📋 Copiar Relatório")
3. Execute **Teste 1** no thread que não funciona
4. Compare os dois relatórios (especialmente a seção "Regra vencedora")

---

## 🛠️ Troubleshooting

### "Thread não encontrada"
- Verifique se o `thread_id` está correto
- Confirme que a conversa existe na tabela `conversations`
- Use o formato correto: `whatsapp_{id}` ou `whatsapp_{tenant_id}_{from}`

### "Nenhum canal encontrado"
- Verifique se há canais habilitados em `tenant_message_channels`
- Execute: `SELECT * FROM tenant_message_channels WHERE provider = 'wpp_gateway' AND is_enabled = 1;`

### "channel_id = 0 ainda sendo enviado"
- Execute **Teste 1** para ver onde está falhando
- Verifique se a normalização está funcionando (0/"0"/"" → null)
- Veja nos "Detalhes" quais JSON paths foram tentados

---

## 📝 Notas Importantes

1. **Trace ID é único:** Cada execução gera um novo trace_id, mesmo para o mesmo thread
2. **Dados sanitizados:** Telefones e mensagens são mascarados no relatório para segurança
3. **Logs no servidor:** O trace_id é logado no servidor para rastreamento completo
4. **Flag de ativação:** A página pode ser desativada via `.env` com `COMMUNICATION_DIAGNOSTICS_ENABLED=false`

---

**Última atualização:** 2026-01-13

