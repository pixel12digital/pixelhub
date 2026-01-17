<?php
/**
 * DIAGNÓSTICO PARA VPS DO GATEWAY
 * 
 * Script para executar na VPS onde está o gateway WPPConnect
 * Verifica se gateway está enviando webhooks para eventos 'message'
 */

echo "=== DIAGNÓSTICO VPS GATEWAY ===\n\n";
echo "Este script deve ser executado na VPS onde está o WPPConnect Gateway\n\n";

echo "VERIFICAÇÕES NECESSÁRIAS NA VPS:\n\n";

echo "1. VERIFICAR LOGS DO GATEWAY:\n";
echo "   - Localização típica: /var/log/wppconnect/* ou ~/wppconnect/logs/*\n";
echo "   - Buscar por: 'webhook', 'message', 'event'\n";
echo "   - Verificar se eventos 'message' estão sendo gerados\n\n";

echo "2. VERIFICAR CONFIGURAÇÃO DO WEBHOOK:\n";
echo "   - Verificar URL do webhook configurada\n";
echo "   - Verificar se eventos 'message' estão habilitados\n";
echo "   - Verificar se webhook está sendo enviado\n\n";

echo "3. VERIFICAR STATUS DA SESSÃO:\n";
echo "   - Verificar se sessão 'pixel12digital' está conectada\n";
echo "   - Verificar se sessão está autenticada\n";
echo "   - Verificar se sessão está recebendo mensagens\n\n";

echo "4. TESTAR ENVIO DE WEBHOOK MANUALMENTE:\n";
echo "   - Usar curl para enviar payload de teste\n";
echo "   - Verificar resposta do webhook\n";
echo "   - Verificar logs de erro\n\n";

echo "COMANDOS ÚTEIS:\n\n";

echo "# 1. Verificar processos do gateway\n";
echo "ps aux | grep -i wppconnect\n";
echo "ps aux | grep -i whatsapp\n";
echo "ps aux | grep -i node\n\n";

echo "# 2. Verificar portas abertas\n";
echo "netstat -tlnp | grep -E '(3000|4000|5000|8080|3001)'\n";
echo "ss -tlnp | grep -E '(3000|4000|5000|8080|3001)'\n\n";

echo "# 3. Verificar logs mais recentes\n";
echo "tail -f /var/log/wppconnect/*.log | grep -i message\n";
echo "tail -f /var/log/wppconnect/*.log | grep -i webhook\n";
echo "journalctl -u wppconnect -f | grep -i message\n\n";

echo "# 4. Verificar se gateway está ouvindo na porta\n";
echo "curl -v http://localhost:3000/health  # Ajustar porta se necessário\n";
echo "curl -v http://localhost:3000/api/sessions  # Listar sessões\n\n";

echo "# 5. Verificar configuração (se arquivo de config existir)\n";
echo "find / -name '*config*.json' -path '*/wppconnect/*' 2>/dev/null\n";
echo "find / -name '*.env' -path '*/wppconnect/*' 2>/dev/null\n\n";

echo "# 6. Testar webhook manualmente (se URL conhecida)\n";
echo "curl -X POST http://[DOMINIO]/api/whatsapp/webhook \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"event\":\"message\",\"session\":{\"id\":\"pixel12digital\"},\"message\":{\"text\":\"test\"}}'\n\n";

echo "INFORMAÇÕES PARA COLETAR:\n\n";

echo "1. Status da sessão 'pixel12digital':\n";
echo "   - Conectada? Autenticada? Recebendo mensagens?\n\n";

echo "2. Logs de webhook:\n";
echo "   - Gateway está tentando enviar webhooks?\n";
echo "   - Há erros ao enviar webhooks?\n";
echo "   - Webhooks estão sendo enviados mas falhando?\n\n";

echo "3. Configuração do webhook:\n";
echo "   - URL configurada: https://[DOMINIO]/api/whatsapp/webhook?\n";
echo "   - Eventos 'message' habilitados?\n";
echo "   - Webhook secret configurado?\n\n";

echo "4. Eventos recentes:\n";
echo "   - Gateway gerou eventos 'message' hoje?\n";
echo "   - Webhooks foram enviados?\n";
echo "   - Resposta do webhook (200/400/500)?\n\n";

echo "\n";

