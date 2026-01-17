<?php
/**
 * Correção rápida: alinhar channel_id ImobSites → imobsites (padrão minúsculo/slug)
 * para destravar envio. Alinha o Hub ao que existe no WPP Pixel.
 *
 * Faz SELECT antes de cada UPDATE. Só altera onde a coluna existe.
 * Uso: php database/correcao-rapida-channel-imobsites.php
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

$de = 'ImobSites';
$para = 'imobsites';

echo "=== Correção rápida: {$de} → {$para} (minúsculo/slug) ===\n\n";

// 1. tenant_message_channels (a tabela "channels" do Hub é tenant_message_channels)
echo "1. tenant_message_channels:\n";
$cols = [];
try {
    $chk = $db->query("SHOW COLUMNS FROM tenant_message_channels");
    while ($r = $chk->fetch(PDO::FETCH_ASSOC)) $cols[] = $r['Field'];
} catch (Exception $e) {
    echo "   Tabela não existe ou erro: " . $e->getMessage() . "\n";
}
if (!empty($cols)) {
    $hasChannelId = in_array('channel_id', $cols);
    $hasSessionId = in_array('session_id', $cols);
    if ($hasChannelId) {
        $sel = $db->prepare("SELECT id, tenant_id, channel_id" . ($hasSessionId ? ", session_id" : "") . " FROM tenant_message_channels WHERE provider = 'wpp_gateway' AND channel_id = ?");
        $sel->execute([$de]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
        echo "   SELECT (provider=wpp_gateway AND channel_id='{$de}'): " . count($rows) . " linha(s)\n";
        if (count($rows) > 0) {
            $up = $db->prepare("UPDATE tenant_message_channels SET channel_id = ? WHERE provider = 'wpp_gateway' AND channel_id = ?");
            $up->execute([$para, $de]);
            echo "   UPDATE channel_id: {$de} → {$para} — " . $up->rowCount() . " linha(s) afetada(s).\n";
        }
        if ($hasSessionId) {
            $sel2 = $db->prepare("SELECT id, session_id FROM tenant_message_channels WHERE provider = 'wpp_gateway' AND session_id = ?");
            $sel2->execute([$de]);
            $rows2 = $sel2->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows2) > 0) {
                $up2 = $db->prepare("UPDATE tenant_message_channels SET session_id = ? WHERE provider = 'wpp_gateway' AND session_id = ?");
                $up2->execute([$para, $de]);
                echo "   UPDATE session_id: {$de} → {$para} — " . $up2->rowCount() . " linha(s) afetada(s).\n";
            }
        }
    } else {
        echo "   Coluna channel_id não encontrada. Colunas: " . implode(', ', $cols) . "\n";
    }
}

// 2. conversations
echo "\n2. conversations:\n";
$colsC = [];
try {
    $chk = $db->query("SHOW COLUMNS FROM conversations");
    while ($r = $chk->fetch(PDO::FETCH_ASSOC)) $colsC[] = $r['Field'];
} catch (Exception $e) {
    echo "   Tabela não existe ou erro: " . $e->getMessage() . "\n";
}
if (!empty($colsC) && in_array('channel_id', $colsC)) {
    $sel = $db->prepare("SELECT id, tenant_id, channel_id FROM conversations WHERE channel_id = ?");
    $sel->execute([$de]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    echo "   SELECT (channel_id='{$de}'): " . count($rows) . " linha(s)\n";
    if (count($rows) > 0) {
        $up = $db->prepare("UPDATE conversations SET channel_id = ? WHERE channel_id = ?");
        $up->execute([$para, $de]);
        echo "   UPDATE channel_id: {$de} → {$para} — " . $up->rowCount() . " linha(s) afetada(s).\n";
    }
} elseif (!empty($colsC)) {
    echo "   Coluna channel_id não encontrada. Colunas: " . implode(', ', $colsC) . "\n";
}

// 3. messages (se existir e tiver channel_id)
echo "\n3. messages:\n";
try {
    $chk = $db->query("SHOW COLUMNS FROM messages");
    $colsM = [];
    while ($r = $chk->fetch(PDO::FETCH_ASSOC)) $colsM[] = $r['Field'];
    if (in_array('channel_id', $colsM)) {
        $sel = $db->prepare("SELECT id, channel_id FROM messages WHERE channel_id = ?");
        $sel->execute([$de]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
        echo "   SELECT (channel_id='{$de}'): " . count($rows) . " linha(s)\n";
        if (count($rows) > 0) {
            $up = $db->prepare("UPDATE messages SET channel_id = ? WHERE channel_id = ?");
            $up->execute([$para, $de]);
            echo "   UPDATE channel_id: {$de} → {$para} — " . $up->rowCount() . " linha(s) afetada(s).\n";
        }
    } else {
        echo "   Tabela existe mas não tem coluna channel_id. Colunas: " . implode(', ', $colsM) . "\n";
    }
} catch (Exception $e) {
    echo "   Tabela messages não existe ou erro: " . $e->getMessage() . "\n";
}

// 4. communication_events (só se tiver coluna channel_id; payload JSON não alteramos aqui)
echo "\n4. communication_events:\n";
try {
    $chk = $db->query("SHOW COLUMNS FROM communication_events");
    $colsE = [];
    while ($r = $chk->fetch(PDO::FETCH_ASSOC)) $colsE[] = $r['Field'];
    if (in_array('channel_id', $colsE)) {
        $sel = $db->prepare("SELECT id, channel_id FROM communication_events WHERE channel_id = ?");
        $sel->execute([$de]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
        echo "   SELECT (channel_id='{$de}'): " . count($rows) . " linha(s)\n";
        if (count($rows) > 0) {
            $up = $db->prepare("UPDATE communication_events SET channel_id = ? WHERE channel_id = ?");
            $up->execute([$para, $de]);
            echo "   UPDATE channel_id: {$de} → {$para} — " . $up->rowCount() . " linha(s) afetada(s).\n";
        }
    } else {
        echo "   Sem coluna channel_id (pode estar em payload JSON). Colunas: " . implode(', ', $colsE) . "\n";
    }
} catch (Exception $e) {
    echo "   Tabela communication_events não existe ou erro: " . $e->getMessage() . "\n";
}

echo "\n=== Fim da correção rápida ===\n";

