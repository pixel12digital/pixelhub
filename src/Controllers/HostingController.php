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
        
        // Campos de credenciais
        $hostingPanelUrl = trim($_POST['hosting_panel_url'] ?? '');
        $hostingPanelUsername = trim($_POST['hosting_panel_username'] ?? '');
        $hostingPanelPassword = trim($_POST['hosting_panel_password'] ?? '');
        $siteAdminUrl = trim($_POST['site_admin_url'] ?? '');
        $siteAdminUsername = trim($_POST['site_admin_username'] ?? '');
        $siteAdminPassword = trim($_POST['site_admin_password'] ?? '');

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
                 hosting_panel_url, hosting_panel_username, hosting_panel_password,
                 site_admin_url, site_admin_username, site_admin_password,
                 backup_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nenhum', NOW(), NOW())
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
                $hostingPanelUrl ?: null,
                $hostingPanelUsername ?: null,
                $hostingPanelPassword ?: null,
                $siteAdminUrl ?: null,
                $siteAdminUsername ?: null,
                $siteAdminPassword ?: null,
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
        
        // Campos de credenciais
        $hostingPanelUrl = trim($_POST['hosting_panel_url'] ?? '');
        $hostingPanelUsername = trim($_POST['hosting_panel_username'] ?? '');
        $hostingPanelPassword = trim($_POST['hosting_panel_password'] ?? '');
        $siteAdminUrl = trim($_POST['site_admin_url'] ?? '');
        $siteAdminUsername = trim($_POST['site_admin_username'] ?? '');
        $siteAdminPassword = trim($_POST['site_admin_password'] ?? '');

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
            // Se senha não foi fornecida, mantém a existente
            $currentAccount = $db->prepare("SELECT hosting_panel_password, site_admin_password FROM hosting_accounts WHERE id = ?");
            $currentAccount->execute([$id]);
            $current = $currentAccount->fetch();
            
            $finalHostingPassword = !empty($hostingPanelPassword) ? $hostingPanelPassword : ($current['hosting_panel_password'] ?? null);
            $finalSitePassword = !empty($siteAdminPassword) ? $siteAdminPassword : ($current['site_admin_password'] ?? null);
            
            $stmt = $db->prepare("
                UPDATE hosting_accounts 
                SET tenant_id = ?, domain = ?, hosting_plan_id = ?, plan_name = ?, amount = ?, 
                    billing_cycle = ?, current_provider = ?, hostinger_expiration_date = ?, 
                    domain_expiration_date = ?, decision = ?, migration_status = ?, notes = ?, 
                    hosting_panel_url = ?, hosting_panel_username = ?, hosting_panel_password = ?,
                    site_admin_url = ?, site_admin_username = ?, site_admin_password = ?,
                    updated_at = NOW()
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
                $hostingPanelUrl ?: null,
                $hostingPanelUsername ?: null,
                $finalHostingPassword,
                $siteAdminUrl ?: null,
                $siteAdminUsername ?: null,
                $finalSitePassword,
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

    /**
     * Retorna dados de uma conta de hospedagem via AJAX (para modal de detalhes)
     */
    public function view(): void
    {
        Auth::requireInternal();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido']);
            return;
        }

        $db = DB::getConnection();

        // Busca conta de hospedagem
        $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Conta de hospedagem não encontrada']);
            return;
        }

        // Busca nome do provedor
        $providerMap = HostingProviderService::getSlugToNameMap();
        $providerSlug = $hostingAccount['current_provider'] ?? '';
        $providerName = $providerMap[$providerSlug] ?? $providerSlug;

        // Calcula status (reutiliza lógica da view)
        $calculateStatus = function($expirationDate, $type = '') {
            if (empty($expirationDate)) {
                $text = $type === 'domain' ? 'Domínio: Sem data' : ($type === 'hosting' ? 'Hospedagem: Sem data' : 'Sem data');
                return [
                    'text' => $text,
                    'style' => 'background: #e9ecef; color: #6c757d; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                ];
            }
            
            $expDate = strtotime($expirationDate);
            $today = strtotime('today');
            $daysLeft = floor(($expDate - $today) / (60 * 60 * 24));
            
            $daysInfo = '';
            if ($daysLeft > 0) {
                $daysInfo = $daysLeft == 1 ? ' (vence em 1 dia)' : ' (vence em ' . $daysLeft . ' dias)';
            } elseif ($daysLeft == 0) {
                $daysInfo = ' (vence hoje)';
            } else {
                $daysOverdue = abs($daysLeft);
                $daysInfo = $daysOverdue == 1 ? ' (vencido há 1 dia)' : ' (vencido há ' . $daysOverdue . ' dias)';
            }
            
            if ($daysLeft > 30) {
                $statusText = $type === 'domain' ? 'Domínio: Ativo' : ($type === 'hosting' ? 'Hospedagem: Ativa' : 'Ativo');
                $text = $statusText . $daysInfo;
                return [
                    'text' => $text,
                    'style' => 'background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                ];
            } elseif ($daysLeft >= 15 && $daysLeft <= 30) {
                $statusText = $type === 'domain' ? 'Domínio: Vencendo' : ($type === 'hosting' ? 'Hospedagem: Vencendo' : 'Vencendo');
                $text = $statusText . $daysInfo;
                return [
                    'text' => $text,
                    'style' => 'background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                ];
            } elseif ($daysLeft >= 0 && $daysLeft < 15) {
                $statusText = $type === 'domain' ? 'Domínio: Urgente' : ($type === 'hosting' ? 'Hospedagem: Urgente' : 'Urgente');
                $text = $statusText . $daysInfo;
                return [
                    'text' => $text,
                    'style' => 'background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                ];
            } else {
                $statusText = $type === 'domain' ? 'Domínio: Vencido' : ($type === 'hosting' ? 'Hospedagem: Vencida' : 'Vencido');
                $text = $statusText . $daysInfo;
                return [
                    'text' => $text,
                    'style' => 'background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                ];
            }
        };

        $hostingStatus = $calculateStatus($hostingAccount['hostinger_expiration_date'] ?? null, 'hosting');
        $domainStatus = $calculateStatus($hostingAccount['domain_expiration_date'] ?? null, 'domain');

        // Formata valor
        $amount = $hostingAccount['amount'] ?? 0;
        $billingCycle = $hostingAccount['billing_cycle'] ?? 'mensal';
        $amountFormatted = $amount > 0 ? 'R$ ' . number_format($amount, 2, ',', '.') . ' / ' . $billingCycle : '-';

        // Retorna JSON
        header('Content-Type: application/json');
        echo json_encode([
            'id' => $hostingAccount['id'],
            'domain' => $hostingAccount['domain'],
            'provider' => $providerName,
            'plan_name' => $hostingAccount['plan_name'] ?? '-',
            'amount' => $amountFormatted,
            'hosting_status' => $hostingStatus,
            'domain_status' => $domainStatus,
            'hosting_panel_url' => $hostingAccount['hosting_panel_url'] ?? '',
            'hosting_panel_username' => $hostingAccount['hosting_panel_username'] ?? '',
            'hosting_panel_password' => $hostingAccount['hosting_panel_password'] ?? '',
            'site_admin_url' => $hostingAccount['site_admin_url'] ?? '',
            'site_admin_username' => $hostingAccount['site_admin_username'] ?? '',
            'site_admin_password' => $hostingAccount['site_admin_password'] ?? '',
            'hostinger_expiration_date' => $hostingAccount['hostinger_expiration_date'] ?? null,
            'domain_expiration_date' => $hostingAccount['domain_expiration_date'] ?? null,
        ]);
    }
}

