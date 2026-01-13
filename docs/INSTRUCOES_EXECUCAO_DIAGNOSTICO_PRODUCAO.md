# 📋 Instruções: Execução do Diagnóstico em Produção

**Objetivo:** Diagnosticar por que mensagens do ServPro não sobem pro topo da lista.

**Status:** ⚠️ **APENAS DIAGNÓSTICO - NÃO APLICAR CORREÇÕES**

---

## ✅ Passo 1: Teste Controlado

### Enviar mensagem de teste:
- **De:** Número ServPro (final 4223 = 554796474223)
- **Para:** WhatsApp da sessão "Pixel12 Digital"
- **Texto:** `TESTE SERVPRO PROD <hora_exata>`

**Exemplo:** `TESTE SERVPRO PROD 14:32:15`

**⚠️ Anotar:** Horário exato do envio

---

## ✅ Passo 2: Executar Diagnóstico (CLI)

### No servidor de produção:

```bash
# 1. Fazer git pull (se necessário)
cd /caminho/do/pixelhub
git pull

# 2. Executar diagnóstico
php database/diagnose-servpro-simple.php
```

**⚠️ OBRIGATÓRIO:** Executar via CLI, não via navegador.

---

## 📤 Passo 3: Coletar Output Completo

### Copiar EXATAMENTE o retorno do terminal

O output deve conter explicitamente:

1. ✅ **Classificação do evento:**
   - `event_type` gravado (inbound ou outbound)
   - Se foi classificado corretamente como `whatsapp.inbound.message`

2. ✅ **Dados do evento:**
   - `event_id` (UUID)
   - `channel_id` / sessão associada (deve ser "Pixel12 Digital")
   - `tenant_id` resolvido (número ou NULL)

3. ✅ **Conversa atualizada:**
   - `conversation_id` que foi atualizada
   - Valores na conversa do ServPro:
     - `last_message_at` (antes e depois)
     - `unread_count` (antes e depois)
     - `last_message_direction` (antes e depois)

4. ✅ **Isolamento:**
   - Se outra conversa foi atualizada indevidamente (Charles ou "Sem tenant")
   - Diferença de tempo entre atualização do evento e atualização da conversa

5. ✅ **Polling/Updates:**
   - Resultado do endpoint de updates (`has_updates=true` ou `false`)
   - Se a conversa do ServPro está incluída no resultado

6. ✅ **Conclusão:**
   - **(A) CLASSIFICAÇÃO:** ✅ OK ou ❌ Problema
   - **(B) MATCHING:** ✅ OK ou ❌ Problema
   - **(C) POLLING:** ✅ OK ou ❌ Problema

---

## 🚫 O que NÃO fazer

- ❌ Não aplicar correção
- ❌ Não alterar controller
- ❌ Não mexer em `mapEventType()`
- ❌ Não criar logs adicionais
- ❌ Não testar via navegador
- ❌ Não interpretar o resultado (apenas coletar)

---

## 🎯 Objetivo

Com o output completo do script, será definido:

1. **Causa raiz exata:** (A), (B) ou (C)
2. **Arquivo a alterar:** Qual controller/service
3. **Correção mínima:** Mudança cirúrgica e segura

---

## 📝 Exemplo de Output Esperado

```
=== DIAGNÓSTICO: Mensagem ServPro Inbound ===

📋 EVENTO ENCONTRADO:
   event_id: abc123-def456-...
   event_type: whatsapp.inbound.message ✅
   channel_id: Pixel12 Digital
   tenant_id: NULL
   created_at: 2026-01-13 14:32:15

📋 CONVERSA DO SERVPRO:
   conversation_id: 42
   last_message_at: 2026-01-13 14:32:15
   unread_count: 1
   last_message_direction: inbound
   ...

=== CONCLUSÃO ===
(A) CLASSIFICAÇÃO: ✅ OK
(B) MATCHING: ✅ OK
(C) POLLING: ❌ Problema
```

---

**Última atualização:** 2026-01-13

