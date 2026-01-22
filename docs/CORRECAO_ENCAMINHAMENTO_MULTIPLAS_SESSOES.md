# Correção: Encaminhamento para Múltiplas Sessões

## Problema Identificado

A mensagem 101010 foi encaminhada do número 554796474223, mas não foi recebida nas duas sessões conectadas (ImobSites e Pixel12 Digital). 

**Causa Raiz:** O método `send()` em `CommunicationHubController` estava enviando mensagens para apenas **um canal por vez**, usando `LIMIT 1` nas queries e não suportando encaminhamento simultâneo para múltiplas sessões.

## Solução Implementada

### Modificações no `CommunicationHubController::send()`

1. **Novos parâmetros suportados:**
   - `forward_to_all=1`: Encaminha automaticamente para todos os canais habilitados (ImobSites e Pixel12 Digital)
   - `channel_ids[]`: Array com IDs específicos de canais para encaminhamento

2. **Lógica de busca de canais:**
   - Se `forward_to_all=1`: Busca todos os canais habilitados com `channel_id IN ('ImobSites', 'Pixel12 Digital', 'pixel12digital')`
   - Se `channel_ids[]` fornecido: Valida e usa apenas os canais especificados
   - Caso contrário: Mantém comportamento anterior (um único canal)

3. **Envio em lote:**
   - Itera sobre todos os canais alvo
   - Valida status de cada canal antes de enviar
   - Envia mensagem para cada canal independentemente
   - Cria evento de comunicação para cada envio bem-sucedido

4. **Retorno:**
   - **Um canal:** Retorna formato antigo (compatibilidade)
   - **Múltiplos canais:** Retorna resultado agregado com status de cada envio:
     ```json
     {
       "success": true,
       "forwarded": true,
       "total_channels": 2,
       "success_count": 2,
       "failure_count": 0,
       "results": [
         {
           "channel_id": "ImobSites",
           "success": true,
           "event_id": 123,
           "message_id": "msg_abc"
         },
         {
           "channel_id": "Pixel12 Digital",
           "success": true,
           "event_id": 124,
           "message_id": "msg_def"
         }
       ]
     }
     ```

## Como Usar

### Opção 1: Encaminhar para todos os canais habilitados

```javascript
fetch('/api/communication-hub/send', {
  method: 'POST',
  body: new FormData([
    ['channel', 'whatsapp'],
    ['to', '554796474223'],
    ['message', 'Mensagem a ser encaminhada'],
    ['forward_to_all', '1']
  ])
});
```

### Opção 2: Encaminhar para canais específicos

```javascript
const formData = new FormData();
formData.append('channel', 'whatsapp');
formData.append('to', '554796474223');
formData.append('message', 'Mensagem a ser encaminhada');
formData.append('channel_ids[]', 'ImobSites');
formData.append('channel_ids[]', 'Pixel12 Digital');

fetch('/api/communication-hub/send', {
  method: 'POST',
  body: formData
});
```

## Logs Adicionados

O sistema agora registra logs detalhados para rastreamento:
- `[CommunicationHub::send] Modo encaminhamento para todos os canais ativado`
- `[CommunicationHub::send] Canais encontrados para encaminhamento: ImobSites, Pixel12 Digital`
- `[CommunicationHub::send] Processando canal: ImobSites`
- `[CommunicationHub::send] ✅ Sucesso ao enviar para ImobSites`
- `[CommunicationHub::send] ===== RESULTADO FINAL ENCAMINHAMENTO =====`

## Próximos Passos

1. **Atualizar interface do usuário:** Adicionar opção na UI para ativar encaminhamento para múltiplas sessões
2. **Testar:** Verificar se mensagens são recebidas corretamente em ambas as sessões
3. **Monitorar:** Acompanhar logs para identificar possíveis problemas

## Teste Recomendado

Para testar a correção:

1. Envie uma mensagem com `forward_to_all=1` para o número 554796474223
2. Verifique nos logs se ambos os canais foram processados
3. Confirme que a mensagem chegou nas duas sessões (ImobSites e Pixel12 Digital)
4. Verifique no banco de dados se foram criados 2 eventos em `communication_events`

