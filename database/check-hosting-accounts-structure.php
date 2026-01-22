<?php

/**
 * Script para verificar estrutura da tabela hosting_accounts
 * e gerar SQL para aplicar migrations faltantes
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

echo "=== Verificação de Estrutura: hosting_accounts ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'hosting_accounts'");
    if ($stmt->rowCount() === 0) {
        echo "✗ ERRO: Tabela hosting_accounts não existe!\n";
        exit(1);
    }
    
    echo "✓ Tabela hosting_accounts existe\n\n";
    
    // Lista todas as colunas
    $stmt = $db->query("SHOW COLUMNS FROM hosting_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingColumns = [];
    echo "=== Colunas Existentes ===\n";
    foreach ($columns as $col) {
        $existingColumns[] = $col['Field'];
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n=== Colunas Esperadas pelo Código ===\n";
    
    // Colunas da migration inicial
    $expectedColumns = [
        'id',
        'tenant_id',
        'domain',
        'provider',
        'current_provider',
        'hostinger_expiration_date',
        'decision',
        'backup_status',
        'migration_status',
        'notes',
        'created_at',
        'updated_at',
    ];
    
    // Colunas da migration de domain_expiration (20250126)
    $expectedColumns[] = 'domain_expiration_date';
    $expectedColumns[] = 'domain_notified_30';
    $expectedColumns[] = 'domain_notified_15';
    $expectedColumns[] = 'domain_notified_7';
    
    // Colunas da migration de credentials (20250129)
    $expectedColumns[] = 'hosting_panel_url';
    $expectedColumns[] = 'hosting_panel_username';
    $expectedColumns[] = 'hosting_panel_password';
    $expectedColumns[] = 'site_admin_url';
    $expectedColumns[] = 'site_admin_username';
    $expectedColumns[] = 'site_admin_password';
    
    // Colunas adicionais que podem existir
    $expectedColumns[] = 'hosting_plan_id';
    $expectedColumns[] = 'plan_name';
    $expectedColumns[] = 'amount';
    $expectedColumns[] = 'billing_cycle';
    $expectedColumns[] = 'last_backup_at';
    
    $missingColumns = [];
    $presentColumns = [];
    
    foreach ($expectedColumns as $col) {
        if (in_array($col, $existingColumns)) {
            $presentColumns[] = $col;
            echo "  ✓ {$col}\n";
        } else {
            $missingColumns[] = $col;
            echo "  ✗ {$col} (FALTANDO)\n";
        }
    }
    
    echo "\n=== SQL para Aplicar Migrations Faltantes ===\n\n";
    
    if (empty($missingColumns)) {
        echo "✓ Todas as colunas esperadas estão presentes!\n";
    } else {
        echo "-- Execute este SQL no phpMyAdmin da HostMídia\n\n";
        
        // Verifica quais migrations precisam ser aplicadas
        $needsDomainExpiration = false;
        $needsCredentials = false;
        
        $domainExpirationCols = ['domain_expiration_date', 'domain_notified_30', 'domain_notified_15', 'domain_notified_7'];
        $credentialsCols = ['hosting_panel_url', 'hosting_panel_username', 'hosting_panel_password', 
                           'site_admin_url', 'site_admin_username', 'site_admin_password'];
        
        foreach ($domainExpirationCols as $col) {
            if (in_array($col, $missingColumns)) {
                $needsDomainExpiration = true;
                break;
            }
        }
        
        foreach ($credentialsCols as $col) {
            if (in_array($col, $missingColumns)) {
                $needsCredentials = true;
                break;
            }
        }
        
        // Migration 20250126: domain_expiration
        if ($needsDomainExpiration) {
            echo "-- Migration 20250126: Adiciona campos de vencimento de domínio\n";
            if (in_array('domain_expiration_date', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN domain_expiration_date DATE NULL AFTER hostinger_expiration_date;\n";
            }
            if (in_array('domain_notified_30', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN domain_notified_30 TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_expiration_date;\n";
            }
            if (in_array('domain_notified_15', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN domain_notified_15 TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_notified_30;\n";
            }
            if (in_array('domain_notified_7', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN domain_notified_7 TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_notified_15;\n";
            }
            echo "\n";
        }
        
        // Migration 20250129: credentials
        if ($needsCredentials) {
            echo "-- Migration 20250129: Adiciona campos de credenciais\n";
            if (in_array('hosting_panel_url', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN hosting_panel_url VARCHAR(255) NULL AFTER notes;\n";
            }
            if (in_array('hosting_panel_username', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN hosting_panel_username VARCHAR(255) NULL AFTER hosting_panel_url;\n";
            }
            if (in_array('hosting_panel_password', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN hosting_panel_password VARCHAR(255) NULL AFTER hosting_panel_username;\n";
            }
            if (in_array('site_admin_url', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN site_admin_url VARCHAR(255) NULL AFTER hosting_panel_password;\n";
            }
            if (in_array('site_admin_username', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN site_admin_username VARCHAR(255) NULL AFTER site_admin_url;\n";
            }
            if (in_array('site_admin_password', $missingColumns)) {
                echo "ALTER TABLE hosting_accounts ADD COLUMN site_admin_password VARCHAR(255) NULL AFTER site_admin_username;\n";
            }
            echo "\n";
        }
        
        // Outras colunas que podem estar faltando
        $otherMissing = array_diff($missingColumns, $domainExpirationCols, $credentialsCols);
        if (!empty($otherMissing)) {
            echo "-- Outras colunas faltantes\n";
            foreach ($otherMissing as $col) {
                // Tenta inferir o tipo baseado no nome
                $type = 'VARCHAR(255) NULL';
                if (strpos($col, '_id') !== false) {
                    $type = 'INT UNSIGNED NULL';
                } elseif (strpos($col, '_date') !== false || strpos($col, '_at') !== false) {
                    $type = 'DATETIME NULL';
                } elseif (strpos($col, 'amount') !== false) {
                    $type = 'DECIMAL(10,2) NULL';
                } elseif (strpos($col, 'billing_cycle') !== false) {
                    $type = 'VARCHAR(50) NULL';
                }
                echo "ALTER TABLE hosting_accounts ADD COLUMN {$col} {$type};\n";
            }
        }
    }
    
    echo "\n=== Resumo ===\n";
    echo "Colunas existentes: " . count($presentColumns) . "\n";
    echo "Colunas faltantes: " . count($missingColumns) . "\n";
    
    if (!empty($missingColumns)) {
        echo "\n⚠️  ATENÇÃO: Execute o SQL acima no phpMyAdmin antes de continuar!\n";
    }
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

