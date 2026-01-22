<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Services\ContractClauseService;
use PixelHub\Services\CompanySettingsService;

/**
 * Service para gerenciar contratos de projetos
 * 
 * Gerencia contratos formais vinculados a projetos, com valor editável,
 * link único para aceite pelo cliente e histórico de aceitação.
 */
class ProjectContractService
{
    /**
     * Cria um novo contrato para um projeto
     * 
     * @param array $data Dados do contrato
     * @return int ID do contrato criado
     */
    public static function createContract(array $data): int
    {
        $db = DB::getConnection();
        
        // Validações
        $projectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;
        $contractValue = !empty($data['contract_value']) ? (float) $data['contract_value'] : null;
        
        if (empty($projectId)) {
            throw new \InvalidArgumentException('ID do projeto é obrigatório');
        }
        
        if (empty($tenantId)) {
            throw new \InvalidArgumentException('ID do cliente é obrigatório');
        }
        
        if ($contractValue === null || $contractValue <= 0) {
            throw new \InvalidArgumentException('Valor do contrato deve ser maior que zero');
        }
        
        // Gera token único para o link público
        $token = self::generateUniqueToken();
        
        // Processa dados opcionais
        $serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        $servicePrice = !empty($data['service_price']) ? (float) $data['service_price'] : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $status = !empty($data['status']) ? trim($data['status']) : 'draft';
        
        // Valida status
        $allowedStatuses = ['draft', 'sent', 'accepted', 'rejected'];
        if (!in_array($status, $allowedStatuses)) {
            $status = 'draft';
        }
        
        // Monta conteúdo do contrato automaticamente
        $contractContent = self::buildContractContent($projectId, $tenantId, $serviceId, $contractValue);
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO project_contracts 
            (project_id, tenant_id, service_id, contract_value, service_price, contract_token, status, notes, contract_content, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $projectId,
            $tenantId,
            $serviceId,
            $contractValue,
            $servicePrice,
            $token,
            $status,
            $notes,
            $contractContent,
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Busca um contrato por ID
     * 
     * @param int $id ID do contrato
     * @return array|null Contrato ou null se não encontrado
     */
    public static function findContract(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, 
                   p.name as project_name,
                   t.name as tenant_name,
                   s.name as service_name,
                   u.name as whatsapp_sent_by_name
            FROM project_contracts c
            LEFT JOIN projects p ON c.project_id = p.id
            LEFT JOIN tenants t ON c.tenant_id = t.id
            LEFT JOIN services s ON c.service_id = s.id
            LEFT JOIN users u ON c.whatsapp_sent_by = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca um contrato por token (para link público)
     * 
     * @param string $token Token do contrato
     * @return array|null Contrato ou null se não encontrado
     */
    public static function findContractByToken(string $token): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, 
                   p.name as project_name,
                   p.description as project_description,
                   t.name as tenant_name,
                   t.email as tenant_email,
                   t.phone as tenant_phone,
                   s.name as service_name,
                   s.description as service_description
            FROM project_contracts c
            LEFT JOIN projects p ON c.project_id = p.id
            LEFT JOIN tenants t ON c.tenant_id = t.id
            LEFT JOIN services s ON c.service_id = s.id
            WHERE c.contract_token = ?
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca contratos de um projeto
     * 
     * @param int $projectId ID do projeto
     * @return array Lista de contratos
     */
    public static function getContractsByProject(int $projectId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, 
                   t.name as tenant_name,
                   s.name as service_name,
                   u.name as whatsapp_sent_by_name
            FROM project_contracts c
            LEFT JOIN tenants t ON c.tenant_id = t.id
            LEFT JOIN services s ON c.service_id = s.id
            LEFT JOIN users u ON c.whatsapp_sent_by = u.id
            WHERE c.project_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca contratos de um cliente
     * 
     * @param int $tenantId ID do cliente
     * @return array Lista de contratos
     */
    public static function getContractsByTenant(int $tenantId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, 
                   p.name as project_name,
                   s.name as service_name,
                   u.name as whatsapp_sent_by_name
            FROM project_contracts c
            LEFT JOIN projects p ON c.project_id = p.id
            LEFT JOIN services s ON c.service_id = s.id
            LEFT JOIN users u ON c.whatsapp_sent_by = u.id
            WHERE c.tenant_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Atualiza um contrato
     * 
     * @param int $id ID do contrato
     * @param array $data Dados para atualizar
     * @return bool Sucesso da operação
     */
    public static function updateContract(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        // Verifica se existe
        $contract = self::findContract($id);
        if (!$contract) {
            throw new \InvalidArgumentException('Contrato não encontrado');
        }
        
        // Bloqueia edição se já foi aceito ou rejeitado
        if (in_array($contract['status'], ['accepted', 'rejected'])) {
            throw new \InvalidArgumentException('Contrato não pode ser editado após ser ' . ($contract['status'] === 'accepted' ? 'aceito' : 'rejeitado'));
        }
        
        // Monta query de atualização dinamicamente
        $updates = [];
        $params = [];
        
        if (isset($data['contract_value'])) {
            $value = (float) $data['contract_value'];
            if ($value <= 0) {
                throw new \InvalidArgumentException('Valor do contrato deve ser maior que zero');
            }
            $updates[] = "contract_value = ?";
            $params[] = $value;
        }
        
        if (isset($data['status'])) {
            $status = trim($data['status']);
            $allowedStatuses = ['draft', 'sent', 'accepted', 'rejected'];
            if (!in_array($status, $allowedStatuses)) {
                throw new \InvalidArgumentException('Status inválido');
            }
            $updates[] = "status = ?";
            $params[] = $status;
        }
        
        if (isset($data['notes'])) {
            $updates[] = "notes = ?";
            $params[] = !empty($data['notes']) ? trim($data['notes']) : null;
        }
        
        if (empty($updates)) {
            return true; // Nada para atualizar
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE project_contracts SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return true;
    }
    
    /**
     * Registra o envio do contrato via WhatsApp
     * 
     * @param int $id ID do contrato
     * @param int $userId ID do usuário que enviou
     * @return bool Sucesso da operação
     */
    public static function markAsSent(int $id, int $userId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE project_contracts 
            SET status = 'sent', 
                whatsapp_sent_at = NOW(), 
                whatsapp_sent_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId, $id]);
        
        return true;
    }
    
    /**
     * Registra o aceite do contrato pelo cliente
     * 
     * @param string $token Token do contrato
     * @param string|null $ip IP do cliente
     * @param string|null $userAgent User agent do cliente
     * @return bool Sucesso da operação
     */
    public static function acceptContract(string $token, ?string $ip = null, ?string $userAgent = null): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o contrato existe e não foi aceito ainda
        $contract = self::findContractByToken($token);
        if (!$contract) {
            throw new \InvalidArgumentException('Contrato não encontrado');
        }
        
        if ($contract['status'] === 'accepted') {
            throw new \InvalidArgumentException('Este contrato já foi aceito');
        }
        
        if ($contract['status'] === 'rejected') {
            throw new \InvalidArgumentException('Este contrato foi rejeitado');
        }
        
        // Atualiza status
        $stmt = $db->prepare("
            UPDATE project_contracts 
            SET status = 'accepted', 
                accepted_at = NOW(),
                accepted_by_ip = ?,
                accepted_by_user_agent = ?,
                updated_at = NOW()
            WHERE contract_token = ?
        ");
        $stmt->execute([$ip, $userAgent, $token]);
        
        return true;
    }
    
    /**
     * Gera um token único para o link público
     * 
     * @return string Token único
     */
    private static function generateUniqueToken(): string
    {
        $db = DB::getConnection();
        
        do {
            // Gera token aleatório de 32 caracteres
            $token = bin2hex(random_bytes(16));
            
            // Verifica se já existe
            $stmt = $db->prepare("SELECT id FROM project_contracts WHERE contract_token = ?");
            $stmt->execute([$token]);
            $exists = $stmt->fetch();
        } while ($exists);
        
        return $token;
    }
    
    /**
     * Gera o link público para aceite do contrato
     * 
     * @param string $token Token do contrato
     * @return string URL completa do link
     */
    public static function generatePublicLink(string $token): string
    {
        // Obtém a URL base do servidor
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $baseUrl = $protocol . '://' . $host . $basePath;
        
        return rtrim($baseUrl, '/') . '/contract/accept/' . $token;
    }
    
    /**
     * Monta o conteúdo completo do contrato com cláusulas
     * 
     * @param int $projectId ID do projeto
     * @param int $tenantId ID do cliente
     * @param int|null $serviceId ID do serviço
     * @param float $contractValue Valor do contrato
     * @return string Conteúdo HTML do contrato
     */
    private static function buildContractContent(int $projectId, int $tenantId, ?int $serviceId, float $contractValue): string
    {
        $db = DB::getConnection();
        
        // Busca dados do projeto
        $stmt = $db->prepare("SELECT name, description, due_date FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        
        // Busca dados do cliente
        $stmt = $db->prepare("SELECT name, person_type, cpf_cnpj, razao_social, nome_fantasia FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        // Busca dados do serviço
        $service = null;
        if ($serviceId) {
            $stmt = $db->prepare("SELECT name, description, estimated_duration FROM services WHERE id = ?");
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();
        }
        
        // Calcula prazo em dias
        $prazoDias = null;
        $prazoTexto = 'a definir';
        
        if (!empty($project['due_date'])) {
            // Se tem due_date, calcula dias até a data de vencimento
            $dueDate = new \DateTime($project['due_date']);
            $today = new \DateTime();
            $diff = $today->diff($dueDate);
            $prazoDias = (int) $diff->days;
            $prazoTexto = $prazoDias . ' dia' . ($prazoDias > 1 ? 's' : '');
        } elseif (!empty($service['estimated_duration']) && $service['estimated_duration'] > 0) {
            // Se não tem due_date mas tem estimated_duration do serviço, usa ele
            $prazoDias = (int) $service['estimated_duration'];
            $prazoTexto = $prazoDias . ' dia' . ($prazoDias > 1 ? 's' : '');
        }
        
        // Busca dados da empresa
        $companySettings = CompanySettingsService::getSettings();
        $companyName = $companySettings['company_name'] ?? 'Pixel12 Digital';
        $companyCnpj = $companySettings['cnpj'] ?? null;
        $companyAddress = CompanySettingsService::getFormattedAddress();
        $logoUrl = CompanySettingsService::getLogoUrl();
        
        // Prepara variáveis para substituição nas cláusulas
        $variables = [
            'cliente' => $tenant['name'] ?? 'N/A',
            'servico' => $service['name'] ?? 'Serviço prestado',
            'projeto' => $project['name'] ?? 'N/A',
            'valor' => number_format($contractValue, 2, ',', '.'),
            'empresa' => $companyName,
            'prazo' => $prazoTexto,
            'prazo_dias' => $prazoDias !== null ? (string) $prazoDias : '',
        ];
        
        // Monta cabeçalho do contrato
        $content = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
        
        // Logo removido daqui - será exibido apenas no header principal da página
        // Isso evita duplicação e mantém o documento mais institucional
        
        $content .= '<h2 style="color: #023A8D; text-align: center; margin-bottom: 30px;">CONTRATO DE PRESTAÇÃO DE SERVIÇOS</h2>';
        $content .= '<div style="margin-bottom: 30px;">';
        $content .= '<p><strong>CONTRATANTE:</strong> ' . htmlspecialchars($tenant['name'] ?? 'N/A');
        if (!empty($tenant['cpf_cnpj'])) {
            $content .= ' - ' . htmlspecialchars($tenant['cpf_cnpj']);
        }
        $content .= '</p>';
        $content .= '<p><strong>CONTRATADA:</strong> ' . htmlspecialchars($companyName);
        if ($companyCnpj) {
            $content .= ' - CNPJ: ' . htmlspecialchars($companyCnpj);
        }
        $content .= '</p>';
        if ($companyAddress) {
            $content .= '<p><strong>Endereço da CONTRATADA:</strong> ' . htmlspecialchars($companyAddress) . '</p>';
        }
        $content .= '<p><strong>PROJETO:</strong> ' . htmlspecialchars($project['name'] ?? 'N/A') . '</p>';
        if ($service) {
            $content .= '<p><strong>SERVIÇO:</strong> ' . htmlspecialchars($service['name']) . '</p>';
        }
        $content .= '<p><strong>VALOR DO CONTRATO:</strong> R$ ' . number_format($contractValue, 2, ',', '.') . '</p>';
        $content .= '</div>';
        $content .= '<hr style="margin: 30px 0; border: none; border-top: 2px solid #ddd;">';
        
        // Monta cláusulas
        $clauses = ContractClauseService::getActiveClauses();
        foreach ($clauses as $clause) {
            $clauseContent = ContractClauseService::replaceVariables($clause['content'], $variables);
            $content .= '<div style="margin-bottom: 25px;">';
            $content .= '<h3 style="color: #023A8D; font-size: 16px; margin-bottom: 10px;">' . htmlspecialchars($clause['title']) . '</h3>';
            $content .= '<p style="text-align: justify;">' . nl2br(htmlspecialchars($clauseContent)) . '</p>';
            $content .= '</div>';
        }
        
        // Adiciona rodapé
        $content .= '<hr style="margin: 30px 0; border: none; border-top: 2px solid #ddd;">';
        $content .= '<div style="margin-top: 30px; text-align: center; color: #666; font-size: 12px;">';
        $content .= '<p>Este contrato foi gerado automaticamente pelo sistema Pixel Hub em ' . date('d/m/Y') . '</p>';
        $content .= '</div>';
        $content .= '</div>';
        
        return $content;
    }

    /**
     * Constrói preview do conteúdo do contrato sem criar projeto real
     * 
     * @param array $data Dados do contrato (tenant, project_name, service, contract_value, etc.)
     * @return string Conteúdo HTML do contrato
     */
    public static function buildContractContentPreview(array $data): string
    {
        $tenant = $data['tenant'] ?? [];
        $projectName = $data['project_name'] ?? 'N/A';
        $service = $data['service'] ?? [];
        $contractValue = $data['contract_value'] ?? 0.0;
        $prazoTexto = $data['prazo'] ?? 'a definir';
        $prazoDias = $data['prazo_dias'] ?? null;
        $companyName = $data['company_name'] ?? 'Pixel12 Digital';
        $companyCnpj = $data['company_cnpj'] ?? null;
        $companyAddress = $data['company_address'] ?? '';
        $logoUrl = $data['logo_url'] ?? null;
        
        // Prepara variáveis para substituição nas cláusulas
        $variables = [
            'cliente' => $tenant['name'] ?? 'N/A',
            'servico' => $service['name'] ?? 'Serviço prestado',
            'projeto' => $projectName,
            'valor' => number_format($contractValue, 2, ',', '.'),
            'empresa' => $companyName,
            'prazo' => $prazoTexto,
            'prazo_dias' => $prazoDias !== null ? (string) $prazoDias : '',
            'company_name' => $companyName,
            'company_cnpj' => $companyCnpj ?? '',
            'company_address' => $companyAddress,
        ];
        
        // Monta cabeçalho do contrato
        $content = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
        
        // Logo da empresa (se houver)
        if ($logoUrl) {
            $fullLogoUrl = strpos($logoUrl, 'http') === 0 ? $logoUrl : pixelhub_url($logoUrl);
            $content .= '<div style="text-align: center; margin-bottom: 30px;">';
            $content .= '<img src="' . htmlspecialchars($fullLogoUrl) . '" alt="' . htmlspecialchars($companyName) . '" style="max-height: 80px; max-width: 300px;">';
            $content .= '</div>';
        }
        
        $content .= '<h1 style="text-align: center; color: #023A8D; margin-bottom: 30px; font-size: 24px;">CONTRATO DE PRESTAÇÃO DE SERVIÇOS</h1>';
        
        // Identificação das partes
        $content .= '<div style="margin-bottom: 20px;">';
        $content .= '<p><strong>CONTRATANTE:</strong> ' . htmlspecialchars($tenant['name'] ?? 'N/A');
        if (!empty($tenant['cpf_cnpj'])) {
            $docLabel = ($tenant['person_type'] ?? '') === 'juridica' ? 'CNPJ' : 'CPF';
            $content .= ' - ' . $docLabel . ': ' . htmlspecialchars($tenant['cpf_cnpj']);
        }
        $content .= '</p>';
        
        $content .= '<p><strong>CONTRATADA:</strong> ' . htmlspecialchars($companyName);
        if ($companyCnpj) {
            $content .= ' - CNPJ: ' . htmlspecialchars($companyCnpj);
        }
        if ($companyAddress) {
            $content .= '<br>' . htmlspecialchars($companyAddress);
        }
        $content .= '</p>';
        $content .= '</div>';
        
        // Dados do projeto e serviço
        $content .= '<div style="margin-bottom: 20px;">';
        $content .= '<p><strong>PROJETO:</strong> ' . htmlspecialchars($projectName) . '</p>';
        $content .= '<p><strong>SERVIÇO(S):</strong> ' . htmlspecialchars($service['name'] ?? 'Serviço prestado') . '</p>';
        $content .= '<p><strong>VALOR:</strong> R$ ' . number_format($contractValue, 2, ',', '.') . '</p>';
        $content .= '</div>';
        
        // Busca cláusulas ativas
        $clauses = ContractClauseService::getActiveClauses();
        
        if (empty($clauses)) {
            $content .= '<p style="color: #666; font-style: italic;">Nenhuma cláusula configurada.</p>';
        } else {
            // Itera pelas cláusulas e substitui variáveis
            foreach ($clauses as $clause) {
                $clauseContent = ContractClauseService::replaceVariables($clause['content'], $variables);
                $content .= '<div style="margin-bottom: 25px;">';
                $content .= '<h3 style="color: #023A8D; font-size: 16px; margin-bottom: 10px;">' . htmlspecialchars($clause['title']) . '</h3>';
                $content .= '<div style="text-align: justify;">' . nl2br(htmlspecialchars($clauseContent)) . '</div>';
                $content .= '</div>';
            }
        }
        
        $content .= '</div>';
        
        return $content;
    }
}

