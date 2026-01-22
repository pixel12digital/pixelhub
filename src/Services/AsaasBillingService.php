<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Service de alto nível para integração de cobrança com Asaas
 * 
 * Faz a ponte entre o mundo Pixel Hub (tenants/hosting) e o Asaas.
 */
class AsaasBillingService
{
    /**
     * Garante que o tenant possui asaas_customer_id
     * 
     * Fluxo:
     * - Se já tiver asaas_customer_id: retorna o ID
     * - Senão: tenta buscar customer no Asaas por cpfCnpj
     * - Se encontrar: atualiza dados básicos se precisar e salva asaas_customer_id no tenant
     * - Se não encontrar: cria um novo customer e salva o ID no tenant
     * 
     * @param array $tenant Dados do tenant
     * @return string ID do customer no Asaas
     */
    public static function ensureCustomerForTenant(array $tenant): string
    {
        $db = DB::getConnection();

        // Se já tem customer_id, retorna
        if (!empty($tenant['asaas_customer_id'])) {
            return $tenant['asaas_customer_id'];
        }

        // Prepara dados do customer para o Asaas
        $cpfCnpj = $tenant['cpf_cnpj'] ?? $tenant['document'] ?? '';
        $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);

        if (empty($cpfCnpj)) {
            throw new \RuntimeException('Tenant não possui CPF/CNPJ cadastrado');
        }

        $personType = ($tenant['person_type'] ?? 'pf') === 'pj' ? 'COMPANY' : 'FISICA';
        $name = $tenant['name'] ?? '';
        $email = $tenant['email'] ?? null;
        $phone = $tenant['phone'] ?? null;
        $phoneFixed = $tenant['phone_fixed'] ?? null;

        // Remove formatação do telefone
        if ($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
        }
        if ($phoneFixed) {
            $phoneFixed = preg_replace('/[^0-9]/', '', $phoneFixed);
        }

        // Tenta buscar customer existente no Asaas
        $asaasCustomer = AsaasClient::findCustomerByCpfCnpj($cpfCnpj);

        if ($asaasCustomer) {
            // Customer encontrado, atualiza dados se necessário
            $customerId = $asaasCustomer['id'];
            
            $updateData = [];
            if (!empty($name) && ($asaasCustomer['name'] ?? '') !== $name) {
                $updateData['name'] = $name;
            }
            if (!empty($email) && ($asaasCustomer['email'] ?? '') !== $email) {
                $updateData['email'] = $email;
            }
            if (!empty($phone) && ($asaasCustomer['phone'] ?? '') !== $phone) {
                $updateData['phone'] = $phone;
            }
            
            // Monta endereço completo se disponível
            $addressData = self::buildAddressData($tenant);
            if (!empty($addressData)) {
                $updateData = array_merge($updateData, $addressData);
            }

            if (!empty($updateData)) {
                AsaasClient::updateCustomer($customerId, $updateData);
            }
        } else {
            // Cria novo customer
            $customerData = [
                'name' => $name,
                'cpfCnpj' => $cpfCnpj,
                'personType' => $personType,
            ];

            if ($email) {
                $customerData['email'] = $email;
            }
            if ($phone) {
                $customerData['phone'] = $phone;
            }
            if ($phoneFixed) {
                $customerData['phone'] = $phoneFixed; // Asaas pode ter apenas um campo phone
            }

            // Para PJ, adiciona campos específicos
            if ($personType === 'COMPANY') {
                if (!empty($tenant['razao_social'])) {
                    $customerData['companyName'] = $tenant['razao_social'];
                }
                if (!empty($tenant['nome_fantasia'])) {
                    $customerData['name'] = $tenant['nome_fantasia'];
                }
            }
            
            // Adiciona endereço se disponível
            $addressData = self::buildAddressData($tenant);
            if (!empty($addressData)) {
                $customerData = array_merge($customerData, $addressData);
            }

            $asaasCustomer = AsaasClient::createCustomer($customerData);
            $customerId = $asaasCustomer['id'];
        }

        // Salva customer_id no tenant
        $stmt = $db->prepare("UPDATE tenants SET asaas_customer_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$customerId, $tenant['id']]);

        return $customerId;
    }

    /**
     * Consolida dados de múltiplos customers do Asaas em um único array
     * 
     * Prioriza dados não vazios, mesclando informações de todos os customers.
     * Exemplo: se um customer tem endereço e outro não, usa o endereço do que tem.
     * 
     * @param array $customers Array de customers do Asaas
     * @return array Dados consolidados
     */
    public static function consolidateAsaasCustomersData(array $customers): array
    {
        if (empty($customers)) {
            return [];
        }

        $consolidated = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'postalCode' => '',
            'address' => '',
            'addressNumber' => '',
            'complement' => '',
            'province' => '',
            'city' => '',
            'state' => '',
        ];

