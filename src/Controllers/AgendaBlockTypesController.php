<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar tipos de blocos de agenda (FUTURE, CLIENTES, etc.)
 * Define os tipos exibidos no select "Tipo de bloco"
 */
class AgendaBlockTypesController extends Controller
{
    /**
     * Lista todos os tipos
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT t.*, 
                    (SELECT COUNT(*) FROM agenda_block_templates WHERE tipo_id = t.id AND ativo = 1) as templates_count,
                    (SELECT COUNT(*) FROM agenda_blocks WHERE tipo_id = t.id) as blocks_count
                FROM agenda_block_types t
                ORDER BY t.nome ASC
            ");
            $types = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $types = [];
            if (strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e;
            }
        }

        $this->view('agenda_block_types.index', [
            'types' => $types,
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Auth::requireInternal();

        $this->view('agenda_block_types.form', [
            'type' => null,
        ]);
    }

    /**
     * Salva novo tipo
     */
    public function store(): void
    {
        Auth::requireInternal();

        $nome = trim($_POST['nome'] ?? '');
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $corHex = trim($_POST['cor_hex'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome) || empty($codigo)) {
            $this->redirect('/settings/agenda-block-types/create?error=' . urlencode('Nome e código são obrigatórios.'));
            return;
        }

        $codigo = preg_replace('/[^A-Z0-9_]/', '', $codigo);
        if (empty($codigo)) {
            $this->redirect('/settings/agenda-block-types/create?error=' . urlencode('Código deve conter apenas letras, números e underscore.'));
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                INSERT INTO agenda_block_types (nome, codigo, cor_hex, descricao, ativo)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nome,
                $codigo,
                $corHex ?: null,
                $descricao ?: null,
                $ativo,
            ]);
            $this->redirect('/settings/agenda-block-types?success=created');
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->redirect('/settings/agenda-block-types/create?error=' . urlencode('Código já existe. Use outro.'));
            } else {
                error_log("Erro ao criar tipo: " . $e->getMessage());
                $this->redirect('/settings/agenda-block-types/create?error=' . urlencode($e->getMessage()));
            }
        }
    }

    /**
     * Formulário de edição
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-types?error=missing_id');
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM agenda_block_types WHERE id = ?");
        $stmt->execute([$id]);
        $type = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$type) {
            $this->redirect('/settings/agenda-block-types?error=not_found');
            return;
        }

        $this->view('agenda_block_types.form', [
            'type' => $type,
        ]);
    }

    /**
     * Atualiza tipo
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-types?error=missing_id');
            return;
        }

        $nome = trim($_POST['nome'] ?? '');
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $corHex = trim($_POST['cor_hex'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome) || empty($codigo)) {
            $this->redirect('/settings/agenda-block-types/edit?id=' . $id . '&error=' . urlencode('Nome e código são obrigatórios.'));
            return;
        }

        $codigo = preg_replace('/[^A-Z0-9_]/', '', $codigo);
        if (empty($codigo)) {
            $this->redirect('/settings/agenda-block-types/edit?id=' . $id . '&error=' . urlencode('Código deve conter apenas letras, números e underscore.'));
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                UPDATE agenda_block_types
                SET nome = ?, codigo = ?, cor_hex = ?, descricao = ?, ativo = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nome, $codigo, $corHex ?: null, $descricao ?: null, $ativo, $id]);
            $this->redirect('/settings/agenda-block-types?success=updated');
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->redirect('/settings/agenda-block-types/edit?id=' . $id . '&error=' . urlencode('Código já existe. Use outro.'));
            } else {
                error_log("Erro ao atualizar tipo: " . $e->getMessage());
                $this->redirect('/settings/agenda-block-types/edit?id=' . $id . '&error=' . urlencode($e->getMessage()));
            }
        }
    }

    /**
     * Exclui tipo (soft delete: ativo = 0)
     * Não remove do banco para preservar blocos/templates existentes
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-types?error=missing_id');
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("UPDATE agenda_block_types SET ativo = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $this->redirect('/settings/agenda-block-types?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir tipo: " . $e->getMessage());
            $this->redirect('/settings/agenda-block-types?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Reativa tipo (ativo = 1)
     */
    public function restore(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-types?error=missing_id');
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("UPDATE agenda_block_types SET ativo = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $this->redirect('/settings/agenda-block-types?success=restored');
        } catch (\Exception $e) {
            error_log("Erro ao reativar tipo: " . $e->getMessage());
            $this->redirect('/settings/agenda-block-types?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Exclui tipo permanentemente (hard delete)
     * Só permite se não houver modelos nem blocos usando o tipo
     */
    public function hardDelete(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-types?error=missing_id');
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) as c FROM agenda_block_templates WHERE tipo_id = ?");
            $stmt->execute([$id]);
            $templatesCount = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['c'];

            $stmt = $db->prepare("SELECT COUNT(*) as c FROM agenda_blocks WHERE tipo_id = ?");
            $stmt->execute([$id]);
            $blocksCount = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['c'];

            if ($templatesCount > 0 || $blocksCount > 0) {
                $this->redirect('/settings/agenda-block-types?error=' . urlencode("Não é possível excluir: tipo em uso por {$templatesCount} modelo(s) e {$blocksCount} bloco(s). Desative primeiro."));
                return;
            }

            $stmt = $db->prepare("DELETE FROM agenda_block_types WHERE id = ?");
            $stmt->execute([$id]);
            $this->redirect('/settings/agenda-block-types?success=hard_deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir tipo: " . $e->getMessage());
            $this->redirect('/settings/agenda-block-types?error=' . urlencode($e->getMessage()));
        }
    }
}
