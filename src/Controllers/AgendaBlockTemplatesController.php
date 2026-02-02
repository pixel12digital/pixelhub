<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar modelos de blocos de agenda (templates)
 * Define quais blocos são criados ao clicar em "Gerar Blocos do Dia"
 */
class AgendaBlockTemplatesController extends Controller
{
    private const DIAS_SEMANA = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Lista todos os templates agrupados por dia
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT t.*, bt.nome as tipo_nome, bt.codigo as tipo_codigo, bt.cor_hex as tipo_cor
                FROM agenda_block_templates t
                INNER JOIN agenda_block_types bt ON t.tipo_id = bt.id
                ORDER BY t.dia_semana ASC, t.hora_inicio ASC
            ");
            $templates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $tipos = $this->getTipos($db);
        } catch (\PDOException $e) {
            $templates = [];
            $tipos = [];
            if (strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e;
            }
        }

        $this->view('agenda_block_templates.index', [
            'templates' => $templates,
            'tipos' => $tipos,
            'diasSemana' => self::DIAS_SEMANA,
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();
        $tipos = $this->getTipos($db);

        $this->view('agenda_block_templates.form', [
            'template' => null,
            'tipos' => $tipos,
            'diasSemana' => self::DIAS_SEMANA,
        ]);
    }

    /**
     * Salva novo template
     */
    public function store(): void
    {
        Auth::requireInternal();

        $diaSemana = (int)($_POST['dia_semana'] ?? 0);
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');
        $tipoId = (int)($_POST['tipo_id'] ?? 0);
        $descricao = trim($_POST['descricao_padrao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($diaSemana < 1 || $diaSemana > 7 || empty($horaInicio) || empty($horaFim) || $tipoId <= 0) {
            $this->redirect('/settings/agenda-block-templates/create?error=' . urlencode('Dia, horários e tipo são obrigatórios.'));
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                INSERT INTO agenda_block_templates (dia_semana, hora_inicio, hora_fim, tipo_id, descricao_padrao, ativo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$diaSemana, $horaInicio, $horaFim, $tipoId, $descricao ?: null, $ativo]);
            $this->redirect('/settings/agenda-block-templates?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar template: " . $e->getMessage());
            $this->redirect('/settings/agenda-block-templates/create?error=' . urlencode($e->getMessage()));
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
            $this->redirect('/settings/agenda-block-templates?error=missing_id');
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT t.*, bt.nome as tipo_nome, bt.codigo as tipo_codigo
            FROM agenda_block_templates t
            INNER JOIN agenda_block_types bt ON t.tipo_id = bt.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template) {
            $this->redirect('/settings/agenda-block-templates?error=not_found');
            return;
        }

        $tipos = $this->getTipos($db);

        $this->view('agenda_block_templates.form', [
            'template' => $template,
            'tipos' => $tipos,
            'diasSemana' => self::DIAS_SEMANA,
        ]);
    }

    /**
     * Atualiza template
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-templates?error=missing_id');
            return;
        }

        $diaSemana = (int)($_POST['dia_semana'] ?? 0);
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');
        $tipoId = (int)($_POST['tipo_id'] ?? 0);
        $descricao = trim($_POST['descricao_padrao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($diaSemana < 1 || $diaSemana > 7 || empty($horaInicio) || empty($horaFim) || $tipoId <= 0) {
            $this->redirect('/settings/agenda-block-templates/edit?id=' . $id . '&error=' . urlencode('Dia, horários e tipo são obrigatórios.'));
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                UPDATE agenda_block_templates
                SET dia_semana = ?, hora_inicio = ?, hora_fim = ?, tipo_id = ?, descricao_padrao = ?, ativo = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$diaSemana, $horaInicio, $horaFim, $tipoId, $descricao ?: null, $ativo, $id]);
            $this->redirect('/settings/agenda-block-templates?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar template: " . $e->getMessage());
            $this->redirect('/settings/agenda-block-templates/edit?id=' . $id . '&error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Exclui template
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/agenda-block-templates?error=missing_id');
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("DELETE FROM agenda_block_templates WHERE id = ?");
            $stmt->execute([$id]);
            $this->redirect('/settings/agenda-block-templates?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir template: " . $e->getMessage());
            $this->redirect('/settings/agenda-block-templates?error=' . urlencode($e->getMessage()));
        }
    }

    private function getTipos(\PDO $db): array
    {
        $stmt = $db->query("SELECT id, nome, codigo FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