        // Itera sobre todos os customers e consolida dados
        foreach ($customers as $customer) {
            // Nome: usa o mais completo (mais longo) ou o primeiro não vazio
            if (!empty($customer['name']) && strlen($customer['name']) > strlen($consolidated['name'])) {
                $consolidated['name'] = $customer['name'];
            }

            // Email: usa o primeiro não vazio
            if (empty($consolidated['email']) && !empty($customer['email'])) {
                $consolidated['email'] = $customer['email'];
            }

            // Telefone: usa o primeiro não vazio
            if (empty($consolidated['phone']) && !empty($customer['phone'])) {
                $consolidated['phone'] = $customer['phone'];
            }

            // Endereço: consolida campo por campo, priorizando não vazios
            if (empty($consolidated['postalCode']) && !empty($customer['postalCode'])) {
                $consolidated['postalCode'] = $customer['postalCode'];
            }

            if (empty($consolidated['address']) && !empty($customer['address'])) {
                $consolidated['address'] = $customer['address'];
            }

            if (empty($consolidated['addressNumber']) && !empty($customer['addressNumber'])) {
                $consolidated['addressNumber'] = $customer['addressNumber'];
            }

            if (empty($consolidated['complement']) && !empty($customer['complement'])) {
                $consolidated['complement'] = $customer['complement'];
            }

            if (empty($consolidated['province']) && !empty($customer['province'])) {
                $consolidated['province'] = $customer['province'];
            }

            // Cidade: prioriza valores mais completos (que contêm letras, não apenas números)
            // Se já temos uma cidade, só substitui se a nova for mais completa (mais longa e com letras)
            if (!empty($customer['city'])) {
                $currentCity = $consolidated['city'] ?? '';
                $newCityOriginal = trim($customer['city']);
                
                // Remove código IBGE do início para análise/comparação
                $currentCityProcessed = !empty($currentCity) ? preg_replace('/^\d+\s*-\s*/', '', $currentCity) : '';
                $currentCityProcessed = trim($currentCityProcessed);
                
                $newCityProcessed = preg_replace('/^\d+\s*-\s*/', '', $newCityOriginal);
                $newCityProcessed = trim($newCityProcessed);
                
                // Análise: verifica se são apenas numéricos ou têm letras
                $isCurrentNumericOnly = !empty($currentCityProcessed) && preg_match('/^\d+$/', $currentCityProcessed);
                $isNewNumericOnly = preg_match('/^\d+$/', $newCityProcessed);
                $currentHasLetters = !empty($currentCityProcessed) && preg_match('/[a-zA-Z]/', $currentCityProcessed);
                $newHasLetters = preg_match('/[a-zA-Z]/', $newCityProcessed);
                
                // DECISÃO: Prioriza cidades com letras sobre apenas numéricas
                // SEMPRE salva o valor original (não processado) quando encontrar um melhor
                $shouldUpdate = false;
                $bestCity = $currentCity;
                
                if (empty($currentCity)) {
                    // Primeiro customer: usa o original (pode ser processado depois)
                    $bestCity = $newCityOriginal;
                    $shouldUpdate = true;
                } elseif ($isCurrentNumericOnly && $newHasLetters) {
                    // Atual é apenas numérica, nova tem letras: SUBSTITUI pela original
                    $bestCity = $newCityOriginal;
                    $shouldUpdate = true;
                } elseif ($currentHasLetters && $isNewNumericOnly) {
                    // Atual tem letras, nova é apenas numérica: MANTÉM a atual
                    $shouldUpdate = false;
                } elseif ($newHasLetters && !$currentHasLetters) {
                    // Nova tem letras, atual não tem: SUBSTITUI
                    $bestCity = $newCityOriginal;
                    $shouldUpdate = true;
                } elseif ($newHasLetters && $currentHasLetters && strlen($newCityProcessed) > strlen($currentCityProcessed)) {
                    // Ambos têm letras, nova é mais completa: SUBSTITUI
                    $bestCity = $newCityOriginal;
                    $shouldUpdate = true;
                } elseif ($isCurrentNumericOnly && $isNewNumericOnly && strlen($newCityProcessed) > strlen($currentCityProcessed)) {
                    // Ambos são apenas numéricos, nova é mais longa: SUBSTITUI (melhor que nada)
                    $bestCity = $newCityOriginal;
                    $shouldUpdate = true;
                }
                
                if ($shouldUpdate) {
                    $consolidated['city'] = $bestCity;
                }
            }

            if (empty($consolidated['state']) && !empty($customer['state'])) {
                $consolidated['state'] = $customer['state'];
            }
        }

