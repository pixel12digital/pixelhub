# 🔓 Solução: Chromium Lock - Perfil em Uso

## 🔍 Problema Identificado

O Chromium detectou que o perfil da sessão "ImobSites" está sendo usado por outro processo (PID 2978) e bloqueou o acesso para evitar corrupção.

**Erro:**
```
The profile appears to be in use by another Chromium process (2978)
Chromium has locked the profile so that it doesn't get corrupted
```

---

## 🛠️ Soluções

### Solução 1: Matar Processo Chromium Travado (RECOMENDADO)

```bash
# Verificar processos Chromium no container
docker exec wppconnect-server ps aux | grep -i chromium | grep -v grep

# Matar processo travado (PID 2978)
docker exec wppconnect-server kill -9 2978 2>/dev/null || echo "Processo não encontrado ou já foi encerrado"

# Verificar se ainda há processos Chromium
docker exec wppconnect-server ps aux | grep -i chromium | grep -v grep

# Limpar locks do perfil
docker exec wppconnect-server find ./userDataDir/ImobSites -name "*.lock" -delete 2>/dev/null
docker exec wppconnect-server find ./userDataDir/ImobSites -name "SingletonLock" -delete 2>/dev/null

# Tentar criar sessão novamente
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'
```

### Solução 2: Deletar Perfil e Recriar

Se a Solução 1 não funcionar, deletar o perfil completo:

```bash
# ⚠️ ATENÇÃO: Isso vai deletar o perfil da sessão ImobSites
# A sessão precisará ser recriada do zero

# Deletar perfil
docker exec wppconnect-server rm -rf ./userDataDir/ImobSites 2>/dev/null
docker exec wppconnect-server rm -rf ./sessions/ImobSites 2>/dev/null

# Deletar sessão no gateway-wrapper
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

sleep 2

# Recriar sessão
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'
```

### Solução 3: Reiniciar Container WPPConnect

Reiniciar o container vai matar todos os processos travados:

```bash
# ⚠️ ATENÇÃO: Isso vai desconectar TODAS as sessões temporariamente

# Reiniciar WPPConnect
docker restart wppconnect-server

# Aguardar inicialização
sleep 15

# Verificar se reiniciou
docker ps | grep wppconnect-server

# Tentar criar sessão novamente
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 5

# Verificar logs
docker logs wppconnect-server --tail 30 | grep -i "ImobSites\|browser\|error"
```

---

## 🎯 Comando Completo (Solução 1 - Recomendada)

Execute este comando completo:

```bash
echo "=== 1. Verificar processos Chromium ==="
docker exec wppconnect-server ps aux | grep -i chromium | grep -v grep

echo -e "\n=== 2. Matar processo travado (PID 2978) ==="
docker exec wppconnect-server kill -9 2978 2>/dev/null || echo "Processo não encontrado"

echo -e "\n=== 3. Limpar locks do perfil ==="
docker exec wppconnect-server find ./userDataDir/ImobSites -name "*.lock" -delete 2>/dev/null
docker exec wppconnect-server find ./userDataDir/ImobSites -name "SingletonLock" -delete 2>/dev/null
docker exec wppconnect-server find ./userDataDir/ImobSites -name "lockfile" -delete 2>/dev/null

echo -e "\n=== 4. Verificar se locks foram removidos ==="
docker exec wppconnect-server find ./userDataDir/ImobSites -name "*lock*" 2>/dev/null | head -10 || echo "Nenhum lock encontrado"

echo -e "\n=== 5. Deletar sessão no gateway-wrapper ==="
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

sleep 3

echo -e "\n=== 6. Recriar sessão ==="
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 5

echo -e "\n=== 7. Verificar logs do WPPConnect ==="
docker logs wppconnect-server --tail 30 | grep -i "ImobSites\|browser\|error" | tail -15

echo -e "\n=== 8. Tentar obter QR code ==="
curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr" | jq '.'
```

---

## 📋 Explicação do Problema

**O que aconteceu:**
1. Uma tentativa anterior de criar a sessão "ImobSites" iniciou um processo Chromium
2. O processo não foi encerrado corretamente (travou ou foi interrompido)
3. O Chromium bloqueou o perfil para evitar corrupção
4. Novas tentativas falham porque o perfil está bloqueado

**Por que "pixel12digital" funciona:**
- A sessão "pixel12digital" já estava conectada antes
- Não há processo travado usando seu perfil
- O perfil não está bloqueado

---

**Execute o comando completo acima e me envie a saída. Isso deve resolver o problema!**

