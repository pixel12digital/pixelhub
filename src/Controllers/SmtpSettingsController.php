<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Core\Security;
use PixelHub\Services\SmtpService;
use PixelHub\Core\Auth;

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

        if (!empty($errors)) {
            $this->redirect('/settings/smtp?error=' . urlencode(implode(', ', $errors)));
            return;
        }

        try {
            // TEMP: Sem criptografia para testar
            $encryptedPassword = $smtpPassword;

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

            $this->redirect('/settings/smtp?success=1&message=' . urlencode('Configurações SMTP atualizadas com sucesso!'));
        } catch (\Exception $e) {
            $this->redirect('/settings/smtp?error=' . urlencode('Erro ao salvar configurações: ' . $e->getMessage()));
        }
    }

    /**
     * Testa conexão SMTP enviando email de teste
     */
    public function test(): void
    {
        error_log("SMTP_TEST_DEBUG: Iniciando método test");
        
        try {
            Auth::requireInternal();
            error_log("SMTP_TEST_DEBUG: Auth::requireInternal() OK");
        } catch (\Exception $e) {
            error_log("SMTP_TEST_DEBUG: Auth::requireInternal() falhou: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro de autenticação: ' . $e->getMessage()]);
            return;
        }
        
        $db = DB::getConnection();
        error_log("SMTP_TEST_DEBUG: Conexão com DB OK");
        
        // Busca configurações atuais
        $stmt = $db->query("SELECT * FROM smtp_settings LIMIT 1");
        $settings = $stmt->fetch();
        
        error_log("SMTP_TEST_DEBUG: Configurações SMTP: " . json_encode($settings));
        
        if (!$settings || !$settings['smtp_enabled']) {
            error_log("SMTP_TEST_DEBUG: SMTP não configurado ou desativado");
            $this->json(['success' => false, 'error' => 'SMTP não está configurado ou está desativado']);
            return;
        }
        
        try {
            $smtpService = new SmtpService($settings);
            error_log("SMTP_TEST_DEBUG: SmtpService criado com sucesso");
            
            $user = Auth::user();
            error_log("SMTP_TEST_DEBUG: Auth::user() resultado: " . json_encode($user));
            
            $userEmail = $user['email'] ?? null;
            error_log("SMTP_TEST_DEBUG: Email do usuário: " . ($userEmail ?? 'NULL'));
            
            if (!$userEmail) {
                error_log("SMTP_TEST_DEBUG: Email do usuário não encontrado");
                $this->json(['success' => false, 'error' => 'Email do usuário não encontrado']);
                return;
            }

            error_log("SMTP_TEST_DEBUG: Enviando email de teste para " . $userEmail);
            $result = $smtpService->sendTest($userEmail);
            error_log("SMTP_TEST_DEBUG: Resultado do envio: " . ($result ? 'SUCCESS' : 'FAIL'));
            
            if ($result) {
                $this->json(['success' => true, 'message' => 'Email de teste enviado com sucesso para ' . $userEmail]);
            } else {
                $this->json(['success' => false, 'error' => 'Falha ao enviar email de teste']);
            }
        } catch (\Exception $e) {
            error_log("SMTP_TEST_DEBUG: Exceção no teste: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro no teste: ' . $e->getMessage()]);
        }
    }
}
