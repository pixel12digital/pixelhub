<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PDO;

/**
 * Controller para gerenciamento de usuários internos
 * 
 * Roles disponíveis:
 * - admin: Acesso total, gerencia usuários e configurações
 * - operator: Acesso ao CRM, Inbox, Comunicação (sem configurações do sistema)
 * - viewer: Apenas leitura (futuro: portal do cliente)
 */
class UsersController extends Controller
{
    public const ROLES = [
        'admin'    => 'Administrador',
        'operator' => 'Operador',
        'viewer'   => 'Visualizador',
    ];

    public const ROLE_DESCRIPTIONS = [
        'admin'    => 'Acesso total ao sistema, incluindo configurações e gerenciamento de usuários',
        'operator' => 'Acesso ao CRM, Inbox, Comunicação e operações do dia a dia',
        'viewer'   => 'Apenas visualização, sem permissão para criar ou editar',
    ];

    /**
     * Lista de usuários
     * GET /settings/users
     */
    public function index(): void
    {
        Auth::requireInternal();
        $this->requireAdmin();

        $db = DB::getConnection();
        $users = $db->query("
            SELECT id, name, email, phone, is_internal, role, is_active, last_login_at, created_at, updated_at
            FROM users
            ORDER BY is_active DESC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('settings.users', [
            'users' => $users,
            'roles' => self::ROLES,
            'roleDescriptions' => self::ROLE_DESCRIPTIONS,
        ]);
    }

    /**
     * Cria novo usuário
     * POST /settings/users/store
     */
    public function store(): void
    {
        Auth::requireInternal();
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $role = trim($input['role'] ?? 'operator');
        $password = trim($input['password'] ?? '');

        // Validações
        if (empty($name)) {
            $this->json(['success' => false, 'error' => 'Nome é obrigatório'], 400);
            return;
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'E-mail válido é obrigatório'], 400);
            return;
        }
        if (empty($password) || strlen($password) < 6) {
            $this->json(['success' => false, 'error' => 'Senha deve ter no mínimo 6 caracteres'], 400);
            return;
        }
        if (!array_key_exists($role, self::ROLES)) {
            $this->json(['success' => false, 'error' => 'Perfil inválido'], 400);
            return;
        }

        $db = DB::getConnection();

        // Verifica duplicidade de email
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->json(['success' => false, 'error' => 'Já existe um usuário com este e-mail'], 400);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO users (name, email, phone, password_hash, is_internal, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$name, $email, $phone ?: null, $passwordHash, $role]);

        $userId = (int) $db->lastInsertId();

        $this->json([
            'success' => true,
            'user_id' => $userId,
            'message' => 'Usuário criado com sucesso',
        ]);
    }

    /**
     * Atualiza usuário
     * POST /settings/users/update
     */
    public function update(): void
    {
        Auth::requireInternal();
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;

        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        // Busca usuário existente
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->json(['success' => false, 'error' => 'Usuário não encontrado'], 404);
            return;
        }

        $name = trim($input['name'] ?? $user['name']);
        $email = trim($input['email'] ?? $user['email']);
        $phone = trim($input['phone'] ?? $user['phone'] ?? '');
        $role = trim($input['role'] ?? $user['role'] ?? 'operator');

        if (empty($name)) {
            $this->json(['success' => false, 'error' => 'Nome é obrigatório'], 400);
            return;
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'E-mail válido é obrigatório'], 400);
            return;
        }
        if (!array_key_exists($role, self::ROLES)) {
            $this->json(['success' => false, 'error' => 'Perfil inválido'], 400);
            return;
        }

        // Verifica duplicidade de email (excluindo o próprio)
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $this->json(['success' => false, 'error' => 'Já existe outro usuário com este e-mail'], 400);
            return;
        }

        // Impede que o próprio admin se rebaixe
        $currentUser = Auth::user();
        if ($currentUser && (int)$currentUser['id'] === $id && $role !== 'admin') {
            $this->json(['success' => false, 'error' => 'Você não pode alterar seu próprio perfil para não-administrador'], 400);
            return;
        }

        $stmt = $db->prepare("
            UPDATE users SET name = ?, email = ?, phone = ?, role = ?, updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone ?: null, $role, $id]);

        // Atualiza senha se fornecida
        $password = trim($input['password'] ?? '');
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $this->json(['success' => false, 'error' => 'Senha deve ter no mínimo 6 caracteres'], 400);
                return;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
        }

        $this->json(['success' => true, 'message' => 'Usuário atualizado com sucesso']);
    }

    /**
     * Ativa/desativa usuário
     * POST /settings/users/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID é obrigatório'], 400);
            return;
        }

        // Impede desativar a si mesmo
        $currentUser = Auth::user();
        if ($currentUser && (int)$currentUser['id'] === $id) {
            $this->json(['success' => false, 'error' => 'Você não pode desativar sua própria conta'], 400);
            return;
        }

        $db = DB::getConnection();

        $stmt = $db->prepare("SELECT id, is_active, name FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->json(['success' => false, 'error' => 'Usuário não encontrado'], 404);
            return;
        }

        $newStatus = $user['is_active'] ? 0 : 1;
        $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        $statusLabel = $newStatus ? 'ativado' : 'desativado';
        $this->json(['success' => true, 'message' => "Usuário {$statusLabel} com sucesso", 'is_active' => $newStatus]);
    }

    /**
     * Busca usuário por ID (AJAX)
     * GET /settings/users/get?id=X
     */
    public function get(): void
    {
        Auth::requireInternal();
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT id, name, email, phone, role, is_active, is_internal, last_login_at, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->json(['success' => false, 'error' => 'Usuário não encontrado'], 404);
            return;
        }

        $this->json(['success' => true, 'user' => $user]);
    }

    /**
     * Verifica se o usuário logado é admin
     */
    private function requireAdmin(): void
    {
        $user = Auth::user();
        $role = $user['role'] ?? 'admin';
        if ($role !== 'admin') {
            if ($this->isJsonRequest()) {
                $this->json(['success' => false, 'error' => 'Apenas administradores podem gerenciar usuários'], 403);
                exit;
            }
            http_response_code(403);
            echo "Acesso negado. Apenas administradores podem acessar esta área.";
            exit;
        }
    }

    private function isJsonRequest(): bool
    {
        return (
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );
    }
}
