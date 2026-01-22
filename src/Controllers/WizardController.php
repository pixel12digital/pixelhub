<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProjectService;
use PixelHub\Services\ServiceService;
use PixelHub\Services\AsaasBillingService;
use PixelHub\Services\AsaasClient;
use PixelHub\Services\ProjectContractService;

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
            $serviceIds = !empty($_POST['service_ids']) && is_array($_POST['service_ids']) 
                ? array_map('intval', $_POST['service_ids']) 
                : [];
            $projectName = trim($_POST['project_name'] ?? '');
            $contractValue = !empty($_POST['contract_value']) ? (float) str_replace(['.', ','], ['', '.'], $_POST['contract_value']) : null;
            $generateInvoice = isset($_POST['generate_invoice']) && $_POST['generate_invoice'] === '1';
            $markAsPaid = isset($_POST['mark_as_paid']) && $_POST['mark_as_paid'] === '1';
            $generateContract = isset($_POST['generate_contract']) && $_POST['generate_contract'] === '1';
            $sendContractWhatsApp = isset($_POST['send_contract_whatsapp']) && $_POST['send_contract_whatsapp'] === '1';

            if (empty($projectName)) {
                $this->json(['error' => 'Nome do projeto é obrigatório'], 400);
                return;
            }

            if (empty($serviceIds)) {
                $this->json(['error' => 'Selecione pelo menos um serviço do catálogo'], 400);
                return;
            }

            // Busca valores customizados dos serviços (se houver)
            $serviceCustomPrices = [];
            if (!empty($_POST['service_custom_prices']) && is_array($_POST['service_custom_prices'])) {
                foreach ($_POST['service_custom_prices'] as $serviceId => $price) {
                    $serviceCustomPrices[(int) $serviceId] = (float) $price;
                }
            }

            // Busca prazos customizados dos serviços (se houver)
            $serviceCustomDurations = [];
            if (!empty($_POST['service_custom_durations']) && is_array($_POST['service_custom_durations'])) {
                foreach ($_POST['service_custom_durations'] as $serviceId => $duration) {
                    $serviceCustomDurations[(int) $serviceId] = (int) $duration;
                }
            }

            // Busca dados dos serviços
            $services = [];
            foreach ($serviceIds as $serviceId) {
                $service = ServiceService::findService($serviceId);
                if (!$service) {
                    $this->json(['error' => 'Serviço ID ' . $serviceId . ' não encontrado'], 400);
                    return;
                }
                // Substitui preço pelo valor customizado se houver
                if (isset($serviceCustomPrices[$serviceId])) {
                    $service['price'] = $serviceCustomPrices[$serviceId];
                }
                // Substitui prazo pelo valor customizado se houver
                if (isset($serviceCustomDurations[$serviceId])) {
                    $service['estimated_duration'] = $serviceCustomDurations[$serviceId];
                }
                $services[] = $service;
            }

            // Cria um projeto para cada serviço selecionado
            $createdProjects = [];
            $invoiceIds = [];
            $tenant = null;
            $asaasCustomerId = null;
            
            // Prepara dados do tenant para faturas (se necessário)
            if ($generateInvoice && $tenantId && $contractValue) {
                $stmt = $db->prepare("SELECT id, name, asaas_customer_id FROM tenants WHERE id = ?");
                $stmt->execute([$tenantId]);
                $tenant = $stmt->fetch();
                
                if ($tenant) {
                    $asaasCustomerId = AsaasBillingService::ensureCustomerForTenant($tenant);
                }
            }

            // Calcula valor por serviço
            // Se houver valores customizados, usa eles. Senão, distribui o valor do contrato igualmente
            $hasCustomPrices = !empty($serviceCustomPrices);
            
            // Se não houver valor de contrato mas houver valores customizados, usa a soma deles como valor total
            if (!$contractValue && $hasCustomPrices) {
                $contractValue = 0;
                foreach ($services as $service) {
                    $contractValue += $service['price'] ?? 0;
                }
            }

            foreach ($services as $index => $service) {
                // Cria nome do projeto baseado no serviço se houver múltiplos
                $finalProjectName = count($services) > 1 
                    ? $projectName . ' - ' . $service['name']
                    : $projectName;

                // Cria projeto
                $projectData = [
                    'name' => $finalProjectName,
                    'tenant_id' => $tenantId,
                    'service_id' => $service['id'],
                    'type' => $tenantId ? 'cliente' : 'interno',
                    'status' => 'ativo',
                    'priority' => 'media',
                    'description' => 'Projeto criado via assistente de cadastramento',
                    'created_by' => $user['id'] ?? null,
                ];

                // Se tem serviço, usa dados do serviço como referência (pode ter sido customizado)
                if (!empty($service['estimated_duration']) && $service['estimated_duration'] > 0) {
                    // Calcula prazo baseado no serviço (usa prazo customizado se houver)
                    $duration = (int) $service['estimated_duration'];
                    $dueDate = date('Y-m-d', strtotime('+' . $duration . ' days'));
                    $projectData['due_date'] = $dueDate;
                }

                $projectId = ProjectService::createProject($projectData);
                $project = ProjectService::findProject($projectId);
                $createdProjects[] = $project;

                // Determina valor do serviço
                // Se houver valores customizados, usa o valor individual do serviço
                // Senão, distribui o valor do contrato igualmente
                if ($hasCustomPrices && isset($service['price']) && $service['price'] > 0) {
                    $serviceValue = $service['price'];
                } else {
                    // Distribui valor do contrato igualmente entre os serviços
                    $serviceValue = $contractValue && count($services) > 0 
                        ? round($contractValue / count($services), 2) 
                        : ($service['price'] ?? 0);
                }

                // Cria contrato para o projeto (se solicitado e se houver cliente e valor)
                $contractId = null;
                if ($generateContract && $tenantId && $serviceValue) {
                    try {
                        $contractId = ProjectContractService::createContract([
                            'project_id' => $projectId,
                            'tenant_id' => $tenantId,
                            'service_id' => $service['id'],
                            'contract_value' => $serviceValue,
                            'service_price' => $service['price'] ?? null,
                            'status' => $sendContractWhatsApp ? 'sent' : 'draft',
                            'notes' => 'Contrato criado via assistente de cadastramento',
                        ]);
                        
                        // Se solicitado, envia via WhatsApp
                        if ($sendContractWhatsApp && $contractId) {
                            try {
                                $contract = ProjectContractService::findContract($contractId);
                                if ($contract) {
                                    // Busca dados do cliente
                                    $stmt = $db->prepare("SELECT id, name, phone, email FROM tenants WHERE id = ?");
                                    $stmt->execute([$tenantId]);
                                    $tenantForContract = $stmt->fetch();
                                    
                                    if ($tenantForContract && !empty($tenantForContract['phone'])) {
                                        // Marca como enviado
                                        ProjectContractService::markAsSent($contractId, $user['id'] ?? null);
                                        
                                        // Gera link público de aceite
                                        $publicLink = ProjectContractService::generatePublicLink($contract['contract_token']);
                                        
                                        // Prepara mensagem com link de aceite
                                        $projectNameForContract = $project['name'] ?? $finalProjectName;
                                        $contractValueFormatted = 'R$ ' . number_format((float) $serviceValue, 2, ',', '.');
                                        
                                        $message = "Olá! 👋\n\n";
                                        $message .= "Temos um contrato para você assinar digitalmente:\n\n";
                                        $message .= "📋 *Projeto:* {$projectNameForContract}\n";
                                        $message .= "💰 *Valor:* {$contractValueFormatted}\n\n";
                                        $message .= "Por favor, acesse o link abaixo para visualizar e aceitar o contrato:\n\n";
                                        $message .= $publicLink . "\n\n";
                                        $message .= "Aguardamos sua confirmação! 😊";
                                        
                                        // Normaliza telefone
                                        $phoneNormalized = preg_replace('/[^0-9]/', '', $tenantForContract['phone']);
                                        
                                        // Registra no histórico de WhatsApp
                                        try {
                                            $logStmt = $db->prepare("
                                                INSERT INTO whatsapp_generic_logs 
                                                (tenant_id, template_id, phone, message, sent_at, created_at)
                                                VALUES (?, NULL, ?, ?, NOW(), NOW())
                                            ");
                                            $logStmt->execute([
                                                $tenantId,
                                                $phoneNormalized,
                                                $message
                                            ]);
                                        } catch (\Exception $logError) {
                                            // Log erro mas não bloqueia o processo
                                            error_log("Erro ao registrar log de WhatsApp (contrato {$contractId}): " . $logError->getMessage());
                                        }
                                        
                                        // Gera link do WhatsApp (será aberto em nova aba)
                                        $whatsappLink = \PixelHub\Services\WhatsAppTemplateService::buildWhatsAppLink($phoneNormalized, $message);
                                        
                                        // Armazena link para retornar ao frontend
                                        // Nota: O envio real via WhatsApp precisa ser feito manualmente pelo usuário
                                        // Aqui apenas preparamos o link e registramos no histórico
                                    }
                                }
                            } catch (\Exception $e) {
                                error_log("Erro ao enviar contrato via WhatsApp no wizard (projeto {$projectId}): " . $e->getMessage());
                            }
                        }
                    } catch (\Exception $e) {
                        // Log erro mas não bloqueia criação do projeto
                        error_log("Erro ao criar contrato no wizard (projeto {$projectId}): " . $e->getMessage());
                    }
                }

                // Gera faturas se solicitado e se houver cliente
                if ($generateInvoice && $tenant && $asaasCustomerId) {
                    $billingData = $_POST['billing'] ?? [];
                    
                    // Descrição base
                    $baseDescription = count($services) > 1 
                        ? $service['name'] . ' - ' . $projectName
                        : $service['name'] . ' - ' . $projectName;

                    // 1. Cobrança Única (À Vista)
                    if (!empty($billingData['one_time']['enabled']) && $billingData['one_time']['enabled'] === '1') {
                        try {
                            $oneTimeValue = !empty($billingData['one_time']['value']) 
                                ? (float) str_replace(['.', ','], ['', '.'], $billingData['one_time']['value'])
                                : $serviceValue;
                            
                            if ($oneTimeValue > 0) {
                                $dueDate = !empty($billingData['one_time']['due_date']) 
                                    ? $billingData['one_time']['due_date']
                                    : date('Y-m-d', strtotime('+7 days'));
                                
                                $paymentMethod = $billingData['one_time']['payment_method'] ?? 'UNDEFINED';
                                $description = !empty($billingData['one_time']['description']) 
                                    ? $billingData['one_time']['description']
                                    : $baseDescription . ' - Pagamento à vista';

                                $paymentData = [
                                    'customer' => $asaasCustomerId,
                                    'billingType' => $paymentMethod,
                                    'value' => $oneTimeValue,
                                    'dueDate' => $dueDate,
                                    'description' => $description,
                                    'externalReference' => 'PROJECT_' . $projectId . '_ONETIME',
                                ];

                                $payment = AsaasClient::createPayment($paymentData);
                                
                                if ($payment && isset($payment['id'])) {
                                    $stmt = $db->prepare("
                                        INSERT INTO billing_invoices 
                                        (tenant_id, project_id, asaas_payment_id, asaas_customer_id, amount, status, due_date, description, external_reference, billing_type, created_at, updated_at)
                                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, 'one_time', NOW(), NOW())
                                    ");
                                    $stmt->execute([
                                        $tenantId,
                                        $projectId,
                                        $payment['id'],
                                        $asaasCustomerId,
                                        $oneTimeValue,
                                        $dueDate,
                                        $description,
                                        $paymentData['externalReference'],
                                    ]);
                                    $invoiceIds[] = (int) $db->lastInsertId();
                                }
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao gerar cobrança única no wizard (projeto {$projectId}): " . $e->getMessage());
                        }
                    }

                    // 2. Parcelamento
                    if (!empty($billingData['installment']['enabled']) && $billingData['installment']['enabled'] === '1') {
                        try {
                            $installmentValue = !empty($billingData['installment']['value']) 
                                ? (float) str_replace(['.', ','], ['', '.'], $billingData['installment']['value'])
                                : $serviceValue;
                            
                            if ($installmentValue > 0) {
                                $installmentCount = !empty($billingData['installment']['count']) 
                                    ? (int) $billingData['installment']['count']
                                    : 1;
                                
                                $firstDueDate = !empty($billingData['installment']['first_due_date']) 
                                    ? $billingData['installment']['first_due_date']
                                    : date('Y-m-d', strtotime('+7 days'));
                                
                                $paymentMethod = $billingData['installment']['payment_method'] ?? 'UNDEFINED';
                                $description = !empty($billingData['installment']['description']) 
                                    ? $billingData['installment']['description']
                                    : $baseDescription . ' - Parcelado em ' . $installmentCount . 'x';

                                $installmentValuePerMonth = $installmentValue / $installmentCount;

                                // Cria parcelamento no Asaas
                                $paymentData = [
                                    'customer' => $asaasCustomerId,
                                    'billingType' => $paymentMethod,
                                    'value' => $installmentValuePerMonth,
                                    'installmentCount' => $installmentCount,
                                    'installmentValue' => $installmentValuePerMonth,
                                    'dueDate' => $firstDueDate,
                                    'description' => $description,
                                    'externalReference' => 'PROJECT_' . $projectId . '_INSTALLMENT',
                                ];

                                // Adiciona juros se houver
                                if (!empty($billingData['installment']['interest']) && (float) $billingData['installment']['interest'] > 0) {
                                    $paymentData['interest'] = (float) $billingData['installment']['interest'];
                                }

                                // Adiciona multa se houver
                                if (!empty($billingData['installment']['fine_value']) && (float) $billingData['installment']['fine_value'] > 0) {
                                    $fineType = $billingData['installment']['fine_type'] ?? 'PERCENTAGE';
                                    if ($fineType === 'PERCENTAGE') {
                                        $paymentData['fine'] = [
                                            'value' => (float) $billingData['installment']['fine_value'],
                                            'type' => 'PERCENTAGE'
                                        ];
                                    } else {
                                        $paymentData['fine'] = [
                                            'value' => (float) $billingData['installment']['fine_value'],
                                            'type' => 'FIXED'
                                        ];
                                    }
                                }

                                // Adiciona desconto se houver prazo
                                if (!empty($billingData['installment']['discount_days']) && (int) $billingData['installment']['discount_days'] > 0) {
                                    $paymentData['discount'] = [
                                        'days' => (int) $billingData['installment']['discount_days']
                                    ];
                                }

                                $payment = AsaasClient::createPayment($paymentData);
                                
                                if ($payment && isset($payment['id'])) {
                                    $stmt = $db->prepare("
                                        INSERT INTO billing_invoices 
                                        (tenant_id, project_id, asaas_payment_id, asaas_customer_id, amount, status, due_date, description, external_reference, billing_type, created_at, updated_at)
                                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, 'installment', NOW(), NOW())
                                    ");
                                    $stmt->execute([
                                        $tenantId,
                                        $projectId,
                                        $payment['id'],
                                        $asaasCustomerId,
                                        $installmentValue,
                                        $firstDueDate,
                                        $description,
                                        $paymentData['externalReference'],
                                    ]);
                                    $invoiceIds[] = (int) $db->lastInsertId();
                                }
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao gerar parcelamento no wizard (projeto {$projectId}): " . $e->getMessage());
                        }
                    }

                    // 3. Assinatura (Recorrente)
                    if (!empty($billingData['subscription']['enabled']) && $billingData['subscription']['enabled'] === '1') {
                        try {
                            $subscriptionValue = !empty($billingData['subscription']['value']) 
                                ? (float) str_replace(['.', ','], ['', '.'], $billingData['subscription']['value'])
                                : $serviceValue;
                            
                            if ($subscriptionValue > 0) {
                                $cycle = $billingData['subscription']['cycle'] ?? 'MONTHLY';
                                $nextDueDate = !empty($billingData['subscription']['next_due_date']) 
                                    ? $billingData['subscription']['next_due_date']
                                    : date('Y-m-d', strtotime('+1 month'));
                                
                                $paymentMethod = $billingData['subscription']['payment_method'] ?? 'CREDIT_CARD';
                                $description = !empty($billingData['subscription']['description']) 
                                    ? $billingData['subscription']['description']
                                    : $baseDescription . ' - Assinatura ' . strtolower($cycle);

                                $subscriptionData = [
                                    'customer' => $asaasCustomerId,
                                    'billingType' => $paymentMethod,
                                    'value' => $subscriptionValue,
                                    'cycle' => $cycle,
                                    'nextDueDate' => $nextDueDate,
                                    'description' => $description,
                                    'externalReference' => 'PROJECT_' . $projectId . '_SUBSCRIPTION',
                                ];

                                $subscription = AsaasClient::createSubscription($subscriptionData);
                                
                                if ($subscription && isset($subscription['id'])) {
                                    // Salva assinatura no banco (pode criar uma tabela específica ou usar billing_invoices)
                                    $stmt = $db->prepare("
                                        INSERT INTO billing_invoices 
                                        (tenant_id, project_id, asaas_payment_id, asaas_customer_id, amount, status, due_date, description, external_reference, billing_type, created_at, updated_at)
                                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, 'subscription', NOW(), NOW())
                                    ");
                                    $stmt->execute([
                                        $tenantId,
                                        $projectId,
                                        $subscription['id'],
                                        $asaasCustomerId,
                                        $subscriptionValue,
                                        $nextDueDate,
                                        $description,
                                        $subscriptionData['externalReference'],
                                    ]);
                                    $invoiceIds[] = (int) $db->lastInsertId();
                                }
                            }
                        } catch (\Exception $e) {
                            error_log("Erro ao gerar assinatura no wizard (projeto {$projectId}): " . $e->getMessage());
                        }
                    }
                }
            }

            // Se não gerou fatura mas marcou como pago, cria registro de fatura paga para cada projeto
            if (!$generateInvoice && $markAsPaid && $tenantId) {
                foreach ($createdProjects as $project) {
                    try {
                        // Determina valor do projeto
                        $projectValue = $contractValue && count($createdProjects) > 0 
                            ? round($contractValue / count($createdProjects), 2)
                            : $contractValue;
                        
                        if ($projectValue && $projectValue > 0) {
                            // Cria registro de fatura paga (sem asaas_payment_id)
                            $stmt = $db->prepare("
                                INSERT INTO billing_invoices 
                                (tenant_id, project_id, asaas_payment_id, asaas_customer_id, amount, status, due_date, description, external_reference, billing_type, paid_at, created_at, updated_at)
                                VALUES (?, ?, 'MANUAL_PAID', ?, ?, 'paid', NOW(), ?, ?, 'manual', NOW(), NOW(), NOW())
                            ");
                            $description = 'Pagamento recebido - ' . $project['name'];
                            $stmt->execute([
                                $tenantId,
                                $project['id'],
                                null, // asaas_customer_id
                                $projectValue,
                                $description,
                                'PROJECT_' . $project['id'] . '_MANUAL_PAID',
                            ]);
                            $invoiceIds[] = (int) $db->lastInsertId();
                        }
                    } catch (\Exception $e) {
                        error_log("Erro ao criar registro de pagamento manual (projeto {$project['id']}): " . $e->getMessage());
                    }
                }
            }

            // Atualiza status financeiro do tenant uma vez no final
            if (($generateInvoice && $tenantId && count($invoiceIds) > 0) || ($markAsPaid && $tenantId)) {
                try {
                    AsaasBillingService::refreshTenantBillingStatus($tenantId);
                } catch (\Exception $e) {
                    error_log("Erro ao atualizar status financeiro: " . $e->getMessage());
                }
            }

            // Retorna sucesso - redireciona para o primeiro projeto criado
            $firstProject = $createdProjects[0];
            $this->json([
                'success' => true,
                'project_id' => $firstProject['id'],
                'project_name' => $firstProject['name'],
                'projects_count' => count($createdProjects),
                'projects_created' => array_map(function($p) {
                    return ['id' => $p['id'], 'name' => $p['name']];
                }, $createdProjects),
                'invoice_ids' => $invoiceIds,
                'redirect_url' => '/projects/board?project_id=' . $firstProject['id'],
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro no wizard: " . $e->getMessage());
            $this->json(['error' => 'Erro ao criar projeto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Gera sugestões de nome de projeto usando IA
     * 
     * POST /wizard/suggest-project-name
     */
    public function suggestProjectName(): void
    {
        Auth::requireInternal();

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $clientName = trim($input['client_name'] ?? '');
            $services = $input['services'] ?? [];
            
            if (empty($services)) {
                $this->json(['error' => 'Nenhum serviço fornecido'], 400);
                return;
            }
            
            // Prepara informações para a IA
            $serviceNames = array_column($services, 'name');
            $serviceCategories = array_unique(array_filter(array_column($services, 'category')));
            $serviceDescriptions = array_filter(array_column($services, 'description'));
            
            // Chama serviço de IA para gerar sugestões
            $suggestions = \PixelHub\Services\AIService::suggestProjectNames([
                'client_name' => $clientName,
                'services' => $serviceNames,
                'categories' => $serviceCategories,
                'descriptions' => $serviceDescriptions,
            ]);
            
            $this->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar sugestões de nome: " . $e->getMessage());
            $this->json(['error' => 'Erro ao gerar sugestões: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Gera preview completo do contrato para exibição no modal
     * 
     * POST /wizard/preview-contract
     */
    public function previewContract(): void
    {
        Auth::requireInternal();

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $tenantId = !empty($input['tenant_id']) ? (int) $input['tenant_id'] : null;
            $serviceIds = !empty($input['service_ids']) && is_array($input['service_ids']) 
                ? array_map('intval', $input['service_ids']) 
                : [];
            $projectName = trim($input['project_name'] ?? '');
            $contractValue = !empty($input['contract_value']) ? (float) str_replace(['.', ','], ['', '.'], $input['contract_value']) : null;
            
            if (!$tenantId || empty($serviceIds) || empty($projectName) || !$contractValue) {
                $this->json(['error' => 'Dados incompletos para gerar preview'], 400);
                return;
            }

            $db = DB::getConnection();
            
            // Busca dados do cliente
            $stmt = $db->prepare("SELECT id, name, person_type, cpf_cnpj FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();
            
            if (!$tenant) {
                $this->json(['error' => 'Cliente não encontrado'], 404);
                return;
            }
            
            // Busca dados do primeiro serviço (para preview)
            $serviceId = $serviceIds[0];
            $service = ServiceService::findService($serviceId);
            
            if (!$service) {
                $this->json(['error' => 'Serviço não encontrado'], 404);
                return;
            }
            
            // Calcula prazo
            $prazoTexto = 'a definir';
            $prazoDias = null;
            if (!empty($service['estimated_duration']) && $service['estimated_duration'] > 0) {
                $prazoDias = (int) $service['estimated_duration'];
                $prazoTexto = $prazoDias . ' dia' . ($prazoDias > 1 ? 's' : '');
            }
            
            // Busca dados da empresa
            $companySettings = \PixelHub\Services\CompanySettingsService::getSettings();
            $companyName = $companySettings['company_name'] ?? 'Pixel12 Digital';
            $companyCnpj = $companySettings['cnpj'] ?? null;
            $companyAddress = \PixelHub\Services\CompanySettingsService::getFormattedAddress();
            $logoUrl = \PixelHub\Services\CompanySettingsService::getLogoUrl();
            
            // Monta preview do contrato completo (simulado, sem criar projeto real)
            $contractContent = \PixelHub\Services\ProjectContractService::buildContractContentPreview([
                'tenant' => $tenant,
                'project_name' => $projectName,
                'service' => $service,
                'contract_value' => $contractValue,
                'prazo' => $prazoTexto,
                'prazo_dias' => $prazoDias,
                'company_name' => $companyName,
                'company_cnpj' => $companyCnpj,
                'company_address' => $companyAddress,
                'logo_url' => $logoUrl,
            ]);
            
            $this->json([
                'success' => true,
                'contract_content' => $contractContent,
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar preview do contrato: " . $e->getMessage());
            $this->json(['error' => 'Erro ao gerar preview: ' . $e->getMessage()], 500);
        }
    }
}

