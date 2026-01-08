<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProjectContractService;
use PixelHub\Services\WhatsAppTemplateService;

/**
 * Controller para gerenciar contratos de projetos
 */
class ProjectContractController extends Controller
{
    /**
     * Lista todos os contratos ou contratos de um projeto
     * 
     * GET /contracts (lista todos)
     * GET /contracts?project_id={id} (lista de um projeto)
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $projectId = !empty($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        $tenantId = !empty($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
        $status = !empty($_GET['status']) ? $_GET['status'] : null;
        
        $db = DB::getConnection();
        
        // Monta query com filtros
        $where = [];
        $params = [];
        
        if ($projectId) {
            $where[] = "c.project_id = ?";
            $params[] = $projectId;
        }
        
        if ($tenantId) {
            $where[] = "c.tenant_id = ?";
            $params[] = $tenantId;
        }
        
        if ($status) {
            $where[] = "c.status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
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
            {$whereClause}
            ORDER BY c.created_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contracts = $stmt->fetchAll();
        
        // Se for requisição AJAX, retorna JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json([
                'success' => true,
                'contracts' => $contracts,
            ]);
            return;
        }
        
        // Caso contrário, renderiza view
        // Busca lista de projetos e clientes para filtros
        $projects = $db->query("SELECT id, name FROM projects ORDER BY name ASC")->fetchAll();
        $tenants = $db->query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll();
        
        $this->view('contracts.index', [
            'contracts' => $contracts,
            'projects' => $projects,
            'tenants' => $tenants,
            'selectedProjectId' => $projectId,
            'selectedTenantId' => $tenantId,
            'selectedStatus' => $status,
        ]);
    }
    
    /**
     * Visualiza um contrato
     * 
     * GET /contracts/{id}
     */
    public function show(): void
    {
        Auth::requireInternal();
        
        $id = !empty($_GET['id']) ? (int) $_GET['id'] : null;
        
        if (!$id) {
            $this->json(['error' => 'ID do contrato é obrigatório'], 400);
            return;
        }
        
        $contract = ProjectContractService::findContract($id);
        
        if (!$contract) {
            $this->json(['error' => 'Contrato não encontrado'], 404);
            return;
        }
        
        // Gera link público
        $contract['public_link'] = ProjectContractService::generatePublicLink($contract['contract_token']);
        
        $this->json([
            'success' => true,
            'contract' => $contract,
        ]);
    }
    
