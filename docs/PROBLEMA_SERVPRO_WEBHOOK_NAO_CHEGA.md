# Problema: Webhook INBOUND do ServPro não está chegando ao sistema

**Data:** 2026-01-14  
**Status:** 🔴 Confirmado - Webhook INBOUND não está chegando

---

## 📊 Contexto

- **Pixel12 Digital (destino):** `554797309525` - Número que RECEBE mensagens
- **ServPro (origem de teste):** `554796474223` - Número que ENVIA mensagens para Pixel12
- **Charles (origem de teste):** `554796164699` - Número que ENVIA mensagens para Pixel12

**Fluxo esperado:**
1. ServPro/Charles envia mensagem → WhatsApp
2. WhatsApp recebe → Gateway WhatsApp
3. Gateway envia webhook INBOUND → PixelHub
4. PixelHub ingere → Banco de dados

---

## 📊 Evidências

### ✅ O que está funcionando:
- **Charles → Pixel12:** 20 mensagens INBOUND recebidas nas últimas 24h
- **Simulador de Webhook:** Funciona perfeitamente (testado com ServPro)
- **Sistema de ingestão:** Funcionando (mensagens simuladas são inseridas)
- **Gateway recebe mensagens:** Confirmado (mensagens aparecem no WhatsApp)

### ❌ O que NÃO está funcionando:
- **ServPro → Pixel12:** 0 mensagens INBOUND recebidas nas últimas 24h
- **Checklist de Teste:** Mostra FAIL para webhook_received e inserted
- **Gateway não envia webhook:** Quando ServPro envia mensagem, webhook não chega ao PixelHub

---

## 🔍 Diagnóstico Realizado

### Script de Verificação
```bash
php database/check-webhook-logs-servpro.php
```

**Resultado:**
```
⚠️  ServPro - NENHUMA MENSAGEM ENCONTRADA nos últimos 2 horas!
   Isso indica que o webhook NÃO está chegando para o ServPro.
```

### Análise do Banco de Dados
- ✅ Mensagens do Charles aparecem normalmente
- ❌ Nenhuma mensagem do ServPro encontrada
- ✅ Mensagens simuladas do ServPro funcionam (inseridas corretamente)

---

## 🎯 Causa Raiz Identificada

**O gateway NÃO está enviando webhook INBOUND quando o ServPro envia mensagens para a Pixel12 Digital.**

Isso é um problema do **Gateway WhatsApp**, não do PixelHub, porque:

1. ✅ O sistema de ingestão funciona (mensagens simuladas são inseridas)
2. ✅ O webhook endpoint está funcionando (testado com simulador)
3. ✅ Mensagens INBOUND do Charles chegam normalmente (20 mensagens nas últimas 24h)
4. ❌ Mensagens INBOUND do ServPro não chegam (0 mensagens nas últimas 24h)
5. ✅ Gateway recebe as mensagens (aparecem no WhatsApp normalmente)

**Análise comparativa:**
- **Charles (554796164699) → Pixel12 (554797309525):** ✅ Webhook chega
- **ServPro (554796474223) → Pixel12 (554797309525):** ❌ Webhook NÃO chega

---

## 🔧 Ações Recomendadas

### 1. Verificar Configuração do Gateway

**Verificar no gateway (wpp.pixel12digital.com.br):**
- Se há configuração específica por número de origem
- Se há filtros ou regras que bloqueiam webhooks do ServPro (`554796474223`)
- Comparar configuração do Charles (funciona) com a do ServPro (não funciona)
- Verificar se há whitelist/blacklist de números
- Verificar configuração de webhook para mensagens INBOUND

### 2. Verificar Logs do Gateway

**Acessar logs do gateway (não do PixelHub):**
- Verificar se há tentativas de envio de webhook para o ServPro
- Verificar se há erros ao enviar webhook
- Verificar se o gateway está recebendo as mensagens do ServPro

### 3. Testar Webhook Manualmente

**Usar o simulador do painel de diagnóstico:**
1. Acesse: Configurações → WhatsApp Gateway → Diagnóstico (Debug)
2. Use o simulador com:
   - Template: "Mensagem Recebida (inbound)"
   - From: `554796474223`
   - Body: "Teste"
3. Verifique se é inserido no banco (deve funcionar)

### 4. Verificar Logs do Servidor

**Usar o botão "Verificar Logs do Servidor" no painel:**
1. Preencha o telefone: `554796474223`
2. Clique em "Verificar Logs do Servidor"
3. Veja se há algum log de webhook chegando

---

## 📝 Como Usar o Painel de Diagnóstico

### Verificar se Webhook Chegou:
1. Acesse: Configurações → WhatsApp Gateway → Diagnóstico (Debug)
2. No bloco "Checklist de Teste":
   - Telefone: `554796474223`
   - Thread ID: `whatsapp_34` (opcional)
   - Clique em "Capturar Agora"
3. Se mostrar FAIL em "Webhook Received", confirma que não chegou

### Verificar Logs do Servidor:
1. No mesmo bloco "Checklist de Teste"
2. Clique em "Verificar Logs do Servidor"
3. Veja se há logs de webhook chegando

### Consultar Mensagens:
1. No bloco "Últimas Mensagens e Threads":
   - Telefone: `4223` ou `554796474223`
   - Intervalo: "Últimos 15 min"
   - Clique em "Recarregar"
2. Se não aparecer nada, confirma que não há mensagens no banco

---

## 🎯 Próximos Passos

1. **Verificar configuração do gateway** para o número do ServPro
2. **Verificar logs do gateway** (não do PixelHub) para ver se está tentando enviar webhook
3. **Testar envio de mensagem** do ServPro e monitorar logs do gateway em tempo real
4. **Comparar configuração** do Charles (que funciona) com a do ServPro

---

## 📌 Nota Importante

**Este é um problema do Gateway WhatsApp, não do PixelHub.**

O PixelHub está funcionando corretamente:
- ✅ Endpoint de webhook está funcionando
- ✅ Sistema de ingestão está funcionando
- ✅ Mensagens simuladas são inseridas corretamente
- ✅ Mensagens do Charles chegam normalmente

O problema está no **Gateway não enviando webhook** para o número do ServPro.

---

**Última atualização:** 2026-01-14 16:20

