<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar pedidos de serviço (service_orders)
 * 
 * Pedidos são criados ANTES do projeto. Cliente preenche dados cadastrais,
 * briefing e aprova condições. Após aprovação, converte automaticamente em projeto.
 */
class ServiceOrderService
{
    /**
     * Cria um novo pedido de serviço
     * 
     * @param array $data Dados do pedido
     * @return int ID do pedido criado
     */
    public static function createOrder(array $data): int
    {
        $db = DB::getConnection();
        
        // Validações
        $serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        if (empty($serviceId)) {
            throw new \InvalidArgumentException('ID do serviço é obrigatório');
        }
        
        // Verifica se o serviço existe
        $service = ServiceService::findService($serviceId);
        if (!$service) {
            throw new \InvalidArgumentException('Serviço não encontrado');
        }
        
        // Processa dados
        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;
        $contractValue = !empty($data['contract_value']) ? (float) $data['contract_value'] : null;
        $paymentCondition = !empty($data['payment_condition']) ? trim($data['payment_condition']) : null;
        $paymentMethod = !empty($data['payment_method']) ? trim($data['payment_method']) : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $createdBy = !empty($data['created_by']) ? (int) $data['created_by'] : null;
        
        // Gera token único para o link público
        $token = self::generateUniqueToken();
        
        // Define expiração (30 dias por padrão)
        $expiresAt = new \DateTime('+30 days');
        
        // Obtém service_slug do serviço
        $serviceSlug = $service['slug'] ?? null;
        
        // Se não tiver slug no serviço, infere do nome ou usa padrão
        if (empty($serviceSlug)) {
            $serviceName = strtolower($service['name'] ?? '');
            if (stripos($serviceName, 'cartão') !== false || stripos($serviceName, 'card') !== false) {
                $serviceSlug = 'business_card_express';
            }
        }
        
        // Define status inicial
        $status = 'draft'; // Conforme especificação
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO service_orders 
            (service_id, service_slug, tenant_id, contract_value, payment_condition, payment_method, 
             token, expires_at, status, notes, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $serviceId,
            $serviceSlug,
            $tenantId,
            $contractValue,
            $paymentCondition,
            $paymentMethod,
            $token,
            $expiresAt->format('Y-m-d H:i:s'),
            $status,
            $notes,
            $createdBy,
        ]);
        
        $orderId = (int) $db->lastInsertId();
        
        // Log
        error_log(sprintf(
            '[ServiceOrder] order_created: order_id=%d, service_id=%d, service_slug=%s, status=%s',
            $orderId,
            $serviceId,
            $serviceSlug ?: 'NULL',
            $status
        ));
        
        // Se o serviço for business_card_express e status for active/paid, cria chat automaticamente
        if ($serviceSlug === 'business_card_express' && ($status === 'active' || $status === 'awaiting_payment')) {
            try {
                ServiceChatService::createThread($orderId, $tenantId);
                error_log(sprintf(
                    '[ServiceOrder] chat_thread_created_automatically: order_id=%d',
                    $orderId
                ));
            } catch (\Exception $e) {
                error_log(sprintf(
                    '[ServiceOrder] erro_ao_criar_chat_automatico: order_id=%d, erro=%s',
                    $orderId,
                    $e->getMessage()
                ));
            }
        }
        
