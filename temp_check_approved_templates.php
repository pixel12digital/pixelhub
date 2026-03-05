<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$db = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== TEMPLATES NA TABELA whatsapp_message_templates ===\n\n";

$stmt = $db->query("SELECT * FROM whatsapp_message_templates");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de templates: " . count($templates) . "\n\n";

foreach ($templates as $t) {
    echo "ID: {$t['id']}\n";
    echo "Nome: {$t['template_name']}\n";
    echo "Status: {$t['status']}\n";
    echo "Categoria: {$t['category']}\n";
    echo "Idioma: {$t['language']}\n";
    echo "Ativo: " . ($t['is_active'] ? 'SIM' : 'NÃO') . "\n";
    echo "Conteúdo: " . substr($t['content'], 0, 100) . "...\n";
    if ($t['variables']) {
        echo "Variáveis: {$t['variables']}\n";
    }
    echo "---\n\n";
}

// Verifica se o template ID que está sendo usado existe
echo "=== VERIFICAÇÃO DO TEMPLATE SELECIONADO ===\n\n";
echo "Pelo screenshot, você selecionou um template.\n";
echo "Verifique se o ID do template selecionado corresponde a algum dos templates acima.\n\n";

// Verifica últimas tentativas de envio
echo "=== ÚLTIMOS LOGS DE ENVIO VIA META API ===\n\n";
$logFile = __DIR__ . '/logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    
    $found = false;
    foreach ($lastLines as $line) {
        if (stripos($line, 'sendViaMetaAPI') !== false || 
            stripos($line, 'whatsapp_api') !== false ||
            stripos($line, 'Meta API') !== false) {
            echo $line;
            $found = true;
        }
    }
    
    if (!$found) {
        echo "Nenhum log de envio Meta API encontrado nos últimos 100 registros.\n";
    }
} else {
    echo "Arquivo de log não encontrado.\n";
}
