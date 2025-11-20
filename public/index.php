<?php

/**
 * Bootstrap principal do Pixel Hub
 */

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Autoload manual simples
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\Router;

// ============================================
// 1. DEFINE BASE_PATH (FONTE ÚNICA DA VERDADE)
// ============================================
// Descobre o diretório base do projeto (subpasta)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

// Ex: /painel.pixel12digital/public ou /
if (!defined('BASE_PATH')) {
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '') {
        define('BASE_PATH', '');
    } else {
        define('BASE_PATH', $scriptDir);
    }
}

// ============================================
// 2. HELPER GLOBAL PARA URLs
// ============================================
if (!function_exists('pixelhub_url')) {
    function pixelhub_url(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

// Carrega variáveis de ambiente
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar configurações: " . $e->getMessage());
}

// Configura exibição de erros baseado no APP_DEBUG
if (Env::isDebug()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Configura timezone
date_default_timezone_set('America/Sao_Paulo');

// Descobre o caminho da requisição
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Define arquivo de log específico do projeto
$logDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = realpath($logDir) . DIRECTORY_SEPARATOR . 'pixelhub.log';
if ($logFile === false) {
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'pixelhub.log';
}

// Função helper para log
if (!function_exists('pixelhub_log')) {
    function pixelhub_log($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
    }
}

pixelhub_log("BASE_PATH definido como: '" . BASE_PATH . "' (scriptDir: '{$scriptDir}')");
error_log("BASE_PATH definido como: '" . BASE_PATH . "' (scriptDir: '{$scriptDir}')");

// Remove o prefixo da pasta do projeto da URI (usa $scriptDir já calculado)
if ($scriptDir !== '' && $scriptDir !== '/') {
    // Se a URI começa com o scriptDir, remove esse prefixo
    if (strpos($uri, $scriptDir) === 0) {
        $path = substr($uri, strlen($scriptDir));
    } else {
        $path = $uri;
    }
} else {
    $path = $uri;
}

// Normaliza o path
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}
if ($path === '') {
    $path = '/';
}

// Debug: log dos valores
error_log("=== Router Debug ===");
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A'));
error_log("scriptDir: " . $scriptDir);
error_log("path calculado: " . $path);
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// Cria router e define rotas
$router = new Router();

// Rotas públicas
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Rota raiz: redireciona para login se não autenticado, senão vai para dashboard
use PixelHub\Core\Auth;

$router->get('/', function () {
    if (Auth::check()) {
        // Se já está logado, manda pra dashboard
        $url = pixelhub_url('/dashboard');
    } else {
        // Senão, manda pro login
        $url = pixelhub_url('/login');
    }
    
    header("Location: {$url}");
    exit;
});

// Rotas protegidas
$router->get('/dashboard', 'DashboardController@index');

// Rotas de tenants (clientes)
$router->get('/tenants', 'TenantsController@index');
$router->get('/tenants/create', 'TenantsController@create');
$router->post('/tenants/store', 'TenantsController@store');
$router->get('/tenants/edit', 'TenantsController@edit');
$router->post('/tenants/update', 'TenantsController@update');
$router->post('/tenants/delete', 'TenantsController@delete');
    $router->get('/tenants/view', 'TenantsController@show');
    $router->post('/tenants/sync-billing', 'TenantsController@syncBilling');

    // Rotas de hospedagem (apenas internos)
$router->get('/hosting', 'HostingController@index');
$router->get('/hosting/create', 'HostingController@create');
$router->post('/hosting/store', 'HostingController@store');
$router->get('/hosting/edit', 'HostingController@edit');
$router->post('/hosting/update', 'HostingController@update');
$router->get('/hosting/view', 'HostingController@view');
$router->get('/hosting/backups', 'HostingBackupController@index');
$router->get('/hosting/backups/logs', 'HostingBackupController@viewLogs');
$router->post('/hosting/backups/upload', 'HostingBackupController@upload');
$router->post('/hosting/backups/chunk-init', 'HostingBackupController@chunkInit');
$router->post('/hosting/backups/chunk-upload', 'HostingBackupController@chunkUpload');
$router->post('/hosting/backups/chunk-complete', 'HostingBackupController@chunkComplete');
$router->get('/hosting/backups/download', 'HostingBackupController@download');
$router->post('/hosting/backups/delete', 'HostingBackupController@delete');

    // Rotas de planos de hospedagem
    $router->get('/hosting-plans', 'HostingPlanController@index');
    $router->get('/hosting-plans/create', 'HostingPlanController@create');
    $router->post('/hosting-plans/store', 'HostingPlanController@store');
    $router->get('/hosting-plans/edit', 'HostingPlanController@edit');
    $router->post('/hosting-plans/update', 'HostingPlanController@update');
    $router->post('/hosting-plans/toggle-status', 'HostingPlanController@toggleStatus');

    // Webhook do Asaas
    $router->post('/webhook/asaas', 'AsaasWebhookController@handle');

    // Rotas de cobranças / WhatsApp
    $router->get('/billing/collections', 'BillingCollectionsController@index');
    $router->get('/billing/overview', 'BillingCollectionsController@overview');
    $router->get('/billing/whatsapp-modal', 'BillingCollectionsController@showWhatsAppModal');
    $router->post('/billing/whatsapp-sent', 'BillingCollectionsController@markWhatsAppSent');
    $router->get('/billing/tenant-reminder', 'BillingCollectionsController@getTenantReminderData');
    $router->post('/billing/tenant-reminder-sent', 'BillingCollectionsController@markTenantReminderSent');
    $router->post('/billing/sync-all-from-asaas', 'BillingCollectionsController@syncAllFromAsaas');
    
    // Rota de Carteira Recorrente
    $router->get('/recurring-contracts', 'RecurringContractsController@index');
    $router->post('/recurring-contracts/update-category', 'RecurringContractsController@updateCategory');
    
    // Rotas de Categorias de Contratos
    $router->get('/billing/service-types', 'BillingServiceTypesController@index');
    $router->get('/billing/service-types/create', 'BillingServiceTypesController@create');
    $router->post('/billing/service-types/store', 'BillingServiceTypesController@store');
    $router->get('/billing/service-types/edit', 'BillingServiceTypesController@edit');
    $router->post('/billing/service-types/update', 'BillingServiceTypesController@update');
    $router->post('/billing/service-types/toggle-status', 'BillingServiceTypesController@toggleStatus');

    // Configurações de Infraestrutura — Provedores de Hospedagem
    $router->get('/settings/hosting-providers', 'HostingProvidersController@index');
    $router->get('/settings/hosting-providers/create', 'HostingProvidersController@create');
    $router->post('/settings/hosting-providers/store', 'HostingProvidersController@store');
    $router->get('/settings/hosting-providers/edit', 'HostingProvidersController@edit');
    $router->post('/settings/hosting-providers/update', 'HostingProvidersController@update');
    $router->post('/settings/hosting-providers/toggle-status', 'HostingProvidersController@toggleStatus');

    // Configurações — Templates de WhatsApp Genéricos
    $router->get('/settings/whatsapp-templates', 'WhatsAppTemplatesController@index');
    $router->get('/settings/whatsapp-templates/create', 'WhatsAppTemplatesController@create');
    $router->post('/settings/whatsapp-templates/store', 'WhatsAppTemplatesController@store');
    $router->get('/settings/whatsapp-templates/edit', 'WhatsAppTemplatesController@edit');
    $router->post('/settings/whatsapp-templates/update', 'WhatsAppTemplatesController@update');
    $router->post('/settings/whatsapp-templates/delete', 'WhatsAppTemplatesController@delete');
    $router->post('/settings/whatsapp-templates/toggle-status', 'WhatsAppTemplatesController@toggleStatus');
    $router->get('/settings/whatsapp-templates/ajax-templates', 'WhatsAppTemplatesController@getTemplatesAjax');
    $router->get('/settings/whatsapp-templates/template-data', 'WhatsAppTemplatesController@getTemplateData');

    // Rotas de acessos e links de infraestrutura (apenas internos)
    $router->get('/owner/shortcuts', 'OwnerShortcutsController@index');
    $router->post('/owner/shortcuts/store', 'OwnerShortcutsController@store');
    $router->post('/owner/shortcuts/update', 'OwnerShortcutsController@update');
    $router->post('/owner/shortcuts/delete', 'OwnerShortcutsController@delete');
    $router->get('/owner/shortcuts/password', 'OwnerShortcutsController@getPassword');
    $router->post('/owner/shortcuts/password', 'OwnerShortcutsController@getPassword');

    // Rotas de Projetos & Tarefas (apenas internos)
    $router->get('/projects', 'ProjectController@index');
    $router->post('/projects/store', 'ProjectController@store');
    $router->post('/projects/update', 'ProjectController@update');
    $router->post('/projects/archive', 'ProjectController@archive');
    
    // Rotas de Quadro Kanban
    $router->get('/projects/board', 'TaskBoardController@board');
    $router->post('/tasks/store', 'TaskBoardController@store');
    $router->post('/tasks/update', 'TaskBoardController@update');
    $router->post('/tasks/move', 'TaskBoardController@move');
    $router->get('/tasks/{id}', 'TaskBoardController@show');
    
    // Rotas de Checklist (AJAX)
    $router->post('/tasks/checklist/add', 'TaskChecklistController@add');
    $router->post('/tasks/checklist/toggle', 'TaskChecklistController@toggle');
    $router->post('/tasks/checklist/update', 'TaskChecklistController@update');
    $router->post('/tasks/checklist/delete', 'TaskChecklistController@delete');

// Resolve a rota
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $router->dispatch($method, $path);
} catch (\Exception $e) {
    error_log("Erro na aplicação: " . $e->getMessage());
    
    if (Env::isDebug()) {
        echo "<h1>Erro</h1>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        http_response_code(500);
        echo "Erro interno do servidor.";
    }
}

