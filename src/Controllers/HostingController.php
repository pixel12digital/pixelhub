<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\MoneyHelper;
use PixelHub\Services\HostingProviderService;

/**
 * Controller para gerenciar contas de hospedagem
 */
class HostingController extends Controller
{
    /**
     * Lista todas as contas de hospedagem
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Busca todos os hosting accounts com dados do tenant
        $stmt = $db->query("
            SELECT ha.*, t.name as tenant_name
            FROM hosting_accounts ha
            INNER JOIN tenants t ON ha.tenant_id = t.id
            ORDER BY ha.created_at DESC
        ");
        $hostingAccounts = $stmt->fetchAll();
        
        // Garante que last_backup_at está presente (pode ser NULL)
        foreach ($hostingAccounts as &$account) {
            if (!isset($account['last_backup_at'])) {
                $account['last_backup_at'] = null;
            }
        }
        unset($account);

        // Busca mapa de provedores para exibir nomes
        $providerMap = HostingProviderService::getSlugToNameMap();

        $this->view('hosting.index', [
            'hostingAccounts' => $hostingAccounts,
            'providerMap' => $providerMap,
        ]);
    }

    /**
     * Exibe formulário de criação de conta de hospedagem
     */
    public function create(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $tenantId = $_GET['tenant_id'] ?? null;
        $redirectTo = $_GET['redirect_to'] ?? 'hosting';

        // Busca lista de tenants para o select
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();

        // Busca planos de hospedagem ativos
        $stmt = $db->query("SELECT id, name, amount, billing_cycle FROM hosting_plans WHERE is_active = 1 ORDER BY name");
        $hostingPlans = $stmt->fetchAll();

        // Busca provedores de hospedagem ativos
        $providers = HostingProviderService::getAllActive();

        $this->view('hosting.form', [
            'tenantId' => $tenantId,
            'redirectTo' => $redirectTo,
            'tenants' => $tenants,
            'hostingPlans' => $hostingPlans,
            'hostingAccount' => null,
            'providers' => $providers,
        ]);
    }

