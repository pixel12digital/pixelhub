# Configurar Webhook Meta para Atualização Automática de Templates

## Problema
Templates aprovados no Meta Business Suite não atualizam automaticamente o status no PixelHub.

## Causa
O webhook do Meta não está configurado para enviar notificações de `message_template_status_update`.

## Solução: Configurar Webhook no Meta Business Suite

### 1. Acesse Meta Business Suite
1. Vá para https://business.facebook.com/
2. Selecione sua conta de negócios
3. Vá em **Configurações** → **WhatsApp Manager**

### 2. Configure o Webhook
1. No menu lateral, clique em **Configurações da API**
2. Na seção **Webhooks**, clique em **Configurar webhooks**
3. Clique em **Editar** na configuração existente (ou **Adicionar webhook** se não houver)

### 3. Preencha os Dados do Webhook

**URL de Callback:**
```
https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook
```

**Token de Verificação:**
```
pixelhub_meta_webhook_2024
```
*(Este token está definido em `src/Controllers/MetaWebhookController.php`)*

### 4. Inscreva-se nos Campos (Subscription Fields)

Marque os seguintes campos:
- ✅ **messages** (mensagens recebidas)
- ✅ **message_status** (status de entrega)
- ✅ **message_template_status_update** ⚠️ **IMPORTANTE** (atualizações de status de templates)

### 5. Salve e Teste

1. Clique em **Salvar**
2. O Meta enviará uma requisição de verificação
3. Se tudo estiver correto, o webhook será ativado

### 6. Teste a Configuração

Após configurar o webhook:
1. Crie um novo template no PixelHub
2. Envie para aprovação no Meta
3. Aguarde aprovação (pode levar minutos ou horas)
4. O status no PixelHub deve atualizar automaticamente para "Aprovado"

---

## Sincronização Manual (Temporária)

Enquanto o webhook não está configurado, use o script de sincronização manual:

### Sincronizar um template específico:
```bash
php temp_sync_template_status.php
```

### Sincronizar todos os templates pendentes:
```bash
php temp_sync_all_pending_templates.php
```

---

## Verificar Logs do Webhook

Para verificar se o webhook está recebendo notificações:

```sql
-- Ver últimos webhooks recebidos
SELECT * FROM webhook_raw_logs 
WHERE source = 'meta' 
ORDER BY created_at DESC 
LIMIT 10;

-- Ver webhooks de template status
SELECT * FROM webhook_raw_logs 
WHERE source = 'meta' 
AND payload LIKE '%message_template_status_update%'
ORDER BY created_at DESC;
```

---

## Estrutura do Webhook Implementado

O webhook já está implementado em `src/Controllers/MetaWebhookController.php`:

```php
// Linha 251-254
if ($field === 'message_template_status_update') {
    $this->processTemplateStatusUpdate($value);
    return true;
}
```

O método `processTemplateStatusUpdate()` (linhas 600-675) processa:
- ✅ Aprovação de templates (`APPROVED`)
- ✅ Rejeição de templates (`REJECTED`)
- ✅ Desativação de templates (`DISABLED`)

---

## Troubleshooting

### Webhook não está recebendo notificações
1. Verifique se a URL está correta no Meta Business Suite
2. Verifique se o campo `message_template_status_update` está marcado
3. Verifique os logs do servidor: `tail -f /var/log/apache2/error.log`

### Status não atualiza mesmo com webhook configurado
1. Verifique se há erros nos logs: `SELECT * FROM webhook_raw_logs WHERE processed = 0`
2. Execute a sincronização manual
3. Verifique se o `meta_template_id` está correto no banco

---

## Próximos Passos

Após configurar o webhook:
1. ✅ Templates aprovados atualizarão automaticamente
2. ✅ Templates rejeitados mostrarão o motivo da rejeição
3. ✅ Não será mais necessário sincronizar manualmente
