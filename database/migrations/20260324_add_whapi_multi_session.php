<?php

/**
 * Migration: Suporte a múltiplos canais Whapi.Cloud
 *
 * 1. Adiciona session_name (identificador do canal, ex: 'pixel12digital', 'orsegups')
 * 2. Remove constraint unique_global_provider (limitava a 1 linha por provider_type)
 * 3. Cria novo índice único em (provider_type, session_name)
 * 4. Define session_name da linha existente como 'pixel12digital'
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
if (file_exists(__DIR__ . '/../bootstrap.php')) {
    require_once __DIR__ . '/../bootstrap.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix  = 'PixelHub\\';
        $baseDir = __DIR__ . '/../../src/';
        $len     = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) require $file;
    });
    if (file_exists(__DIR__ . '/../../src/Core/Env.php')) {
        require_once __DIR__ . '/../../src/Core/Env.php';
        \PixelHub\Core\Env::load();
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$pdo  = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: Whapi Multi-Session ===\n";

// 1. Adiciona session_name se ainda não existir
$cols = array_column($pdo->query("SHOW COLUMNS FROM whatsapp_provider_configs")->fetchAll(PDO::FETCH_ASSOC), 'Field');

if (!in_array('session_name', $cols)) {
    $pdo->exec("
        ALTER TABLE whatsapp_provider_configs
        ADD COLUMN session_name VARCHAR(100) NULL
            COMMENT 'Identificador do canal Whapi (ex: pixel12digital, orsegups)'
        AFTER whapi_channel_id
    ");
    echo "OK: coluna session_name adicionada\n";
} else {
    echo "SKIP: session_name já existe\n";
}

// 2. Define session_name da linha whapi existente como 'pixel12digital'
$upd = $pdo->exec("
    UPDATE whatsapp_provider_configs
    SET session_name = 'pixel12digital'
    WHERE provider_type = 'whapi'
      AND (session_name IS NULL OR session_name = '')
");
echo "OK: {$upd} linha(s) atualizadas para session_name='pixel12digital'\n";

// 3. Remove o índice único antigo (unique_global_provider) que impedia múltiplos canais
try {
    $pdo->exec("ALTER TABLE whatsapp_provider_configs DROP INDEX unique_global_provider");
    echo "OK: índice unique_global_provider removido\n";
} catch (\PDOException $e) {
    echo "SKIP: unique_global_provider não encontrado ou já removido\n";
}

// 4. Cria novo índice único em (provider_type, session_name)
try {
    $pdo->exec("
        CREATE UNIQUE INDEX uq_provider_session
        ON whatsapp_provider_configs (provider_type, session_name)
    ");
    echo "OK: índice uq_provider_session criado\n";
} catch (\PDOException $e) {
    echo "SKIP: índice uq_provider_session — " . $e->getMessage() . "\n";
}

echo "=== Concluído ===\n";
