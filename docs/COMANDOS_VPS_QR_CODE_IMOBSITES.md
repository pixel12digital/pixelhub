# 🚀 Comandos para VPS - Gerar QR Code imobsites

## Problema: Container não tem curl

O container `gateway-wrapper` não tem `curl` instalado. Vamos acessar via IP do container.

---

## Solução: Acessar via IP do Container

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"

# Obter IP do container
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)
echo "IP do container: $CONTAINER_IP"

# Acessar API diretamente via IP
echo "=== Status da sessão ==="
curl -s -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

echo -e "\n=== Gerando QR Code ==="
curl -s -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'
```

---

## Comando Completo (Copy & Paste)

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"

# Obter IP do container
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)
echo "IP do container gateway-wrapper: $CONTAINER_IP"
echo ""

echo "=== 1. Status da sessão $SESSION ==="
STATUS_RESPONSE=$(curl -s -X GET -H "X-Gateway-Secret: $SECRET" "http://$CONTAINER_IP:3000/api/channels/$SESSION")
echo "$STATUS_RESPONSE" | jq '.' 2>/dev/null || echo "$STATUS_RESPONSE"

echo ""
echo "=== 2. Gerando QR Code ==="
QR_RESPONSE=$(curl -s -X GET -H "X-Gateway-Secret: $SECRET" "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr")
echo "$QR_RESPONSE" | jq '.' 2>/dev/null || echo "$QR_RESPONSE"

# Tentar salvar QR code se vier em base64
QR_CODE=$(echo "$QR_RESPONSE" | jq -r '.qr // .qrcode // .data // empty' 2>/dev/null)
if [ ! -z "$QR_CODE" ] && [ "$QR_CODE" != "null" ] && [ "$QR_CODE" != "" ]; then
    echo ""
    echo "=== 3. Salvando QR code ==="
    echo "$QR_CODE" | base64 -d > /tmp/qrcode_imobsites.png 2>/dev/null
    if [ -f "/tmp/qrcode_imobsites.png" ]; then
        echo "✅ QR code salvo em: /tmp/qrcode_imobsites.png"
        echo "Para baixar: scp root@212.85.11.238:/tmp/qrcode_imobsites.png ./"
        ls -lh /tmp/qrcode_imobsites.png
    else
        echo "⚠️ Não foi possível salvar como imagem."
        echo "QR code (primeiros 100 chars): ${QR_CODE:0:100}..."
    fi
fi
```

---

## Listar Todas as Sessões

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"

CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)
echo "IP do container: $CONTAINER_IP"
echo ""
echo "=== Listando TODAS as sessões ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" "http://$CONTAINER_IP:3000/api/channels" | jq '.'
```

---

## Verificar Health do Gateway

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"

CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)
echo "=== Health check ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" "http://$CONTAINER_IP:3000/health" | jq '.'
```

---

## Alternativa: Usar wget (se disponível no container)

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"

# Tentar com wget
docker exec gateway-wrapper wget -qO- \
  --header="X-Gateway-Secret: $SECRET" \
  "http://localhost:3000/api/channels/$SESSION" | jq '.'
```

---

## Verificar Porta Exposta

O container pode estar expondo a porta 3000 na interface `172.19.0.1`:

```bash
# Verificar porta exposta
netstat -tlnp | grep 3000

# Tentar acessar via 172.19.0.1:3000
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"

curl -s -X GET -H "X-Gateway-Secret: $SECRET" "http://172.19.0.1:3000/api/channels/$SESSION" | jq '.'
curl -s -X GET -H "X-Gateway-Secret: $SECRET" "http://172.19.0.1:3000/api/channels/$SESSION/qr" | jq '.'
```

---

## Troubleshooting

### Se não conseguir conectar via IP do container

```bash
# Verificar rede do container
docker inspect gateway-wrapper | grep -A 20 "Networks"

# Verificar se a porta 3000 está acessível
docker port gateway-wrapper

# Verificar logs do gateway
docker logs gateway-wrapper --tail 30
```

### Se der erro de conexão

```bash
# Verificar se o container está rodando
docker ps | grep gateway-wrapper

# Verificar se a porta está aberta
docker exec gateway-wrapper netstat -tlnp 2>/dev/null || echo "netstat não disponível no container"
```

---

**Última atualização:** Janeiro 2026
