<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\ServiceService;

/**
 * Controller para gerenciar catálogo de serviços pontuais
 * 
 * Gerencia o catálogo de serviços oferecidos pela agência
 * (ex: Criação de Site, Logo, Cartão de Visita, etc.)
 */
class ServicesController extends Controller
{
    /**
     * Lista todos os serviços
     * 
     * GET /services
     */
    public function index(): void
    {
        Auth::requireInternal();

        $category = $_GET['category'] ?? null;
        $activeOnly = !isset($_GET['show_inactive']) || $_GET['show_inactive'] != '1';
        
        $services = ServiceService::getAllServices($category, $activeOnly);
        $categories = ServiceService::getCategories();

        $this->view('services.index', [
            'services' => $services,
            'categories' => $categories,
            'selectedCategory' => $category,
            'activeOnly' => $activeOnly,
        ]);
    }

    /**
     * Exibe formulário de criação
     * 
     * GET /services/create
     */
    public function create(): void
    {
        Auth::requireInternal();

        $categories = ServiceService::getCategories();

        $this->view('services.form', [
            'service' => null,
            'categories' => $categories,
        ]);
    }

    /**
     * Salva novo serviço
     * 
     * POST /services/store
     */
    public function store(): void
    {
        Auth::requireInternal();

        try {
            $id = ServiceService::createService($_POST);
            $this->redirect('/services?success=created');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/services/create?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao criar serviço: " . $e->getMessage());
            $this->redirect('/services/create?error=database_error');
        }
    }

    /**
     * Exibe formulário de edição
     * 
     * GET /services/edit?id={id}
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/services');
            return;
        }

        $service = ServiceService::findService($id);

        if (!$service) {
            $this->redirect('/services?error=not_found');
            return;
        }

        $categories = ServiceService::getCategories();

        $this->view('services.form', [
            'service' => $service,
            'categories' => $categories,
        ]);
    }

    /**
     * Atualiza serviço existente
     * 
     * POST /services/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/services');
            return;
        }

        try {
            ServiceService::updateService($id, $_POST);
            $this->redirect('/services?success=updated');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/services/edit?id=' . $id . '&error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao atualizar serviço: " . $e->getMessage());
            $this->redirect('/services/edit?id=' . $id . '&error=database_error');
        }
    }

    /**
     * Alterna status ativo/inativo
     * 
     * POST /services/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/services');
            return;
        }

        try {
            ServiceService::toggleStatus($id);
            $this->redirect('/services?success=toggled');
        } catch (\Exception $e) {
            error_log("Erro ao alternar status do serviço: " . $e->getMessage());
            $this->redirect('/services?error=database_error');
        }
    }
}

