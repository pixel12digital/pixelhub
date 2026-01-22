# 📋 DIAGNÓSTICO: Bug de Vínculo de Conversa - Status Atual

**Caso:** Victor (telefone final 9047)  
**Data:** 2026-01-22

---

## ✅ FASE 1 CONCLUÍDA: Queries ANTES do Vínculo

### Resultados Encontrados:

**2 Conversas Identificadas:**
- **Conversa A (ID 15):** LID `169183207809126@lid` → Tenant 121
- **Conversa B (ID 17):** LID `169183207809126` (digits-only) → Tenant 7

**1 Par Duplicado:** Conversa 15 ↔ 17 (via mapeamento LID → telefone `557781649047`)

**Arquivos Gerados:**
- ✅ `evidencias/db/estado-inicial-antes-vinculo.txt`
- ✅ `evidencias/db/duplicados-inicial.txt`
- ✅ `evidencias/db/mapeamento-lid-inicial.txt`

---

## 📝 PRÓXIMOS PASSOS

### PASSO 2: Capturar Network ANTES

**Siga o guia:** `evidencias/GUIA_NETWORK_ANTES.md`

**O que fazer:**
1. Abra Communication Hub no navegador
2. DevTools (F12) → Network → Marque "Preserve log"
3. Acesse lista de conversas
4. Salve response de `GET /communication-hub/conversations-list` → `evidencias/network/network-lista-antes.json`
5. Abra conversa do "Victor"
6. Salve response de `GET /communication-hub/thread-info` → `evidencias/network/network-detalhe-antes.json`

---

### PASSO 3: Executar Vínculo

**Siga o guia:** `evidencias/GUIA_NETWORK_VINCULO.md`

**O que fazer:**
1. Clique em "Vincular" na conversa do "Victor"
2. Selecione um tenant/cliente
3. **Anote qual tenant você selecionou:** ID e Nome
4. Clique em "Vincular"
5. Salve:
   - Request: `POST /communication-hub/incoming-lead/link-tenant` → `evidencias/network/network-vinculo-request.json`
   - Response: → `evidencias/network/network-vinculo-response.json`

---

### PASSO 4: Executar Queries DEPOIS

**IMEDIATAMENTE após o vínculo:**

```bash
php evidencias-executar-depois.php
```

Isso gerará:
- `evidencias/db/estado-final-depois-vinculo.txt`
- `evidencias/db/duplicados-final.txt`
- `evidencias/db/conflitos-vinculo.txt`
- `evidencias/db/historico-atualizacoes.txt`

---

### PASSO 5: Capturar Network DEPOIS

**Siga o guia:** `evidencias/GUIA_NETWORK_DEPOIS.md`

**O que fazer:**
1. Recarregue a página (F5)
2. Salve response de `GET /communication-hub/conversations-list` → `evidencias/network/network-lista-depois.json`
3. Verifique se apareceu duplicada ou se mudou de categoria

---

### PASSO 6: Preencher Relatório Final

**Arquivo:** `evidencias/reports/comparacao-antes-depois.md`

**Preencha:**
- Tabela de comparação ANTES vs DEPOIS
- Validação das hipóteses H1-H4
- Conclusão objetiva

---

## 📊 RESUMO DO QUE JÁ SABEMOS

### Evidências ANTES:

1. **Duas conversas existem:**
   - Conversa A: LID com @lid (`169183207809126@lid`) → Tenant 121
   - Conversa B: LID digits-only (`169183207809126`) → Tenant 7

2. **Ambas compartilham:**
   - Mesmo telefone mapeado: `557781649047`
   - Relacionamento via `whatsapp_business_ids`

3. **Ambas já estão vinculadas:**
   - Não são incoming leads (`is_incoming_lead = 0`)
   - Já têm `tenant_id` diferentes

### Hipóteses Parcialmente Validadas:

- ✅ **H1:** SIM - Existem duas conversas (confirmado por Query 2)
- ⏳ **H2:** Provável SIM - Compartilham telefone mapeado
- ⏳ **H3:** A validar - Verificar qual aparece na listagem
- ⏳ **H4:** A validar - Verificar mudança de categoria

---

## 🗂️ ESTRUTURA DE ARQUIVOS

```
evidencias/
├── db/
│   ├── estado-inicial-antes-vinculo.txt ✅
│   ├── duplicados-inicial.txt ✅
│   ├── mapeamento-lid-inicial.txt ✅
│   ├── estado-final-depois-vinculo.txt ⏳
│   ├── duplicados-final.txt ⏳
│   ├── conflitos-vinculo.txt ⏳
│   └── historico-atualizacoes.txt ⏳
├── network/
│   ├── network-lista-antes.json ⏳
│   ├── network-detalhe-antes.json ⏳
│   ├── network-vinculo-request.json ⏳
│   ├── network-vinculo-response.json ⏳
│   └── network-lista-depois.json ⏳
├── reports/
│   └── comparacao-antes-depois.md ⏳
├── GUIA_NETWORK_ANTES.md ✅
├── GUIA_NETWORK_VINCULO.md ✅
├── GUIA_NETWORK_DEPOIS.md ✅
├── RESUMO_ANTES.md ✅
└── README.md (este arquivo) ✅
```

**Legenda:**
- ✅ Concluído
- ⏳ Pendente

---

## ⚠️ IMPORTANTE

- **NÃO commitar** arquivos da pasta `evidencias/`
- **NÃO criar** scripts no repositório (apenas os temporários `evidencias-executar-*.php`)
- **Deletar** scripts temporários após uso

---

**Próxima ação:** Siga o PASSO 2 (Capturar Network ANTES)

