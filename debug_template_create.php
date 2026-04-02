<?php
/**
 * Script de diagnóstico para criação de templates WhatsApp
 */

require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

try {
    echo "=== DIAGNÓSTICO: Criação de Template WhatsApp ===\n\n";
    
    // 1. Verificar conexão com banco
    echo "1. Testando conexão com banco de dados...\n";
    $db = DB::getConnection();
    echo "   ✓ Conexão estabelecida\n\n";
    
    // 2. Verificar se tabela existe
    echo "2. Verificando se tabela whatsapp_message_templates existe...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'whatsapp_message_templates'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "   ✓ Tabela existe\n\n";
        
        // 3. Verificar estrutura da tabela
        echo "3. Estrutura da tabela:\n";
        $stmt = $db->query("DESCRIBE whatsapp_message_templates");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $col) {
            echo "   - {$col['Field']}: {$col['Type']} " . 
                 ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . 
                 ($col['Key'] ? " [{$col['Key']}]" : '') . "\n";
        }
        echo "\n";
        
    } else {
        echo "   ✗ ERRO: Tabela não existe!\n";
        echo "   Execute a migration: php database/migrate.php\n\n";
        exit(1);
    }
    
    // 4. Testar INSERT básico
    echo "4. Testando INSERT básico...\n";
    
    $testData = [
        'tenant_id' => null,
        'template_name' => 'teste_debug_' . time(),
        'category' => 'marketing',
        'language' => 'pt_BR',
        'status' => 'draft',
        'content' => 'Teste de template {{1}}',
        'header_type' => 'none',
        'header_content' => null,
        'footer_text' => null,
        'buttons' => null,
        'variables' => json_encode([['index' => 1, 'example' => 'teste']])
    ];
    
    try {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_message_templates (
                tenant_id,
                template_name,
                category,
                language,
                status,
                content,
                header_type,
                header_content,
                footer_text,
                buttons,
                variables
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testData['tenant_id'],
            $testData['template_name'],
            $testData['category'],
            $testData['language'],
            $testData['status'],
            $testData['content'],
            $testData['header_type'],
            $testData['header_content'],
            $testData['footer_text'],
            $testData['buttons'],
            $testData['variables']
        ]);
        
        $insertId = $db->lastInsertId();
        echo "   ✓ INSERT bem-sucedido! ID: {$insertId}\n\n";
        
        // Limpar teste
        $db->exec("DELETE FROM whatsapp_message_templates WHERE id = {$insertId}");
        echo "   ✓ Registro de teste removido\n\n";
        
    } catch (\Exception $e) {
        echo "   ✗ ERRO no INSERT: " . $e->getMessage() . "\n";
        echo "   SQL State: " . $e->getCode() . "\n\n";
        
        // Mostrar detalhes do erro
        if ($db->errorInfo()[0] !== '00000') {
            echo "   Detalhes do erro PDO:\n";
            print_r($db->errorInfo());
            echo "\n";
        }
    }
    
    // 5. Verificar MetaTemplateService
    echo "5. Testando MetaTemplateService...\n";
    
    try {
        require_once __DIR__ . '/src/Services/MetaTemplateService.php';
        
        $testData2 = [
            'tenant_id' => null,
            'template_name' => 'teste_service_' . time(),
            'category' => 'marketing',
            'language' => 'pt_BR',
            'content' => 'Teste via service {{1}}',
            'header_type' => 'none',
            'variables' => [['index' => 1, 'example' => 'teste']]
        ];
        
        $templateId = \PixelHub\Services\MetaTemplateService::create($testData2);
        echo "   ✓ MetaTemplateService::create() funcionou! ID: {$templateId}\n\n";
        
        // Limpar
        $db->exec("DELETE FROM whatsapp_message_templates WHERE id = {$templateId}");
        echo "   ✓ Registro de teste removido\n\n";
        
    } catch (\Exception $e) {
        echo "   ✗ ERRO no MetaTemplateService: " . $e->getMessage() . "\n";
        echo "   Trace: " . $e->getTraceAsString() . "\n\n";
    }
    
    // 6. Verificar logs de erro do PHP
    echo "6. Verificando configuração de logs PHP...\n";
    echo "   error_reporting: " . error_reporting() . "\n";
    echo "   display_errors: " . ini_get('display_errors') . "\n";
    echo "   log_errors: " . ini_get('log_errors') . "\n";
    echo "   error_log: " . ini_get('error_log') . "\n\n";
    
    echo "=== DIAGNÓSTICO CONCLUÍDO ===\n";
    
} catch (\Exception $e) {
    echo "\n✗ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
