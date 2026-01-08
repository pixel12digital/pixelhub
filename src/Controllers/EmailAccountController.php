<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\HostingProviderService;
use PDO;

/**
 * Controller para gerenciar contas de email dos clientes
 */
class EmailAccountController extends Controller
{
    /**
     * Lista contas de email de um tenant
     */
    public function index(): void
    {
        Auth::requireInternal();

        $tenantId = $_GET['tenant_id'] ?? null;
        $redirectTo = $_GET['redirect_to'] ?? 'tenant';

        if (!$tenantId) {
            $this->redirect('/tenants?error=missing_tenant_id');
            return;
        }

        $db = DB::getConnection();

        // Valida se tenant existe
        $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=tenant_not_found');
            return;
        }

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

        $this->view('email_accounts.index', [
            'tenant' => $tenant,
            'emailAccounts' => $emailAccounts,
            'redirectTo' => $redirectTo,
        ]);
    }

    /**
     * Exibe formulário de criação de conta de email
     */
    public function create(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $tenantId = $_GET['tenant_id'] ?? null;
        $redirectTo = $_GET['redirect_to'] ?? 'tenant';

        if (!$tenantId) {
            $this->redirect('/tenants?error=missing_tenant_id');
            return;
        }

        // Valida se tenant existe
        $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=tenant_not_found');
            return;
        }

        // Busca hosting accounts do tenant para vincular (opcional)
        $stmt = $db->prepare("
            SELECT id, domain 
            FROM hosting_accounts 
            WHERE tenant_id = ?
            ORDER BY domain ASC
        ");
        $stmt->execute([$tenantId]);
        $hostingAccounts = $stmt->fetchAll();

        // Busca provedores de hospedagem ativos
        try {
            $providers = HostingProviderService::getAllActive();
        } catch (\Throwable $e) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log("EmailAccountController@create: Erro ao buscar provedores: " . $e->getMessage());
            }
            $providers = [];
        }

        $this->view('email_accounts.form', [
            'tenant' => $tenant,
            'hostingAccounts' => $hostingAccounts,
            'emailAccount' => null,
            'redirectTo' => $redirectTo,
            'providers' => $providers,
        ]);
    }

    /**
     * Salva nova conta de email
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $tenantId = $_POST['tenant_id'] ?? null;
        $hostingAccountId = !empty($_POST['hosting_account_id']) ? (int) $_POST['hosting_account_id'] : null;
        $email = trim($_POST['email'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $accessUrl = trim($_POST['access_url'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $redirectTo = $_POST['redirect_to'] ?? 'tenant';

        // Validações
        if (!$tenantId) {
            $this->redirect('/email-accounts/create?error=missing_tenant_id&redirect_to=' . $redirectTo);
            return;
        }

        if (empty($email)) {
            $this->redirect('/email-accounts/create?error=missing_email&tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo);
            return;
        }

        // Valida formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/email-accounts/create?error=invalid_email&tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo);
            return;
        }

        // Valida se tenant existe
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            $this->redirect('/tenants?error=tenant_not_found');
            return;
        }

        // Valida hosting_account_id se fornecido
        if ($hostingAccountId) {
            $stmt = $db->prepare("SELECT id FROM hosting_accounts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$hostingAccountId, $tenantId]);
            if (!$stmt->fetch()) {
                $this->redirect('/email-accounts/create?error=invalid_hosting_account&tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo);
                return;
            }
        }

        // Criptografa senha se fornecida usando AES-256-CBC
        $passwordEncrypted = null;
        if (!empty($password)) {
            $passwordEncrypted = CryptoHelper::encrypt($password);
        }

        // Insere no banco
        try {
            $stmt = $db->prepare("
                INSERT INTO tenant_email_accounts 
                (tenant_id, hosting_account_id, email, provider, access_url, username, password_encrypted, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $tenantId,
                $hostingAccountId ?: null,
                $email,
                $provider ?: null,
                $accessUrl ?: null,
                $username ?: null,
                $passwordEncrypted,
                $notes ?: null,
            ]);

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&success=email_created');
            } else {
                $this->redirect('/email-accounts?tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo . '&success=created');
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar conta de email: " . $e->getMessage());
            $this->redirect('/email-accounts/create?error=database_error&tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo);
        }
    }

    /**
     * Exibe formulário de edição de conta de email
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $redirectTo = $_GET['redirect_to'] ?? 'tenant';

        if ($id <= 0) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        // Busca conta de email
        $stmt = $db->prepare("SELECT * FROM tenant_email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $emailAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emailAccount) {
            $this->redirect('/tenants?error=email_account_not_found');
            return;
        }

        $tenantId = $emailAccount['tenant_id'];

        // Busca dados do tenant
        $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=tenant_not_found');
            return;
        }

        // Busca hosting accounts do tenant
        $stmt = $db->prepare("
            SELECT id, domain 
            FROM hosting_accounts 
            WHERE tenant_id = ?
            ORDER BY domain ASC
        ");
        $stmt->execute([$tenantId]);
        $hostingAccounts = $stmt->fetchAll();

        // Busca provedores de hospedagem ativos
        try {
            $providers = HostingProviderService::getAllActive();
        } catch (\Throwable $e) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log("EmailAccountController@edit: Erro ao buscar provedores: " . $e->getMessage());
            }
            $providers = [];
        }

        $this->view('email_accounts.form', [
            'tenant' => $tenant,
            'hostingAccounts' => $hostingAccounts,
            'emailAccount' => $emailAccount,
            'redirectTo' => $redirectTo,
            'providers' => $providers,
        ]);
    }

    /**
     * Atualiza conta de email existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $tenantId = $_POST['tenant_id'] ?? null;
        $hostingAccountId = !empty($_POST['hosting_account_id']) ? (int) $_POST['hosting_account_id'] : null;
        $email = trim($_POST['email'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $accessUrl = trim($_POST['access_url'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $redirectTo = $_POST['redirect_to'] ?? 'tenant';

        if ($id <= 0) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        // Validações
        if (!$tenantId) {
            $this->redirect('/email-accounts/edit?id=' . $id . '&error=missing_tenant_id&redirect_to=' . $redirectTo);
            return;
        }

        if (empty($email)) {
            $this->redirect('/email-accounts/edit?id=' . $id . '&error=missing_email&redirect_to=' . $redirectTo);
            return;
        }

        // Valida formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/email-accounts/edit?id=' . $id . '&error=invalid_email&redirect_to=' . $redirectTo);
            return;
        }

        // Busca conta atual para manter senha se não foi alterada
        $stmt = $db->prepare("SELECT password_encrypted FROM tenant_email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();

        // Se senha não foi fornecida, mantém a existente
        $passwordEncrypted = $current['password_encrypted'] ?? null;
        if (!empty($password)) {
            $passwordEncrypted = CryptoHelper::encrypt($password);
        }

        // Atualiza no banco
        try {
            $stmt = $db->prepare("
                UPDATE tenant_email_accounts 
                SET hosting_account_id = ?, email = ?, provider = ?, 
                    access_url = ?, username = ?, password_encrypted = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $hostingAccountId ?: null,
                $email,
                $provider ?: null,
                $accessUrl ?: null,
                $username ?: null,
                $passwordEncrypted,
                $notes ?: null,
                $id,
            ]);

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&success=email_updated');
            } else {
                $this->redirect('/email-accounts?tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo . '&success=updated');
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar conta de email: " . $e->getMessage());
            $this->redirect('/email-accounts/edit?id=' . $id . '&error=database_error&redirect_to=' . $redirectTo);
        }
    }

    /**
     * Exclui uma conta de email
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $redirectTo = $_POST['redirect_to'] ?? 'tenant';

        if ($id <= 0) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        // Busca tenant_id antes de excluir
        $stmt = $db->prepare("SELECT tenant_id FROM tenant_email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $emailAccount = $stmt->fetch();

        if (!$emailAccount) {
            $this->redirect('/tenants?error=email_account_not_found');
            return;
        }

        $tenantId = $emailAccount['tenant_id'];

        try {
            $stmt = $db->prepare("DELETE FROM tenant_email_accounts WHERE id = ?");
            $stmt->execute([$id]);

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&success=email_deleted');
            } else {
                $this->redirect('/email-accounts?tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo . '&success=deleted');
            }
        } catch (\Exception $e) {
            error_log("Erro ao excluir conta de email: " . $e->getMessage());
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&error=email_delete_failed');
        }
    }

    /**
     * Retorna a senha descriptografada via AJAX
     * Requer confirmação do PIN de visualização (INFRA_VIEW_PIN)
     */
    public function getPassword(): void
    {
        Auth::requireInternal();

        // Aceita tanto POST quanto GET, mas prioriza POST
        $id = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
        $providedPin = trim($_POST['view_pin'] ?? $_GET['view_pin'] ?? '');

        if ($id <= 0) {
            error_log("getPassword: ID inválido recebido - " . ($_POST['id'] ?? $_GET['id'] ?? 'não fornecido'));
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        // Valida o PIN de visualização
        $expectedPin = \PixelHub\Core\Env::get('INFRA_VIEW_PIN') ?: '';
        
        // Se INFRA_VIEW_PIN não estiver configurado, permite visualização sem PIN
        if (empty($expectedPin)) {
            error_log("getPassword: INFRA_VIEW_PIN não configurado - permitindo acesso sem PIN para ID {$id}");
            // Continua o fluxo sem validar PIN
        } else {
            // PIN configurado: valida o PIN fornecido
            if (empty($providedPin)) {
                error_log("getPassword: PIN de visualização não fornecido para ID {$id}");
                $this->json(['error' => 'PIN de visualização não fornecido'], 400);
                return;
            }

            if ($providedPin !== $expectedPin) {
                error_log("getPassword: PIN de visualização incorreto para ID {$id}");
                $this->json(['error' => 'PIN incorreto. Tente novamente.'], 403);
                return;
            }
        }

        $db = DB::getConnection();

        // Busca conta de email
        $stmt = $db->prepare("SELECT password_encrypted FROM tenant_email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->json(['error' => 'Conta de email não encontrada'], 404);
            return;
        }

        if (empty($account['password_encrypted'])) {
            $this->json(['password' => '']);
            return;
        }

        try {
            $decryptedPassword = CryptoHelper::decrypt($account['password_encrypted']);
            $this->json(['password' => $decryptedPassword]);
        } catch (\Exception $e) {
            error_log("Erro ao descriptografar senha (ID {$id}): " . $e->getMessage());
            $this->json(['error' => 'Erro ao descriptografar senha'], 500);
        }
    }

    /**
     * Duplica uma conta de email (cria uma nova conta com todos os dados copiados)
     */
    public function duplicate(): void
    {
        Auth::requireInternal();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $redirectTo = $_GET['redirect_to'] ?? 'tenant';

        if ($id <= 0) {
            $this->redirect('/tenants?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca conta de email original
        $stmt = $db->prepare("SELECT * FROM tenant_email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $emailAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emailAccount) {
            $this->redirect('/tenants?error=email_account_not_found');
            return;
        }

        $tenantId = $emailAccount['tenant_id'];

        // Gera email único para a cópia
        $originalEmail = $emailAccount['email'];
        $newEmail = $originalEmail;
        
        // Tenta adicionar sufixo -copia, -copia2, etc até encontrar um email disponível
        $counter = 1;
        do {
            if ($counter === 1) {
                // Primeira tentativa: adiciona -copia
                if (strpos($originalEmail, '@') !== false) {
                    $parts = explode('@', $originalEmail);
                    $newEmail = $parts[0] . '-copia@' . $parts[1];
                } else {
                    $newEmail = $originalEmail . '-copia';
                }
            } else {
                // Tentativas seguintes: adiciona número
                if (strpos($originalEmail, '@') !== false) {
                    $parts = explode('@', $originalEmail);
                    $newEmail = $parts[0] . '-copia' . $counter . '@' . $parts[1];
                } else {
                    $newEmail = $originalEmail . '-copia' . $counter;
                }
            }
            
            // Verifica se o email já existe
            $stmt = $db->prepare("SELECT id FROM tenant_email_accounts WHERE email = ?");
            $stmt->execute([$newEmail]);
            $exists = $stmt->fetch();
            
            if (!$exists) {
                break; // Email disponível
            }
            
            $counter++;
            
            // Limite de segurança para evitar loop infinito
            if ($counter > 100) {
                $newEmail = $originalEmail . '-copia-' . time();
                break;
            }
        } while (true);

        // Copia todos os dados da conta original
        try {
            $stmt = $db->prepare("
                INSERT INTO tenant_email_accounts 
                (tenant_id, hosting_account_id, email, provider, access_url, username, password_encrypted, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $emailAccount['tenant_id'],
                $emailAccount['hosting_account_id'],
                $newEmail,
                $emailAccount['provider'],
                $emailAccount['access_url'],
                $emailAccount['username'],
                $emailAccount['password_encrypted'], // Copia a senha criptografada
                $emailAccount['notes'],
            ]);

            $newId = $db->lastInsertId();

            // Redireciona com mensagem de sucesso
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&success=email_duplicated');
            } else {
                $this->redirect('/email-accounts?tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo . '&success=duplicated');
            }
        } catch (\Exception $e) {
            error_log("Erro ao duplicar conta de email: " . $e->getMessage());
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=hosting&error=duplicate_failed');
            } else {
                $this->redirect('/email-accounts?tenant_id=' . $tenantId . '&redirect_to=' . $redirectTo . '&error=duplicate_failed');
            }
        }
    }
}

