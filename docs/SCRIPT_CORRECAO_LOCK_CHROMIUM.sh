#!/bin/bash

# Script de Correção: Lock do Chromium - Perfil ImobSites
# Prioriza correção sem mexer em código, só saneando processo/profile

echo "=== (1) Ver processos Chromium no container ==="
docker exec wppconnect-server sh -lc '
ps -eo pid,ppid,cmd --sort=pid | egrep -i "chromium|chrome" | egrep -v "egrep|grep" || true
'

echo
echo "=== (2) Ver se existe a pasta do profile e locks comuns ==="
docker exec wppconnect-server sh -lc '
set -e
BASE="./userDataDir/ImobSites"
echo "Profile dir: $BASE"
ls -lah "$BASE" || true
echo
echo "Locks (Singleton*/Lockfile):"
find "$BASE" -maxdepth 2 -type f \( -name "Singleton*" -o -name "lockfile" -o -name "*.lock" \) -print -exec ls -lah {} \; 2>/dev/null || true
'

echo
echo "=== (3) Ver quem está usando esse diretório (se lsof existir) ==="
docker exec wppconnect-server sh -lc '
BASE="./userDataDir/ImobSites"
command -v lsof >/dev/null 2>&1 && lsof +D "$BASE" 2>/dev/null | head -n 50 || echo "lsof não disponível no container"
'

echo
echo "=== (4) Se houver Chromium rodando, checar se algum usa ImobSites no cmdline ==="
docker exec wppconnect-server sh -lc '
ps -eo pid,cmd | egrep -i "chromium|chrome" | egrep -i "ImobSites|userDataDir" || true
'

echo
echo "=== (5) Remover locks do profile ImobSites (somente locks) ==="
docker exec wppconnect-server sh -lc '
BASE="./userDataDir/ImobSites"
echo "Removendo locks em: $BASE"
rm -f "$BASE"/SingletonLock "$BASE"/SingletonSocket "$BASE"/SingletonCookie "$BASE"/lockfile "$BASE"/*.lock 2>/dev/null || true
echo "OK"
'

echo
echo "=== (6) Reiniciar somente o wppconnect-server (para limpar estado) ==="
docker restart wppconnect-server

echo
echo "=== Aguardando inicialização (15 segundos) ==="
sleep 15

echo
echo "=== (7) Verificar se container reiniciou ==="
docker ps | grep wppconnect-server

echo
echo "=== (8) Deletar e recriar sessão imobsites ==="
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

echo "Deletando sessão..."
curl -s -X DELETE -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION" | jq '.'

sleep 3

echo -e "\nRecriando sessão..."
curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 5

echo -e "\n=== (9) Verificar logs do WPPConnect ==="
docker logs wppconnect-server --tail 30 | grep -i "ImobSites\|browser\|error" | tail -15

echo -e "\n=== (10) Tentar obter QR code ==="
QR_RESPONSE=$(curl -s -X GET -H "X-Gateway-Secret: $SECRET" \
  "http://$CONTAINER_IP:3000/api/channels/$SESSION/qr")
echo "$QR_RESPONSE" | jq '.'

echo -e "\n=== (11) Verificar se QR code foi gerado ==="
QR_CODE=$(echo "$QR_RESPONSE" | jq -r '.qr // .qrcode // .data // empty' 2>/dev/null)
if [ ! -z "$QR_CODE" ] && [ "$QR_CODE" != "null" ] && [ "$QR_CODE" != "" ]; then
    echo "✅ QR code encontrado na resposta!"
    echo "$QR_CODE" | base64 -d > /tmp/qrcode_imobsites.png 2>/dev/null
    if [ -f "/tmp/qrcode_imobsites.png" ]; then
        echo "✅ QR code salvo em: /tmp/qrcode_imobsites.png"
        ls -lh /tmp/qrcode_imobsites.png
    fi
else
    echo "⚠️ QR code ainda não está na resposta"
    echo "Verifique a UI: https://wpp.pixel12digital.com.br:8443/ui/sessoes/imobsites"
fi

echo -e "\n=== Script concluído ==="
echo "Se o QR code não aparecer, execute: docker logs -f --tail 200 wppconnect-server"
echo "E tente gerar QR code novamente pela UI"

