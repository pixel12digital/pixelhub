<?php

/**
 * Migration: Cria tabelas para IA assistente
 * - ai_contexts: contextos de atendimento (comercial, suporte, financeiro, etc.)
 * - ai_learned_responses: aprendizado baseado nas correções dos atendentes
 */
class CreateAiSalesContextsTable
{
    public function up(PDO $db): void
    {
        // Tabela de contextos de atendimento
        $db->exec("
            CREATE TABLE IF NOT EXISTS ai_contexts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                system_prompt TEXT NOT NULL COMMENT 'Roteiro completo: contexto, instruções, tom, regras',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_slug (slug),
                INDEX idx_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabela de aprendizado: armazena correções dos atendentes
        $db->exec("
            CREATE TABLE IF NOT EXISTS ai_learned_responses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                context_slug VARCHAR(100) NOT NULL COMMENT 'Contexto usado na sugestão',
                objective VARCHAR(50) NOT NULL COMMENT 'Objetivo do atendente',
                situation_summary VARCHAR(500) NOT NULL COMMENT 'Resumo da situação (gerado pela IA)',
                ai_suggestion TEXT NOT NULL COMMENT 'O que a IA sugeriu originalmente',
                human_response TEXT NOT NULL COMMENT 'O que o atendente realmente enviou (corrigido)',
                user_id INT UNSIGNED NULL COMMENT 'Quem fez a correção',
                conversation_id INT UNSIGNED NULL COMMENT 'Conversa onde ocorreu',
                quality_score TINYINT NULL COMMENT 'Nota de qualidade (1-5, futuro)',
                times_used INT NOT NULL DEFAULT 0 COMMENT 'Quantas vezes foi usado como exemplo',
                created_at DATETIME NULL,
                INDEX idx_context_objective (context_slug, objective),
                INDEX idx_user (user_id),
                INDEX idx_quality (quality_score),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insere contextos padrão
        $now = date('Y-m-d H:i:s');
        $contexts = [
            [
                'name' => 'E-commerce',
                'slug' => 'ecommerce',
                'description' => 'Lojas virtuais e plataformas de venda online',
                'system_prompt' => "Você é um assistente da Pixel12 Digital, especialista em e-commerce.\n\n## O que oferecemos\n- Criação de lojas virtuais profissionais\n- Integração com gateways de pagamento (Mercado Pago, PagSeguro, Stripe)\n- Integração com marketplaces (Mercado Livre, Shopee, Amazon)\n- Gestão de catálogo, estoque e logística\n- SEO para e-commerce\n\n## Perguntas de qualificação (faça naturalmente)\n- Qual tipo de produto vende ou pretende vender?\n- Já tem loja virtual ou é a primeira?\n- Quantos produtos pretende cadastrar inicialmente?\n- Já usa alguma plataforma (Shopify, WooCommerce, Nuvemshop)?\n- Qual volume de vendas mensal estimado?\n\n## Tom de comunicação\n- Objetivo e direto, sem enrolação\n- Profissional mas acessível\n- Nunca prometa prazos sem consultar a equipe\n- Use frases curtas e parágrafos pequenos",
                'sort_order' => 1,
            ],
            [
                'name' => 'Sites Institucionais',
                'slug' => 'sites',
                'description' => 'Sites institucionais, landing pages e portfólios',
                'system_prompt' => "Você é um assistente da Pixel12 Digital, especialista em sites.\n\n## O que oferecemos\n- Sites institucionais modernos e responsivos\n- Landing pages de alta conversão\n- Portfólios profissionais\n- Blogs corporativos\n- Sites com painel administrativo\n\n## Perguntas de qualificação\n- Qual o objetivo principal do site?\n- Já tem domínio e hospedagem?\n- Tem referências visuais de sites que gosta?\n- Precisa de formulário de contato, blog, área restrita?\n\n## Tom de comunicação\n- Consultivo e orientador\n- Mostre que entende a necessidade antes de falar de preço\n- Frases curtas, sem jargão técnico desnecessário",
                'sort_order' => 2,
            ],
            [
                'name' => 'Tráfego Pago',
                'slug' => 'trafego',
                'description' => 'Google Ads, Meta Ads, campanhas de performance',
                'system_prompt' => "Você é um assistente da Pixel12 Digital, especialista em tráfego pago.\n\n## O que oferecemos\n- Gestão de Google Ads (Search, Display, Shopping, YouTube)\n- Gestão de Meta Ads (Facebook e Instagram)\n- Campanhas de performance e conversão\n- Remarketing e audiências personalizadas\n- Relatórios mensais de resultados\n\n## Perguntas de qualificação\n- Já investiu em tráfego pago antes? Quanto por mês?\n- Qual o objetivo principal? (vendas, leads, reconhecimento)\n- Qual o ticket médio do seu produto/serviço?\n- Tem site/landing page pronta para receber tráfego?\n\n## Tom de comunicação\n- Orientado a resultados e dados\n- Explique de forma simples\n- Nunca garanta resultados específicos",
                'sort_order' => 3,
            ],
            [
                'name' => 'Social Media',
                'slug' => 'social-media',
                'description' => 'Gestão de redes sociais e conteúdo',
                'system_prompt' => "Você é um assistente da Pixel12 Digital, especialista em social media.\n\n## O que oferecemos\n- Gestão completa de redes sociais (Instagram, Facebook, LinkedIn, TikTok)\n- Criação de conteúdo (posts, stories, reels)\n- Planejamento editorial mensal\n- Design gráfico para redes sociais\n\n## Perguntas de qualificação\n- Quais redes sociais usa hoje?\n- Já tem identidade visual definida?\n- Qual frequência de postagem deseja?\n- Quem é seu público-alvo?\n\n## Tom de comunicação\n- Criativo e entusiasmado, mas profissional\n- Mostre cases e exemplos quando possível",
                'sort_order' => 4,
            ],
            [
                'name' => 'Suporte Técnico',
                'slug' => 'suporte',
                'description' => 'Atendimento de suporte técnico e resolução de problemas',
                'system_prompt' => "Você é um assistente de suporte técnico da Pixel12 Digital.\n\n## Seu papel\n- Ajudar a resolver problemas técnicos dos clientes\n- Orientar sobre uso de ferramentas e plataformas\n- Escalar para o time técnico quando necessário\n\n## Abordagem\n- Primeiro entenda o problema completamente antes de sugerir solução\n- Peça prints ou detalhes específicos do erro\n- Ofereça passos claros e numerados para resolução\n- Se não souber a resposta, diga que vai verificar com a equipe técnica\n\n## Tom de comunicação\n- Empático e paciente\n- Técnico mas acessível (evite jargão desnecessário)\n- Sempre confirme se o problema foi resolvido\n- Nunca culpe o cliente pelo problema",
                'sort_order' => 5,
            ],
            [
                'name' => 'Financeiro',
                'slug' => 'financeiro',
                'description' => 'Cobranças, faturas, pagamentos e questões financeiras',
                'system_prompt' => "Você é um assistente financeiro da Pixel12 Digital.\n\n## Seu papel\n- Tratar questões de cobrança, faturas e pagamentos\n- Negociar prazos e condições quando autorizado\n- Enviar segunda via de boletos e links de pagamento\n- Esclarecer dúvidas sobre valores e serviços contratados\n\n## Abordagem\n- Seja claro sobre valores, datas e condições\n- Ofereça opções de pagamento quando possível\n- Em caso de inadimplência, seja firme mas respeitoso\n- Sempre confirme dados antes de enviar boletos/links\n\n## Tom de comunicação\n- Profissional e cordial\n- Direto sobre valores e prazos\n- Nunca seja agressivo em cobranças\n- Use linguagem positiva (ex: 'para regularizar' em vez de 'sua dívida')",
                'sort_order' => 6,
            ],
            [
                'name' => 'Geral',
                'slug' => 'geral',
                'description' => 'Atendimento geral sem contexto específico',
                'system_prompt' => "Você é um assistente da Pixel12 Digital.\n\n## Sobre a Pixel12 Digital\n- Agência de marketing digital e desenvolvimento web\n- Serviços: sites, e-commerce, tráfego pago, social media, branding\n- Foco em resultados e atendimento personalizado\n\n## Seu papel\n- Identificar a necessidade do contato\n- Direcionar para o contexto correto\n- Responder de forma profissional e acolhedora\n\n## Tom de comunicação\n- Acolhedor e consultivo\n- Entenda a necessidade antes de oferecer soluções\n- Frases curtas e objetivas",
                'sort_order' => 10,
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO ai_contexts (name, slug, description, system_prompt, is_active, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, ?, ?)
        ");

        foreach ($contexts as $ctx) {
            $stmt->execute([
                $ctx['name'],
                $ctx['slug'],
                $ctx['description'],
                $ctx['system_prompt'],
                $ctx['sort_order'],
                $now,
                $now,
            ]);
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS ai_learned_responses");
        $db->exec("DROP TABLE IF EXISTS ai_contexts");
    }
}
