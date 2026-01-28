<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Pixel Hub' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #023A8D;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 20px;
            font-weight: 600;
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-user span {
            font-size: 14px;
        }
        .header-user a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 5px 10px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            transition: background 0.3s;
        }
        .header-user a:hover {
            background: rgba(255,255,255,0.1);
        }
        .header-user-menu {
            position: relative;
            display: inline-block;
        }
        .header-user-menu-toggle {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        .header-user-menu-toggle:hover {
            background: rgba(255,255,255,0.3);
        }
        .header-user-menu-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 220px;
            z-index: 1000;
            overflow: hidden;
        }
        .header-user-menu-dropdown.show {
            display: block;
        }
        .header-user-menu-dropdown .user-info {
            padding: 16px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        .header-user-menu-dropdown .user-info .user-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .header-user-menu-dropdown .user-info .user-email {
            font-size: 12px;
            color: #666;
        }
        .header-user-menu-dropdown .menu-item {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .header-user-menu-dropdown .menu-item:hover {
            background: #f0f0f0;
        }
        .header-user-menu-dropdown .menu-item.danger {
            color: #dc3545;
        }
        .header-user-menu-dropdown .menu-item.danger:hover {
            background: #fee;
            color: #c82333;
        }
        .header-user-menu-dropdown .menu-divider {
            height: 1px;
            background: #eee;
            margin: 4px 0;
        }
        .container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        /* ===== SIDEBAR MODERNO E COMPACTO ===== */
        .sidebar {
            width: 72px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 12px 0;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: visible;
            position: relative;
        }
        /* Quando o sidebar está expandido (hover ou classe) */
        .sidebar:hover,
        .sidebar.expanded {
            width: 240px;
        }
        /* Container para alinhar ícone e texto */
        .sidebar-item-content {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        /* Ícone do menu */
        .sidebar-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            opacity: 0.65;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .sidebar-icon svg {
            width: 100%;
            height: 100%;
        }
        .sidebar-top-link:hover .sidebar-icon,
        .sidebar-module-header:hover .sidebar-icon,
        .sidebar a.sub-item:hover .sidebar-icon {
            opacity: 1;
        }
        .sidebar-top-link.active .sidebar-icon,
        .sidebar-module-header.active .sidebar-icon,
        .sidebar a.sub-item.active .sidebar-icon {
            opacity: 1;
        }
        /* Texto do menu - escondido por padrão, aparece no hover */
        .sidebar-text {
            white-space: nowrap;
            opacity: 0;
            transform: translateX(-8px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            pointer-events: none;
            font-size: 13px;
            font-weight: 500;
        }
        .sidebar:hover .sidebar-text,
        .sidebar.expanded .sidebar-text {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }
        /* Divisores do menu */
        .sidebar-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 16px;
            transition: margin 0.3s ease;
        }
        .sidebar:hover .sidebar-divider,
        .sidebar.expanded .sidebar-divider {
            margin: 8px 20px;
        }
        /* Links de topo (Dashboard) */
        .sidebar-top-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin: 2px 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: relative;
            min-height: 40px;
            cursor: pointer;
        }
        .sidebar:hover .sidebar-top-link,
        .sidebar.expanded .sidebar-top-link {
            padding: 10px 16px;
        }
        /* Tooltip quando sidebar está colapsado (apenas quando não está expandido) */
        .sidebar-top-link::before,
        .sidebar-module-header::before {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%) translateX(-8px);
            background: #1f2937;
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .sidebar-top-link:hover::before,
        .sidebar-module-header:hover::before {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) translateX(0);
        }
        /* Esconde tooltip quando sidebar está expandido */
        .sidebar:hover .sidebar-top-link::before,
        .sidebar:hover .sidebar-module-header::before,
        .sidebar.expanded .sidebar-top-link::before,
        .sidebar.expanded .sidebar-module-header::before {
            opacity: 0 !important;
            visibility: hidden !important;
        }
        .sidebar-top-link:hover {
            background: #f1f5f9;
            color: #023A8D;
        }
        .sidebar-top-link.active {
            background: #eff6ff;
            color: #023A8D;
            font-weight: 600;
        }
        .sidebar-top-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: #023A8D;
            border-radius: 0 2px 2px 0;
        }
        /* Módulos do menu (accordion) */
        .sidebar-module {
            margin-bottom: 2px;
        }
        .sidebar-module-header {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin: 2px 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
            user-select: none;
            position: relative;
            min-height: 40px;
        }
        .sidebar:hover .sidebar-module-header,
        .sidebar.expanded .sidebar-module-header {
            padding: 10px 16px;
        }
        .sidebar-module-header:hover {
            background: #f1f5f9;
            color: #023A8D;
        }
        .sidebar-module-header.active {
            background: #eff6ff;
            color: #023A8D;
            font-weight: 600;
        }
        .sidebar-module-header.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: #023A8D;
            border-radius: 0 2px 2px 0;
        }
        /* Indicador de expansão (seta) */
        .sidebar-module-header.has-children .sidebar-chevron {
            margin-left: auto;
            opacity: 0;
            transform: rotate(-90deg);
            transition: all 0.2s ease;
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar:hover .sidebar-module-header.has-children .sidebar-chevron,
        .sidebar.expanded .sidebar-module-header.has-children .sidebar-chevron {
            opacity: 0.6;
            transform: rotate(0deg);
        }
        .sidebar-module-header.is-open .sidebar-chevron {
            transform: rotate(180deg);
        }
        .sidebar-module-header:hover .sidebar-chevron {
            opacity: 1;
        }
        /* Conteúdo dos subitens */
        .sidebar-module-content {
            display: none;
            overflow: hidden;
            margin-top: 2px;
        }
        .sidebar-module-content.is-open {
            display: block;
        }
        /* Subitens */
        .sidebar a.sub-item {
            display: flex;
            align-items: center;
            padding: 8px 16px 8px 44px;
            margin: 1px 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 12.5px;
            border-radius: 6px;
            transition: all 0.2s ease;
            position: relative;
            min-height: 36px;
            opacity: 0;
            transform: translateX(-8px);
        }
        .sidebar:hover a.sub-item,
        .sidebar.expanded a.sub-item {
            opacity: 1;
            transform: translateX(0);
        }
        .sidebar a.sub-item:hover {
            background: #f8fafc;
            color: #023A8D;
        }
        .sidebar a.sub-item.active {
            background: #eff6ff;
            color: #023A8D;
            font-weight: 600;
        }
        .sidebar a.sub-item.active::before {
            content: '';
            position: absolute;
            left: 32px;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 5px;
            background: #023A8D;
            border-radius: 50%;
            opacity: 0.8;
        }
        .sidebar a.sub-item:hover::before {
            opacity: 0.6;
        }
        .sidebar a.sub-item.active:hover::before {
            opacity: 1;
        }
        /* Títulos internos (não clicáveis) */
        .sidebar-internal-title {
            padding: 8px 16px 4px 44px;
            font-size: 10px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 8px;
            opacity: 0;
            transform: translateX(-8px);
            transition: all 0.2s ease;
        }
        .sidebar:hover .sidebar-internal-title,
        .sidebar.expanded .sidebar-internal-title {
            opacity: 1;
            transform: translateX(0);
        }
        /* Ajuste para subitens de terceiro nível */
        .sidebar a.sub-item[style*="padding-left: 56px"] {
            padding-left: 56px !important;
        }
        .content {
            flex: 1;
            padding: 30px;
        }
        .content-header {
            margin-bottom: 30px;
        }
        .content-header h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .stat-card .value {
            font-size: 32px;
            color: #023A8D;
            font-weight: 600;
        }
    </style>
    <!-- CSS de otimização visual PixelHub -->
    <link rel="stylesheet" href="<?= pixelhub_url('/assets/css/app-overrides.css') ?>">
</head>
<body>
    <?php
    // Obtém o usuário logado se não estiver disponível
    if (!isset($user)) {
        $user = \PixelHub\Core\Auth::user();
    }
    ?>
    <header class="header">
        <h1>Pixel Hub</h1>
        <div class="header-user">
            <a href="<?= pixelhub_url('/wizard/new-project') ?>" 
               style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; margin-right: 10px; transition: background 0.3s; display: inline-flex; align-items: center; gap: 6px;"
               onmouseover="this.style.background='rgba(255,255,255,0.3)'"
               onmouseout="this.style.background='rgba(255,255,255,0.2)'"
               title="Criar novo projeto">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M2 12h20"/>
                </svg>
                Novo Projeto
            </a>
            <button type="button"
                    onclick="startGlobalScreenRecording()"
                    style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; margin-right: 10px; transition: background 0.3s; display: inline-flex; align-items: center; gap: 6px;"
                    onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.2)'"
                    title="Gravar tela (contexto inteligente)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="M10 4v4M14 4v4M4 8h16"/>
                </svg>
                Gravar tela
            </button>
            <div class="header-user-menu">
                <button type="button" class="header-user-menu-toggle" onclick="toggleUserMenu()">
                    <span><?= htmlspecialchars($user['name'] ?? 'Usuário') ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
                <div class="header-user-menu-dropdown" id="userMenuDropdown">
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($user['name'] ?? 'Usuário') ?></div>
                        <div class="user-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    </div>
                    <div class="menu-divider"></div>
                    <a href="<?= pixelhub_url('/logout') ?>" class="menu-item danger">Sair</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <nav class="sidebar">
            <?php
            // Função auxiliar para verificar se uma rota está ativa
            $currentUri = $_SERVER['REQUEST_URI'] ?? '';
            $isActive = function($patterns) use ($currentUri) {
                foreach ($patterns as $pattern) {
                    if (strpos($currentUri, $pattern) !== false) {
                        return true;
                    }
                }
                return false;
            };
            
            // Função auxiliar para verificar se um módulo deve estar expandido
            $shouldExpand = function($patterns) use ($currentUri) {
                foreach ($patterns as $pattern) {
                    if (strpos($currentUri, $pattern) !== false) {
                        return true;
                    }
                }
                return false;
            };
            ?>
            
            <!-- Dashboard (sem subitens) -->
            <a href="<?= pixelhub_url('/dashboard') ?>" class="sidebar-top-link <?= ($currentUri === '/' || strpos($currentUri, '/dashboard') !== false) ? 'active' : '' ?>" data-title="Dashboard">
                <span class="sidebar-item-content">
                    <span class="sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </span>
                    <span class="sidebar-text">Dashboard</span>
                </span>
            </a>
            
            <!-- Clientes -->
            <?php
            $clientesActive = $isActive(['/tenants']);
            $clientesExpanded = $shouldExpand(['/tenants']);
            ?>
            <div class="sidebar-module" data-module="clientes">
                <div class="sidebar-module-header has-children <?= $clientesActive ? 'active' : '' ?> <?= $clientesExpanded ? 'is-open' : '' ?>" data-title="Clientes">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </span>
                        <span class="sidebar-text">Clientes</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $clientesExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/tenants') ?>" class="sub-item <?= (strpos($currentUri, '/tenants') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Lista de Clientes</span>
                    </a>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <!-- Comunicação -->
            <?php
            $comunicacaoActive = $isActive(['/communication-hub', '/communication-hub/thread']);
            $comunicacaoExpanded = $shouldExpand(['/communication-hub', '/communication-hub/thread']);
            ?>
            <div class="sidebar-module" data-module="comunicacao">
                <div class="sidebar-module-header has-children <?= $comunicacaoActive ? 'active' : '' ?> <?= $comunicacaoExpanded ? 'is-open' : '' ?>" data-title="Comunicação">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </span>
                        <span class="sidebar-text">Comunicação</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $comunicacaoExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/communication-hub') ?>" class="sub-item <?= (strpos($currentUri, '/communication-hub') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Inbox Unificada</span>
                    </a>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <!-- Agenda -->
            <?php
            $agendaActive = $isActive(['/agenda', '/agenda/semana', '/agenda/stats', '/agenda/bloco']);
            $agendaExpanded = $shouldExpand(['/agenda', '/agenda/semana', '/agenda/stats', '/agenda/bloco']);
            ?>
            <div class="sidebar-module" data-module="agenda">
                <div class="sidebar-module-header has-children <?= $agendaActive ? 'active' : '' ?> <?= $agendaExpanded ? 'is-open' : '' ?>" data-title="Agenda">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </span>
                        <span class="sidebar-text">Agenda</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $agendaExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/agenda') ?>" class="sub-item <?= (strpos($currentUri, '/agenda') !== false && strpos($currentUri, '/agenda/semana') === false && strpos($currentUri, '/agenda/stats') === false && strpos($currentUri, '/agenda/bloco') === false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Agenda Diária</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/semana') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/semana') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Agenda Semanal</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/stats') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/stats') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Resumo Semanal</span>
                    </a>
                </div>
            </div>
            
            <!-- Financeiro -->
            <?php
            $financeiroActive = $isActive(['/billing/overview', '/billing/collections', '/recurring-contracts']);
            $financeiroExpanded = $shouldExpand(['/billing/overview', '/billing/collections', '/recurring-contracts']);
            ?>
            <div class="sidebar-module" data-module="financeiro">
                <div class="sidebar-module-header has-children <?= $financeiroActive ? 'active' : '' ?> <?= $financeiroExpanded ? 'is-open' : '' ?>" data-title="Financeiro">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </span>
                        <span class="sidebar-text">Financeiro</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $financeiroExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/billing/overview') ?>" class="sub-item <?= (strpos($currentUri, '/billing/overview') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Central de Cobranças</span>
                    </a>
                    <a href="<?= pixelhub_url('/billing/collections') ?>" class="sub-item <?= (strpos($currentUri, '/billing/collections') !== false && strpos($currentUri, '/billing/overview') === false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Histórico de Cobranças</span>
                    </a>
                    <a href="<?= pixelhub_url('/recurring-contracts') ?>" class="sub-item <?= (strpos($currentUri, '/recurring-contracts') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Carteira Recorrente</span>
                    </a>
                </div>
            </div>
            
            <!-- Serviços -->
            <?php
            $servicosActive = $isActive(['/services', '/hosting', '/hosting-plans', '/service-orders']);
            $servicosExpanded = $shouldExpand(['/services', '/hosting', '/hosting-plans', '/service-orders']);
            ?>
            <div class="sidebar-module" data-module="servicos">
                <div class="sidebar-module-header has-children <?= $servicosActive ? 'active' : '' ?> <?= $servicosExpanded ? 'is-open' : '' ?>" data-title="Serviços">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </span>
                        <span class="sidebar-text">Serviços</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $servicosExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/services') ?>" class="sub-item <?= (strpos($currentUri, '/services') !== false && strpos($currentUri, '/service-orders') === false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Catálogo de Serviços</span>
                    </a>
                    <a href="<?= pixelhub_url('/service-orders') ?>" class="sub-item <?= (strpos($currentUri, '/service-orders') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Pedidos de Serviço</span>
                    </a>
                    <a href="<?= pixelhub_url('/hosting') ?>" class="sub-item <?= (strpos($currentUri, '/hosting') !== false && strpos($currentUri, '/hosting-plans') === false && strpos($currentUri, '/services') === false && strpos($currentUri, '/service-orders') === false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Hospedagem & Cobranças</span>
                    </a>
                    <a href="<?= pixelhub_url('/hosting-plans') ?>" class="sub-item <?= (strpos($currentUri, '/hosting-plans') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Planos de Hospedagem</span>
                    </a>
                </div>
            </div>
            
            <!-- Projetos & Tarefas -->
            <?php
            $projetosActive = $isActive(['/projects/board', '/projects', '/screen-recordings', '/contracts', '/tickets']);
            $projetosExpanded = $shouldExpand(['/projects/board', '/projects', '/screen-recordings', '/contracts', '/tickets']);
            ?>
            <div class="sidebar-module" data-module="projetos">
                <div class="sidebar-module-header has-children <?= $projetosActive ? 'active' : '' ?> <?= $projetosExpanded ? 'is-open' : '' ?>" data-title="Projetos & Tarefas">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </span>
                        <span class="sidebar-text">Projetos & Tarefas</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $projetosExpanded ? 'is-open' : '' ?>">
                    <a href="<?= pixelhub_url('/projects/board') ?>" class="sub-item <?= (strpos($currentUri, '/projects/board') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Quadro Kanban</span>
                    </a>
                    <a href="<?= pixelhub_url('/projects') ?>" class="sub-item <?= (strpos($currentUri, '/projects') !== false && strpos($currentUri, '/projects/board') === false && strpos($currentUri, '/contracts') === false && strpos($currentUri, '/tickets') === false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Lista de Projetos</span>
                    </a>
                    <a href="<?= pixelhub_url('/tickets') ?>" class="sub-item <?= (strpos($currentUri, '/tickets') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Tickets</span>
                    </a>
                    <a href="<?= pixelhub_url('/contracts') ?>" class="sub-item <?= (strpos($currentUri, '/contracts') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Contratos de Projetos</span>
                    </a>
                    <a href="<?= pixelhub_url('/screen-recordings') ?>" class="sub-item <?= (strpos($currentUri, '/screen-recordings') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Gravações de Tela</span>
                    </a>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <!-- Configurações -->
            <?php
            $configuracoesActive = $isActive(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/settings/contract-clauses', '/settings/company', '/diagnostic/financial', '/diagnostic/communication', '/settings/asaas', '/settings/ai', '/settings/whatsapp-gateway', '/settings/communication-events', '/owner/shortcuts']);
            $configuracoesExpanded = $shouldExpand(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/settings/contract-clauses', '/settings/company', '/diagnostic/financial', '/diagnostic/communication', '/settings/asaas', '/settings/ai', '/settings/whatsapp-gateway', '/settings/communication-events', '/owner/shortcuts']);
            ?>
            <div class="sidebar-module" data-module="configuracoes">
                <div class="sidebar-module-header has-children <?= $configuracoesActive ? 'active' : '' ?> <?= $configuracoesExpanded ? 'is-open' : '' ?>" data-title="Configurações">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3m15.364 6.364l-4.243-4.243m-4.242 0L5.636 17.364M18.364 6.636l-4.243 4.243m0-4.242L6.636 5.636"></path>
                            </svg>
                        </span>
                        <span class="sidebar-text">Configurações</span>
                    </span>
                    <span class="sidebar-chevron">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </div>
                <div class="sidebar-module-content <?= $configuracoesExpanded ? 'is-open' : '' ?>">
                    <div class="sidebar-internal-title">Diagnóstico</div>
                    <a href="<?= pixelhub_url('/diagnostic/financial') ?>" class="sub-item <?= (strpos($currentUri, '/diagnostic/financial') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Financeiro</span>
                    </a>
                    <a href="<?= pixelhub_url('/diagnostic/communication') ?>" class="sub-item <?= (strpos($currentUri, '/diagnostic/communication') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Comunicação</span>
                    </a>
                    <div class="sidebar-internal-title">Empresa</div>
                    <a href="<?= pixelhub_url('/settings/company') ?>" class="sub-item <?= (strpos($currentUri, '/settings/company') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Dados da Empresa</span>
                    </a>
                    <div class="sidebar-internal-title">Financeiro</div>
                    <a href="<?= pixelhub_url('/billing/service-types') ?>" class="sub-item <?= (strpos($currentUri, '/billing/service-types') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Categorias de Contratos</span>
                    </a>
                    <a href="<?= pixelhub_url('/settings/asaas') ?>" class="sub-item <?= (strpos($currentUri, '/settings/asaas') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Configurações Asaas</span>
                    </a>
                    <div class="sidebar-internal-title">Integrações</div>
                    <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-gateway') !== false && strpos($currentUri, '/settings/whatsapp-gateway/test') === false) ? 'active' : '' ?>">
                        <span class="sidebar-text">WhatsApp Gateway</span>
                    </a>
                    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/test') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-gateway/test') !== false) ? 'active' : '' ?>" style="padding-left: 56px;">
                        <span class="sidebar-text">→ Testes & Logs</span>
                    </a>
                    <a href="<?= pixelhub_url('/settings/ai') ?>" class="sub-item <?= (strpos($currentUri, '/settings/ai') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Configurações IA</span>
                    </a>
                    <div class="sidebar-internal-title">Mensagens</div>
                    <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-templates') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Mensagens WhatsApp</span>
                    </a>
                    <a href="<?= pixelhub_url('/settings/communication-events') ?>" class="sub-item <?= (strpos($currentUri, '/settings/communication-events') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Central de Eventos</span>
                    </a>
                    <div class="sidebar-internal-title">Contratos</div>
                    <a href="<?= pixelhub_url('/settings/contract-clauses') ?>" class="sub-item <?= (strpos($currentUri, '/settings/contract-clauses') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Cláusulas de Contrato</span>
                    </a>
                    <div class="sidebar-internal-title">Infraestrutura</div>
                    <a href="<?= pixelhub_url('/settings/hosting-providers') ?>" class="sub-item <?= (strpos($currentUri, '/settings/hosting-providers') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Provedores de Hospedagem</span>
                    </a>
                    <a href="<?= pixelhub_url('/owner/shortcuts') ?>" class="sub-item <?= (strpos($currentUri, '/owner/shortcuts') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Acessos & Links</span>
                    </a>
                </div>
            </div>
        </nav>
        
        <main class="content">
            <?= $content ?? '' ?>
        </main>
    </div>
    
    <script>
        /**
         * Sistema de Accordion para o Menu Lateral
         * 
         * Funcionalidade:
         * - Cada módulo de topo (com subitens) pode ser expandido/recolhido
         * - Apenas um módulo pode estar expandido por vez
         * - O módulo que contém a página atual é expandido automaticamente
         * 
         * Classes utilizadas:
         * - .sidebar-module: Container do módulo
         * - .sidebar-module-header: Cabeçalho clicável do módulo
         * - .sidebar-module-header.has-children: Indica que tem subitens
         * - .sidebar-module-header.is-open: Módulo expandido
         * - .sidebar-module-header.active: Módulo contém página ativa
         * - .sidebar-module-content: Container dos subitens
         * - .sidebar-module-content.is-open: Subitens visíveis
         */
        (function() {
            // Encontra todos os módulos do menu
            const modules = document.querySelectorAll('.sidebar-module');
            
            // Para cada módulo, adiciona listener de clique no cabeçalho
            modules.forEach(function(module) {
                const header = module.querySelector('.sidebar-module-header');
                const content = module.querySelector('.sidebar-module-content');
                
                // Só adiciona listener se o módulo tiver subitens
                if (header && content && header.classList.contains('has-children')) {
                    header.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Verifica se o módulo clicado já está aberto
                        const isCurrentlyOpen = header.classList.contains('is-open');
                        
                        // Fecha todos os módulos
                        modules.forEach(function(otherModule) {
                            const otherHeader = otherModule.querySelector('.sidebar-module-header');
                            const otherContent = otherModule.querySelector('.sidebar-module-content');
                            
                            if (otherHeader && otherContent) {
                                otherHeader.classList.remove('is-open');
                                otherContent.classList.remove('is-open');
                            }
                        });
                        
                        // Se o módulo clicado não estava aberto, abre ele
                        if (!isCurrentlyOpen) {
                            header.classList.add('is-open');
                            content.classList.add('is-open');
                        }
                    });
                }
            });
        })();
    </script>
    
    <!-- Script do menu dropdown do usuário -->
    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }
        
        function closeUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        }
        
        // Fecha o menu ao clicar fora dele
        document.addEventListener('click', function(event) {
            const menu = document.querySelector('.header-user-menu');
            const dropdown = document.getElementById('userMenuDropdown');
            if (menu && dropdown && !menu.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
    
    <!-- Script do gravador de tela (global) -->
    <script src="<?= pixelhub_url('/assets/js/screen-recorder.js') ?>"></script>
    
    <!-- Função global para iniciar gravação com contexto inteligente -->
    <script>
        /**
         * Inicia gravação de tela com contexto inteligente
         * - Se houver currentTaskId: abre em modo TAREFA (anexa na task)
         * - Se não houver: abre em modo LIBRARY (salva na biblioteca e gera link compartilhável)
         */
        function startGlobalScreenRecording() {
            if (!window.PixelHubScreenRecorder) {
                alert('Gravador de tela não está disponível no momento.');
                return;
            }

            // Verifica se realmente há uma tarefa válida em foco
            // (não apenas se currentTaskId existe, mas se é um número válido)
            const taskId = window.currentTaskId;
            if (taskId && typeof taskId === 'number' && taskId > 0) {
                // Modo tarefa: vincula à tarefa atual
                console.log('[ScreenRecorder] startGlobalScreenRecording: modo tarefa, taskId=', taskId);
                window.PixelHubScreenRecorder.open(taskId, 'task');
            } else {
                // Modo biblioteca: salva na biblioteca e gera link compartilhável
                console.log('[ScreenRecorder] startGlobalScreenRecording: modo biblioteca (sem tarefa)');
                window.currentTaskId = null;
                window.PixelHubScreenRecorder.open(null, 'library');
            }
        }
    </script>
</body>
</html>

