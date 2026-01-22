# 🔍 Diagnóstico: QR Code não está sendo gerado para imobsites

## Problema Identificado

✅ Endpoints respondem com sucesso  
❌ Mas não retornam o QR code na resposta  
⚠️ A sessão existe mas está em status "initializing" ou "qr_required"

---

## Comandos de Diagnóstico

### 1. Verificar Logs Completos do WPPConnect

```bash
# Ver logs recentes do WPPConnect focando em ImobSites
docker logs wppconnect-server --tail 100 | grep -i "ImobSites" | tail -30

# Ver todos os logs recentes (pode conter QR code em base64)
docker logs wppconnect-server --tail 100 | tail -50
```

### 2. Verificar Status da Sessão no WPPConnect

```bash
# Verificar se a sessão está registrada no WPPConnect
docker exec wppconnect-server ls -la /sessions/ 2>/dev/null || echo "Diretório não encontrado"

# Verificar estrutura de sessões
docker exec wppconnect-server find /sessions -name "*ImobSites*" -o -name "*imobsites*" 2>/dev/null
```

### 3. Verificar Comunicação entre Gateway e WPPConnect

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

# Verificar se o gateway consegue se comunicar com WPPConnect
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/health" | jq '.'

# Verificar logs do gateway-wrapper em tempo real
docker logs gateway-wrapper --tail 50 | grep -i "ImobSites\|qr\|wppconnect" | tail -20
```

### 4. Tentar Forçar Geração de QR Code

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

# 1. Deletar sessão existente
echo "=== Deletando sessão ==="
curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

sleep 2

# 2. Recriar sessão
echo -e "\n=== Recriando sessão ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"channel\": \"$SESSION\"}" \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 3

# 3. Tentar obter QR code
echo -e "\n=== Tentando obter QR code ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'

# 4. Tentar endpoint da UI
echo -e "\n=== Tentando endpoint da UI ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/ui/sessoes/$SESSION/qr-json" | jq '.'
```

### 5. Verificar Variáveis de Ambiente do Gateway

```bash
# Verificar configurações do gateway-wrapper
docker exec gateway-wrapper env | grep -i "wpp\|session\|qr" | sort

# Verificar configurações do wppconnect-server
docker exec wppconnect-server env | grep -i "session\|qr" | sort
```

---

## Comando Completo de Diagnóstico

Execute este comando completo:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

echo "=== 1. Status atual da sessão ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

echo -e "\n=== 2. Logs do gateway-wrapper (últimas 30 linhas) ==="
docker logs gateway-wrapper --tail 30 | grep -i "ImobSites\|imobsites\|qr" | tail -15

echo -e "\n=== 3. Logs do wppconnect-server (últimas 50 linhas) ==="
docker logs wppconnect-server --tail 50 | grep -i "ImobSites\|imobsites" | tail -20

echo -e "\n=== 4. Verificando sessões no WPPConnect ==="
docker exec wppconnect-server ls -la /sessions/ 2>/dev/null | grep -i "imob" || echo "Nenhuma sessão encontrada"

echo -e "\n=== 5. Health check do gateway ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/health" | jq '.'
```

---

## Possíveis Causas

1. **WPPConnect não está gerando QR code** - Verificar logs do wppconnect-server
2. **Gateway não está retornando QR code** - Verificar logs do gateway-wrapper
3. **Sessão em estado incorreto** - Pode precisar ser reiniciada
4. **Problema de comunicação** - Gateway não consegue se comunicar com WPPConnect

---

## Solução Alternativa: Reiniciar Container do WPPConnect

Se nada funcionar, pode ser necessário reiniciar o container:

```bash
# ⚠️ ATENÇÃO: Isso vai desconectar TODAS as sessões
echo "Reiniciando wppconnect-server..."
docker restart wppconnect-server

# Aguardar inicialização
sleep 10

# Verificar se reiniciou
docker ps | grep wppconnect-server
```

---

**Execute o comando completo de diagnóstico e me envie a saída completa.**

