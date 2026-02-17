# Gateway Sessions Sync - Documentação

## Problema Identificado

O **gateway-wrapper** não registra automaticamente sessões criadas via WPPConnect no arquivo `sessions.json`. Isso causa:

- Sessões criadas via Pixel Hub não aparecem na lista
- API `/api/channels` retorna apenas sessões registradas manualmente
- Usuário precisa adicionar sessões manualmente ao `sessions.json`

---

## Solução Temporária (Manual)

### Quando criar uma nova sessão via Pixel Hub ou WPPConnect:

**1. Verificar se sessão foi criada no WPPConnect:**

```bash
docker logs wppconnect-server --tail 100 | grep -i "nome-da-sessao"
```

**2. Adicionar sessão ao sessions.json do gateway-wrapper:**

```bash
# Exemplo para adicionar sessão "ImobSites"
docker exec gateway-wrapper sh -c 'cat /app/src/data/sessions.json | python3 -c "
import sys, json
from datetime import datetime
sessions = json.load(sys.stdin)
new_session = {
    \"session_id\": \"ImobSites\",
    \"created_at\": datetime.utcnow().isoformat() + \"Z\",
    \"updated_at\": datetime.utcnow().isoformat() + \"Z\",
    \"status\": \"disconnected\"
}
# Verifica se já existe
if not any(s[\"session_id\"] == \"ImobSites\" for s in sessions):
    sessions.append(new_session)
print(json.dumps(sessions, indent=2))
" > /tmp/sessions_new.json && mv /tmp/sessions_new.json /app/src/data/sessions.json'
```

**3. Reiniciar gateway-wrapper:**

```bash
docker restart gateway-wrapper
```

**4. Verificar se sessão aparece na API:**

```bash
curl -s -H "X-Gateway-Secret: SEU_SECRET_AQUI" "https://wpp.pixel12digital.com.br:8443/api/channels" | python3 -m json.tool
```

---

## Solução Permanente (Recomendada)

### Opção 1: Modificar gateway-wrapper

Adicionar sincronização automática entre WPPConnect e `sessions.json`:

1. Monitorar eventos de criação de sessão do WPPConnect
2. Auto-registrar no `sessions.json`
3. Recarregar lista de sessões

### Opção 2: Endpoint no Pixel Hub

Criar endpoint `/settings/whatsapp-gateway/sessions/register` que:

1. Recebe `channel_id` após criação bem-sucedida
2. Chama script na VPS para adicionar ao `sessions.json`
3. Reinicia gateway-wrapper automaticamente

---

## Comando Consolidado para Adicionar Sessão

```bash
#!/bin/bash
# Uso: ./add_session.sh NOME_DA_SESSAO

SESSION_ID="$1"

if [ -z "$SESSION_ID" ]; then
    echo "Uso: $0 NOME_DA_SESSAO"
    exit 1
fi

echo "Adicionando sessão: $SESSION_ID"

docker exec gateway-wrapper sh -c "cat /app/src/data/sessions.json | python3 -c \"
import sys, json
from datetime import datetime
sessions = json.load(sys.stdin)
new_session = {
    'session_id': '$SESSION_ID',
    'created_at': datetime.utcnow().isoformat() + 'Z',
    'updated_at': datetime.utcnow().isoformat() + 'Z',
    'status': 'disconnected'
}
if not any(s['session_id'] == '$SESSION_ID' for s in sessions):
    sessions.append(new_session)
    print('Sessão adicionada')
else:
    print('Sessão já existe')
print(json.dumps(sessions, indent=2))
\" > /tmp/sessions_new.json && mv /tmp/sessions_new.json /app/src/data/sessions.json"

echo "Reiniciando gateway-wrapper..."
docker restart gateway-wrapper

sleep 3

echo "Verificando..."
curl -s -H "X-Gateway-Secret: d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54" \
"https://wpp.pixel12digital.com.br:8443/api/channels" | python3 -m json.tool

echo "Concluído!"
```

---

## Bugs Relacionados

### Bug 1: Botão "Desconectar" deleta sessão

**Status:** ✅ CORRIGIDO

- Renomeado para "Excluir sessão"
- Aviso claro de exclusão permanente
- Arquivo: `views/settings/whatsapp_gateway.php`

### Bug 2: Polling não detectava sessões

**Status:** ✅ CORRIGIDO

- Logs detalhados adicionados
- Problema era backend retornando apenas 1 sessão
- Causa raiz: gateway-wrapper não registrava sessões

---

## Manutenção

### Verificar sessões registradas:

```bash
docker exec gateway-wrapper cat /app/src/data/sessions.json | python3 -m json.tool
```

### Verificar sessões no WPPConnect:

```bash
docker exec wppconnect-server ls -la /usr/src/wpp-server/userDataDir/
```

### Sincronizar manualmente:

```bash
# Listar sessões do WPPConnect
SESSIONS=$(docker exec wppconnect-server ls /usr/src/wpp-server/userDataDir/)

# Para cada sessão, adicionar ao sessions.json se não existir
for SESSION in $SESSIONS; do
    echo "Verificando: $SESSION"
    # Adicionar lógica de sincronização aqui
done
```

---

## Notas Importantes

1. **Case sensitivity:** Linux diferencia maiúsculas/minúsculas
   - `ImobSites` ≠ `imobsites`
   - Sempre usar o mesmo case em todos os lugares

2. **Persistência:** `sessions.json` é a fonte da verdade para gateway-wrapper
   - WPPConnect pode ter sessões que gateway-wrapper não expõe
   - Sempre sincronizar após criar sessão

3. **Backup:** Fazer backup de `sessions.json` antes de modificar
   ```bash
   docker exec gateway-wrapper cp /app/src/data/sessions.json /app/src/data/sessions.json.bak
   ```

---

**Última atualização:** 2026-02-17
**Autor:** Diagnóstico automático - Cascade AI
