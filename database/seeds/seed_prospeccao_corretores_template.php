<?php

/**
 * Seed: Template e fluxos de chatbot para prospecção de corretores
 * 
 * Cria:
 * 1. Template "prospeccao_sistema_corretores" (Marketing)
 * 2. Fluxo para botão "Quero conhecer"
 * 3. Fluxo para botão "Não tenho interesse"
 * 
 * Uso: php database/seeds/seed_prospeccao_corretores_template.php
 * 
 * Data: 2026-03-04
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

try {
    $db = DB::getConnection();
    
    echo "=== Criando Template e Fluxos de Prospecção de Corretores ===\n\n";
    
    // 1. Cria template
    echo "1. Criando template 'prospeccao_sistema_corretores'...\n";
    
    $templateContent = "Olá! Estamos entrando em contato porque identificamos que você atua como corretor de imóveis.\n\n" .
                      "Desenvolvemos uma estrutura que ajuda corretores a captar e organizar interessados em imóveis através de um site próprio integrado com WhatsApp.\n\n" .
                      "Gostaria de ver rapidamente como funciona?";
    
    $buttons = [
        [
            'type' => 'quick_reply',
            'text' => 'Quero conhecer',
            'id' => 'btn_quero_conhecer'
        ],
        [
            'type' => 'quick_reply',
            'text' => 'Não tenho interesse',
            'id' => 'btn_nao_tenho_interesse'
        ]
    ];
    
    // Verifica se template já existe
    $checkStmt = $db->prepare("SELECT id FROM whatsapp_message_templates WHERE template_name = ?");
    $checkStmt->execute(['prospeccao_sistema_corretores']);
    $existingTemplate = $checkStmt->fetch();
    
    if ($existingTemplate) {
        $templateId = $existingTemplate['id'];
        echo "   ⊙ Template já existe (ID: {$templateId}), atualizando...\n";
        
        $stmt = $db->prepare("
            UPDATE whatsapp_message_templates 
            SET content = ?,
                buttons = ?,
                category = 'marketing',
                language = 'pt_BR',
                status = 'draft',
                is_active = 1
            WHERE id = ?
        ");
        
        $stmt->execute([
            $templateContent,
            json_encode($buttons),
            $templateId
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_message_templates (
                tenant_id,
                template_name,
                category,
                language,
                status,
                content,
                header_type,
                footer_text,
                buttons,
                is_active
            ) VALUES (NULL, ?, 'marketing', 'pt_BR', 'draft', ?, 'none', NULL, ?, 1)
        ");
        
        $stmt->execute([
            'prospeccao_sistema_corretores',
            $templateContent,
            json_encode($buttons)
        ]);
        
        $templateId = (int) $db->lastInsertId();
        echo "   ✓ Template criado (ID: {$templateId})\n";
    }
    
    // 2. Cria fluxo para "Quero conhecer"
    echo "\n2. Criando fluxo para botão 'Quero conhecer'...\n";
    
    $responseQueroConhecer = "Aqui mostramos rapidamente como funciona a estrutura que criamos para corretores captarem e organizarem leads de imóveis:\n\n" .
                             "[LINK_DA_LANDING_PAGE]\n\n" .
                             "Se quiser, posso também te mostrar rapidamente como funciona na prática.";
    
    $nextButtonsQueroConhecer = [
        [
            'text' => 'Falar no WhatsApp',
            'flow_id' => null // Será preenchido após criar fluxo de atendimento humano
        ],
        [
            'text' => 'Tirar dúvidas',
            'flow_id' => null
        ]
    ];
    
    // Verifica se fluxo já existe
    $checkStmt = $db->prepare("SELECT id FROM chatbot_flows WHERE trigger_value = ?");
    $checkStmt->execute(['btn_quero_conhecer']);
    $existingFlow = $checkStmt->fetch();
    
    if ($existingFlow) {
        $flowId1 = $existingFlow['id'];
        echo "   ⊙ Fluxo 'Quero conhecer' já existe (ID: {$flowId1}), atualizando...\n";
        
        $stmt = $db->prepare("
            UPDATE chatbot_flows 
            SET name = ?,
                response_message = ?,
                next_buttons = ?,
                forward_to_human = 1,
                is_active = 1
            WHERE id = ?
        ");
        
        $stmt->execute([
            'Prospecção Corretores - Quero Conhecer',
            $responseQueroConhecer,
            json_encode($nextButtonsQueroConhecer),
            $flowId1
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO chatbot_flows (
                tenant_id,
                name,
                trigger_type,
                trigger_value,
                response_type,
                response_message,
                next_buttons,
                forward_to_human,
                add_tags,
                update_lead_status,
                priority,
                is_active
            ) VALUES (NULL, ?, 'template_button', 'btn_quero_conhecer', 'text', ?, ?, 1, ?, 'interessado', 10, 1)
        ");
        
        $stmt->execute([
            'Prospecção Corretores - Quero Conhecer',
            $responseQueroConhecer,
            json_encode($nextButtonsQueroConhecer),
            json_encode(['corretor', 'interessado', 'prospeccao'])
        ]);
        
        $flowId1 = (int) $db->lastInsertId();
        echo "   ✓ Fluxo criado (ID: {$flowId1})\n";
    }
    
    // 3. Cria fluxo para "Não tenho interesse"
    echo "\n3. Criando fluxo para botão 'Não tenho interesse'...\n";
    
    $responseNaoInteresse = "Sem problemas.\n\n" .
                           "Se no futuro quiser conhecer formas de captar mais interessados em seus imóveis, estaremos à disposição.\n\n" .
                           "Tenha um excelente dia.";
    
    // Verifica se fluxo já existe
    $checkStmt = $db->prepare("SELECT id FROM chatbot_flows WHERE trigger_value = ?");
    $checkStmt->execute(['btn_nao_tenho_interesse']);
    $existingFlow = $checkStmt->fetch();
    
    if ($existingFlow) {
        $flowId2 = $existingFlow['id'];
        echo "   ⊙ Fluxo 'Não tenho interesse' já existe (ID: {$flowId2}), atualizando...\n";
        
        $stmt = $db->prepare("
            UPDATE chatbot_flows 
            SET name = ?,
                response_message = ?,
                next_buttons = NULL,
                forward_to_human = 0,
                is_active = 1
            WHERE id = ?
        ");
        
        $stmt->execute([
            'Prospecção Corretores - Não Tenho Interesse',
            $responseNaoInteresse,
            $flowId2
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO chatbot_flows (
                tenant_id,
                name,
                trigger_type,
                trigger_value,
                response_type,
                response_message,
                next_buttons,
                forward_to_human,
                add_tags,
                update_lead_status,
                priority,
                is_active
            ) VALUES (NULL, ?, 'template_button', 'btn_nao_tenho_interesse', 'text', ?, NULL, 0, ?, 'nao_interessado', 10, 1)
        ");
        
        $stmt->execute([
            'Prospecção Corretores - Não Tenho Interesse',
            $responseNaoInteresse,
            json_encode(['corretor', 'nao_interessado', 'prospeccao'])
        ]);
        
        $flowId2 = (int) $db->lastInsertId();
        echo "   ✓ Fluxo criado (ID: {$flowId2})\n";
    }
    
    echo "\n=== Seed concluído com sucesso! ===\n\n";
    echo "Template ID: {$templateId}\n";
    echo "Fluxo 'Quero conhecer' ID: {$flowId1}\n";
    echo "Fluxo 'Não tenho interesse' ID: {$flowId2}\n\n";
    
    echo "⚠️  PRÓXIMOS PASSOS:\n";
    echo "1. Submeter template para aprovação no Meta Business Suite\n";
    echo "2. Substituir [LINK_DA_LANDING_PAGE] pelo link real da landing page\n";
    echo "3. Criar campanha de prospecção usando este template\n";
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
