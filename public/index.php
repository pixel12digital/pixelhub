<?php

/**
 * Bootstrap principal do Pixel Hub
 */

// Diagnóstico: agenda-trace força exibição de erros
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'agenda-trace') !== false) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// IMPORTANTE: Aumenta limites para rotas de envio de mídia ANTES de qualquer parsing
// Isso previne falhas silenciosas ao processar POST bodies grandes (áudio/imagem base64)
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'communication-hub/send') !== false) {
    ini_set('memory_limit', '256M');
    ini_set('post_max_size', '64M');
    ini_set('upload_max_filesize', '64M');
    ini_set('max_execution_time', '120');
    ini_set('max_input_time', '120');
}

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
use PixelHub\Core\Security;

// ============================================
// 1. DEFINE BASE_PATH (FONTE ÚNICA DA VERDADE)
// ============================================
// Descobre o diretório base do projeto (subpasta)
// IMPORTANTE: Quando acessamos um arquivo que existe (ex: debug-share.php),
// o SCRIPT_NAME é o próprio arquivo, não o index.php. Por isso, precisamos
// sempre usar o diretório do index.php como referência.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

// Remove '/public' do scriptDir se estiver presente
// Isso garante que BASE_PATH seja /painel.pixel12digital e não /painel.pixel12digital/public
if (substr($scriptDir, -7) === '/public') {
    $scriptDir = substr($scriptDir, 0, -7);
}

// Se o SCRIPT_NAME não é index.php, assume que estamos em um subdiretório
// e calcula o BASE_PATH a partir do diretório do index.php
if (basename($scriptName) !== 'index.php' && strpos($scriptName, '/index.php') === false) {
    // Estamos em um arquivo que não é index.php, então o BASE_PATH
    // deve ser calculado a partir do diretório public/
    // Remove o caminho do arquivo atual e adiciona o caminho até public/
    $currentDir = dirname($scriptName);
    // Remove '/public' se estiver presente
    if (substr($currentDir, -7) === '/public') {
        $currentDir = substr($currentDir, 0, -7);
    }
    $scriptDir = $currentDir;
}

// Ex: /painel.pixel12digital ou /
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

// ============================================
// 3. HELPER GLOBAL PARA ESCAPE XSS
// ============================================
if (!function_exists('e')) {
    /**
     * Escape HTML para prevenir XSS
     * Alias para htmlspecialchars com configurações seguras
     */
    function e(string $string, int $flags = ENT_QUOTES, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars($string, $flags, $encoding);
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
    error_reporting(E_ALL); // Sempre reporta erros, mas não exibe em produção
}
// Diagnóstico agenda-trace: força exibir erros
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'agenda-trace') !== false) {
    ini_set('display_errors', '1');
}

// Configura timezone
date_default_timezone_set('America/Sao_Paulo');

// Aplica headers de segurança (apenas se não estiver em debug ou se for produção)
if (!Env::isDebug() || Env::get('APP_ENV') === 'production') {
    Security::setSecurityHeaders();
}

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

// CORREÇÃO CRÍTICA: O path deve ser calculado SEMPRE a partir da URI original
// Não importa se o arquivo existe ou não, sempre usamos REQUEST_URI
// O problema anterior era usar SCRIPT_NAME que muda quando o arquivo existe

// ATALHO PRIMEIRO: Se for /screen-recordings/share, usa diretamente sem calcular
// Isso garante que funcione independente de problemas com BASE_PATH
if (strpos($uri, '/screen-recordings/share') !== false) {
    // Extrai apenas a parte do path (sem query string)
    $path = parse_url($uri, PHP_URL_PATH);
    // Remove BASE_PATH se estiver presente
    if (defined('BASE_PATH') && BASE_PATH !== '' && BASE_PATH !== '/' && strpos($path, BASE_PATH) === 0) {
        $path = substr($path, strlen(BASE_PATH));
    }
    // Garante que começa com /
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
} else {
    // Para outras rotas, calcula normalmente
    // Remove o prefixo BASE_PATH da URI se estiver presente
    $path = $uri;
    if (defined('BASE_PATH') && BASE_PATH !== '' && BASE_PATH !== '/') {
        // Se a URI começa com BASE_PATH, remove esse prefixo
        if (strpos($uri, BASE_PATH) === 0) {
            $path = substr($uri, strlen(BASE_PATH));
            // Garante que começa com /
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
        }
    }

    // Se ainda não funcionou (BASE_PATH não estava na URI), tenta com scriptDir
    // Mas só se o path ainda for igual à URI original
    if ($path === $uri && $scriptDir !== '' && $scriptDir !== '/') {
        // Se a URI começa com o scriptDir, remove esse prefixo
        if (strpos($uri, $scriptDir) === 0) {
            $path = substr($uri, strlen($scriptDir));
            // Garante que começa com /
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
        }
    }

    // Se ainda não funcionou, tenta remover /public se estiver no início
    if ($path === $uri || strpos($path, '/public/') === 0) {
        if (strpos($path, '/public/') === 0) {
            $path = substr($path, 7); // Remove '/public'
            // Garante que começa com /
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
        }
    }

    // Se ainda não funcionou, usa a URI diretamente (sem remover nada)
    if ($path === $uri && ($path === '' || $path[0] !== '/')) {
        $path = '/' . $path;
    }
}

// Log para debug
pixelhub_log("Path calculado - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . ", URI: {$uri}, BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : 'N/A') . ", scriptDir: {$scriptDir}, SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . ", path final: {$path}");
error_log("Path calculado - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . ", URI: {$uri}, BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : 'N/A') . ", scriptDir: {$scriptDir}, SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . ", path final: {$path}");

// Normaliza o path (remove barras duplicadas e barra final)
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}
if ($path === '') {
    $path = '/';
}
// Remove barra final para normalizar (Router também faz isso, mas fazemos aqui para garantir)
$path = rtrim($path, '/') ?: '/';

