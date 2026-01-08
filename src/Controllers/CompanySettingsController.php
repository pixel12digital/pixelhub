<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\CompanySettingsService;

/**
 * Controller para gerenciar configurações da empresa
 */
class CompanySettingsController extends Controller
{
    /**
     * Exibe formulário de configurações da empresa
     */
    public function index(): void
    {
        Auth::requireInternal();

        $settings = CompanySettingsService::getSettings();
        
        // Se não existe, cria registro padrão
        if (!$settings) {
            try {
                CompanySettingsService::updateSettings([
                    'company_name' => 'Pixel12 Digital',
                ]);
                $settings = CompanySettingsService::getSettings();
            } catch (\Exception $e) {
                error_log("Erro ao criar configurações padrão da empresa: " . $e->getMessage());
            }
        }

        $this->view('company_settings.form', [
            'settings' => $settings,
        ]);
    }

    /**
     * Atualiza configurações da empresa
     */
    public function update(): void
    {
        Auth::requireInternal();

        $settings = CompanySettingsService::getSettings();

        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_name_fantasy' => trim($_POST['company_name_fantasy'] ?? '') ?: null,
            'cnpj' => trim($_POST['cnpj'] ?? '') ?: null,
            'ie' => trim($_POST['ie'] ?? '') ?: null,
            'im' => trim($_POST['im'] ?? '') ?: null,
            'address_street' => trim($_POST['address_street'] ?? '') ?: null,
            'address_number' => trim($_POST['address_number'] ?? '') ?: null,
            'address_complement' => trim($_POST['address_complement'] ?? '') ?: null,
            'address_neighborhood' => trim($_POST['address_neighborhood'] ?? '') ?: null,
            'address_city' => trim($_POST['address_city'] ?? '') ?: null,
            'address_state' => trim($_POST['address_state'] ?? '') ?: null,
            'address_cep' => trim($_POST['address_cep'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'website' => trim($_POST['website'] ?? '') ?: null,
            'logo_url' => trim($_POST['logo_url'] ?? '') ?: null,
        ];

        // Processa upload de logo se houver
        if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (in_array($extension, $allowedExtensions)) {
                $fileName = 'logo_' . time() . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $filePath)) {
                    // Remove logo antigo se existir
                    if (!empty($settings['logo_path']) && strpos($settings['logo_path'], '/uploads/logos/') === 0) {
                        $oldLogoPath = __DIR__ . '/../../public' . $settings['logo_path'];
                        if (file_exists($oldLogoPath)) {
                            @unlink($oldLogoPath);
                        }
                    }
                    $data['logo_path'] = '/uploads/logos/' . $fileName;
                }
            }
        }

        try {
            CompanySettingsService::updateSettings($data);
            $this->redirect('/settings/company?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar configurações da empresa: " . $e->getMessage());
            $this->redirect('/settings/company?error=' . urlencode($e->getMessage()));
        }
    }
}

