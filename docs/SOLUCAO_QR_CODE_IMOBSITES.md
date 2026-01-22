# 🔧 Solução: QR Code não está sendo gerado - Diagnóstico

## Problema Identificado

✅ Gateway-wrapper está criando a sessão "ImobSites"  
❌ **WPPConnect não está recebendo/processando a requisição**  
⚠️ Sessão fica em "initializing" e nunca gera QR code

---

## Comandos para Diagnosticar Comunicação

### 1. Verificar se WPPConnect está respondendo

```bash
# Verificar se o WPPConnect está rodando e respondendo
docker ps | grep wppconnect

# Verificar logs gerais do WPPConnect (últimas 50 linhas)
docker logs wppconnect-server --tail 50

# Verificar se há erros
docker logs wppconnect-server --tail 100 | grep -i "error\|fail\|exception" | tail -20
```

### 2. Verificar Comunicação entre Containers

```bash
# Verificar rede Docker
docker network ls

# Verificar se os containers estão na mesma rede
docker inspect gateway-wrapper | grep -A 10 "Networks"
docker inspect wppconnect-server | grep -A 10 "Networks"

# Testar conectividade entre containers
docker exec gateway-wrapper ping -c 2 wppconnect-server 2>/dev/null || echo "ping não disponível, tentando outra forma"
```

### 3. Verificar Configuração do Gateway-Wrapper

```bash
# Verificar variáveis de ambiente do gateway-wrapper
docker exec gateway-wrapper env | grep -i "wpp\|connect\|session" | sort

# Verificar logs completos do gateway-wrapper
docker logs gateway-wrapper --tail 100 | tail -30
```

### 4. Verificar Sessões Existentes no WPPConnect

```bash
# Verificar diretório de sessões
docker exec wppconnect-server ls -la /sessions/ 2>/dev/null || echo "Diretório /sessions/ não existe"

# Verificar se há outras sessões
docker exec wppconnect-server find / -name "*session*" -type d 2>/dev/null | head -10

# Verificar logs do WPPConnect para ver quais sessões existem
docker logs wppconnect-server --tail 200 | grep -i "session\|qr" | tail -30
```

---

## Comando Completo de Diagnóstico

Execute este comando:

```bash
echo "=== 1. Containers rodando ==="
docker ps | grep -E "wppconnect|gateway"

echo -e "\n=== 2. Logs WPPConnect (últimas 50 linhas) ==="
docker logs wppconnect-server --tail 50

echo -e "\n=== 3. Erros no WPPConnect ==="
docker logs wppconnect-server --tail 100 | grep -i "error\|fail\|exception" | tail -15

echo -e "\n=== 4. Logs Gateway-Wrapper (últimas 30 linhas) ==="
docker logs gateway-wrapper --tail 30

echo -e "\n=== 5. Verificar rede dos containers ==="
echo "Gateway-wrapper:"
docker inspect gateway-wrapper | grep -A 5 "Networks" | head -10
echo "WPPConnect:"
docker inspect wppconnect-server | grep -A 5 "Networks" | head -10

echo -e "\n=== 6. Variáveis de ambiente do gateway-wrapper ==="
docker exec gateway-wrapper env | grep -i "wpp\|connect\|session" | sort
```

---

## Possível Solução: Reiniciar Sessão Corretamente

Se a comunicação estiver OK mas a sessão não está sendo criada no WPPConnect:

```bash
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

# 1. Deletar sessão completamente
echo "=== Deletando sessão ==="
curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

# Aguardar
sleep 3

# 2. Verificar se foi deletada
echo -e "\n=== Verificando se foi deletada ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

# 3. Recriar sessão
echo -e "\n=== Recriando sessão ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"channel\": \"$SESSION\"}" \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

# Aguardar inicialização
sleep 5

# 4. Verificar status
echo -e "\n=== Status após recriação ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

# 5. Tentar obter QR code
echo -e "\n=== Tentando obter QR code ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'

# 6. Verificar logs em tempo real
echo -e "\n=== Verificando logs do WPPConnect (últimas 20 linhas) ==="
docker logs wppconnect-server --tail 20 | grep -i "ImobSites\|imobsites" || echo "Nenhum log encontrado para ImobSites"
```

---

## Solução Alternativa: Reiniciar Containers

Se nada funcionar, pode ser necessário reiniciar os containers:

```bash
# ⚠️ ATENÇÃO: Isso vai desconectar TODAS as sessões
echo "Reiniciando containers..."

# Reiniciar gateway-wrapper
docker restart gateway-wrapper
sleep 5

# Reiniciar wppconnect-server
docker restart wppconnect-server
sleep 10

# Verificar se reiniciaram
docker ps | grep -E "wppconnect|gateway"

# Aguardar estabilização
sleep 5

# Tentar criar sessão novamente
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"channel\": \"$SESSION\"}" \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 5

curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'
```

---

**Execute primeiro o comando completo de diagnóstico e me envie a saída completa.**

