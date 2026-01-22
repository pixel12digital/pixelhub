<?php

/**
 * Seeder: Popula templates padrão de WhatsApp para ajuste de hospedagem
 * 
 * Cria dois templates:
 * - Ajuste hospedagem – Site/Landing (39,90)
 * - Ajuste hospedagem – E-commerce (59,90)
 */

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    // Autoload manual simples
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../../src/';
        
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

use PixelHub\Core\DB;

echo "=== Seed: Templates WhatsApp - Ajuste Hospedagem ===\n\n";

try {
    $db = DB::getConnection();

    $templates = [
        [
            'name' => 'Ajuste hospedagem – Site/Landing (39,90)',
            'code' => 'hospedagem_site_39_90',
            'category' => 'comercial',
            'description' => 'Aviso de ajuste de valor de hospedagem para site/landing page (R$ 39,90/mês)',
            'content' => "Oi {nome}, tudo bem? 😊\n\nEstou organizando a parte de hospedagem dos sites e, a partir do próximo mês, a hospedagem do seu site/landing page {dominio} passará a ser de R$ 39,90 por mês.\n\nSe você preferir contratar a hospedagem diretamente, precisa ser através deste link para que eu consiga migrar o seu site corretamente e te orientar no processo. Me chama antes para eu te indicar o plano mais compatível com a sua necessidade:\n👉 https://www.hostg.xyz/aff_c?offer_id=6&aff_id=176530\n\nCaso você opte por manter tudo dentro do meu provedor, você continuará recebendo normalmente a cobrança mensal e não precisa tomar nenhuma ação agora.\n\nQualquer dúvida, é só me chamar aqui no WhatsApp. 👍",
            'variables' => ['nome', 'dominio'],
            'is_active' => 1,
        ],
        [
            'name' => 'Ajuste hospedagem – E-commerce (59,90)',
            'code' => 'hospedagem_ecommerce_59_90',
            'category' => 'comercial',
            'description' => 'Aviso de ajuste de valor de hospedagem para e-commerce (R$ 59,90/mês)',
            'content' => "Oi {nome}, tudo bem? 😊\n\nEstou ajustando a infraestrutura de hospedagem das lojas virtuais e, a partir do próximo mês, a hospedagem do seu e-commerce {dominio} passará a ser de R$ 59,90 por mês.\n\nSe você preferir contratar a hospedagem diretamente, precisa ser através deste link para que eu consiga migrar a sua loja com segurança e te orientar na escolha. Me chama antes para eu indicar o plano mais adequado para o seu e-commerce:\n👉 https://www.hostg.xyz/aff_c?offer_id=6&aff_id=176530\n\nSe decidir continuar com tudo dentro do meu provedor, você continuará recebendo normalmente a cobrança mensal e não precisa fazer nada agora.\n\nFicou com alguma dúvida ou quer alinhar o melhor formato pra você? É só me chamar aqui. 👍",
            'variables' => ['nome', 'dominio'],
            'is_active' => 1,
        ],
    ];

    $stmtCheck = $db->prepare("SELECT id FROM whatsapp_templates WHERE code = ?");
    $stmtInsert = $db->prepare("
        INSERT INTO whatsapp_templates 
        (name, code, category, description, content, variables, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmtUpdate = $db->prepare("
        UPDATE whatsapp_templates 
        SET name = ?, category = ?, description = ?, content = ?, variables = ?, is_active = ?, updated_at = NOW()
        WHERE code = ?
    ");

    foreach ($templates as $template) {
        // Verifica se já existe pelo code
        $stmtCheck->execute([$template['code']]);
        $existing = $stmtCheck->fetch();

        $variablesJson = json_encode($template['variables']);

        if ($existing) {
            // Atualiza se já existir
            $stmtUpdate->execute([
                $template['name'],
                $template['category'],
                $template['description'],
                $template['content'],
                $variablesJson,
                $template['is_active'],
                $template['code'],
            ]);
            echo "✓ Template '{$template['name']}' atualizado (ID: {$existing['id']})\n";
        } else {
            // Insere se não existir
            $stmtInsert->execute([
                $template['name'],
                $template['code'],
                $template['category'],
                $template['description'],
                $template['content'],
                $variablesJson,
                $template['is_active'],
            ]);
            $newId = $db->lastInsertId();
            echo "✓ Template '{$template['name']}' criado (ID: {$newId})\n";
        }
    }

    echo "\n✓ Seed concluído!\n\n";

} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    error_log("Erro no seed de templates WhatsApp: " . $e->getMessage());
    exit(1);
}

