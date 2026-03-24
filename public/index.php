<?php

/**
 * Bootstrap principal do Pixel Hub
 */

// ── Early-response para webhook Whapi ANTES de qualquer bootstrap ───────────
// O bootstrap (session_start + autoloader + Env::load) pode demorar >15s no
// hosting compartilhado. O Whapi tem timeout de 15s, causando ETIMEDOUT.
// Solução: responde 200 imediatamente, processa em background.
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    strpos(($_SERVER['REQUEST_URI'] ?? ''), '/api/whatsapp/whapi/webhook') !== false) {

    // Lê o input AGORA (antes do bootstrap consumir php://input)
    $GLOBALS['_WHAPI_RAW_INPUT'] = file_get_contents('php://input');

    // Envia 200 imediatamente para o Whapi
    ignore_user_abort(true);
    $earlyBody = '{"success":true,"code":"RECEIVED"}';
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($earlyBody));
    header('Connection: close');
    echo $earlyBody;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) @ob_end_flush();
        flush();
    }
    // Sinaliza que a resposta já foi enviada (evita double-echo no controller)
    $GLOBALS['_WHAPI_EARLY_RESPONSE_SENT'] = true;
    // Continua o bootstrap e processamento abaixo (em background)
    set_time_limit(120);
    ini_set('max_execution_time', 120);
}
// ── fim early-response Whapi ─────────────────────────────────────────────────

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
    $router->post('/tenants/toggle-row-selection', 'TenantsController@toggleRowSelection');
    $router->get('/tenants/get-row-selections', 'TenantsController@getRowSelections');
    $router->post('/tenants/update-asaas-fields', 'TenantsController@updateAsaasFields');
    $router->post('/tenants/whatsapp-generic-log', 'TenantsController@logGenericWhatsApp');
    $router->get('/tenants/whatsapp-timeline-ajax', 'TenantsController@getWhatsAppTimelineAjax');
    $router->get('/tenants/search-ajax', 'TenantsController@searchAjax');
    $router->post('/tenants/update-observations', 'TenantsController@updateObservations');

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
    
    // Webhook do WhatsApp Gateway (WPPConnect) - legado
    $router->post('/api/whatsapp/webhook', 'WhatsAppWebhookController@handle');
    
    // Webhook do Whapi.Cloud (substitui WPPConnect)
    $router->get('/api/whatsapp/whapi/webhook', function() {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'code' => 'WEBHOOK_READY']);
        exit;
    });
    $router->post('/api/whatsapp/whapi/webhook', 'WhapiWebhookController@handle');

    // Polling ativo Whapi.Cloud (alternativa ao webhook quando firewall bloqueia inbound)
    $router->get('/api/cron/whapi-poll', 'WhapiPollerController@poll');

    // Webhook do Meta Official API
    $router->get('/api/whatsapp/meta/webhook', 'MetaWebhookController@handle');
    $router->post('/api/whatsapp/meta/webhook', 'MetaWebhookController@handle');

    // Notificações de usuário (chatbot/CRM)
    $router->get('/api/notifications', 'UserNotificationController@index');
    $router->get('/api/notifications/unread-count', 'UserNotificationController@unreadCount');
    $router->post('/api/notifications/read', 'UserNotificationController@markRead');

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
    $router->post('/billing/save-contact-status', 'BillingCollectionsController@saveContactStatus');
    $router->post('/billing/update-auto-settings', 'BillingCollectionsController@updateAutoSettings');
    $router->get('/billing/notifications-log', 'BillingCollectionsController@notificationsLog');
    $router->get('/billing/failure-count', 'BillingCollectionsController@failureCount');
    $router->post('/billing/send-manual', 'BillingCollectionsController@sendManual');
    $router->get('/billing/get-last-dispatch', 'BillingCollectionsController@getLastDispatch');
    $router->get('/billing/preview-message', 'BillingCollectionsController@previewMessage');
    
    // Rotas de Mensagem de Start (modal)
    $router->get('/billing/get-start-message', 'BillingCollectionsController@getStartMessage');
    $router->post('/billing/cancel-start-message', 'BillingCollectionsController@cancelStartMessage');
    $router->post('/billing/send-start-message', 'BillingCollectionsController@sendStartMessage');
    
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

    // Configurações — Códigos de Rastreamento
    $router->get('/settings/tracking-codes', 'TrackingCodesController@index');
    $router->get('/settings/tracking-codes/options', 'TrackingCodesController@options');
    $router->get('/settings/tracking-codes/by-channel', 'TrackingCodesController@byChannel');
    $router->get('/settings/tracking-codes/edit', 'TrackingCodesController@edit');
    $router->post('/settings/tracking-codes/store', 'TrackingCodesController@store');
    $router->post('/settings/tracking-codes/update', 'TrackingCodesController@update');
    $router->post('/settings/tracking-codes/delete', 'TrackingCodesController@delete');
    $router->post('/settings/tracking-codes/toggle', 'TrackingCodesController@toggle');

    // Configurações — Campanhas de Rastreamento
    $router->get('/settings/tracking-campaigns', 'TrackingCampaignsController@index');
    $router->get('/settings/tracking-campaigns/edit', 'TrackingCampaignsController@edit');
    $router->get('/settings/tracking-campaigns/options', 'TrackingCampaignsController@options');
    $router->post('/settings/tracking-campaigns/store', 'TrackingCampaignsController@store');
    $router->post('/settings/tracking-campaigns/update', 'TrackingCampaignsController@update');
    $router->post('/settings/tracking-campaigns/delete', 'TrackingCampaignsController@delete');
    $router->post('/settings/tracking-campaigns/toggle', 'TrackingCampaignsController@toggle');

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
    
    // Configurações de Providers WhatsApp (WPPConnect + Meta Official API)
    $router->get('/settings/whatsapp-providers', 'WhatsAppProvidersController@index');
    $router->post('/settings/whatsapp-providers/meta/save', 'WhatsAppProvidersController@saveMetaConfig');
    $router->post('/settings/whatsapp-providers/meta/test', 'WhatsAppProvidersController@testMetaConnection');
    $router->post('/settings/whatsapp-providers/toggle-status', 'WhatsAppProvidersController@toggleStatus');
    $router->post('/settings/whatsapp-providers/delete', 'WhatsAppProvidersController@delete');
    
    // Templates WhatsApp Business API (Meta)
    $router->get('/whatsapp/templates', 'WhatsAppTemplateController@index');
    $router->get('/whatsapp/templates/create', 'WhatsAppTemplateController@create');
    $router->post('/whatsapp/templates/create', 'WhatsAppTemplateController@store');
    $router->get('/whatsapp/templates/edit', 'WhatsAppTemplateController@edit');
    $router->post('/whatsapp/templates/update', 'WhatsAppTemplateController@update');
    $router->get('/whatsapp/templates/view', 'WhatsAppTemplateController@view');
    $router->post('/whatsapp/templates/submit', 'WhatsAppTemplateController@submit');
    $router->post('/whatsapp/templates/delete', 'WhatsAppTemplateController@delete');
    $router->get('/api/whatsapp/templates/approved', 'WhatsAppTemplateController@listApproved');
    $router->get('/api/templates/inspector-data', 'WhatsAppTemplateController@getInspectorData');
    $router->post('/api/templates/simulate-button', 'WhatsAppTemplateController@simulateButton');
    
    // Fluxos de Chatbot
    $router->get('/chatbot/flows', 'ChatbotController@index');
    $router->get('/chatbot/flows/create', 'ChatbotController@create');
    $router->post('/chatbot/flows/create', 'ChatbotController@store');
    $router->get('/chatbot/flows/edit', 'ChatbotController@edit');
    $router->post('/chatbot/flows/update', 'ChatbotController@update');
    $router->get('/chatbot/flows/view', 'ChatbotController@view');
    $router->post('/chatbot/flows/toggle', 'ChatbotController@toggle');
    $router->post('/chatbot/flows/delete', 'ChatbotController@delete');
    $router->post('/chatbot/flows/test', 'ChatbotController@test');
    
    // Campanhas de Templates
    $router->get('/campaigns', 'TemplateCampaignController@index');
    $router->get('/campaigns/create', 'TemplateCampaignController@create');
    $router->post('/campaigns/create', 'TemplateCampaignController@store');
    $router->get('/campaigns/view', 'TemplateCampaignController@view');
    $router->post('/campaigns/start', 'TemplateCampaignController@start');
    $router->post('/campaigns/pause', 'TemplateCampaignController@pause');
    $router->post('/campaigns/resume', 'TemplateCampaignController@resume');
    $router->post('/campaigns/delete', 'TemplateCampaignController@delete');
    $router->get('/campaigns/metrics', 'TemplateCampaignController@metrics');
    $router->post('/campaigns/process-batch', 'TemplateCampaignController@processBatch');
    
    // Redireciona URLs antigas do Gateway WPPConnect para a página de providers
    $router->get('/settings/whatsapp-gateway', function() { header('Location: /settings/whatsapp-providers'); exit; });
    $router->get('/settings/whatsapp-gateway/test', function() { header('Location: /settings/whatsapp-providers'); exit; });
    $router->get('/settings/whatsapp-gateway/diagnostic', function() { header('Location: /settings/whatsapp-providers'); exit; });

    // IA Assistente de Respostas
    $router->get('/api/ai/contexts', 'AISuggestController@contexts');
    $router->post('/api/ai/suggest-reply', 'AISuggestController@suggestReply');
    $router->post('/api/ai/learn', 'AISuggestController@learn');
    $router->post('/api/ai/chat', 'AISuggestController@chat');
    $router->post('/api/ai/suggest-chat', 'AISuggestController@suggestChat');
    $router->get('/settings/ai-contexts', 'AISuggestController@adminContexts');
    $router->get('/api/ai/contexts/all', 'AISuggestController@allContexts');
    $router->post('/api/ai/contexts/save', 'AISuggestController@saveContext');

    // Gerenciamento de Usuários
    $router->get('/settings/users', 'UsersController@index');
    $router->post('/settings/users/store', 'UsersController@store');
    $router->post('/settings/users/update', 'UsersController@update');
    $router->post('/settings/users/toggle-status', 'UsersController@toggleStatus');
    $router->get('/settings/users/get', 'UsersController@get');

    // Whapi.Cloud config routes
    $router->post('/settings/whatsapp-providers/whapi/save', 'WhatsAppProvidersController@saveWhapiConfig');
    $router->post('/settings/whatsapp-providers/whapi/test', 'WhatsAppProvidersController@testWhapiConnection');
    
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
    $router->get('/communication-hub/unread-count', 'CommunicationHubController@getUnreadCount');
    $router->get('/communication-hub/sessions', 'CommunicationHubController@getSessions');
    $router->get('/communication-hub/check-updates', 'CommunicationHubController@checkUpdates');
    $router->get('/communication-hub/messages/check', 'CommunicationHubController@checkNewMessages');
    $router->get('/communication-hub/messages/new', 'CommunicationHubController@getNewMessages');
    $router->get('/communication-hub/message', 'CommunicationHubController@getMessage');
    $router->get('/communication-hub/media', 'CommunicationHubController@serveMedia');
    
    // Rotas do Inbox de Emails (separadas do WhatsApp)
    $router->get('/inbox/emails', 'InboxEmailController@listEmails');
    $router->get('/inbox/emails/thread', 'InboxEmailController@getEmailThread');
    $router->get('/inbox/emails/search-recipients', 'InboxEmailController@searchRecipients');
    $router->post('/inbox/emails/send', 'InboxEmailController@sendEmail');
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
    $router->post('/communication-hub/message/delete', 'CommunicationHubController@deleteMessage');
    
    // Transcrição de áudio sob demanda
    $router->post('/communication-hub/transcribe', 'CommunicationHubController@transcribe');
    $router->get('/communication-hub/transcription-status', 'CommunicationHubController@getTranscriptionStatus');

    // Rotas de Prospecção Ativa (CRM / Comercial)
    $router->get('/prospecting', 'ProspectingController@index');
    $router->get('/prospecting/search-tenants', 'ProspectingController@searchTenants');
    $router->get('/prospecting/search-cnae', 'ProspectingController@searchCnae');
    $router->post('/prospecting/store', 'ProspectingController@store');
    $router->post('/prospecting/update', 'ProspectingController@update');
    $router->post('/prospecting/toggle-status', 'ProspectingController@toggleStatus');
    $router->post('/prospecting/delete', 'ProspectingController@delete');
    $router->post('/prospecting/preview', 'ProspectingController@preview');
    $router->post('/prospecting/run', 'ProspectingController@run');
    $router->get('/prospecting/results', 'ProspectingController@results');
    $router->post('/prospecting/update-result-status', 'ProspectingController@updateResultStatus');
    $router->post('/prospecting/enrich-google-maps', 'ProspectingController@enrichWithGoogleMaps');
    $router->post('/prospecting/apply-google-enrichment', 'ProspectingController@applyGoogleEnrichment');
    $router->post('/prospecting/enrich-cnpjws', 'ProspectingController@enrichWithCnpjWs');
    $router->post('/prospecting/enrich-apify-phone', 'ProspectingController@enrichWithApifyPhone');
    $router->post('/prospecting/save-phone', 'ProspectingController@savePhone');
    $router->get('/prospecting/whatsapp-sessions', 'ProspectingController@whatsappSessions');
    $router->post('/prospecting/convert-to-lead', 'ProspectingController@convertToLead');
    $router->post('/prospecting/mark-wa-sent', 'ProspectingController@markWaSent');
    $router->get('/prospecting/poll-status', 'ProspectingController@pollStatus');
    // SDR — Sales Development Representative
    $router->post('/prospecting/sdr/dispatch', 'ProspectingController@sdrDispatch');
    $router->post('/prospecting/sdr/dispatch-selection', 'ProspectingController@sdrDispatchSelection');
    $router->post('/prospecting/sdr/takeover', 'ProspectingController@sdrTakeover');
    $router->get('/prospecting/sdr/sessions', 'ProspectingController@sdrSessions');
    $router->get('/prospecting/sdr/status', 'ProspectingController@sdrStatus');
    $router->get('/prospecting/sdr/conversations', 'ProspectingController@sdrConversations');

    // Treinamento de Vendas — Simulador de Abordagem
    $router->get('/prospecting/training', 'SalesTrainingController@index');
    $router->post('/prospecting/training/generate', 'SalesTrainingController@generate');
    $router->post('/prospecting/training/chat', 'SalesTrainingController@chat');
    $router->post('/prospecting/training/prospect', 'SalesTrainingController@prospect');

    // Configurações — Catálogo de Produtos por Conta
    $router->get('/settings/tenant-products', 'TenantProductsController@index');
    $router->post('/settings/tenant-products/store', 'TenantProductsController@store');
    $router->post('/settings/tenant-products/update', 'TenantProductsController@update');
    $router->post('/settings/tenant-products/toggle-status', 'TenantProductsController@toggleStatus');
    $router->post('/settings/tenant-products/delete', 'TenantProductsController@delete');
    $router->get('/settings/tenant-products/by-tenant', 'TenantProductsController@byTenant');

    // Configurações — Google Maps API
    $router->get('/settings/google-maps', 'ProspectingController@settingsIndex');
    $router->post('/settings/google-maps/save', 'ProspectingController@settingsSave');
    $router->post('/settings/google-maps/test', 'ProspectingController@settingsTest');

    // Configurações — Apify API (prospecção Instagram)
    $router->get('/settings/apify', 'ProspectingController@settingsApify');
    $router->post('/settings/apify/save', 'ProspectingController@settingsApifySave');
    $router->post('/settings/apify/test', 'ProspectingController@settingsApifyTest');

    // Rotas de Oportunidades / CRM (apenas internos)
    $router->get('/opportunities', 'OpportunitiesController@index');
    $router->get('/opportunities/view', 'OpportunitiesController@show');
    $router->get('/opportunities/view-by-lead', 'OpportunitiesController@viewByLead');
    $router->post('/opportunities/store', 'OpportunitiesController@store');
    $router->post('/opportunities/update', 'OpportunitiesController@update');
    $router->post('/opportunities/change-stage', 'OpportunitiesController@changeStage');
    $router->post('/opportunities/mark-lost', 'OpportunitiesController@markLost');
    $router->post('/opportunities/reopen', 'OpportunitiesController@reopen');
    $router->post('/opportunities/create-ajax', 'OpportunitiesController@createAjax');
    $router->post('/opportunities/add-note', 'OpportunitiesController@addNote');
    $router->post('/opportunities/update-origin', 'OpportunitiesController@updateOrigin');
    $router->post('/opportunities/link-tenant', 'OpportunitiesController@linkTenant');
    $router->get('/opportunities/search-ajax', 'OpportunitiesController@searchAjax');
    $router->get('/leads/search-ajax', 'OpportunitiesController@searchLeads');
    $router->get('/tenants/search-opp', 'OpportunitiesController@searchTenants');
    $router->post('/leads/store-ajax', 'OpportunitiesController@storeLeadAjax');
    $router->get('/opportunities/find-conversation', 'OpportunitiesController@findConversation');
    $router->get('/api/opportunities/conversation-history', 'OpportunitiesController@conversationHistory');
    $router->get('/opportunities/followup-details', 'OpportunitiesController@followupDetails');
    $router->post('/opportunities/update-followup', 'OpportunitiesController@updateFollowup');
    $router->post('/opportunities/delete-followup', 'OpportunitiesController@deleteFollowup');
    
    // Rotas de Contatos Unificados (Leads e Clientes)
    $router->post('/contacts/convert-to-client', 'ContactsController@convertToClient');
    $router->post('/contacts/update', 'ContactsController@update');
    $router->get('/contacts/search', 'ContactsController@search');
    $router->get('/contacts/check-duplicate', 'ContactsController@checkDuplicate');
    
    // Rotas de Leads
    $router->get('/leads', 'LeadsController@index');
    $router->post('/leads/store', 'LeadsController@store');
    $router->get('/leads/edit', 'LeadsController@edit');
    $router->post('/leads/update', 'LeadsController@update');
    $router->post('/leads/delete', 'LeadsController@delete');
    
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
    $router->post('/tasks/create-daily-task', 'TaskBoardController@createDailyTask');
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
    $router->post('/agenda/schedule-task', 'AgendaController@scheduleTask');
    $router->post('/agenda/create-and-schedule-task', 'AgendaController@createAndScheduleTask');
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
    $router->post('/agenda/bloco/quick-status', 'AgendaController@quickStatus');
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
    $router->get('/tickets/list-by-tenant', 'TicketController@listByTenant');
    $router->post('/tickets/mark-billable', 'TicketController@markBillable');
    $router->post('/tickets/generate-billing', 'TicketController@generateBilling');
    $router->post('/tickets/cancel-billing', 'TicketController@cancelBilling');
    $router->post('/tickets/toggle-billable', 'TicketController@toggleBillable');
    $router->post('/tickets/process-billing-and-close', 'TicketController@processBillingAndClose');

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

