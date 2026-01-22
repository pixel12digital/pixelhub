<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;

/**
 * Controller para gerenciar materiais promocionais do ImobSites
 */
class MarketingController extends Controller
{
    /**
     * Exibe a página de materiais ImobSites
     */
    public function index(): void
    {
        Auth::requireInternal();

        // Carrega o arquivo de configuração de materiais
        $assetsPath = __DIR__ . '/../../config/marketing_assets.php';
        
        if (!file_exists($assetsPath)) {
            error_log("Arquivo de configuração de materiais não encontrado: {$assetsPath}");
            $assets = [
                'documentos' => [],
                'criativos' => [],
                'roteiros' => [],
            ];
        } else {
            $assets = require $assetsPath;
        }

        $documents = $assets['documentos'] ?? [];
        $creatives = $assets['criativos'] ?? [];
        $scripts = $assets['roteiros'] ?? [];

        $this->view('marketing.index', [
            'documents' => $documents,
            'creatives' => $creatives,
            'scripts' => $scripts,
        ]);
    }
}









