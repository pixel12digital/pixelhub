<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;

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
        $this->view('auth.login', ['error' => $error]);
    }

    /**
     * Processa login
     */
    public function login(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->redirect('/login?error=empty');
        }

        $user = Auth::login($email, $password);

        if ($user) {
            // Redireciona baseado no tipo de usuário
            if (Auth::isInternal()) {
                $this->redirect('/dashboard');
            } else {
                $this->redirect('/cliente');
            }
        } else {
            $this->redirect('/login?error=invalid');
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

