<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para Carteira Recorrente
 * 
 * RESUMO CONCEITUAL:
 * 
 * A Carteira Recorrente é uma visão gerencial consolidada de todos os contratos e assinaturas 
 * recorrentes da Pixel12Digital com seus clientes. Ela permite visualizar:
 * - Todos os contratos recorrentes em um só lugar (hospedagem, SaaS, outros serviços)
 * - Receita recorrente estimada (mensal/anual)
 * - Análise financeira consolidada
 * 
 * IMPORTANTE: Esta tela NÃO substitui as telas atuais de financeiro:
 * - Aba Financeiro do cliente (/tenants/view?tab=financial) continua mostrando faturas individuais
 * - Central de Cobranças (/billing/overview) continua sendo o lugar para gerenciar cobranças
 * - Esta é apenas uma camada de visão gerencial em cima do que já existe
 * 
 * CAMPOS UTILIZADOS DE billing_contracts (nesta primeira versão):
 * - id, tenant_id, hosting_account_id (opcional), hosting_plan_id (opcional)
 * - plan_snapshot_name (nome do plano/contrato)
 * - billing_mode ('mensal' ou 'anual')
 * - amount (valor recorrente - receita do contrato)
 * - annual_total_amount (quando for anual)
 * - status ('ativo', 'cancelado', etc.)
 * - created_at, updated_at
 * 
 * REGRA CRÍTICA: Não quebrar nada do financeiro atual
 * - Não alterar sincronização com Asaas
 * - Não alterar aba Financeiro do cliente
 * - Não alterar Central de Cobranças
 * - Toda lógica nova está isolada neste controller e na view
 * 
 * @see docs/ESPEC_CARTEIRA_RECORRENTE.md
 * @see docs/ARQUITETURA_HOSPEDAGEM_FINANCEIRO.md
 */
