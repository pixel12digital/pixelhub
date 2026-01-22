# Análise: Campos de Cliente e Integração Financeira com Asaas

**Data:** 2026-01-07  
**Objetivo:** Analisar necessidade de campos adicionais no cadastro de cliente e espelhamento com Asaas  
**Tipo:** Análise Arquitetural (sem implementação)

---

## 1. Comparativo: Campos Atuais vs. Asaas

### 1.1. Campos que TEMOS no Sistema

**Tabela `tenants` (atual):**
- `person_type` (pf/pj)
- `name` / `razao_social` / `nome_fantasia`
- `cpf_cnpj` / `document`
- `responsavel_nome` / `responsavel_cpf` (PJ)
- `email`
- `phone` (WhatsApp)
- `status` (active/inactive)
- `asaas_customer_id` (vínculo único)
- `billing_status`
- `billing_last_check_at`
- `internal_notes`
- `is_archived`
- `is_financial_only`

### 1.2. Campos que o ASAAS possui (tela de detalhes)

**Dados do Cliente:**
- ✅ Nome / Razão Social (temos)
- ✅ CPF/CNPJ (temos)
- ✅ Email (temos)
- ✅ Celular / Fone (temos parcialmente)
- ❌ **CEP** (não temos)
- ❌ **Rua / Endereço** (não temos)
- ❌ **Número** (não temos)
- ❌ **Complemento** (não temos)
- ❌ **Bairro** (não temos)
- ❌ **Cidade** (não temos)
- ❌ **Estado** (não temos)
- ❌ **Emails adicionais** (múltiplos, não temos)
- ❌ **Empresa** (campo específico do Asaas, não temos)
- ✅ **Observações** (temos `internal_notes`)
- ❌ **Enviar boletos via Correios** (flag específica Asaas)

**Funcionalidades Asaas (não espelhamos):**
- Assinaturas (subscriptions)
- Parcelamentos
- Cobranças (já sincronizamos via `billing_invoices`)
- Histórico de notificações

---

## 2. Análise de Necessidade por Contexto

### 2.1. Wizard de Cadastro (Fluxo Rápido)

**Campos atuais no modal do wizard:**
- Tipo (PF/PJ) ✅
- Nome / Razão Social ✅
- CPF/CNPJ ✅
- Email (opcional) ✅
- WhatsApp (opcional) ✅

**Análise:**
- ✅ **Adequado para o fluxo rápido** - Apenas dados mínimos necessários
- ✅ **Boa UX** - Não sobrecarrega o usuário na criação inicial
- ✅ **Alinhado com boas práticas** - Dados podem ser completados depois

**Recomendação:** Manter como está. Campos adicionais devem ser editados posteriormente no cadastro completo.

---

### 2.2. Cadastro Completo de Cliente (Tela `/tenants/create` ou `/tenants/edit`)

**Campos atuais:**
- Todos os campos do wizard ✅
- Responsável (PJ) ✅
- Status ✅
- Observações internas ✅

**Campos do Asaas que FALTAM:**
- ❌ Endereço completo (CEP, Rua, Número, Complemento, Bairro, Cidade, Estado)
- ❌ Emails adicionais (múltiplos)
- ❌ Telefone fixo (separado do celular)

**Análise:**

#### **Endereço:**
- **Necessário para Asaas?** ⚠️ **PARCIALMENTE**
  - Asaas usa endereço para:
    - Emissão de boletos (exigido para alguns bancos)
    - Notificações via Correios (opcional)
    - Validações fiscais
  - **Impacto:** Se cliente não tiver endereço, algumas cobranças podem falhar
  - **Solução:** Deixar opcional no sistema, mas **obrigatório ao criar customer no Asaas**

#### **Emails Adicionais:**
- **Necessário?** ⚠️ **BAIXO**
  - Asaas permite múltiplos emails para notificações
  - Sistema atual usa apenas 1 email
  - **Impacto:** Pode ser útil para notificações, mas não crítico
  - **Solução:** Poderia ser tabela separada `tenant_emails` se necessário no futuro

#### **Telefone Fixo:**
- **Necessário?** ❌ **NÃO CRÍTICO**
  - Asaas diferencia celular e telefone fixo
  - Sistema atual usa apenas WhatsApp (celular)
  - **Impacto:** Mínimo
  - **Solução:** Se necessário, adicionar campo `phone_fixed` opcional

---

## 3. Estratégia de Espelhamento com Asaas

### 3.1. Situação Atual

**Fluxo Implementado (`AsaasBillingService::ensureCustomerForTenant`):**

1. ✅ **Criação unidirecional:** Sistema → Asaas
   - Ao criar cliente, cria customer no Asaas (se tiver CPF/CNPJ)
   - Envia: nome, CPF/CNPJ, email, telefone, razão social (PJ)

2. ✅ **Busca inteligente:** Verifica se customer já existe no Asaas antes de criar

