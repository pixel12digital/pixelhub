<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Core\Security;
use PixelHub\Services\SmtpService;

/**
 * Controller para gerenciar configurações SMTP
 */
class SmtpSettingsController extends Controller
{
    /**
     * Exibe página de configurações SMTP
     */
    public function index(): void
    {
        $db = DB::getConnection();
        
        // Busca configurações SMTP
        $stmt = $db->query("SELECT * FROM smtp_settings LIMIT 1");
        $smtpSettings = $stmt->fetch() ?: [
            'smtp_enabled' => 0,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_from_name' => 'Pixel12 Digital',
            'smtp_from_email' => 'noreply@pixel12digital.com.br',
        ];

        // Descriptografa senha para exibição (se houver)
        if (!empty($smtpSettings['smtp_password'])) {
            $smtpSettings['smtp_password'] = Security::decrypt($smtpSettings['smtp_password']);
        }

        $this->view('settings.smtp', [
            'smtpSettings' => $smtpSettings,
        ]);
    }

    /**
     * Atualiza configurações SMTP
     */
    public function update(): void
    {
        error_log("SMTP_DEBUG: Iniciando método update");
        $db = DB::getConnection();
        
        // Validação básica
        $smtpEnabled = isset($_POST['smtp_enabled']) ? 1 : 0;
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = (int) ($_POST['smtp_port'] ?? 587);
        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        $smtpPassword = trim($_POST['smtp_password'] ?? '');
        $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtpFromName = trim($_POST['smtp_from_name'] ?? 'Pixel12 Digital');
        $smtpFromEmail = trim($_POST['smtp_from_email'] ?? 'noreply@pixel12digital.com.br');

        // Validações
        $errors = [];
        
        if ($smtpEnabled) {
            if (empty($smtpHost)) {
                $errors[] = 'O host SMTP é obrigatório quando o envio está ativado';
            }
            if (empty($smtpUsername)) {
                $errors[] = 'O usuário SMTP é obrigatório quando o envio está ativado';
            }
            if (empty($smtpPassword)) {
                $errors[] = 'A senha SMTP é obrigatória quando o envio está ativado';
            }
            if (!in_array($smtpEncryption, ['tls', 'ssl', 'none'])) {
                $errors[] = 'Tipo de criptografia inválido';
            }
            if ($smtpPort < 1 || $smtpPort > 65535) {
                $errors[] = 'Porta SMTP inválida';
            }
            if (!filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email do remetente inválido';
            }
        }

        error_log("SMTP_DEBUG: Validações concluídas, errors=" . json_encode($errors));
        if (!empty($errors)) {
            error_log("SMTP_DEBUG: Redirecionando com erros");
            $this->redirect('/settings/smtp?error=' . urlencode(implode(', ', $errors)));
            return;
        }

        error_log("SMTP_DEBUG: Iniciando try block");
        try {
            // TEMP: Desabilitar criptografia para debug
            error_log("SMTP_DEBUG: Antes de criptografar senha");
            $encryptedPassword = $smtpPassword; // Sem criptografia temporariamente

            // Atualiza configurações
            $stmt = $db->prepare("
                UPDATE smtp_settings SET
                    smtp_enabled = ?,
                    smtp_host = ?,
                    smtp_port = ?,
                    smtp_username = ?,
                    smtp_password = ?,
                    smtp_encryption = ?,
                    smtp_from_name = ?,
                    smtp_from_email = ?,
                    updated_at = NOW()
                WHERE id = (SELECT id FROM (SELECT id FROM smtp_settings LIMIT 1) AS sub)
            ");
            
            $stmt->execute([
                $smtpEnabled,
                $smtpHost,
                $smtpPort,
                $smtpUsername,
                $encryptedPassword,
                $smtpEncryption,
                $smtpFromName,
                $smtpFromEmail,
            ]);

            // Se não existia registro, insere
            if ($stmt->rowCount() === 0) {
                $stmt = $db->prepare("
                    INSERT INTO smtp_settings (
                        smtp_enabled, smtp_host, smtp_port, smtp_username,
                        smtp_password, smtp_encryption, smtp_from_name, smtp_from_email
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $smtpEnabled,
                    $smtpHost,
                    $smtpPort,
                    $smtpUsername,
                    $encryptedPassword,
                    $smtpEncryption,
                    $smtpFromName,
                    $smtpFromEmail,
                ]);
            }

            error_log("SMTP_DEBUG: Redirecionando com sucesso");
            $this->redirect('/settings/smtp?success=1&message=' . urlencode('Configurações SMTP atualizadas com sucesso!'));
        } catch (\Exception $e) {
            error_log("SMTP_DEBUG: Exceção capturada: " . $e->getMessage());
            $this->redirect('/settings/smtp?error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
        }
    }

    /**
     * Testa conexão SMTP enviando email de teste
     */
    public function test(): void
    {
        $db = DB::getConnection();
        
        // Busca configurações atuais
        $stmt = $db->query("SELECT * FROM smtp_settings LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings || !$settings['smtp_enabled']) {
            $this->json(['success' => false, 'error' => 'SMTP não está configurado ou está desativado']);
            return;
        }

        // Descriptografa senha
        $settings['smtp_password'] = Security::decrypt($settings['smtp_password']);
        
        try {
            $smtpService = new SmtpService($settings);
            $userEmail = $_SESSION['user']['email'] ?? null;
            
            if (!$userEmail) {
                $this->json(['success' => false, 'error' => 'Usuário não autenticado']);
                return;
            }

            $result = $smtpService->sendTest($userEmail);
            
            if ($result) {
                $this->json(['success' => true, 'message' => 'Email de teste enviado com sucesso para ' . $userEmail]);
            } else {
                $this->json(['success' => false, 'error' => 'Falha ao enviar email de teste']);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro no teste: ' . $e->getMessage()]);
        }
    }
}
