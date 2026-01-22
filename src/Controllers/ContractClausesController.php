<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\ContractClauseService;

/**
 * Controller para gerenciar cláusulas de contrato configuráveis
 */
class ContractClausesController extends Controller
{
    /**
     * Lista todas as cláusulas
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            $clauses = ContractClauseService::getAllClauses();
            $error = null;
        } catch (\PDOException $e) {
            // Se a tabela não existe, mostra mensagem amigável
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Table") !== false) {
                $clauses = [];
                $error = 'Tabela contract_clauses não encontrada. Execute a migration primeiro.';
            } else {
                throw $e;
            }
        }

        $this->view('contract_clauses.index', [
            'clauses' => $clauses,
            'error' => $error ?? null,
        ]);
    }

    /**
     * Exibe formulário de criação
     */
    public function create(): void
    {
        Auth::requireInternal();

        $this->view('contract_clauses.form', [
            'clause' => null,
        ]);
    }

    /**
     * Salva nova cláusula
     */
    public function store(): void
    {
        Auth::requireInternal();

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $orderIndex = !empty($_POST['order_index']) ? (int) $_POST['order_index'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            ContractClauseService::createClause([
                'title' => $title,
                'content' => $content,
                'order_index' => $orderIndex,
                'is_active' => $isActive,
            ]);

            $this->redirect('/settings/contract-clauses?success=created');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/settings/contract-clauses/create?error=' . urlencode($e->getMessage()));
        } catch (\RuntimeException $e) {
            // Erros de runtime (como tabela não existe) são mostrados diretamente
            error_log("Erro ao criar cláusula: " . $e->getMessage());
            $this->redirect('/settings/contract-clauses/create?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao criar cláusula: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->redirect('/settings/contract-clauses/create?error=' . urlencode('Erro ao criar cláusula: ' . $e->getMessage()));
        }
    }

    /**
     * Exibe formulário de edição
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = !empty($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$id) {
            $this->redirect('/settings/contract-clauses?error=missing_id');
            return;
        }

        $clause = ContractClauseService::findClause($id);

        if (!$clause) {
            $this->redirect('/settings/contract-clauses?error=not_found');
            return;
        }

        $this->view('contract_clauses.form', [
            'clause' => $clause,
        ]);
    }

    /**
     * Atualiza cláusula
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;

        if (!$id) {
            $this->redirect('/settings/contract-clauses?error=missing_id');
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $orderIndex = !empty($_POST['order_index']) ? (int) $_POST['order_index'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            ContractClauseService::updateClause($id, [
                'title' => $title,
                'content' => $content,
                'order_index' => $orderIndex,
                'is_active' => $isActive,
            ]);

            $this->redirect('/settings/contract-clauses?success=updated');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/settings/contract-clauses/edit?id=' . $id . '&error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao atualizar cláusula: " . $e->getMessage());
            $this->redirect('/settings/contract-clauses/edit?id=' . $id . '&error=Erro ao atualizar cláusula');
        }
    }

    /**
     * Deleta cláusula
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;

        if (!$id) {
            $this->json(['error' => 'ID da cláusula é obrigatório'], 400);
            return;
        }

        try {
            ContractClauseService::deleteClause($id);
            $this->json(['success' => true, 'message' => 'Cláusula deletada com sucesso']);
        } catch (\Exception $e) {
            error_log("Erro ao deletar cláusula: " . $e->getMessage());
            $this->json(['error' => 'Erro ao deletar cláusula'], 500);
        }
    }
}

