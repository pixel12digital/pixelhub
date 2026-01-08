<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\ServiceOrderService;
use PixelHub\Services\ServiceService;
use PixelHub\Services\AsaasClient;
use PixelHub\Services\AsaasBillingService;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar pedidos de serviço (service_orders)
 * 
 * Gerencia pedidos criados ANTES do projeto. Cliente preenche dados,
 * briefing e aprova. Após aprovação, converte automaticamente em projeto.
 */
class ServiceOrderController extends Controller
{
    /**
     * Lista todos os pedidos (interno)
     * 
     * GET /service-orders
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $filters = [
            'service_id' => !empty($_GET['service_id']) ? (int) $_GET['service_id'] : null,
            'tenant_id' => !empty($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null,
            'status' => !empty($_GET['status']) ? trim($_GET['status']) : null,
        ];
        
        $orders = ServiceOrderService::listOrders($filters);
        $services = ServiceService::getAllServices(null, false);
        
        $this->view('service_orders.index', [
            'orders' => $orders,
            'services' => $services,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Exibe formulário de criação de pedido (interno)
     * 
     * GET /service-orders/create
     */
    public function create(): void
    {
        Auth::requireInternal();
        
        $serviceId = !empty($_GET['service_id']) ? (int) $_GET['service_id'] : null;
        $tenantId = !empty($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
        
        $services = ServiceService::getAllServices(null, true);
        
        // Busca tenants ativos
        $db = DB::getConnection();
        $tenants = $db->query("
            SELECT id, name, email, phone 
            FROM tenants 
            WHERE status = 'active' 
            ORDER BY name ASC
        ")->fetchAll();
        
        $service = null;
        if ($serviceId) {
            $service = ServiceService::findService($serviceId);
        }
        
        $this->view('service_orders.form', [
            'order' => null,
            'services' => $services,
            'tenants' => $tenants,
            'selectedServiceId' => $serviceId,
            'selectedTenantId' => $tenantId,
            'service' => $service,
        ]);
    }
    
    /**
     * Salva novo pedido (interno)
     * 
     * POST /service-orders/store
     */
    public function store(): void
    {
        Auth::requireInternal();
        
        $user = Auth::user();
        
        try {
            $data = [
                'service_id' => $_POST['service_id'] ?? null,
                'tenant_id' => !empty($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null,
                'contract_value' => !empty($_POST['contract_value']) ? 
                    (float) str_replace(['.', ','], ['', '.'], $_POST['contract_value']) : null,
                'payment_condition' => $_POST['payment_condition'] ?? null,
                'payment_method' => $_POST['payment_method'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'created_by' => $user['id'] ?? null,
            ];
            
            $orderId = ServiceOrderService::createOrder($data);
            
            // Busca pedido criado para pegar o token
            $order = ServiceOrderService::findOrder($orderId);
            $publicLink = ServiceOrderService::generatePublicLink($order['token']);
            
            $this->redirect('/service-orders?success=created&link=' . urlencode($publicLink));
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/service-orders/create?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao criar pedido: " . $e->getMessage());
            $this->redirect('/service-orders/create?error=database_error');
        }
    }
    
    /**
     * Visualiza um pedido (interno)
     * 
     * GET /service-orders/view?id={id}
     */
    public function show(): void
    {
        Auth::requireInternal();
        
        $id = !empty($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            $this->redirect('/service-orders');
            return;
        }
        
        $order = ServiceOrderService::findOrder($id);
        
        if (!$order) {
            $this->redirect('/service-orders?error=not_found');
            return;
        }
        
        $publicLink = ServiceOrderService::generatePublicLink($order['token']);
        
        $this->view('service_orders.view', [
            'order' => $order,
            'publicLink' => $publicLink,
        ]);
    }
    
    /**
     * Página pública para preencher pedido (wizard 3 etapas)
     * 
     * GET /client-portal/orders/{token}
     */
    public function publicForm(): void
    {
        // Tenta pegar token do path ou query string
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            $path = strtok($path, '?');
            if (defined('BASE_PATH') && BASE_PATH !== '') {
                $path = str_replace(BASE_PATH, '', $path);
            }
            // Tenta extrair token do path /client-portal/orders/TOKEN
            if (preg_match('#/client-portal/orders/([a-f0-9]{64})#i', $path, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (empty($token)) {
            http_response_code(404);
            $this->view('service_orders.not_found', []);
            return;
        }
        
        $order = ServiceOrderService::findOrderByToken($token);
        
        if (!$order) {
            http_response_code(404);
            $this->view('service_orders.not_found', []);
            return;
        }
        
        // Se já foi convertido, mostra mensagem
        if ($order['status'] === 'converted') {
            $this->view('service_orders.already_converted', [
                'order' => $order,
            ]);
            return;
        }
        
        // Busca serviço para pegar briefing_template
        $service = ServiceService::findService($order['service_id']);
        $briefingTemplate = null;
        if ($service && !empty($service['briefing_template'])) {
            $briefingTemplate = $service['briefing_template']; // Já é JSON string
            
            // Se for cartão de visita, garante que o template tem os campos corretos
            $serviceCode = $service['code'] ?? '';
            if ($serviceCode === 'business_card' || stripos($service['name'] ?? '', 'cartão') !== false) {
                $template = json_decode($briefingTemplate, true);
                if ($template && isset($template['questions'])) {
                    // Procura e substitui negocio_descricao por segment
                    foreach ($template['questions'] as $index => $question) {
                        if (($question['id'] ?? '') === 'negocio_descricao') {
                            $template['questions'][$index] = [
                                'id' => 'segment',
                                'type' => 'segment',
                                'label' => 'Qual o segmento do seu negócio?',
                                'required' => true,
                                'order' => $question['order'] ?? 2
                            ];
                        }
                        // Substitui verso_informacoes por verso_guided
                        if (($question['id'] ?? '') === 'verso_informacoes') {
                            $template['questions'][$index] = [
                                'id' => 'verso_informacoes',
                                'type' => 'verso_guided',
                                'label' => 'Informações do verso',
                                'required' => false,
                                'order' => $question['order'] ?? 5
                            ];
                        }
                    }
                    $briefingTemplate = json_encode($template);
                }
            }
        }
        
        // Decodifica client_data se existir
        $clientData = null;
        if (!empty($order['client_data'])) {
            $clientData = json_decode($order['client_data'], true);
        }
        
        // Decodifica briefing_data se existir
        $briefingData = null;
        if (!empty($order['briefing_data'])) {
            $briefingData = json_decode($order['briefing_data'], true);
        }
        
        // Determina step atual baseado na URL ou status
        $step = $_GET['step'] ?? null;
        if (!$step) {
            if ($order['status'] === 'briefing_filled' || $order['status'] === 'approved') {
                $step = 'approval';
            } elseif ($order['status'] === 'client_data_filled' || !empty($briefingData)) {
                $step = 'briefing';
            } elseif (!empty($clientData) && !empty($clientData['address']['city'])) {
                // Se já tem dados do cliente e endereço, vai para briefing
                $step = 'briefing';
            } elseif (!empty($clientData)) {
                // Se tem dados do cliente mas não tem endereço completo, vai para endereço
                $step = 'address';
            } else {
                $step = 'client_data';
            }
        }
        
        // Obtém service_code do service
        $serviceCode = '';
        if ($service && isset($service['code'])) {
            $serviceCode = $service['code'];
        }
        
        $this->view('service_orders.public_form', [
            'order' => $order,
            'token' => $token,
            'briefingTemplate' => $briefingTemplate,
            'clientData' => $clientData,
            'briefingData' => $briefingData,
            'currentStep' => $step,
            'serviceCode' => $serviceCode,
        ]);
    }
    
    /**
     * Salva dados do cliente (Etapa 1) - AJAX
     * 
     * POST /client-portal/orders/save-client-data
     */
    public function saveClientData(): void
    {
        // Lê dados do JSON (quando enviado via AJAX) ou do POST tradicional
        $jsonData = json_decode(file_get_contents('php://input'), true);
        
        $token = $jsonData['token'] ?? $_POST['token'] ?? '';
        
        if (empty($token)) {
            $this->json(['error' => 'Token é obrigatório'], 400);
            return;
        }
        
        $order = ServiceOrderService::findOrderByToken($token);
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        
        try {
            $clientData = $jsonData['client_data'] ?? json_decode($_POST['client_data'] ?? '{}', true);
            
            if (empty($clientData)) {
                $this->json(['error' => 'Dados do cliente são obrigatórios'], 400);
                return;
            }
            
            ServiceOrderService::saveClientData($order['id'], $clientData);
            
            // Busca pedido atualizado
            $updatedOrder = ServiceOrderService::findOrder($order['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Dados salvos com sucesso',
                'order' => $updatedOrder,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao salvar dados do cliente: " . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar dados'], 500);
        }
    }
    
    /**
     * Salva briefing (Etapa 2) - AJAX
     * 
     * POST /client-portal/orders/save-briefing
     */
    public function saveBriefing(): void
    {
        // Lê dados do JSON (quando enviado via AJAX) ou do POST tradicional
        $jsonData = json_decode(file_get_contents('php://input'), true);
        
        $token = $jsonData['token'] ?? $_POST['token'] ?? '';
        
        if (empty($token)) {
            $this->json(['error' => 'Token é obrigatório'], 400);
            return;
        }
        
        $order = ServiceOrderService::findOrderByToken($token);
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        
        try {
            $briefingData = $jsonData['briefing_data'] ?? json_decode($_POST['briefing_data'] ?? '{}', true);
            
            if (empty($briefingData)) {
                $this->json(['error' => 'Dados do briefing são obrigatórios'], 400);
                return;
            }
            
            ServiceOrderService::saveBriefing($order['id'], $briefingData);
            
            $this->json([
                'success' => true,
                'message' => 'Briefing salvo com sucesso',
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao salvar briefing: " . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar briefing'], 500);
        }
    }
    
    /**
     * Aprova pedido (Etapa 3) - AJAX
     * 
     * POST /client-portal/orders/approve
     */
    public function approve(): void
    {
        // Lê dados do JSON (quando enviado via AJAX) ou do POST tradicional
        $jsonData = json_decode(file_get_contents('php://input'), true);
        
        $token = $jsonData['token'] ?? $_POST['token'] ?? '';
        
        if (empty($token)) {
            $this->json(['error' => 'Token é obrigatório'], 400);
            return;
        }
        
        $order = ServiceOrderService::findOrderByToken($token);
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        
        try {
            $paymentData = [
                'payment_condition' => $jsonData['payment_condition'] ?? $_POST['payment_condition'] ?? null,
                'payment_method' => $jsonData['payment_method'] ?? $_POST['payment_method'] ?? null,
            ];
            
            ServiceOrderService::approveOrder($order['id'], $paymentData);
            
            // Busca pedido atualizado (agora convertido)
            $updatedOrder = ServiceOrderService::findOrder($order['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Pedido aprovado e convertido em projeto com sucesso!',
                'order' => $updatedOrder,
                'project_id' => $updatedOrder['project_id'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao aprovar pedido: " . $e->getMessage());
            $this->json(['error' => 'Erro ao aprovar pedido'], 500);
        }
    }

    /**
     * Lookup de cliente existente (público) usando token do pedido
     *
     * Regras:
     * - Se identificar email/CPF/CNPJ, tenta localizar em tenants
     * - Se não achar, tenta localizar no Asaas (sincroniza) e cria/atualiza tenant
     * - Se não achar, responde pedindo confirmação se é esse mesmo (frontend decide insistência)
     *
     * POST /client-portal/orders/lookup-existing-client
     * Body JSON: { token: string, identifier: string }
     */
    public function lookupExistingClient(): void
    {
        header('Content-Type: application/json');

        $jsonData = json_decode(file_get_contents('php://input'), true) ?: [];

        $token = trim($jsonData['token'] ?? '');
        $identifier = trim($jsonData['identifier'] ?? '');

        if (empty($token)) {
            $this->json(['success' => false, 'error' => 'Token é obrigatório'], 400);
            return;
        }

        if (empty($identifier)) {
            $this->json(['success' => false, 'error' => 'Informe seu email ou CPF/CNPJ'], 400);
            return;
        }

        $order = ServiceOrderService::findOrderByToken($token);
        if (!$order) {
            $this->json(['success' => false, 'error' => 'Pedido não encontrado ou link expirado'], 404);
            return;
        }

        // Detecta tipo de identificador
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $digits = preg_replace('/[^0-9]/', '', $identifier);
        $isCpfCnpj = in_array(strlen($digits), [11, 14], true);

        // Se usuário informou telefone ou algo inválido, orienta
        $isPhoneLike = in_array(strlen($digits), [10, 11], true) && !$isCpfCnpj && !$isEmail;
        if (!$isEmail && !$isCpfCnpj) {
            $msg = $isPhoneLike
                ? 'Entendi o telefone, mas para localizar seu cadastro eu preciso do seu **email** ou **CPF/CNPJ**. Pode me informar um deles?'
                : 'Para localizar seu cadastro, informe seu **email** ou **CPF/CNPJ**. Pode enviar agora?';

            $this->json([
                'success' => true,
                'found' => false,
                'needs_confirmation' => false,
                'message' => $msg,
                'expected' => 'email_or_cpf_cnpj'
            ]);
            return;
        }

        $db = DB::getConnection();

        // 1) Busca no banco (tenants)
        $tenant = null;
        if ($isEmail) {
            $stmt = $db->prepare("SELECT * FROM tenants WHERE email = ? LIMIT 1");
            $stmt->execute([strtolower($identifier)]);
            $tenant = $stmt->fetch() ?: null;
        } elseif ($isCpfCnpj) {
            $stmt = $db->prepare("SELECT * FROM tenants WHERE cpf_cnpj = ? OR document = ? LIMIT 1");
            $stmt->execute([$digits, $digits]);
            $tenant = $stmt->fetch() ?: null;
        }

        // 2) Se achou tenant, garante/sincroniza com Asaas e retorna dados
        if ($tenant) {
            $asaasCustomer = null;
            try {
                // Garante que existe customer no Asaas (e salva asaas_customer_id)
                $customerId = AsaasBillingService::ensureCustomerForTenant($tenant);
                $asaasCustomer = !empty($customerId) ? AsaasClient::getCustomer($customerId) : null;
            } catch (\Throwable $e) {
                // Não falha o fluxo por erro do Asaas - apenas loga
                error_log('[lookupExistingClient] Erro ao sincronizar com Asaas (tenant existente): ' . $e->getMessage());
            }

            $payload = $this->buildClientPayloadFromTenantAndAsaas($tenant, $asaasCustomer);

            $this->json([
                'success' => true,
                'found' => true,
                'tenant_id' => (int) $tenant['id'],
                'message' => $payload['message'],
                'client' => $payload['client'],
                'address' => $payload['address'],
            ]);
            return;
        }

        // 3) Se não achou no banco, tenta no Asaas
        $asaasCustomer = null;
        try {
            $asaasCustomer = AsaasClient::findCustomerByCpfCnpjOrEmail($identifier);
        } catch (\Throwable $e) {
            error_log('[lookupExistingClient] Erro ao buscar customer no Asaas: ' . $e->getMessage());
        }

        if (!$asaasCustomer) {
            $this->json([
                'success' => true,
                'found' => false,
                'needs_confirmation' => true,
                'message' => 'Não localizei seu cadastro com esse email/CPF/CNPJ. É esse mesmo? Se quiser, você pode enviar outro email/CPF/CNPJ.'
            ]);
            return;
        }

        // 4) Upsert tenant a partir do Asaas e retorna
        try {
            $tenant = $this->upsertTenantFromAsaasCustomer($asaasCustomer);
        } catch (\Throwable $e) {
            error_log('[lookupExistingClient] Erro ao criar/atualizar tenant com dados do Asaas: ' . $e->getMessage());
            $this->json([
                'success' => true,
                'found' => false,
                'needs_confirmation' => true,
                'message' => 'Encontrei seu cadastro no sistema de pagamento, mas não consegui sincronizar agora. Podemos seguir com um cadastro rápido (bem simples) para não te atrasar.'
            ]);
            return;
        }

        $payload = $this->buildClientPayloadFromTenantAndAsaas($tenant, $asaasCustomer);

        $this->json([
            'success' => true,
            'found' => true,
            'tenant_id' => (int) $tenant['id'],
            'message' => $payload['message'],
            'client' => $payload['client'],
            'address' => $payload['address'],
        ]);
    }

    /**
     * Monta payload amigável para o chat a partir de tenant + Asaas (se houver)
     */
    private function buildClientPayloadFromTenantAndAsaas(array $tenant, ?array $asaasCustomer): array
    {
        // Prioriza dados mais completos do Asaas quando disponíveis
        $name = trim(($asaasCustomer['name'] ?? '') ?: ($tenant['name'] ?? ''));
        $email = strtolower(trim(($asaasCustomer['email'] ?? '') ?: ($tenant['email'] ?? '')));
        $cpfCnpj = preg_replace('/[^0-9]/', '', ($asaasCustomer['cpfCnpj'] ?? '') ?: ($tenant['cpf_cnpj'] ?? ($tenant['document'] ?? '')));
        $phone = preg_replace('/[^0-9]/', '', ($asaasCustomer['mobilePhone'] ?? '') ?: ($asaasCustomer['phone'] ?? '') ?: ($tenant['phone'] ?? ''));

        $personType = ($tenant['person_type'] ?? 'pf') ?: 'pf';
        if (strlen($cpfCnpj) === 14) {
            $personType = 'pj';
        } elseif (strlen($cpfCnpj) === 11) {
            $personType = 'pf';
        }

        // Endereço
        $address = [
            'cep' => preg_replace('/[^0-9]/', '', ($asaasCustomer['postalCode'] ?? '') ?: ($tenant['address_cep'] ?? '')),
            'street' => trim(($asaasCustomer['address'] ?? '') ?: ($tenant['address_street'] ?? '')),
            'number' => trim(($asaasCustomer['addressNumber'] ?? '') ?: ($tenant['address_number'] ?? '')),
            'complement' => trim(($asaasCustomer['complement'] ?? '') ?: ($tenant['address_complement'] ?? '')),
            'neighborhood' => trim(($asaasCustomer['province'] ?? '') ?: ($tenant['address_neighborhood'] ?? '')),
            'city' => trim(($asaasCustomer['city'] ?? '') ?: ($tenant['address_city'] ?? '')),
            'state' => strtoupper(trim(($asaasCustomer['state'] ?? '') ?: ($tenant['address_state'] ?? ''))),
        ];

        $msgParts = [];
        if ($name) $msgParts[] = "Nome: {$name}";
        if ($email) $msgParts[] = "Email: {$email}";
        if ($phone) $msgParts[] = "Telefone: {$phone}";
        if (!empty($address['city']) && !empty($address['state'])) $msgParts[] = "Cidade/UF: {$address['city']}/{$address['state']}";

        $message = "Encontrei seu cadastro! ✅\n" . implode("\n", $msgParts) . "\n\nSe estiver tudo certo, vou continuar.";

        return [
            'message' => $message,
            'client' => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'cpf_cnpj' => $cpfCnpj,
                'person_type' => $personType,
                'asaas_customer_id' => $asaasCustomer['id'] ?? ($tenant['asaas_customer_id'] ?? null),
            ],
            'address' => $address,
        ];
    }

    /**
     * Cria ou atualiza tenant a partir de um customer do Asaas
     */
    private function upsertTenantFromAsaasCustomer(array $asaasCustomer): array
    {
        $db = DB::getConnection();

        $customerId = $asaasCustomer['id'] ?? null;
        if (empty($customerId)) {
            throw new \RuntimeException('Customer do Asaas sem id');
        }

        // Tenta localizar pelo asaas_customer_id
        $stmt = $db->prepare("SELECT * FROM tenants WHERE asaas_customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $tenant = $stmt->fetch() ?: null;

        $cpfCnpj = preg_replace('/[^0-9]/', '', $asaasCustomer['cpfCnpj'] ?? '');
        $email = strtolower(trim($asaasCustomer['email'] ?? ''));
        $name = trim($asaasCustomer['name'] ?? '');
        $phone = trim(($asaasCustomer['mobilePhone'] ?? '') ?: ($asaasCustomer['phone'] ?? ''));

        $personType = strlen($cpfCnpj) === 14 ? 'pj' : 'pf';

        $address = [
            'cep' => preg_replace('/[^0-9]/', '', $asaasCustomer['postalCode'] ?? ''),
            'street' => trim($asaasCustomer['address'] ?? ''),
            'number' => trim($asaasCustomer['addressNumber'] ?? ''),
            'complement' => trim($asaasCustomer['complement'] ?? ''),
            'neighborhood' => trim($asaasCustomer['province'] ?? ''),
            'city' => trim($asaasCustomer['city'] ?? ''),
            'state' => strtoupper(trim($asaasCustomer['state'] ?? '')),
        ];

        if ($tenant) {
            // Atualiza apenas campos vazios ou diferentes (não sobrescreve dados internos por acidente)
            $updates = [];
            $params = [];

            if (!empty($name) && empty($tenant['name'])) { $updates[] = "name = ?"; $params[] = $name; }
            if (!empty($email) && empty($tenant['email'])) { $updates[] = "email = ?"; $params[] = $email; }
            if (!empty($phone) && empty($tenant['phone'])) { $updates[] = "phone = ?"; $params[] = $phone; }
            if (!empty($cpfCnpj) && empty($tenant['cpf_cnpj'])) { $updates[] = "cpf_cnpj = ?"; $params[] = $cpfCnpj; $updates[] = "document = ?"; $params[] = $cpfCnpj; }
            if (!empty($personType) && empty($tenant['person_type'])) { $updates[] = "person_type = ?"; $params[] = $personType; }

            if (!empty($address['cep']) && empty($tenant['address_cep'])) { $updates[] = "address_cep = ?"; $params[] = $address['cep']; }
            if (!empty($address['street']) && empty($tenant['address_street'])) { $updates[] = "address_street = ?"; $params[] = $address['street']; }
            if (!empty($address['number']) && empty($tenant['address_number'])) { $updates[] = "address_number = ?"; $params[] = $address['number']; }
            if (!empty($address['complement']) && empty($tenant['address_complement'])) { $updates[] = "address_complement = ?"; $params[] = $address['complement']; }
            if (!empty($address['neighborhood']) && empty($tenant['address_neighborhood'])) { $updates[] = "address_neighborhood = ?"; $params[] = $address['neighborhood']; }
            if (!empty($address['city']) && empty($tenant['address_city'])) { $updates[] = "address_city = ?"; $params[] = $address['city']; }
            if (!empty($address['state']) && empty($tenant['address_state'])) { $updates[] = "address_state = ?"; $params[] = $address['state']; }

            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $params[] = (int) $tenant['id'];
                $sql = "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = ?";
                $db->prepare($sql)->execute($params);
            }

            // Recarrega
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([(int) $tenant['id']]);
            return $stmt->fetch() ?: $tenant;
        }

        // Se não achou por asaas_customer_id, tenta por CPF/CNPJ se tiver
        if (!empty($cpfCnpj)) {
            $stmt = $db->prepare("SELECT * FROM tenants WHERE cpf_cnpj = ? OR document = ? LIMIT 1");
            $stmt->execute([$cpfCnpj, $cpfCnpj]);
            $tenant = $stmt->fetch() ?: null;
            if ($tenant) {
                // Vincula asaas_customer_id
                $db->prepare("UPDATE tenants SET asaas_customer_id = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$customerId, (int) $tenant['id']]);

                $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
                $stmt->execute([(int) $tenant['id']]);
                return $stmt->fetch() ?: $tenant;
            }
        }

        // Cria novo tenant mínimo
        $stmt = $db->prepare("
            INSERT INTO tenants
            (person_type, name, cpf_cnpj, document, email, phone,
             asaas_customer_id,
             address_cep, address_street, address_number, address_complement,
             address_neighborhood, address_city, address_state,
             status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            $personType,
            $name ?: ($email ?: 'Cliente'),
            $cpfCnpj ?: null,
            $cpfCnpj ?: null,
            $email ?: null,
            $phone ?: null,
            $customerId,
            $address['cep'] ?: null,
            $address['street'] ?: null,
            $address['number'] ?: null,
            $address['complement'] ?: null,
            $address['neighborhood'] ?: null,
            $address['city'] ?: null,
            $address['state'] ?: null,
        ]);

        $tenantId = (int) $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }
    
    /**
     * Inicia geração do cartão de visita (público) - AJAX
     * 
     * POST /client-portal/orders/start-generation
     */
    public function startGeneration(): void
    {
        header('Content-Type: application/json');
        
        $jsonData = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = trim($jsonData['token'] ?? '');
        
        if (empty($token)) {
            $this->json(['success' => false, 'error' => 'Token é obrigatório'], 400);
            return;
        }
        
        $order = ServiceOrderService::findOrderByToken($token);
        if (!$order) {
            $this->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
            return;
        }
        
        // Valida se o briefing está completo
        try {
            $intakeReady = ServiceOrderService::validateIntakeReady($order['id']);
            
            if (!$intakeReady['ready']) {
                $this->json([
                    'success' => false,
                    'error' => 'Briefing incompleto',
                    'missing' => $intakeReady['missing'],
                    'missingStep' => $intakeReady['missingStep'] ?? null
                ], 400);
                return;
            }
            
            // Atualiza status para design_generating
            ServiceOrderService::updateOrderStatus($order['id'], 'design_generating');
            
            // TODO: Dispara job/worker para gerar cartão
            // Por enquanto, apenas retorna sucesso
            // No futuro, isso vai disparar: generate_business_card($order['id'])
            
            $this->json([
                'success' => true,
                'message' => 'Geração iniciada com sucesso',
                'order_status' => 'design_generating',
                // TODO: Retornar deliverables quando estiver pronto
                'deliverables' => null
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao iniciar geração: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao iniciar geração'], 500);
        }
    }
    
    /**
     * Exclui um pedido de serviço
     * 
     * POST /service-orders/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();
        
        $orderId = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        
        if (empty($orderId)) {
            $this->json(['error' => 'ID do pedido é obrigatório'], 400);
            return;
        }
        
        try {
            ServiceOrderService::deleteOrder($orderId);
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['success' => true, 'message' => 'Pedido excluído com sucesso']);
            } else {
                header('Location: ' . pixelhub_url('/service-orders?success=deleted'));
                exit;
            }
        } catch (\Exception $e) {
            error_log("Erro ao excluir pedido: " . $e->getMessage());
            
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'convertido') !== false) {
                $errorMessage = 'Não é possível excluir um pedido que já foi convertido em projeto';
            } else {
                $errorMessage = 'Erro ao excluir pedido: ' . $errorMessage;
            }
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['error' => $errorMessage], 400);
            } else {
                header('Location: ' . pixelhub_url('/service-orders?error=' . urlencode($errorMessage)));
                exit;
            }
        }
    }
}

