<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\MoneyHelper;

/**
 * Controller para gerenciar planos de hospedagem
 */
class HostingPlanController extends Controller
{
    /**
     * Lista todos os planos de hospedagem
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $stmt = $db->query("
            SELECT * FROM hosting_plans
            ORDER BY is_active DESC, name ASC
        ");
        $plans = $stmt->fetchAll();

        $this->view('hosting_plans.index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Exibe formulário de criação de plano
     */
    public function create(): void
    {
        Auth::requireInternal();

        $this->view('hosting_plans.form', [
            'plan' => null,
        ]);
    }

    /**
     * Salva novo plano
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $name = trim($_POST['name'] ?? '');
        $serviceType = trim($_POST['service_type'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $rawAmount = $_POST['amount'] ?? '0';
        $billingCycle = $_POST['billing_cycle'] ?? 'mensal';
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
        $annualEnabled = isset($_POST['annual_enabled']) && $_POST['annual_enabled'] == '1' ? 1 : 0;
        $rawAnnualMonthlyAmount = $_POST['annual_monthly_amount'] ?? '0';
        $rawAnnualTotalAmount = $_POST['annual_total_amount'] ?? '0';

        // Validações
        if (empty($name)) {
            $this->redirect('/hosting-plans/create?error=missing_name');
            return;
        }

        if (empty($serviceType) || !in_array($serviceType, ['hospedagem', 'ecommerce', 'manutencao', 'saas'], true)) {
            $this->redirect('/hosting-plans/create?error=missing_service_type');
            return;
        }

        if (empty($provider) || !in_array($provider, ['hostmedia', 'vercel'], true)) {
            $this->redirect('/hosting-plans/create?error=missing_provider');
            return;
        }

        $amount = MoneyHelper::normalizeAmount($rawAmount);

        if ($amount <= 0) {
            $this->redirect('/hosting-plans/create?error=invalid_amount');
            return;
        }

        // Normaliza valores anuais
        $annualMonthlyAmount = null;
        $annualTotalAmount = null;

        if ($annualEnabled) {
            $annualMonthlyAmount = MoneyHelper::normalizeAmount($rawAnnualMonthlyAmount);
            $annualTotalAmount = MoneyHelper::normalizeAmount($rawAnnualTotalAmount);

            if ($annualMonthlyAmount <= 0 || $annualTotalAmount <= 0) {
                $this->redirect('/hosting-plans/create?error=invalid_annual_amount');
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO hosting_plans 
                (name, service_type, provider, amount, billing_cycle, annual_enabled, annual_monthly_amount, annual_total_amount, 
                 description, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $name,
                $serviceType,
                $provider,
                $amount,
                $billingCycle,
                $annualEnabled,
                $annualMonthlyAmount,
                $annualTotalAmount,
                $description ?: null,
                $isActive,
            ]);

            $this->redirect('/hosting-plans?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar plano: " . $e->getMessage());
            $this->redirect('/hosting-plans/create?error=database_error');
        }
    }

    /**
     * Exibe formulário de edição de plano
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $planId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($planId <= 0) {
            $this->redirect('/hosting-plans');
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosting_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        if (!$plan) {
            $this->redirect('/hosting-plans?error=not_found');
            return;
        }

        $this->view('hosting_plans.form', [
            'plan' => $plan,
        ]);
    }

    /**
     * Atualiza plano existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $planId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $serviceType = trim($_POST['service_type'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $rawAmount = $_POST['amount'] ?? '0';
        $billingCycle = $_POST['billing_cycle'] ?? 'mensal';
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
        $annualEnabled = isset($_POST['annual_enabled']) && $_POST['annual_enabled'] == '1' ? 1 : 0;
        $rawAnnualMonthlyAmount = $_POST['annual_monthly_amount'] ?? '0';
        $rawAnnualTotalAmount = $_POST['annual_total_amount'] ?? '0';

        if ($planId <= 0) {
            $this->redirect('/hosting-plans');
            return;
        }

        if (empty($name)) {
            $this->redirect('/hosting-plans/edit?id=' . $planId . '&error=missing_name');
            return;
        }

        if (empty($serviceType) || !in_array($serviceType, ['hospedagem', 'ecommerce', 'manutencao', 'saas'], true)) {
            $this->redirect('/hosting-plans/edit?id=' . $planId . '&error=missing_service_type');
            return;
        }

        if (empty($provider) || !in_array($provider, ['hostmedia', 'vercel'], true)) {
            $this->redirect('/hosting-plans/edit?id=' . $planId . '&error=missing_provider');
            return;
        }

        $amount = MoneyHelper::normalizeAmount($rawAmount);

        if ($amount <= 0) {
            $this->redirect('/hosting-plans/edit?id=' . $planId . '&error=invalid_amount');
            return;
        }

        // Normaliza valores anuais
        $annualMonthlyAmount = null;
        $annualTotalAmount = null;

        if ($annualEnabled) {
            $annualMonthlyAmount = MoneyHelper::normalizeAmount($rawAnnualMonthlyAmount);
            $annualTotalAmount = MoneyHelper::normalizeAmount($rawAnnualTotalAmount);

            if ($annualMonthlyAmount <= 0 || $annualTotalAmount <= 0) {
                $this->redirect('/hosting-plans/edit?id=' . $planId . '&error=invalid_annual_amount');
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                UPDATE hosting_plans 
                SET name = ?, service_type = ?, provider = ?, amount = ?, billing_cycle = ?, annual_enabled = ?, 
                    annual_monthly_amount = ?, annual_total_amount = ?, 
                    description = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $serviceType,
                $provider,
                $amount,
                $billingCycle,
                $annualEnabled,
                $annualMonthlyAmount,
                $annualTotalAmount,
                $description ?: null,
                $isActive,
                $planId,
            ]);

            $this->redirect('/hosting-plans?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar plano: " . $e->getMessage());
            $this->redirect('/hosting-plans/edit?id=' . $planId . '&error=database_error');
        }
    }

    /**
     * Alterna status ativo/inativo do plano
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $planId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($planId <= 0) {
            $this->redirect('/hosting-plans');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("UPDATE hosting_plans SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$planId]);

            $this->redirect('/hosting-plans?success=toggled');
        } catch (\Exception $e) {
            error_log("Erro ao alternar status do plano: " . $e->getMessage());
            $this->redirect('/hosting-plans?error=toggle_failed');
        }
    }

    /**
     * Exclui plano de hospedagem
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $planId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($planId <= 0) {
            $this->redirect('/hosting-plans?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Verificar se há contas de hospedagem usando este plano
        $stmt = $db->prepare("SELECT COUNT(*) FROM hosting_accounts WHERE hosting_plan_id = ?");
        $stmt->execute([$planId]);
        $hostingCount = (int) $stmt->fetchColumn();

        if ($hostingCount > 0) {
            $this->redirect('/hosting-plans?error=cannot_delete_has_hosting');
            return;
        }

        // Verificar se há contratos usando este plano
        $stmt = $db->prepare("SELECT COUNT(*) FROM billing_contracts WHERE hosting_plan_id = ?");
        $stmt->execute([$planId]);
        $contractCount = (int) $stmt->fetchColumn();

        if ($contractCount > 0) {
            $this->redirect('/hosting-plans?error=cannot_delete_has_contracts');
            return;
        }

        // Se estiver tudo ok, excluir
        try {
            $stmt = $db->prepare("DELETE FROM hosting_plans WHERE id = ?");
            $stmt->execute([$planId]);

            $this->redirect('/hosting-plans?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir plano: " . $e->getMessage());
            $this->redirect('/hosting-plans?error=delete_failed');
        }
    }
}

