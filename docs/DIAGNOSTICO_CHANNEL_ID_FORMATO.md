# Diagnóstico: Formato do channel_id

**Data:** 2026-01-13  
**Objetivo:** Identificar o formato exato do `channel_id` usado pelo gateway WhatsApp

---

## 🔍 Análise dos Payloads

### Estrutura dos Eventos Inbound

Os payloads de `whatsapp.inbound.message` têm esta estrutura:

```json
{
  "spec_version": "1.0",
  "event": "message",
  "session": {
    "id": "Pixel12 Digital",
    "name": "Pixel12 Digital"
  },
  "message": {
    "from": "554796164699@c.us",
    "to": "554797309525@c.us",
    "text": "..."
  },
  "raw": {
    "provider": "wppconnect",
    "payload": {
      "session": "Pixel12 Digital",
      ...
    }
  }
}
```

### ⚠️ Descoberta Importante

**O `channel_id` NÃO está diretamente no payload**, mas o **`session.id`** está presente com o valor **`"Pixel12 Digital"`**.

Este é o identificador do canal/instância que recebeu a mensagem.

---

## 📋 Formato do channel_id

### Baseado na Análise:

1. **Gateway retorna:** Campo `id` ou `channel_id` na resposta de `listChannels()`
2. **Payloads de eventos:** Campo `session.id` contém o identificador (ex: `"Pixel12 Digital"`)
3. **Tipo:** VARCHAR(100) - pode ser string ou número

### Identificador Encontrado nos Payloads:

```
"Pixel12 Digital"
```

Este é o valor que deve ser usado como `channel_id` na tabela `tenant_message_channels`.

---

## 🛠️ Como Cadastrar um Canal

### Opção 1: Usando o identificador dos payloads

Se você já tem eventos no sistema, use o `session.id` encontrado nos payloads:

```sql
INSERT INTO tenant_message_channels 
(tenant_id, provider, channel_id, is_enabled, created_at) 
VALUES 
(1, 'wpp_gateway', 'Pixel12 Digital', 1, NOW());
```

**Nota:** A migration atual exige `tenant_id NOT NULL`. Para canal compartilhado, você pode:
- Usar `tenant_id = 0` (se permitido)
- Ou criar um tenant especial para canais compartilhados
- Ou alterar a migration para permitir `tenant_id NULL`

### Opção 2: Usando listChannels() do Gateway

Se o gateway estiver acessível, use o endpoint de teste:

```
GET /settings/whatsapp-gateway/test/channels
```

Isso retorna os canais disponíveis com seus IDs reais.

---

## 🔧 Correção Necessária na Migration

A migration atual tem:

```sql
tenant_id INT UNSIGNED NOT NULL
```

Para suportar canais compartilhados (sem tenant específico), seria ideal:

```sql
tenant_id INT UNSIGNED NULL
```

E remover a constraint `UNIQUE KEY unique_tenant_provider` ou ajustá-la para permitir múltiplos canais compartilhados.

---

## ✅ Recomendação Final

1. **Para teste imediato:**
   ```sql
   INSERT INTO tenant_message_channels 
   (tenant_id, provider, channel_id, is_enabled, created_at) 
   VALUES 
   (1, 'wpp_gateway', 'Pixel12 Digital', 1, NOW());
   ```
   (Use um tenant_id válido existente, ou ajuste a migration primeiro)

2. **Para produção:**
   - Alterar migration para permitir `tenant_id NULL`
   - Cadastrar canal compartilhado: `tenant_id = NULL, channel_id = 'Pixel12 Digital'`
   - Ou usar o valor retornado por `listChannels()` se diferente

3. **Evolução futura:**
   - Ao criar/atualizar `conversations` a partir de eventos inbound, persistir `session.id` como `channel_id` na própria conversation
   - Assim, a resposta sempre usa o mesmo canal que recebeu

---

## 📝 Próximos Passos

1. ✅ Validar Teste 1 do diagnóstico com thread real (`whatsapp_31` ou `whatsapp_32`)
2. ✅ Cadastrar canal em `tenant_message_channels` usando `'Pixel12 Digital'`
3. ✅ Testar envio real após cadastro
4. 🔄 Considerar evolução: persistir `channel_id` na tabela `conversations`

---

**Última atualização:** 2026-01-13

