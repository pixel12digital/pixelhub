<?php

use PixelHub\Core\DB;

/**
 * Migration: Atualizar e criar fluxos de chatbot para prospecção de corretores
 * 
 * IMPORTANTE: Não há follow-up após 24h para evitar cobrança duplicada do Meta
 * 
 * Fluxo completo:
 * 1. Template inicial → Quero conhecer / Sem interesse
 * 2. Pergunta perfil → Sou autônomo / Trabalho em imobiliária
 * 3. Envia link → Quero agendar / Vou analisar primeiro
 * 
 * Data: 2026-03-04
 */

try {
    $db = DB::getConnection();
    
    echo "Iniciando migration: Atualizar fluxos de chatbot...\n\n";
    
    // ========================================
    // 1. ATUALIZAR FLUXO ID 1: "Quero conhecer"
    // ========================================
    echo "1. Atualizando fluxo 'Quero conhecer' (ID 1)...\n";
    
    $db->prepare("
        UPDATE chatbot_flows 
        SET 
            response_message = 'Ótimo! Para personalizar melhor nossa conversa, você trabalha como:',
            updated_at = NOW()
        WHERE id = 1
    ")->execute();
    
    echo "   ✓ Fluxo ID 1 atualizado\n\n";
    
    // ========================================
    // 2. ATUALIZAR FLUXO ID 2: "Sem interesse"
    // ========================================
    echo "2. Atualizando fluxo 'Sem interesse' (ID 2)...\n";
    
    $db->prepare("
        UPDATE chatbot_flows 
        SET 
            response_message = 'Sem problemas! Se mudar de ideia, estamos à disposição. Tenha um ótimo dia! 😊',
            updated_at = NOW()
        WHERE id = 2
    ")->execute();
    
    echo "   ✓ Fluxo ID 2 atualizado\n\n";
    
    // ========================================
    // 3. CRIAR FLUXO: "Sou autônomo"
    // ========================================
    echo "3. Criando fluxo 'Sou autônomo' (ID 3)...\n";
    
    $stmt = $db->query("SELECT id FROM chatbot_flows WHERE id = 3");
    if ($stmt->rowCount() > 0) {
        echo "   ⚠ Fluxo ID 3 já existe, atualizando...\n";
        $db->prepare("
            UPDATE chatbot_flows 
            SET 
                name = 'Prospecção Corretores - Sou Autônomo',
                trigger_type = 'template_button',
                trigger_value = 'Sou autônomo',
                response_type = 'text',
                response_message = 'Perfeito! Aqui está um vídeo rápido mostrando como nossa estrutura ajuda corretores autônomos a captarem e organizarem leads de imóveis:

[LINK_VIDEO_AUTONOMO]

Gostaria de agendar uma demonstração personalizada ou prefere analisar primeiro?',
                is_active = 1,
                priority = 100,
                tenant_id = NULL,
                updated_at = NOW()
            WHERE id = 3
        ")->execute();
        echo "   ✓ Fluxo ID 3 atualizado\n\n";
    } else {
        $db->prepare("
            INSERT INTO chatbot_flows (
                id, name, trigger_type, trigger_value, 
                response_type, response_message, 
                is_active, priority, tenant_id,
                created_at, updated_at
            ) VALUES (
                3, 'Prospecção Corretores - Sou Autônomo', 'template_button', 'Sou autônomo',
                'text', 'Perfeito! Aqui está um vídeo rápido mostrando como nossa estrutura ajuda corretores autônomos a captarem e organizarem leads de imóveis:

[LINK_VIDEO_AUTONOMO]

Gostaria de agendar uma demonstração personalizada ou prefere analisar primeiro?',
                1, 100, NULL,
                NOW(), NOW()
            )
        ")->execute();
        
        echo "   ✓ Fluxo ID 3 criado\n\n";
    }
    
    // ========================================
    // 4. CRIAR FLUXO: "Trabalho em imobiliária"
    // ========================================
    echo "4. Criando fluxo 'Trabalho em imobiliária' (ID 4)...\n";
    
    $stmt = $db->query("SELECT id FROM chatbot_flows WHERE id = 4");
    if ($stmt->rowCount() > 0) {
        echo "   ⚠ Fluxo ID 4 já existe, atualizando...\n";
        $db->prepare("
            UPDATE chatbot_flows 
            SET 
                name = 'Prospecção Corretores - Trabalho em Imobiliária',
                trigger_type = 'template_button',
                trigger_value = 'Trabalho em imobiliária',
                response_type = 'text',
                response_message = 'Excelente! Aqui está um vídeo rápido mostrando como nossa estrutura ajuda imobiliárias a gerenciarem leads e equipes:

[LINK_VIDEO_IMOBILIARIA]

Gostaria de agendar uma demonstração personalizada ou prefere analisar primeiro?',
                is_active = 1,
                priority = 100,
                tenant_id = NULL,
                updated_at = NOW()
            WHERE id = 4
        ")->execute();
        echo "   ✓ Fluxo ID 4 atualizado\n\n";
    } else {
        $db->prepare("
            INSERT INTO chatbot_flows (
                id, name, trigger_type, trigger_value, 
                response_type, response_message, 
                is_active, priority, tenant_id,
                created_at, updated_at
            ) VALUES (
                4, 'Prospecção Corretores - Trabalho em Imobiliária', 'template_button', 'Trabalho em imobiliária',
                'text', 'Excelente! Aqui está um vídeo rápido mostrando como nossa estrutura ajuda imobiliárias a gerenciarem leads e equipes:

[LINK_VIDEO_IMOBILIARIA]

Gostaria de agendar uma demonstração personalizada ou prefere analisar primeiro?',
                1, 100, NULL,
                NOW(), NOW()
            )
        ")->execute();
        
        echo "   ✓ Fluxo ID 4 criado\n\n";
    }
    
    // ========================================
    // 5. CRIAR FLUXO: "Quero agendar demonstração"
    // ========================================
    echo "5. Criando fluxo 'Quero agendar demonstração' (ID 5)...\n";
    
    $stmt = $db->query("SELECT id FROM chatbot_flows WHERE id = 5");
    if ($stmt->rowCount() > 0) {
        echo "   ⚠ Fluxo ID 5 já existe, atualizando...\n";
        $db->prepare("
            UPDATE chatbot_flows 
            SET 
                name = 'Prospecção Corretores - Agendar Demonstração',
                trigger_type = 'template_button',
                trigger_value = 'Quero agendar demonstração',
                response_type = 'forward_to_human',
                response_message = 'Perfeito! Vou transferir você para um especialista que vai te ajudar a agendar a melhor data e horário. Um momento! 😊',
                is_active = 1,
                priority = 100,
                tenant_id = NULL,
                updated_at = NOW()
            WHERE id = 5
        ")->execute();
        echo "   ✓ Fluxo ID 5 atualizado\n\n";
    } else {
        $db->prepare("
            INSERT INTO chatbot_flows (
                id, name, trigger_type, trigger_value, 
                response_type, response_message, 
                is_active, priority, tenant_id,
                created_at, updated_at
            ) VALUES (
                5, 'Prospecção Corretores - Agendar Demonstração', 'template_button', 'Quero agendar demonstração',
                'forward_to_human', 'Perfeito! Vou transferir você para um especialista que vai te ajudar a agendar a melhor data e horário. Um momento! 😊',
                1, 100, NULL,
                NOW(), NOW()
            )
        ")->execute();
        
        echo "   ✓ Fluxo ID 5 criado\n\n";
    }
    
    // ========================================
    // 6. CRIAR FLUXO: "Vou analisar primeiro"
    // ========================================
    echo "6. Criando fluxo 'Vou analisar primeiro' (ID 6)...\n";
    
    $stmt = $db->query("SELECT id FROM chatbot_flows WHERE id = 6");
    if ($stmt->rowCount() > 0) {
        echo "   ⚠ Fluxo ID 6 já existe, atualizando...\n";
        $db->prepare("
            UPDATE chatbot_flows 
            SET 
                name = 'Prospecção Corretores - Vou Analisar',
                trigger_type = 'template_button',
                trigger_value = 'Vou analisar primeiro',
                response_type = 'text',
                response_message = 'Perfeito! Fique à vontade para analisar com calma. Se tiver qualquer dúvida, é só me chamar aqui no WhatsApp. Estamos à disposição! 😊',
                is_active = 1,
                priority = 100,
                tenant_id = NULL,
                updated_at = NOW()
            WHERE id = 6
        ")->execute();
        echo "   ✓ Fluxo ID 6 atualizado\n\n";
    } else {
        $db->prepare("
            INSERT INTO chatbot_flows (
                id, name, trigger_type, trigger_value, 
                response_type, response_message, 
                is_active, priority, tenant_id,
                created_at, updated_at
            ) VALUES (
                6, 'Prospecção Corretores - Vou Analisar', 'template_button', 'Vou analisar primeiro',
                'text', 'Perfeito! Fique à vontade para analisar com calma. Se tiver qualquer dúvida, é só me chamar aqui no WhatsApp. Estamos à disposição! 😊',
                1, 100, NULL,
                NOW(), NOW()
            )
        ")->execute();
        
        echo "   ✓ Fluxo ID 6 criado\n\n";
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
    echo "\nFluxos criados/atualizados:\n";
    echo "  1. Quero conhecer (atualizado)\n";
    echo "  2. Sem interesse (atualizado)\n";
    echo "  3. Sou autônomo (criado)\n";
    echo "  4. Trabalho em imobiliária (criado)\n";
    echo "  5. Quero agendar demonstração (criado)\n";
    echo "  6. Vou analisar primeiro (criado)\n";
    echo "\n⚠️  IMPORTANTE: Não há follow-up após 24h para evitar cobrança duplicada do Meta\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro na migration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
