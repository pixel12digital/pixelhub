<?php

/**
 * Seed: Motivos padrão de perda de oportunidades
 * 
 * Baseado em padrões de CRM comercial (HubSpot, Pipedrive, RD Station)
 */
class SeedOpportunityLostReasons
{
    public function run(PDO $db): void
    {
        $reasons = [
            // Categoria: Contato/Comunicação
            [
                'label' => 'Lead não retornou contato',
                'slug' => 'no_response',
                'category' => 'contact',
                'description' => 'Lead não respondeu às tentativas de contato (mensagens, ligações, e-mails)',
                'display_order' => 10
            ],
            [
                'label' => 'Sem contato/Telefone inválido',
                'slug' => 'invalid_contact',
                'category' => 'contact',
                'description' => 'Não foi possível estabelecer contato (telefone errado, bloqueado, inexistente)',
                'display_order' => 20
            ],
            [
                'label' => 'Lead não qualificado',
                'slug' => 'not_qualified',
                'category' => 'contact',
                'description' => 'Lead não tem perfil adequado para o serviço (fora do ICP)',
                'display_order' => 30
            ],
            
            // Categoria: Preço/Orçamento
            [
                'label' => 'Preço muito alto',
                'slug' => 'price_too_high',
                'category' => 'price',
                'description' => 'Cliente considerou o valor acima do orçamento disponível',
                'display_order' => 40
            ],
            [
                'label' => 'Sem orçamento no momento',
                'slug' => 'no_budget',
                'category' => 'price',
                'description' => 'Cliente não tem orçamento disponível para investir agora',
                'display_order' => 50
            ],
            
            // Categoria: Concorrência
            [
                'label' => 'Fechou com concorrente',
                'slug' => 'competitor',
                'category' => 'competition',
                'description' => 'Cliente optou por contratar outra empresa/fornecedor',
                'display_order' => 60
            ],
            [
                'label' => 'Já possui solução similar',
                'slug' => 'has_solution',
                'category' => 'competition',
                'description' => 'Cliente já tem solução interna ou contratada que atende a necessidade',
                'display_order' => 70
            ],
            
            // Categoria: Timing/Momento
            [
                'label' => 'Adiou a decisão',
                'slug' => 'postponed',
                'category' => 'timing',
                'description' => 'Cliente decidiu adiar o projeto/contratação para outro momento',
                'display_order' => 80
            ],
            [
                'label' => 'Perdeu urgência/prioridade',
                'slug' => 'lost_priority',
                'category' => 'timing',
                'description' => 'Projeto deixou de ser prioridade para o cliente',
                'display_order' => 90
            ],
            
            // Categoria: Produto/Serviço
            [
                'label' => 'Serviço não atende necessidade',
                'slug' => 'service_mismatch',
                'category' => 'service',
                'description' => 'Nosso serviço não resolve o problema específico do cliente',
                'display_order' => 100
            ],
            [
                'label' => 'Falta de recursos/funcionalidades',
                'slug' => 'missing_features',
                'category' => 'service',
                'description' => 'Cliente precisa de funcionalidades que não oferecemos',
                'display_order' => 110
            ],
            
            // Categoria: Processo/Experiência
            [
                'label' => 'Processo de venda muito longo',
                'slug' => 'long_sales_cycle',
                'category' => 'process',
                'description' => 'Cliente desistiu devido ao tempo do processo comercial',
                'display_order' => 120
            ],
            [
                'label' => 'Falta de confiança/credibilidade',
                'slug' => 'lack_trust',
                'category' => 'process',
                'description' => 'Cliente não se sentiu confiante para fechar negócio',
                'display_order' => 130
            ],
            
            // Categoria: Outros
            [
                'label' => 'Mudança de estratégia do cliente',
                'slug' => 'strategy_change',
                'category' => 'other',
                'description' => 'Cliente mudou direcionamento estratégico do negócio',
                'display_order' => 140
            ],
            [
                'label' => 'Problemas internos do cliente',
                'slug' => 'client_issues',
                'category' => 'other',
                'description' => 'Cliente enfrentou problemas internos (financeiros, reestruturação, etc)',
                'display_order' => 150
            ],
            [
                'label' => 'Outro motivo',
                'slug' => 'other',
                'category' => 'other',
                'description' => 'Motivo não listado nas opções acima (detalhar em observações)',
                'display_order' => 999
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO opportunity_lost_reasons 
            (label, slug, category, description, is_active, display_order)
            VALUES (?, ?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                category = VALUES(category),
                description = VALUES(description),
                display_order = VALUES(display_order)
        ");

        foreach ($reasons as $reason) {
            $stmt->execute([
                $reason['label'],
                $reason['slug'],
                $reason['category'],
                $reason['description'],
                $reason['display_order']
            ]);
        }

        echo "✓ Seed de motivos de perda executado com sucesso (" . count($reasons) . " motivos)\n";
    }
}
