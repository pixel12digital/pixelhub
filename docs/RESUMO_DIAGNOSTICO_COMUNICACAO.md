# Resumo: Diagnóstico de Comunicação - Status Atual

**Data:** 2026-01-13  
**Status:** ✅ Diagnóstico implementado | 🔄 Aguardando cadastro de canal

---

## ✅ O que foi implementado

### 1. Página de Diagnóstico
- **Localização:** Configurações → Diagnóstico → Comunicação
- **URL:** `/diagnostic/communication`
- **Funcionalidades:**
  - Teste 1: Resolver Canal (diagnóstico completo)
  - Teste 2: Dry-run Envio (simulação)
  - Teste 3: Envio Real (controlado, com confirmação)
  - Relatório completo com trace_id, passos, decisões, tempos
  - Botão para copiar relatório

### 2. Scripts de Apoio
- `database/list-threads-for-diagnostic.php` - Lista threads disponíveis
- `database/check-channel-id-format.php` - Verifica formato do channel_id
- `database/check-conversation-tenants.php` - Verifica tenant_id das conversations
- `database/upsert-whatsapp-channels.php` - Faz upsert de canais no banco

---

## 🔍 Descobertas

### Formato do channel_id
- **Identificador:** `session.id` dos payloads de eventos
- **Valor encontrado:** `"Pixel12 Digital"`
- **Tipo:** VARCHAR(100) - string

### Status das Conversations
- **Conversations 31 e 32:** `tenant_id = NULL` (conversas compartilhadas)
- **Todas as conversations WhatsApp:** `tenant_id = NULL` (33 conversations)

### Problema Identificado
- **Causa raiz:** Tabela `tenant_message_channels` está vazia
- **Sintoma:** `channel_id = 0` sendo enviado
- **Solução:** Cadastrar canal em `tenant_message_channels`

---

## 🛠️ Próximos Passos

### 1. Cadastrar Canal (IMEDIATO)

Execute o script de upsert escolhendo um tenant:

```bash
# Lista tenants disponíveis
php database/upsert-whatsapp-channels.php

# Cadastra canal para um tenant específico
php database/upsert-whatsapp-channels.php [tenant_id]
```

**Exemplo:**
```bash
php database/upsert-whatsapp-channels.php 2
```

Isso vai:
- Tentar buscar canais do gateway (se disponível)
- Fazer fallback para `'Pixel12 Digital'` (identificador dos payloads)
- Fazer upsert em `tenant_message_channels`
- Habilitar o canal automaticamente

### 2. Validar Diagnóstico

Após cadastrar o canal:

1. Acesse: Configurações → Diagnóstico → Comunicação
2. Preencha Thread ID: `whatsapp_31` ou `whatsapp_32`
3. Clique em "🔍 Resolver Canal"
4. Verifique no relatório:
   - ✅ `normalized_channel_id` deve mostrar `"Pixel12 Digital"`
   - ✅ `winning_rule` deve mostrar qual regra foi usada
   - ✅ Não deve mais aparecer "tenant sem canais ativos"

### 3. Testar Envio

Se o Teste 1 funcionar:

1. Preencha uma mensagem de teste
2. Execute Teste 2 (Dry-run) para validar
3. Execute Teste 3 (Envio Real) se tudo estiver OK

---

## 📝 Observações Importantes

### Sobre tenant_id NULL nas conversations

- Todas as conversations atuais têm `tenant_id = NULL`
- Isso significa que são conversas "compartilhadas" (não vinculadas a tenant específico)
- O script de upsert permite escolher um tenant para cadastrar o canal
- O sistema vai buscar canal do tenant OU canal compartilhado (fallback)

### Sobre o formato do channel_id

- O gateway pode retornar canais via `listChannels()`
- Se não disponível, usa fallback: `'Pixel12 Digital'` (encontrado nos payloads)
- O script de upsert tenta ambos os métodos automaticamente

### Evolução Futura

- **Persistir channel_id na conversation:** Ao criar/atualizar `conversations` a partir de eventos inbound, salvar `session.id` como `channel_id` na própria conversation
- **Isso garante:** Resposta sempre usa o mesmo canal que recebeu (padrão CRM)

---

## 📚 Documentação Criada

1. `docs/GUIA_DIAGNOSTICO_COMUNICACAO.md` - Guia completo de uso
2. `docs/DIAGNOSTICO_CHANNEL_ID_FORMATO.md` - Análise do formato do channel_id
3. `docs/RESUMO_DIAGNOSTICO_COMUNICACAO.md` - Este documento

---

**Última atualização:** 2026-01-13

