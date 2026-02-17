#!/bin/bash
echo "=== DIAGNÓSTICO COMPLETO WHATSAPP GATEWAY ==="
echo "Data/Hora: $(date)"
echo ""

echo "1) Status dos containers:"
docker ps -a | grep -E "(gateway|wppconnect)" || echo "Nenhum container encontrado"
echo ""

echo "2) Logs recentes - gateway-wrapper (últimas 2h):"
echo "---"
docker logs gateway-wrapper --since 2h | tail -30 2>/dev/null || echo "Sem logs ou container não existe"
echo ""

echo "3) Logs recentes - wppconnect-server (últimas 2h):"
echo "---"
docker logs wppconnect-server --since 2h | tail -30 2>/dev/null || echo "Sem logs ou container não existe"
echo ""

echo "4) Arquivo de sessões no gateway:"
echo "---"
docker exec gateway-wrapper cat /app/src/data/sessions.json 2>/dev/null || echo "Não foi possível ler sessions.json"
echo ""

echo "5) Sessões no WPPConnect:"
echo "---"
docker exec wppconnect-server ls -la /sessions/ 2>/dev/null || echo "Não foi possível listar sessões"
echo ""

echo "6) Status via API do gateway:"
echo "---"
curl -s -H "X-Gateway-Secret: d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54" \
"https://wpp.pixel12digital.com.br:8443/api/channels" 2>/dev/null | head -20 || echo "Falha na API"
echo ""

echo "7) Erros nos logs (últimas 6h):"
echo "---"
docker logs gateway-wrapper --since 6h 2>/dev/null | grep -i "error\|fail\|exception" | tail -20 || echo "Nenhum erro encontrado"
echo ""

echo "=== FIM DO DIAGNÓSTICO ==="
