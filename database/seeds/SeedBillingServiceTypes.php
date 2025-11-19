<?php

/**
 * Seeder: Popula categorias iniciais de tipos de serviço
 * 
 * Cria as categorias padrão para contratos recorrentes:
 * - hospedagem: Hospedagem
 * - saas_imobsites: SaaS ImobSites
 * - saas_cfc: SaaS CFC
 * - outros: Outros serviços
 */
class SeedBillingServiceTypes
{
    public function run(PDO $db): void
    {
        // Verifica se já existem registros
        $stmt = $db->query("SELECT COUNT(*) FROM billing_service_types");
        $count = (int) $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "Categorias já existem. Pulando seed.\n";
            return;
        }

        $categories = [
            [
                'slug' => 'hospedagem',
                'name' => 'Hospedagem',
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'slug' => 'saas_imobsites',
                'name' => 'SaaS ImobSites',
                'is_active' => 1,
                'sort_order' => 2,
            ],
            [
                'slug' => 'saas_cfc',
                'name' => 'SaaS CFC',
                'is_active' => 1,
                'sort_order' => 3,
            ],
            [
                'slug' => 'outros',
                'name' => 'Outros serviços',
                'is_active' => 1,
                'sort_order' => 99,
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO billing_service_types (slug, name, is_active, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");

        foreach ($categories as $category) {
            $stmt->execute([
                $category['slug'],
                $category['name'],
                $category['is_active'],
                $category['sort_order'],
            ]);
        }

        echo "Categorias de serviço criadas com sucesso.\n";
    }
}

