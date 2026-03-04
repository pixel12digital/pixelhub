<?php
/**
 * Script para verificar logs do servidor via SSH
 */

echo "=== VERIFICAR LOGS DO SERVIDOR ===\n\n";

$server = 'pixel12digital@hub.pixel12digital.com.br';
$logPath = '~/hub.pixel12digital.com.br/error_log';

echo "Executando comando no servidor...\n";
echo "Comando: tail -50 {$logPath} | grep -i 'meta\\|webhook'\n\n";

$command = "ssh {$server} \"tail -100 {$logPath} | grep -iE 'meta|webhook|GET.*api/whatsapp|POST.*api/whatsapp'\"";

echo "Logs recentes relacionados a Meta/Webhook:\n";
echo str_repeat('-', 80) . "\n";

$output = shell_exec($command);

if (empty($output)) {
    echo "❌ Nenhum log encontrado ou erro ao conectar SSH\n\n";
    echo "Tente executar manualmente no servidor:\n";
    echo "tail -100 ~/hub.pixel12digital.com.br/error_log | grep -i meta\n";
} else {
    echo $output;
}

echo "\n" . str_repeat('-', 80) . "\n";
echo "\n=== FIM ===\n";
