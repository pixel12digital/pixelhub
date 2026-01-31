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
            position: relative;
            z-index: 100;
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
        
        /* ===== SIDEBAR COM ÍCONES (RECOLHIDA POR PADRÃO) ===== */
        .sidebar {
            width: 64px;
            min-width: 64px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 12px 0;
            position: sticky;
            top: 0;
            height: calc(100vh - 60px);
            overflow-y: auto;
            overflow-x: hidden;
            flex-shrink: 0;
            transition: width 0.25s ease, min-width 0.25s ease;
            z-index: 50;
            /* Scrollbar sutil */
            scrollbar-width: thin;
            scrollbar-color: transparent transparent;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: transparent;
            border-radius: 3px;
        }
        /* Mostra scrollbar no hover */
        .sidebar:hover::-webkit-scrollbar-thumb,
        .sidebar.expanded::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }
        .sidebar:hover {
            scrollbar-color: #cbd5e1 transparent;
        }
        
        /* Sidebar expandida (hover ou toggle mobile) */
        .sidebar:hover,
        .sidebar.expanded {
            width: 260px;
            min-width: 260px;
        }
        
        /* Toggle para mobile */
        .sidebar-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 48px;
            height: 48px;
            background: #023A8D;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 200;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            align-items: center;
            justify-content: center;
        }
        .sidebar-toggle svg {
            width: 24px;
            height: 24px;
        }
        
        /* Container para alinhar ícone e texto */
        .sidebar-item-content {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            min-width: 0;
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
            transition: opacity 0.2s ease;
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
        
        /* Texto do menu - escondido quando recolhido */
        .sidebar-text {
            white-space: nowrap;
            font-size: 13px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0;
            transform: translateX(-8px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .sidebar:hover .sidebar-text,
        .sidebar.expanded .sidebar-text {
            opacity: 1;
            transform: translateX(0);
        }
        
        /* Divisores do menu */
        .sidebar-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 12px;
        }
        .sidebar:hover .sidebar-divider,
        .sidebar.expanded .sidebar-divider {
            margin: 8px 16px;
        }
        
        /* Links de topo (Dashboard, Comunicação) */
        .sidebar-top-link {
            display: flex;
            align-items: center;
            padding: 10px 22px;
            margin: 2px 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            border-radius: 8px;
            transition: background 0.2s ease, color 0.2s ease, padding 0.2s ease;
            position: relative;
            min-height: 40px;
            cursor: pointer;
        }
        .sidebar:hover .sidebar-top-link,
        .sidebar.expanded .sidebar-top-link {
            padding: 10px 16px;
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
        .sidebar-top-link.active::after {
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
        
        /* Tooltip quando recolhido */
        .sidebar-top-link[data-title]::before,
        .sidebar-module-header[data-title]::before {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%);
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
            transition: opacity 0.15s ease, visibility 0.15s ease;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .sidebar-top-link[data-title]:hover::before,
        .sidebar-module-header[data-title]:hover::before {
            opacity: 1;
            visibility: visible;
        }
        /* Esconde tooltip quando expandido */
        .sidebar:hover .sidebar-top-link[data-title]::before,
        .sidebar:hover .sidebar-module-header[data-title]::before,
        .sidebar.expanded .sidebar-top-link[data-title]::before,
        .sidebar.expanded .sidebar-module-header[data-title]::before {
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        /* Módulos do menu (accordion principal) */
        .sidebar-module {
            margin-bottom: 2px;
        }
        .sidebar-module-header {
            display: flex;
            align-items: center;
            padding: 10px 22px;
            margin: 2px 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.2s ease, color 0.2s ease, padding 0.2s ease;
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
        .sidebar-module-header.active::after {
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
        
        /* Chevron (seta de expansão) */
        .sidebar-chevron {
            margin-left: auto;
            opacity: 0;
            transform: rotate(0deg);
            transition: transform 0.2s ease, opacity 0.2s ease;
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar:hover .sidebar-chevron,
        .sidebar.expanded .sidebar-chevron {
            opacity: 0.6;
        }
        .sidebar-module-header.is-open .sidebar-chevron {
            transform: rotate(180deg);
        }
        .sidebar-module-header:hover .sidebar-chevron {
            opacity: 1;
        }
        
        /* Conteúdo dos módulos - accordion com animação suave */
        .sidebar-module-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease-out;
        }
        .sidebar-module-content.is-open {
            max-height: 600px;
            transition: max-height 0.3s ease-in;
        }
        
        /* Subitens simples */
        .sidebar a.sub-item {
            display: flex;
            align-items: center;
            padding: 8px 16px 8px 52px;
            margin: 1px 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 12.5px;
            border-radius: 6px;
            transition: background 0.2s ease, color 0.2s ease;
            position: relative;
            min-height: 34px;
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
            left: 40px;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 5px;
            background: #023A8D;
            border-radius: 50%;
        }
        
        /* ===== SUBGRUPOS COLAPSÁVEIS (dentro de Configurações) ===== */
        .sidebar-subgroup {
            margin: 4px 0;
        }
        .sidebar-subgroup-header {
            display: flex;
            align-items: center;
            padding: 6px 16px 6px 44px;
            margin: 0 8px;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.2s ease, color 0.2s ease;
            user-select: none;
            opacity: 0;
            transform: translateX(-8px);
        }
        .sidebar:hover .sidebar-subgroup-header,
        .sidebar.expanded .sidebar-subgroup-header {
            opacity: 1;
            transform: translateX(0);
        }
        .sidebar-subgroup-header:hover {
            background: #f1f5f9;
            color: #64748b;
        }
        .sidebar-subgroup-header.is-open {
            color: #475569;
        }
        .sidebar-subgroup-chevron {
            margin-left: auto;
            opacity: 0.5;
            transform: rotate(0deg);
            transition: transform 0.2s ease, opacity 0.2s ease;
            width: 12px;
            height: 12px;
        }
        .sidebar-subgroup-header.is-open .sidebar-subgroup-chevron {
            transform: rotate(180deg);
        }
        .sidebar-subgroup-header:hover .sidebar-subgroup-chevron {
            opacity: 1;
        }
        .sidebar-subgroup-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.2s ease-out;
        }
        .sidebar-subgroup-content.is-open {
            max-height: 300px;
            transition: max-height 0.25s ease-in;
        }
        .sidebar-subgroup-content a.sub-item {
            padding-left: 56px;
            font-size: 12px;
            min-height: 32px;
        }
        .sidebar-subgroup-content a.sub-item.active::before {
            left: 44px;
        }
        
        /* Subitem de terceiro nível (ex: Testes & Logs) */
        .sidebar a.sub-item.level-3 {
            padding-left: 64px;
            font-size: 11.5px;
            min-height: 30px;
            color: #94a3b8;
        }
        .sidebar a.sub-item.level-3:hover {
            color: #64748b;
        }
        .sidebar a.sub-item.level-3.active {
            color: #023A8D;
        }
        .sidebar a.sub-item.level-3.active::before {
            left: 52px;
            width: 4px;
            height: 4px;
        }
        
        /* ===== RESPONSIVO / MOBILE ===== */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 60px;
                width: 0;
                min-width: 0;
                z-index: 100;
                box-shadow: none;
                transition: width 0.3s ease, min-width 0.3s ease, box-shadow 0.3s ease;
            }
            .sidebar.expanded {
                width: 280px;
                min-width: 280px;
                box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            }
            .sidebar:hover {
                width: 0;
                min-width: 0;
            }
            .sidebar-toggle {
                display: flex;
            }
            .sidebar-text,
            .sidebar a.sub-item,
            .sidebar-chevron,
            .sidebar-subgroup-header {
                opacity: 0;
            }
            .sidebar.expanded .sidebar-text,
            .sidebar.expanded a.sub-item,
            .sidebar.expanded .sidebar-chevron,
            .sidebar.expanded .sidebar-subgroup-header {
                opacity: 1;
                transform: translateX(0);
            }
            .content {
                margin-left: 0 !important;
            }
        }
        
        .content {
            flex: 1;
            padding: 30px;
            min-width: 0;
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
        <nav class="sidebar" id="sidebar">
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
                <div class="sidebar-module-header <?= $clientesActive ? 'active' : '' ?> <?= $clientesExpanded ? 'is-open' : '' ?>" data-title="Clientes">
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
            
            <!-- Comunicação (link direto) -->
            <a href="<?= pixelhub_url('/communication-hub') ?>" class="sidebar-top-link <?= (strpos($currentUri, '/communication-hub') !== false) ? 'active' : '' ?>" data-title="Comunicação">
                <span class="sidebar-item-content">
                    <span class="sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </span>
                    <span class="sidebar-text">Comunicação</span>
                </span>
            </a>
            
            <div class="sidebar-divider"></div>
            
            <!-- Agenda -->
            <?php
            $agendaActive = $isActive(['/agenda', '/agenda/semana', '/agenda/stats', '/agenda/bloco']);
            $agendaExpanded = $shouldExpand(['/agenda', '/agenda/semana', '/agenda/stats', '/agenda/bloco']);
            ?>
            <div class="sidebar-module" data-module="agenda">
                <div class="sidebar-module-header <?= $agendaActive ? 'active' : '' ?> <?= $agendaExpanded ? 'is-open' : '' ?>" data-title="Agenda">
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
                <div class="sidebar-module-header <?= $financeiroActive ? 'active' : '' ?> <?= $financeiroExpanded ? 'is-open' : '' ?>" data-title="Financeiro">
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
                <div class="sidebar-module-header <?= $servicosActive ? 'active' : '' ?> <?= $servicosExpanded ? 'is-open' : '' ?>" data-title="Serviços">
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
                <div class="sidebar-module-header <?= $projetosActive ? 'active' : '' ?> <?= $projetosExpanded ? 'is-open' : '' ?>" data-title="Projetos & Tarefas">
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
            
            <!-- Configurações (com subgrupos colapsáveis) -->
            <?php
            $configuracoesActive = $isActive(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/settings/contract-clauses', '/settings/company', '/diagnostic/financial', '/diagnostic/communication', '/settings/asaas', '/settings/ai', '/settings/whatsapp-gateway', '/settings/communication-events', '/owner/shortcuts']);
            $configuracoesExpanded = $shouldExpand(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/settings/contract-clauses', '/settings/company', '/diagnostic/financial', '/diagnostic/communication', '/settings/asaas', '/settings/ai', '/settings/whatsapp-gateway', '/settings/communication-events', '/owner/shortcuts']);
            
            // Determinar qual subgrupo deve estar aberto baseado na rota atual
            $diagnosticoOpen = $shouldExpand(['/diagnostic/financial', '/diagnostic/communication']);
            $empresaOpen = $shouldExpand(['/settings/company']);
            $financeiroConfigOpen = $shouldExpand(['/billing/service-types', '/settings/asaas']);
            $integracoesOpen = $shouldExpand(['/settings/whatsapp-gateway', '/settings/ai']);
            $mensagensOpen = $shouldExpand(['/settings/whatsapp-templates', '/settings/communication-events']);
            $contratosOpen = $shouldExpand(['/settings/contract-clauses']);
            $infraOpen = $shouldExpand(['/settings/hosting-providers', '/owner/shortcuts']);
            ?>
            <div class="sidebar-module" data-module="configuracoes">
                <div class="sidebar-module-header <?= $configuracoesActive ? 'active' : '' ?> <?= $configuracoesExpanded ? 'is-open' : '' ?>" data-title="Configurações">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
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
                    
                    <!-- Subgrupo: Diagnóstico -->
                    <div class="sidebar-subgroup" data-subgroup="diagnostico">
                        <div class="sidebar-subgroup-header <?= $diagnosticoOpen ? 'is-open' : '' ?>">
                            <span>Diagnóstico</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $diagnosticoOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/diagnostic/financial') ?>" class="sub-item <?= (strpos($currentUri, '/diagnostic/financial') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Financeiro</span>
                            </a>
                            <a href="<?= pixelhub_url('/diagnostic/communication') ?>" class="sub-item <?= (strpos($currentUri, '/diagnostic/communication') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Comunicação</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subgrupo: Empresa -->
                    <div class="sidebar-subgroup" data-subgroup="empresa">
                        <div class="sidebar-subgroup-header <?= $empresaOpen ? 'is-open' : '' ?>">
                            <span>Empresa</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $empresaOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/settings/company') ?>" class="sub-item <?= (strpos($currentUri, '/settings/company') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Dados da Empresa</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subgrupo: Financeiro -->
                    <div class="sidebar-subgroup" data-subgroup="financeiro-config">
                        <div class="sidebar-subgroup-header <?= $financeiroConfigOpen ? 'is-open' : '' ?>">
                            <span>Financeiro</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $financeiroConfigOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/billing/service-types') ?>" class="sub-item <?= (strpos($currentUri, '/billing/service-types') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Categorias de Contratos</span>
                            </a>
                            <a href="<?= pixelhub_url('/settings/asaas') ?>" class="sub-item <?= (strpos($currentUri, '/settings/asaas') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Configurações Asaas</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subgrupo: Integrações -->
                    <div class="sidebar-subgroup" data-subgroup="integracoes">
                        <div class="sidebar-subgroup-header <?= $integracoesOpen ? 'is-open' : '' ?>">
                            <span>Integrações</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $integracoesOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-gateway') !== false && strpos($currentUri, '/settings/whatsapp-gateway/test') === false) ? 'active' : '' ?>">
                                <span class="sidebar-text">WhatsApp Gateway</span>
                            </a>
                            <a href="<?= pixelhub_url('/settings/whatsapp-gateway/test') ?>" class="sub-item level-3 <?= (strpos($currentUri, '/settings/whatsapp-gateway/test') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Testes & Logs</span>
                            </a>
                            <a href="<?= pixelhub_url('/settings/ai') ?>" class="sub-item <?= (strpos($currentUri, '/settings/ai') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Configurações IA</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subgrupo: Mensagens -->
                    <div class="sidebar-subgroup" data-subgroup="mensagens">
                        <div class="sidebar-subgroup-header <?= $mensagensOpen ? 'is-open' : '' ?>">
                            <span>Mensagens</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $mensagensOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-templates') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Mensagens WhatsApp</span>
                            </a>
                            <a href="<?= pixelhub_url('/settings/communication-events') ?>" class="sub-item <?= (strpos($currentUri, '/settings/communication-events') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Central de Eventos</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subgrupo: Contratos -->
                    <div class="sidebar-subgroup" data-subgroup="contratos">
                        <div class="sidebar-subgroup-header <?= $contratosOpen ? 'is-open' : '' ?>">
                            <span>Contratos</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $contratosOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/settings/contract-clauses') ?>" class="sub-item <?= (strpos($currentUri, '/settings/contract-clauses') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Cláusulas de Contrato</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subgrupo: Infraestrutura -->
                    <div class="sidebar-subgroup" data-subgroup="infraestrutura">
                        <div class="sidebar-subgroup-header <?= $infraOpen ? 'is-open' : '' ?>">
                            <span>Infraestrutura</span>
                            <svg class="sidebar-subgroup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </div>
                        <div class="sidebar-subgroup-content <?= $infraOpen ? 'is-open' : '' ?>">
                            <a href="<?= pixelhub_url('/settings/hosting-providers') ?>" class="sub-item <?= (strpos($currentUri, '/settings/hosting-providers') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Provedores de Hospedagem</span>
                            </a>
                            <a href="<?= pixelhub_url('/owner/shortcuts') ?>" class="sub-item <?= (strpos($currentUri, '/owner/shortcuts') !== false) ? 'active' : '' ?>">
                                <span class="sidebar-text">Acessos & Links</span>
                            </a>
                        </div>
                    </div>
                    
                </div>
            </div>
        </nav>
        
        <main class="content">
            <?= $content ?? '' ?>
        </main>
    </div>
    
    <!-- Toggle para mobile -->
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
    
    <script>
        /**
         * Sistema de Sidebar com Ícones + Expansão + Acordeão
         */
        (function() {
            const sidebar = document.getElementById('sidebar');
            const modules = document.querySelectorAll('.sidebar-module');
            const subgroups = document.querySelectorAll('.sidebar-subgroup');
            
            // ===== Accordion para módulos principais =====
            modules.forEach(function(module) {
                const header = module.querySelector('.sidebar-module-header');
                const content = module.querySelector('.sidebar-module-content');
                
                if (header && content) {
                    header.addEventListener('click', function(e) {
                        e.preventDefault();
                        const isCurrentlyOpen = header.classList.contains('is-open');
                        
                        // Fecha todos os módulos (accordion)
                        modules.forEach(function(otherModule) {
                            const otherHeader = otherModule.querySelector('.sidebar-module-header');
                            const otherContent = otherModule.querySelector('.sidebar-module-content');
                            if (otherHeader && otherContent) {
                                otherHeader.classList.remove('is-open');
                                otherContent.classList.remove('is-open');
                            }
                        });
                        
                        // Abre o módulo clicado se não estava aberto
                        if (!isCurrentlyOpen) {
                            header.classList.add('is-open');
                            content.classList.add('is-open');
                        }
                    });
                }
            });
            
            // ===== Accordion para subgrupos (dentro de Configurações) =====
            subgroups.forEach(function(subgroup) {
                const header = subgroup.querySelector('.sidebar-subgroup-header');
                const content = subgroup.querySelector('.sidebar-subgroup-content');
                
                if (header && content) {
                    header.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const isCurrentlyOpen = header.classList.contains('is-open');
                        
                        // Fecha todos os subgrupos (accordion)
                        subgroups.forEach(function(otherSubgroup) {
                            const otherHeader = otherSubgroup.querySelector('.sidebar-subgroup-header');
                            const otherContent = otherSubgroup.querySelector('.sidebar-subgroup-content');
                            if (otherHeader && otherContent) {
                                otherHeader.classList.remove('is-open');
                                otherContent.classList.remove('is-open');
                            }
                        });
                        
                        // Abre o subgrupo clicado se não estava aberto
                        if (!isCurrentlyOpen) {
                            header.classList.add('is-open');
                            content.classList.add('is-open');
                        }
                    });
                }
            });
        })();
        
        // ===== Toggle para mobile =====
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('expanded');
        }
        
        // Fecha sidebar no mobile ao clicar fora
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            if (window.innerWidth <= 768 && sidebar.classList.contains('expanded')) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('expanded');
                }
            }
        });
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
