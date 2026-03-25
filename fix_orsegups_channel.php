<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== CONFIGURANDO CHANNEL ID ORSEGUPS ===\n";

// Primeiro, vamos ver se temos algum channel ID configurado na sessão pixel12digital como referência
$stmt = $db->prepare("
    SELECT session_name, whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND whapi_channel_id IS NOT NULL
");
$stmt->execute();
$existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($existing) {
    echo "\nCanais existentes (referência):\n";
    foreach ($existing as $e) {
        echo sprintf("- %s: %s\n", $e['session_name'], $e['whapi_channel_id']);
    }
    echo "\n";
}

// Verificar configuração atual da orsegups
$stmt = $db->prepare("
    SELECT id, session_name, whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'orsegups'
");
$stmt->execute();
$orsegups = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orsegups) {
    echo "❌ Sessão 'orsegups' não encontrada!\n";
    exit;
}

echo "Configuração atual 'orsegups':\n";
echo sprintf("- ID: %d\n", $orsegups['id']);
echo sprintf("- Channel ID: %s\n", $orsegups['whapi_channel_id'] ?? 'NÃO CONFIGURADO');

// Se não tiver channel_id, sugerir configurar manualmente
if (empty($orsegups['whapi_channel_id'])) {
    echo "\n⚠️ Channel ID não configurado!\n";
    echo "\nPara corrigir:\n";
    echo "1. Acesse o painel Whapi.Cloud\n";
    echo "2. Selecione o canal 'orsegups'\n";
    echo "3. Copie o Channel ID (geralmente algo como 'channel_xxxxx')\n";
    echo "4. Execute o SQL abaixo para atualizar:\n";
    echo sprintf(
        "UPDATE whatsapp_provider_configs SET whapi_channel_id = 'SEU_CHANNEL_ID' WHERE id = %d;\n",
        $orsegups['id']
    );
    
    // Se temos um canal existente, podemos tentar usar o mesmo como teste
    if (!empty($existing[0]['whapi_channel_id'])) {
        echo "\n🔧 OU use o Canal ID existente como teste:\n";
        echo sprintf(
            "UPDATE whatsapp_provider_configs SET whapi_channel_id = '%s' WHERE id = %d;\n",
            $existing[0]['whapi_channel_id'],
            $orsegups['id']
        );
    }
} else {
    echo "\n✅ Channel ID já configurado!\n";
}

echo "\n=== FIM ===\n";
