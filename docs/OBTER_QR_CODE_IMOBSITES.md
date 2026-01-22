# 🔍 Obter QR Code da Sessão imobsites

## Status Atual

✅ **Sessão existe:** `imobsites`  
✅ **Status:** `qr_required` (precisa de QR code)  
⚠️ **Problema:** O endpoint `/qr` retorna sucesso mas não retorna o QR code na resposta

---

## Soluções

### Solução 1: Verificar Logs do Gateway

O QR code pode estar sendo gerado mas não retornado na resposta. Verifique os logs:

```bash
# Ver logs recentes do gateway-wrapper
docker logs gateway-wrapper --tail 50 | grep -i "imobsites\|qr"

# Ver logs do wppconnect-server
docker logs wppconnect-server --tail 50 | grep -i "imobsites\|qr"
```

### Solução 2: Verificar Status da Sessão (pode conter QR code)

Alguns gateways retornam o QR code no status da sessão:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

# Verificar status completo
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'
```

### Solução 3: Tentar Endpoint Alternativo

Alguns gateways usam endpoints diferentes:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

# Tentar diferentes endpoints
echo "=== Tentativa 1: /qr ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'

echo -e "\n=== Tentativa 2: /qrcode ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qrcode" | jq '.'

echo -e "\n=== Tentativa 3: /connect ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{}' \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/connect" | jq '.'
```

### Solução 4: Usar a UI Web Diretamente

A forma mais fácil pode ser usar a interface web:

1. **Acesse:** `https://wpp.pixel12digital.com.br:8443/ui/sessoes/imobsites`
2. **Faça login** com o usuário do htpasswd:
   ```bash
   # Ver usuário
   cat /etc/nginx/.htpasswd_wpp.pixel12digital.com.br | cut -d: -f1
   ```
3. **Na UI**, procure por:
   - Botão "Atualizar QR"
   - Botão "Reconectar"
   - Seção "QR Code para Conectar"

### Solução 5: Verificar se QR Code está em Base64 no WPPConnect

O WPPConnect pode estar gerando o QR code mas não expondo via API do gateway-wrapper. Verifique diretamente:

```bash
# Ver logs do WPPConnect para ver se QR code foi gerado
docker logs wppconnect-server --tail 100 | grep -A 5 -B 5 "imobsites" | grep -i "qr\|base64"
```

### Solução 6: Reiniciar a Sessão (Forçar Nova Geração de QR)

Se nada funcionar, pode ser necessário reiniciar a sessão:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

# Desconectar sessão
curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

# Aguardar alguns segundos
sleep 3

# Recriar sessão
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"channel\": \"$SESSION\"}" \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

# Aguardar inicialização
sleep 2

# Tentar obter QR code novamente
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'
```

---

## Comando de Diagnóstico Completo

Execute este comando para diagnóstico completo:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

echo "=== 1. Status da sessão ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

echo -e "\n=== 2. Tentando obter QR code ==="
QR_RESPONSE=$(curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr")
echo "$QR_RESPONSE" | jq '.'

echo -e "\n=== 3. Verificando todos os campos da resposta ==="
echo "$QR_RESPONSE" | jq 'keys'

echo -e "\n=== 4. Logs do gateway-wrapper (últimas 20 linhas) ==="
docker logs gateway-wrapper --tail 20 2>&1 | grep -i "imobsites\|qr" || echo "Nenhum log encontrado"

echo -e "\n=== 5. Logs do wppconnect-server (últimas 20 linhas) ==="
docker logs wppconnect-server --tail 20 2>&1 | grep -i "imobsites\|qr" || echo "Nenhum log encontrado"
```

---

## Recomendação

**A forma mais fácil e confiável é usar a UI web:**

1. Acesse: `https://wpp.pixel12digital.com.br:8443/ui/sessoes/imobsites`
2. Faça login
3. O QR code deve aparecer automaticamente na interface

Se não aparecer na UI, execute o comando de diagnóstico completo acima e me envie a saída.

---

**Última atualização:** Janeiro 2026