// ATALHO: Se for /screen-recordings/share, inclui diretamente sem passar pelo router
// Isso garante que funcione mesmo se houver problemas com o router
// Verifica tanto o path calculado quanto a URI original (para casos onde o path está errado)
$isShareRoute = ($path === '/screen-recordings/share' || strpos($path, '/screen-recordings/share?') === 0) ||
                (strpos($uri, '/screen-recordings/share') !== false && strpos($uri, '/screen-recordings/debug-share.php') === false);

// Log sempre para debug
pixelhub_log('[Bypass Check] URI: ' . $uri . ', Path: ' . $path . ', isShareRoute: ' . ($isShareRoute ? 'SIM' : 'NÃO'));
error_log('[Bypass Check] URI: ' . $uri . ', Path: ' . $path . ', isShareRoute: ' . ($isShareRoute ? 'SIM' : 'NÃO'));

if ($isShareRoute) {
    $shareFile = __DIR__ . '/screen-recordings/share.php';
    pixelhub_log('[Direct Share] Tentando acessar share.php - Arquivo: ' . $shareFile);
    error_log('[Direct Share] Tentando acessar share.php - Arquivo: ' . $shareFile);
    pixelhub_log('[Direct Share] Arquivo existe: ' . (file_exists($shareFile) ? 'SIM' : 'NÃO'));
    error_log('[Direct Share] Arquivo existe: ' . (file_exists($shareFile) ? 'SIM' : 'NÃO'));
    
    if (file_exists($shareFile)) {
        pixelhub_log('[Direct Share] Acessando share.php diretamente (bypass router)');
        pixelhub_log('[Direct Share] URI: ' . $uri . ', Path: ' . $path);
        pixelhub_log('[Direct Share] $_GET antes do require: ' . json_encode($_GET));
        error_log('[Direct Share] Acessando share.php diretamente (bypass router)');
        error_log('[Direct Share] URI: ' . $uri . ', Path: ' . $path);
        error_log('[Direct Share] $_GET antes do require: ' . json_encode($_GET));
        
        // Tenta incluir o arquivo com tratamento de erro
        try {
            // Força flush dos logs antes de incluir
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[Direct Share] Antes de incluir share.php - Forçando flush');
            }
            error_log('[Direct Share] Antes de incluir share.php - Forçando flush');
            flush();
            
            // Limpa qualquer output buffer antes
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Log imediato antes do include
            error_log('[Direct Share] EXECUTANDO include agora...');
            
            // Verifica se o arquivo pode ser lido
            if (!is_readable($shareFile)) {
                throw new \RuntimeException('Arquivo share.php não é legível: ' . $shareFile);
            }
            
            // Inclui o arquivo - usa require para garantir que pare se houver erro
            require $shareFile;
            
            // Se chegou aqui, o arquivo foi incluído mas não fez exit
            pixelhub_log('[Direct Share] AVISO: share.php foi incluído mas não fez exit');
            error_log('[Direct Share] AVISO: share.php foi incluído mas não fez exit');
            exit;
        } catch (\Throwable $e) {
            pixelhub_log('[Direct Share] ERRO ao incluir share.php: ' . $e->getMessage());
            pixelhub_log('[Direct Share] Stack trace: ' . $e->getTraceAsString());
            error_log('[Direct Share] ERRO ao incluir share.php: ' . $e->getMessage());
            error_log('[Direct Share] Stack trace: ' . $e->getTraceAsString());
            http_response_code(500);
            echo 'Erro ao carregar página de compartilhamento: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    } else {
        pixelhub_log('[Direct Share] ERRO: Arquivo share.php não encontrado em: ' . $shareFile);
        error_log('[Direct Share] ERRO: Arquivo share.php não encontrado em: ' . $shareFile);
        http_response_code(404);
        echo 'Arquivo share.php não encontrado: ' . $shareFile;
        exit;
    }
}

