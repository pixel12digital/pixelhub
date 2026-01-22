#!/bin/bash
# Script para executar no servidor do Hub
# Verifica logs relacionados ao teste do webhook

CORRELATION_ID="9858a507-cc4c-4632-8f92-462535eab504"
TEST_TIME="21:35"
CONTAINER_NAME="gateway-hub"  # Ajustar conforme necessário

echo "=== Verificando Logs do Hub no Servidor ==="
echo "correlation_id: $CORRELATION_ID"
echo "horário do teste: ~$TEST_TIME"
echo "container: $CONTAINER_NAME"
echo ""

# Verifica se o container existe
if ! docker ps -a | grep -q "$CONTAINER_NAME"; then
    echo "⚠️  Container '$CONTAINER_NAME' não encontrado."
    echo "Containers disponíveis:"
    docker ps -a --format "table {{.Names}}\t{{.Status}}"
    echo ""
    read -p "Digite o nome do container do Hub: " CONTAINER_NAME
fi

echo "=== Buscando por correlation_id ==="
docker logs --since 21:30 "$CONTAINER_NAME" 2>&1 | grep -i "$CORRELATION_ID" | tail -20

echo ""
echo "=== Buscando HUB_WEBHOOK_IN próximo ao horário do teste ==="
docker logs --since 21:30 "$CONTAINER_NAME" 2>&1 | grep -i "HUB_WEBHOOK_IN.*$TEST_TIME" | tail -10

echo ""
echo "=== Buscando HUB_MSG_SAVE próximo ao horário do teste ==="
docker logs --since 21:30 "$CONTAINER_NAME" 2>&1 | grep -i "HUB_MSG_SAVE.*$TEST_TIME" | tail -10

echo ""
echo "=== Buscando HUB_MSG_DROP próximo ao horário do teste ==="
docker logs --since 21:30 "$CONTAINER_NAME" 2>&1 | grep -i "HUB_MSG_DROP.*$TEST_TIME" | tail -10

echo ""
echo "=== Buscando erros/exceções próximo ao horário do teste ==="
docker logs --since 21:30 "$CONTAINER_NAME" 2>&1 | grep -iE "Exception|Error|Fatal.*$TEST_TIME" | tail -10

echo ""
echo "=== Últimas 30 linhas de log (para contexto) ==="
docker logs --tail 30 "$CONTAINER_NAME" 2>&1