3. ⚠️ **Sincronização parcial:** Asaas → Sistema
   - Existe método `syncCustomerAndInvoicesForTenant`, mas não está sendo usado automaticamente
   - Dados editados no Asaas **não refletem automaticamente** no sistema

4. ⚠️ **Campos bloqueados:** Quando cliente tem `asaas_customer_id`, alguns campos ficam readonly
   - Nome, CPF/CNPJ, Email ficam bloqueados na edição
   - **Problema:** Se Asaas for atualizado, sistema não reflete a mudança

### 3.2. Problemas Identificados

#### **Problema 1: Dados dessincronizados**
- ❌ Edição no Asaas não atualiza sistema
- ❌ Edição no sistema não atualiza Asaas (para campos críticos)
- **Risco:** Dados divergentes entre sistemas

#### **Problema 2: Endereço faltando**
- ❌ Sistema não coleta endereço
- ⚠️ Asaas pode exigir endereço para algumas operações
- **Risco:** Falhas na criação de cobranças

#### **Problema 3: Sincronização manual**
- ⚠️ Não há botão "Sincronizar com Asaas" visível na UI
- ⚠️ Usuário não sabe quando dados estão desatualizados

---

## 4. Recomendações Arquiteturais

### 4.1. Wizard de Cadastro

**✅ MANTER COMO ESTÁ:**
- Apenas campos essenciais (Nome, CPF/CNPJ, Email, WhatsApp)
- Objetivo: Cadastro rápido sem atrito
- Dados podem ser completados depois

**Recomendação:** Não adicionar mais campos ao wizard.

---

### 4.2. Cadastro Completo de Cliente

**✅ ADICIONAR (Alta Prioridade):**

1. **Endereço Completo:**
   ```
   - cep (VARCHAR(10))
   - address_street (VARCHAR(255))
   - address_number (VARCHAR(20))
   - address_complement (VARCHAR(100))
   - address_neighborhood (VARCHAR(100))
   - address_city (VARCHAR(100))
   - address_state (VARCHAR(2)) // UF
   ```

   **Justificativa:**
   - Necessário para emissão de boletos no Asaas
   - Pode ser obrigatório em alguns casos fiscais
   - Melhora rastreabilidade de clientes

   **Implementação:**
   - Campos opcionais no sistema
   - **Obrigatórios apenas ao criar customer no Asaas** (se não tiver, pode gerar erro)
   - Integração com API ViaCEP para preenchimento automático

**⚠️ ADICIONAR (Média Prioridade - Futuro):**

2. **Telefone Fixo (separado):**
   ```
   - phone_fixed (VARCHAR(20))
   ```

   **Justificativa:**
   - Asaas diferencia celular de fixo
   - Pode ser útil para validações
   - Baixo impacto se não implementar agora

3. **Emails Adicionais (se necessário):**
   ```
   Tabela: tenant_emails
   - tenant_id (FK)
   - email (VARCHAR(255))
   - is_primary (TINYINT(1))
   ```

   **Justificativa:**
   - Útil para notificações múltiplas
   - Não crítico para funcionamento básico
   - Pode ser implementado depois se houver demanda

**❌ NÃO ADICIONAR:**

- Campo "Empresa" (específico do Asaas, não necessário no nosso contexto)
- Campo "Enviar boletos via Correios" (gerenciado no Asaas diretamente)
- Histórico de notificações (já temos em `billing_notifications`)

---

### 4.3. Estratégia de Sincronização

#### **Opção A: Sincronização Manual (Recomendada - Curto Prazo)**

**Implementar:**
1. Botão "Sincronizar com Asaas" na tela de cliente
2. Ao clicar:
   - Busca dados atualizados do customer no Asaas
   - Atualiza campos: nome, email, telefone, endereço
   - Mostra diff do que mudou
   - Pergunta ao usuário se quer aplicar mudanças

**Vantagens:**
- ✅ Controle do usuário
- ✅ Evita sobrescrever dados sem consentimento
- ✅ Simples de implementar

**Desvantagens:**
- ⚠️ Depende de ação manual
- ⚠️ Dados podem ficar desatualizados

---

#### **Opção B: Sincronização Automática Bidirecional (Médio Prazo)**

**Implementar:**
1. **Sistema → Asaas:** Ao editar cliente (se tiver `asaas_customer_id`):
   - Atualiza customer no Asaas automaticamente
   - Campos sincronizados: nome, email, telefone, endereço
   - CPF/CNPJ não pode ser alterado (bloqueio)

2. **Asaas → Sistema:** Webhook ou job agendado:
   - Webhook do Asaas notifica quando customer é atualizado
   - Ou job diário que verifica mudanças

**Vantagens:**
- ✅ Dados sempre sincronizados
- ✅ Experiência fluida

**Desvantagens:**
- ❌ Complexidade maior
- ❌ Risco de sobrescrever dados intencionalmente
- ❌ Requer webhook do Asaas (verificar disponibilidade)

**Recomendação:** Implementar Opção A primeiro, evoluir para Opção B se necessário.

---

#### **Opção C: Sistema como Fonte da Verdade (Longo Prazo)**

