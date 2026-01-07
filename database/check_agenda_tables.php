<?php

/**
 * Script para verificar dados das tabelas de agenda
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== Verificação das Tabelas de Agenda ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica agenda_block_types
    echo "1. agenda_block_types:\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM agenda_block_types");
    $count = $stmt->fetch();
    echo "   Total: " . $count['total'] . "\n";
    
    if ($count['total'] > 0) {
        $stmt = $db->query("SELECT * FROM agenda_block_types LIMIT 5");
        $types = $stmt->fetchAll();
        echo "   Primeiros registros:\n";
        foreach ($types as $type) {
            echo "   - ID: {$type['id']}, Código: {$type['codigo']}, Nome: {$type['nome']}\n";
        }
    } else {
        echo "   ⚠️ Tabela vazia!\n";
    }
    
    echo "\n";
    
    // Verifica agenda_block_templates
    echo "2. agenda_block_templates:\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM agenda_block_templates");
    $count = $stmt->fetch();
    echo "   Total: " . $count['total'] . "\n";
    
    if ($count['total'] > 0) {
        $stmt = $db->query("SELECT * FROM agenda_block_templates ORDER BY dia_semana, hora_inicio LIMIT 10");
        $templates = $stmt->fetchAll();
        echo "   Primeiros registros:\n";
        foreach ($templates as $template) {
            echo "   - Dia: {$template['dia_semana']}, {$template['hora_inicio']}-{$template['hora_fim']}, Tipo ID: {$template['tipo_id']}\n";
        }
    } else {
        echo "   ⚠️ Tabela vazia!\n";
    }
    
    echo "\n";
    
    // Verifica agenda_blocks
    echo "3. agenda_blocks:\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM agenda_blocks");
    $count = $stmt->fetch();
    echo "   Total: " . $count['total'] . "\n";
    
    if ($count['total'] > 0) {
        $stmt = $db->query("SELECT * FROM agenda_blocks ORDER BY data DESC, hora_inicio ASC LIMIT 5");
        $blocks = $stmt->fetchAll();
        echo "   Primeiros registros:\n";
        foreach ($blocks as $block) {
            echo "   - Data: {$block['data']}, {$block['hora_inicio']}-{$block['hora_fim']}, Status: {$block['status']}\n";
        }
    } else {
        echo "   ⚠️ Tabela vazia!\n";
    }
    
    echo "\n";
    echo "✓ Verificação concluída!\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}











