# 🔧 Solução Final: QR Code imobsites

## Situação Atual

- ✅ **pixel12digital:** Conectada e funcionando (QR code foi escaneado)
- ❌ **imobsites:** Status "Aguardando QR" mas QR code não aparece
- ❌ Botões na UI não funcionam

---

## Solução: Deletar e Recriar Sessão Corretamente

Como "pixel12digital" funciona, vamos deletar "imobsites" e recriar seguindo o mesmo padrão:

### Passo 1: Deletar Sessão Atual

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

echo "=== Deletando sessão imobsites ==="
curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

sleep 2
```

### Passo 2: Verificar Como pixel12digital Foi Criada

```bash
echo "=== Verificando sessão pixel12digital (que funciona) ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/pixel12digital" | jq '.'

echo -e "\n=== Verificando alias de sessão ==="
docker exec gateway-wrapper env | grep SESSION_ID_ALIAS
```

### Passo 3: Recriar Sessão Usando Nome Exato

O problema pode ser case-sensitivity. Vamos tentar diferentes variações:

```bash
# Tentar com nome exato (minúsculas)
echo "=== Criando com 'imobsites' (minúsculas) ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 3

# Verificar se QR code aparece agora
echo -e "\n=== Tentando obter QR code ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/imobsites/qr" | jq '.'

# Verificar logs do WPPConnect
echo -e "\n=== Logs do WPPConnect ==="
docker logs wppconnect-server --tail 20 | grep -i "imobsites\|ImobSites" | tail -10
```

### Passo 4: Se Não Funcionar, Tentar Via UI

1. Acesse: `https://wpp.pixel12digital.com.br:8443/ui/sessoes`
2. Clique em **"Excluir"** na sessão "imobsites"
3. Clique em **"Criar Sessão"** e digite: `imobsites` (minúsculas)
4. Clique em **"Criar Sessão"**
5. Aguarde alguns segundos
6. Clique em **"Ver Detalhes"** na sessão criada
7. Verifique se o QR code aparece

---

## Comando Completo (Copy & Paste)

Execute este comando completo:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

echo "=== 1. Deletando sessão atual ==="
curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

sleep 3

echo -e "\n=== 2. Verificando como pixel12digital está configurada ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/pixel12digital" | jq '.'

echo -e "\n=== 3. Recriando sessão imobsites ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 5

echo -e "\n=== 4. Verificando status ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

echo -e "\n=== 5. Tentando obter QR code ==="
QR_RESPONSE=$(curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr")
echo "$QR_RESPONSE" | jq '.'

# Verificar se há QR code na resposta
QR_CODE=$(echo "$QR_RESPONSE" | jq -r '.qr // .qrcode // .data // empty' 2>/dev/null)
if [ ! -z "$QR_CODE" ] && [ "$QR_CODE" != "null" ] && [ "$QR_CODE" != "" ]; then
    echo -e "\n✅ QR code encontrado na resposta!"
    echo "$QR_CODE" | base64 -d > /tmp/qrcode_imobsites.png 2>/dev/null
    if [ -f "/tmp/qrcode_imobsites.png" ]; then
        echo "✅ QR code salvo em: /tmp/qrcode_imobsites.png"
    fi
else
    echo -e "\n⚠️ QR code não encontrado na resposta"
fi

echo -e "\n=== 6. Verificando logs do WPPConnect ==="
docker logs wppconnect-server --tail 30 | grep -i "imobsites\|ImobSites" | tail -10 || echo "Nenhum log encontrado"
```

---

## Se Ainda Não Funcionar

Se após deletar e recriar o QR code ainda não aparecer:

1. **Verifique se há diferença no nome:** Tente criar com nome diferente (ex: `imobsites2`, `imobsites-test`)
2. **Verifique logs detalhados:** Execute `docker logs gateway-wrapper --tail 100` e procure por erros
3. **Compare com pixel12digital:** Verifique se há alguma configuração especial para pixel12digital

---

**Execute o comando completo acima e me envie a saída.**

