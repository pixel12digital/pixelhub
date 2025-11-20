<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Storage;
use PixelHub\Services\HostingProviderService;
use PixelHub\Services\TaskService;

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
            
            // Verifica existência dos arquivos físicos
            foreach ($backups as &$backup) {
                $backup['file_exists'] = Storage::fileExists($backup['stored_path']);
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

        // Busca últimas notificações WhatsApp do tenant
        $stmt = $db->prepare("
            SELECT bn.*, bi.due_date, bi.amount
            FROM billing_notifications bn
            LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id
            WHERE bn.tenant_id = ?
            ORDER BY bn.sent_at DESC, bn.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$tenantId]);
        $whatsappNotifications = $stmt->fetchAll();

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

        $this->view('tenants.view', [
            'tenant' => $tenant,
            'hostingAccounts' => $hostingAccounts,
            'backups' => $backups,
            'invoices' => $invoices,
            'overdueCount' => $overdueCount,
            'whatsappNotifications' => $whatsappNotifications,
            'activeTab' => $activeTab,
            'asaasCustomersByCpf' => $asaasCustomersByCpf,
            'asaasPrimaryCustomerId' => $asaasPrimaryCustomerId,
            'providerMap' => $providerMap,
            'tasks' => $tasks,
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
        $whereSql = '';
        $params = [];

        if ($search !== null && $search !== '') {
            $whereSql = " WHERE (
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
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Recebe dados do POST
        $personType = $_POST['person_type'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $createHosting = isset($_POST['create_hosting']) && $_POST['create_hosting'] == '1';

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

        // Insere no banco
        try {
            $stmt = $db->prepare("
                INSERT INTO tenants 
                (person_type, name, cpf_cnpj, razao_social, nome_fantasia, 
                 responsavel_nome, responsavel_cpf, email, phone, document, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
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
                $cpfCnpj, // Mantém document para compatibilidade
                $status,
            ]);

            $tenantId = (int) $db->lastInsertId();

            // Fluxo pós-salvar
            if ($createHosting) {
                // Já vai direto para criação de hospedagem desse cliente
                $this->redirect('/hosting/create?tenant_id=' . $tenantId . '&redirect_to=tenant');
            } else {
                // Vai para painel do cliente
                $this->redirect('/tenants/view?id=' . $tenantId . '&success=created');
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar tenant: " . $e->getMessage());
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
        $status = $_POST['status'] ?? 'active';
        $internalNotes = trim($_POST['internal_notes'] ?? '');

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
                        responsavel_nome = ?, responsavel_cpf = ?, phone = ?, 
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
                    $internalNotes ?: null,
                    $status,
                    $tenantId,
                ]);
            } else {
                // Atualiza todos os campos (cliente ainda não está no Asaas)
                $stmt = $db->prepare("
                    UPDATE tenants 
                    SET person_type = ?, name = ?, cpf_cnpj = ?, razao_social = ?, nome_fantasia = ?,
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
                    $cpfCnpj, // Mantém document para compatibilidade
                    $internalNotes ?: null,
                    $status,
                    $tenantId,
                ]);
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
}