        return $orderId;
    }
    
    /**
     * Busca um pedido por ID
     * 
     * @param int $id ID do pedido
     * @return array|null Pedido ou null se não encontrado
     */
    public static function findOrder(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT o.*, 
                   s.name as service_name,
                   s.description as service_description,
                   s.price as service_price,
                   s.estimated_duration,
                   s.tasks_template,
                   s.briefing_template,
                   t.name as tenant_name,
                   t.email as tenant_email,
                   t.phone as tenant_phone,
                   p.name as project_name,
                   u.name as created_by_name
            FROM service_orders o
            LEFT JOIN services s ON o.service_id = s.id
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN projects p ON o.project_id = p.id
            LEFT JOIN users u ON o.created_by = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca um pedido por token (para link público)
     * 
     * @param string $token Token do pedido
     * @return array|null Pedido ou null se não encontrado
     */
    public static function findOrderByToken(string $token): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT o.*, 
                   s.name as service_name,
                   s.description as service_description,
                   s.price as service_price,
                   s.estimated_duration,
                   s.tasks_template,
                   s.briefing_template,
                   t.name as tenant_name,
                   t.email as tenant_email,
                   t.phone as tenant_phone,
                   t.person_type,
                   t.cpf_cnpj,
                   t.address_cep,
                   t.address_street,
                   t.address_number,
                   t.address_complement,
                   t.address_neighborhood,
                   t.address_city,
                   t.address_state
            FROM service_orders o
            LEFT JOIN services s ON o.service_id = s.id
            LEFT JOIN tenants t ON o.tenant_id = t.id
            WHERE o.token = ?
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        // Verifica se expirou
        if ($result && !empty($result['expires_at'])) {
            $expiresAt = new \DateTime($result['expires_at']);
            $now = new \DateTime();
            if ($now > $expiresAt) {
                return null; // Link expirado
            }
        }
        
        return $result ?: null;
    }
    
    /**
     * Salva dados do cliente (Etapa 1 do wizard)
     * 
     * @param int $orderId ID do pedido
     * @param array $clientData Dados do cliente
     * @return bool Sucesso da operação
     */
    public static function saveClientData(int $orderId, array $clientData): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o pedido existe
        $order = self::findOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        // Valida dados mínimos
        // Se não vier person_type, tenta inferir do CPF/CNPJ
        $cpfCnpjRaw = $clientData['cpf_cnpj'] ?? '';
        $cpfCnpjClean = preg_replace('/[^0-9]/', '', $cpfCnpjRaw);
        $personType = trim($clientData['person_type'] ?? '');
        
        // Se não informou, infere: 11 dígitos = PF, 14 = PJ
        if (empty($personType)) {
            $personType = strlen($cpfCnpjClean) === 11 ? 'pf' : 'pj';
        }
        
        if (!in_array($personType, ['pf', 'pj'])) {
            throw new \InvalidArgumentException('Tipo de pessoa inválido');
        }
        
        $name = trim($clientData['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }
        
        // Valida email
        $email = trim($clientData['email'] ?? '');
        if (empty($email)) {
            throw new \InvalidArgumentException('Email é obrigatório');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }
        
        // Valida telefone (DDD + 8 ou 9 dígitos)
        $phone = trim($clientData['phone'] ?? '');
        if (empty($phone)) {
            throw new \InvalidArgumentException('Telefone é obrigatório');
        }
        $phoneClean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phoneClean) < 10 || strlen($phoneClean) > 11) {
            throw new \InvalidArgumentException('Telefone inválido. Use o formato (DD) 00000-0000');
        }
        
        $cpfCnpj = preg_replace('/[^0-9]/', '', $clientData['cpf_cnpj'] ?? '');
        if (empty($cpfCnpj)) {
            throw new \InvalidArgumentException('CPF/CNPJ é obrigatório');
        }
        
        // Valida endereço - cidade e estado são obrigatórios APENAS se houver dados de endereço
        $address = $clientData['address'] ?? [];
        $hasAddressData = !empty($address) && (isset($address['cep']) || isset($address['city']) || isset($address['state']));
        
        if ($hasAddressData) {
            $city = trim($address['city'] ?? '');
            $state = trim($address['state'] ?? '');
            
            if (empty($city)) {
                throw new \InvalidArgumentException('Cidade é obrigatória');
            }
            
            if (empty($state)) {
                throw new \InvalidArgumentException('Estado é obrigatório');
            }
        }
        
        // Se tenant_id já existe, atualiza o tenant
        if (!empty($order['tenant_id'])) {
            $tenantId = self::updateTenantFromClientData((int) $order['tenant_id'], $clientData);
        } else {
            // Verifica se cliente já existe no sistema
            $existingTenant = self::findTenantByCpfCnpj($cpfCnpj);
            
            if ($existingTenant) {
                // Atualiza tenant existente
                $tenantId = self::updateTenantFromClientData($existingTenant['id'], $clientData);
            } else {
                // Cria novo tenant
                $tenantId = self::createTenantFromClientData($clientData);
            }
        }
        
        // Se já tem dados salvos, faz merge (preserva dados anteriores)
        $existingClientData = null;
        if (!empty($order['client_data'])) {
            $existingClientData = json_decode($order['client_data'], true);
        }
        
        // Faz merge: dados novos sobrescrevem dados antigos
        $mergedClientData = $existingClientData ?? [];
        foreach ($clientData as $key => $value) {
            if ($key === 'address' && is_array($value)) {
                // Para endereço, faz merge dos campos individuais
                $mergedClientData['address'] = array_merge($mergedClientData['address'] ?? [], $value);
            } else {
                $mergedClientData[$key] = $value;
            }
        }
        
        // Salva client_data como JSON (backup) e atualiza tenant_id
        $clientDataJson = json_encode($mergedClientData, JSON_UNESCAPED_UNICODE);
        
        $stmt = $db->prepare("
            UPDATE service_orders 
            SET tenant_id = ?, 
                client_data = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$tenantId, $clientDataJson, $orderId]);
        
        return true;
    }
    
    /**
     * Salva briefing (Etapa 2 do wizard)
     * 
     * @param int $orderId ID do pedido
     * @param array $briefingData Respostas do briefing
     * @return bool Sucesso da operação
     */
    public static function saveBriefing(int $orderId, array $briefingData): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o pedido existe
        $order = self::findOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        // Salva briefing_data como JSON
        $briefingDataJson = json_encode($briefingData, JSON_UNESCAPED_UNICODE);
        
        $stmt = $db->prepare("
            UPDATE service_orders 
            SET briefing_data = ?,
                briefing_status = 'completed',
                briefing_completed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$briefingDataJson, $orderId]);
        
        return true;
    }
    
    /**
     * Aprova pedido (Etapa 3 do wizard)
     * 
     * @param int $orderId ID do pedido
     * @param array $paymentData Dados de pagamento
     * @return bool Sucesso da operação
     */
    public static function approveOrder(int $orderId, array $paymentData): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o pedido existe
        $order = self::findOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        // Verifica se já foi convertido
        if ($order['status'] === 'converted') {
            throw new \InvalidArgumentException('Este pedido já foi convertido em projeto');
        }
        
        // Atualiza dados de pagamento
        $paymentCondition = !empty($paymentData['payment_condition']) ? trim($paymentData['payment_condition']) : null;
        $paymentMethod = !empty($paymentData['payment_method']) ? trim($paymentData['payment_method']) : null;
        
        // Status muda para 'active' conforme especificação (não 'approved')
        $stmt = $db->prepare("
            UPDATE service_orders 
            SET status = 'active',
                payment_condition = ?,
                payment_method = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$paymentCondition, $paymentMethod, $orderId]);
        
        // Log
        error_log(sprintf(
            '[ServiceOrder] order_approved: order_id=%d, status=active',
            $orderId
        ));
        
        // Cria chat automaticamente se for business_card_express
        $serviceSlug = $order['service_slug'] ?? null;
        if (empty($serviceSlug) && !empty($order['service_id'])) {
            $service = ServiceService::findService((int) $order['service_id']);
            if ($service && !empty($service['slug'])) {
                $serviceSlug = $service['slug'];
            }
        }
        
        if ($serviceSlug === 'business_card_express') {
            try {
                ServiceChatService::ensureThreadForOrder($orderId);
                error_log(sprintf(
                    '[ServiceOrder] chat_thread_created_automatically_after_payment: order_id=%d',
                    $orderId
                ));
            } catch (\Exception $e) {
                error_log(sprintf(
                    '[ServiceOrder] erro_ao_criar_chat_apos_pagamento: order_id=%d, erro=%s',
                    $orderId,
                    $e->getMessage()
                ));
            }
        }
        
        // Converte automaticamente em projeto (se necessário)
        // Por enquanto, para cartão de visita express, não converte automaticamente em projeto
        // O cartão é gerado diretamente do pedido via chat
        
        return true;
    }
    
    /**
     * Converte pedido em projeto automaticamente
     * 
     * @param int $orderId ID do pedido
     * @return int ID do projeto criado
     */
    public static function convertToProject(int $orderId): int
    {
        $db = DB::getConnection();
        
        // Busca pedido
        $order = self::findOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        // Verifica se já foi convertido
        if (!empty($order['project_id'])) {
            return (int) $order['project_id'];
        }
        
        // Verifica se tem tenant_id
        if (empty($order['tenant_id'])) {
            throw new \RuntimeException('Pedido não possui cliente vinculado. Complete a Etapa 1 primeiro.');
        }
        
        // Verifica se briefing foi preenchido
        if (empty($order['briefing_data']) || $order['briefing_status'] !== 'completed') {
            throw new \RuntimeException('Briefing não foi preenchido. Complete a Etapa 2 primeiro.');
        }
        
        // Cria projeto
        $projectName = $order['service_name'] . ' - ' . $order['tenant_name'];
        
        $projectData = [
            'name' => $projectName,
            'tenant_id' => $order['tenant_id'],
            'service_id' => $order['service_id'],
            'type' => 'cliente',
            'status' => 'ativo',
            'is_customer_visible' => 1,
            'description' => $order['service_description'],
        ];
        
        // Calcula due_date baseado no estimated_duration
        if (!empty($order['estimated_duration'])) {
            $dueDate = new \DateTime('+' . (int) $order['estimated_duration'] . ' days');
            $projectData['due_date'] = $dueDate->format('Y-m-d');
        }
        
        $projectId = ProjectService::createProject($projectData);
        
        // Aplica tasks_template se existir
        if (!empty($order['tasks_template'])) {
            $tasksTemplate = json_decode($order['tasks_template'], true);
            if (is_array($tasksTemplate) && !empty($tasksTemplate['tasks'])) {
                self::applyTasksTemplate($projectId, $tasksTemplate);
            }
        }
        
        // Salva briefing_data no projeto (se houver campo briefing_data em projects)
        // Por enquanto, podemos salvar em uma tabela separada ou usar notes
        
        // Atualiza pedido com project_id
        $stmt = $db->prepare("
            UPDATE service_orders 
            SET project_id = ?,
                status = 'converted',
                converted_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$projectId, $orderId]);
        
        // TODO: Gerar fatura no Asaas se necessário
        
        return $projectId;
    }
    
    /**
     * Aplica tasks_template ao projeto
     * 
     * @param int $projectId ID do projeto
     * @param array $tasksTemplate Template de tarefas
     * @return void
     */
    private static function applyTasksTemplate(int $projectId, array $tasksTemplate): void
    {
        $tasks = $tasksTemplate['tasks'] ?? [];
        
        foreach ($tasks as $taskData) {
            $title = $taskData['title'] ?? '';
            if (empty($title)) {
                continue;
            }
            
            $task = [
                'project_id' => $projectId,
                'title' => $title,
                'description' => $taskData['description'] ?? null,
                'status' => $taskData['status'] ?? 'backlog',
                'order' => $taskData['order'] ?? 0,
            ];
            
            $taskId = TaskService::createTask($task);
            
            // Aplica checklist se existir
            $checklist = $taskData['checklist'] ?? [];
            if (!empty($checklist) && is_array($checklist)) {
                foreach ($checklist as $item) {
                    if (is_string($item)) {
                        TaskChecklistService::addItem($taskId, $item);
                    } elseif (is_array($item) && !empty($item['label'])) {
                        TaskChecklistService::addItem($taskId, $item['label']);
                    }
                }
            }
        }
    }
    
    /**
     * Busca tenant por CPF/CNPJ
     * 
     * @param string $cpfCnpj CPF/CNPJ sem formatação
     * @return array|null Tenant ou null se não encontrado
     */
    private static function findTenantByCpfCnpj(string $cpfCnpj): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM tenants 
            WHERE cpf_cnpj = ? OR document = ?
            LIMIT 1
        ");
        $stmt->execute([$cpfCnpj, $cpfCnpj]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Cria tenant a partir de client_data
     * 
     * @param array $clientData Dados do cliente
     * @return int ID do tenant criado
     */
    private static function createTenantFromClientData(array $clientData): int
    {
        $db = DB::getConnection();
        
        $personType = trim($clientData['person_type'] ?? 'pf');
        $name = trim($clientData['name'] ?? '');
        $cpfCnpj = preg_replace('/[^0-9]/', '', $clientData['cpf_cnpj'] ?? '');
        $email = !empty($clientData['email']) ? trim($clientData['email']) : null;
        $phone = !empty($clientData['phone']) ? trim($clientData['phone']) : null;
        
        // Dados de endereço
        $address = $clientData['address'] ?? [];
        $cep = !empty($address['cep']) ? preg_replace('/[^0-9-]/', '', $address['cep']) : null;
        $street = !empty($address['street']) ? trim($address['street']) : null;
        $number = !empty($address['number']) ? trim($address['number']) : null;
        $complement = !empty($address['complement']) ? trim($address['complement']) : null;
        $neighborhood = !empty($address['neighborhood']) ? trim($address['neighborhood']) : null;
        $city = !empty($address['city']) ? trim($address['city']) : null;
        $state = !empty($address['state']) ? strtoupper(trim($address['state'])) : null;
        
        // Para PJ
        $razaoSocial = null;
        $nomeFantasia = null;
        if ($personType === 'pj') {
            $razaoSocial = !empty($clientData['razao_social']) ? trim($clientData['razao_social']) : $name;
            $nomeFantasia = !empty($clientData['nome_fantasia']) ? trim($clientData['nome_fantasia']) : $name;
        }
        
        $stmt = $db->prepare("
            INSERT INTO tenants 
            (person_type, name, cpf_cnpj, document, email, phone,
             razao_social, nome_fantasia,
             address_cep, address_street, address_number, address_complement,
             address_neighborhood, address_city, address_state,
             status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        
        $stmt->execute([
            $personType,
            $name,
            $cpfCnpj,
            $cpfCnpj, // document (legado)
            $email,
            $phone,
            $razaoSocial,
            $nomeFantasia,
            $cep,
            $street,
            $number,
            $complement,
            $neighborhood,
            $city,
            $state,
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Atualiza tenant a partir de client_data
     * 
     * @param int $tenantId ID do tenant
     * @param array $clientData Dados do cliente
     * @return int ID do tenant (mesmo)
     */
    private static function updateTenantFromClientData(int $tenantId, array $clientData): int
    {
        $db = DB::getConnection();
        
        // Busca tenant atual
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        if (!$tenant) {
            throw new \InvalidArgumentException('Cliente não encontrado');
        }
        
        // Prepara dados para atualização (só atualiza campos que vieram preenchidos)
        $updates = [];
        $params = [];
        
        if (!empty($clientData['name'])) {
            $updates[] = "name = ?";
            $params[] = trim($clientData['name']);
        }
        
        if (!empty($clientData['email'])) {
            $updates[] = "email = ?";
            $params[] = trim($clientData['email']);
        }
        
        if (!empty($clientData['phone'])) {
            $updates[] = "phone = ?";
            $params[] = trim($clientData['phone']);
        }
        
        // Endereço
        $address = $clientData['address'] ?? [];
        if (!empty($address['cep'])) {
            $updates[] = "address_cep = ?";
            $params[] = preg_replace('/[^0-9-]/', '', $address['cep']);
        }
        if (!empty($address['street'])) {
            $updates[] = "address_street = ?";
            $params[] = trim($address['street']);
        }
        if (!empty($address['number'])) {
            $updates[] = "address_number = ?";
            $params[] = trim($address['number']);
        }
        if (!empty($address['complement'])) {
            $updates[] = "address_complement = ?";
            $params[] = trim($address['complement']);
        }
        if (!empty($address['neighborhood'])) {
            $updates[] = "address_neighborhood = ?";
            $params[] = trim($address['neighborhood']);
        }
        if (!empty($address['city'])) {
            $updates[] = "address_city = ?";
            $params[] = trim($address['city']);
        }
        if (!empty($address['state'])) {
            $updates[] = "address_state = ?";
            $params[] = strtoupper(trim($address['state']));
        }
        
        if (empty($updates)) {
            return $tenantId; // Nada para atualizar
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $tenantId;
        
        $sql = "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $tenantId;
    }
    
    /**
     * Gera um token único para o link público
     * 
     * @return string Token único
     */
    private static function generateUniqueToken(): string
    {
        $db = DB::getConnection();
        
        do {
            // Gera token aleatório de 32 caracteres (64 em hex)
            $token = bin2hex(random_bytes(32));
            
            // Verifica se já existe
            $stmt = $db->prepare("SELECT id FROM service_orders WHERE token = ?");
            $stmt->execute([$token]);
            $exists = $stmt->fetch();
        } while ($exists);
        
        return $token;
    }
    
    /**
     * Gera o link público para preenchimento do pedido
     * 
     * @param string $token Token do pedido
     * @return string URL completa do link
     */
    public static function generatePublicLink(string $token): string
    {
        // Obtém a URL base do servidor
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $baseUrl = $protocol . '://' . $host . $basePath;
        
        return rtrim($baseUrl, '/') . '/client-portal/orders/' . $token;
    }
    
    /**
     * Lista pedidos com filtros
     * 
     * @param array $filters Filtros (service_id, tenant_id, status)
     * @return array Lista de pedidos
     */
    public static function listOrders(array $filters = []): array
    {
        $db = DB::getConnection();
        
        $where = [];
        $params = [];
        
        if (!empty($filters['service_id'])) {
            $where[] = "o.service_id = ?";
            $params[] = (int) $filters['service_id'];
        }
        
        if (!empty($filters['tenant_id'])) {
            $where[] = "o.tenant_id = ?";
            $params[] = (int) $filters['tenant_id'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "o.status = ?";
            $params[] = trim($filters['status']);
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT o.*, 
                   s.name as service_name,
                   t.name as tenant_name,
                   t.email as tenant_email,
                   p.name as project_name,
                   u.name as created_by_name
            FROM service_orders o
            LEFT JOIN services s ON o.service_id = s.id
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN projects p ON o.project_id = p.id
            LEFT JOIN users u ON o.created_by = u.id
            {$whereClause}
            ORDER BY o.created_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Valida se o briefing está completo (intake_ready)
     * 
     * @param int $orderId ID do pedido
     * @return array ['ready' => bool, 'missing' => array, 'missingStep' => string|null]
     */
    public static function validateIntakeReady(int $orderId): array
    {
        $order = self::findOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        $missing = [];
        $missingStep = null;
        
        // Decodifica dados de service_orders
        $clientData = !empty($order['client_data']) ? json_decode($order['client_data'], true) : [];
        $briefingData = !empty($order['briefing_data']) ? json_decode($order['briefing_data'], true) : [];
        
        // TAMBÉM verifica service_intakes (dados do chat)
        $intake = \PixelHub\Services\BusinessCardIntakeService::findIntakeByOrder($orderId);
        $intakeData = $intake ? json_decode($intake['data_json'], true) : [];
        
        // Merge dos dados: service_orders tem prioridade, mas usa intake como fallback
        if (empty($clientData['name']) && !empty($intakeData['full_name'])) {
            $clientData['name'] = $intakeData['full_name'];
        }
        if (empty($clientData['phone']) && !empty($intakeData['phone_whatsapp'])) {
            $clientData['phone'] = $intakeData['phone_whatsapp'];
        }
        if (empty($clientData['email']) && !empty($intakeData['email'])) {
            $clientData['email'] = $intakeData['email'];
        }
        
        // Merge briefing data
        if (empty($briefingData['q_empresa_nome']) && !empty($intakeData['company'])) {
            $briefingData['q_empresa_nome'] = $intakeData['company'];
        }
        if (empty($briefingData['q_segment']) && !empty($intakeData['segment'])) {
            $briefingData['q_segment'] = $intakeData['segment'];
        }
        if (empty($briefingData['q_cores_preferencia']) && !empty($intakeData['style']['background'])) {
            $briefingData['q_cores_preferencia'] = ucfirst($intakeData['style']['background']);
        }
        
        // Merge back_side se necessário
        if (empty($briefingData['back_side']) && !empty($intakeData)) {
            $backSideFields = [];
            $backSideInclude = [];
            
            if (!empty($intakeData['full_name'])) {
                $backSideFields['nome'] = $intakeData['full_name'];
                $backSideInclude[] = 'nome';
            }
            if (!empty($intakeData['job_title'])) {
                $backSideFields['cargo'] = $intakeData['job_title'];
                $backSideInclude[] = 'cargo';
            }
            if (!empty($intakeData['phone_whatsapp'])) {
                $backSideFields['whatsapp'] = $intakeData['phone_whatsapp'];
                $backSideInclude[] = 'whatsapp';
            }
            if (!empty($intakeData['email'])) {
                $backSideFields['email'] = $intakeData['email'];
                $backSideInclude[] = 'email';
            }
            
            if (!empty($backSideFields)) {
                $briefingData['back_side'] = [
                    'include' => $backSideInclude,
                    'fields' => $backSideFields
                ];
            }
            
            // QR Code
            if (!empty($intakeData['qr']) && is_array($intakeData['qr']) && !empty($intakeData['qr']['enabled'])) {
                if (!isset($briefingData['back_side'])) {
                    $briefingData['back_side'] = ['include' => [], 'fields' => []];
                }
                $briefingData['back_side']['include'][] = 'qr_code';
                $briefingData['back_side']['qr'] = [
                    'type' => $intakeData['qr']['target'] ?? 'whatsapp',
                    'value' => $intakeData['qr']['value'] ?? ''
                ];
            }
        }
        
        // Validações obrigatórias básicas
        if (empty($clientData['name']) && empty($briefingData['q_empresa_nome']) && empty($intakeData['full_name'])) {
            $missing[] = 'Nome completo';
            $missingStep = 'client_data';
        }
        
        if (empty($clientData['phone']) && empty($briefingData['back_side']['fields']['whatsapp']) && empty($intakeData['phone_whatsapp'])) {
            $missing[] = 'WhatsApp ou telefone';
            if (!$missingStep) $missingStep = 'client_data';
        }
        
        if (empty($clientData['email']) && empty($briefingData['back_side']['fields']['email']) && empty($intakeData['email'])) {
            $missing[] = 'E-mail';
            if (!$missingStep) $missingStep = 'client_data';
        }
        
        // Validações específicas para cartão de visita
        $service = ServiceService::findService($order['service_id']);
        $serviceCode = $service['code'] ?? '';
        $serviceSlug = $service['slug'] ?? '';
        
        if ($serviceCode === 'business_card' || $serviceSlug === 'business_card_express') {
            // Segmento obrigatório
            if (empty($briefingData['segment']) && empty($briefingData['q_segment']) && empty($intakeData['segment'])) {
                $missing[] = 'Segmento do negócio';
                if (!$missingStep) $missingStep = 'briefing';
            }
            
            // Preferência de cores obrigatória
            if (empty($briefingData['cores_preferencia']) && empty($briefingData['q_cores_preferencia']) && empty($intakeData['style']['background'])) {
                $missing[] = 'Preferência de cores (Claras/Escuras/Neutras/Coloridas)';
                if (!$missingStep) $missingStep = 'briefing';
            }
            
            // Se QR foi selecionado, deve estar configurado
            $hasQrInBackSide = !empty($briefingData['back_side']['include']) && 
                is_array($briefingData['back_side']['include']) &&
                in_array('qr_code', $briefingData['back_side']['include']);
            $hasQrInIntake = !empty($intakeData['qr']) && is_array($intakeData['qr']) && !empty($intakeData['qr']['enabled']);
            
            if ($hasQrInBackSide || $hasQrInIntake) {
                $qrValue = $briefingData['back_side']['qr']['value'] ?? $intakeData['qr']['value'] ?? null;
                if (empty($qrValue)) {
                    $missing[] = 'Configuração do QR Code';
                    if (!$missingStep) $missingStep = 'briefing';
                }
            }
        }
        
        return [
            'ready' => empty($missing),
            'missing' => $missing,
            'missingStep' => $missingStep
        ];
    }
    
    /**
     * Atualiza status do pedido
     * 
     * @param int $orderId ID do pedido
     * @param string $status Novo status
     * @return bool True se atualizado com sucesso
     */
    public static function updateOrderStatus(int $orderId, string $status): bool
    {
        $db = DB::getConnection();
        
        // Valida status
        $allowedStatuses = ['draft', 'client_data_filled', 'briefing_filled', 'approved', 'design_generating', 'design_review', 'converted'];
        if (!in_array($status, $allowedStatuses)) {
            throw new \InvalidArgumentException('Status inválido: ' . $status);
        }
        
        $stmt = $db->prepare("UPDATE service_orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        
        return true;
    }
    
    /**
     * Exclui um pedido e todos os dados relacionados
     * 
     * @param int $id ID do pedido
     * @return bool True se excluído com sucesso
     * @throws \Exception Se o pedido não for encontrado ou já foi convertido
     */
    public static function deleteOrder(int $id): bool
    {
        $db = DB::getConnection();
        
        // Busca o pedido primeiro
        $order = self::findOrder($id);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        // Não permite excluir se já foi convertido em projeto
        if ($order['status'] === 'converted' && !empty($order['project_id'])) {
            throw new \Exception('Não é possível excluir um pedido que já foi convertido em projeto');
        }
        
        // Inicia transação para garantir consistência
        $db->beginTransaction();
        
        try {
            // Remove arquivos relacionados se houver
            // (arquivos podem estar em briefing_data como referências)
            if (!empty($order['briefing_data'])) {
                $briefingData = json_decode($order['briefing_data'], true);
                if (is_array($briefingData)) {
                    foreach ($briefingData as $key => $value) {
                        // Se o valor contém um caminho de arquivo ou é um nome de arquivo
                        if (is_string($value)) {
                            // Verifica se parece ser um caminho de arquivo
                            if (preg_match('/\.(jpg|jpeg|png|gif|pdf|doc|docx)$/i', $value)) {
                                // Tenta encontrar o arquivo em possíveis localizações
                                $possiblePaths = [
                                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/service_orders/' . $value,
                                    $_SERVER['DOCUMENT_ROOT'] . '/../storage/service_orders/' . $value,
                                    $_SERVER['DOCUMENT_ROOT'] . '/../public/uploads/service_orders/' . $value,
                                ];
                                
                                foreach ($possiblePaths as $filePath) {
                                    if (file_exists($filePath)) {
                                        @unlink($filePath);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Exclui o pedido
            // Note: Foreign keys com ON DELETE CASCADE/SET NULL já cuidam dos relacionamentos
            $stmt = $db->prepare("DELETE FROM service_orders WHERE id = ?");
            $stmt->execute([$id]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}

