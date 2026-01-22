# Explicação: Por que alguns contatos exibem tags de canal e outros não?

## Problema

Na interface do Painel de Comunicação, alguns contatos exibem tags ao lado do número de telefone (como "pixel12digital" ou "imobsites"), enquanto outros contatos (como "Charles Dietrich" e "Contato Desconhecido") não exibem essa informação.

## Causa Raiz

A exibição da tag depende do campo `channel_id` na tabela `conversations`:

- **Contatos com tag**: Têm o campo `channel_id` preenchido na tabela `conversations`
- **Contatos sem tag**: Têm o campo `channel_id` como `NULL` ou vazio na tabela `conversations`

### Por que alguns contatos não têm `channel_id`?

1. **Conversas antigas**: Criadas antes da implementação do campo `channel_id`
2. **Eventos sem informações de sessão**: Alguns eventos não contêm informações suficientes no payload para identificar o canal/sessão
3. **Falha na extração**: Eventos processados quando o sistema não conseguia extrair o `channel_id` corretamente

## Como funciona a exibição

O código em `views/communication_hub/index.php` (linhas 1078-1079) exibe o `channel_id` apenas quando ele não está vazio:

```php
<?php if (!empty($thread['channel_id'])): ?>
    <span style="opacity: 0.6; font-size: 11px;">• <?= htmlspecialchars($thread['channel_id']) ?></span>
<?php endif; ?>
```

O `channel_id` é obtido diretamente da tabela `conversations` na query em `src/Controllers/CommunicationHubController.php` (linha 1416):

```php
'channel_id' => $conv['channel_id'] ?? null, // Nome da sessão
```

## Solução

### Script de Correção

Foi criado um script para preencher os `channel_id` faltantes a partir dos eventos mais recentes:

**Arquivo**: `database/fill-missing-channel-id-conversations.php`

### Como usar:

1. Execute o script via terminal:
```bash
php database/fill-missing-channel-id-conversations.php
```

2. O script irá:
   - Buscar todas as conversas sem `channel_id`
   - Tentar extrair o `channel_id` dos eventos mais recentes de cada conversa
   - Atualizar a tabela `conversations` com o `channel_id` encontrado

3. Após a execução, as conversas atualizadas passarão a exibir a tag do canal na interface.

### Limitações

- Se não houver eventos com informações de `channel_id` para uma conversa, ela não poderá ser atualizada
- Conversas muito antigas podem não ter eventos disponíveis
- O script usa a mesma lógica de extração do `ConversationService`, priorizando `sessionId` sobre `channel_id` do metadata

## Estrutura de Dados

### Tabela `conversations`

- `channel_id`: Nome da sessão/canal (ex: "pixel12digital", "imobsites")
- `session_id`: ID da sessão (geralmente igual ao `channel_id` para WhatsApp)
- `contact_external_id`: ID externo do contato (número de telefone)
- `tenant_id`: ID do cliente vinculado (pode ser NULL para contatos desconhecidos)

### Tabela `communication_events`

- `payload`: JSON com os dados do evento, incluindo informações de sessão
- `event_type`: Tipo do evento (ex: "whatsapp.inbound.message")
- `tenant_id`: ID do tenant associado ao evento

## Prevenção Futura

O sistema já está configurado para preencher o `channel_id` automaticamente quando novas conversas são criadas através do `ConversationService`. O problema afeta principalmente conversas antigas criadas antes dessa implementação.

## Verificação

Para verificar quantas conversas ainda estão sem `channel_id`:

```sql
SELECT COUNT(*) 
FROM conversations 
WHERE channel_type = 'whatsapp' 
AND (channel_id IS NULL OR channel_id = '');
```

Para ver conversas específicas sem `channel_id`:

```sql
SELECT 
    id,
    conversation_key,
    contact_external_id,
    contact_name,
    tenant_id,
    created_at,
    last_message_at
FROM conversations 
WHERE channel_type = 'whatsapp' 
AND (channel_id IS NULL OR channel_id = '')
ORDER BY last_message_at DESC;
```


