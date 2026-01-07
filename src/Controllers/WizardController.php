<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProjectService;
use PixelHub\Services\ServiceService;
use PixelHub\Services\AsaasBillingService;
use PixelHub\Services\AsaasClient;

/**
 * Controller para Assistente de Cadastramento (Wizard)
 * 
 * Fluxo guiado completo: Cliente → Serviço → Projeto → Financeiro
 */
class WizardController extends Controller
{
    /**
     * Página inicial do wizard
     * 
     * GET /wizard/new-project
     */
    public function newProject(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Busca lista de clientes
        $stmt = $db->query("SELECT id, name, person_type FROM tenants WHERE status = 'active' ORDER BY name ASC");
        $tenants = $stmt->fetchAll();

        // Busca serviços ativos
        $services = ServiceService::getAllServices(null, true);

        $this->view('wizard.new_project', [
            'tenants' => $tenants,
            'services' => $services,
        ]);
    }

    /**
     * Salva o projeto completo via wizard
     * 
     * POST /wizard/create-project
     */
    public function createProject(): void
    {
        Auth::requireInternal();

        try {
            $user = Auth::user();
            $db = DB::getConnection();

            // Validações
            $tenantId = !empty($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;
            $serviceId = !empty($_POST['service_id']) ? (int) $_POST['service_id'] : null;
            $projectName = trim($_POST['project_name'] ?? '');
            $contractValue = !empty($_POST['contract_value']) ? (float) str_replace(['.', ','], ['', '.'], $_POST['contract_value']) : null;
            $generateInvoice = isset($_POST['generate_invoice']) && $_POST['generate_invoice'] === '1';

            if (empty($projectName)) {
                $this->json(['error' => 'Nome do projeto é obrigatório'], 400);
                return;
            }

            if (empty($serviceId)) {
                $this->json(['error' => 'Selecione um serviço do catálogo'], 400);
                return;
            }

            // Busca dados do serviço
            $service = ServiceService::findService($serviceId);
            if (!$service) {
                $this->json(['error' => 'Serviço não encontrado'], 400);
                return;
            }

            // Cria projeto
            $projectData = [
                'name' => $projectName,
                'tenant_id' => $tenantId,
                'service_id' => $serviceId,
                'type' => $tenantId ? 'cliente' : 'interno',
                'status' => 'ativo',
                'priority' => 'media',
                'description' => 'Projeto criado via assistente de cadastramento',
                'created_by' => $user['id'] ?? null,
            ];

            // Se tem serviço, usa dados do serviço como referência
            if ($service['estimated_duration']) {
                // Calcula prazo baseado no serviço
                $dueDate = date('Y-m-d', strtotime('+' . $service['estimated_duration'] . ' days'));
                $projectData['due_date'] = $dueDate;
            }

            $projectId = ProjectService::createProject($projectData);
            $project = ProjectService::findProject($projectId);

            $invoiceId = null;
            
            // Gera fatura se solicitado e se houver cliente
            if ($generateInvoice && $tenantId && $contractValue) {
                try {
                    // Busca tenant para pegar asaas_customer_id
                    $stmt = $db->prepare("SELECT id, name, asaas_customer_id FROM tenants WHERE id = ?");
                    $stmt->execute([$tenantId]);
                    $tenant = $stmt->fetch();

                    if ($tenant) {
                        // Garante que tem customer no Asaas
                        $asaasCustomerId = AsaasBillingService::ensureCustomerForTenant($tenant);
                        
                        // Cria fatura no Asaas
                        $paymentData = [
                            'customer' => $asaasCustomerId,
                            'billingType' => 'UNDEFINED',
                            'value' => $contractValue,
                            'dueDate' => date('Y-m-d', strtotime('+7 days')), // Vencimento em 7 dias
                            'description' => $service['name'] . ' - ' . $projectName,
                            'externalReference' => 'PROJECT_' . $projectId,
                        ];

                        try {
                            $payment = AsaasClient::createPayment($paymentData);
                            
                            if ($payment && isset($payment['id'])) {
                                // Salva fatura no banco
                                $stmt = $db->prepare("
                                    INSERT INTO billing_invoices 
                                    (tenant_id, project_id, asaas_payment_id, asaas_customer_id, amount, status, due_date, description, external_reference, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())
                                ");
                                $stmt->execute([
                                    $tenantId,
                                    $projectId,
                                    $payment['id'],
                                    $asaasCustomerId,
                                    $contractValue,
                                    $paymentData['dueDate'],
                                    $paymentData['description'],
                                    $paymentData['externalReference'],
                                ]);
                                $invoiceId = (int) $db->lastInsertId();
                                
                                // Atualiza status financeiro do tenant
                                AsaasBillingService::refreshTenantBillingStatus($tenantId);
                            }
                        } catch (\Exception $e) {
                            // Log erro mas não bloqueia criação do projeto
                            error_log("Erro ao gerar fatura no wizard: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    // Log erro mas não bloqueia criação do projeto
                    error_log("Erro ao gerar fatura no wizard: " . $e->getMessage());
                }
            }

            // Retorna sucesso
            $this->json([
                'success' => true,
                'project_id' => $projectId,
                'project_name' => $project['name'],
                'invoice_id' => $invoiceId,
                'redirect_url' => '/projects/board?project_id=' . $projectId,
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro no wizard: " . $e->getMessage());
            $this->json(['error' => 'Erro ao criar projeto: ' . $e->getMessage()], 500);
        }
    }
}

