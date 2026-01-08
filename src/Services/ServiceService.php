<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar catálogo de serviços pontuais
 * 
 * Gerencia o catálogo de serviços oferecidos pela agência
 * (ex: Criação de Site, Logo, Cartão de Visita, etc.)
 */
class ServiceService
{
    /**
     * Lista todos os serviços com filtros opcionais
     */
    public static function getAllServices(?string $category = null, bool $activeOnly = true): array
    {
        $db = DB::getConnection();
        
        $sql = "SELECT * FROM services WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY category ASC, name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca um serviço por ID
     */
    public static function findService(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Cria um novo serviço
     */
    public static function createService(array $data): int
    {
        $db = DB::getConnection();
        
        // Validações
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('Nome do serviço é obrigatório');
        }
        
        // Processa dados
        $description = !empty($data['description']) ? trim($data['description']) : null;
        $category = !empty($data['category']) ? trim($data['category']) : null;
        $price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
        $estimatedDuration = isset($data['estimated_duration']) && $data['estimated_duration'] !== '' ? (int) $data['estimated_duration'] : null;
        $billingType = !empty($data['billing_type']) ? trim($data['billing_type']) : 'one_time';
        // Valida billing_type
        if (!in_array($billingType, ['one_time', 'recurring'])) {
            $billingType = 'one_time';
        }
        $isActive = isset($data['is_active']) && $data['is_active'] == '1' ? 1 : 0;
        
        // Processa templates JSON
        $tasksTemplate = null;
        if (!empty($data['tasks_template'])) {
            $tasksTemplate = is_string($data['tasks_template']) ? $data['tasks_template'] : json_encode($data['tasks_template']);
        }
        
        $briefingTemplate = null;
        if (!empty($data['briefing_template'])) {
            $briefingTemplate = is_string($data['briefing_template']) ? $data['briefing_template'] : json_encode($data['briefing_template']);
        }
        
        $defaultTimeline = null;
        if (!empty($data['default_timeline'])) {
            $defaultTimeline = is_string($data['default_timeline']) ? $data['default_timeline'] : json_encode($data['default_timeline']);
        }
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO services 
            (name, description, category, price, billing_type, estimated_duration, tasks_template, briefing_template, default_timeline, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $name,
            $description,
            $category,
            $price,
            $billingType,
            $estimatedDuration,
            $tasksTemplate,
            $briefingTemplate,
            $defaultTimeline,
            $isActive,
        ]);
        
        return (int) $db->lastInsertId();
    }

    /**
     * Atualiza um serviço existente
     */
    public static function updateService(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        // Verifica se existe
        $service = self::findService($id);
        if (!$service) {
            throw new \InvalidArgumentException('Serviço não encontrado');
        }
        
        // Validações
        $name = trim($data['name'] ?? $service['name']);
        if (empty($name)) {
            throw new \InvalidArgumentException('Nome do serviço é obrigatório');
        }
        
        // Processa dados
        $description = isset($data['description']) ? (!empty($data['description']) ? trim($data['description']) : null) : $service['description'];
        $category = isset($data['category']) ? (!empty($data['category']) ? trim($data['category']) : null) : $service['category'];
        $price = isset($data['price']) ? ($data['price'] !== '' ? (float) $data['price'] : null) : $service['price'];
        $estimatedDuration = isset($data['estimated_duration']) ? ($data['estimated_duration'] !== '' ? (int) $data['estimated_duration'] : null) : $service['estimated_duration'];
        $billingType = isset($data['billing_type']) ? trim($data['billing_type']) : ($service['billing_type'] ?? 'one_time');
        // Valida billing_type
        if (!in_array($billingType, ['one_time', 'recurring'])) {
            $billingType = 'one_time';
        }
        $isActive = isset($data['is_active']) ? (($data['is_active'] == '1' ? 1 : 0)) : $service['is_active'];
        
        // Processa templates JSON
        $tasksTemplate = null;
        if (isset($data['tasks_template'])) {
            if (!empty($data['tasks_template'])) {
                $tasksTemplate = is_string($data['tasks_template']) ? $data['tasks_template'] : json_encode($data['tasks_template']);
            }
        } else {
            $tasksTemplate = $service['tasks_template'];
        }
        
        $briefingTemplate = null;
        if (isset($data['briefing_template'])) {
            if (!empty($data['briefing_template'])) {
                $briefingTemplate = is_string($data['briefing_template']) ? $data['briefing_template'] : json_encode($data['briefing_template']);
            }
        } else {
            $briefingTemplate = $service['briefing_template'];
        }
        
        $defaultTimeline = null;
        if (isset($data['default_timeline'])) {
            if (!empty($data['default_timeline'])) {
                $defaultTimeline = is_string($data['default_timeline']) ? $data['default_timeline'] : json_encode($data['default_timeline']);
            }
        } else {
            $defaultTimeline = $service['default_timeline'];
        }
        
        // Atualiza no banco
        $stmt = $db->prepare("
            UPDATE services 
            SET name = ?, description = ?, category = ?, price = ?, billing_type = ?, estimated_duration = ?, 
                tasks_template = ?, briefing_template = ?, default_timeline = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name,
            $description,
            $category,
            $price,
            $billingType,
            $estimatedDuration,
            $tasksTemplate,
            $briefingTemplate,
            $defaultTimeline,
            $isActive,
            $id,
        ]);
        
        return true;
    }

    /**
     * Alterna status ativo/inativo
     */
    public static function toggleStatus(int $id): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("UPDATE services SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        return true;
    }

    /**
     * Retorna categorias disponíveis
     */
    public static function getCategories(): array
    {
        return [
            'design' => 'Design',
            'dev' => 'Desenvolvimento',
            'sites_tecnologia' => 'Sites & Tecnologia',
            'marketing' => 'Marketing',
            'consultoria' => 'Consultoria',
            'outros' => 'Outros',
        ];
    }
}

