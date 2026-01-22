<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Storage;
use PixelHub\Services\HostingProviderService;
use PixelHub\Services\TaskService;
use PixelHub\Services\TicketService;
use PixelHub\Services\WhatsAppHistoryService;
use PixelHub\Services\ProjectService;

/**
 * Controller para gerenciar tenants (clientes)
 */
class TenantsController extends Controller
{
    /**
     * Visualiza detalhes de um tenant (Painel do Cliente)
     */
    public function show(): void
    {
        Auth::requireInternal();

        $tenantId = $_GET['id'] ?? null;
        $activeTab = $_GET['tab'] ?? 'overview';

        if (!$tenantId) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca dados do tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=not_found');
            return;
        }

        // Busca hosting accounts do tenant
        $stmt = $db->prepare("
            SELECT * FROM hosting_accounts
            WHERE tenant_id = ?
            ORDER BY domain ASC
        ");
        $stmt->execute([$tenantId]);
        $hostingAccounts = $stmt->fetchAll();
        
        // Garante que last_backup_at está presente (pode ser NULL)
        foreach ($hostingAccounts as &$account) {
            if (!isset($account['last_backup_at'])) {
                $account['last_backup_at'] = null;
            }
        }
        unset($account);

        // Busca contas de email do tenant
        $stmt = $db->prepare("
            SELECT ea.*, ha.domain as hosting_domain
            FROM tenant_email_accounts ea
            LEFT JOIN hosting_accounts ha ON ea.hosting_account_id = ha.id
            WHERE ea.tenant_id = ?
            ORDER BY ea.email ASC
        ");
        $stmt->execute([$tenantId]);
        $emailAccounts = $stmt->fetchAll();

        // Busca todos os backups dos hosting accounts desse tenant
        if (!empty($hostingAccounts)) {
            $hostingIds = array_column($hostingAccounts, 'id');
            $placeholders = str_repeat('?,', count($hostingIds) - 1) . '?';
            
            $stmt = $db->prepare("
                SELECT hb.*, ha.domain, ha.id as hosting_account_id
                FROM hosting_backups hb
                INNER JOIN hosting_accounts ha ON hb.hosting_account_id = ha.id
                WHERE hb.hosting_account_id IN ({$placeholders})
                ORDER BY hb.created_at DESC
            ");
            $stmt->execute($hostingIds);
            $backups = $stmt->fetchAll();
            
            // Lazy loading: não verifica existência de arquivos aqui (otimização de performance)
            // A verificação será feita apenas quando necessário (ex: ao clicar em Download)
            foreach ($backups as &$backup) {
                $backup['file_exists'] = null; // null = não verificado ainda (lazy loading)
            }
            unset($backup);
        } else {
            $backups = [];
        }

        // Busca faturas do tenant para aba Financeiro (ignora deletadas)
        $stmt = $db->prepare("
            SELECT * FROM billing_invoices
            WHERE tenant_id = ? AND (is_deleted IS NULL OR is_deleted = 0)
            ORDER BY due_date DESC, created_at DESC
        ");
        $stmt->execute([$tenantId]);
        $invoices = $stmt->fetchAll();

        // Conta faturas em atraso (ignora deletadas)
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM billing_invoices
            WHERE tenant_id = ? AND status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0)
        ");
        $stmt->execute([$tenantId]);
        $overdueCount = (int) $stmt->fetchColumn();

        // Busca timeline unificada de WhatsApp usando WhatsAppHistoryService
        // Limite de histórico na Visão Geral: 5 mensagens (para não poluir a tela)
        // Na aba de notificações, busca mais registros (50) para exibir histórico completo
        $timelineLimit = $activeTab === 'notifications' ? 50 : 5;
        $whatsappTimeline = WhatsAppHistoryService::getTimelineByTenant((int)$tenantId, $timelineLimit);
        
        // Último contato WhatsApp (primeiro item da timeline, se houver)
        $lastWhatsAppContact = !empty($whatsappTimeline) ? $whatsappTimeline[0] : null;
        
        // Busca billing_notifications para a aba de notificações
        $whatsappNotifications = [];
        if ($activeTab === 'notifications') {
            $stmt = $db->prepare("
                SELECT bn.*, bi.due_date, bi.amount 
                FROM billing_notifications bn 
                LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id 
                WHERE bn.tenant_id = ? 
                ORDER BY bn.sent_at DESC, bn.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$tenantId]);
            $whatsappNotifications = $stmt->fetchAll();
        }

        // Busca todos os customers Asaas para o CPF/CNPJ do tenant (apenas na aba financeira)
        // Sempre inicializa as variáveis para evitar erros na view
        $asaasCustomersByCpf = [];
        $asaasPrimaryCustomerId = $tenant['asaas_customer_id'] ?? null;
        
        if ($activeTab === 'financial') {
            try {
                $cpfCnpj = $tenant['cpf_cnpj'] ?? $tenant['document'] ?? '';
                if (!empty($cpfCnpj)) {
                    // Normaliza CPF/CNPJ (apenas dígitos)
                    $cpfCnpjNormalizado = preg_replace('/[^0-9]/', '', $cpfCnpj);
                    
                    if (!empty($cpfCnpjNormalizado)) {
                        // Busca todos os customers no Asaas com esse CPF/CNPJ
                        $asaasCustomersByCpf = \PixelHub\Services\AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado);
                    }
                }
            } catch (\Exception $e) {
                // Em caso de erro (timeout, API indisponível, etc.), apenas loga e continua
                // Não quebra a tela, apenas não mostra a lista de customers
                error_log("Erro ao buscar customers Asaas por CPF para tenant {$tenantId}: " . $e->getMessage());
                $asaasCustomersByCpf = [];
            }
        }

        // Busca mapa de provedores para exibir nomes
        $providerMap = HostingProviderService::getSlugToNameMap();

        // Busca projetos do tenant (apenas se necessário para a aba de tarefas)
        $clientProjects = [];
        if ($activeTab === 'tasks') {
            $clientProjects = ProjectService::getAllProjects((int) $tenantId, 'ativo', 'cliente');
        }
        
        // Busca tarefas do tenant (apenas se necessário para a aba de tarefas)
        $tasks = [];
        if ($activeTab === 'tasks') {
            // Reaproveitar TaskService já existente
            // projectId = null, tenantId = $tenantId, clientQuery = null
            $tasksGrouped = TaskService::getAllTasks(null, (int) $tenantId, null);
            
            // Converte array agrupado por status em array simples para facilitar manipulação na view
            $tasks = [];
            foreach ($tasksGrouped as $status => $statusTasks) {
                foreach ($statusTasks as $task) {
                    $tasks[] = $task;
                }
            }
        }
        
        // Busca tickets do tenant (apenas se necessário para a aba de tarefas)
        $tickets = [];
        if ($activeTab === 'tasks') {
            $tickets = TicketService::getAllTickets(['tenant_id' => (int) $tenantId]);
        }

        // Busca documentos gerais do tenant (apenas se necessário para a aba docs_backups)
        $tenantDocuments = [];
        $contracts = [];
        if ($activeTab === 'docs_backups') {
            $stmt = $db->prepare("
                SELECT * FROM tenant_documents
                WHERE tenant_id = ?
                ORDER BY created_at DESC, id DESC
            ");
            $stmt->execute([$tenantId]);
            $tenantDocuments = $stmt->fetchAll();
            
            // Verifica existência dos arquivos físicos
            foreach ($tenantDocuments as &$doc) {
                if (!empty($doc['stored_path'])) {
                    $doc['file_exists'] = Storage::fileExists($doc['stored_path']);
                } else {
                    $doc['file_exists'] = false;
                }
            }
            unset($doc);
            
            // Busca contratos do tenant
            $contracts = \PixelHub\Services\ProjectContractService::getContractsByTenant($tenantId);
        }

        // Busca dados consolidados do Asaas para preencher o formulário de edição
        $consolidatedAsaasData = [];
        if (!empty($tenant['cpf_cnpj'])) {
            try {
                $cpfCnpjNormalizado = preg_replace('/[^0-9]/', '', $tenant['cpf_cnpj']);
                if (!empty($cpfCnpjNormalizado)) {
                    $allCustomers = \PixelHub\Services\AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado);
                    if (!empty($allCustomers)) {
                        $consolidatedAsaasRaw = \PixelHub\Services\AsaasBillingService::consolidateAsaasCustomersData($allCustomers);
                        $consolidatedAsaasData = \PixelHub\Services\AsaasBillingService::convertConsolidatedDataToTenantFormat($consolidatedAsaasRaw);
                    }
                }
            } catch (\Exception $e) {
                // Silenciosamente ignora erros ao buscar dados consolidados
                error_log("Aviso: Erro ao buscar dados consolidados do Asaas: " . $e->getMessage());
            }
        }

        $this->view('tenants.view', [
            'tenant' => $tenant,
            'hostingAccounts' => $hostingAccounts,
            'emailAccounts' => $emailAccounts,
            'backups' => $backups,
            'invoices' => $invoices,
            'overdueCount' => $overdueCount,
            'whatsappNotifications' => $whatsappNotifications,
            'whatsappTimeline' => $whatsappTimeline,
            'lastWhatsAppContact' => $lastWhatsAppContact,
            'tickets' => $tickets ?? [],
            'activeTab' => $activeTab,
            'asaasCustomersByCpf' => $asaasCustomersByCpf,
            'asaasPrimaryCustomerId' => $asaasPrimaryCustomerId,
            'consolidatedAsaasData' => $consolidatedAsaasData,
            'providerMap' => $providerMap,
            'tasks' => $tasks,
            'clientProjects' => $clientProjects ?? [],
            'contracts' => $contracts ?? [],
            'tenantDocuments' => $tenantDocuments ?? [],
            'tenantDocuments' => $tenantDocuments,
        ]);
    }

