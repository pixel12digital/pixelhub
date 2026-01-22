# Resumo Curto - Diagnóstico Gateway (para ChatGPT)

## O que fizemos

Executamos comandos no VPS para verificar logs do `gateway-wrapper` filtrados por `pixel12digital`:
```bash
docker logs gateway-wrapper --since 1h | grep -iE "(pixel12|Pixel12)" | grep -iE "(session|webhook|config)" | tail -30
docker logs gateway-wrapper --since 10m | grep -i "onmessage" | grep -iE "(ImobSites|pixel12digital)" | tail -30
docker logs gateway-wrapper --since 2h | grep -iE "(Session.*created|webhook.*configured)" | grep -iE "(ImobSites|pixel12digital)" | tail -30
```

## O que encontramos

**Funcionando:**
- Webhook do painel recebendo eventos (status 200 OK)
- Sessão `pixel12digital` criada no gateway-wrapper
- Eventos `onpresencechanged` sendo recebidos e convertidos para `connection.update`

**Não funcionando:**
- Nenhum evento `onMessage` / `onmessage` encontrado nos logs para `pixel12digital`
- WPPConnect não está emitindo eventos `onMessage` para essa sessão

## Problema identificado

O WPPConnect registra o listener `onMessage` para `pixel12digital` e está autenticado, mas **não está emitindo eventos `onMessage`** quando mensagens chegam.

O gateway-wrapper recebe apenas `onpresencechanged` e `onack`, mas não recebe `onMessage` do WPPConnect.

## Próximo passo sugerido

Comparar logs entre `ImobSites` (funciona) e `pixel12digital` (não funciona) para verificar se `ImobSites` recebe `onMessage` enquanto `pixel12digital` não recebe:

```bash
docker logs gateway-wrapper --since 10m | grep -i "onmessage" | grep -iE "(ImobSites|pixel12digital)"
```

Se `ImobSites` aparecer nos resultados e `pixel12digital` não, confirma que o problema está na configuração/emissão de eventos do WPPConnect para `pixel12digital`, não no gateway-wrapper ou webhook.

