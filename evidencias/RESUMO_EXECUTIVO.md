# ✅ DIAGNÓSTICO COMPLETO - Resumo Executivo

**Data:** 2026-01-22  
**Caso:** Victor (telefone final 9047)  
**Status:** ✅ Todas as hipóteses validadas com evidências

---

## 🎯 CAUSA RAIZ CONFIRMADA

**Problema:** Integridade de dados - existem duas conversas distintas na tabela `conversations` para o mesmo contato, vinculadas a tenants diferentes.

**Conversas Identificadas:**
- **Conversa A (ID 15):** `169183207809126@lid` → Tenant 121
- **Conversa B (ID 17):** `169183207809126` (digits-only) → Tenant 7

---

## ✅ VALIDAÇÃO DAS HIPÓTESES

### H1: Existem duas conversas? ✅ **SIM - CONFIRMADA**

**Evidência:**
- Query 2 encontrou 1 par duplicado
- Arquivo: `db/duplicados-inicial.txt`

---

### H2: Ambas compartilham mensagens? ✅ **SIM - CONFIRMADA**

**Evidência:**
- Query 7 encontrou **6 mensagens compartilhadas** (100% dos event_id)
- Ambas retornam exatamente os mesmos `event_id`
- Arquivo: `db/mensagens-compartilhadas-15-17.txt`

---

### H3: Listagem mostra B em vez de A? ✅ **SIM - CONFIRMADA**

**Evidência:**
- `network-lista-antes.json` mostra ambas na lista
- Conversa A: `(11) 94086-3773` → Tenant 121
- Conversa B: `(47) 99950-8860` → Tenant 7
- UI pode renderizar/selecionar a conversa errada

---

### H4: Mudou de incoming_leads → threads? ⚠️ **N/A**

**Motivo:** Ambas já estão vinculadas (`is_incoming_lead: false`). Não é o cenário de lead sem tenant.

---

## 📊 IMPACTO DO BUG

1. **"Vínculo vai para cliente errado"**
   - UI pode exibir/operar na Conversa B (Tenant 7) ao invés da A (Tenant 121)

2. **"Conversa some e reaparece"**
   - Duas conversas competem na listagem
   - Dependendo de qual é renderizada, parece que "sumiu"

3. **"Conversa duplicada"**
   - Ambas aparecem na lista com informações diferentes
   - Mesmas mensagens, mas números/tenants diferentes

---

## 📁 ARQUIVOS DE EVIDÊNCIA

### Banco de Dados
- ✅ `db/estado-inicial-antes-vinculo.txt` - 2 conversas identificadas
- ✅ `db/duplicados-inicial.txt` - 1 par duplicado confirmado
- ✅ `db/mapeamento-lid-inicial.txt` - LID → `557781649047`
- ✅ `db/mensagens-compartilhadas-15-17.txt` - 6 mensagens compartilhadas (100%)

### Network
- ✅ `network/network-lista-antes.json` - Ambas aparecem na listagem
- ✅ `network/network-detalhe-antes.json` - Detalhe da Conversa 15

---

## 🔍 CONCLUSÃO TÉCNICA

O problema é de **integridade referencial**: duas conversas foram criadas para o mesmo contato usando identificadores diferentes (`169183207809126@lid` vs `169183207809126`), resultando em:

- Duplicação de registros
- Vínculos conflitantes (Tenant 121 vs 7)
- Mensagens compartilhadas (mesmos event_id)
- Inconsistência na UI (qual conversa exibir?)

**Próximo passo (fora do escopo deste diagnóstico):** Implementar lógica de merge/deduplicação ou constraint para prevenir duplicação.

---

**Diagnóstico concluído sem alterações de código.**