**Estratégia:**
- Sistema sempre sobrescreve Asaas
- Asaas é apenas receptor de dados
- Edições devem ser feitas apenas no sistema

**Vantagens:**
- ✅ Controle total
- ✅ Dados consistentes

**Desvantagens:**
- ❌ Usuário não pode editar diretamente no Asaas
- ❌ Requer disciplina operacional

**Recomendação:** Considerar se workflow permitir (provavelmente não é o caso).

---

### 4.4. Campos no Wizard vs. Cadastro Completo

**Estratégia Recomendada:**

| Campo | Wizard | Cadastro Completo | Sincroniza com Asaas |
|-------|--------|-------------------|---------------------|
| Tipo (PF/PJ) | ✅ Obrigatório | ✅ Obrigatório | Não aplicável |
| Nome / Razão Social | ✅ Obrigatório | ✅ Obrigatório | ✅ Sim |
| CPF/CNPJ | ✅ Obrigatório | ✅ Obrigatório | ⚠️ Não pode alterar |
| Email | ⚠️ Opcional | ⚠️ Opcional | ✅ Sim |
| WhatsApp | ⚠️ Opcional | ⚠️ Opcional | ✅ Sim (como phone) |
| Telefone Fixo | ❌ Não | ⚠️ Opcional | ✅ Sim |
| Endereço Completo | ❌ Não | ⚠️ Opcional | ✅ Sim |
| Responsável (PJ) | ❌ Não | ⚠️ Opcional | Não aplicável |
| Observações | ❌ Não | ✅ Disponível | ❌ Não |

**Legenda:**
- ✅ Obrigatório/Disponível
- ⚠️ Opcional
- ❌ Não disponível
- Sim/Não = Sincroniza com Asaas

---

## 5. Impacto Financeiro e Operacional

### 5.1. Campos Críticos para Financeiro

**Obrigatórios para criar cobrança no Asaas:**
- ✅ Nome (sempre)
- ✅ CPF/CNPJ (sempre)
- ⚠️ Endereço (depende do tipo de cobrança)
  - Boletos: geralmente requerem
  - Pix: não requer
  - Cartão: não requer

**Recomendação:**
- Coletar endereço no cadastro completo
- Validar se tem endereço antes de criar boletos no Asaas
- Mostrar aviso se cliente não tiver endereço ao tentar gerar boletos

---

### 5.2. Fluxo Recomendado

**1. Wizard (Cadastro Rápido):**
```
Cliente preenche: Nome, CPF/CNPJ, Email, WhatsApp
→ Cria tenant no sistema
→ NÃO cria customer no Asaas ainda (só cria quando gerar primeira cobrança)
```

**2. Cadastro Completo (Edição):**
```
Usuário completa: Endereço, Telefone Fixo, etc.
→ Pode sincronizar com Asaas manualmente
```

**3. Primeira Cobrança:**
```
Ao gerar primeira cobrança/projeto:
→ Verifica se tem CPF/CNPJ (obrigatório)
→ Verifica se tem endereço (se for boleto)
→ Cria customer no Asaas com todos os dados disponíveis
→ Vincula asaas_customer_id ao tenant
```

---

## 6. Conclusão e Próximos Passos

### 6.1. Resumo Executivo

**Campos necessários adicionar:**
- ✅ **Endereço completo** (alta prioridade)
- ⚠️ **Telefone fixo** (baixa prioridade)
- ❌ **Emails múltiplos** (não necessário agora)

**Sincronização:**
- ✅ Implementar botão "Sincronizar com Asaas" (curto prazo)
- ⚠️ Considerar sincronização automática no futuro (médio prazo)

**Wizard:**
- ✅ Manter como está (apenas campos essenciais)

---

### 6.2. Priorização

**FASE 1 (Imediato - se necessário):**
1. Adicionar campos de endereço na tabela `tenants`
2. Adicionar campos de endereço no formulário completo
3. Integração ViaCEP para preenchimento automático
4. Validar endereço antes de criar boletos no Asaas

**FASE 2 (Médio Prazo):**
1. Botão "Sincronizar com Asaas" na tela de cliente
2. Método que busca e atualiza dados do Asaas
3. Mostrar diff de mudanças antes de aplicar

**FASE 3 (Longo Prazo - se necessário):**
1. Webhook do Asaas para sincronização automática
2. Telefone fixo separado
3. Emails múltiplos (se houver demanda)

---

### 6.3. Decisão Recomendada

**✅ IMPLEMENTAR:**
- Campos de endereço completo no cadastro completo (não no wizard)
- Botão de sincronização manual com Asaas
- Validação de endereço ao criar boletos

**❌ NÃO IMPLEMENTAR AGORA:**
- Sincronização automática bidirecional
- Telefone fixo separado
- Emails múltiplos

**📌 MANTÉM:**
- Wizard com campos mínimos (como está)
- Estrutura atual de sincronização unidirecional (Sistema → Asaas)

---

**Fim da Análise**