// Debug: log dos valores
error_log("=== Router Debug ===");
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A'));
error_log("scriptDir: " . $scriptDir);
error_log("path calculado: " . $path);
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// LOG ESPECIAL: Captura TODAS as requisições POST (para debug)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    error_log("=== 🔍 POST REQUEST DETECTADO ===");
    error_log("Path: {$path}");
    error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
    error_log("POST data: " . json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
    error_log("Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A'));
    
    // Log especial para communication-hub/send
    if (strpos($path, 'communication-hub/send') !== false) {
        error_log("=== 🔍🔍 POST /communication-hub/send ESPECÍFICO DETECTADO 🔍🔍 ===");
    }
}

// Cria router e define rotas
$router = new Router();

    // Rotas públicas
    $router->get('/login', 'AuthController@loginForm');
    $router->post('/login', 'AuthController@login');
    $router->get('/logout', 'AuthController@logout');
    
    // Rotas públicas de contratos (não requerem autenticação)
    // Aceita tanto /contract/accept?token=XXX quanto /contract/accept/XXX
    $router->get('/contract/accept', 'ProjectContractController@acceptPage');
    $router->get('/contract/accept/*', 'ProjectContractController@acceptPage');
    $router->post('/contract/accept', 'ProjectContractController@accept');
    
    // Rotas públicas para cliente preencher pedido de serviço
    $router->get('/client-portal/orders/*', 'ServiceOrderController@publicForm');
    $router->post('/client-portal/orders/save-client-data', 'ServiceOrderController@saveClientData');
    $router->post('/client-portal/orders/save-briefing', 'ServiceOrderController@saveBriefing');
    $router->post('/client-portal/orders/approve', 'ServiceOrderController@approve');
    $router->post('/client-portal/orders/start-generation', 'ServiceOrderController@startGeneration');
    $router->post('/client-portal/orders/lookup-existing-client', 'ServiceOrderController@lookupExistingClient');
    $router->post('/client-portal/orders/ai-orchestrate', 'AIOrchestratorController@processMessage');
    
    // Rotas públicas para chat vinculado a pedidos
    $router->get('/chat/order', 'ChatController@show');
    $router->post('/chat/message', 'ChatController@sendMessage');
    $router->get('/chat/messages', 'ChatController@getMessages');

// Rota pública para compartilhar gravações (não requer autenticação)
$router->get('/screen-recordings/share', function() {
    pixelhub_log('[Share Route] Rota /screen-recordings/share foi chamada!');
    error_log('[Share Route] Rota /screen-recordings/share foi chamada!');
    
    // Inclui o arquivo share.php diretamente
    $shareFile = __DIR__ . '/screen-recordings/share.php';
    pixelhub_log('[Share Route] Tentando incluir arquivo: ' . $shareFile);
    error_log('[Share Route] Tentando incluir arquivo: ' . $shareFile);
    pixelhub_log('[Share Route] Arquivo existe: ' . (file_exists($shareFile) ? 'SIM' : 'NÃO'));
    error_log('[Share Route] Arquivo existe: ' . (file_exists($shareFile) ? 'SIM' : 'NÃO'));
    
    if (file_exists($shareFile)) {
        pixelhub_log('[Share Route] Incluindo arquivo share.php...');
        error_log('[Share Route] Incluindo arquivo share.php...');
        require $shareFile;
    } else {
        pixelhub_log('[Share Route] ERRO: Arquivo share.php não encontrado em: ' . $shareFile);
        error_log('[Share Route] ERRO: Arquivo share.php não encontrado em: ' . $shareFile);
        http_response_code(404);
        echo 'Arquivo não encontrado: ' . $shareFile;
    }
    exit;
});
pixelhub_log('[Router Setup] Rota /screen-recordings/share registrada');
error_log('[Router Setup] Rota /screen-recordings/share registrada');

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
    $router->post('/tenants/check-asaas', 'TenantsController@checkAsaas');
$router->get('/tenants/edit', 'TenantsController@edit');
$router->post('/tenants/update', 'TenantsController@update');
$router->post('/tenants/delete', 'TenantsController@delete');
$router->post('/tenants/archive', 'TenantsController@archive');
    $router->get('/tenants/view', 'TenantsController@show');
    $router->get('/tenants/whatsapp-history', 'TenantsController@whatsappHistory');
    $router->post('/tenants/sync-billing', 'TenantsController@syncBilling');
    $router->post('/tenants/sync-asaas-data', 'TenantsController@syncAsaasData');
    $router->post('/tenants/update-asaas-fields', 'TenantsController@updateAsaasFields');
    $router->post('/tenants/whatsapp-generic-log', 'TenantsController@logGenericWhatsApp');
    $router->get('/tenants/whatsapp-timeline-ajax', 'TenantsController@getWhatsAppTimelineAjax');
    $router->get('/tenants/search-ajax', 'TenantsController@searchAjax');

    // Rotas de documentos gerais de tenants (apenas internos)
    $router->post('/tenants/documents/upload', 'TenantDocumentsController@upload');
    $router->get('/tenants/documents/download', 'TenantDocumentsController@download');
    $router->post('/tenants/documents/delete', 'TenantDocumentsController@delete');

    // Rotas de hospedagem (apenas internos)
$router->get('/hosting', 'HostingController@index');
$router->get('/hosting/create', 'HostingController@create');
$router->post('/hosting/store', 'HostingController@store');
$router->get('/hosting/edit', 'HostingController@edit');
$router->post('/hosting/update', 'HostingController@update');
$router->get('/hosting/view', 'HostingController@show');
$router->get('/hosting/backups', 'HostingBackupController@index');
$router->get('/hosting/backups/logs', 'HostingBackupController@viewLogs');
$router->post('/hosting/backups/upload', 'HostingBackupController@upload');
$router->post('/hosting/backups/chunk-init', 'HostingBackupController@chunkInit');
$router->post('/hosting/backups/chunk-upload', 'HostingBackupController@chunkUpload');
$router->post('/hosting/backups/chunk-complete', 'HostingBackupController@chunkComplete');
$router->get('/hosting/backups/download', 'HostingBackupController@download');
$router->post('/hosting/backups/delete', 'HostingBackupController@delete');

    // Rotas de contas de email (apenas internos)
    $router->get('/email-accounts', 'EmailAccountController@index');
    $router->get('/email-accounts/create', 'EmailAccountController@create');
    $router->post('/email-accounts/store', 'EmailAccountController@store');
    $router->get('/email-accounts/edit', 'EmailAccountController@edit');
    $router->post('/email-accounts/update', 'EmailAccountController@update');
    $router->post('/email-accounts/delete', 'EmailAccountController@delete');
    $router->get('/email-accounts/duplicate', 'EmailAccountController@duplicate');
    $router->get('/email-accounts/password', 'EmailAccountController@getPassword');
    $router->post('/email-accounts/password', 'EmailAccountController@getPassword');

    // Rotas de planos de hospedagem
    $router->get('/hosting-plans', 'HostingPlanController@index');
    $router->get('/hosting-plans/create', 'HostingPlanController@create');
    $router->post('/hosting-plans/store', 'HostingPlanController@store');
    $router->get('/hosting-plans/edit', 'HostingPlanController@edit');
    $router->post('/hosting-plans/update', 'HostingPlanController@update');
    $router->post('/hosting-plans/toggle-status', 'HostingPlanController@toggleStatus');
    $router->post('/hosting-plans/delete', 'HostingPlanController@delete');

    // Rotas de tipos de serviço para planos
    $router->get('/settings/plan-service-types', 'PlanServiceTypeController@index');
    $router->post('/settings/plan-service-types/store', 'PlanServiceTypeController@store');
    $router->post('/settings/plan-service-types/update', 'PlanServiceTypeController@update');
    $router->post('/settings/plan-service-types/toggle-status', 'PlanServiceTypeController@toggleStatus');
    $router->post('/settings/plan-service-types/delete', 'PlanServiceTypeController@delete');

    // Webhook do Asaas
    $router->post('/webhook/asaas', 'AsaasWebhookController@handle');
    
    // Webhook do WhatsApp Gateway
    $router->post('/api/whatsapp/webhook', 'WhatsAppWebhookController@handle');
    
    // API de Eventos (para sistemas internos emitirem eventos)
    $router->post('/api/events', 'EventIngestionController@handle');

    // Rotas de cobranças / WhatsApp
    $router->get('/billing/collections', 'BillingCollectionsController@index');
    $router->get('/billing/overview', 'BillingCollectionsController@overview');
    $router->get('/billing/whatsapp-modal', 'BillingCollectionsController@showWhatsAppModal');
    $router->post('/billing/whatsapp-sent', 'BillingCollectionsController@markWhatsAppSent');
    $router->get('/billing/tenant-reminder', 'BillingCollectionsController@getTenantReminderData');
    $router->post('/billing/tenant-reminder-sent', 'BillingCollectionsController@markTenantReminderSent');
    $router->post('/billing/sync-all-from-asaas', 'BillingCollectionsController@syncAllFromAsaas');
    $router->get('/billing/sync-errors', 'BillingCollectionsController@viewSyncErrors');
    $router->post('/billing/send-via-inbox', 'BillingCollectionsController@sendViaInbox');
    $router->post('/billing/update-auto-settings', 'BillingCollectionsController@updateAutoSettings');
    $router->get('/billing/notifications-log', 'BillingCollectionsController@notificationsLog');
    $router->get('/billing/failure-count', 'BillingCollectionsController@failureCount');
    $router->post('/billing/send-manual', 'BillingCollectionsController@sendManual');
    $router->get('/billing/get-last-dispatch', 'BillingCollectionsController@getLastDispatch');
    $router->get('/billing/preview-message', 'BillingCollectionsController@previewMessage');
    
    // Rotas de Templates de Cobrança
    $router->get('/billing/templates', 'BillingTemplatesController@index');
    $router->get('/billing/templates/view', 'BillingTemplatesController@show');
    
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

    // Rotas de Catálogo de Serviços (Pontuais)
    $router->get('/services', 'ServicesController@index');
    $router->get('/services/create', 'ServicesController@create');
    $router->post('/services/store', 'ServicesController@store');
    $router->get('/services/edit', 'ServicesController@edit');
    $router->post('/services/update', 'ServicesController@update');
    $router->post('/services/toggle-status', 'ServicesController@toggleStatus');
    
    // Rotas de pedidos de serviço (service_orders) - Interno
    $router->get('/service-orders', 'ServiceOrderController@index');
    $router->get('/service-orders/create', 'ServiceOrderController@create');
    $router->post('/service-orders/store', 'ServiceOrderController@store');
    $router->get('/service-orders/view', 'ServiceOrderController@show');
    $router->post('/service-orders/delete', 'ServiceOrderController@delete');

    // Rotas do Assistente de Cadastramento (Wizard)
    $router->get('/wizard/new-project', 'WizardController@newProject');
    $router->post('/wizard/create-project', 'WizardController@createProject');
    $router->post('/wizard/suggest-project-name', 'WizardController@suggestProjectName');
    $router->post('/wizard/preview-contract', 'WizardController@previewContract');

    // Rotas de Configurações de IA
    $router->get('/settings/ai', 'AISettingsController@index');
    $router->post('/settings/ai', 'AISettingsController@update');
    $router->post('/settings/ai/test', 'AISettingsController@testConnection');

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
    $router->get('/settings/whatsapp-templates/quick-replies', 'WhatsAppTemplatesController@getQuickReplies');
    $router->get('/settings/whatsapp-templates/template-data', 'WhatsAppTemplatesController@getTemplateData');
    $router->get('/settings/whatsapp-templates/categories', 'WhatsAppTemplatesController@categories');
    $router->post('/settings/whatsapp-templates/categories/store', 'WhatsAppTemplatesController@storeCategory');
    $router->post('/settings/whatsapp-templates/categories/update', 'WhatsAppTemplatesController@updateCategory');
    $router->post('/settings/whatsapp-templates/categories/delete', 'WhatsAppTemplatesController@deleteCategory');
    $router->get('/settings/whatsapp-templates/categories/ajax', 'WhatsAppTemplatesController@getCategoriesAjax');
    $router->post('/settings/whatsapp-templates/categories/reorder', 'WhatsAppTemplatesController@reorderCategories');

    // Rotas de Configurações - Cláusulas de Contrato
    $router->get('/settings/contract-clauses', 'ContractClausesController@index');
    $router->get('/settings/contract-clauses/create', 'ContractClausesController@create');
    $router->post('/settings/contract-clauses/store', 'ContractClausesController@store');
    $router->get('/settings/contract-clauses/edit', 'ContractClausesController@edit');
    $router->post('/settings/contract-clauses/update', 'ContractClausesController@update');
    $router->post('/settings/contract-clauses/delete', 'ContractClausesController@delete');

    // Rotas de Configurações - Tipos de Atividades (Atividade avulsa)
    $router->get('/settings/activity-types', 'ActivityTypesController@index');
    $router->get('/settings/activity-types/create', 'ActivityTypesController@create');
    $router->post('/settings/activity-types/store', 'ActivityTypesController@store');
    $router->get('/settings/activity-types/edit', 'ActivityTypesController@edit');
    $router->post('/settings/activity-types/update', 'ActivityTypesController@update');
    $router->post('/settings/activity-types/delete', 'ActivityTypesController@delete');
    $router->post('/settings/activity-types/restore', 'ActivityTypesController@restore');

    // Rotas de Configurações - Tipos de Blocos de Agenda
    $router->get('/settings/agenda-block-types', 'AgendaBlockTypesController@index');
    $router->get('/settings/agenda-block-types/create', 'AgendaBlockTypesController@create');
    $router->post('/settings/agenda-block-types/store', 'AgendaBlockTypesController@store');
    $router->get('/settings/agenda-block-types/edit', 'AgendaBlockTypesController@edit');
    $router->post('/settings/agenda-block-types/update', 'AgendaBlockTypesController@update');
    $router->post('/settings/agenda-block-types/delete', 'AgendaBlockTypesController@delete');
    $router->post('/settings/agenda-block-types/restore', 'AgendaBlockTypesController@restore');
    $router->post('/settings/agenda-block-types/hard-delete', 'AgendaBlockTypesController@hardDelete');

    // Rotas de Configurações - Modelos de Blocos de Agenda
    $router->get('/settings/agenda-block-templates', 'AgendaBlockTemplatesController@index');
    $router->get('/settings/agenda-block-templates/create', 'AgendaBlockTemplatesController@create');
    $router->post('/settings/agenda-block-templates/store', 'AgendaBlockTemplatesController@store');
    $router->get('/settings/agenda-block-templates/edit', 'AgendaBlockTemplatesController@edit');
    $router->post('/settings/agenda-block-templates/update', 'AgendaBlockTemplatesController@update');
    $router->post('/settings/agenda-block-templates/delete', 'AgendaBlockTemplatesController@delete');

    // Rotas de Configurações - Dados da Empresa
    $router->get('/settings/company', 'CompanySettingsController@index');
    $router->post('/settings/company', 'CompanySettingsController@update');

    // Rotas de Diagnóstico
    $router->get('/diagnostic/financial', 'DiagnosticController@financial');
    $router->get('/diagnostic/financial/errors', 'DiagnosticController@getErrorsJson');
    $router->get('/diagnostic/communication', 'DiagnosticController@communication');
    $router->post('/diagnostic/communication/run', 'DiagnosticController@runCommunicationDiagnostic');

    // Rotas de Configurações do Asaas
    $router->get('/settings/asaas', 'AsaasSettingsController@index');
    $router->post('/settings/asaas', 'AsaasSettingsController@update');
    $router->post('/settings/asaas/test', 'AsaasSettingsController@testConnection');
    
    // Configurações SMTP
    $router->get('/settings/smtp', 'SmtpSettingsController@index');
    $router->post('/settings/smtp', 'SmtpSettingsController@update');
    $router->post('/settings/smtp/test', 'SmtpSettingsController@test');
    
    // Configurações do WhatsApp Gateway
    $router->get('/settings/whatsapp-gateway', 'WhatsAppGatewaySettingsController@index');
    $router->post('/settings/whatsapp-gateway', 'WhatsAppGatewaySettingsController@update');
    $router->post('/settings/whatsapp-gateway/test-connection', 'WhatsAppGatewaySettingsController@testConnection');
    $router->get('/settings/whatsapp-gateway/check', 'WhatsAppGatewaySettingsController@checkProduction');
    // Sessões WhatsApp (listar, criar, reconectar)
    $router->get('/settings/whatsapp-gateway/sessions', 'WhatsAppGatewaySettingsController@sessionsList');
    $router->post('/settings/whatsapp-gateway/sessions/create', 'WhatsAppGatewaySettingsController@sessionsCreate');
    $router->post('/settings/whatsapp-gateway/sessions/reconnect', 'WhatsAppGatewaySettingsController@sessionsReconnect');
    $router->post('/settings/whatsapp-gateway/sessions/disconnect', 'WhatsAppGatewaySettingsController@sessionsDisconnect');

    // IA Assistente de Respostas
    $router->get('/api/ai/contexts', 'AISuggestController@contexts');
    $router->post('/api/ai/suggest-reply', 'AISuggestController@suggestReply');
    $router->post('/api/ai/learn', 'AISuggestController@learn');
    $router->get('/settings/ai-contexts', 'AISuggestController@adminContexts');
    $router->get('/api/ai/contexts/all', 'AISuggestController@allContexts');
    $router->post('/api/ai/contexts/save', 'AISuggestController@saveContext');

    // Gerenciamento de Usuários
    $router->get('/settings/users', 'UsersController@index');
    $router->post('/settings/users/store', 'UsersController@store');
    $router->post('/settings/users/update', 'UsersController@update');
    $router->post('/settings/users/toggle-status', 'UsersController@toggleStatus');
    $router->get('/settings/users/get', 'UsersController@get');

    // Sistema de Alertas e Monitoramento
    $router->get('/api/system-alerts', 'SystemAlertController@activeAlerts');
    $router->post('/api/system-alerts/acknowledge', 'SystemAlertController@acknowledge');
    $router->get('/api/system-alerts/check', 'SystemAlertController@check');
    
    // Testes do WhatsApp Gateway
    $router->get('/settings/whatsapp-gateway/test', 'WhatsAppGatewayTestController@index');
    $router->post('/settings/whatsapp-gateway/test/send', 'WhatsAppGatewayTestController@sendTest');
    $router->get('/settings/whatsapp-gateway/test/channels', 'WhatsAppGatewayTestController@listChannels');
    $router->get('/settings/whatsapp-gateway/test/events', 'WhatsAppGatewayTestController@getEvents');
    $router->get('/settings/whatsapp-gateway/test/logs', 'WhatsAppGatewayTestController@getLogs');
    $router->post('/settings/whatsapp-gateway/test/webhook', 'WhatsAppGatewayTestController@simulateWebhook');
    
    // Diagnóstico do WhatsApp Gateway
    $router->get('/settings/whatsapp-gateway/diagnostic', 'WhatsAppGatewayDiagnosticController@index');
    $router->get('/settings/whatsapp-gateway/diagnostic/messages', 'WhatsAppGatewayDiagnosticController@getMessages');
    $router->get('/settings/whatsapp-gateway/diagnostic/logs', 'WhatsAppGatewayDiagnosticController@getLogs');
    $router->get('/settings/whatsapp-gateway/diagnostic/check-logs', 'WhatsAppGatewayDiagnosticController@checkWebhookLogs');
    $router->get('/settings/whatsapp-gateway/diagnostic/check-servpro-logs', 'WhatsAppGatewayDiagnosticController@checkServproLogs');
    $router->post('/settings/whatsapp-gateway/diagnostic/simulate-webhook', 'WhatsAppGatewayDiagnosticController@simulateWebhook');
    $router->post('/settings/whatsapp-gateway/diagnostic/checklist-capture', 'WhatsAppGatewayDiagnosticController@checklistCapture');
    $router->post('/settings/whatsapp-gateway/diagnostic/qr', 'WhatsAppGatewayDiagnosticController@diagnoseQr');
    
    // Rotas de Central de Eventos de Comunicação
    $router->get('/settings/communication-events', 'CommunicationEventsController@index');
    $router->get('/settings/communication-events/view', 'CommunicationEventsController@show');
    
    // Rotas do Painel Operacional de Comunicação
    $router->get('/communication-hub', 'CommunicationHubController@index');
    $router->get('/communication-hub/thread', 'CommunicationHubController@thread');
    $router->get('/communication-hub/thread-data', 'CommunicationHubController@getThreadData');
    $router->post('/communication-hub/send', 'CommunicationHubController@send');
    $router->get('/communication-hub/filter-options', 'CommunicationHubController@getFilterOptions');
    $router->get('/communication-hub/conversations-list', 'CommunicationHubController@getConversationsList');
    $router->get('/communication-hub/find-tenant-conversation', 'CommunicationHubController@findTenantConversation');
    $router->get('/communication-hub/check-updates', 'CommunicationHubController@checkUpdates');
    $router->get('/communication-hub/messages/check', 'CommunicationHubController@checkNewMessages');
    $router->get('/communication-hub/messages/new', 'CommunicationHubController@getNewMessages');
    $router->get('/communication-hub/message', 'CommunicationHubController@getMessage');
    $router->get('/communication-hub/media', 'CommunicationHubController@serveMedia');
    // Incoming Leads actions
    $router->post('/communication-hub/incoming-lead/create-tenant', 'CommunicationHubController@createTenantFromIncomingLead');
    $router->post('/communication-hub/incoming-lead/link-tenant', 'CommunicationHubController@linkIncomingLeadToTenant');
    $router->post('/communication-hub/incoming-lead/create-lead', 'CommunicationHubController@createLeadFromConversation');
    $router->post('/communication-hub/incoming-lead/link-lead', 'CommunicationHubController@linkConversationToLead');
    $router->get('/communication-hub/leads-list', 'CommunicationHubController@getLeadsList');
    $router->get('/communication-hub/check-phone-duplicates', 'CommunicationHubController@checkPhoneDuplicates');
    $router->post('/communication-hub/incoming-lead/reject', 'CommunicationHubController@rejectIncomingLead');
    $router->post('/communication-hub/conversation/change-tenant', 'CommunicationHubController@changeConversationTenant');
    $router->post('/communication-hub/conversation/update-status', 'CommunicationHubController@updateConversationStatus');
    $router->post('/communication-hub/conversation/update-contact-name', 'CommunicationHubController@updateContactName');
    $router->post('/communication-hub/conversation/merge', 'CommunicationHubController@mergeConversations');
    $router->post('/communication-hub/conversation/delete', 'CommunicationHubController@deleteConversation');
    $router->post('/communication-hub/conversation/unlink', 'CommunicationHubController@unlinkConversation');
    
    // Transcrição de áudio sob demanda
    $router->post('/communication-hub/transcribe', 'CommunicationHubController@transcribe');
    $router->get('/communication-hub/transcription-status', 'CommunicationHubController@getTranscriptionStatus');

    // Rotas de Oportunidades / CRM (apenas internos)
    $router->get('/opportunities', 'OpportunitiesController@index');
    $router->get('/opportunities/view', 'OpportunitiesController@show');
    $router->post('/opportunities/store', 'OpportunitiesController@store');
    $router->post('/opportunities/update', 'OpportunitiesController@update');
    $router->post('/opportunities/change-stage', 'OpportunitiesController@changeStage');
    $router->post('/opportunities/mark-lost', 'OpportunitiesController@markLost');
    $router->post('/opportunities/reopen', 'OpportunitiesController@reopen');
    $router->post('/opportunities/create-ajax', 'OpportunitiesController@createAjax');
    $router->post('/opportunities/add-note', 'OpportunitiesController@addNote');
    $router->get('/leads/search-ajax', 'OpportunitiesController@searchLeads');
    $router->get('/tenants/search-opp', 'OpportunitiesController@searchTenants');
    $router->post('/leads/store-ajax', 'OpportunitiesController@storeLeadAjax');
    $router->get('/opportunities/find-conversation', 'OpportunitiesController@findConversation');

    // Rotas de acessos e links de infraestrutura (apenas internos)
    $router->get('/owner/shortcuts', 'OwnerShortcutsController@index');
    $router->post('/owner/shortcuts/store', 'OwnerShortcutsController@store');
    $router->post('/owner/shortcuts/update', 'OwnerShortcutsController@update');
    $router->post('/owner/shortcuts/delete', 'OwnerShortcutsController@delete');
    $router->get('/owner/shortcuts/password', 'OwnerShortcutsController@getPassword');
    $router->post('/owner/shortcuts/password', 'OwnerShortcutsController@getPassword');

    // Rotas de Projetos & Tarefas (apenas internos)
    $router->get('/projects', 'ProjectController@index');
    $router->get('/projects/show', 'ProjectController@show');
    $router->post('/projects/store', 'ProjectController@store');
    $router->post('/projects/update', 'ProjectController@update');
    $router->post('/projects/archive', 'ProjectController@archive');
    
    // Rotas de Contratos de Projetos (apenas internos)
    $router->get('/contracts', 'ProjectContractController@index');
    $router->get('/contracts/show', 'ProjectContractController@show');
    $router->post('/contracts/update-value', 'ProjectContractController@updateValue');
    $router->post('/contracts/send-whatsapp', 'ProjectContractController@sendWhatsApp');
    $router->get('/contracts/download-pdf', 'ProjectContractController@downloadPdf');
    
    // Rotas de Quadro Kanban
    $router->get('/projects/board', 'TaskBoardController@board');
    $router->post('/tasks/store', 'TaskBoardController@store');
    $router->post('/tasks/update', 'TaskBoardController@update');
    $router->post('/tasks/move', 'TaskBoardController@move');
    $router->post('/tasks/delete', 'TaskBoardController@delete');
    $router->get('/tasks/{id}', 'TaskBoardController@show');
    
    // Rotas de Checklist (AJAX)
    $router->post('/tasks/checklist/add', 'TaskChecklistController@add');
    $router->post('/tasks/checklist/toggle', 'TaskChecklistController@toggle');
    $router->post('/tasks/checklist/update', 'TaskChecklistController@update');
    $router->post('/tasks/checklist/delete', 'TaskChecklistController@delete');
    $router->post('/tasks/checklist/reorder', 'TaskChecklistController@reorder');
    
    // Rotas de anexos de tarefas
    $router->post('/tasks/attachments/upload', 'TaskAttachmentsController@upload');
    $router->get('/tasks/attachments/list', 'TaskAttachmentsController@list');
    $router->get('/tasks/attachments/download', 'TaskAttachmentsController@download');
    $router->post('/tasks/attachments/delete', 'TaskAttachmentsController@delete');
    
    // Rotas de Biblioteca de Gravações de Tela
    $router->get('/screen-recordings', 'ScreenRecordingsController@index');
    $router->post('/screen-recordings/delete', 'ScreenRecordingsController@delete');
    $router->get('/screen-recordings/check-token', 'ScreenRecordingsController@checkToken');
    $router->get('/screen-recordings/recorder-popup', 'ScreenRecordingsController@recorderPopup');
    
    // Rotas de Agenda (unificada: Lista | Quadro)
    $router->get('/agenda', 'AgendaController@agendaUnified');
    $router->get('/agenda/blocos', 'AgendaController@blocos'); // compat: redireciona para ?view=lista
    $router->get('/agenda/tarefas', 'AgendaController@index'); // legado: tarefas do dia (view=hoje|semana)
    $router->get('/agenda/timeline', 'AgendaController@timeline');
    $router->get('/agenda/manual-item/novo', 'AgendaController@createManualItem');
    $router->post('/agenda/manual-item/novo', 'AgendaController@storeManualItem');
    $router->get('/agenda/semana', 'AgendaController@semana');
    $router->get('/agenda/stats', 'AgendaController@stats');
    $router->get('/agenda/bloco', 'AgendaController@show');
    $router->get('/agenda/available-blocks', 'AgendaController@getAvailableBlocks');
    $router->get('/agenda/block-types', 'AgendaController@getBlockTypes');
    $router->get('/agenda/tasks-by-project', 'AgendaController@getTasksByProject');
    $router->get('/agenda/activity-types', 'AgendaController@getActivityTypes');
    $router->get('/agenda/bloco/editar', 'AgendaController@editBlock');
    $router->post('/agenda/bloco/editar', 'AgendaController@updateBlock');
    $router->get('/agenda/bloco/novo', 'AgendaController@createBlock');
    $router->post('/agenda/bloco/novo', 'AgendaController@storeBlock');
    $router->post('/agenda/bloco/quick-add', 'AgendaController@quickAddBlock');
    $router->post('/agenda/bloco/attach-task', 'AgendaController@attachTask');
    $router->post('/agenda/bloco/detach-task', 'AgendaController@detachTask');
    $router->post('/agenda/bloco/set-focus-task', 'AgendaController@setFocusTask');
    $router->post('/agenda/bloco/create-quick-task', 'AgendaController@createQuickTask');
    $router->post('/agenda/bloco/delete', 'AgendaController@delete');
    $router->post('/agenda/bloco/finish', 'AgendaController@finishBlock');
    $router->post('/agenda/bloco/reopen', 'AgendaController@reopenBlock');
    $router->post('/agenda/bloco/segment/start', 'AgendaController@startSegment');
    $router->post('/agenda/bloco/segment/pause', 'AgendaController@pauseSegment');
    $router->post('/agenda/bloco/segment/create-manual', 'AgendaController@createSegmentManual');
    $router->post('/agenda/bloco/segment/update', 'AgendaController@updateSegment');
    $router->post('/agenda/bloco/segment/delete', 'AgendaController@deleteSegment');
    $router->post('/agenda/bloco/project/add', 'AgendaController@addProjectToBlock');
    $router->post('/agenda/bloco/project/remove', 'AgendaController@removeProjectFromBlock');
    $router->get('/agenda/bloco/segments', 'AgendaController@getSegments');
    $router->get('/agenda/bloco/linked-tasks', 'AgendaController@getLinkedTasks');
    $router->post('/agenda/bloco/task-time', 'AgendaController@updateTaskTime');
    $router->post('/agenda/start', 'AgendaController@start');
    $router->get('/agenda/ongoing-block', 'AgendaController@getOngoingBlock');
    $router->post('/agenda/finish', 'AgendaController@finish');
    $router->post('/agenda/cancel', 'AgendaController@cancel');
    $router->post('/agenda/update-project-focus', 'AgendaController@updateProjectFocus');
    $router->post('/agenda/generate-blocks', 'AgendaController@generateBlocks');
    $router->get('/agenda/weekly-report', 'AgendaController@weeklyReport');
    $router->get('/agenda/monthly-report', 'AgendaController@monthlyReport');
    $router->get('/agenda/report-export-csv', 'AgendaController@reportExportCsv');
    $router->get('/agenda/report-export-pdf', 'AgendaController@reportExportPdf');
    
    // Rotas de Tarefas
    $router->get('/tasks/modal', 'TaskBoardController@modal');
    $router->post('/tasks/update-status', 'TaskBoardController@updateTaskStatus');
    
    // Rotas de Tickets
    $router->get('/tickets', 'TicketController@index');
    $router->get('/tickets/create', 'TicketController@create');
    $router->get('/tickets/create-from-task', 'TicketController@createFromTask');
    $router->post('/tickets/store', 'TicketController@store');
    $router->get('/tickets/show', 'TicketController@show');
    $router->get('/tickets/edit', 'TicketController@edit');
    $router->post('/tickets/update', 'TicketController@update');
    $router->post('/tickets/create-task', 'TicketController@createTaskFromTicket');
    $router->post('/tickets/close', 'TicketController@close');
    $router->post('/tickets/add-note', 'TicketController@addNote');

// Handler para erros fatais (antes do try-catch)
register_shutdown_function(function() use ($path) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Verifica se display_errors está habilitado
        $displayErrors = ini_get('display_errors');
        $showDetails = ($displayErrors == '1' || $displayErrors == 'On');
        
        // Verifica se é uma requisição AJAX (rotas que retornam JSON)
        $isAjaxRoute = strpos($path, '/hosting/view') === 0 || 
                       strpos($path, '/billing/') === 0 ||
                       strpos($path, '/tasks/') === 0 ||
                       strpos($path, '/communication-hub/') === 0 ||
                       (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                       (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        $errorMsg = "Fatal error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'];
        if (function_exists('pixelhub_log')) {
            pixelhub_log($errorMsg);
        } else {
            @error_log($errorMsg);
        }
        
        if ($showDetails) {
            // Mostra erro detalhado quando display_errors está habilitado
            http_response_code(500);
            echo "<h1>Erro Fatal 500</h1>\n";
            echo "<h2>Mensagem:</h2>\n";
            echo "<pre>" . htmlspecialchars($error['message']) . "</pre>\n";
            echo "<h2>Arquivo:</h2>\n";
            echo "<pre>" . htmlspecialchars($error['file']) . ":" . $error['line'] . "</pre>\n";
            echo "<h2>Tipo de Erro:</h2>\n";
            echo "<pre>" . $error['type'] . "</pre>\n";
        } elseif ($isAjaxRoute) {
            header('Content-Type: application/json; charset=utf-8', true);
            http_response_code(500);
            
            // PATCH E: Detectar modo local para mostrar debug
            $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
            
            $response = [
                'success' => false,
                'error' => 'Erro interno do servidor',
                'error_code' => 'FATAL_ERROR'
            ];
            
            // Se for local e for rota de comunicação, mostra mais detalhes
            if ($isLocal && strpos($path, '/communication-hub/') === 0) {
                $response['debug'] = [
                    'message' => $error['message'],
                    'file' => basename($error['file']),
                    'line' => $error['line'],
                    'type' => $error['type']
                ];
            }
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            http_response_code(500);
            echo "Erro interno do servidor.";
        }
        exit;
    }
});

// Resolve a rota
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // Log todas as rotas registradas antes de dispatch (apenas para debug)
    if (strpos($path, '/screen-recordings/share') !== false) {
        pixelhub_log('[Router Debug] Path sendo buscado: ' . $path);
        pixelhub_log('[Router Debug] Method: ' . $method);
        error_log('[Router Debug] Path sendo buscado: ' . $path);
        error_log('[Router Debug] Method: ' . $method);
    }
    
    $router->dispatch($method, $path);
} catch (\Exception $e) {
    $errorMsg = "Erro na aplicação: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
    if (function_exists('pixelhub_log')) {
        pixelhub_log($errorMsg);
        pixelhub_log("Stack trace: " . $e->getTraceAsString());
    } else {
        error_log($errorMsg);
    }
    
    // Verifica se display_errors está habilitado
    $displayErrors = ini_get('display_errors');
    $showDetails = ($displayErrors == '1' || $displayErrors == 'On');
    
    // Verifica se é uma requisição AJAX
    $isAjaxRoute = strpos($path, '/hosting/view') === 0 || 
                   strpos($path, '/billing/') === 0 ||
                   strpos($path, '/tasks/') === 0 ||
                   (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                   (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    if ($showDetails) {
        // Mostra erro detalhado quando display_errors está habilitado
        http_response_code(500);
        echo "<h1>Erro 500 - Exception</h1>\n";
        echo "<h2>Mensagem:</h2>\n";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>\n";
        echo "<h2>Arquivo:</h2>\n";
        echo "<pre>" . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</pre>\n";
        echo "<h2>Stack Trace:</h2>\n";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
    } elseif ($isAjaxRoute) {
        header('Content-Type: application/json', true);
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao processar requisição: ' . htmlspecialchars($e->getMessage())]);
    } else {
        http_response_code(500);
        echo "Erro interno do servidor.";
    }
}