    /**
     * Lista todos os tenants
     */
    public function index(): void
    {
        Auth::requireInternal();

        // Lê parâmetros de busca e paginação via GET
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = $page > 0 ? $page : 1;
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Busca tenants com paginação
        $result = $this->searchWithPagination($search, $perPage, $offset);
        $tenants = $result['items'];
        $total = $result['total'];

        // Calcula total de páginas e ajusta página se necessário
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
            // Rebusca com página corrigida
            $offset = ($page - 1) * $perPage;
            $result = $this->searchWithPagination($search, $perPage, $offset);
            $tenants = $result['items'];
        }

        // Verifica se é requisição AJAX
        $isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

        if ($isAjax) {
            // Renderizar apenas o <tbody> e paginação como parciais
            // Variáveis já estão no escopo, mas garantimos com extract para clareza
            $viewData = [
                'tenants' => $tenants,
                'search' => $search,
                'page' => $page,
                'totalPages' => $totalPages,
            ];
            
            ob_start();
            extract($viewData);
            require __DIR__ . '/../../views/tenants/_table_rows.php';
            $tableRowsHtml = ob_get_clean();

            ob_start();
            extract($viewData);
            require __DIR__ . '/../../views/tenants/_pagination.php';
            $paginationHtml = ob_get_clean();

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'html' => $tableRowsHtml,
                'paginationHtml' => $paginationHtml,
                'total' => $total,
                'page' => $page,
                'totalPages' => $totalPages,
                'search' => $search,
            ]);
            return;
        }

        $this->view('tenants.index', [
            'tenants' => $tenants,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Busca tenants com paginação
     * 
     * @param string|null $search Termo de busca
     * @param int $limit Limite de registros
     * @param int $offset Offset para paginação
     * @return array{items: array<int, array>, total: int}
     */
    private function searchWithPagination(?string $search, int $limit, int $offset): array
    {
        $db = DB::getConnection();

        // Monta WHERE clause para busca
        // Filtro padrão: excluir arquivados e somente financeiro
        $whereSql = " WHERE (t.is_archived = 0 AND t.is_financial_only = 0)";
        $params = [];

        if ($search !== null && $search !== '') {
            $whereSql .= " AND (
                t.name LIKE :search1
                OR t.email LIKE :search2
                OR t.phone LIKE :search3
            )";
            $searchTerm = '%' . $search . '%';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }

        // Query para contar total (sem GROUP BY para COUNT)
        $sqlCount = "
            SELECT COUNT(DISTINCT t.id) AS total
            FROM tenants t
            LEFT JOIN hosting_accounts ha ON t.id = ha.tenant_id
            {$whereSql}
        ";

        if (!empty($params)) {
            $stmt = $db->prepare($sqlCount);
            $stmt->execute($params);
        } else {
            $stmt = $db->query($sqlCount);
        }
        $row = $stmt->fetch();
        $total = (int)($row['total'] ?? 0);

        // Query para buscar itens paginados
        $sqlItems = "
            SELECT t.*, 
                   COUNT(ha.id) as hosting_count,
                   COUNT(CASE WHEN ha.backup_status = 'completo' THEN 1 END) as backups_completos
            FROM tenants t
            LEFT JOIN hosting_accounts ha ON t.id = ha.tenant_id
            {$whereSql}
            GROUP BY t.id
            ORDER BY t.name ASC
            LIMIT :limit OFFSET :offset
        ";

        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $db->prepare($sqlItems);
        
        // Bind dos parâmetros com tipos corretos
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
        }

        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Exibe formulário de criação/edição de cliente
     */
    public function create(): void
    {
        Auth::requireInternal();

        $createHosting = isset($_GET['create_hosting']) && $_GET['create_hosting'] == '1';

        $this->view('tenants.form', [
            'tenant' => null,
            'createHosting' => $createHosting,
        ]);
    }

    /**
     * Salva novo cliente
     */
    /**
     * Verifica se cliente existe no sistema ou no Asaas (AJAX)
     */
    public function checkAsaas(): void
    {
        Auth::requireInternal();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $cpfCnpj = preg_replace('/[^0-9]/', '', $input['cpf_cnpj'] ?? '');
        
        if (empty($cpfCnpj)) {
            $this->json(['error' => 'CPF/CNPJ não informado'], 400);
            return;
        }
        
        $db = DB::getConnection();
        
        // Verifica se já existe no sistema
        $stmt = $db->prepare("
            SELECT id, name, asaas_customer_id 
            FROM tenants 
            WHERE cpf_cnpj = ? OR document = ?
            LIMIT 1
        ");
        $stmt->execute([$cpfCnpj, $cpfCnpj]);
        $existingTenant = $stmt->fetch();
        
        if ($existingTenant) {
            $this->json([
                'exists_in_system' => true,
                'system_name' => $existingTenant['name'],
                'system_id' => $existingTenant['id'],
                'asaas_customer_id' => $existingTenant['asaas_customer_id']
            ]);
            return;
        }
        
        // Verifica se existe no Asaas
        try {
            $asaasCustomer = \PixelHub\Services\AsaasClient::findCustomerByCpfCnpj($cpfCnpj);
            
            if ($asaasCustomer) {
                // Verifica se este customer_id já está vinculado a outro tenant
                $stmt = $db->prepare("SELECT id, name FROM tenants WHERE asaas_customer_id = ?");
                $stmt->execute([$asaasCustomer['id']]);
                $linkedTenant = $stmt->fetch();
                
                if ($linkedTenant) {
                    $this->json([
                        'exists_in_system' => true,
                        'system_name' => $linkedTenant['name'],
                        'system_id' => $linkedTenant['id'],
                        'asaas_customer_id' => $asaasCustomer['id'],
                        'message' => 'Este cliente do Asaas já está vinculado a outro cliente no sistema'
                    ]);
                    return;
                }
                
                $this->json([
                    'exists_in_asaas' => true,
                    'exists_in_system' => false,
                    'asaas_data' => $asaasCustomer
                ]);
                return;
            }
            
            // Não existe em nenhum lugar
            $this->json([
                'exists_in_asaas' => false,
                'exists_in_system' => false
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao verificar cliente no Asaas: " . $e->getMessage());
            
            $errorMessage = 'Erro ao verificar cliente no Asaas';
            
            // Mensagens mais amigáveis para erros comuns
            if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), 'inválida') !== false) {
                $errorMessage = 'Chave de API do Asaas inválida ou não configurada. Verifique as configurações em Configurações → Configurações Asaas.';
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $errorMessage = 'Acesso negado ao Asaas. Verifique se sua chave de API tem permissões necessárias.';
            } elseif (strpos($e->getMessage(), 'não está configurado') !== false) {
                $errorMessage = 'Asaas não está configurado. Configure a chave de API em Configurações → Configurações Asaas.';
            } else {
                $errorMessage = 'Erro ao conectar com Asaas: ' . $e->getMessage();
            }
            
            $this->json([
                'error' => $errorMessage
            ], 500);
        }
    }

    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Recebe dados do POST
        $personType = $_POST['person_type'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $phoneFixed = trim($_POST['phone_fixed'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $createHosting = isset($_POST['create_hosting']) && $_POST['create_hosting'] == '1';
        
        // Campos de endereço
        $addressCep = trim($_POST['address_cep'] ?? '');
        $addressStreet = trim($_POST['address_street'] ?? '');
        $addressNumber = trim($_POST['address_number'] ?? '');
        $addressComplement = trim($_POST['address_complement'] ?? '');
        $addressNeighborhood = trim($_POST['address_neighborhood'] ?? '');
        $addressCity = trim($_POST['address_city'] ?? '');
        $addressState = strtoupper(trim($_POST['address_state'] ?? ''));

        // Validações
        if (!in_array($personType, ['pf', 'pj'])) {
            $this->redirect('/tenants/create?error=invalid_person_type' . ($createHosting ? '&create_hosting=1' : ''));
            return;
        }

        // Processa dados conforme tipo de pessoa
        if ($personType === 'pf') {
            $name = trim($_POST['nome_pf'] ?? '');
            $cpfCnpj = trim($_POST['cpf_pf'] ?? '');
            $razaoSocial = null;
            $nomeFantasia = null;
            $responsavelNome = null;
            $responsavelCpf = null;

            if (empty($name)) {
                $this->redirect('/tenants/create?error=missing_name' . ($createHosting ? '&create_hosting=1' : ''));
                return;
            }

            if (empty($cpfCnpj)) {
                $this->redirect('/tenants/create?error=missing_cpf' . ($createHosting ? '&create_hosting=1' : ''));
                return;
            }
        } else { // PJ
            $razaoSocial = trim($_POST['razao_social'] ?? '');
            $cpfCnpj = trim($_POST['cnpj'] ?? '');
            $name = $razaoSocial; // Para Asaas, name = razão social
            $nomeFantasia = trim($_POST['nome_fantasia'] ?? '') ?: null;
            $responsavelNome = trim($_POST['responsavel_nome'] ?? '') ?: null;
            $responsavelCpf = trim($_POST['responsavel_cpf'] ?? '') ?: null;

            if (empty($razaoSocial)) {
                $this->redirect('/tenants/create?error=missing_razao_social' . ($createHosting ? '&create_hosting=1' : ''));
                return;
            }

            if (empty($cpfCnpj)) {
                $this->redirect('/tenants/create?error=missing_cnpj' . ($createHosting ? '&create_hosting=1' : ''));
                return;
            }
        }

        // Verifica se veio asaas_customer_id do formulário (importação do Asaas)
        $asaasCustomerId = trim($_POST['asaas_customer_id'] ?? '') ?: null;

        // Insere no banco
        try {
            $stmt = $db->prepare("
                INSERT INTO tenants 
                (person_type, name, cpf_cnpj, razao_social, nome_fantasia, 
                 responsavel_nome, responsavel_cpf, email, phone, phone_fixed, 
                 address_cep, address_street, address_number, address_complement, 
                 address_neighborhood, address_city, address_state,
                 document, asaas_customer_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $personType,
                $name,
                $cpfCnpj,
                $razaoSocial,
                $nomeFantasia,
                $responsavelNome,
                $responsavelCpf,
                $email ?: null,
                $phone ?: null,
                $phoneFixed ?: null,
                $addressCep ?: null,
                $addressStreet ?: null,
                $addressNumber ?: null,
                $addressComplement ?: null,
                $addressNeighborhood ?: null,
                $addressCity ?: null,
                $addressState ?: null,
                $cpfCnpj, // Mantém document para compatibilidade
                $asaasCustomerId,
                $status,
            ]);

            $tenantId = (int) $db->lastInsertId();

            // Se for requisição AJAX (criação inline do kanban), retorna JSON
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                // Busca dados do tenant criado
                $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
                $stmt->execute([$tenantId]);
                $tenant = $stmt->fetch();
                
                $this->json([
                    'success' => true,
                    'id' => $tenantId,
                    'name' => $tenant['name'] ?? $name
                ]);
                return;
            }

            // Fluxo pós-salvar (não AJAX)
            if ($createHosting) {
                // Já vai direto para criação de hospedagem desse cliente
                $this->redirect('/hosting/create?tenant_id=' . $tenantId . '&redirect_to=tenant');
            } else {
                // Vai para painel do cliente
                $this->redirect('/tenants/view?id=' . $tenantId . '&success=created');
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar tenant: " . $e->getMessage());
            
            // Se for AJAX, retorna JSON com erro
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                $this->json(['error' => 'Erro ao criar cliente: ' . $e->getMessage()], 500);
                return;
            }
            
            $this->redirect('/tenants/create?error=database_error' . ($createHosting ? '&create_hosting=1' : ''));
        }
    }

    /**
     * Exibe formulário de edição de cliente
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $tenantId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($tenantId <= 0) {
            $this->redirect('/tenants');
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=not_found');
            return;
        }

        $this->view('tenants.form', [
            'tenant' => $tenant,
            'createHosting' => false,
        ]);
    }

    /**
     * Atualiza cliente existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $tenantId = $_POST['id'] ?? null;
        $personType = $_POST['person_type'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $phoneFixed = trim($_POST['phone_fixed'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $internalNotes = trim($_POST['internal_notes'] ?? '');
        
        // Campos de endereço
        $addressCep = trim($_POST['address_cep'] ?? '');
        $addressStreet = trim($_POST['address_street'] ?? '');
        $addressNumber = trim($_POST['address_number'] ?? '');
        $addressComplement = trim($_POST['address_complement'] ?? '');
        $addressNeighborhood = trim($_POST['address_neighborhood'] ?? '');
        $addressCity = trim($_POST['address_city'] ?? '');
        $addressState = strtoupper(trim($_POST['address_state'] ?? ''));

        if (!$tenantId) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        // Busca tenant atual para verificar se tem asaas_customer_id
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $currentTenant = $stmt->fetch();

        if (!$currentTenant) {
            $this->redirect('/tenants?error=not_found');
            return;
        }

        $hasAsaasCustomerId = !empty($currentTenant['asaas_customer_id']);

        // Validações
        if (!in_array($personType, ['pf', 'pj'])) {
            $this->redirect('/tenants/edit?id=' . $tenantId . '&error=invalid_person_type');
            return;
        }

        // Processa dados conforme tipo de pessoa
        if ($personType === 'pf') {
            $name = trim($_POST['nome_pf'] ?? '');
            $cpfCnpj = trim($_POST['cpf_pf'] ?? '');
            $razaoSocial = null;
            $nomeFantasia = null;
            $responsavelNome = null;
            $responsavelCpf = null;

            // Se tem asaas_customer_id, não valida campos do Asaas (já estão readonly)
            if (!$hasAsaasCustomerId) {
                if (empty($name)) {
                    $this->redirect('/tenants/edit?id=' . $tenantId . '&error=missing_name');
                    return;
                }

                if (empty($cpfCnpj)) {
                    $this->redirect('/tenants/edit?id=' . $tenantId . '&error=missing_cpf');
                    return;
                }
            } else {
                // Se tem asaas_customer_id, mantém valores atuais (não atualiza campos do Asaas)
                $name = $currentTenant['name'] ?? '';
                $cpfCnpj = $currentTenant['cpf_cnpj'] ?? $currentTenant['document'] ?? '';
            }
        } else { // PJ
            $razaoSocial = trim($_POST['razao_social'] ?? '');
            $cpfCnpj = trim($_POST['cnpj'] ?? '');
            $name = $razaoSocial; // Para Asaas, name = razão social
            $nomeFantasia = trim($_POST['nome_fantasia'] ?? '') ?: null;
            $responsavelNome = trim($_POST['responsavel_nome'] ?? '') ?: null;
            $responsavelCpf = trim($_POST['responsavel_cpf'] ?? '') ?: null;

            // Se tem asaas_customer_id, não valida campos do Asaas (já estão readonly)
            if (!$hasAsaasCustomerId) {
                if (empty($razaoSocial)) {
                    $this->redirect('/tenants/edit?id=' . $tenantId . '&error=missing_razao_social');
                    return;
                }

                if (empty($cpfCnpj)) {
                    $this->redirect('/tenants/edit?id=' . $tenantId . '&error=missing_cnpj');
                    return;
                }
            } else {
                // Se tem asaas_customer_id, mantém valores atuais (não atualiza campos do Asaas)
                $razaoSocial = $currentTenant['razao_social'] ?? $currentTenant['name'] ?? '';
                $name = $razaoSocial;
                $cpfCnpj = $currentTenant['cpf_cnpj'] ?? $currentTenant['document'] ?? '';
                // Mantém campos PJ que não vêm do Asaas se não foram preenchidos
                if (empty($nomeFantasia)) {
                    $nomeFantasia = $currentTenant['nome_fantasia'] ?? null;
                }
                if (empty($responsavelNome)) {
                    $responsavelNome = $currentTenant['responsavel_nome'] ?? null;
                }
                if (empty($responsavelCpf)) {
                    $responsavelCpf = $currentTenant['responsavel_cpf'] ?? null;
                }
            }
        }

        // Se tem asaas_customer_id, não atualiza email (vem do Asaas)
        if ($hasAsaasCustomerId) {
            $email = $currentTenant['email'] ?? null;
        }

        try {
            // Monta query dinâmica baseada em se tem asaas_customer_id
            if ($hasAsaasCustomerId) {
                // Atualiza apenas campos internos (não atualiza name, cpf_cnpj, email que vêm do Asaas)
                $stmt = $db->prepare("
                    UPDATE tenants 
                    SET person_type = ?, razao_social = ?, nome_fantasia = ?,
                        responsavel_nome = ?, responsavel_cpf = ?, phone = ?, phone_fixed = ?,
                        address_cep = ?, address_street = ?, address_number = ?, address_complement = ?,
                        address_neighborhood = ?, address_city = ?, address_state = ?,
                        internal_notes = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $personType,
                    $razaoSocial,
                    $nomeFantasia,
                    $responsavelNome,
                    $responsavelCpf,
                    $phone ?: null,
                    $phoneFixed ?: null,
                    $addressCep ?: null,
                    $addressStreet ?: null,
                    $addressNumber ?: null,
                    $addressComplement ?: null,
                    $addressNeighborhood ?: null,
                    $addressCity ?: null,
                    $addressState ?: null,
                    $internalNotes ?: null,
                    $status,
                    $tenantId,
                ]);
            } else {
                // Atualiza todos os campos (cliente ainda não está no Asaas)
                $stmt = $db->prepare("
                    UPDATE tenants 
                    SET person_type = ?, name = ?, cpf_cnpj = ?, razao_social = ?, nome_fantasia = ?,
                        responsavel_nome = ?, responsavel_cpf = ?, email = ?, phone = ?, phone_fixed = ?,
                        address_cep = ?, address_street = ?, address_number = ?, address_complement = ?,
                        address_neighborhood = ?, address_city = ?, address_state = ?,
                        responsavel_nome = ?, responsavel_cpf = ?, email = ?, phone = ?, 
                        document = ?, internal_notes = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $personType,
                    $name,
                    $cpfCnpj,
                    $razaoSocial,
                    $nomeFantasia,
                    $responsavelNome,
                    $responsavelCpf,
                    $email ?: null,
                    $phone ?: null,
                    $phoneFixed ?: null,
                    $addressCep ?: null,
                    $addressStreet ?: null,
                    $addressNumber ?: null,
                    $addressComplement ?: null,
                    $addressNeighborhood ?: null,
                    $addressCity ?: null,
                    $addressState ?: null,
                    $cpfCnpj, // Mantém document para compatibilidade
                    $internalNotes ?: null,
                    $status,
                    $tenantId,
                ]);
            }

            // Se cliente tem asaas_customer_id, atualiza dados no Asaas também
            if ($hasAsaasCustomerId && !empty($currentTenant['asaas_customer_id'])) {
                try {
                    $updatedTenant = $db->prepare("SELECT * FROM tenants WHERE id = ?");
                    $updatedTenant->execute([$tenantId]);
                    $tenantData = $updatedTenant->fetch();
                    
                    if ($tenantData) {
                        // Atualiza customer no Asaas com dados atualizados
                        \PixelHub\Services\AsaasBillingService::syncCustomerDataToAsaas($tenantData);
                    }
                } catch (\Exception $e) {
                    // Log erro mas não bloqueia atualização local
                    error_log("Erro ao atualizar cliente no Asaas: " . $e->getMessage());
                }
            }
            
            $this->redirect('/tenants/view?id=' . $tenantId . '&success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar tenant: " . $e->getMessage());
            $this->redirect('/tenants/edit?id=' . $tenantId . '&error=database_error');
        }
    }

    /**
     * Exclui um cliente
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $tenantId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($tenantId <= 0) {
            $this->redirect('/tenants');
            return;
        }

        $db = DB::getConnection();

        // Verificar se há vínculos importantes (hospedagem, faturas, etc.)
        $stmt = $db->prepare("SELECT COUNT(*) FROM hosting_accounts WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $hostingCount = (int) $stmt->fetchColumn();

        if ($hostingCount > 0) {
            $this->redirect('/tenants/view?id=' . $tenantId . '&error=cannot_delete_has_hosting');
            return;
        }

        // Se estiver tudo ok, excluir
        try {
            $stmt = $db->prepare("DELETE FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);

            $this->redirect('/tenants?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir tenant: " . $e->getMessage());
            $this->redirect('/tenants/view?id=' . $tenantId . '&error=delete_failed');
        }
    }

    /**
     * Arquivar/desarquivar cliente (oculta da lista CRM, mantém no financeiro)
     */
    public function archive(): void
    {
        Auth::requireInternal();

        $tenantId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $action = $_POST['action'] ?? 'archive'; // 'archive' ou 'unarchive'

        if ($tenantId <= 0) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        $db = DB::getConnection();
        
        // Busca tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=not_found');
            return;
        }

        try {
            if ($action === 'archive') {
                // Arquivar: marca como arquivado e somente financeiro
                $stmt = $db->prepare("
                    UPDATE tenants 
                    SET is_archived = 1, is_financial_only = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$tenantId]);
                $successParam = 'archived';
                $message = 'Cliente arquivado com sucesso. Ele não aparecerá mais na lista de clientes, mas continuará acessível na Central de Cobrança.';
            } else {
                // Desarquivar
                $stmt = $db->prepare("
                    UPDATE tenants 
                    SET is_archived = 0, is_financial_only = 0, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$tenantId]);
                $successParam = 'unarchived';
                $message = 'Cliente desarquivado com sucesso.';
            }

            $this->redirect('/tenants/view?id=' . $tenantId . '&success=' . $successParam . '&message=' . urlencode($message));
        } catch (\Exception $e) {
            error_log("Erro ao arquivar tenant: " . $e->getMessage());
            $this->redirect('/tenants/view?id=' . $tenantId . '&error=archive_failed');
        }
    }

    /**
     * Atualiza campos do Asaas diretamente pelo sistema
     * POST /tenants/update-asaas-fields
     * 
     * Busca e consolida dados de TODOS os cadastros do Asaas para o CPF antes de atualizar.
     */
    public function updateAsaasFields(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;

        if ($tenantId <= 0) {
            $this->json(['success' => false, 'message' => 'ID do cliente não fornecido']);
            return;
        }

        $db = DB::getConnection();

        // Busca tenant atual
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->json(['success' => false, 'message' => 'Cliente não encontrado']);
            return;
        }

        try {
            // Prepara dados do formulário
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $phoneFixed = trim($_POST['phone_fixed'] ?? '');
            $addressCep = trim($_POST['address_cep'] ?? '');
            $addressStreet = trim($_POST['address_street'] ?? '');
            $addressNumber = trim($_POST['address_number'] ?? '');
            $addressComplement = trim($_POST['address_complement'] ?? '');
            $addressNeighborhood = trim($_POST['address_neighborhood'] ?? '');
            $addressCity = trim($_POST['address_city'] ?? '');
            $addressState = strtoupper(trim($_POST['address_state'] ?? ''));

            // Busca todos os customers do Asaas para este CPF e consolida dados
            $consolidatedData = [];
            $allCustomers = [];
            
            try {
                $cpfCnpj = $tenant['cpf_cnpj'] ?? $tenant['document'] ?? '';
                $cpfCnpjNormalizado = preg_replace('/[^0-9]/', '', $cpfCnpj);
                
                if (!empty($cpfCnpjNormalizado)) {
                    // Busca TODOS os customers do Asaas para este CPF
                    $allCustomers = \PixelHub\Services\AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado);
                    
                    if (!empty($allCustomers)) {
                        // Consolida dados de todos os customers
                        $consolidatedAsaasData = \PixelHub\Services\AsaasBillingService::consolidateAsaasCustomersData($allCustomers);
                        
                        // Converte para formato do tenant
                        $consolidatedData = \PixelHub\Services\AsaasBillingService::convertConsolidatedDataToTenantFormat($consolidatedAsaasData);
                    }
                }
            } catch (\Exception $e) {
                // Se falhar ao buscar do Asaas, continua com dados do formulário
                error_log("Aviso: Erro ao buscar customers do Asaas para consolidação: " . $e->getMessage());
            }

            // Mescla dados: prioriza dados do formulário, mas preenche vazios com dados consolidados do Asaas
            $finalEmail = !empty($email) ? $email : ($consolidatedData['email'] ?? $tenant['email'] ?? '');
            $finalPhone = !empty($phone) ? $phone : ($tenant['phone'] ?? '');
            $finalPhoneFixed = !empty($phoneFixed) ? $phoneFixed : ($consolidatedData['phone_fixed'] ?? $tenant['phone_fixed'] ?? '');
            $finalAddressCep = !empty($addressCep) ? $addressCep : ($consolidatedData['address_cep'] ?? $tenant['address_cep'] ?? '');
            $finalAddressStreet = !empty($addressStreet) ? $addressStreet : ($consolidatedData['address_street'] ?? $tenant['address_street'] ?? '');
            $finalAddressNumber = !empty($addressNumber) ? $addressNumber : ($consolidatedData['address_number'] ?? $tenant['address_number'] ?? '');
            $finalAddressComplement = !empty($addressComplement) ? $addressComplement : ($consolidatedData['address_complement'] ?? $tenant['address_complement'] ?? '');
            $finalAddressNeighborhood = !empty($addressNeighborhood) ? $addressNeighborhood : ($consolidatedData['address_neighborhood'] ?? $tenant['address_neighborhood'] ?? '');
            $finalAddressCity = !empty($addressCity) ? $addressCity : ($consolidatedData['address_city'] ?? $tenant['address_city'] ?? '');
            $finalAddressState = !empty($addressState) ? $addressState : ($consolidatedData['address_state'] ?? $tenant['address_state'] ?? '');

            // Atualiza no banco de dados local
            $stmt = $db->prepare("
                UPDATE tenants 
                SET email = ?, phone = ?, phone_fixed = ?,
                    address_cep = ?, address_street = ?, address_number = ?, 
                    address_complement = ?, address_neighborhood = ?, address_city = ?, address_state = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $finalEmail ?: null,
                $finalPhone ?: null,
                $finalPhoneFixed ?: null,
                $finalAddressCep ?: null,
                $finalAddressStreet ?: null,
                $finalAddressNumber ?: null,
                $finalAddressComplement ?: null,
                $finalAddressNeighborhood ?: null,
                $finalAddressCity ?: null,
                $finalAddressState ?: null,
                $tenantId
            ]);

            // Busca tenant atualizado
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $updatedTenant = $stmt->fetch();

            // Sincroniza com Asaas (todos os customers encontrados)
            try {
                // Verifica se API está configurada
                \PixelHub\Services\AsaasConfig::getConfig();

                $syncedCount = 0;
                $errors = [];

                if (!empty($allCustomers)) {
                    // Sincroniza com TODOS os customers encontrados
                    foreach ($allCustomers as $customer) {
                        try {
                            $customerId = $customer['id'] ?? null;
                            if (empty($customerId)) {
                                continue;
                            }

                            // Prepara dados para atualização no Asaas
                            $updateData = [];
                            
                            if (!empty($finalEmail)) {
                                $updateData['email'] = $finalEmail;
                            }
                            
                            // Prioriza telefone fixo se existir
                            if (!empty($finalPhoneFixed)) {
                                $phoneDigits = preg_replace('/[^0-9]/', '', $finalPhoneFixed);
                                $updateData['phone'] = $phoneDigits;
                            } elseif (!empty($finalPhone)) {
                                $phoneDigits = preg_replace('/[^0-9]/', '', $finalPhone);
                                $updateData['phone'] = $phoneDigits;
                            }

                            // Adiciona endereço se completo
                            $addressData = \PixelHub\Services\AsaasBillingService::buildAddressData($updatedTenant);
                            if (!empty($addressData)) {
                                $updateData = array_merge($updateData, $addressData);
                            }

                            // Atualiza customer no Asaas
                            if (!empty($updateData)) {
                                \PixelHub\Services\AsaasClient::updateCustomer($customerId, $updateData);
                                $syncedCount++;
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Erro ao sincronizar customer {$customerId}: " . $e->getMessage();
                            error_log("Erro ao sincronizar customer {$customerId} do Asaas: " . $e->getMessage());
                        }
                    }
                } else {
                    // Se não encontrou customers, cria ou atualiza o principal
                    if (empty($updatedTenant['asaas_customer_id'])) {
                        $asaasCustomerId = \PixelHub\Services\AsaasBillingService::ensureCustomerForTenant($updatedTenant);
                        $syncedCount = 1;
                    } else {
                        \PixelHub\Services\AsaasBillingService::syncCustomerDataToAsaas($updatedTenant);
                        $syncedCount = 1;
                    }
                }

                $message = "Campos atualizados com sucesso!";
                if ($syncedCount > 0) {
                    $message .= " Sincronizado com {$syncedCount} cadastro(s) no Asaas.";
                }
                if (!empty($errors)) {
                    $message .= " Alguns erros ocorreram: " . implode('; ', $errors);
                }

                $this->json([
                    'success' => true,
                    'message' => $message
                ]);
            } catch (\RuntimeException $e) {
                // Se erro no Asaas, ainda retorna sucesso (dados salvos localmente)
                error_log("Erro ao sincronizar com Asaas: " . $e->getMessage());
                $this->json([
                    'success' => true,
                    'message' => 'Campos atualizados localmente. Erro ao sincronizar com Asaas: ' . $e->getMessage()
                ]);
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar campos do Asaas: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erro ao atualizar campos: ' . $e->getMessage()]);
        }
    }

    /**
     * Sincroniza dados do Asaas para o tenant (busca e consolida de todos os cadastros)
     * POST /tenants/sync-asaas-data
     * 
     * Busca todos os cadastros do Asaas para o CPF, consolida os dados e atualiza o tenant local.
     */
    public function syncAsaasData(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;

        if ($tenantId <= 0) {
            $this->json(['success' => false, 'message' => 'ID do cliente não fornecido']);
            return;
        }

        $db = DB::getConnection();

        // Busca tenant atual
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->json(['success' => false, 'message' => 'Cliente não encontrado']);
            return;
        }

        try {
            // Verifica se API está configurada
            \PixelHub\Services\AsaasConfig::getConfig();

            $cpfCnpj = $tenant['cpf_cnpj'] ?? $tenant['document'] ?? '';
            $cpfCnpjNormalizado = preg_replace('/[^0-9]/', '', $cpfCnpj);

            if (empty($cpfCnpjNormalizado)) {
                $this->json(['success' => false, 'message' => 'Cliente não possui CPF/CNPJ cadastrado']);
                return;
            }

            // Busca TODOS os customers do Asaas para este CPF
            $allCustomers = \PixelHub\Services\AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado);

            if (empty($allCustomers)) {
                $this->json([
                    'success' => true,
                    'message' => 'Nenhum cadastro encontrado no Asaas para este CPF.',
                    'customers_found' => 0
                ]);
                return;
            }

            // Consolida dados de todos os customers
            $consolidatedAsaasRaw = \PixelHub\Services\AsaasBillingService::consolidateAsaasCustomersData($allCustomers);
            $consolidatedData = \PixelHub\Services\AsaasBillingService::convertConsolidatedDataToTenantFormat($consolidatedAsaasRaw);

            // Prepara dados para atualização: mescla dados consolidados com dados atuais (consolidados têm prioridade se campo local estiver vazio)
            $fieldsUpdated = [];
            
            $finalEmail = !empty($consolidatedData['email']) ? $consolidatedData['email'] : ($tenant['email'] ?? null);
            if ($finalEmail !== ($tenant['email'] ?? null)) {
                $fieldsUpdated[] = 'Email';
            }

            $finalPhone = !empty($consolidatedData['phone']) ? $consolidatedData['phone'] : ($tenant['phone'] ?? null);
            if ($finalPhone !== ($tenant['phone'] ?? null)) {
                $fieldsUpdated[] = 'WhatsApp';
            }

            $finalPhoneFixed = !empty($consolidatedData['phone_fixed']) ? $consolidatedData['phone_fixed'] : ($tenant['phone_fixed'] ?? null);
            if ($finalPhoneFixed !== ($tenant['phone_fixed'] ?? null)) {
                $fieldsUpdated[] = 'Telefone Fixo';
            }

            $finalAddressCep = !empty($consolidatedData['address_cep']) ? $consolidatedData['address_cep'] : ($tenant['address_cep'] ?? null);
            if ($finalAddressCep !== ($tenant['address_cep'] ?? null)) {
                $fieldsUpdated[] = 'CEP';
            }

            $finalAddressStreet = !empty($consolidatedData['address_street']) ? $consolidatedData['address_street'] : ($tenant['address_street'] ?? null);
            if ($finalAddressStreet !== ($tenant['address_street'] ?? null)) {
                $fieldsUpdated[] = 'Rua';
            }

            $finalAddressNumber = !empty($consolidatedData['address_number']) ? $consolidatedData['address_number'] : ($tenant['address_number'] ?? null);
            if ($finalAddressNumber !== ($tenant['address_number'] ?? null)) {
                $fieldsUpdated[] = 'Número';
            }

            $finalAddressComplement = !empty($consolidatedData['address_complement']) ? $consolidatedData['address_complement'] : ($tenant['address_complement'] ?? null);
            if ($finalAddressComplement !== ($tenant['address_complement'] ?? null)) {
                $fieldsUpdated[] = 'Complemento';
            }

            $finalAddressNeighborhood = !empty($consolidatedData['address_neighborhood']) ? $consolidatedData['address_neighborhood'] : ($tenant['address_neighborhood'] ?? null);
            if ($finalAddressNeighborhood !== ($tenant['address_neighborhood'] ?? null)) {
                $fieldsUpdated[] = 'Bairro';
            }

            // Para cidade: sempre usa dados consolidados se disponíveis e não vazios
            // Prioriza dados consolidados sobre valor atual do tenant
            $finalAddressCity = null;
            $tenantCurrentCity = $tenant['address_city'] ?? null;
            $hasTenantCity = !empty($tenantCurrentCity);
            
            if (isset($consolidatedData['address_city']) && $consolidatedData['address_city'] !== '' && $consolidatedData['address_city'] !== null) {
                $consolidatedCity = trim($consolidatedData['address_city']);
                $isNumericOnly = preg_match('/^\d+$/', $consolidatedCity);
                
                // Se a cidade consolidada tem letras, sempre usa (melhor qualidade)
                if (!$isNumericOnly) {
                    $finalAddressCity = $consolidatedCity;
                } elseif ($isNumericOnly && !$hasTenantCity) {
                    // Se consolidada é apenas numérica mas tenant não tem cidade, usa a consolidada (melhor que nada)
                    $finalAddressCity = $consolidatedCity;
                } else {
                    // Se consolidada é apenas numérica e tenant já tem cidade, mantém a do tenant
                    $finalAddressCity = $tenantCurrentCity;
                }
            } else {
                // Se não veio nos dados consolidados, mantém valor atual do tenant
                $finalAddressCity = $tenantCurrentCity;
            }
            
            // Normaliza para comparação
            $tenantCityNormalized = $tenantCurrentCity;
            if ($tenantCityNormalized !== null) {
                $tenantCityNormalized = trim($tenantCityNormalized);
            }
            $finalCityNormalized = $finalAddressCity;
            if ($finalCityNormalized !== null) {
                $finalCityNormalized = trim($finalCityNormalized);
            }
            
            // Compara: se diferentes, marca para atualização
            if ($finalCityNormalized !== $tenantCityNormalized) {
                $fieldsUpdated[] = 'Cidade';
            }

            $finalAddressState = !empty($consolidatedData['address_state']) ? $consolidatedData['address_state'] : ($tenant['address_state'] ?? null);
            if ($finalAddressState !== ($tenant['address_state'] ?? null)) {
                $fieldsUpdated[] = 'Estado';
            }

            // Atualiza no banco de dados local apenas se houver mudanças
            if (!empty($fieldsUpdated)) {
                $stmt = $db->prepare("
                    UPDATE tenants 
                    SET email = ?, phone = ?, phone_fixed = ?,
                        address_cep = ?, address_street = ?, address_number = ?, 
                        address_complement = ?, address_neighborhood = ?, address_city = ?, address_state = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $finalEmail,
                    $finalPhone,
                    $finalPhoneFixed,
                    $finalAddressCep,
                    $finalAddressStreet,
                    $finalAddressNumber,
                    $finalAddressComplement,
                    $finalAddressNeighborhood,
                    $finalAddressCity,
                    $finalAddressState,
                    $tenantId
                ]);
            }

            // Busca tenant atualizado
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $updatedTenant = $stmt->fetch();

            // Sincroniza de volta com todos os customers do Asaas
            $syncedCount = 0;
            $errors = [];

            foreach ($allCustomers as $customer) {
                try {
                    $customerId = $customer['id'] ?? null;
                    if (empty($customerId)) {
                        continue;
                    }

                    // Prepara dados para atualização no Asaas
                    $updateData = [];
                    
                    if (!empty($updatedTenant['email'])) {
                        $updateData['email'] = $updatedTenant['email'];
                    }
                    
                    // Prioriza telefone fixo se existir
                    if (!empty($updatedTenant['phone_fixed'])) {
                        $phoneDigits = preg_replace('/[^0-9]/', '', $updatedTenant['phone_fixed']);
                        $updateData['phone'] = $phoneDigits;
                    } elseif (!empty($updatedTenant['phone'])) {
                        $phoneDigits = preg_replace('/[^0-9]/', '', $updatedTenant['phone']);
                        $updateData['phone'] = $phoneDigits;
                    }

                    // Adiciona endereço se completo
                    $addressData = \PixelHub\Services\AsaasBillingService::buildAddressData($updatedTenant);
                    if (!empty($addressData)) {
                        $updateData = array_merge($updateData, $addressData);
                    }

                    // Atualiza customer no Asaas
                    if (!empty($updateData)) {
                        \PixelHub\Services\AsaasClient::updateCustomer($customerId, $updateData);
                        $syncedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erro ao sincronizar customer {$customerId}: " . $e->getMessage();
                    error_log("Erro ao sincronizar customer {$customerId} do Asaas: " . $e->getMessage());
                }
            }

            // Se não tinha asaas_customer_id, define o primeiro como principal
            if (empty($updatedTenant['asaas_customer_id']) && !empty($allCustomers[0]['id'])) {
                $stmt = $db->prepare("UPDATE tenants SET asaas_customer_id = ? WHERE id = ?");
                $stmt->execute([$allCustomers[0]['id'], $tenantId]);
            }

            $this->json([
                'success' => true,
                'message' => 'Sincronização concluída com sucesso!',
                'customers_found' => count($allCustomers),
                'fields_updated' => $fieldsUpdated,
                'customers_synced' => $syncedCount,
                'errors' => $errors
            ]);

        } catch (\RuntimeException $e) {
            error_log("Erro ao sincronizar dados do Asaas: " . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage()
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao sincronizar dados do Asaas: " . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Erro inesperado ao sincronizar. Verifique os logs.'
            ]);
        }
    }

    /**
     * Sincroniza faturas do tenant com o Asaas
     */
    public function syncBilling(): void
    {
        Auth::requireInternal();

        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;

        if ($tenantId <= 0) {
            $this->redirect('/tenants?error=missing_tenant_id');
            return;
        }

        // Verifica se tenant existe
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT id, asaas_customer_id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=tenant_not_found');
            return;
        }

        // Verifica se API está configurada (AsaasConfig::getConfig() já valida e lança exceção se não configurado)
        try {
            \PixelHub\Services\AsaasConfig::getConfig();
        } catch (\RuntimeException $e) {
            // Usa a mensagem da exception para exibir na tela
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&error=asaas_not_configured&message=' . urlencode($e->getMessage()));
            return;
        } catch (\Exception $e) {
            // Erro genérico de configuração
            error_log("Erro ao verificar configuração do Asaas: " . $e->getMessage());
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&error=asaas_config_error&message=' . urlencode('Erro na configuração do Asaas. Verifique os logs.'));
            return;
        }

        // Executa sincronização
        try {
            // Se já tem asaas_customer_id, usa método completo que também atualiza dados do customer
            if (!empty($tenant['asaas_customer_id'])) {
                $result = \PixelHub\Services\AsaasBillingService::syncCustomerAndInvoicesForTenant($tenantId);
                $invoiceStats = $result['invoices'];
                $customerUpdated = $result['customer_updated'] ? ' (dados do cliente atualizados)' : '';
                
                // Mensagem de sucesso com estatísticas
                $message = 'Sincronização concluída: ' . $invoiceStats['created'] . ' faturas criadas, ' . $invoiceStats['updated'] . ' atualizadas.' . $customerUpdated;
            } else {
                // Se não tem asaas_customer_id, usa método que cria o customer se necessário
                $stats = \PixelHub\Services\AsaasBillingService::syncInvoicesForTenant($tenantId);
                
                // Mensagem de sucesso com estatísticas
                $message = 'Sincronização concluída: ' . $stats['created'] . ' faturas criadas, ' . $stats['updated'] . ' atualizadas.';
            }
            
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&success=synced&message=' . urlencode($message));
        } catch (\RuntimeException $e) {
            // Erro específico (tenant sem CPF, API, etc.)
            $errorMessage = $e->getMessage();
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&error=sync_failed&message=' . urlencode($errorMessage));
        } catch (\Exception $e) {
            // Erro genérico
            error_log("Erro ao sincronizar faturas do tenant {$tenantId}: " . $e->getMessage());
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&error=sync_failed&message=' . urlencode('Erro inesperado ao sincronizar. Verifique os logs.'));
        }
    }

    /**
     * Endpoint para registrar log genérico de WhatsApp (modal do cliente)
     * 
     * Registra envio de mensagem genérica (não relacionada a cobrança) em whatsapp_generic_logs.
     * Este endpoint é usado pelo modal genérico do painel do cliente (tab Overview).
     * 
     * Payload esperado (JSON ou form-data):
     * - tenant_id (obrigatório)
     * - template_id (opcional - pode ser null se nenhum template foi usado)
     * - phone_raw (telefone que o usuário está vendo/editando no modal)
     * - message (conteúdo final que está no textarea, já editado pelo usuário)
     * 
     * Retorna JSON:
     * - Sucesso: { "success": true }
     * - Erro: { "success": false, "message": "..." }
     */
    public function logGenericWhatsApp(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json; charset=utf-8');

        // Lê dados do POST (suporta JSON ou form-data)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Se não conseguiu parsear JSON, tenta form-data
        if ($data === null) {
            $data = $_POST;
        }

        $tenantId = isset($data['tenant_id']) ? (int) $data['tenant_id'] : 0;
        $templateId = isset($data['template_id']) && $data['template_id'] !== '' && $data['template_id'] !== null 
            ? (int) $data['template_id'] 
            : null;
        $phoneRaw = isset($data['phone_raw']) ? trim($data['phone_raw']) : '';
        $message = isset($data['message']) ? trim($data['message']) : '';

        // Validações
        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'tenant_id é obrigatório']);
            return;
        }

        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'message é obrigatório']);
            return;
        }

        $db = DB::getConnection();

        // Valida se tenant existe
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
            return;
        }

        // Valida template_id se informado (opcional, mas se informado deve existir)
        if ($templateId !== null && $templateId > 0) {
            $stmt = $db->prepare("SELECT id FROM whatsapp_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch();

            if (!$template) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Template não encontrado']);
                return;
            }
        }

        // Normaliza telefone usando a mesma lógica existente
        $phoneNormalized = \PixelHub\Services\WhatsAppTemplateService::normalizePhone($phoneRaw);

        if (empty($phoneNormalized)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Telefone inválido ou não foi possível normalizar']);
            return;
        }

        // Insere registro em whatsapp_generic_logs
        try {
            $stmt = $db->prepare("
                INSERT INTO whatsapp_generic_logs 
                (tenant_id, template_id, phone, message, sent_at, created_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $tenantId,
                $templateId,
                $phoneNormalized,
                $message,
            ]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erro ao registrar log genérico de WhatsApp: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao registrar log. Tente novamente.']);
        }
    }

    /**
     * Endpoint AJAX: Retorna timeline atualizada de WhatsApp do tenant
     * 
     * Usado para atualizar a timeline sem recarregar a página após envio de mensagem.
     * 
     * Retorna JSON:
     * {
     *   "success": true,
     *   "timeline": [...],
     *   "lastContact": {...} | null
     * }
     */
    public function getWhatsAppTimelineAjax(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json; charset=utf-8');

        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'tenant_id é obrigatório']);
            return;
        }

        // Busca timeline unificada (mantém limite de 10 para AJAX, pois pode ser usado em outros contextos)
        $whatsappTimeline = WhatsAppHistoryService::getTimelineByTenant($tenantId, 10);
        
        // Último contato WhatsApp (primeiro item da timeline, se houver)
        $lastWhatsAppContact = !empty($whatsappTimeline) ? $whatsappTimeline[0] : null;

        echo json_encode([
            'success' => true,
            'timeline' => $whatsappTimeline,
            'lastContact' => $lastWhatsAppContact,
        ]);
    }

    /**
     * Busca clientes dinamicamente via AJAX para autocomplete
     * 
     * Agrupa tenants por CPF/CNPJ (seguindo padrão do financeiro) para evitar duplicatas.
     * Retorna apenas o tenant principal de cada grupo, mas indica quando há duplicatas.
     * 
     * GET /tenants/search-ajax?q=termo
     * Retorna JSON com lista de clientes que correspondem ao termo de busca
     */
    public function searchAjax(): void
    {
        Auth::requireInternal();

        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        
        // Requer pelo menos 3 caracteres
        if (strlen($query) < 3) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'clients' => []
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $db = DB::getConnection();
        
        // Busca clientes que correspondem ao termo (busca no nome)
        // Seguindo padrão do financeiro: filtra arquivados e busca dados para agrupamento
        $searchTerm = '%' . $query . '%';
        $stmt = $db->prepare("
            SELECT 
                t.id, 
                t.name,
                t.cpf_cnpj,
                t.document,
                t.is_archived,
                t.is_financial_only,
                t.asaas_customer_id,
                -- Conta relacionamentos para priorizar (seguindo padrão do financeiro)
                (SELECT COUNT(*) FROM projects WHERE tenant_id = t.id AND status = 'ativo') as projects_count,
                (SELECT COUNT(*) FROM hosting_accounts WHERE tenant_id = t.id) as hosting_count,
                CASE 
                    WHEN t.is_archived = 0 AND t.is_financial_only = 0 THEN 1
                    ELSE 0
                END as is_active
            FROM tenants t
            WHERE t.name LIKE ?
            ORDER BY 
                is_active DESC,  -- Não arquivados primeiro
                projects_count DESC,  -- Com mais projetos primeiro
                hosting_count DESC,   -- Com mais hospedagens primeiro
                (CASE WHEN t.asaas_customer_id IS NOT NULL THEN 1 ELSE 0 END) DESC,  -- Com Asaas vinculado
                t.id DESC  -- Mais recente
            LIMIT 30  -- Busca mais para ter dados suficientes para agrupar
        ");
        $stmt->execute([$searchTerm]);
        $allClients = $stmt->fetchAll();

        // Agrupa por CPF/CNPJ normalizado (seguindo padrão do financeiro)
        // O financeiro busca todos os customers do Asaas por CPF e agrupa - aqui fazemos o mesmo com tenants
        $groupedByCpf = [];
        $clientsWithoutCpf = [];
        
        foreach ($allClients as $client) {
            // Normaliza CPF/CNPJ (mesmo padrão do AsaasClient)
            $cpfCnpj = preg_replace('/[^0-9]/', '', $client['cpf_cnpj'] ?? $client['document'] ?? '');
            
            if (empty($cpfCnpj)) {
                // Sem CPF/CNPJ: adiciona direto (não agrupa, mas ainda filtra arquivados)
                if ((int)($client['is_active'] ?? 0) === 1) {
                    $clientsWithoutCpf[] = [
                        'id' => $client['id'],
                        'name' => $client['name'],
                        'has_duplicates' => false,
                        'duplicates_count' => 0
                    ];
                }
            } else {
                // Com CPF/CNPJ: agrupa
                if (!isset($groupedByCpf[$cpfCnpj])) {
                    // Primeiro tenant deste CPF/CNPJ
                    $groupedByCpf[$cpfCnpj] = [
                        'primary' => $client,
                        'duplicates' => [],
                        'has_duplicates' => false
                    ];
                } else {
                    // Já existe tenant com este CPF/CNPJ - é duplicata
                    $groupedByCpf[$cpfCnpj]['duplicates'][] = $client;
                    $groupedByCpf[$cpfCnpj]['has_duplicates'] = true;
                    
                    // Se este cliente é melhor que o principal atual, troca (seguindo lógica de priorização)
                    $currentPrimary = $groupedByCpf[$cpfCnpj]['primary'];
                    if ($this->isClientBetterThan($client, $currentPrimary)) {
                        // Move o atual principal para duplicatas
                        $groupedByCpf[$cpfCnpj]['duplicates'][] = $currentPrimary;
                        // Define o novo como principal
                        $groupedByCpf[$cpfCnpj]['primary'] = $client;
                    }
                }
            }
        }

        // Prepara resultado final (apenas principais, seguindo padrão do financeiro)
        $clients = [];
        
        // Adiciona grupos por CPF/CNPJ (apenas o principal, mas com flag de duplicatas)
        foreach ($groupedByCpf as $group) {
            $primary = $group['primary'];
            
            // Só adiciona se não estiver arquivado (já está ordenado, mas garante)
            if ((int)($primary['is_active'] ?? 0) === 1) {
                $duplicatesCount = count($group['duplicates'] ?? []);
                
                $clientData = [
                    'id' => $primary['id'],
                    'name' => $primary['name'],
                    'has_duplicates' => $group['has_duplicates'] ?? false,
                    'duplicates_count' => $duplicatesCount
                ];
                
                $clients[] = $clientData;
            }
        }
        
        // Adiciona clientes sem CPF/CNPJ (já filtrados por arquivados)
        foreach ($clientsWithoutCpf as $client) {
            $clients[] = $client;
        }

        // Limita a 10 resultados finais
        $clients = array_slice($clients, 0, 10);

        echo json_encode([
            'success' => true,
            'clients' => $clients
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Compara dois clientes e retorna true se client1 é "melhor" que client2
     * Usado para escolher o tenant principal quando há duplicatas com mesmo CPF/CNPJ
     * 
     * Lógica de priorização (seguindo padrão do financeiro):
     * 1. Não arquivado é melhor que arquivado
     * 2. Com mais projetos é melhor
     * 3. Com mais hospedagens é melhor
     * 4. Com asaas_customer_id vinculado é melhor
     * 5. Mais recente (maior ID) é melhor
     * 
     * @param array $client1 Primeiro cliente
     * @param array $client2 Segundo cliente
     * @return bool True se client1 é melhor que client2
     */
    private function isClientBetterThan(array $client1, array $client2): bool
    {
        // 1. Não arquivado é melhor que arquivado
        $active1 = (int)($client1['is_active'] ?? 0);
        $active2 = (int)($client2['is_active'] ?? 0);
        if ($active1 !== $active2) {
            return $active1 > $active2;
        }

        // 2. Com mais projetos é melhor
        $projects1 = (int)($client1['projects_count'] ?? 0);
        $projects2 = (int)($client2['projects_count'] ?? 0);
        if ($projects1 !== $projects2) {
            return $projects1 > $projects2;
        }

        // 3. Com mais hospedagens é melhor
        $hosting1 = (int)($client1['hosting_count'] ?? 0);
        $hosting2 = (int)($client2['hosting_count'] ?? 0);
        if ($hosting1 !== $hosting2) {
            return $hosting1 > $hosting2;
        }

        // 4. Com asaas_customer_id é melhor (importante para sincronização)
        $hasAsaas1 = !empty($client1['asaas_customer_id']);
        $hasAsaas2 = !empty($client2['asaas_customer_id']);
        if ($hasAsaas1 !== $hasAsaas2) {
            return $hasAsaas1;
        }

        // 5. Mais recente (maior ID) é melhor
        return (int)($client1['id'] ?? 0) > (int)($client2['id'] ?? 0);
    }

    /**
     * Exibe histórico completo de WhatsApp do tenant
     */
    public function whatsappHistory(): void
    {
        Auth::requireInternal();

        $tenantId = $_GET['id'] ?? null;

        if (!$tenantId) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca dados do tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=not_found');
            return;
        }

        // Busca timeline unificada com limite maior para histórico completo (100 mensagens)
        $whatsappTimeline = WhatsAppHistoryService::getTimelineByTenant((int)$tenantId, 100);

        $this->view('tenants.whatsapp_history', [
            'tenant' => $tenant,
            'whatsappTimeline' => $whatsappTimeline,
        ]);
    }
}