class RecurringContractsController extends Controller
{
    /**
     * Lista todos os contratos recorrentes com filtros e resumo
     * 
     * GET /recurring-contracts
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Filtros da query string (todos opcionais)
        $tenantIdFilter = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $statusFilter = $_GET['status'] ?? 'all';
        $billingModeFilter = $_GET['billing_mode'] ?? 'all';
        $categoryFilter = $_GET['category'] ?? 'all';

        // Monta query base com JOINs
        $where = [];
        $params = [];

        // Filtro por tenant
        if ($tenantIdFilter) {
            $where[] = "bc.tenant_id = ?";
            $params[] = $tenantIdFilter;
        }

        // Filtro por status (valida se o status existe antes de aplicar)
        if ($statusFilter !== 'all') {
            // Valida se o status é um valor válido (busca na lista de status disponíveis)
            $validStatuses = $db->query("SELECT DISTINCT status FROM billing_contracts WHERE status IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN);
            if (in_array($statusFilter, $validStatuses)) {
                $where[] = "bc.status = ?";
                $params[] = $statusFilter;
            } else {
                // Se status inválido, ignora o filtro (fallback para 'all')
                $statusFilter = 'all';
            }
        }

        // Filtro por billing_mode
        if ($billingModeFilter !== 'all') {
            $where[] = "bc.billing_mode = ?";
            $params[] = $billingModeFilter;
        }

        // Filtro por categoria (usando service_type da tabela ou fallback)
        if ($categoryFilter !== 'all') {
            // Se for um slug válido, filtra por service_type
            // Caso contrário, usa fallback baseado em hosting_account_id/hosting_plan_id
            if ($categoryFilter === 'hospedagem') {
                // Fallback: se service_type for NULL mas tiver hosting_account_id ou hosting_plan_id, considera hospedagem
                $where[] = "(bc.service_type = 'hospedagem' OR (bc.service_type IS NULL AND (bc.hosting_account_id IS NOT NULL OR bc.hosting_plan_id IS NOT NULL)))";
            } else {
                // Filtra por service_type específico
                $where[] = "bc.service_type = ?";
                $params[] = $categoryFilter;
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Verifica se a tabela billing_service_types existe
        $hasServiceTypesTable = false;
        try {
            $db->query("SELECT 1 FROM billing_service_types LIMIT 1");
            $hasServiceTypesTable = true;
        } catch (\PDOException $e) {
            // Tabela não existe ainda
            error_log("Tabela billing_service_types não existe. Execute as migrations: php database/migrate.php");
        }

        // Query principal com JOINs (incluindo billing_service_types e current_provider)
        $serviceTypesJoin = $hasServiceTypesTable 
            ? "LEFT JOIN billing_service_types bst ON bc.service_type = bst.slug AND bst.is_active = 1"
            : "";
        
        $sql = "
            SELECT 
                bc.*,
                t.id as tenant_id,
                t.name as tenant_name,
                t.person_type,
                t.nome_fantasia,
                ha.domain as hosting_domain,
                ha.current_provider as hosting_provider,
                hp.name as hosting_plan_name" . 
                ($hasServiceTypesTable ? ",
                bst.name as service_type_name,
                bst.slug as service_type_slug" : "") . "
            FROM billing_contracts bc
            INNER JOIN tenants t ON bc.tenant_id = t.id
            LEFT JOIN hosting_accounts ha ON bc.hosting_account_id = ha.id
            LEFT JOIN hosting_plans hp ON bc.hosting_plan_id = hp.id
            {$serviceTypesJoin}
            {$whereClause}
            ORDER BY bc.created_at DESC
        ";

        // Paginação simples (20 por página)
        $page = isset($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Conta total de registros
        $countSql = "
            SELECT COUNT(*) as total
            FROM billing_contracts bc
            INNER JOIN tenants t ON bc.tenant_id = t.id
            {$whereClause}
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $countResult = $countStmt->fetch();
        $totalRecords = (int) ($countResult['total'] ?? 0);
        $totalPages = ceil($totalRecords / $perPage);

        // Busca contratos com paginação
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contracts = $stmt->fetchAll();

        // Adiciona categoria calculada em memória para cada contrato (fallback se service_type for NULL)
        foreach ($contracts as &$contract) {
            // Se service_type estiver preenchido, usa ele
            if (!empty($contract['service_type'])) {
                $contract['category'] = $contract['service_type'];
                $contract['category_name'] = $contract['service_type_name'] ?? ucfirst($contract['service_type']);
            } else {
                // Fallback: se tiver hosting_account_id ou hosting_plan_id, considera "hospedagem"
                if (!empty($contract['hosting_account_id']) || !empty($contract['hosting_plan_id'])) {
                    $contract['category'] = 'hospedagem';
                    $contract['category_name'] = 'Hospedagem';
                } else {
                    $contract['category'] = 'outros';
                    $contract['category_name'] = 'Outros serviços';
                }
            }
        }
        unset($contract); // Remove referência

        // Calcula indicadores de resumo (apenas contratos ativos)
        // Nota: Usamos status = 'ativo' conforme convenção atual da coluna status
        // Aplica mesmos filtros de tenant e billing_mode no resumo (mas sempre status = ativo)
        $summaryWhere = ["status = 'ativo'"];
        $summaryParams = [];
        if ($tenantIdFilter) {
            $summaryWhere[] = "tenant_id = ?";
            $summaryParams[] = $tenantIdFilter;
        }
        if ($billingModeFilter !== 'all') {
            $summaryWhere[] = "billing_mode = ?";
            $summaryParams[] = $billingModeFilter;
        }
        // Aplica filtro de categoria no resumo também
        if ($categoryFilter !== 'all') {
            if ($categoryFilter === 'hospedagem') {
                $summaryWhere[] = "(service_type = 'hospedagem' OR (service_type IS NULL AND (hosting_account_id IS NOT NULL OR hosting_plan_id IS NOT NULL)))";
            } else {
                $summaryWhere[] = "service_type = ?";
                $summaryParams[] = $categoryFilter;
            }
        }

        $summaryWhereClause = 'WHERE ' . implode(' AND ', $summaryWhere);
        $summarySql = "
            SELECT 
                COUNT(*) as total_contracts_ativos,
                COALESCE(SUM(CASE WHEN billing_mode = 'mensal' THEN amount ELSE 0 END), 0) as total_mensal,
                COALESCE(SUM(CASE WHEN billing_mode = 'anual' THEN amount ELSE 0 END), 0) as total_anual,
                COALESCE(SUM(amount), 0) as total_receita
            FROM billing_contracts
            {$summaryWhereClause}
        ";

        $summaryStmt = $db->prepare($summarySql);
        $summaryStmt->execute($summaryParams);
        $summary = $summaryStmt->fetch();

        // Calcula resumo por categoria (apenas contratos mensais ativos)
        // Nota: Esta categorização é provisória até existir um campo service_type oficial
        $categorySummaryWhere = ["status = 'ativo'", "billing_mode = 'mensal'"];
        $categorySummaryParams = [];
        if ($tenantIdFilter) {
            $categorySummaryWhere[] = "tenant_id = ?";
            $categorySummaryParams[] = $tenantIdFilter;
        }
        // Aplica filtro de categoria se estiver selecionado
        if ($categoryFilter !== 'all') {
            if ($categoryFilter === 'hospedagem') {
                $categorySummaryWhere[] = "(service_type = 'hospedagem' OR (service_type IS NULL AND (hosting_account_id IS NOT NULL OR hosting_plan_id IS NOT NULL)))";
            } else {
                $categorySummaryWhere[] = "service_type = ?";
                $categorySummaryParams[] = $categoryFilter;
            }
        }

        $categorySummaryWhereClause = 'WHERE ' . implode(' AND ', $categorySummaryWhere);
        $categorySummarySql = "
            SELECT 
                COALESCE(SUM(CASE WHEN (hosting_account_id IS NOT NULL OR hosting_plan_id IS NOT NULL) THEN amount ELSE 0 END), 0) as mensal_hospedagem,
                COALESCE(SUM(CASE WHEN (hosting_account_id IS NULL AND hosting_plan_id IS NULL) THEN amount ELSE 0 END), 0) as mensal_outros
            FROM billing_contracts
            {$categorySummaryWhereClause}
        ";

        $categorySummaryStmt = $db->prepare($categorySummarySql);
        $categorySummaryStmt->execute($categorySummaryParams);
        $categorySummary = $categorySummaryStmt->fetch();

        // Busca lista de tenants para o filtro (apenas tenants que têm contratos)
        $tenantsSql = "
            SELECT DISTINCT t.id, t.name, t.nome_fantasia, t.person_type
            FROM tenants t
            INNER JOIN billing_contracts bc ON t.id = bc.tenant_id
            ORDER BY t.name ASC
        ";
        $tenantsStmt = $db->query($tenantsSql);
        $tenantsOptions = $tenantsStmt->fetchAll();

        // Busca valores únicos de status para o filtro
        $statusSql = "SELECT DISTINCT status FROM billing_contracts WHERE status IS NOT NULL ORDER BY status ASC";
        $statusStmt = $db->query($statusSql);
        $statusOptions = $statusStmt->fetchAll(\PDO::FETCH_COLUMN);

        // Busca categorias ativas para o filtro (verifica se a tabela existe primeiro)
        $serviceTypesOptions = [];
        try {
            // Verifica se a tabela existe
            $db->query("SELECT 1 FROM billing_service_types LIMIT 1");
            $serviceTypesSql = "SELECT slug, name FROM billing_service_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
            $serviceTypesStmt = $db->query($serviceTypesSql);
            $serviceTypesOptions = $serviceTypesStmt->fetchAll();
        } catch (\PDOException $e) {
            // Tabela não existe ainda - migrations não foram executadas
            // Continua sem categorias (fallback para categorias hardcoded)
            error_log("Tabela billing_service_types não existe ainda. Execute as migrations: php database/migrate.php");
        }

        $this->view('billing.recurring_wallet', [
            'contracts' => $contracts,
            'filters' => [
                'tenant_id' => $tenantIdFilter,
                'status' => $statusFilter,
                'billing_mode' => $billingModeFilter,
                'category' => $categoryFilter,
            ],
            'summary' => [
                'total_contracts_ativos' => (int) ($summary['total_contracts_ativos'] ?? 0),
                'total_mensal' => (float) ($summary['total_mensal'] ?? 0),
                'total_anual' => (float) ($summary['total_anual'] ?? 0),
                'total_receita' => (float) ($summary['total_receita'] ?? 0),
                'mensal_hospedagem' => (float) ($categorySummary['mensal_hospedagem'] ?? 0),
                'mensal_outros' => (float) ($categorySummary['mensal_outros'] ?? 0),
            ],
            'tenantsOptions' => $tenantsOptions,
            'statusOptions' => $statusOptions,
            'serviceTypesOptions' => $serviceTypesOptions,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Atualiza a categoria (service_type) de um contrato via AJAX
     * 
     * POST /recurring-contracts/update-category
     */
    public function updateCategory(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        $contractId = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        $serviceType = trim($_POST['service_type'] ?? '');

        if ($contractId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID do contrato inválido']);
            return;
        }

        // Se service_type for vazio, define como NULL
        if (empty($serviceType)) {
            $serviceType = null;
        } else {
            // Valida se o service_type existe na tabela
            $db = DB::getConnection();
            $stmt = $db->prepare("SELECT id FROM billing_service_types WHERE slug = ? AND is_active = 1");
            $stmt->execute([$serviceType]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Categoria inválida']);
                return;
            }
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("UPDATE billing_contracts SET service_type = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$serviceType, $contractId]);

            // Busca o nome da categoria atualizada
            $categoryName = 'Outros serviços';
            if ($serviceType) {
                $stmt = $db->prepare("SELECT name FROM billing_service_types WHERE slug = ?");
                $stmt->execute([$serviceType]);
                $result = $stmt->fetch();
                if ($result) {
                    $categoryName = $result['name'];
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso',
                'category_name' => $categoryName,
                'category_slug' => $serviceType ?? 'outros',
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar categoria do contrato: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar categoria']);
        }
    }
}