    /**
     * Atualiza valor do contrato
     * 
     * POST /contracts/{id}/update-value
     */
    public function updateValue(): void
    {
        Auth::requireInternal();
        
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $value = !empty($_POST['contract_value']) ? (float) str_replace(['.', ','], ['', '.'], $_POST['contract_value']) : null;
        
        if (!$id) {
            $this->json(['error' => 'ID do contrato é obrigatório'], 400);
            return;
        }
        
        if ($value === null || $value <= 0) {
            $this->json(['error' => 'Valor do contrato deve ser maior que zero'], 400);
            return;
        }
        
        try {
            ProjectContractService::updateContract($id, [
                'contract_value' => $value,
            ]);
            
            $contract = ProjectContractService::findContract($id);
            
            $this->json([
                'success' => true,
                'message' => 'Valor do contrato atualizado com sucesso',
                'contract' => $contract,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar valor do contrato: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar valor do contrato'], 500);
        }
    }
    
    /**
     * Envia contrato via WhatsApp
     * 
     * POST /contracts/{id}/send-whatsapp
     */
    public function sendWhatsApp(): void
    {
        Auth::requireInternal();
        
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        
        if (!$id) {
            $this->json(['error' => 'ID do contrato é obrigatório'], 400);
            return;
        }
        
        $user = Auth::user();
        $contract = ProjectContractService::findContract($id);
        
        if (!$contract) {
            $this->json(['error' => 'Contrato não encontrado'], 404);
            return;
        }
        
        // Busca dados do cliente
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT id, name, phone, email FROM tenants WHERE id = ?");
        $stmt->execute([$contract['tenant_id']]);
        $tenant = $stmt->fetch();
        
        if (!$tenant || empty($tenant['phone'])) {
            $this->json(['error' => 'Cliente não encontrado ou não possui WhatsApp cadastrado'], 400);
            return;
        }
        
        // Gera link público
        $publicLink = ProjectContractService::generatePublicLink($contract['contract_token']);
        
        // Prepara mensagem
        $projectName = $contract['project_name'] ?? 'Projeto';
        $contractValue = 'R$ ' . number_format((float) $contract['contract_value'], 2, ',', '.');
        
        $message = "Olá! 👋\n\n";
        $message .= "Temos um contrato para você assinar digitalmente:\n\n";
        $message .= "📋 *Projeto:* {$projectName}\n";
        $message .= "💰 *Valor:* {$contractValue}\n\n";
        $message .= "Por favor, acesse o link abaixo para visualizar e aceitar o contrato:\n\n";
        $message .= $publicLink . "\n\n";
        $message .= "Aguardamos sua confirmação! 😊";
        
        // Normaliza telefone
        $phoneNormalized = preg_replace('/[^0-9]/', '', $tenant['phone']);
        
        // Gera link do WhatsApp
        $whatsappLink = WhatsAppTemplateService::buildWhatsAppLink($phoneNormalized, $message);
        
        // Marca como enviado
        try {
            ProjectContractService::markAsSent($id, $user['id']);
        } catch (\Exception $e) {
            error_log("Erro ao marcar contrato como enviado: " . $e->getMessage());
        }
        
        // Registra no histórico de WhatsApp
        try {
            $logStmt = $db->prepare("
                INSERT INTO whatsapp_generic_logs 
                (tenant_id, template_id, phone, message, sent_at, created_at)
                VALUES (?, NULL, ?, ?, NOW(), NOW())
            ");
            $logStmt->execute([
                $contract['tenant_id'],
                $phoneNormalized,
                $message
            ]);
        } catch (\Exception $logError) {
            // Log erro mas não bloqueia o processo
            error_log("Erro ao registrar log de WhatsApp (contrato {$id}): " . $logError->getMessage());
        }
        
        $this->json([
            'success' => true,
            'message' => 'Link do WhatsApp gerado com sucesso',
            'whatsapp_link' => $whatsappLink,
            'phone' => $tenant['phone'],
            'phone_normalized' => $phoneNormalized,
            'contract_message' => $message,
        ]);
    }
    
    /**
     * Página pública para aceitar contrato
     * 
     * GET /contract/accept?token={token}
     */
    public function acceptPage(): void
    {
        $token = $_GET['token'] ?? '';
        
        // Tenta pegar do path também (caso venha como /contract/accept/TOKEN)
        if (empty($token)) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            // Remove query string
            $path = strtok($path, '?');
            // Remove BASE_PATH se existir
            if (defined('BASE_PATH') && BASE_PATH !== '') {
                $path = str_replace(BASE_PATH, '', $path);
            }
            // Tenta extrair token do path
            if (preg_match('#/contract/accept/([a-f0-9]{32})#i', $path, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (empty($token)) {
            http_response_code(404);
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Contrato não encontrado</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .error { color: #d32f2f; }
                </style>
            </head>
            <body>
                <h1 class='error'>Contrato não encontrado</h1>
                <p>O link do contrato é inválido ou expirou.</p>
            </body>
            </html>";
            exit;
        }
        
        $contract = ProjectContractService::findContractByToken($token);
        
        if (!$contract) {
            http_response_code(404);
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Contrato não encontrado</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .error { color: #d32f2f; }
                </style>
            </head>
            <body>
                <h1 class='error'>Contrato não encontrado</h1>
                <p>O link do contrato é inválido ou expirou.</p>
            </body>
            </html>";
            exit;
        }
        
        // Se já foi aceito, mostra mensagem
        if ($contract['status'] === 'accepted') {
            $this->view('contracts.already_accepted', [
                'contract' => $contract,
            ]);
            return;
        }
        
        // Se foi rejeitado, mostra mensagem
        if ($contract['status'] === 'rejected') {
            $this->view('contracts.rejected', [
                'contract' => $contract,
            ]);
            return;
        }
        
        // Mostra formulário de aceite
        $this->view('contracts.accept', [
            'contract' => $contract,
            'token' => $token,
        ]);
    }
    
    /**
     * Processa aceite do contrato
     * 
     * POST /contract/accept/{token}
     */
    public function accept(): void
    {
        $token = $_POST['token'] ?? '';
        
        if (empty($token)) {
            $this->json(['error' => 'Token do contrato é obrigatório'], 400);
            return;
        }
        
        try {
            // Obtém IP e User Agent
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Registra aceite
            ProjectContractService::acceptContract($token, $ip, $userAgent);
            
            // Busca contrato atualizado
            $contract = ProjectContractService::findContractByToken($token);
            
            $this->view('contracts.accepted', [
                'contract' => $contract,
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Erro</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .error { color: #d32f2f; }
                </style>
            </head>
            <body>
                <h1 class='error'>Erro</h1>
                <p>" . htmlspecialchars($e->getMessage()) . "</p>
            </body>
            </html>";
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao aceitar contrato: " . $e->getMessage());
            http_response_code(500);
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Erro</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .error { color: #d32f2f; }
                </style>
            </head>
            <body>
                <h1 class='error'>Erro</h1>
                <p>Ocorreu um erro ao processar o aceite do contrato. Por favor, tente novamente.</p>
            </body>
            </html>";
            exit;
        }
    }
    
    /**
     * Gera e faz download do contrato em PDF
     * 
     * GET /contracts/download-pdf?id={id}
     */
    public function downloadPdf(): void
    {
        Auth::requireInternal();
        
        $id = !empty($_GET['id']) ? (int) $_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo "ID do contrato é obrigatório";
            exit;
        }
        
        $contract = ProjectContractService::findContract($id);
        
        if (!$contract) {
            http_response_code(404);
            echo "Contrato não encontrado";
            exit;
        }
        
        // Gera PDF usando a biblioteca TCPDF ou similar
        // Por enquanto, vamos usar uma solução simples com HTML para PDF
        try {
            $contractContent = $contract['contract_content'] ?? '';
            
            if (empty($contractContent)) {
                // Se não tem conteúdo, gera novamente
                $contractContent = ProjectContractService::buildContractContent(
                    $contract['project_id'],
                    $contract['tenant_id'],
                    $contract['service_id'],
                    $contract['contract_value']
                );
            }
            
            // Gera HTML otimizado para impressão/PDF
            $this->generatePdfHtml($contract, $contractContent);
        } catch (\Exception $e) {
            error_log("Erro ao gerar PDF do contrato: " . $e->getMessage());
            http_response_code(500);
            echo "Erro ao gerar PDF: " . htmlspecialchars($e->getMessage());
            exit;
        }
    }
    
    /**
     * Gera HTML otimizado para impressão/PDF
     */
    private function generatePdfHtml(array $contract, string $htmlContent): void
    {
        $projectName = htmlspecialchars($contract['project_name'] ?? 'Projeto');
        $filename = 'contrato_' . $contract['id'] . '_' . date('Y-m-d') . '.pdf';
        
        // HTML otimizado para impressão/PDF
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrato - ' . $projectName . '</title>
    <style>
        @media print {
            @page {
                margin: 2cm;
                size: A4;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .print-button {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 8px;
        }
        .print-button button {
            background: #023A8D;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .print-button button:hover {
            background: #022a70;
        }
        h1 {
            color: #023A8D;
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
        }
        h3 {
            color: #023A8D;
            margin-top: 25px;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .contract-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .contract-content {
            text-align: justify;
        }
        .contract-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        img {
            max-width: 100%;
            height: auto;
        }
    </style>
    <script>
        function printPDF() {
            window.print();
        }
        // Auto-print quando carregar (opcional - comentado)
        // window.onload = function() { setTimeout(printPDF, 500); };
    </script>
</head>
<body>
    <div class="print-button no-print">
        <button onclick="printPDF()">🖨️ Imprimir / Salvar como PDF</button>
        <p style="margin-top: 10px; font-size: 14px; color: #666;">Use o botão acima ou pressione Ctrl+P para imprimir/salvar como PDF</p>
    </div>
    ' . $htmlContent . '
</body>
</html>';
        
        // Define headers
        header('Content-Type: text/html; charset=UTF-8');
        
        echo $html;
        exit;
    }
}

