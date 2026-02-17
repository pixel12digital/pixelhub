<?php

/**
 * Script de Migração: Adicionar campos de lead na tabela tenants
 * 
 * Este script deve ser executado no servidor de produção para preparar
 * a tabela tenants para receber os dados de leads.
 * 
 * EXECUÇÃO:
 * php scripts/migrate_add_lead_fields_fixed.php
 */

// Configuração do banco de dados de produção
$host = 'pixelhub-db.c58i8jm2i1sa.us-east-1.rds.amazonaws.com';
$database = 'pixelhub';
$username = 'pixelhub';
$password = 'P@ssw0rd!@#2024';

// Tentar resolver DNS
echo "=== Testando conexão com banco de dados ===\n";
echo "Host: $host\n";
echo "Database: $database\n";

// Testar resolução DNS
$ip = gethostbyname($host);
if ($ip === $host) {
    echo "❌ ERRO: Não foi possível resolver o hostname: $host\n";
    echo "Tentando usar IP direto...\n";
    
    // IPs alternativos conhecidos
    $altHosts = [
        '52.72.167.114',  // IP possível do RDS
        '54.235.193.81',  // IP possível do RDS
        'ec2-52-72-167-114.compute-1.amazonaws.com'
    ];
    
    $connected = false;
    foreach ($altHosts as $altHost) {
        echo "Tentando conectar com: $altHost\n";
        try {
            $pdo = new PDO("mysql:host=$altHost;dbname=$database;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✅ Conectado com sucesso usando: $altHost\n";
            $connected = true;
            $host = $altHost;
            break;
        } catch (Exception $e) {
            echo "❌ Falha com $altHost: " . $e->getMessage() . "\n";
        }
    }
    
    if (!$connected) {
        echo "\n❌ ERRO CRÍTICO: Não foi possível conectar ao banco de dados\n";
        echo "Verifique:\n";
        echo "1. Se o servidor tem acesso à internet\n";
        echo "2. Se as credenciais estão corretas\n";
        echo "3. Se o security group da VPS permite acesso ao RDS\n";
        echo "4. Se o hostname está correto\n";
        exit(1);
    }
} else {
    echo "✅ DNS resolvido: $host -> $ip\n";
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Migration: Adicionar campos de lead na tabela tenants ===\n";
    echo "Conectado ao banco de dados de PRODUÇÃO.\n";
    
    // 1. Verificar se a tabela tenants existe
    echo "\n1. Verificando estrutura da tabela tenants...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'tenants'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Tabela 'tenants' não encontrada!\n";
        exit(1);
    }
    
    $stmt = $pdo->query("DESCRIBE tenants");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas atuais: " . implode(', ', $columns) . "\n";
    
    // 2. Adicionar campos de lead na tabela tenants
    echo "\n2. Adicionando campos de lead na tabela tenants...\n";
    
    $newColumns = [
        "contact_type VARCHAR(20) NOT NULL DEFAULT 'client' COMMENT 'Tipo de contato: lead ou client'",
        "source VARCHAR(50) NULL COMMENT 'Origem do lead: whatsapp, site, indicacao, outro'",
        "notes TEXT NULL COMMENT 'Observações livres do lead'",
        "created_by INT UNSIGNED NULL COMMENT 'FK para users (quem criou o lead)'",
        "lead_converted_at DATETIME NULL COMMENT 'Data da conversão de lead para cliente'",
        "original_lead_id INT UNSIGNED NULL COMMENT 'ID original do lead antes da conversão'"
    ];
    
    foreach ($newColumns as $columnDef) {
        // Extrair nome da coluna
        preg_match('/^(\w+)/', $columnDef, $matches);
        $columnName = $matches[1];
        
        if (!in_array($columnName, $columns)) {
            try {
                $pdo->exec("ALTER TABLE tenants ADD COLUMN $columnDef");
                echo "✓ Campo adicionado: $columnName\n";
            } catch (Exception $e) {
                echo "❌ Erro ao adicionar campo $columnName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "- Campo já existe: $columnName\n";
        }
    }
    
    // 3. Verificar e adicionar índices
    echo "\n3. Verificando e adicionando índices...\n";
    
    $stmt = $pdo->query("SHOW INDEX FROM tenants");
    $indexes = [];
    while ($row = $stmt->fetch()) {
        $indexes[] = $row['Key_name'];
    }
    $indexes = array_unique($indexes);
    
    $newIndexes = [
        "idx_contact_type" => "INDEX idx_contact_type (contact_type)",
        "idx_source" => "INDEX idx_source (source)",
        "idx_created_by" => "INDEX idx_created_by (created_by)",
        "idx_lead_converted_at" => "INDEX idx_lead_converted_at (lead_converted_at)",
        "idx_original_lead_id" => "INDEX idx_original_lead_id (original_lead_id)"
    ];
    
    foreach ($newIndexes as $indexName => $indexDef) {
        if (!in_array($indexName, $indexes)) {
            try {
                $pdo->exec("ALTER TABLE tenants ADD $indexDef");
                echo "✓ Índice adicionado: $indexName\n";
            } catch (Exception $e) {
                echo "❌ Erro ao adicionar índice $indexName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "- Índice já existe: $indexName\n";
        }
    }
    
    // 4. Atualizar registros existentes para compatibilidade
    echo "\n4. Atualizando registros existentes para compatibilidade...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants WHERE contact_type IS NULL OR contact_type = ''");
    $result = $stmt->fetch();
    
    if ($result['total'] > 0) {
        $pdo->exec("UPDATE tenants SET contact_type = 'client' WHERE contact_type IS NULL OR contact_type = ''");
        echo "✓ {$result['total']} registros atualizados para contact_type = 'client'\n";
    } else {
        echo "- Nenhum registro precisa ser atualizado\n";
    }
    
    // 5. Verificar estrutura final
    echo "\n5. Verificando estrutura final...\n";
    
    $stmt = $pdo->query("DESCRIBE tenants");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredFields = ['contact_type', 'source', 'notes', 'created_by', 'lead_converted_at', 'original_lead_id'];
    $allFieldsExist = true;
    
    foreach ($requiredFields as $field) {
        $found = false;
        foreach ($finalColumns as $column) {
            if ($column['Field'] === $field) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ Campo obrigatório não encontrado: $field\n";
            $allFieldsExist = false;
        }
    }
    
    if ($allFieldsExist) {
        echo "✅ Todos os campos obrigatórios foram adicionados\n";
    } else {
        echo "❌ Alguns campos obrigatórios estão faltando\n";
        exit(1);
    }
    
    echo "\n=== Migration concluída com sucesso! ===\n";
    echo "Tabela 'tenants' agora suporta leads e clientes unificados.\n";
    echo "\nPróximo passo: Executar migração de dados\n";
    echo "php scripts/migrate_leads_to_tenants_fixed.php --dry-run\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
