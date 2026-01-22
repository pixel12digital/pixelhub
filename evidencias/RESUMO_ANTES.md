# RESUMO: Estado ANTES do Vínculo

**Data:** 2026-01-22 19:12:46

## 📊 RESULTADOS ENCONTRADOS

### Conversas Identificadas

**CONVERSA A (ID: 15)**
- `contact_external_id`: `169183207809126@lid` (LID com sufixo)
- `remote_key`: `lid:169183207809126`
- `thread_key`: `wpp_gateway:pixel12digital:lid:169183207809126`
- `tenant_id`: **121** (SO OBRAS EPC DISTRIBUICAO E INSTALACOES LTDA)
- `is_incoming_lead`: **0** (já vinculada)
- `mapped_phone`: `557781649047`

**CONVERSA B (ID: 17)**
- `contact_external_id`: `169183207809126` (LID digits-only, sem @lid)
- `remote_key`: `tel:169183207809126`
- `thread_key`: **NULL**
- `tenant_id`: **7** (LAWINTER VAI D ECRUZEIRO LTDA)
- `is_incoming_lead`: **0** (já vinculada)
- `mapped_phone`: `557781649047` (mesmo telefone!)

### Duplicados

✅ **ENCONTRADO:** 1 par duplicado
- Conversa 15 ↔ Conversa 17
- Relacionamento: Via mapeamento LID → telefone
- **PROBLEMA:** Ambas já estão vinculadas a tenants diferentes!

### Mapeamento LID

- `business_id`: `169183207809126@lid`
- `phone_number`: `557781649047`
- Ambas as conversas compartilham o mesmo telefone mapeado

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

1. **Ambas as conversas já estão vinculadas** (não são incoming leads)
   - Conversa A → Tenant 121
   - Conversa B → Tenant 7

2. **O telefone final é 9047** (do mapeamento: `557781649047`)

3. **Se você tentar vincular uma delas novamente:**
   - O sistema pode atualizar a Conversa A
   - Mas a Conversa B continuará vinculada ao Tenant 7
   - Isso pode causar confusão na listagem

---

## 📝 PRÓXIMOS PASSOS

1. **Capturar Network ANTES** (seguir `GUIA_NETWORK_ANTES.md`)
2. **Executar vínculo** (seguir `GUIA_NETWORK_VINCULO.md`)
3. **Executar queries DEPOIS** (`php evidencias-executar-depois.php`)
4. **Capturar Network DEPOIS**
5. **Gerar relatório final**