    /**
     * Salva nova conta de hospedagem
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Recebe dados do POST
        $tenantId = $_POST['tenant_id'] ?? null;
        $domain = trim($_POST['domain'] ?? '');
        $hostingPlanId = !empty($_POST['hosting_plan_id']) ? (int) $_POST['hosting_plan_id'] : null;
        $planName = trim($_POST['plan_name'] ?? '');
        $rawAmount = $_POST['amount'] ?? '0';
        $billingCycle = $_POST['billing_cycle'] ?? 'mensal';
        $currentProvider = $_POST['current_provider'] ?? 'hostinger';
        $hostingerExpirationDate = !empty($_POST['hostinger_expiration_date']) ? $_POST['hostinger_expiration_date'] : null;
        $domainExpirationDate = !empty($_POST['domain_expiration_date']) ? $_POST['domain_expiration_date'] : null;
        $decision = $_POST['decision'] ?? 'pendente';
        $migrationStatus = $_POST['migration_status'] ?? 'nao_iniciada';
        $notes = trim($_POST['notes'] ?? '');
        $redirectTo = $_POST['redirect_to'] ?? 'hosting';

        // Validações
        if (!$tenantId) {
            $this->redirect('/hosting/create?error=missing_tenant&redirect_to=' . $redirectTo);
            return;
        }

        if (empty($domain)) {
            $this->redirect('/hosting/create?error=missing_domain&redirect_to=' . $redirectTo . ($tenantId ? '&tenant_id=' . $tenantId : ''));
            return;
        }

        // Valida se tenant existe
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            $this->redirect('/hosting/create?error=invalid_tenant&redirect_to=' . $redirectTo);
            return;
        }

        // Normaliza amount (aceita formato BR ou decimal)
        $amount = MoneyHelper::normalizeAmount($rawAmount);

        // Insere no banco
        try {
            $stmt = $db->prepare("
                INSERT INTO hosting_accounts 
                (tenant_id, domain, hosting_plan_id, plan_name, amount, billing_cycle, current_provider, 
                 hostinger_expiration_date, domain_expiration_date, decision, migration_status, notes, 
                 backup_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nenhum', NOW(), NOW())
            ");

            $stmt->execute([
                $tenantId,
                $domain,
                $hostingPlanId,
                $planName ?: null,
                $amount,
                $billingCycle,
                $currentProvider,
                $hostingerExpirationDate ?: null,
                $domainExpirationDate ?: null,
                $decision,
                $migrationStatus,
                $notes ?: null,
            ]);

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&success=created');
            } else {
                $this->redirect('/hosting?success=created');
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar hosting account: " . $e->getMessage());
            $this->redirect('/hosting/create?error=database_error&redirect_to=' . $redirectTo . ($tenantId ? '&tenant_id=' . $tenantId : ''));
        }
    }

    /**
     * Exibe formulário de edição de conta de hospedagem
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $redirectTo = $_GET['redirect_to'] ?? 'hosting';
        $tenantIdFromQuery = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;

        if ($id <= 0) {
            $this->redirect('/hosting');
            return;
        }

        // Busca conta de hospedagem
        $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            $this->redirect('/hosting?error=not_found');
            return;
        }

        // Usa tenant_id da query string se fornecido, senão usa do hostingAccount
        // Isso mantém consistência com o padrão de create() e permite fixar o tenant na edição
        $tenantId = $tenantIdFromQuery ?? $hostingAccount['tenant_id'];

        // Busca lista de tenants para o select
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();

        // Busca planos de hospedagem ativos
        $stmt = $db->query("SELECT id, name, amount, billing_cycle FROM hosting_plans WHERE is_active = 1 ORDER BY name");
        $hostingPlans = $stmt->fetchAll();

        // Busca provedores de hospedagem ativos
        $providers = HostingProviderService::getAllActive();

        $this->view('hosting.form', [
            'tenantId' => $tenantId,
            'redirectTo' => $redirectTo,
            'tenants' => $tenants,
            'hostingPlans' => $hostingPlans,
            'hostingAccount' => $hostingAccount,
            'providers' => $providers,
        ]);
    }

    /**
     * Atualiza conta de hospedagem existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $tenantId = $_POST['tenant_id'] ?? null;
        $domain = trim($_POST['domain'] ?? '');
        $hostingPlanId = !empty($_POST['hosting_plan_id']) ? (int) $_POST['hosting_plan_id'] : null;
        $planName = trim($_POST['plan_name'] ?? '');
        $rawAmount = $_POST['amount'] ?? '0';
        $billingCycle = $_POST['billing_cycle'] ?? 'mensal';
        $currentProvider = $_POST['current_provider'] ?? 'hostinger';
        $hostingerExpirationDate = !empty($_POST['hostinger_expiration_date']) ? $_POST['hostinger_expiration_date'] : null;
        $domainExpirationDate = !empty($_POST['domain_expiration_date']) ? $_POST['domain_expiration_date'] : null;
        $decision = $_POST['decision'] ?? 'pendente';
        $migrationStatus = $_POST['migration_status'] ?? 'nao_iniciada';
        $notes = trim($_POST['notes'] ?? '');
        $redirectTo = $_POST['redirect_to'] ?? 'hosting';

        if ($id <= 0) {
            $this->redirect('/hosting');
            return;
        }

        // Validações
        if (!$tenantId) {
            $this->redirect('/hosting/edit?id=' . $id . '&error=missing_tenant&redirect_to=' . $redirectTo);
            return;
        }

        if (empty($domain)) {
            $this->redirect('/hosting/edit?id=' . $id . '&error=missing_domain&redirect_to=' . $redirectTo);
            return;
        }

        // Valida se tenant existe
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            $this->redirect('/hosting/edit?id=' . $id . '&error=invalid_tenant&redirect_to=' . $redirectTo);
            return;
        }

        // Normaliza amount (aceita formato BR ou decimal)
        $amount = MoneyHelper::normalizeAmount($rawAmount);

        // Atualiza no banco
        try {
            $stmt = $db->prepare("
                UPDATE hosting_accounts 
                SET tenant_id = ?, domain = ?, hosting_plan_id = ?, plan_name = ?, amount = ?, 
                    billing_cycle = ?, current_provider = ?, hostinger_expiration_date = ?, 
                    domain_expiration_date = ?, decision = ?, migration_status = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $tenantId,
                $domain,
                $hostingPlanId,
                $planName ?: null,
                $amount,
                $billingCycle,
                $currentProvider,
                $hostingerExpirationDate ?: null,
                $domainExpirationDate ?: null,
                $decision,
                $migrationStatus,
                $notes ?: null,
                $id,
            ]);

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&success=updated');
            } else {
                $this->redirect('/hosting?success=updated');
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar hosting account: " . $e->getMessage());
            $this->redirect('/hosting/edit?id=' . $id . '&error=database_error&redirect_to=' . $redirectTo);
        }
    }
}

