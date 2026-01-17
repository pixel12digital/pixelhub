<?php
/**
 * Diagnóstico: Erro "Canal não encontrado" (CHANNEL_NOT_FOUND) para ImobSites
 *
 * Este script verifica:
 * 1. Canais ImobSites na tabela tenant_message_channels
 * 2. Conversas que usam channel_id ImobSites
 * 3. O que o gateway WPPConnect espera (session name exato)
 *
 * Uso: php database/diagnostico-canal-imobsites.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== DIAGNÓSTICO: Canal ImobSites - CHANNEL_NOT_FOUND ===\n\n";

// 1. Canais no banco (tenant_message_channels)
echo "1. Canais no banco (tenant_message_channels, provider=wpp_gateway):\n";
echo str_repeat("-", 70) . "\n";

$cols = [];
try {
    $chk = $db->query("SHOW COLUMNS FROM tenant_message_channels");
    while ($r = $chk->fetch(PDO::FETCH_ASSOC)) $cols[] = $r['Field'];
} catch (Exception $e) { $cols = ['id','tenant_id','channel_id','provider','is_enabled']; }

$hasSessionId = in_array('session_id', $cols);
$sel = "SELECT id, tenant_id, channel_id" . ($hasSessionId ? ", session_id" : "") . ", is_enabled, provider 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway' 
        AND (channel_id LIKE '%ImobSites%' OR channel_id LIKE '%imobsites%' OR LOWER(channel_id) = 'imobsites')
        ORDER BY tenant_id, channel_id";
$stmt = $db->query($sel);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "   Nenhum canal 'ImobSites' encontrado em tenant_message_channels.\n";
    echo "   Listando todos os canais wpp_gateway habilitados:\n\n";
    $stmt2 = $db->query("SELECT id, tenant_id, channel_id" . ($hasSessionId ? ", session_id" : "") . ", is_enabled 
                         FROM tenant_message_channels 
                         WHERE provider = 'wpp_gateway' AND is_enabled = 1 
                         ORDER BY channel_id");
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo "   - tenant_id={$r['tenant_id']} | channel_id=\"" . ($r['channel_id'] ?? '') . "\"";
        if ($hasSessionId && isset($r['session_id']) && $r['session_id'] !== null && $r['session_id'] !== '') {
            echo " | session_id=\"" . $r['session_id'] . "\"";
        }
        echo "\n";
    }
} else {
    foreach ($rows as $r) {
        echo "   id={$r['id']} | tenant_id={$r['tenant_id']} | channel_id=\"" . ($r['channel_id'] ?? '') . "\"";
        if ($hasSessionId && isset($r['session_id']) && $r['session_id'] !== null && $r['session_id'] !== '') {
            echo " | session_id=\"" . $r['session_id'] . "\"";
        }
        echo " | is_enabled=" . ($r['is_enabled'] ?? 0) . "\n";
    }
}

// 2. Conversas com channel_id ImobSites
echo "\n2. Conversas (conversations) com channel_id contendo 'ImobSites' ou 'imobsites':\n";
echo str_repeat("-", 70) . "\n";
try {
    $stmt = $db->query("SELECT id, tenant_id, channel_id, contact_external_id, status 
                       FROM conversations 
                       WHERE channel_id LIKE '%ImobSites%' OR channel_id LIKE '%imobsites%' OR LOWER(TRIM(channel_id)) = 'imobsites'
                       ORDER BY id DESC LIMIT 10");
    $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($convs)) {
        echo "   Nenhuma conversa encontrada com esse channel_id.\n";
    } else {
        foreach ($convs as $c) {
            echo "   id={$c['id']} | tenant_id=" . ($c['tenant_id'] ?? 'NULL') . " | channel_id=\"" . ($c['channel_id'] ?? '') . "\" | contact=" . ($c['contact_external_id'] ?? '') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   Erro ao consultar conversations: " . $e->getMessage() . "\n";
}

// 3. Resumo e recomendações
echo "\n3. CAUSA DO ERRO 'Canal não encontrado' (CHANNEL_NOT_FOUND):\n";
echo str_repeat("-", 70) . "\n";
echo "   O Painel envia a mensagem ao gateway WPPConnect. Antes de enviar, ele\n";
echo "   consulta o gateway em: GET {WPP_GATEWAY_BASE_URL}/api/channels/{channel_id}\n";
echo "   Se o gateway retornar 404, o Painel exibe 'Canal não encontrado' e\n";
echo "   error_code CHANNEL_NOT_FOUND.\n\n";
echo "   Isso significa que o gateway WPPConnect NÃO possui uma sessão com o\n";
echo "   nome exato que está no banco (ex: \"ImobSites\"). O nome é case-sensitive.\n\n";

echo "4. O QUE FAZER:\n";
echo str_repeat("-", 70) . "\n";
echo "   A) No WPPConnect (gateway):\n";
echo "      - Acesse o painel/API do WPPConnect e liste as sessões (channels).\n";
echo "      - Anote o nome EXATO da sessão (ex: \"ImobSites\", \"imobsites\", etc.).\n";
echo "      - Se a sessão ImobSites NÃO existir: crie e conecte a sessão no gateway.\n";
echo "      - Se existir com outro nome: use esse nome exato no passo B.\n\n";
echo "   B) No Pixel Hub (banco tenant_message_channels):\n";
echo "      - O campo channel_id (ou session_id, se existir) deve ser IDÊNTICO\n";
echo "        ao nome da sessão no WPPConnect (incluindo maiúsculas/minúsculas).\n";
echo "      - Exemplo: se no gateway a sessão é \"imobsites\", no banco deve estar\n";
echo "        \"imobsites\" e não \"ImobSites\".\n\n";
echo "   C) Conferir também:\n";
echo "      - A coluna conversations.channel_id deve bater com o mesmo valor\n";
echo "        (quando a conversa veio de uma sessão específica).\n\n";

echo "=== Fim do diagnóstico ===\n";