        // Remove campos vazios do resultado final, mas sempre preserva cidade se foi processada (mesmo que vazia após remover código IBGE)
        // Se cidade consolidada for apenas numérica, ainda mantém para que possa ser substituída por valores melhores
        $filtered = [];
        foreach ($consolidated as $key => $value) {
            // Preserva cidade sempre (pode ter sido processada mas ainda ser válida)
            if ($key === 'city' && isset($consolidated['city'])) {
                $filtered[$key] = $consolidated['city']; // Sempre preserva cidade consolidada
            } elseif ($value !== '') {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    /**
     * Converte dados consolidados do Asaas para formato do tenant local
     * 
     * @param array $consolidatedData Dados consolidados do Asaas
     * @return array Dados no formato do tenant
     */
    public static function convertConsolidatedDataToTenantFormat(array $consolidatedData): array
    {
        $tenantData = [];

        if (!empty($consolidatedData['email'])) {
            $tenantData['email'] = $consolidatedData['email'];
        }

        // Telefone: o Asaas tem apenas um campo 'phone', então vamos usar para phone (celular)
        // phone_fixed será preenchido apenas se vier explicitamente ou se o tenant já tiver
        if (!empty($consolidatedData['phone'])) {
            $tenantData['phone'] = $consolidatedData['phone'];
        }

        if (!empty($consolidatedData['postalCode'])) {
            $cep = preg_replace('/[^0-9]/', '', $consolidatedData['postalCode']);
            if (strlen($cep) === 8) {
                $tenantData['address_cep'] = substr($cep, 0, 5) . '-' . substr($cep, 5);
            } else {
                $tenantData['address_cep'] = $consolidatedData['postalCode'];
            }
        }

        if (!empty($consolidatedData['address'])) {
            $tenantData['address_street'] = $consolidatedData['address'];
        }

        if (!empty($consolidatedData['addressNumber'])) {
            $tenantData['address_number'] = $consolidatedData['addressNumber'];
        }

        if (!empty($consolidatedData['complement'])) {
            $tenantData['address_complement'] = $consolidatedData['complement'];
        }

        if (!empty($consolidatedData['province'])) {
            $tenantData['address_neighborhood'] = $consolidatedData['province'];
        }

        // Cidade: processa e salva se presente no consolidado
        // Se vier como número (código IBGE), tenta buscar nome via CEP
        if (isset($consolidatedData['city']) && $consolidatedData['city'] !== '') {
            $city = $consolidatedData['city'];
            
            // Se a cidade é um número (código IBGE), tenta buscar nome via CEP
            if (is_numeric($city) && !empty($consolidatedData['postalCode'])) {
                $cep = preg_replace('/[^0-9]/', '', $consolidatedData['postalCode']);
                if (strlen($cep) === 8) {
                    // Busca cidade via ViaCEP
                    try {
                        $viaCepUrl = "https://viacep.com.br/ws/{$cep}/json/";
                        $ch = curl_init($viaCepUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                        $viaCepResponse = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode === 200 && $viaCepResponse) {
                            $viaCepData = json_decode($viaCepResponse, true);
                            if (!empty($viaCepData['localidade']) && !isset($viaCepData['erro'])) {
                                $city = $viaCepData['localidade'];
                                // Se tiver UF, adiciona para ficar completo
                                if (!empty($viaCepData['uf'])) {
                                    $city .= ' - ' . $viaCepData['uf'];
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Se falhar, continua com código IBGE
                        error_log("Erro ao buscar cidade via CEP: " . $e->getMessage());
                    }
                }
            }
            
            // Processa cidade (remove código IBGE do início se presente)
            $city = trim((string)$city);
            $city = preg_replace('/^\d+\s*-\s*/', '', $city);
            $city = trim($city);
            
            // Só salva se tiver conteúdo e não for apenas numérica
            if ($city !== '' && !preg_match('/^\d+$/', $city)) {
                $tenantData['address_city'] = $city;
            }
        }

        if (!empty($consolidatedData['state'])) {
            $tenantData['address_state'] = strtoupper($consolidatedData['state']);
        }

        return $tenantData;
    }

    /**
     * Monta dados de endereço no formato do Asaas
     * 
     * @param array $tenant Dados do tenant
     * @return array Dados de endereço ou array vazio se não houver endereço completo
     */
    public static function buildAddressData(array $tenant): array
    {
        $cep = preg_replace('/[^0-9]/', '', $tenant['address_cep'] ?? '');
        $street = trim($tenant['address_street'] ?? '');
        $number = trim($tenant['address_number'] ?? '');
        $complement = trim($tenant['address_complement'] ?? '');
        $neighborhood = trim($tenant['address_neighborhood'] ?? '');
        $city = trim($tenant['address_city'] ?? '');
        $state = strtoupper(trim($tenant['address_state'] ?? ''));

        // Retorna array vazio se não tiver pelo menos CEP, rua e cidade
        if (empty($cep) || empty($street) || empty($city)) {
            return [];
        }

        $addressData = [
            'postalCode' => $cep,
            'address' => $street,
            'addressNumber' => $number ?: 'S/N',
            'complement' => $complement ?: null,
            'province' => $neighborhood ?: null,
            'city' => $city,
        ];

        if (!empty($state) && strlen($state) === 2) {
            $addressData['state'] = $state;
        }

        // Remove valores vazios/null
        return array_filter($addressData, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Sincroniza dados do tenant para o Asaas (sistema → Asaas)
     * 
     * Atualiza os dados do customer no Asaas com as informações do tenant.
     * Usado quando o cliente é editado no sistema e precisa ser atualizado no Asaas.
     * 
     * @param array $tenant Dados completos do tenant
     * @return void
     * @throws \RuntimeException Se tenant não possui asaas_customer_id
     */
    public static function syncCustomerDataToAsaas(array $tenant): void
    {
        if (empty($tenant['asaas_customer_id'])) {
            throw new \RuntimeException('Tenant não possui asaas_customer_id');
        }

        $customerId = $tenant['asaas_customer_id'];
        
        // Prepara dados para atualização
        $updateData = [];
        
        $name = $tenant['name'] ?? '';
        $email = $tenant['email'] ?? null;
        $phone = $tenant['phone'] ?? null;
        $phoneFixed = $tenant['phone_fixed'] ?? null;
        
        // Remove formatação do telefone
        if ($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
        }
        if ($phoneFixed) {
            $phoneFixed = preg_replace('/[^0-9]/', '', $phoneFixed);
        }
        
        if (!empty($name)) {
            $updateData['name'] = $name;
        }
        
        if (!empty($email)) {
            $updateData['email'] = $email;
        }
        
        // Prioriza telefone fixo se existir, senão usa celular
        if (!empty($phoneFixed)) {
            $updateData['phone'] = $phoneFixed;
        } elseif (!empty($phone)) {
            $updateData['phone'] = $phone;
        }
        
        // Para PJ, atualiza companyName se existir
        if (($tenant['person_type'] ?? 'pf') === 'pj') {
            if (!empty($tenant['razao_social'])) {
                $updateData['companyName'] = $tenant['razao_social'];
            }
            if (!empty($tenant['nome_fantasia'])) {
                $updateData['name'] = $tenant['nome_fantasia'];
            }
        }
        
        // Adiciona endereço se disponível
        $addressData = self::buildAddressData($tenant);
        if (!empty($addressData)) {
            $updateData = array_merge($updateData, $addressData);
        }
        
        // Atualiza no Asaas se houver dados para atualizar
        if (!empty($updateData)) {
            try {
                AsaasClient::updateCustomer($customerId, $updateData);
            } catch (\Exception $e) {
                error_log("Erro ao atualizar customer no Asaas (ID: {$customerId}): " . $e->getMessage());
                throw new \RuntimeException('Erro ao atualizar cliente no Asaas: ' . $e->getMessage());
            }
        }
    }

    /**
     * Cria um contrato de cobrança vinculado a um hosting_account + hosting_plan
     * 
     * Por enquanto, apenas cria o registro em billing_contracts (sem chamar Asaas).
     * TODO: Quando implementar criação de subscription no Asaas, chamar aqui:
     * - AsaasClient::createSubscription() com dados do plano
     * - Salvar asaas_subscription_id retornado
     * 
     * @param array $tenant Dados do tenant
     * @param array $hostingAccount Dados da conta de hospedagem
     * @param array $hostingPlan Dados do plano
     * @param string $billingMode 'mensal' | 'anual'
     * @return int ID do contrato criado
     */
    public static function createBillingContractForHosting(
        array $tenant,
        array $hostingAccount,
        array $hostingPlan,
        string $billingMode
    ): int {
        $db = DB::getConnection();

        // Prepara dados do contrato
        $planName = $hostingPlan['name'] ?? 'Plano de Hospedagem';
        $planSnapshotName = $planName . ' - ' . ucfirst($billingMode);
        
        $amount = (float) ($hostingPlan['amount'] ?? 0);
        $annualTotalAmount = null;

        if ($billingMode === 'anual') {
            $annualTotalAmount = (float) ($hostingPlan['annual_total_amount'] ?? 0);
            if ($annualTotalAmount <= 0) {
                throw new \RuntimeException('Plano não possui valor anual configurado');
            }
        }

        // Gera external_reference
        $externalRef = "PIXEL_CONTRACT:" . time() . "_" . $hostingAccount['id'];

        // Insere contrato
        $stmt = $db->prepare("
            INSERT INTO billing_contracts 
            (tenant_id, hosting_account_id, hosting_plan_id, plan_snapshot_name, 
             billing_mode, amount, annual_total_amount, asaas_external_reference, 
             status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo', NOW(), NOW())
        ");

        $stmt->execute([
            $tenant['id'],
            $hostingAccount['id'],
            $hostingPlan['id'] ?? null,
            $planSnapshotName,
            $billingMode,
            $amount,
            $annualTotalAmount,
            $externalRef,
        ]);

        $contractId = (int) $db->lastInsertId();

        // TODO: Quando implementar criação de subscription no Asaas:
        // 1. Garantir customer: self::ensureCustomerForTenant($tenant)
        // 2. Preparar payload usando AsaasPlanMapper::buildMonthlySubscriptionPayload() ou buildYearlyPaymentPayload()
        // 3. Chamar AsaasClient::createSubscription() ou createPayment()
        // 4. Atualizar billing_contracts.asaas_subscription_id com o ID retornado

        return $contractId;
    }

    /**
     * Atualiza o billing_status do tenant com base nas faturas
     * 
     * Regra simples:
     * - Se não houver faturas -> 'sem_cobranca'
     * - Se houver faturas em status 'overdue' -> 'atrasado_parcial' ou 'atrasado_total'
     * - Se só houver 'paid' ou 'canceled' e nada pendente/em atraso -> 'em_dia'
     * 
     * @param int $tenantId ID do tenant
     * @return void
     */
    public static function refreshTenantBillingStatus(int $tenantId): void
    {
        $db = DB::getConnection();

        // Busca faturas do tenant - ignora cobranças deletadas
        $stmt = $db->prepare("
            SELECT status, COUNT(*) as count
            FROM billing_invoices
            WHERE tenant_id = ? AND (is_deleted IS NULL OR is_deleted = 0)
            GROUP BY status
        ");
        $stmt->execute([$tenantId]);
        $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiza contadores por status
        $counts = [];
        foreach ($statusCounts as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        // Determina status
        $billingStatus = 'sem_cobranca';
        
        if (!empty($counts)) {
            $hasOverdue = isset($counts['overdue']) && $counts['overdue'] > 0;
            $hasPending = isset($counts['pending']) && $counts['pending'] > 0;
            $hasPaid = isset($counts['paid']) && $counts['paid'] > 0;
            
            if ($hasOverdue) {
                // Se tem atrasadas, verifica se todas estão atrasadas
                $total = array_sum($counts);
                $overdueCount = $counts['overdue'];
                
                if ($overdueCount === $total) {
                    $billingStatus = 'atrasado_total';
                } else {
                    $billingStatus = 'atrasado_parcial';
                }
            } elseif ($hasPending) {
                // Tem pendentes mas não atrasadas = em dia
                $billingStatus = 'em_dia';
            } elseif ($hasPaid) {
                // Só tem pagas = em dia
                $billingStatus = 'em_dia';
            }
        }

        // Atualiza tenant
        $stmt = $db->prepare("
            UPDATE tenants 
            SET billing_status = ?, billing_last_check_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$billingStatus, $tenantId]);
    }

    /**
     * Sincroniza faturas de um tenant com o Asaas
     * 
     * Busca todos os payments do customer no Asaas e atualiza/cria registros em billing_invoices.
     * 
     * @param int $tenantId ID do tenant
     * @return array Estatísticas da sincronização ['created' => int, 'updated' => int]
     * @throws \RuntimeException Se tenant não encontrado, sem CPF/CNPJ, ou erro na API
     */
    public static function syncInvoicesForTenant(int $tenantId): array
    {
        $db = DB::getConnection();

        // Busca tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            throw new \RuntimeException("Tenant não encontrado (ID: {$tenantId})");
        }

        // Valida dados mínimos
        $cpfCnpj = $tenant['cpf_cnpj'] ?? $tenant['document'] ?? '';
        if (empty($cpfCnpj)) {
            throw new \RuntimeException("Tenant não possui CPF/CNPJ cadastrado. É necessário para sincronização com Asaas.");
        }

        // Garante que tem customer no Asaas
        $customerId = self::ensureCustomerForTenant($tenant);

        // Busca payments no Asaas
        try {
            // Monta query params
            $queryParams = http_build_query([
                'customer' => $customerId,
                'limit' => 100,
                'order' => 'desc',
                'sort' => 'dueDate',
            ]);

            $response = AsaasClient::request('GET', '/payments?' . $queryParams, null);
        } catch (\RuntimeException $e) {
            // Se erro de API, relança com mensagem mais clara
            throw new \RuntimeException("Erro ao buscar faturas no Asaas: " . $e->getMessage());
        }

        // Processa payments retornados
        $payments = $response['data'] ?? [];
        $created = 0;
        $updated = 0;

        foreach ($payments as $payment) {
            $paymentId = $payment['id'] ?? null;
            if (empty($paymentId)) {
                continue;
            }

            // Busca fatura existente
            $stmt = $db->prepare("SELECT * FROM billing_invoices WHERE asaas_payment_id = ?");
            $stmt->execute([$paymentId]);
            $invoice = $stmt->fetch();

            // Verifica se a cobrança foi deletada no Asaas
            // O campo 'deleted' pode ser boolean true ou string 'true'
            $asaasDeleted = isset($payment['deleted']) && (
                $payment['deleted'] === true || 
                $payment['deleted'] === 'true' || 
                $payment['deleted'] === 1
            );
            
            // Mapeia status do Asaas para interno
            $asaasStatus = strtoupper($payment['status'] ?? 'PENDING');
            $statusMapping = [
                'PENDING' => 'pending',
                'CONFIRMED' => 'paid',
                'RECEIVED' => 'paid',
                'RECEIVED_IN_CASH' => 'paid',
                'OVERDUE' => 'overdue',
                'CANCELED' => 'canceled',
                'REFUNDED' => 'refunded',
            ];
            $internalStatus = $statusMapping[$asaasStatus] ?? 'pending';
            
            // Se a cobrança estiver deletada ou com status de cancelamento/reembolso,
            // marca como cancelada e is_deleted = 1
            $isDeleted = 0;
            if ($asaasDeleted || in_array($asaasStatus, ['CANCELED', 'REFUNDED'])) {
                $internalStatus = 'canceled';
                $isDeleted = 1;
                
                // Log informativo para auditoria
                error_log("AsaasBillingService: Cobrança {$paymentId} marcada como deletada/cancelada (deleted={$asaasDeleted}, status={$asaasStatus})");
            }

            // Prepara dados
            $dueDate = null;
            if (!empty($payment['dueDate'])) {
                try {
                    $date = new \DateTime($payment['dueDate']);
                    $dueDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    error_log("Erro ao converter dueDate: " . $e->getMessage());
                }
            }

            $paidAt = null;
            if (!empty($payment['confirmedDate'])) {
                try {
                    $date = new \DateTime($payment['confirmedDate']);
                    $paidAt = $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // Tenta paymentDate como fallback
                    if (!empty($payment['paymentDate'])) {
                        try {
                            $date = new \DateTime($payment['paymentDate']);
                            $paidAt = $date->format('Y-m-d H:i:s');
                        } catch (\Exception $e2) {
                            error_log("Erro ao converter paymentDate: " . $e2->getMessage());
                        }
                    }
                }
            }

            $amount = (float) ($payment['value'] ?? 0);
            $customerIdFromPayment = $payment['customer'] ?? null;
            $invoiceUrl = $payment['invoiceUrl'] ?? null;
            $billingType = $payment['billingType'] ?? null;
            $description = $payment['description'] ?? null;
            $externalRef = $payment['externalReference'] ?? null;

            if ($invoice) {
                // Atualiza fatura existente
                $stmt = $db->prepare("
                    UPDATE billing_invoices 
                    SET asaas_customer_id = ?, due_date = ?, amount = ?, status = ?, 
                        is_deleted = ?, paid_at = ?, invoice_url = ?, billing_type = ?, 
                        description = ?, external_reference = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $customerIdFromPayment,
                    $dueDate,
                    $amount,
                    $internalStatus,
                    $isDeleted,
                    $paidAt,
                    $invoiceUrl,
                    $billingType,
                    $description,
                    $externalRef,
                    $invoice['id'],
                ]);
                $updated++;
            } else {
                // Cria nova fatura
                $stmt = $db->prepare("
                    INSERT INTO billing_invoices 
                    (tenant_id, asaas_payment_id, asaas_customer_id, due_date, amount, 
                     status, is_deleted, paid_at, invoice_url, billing_type, description, 
                     external_reference, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $paymentId,
                    $customerIdFromPayment,
                    $dueDate,
                    $amount,
                    $internalStatus,
                    $isDeleted,
                    $paidAt,
                    $invoiceUrl,
                    $billingType,
                    $description,
                    $externalRef,
                ]);
                $created++;
            }
        }

        // Atualiza status financeiro do tenant
        self::refreshTenantBillingStatus($tenantId);

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($payments),
        ];
    }

    /**
     * Sincroniza customer e faturas de um tenant com o Asaas
     * 
     * Este método faz uma sincronização completa:
     * 1. Busca/atualiza dados do customer no Asaas (se já tiver asaas_customer_id)
     * 2. Garante que o customer existe no Asaas
     * 3. Busca todos os customers do Asaas com o mesmo CPF/CNPJ
     * 4. Sincroniza todas as faturas de todos os customers encontrados
     * 5. Limpa faturas que foram deletadas no Asaas
     * 
     * @param int $tenantId ID do tenant
     * @return array Estatísticas da sincronização ['customer_updated' => bool, 'invoices' => ['created' => int, 'updated' => int, 'total' => int], 'cleanup' => ['checked' => int, 'deleted' => int, 'errors' => int]]
     * @throws \RuntimeException Se tenant não encontrado, sem asaas_customer_id, ou erro na API
     */
    public static function syncCustomerAndInvoicesForTenant(int $tenantId): array
    {
        $db = DB::getConnection();

        // Busca tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            throw new \RuntimeException("Tenant não encontrado (ID: {$tenantId})");
        }

        // Verifica se tem asaas_customer_id
        if (empty($tenant['asaas_customer_id'])) {
            throw new \RuntimeException("Tenant não possui asaas_customer_id. É necessário sincronizar o cliente primeiro.");
        }

        $customerUpdated = false;

        // Opcional: busca dados atualizados do customer no Asaas e atualiza no banco local
        try {
            $asaasCustomer = AsaasClient::getCustomer($tenant['asaas_customer_id']);
            
            // Atualiza dados do tenant com informações do Asaas (se necessário)
            $updateFields = [];
            $updateValues = [];

            // Atualiza email se estiver vazio ou diferente
            $asaasEmail = $asaasCustomer['email'] ?? null;
            if (!empty($asaasEmail) && ($tenant['email'] ?? '') !== $asaasEmail) {
                $updateFields[] = "email = ?";
                $updateValues[] = $asaasEmail;
            }

            // Atualiza nome se estiver vazio ou diferente
            $asaasName = $asaasCustomer['name'] ?? null;
            if (!empty($asaasName) && ($tenant['name'] ?? '') !== $asaasName) {
                $updateFields[] = "name = ?";
                $updateValues[] = $asaasName;
            }

            // Para PJ, atualiza companyName se disponível
            if (($tenant['person_type'] ?? 'pf') === 'pj') {
                $asaasCompanyName = $asaasCustomer['companyName'] ?? null;
                if (!empty($asaasCompanyName) && ($tenant['razao_social'] ?? '') !== $asaasCompanyName) {
                    $updateFields[] = "razao_social = ?";
                    $updateValues[] = $asaasCompanyName;
                }
            }

            if (!empty($updateFields)) {
                $updateValues[] = $tenantId;
                $sql = "UPDATE tenants SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($updateValues);
                $customerUpdated = true;
            }
        } catch (\RuntimeException $e) {
            // Se não conseguir buscar customer (ex: deletado no Asaas), apenas loga e continua
            error_log("Aviso: Não foi possível buscar dados atualizados do customer no Asaas: " . $e->getMessage());
        }

        // Garante que o customer existe (pode atualizar dados se necessário)
        self::ensureCustomerForTenant($tenant);

        // Busca todos os customers do Asaas com o mesmo CPF/CNPJ
        $cpfCnpj = $tenant['cpf_cnpj'] ?? $tenant['document'] ?? '';
        $cpfCnpjNormalizado = preg_replace('/[^0-9]/', '', $cpfCnpj);
        
        $allCustomers = [];
        if (!empty($cpfCnpjNormalizado)) {
            try {
                $allCustomers = AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado);
            } catch (\Exception $e) {
                error_log("Aviso: Erro ao buscar customers por CPF para tenant {$tenantId}: " . $e->getMessage());
                // Se falhar, usa apenas o customer principal
                $allCustomers = [];
            }
        }

        // Se não encontrou nenhum customer por CPF, usa apenas o customer principal
        if (empty($allCustomers)) {
            $allCustomers = [['id' => $tenant['asaas_customer_id']]];
        }

        // Coleta todos os payment IDs ativos de todos os customers
        $activePaymentIds = [];
        $invoiceStats = ['created' => 0, 'updated' => 0, 'total' => 0];

        // Sincroniza faturas de cada customer encontrado
        foreach ($allCustomers as $customer) {
            $customerId = $customer['id'] ?? null;
            if (empty($customerId)) {
                continue;
            }

            try {
                // Busca payments deste customer no Asaas
                $queryParams = http_build_query([
                    'customer' => $customerId,
                    'limit' => 100,
                    'order' => 'desc',
                    'sort' => 'dueDate',
                ]);

                $response = AsaasClient::request('GET', '/payments?' . $queryParams, null);
                $payments = $response['data'] ?? [];

                // Processa cada payment
                foreach ($payments as $payment) {
                    $paymentId = $payment['id'] ?? null;
                    if (empty($paymentId)) {
                        continue;
                    }

                    // Adiciona à lista de payments ativos
                    $activePaymentIds[] = $paymentId;

                    // Busca fatura existente
                    $stmt = $db->prepare("SELECT * FROM billing_invoices WHERE asaas_payment_id = ?");
                    $stmt->execute([$paymentId]);
                    $invoice = $stmt->fetch();

                    // Verifica se a cobrança foi deletada no Asaas
                    $asaasDeleted = isset($payment['deleted']) && (
                        $payment['deleted'] === true || 
                        $payment['deleted'] === 'true' || 
                        $payment['deleted'] === 1
                    );
                    
                    // Mapeia status do Asaas para interno
                    $asaasStatus = strtoupper($payment['status'] ?? 'PENDING');
                    $statusMapping = [
                        'PENDING' => 'pending',
                        'CONFIRMED' => 'paid',
                        'RECEIVED' => 'paid',
                        'RECEIVED_IN_CASH' => 'paid',
                        'OVERDUE' => 'overdue',
                        'CANCELED' => 'canceled',
                        'REFUNDED' => 'refunded',
                    ];
                    $internalStatus = $statusMapping[$asaasStatus] ?? 'pending';
                    
                    // Se a cobrança estiver deletada ou com status de cancelamento/reembolso,
                    // marca como cancelada e is_deleted = 1
                    $isDeleted = 0;
                    if ($asaasDeleted || in_array($asaasStatus, ['CANCELED', 'REFUNDED'])) {
                        $internalStatus = 'canceled';
                        $isDeleted = 1;
                    }

                    // Prepara dados
                    $dueDate = null;
                    if (!empty($payment['dueDate'])) {
                        try {
                            $date = new \DateTime($payment['dueDate']);
                            $dueDate = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            error_log("Erro ao converter dueDate: " . $e->getMessage());
                        }
                    }

                    $paidAt = null;
                    if (!empty($payment['confirmedDate'])) {
                        try {
                            $date = new \DateTime($payment['confirmedDate']);
                            $paidAt = $date->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            if (!empty($payment['paymentDate'])) {
                                try {
                                    $date = new \DateTime($payment['paymentDate']);
                                    $paidAt = $date->format('Y-m-d H:i:s');
                                } catch (\Exception $e2) {
                                    error_log("Erro ao converter paymentDate: " . $e2->getMessage());
                                }
                            }
                        }
                    }

                    $amount = (float) ($payment['value'] ?? 0);
                    $customerIdFromPayment = $payment['customer'] ?? null;
                    $invoiceUrl = $payment['invoiceUrl'] ?? null;
                    $billingType = $payment['billingType'] ?? null;
                    $description = $payment['description'] ?? null;
                    $externalRef = $payment['externalReference'] ?? null;

                    if ($invoice) {
                        // Atualiza fatura existente
                        $stmt = $db->prepare("
                            UPDATE billing_invoices 
                            SET tenant_id = ?, asaas_customer_id = ?, due_date = ?, amount = ?, status = ?, 
                                is_deleted = ?, paid_at = ?, invoice_url = ?, billing_type = ?, 
                                description = ?, external_reference = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $tenantId, // Garante que está vinculado ao tenant correto
                            $customerIdFromPayment,
                            $dueDate,
                            $amount,
                            $internalStatus,
                            $isDeleted,
                            $paidAt,
                            $invoiceUrl,
                            $billingType,
                            $description,
                            $externalRef,
                            $invoice['id'],
                        ]);
                        $invoiceStats['updated']++;
                    } else {
                        // Cria nova fatura
                        $stmt = $db->prepare("
                            INSERT INTO billing_invoices 
                            (tenant_id, asaas_payment_id, asaas_customer_id, due_date, amount, 
                             status, is_deleted, paid_at, invoice_url, billing_type, description, 
                             external_reference, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $tenantId,
                            $paymentId,
                            $customerIdFromPayment,
                            $dueDate,
                            $amount,
                            $internalStatus,
                            $isDeleted,
                            $paidAt,
                            $invoiceUrl,
                            $billingType,
                            $description,
                            $externalRef,
                        ]);
                        $invoiceStats['created']++;
                    }
                }

                $invoiceStats['total'] += count($payments);

            } catch (\RuntimeException $e) {
                error_log("Erro ao sincronizar faturas do customer {$customerId}: " . $e->getMessage());
                // Continua com os outros customers
            }
        }

        // Atualiza status financeiro do tenant
        self::refreshTenantBillingStatus($tenantId);

        // Limpa faturas que foram deletadas no Asaas
        $cleanupStats = self::cleanupDeletedInvoicesForTenant($tenantId, $activePaymentIds);

        return [
            'success' => true,
            'customer_updated' => $customerUpdated,
            'invoices' => $invoiceStats,
            'cleanup' => $cleanupStats,
        ];
    }

    /**
     * Sincroniza faturas usando asaas_customer_id diretamente
     * 
     * @param string $asaasCustomerId ID do customer no Asaas
     * @param int $tenantId ID do tenant no Pixel Hub
     * @return array Estatísticas da sincronização
     */
    public static function syncInvoicesForAsaasCustomer(string $asaasCustomerId, int $tenantId): array
    {
        $db = DB::getConnection();

        // Busca payments no Asaas
        try {
            $queryParams = http_build_query([
                'customer' => $asaasCustomerId,
                'limit' => 100,
                'order' => 'desc',
                'sort' => 'dueDate',
            ]);

            $response = AsaasClient::request('GET', '/payments?' . $queryParams, null);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Erro ao buscar faturas no Asaas: " . $e->getMessage());
        }

        // Processa payments retornados
        $payments = $response['data'] ?? [];
        $created = 0;
        $updated = 0;

        foreach ($payments as $payment) {
            $paymentId = $payment['id'] ?? null;
            if (empty($paymentId)) {
                continue;
            }

            // Busca fatura existente
            $stmt = $db->prepare("SELECT * FROM billing_invoices WHERE asaas_payment_id = ?");
            $stmt->execute([$paymentId]);
            $invoice = $stmt->fetch();

            // Verifica se a cobrança foi deletada no Asaas
            $asaasDeleted = isset($payment['deleted']) && (
                $payment['deleted'] === true || 
                $payment['deleted'] === 'true' || 
                $payment['deleted'] === 1
            );
            
            // Mapeia status do Asaas para interno
            $asaasStatus = strtoupper($payment['status'] ?? 'PENDING');
            $statusMapping = [
                'PENDING' => 'pending',
                'CONFIRMED' => 'paid',
                'RECEIVED' => 'paid',
                'RECEIVED_IN_CASH' => 'paid',
                'OVERDUE' => 'overdue',
                'CANCELED' => 'canceled',
                'REFUNDED' => 'refunded',
            ];
            $internalStatus = $statusMapping[$asaasStatus] ?? 'pending';
            
            $isDeleted = 0;
            if ($asaasDeleted || in_array($asaasStatus, ['CANCELED', 'REFUNDED'])) {
                $internalStatus = 'canceled';
                $isDeleted = 1;
            }

            // Prepara dados
            $dueDate = null;
            if (!empty($payment['dueDate'])) {
                try {
                    $date = new \DateTime($payment['dueDate']);
                    $dueDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    error_log("Erro ao converter dueDate: " . $e->getMessage());
                }
            }

            $paidAt = null;
            if (!empty($payment['confirmedDate'])) {
                try {
                    $date = new \DateTime($payment['confirmedDate']);
                    $paidAt = $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    if (!empty($payment['paymentDate'])) {
                        try {
                            $date = new \DateTime($payment['paymentDate']);
                            $paidAt = $date->format('Y-m-d H:i:s');
                        } catch (\Exception $e2) {
                            error_log("Erro ao converter paymentDate: " . $e2->getMessage());
                        }
                    }
                }
            }

            $amount = (float) ($payment['value'] ?? 0);
            $customerIdFromPayment = $payment['customer'] ?? null;
            $invoiceUrl = $payment['invoiceUrl'] ?? null;
            $billingType = $payment['billingType'] ?? null;
            $description = $payment['description'] ?? null;
            $externalRef = $payment['externalReference'] ?? null;

            if ($invoice) {
                // Atualiza fatura existente
                $stmt = $db->prepare("
                    UPDATE billing_invoices 
                    SET asaas_customer_id = ?, due_date = ?, amount = ?, status = ?, 
                        is_deleted = ?, paid_at = ?, invoice_url = ?, billing_type = ?, 
                        description = ?, external_reference = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $customerIdFromPayment,
                    $dueDate,
                    $amount,
                    $internalStatus,
                    $isDeleted,
                    $paidAt,
                    $invoiceUrl,
                    $billingType,
                    $description,
                    $externalRef,
                    $invoice['id'],
                ]);
                $updated++;
            } else {
                // Cria nova fatura
                $stmt = $db->prepare("
                    INSERT INTO billing_invoices 
                    (tenant_id, asaas_payment_id, asaas_customer_id, due_date, amount, 
                     status, is_deleted, paid_at, invoice_url, billing_type, description, 
                     external_reference, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $paymentId,
                    $customerIdFromPayment,
                    $dueDate,
                    $amount,
                    $internalStatus,
                    $isDeleted,
                    $paidAt,
                    $invoiceUrl,
                    $billingType,
                    $description,
                    $externalRef,
                ]);
                $created++;
            }
        }

        // Atualiza status financeiro do tenant
        self::refreshTenantBillingStatus($tenantId);

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($payments),
        ];
    }

    /**
     * Sincroniza todos os customers do Asaas e suas faturas
     * 
     * @return array Estatísticas da sincronização
     */
    public static function syncAllCustomersAndInvoices(): array
    {
        $db = DB::getConnection();
        
        $stats = [
            'created_customers' => 0,
            'updated_customers' => 0,
            'skipped_customers' => 0,
            'total_invoices_created' => 0,
            'total_invoices_updated' => 0,
            'errors' => [],
        ];

        $offset = 0;
        $limit = 100;
        $hasMore = true;

        // Busca todos os customers do Asaas (paginado)
        while ($hasMore) {
            try {
                $result = AsaasClient::listAllCustomers($limit, $offset);
                $customers = $result['data'] ?? [];
                $hasMore = $result['hasMore'] ?? false;

                foreach ($customers as $asaasCustomer) {
                    try {
                        // Ignora customers deletados
                        $asaasDeleted = isset($asaasCustomer['deleted']) && (
                            $asaasCustomer['deleted'] === true || 
                            $asaasCustomer['deleted'] === 'true' || 
                            $asaasCustomer['deleted'] === 1
                        );
                        
                        if ($asaasDeleted) {
                            $stats['skipped_customers']++;
                            continue;
                        }

                        $asaasCustomerId = $asaasCustomer['id'] ?? null;
                        if (empty($asaasCustomerId)) {
                            $stats['skipped_customers']++;
                            continue;
                        }

                        // Tenta encontrar tenant existente
                        $tenant = null;
                        
                        // 1. Por asaas_customer_id
                        $stmt = $db->prepare("SELECT * FROM tenants WHERE asaas_customer_id = ?");
                        $stmt->execute([$asaasCustomerId]);
                        $tenant = $stmt->fetch();

                        // 2. Se não encontrou, tenta por CPF/CNPJ
                        if (!$tenant) {
                            $cpfCnpj = preg_replace('/[^0-9]/', '', $asaasCustomer['cpfCnpj'] ?? '');
                            if (!empty($cpfCnpj)) {
                                $stmt = $db->prepare("SELECT * FROM tenants WHERE cpf_cnpj = ? OR document = ?");
                                $stmt->execute([$cpfCnpj, $cpfCnpj]);
                                $tenant = $stmt->fetch();
                            }
                        }

                        // 3. Se ainda não encontrou, tenta por email
                        if (!$tenant && !empty($asaasCustomer['email'])) {
                            $stmt = $db->prepare("SELECT * FROM tenants WHERE email = ?");
                            $stmt->execute([$asaasCustomer['email']]);
                            $tenant = $stmt->fetch();
                        }

                        // Prepara dados do customer
                        $cpfCnpj = preg_replace('/[^0-9]/', '', $asaasCustomer['cpfCnpj'] ?? '');
                        $personType = ($asaasCustomer['personType'] ?? 'FISICA') === 'COMPANY' ? 'pj' : 'pf';
                        $name = $asaasCustomer['name'] ?? '';
                        $email = $asaasCustomer['email'] ?? null;
                        $phone = $asaasCustomer['phone'] ?? $asaasCustomer['mobilePhone'] ?? null;
                        
                        // Remove formatação do telefone
                        if ($phone) {
                            $phone = preg_replace('/[^0-9]/', '', $phone);
                        }

                        if ($tenant) {
                            // Atualiza tenant existente
                            $updateFields = [];
                            $updateValues = [];

                            // Sempre atualiza asaas_customer_id se estiver vazio ou diferente
                            if (empty($tenant['asaas_customer_id']) || $tenant['asaas_customer_id'] !== $asaasCustomerId) {
                                $updateFields[] = "asaas_customer_id = ?";
                                $updateValues[] = $asaasCustomerId;
                            }

                            // Atualiza dados se estiverem vazios (Asaas como fonte de verdade)
                            if (empty($tenant['cpf_cnpj']) && !empty($cpfCnpj)) {
                                $updateFields[] = "cpf_cnpj = ?";
                                $updateFields[] = "document = ?";
                                $updateValues[] = $cpfCnpj;
                                $updateValues[] = $cpfCnpj;
                            }

                            if (empty($tenant['email']) && !empty($email)) {
                                $updateFields[] = "email = ?";
                                $updateValues[] = $email;
                            }

                            // WhatsApp: só preenche se estiver vazio (não sobrescreve valor manual)
                            // O WhatsApp é um campo interno que pode ser editado no Pixel Hub
                            // e não deve ser sobrescrito pela sincronização do Asaas
                            if (empty($tenant['phone']) && !empty($phone)) {
                                $updateFields[] = "phone = ?";
                                $updateValues[] = $phone;
                            }

                            // Atualiza nome se estiver vazio ou se for diferente e o Asaas tiver mais informação
                            if (empty($tenant['name']) && !empty($name)) {
                                $updateFields[] = "name = ?";
                                $updateValues[] = $name;
                            }

                            if (!empty($updateFields)) {
                                $updateValues[] = $tenant['id'];
                                $sql = "UPDATE tenants SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($updateValues);
                            }

                            $stats['updated_customers']++;
                            $tenantId = $tenant['id'];
                        } else {
                            // Cria novo tenant
                            if (empty($cpfCnpj)) {
                                // Se não tem CPF/CNPJ, ainda cria mas marca como incompleto
                                $cpfCnpj = null;
                            }

                            // Para PJ, extrai dados adicionais
                            $razaoSocial = null;
                            $nomeFantasia = null;
                            
                            if ($personType === 'pj') {
                                $razaoSocial = $asaasCustomer['companyName'] ?? $name;
                                $nomeFantasia = $name;
                            }

                            $stmt = $db->prepare("
                                INSERT INTO tenants 
                                (person_type, name, cpf_cnpj, document, razao_social, nome_fantasia, 
                                 email, phone, asaas_customer_id, status, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                            ");
                            
                            $stmt->execute([
                                $personType,
                                $name,
                                $cpfCnpj,
                                $cpfCnpj, // document também
                                $razaoSocial,
                                $nomeFantasia,
                                $email,
                                $phone,
                                $asaasCustomerId,
                            ]);

                            $tenantId = (int) $db->lastInsertId();
                            $stats['created_customers']++;
                        }

                        // Sincroniza faturas deste customer
                        try {
                            $invoiceStats = self::syncInvoicesForAsaasCustomer($asaasCustomerId, $tenantId);
                            $stats['total_invoices_created'] += $invoiceStats['created'];
                            $stats['total_invoices_updated'] += $invoiceStats['updated'];
                        } catch (\Exception $e) {
                            error_log("Erro ao sincronizar faturas do customer {$asaasCustomerId}: " . $e->getMessage());
                            $stats['errors'][] = "Customer {$asaasCustomerId}: " . $e->getMessage();
                        }

                    } catch (\Exception $e) {
                        error_log("Erro ao processar customer do Asaas: " . $e->getMessage());
                        $stats['errors'][] = "Customer: " . $e->getMessage();
                        $stats['skipped_customers']++;
                    }
                }

                $offset += $limit;
                
                // Limite de segurança: máximo 1000 customers por execução
                if ($offset >= 1000) {
                    break;
                }

            } catch (\Exception $e) {
                error_log("Erro ao buscar customers do Asaas: " . $e->getMessage());
                $stats['errors'][] = "Erro na paginação: " . $e->getMessage();
                break;
            }
        }

        return $stats;
    }

    /**
     * Limpa faturas que foram deletadas/canceladas no Asaas
     * 
     * Verifica todas as invoices locais do tenant que estão pendentes/atrasadas
     * e não estão na lista de payments ativos do Asaas. Se uma invoice não for
     * encontrada na API (404) ou estiver com status CANCELED/REFUNDED/DELETED,
     * marca como is_deleted = 1 e status = 'canceled'.
     * 
     * @param int $tenantId ID do tenant
     * @param array $activePaymentIds Array de IDs de payments que estão ativos no Asaas (ex: ['pay_0000...', ...])
     * @return array Estatísticas da limpeza ['checked' => int, 'deleted' => int, 'errors' => int]
     */
    public static function cleanupDeletedInvoicesForTenant(int $tenantId, array $activePaymentIds = []): array
    {
        $db = DB::getConnection();

        // Busca todas as invoices do tenant que estão pendentes/atrasadas e não deletadas
        $stmt = $db->prepare("
            SELECT id, asaas_payment_id, status
            FROM billing_invoices
            WHERE tenant_id = ?
            AND status IN ('pending', 'overdue')
            AND (is_deleted IS NULL OR is_deleted = 0)
            ORDER BY due_date DESC
        ");
        $stmt->execute([$tenantId]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($invoices)) {
            return [
                'checked' => 0,
                'deleted' => 0,
                'errors' => 0,
            ];
        }

        // Converte activePaymentIds para array de strings para comparação
        $activePaymentIdsMap = array_flip($activePaymentIds);

        $checked = 0;
        $deleted = 0;
        $errors = 0;

        foreach ($invoices as $invoice) {
            $invoiceId = $invoice['id'];
            $paymentId = $invoice['asaas_payment_id'];

            // Se temos lista de payments ativos e este payment está na lista, pula
            if (!empty($activePaymentIdsMap) && isset($activePaymentIdsMap[$paymentId])) {
                continue;
            }

            $checked++;

            try {
                // Busca payment no Asaas
                $payment = AsaasClient::request('GET', "/payments/{$paymentId}", null);

                // Verifica se foi deletada ou cancelada
                $asaasDeleted = isset($payment['deleted']) && (
                    $payment['deleted'] === true ||
                    $payment['deleted'] === 'true' ||
                    $payment['deleted'] === 1
                );
                $asaasStatus = strtoupper($payment['status'] ?? 'PENDING');

                if ($asaasDeleted || in_array($asaasStatus, ['CANCELED', 'REFUNDED'])) {
                    // Marca como deletada
                    $stmt = $db->prepare("
                        UPDATE billing_invoices
                        SET status = 'canceled',
                            is_deleted = 1,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$invoiceId]);
                    $deleted++;

                    error_log("AsaasBillingService: Invoice #{$invoiceId} (payment {$paymentId}) marcada como deletada (deleted={$asaasDeleted}, status={$asaasStatus})");
                }
                // Se não está deletada, mantém como está

            } catch (\RuntimeException $e) {
                // Se retornar 404, a cobrança não existe mais no Asaas
                if (strpos($e->getMessage(), '404') !== false) {
                    // Marca como deletada
                    $stmt = $db->prepare("
                        UPDATE billing_invoices
                        SET status = 'canceled',
                            is_deleted = 1,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$invoiceId]);
                    $deleted++;

                    error_log("AsaasBillingService: Invoice #{$invoiceId} (payment {$paymentId}) marcada como deletada (não encontrada no Asaas - 404)");
                } else {
                    // Outro erro (API, rede, etc.) - apenas loga, não marca como deletada
                    $errors++;
                    error_log("AsaasBillingService: Erro ao verificar invoice #{$invoiceId} (payment {$paymentId}): " . $e->getMessage());
                }
            }
        }

        // Se houve alguma atualização, refresca o status financeiro do tenant
        if ($deleted > 0) {
            self::refreshTenantBillingStatus($tenantId);
        }

        return [
            'checked' => $checked,
            'deleted' => $deleted,
            'errors' => $errors,
        ];
    }
}

