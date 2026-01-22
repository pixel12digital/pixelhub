<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\Security;
use PixelHub\Core\RateLimiter;

/**
 * Controller para autenticação
 */
class AuthController extends Controller
{
    /**
     * Exibe formulário de login
     */
    public function loginForm(): void
    {
        // Se já estiver logado, redireciona para dashboard
        if (Auth::check()) {
            $this->redirect('/dashboard');
            return;
        }

        $error = $_GET['error'] ?? null;
        $csrfToken = Security::csrfToken();
        $this->view('auth.login', ['error' => $error, 'csrf_token' => $csrfToken]);
    }

    /**
     * Processa login
     */
    public function login(): void
    {
        // Valida CSRF token (se fornecido - compatível com formulários antigos)
        $csrfToken = $_POST['csrf_token'] ?? null;
        if ($csrfToken && !Security::validateCsrf($csrfToken)) {
            error_log("Tentativa de login com CSRF token inválido");
            $this->redirect('/login?error=csrf');
            return;
        }
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validação básica
        if (empty($email) || empty($password)) {
            $this->redirect('/login?error=empty');
            return;
        }
        
        // Valida formato de email
        if (!Security::validateEmail($email)) {
            $this->redirect('/login?error=invalid_email');
            return;
        }
        
        // Sanitiza email
        $email = Security::sanitize($email);

        $user = Auth::login($email, $password);

        if ($user) {
            // Redireciona baseado no tipo de usuário
            if (Auth::isInternal()) {
                $this->redirect('/dashboard');
            } else {
                $this->redirect('/cliente');
            }
        } else {
            // Verifica se está em lockout
            $rateLimitKey = 'login_' . md5($email . ($_SERVER['REMOTE_ADDR'] ?? ''));
            $lockoutTime = RateLimiter::getLockoutTimeRemaining($rateLimitKey);
            
            if ($lockoutTime > 0) {
                $this->redirect('/login?error=locked&time=' . $lockoutTime);
            } else {
                $this->redirect('/login?error=invalid');
            }
        }
    }

    /**
     * Faz logout
     */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}

