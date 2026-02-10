<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Auth;
use PixelHub\Core\Controller;
use PixelHub\Services\BillingTemplateRegistry;

/**
 * Controller para visualização de templates de cobrança
 * 
 * Fase 1: Read-only - apenas visualização sem edição
 */
class BillingTemplatesController extends Controller
{
    /**
     * Lista todos os templates
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $channel = $_GET['channel'] ?? 'all';
        $search = $_GET['search'] ?? '';
        
        $templates = BillingTemplateRegistry::getAllTemplates();
        
        // Filtrar por canal
        if ($channel !== 'all') {
            $templates = array_filter($templates, function($t) use ($channel) {
                return strtolower($t['channel']) === strtolower($channel);
            });
        }
        
        // Filtrar por busca
        if (!empty($search)) {
            $templates = array_filter($templates, function($t) use ($search) {
                return stripos($t['label'], $search) !== false || 
                       stripos($t['stage'], $search) !== false;
            });
        }
        
        $this->view('billing_templates/index', [
            'templates' => $templates,
            'channel' => $channel,
            'search' => $search,
            'placeholders' => BillingTemplateRegistry::getPlaceholders()
        ]);
    }
    
    /**
     * API para obter detalhes de um template
     */
    public function show(): void
    {
        Auth::requireInternal();
        
        $key = $_GET['key'] ?? '';
        
        if (empty($key)) {
            $this->json(['success' => false, 'error' => 'Template não informado']);
            return;
        }
        
        $template = BillingTemplateRegistry::getTemplate($key);
        
        if (!$template) {
            $this->json(['success' => false, 'error' => 'Template não encontrado']);
            return;
        }
        
        $this->json([
            'success' => true,
            'template' => $template
        ]);
    }
}
