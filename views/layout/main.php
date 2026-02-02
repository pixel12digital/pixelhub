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
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            z-index: 100;
        }
        
        /* Spacer para compensar header fixo */
        .header-spacer {
            height: 60px;
            flex-shrink: 0;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 600;
        }
        /* ===== MENU DE USUÁRIO (estilo SaaS profissional) ===== */
        .header-user-menu {
            position: relative;
            display: inline-block;
        }
        .header-user-menu-toggle {
            background: transparent;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s ease;
        }
        .header-user-menu-toggle:hover {
            background: rgba(255,255,255,0.1);
        }
        /* Avatar com inicial */
        .header-user-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .header-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.2;
        }
        .header-user-name {
            font-size: 13px;
            font-weight: 500;
            color: white;
        }
        .header-user-role {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
        }
        .header-user-chevron {
            opacity: 0.7;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }
        .header-user-menu-toggle[aria-expanded="true"] .header-user-chevron {
            transform: rotate(180deg);
        }
        /* Dropdown */
        .header-user-menu-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 200px;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .header-user-menu-dropdown.show {
            display: block;
        }
        .header-user-menu-dropdown .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .header-user-menu-dropdown .dropdown-header .user-name {
            font-weight: 600;
            color: #111827;
            font-size: 14px;
            margin-bottom: 2px;
        }
        .header-user-menu-dropdown .dropdown-header .user-email {
            font-size: 12px;
            color: #6b7280;
        }
        .header-user-menu-dropdown .dropdown-body {
            padding: 4px 0;
        }
        .header-user-menu-dropdown .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.15s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .header-user-menu-dropdown .menu-item:hover {
            background: #f3f4f6;
        }
        .header-user-menu-dropdown .menu-item svg {
            width: 16px;
            height: 16px;
            opacity: 0.6;
            flex-shrink: 0;
        }
        .header-user-menu-dropdown .menu-item:hover svg {
            opacity: 0.8;
        }
        .header-user-menu-dropdown .menu-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 4px 0;
        }
        .header-user-menu-dropdown .menu-item.danger {
            color: #dc2626;
        }
        .header-user-menu-dropdown .menu-item.danger:hover {
            background: #fef2f2;
        }
        .header-user-menu-dropdown .menu-item.danger svg {
            color: #dc2626;
        }
        
        /* Badge de mensagens não lidas no ícone Comunicação (sidebar) */
        .sidebar-icon-comunicacao {
            position: relative;
        }
        .sidebar-inbox-badge {
            position: absolute;
            top: -6px;
            right: -8px;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            background: #dc2626;
            color: white;
            font-size: 11px;
            font-weight: 700;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(220,38,38,0.4);
            border: 2px solid #b91c1c;
            z-index: 2;
        }
        
        /* ===== INBOX DRAWER ===== */
        .inbox-drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .inbox-drawer-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        .inbox-drawer {
            position: fixed;
            top: 0;
            right: -850px;
            width: 850px;
            max-width: 95vw;
            height: 100vh;
            background: #fff;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
            transition: right 0.3s ease, width 0.3s ease;
        }
        .inbox-drawer.open {
            right: 0;
        }
        /* Inbox minimizado: apenas alça visível, sem overlay bloqueando */
        .inbox-drawer.inbox--minimized {
            width: 40px;
            min-width: 40px;
            max-width: 40px;
        }
        .inbox-drawer.inbox--minimized .inbox-drawer-header,
        .inbox-drawer.inbox--minimized .inbox-drawer-body {
            display: none;
        }
        .inbox-drawer.inbox--minimized .inbox-chevron-handle {
            display: flex;
        }
        /* Handle da seta - ancorado na borda esquerda do Inbox, offset para fora (gutter), sem ocupar largura da lista */
        .inbox-chevron-handle {
            display: none;
            position: absolute;
            left: 0;
            top: 50%;
            transform: translate(-100%, -50%);
            width: 28px;
            min-width: 28px;
            height: 72px;
            align-items: center;
            justify-content: center;
            background: #023A8D;
            color: white;
            cursor: pointer;
            border: none;
            border-radius: 0 6px 6px 0;
            z-index: 10;
            transition: background 0.2s ease;
        }
        .inbox-chevron-handle:hover {
            background: #032d6b;
        }
        .inbox-drawer.open:not(.inbox--minimized) .inbox-chevron-handle {
            display: flex;
        }
        .inbox-drawer.inbox--minimized .inbox-chevron-handle {
            transform: translateY(-50%);
        }
        .inbox-chevron-handle svg {
            width: 18px;
            height: 18px;
        }
        .inbox-drawer.open:not(.inbox--minimized) .inbox-chevron-icon {
            transform: rotate(180deg);
        }
        /* Gutter: reserva espaço quando Inbox minimizado para não sobrepor ícones/conteúdo */
        body.inbox-minimized .container,
        body.inbox-minimized .header {
            margin-right: 40px;
            transition: margin-right 0.3s ease;
        }
        .container, .header {
            transition: margin-right 0.3s ease;
        }
        .inbox-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: #023A8D;
            color: white;
            flex-shrink: 0;
        }
        .inbox-drawer-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .inbox-drawer-close {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        .inbox-drawer-close:hover {
            background: rgba(255,255,255,0.15);
        }
        .inbox-drawer-close svg {
            width: 20px;
            height: 20px;
        }
        .inbox-drawer-body {
            display: flex;
            flex: 1;
            overflow: hidden;
            min-height: 0;
        }
        .inbox-drawer-list {
            width: 320px;
            min-width: 320px;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            background: #f9fafb;
        }
        .inbox-drawer-list-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: white;
        }
        .inbox-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .inbox-filters select {
            flex: 1;
            min-width: 90px;
            max-width: 140px;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 12px;
        }
        .inbox-filters #inboxFilterTenant {
            max-width: 160px;
        }
        .inbox-btn-nova-conversa {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #25d366;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .inbox-btn-nova-conversa:hover {
            background: #20bd5a;
        }
        .inbox-drawer-list-header select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            background: white;
        }
        .inbox-drawer-list-scroll {
            flex: 1;
            overflow-y: auto;
        }
        .inbox-drawer-conversation {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            transition: background 0.15s ease;
            background: white;
        }
        .inbox-drawer-conversation:hover {
            background: #f3f4f6;
        }
        .inbox-drawer-conversation.active {
            background: #e0f2fe;
            border-left: 3px solid #023A8D;
        }
        .inbox-drawer-conversation .conv-name {
            font-weight: 600;
            font-size: 14px;
            color: #111827;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .inbox-drawer-conversation .conv-name .conv-time {
            font-weight: 400;
            font-size: 11px;
            color: #6b7280;
            white-space: nowrap;
            flex-shrink: 0;
        }
        /* Zona de respiro à direita: conteúdo nunca entra na área do menu ⋮ */
        .inbox-drawer-conversation .conv-content {
            flex: 1;
            min-width: 0;
            padding-right: 40px;
        }
        .inbox-drawer-conversation .conv-preview {
            font-size: 13px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .inbox-drawer-conversation .conv-unread {
            background: #023A8D;
            color: white;
            font-size: 10px;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            margin-left: 8px;
        }
        /* Seção Conversas não vinculadas (mesmo comportamento do Painel de Comunicação) */
        .inbox-unlinked-section {
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
        }
        .inbox-unlinked-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .inbox-unlinked-title {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .inbox-unlinked-icon {
            width: 16px;
            height: 16px;
            color: #6b7280;
            flex-shrink: 0;
        }
        .inbox-unlinked-badge {
            background: #e5e7eb;
            color: #374151;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .inbox-unlinked-description {
            margin: 0;
            font-size: 11px;
            color: #6b7280;
            line-height: 1.4;
        }
        .inbox-unlinked-separator {
            height: 12px;
            border-bottom: 2px solid #e4e6eb;
            margin: 12px 0;
        }
        /* Menu ⋮ e botão Vincular (paridade com Painel) */
        .inbox-drawer .incoming-lead-menu, .inbox-drawer .conversation-menu {
            position: relative;
            display: inline-block;
        }
        .inbox-drawer .incoming-lead-menu-toggle, .inbox-drawer .conversation-menu-toggle {
            padding: 4px 8px;
            background: transparent;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: #6b7280;
            line-height: 1;
            width: 28px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .inbox-drawer .incoming-lead-menu-dropdown, .inbox-drawer .conversation-menu-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 4px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            z-index: 1100;
            min-width: 140px;
            padding: 4px 0;
        }
        .inbox-drawer .incoming-lead-menu-dropdown.show, .inbox-drawer .conversation-menu-dropdown.show {
            display: block;
        }
        .inbox-drawer .incoming-lead-menu-item, .inbox-drawer .conversation-menu-item {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 8px 12px;
            background: transparent;
            border: none;
            text-align: left;
            cursor: pointer;
            font-size: 12px;
            color: #374151;
        }
        .inbox-drawer .incoming-lead-menu-item:hover, .inbox-drawer .conversation-menu-item:hover {
            background: #f3f4f6;
        }
        .inbox-drawer .incoming-lead-menu-item.danger, .inbox-drawer .conversation-menu-item.danger {
            color: #dc2626;
        }
        .inbox-drawer .incoming-lead-btn-primary {
            padding: 6px 12px;
            background: #023A8D;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .inbox-drawer .incoming-lead-btn-primary:hover {
            background: #022a6b;
        }
        .inbox-drawer-chat {
            flex: 1;
            min-height: 0;
            min-width: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #f0f2f5;
        }
        .inbox-drawer-chat-header {
            padding: 14px 20px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .inbox-drawer-chat-header .chat-avatar {
            width: 40px;
            height: 40px;
            background: #023A8D;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        .inbox-drawer-chat-header .chat-info h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: #111827;
        }
        .inbox-drawer-chat-header .chat-info span {
            font-size: 12px;
            color: #6b7280;
        }
        .inbox-drawer-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .inbox-drawer-messages .msg {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.4;
        }
        .inbox-drawer-messages .msg.inbound {
            background: white;
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }
        .inbox-drawer-messages .msg.outbound {
            background: #dcf8c6;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .inbox-drawer-messages .msg .msg-time {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
            text-align: right;
        }
        .inbox-drawer-input {
            padding: 12px 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: flex-end;
            gap: 12px;
            overflow: hidden;
            min-width: 0;
        }
        .inbox-drawer-input textarea {
            flex: 1;
            min-width: 0;
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            overflow-y: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        .inbox-drawer-input textarea:focus {
            border-color: #023A8D;
        }
        .inbox-drawer-input button {
            background: #023A8D;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 24px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .inbox-drawer-input button:hover {
            background: #012d6e;
        }
        .inbox-drawer-input .inbox-media-btn {
            background: transparent;
            color: #6b7280;
            padding: 10px;
            border-radius: 50%;
        }
        .inbox-drawer-input .inbox-media-btn:hover {
            background: #f3f4f6;
            color: #023A8D;
        }
        .inbox-drawer-input .inbox-media-btn svg {
            width: 20px;
            height: 20px;
        }
        .inbox-drawer-input .inbox-media-btn.recording {
            background: #ffebee;
            color: #ff4444;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .inbox-drawer-placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 14px;
        }
        .inbox-drawer-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: #6b7280;
        }
        /* Inbox: imagem clicável para viewer (igual ao Painel de Comunicação) */
        .inbox-media-open {
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            cursor: pointer;
            display: inline-block;
        }
        .inbox-media-thumb {
            display: block;
            max-width: 200px;
            height: auto;
            border-radius: 8px;
        }
        .inbox-media-thumb.inbox-media-lazy {
            background: #f0f0f0;
            min-width: 100px;
            min-height: 80px;
        }
        .inbox-media-open:hover .inbox-media-thumb {
            filter: brightness(0.95);
        }
        /* Inbox: transcrição de áudio - ícone na toolbar ao lado do menu */
        .inbox-drawer .inbox-audio-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .inbox-drawer .inbox-audio-toolbar {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .inbox-drawer .inbox-audio-toolbar audio {
            flex: 1;
            max-width: 250px;
        }
        .inbox-drawer .inbox-audio-actions {
            display: flex;
            align-items: center;
            gap: 2px;
            flex-shrink: 0;
        }
        .inbox-drawer .inbox-transcribe-icon {
            background: transparent;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }
        .inbox-drawer .inbox-transcribe-icon:hover {
            background: rgba(0,0,0,0.05);
            color: #374151;
        }
        .inbox-drawer .inbox-transcribe-icon.processing {
            cursor: default;
            pointer-events: none;
        }
        .inbox-drawer .inbox-transcribe-icon.processing .inbox-transcription-spinner {
            display: inline-block;
        }
        .inbox-drawer .inbox-transcribe-icon.processing svg {
            display: none;
        }
        .inbox-drawer .inbox-transcription-body {
            display: none;
            margin-top: 6px;
            padding: 8px 10px;
            background: #f8f8f8;
            border-radius: 6px;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .inbox-drawer .inbox-transcription-body.open {
            display: block;
        }
        .inbox-drawer .inbox-transcription-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 1.5px solid #d1d5db;
            border-top-color: #6b7280;
            border-radius: 50%;
            animation: inbox-transcription-spin 0.8s linear infinite;
        }
        @keyframes inbox-transcription-spin {
            to { transform: rotate(360deg); }
        }
        /* Inbox: modal viewer de mídia (download, nova aba, fechar) */
        #inbox-media-viewer {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.92);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }
        #inbox-media-viewer .viewer-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #inbox-media-viewer-img {
            max-width: 90vw;
            max-height: 90vh;
            width: auto;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.6);
        }
        #inbox-media-viewer .viewer-actions {
            position: absolute;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 10001;
        }
        #inbox-media-viewer:hover .viewer-actions,
        #inbox-media-viewer .viewer-actions.visible {
            opacity: 1;
        }
        #inbox-media-viewer .viewer-btn {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, transform 0.15s ease;
        }
        #inbox-media-viewer .viewer-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.08);
        }
        #inbox-media-viewer .viewer-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        #inbox-media-viewer .viewer-btn-close {
            background: rgba(220,53,69,0.7);
        }
        #inbox-media-viewer .viewer-btn-close:hover {
            background: rgba(220,53,69,0.9);
        }
        @media (max-width: 768px) {
            #inbox-media-viewer .viewer-actions { opacity: 1; }
            #inbox-media-viewer .viewer-btn { width: 48px; height: 48px; }
        }
        
        /* Inbox áudio: Recording / Preview (igual ao Painel de Comunicação) */
        .inbox-rec-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }
        .inbox-rec-status {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 120px;
        }
        .inbox-rec-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #d93025;
            animation: inbox-rec-pulse 1s ease-in-out infinite;
            flex-shrink: 0;
        }
        @keyframes inbox-rec-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .inbox-rec-time {
            font-variant-numeric: tabular-nums;
            min-width: 48px;
            color: #54656f;
            font-size: 14px;
            font-weight: 500;
        }
        .inbox-rec-max {
            color: #999;
            font-size: 12px;
            margin-left: 2px;
        }
        .inbox-audio-preview {
            flex: 1;
            height: 36px;
            min-width: 180px;
            max-width: 100%;
        }
        .inbox-audio-preview::-webkit-media-controls-panel { background-color: #fff; }
        .inbox-audio-preview::-webkit-media-controls-play-button,
        .inbox-audio-preview::-webkit-media-controls-current-time-display,
        .inbox-audio-preview::-webkit-media-controls-time-remaining-display,
        .inbox-audio-preview::-webkit-media-controls-timeline { color: #54656f; }
        .inbox-icon-btn {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: #54656f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .inbox-icon-btn:hover {
            background: rgba(0,0,0,.06);
        }
        .inbox-icon-btn svg { display: block; }
        .inbox-icon-btn.inbox-send {
            background: rgba(0,0,0,.06);
            color: #023A8D;
        }
        .inbox-icon-btn.inbox-send:hover {
            background: rgba(0,0,0,.10);
        }
        .inbox-sending {
            flex: 1;
            text-align: center;
            color: #54656f;
            font-size: 14px;
            font-style: italic;
        }
        
        /* Mobile responsivo */
        @media (max-width: 768px) {
            .inbox-drawer {
                width: 100vw;
                max-width: 100vw;
                right: -100vw;
            }
            .inbox-drawer.inbox--minimized {
                width: 40px;
                min-width: 40px;
                max-width: 40px;
            }
            .inbox-drawer-list {
                width: 100%;
                min-width: 100%;
            }
            .inbox-drawer-chat {
                display: none;
            }
            .inbox-drawer.chat-open .inbox-drawer-list {
                display: none;
            }
            .inbox-drawer.chat-open .inbox-drawer-chat {
                display: flex;
            }
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 60px);
            margin-top: 60px; /* Compensa header fixo */
        }
        
        /* ===== SIDEBAR COM ÍCONES (RECOLHIDA POR PADRÃO) ===== */
        .sidebar {
            width: 64px;
            min-width: 64px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 12px 0;
            position: fixed;
            top: 60px; /* Abaixo do header fixo */
            left: 0;
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
            margin-left: 64px; /* Compensa sidebar fixa (largura recolhida) */
            transition: margin-left 0.25s ease;
        }
        /* Quando sidebar está expandida via hover, o conteúdo não precisa se mover */
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
        
        <!-- Menu de Usuário (estilo SaaS) -->
        <div class="header-user-menu">
            <?php 
            $userName = $user['name'] ?? 'Usuário';
            $userEmail = $user['email'] ?? '';
            $userInitial = strtoupper(substr($userName, 0, 1));
            ?>
            <button type="button" class="header-user-menu-toggle" onclick="toggleUserMenu()" aria-expanded="false">
                <span class="header-user-avatar"><?= $userInitial ?></span>
                <span class="header-user-info">
                    <span class="header-user-name"><?= htmlspecialchars($userName) ?></span>
                    <span class="header-user-role">Administrador</span>
                </span>
                <svg class="header-user-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div class="header-user-menu-dropdown" id="userMenuDropdown">
                <div class="dropdown-header">
                    <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="dropdown-body">
                    <a href="<?= pixelhub_url('/settings/company') ?>" class="menu-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                        Configurações
                    </a>
                    <div class="menu-divider"></div>
                    <a href="<?= pixelhub_url('/logout') ?>" class="menu-item danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Sair
                    </a>
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
            
            <!-- Comunicação (toggle Inbox) - Painel oculto no menu, rota /communication-hub intacta -->
            <div class="sidebar-comunicacao-wrap">
                <div class="sidebar-top-link sidebar-comunicacao-toggle <?= (strpos($currentUri, '/communication-hub') !== false) ? 'active' : '' ?>" data-title="Comunicação" onclick="sidebarComunicacaoToggle(event)" onkeydown="if(event.key==='Enter'||event.key===' ')sidebarComunicacaoToggle(event)" role="button" tabindex="0">
                    <span class="sidebar-item-content">
                        <span class="sidebar-icon sidebar-icon-comunicacao">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <span class="sidebar-inbox-badge" id="inboxBadge" style="display: none;">0</span>
                        </span>
                        <span class="sidebar-text">Comunicação</span>
                    </span>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <!-- Agenda -->
            <?php
            $agendaActive = $isActive(['/agenda', '/agenda/blocos', '/agenda/semana', '/agenda/stats', '/agenda/weekly-report', '/agenda/timeline', '/agenda/bloco', '/agenda/manual-item']);
            $agendaExpanded = $shouldExpand(['/agenda', '/agenda/blocos', '/agenda/semana', '/agenda/stats', '/agenda/weekly-report', '/agenda/timeline', '/agenda/bloco', '/agenda/manual-item']);
            $isAgendaUnified = (strpos($currentUri, '/agenda') !== false && strpos($currentUri, '/agenda/blocos') === false && strpos($currentUri, '/agenda/semana') === false && strpos($currentUri, '/agenda/stats') === false && strpos($currentUri, '/agenda/weekly-report') === false && strpos($currentUri, '/agenda/timeline') === false && strpos($currentUri, '/agenda/bloco') === false && strpos($currentUri, '/agenda/manual-item') === false);
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
                    <a href="<?= pixelhub_url('/agenda') ?>" class="sub-item <?= $isAgendaUnified ? 'active' : '' ?>">
                        <span class="sidebar-text">O que fazer</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/blocos') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/blocos') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Blocos de tempo</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/semana') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/semana') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Blocos semana</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/timeline') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/timeline') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Visão macro</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/stats') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/stats') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Resumo Semanal</span>
                    </a>
                    <a href="<?= pixelhub_url('/agenda/weekly-report') ?>" class="sub-item <?= (strpos($currentUri, '/agenda/weekly-report') !== false) ? 'active' : '' ?>">
                        <span class="sidebar-text">Relatório de Produtividade</span>
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
    
    <!-- ===== INBOX DRAWER GLOBAL ===== -->
    <div class="inbox-drawer-overlay" id="inboxOverlay" onclick="inboxOverlayClick()"></div>
    <div class="inbox-drawer" id="inboxDrawer">
        <button type="button" class="inbox-chevron-handle" id="inboxChevronHandle" onclick="toggleInboxMinimized(event)" title="Minimizar" aria-label="Minimizar ou expandir Inbox">
            <svg class="inbox-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="inbox-drawer-header">
            <h2>Inbox</h2>
            <button type="button" class="inbox-drawer-close" onclick="inboxCloseBtnClick(event)" title="Minimizar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="inbox-drawer-body">
            <!-- Lista de Conversas -->
            <div class="inbox-drawer-list">
                <div class="inbox-drawer-list-header inbox-filters">
                    <select id="inboxFilterChannel" onchange="onInboxChannelChange(); loadInboxConversations();" title="Canal">
                        <option value="all">Canal: Todos</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="chat">Chat Interno</option>
                    </select>
                    <div id="inboxSessionFilterWrap" style="display: none;">
                        <select id="inboxFilterSession" onchange="loadInboxConversations()" title="Sessão WhatsApp">
                            <option value="">Todas as sessões</option>
                        </select>
                    </div>
                    <select id="inboxFilterTenant" onchange="loadInboxConversations()" title="Cliente">
                        <option value="">Cliente: Todos</option>
                    </select>
                    <select id="inboxFilterStatus" onchange="loadInboxConversations()" title="Status">
                        <option value="active">Ativas</option>
                        <option value="archived">Arquivadas</option>
                        <option value="ignored">Ignoradas</option>
                        <option value="all">Todas</option>
                    </select>
                    <button type="button" id="inboxBtnNovaConversa" class="inbox-btn-nova-conversa" onclick="openInboxNovaConversa()" title="Nova conversa">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Nova Conversa
                    </button>
                </div>
                <div class="inbox-drawer-list-scroll" id="inboxListScroll">
                    <div class="inbox-drawer-loading" id="inboxListLoading">
                        Carregando conversas...
                    </div>
                </div>
            </div>
            <!-- Painel de Chat -->
            <div class="inbox-drawer-chat" id="inboxChat" style="display: none;">
                <div class="inbox-drawer-chat-header" id="inboxChatHeader">
                    <!-- Preenchido via JS -->
                </div>
                <div class="inbox-drawer-messages" id="inboxMessages">
                    <!-- Mensagens carregadas via JS -->
                </div>
                <!-- Preview de mídia (antes do input) -->
                <div id="inboxMediaPreview" style="display: none; padding: 8px 12px; background: #f5f5f5; border-radius: 8px; margin: 8px; border: 1px solid #e0e0e0;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img id="inboxMediaThumb" src="" alt="Preview" style="display: none; width: 60px; height: 60px; border-radius: 6px; object-fit: cover;">
                        <span id="inboxMediaDocName" style="display: none; font-size: 13px; color: #333;">📄 arquivo.pdf</span>
                        <button type="button" onclick="removeInboxMediaPreview()" style="margin-left: auto; background: #ff4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 14px;">✕</button>
                    </div>
                </div>
                <!-- Estado Recording: timer + Parar + Cancelar (mesmo comportamento do Painel de Comunicação) -->
                <div id="inboxRecordingUI" class="inbox-rec-wrap" style="display: none;">
                    <div class="inbox-rec-status">
                        <span class="inbox-rec-dot" aria-hidden="true"></span>
                        <span class="inbox-rec-time" id="inboxRecordingTime">0:00</span>
                        <span class="inbox-rec-max" id="inboxRecMax">/ 2:00</span>
                    </div>
                    <button type="button" class="inbox-icon-btn" id="inboxBtnRecStop" title="Parar gravação" aria-label="Parar gravação">
                        <svg viewBox="0 0 24 24" width="18" height="18"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>
                    </button>
                    <button type="button" class="inbox-icon-btn" id="inboxBtnRecCancel" title="Cancelar" aria-label="Cancelar">
                        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 6V4h8v2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6 6l1 16h10l1-16" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <!-- Estado Preview: player + lixeira + microfone + enviar (igual ao Painel) -->
                <div id="inboxAudioPreviewUI" class="inbox-rec-wrap" style="display: none;">
                    <audio id="inboxAudioPreview" controls class="inbox-audio-preview"></audio>
                    <button type="button" class="inbox-icon-btn" id="inboxBtnReviewCancel" title="Cancelar" aria-label="Cancelar">
                        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 6V4h8v2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6 6l1 16h10l1-16" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>
                    <button type="button" class="inbox-icon-btn" id="inboxBtnReviewRerecord" title="Regravar" aria-label="Regravar">
                        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 14a3 3 0 003-3V6a3 3 0 10-6 0v5a3 3 0 003 3zm5-3a5 5 0 0 1-10 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 19v3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 22h8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                    <button type="button" class="inbox-icon-btn inbox-send" id="inboxBtnReviewSend" title="Enviar áudio" aria-label="Enviar áudio">
                        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M22 2L11 13" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 2l-7 20-4-9-9-4 20-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <!-- Estado Sending -->
                <div id="inboxAudioSendingUI" class="inbox-rec-wrap" style="display: none;">
                    <span class="inbox-sending">Enviando...</span>
                </div>
                <div class="inbox-drawer-input">
                    <button type="button" class="inbox-media-btn" id="inboxBtnAttach" onclick="triggerInboxFileInput()" title="Anexar arquivo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                        </svg>
                    </button>
                    <textarea id="inboxMessageInput" rows="1" placeholder="Digite sua mensagem..." autocomplete="nope" data-lpignore="true" data-1p-ignore data-form-type="other" onkeydown="handleInboxInputKeypress(event)" oninput="autoResizeInboxTextarea(this); updateInboxSendMicVisibility()"></textarea>
                    <button type="button" class="inbox-media-btn" id="inboxBtnMic" onclick="startInboxRecording()" title="Gravar áudio">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <line x1="12" y1="19" x2="12" y2="23"></line>
                            <line x1="8" y1="23" x2="16" y2="23"></line>
                        </svg>
                    </button>
                    <button type="button" id="inboxBtnSend" onclick="sendInboxMessage()" style="display: none;">Enviar</button>
                </div>
                <!-- Input file oculto -->
                <input type="file" id="inboxFileInput" accept="image/*,.pdf,.doc,.docx" style="display: none;">
            </div>
            <!-- Placeholder quando nenhuma conversa selecionada -->
            <div class="inbox-drawer-placeholder" id="inboxPlaceholder">
                Selecione uma conversa para começar
            </div>
        </div>
    </div>
    
    <!-- Modal Inbox: Viewer de mídia (download, nova aba, fechar - igual ao Painel de Comunicação) -->
    <div id="inbox-media-viewer">
        <div class="viewer-container">
            <div class="viewer-actions">
                <button id="inbox-media-download" class="viewer-btn" title="Baixar">
                    <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                </button>
                <button id="inbox-media-open-new" class="viewer-btn" title="Abrir em nova aba">
                    <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                </button>
                <button id="inbox-media-close" class="viewer-btn viewer-btn-close" title="Fechar">
                    <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <img id="inbox-media-viewer-img" src="" alt="Mídia">
        </div>
    </div>
    
    <!-- Modal Inbox: Vincular a Cliente Existente (quando Painel não carregado) -->
    <div id="inbox-link-tenant-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2100; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Vincular a Cliente Existente</h2>
                <button type="button" onclick="inboxCloseLinkTenantModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
            </div>
            <div style="margin-bottom: 20px; padding: 12px; background: #f0f2f5; border-radius: 6px;">
                <div style="font-size: 12px; color: #667781; margin-bottom: 4px;">Contato:</div>
                <div style="font-weight: 600; color: #111b21;" id="inbox-link-tenant-contact-name"></div>
            </div>
            <form onsubmit="inboxLinkIncomingLeadToTenant(event)">
                <input type="hidden" id="inbox-link-tenant-conversation-id" value="">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Buscar Cliente *</label>
                    <input type="text" id="inbox-link-tenant-search" placeholder="Digite nome, email, telefone ou CPF/CNPJ..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;" onkeyup="inboxFilterLinkTenantOptions(this.value)">
                    <select id="inbox-link-tenant-select" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                        <option value="">Selecione um cliente...</option>
                    </select>
                    <div id="inbox-link-tenant-no-results" style="display: none; margin-top: 8px; padding: 8px; background: #fff3cd; border-radius: 4px; color: #856404; font-size: 12px;">Nenhum cliente encontrado com essa busca.</div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Vincular</button>
                    <button type="button" onclick="inboxCloseLinkTenantModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php
    // Modal "Nova Mensagem" para Inbox (quando não está no Communication Hub)
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($currentUri, '/communication-hub') === false):
        $modalTenants = [];
        $modalSessions = [];
        try {
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT id, name, COALESCE(phone,'') as phone FROM tenants WHERE (is_archived IS NULL OR is_archived = 0) ORDER BY name LIMIT 100");
            if ($stmt) $modalTenants = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $stmt = $db->query("SELECT DISTINCT channel_id FROM conversations WHERE channel_type = 'whatsapp' AND channel_id IS NOT NULL AND channel_id != '' ORDER BY channel_id");
            if ($stmt) {
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $id = $row['channel_id'];
                    $modalSessions[] = ['id' => $id, 'name' => ucwords(str_replace(['_','-'], ' ', $id)), 'status' => 'connected'];
                }
            }
        } catch (\Throwable $e) { /* silencioso */ }
        $tenants = $modalTenants;
        $whatsapp_sessions = $modalSessions;
        require __DIR__ . '/../partials/new_message_modal.php';
    endif;
    ?>
    
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
            const toggle = document.querySelector('.header-user-menu-toggle');
            if (dropdown && toggle) {
                const isOpen = dropdown.classList.toggle('show');
                toggle.setAttribute('aria-expanded', isOpen);
            }
        }
        
        function closeUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            const toggle = document.querySelector('.header-user-menu-toggle');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        }
        
        // Fecha o menu ao clicar fora dele
        document.addEventListener('click', function(event) {
            const menu = document.querySelector('.header-user-menu');
            const dropdown = document.getElementById('userMenuDropdown');
            const toggle = document.querySelector('.header-user-menu-toggle');
            if (menu && dropdown && !menu.contains(event.target)) {
                dropdown.classList.remove('show');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
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
    
    <!-- ===== INBOX DRAWER GLOBAL SCRIPT ===== -->
    <script>
    (function() {
        'use strict';
        
        // Estado do Inbox Drawer
        const InboxState = {
            isOpen: false,
            conversations: [],
            incomingLeads: [],
            currentThreadId: null,
            currentChannel: null,
            currentLoadController: null,
            pollingInterval: null,
            messagePollingInterval: null,
            lastUpdateTs: null,
            lastMessageTs: null,
            lastMessageId: null,
            filterOptionsLoaded: false
        };
        
        // URL base - vazia para usar URLs relativas (funciona em qualquer ambiente)
        const INBOX_BASE_URL = '';
        
        // ===== ESTADO DE MÍDIA (Anexos) =====
        const InboxMediaState = {
            file: null,
            base64: null,
            mimeType: null,
            fileName: null,
            fileSize: null,
            type: null // 'image' ou 'document'
        };
        
        function clearInboxMediaState() {
            InboxMediaState.file = null;
            InboxMediaState.base64 = null;
            InboxMediaState.mimeType = null;
            InboxMediaState.fileName = null;
            InboxMediaState.fileSize = null;
            InboxMediaState.type = null;
        }
        
        // ===== ESTADO DE GRAVAÇÃO DE ÁUDIO (idle | recording | preview | sending) =====
        const InboxAudioState = {
            state: 'idle',
            isRecording: false,
            recorder: null,
            stream: null,
            chunks: [],
            blob: null,
            startTime: null,
            timerInterval: null,
            audioPreviewUrl: null
        };
        const INBOX_MAX_RECORDING_MS = 120000; // 2 min
        const INBOX_MIN_AUDIO_BYTES = 2000;
        
        // ===== FUNÇÕES DE MÍDIA (Anexos) =====
        window.triggerInboxFileInput = function() {
            const fileInput = document.getElementById('inboxFileInput');
            if (fileInput) fileInput.click();
        };
        
        // Viewer de mídia Inbox (igual ao Painel de Comunicação: ampliar, download, nova aba, fechar)
        function initInboxMediaViewerOnce() {
            if (window.__inboxMediaViewerInitialized) return;
            window.__inboxMediaViewerInitialized = true;
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.inbox-media-open');
                const img = e.target.closest('.inbox-media-thumb');
                const target = btn || img;
                if (target) {
                    e.preventDefault();
                    const src = target.getAttribute('data-src') || target.src;
                    if (src) openInboxMediaViewer(src);
                    return;
                }
                const closeBtn = e.target.closest('#inbox-media-close');
                if (closeBtn) {
                    e.preventDefault();
                    const v = document.getElementById('inbox-media-viewer');
                    if (v) v.style.display = 'none';
                    return;
                }
                const v = document.getElementById('inbox-media-viewer');
                if (v && (e.target === v || e.target.classList.contains('viewer-container'))) {
                    v.style.display = 'none';
                    return;
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const v = document.getElementById('inbox-media-viewer');
                    if (v && v.style.display !== 'none') v.style.display = 'none';
                }
            });
        }
        function openInboxMediaViewer(src) {
            const viewer = document.getElementById('inbox-media-viewer');
            const img = document.getElementById('inbox-media-viewer-img');
            const downloadBtn = document.getElementById('inbox-media-download');
            const openNewBtn = document.getElementById('inbox-media-open-new');
            if (!viewer || !img || !downloadBtn || !openNewBtn) return;
            img.removeAttribute('style');
            img.removeAttribute('width');
            img.removeAttribute('height');
            function fitToViewport() {
                const vw = window.innerWidth * 0.88;
                const vh = window.innerHeight * 0.88;
                const nw = img.naturalWidth;
                const nh = img.naturalHeight;
                if (!nw || !nh) return;
                const scale = Math.min(vw / nw, vh / nh);
                img.style.width = Math.round(nw * scale) + 'px';
                img.style.height = Math.round(nh * scale) + 'px';
            }
            img.onload = fitToViewport;
            img.src = src;
            if (img.complete && img.naturalWidth) fitToViewport();
            downloadBtn.onclick = function() {
                const a = document.createElement('a');
                a.href = src;
                a.download = '';
                a.click();
            };
            openNewBtn.onclick = function() { window.open(src, '_blank'); };
            const actions = viewer.querySelector('.viewer-actions');
            if (actions) actions.classList.toggle('visible', window.innerWidth <= 768);
            viewer.style.display = 'flex';
        }
        
        // Handler quando arquivo é selecionado
        document.addEventListener('DOMContentLoaded', function() {
            initInboxMediaViewerOnce();
            const fileInput = document.getElementById('inboxFileInput');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        console.log('[Inbox] Arquivo selecionado:', file.name);
                        processInboxMediaFile(file);
                    }
                });
            }
            // Botões de áudio Inbox (igual ao Painel: Parar, Cancelar, Lixeira, Regravar, Enviar)
            const btnRecStop = document.getElementById('inboxBtnRecStop');
            const btnRecCancel = document.getElementById('inboxBtnRecCancel');
            const btnReviewCancel = document.getElementById('inboxBtnReviewCancel');
            const btnReviewRerecord = document.getElementById('inboxBtnReviewRerecord');
            const btnReviewSend = document.getElementById('inboxBtnReviewSend');
            if (btnRecStop) btnRecStop.addEventListener('click', stopInboxRecording);
            if (btnRecCancel) btnRecCancel.addEventListener('click', cancelInboxRecording);
            if (btnReviewCancel) btnReviewCancel.addEventListener('click', cancelInboxPreview);
            if (btnReviewRerecord) btnReviewRerecord.addEventListener('click', rerecordInboxAudio);
            if (btnReviewSend) btnReviewSend.addEventListener('click', sendInboxAudioFromPreview);
        });
        
        function processInboxMediaFile(file) {
            if (!file) return;
            
            // Valida tamanho (máx 16MB)
            if (file.size > 16 * 1024 * 1024) {
                alert('Arquivo muito grande. Máximo permitido: 16MB');
                return;
            }
            
            // Valida tipo
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            if (!allowedTypes.includes(file.type)) {
                alert('Tipo de arquivo não suportado. Use: imagens (JPG, PNG, GIF, WebP), PDF ou DOC/DOCX.');
                return;
            }
            
            const isImage = file.type.startsWith('image/');
            InboxMediaState.type = isImage ? 'image' : 'document';
            InboxMediaState.file = file;
            InboxMediaState.mimeType = file.type;
            InboxMediaState.fileName = file.name;
            InboxMediaState.fileSize = file.size;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const result = e.target.result;
                InboxMediaState.base64 = result.split(',')[1];
                showInboxMediaPreview(file, result);
            };
            reader.onerror = function() {
                alert('Erro ao ler arquivo.');
                clearInboxMediaState();
            };
            reader.readAsDataURL(file);
        }
        
        function showInboxMediaPreview(file, dataUrl) {
            const container = document.getElementById('inboxMediaPreview');
            const thumb = document.getElementById('inboxMediaThumb');
            const docName = document.getElementById('inboxMediaDocName');
            
            if (!container) return;
            
            const isImage = file.type.startsWith('image/');
            
            if (isImage && thumb) {
                thumb.src = dataUrl;
                thumb.style.display = 'block';
                if (docName) docName.style.display = 'none';
            } else if (docName) {
                docName.textContent = '📄 ' + file.name;
                docName.style.display = 'block';
                if (thumb) thumb.style.display = 'none';
            }
            
            container.style.display = 'block';
            updateInboxSendMicVisibility();
        }
        
        window.removeInboxMediaPreview = function() {
            clearInboxMediaState();
            const container = document.getElementById('inboxMediaPreview');
            const thumb = document.getElementById('inboxMediaThumb');
            const docName = document.getElementById('inboxMediaDocName');
            const fileInput = document.getElementById('inboxFileInput');
            
            if (container) container.style.display = 'none';
            if (thumb) { thumb.style.display = 'none'; thumb.src = ''; }
            if (docName) docName.style.display = 'none';
            if (fileInput) fileInput.value = '';
            
            updateInboxSendMicVisibility();
        };
        
        // ===== FUNÇÕES DE GRAVAÇÃO DE ÁUDIO (igual ao Painel: idle → recording → preview → enviar) =====
        function setInboxAudioUI(state) {
            const inputUI = document.querySelector('.inbox-drawer-input');
            const recordingUI = document.getElementById('inboxRecordingUI');
            const previewUI = document.getElementById('inboxAudioPreviewUI');
            const sendingUI = document.getElementById('inboxAudioSendingUI');
            const timeEl = document.getElementById('inboxRecordingTime');
            const recMaxEl = document.getElementById('inboxRecMax');
            const audioEl = document.getElementById('inboxAudioPreview');
            
            if (inputUI) inputUI.style.display = state === 'idle' ? 'flex' : 'none';
            if (recordingUI) recordingUI.style.display = state === 'recording' ? 'flex' : 'none';
            if (previewUI) previewUI.style.display = state === 'preview' ? 'flex' : 'none';
            if (sendingUI) sendingUI.style.display = state === 'sending' ? 'flex' : 'none';
            
            if (state === 'idle' && timeEl) timeEl.textContent = '0:00';
            if (recMaxEl) recMaxEl.style.display = state === 'recording' ? '' : 'none';
            if (audioEl && state !== 'preview' && audioEl.src) {
                URL.revokeObjectURL(audioEl.src);
                audioEl.src = '';
                audioEl.load();
            }
        }
        
        window.startInboxRecording = async function() {
            if (InboxAudioState.isRecording || InboxAudioState.state === 'preview') return;
            
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Seu navegador não suporta gravação de áudio.');
                    return;
                }
                
                InboxAudioState.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mimeType = MediaRecorder.isTypeSupported('audio/ogg;codecs=opus') ? 'audio/ogg;codecs=opus' : '';
                const options = { audioBitsPerSecond: 24000 };
                if (mimeType) options.mimeType = mimeType;
                
                InboxAudioState.recorder = new MediaRecorder(InboxAudioState.stream, options);
                InboxAudioState.chunks = [];
                
                InboxAudioState.recorder.ondataavailable = (e) => {
                    if (e.data && e.data.size) InboxAudioState.chunks.push(e.data);
                };
                
                InboxAudioState.recorder.onstop = () => {
                    InboxAudioState.blob = new Blob(InboxAudioState.chunks, { type: InboxAudioState.recorder.mimeType || 'audio/ogg' });
                };
                
                InboxAudioState.recorder.start();
                InboxAudioState.isRecording = true;
                InboxAudioState.state = 'recording';
                InboxAudioState.startTime = Date.now();
                setInboxAudioUI('recording');
                
                const timeEl = document.getElementById('inboxRecordingTime');
                if (timeEl) timeEl.textContent = '0:00';
                
                InboxAudioState.timerInterval = setInterval(() => {
                    if (!timeEl || !InboxAudioState.startTime) return;
                    const elapsed = Date.now() - InboxAudioState.startTime;
                    const s = Math.floor(elapsed / 1000);
                    const mm = Math.floor(s / 60);
                    const ss = String(s % 60).padStart(2, '0');
                    timeEl.textContent = `${mm}:${ss}`;
                    if (elapsed >= INBOX_MAX_RECORDING_MS) {
                        stopInboxRecording();
                    }
                }, 200);
                
                console.log('[Inbox] Gravação iniciada');
            } catch (err) {
                console.error('[Inbox] Erro ao iniciar gravação:', err);
                alert('Erro ao acessar microfone. Verifique as permissões.');
                resetInboxAudioState();
            }
        };
        
        window.stopInboxRecording = function() {
            if (!InboxAudioState.isRecording || !InboxAudioState.recorder) return;
            
            if (InboxAudioState.timerInterval) {
                clearInterval(InboxAudioState.timerInterval);
                InboxAudioState.timerInterval = null;
            }
            
            InboxAudioState.recorder.stop();
            InboxAudioState.isRecording = false;
            
            if (InboxAudioState.stream) {
                InboxAudioState.stream.getTracks().forEach(track => track.stop());
                InboxAudioState.stream = null;
            }
            
            setTimeout(() => {
                if (!InboxAudioState.blob || InboxAudioState.blob.size < INBOX_MIN_AUDIO_BYTES) {
                    resetInboxAudioState();
                    alert('Áudio muito curto. Grave um pouco mais.');
                    return;
                }
                
                const audioEl = document.getElementById('inboxAudioPreview');
                if (audioEl) {
                    if (InboxAudioState.audioPreviewUrl) URL.revokeObjectURL(InboxAudioState.audioPreviewUrl);
                    InboxAudioState.audioPreviewUrl = URL.createObjectURL(InboxAudioState.blob);
                    audioEl.src = InboxAudioState.audioPreviewUrl;
                    audioEl.load();
                }
                
                InboxAudioState.state = 'preview';
                setInboxAudioUI('preview');
                console.log('[Inbox] Preview de áudio exibido');
            }, 150);
        };
        
        window.cancelInboxRecording = function() {
            if (InboxAudioState.timerInterval) {
                clearInterval(InboxAudioState.timerInterval);
                InboxAudioState.timerInterval = null;
            }
            if (InboxAudioState.recorder && InboxAudioState.recorder.state !== 'inactive') {
                try { InboxAudioState.recorder.stop(); } catch (e) {}
            }
            if (InboxAudioState.stream) {
                InboxAudioState.stream.getTracks().forEach(track => track.stop());
            }
            resetInboxAudioState();
            console.log('[Inbox] Gravação cancelada');
        };
        
        window.cancelInboxPreview = function() {
            resetInboxAudioState();
            console.log('[Inbox] Preview cancelado');
        };
        
        window.rerecordInboxAudio = async function() {
            resetInboxAudioState();
            try {
                await startInboxRecording();
            } catch (e) {
                alert('Não foi possível reiniciar a gravação.');
            }
        };
        
        window.sendInboxAudioFromPreview = async function() {
            if (!InboxAudioState.blob || InboxAudioState.blob.size < INBOX_MIN_AUDIO_BYTES) {
                alert('Áudio muito curto. Grave novamente.');
                return;
            }
            const blobToSend = InboxAudioState.blob;
            InboxAudioState.state = 'sending';
            setInboxAudioUI('sending');
            
            // Optimistic UI: insere mensagem de áudio na lista antes do envio
            const container = document.getElementById('inboxMessages');
            let optimisticEl = null;
            let audioUrl = null;
            if (container) {
                audioUrl = URL.createObjectURL(blobToSend);
                optimisticEl = document.createElement('div');
                optimisticEl.className = 'msg outbound';
                optimisticEl.setAttribute('data-inbox-optimistic', '1');
                optimisticEl.innerHTML = '<audio controls style="max-width: 250px;"></audio><div class="msg-time">Enviando...</div>';
                const audioEl = optimisticEl.querySelector('audio');
                if (audioEl) audioEl.src = audioUrl;
                container.appendChild(optimisticEl);
                container.scrollTop = container.scrollHeight;
            }
            
            try {
                const sendResult = await sendInboxAudio(blobToSend);
                if (optimisticEl) {
                    const timeEl = optimisticEl.querySelector('.msg-time');
                    if (timeEl) timeEl.textContent = 'agora';
                    if (sendResult && sendResult.event_id) {
                        optimisticEl.setAttribute('data-msg-id', sendResult.event_id);
                    }
                }
            } catch (err) {
                if (optimisticEl && optimisticEl.parentNode) optimisticEl.remove();
                if (audioUrl) URL.revokeObjectURL(audioUrl);
                alert('Erro ao enviar áudio: ' + err.message);
            } finally {
                resetInboxAudioState();
            }
        };
        
        function resetInboxAudioState() {
            if (InboxAudioState.timerInterval) {
                clearInterval(InboxAudioState.timerInterval);
                InboxAudioState.timerInterval = null;
            }
            if (InboxAudioState.recorder && InboxAudioState.recorder.state !== 'inactive') {
                try { InboxAudioState.recorder.stop(); } catch (e) {}
            }
            if (InboxAudioState.stream) {
                InboxAudioState.stream.getTracks().forEach(track => track.stop());
                InboxAudioState.stream = null;
            }
            if (InboxAudioState.audioPreviewUrl) {
                try { URL.revokeObjectURL(InboxAudioState.audioPreviewUrl); } catch (e) {}
                InboxAudioState.audioPreviewUrl = null;
            }
            const audioEl = document.getElementById('inboxAudioPreview');
            if (audioEl && audioEl.src) {
                audioEl.src = '';
                audioEl.load();
            }
            InboxAudioState.state = 'idle';
            InboxAudioState.isRecording = false;
            InboxAudioState.recorder = null;
            InboxAudioState.chunks = [];
            InboxAudioState.blob = null;
            InboxAudioState.startTime = null;
            setInboxAudioUI('idle');
        }
        
        async function sendInboxAudio(blob) {
            if (!InboxState.currentThreadId || !InboxState.currentChannel) {
                alert('Selecione uma conversa primeiro.');
                return;
            }
            
            try {
                // Converte blob para base64
                const base64 = await blobToBase64(blob);
                
                // Usa FormData pois o endpoint espera $_POST
                const formData = new FormData();
                formData.append('channel', InboxState.currentChannel);
                formData.append('to', sessionStorage.getItem('inbox_selected_phone') || '');
                formData.append('thread_id', InboxState.currentThreadId);
                formData.append('tenant_id', sessionStorage.getItem('inbox_selected_tenant_id') || '');
                formData.append('channel_id', sessionStorage.getItem('inbox_selected_channel_id') || '');
                formData.append('type', 'audio');
                formData.append('base64Ptt', base64);
                
                console.log('[Inbox] Enviando áudio via API');
                
                const response = await fetch(INBOX_BASE_URL + '/communication-hub/send', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('[Inbox] Áudio enviado com sucesso');
                    return { event_id: result.event_id };
                } else {
                    throw new Error(result.error || 'Erro ao enviar áudio');
                }
            } catch (err) {
                console.error('[Inbox] Erro ao enviar áudio:', err);
                throw err;
            }
        }
        
        function blobToBase64(blob) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const result = reader.result;
                    // Remove o prefixo "data:...;base64,"
                    const base64 = result.split(',')[1];
                    resolve(base64);
                };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        }
        
        // ===== AUTO-RESIZE TEXTAREA (igual ao Painel de Comunicação) =====
        window.autoResizeInboxTextarea = function(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            const newHeight = Math.min(textarea.scrollHeight, 120);
            textarea.style.height = newHeight + 'px';
        };
        
        // ===== VISIBILIDADE SEND/MIC =====
        window.updateInboxSendMicVisibility = function() {
            const input = document.getElementById('inboxMessageInput');
            const btnSend = document.getElementById('inboxBtnSend');
            const btnMic = document.getElementById('inboxBtnMic');
            
            const hasText = input && input.value.trim().length > 0;
            const hasMedia = InboxMediaState.base64 !== null;
            const hasContent = hasText || hasMedia;
            
            if (btnSend) btnSend.style.display = hasContent ? 'block' : 'none';
            if (btnMic) btnMic.style.display = hasContent ? 'none' : 'block';
        };
        
        // ===== TRANSCRIÇÃO DE ÁUDIO (ícone na toolbar ao lado do ⋮) =====
        window.inboxTranscribeAudio = async function(btn, eventId) {
            if (!eventId) return;
            const container = (btn.closest && btn.closest('.inbox-audio-container')) || btn.parentElement;
            if (!container) return;
            const icon = container.querySelector('.inbox-transcribe-icon');
            if (icon) {
                icon.classList.add('processing');
                icon.title = 'Processando...';
                icon.onclick = null;
            }
            try {
                const response = await fetch(INBOX_BASE_URL + '/communication-hub/transcribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'event_id=' + encodeURIComponent(eventId)
                });
                const data = await response.json();
                if (data.success && data.status === 'completed' && data.transcription) {
                    inboxShowTranscription(container, eventId, data.transcription);
                } else if (data.success && data.status === 'processing') {
                    inboxPollTranscriptionStatus(container, eventId, 0);
                } else {
                    if (icon) {
                        icon.classList.remove('processing');
                        icon.title = 'Tentar novamente';
                        icon.onclick = function() { inboxTranscribeAudio(icon, eventId); };
                    }
                }
            } catch (err) {
                console.error('[Inbox] Transcrição:', err);
                if (icon) {
                    icon.classList.remove('processing');
                    icon.title = 'Tentar novamente';
                    icon.onclick = function() { inboxTranscribeAudio(icon, eventId); };
                }
            }
        };
        async function inboxPollTranscriptionStatus(container, eventId, attempts) {
            if (attempts >= 30) {
                const icon = container.querySelector('.inbox-transcribe-icon');
                if (icon) {
                    icon.classList.remove('processing');
                    icon.title = 'Tentar novamente';
                    icon.onclick = function() { inboxTranscribeAudio(icon, eventId); };
                }
                return;
            }
            await new Promise(r => setTimeout(r, 2000));
            try {
                const response = await fetch(INBOX_BASE_URL + '/communication-hub/transcription-status?event_id=' + encodeURIComponent(eventId));
                const data = await response.json();
                if (data.success && data.status === 'completed' && data.transcription) {
                    inboxShowTranscription(container, eventId, data.transcription);
                } else if (data.status === 'processing') {
                    inboxPollTranscriptionStatus(container, eventId, attempts + 1);
                } else if (data.status === 'failed') {
                    const icon = container.querySelector('.inbox-transcribe-icon');
                    if (icon) {
                        icon.classList.remove('processing');
                        icon.title = 'Tentar novamente';
                        icon.onclick = function() { inboxTranscribeAudio(icon, eventId); };
                    }
                } else {
                    inboxPollTranscriptionStatus(container, eventId, attempts + 1);
                }
            } catch (err) {
                inboxPollTranscriptionStatus(container, eventId, attempts + 1);
            }
        }
        function inboxShowTranscription(container, eventId, transcription) {
            if (!container) return;
            const icon = container.querySelector('.inbox-transcribe-icon');
            if (icon) {
                icon.classList.remove('processing');
                icon.setAttribute('data-has-transcription', '1');
                icon.title = 'Ver transcrição';
                icon.onclick = function() { inboxToggleTranscriptionByIcon(icon); };
            }
            let body = container.querySelector('.inbox-transcription-body');
            if (!body) {
                body = document.createElement('div');
                body.className = 'inbox-transcription-body';
                body.setAttribute('data-event-id', eventId);
                container.appendChild(body);
            }
            body.textContent = transcription || '';
        }
        window.inboxToggleTranscriptionByIcon = function(icon) {
            const container = icon.closest('.inbox-audio-container');
            if (!container) return;
            const body = container.querySelector('.inbox-transcription-body');
            if (!body) return;
            const isOpen = body.classList.toggle('open');
            icon.title = isOpen ? 'Ocultar transcrição' : 'Ver transcrição';
        };
        
        // ===== ABRIR/MINIMIZAR DRAWER (Inbox nunca fecha, só minimiza) =====
        window.sidebarComunicacaoToggle = function(e) {
            if (e) {
                e.preventDefault();
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                if (e.type === 'keydown') e.preventDefault();
            }
            if (InboxState.isOpen) {
                toggleInboxMinimized(e);
            } else {
                openInboxDrawer();
            }
        };
        window.toggleInboxDrawer = function() {
            if (InboxState.isOpen) {
                toggleInboxMinimized();
            } else {
                openInboxDrawer();
            }
        };
        window.inboxOverlayClick = function() {
            if (InboxState.isOpen && document.getElementById('inboxDrawer')?.classList.contains('inbox--minimized') === false) {
                toggleInboxMinimized();
            }
        };
        window.inboxCloseBtnClick = function(e) {
            if (e) e.stopPropagation();
            if (InboxState.isOpen && document.getElementById('inboxDrawer')?.classList.contains('inbox--minimized') === false) {
                toggleInboxMinimized(e);
            }
        };
        
        window.openInboxDrawer = function() {
            const drawer = document.getElementById('inboxDrawer');
            const overlay = document.getElementById('inboxOverlay');
            if (!drawer || !overlay) return;
            
            drawer.classList.add('open');
            overlay.classList.add('open');
            InboxState.isOpen = true;
            
            // Mostra/oculta filtro de sessão conforme canal
            onInboxChannelChange();
            // Carrega opções dos filtros (tenants, sessões) e conversas
            loadInboxFilterOptions();
            loadInboxConversations();
            
            // Restaura última conversa se existir
            const savedThreadId = sessionStorage.getItem('inbox_selected_thread_id');
            const savedChannel = sessionStorage.getItem('inbox_selected_channel');
            if (savedThreadId && savedChannel) {
                setTimeout(() => loadInboxConversation(savedThreadId, savedChannel), 300);
            }
            
            // Inicia polling leve
            startInboxPolling();
        };
        
        window.closeInboxDrawer = function() {
            const drawer = document.getElementById('inboxDrawer');
            const overlay = document.getElementById('inboxOverlay');
            if (!drawer || !overlay) return;
            
            drawer.classList.remove('open');
            drawer.classList.remove('inbox--minimized');
            overlay.classList.remove('open');
            document.body.classList.remove('inbox-minimized');
            InboxState.isOpen = false;
            
            // Para polling
            stopInboxPolling();
        };
        
        /** Minimizar/maximizar Inbox (apenas UI, sem fechar nem recarregar) */
        window.toggleInboxMinimized = function(e) {
            if (e) e.stopPropagation();
            const drawer = document.getElementById('inboxDrawer');
            const overlay = document.getElementById('inboxOverlay');
            const handle = document.getElementById('inboxChevronHandle');
            if (!drawer || !overlay || !drawer.classList.contains('open')) return;
            const isMinimized = drawer.classList.toggle('inbox--minimized');
            if (isMinimized) {
                overlay.classList.remove('open');
                document.body.classList.add('inbox-minimized');
                if (handle) handle.setAttribute('title', 'Expandir');
            } else {
                overlay.classList.add('open');
                document.body.classList.remove('inbox-minimized');
                if (handle) handle.setAttribute('title', 'Minimizar');
            }
        };
        
        // Atalho de teclado (Ctrl+I ou Escape - Escape minimiza, nunca fecha)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                e.preventDefault();
                toggleInboxDrawer();
            }
            if (e.key === 'Escape' && InboxState.isOpen) {
                var drawer = document.getElementById('inboxDrawer');
                if (drawer && !drawer.classList.contains('inbox--minimized')) {
                    toggleInboxMinimized(e);
                }
            }
        });
        
        // ===== MENU ⋮ (três pontos) - funções para Inbox quando Painel não carregado =====
        if (typeof window.toggleIncomingLeadMenu !== 'function') {
            window.toggleIncomingLeadMenu = function(btn) {
                const menu = btn.closest('.incoming-lead-menu');
                const dropdown = menu && menu.querySelector('.incoming-lead-menu-dropdown');
                document.querySelectorAll('.incoming-lead-menu-dropdown.show, .conversation-menu-dropdown.show').forEach(d => { if (d !== dropdown) d.classList.remove('show'); });
                if (dropdown) dropdown.classList.toggle('show');
            };
        }
        if (typeof window.closeIncomingLeadMenu !== 'function') {
            window.closeIncomingLeadMenu = function(btn) {
                const menu = btn && btn.closest ? btn.closest('.incoming-lead-menu') : null;
                const dropdown = menu ? menu.querySelector('.incoming-lead-menu-dropdown') : null;
                if (dropdown) dropdown.classList.remove('show');
            };
        }
        if (typeof window.toggleConversationMenu !== 'function') {
            window.toggleConversationMenu = function(btn) {
                const menu = btn.closest('.conversation-menu');
                const dropdown = menu && menu.querySelector('.conversation-menu-dropdown');
                document.querySelectorAll('.incoming-lead-menu-dropdown.show, .conversation-menu-dropdown.show').forEach(d => { if (d !== dropdown) d.classList.remove('show'); });
                if (dropdown) dropdown.classList.toggle('show');
            };
        }
        if (typeof window.closeConversationMenu !== 'function') {
            window.closeConversationMenu = function(btn) {
                const menu = btn && btn.closest ? btn.closest('.conversation-menu') : null;
                const dropdown = menu ? menu.querySelector('.conversation-menu-dropdown') : null;
                if (dropdown) dropdown.classList.remove('show');
            };
        }
        document.addEventListener('click', function inboxMenuCloseHandler(e) {
            if (!e.target.closest('.incoming-lead-menu') && !e.target.closest('.conversation-menu')) {
                document.querySelectorAll('.inbox-drawer .incoming-lead-menu-dropdown.show, .inbox-drawer .conversation-menu-dropdown.show').forEach(d => d.classList.remove('show'));
            }
        });
        // Funções expostas em window para onclick (avaliados no escopo global)
        window.inboxOpenLinkTenantModal = async function(convId, name) {
            if (typeof openLinkTenantModal === 'function') { openLinkTenantModal(convId, name); return; }
            const modal = document.getElementById('inbox-link-tenant-modal');
            if (!modal) return;
            document.getElementById('inbox-link-tenant-conversation-id').value = convId;
            const contactEl = document.getElementById('inbox-link-tenant-contact-name');
            if (contactEl) contactEl.textContent = name || 'Contato Desconhecido';
            const searchInput = document.getElementById('inbox-link-tenant-search');
            if (searchInput) searchInput.value = '';
            const select = document.getElementById('inbox-link-tenant-select');
            if (select) select.innerHTML = '<option value="">Selecione um cliente...</option>';
            try {
                const res = await fetch(INBOX_BASE_URL + '/communication-hub/filter-options?for_link=1');
                const data = await res.json();
                if (data.success && data.tenants && select) {
                    data.tenants.forEach(function(t) {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = (t.name || '') + (t.email ? ' (' + t.email + ')' : '');
                        opt.setAttribute('data-name', (t.name || '').toLowerCase());
                        opt.setAttribute('data-email', (t.email || '').toLowerCase());
                        opt.setAttribute('data-phone', (t.phone || '').replace(/\D/g, ''));
                        opt.setAttribute('data-cpf-cnpj', (t.cpf_cnpj || '').replace(/\D/g, ''));
                        select.appendChild(opt);
                    });
                }
            } catch (e) { console.warn('[Inbox] Erro ao carregar clientes:', e); }
            if (select) select.value = '';
            document.getElementById('inbox-link-tenant-no-results').style.display = 'none';
            modal.style.display = 'flex';
        };
        window.inboxCloseLinkTenantModal = function() {
            const modal = document.getElementById('inbox-link-tenant-modal');
            if (modal) modal.style.display = 'none';
        };
        window.inboxFilterLinkTenantOptions = function(searchTerm) {
            const search = (searchTerm || '').toLowerCase().trim();
            const select = document.getElementById('inbox-link-tenant-select');
            const noResults = document.getElementById('inbox-link-tenant-no-results');
            if (!select) return;
            const options = select.querySelectorAll('option');
            let visibleCount = 0;
            if (options.length > 0) options[0].style.display = '';
            for (let i = 1; i < options.length; i++) {
                const opt = options[i];
                const name = (opt.getAttribute('data-name') || '');
                const email = (opt.getAttribute('data-email') || '');
                const phone = (opt.getAttribute('data-phone') || '');
                const cpfCnpj = (opt.getAttribute('data-cpf-cnpj') || '');
                const searchNorm = search.replace(/[^a-z0-9]/g, '');
                const mName = name.includes(search) || name.replace(/[^a-z0-9]/g, '').includes(searchNorm);
                const mEmail = email.includes(search);
                const mPhone = phone.includes(searchNorm);
                const mCpf = cpfCnpj.includes(searchNorm);
                if (search === '' || mName || mEmail || mPhone || mCpf) {
                    opt.style.display = '';
                    visibleCount++;
                } else {
                    opt.style.display = 'none';
                }
            }
            if (noResults) {
                noResults.style.display = (search !== '' && visibleCount === 0) ? 'block' : 'none';
                select.style.display = (search !== '' && visibleCount === 0) ? 'none' : 'block';
            }
            if (visibleCount === 1 && search !== '') {
                for (let i = 1; i < options.length; i++) {
                    if (options[i].style.display !== 'none') { select.value = options[i].value; break; }
                }
            } else if (search === '') select.value = '';
        };
        window.inboxLinkIncomingLeadToTenant = async function(ev) {
            ev.preventDefault();
            const conversationId = document.getElementById('inbox-link-tenant-conversation-id').value;
            const tenantId = document.getElementById('inbox-link-tenant-select').value;
            if (!tenantId) { alert('Selecione um cliente'); return; }
            try {
                const res = await fetch(INBOX_BASE_URL + '/communication-hub/incoming-lead/link-tenant', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: parseInt(conversationId), tenant_id: parseInt(tenantId) })
                });
                const result = await res.json();
                if (result.success) {
                    alert('Conversa vinculada ao cliente com sucesso!');
                    inboxCloseLinkTenantModal();
                    if (typeof loadInboxConversations === 'function') loadInboxConversations();
                } else {
                    alert('Erro: ' + (result.error || 'Erro desconhecido'));
                }
            } catch (err) {
                console.error('[Inbox] Erro ao vincular:', err);
                alert('Erro ao vincular conversa. Tente novamente.');
            }
        };
        window.inboxOpenCreateTenantModal = function(convId, name, contact) {
            if (typeof openCreateTenantModal === 'function') { openCreateTenantModal(convId, name, contact); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        window.inboxIgnoreConversation = function(convId, name) {
            if (typeof ignoreConversation === 'function') { ignoreConversation(convId, name); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        window.inboxDeleteConversation = async function(convId, name) {
            if (typeof deleteConversation === 'function') { deleteConversation(convId, name); return; }
            if (!confirm('EXCLUIR PERMANENTEMENTE a conversa com "' + (name || 'Contato') + '"?\n\n⚠️ Esta ação NÃO pode ser desfeita!\n\nTodas as mensagens serão removidas.')) return;
            if (!confirm('Tem certeza ABSOLUTA?\n\nConfirme a exclusão de "' + (name || 'Contato') + '".')) return;
            try {
                const res = await fetch(INBOX_BASE_URL + '/communication-hub/conversation/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: parseInt(convId) })
                });
                const result = await res.json();
                if (result.success) {
                    const deletedConvId = parseInt(convId);
                    const m = InboxState.currentThreadId && String(InboxState.currentThreadId).match(/whatsapp_(\d+)/);
                    const currentConvId = m ? parseInt(m[1]) : 0;
                    if (deletedConvId && currentConvId && deletedConvId === currentConvId) {
                        InboxState.currentThreadId = null;
                        InboxState.currentChannel = null;
                        const drawer = document.getElementById('inboxDrawer');
                        if (drawer) drawer.classList.remove('chat-open');
                        const chat = document.getElementById('inboxChat');
                        if (chat) chat.style.display = 'none';
                        const messages = document.getElementById('inboxMessages');
                        if (messages) messages.innerHTML = '';
                    }
                    alert('Conversa excluída permanentemente.');
                    if (typeof loadInboxConversations === 'function') loadInboxConversations();
                } else {
                    alert('Erro: ' + (result.error || 'Erro ao excluir conversa'));
                }
            } catch (err) {
                console.error('[Inbox] Erro ao excluir:', err);
                alert('Erro ao excluir conversa. Tente novamente.');
            }
        };
        window.inboxArchiveConversation = function(convId, name) {
            if (typeof archiveConversation === 'function') { archiveConversation(convId, name); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        window.inboxReactivateConversation = function(convId, name) {
            if (typeof reactivateConversation === 'function') { reactivateConversation(convId, name); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        window.inboxOpenEditContactNameModal = function(convId, name) {
            if (typeof openEditContactNameModal === 'function') { openEditContactNameModal(convId, name); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        window.inboxOpenChangeTenantModal = function(convId, name, tenantId, tenantName) {
            if (typeof openChangeTenantModal === 'function') { openChangeTenantModal(convId, name, tenantId, tenantName); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        window.inboxUnlinkConversation = function(convId, name) {
            if (typeof unlinkConversation === 'function') { unlinkConversation(convId, name); return; }
            window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
        };
        
        // ===== FILTROS (mesmo comportamento do Painel de Comunicação) =====
        window.onInboxChannelChange = function() {
            const channel = (document.getElementById('inboxFilterChannel') || {}).value;
            const wrap = document.getElementById('inboxSessionFilterWrap');
            if (wrap) wrap.style.display = channel === 'whatsapp' ? '' : 'none';
        };
        window.openInboxNovaConversa = function() {
            if (typeof openNewMessageModal === 'function') {
                openNewMessageModal();
            } else {
                window.open(INBOX_BASE_URL + '/communication-hub', '_blank');
            }
        };
        window.loadInboxFilterOptions = async function() {
            if (InboxState.filterOptionsLoaded) return;
            try {
                const res = await fetch(INBOX_BASE_URL + '/communication-hub/filter-options');
                const data = await res.json();
                if (!data.success) return;
                const tenantSelect = document.getElementById('inboxFilterTenant');
                const sessionSelect = document.getElementById('inboxFilterSession');
                if (tenantSelect && data.tenants) {
                    const currentVal = tenantSelect.value;
                    tenantSelect.innerHTML = '<option value="">Todos</option>';
                    (data.tenants || []).forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = (t.name || '').substring(0, 40);
                        tenantSelect.appendChild(opt);
                    });
                    if (currentVal) tenantSelect.value = currentVal;
                }
                if (sessionSelect && data.whatsapp_sessions) {
                    const currentVal = sessionSelect.value;
                    sessionSelect.innerHTML = '<option value="">Todas as sessões</option>';
                    (data.whatsapp_sessions || []).forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id || s.name;
                        opt.textContent = (s.name || s.id || '') + (s.status === 'connected' ? ' ●' : '');
                        sessionSelect.appendChild(opt);
                    });
                    if (currentVal) sessionSelect.value = currentVal;
                }
                InboxState.filterOptionsLoaded = true;
            } catch (e) {
                console.warn('[Inbox] Erro ao carregar opções dos filtros:', e);
            }
        };
        
        // ===== CARREGAR LISTA DE CONVERSAS =====
        window.loadInboxConversations = async function() {
            const listScroll = document.getElementById('inboxListScroll');
            const loading = document.getElementById('inboxListLoading');
            const filterStatus = document.getElementById('inboxFilterStatus');
            
            if (!listScroll) return;
            
            // Mostra loading
            if (loading) loading.style.display = 'flex';
            
            try {
                const channel = (document.getElementById('inboxFilterChannel') || {}).value || 'all';
                const sessionId = (document.getElementById('inboxFilterSession') || {}).value || '';
                const tenantId = (document.getElementById('inboxFilterTenant') || {}).value || '';
                const status = filterStatus ? filterStatus.value : 'active';
                const params = new URLSearchParams({ channel, status });
                if (sessionId) params.set('session_id', sessionId);
                if (tenantId) params.set('tenant_id', tenantId);
                const url = INBOX_BASE_URL + '/communication-hub/conversations-list?' + params.toString();
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success) {
                    const threads = result.threads || [];
                    const incomingLeads = result.incoming_leads || [];
                    const incomingLeadsCount = result.incoming_leads_count !== undefined ? result.incoming_leads_count : incomingLeads.length;
                    InboxState.conversations = threads;
                    InboxState.incomingLeads = incomingLeads;
                    InboxState.lastUpdateTs = result.latest_update_ts || null;
                    renderInboxList(threads, incomingLeads, incomingLeadsCount);
                    updateInboxBadge();
                }
            } catch (error) {
                console.error('[Inbox] Erro ao carregar conversas:', error);
                listScroll.innerHTML = '<div class="inbox-drawer-loading">Erro ao carregar</div>';
            } finally {
                if (loading) loading.style.display = 'none';
            }
        };
        
        function renderInboxList(threads, incomingLeads, incomingLeadsCount) {
            const listScroll = document.getElementById('inboxListScroll');
            if (!listScroll) return;
            
            threads = threads || [];
            incomingLeads = incomingLeads || [];
            incomingLeadsCount = incomingLeadsCount !== undefined ? incomingLeadsCount : incomingLeads.length;
            
            if (threads.length === 0 && incomingLeads.length === 0) {
                listScroll.innerHTML = '<div class="inbox-drawer-loading">Nenhuma conversa encontrada</div>';
                return;
            }
            
            let html = '';
            
            // Seção "Conversas não vinculadas" (mesmo comportamento do Painel de Comunicação)
            if (incomingLeads.length > 0) {
                html += `
                    <div class="inbox-unlinked-section">
                        <div class="inbox-unlinked-header">
                            <h4 class="inbox-unlinked-title">
                                <svg class="inbox-unlinked-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                Conversas não vinculadas
                            </h4>
                            <span class="inbox-unlinked-badge">${incomingLeadsCount}</span>
                        </div>
                        <p class="inbox-unlinked-description">
                            Conversas ainda não associadas a um cliente. Revise e vincule ou crie um novo.
                        </p>
                    </div>
                `;
                const inboxFilterStatus = (document.getElementById('inboxFilterStatus') || {}).value || 'active';
                const showIgnoreInMenu = inboxFilterStatus !== 'ignored';
                incomingLeads.forEach(lead => {
                    const isActive = InboxState.currentThreadId === lead.thread_id;
                    const unreadBadge = lead.unread_count > 0 ? `<span class="conv-unread">${lead.unread_count}</span>` : '';
                    const time = formatInboxDateBrasilia(lead.last_activity);
                    const threadId = (lead.thread_id || '').replace(/'/g, "\\'");
                    const channel = lead.channel || 'whatsapp';
                    const contactName = escapeInboxHtml(lead.contact_name || 'Contato Desconhecido');
                    const contact = escapeInboxHtml(lead.contact || 'Número não identificado');
                    const channelId = lead.channel_id ? ` <span style="opacity: 0.6; font-size: 11px;">• ${escapeInboxHtml(lead.channel_id)}</span>` : '';
                    const convId = lead.conversation_id || 0;
                    const ignoreBtn = showIgnoreInMenu ? `<button type="button" class="incoming-lead-menu-item" onclick="event.stopPropagation(); inboxIgnoreConversation(${convId}, '${contactName}'); closeIncomingLeadMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>Ignorar</button>` : '';
                    html += `
                        <div class="inbox-drawer-conversation ${isActive ? 'active' : ''}" 
                             data-thread-id="${escapeInboxHtml(lead.thread_id || '')}" 
                             data-conversation-id="${convId}"
                             data-channel="${escapeInboxHtml(channel)}"
                             onclick="loadInboxConversation('${threadId}', '${channel}')">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                <div class="conv-content">
                                    <div class="conv-name">
                                        <span>${contactName}</span>
                                        <span class="conv-time">${time}${unreadBadge}</span>
                                    </div>
                                    <div class="conv-preview" style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <span>${contact}</span>${channelId}
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                    <div class="incoming-lead-menu">
                                        <button type="button" class="incoming-lead-menu-toggle" onclick="event.stopPropagation(); toggleIncomingLeadMenu(this)" aria-label="Mais opções">⋮</button>
                                        <div class="incoming-lead-menu-dropdown">
                                            <button type="button" class="incoming-lead-menu-item" onclick="event.stopPropagation(); inboxOpenCreateTenantModal(${convId}, '${contactName}', '${contact}'); closeIncomingLeadMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Criar Cliente</button>
                                            ${ignoreBtn}
                                            <button type="button" class="incoming-lead-menu-item danger" onclick="event.stopPropagation(); inboxDeleteConversation(${convId}, '${contactName}'); closeIncomingLeadMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>Excluir</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="incoming-lead-actions" style="margin-top: 8px;">
                                <button type="button" class="incoming-lead-btn-primary" onclick="event.stopPropagation(); this.closest('.inbox-drawer-conversation')?.querySelector('.incoming-lead-menu-dropdown')?.classList.remove('show'); inboxOpenLinkTenantModal(${convId}, '${contactName}')">Vincular</button>
                            </div>
                        </div>
                    `;
                });
                if (threads.length > 0) {
                    html += '<div class="inbox-unlinked-separator"></div>';
                }
            }
            
            // Conversas vinculadas (threads normais) - mesma estrutura do Painel com menu ⋮
            threads.forEach(conv => {
                const isActive = InboxState.currentThreadId === conv.thread_id;
                const unreadBadge = conv.unread_count > 0 && !isActive ? `<span class="conv-unread">${conv.unread_count}</span>` : '';
                const time = formatInboxDateBrasilia(conv.last_activity);
                const threadId = (conv.thread_id || '').replace(/'/g, "\\'");
                const channel = conv.channel || 'whatsapp';
                const contactName = escapeInboxHtml(conv.contact_name || conv.tenant_name || conv.phone || 'Cliente');
                const contact = escapeInboxHtml(conv.contact || 'Número não identificado');
                const tenantName = escapeInboxHtml(conv.tenant_name || 'Sem tenant');
                const tenantId = conv.tenant_id;
                const convId = conv.conversation_id || 0;
                const channelId = conv.channel_id ? ` <span style="opacity: 0.6; font-size: 11px;">• ${escapeInboxHtml(conv.channel_id)}</span>` : (conv.channel_type ? ` <span style="opacity: 0.7;">• ${(conv.channel_type || '').toUpperCase()}</span>` : '');
                const tenantLink = (tenantId && conv.tenant_name && conv.tenant_name !== 'Sem tenant') 
                    ? ` <a href="${INBOX_BASE_URL}/tenants/view?id=${tenantId}" onclick="event.stopPropagation();" style="opacity: 0.7; font-weight: 500; color: #023A8D; cursor: pointer; text-decoration: underline; text-decoration-style: dotted;" title="Clique para ver detalhes do cliente">• ${tenantName}</a>`
                    : (!tenantId ? ' <span style="opacity: 0.7; font-size: 10px;">• Sem tenant</span>' : '');
                const line2 = channel === 'whatsapp' 
                    ? `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg><span>${contact}</span>${channelId}${tenantLink}`
                    : `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Chat Interno</span>${tenantLink}`;
                const status = conv.status || 'active';
                let statusMenuItems = '';
                if (status === 'active' || status === '') {
                    statusMenuItems = `<button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxArchiveConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>Arquivar</button><button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxIgnoreConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>Ignorar</button>`;
                } else if (status === 'archived') {
                    statusMenuItems = `<button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxReactivateConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>Desarquivar</button><button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxIgnoreConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>Ignorar</button>`;
                } else if (status === 'ignored') {
                    statusMenuItems = `<button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxReactivateConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Ativar</button><button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxArchiveConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>Arquivar</button>`;
                }
                const unlinkBtn = tenantId ? `<button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxUnlinkConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>Desvincular</button>` : '';
                html += `
                    <div class="inbox-drawer-conversation ${isActive ? 'active' : ''}" 
                         data-thread-id="${escapeInboxHtml(conv.thread_id || '')}" 
                         data-conversation-id="${convId}"
                         data-channel="${escapeInboxHtml(channel)}"
                         onclick="loadInboxConversation('${threadId}', '${channel}')">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                            <div class="conv-content">
                                <div class="conv-name">
                                    <span>${contactName}</span>
                                    <span class="conv-time">${time}${unreadBadge}</span>
                                </div>
                                <div class="conv-preview" style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                    ${line2}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                <div class="conversation-menu">
                                    <button type="button" class="conversation-menu-toggle" onclick="event.stopPropagation(); toggleConversationMenu(this)" aria-label="Mais opções">⋮</button>
                                    <div class="conversation-menu-dropdown">
                                        ${statusMenuItems}
                                        <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxOpenEditContactNameModal(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Editar nome</button>
                                        <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); inboxOpenChangeTenantModal(${convId}, '${contactName}', ${tenantId || 'null'}, '${tenantName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Alterar Cliente</button>
                                        ${unlinkBtn}
                                        <button type="button" class="conversation-menu-item danger" onclick="event.stopPropagation(); inboxDeleteConversation(${convId}, '${contactName}'); closeConversationMenu(this);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>Excluir</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            listScroll.innerHTML = html;
        }
        
        // ===== CARREGAR CONVERSA =====
        window.loadInboxConversation = async function(threadId, channel) {
            console.log('[Inbox] Carregando conversa:', threadId, channel);
            
            // Cancela fetch anterior (AbortController - mesmo padrão Fase 1)
            if (InboxState.currentLoadController) {
                InboxState.currentLoadController.abort();
            }
            InboxState.currentLoadController = new AbortController();
            
            // Atualiza estado
            InboxState.currentThreadId = threadId;
            InboxState.currentChannel = channel;
            
            // Salva no sessionStorage
            sessionStorage.setItem('inbox_selected_thread_id', threadId);
            sessionStorage.setItem('inbox_selected_channel', channel);
            
            // Atualiza visual da lista
            document.querySelectorAll('.inbox-drawer-conversation').forEach(el => {
                el.classList.toggle('active', el.dataset.threadId === threadId);
            });
            
            // Mostra painel de chat
            const chat = document.getElementById('inboxChat');
            const placeholder = document.getElementById('inboxPlaceholder');
            const messages = document.getElementById('inboxMessages');
            const header = document.getElementById('inboxChatHeader');
            
            if (placeholder) placeholder.style.display = 'none';
            if (chat) {
                chat.style.display = 'flex';
                requestAnimationFrame(() => { chat.offsetHeight; });
            }
            if (messages) messages.innerHTML = '<div class="inbox-drawer-loading">Carregando...</div>';
            
            // Reseta campo de mensagem e visibilidade mic/enviar ao trocar conversa
            const input = document.getElementById('inboxMessageInput');
            if (input) {
                input.value = '';
                if (typeof autoResizeInboxTextarea === 'function') autoResizeInboxTextarea(input);
            }
            updateInboxSendMicVisibility();
            
            // Mobile: abre painel de chat
            const drawer = document.getElementById('inboxDrawer');
            if (drawer) drawer.classList.add('chat-open');
            
            try {
                const url = INBOX_BASE_URL + '/communication-hub/thread-data?thread_id=' + threadId + '&channel=' + channel;
                const response = await fetch(url, { signal: InboxState.currentLoadController.signal });
                const result = await response.json();
                
                if (result.success && result.thread) {
                    renderInboxHeader(result.thread);
                    renderInboxMessages(result.messages || []);
                    
                    // Salva dados extras para envio de mensagens
                    const thread = result.thread;
                    // O campo 'contact' contém o número (contact_external_id formatado)
                    // Pode estar no formato: 5547996164699@c.us ou 5547996164699@lid ou só o número
                    const phoneValue = thread.contact || thread.contact_external_id || thread.phone || thread.to || '';
                    sessionStorage.setItem('inbox_selected_phone', phoneValue);
                    sessionStorage.setItem('inbox_selected_tenant_id', thread.tenant_id || '');
                    sessionStorage.setItem('inbox_selected_channel_id', thread.channel_id || '');
                    console.log('[Inbox] Dados salvos - phone:', phoneValue, 'tenant_id:', thread.tenant_id, 'channel_id:', thread.channel_id);
                    
                    // Salva marcadores para polling de novas mensagens
                    const msgs = result.messages || [];
                    if (msgs.length > 0) {
                        const lastMsg = msgs[msgs.length - 1];
                        InboxState.lastMessageTs = lastMsg.timestamp || lastMsg.created_at;
                        InboxState.lastMessageId = lastMsg.id || lastMsg.event_id;
                    } else {
                        InboxState.lastMessageTs = null;
                        InboxState.lastMessageId = null;
                    }
                    
                    // Zera badge de não lidas na lista
                    const convEl = document.querySelector(`.inbox-drawer-conversation[data-thread-id="${threadId}"]`);
                    if (convEl) {
                        const badge = convEl.querySelector('.conv-unread');
                        if (badge) badge.remove();
                    }
                } else {
                    throw new Error(result.error || 'Erro ao carregar');
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('[Inbox] Fetch cancelado (troca de conversa)');
                    return;
                }
                console.error('[Inbox] Erro:', error);
                if (messages) messages.innerHTML = '<div class="inbox-drawer-loading">Erro ao carregar conversa</div>';
            }
        };
        
        function buildInboxAudioWithTranscription(msg, media, safeUrl) {
            const eventId = (msg.id || msg.event_id || media.event_id || '').toString();
            const hasTranscription = media.transcription && media.transcription.trim();
            const transcriptionStatus = media.transcription_status || null;
            const safeEventId = eventId.replace(/'/g, "\\'");
            const transcribeSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>';
            let iconClass = 'inbox-transcribe-icon';
            let iconTitle = 'Transcrever';
            let iconOnclick = '';
            if (eventId) {
                if (hasTranscription) {
                    iconTitle = 'Ver transcrição';
                    iconOnclick = 'onclick="inboxToggleTranscriptionByIcon(this)"';
                } else if (transcriptionStatus === 'processing') {
                    iconClass += ' processing';
                    iconTitle = 'Processando...';
                } else if (transcriptionStatus === 'failed') {
                    iconTitle = 'Tentar novamente';
                    iconOnclick = 'onclick="inboxTranscribeAudio(this,\'' + safeEventId + '\')"';
                } else {
                    iconOnclick = 'onclick="inboxTranscribeAudio(this,\'' + safeEventId + '\')"';
                }
            }
            let html = '<div class="inbox-audio-container"><div class="inbox-audio-toolbar"><audio controls class="inbox-media-lazy" data-src="' + safeUrl + '"></audio><div class="inbox-audio-actions">';
            if (eventId) {
                html += '<button type="button" class="' + iconClass + '" data-event-id="' + escapeInboxHtml(eventId) + '" data-has-transcription="' + (hasTranscription ? '1' : '0') + '" title="' + escapeInboxHtml(iconTitle) + '" ' + iconOnclick + '>' + transcribeSvg + '<span class="inbox-transcription-spinner"></span></button>';
            }
            html += '</div></div>';
            if (hasTranscription) {
                html += '<div class="inbox-transcription-body" data-event-id="' + escapeInboxHtml(eventId) + '">' + escapeInboxHtml(media.transcription) + '</div>';
            }
            html += '</div>';
            return html;
        }
        
        function renderInboxHeader(thread) {
            const header = document.getElementById('inboxChatHeader');
            if (!header) return;
            
            const name = thread.contact_name || thread.phone || 'Sem nome';
            const initial = name.charAt(0).toUpperCase();
            const phone = thread.phone || '';
            
            header.innerHTML = `
                <div class="chat-avatar">${initial}</div>
                <div class="chat-info">
                    <h3>${escapeInboxHtml(name)}</h3>
                    <span>${escapeInboxHtml(phone)}</span>
                </div>
            `;
        }
        
        function renderInboxMessages(messages) {
            const container = document.getElementById('inboxMessages');
            if (!container) return;
            
            console.log('[Inbox] Renderizando mensagens:', messages?.length || 0);
            
            if (!messages || messages.length === 0) {
                container.innerHTML = '<div class="inbox-drawer-loading">Nenhuma mensagem</div>';
                return;
            }
            
            let html = '';
            messages.forEach(msg => {
                // Direção: usa campo 'direction' do backend
                const direction = msg.direction === 'outbound' ? 'outbound' : 'inbound';
                
                // Timestamp: mensagens usam formatInboxMessageTimestamp (sem conversão, já em Brasília)
                const time = msg.timestamp || msg.created_at || '';
                const formattedTime = time ? formatInboxMessageTimestamp(time) : '';
                
                // Conteúdo: backend usa 'content'
                let content = msg.content || msg.body || msg.text || '';
                let renderedContent = '';
                
                // Verifica se tem mídia (objeto 'media' do backend) - mesmo comportamento do Painel
                const media = msg.media;
                if (media && media.url) {
                    const mediaType = (media.media_type || media.type || '').toLowerCase();
                    const safeUrl = escapeInboxHtml(media.url);
                    if (mediaType === 'image' || mediaType === 'sticker') {
                        renderedContent = `<button type="button" class="inbox-media-open" data-src="${safeUrl}"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="inbox-media-thumb inbox-media-lazy" data-src="${safeUrl}" alt="Imagem" onerror="this.outerHTML='<em>[Imagem não disponível]</em>'"></button>`;
                    } else if (mediaType === 'audio' || mediaType === 'ptt' || mediaType === 'voice') {
                        renderedContent = buildInboxAudioWithTranscription(msg, media, safeUrl);
                    } else if (mediaType === 'video') {
                        renderedContent = `<video controls class="inbox-media-lazy" data-src="${safeUrl}" style="max-width: 200px; border-radius: 8px;"></video>`;
                    } else if (mediaType === 'document' || mediaType === 'file') {
                        renderedContent = `<a href="${safeUrl}" target="_blank" style="color: #023A8D; text-decoration: none;">📎 ${escapeInboxHtml(media.file_name || 'Documento')}</a>`;
                    } else {
                        renderedContent = `<a href="${safeUrl}" target="_blank" style="color: #023A8D;">📎 Mídia</a>`;
                    }
                    // Se tem legenda (caption), adiciona abaixo - igual ao Painel
                    const isAudioPlaceholder = content && /^\[(?:Á|A)udio\]$/i.test(content.trim());
                    if (content && content.trim() && !(mediaType && (mediaType === 'audio' || mediaType === 'ptt' || mediaType === 'voice') && isAudioPlaceholder)) {
                        renderedContent += `<div style="margin-top: 6px;">${escapeInboxHtml(content)}</div>`;
                    }
                } else if (content && content.trim()) {
                    // Só texto
                    renderedContent = escapeInboxHtml(content);
                } else {
                    // Sem conteúdo e sem mídia: pula mensagem (mesmo comportamento do Painel)
                    return;
                }
                
                const msgId = (msg.id || msg.event_id || '').toString();
                const msgIdAttr = msgId ? ` data-msg-id="${escapeInboxHtml(msgId)}"` : '';
                html += `
                    <div class="msg ${direction}"${msgIdAttr}>
                        ${renderedContent}
                        <div class="msg-time">${formattedTime}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            initInboxMediaLazyLoad(container);
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    container.scrollTop = container.scrollHeight;
                });
            });
        }
        
        function initInboxMediaLazyLoad(container) {
            if (!container || !container.querySelector) return;
            const lazyEls = container.querySelectorAll('.inbox-media-lazy[data-src]');
            if (lazyEls.length === 0) return;
            if (!window.InboxMediaLazyObserver) {
                window.InboxMediaLazyObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) return;
                        const el = entry.target;
                        const src = el.getAttribute('data-src');
                        if (!src) return;
                        if (el.tagName === 'IMG') {
                            el.src = src;
                            el.classList.remove('inbox-media-lazy');
                        } else if (el.tagName === 'AUDIO' || el.tagName === 'VIDEO') {
                            el.src = src;
                            el.classList.remove('inbox-media-lazy');
                        }
                        window.InboxMediaLazyObserver.unobserve(el);
                    });
                }, { root: container, rootMargin: '200px', threshold: 0.01 });
            }
            lazyEls.forEach(function(el) { window.InboxMediaLazyObserver.observe(el); });
        }
        
        // ===== ENVIAR MENSAGEM =====
        window.handleInboxInputKeypress = function(e) {
            if (e.key === 'Enter') {
                if (e.shiftKey) {
                    // Shift+Enter: quebra linha (comportamento padrão do textarea)
                    return;
                }
                e.preventDefault();
                sendInboxMessage();
            }
        };
        
        window.sendInboxMessage = async function() {
            const input = document.getElementById('inboxMessageInput');
            if (!InboxState.currentThreadId) return;
            
            const message = input ? input.value.trim() : '';
            const hasMedia = InboxMediaState.base64 !== null;
            
            if (!message && !hasMedia) return;
            
            // Limpa input imediatamente e reseta altura do textarea
            if (input) {
                input.value = '';
                if (typeof autoResizeInboxTextarea === 'function') autoResizeInboxTextarea(input);
            }
            
            // Optimistic UI: adiciona mensagem na tela antes do envio
            const container = document.getElementById('inboxMessages');
            let optimisticEl = null;
            if (container) {
                optimisticEl = document.createElement('div');
                optimisticEl.className = 'msg outbound';
                optimisticEl.setAttribute('data-inbox-optimistic', '1');
                
                let content = '';
                if (hasMedia && InboxMediaState.type === 'image') {
                    const dataSrc = `data:${InboxMediaState.mimeType};base64,${InboxMediaState.base64}`;
                    content = `<button type="button" class="inbox-media-open" data-src="${dataSrc.replace(/"/g, '&quot;')}"><img src="${dataSrc}" class="inbox-media-thumb" data-src="${dataSrc.replace(/"/g, '&quot;')}" style="max-width: 200px; border-radius: 8px;"></button>`;
                    if (message) content += `<div style="margin-top: 6px;">${escapeInboxHtml(message)}</div>`;
                } else if (hasMedia && InboxMediaState.type === 'document') {
                    content = `📎 ${InboxMediaState.fileName}`;
                } else {
                    content = escapeInboxHtml(message);
                }
                
                optimisticEl.innerHTML = `${content}<div class="msg-time">Enviando...</div>`;
                container.appendChild(optimisticEl);
                container.scrollTop = container.scrollHeight;
            }
            
            try {
                // Usa FormData pois o endpoint /communication-hub/send espera $_POST
                const formData = new FormData();
                formData.append('channel', InboxState.currentChannel);
                formData.append('to', sessionStorage.getItem('inbox_selected_phone') || '');
                formData.append('thread_id', InboxState.currentThreadId);
                formData.append('tenant_id', sessionStorage.getItem('inbox_selected_tenant_id') || '');
                formData.append('channel_id', sessionStorage.getItem('inbox_selected_channel_id') || '');
                
                if (hasMedia) {
                    // Envia mídia
                    formData.append('type', InboxMediaState.type);
                    if (message) formData.append('caption', message);
                    
                    if (InboxMediaState.type === 'image') {
                        formData.append('base64Image', InboxMediaState.base64);
                    } else {
                        formData.append('base64Document', InboxMediaState.base64);
                        formData.append('fileName', InboxMediaState.fileName);
                    }
                    
                    console.log('[Inbox] Enviando mídia:', { type: InboxMediaState.type, fileName: InboxMediaState.fileName });
                } else {
                    // Envia texto normal
                    formData.append('type', 'text');
                    formData.append('message', message);
                }
                
                const response = await fetch(INBOX_BASE_URL + '/communication-hub/send', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erro ao enviar');
                }
                
                if (hasMedia) {
                    removeInboxMediaPreview();
                }
                
                updateInboxSendMicVisibility();
                
                // Sucesso: mantém mensagem otimista, só atualiza o horário (não recarrega)
                if (optimisticEl) {
                    const timeEl = optimisticEl.querySelector('.msg-time');
                    if (timeEl) timeEl.textContent = 'agora';
                }
                
            } catch (error) {
                console.error('[Inbox] Erro ao enviar:', error);
                if (optimisticEl && optimisticEl.parentNode) {
                    optimisticEl.remove();
                }
                alert('Erro ao enviar: ' + error.message);
            }
        };
        
        // ===== POLLING =====
        function startInboxPolling() {
            if (InboxState.pollingInterval) return;
            
            // Polling da lista de conversas (15s)
            InboxState.pollingInterval = setInterval(() => {
                if (InboxState.isOpen) {
                    checkInboxUpdates();
                }
            }, 15000);
            
            // Polling de mensagens na conversa aberta (5s)
            InboxState.messagePollingInterval = setInterval(() => {
                if (InboxState.isOpen && InboxState.currentThreadId) {
                    checkInboxNewMessages();
                }
            }, 5000);
        }
        
        function stopInboxPolling() {
            if (InboxState.pollingInterval) {
                clearInterval(InboxState.pollingInterval);
                InboxState.pollingInterval = null;
            }
            if (InboxState.messagePollingInterval) {
                clearInterval(InboxState.messagePollingInterval);
                InboxState.messagePollingInterval = null;
            }
        }
        
        async function checkInboxUpdates() {
            try {
                const filterStatus = document.getElementById('inboxFilterStatus');
                const status = filterStatus ? filterStatus.value : 'active';
                const params = new URLSearchParams({ status });
                if (InboxState.lastUpdateTs) {
                    params.set('after_timestamp', InboxState.lastUpdateTs);
                }
                
                const url = INBOX_BASE_URL + '/communication-hub/check-updates?' + params.toString();
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success && result.has_updates) {
                    console.log('[Inbox] Atualizações detectadas, recarregando lista');
                    loadInboxConversations();
                }
            } catch (error) {
                console.error('[Inbox] Erro no polling:', error);
            }
        }
        
        // Verifica novas mensagens na conversa aberta
        async function checkInboxNewMessages() {
            if (!InboxState.currentThreadId || !InboxState.lastMessageTs) return;
            
            try {
                const params = new URLSearchParams({
                    thread_id: InboxState.currentThreadId,
                    after_timestamp: InboxState.lastMessageTs
                });
                if (InboxState.lastMessageId) {
                    params.set('after_event_id', InboxState.lastMessageId);
                }
                
                const url = INBOX_BASE_URL + '/communication-hub/messages/check?' + params.toString();
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success && result.has_new) {
                    console.log('[Inbox] Novas mensagens detectadas, buscando...');
                    fetchInboxNewMessages();
                }
            } catch (error) {
                // Silencioso
            }
        }
        
        // Busca novas mensagens e adiciona à conversa
        let __fetchInboxNewMessagesInProgress = false;
        async function fetchInboxNewMessages() {
            if (!InboxState.currentThreadId || !InboxState.lastMessageTs) return;
            if (__fetchInboxNewMessagesInProgress) return;
            __fetchInboxNewMessagesInProgress = true;
            try {
                const params = new URLSearchParams({
                    thread_id: InboxState.currentThreadId,
                    after_timestamp: InboxState.lastMessageTs
                });
                if (InboxState.lastMessageId) {
                    params.set('after_event_id', InboxState.lastMessageId);
                }
                
                const url = INBOX_BASE_URL + '/communication-hub/messages/new?' + params.toString();
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success && result.messages && result.messages.length > 0) {
                    console.log('[Inbox] Novas mensagens:', result.messages.length);
                    appendInboxMessages(result.messages);
                    
                    // Atualiza marcadores
                    const lastMsg = result.messages[result.messages.length - 1];
                    if (lastMsg) {
                        InboxState.lastMessageTs = lastMsg.timestamp || lastMsg.created_at;
                        InboxState.lastMessageId = lastMsg.id || lastMsg.event_id || (lastMsg.media && lastMsg.media.event_id);
                    }
                }
            } catch (error) {
                console.error('[Inbox] Erro ao buscar novas mensagens:', error);
            } finally {
                __fetchInboxNewMessagesInProgress = false;
            }
        }
        
        // Adiciona novas mensagens ao container (sem recarregar tudo)
        function appendInboxMessages(messages) {
            const container = document.getElementById('inboxMessages');
            if (!container || !messages || messages.length === 0) return;
            // Evita duplicação: quando o poll retorna mensagens outbound, remove as otimistas (a mensagem real já vem na resposta)
            const hasOutbound = (messages || []).some(m => m.direction === 'outbound');
            if (hasOutbound) {
                container.querySelectorAll('[data-inbox-optimistic="1"]').forEach(el => el.remove());
            }
            messages.forEach(msg => {
                const media = msg.media;
                const mediaType = media && (media.media_type || media.type) ? (media.media_type || media.type).toLowerCase() : '';
                const isOutgoingAudio = msg.direction === 'outbound' && (mediaType === 'audio' || mediaType === 'ptt' || mediaType === 'voice');
                const msgId = (msg.id || msg.event_id || (media && media.event_id) || '').toString();
                if (msgId && Array.from(container.querySelectorAll('.msg[data-msg-id]')).some(el => el.getAttribute('data-msg-id') === msgId)) return;
                let dedupeKey = null;
                if (!msgId) {
                    if (isOutgoingAudio && media && media.url) {
                        dedupeKey = 'outbound|audio|' + (media.url || '').slice(-120) + '|' + (msg.timestamp || msg.created_at || '').toString().slice(0, 19);
                    } else {
                        dedupeKey = (msg.direction || '') + '|' + (msg.content || '').slice(0, 100) + '|' + (msg.timestamp || msg.created_at || '');
                    }
                }
                if (dedupeKey && Array.from(container.querySelectorAll('.msg[data-dedupe-key]')).some(el => el.getAttribute('data-dedupe-key') === dedupeKey)) return;
                const direction = msg.direction === 'outbound' ? 'outbound' : 'inbound';
                const time = msg.timestamp || msg.created_at || '';
                const formattedTime = time ? formatInboxMessageTimestamp(time) : '';
                
                let content = msg.content || msg.body || msg.text || '';
                let renderedContent = '';
                
                if (media && media.url) {
                    const mediaType = (media.media_type || media.type || '').toLowerCase();
                    const safeUrl = escapeInboxHtml(media.url);
                    if (mediaType === 'image' || mediaType === 'sticker') {
                        renderedContent = `<button type="button" class="inbox-media-open" data-src="${safeUrl}"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="inbox-media-thumb inbox-media-lazy" data-src="${safeUrl}" alt="Imagem" onerror="this.outerHTML='<em>[Imagem não disponível]</em>'"></button>`;
                    } else if (mediaType === 'audio' || mediaType === 'ptt' || mediaType === 'voice') {
                        renderedContent = buildInboxAudioWithTranscription(msg, media, safeUrl);
                    } else if (mediaType === 'video') {
                        renderedContent = `<video controls class="inbox-media-lazy" data-src="${safeUrl}" style="max-width: 200px; border-radius: 8px;"></video>`;
                    } else {
                        renderedContent = `<a href="${safeUrl}" target="_blank" style="color: #023A8D;">📎 Mídia</a>`;
                    }
                    if (content && content.trim()) {
                        renderedContent += `<div style="margin-top: 6px;">${escapeInboxHtml(content)}</div>`;
                    }
                } else if (content && content.trim()) {
                    renderedContent = escapeInboxHtml(content);
                } else {
                    renderedContent = '<em style="color: #999;">[Mídia]</em>';
                }
                
                const msgEl = document.createElement('div');
                msgEl.className = `msg ${direction}`;
                if (msgId) msgEl.setAttribute('data-msg-id', msgId);
                if (dedupeKey) msgEl.setAttribute('data-dedupe-key', dedupeKey);
                msgEl.innerHTML = `${renderedContent}<div class="msg-time">${formattedTime}</div>`;
                container.appendChild(msgEl);
            });
            
            // Scroll para última mensagem (após DOM pronto)
            requestAnimationFrame(function() {
                container.scrollTop = container.scrollHeight;
            });
            initInboxMediaLazyLoad(container);
        }
        
        // ===== BADGE NO HEADER =====
        function updateInboxBadge() {
            const badge = document.getElementById('inboxBadge');
            if (!badge) return;
            
            let totalUnread = 0;
            InboxState.conversations.forEach(conv => {
                totalUnread += parseInt(conv.unread_count || 0, 10);
            });
            
            if (totalUnread > 0) {
                badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Atualiza badge periodicamente mesmo com drawer fechado
        setInterval(() => {
            if (!InboxState.isOpen) {
                fetchUnreadCount();
            }
        }, 60000); // 1 minuto
        
        async function fetchUnreadCount() {
            try {
                const url = INBOX_BASE_URL + '/communication-hub/conversations-list?status=active';
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success) {
                    InboxState.conversations = result.threads || [];
                    InboxState.incomingLeads = result.incoming_leads || [];
                    updateInboxBadge();
                }
            } catch (error) {
                // Silencioso
            }
        }
        
        // Carrega badge inicial após 3 segundos
        setTimeout(fetchUnreadCount, 3000);
        
        // ===== HELPERS =====
        /**
         * Formata timestamp de mensagens (exibe direto, SEM conversão de timezone).
         * Usado para: communication_events.created_at (armazenado em Brasília).
         * Mesmo comportamento do Painel: formatMessageTimestamp.
         */
        function formatInboxMessageTimestamp(dateStr) {
            if (!dateStr || dateStr === 'now') return 'Agora';
            try {
                const match = dateStr.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
                if (match) {
                    const [, year, month, day, hour, minute] = match;
                    return `${day}/${month} ${hour}:${minute}`;
                }
                const isoMatch = dateStr.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
                if (isoMatch) {
                    const [, year, month, day, hour, minute] = isoMatch;
                    return `${day}/${month} ${hour}:${minute}`;
                }
                return 'Agora';
            } catch (e) {
                return 'Agora';
            }
        }
        /**
         * Formata data para lista de conversas (mesmo comportamento do Painel de Comunicação).
         * Usa formatDateBrasilia: "Agora" ou "dd/mm HH:mm" (fuso America/Sao_Paulo).
         */
        function formatInboxDateBrasilia(dateStr) {
            if (!dateStr || dateStr === 'now') return 'Agora';
            try {
                let isoStr = dateStr;
                if (!dateStr.includes('T') && !dateStr.includes('Z') && !dateStr.includes('+') && !dateStr.includes('-', 10)) {
                    isoStr = dateStr.replace(' ', 'T') + 'Z';
                }
                const dateTime = new Date(isoStr);
                if (isNaN(dateTime.getTime())) return 'Agora';
                const options = { timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false };
                const formatted = dateTime.toLocaleString('pt-BR', options);
                return formatted.replace(',', '').replace(/\/\d{4}/, '');
            } catch (e) {
                return 'Agora';
            }
        }
        
        function escapeInboxHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    })();
    </script>
</body>
</html>
