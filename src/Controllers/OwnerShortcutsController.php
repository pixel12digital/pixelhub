<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\OwnerShortcutsService;

/**
 * Controller para gerenciar acessos e links de infraestrutura
 */
class OwnerShortcutsController extends Controller
{
    /**
     * Lista todos os acessos
     */
    public function index(): void
    {
        Auth::requireInternal();

        $shortcuts = OwnerShortcutsService::getAll();
        $categoryLabels = OwnerShortcutsService::getCategoryLabels();

        $this->view('owner_shortcuts.index', [
            'shortcuts' => $shortcuts,
            'categoryLabels' => $categoryLabels,
        ]);
    }

    /**
     * Cria um novo acesso
     */
    public function store(): void
    {
        Auth::requireInternal();

        try {
            $id = OwnerShortcutsService::create($_POST);
            $this->redirect('/owner/shortcuts?success=created');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/owner/shortcuts?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao criar acesso: " . $e->getMessage());
            $this->redirect('/owner/shortcuts?error=database_error');
        }
    }

    /**
     * Atualiza um acesso existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/owner/shortcuts?error=missing_id');
            return;
        }

        try {
            OwnerShortcutsService::update($id, $_POST);
            $this->redirect('/owner/shortcuts?success=updated');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/owner/shortcuts?error=' . urlencode($e->getMessage()));
        } catch (\RuntimeException $e) {
            $this->redirect('/owner/shortcuts?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao atualizar acesso: " . $e->getMessage());
            $this->redirect('/owner/shortcuts?error=database_error');
        }
    }

    /**
     * Exclui um acesso
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/owner/shortcuts?error=missing_id');
            return;
        }

        try {
            OwnerShortcutsService::delete($id);
            $this->redirect('/owner/shortcuts?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir acesso: " . $e->getMessage());
            $this->redirect('/owner/shortcuts?error=delete_failed');
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

        try {
            $password = OwnerShortcutsService::getDecryptedPassword($id);
            if (empty($password)) {
                $this->json(['error' => 'Senha não encontrada ou vazia'], 404);
                return;
            }
            $this->json(['password' => $password]);
        } catch (\RuntimeException $e) {
            error_log("Erro ao obter senha (ID {$id}): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            error_log("Erro ao obter senha (ID {$id}): " . $e->getMessage());
            $this->json(['error' => 'Erro ao obter senha'], 500);
        }
    }
}

