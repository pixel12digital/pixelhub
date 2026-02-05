<?php
/**
 * Painel Operacional de Comunicação
 * Interface onde operadores enviam mensagens e gerenciam conversas
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<style>
/* ============================================================================
   Layout WhatsApp-like - Comunicação Hub
   ============================================================================ */

/* Previne scroll do body na página de comunicação */
body.communication-hub-page {
    overflow: hidden;
    height: 100vh;
}

/* Container principal com altura fixa (viewport) */
.communication-hub-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 140px); /* Ajusta conforme altura do header + topbar compacto */
    min-height: 500px;
    max-height: calc(100vh - 140px);
    overflow: hidden;
    background: #f0f2f5;
    margin: -20px -20px 0 -20px; /* Remove padding do container pai */
    padding: 0;
}

/* Ajuste para mobile */
@media (max-width: 767px) {
    .communication-hub-container {
        height: calc(100vh - 120px);
        max-height: calc(100vh - 120px);
    }
    
    /* Usa dvh no mobile quando disponível (melhor para teclado virtual) */
    @supports (height: 100dvh) {
        .communication-hub-container {
            height: calc(100dvh - 120px);
            max-height: calc(100dvh - 120px);
        }
    }
}

/* Topbar compacta (título + filtros) */
.communication-topbar {
    flex-shrink: 0;
    background: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    padding: 10px 16px;
    z-index: 10;
}

.communication-topbar h2 {
    margin: 0 0 4px 0;
    font-size: 18px;
    font-weight: 600;
    color: #111b21;
    line-height: 1.2;
}

.communication-topbar p {
    margin: 0 0 8px 0;
    font-size: 12px;
    color: #667781;
    opacity: 0.8;
}

/* Estatísticas colapsáveis - Layout operacional */
.communication-stats {
    display: none; /* Oculto por padrão */
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 12px;
    padding: 10px 0;
}

.communication-stats.expanded {
    display: grid;
}

.communication-stats-item {
    text-align: center;
    padding: 14px 12px;
    background: #023A8D;
    color: white;
    border-radius: 8px;
    font-size: 11px;
    transition: all 0.2s;
    cursor: default;
}

/* Estilo discreto quando valor é 0 */
.communication-stats-item.muted {
    background: #e9ecef;
    color: #6c757d;
}

.communication-stats-item.muted .number {
    color: #adb5bd;
}

.communication-stats-item .number {
    font-size: 28px;
    font-weight: 700;
    display: block;
    margin-bottom: 2px;
    line-height: 1;
}

.communication-stats-item .label {
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    opacity: 0.9;
}

/* Indicador de unidade (para tempo) */
.communication-stats-item .unit {
    font-size: 14px;
    font-weight: 400;
    opacity: 0.8;
}

.communication-stats-toggle {
    background: none;
    border: none;
    color: #667781;
    font-size: 12px;
    cursor: pointer;
    padding: 4px 8px;
    margin-bottom: 8px;
}

.communication-stats-toggle:hover {
    color: #023A8D;
}

/* Filtros compactos */
.communication-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
    align-items: end;
    padding-top: 4px;
}

.communication-filters label {
    display: block;
    margin-bottom: 4px;
    font-weight: 500;
    font-size: 12px;
    color: #667781;
    opacity: 0.75;
}

.communication-filters select,
.communication-filters button {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    font-size: 13px;
    background: white;
    height: 36px;
}

.communication-filters button {
    background: #023A8D;
    color: white;
    border: none;
    font-weight: 600;
    cursor: pointer;
}

.communication-filters button:hover {
    background: #022a6d;
}

/* Botão Nova Conversa */
.btn-nova-conversa {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #25d366 !important;
    color: white !important;
    border: none;
    padding: 7px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    height: 36px;
    white-space: nowrap;
    transition: background 0.2s;
}

.btn-nova-conversa:hover {
    background: #1fb855 !important;
}

.btn-nova-conversa svg {
    flex-shrink: 0;
}

/* Dropdown pesquisável de Cliente */
.searchable-dropdown {
    position: relative;
    width: 100%;
}

.searchable-dropdown-input {
    width: 100%;
    padding: 7px 30px 7px 10px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    font-size: 13px;
    background: white;
    height: 36px;
    box-sizing: border-box;
    cursor: pointer;
}

.searchable-dropdown-input:focus {
    outline: none;
    border-color: #023A8D;
    box-shadow: 0 0 0 2px rgba(2, 58, 141, 0.1);
}

.searchable-dropdown-input::placeholder {
    color: #667781;
}

.searchable-dropdown-arrow {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #667781;
    font-size: 10px;
}

.searchable-dropdown-list {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    margin-top: 2px;
}

.searchable-dropdown-list.show {
    display: block;
}

.searchable-dropdown-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.1s;
}

.searchable-dropdown-item:last-child {
    border-bottom: none;
}

.searchable-dropdown-item:hover {
    background: #f5f6f6;
}

.searchable-dropdown-item.selected {
    background: #e7f3ff;
}

.searchable-dropdown-item-name {
    font-weight: 500;
    font-size: 13px;
    color: #111b21;
    margin-bottom: 2px;
}

.searchable-dropdown-item-detail {
    font-size: 11px;
    color: #667781;
}

.searchable-dropdown-empty {
    padding: 12px;
    text-align: center;
    color: #667781;
    font-size: 12px;
}

.searchable-dropdown-item mark {
    background: #fff3cd;
    padding: 0 2px;
    border-radius: 2px;
}

/* Corpo principal (2 colunas desktop, 1 coluna mobile) */
.communication-body {
    flex: 1;
    display: flex;
    min-height: 0;
    overflow: hidden;
    position: relative;
    background: #f0f2f5;
}

/* Mobile: panes para transição */
@media (max-width: 767px) {
    .communication-body {
        position: relative;
        overflow: hidden;
    }
    
    .pane-list,
    .pane-thread {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        transition: transform 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
        background: white;
    }
    
    .pane-list {
        transform: translateX(0);
        z-index: 1;
    }
    
    .pane-thread {
        transform: translateX(100%);
        z-index: 2;
    }
    
    .communication-body.view-thread .pane-list {
        transform: translateX(-100%);
    }
    
    .communication-body.view-thread .pane-thread {
        transform: translateX(0);
    }
    
    .communication-stats {
        display: none !important; /* Sempre oculto no mobile */
    }
}

/* Desktop: 2 colunas fixas */
@media (min-width: 768px) {
    .communication-body {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 0;
        border-top: 1px solid #e4e6eb;
    }
    
    .pane-list,
    .pane-thread {
        position: relative;
        transform: none !important;
    }
    
    .communication-stats {
        display: none; /* Oculto por padrão, pode expandir */
    }
}

/* Lista de conversas (coluna esquerda) */
.conversation-list-pane {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: white;
    border-right: 1px solid rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.conversation-list-header {
    flex-shrink: 0;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    background: #f0f2f5;
}

.conversation-list-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #111b21;
    line-height: 1.2;
}

.conversation-list-scroll {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 6px 8px;
    min-height: 0;
}

/* Scrollbar discreta */
.conversation-list-scroll::-webkit-scrollbar {
    width: 6px;
}

.conversation-list-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.conversation-list-scroll::-webkit-scrollbar-thumb {
    background: #c4c4c4;
    border-radius: 3px;
}

.conversation-list-scroll::-webkit-scrollbar-thumb:hover {
    background: #a0a0a0;
}

/* Item de conversa */
.conversation-item {
    position: relative; /* CORREÇÃO: necessário para posicionar ícones ::before/::after corretamente */
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
    margin-bottom: 2px;
    border: 1px solid transparent;
}

.conversation-item:hover {
    background: #f5f6f6;
}

.conversation-item.active {
    background: #e7f3ff;
    border-color: #007bff;
}

/* ============================================================================
   Estilos para Conversas Não Vinculadas (antiga seção "Leads Entrantes")
   ============================================================================ */

/* Seção de conversas não vinculadas - header discreto */
.unlinked-conversations-section {
    padding: 10px 8px 8px 8px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 12px;
    border: 1px solid #e5e7eb;
}

.unlinked-conversations-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.unlinked-conversations-title {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
}

.unlinked-conversations-icon {
    width: 16px;
    height: 16px;
    color: #6B7280;
    flex-shrink: 0;
}

.unlinked-conversations-badge {
    background: #e5e7eb;
    color: #374151;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.unlinked-conversations-description {
    margin: 0;
    font-size: 11px;
    color: #6B7280;
    line-height: 1.4;
}

/* Card de conversa não vinculada - estilo neutro */
.incoming-lead-item {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    padding: 10px 12px !important;
    margin-bottom: 6px !important;
}

.incoming-lead-item:hover {
    background: #f9fafb !important;
    border-color: #d1d5db !important;
}

/* Container de ações - layout flexível */
.incoming-lead-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    align-items: center;
    justify-content: flex-end;
}

/* Botão principal (Vincular) - estilo secundário discreto */
.incoming-lead-btn-primary {
    padding: 4px 10px;
    background: transparent;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
    transition: all 0.15s;
}

.incoming-lead-btn-primary:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    color: #111827;
}

/* Menu de três pontos */
.incoming-lead-menu {
    position: relative;
    display: inline-block;
}

.incoming-lead-menu-toggle {
    padding: 4px 8px;
    background: transparent;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    color: #6B7280;
    line-height: 1;
    width: 28px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}

.incoming-lead-menu-toggle:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    color: #374151;
}

.incoming-lead-menu-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 4px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 100;
    min-width: 140px;
    padding: 4px 0;
}

.incoming-lead-menu-dropdown.show {
    display: block;
}

.incoming-lead-menu-item {
    display: block;
    width: 100%;
    padding: 8px 12px;
    background: transparent;
    border: none;
    text-align: left;
    cursor: pointer;
    font-size: 12px;
    color: #374151;
    transition: background 0.15s;
}

.incoming-lead-menu-item:hover {
    background: #f3f4f6;
}

.incoming-lead-menu-item.danger {
    color: #dc2626;
}

.incoming-lead-menu-item.danger:hover {
    background: #fef2f2;
    color: #b91c1c;
}

/* Botões ocultos (mantidos no DOM para JS) */
.incoming-lead-hidden-btn {
    display: none;
}

/* Menu de ações para conversas vinculadas */
.conversation-menu {
    position: relative;
    display: inline-block;
}

.conversation-menu-toggle {
    padding: 4px 8px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    color: #6B7280;
    line-height: 1;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    opacity: 0;
}

.conversation-item:hover .conversation-menu-toggle {
    opacity: 1;
}

.conversation-menu-toggle:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}

.conversation-menu-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 4px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 100;
    min-width: 140px;
    padding: 4px 0;
}

.conversation-menu-dropdown.show {
    display: block;
}

.conversation-menu-item {
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
    transition: background 0.15s;
}

.conversation-menu-item:hover {
    background: #f3f4f6;
}

.conversation-menu-item.danger {
    color: #dc2626;
}

.conversation-menu-item.danger:hover {
    background: #fef2f2;
    color: #b91c1c;
}

/* Indicador visual para conversas arquivadas/ignoradas */
.conversation-item.conversation-archived {
    opacity: 0.7;
    border-left: 3px solid #f59e0b;
}

.conversation-item.conversation-ignored {
    opacity: 0.6;
    border-left: 3px solid #9ca3af;
}

.conversation-item.conversation-archived::after,
.conversation-item.conversation-ignored::after {
    content: attr(data-status-label);
    position: absolute;
    top: 4px;
    right: 40px;
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: 500;
}

/* Ícones de status removidos - borda colorida já indica o status */
/* .conversation-item.conversation-archived::before - REMOVIDO (emoji colorido) */
/* .conversation-item.conversation-ignored::before - REMOVIDO (emoji colorido) */

/* Painel de conversa (coluna direita) */
.conversation-thread-pane {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #f0f2f5;
    position: relative;
    overflow: hidden;
}

/* Header da conversa (fixo) */
.conversation-thread-header {
    flex-shrink: 0;
    padding: 10px 14px;
    background: #f0f2f5;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 5;
    position: relative;
}

.conversation-thread-header.scrolled {
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.conversation-thread-header .back-button {
    display: none; /* Só aparece no mobile */
    background: none;
    border: none;
    padding: 6px;
    cursor: pointer;
    margin-right: 8px;
    color: #54656f;
    flex-shrink: 0;
}

@media (max-width: 767px) {
    .conversation-thread-header .back-button {
        display: block;
    }
}

.conversation-thread-header .info {
    flex: 1;
    min-width: 0;
}

.conversation-thread-header .info strong {
    display: block;
    font-size: 14px;
    color: #111b21;
    margin-bottom: 1px;
    line-height: 1.2;
    font-weight: 600;
}

.conversation-thread-header .info small {
    font-size: 12px;
    color: #667781;
    opacity: 0.75;
}

.conversation-thread-header .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.conversation-thread-header .new-message-header-btn {
    background: transparent;
    border: 1px solid rgba(2, 58, 141, 0.2);
    color: #023A8D;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
    min-width: 36px;
    height: 36px;
}

.conversation-thread-header .new-message-header-btn:hover {
    background: rgba(2, 58, 141, 0.08);
    border-color: rgba(2, 58, 141, 0.3);
}

.conversation-thread-header .new-message-header-btn:active {
    background: rgba(2, 58, 141, 0.12);
}

/* Ajuste mobile para botão */
@media (max-width: 767px) {
    .conversation-thread-header .new-message-header-btn {
        padding: 6px 8px;
        min-width: 40px;
        height: 40px;
    }
    
    .conversation-thread-header .new-message-header-btn svg {
        width: 20px;
        height: 20px;
    }
}

.conversation-thread-header .status {
    font-size: 11px;
    color: #667781;
    opacity: 0.75;
    flex-shrink: 0;
}

/* Shell visual para controlar largura total da thread (scrollbar acompanha) */
.commhub-thread-shell {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

/* Container de mensagens (scrollável) - mantém comportamento de scroll intacto */
.conversation-messages-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 0;
    min-height: 0;
    background: #f0f2f5;
}

/* Wrapper interno para mensagens (sem padding, shell controla) */
.commhub-messages-inner {
    width: 100%;
    box-sizing: border-box;
}

.conversation-messages-container::-webkit-scrollbar {
    width: 6px;
}

.conversation-messages-container::-webkit-scrollbar-track {
    background: transparent;
}

.conversation-messages-container::-webkit-scrollbar-thumb {
    background: #c4c4c4;
    border-radius: 3px;
}

/* Composer (fixo no rodapé) */
.conversation-composer {
    flex-shrink: 0;
    padding: 0;
    background: #f0f2f5;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    z-index: 5;
    position: sticky;
    bottom: 0;
}

.hub-composer-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 10px 20px;
    width: 100%;
    box-sizing: border-box;
}

.hub-composer {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(0,0,0,.12);
    border-radius: 18px;
    padding: 10px 12px;
    background: #fff;
    width: 100%;
    margin: 0;
    position: relative;
}

.hub-text {
    flex: 1;
    border: 0;
    outline: 0;
    resize: none;
    padding: 0;
    line-height: 20px;
    max-height: 120px;
    background: transparent;
    font-family: inherit;
    font-size: 14px;
    min-height: 20px;
    box-sizing: border-box;
}

.hub-text:focus {
    outline: none;
}

.hub-icon-btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #54656f;
    flex-shrink: 0;
}

.hub-icon-btn:hover {
    background: rgba(0,0,0,.06);
}

.hub-icon-btn svg {
    display: block;
}

.hub-send {
    background: rgba(0,0,0,.06);
    color: #023A8D;
}

.hub-send:hover {
    background: rgba(0,0,0,.10);
}

/* Estado Recording: Timer + bolinha */
.hub-rec-status {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.hub-rec-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #d93025;
    animation: hub-rec-pulse 1s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes hub-rec-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.hub-rec-time {
    font-variant-numeric: tabular-nums;
    min-width: 48px;
    color: #54656f;
    font-size: 14px;
    font-weight: 500;
}

.hub-rec-max {
    color: #999;
    font-size: 12px;
    margin-left: 2px;
}

/* Estado Preview: Player */
.hub-audio-preview {
    flex: 1;
    height: 36px;
    min-width: 200px;
    max-width: 100%;
}

.hub-audio-preview::-webkit-media-controls-panel {
    background-color: #fff;
}

.hub-audio-preview::-webkit-media-controls-play-button,
.hub-audio-preview::-webkit-media-controls-current-time-display,
.hub-audio-preview::-webkit-media-controls-time-remaining-display,
.hub-audio-preview::-webkit-media-controls-timeline {
    color: #54656f;
}

/* Estado Sending */
.hub-sending {
    flex: 1;
    text-align: center;
    color: #54656f;
    font-size: 14px;
    font-style: italic;
}

.hub-rec-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #d93025;
    animation: hub-rec-pulse 1s ease-in-out infinite;
}

@keyframes hub-rec-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.hub-rec-time {
    font-variant-numeric: tabular-nums;
    min-width: 48px;
    color: #54656f;
    font-size: 14px;
    font-weight: 500;
}

/* Garante que elementos com atributo hidden desapareçam mesmo se houver display:flex */
[hidden] {
    display: none !important;
}

/* Redundante (opcional), só pra garantir nesses ids */
#hubText[hidden],
#hubRecStatus[hidden],
#hubAudioPreview[hidden],
#btnRecStop[hidden],
#btnRecCancel[hidden],
#btnReviewCancel[hidden],
#btnReviewRerecord[hidden],
#btnReviewSend[hidden],
#hubSending[hidden],
#hubRecMax[hidden] {
    display: none !important;
}

/* Placeholder quando não há conversa selecionada */
.conversation-placeholder {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f2f5;
    color: #667781;
    text-align: center;
    padding: 40px;
}

/* Badge de novas mensagens */
.new-messages-badge {
    position: absolute;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    background: #023A8D;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    display: none;
}

.new-messages-badge.visible {
    display: block;
}

/* Skeleton loading */
.skeleton-message {
    margin-bottom: 15px;
    padding: 12px 16px;
    background: white;
    border-radius: 8px;
    height: 60px;
    animation: skeleton-loading 1.5s ease-in-out infinite;
}

@keyframes skeleton-loading {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Safe area para iPhone */
@supports (padding: max(0px)) {
    .hub-composer-wrap {
        padding-bottom: max(10px, env(safe-area-inset-bottom));
    }
}

/* Ajuste mobile para composer e mensagens */
@media (max-width: 767px) {
    .commhub-thread-shell {
        max-width: 100%;
        padding: 0 16px;
    }
    
    .hub-composer-wrap {
        padding: 10px 16px;
        max-width: 100%;
    }
    
    .hub-text {
        font-size: 16px; /* Evita zoom no iOS */
    }
    
    .conversation-messages-container {
        padding: 10px 0;
    }
    
    /* Mensagens mais largas no mobile */
    .message-bubble [style*="max-width: 70%"],
    .message-bubble [style*="max-width: 72%"] {
        max-width: 88% !important;
    }
}

/* ==========================================================================
   THUMBNAIL de Mídia (estilo WhatsApp) - REGRAS ISOLADAS
   
   PRINCÍPIO: Imagem NUNCA pode ter width E height definidos ao mesmo tempo
   (exceto com object-fit que compensa). Aqui usamos approach simples:
   só max-width, height fica auto = proporção garantida.
   ========================================================================== */

/* Container do thumbnail - apenas estrutural */
.hub-media-open {
    background: transparent;
    border: none;
    padding: 0;
    margin: 0;
    cursor: pointer;
    display: inline-block;
    border-radius: 8px;
    overflow: hidden;
    line-height: 0; /* Remove espaço extra de inline */
}

/* Imagem thumbnail - PROPORCIONAL (sem min-width na imagem!) */
.hub-media-thumb {
    display: block;
    /* REGRA DE OURO: só max-width + height auto = NUNCA distorce */
    max-width: 280px;
    height: auto;
    /* Visual */
    border-radius: 8px;
    background: #f0f0f0;
}

/* Hover sutil */
.hub-media-open:hover .hub-media-thumb {
    filter: brightness(0.95);
}

/* Placeholder de erro */
.hub-media-open .img-error-placeholder {
    display: none;
    width: 150px;
    height: 100px;
    background: #f0f0f0;
    border-radius: 8px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* ========== TRANSCRIÇÃO DE ÁUDIO (estilo discreto) ========== */

/* Container do player com menu */
.audio-player-container {
    display: flex;
    align-items: center;
    gap: 4px;
}

.audio-player-container audio {
    flex: 1;
    max-width: 240px;
}

/* Menu de 3 pontinhos */
.audio-menu-wrapper {
    position: relative;
}

.audio-menu-btn {
    background: transparent;
    border: none;
    font-size: 16px;
    color: #999;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 4px;
    line-height: 1;
}

.audio-menu-btn:hover {
    background: rgba(0,0,0,0.05);
    color: #666;
}

/* Botão de transcrição - ícone discreto */
.audio-transcribe-btn {
    background: transparent;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
}

.audio-transcribe-btn:hover {
    background: rgba(0,0,0,0.05);
    color: #666;
}

.audio-menu-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    min-width: 140px;
    z-index: 100;
}

.audio-menu-dropdown.open {
    display: block;
}

.audio-menu-dropdown button {
    display: block;
    width: 100%;
    padding: 8px 12px;
    background: none;
    border: none;
    text-align: left;
    font-size: 13px;
    color: #333;
    cursor: pointer;
}

.audio-menu-dropdown button:hover {
    background: #f5f5f5;
}

/* Accordion de transcrição (discreto, com chevron) */
.transcription-accordion {
    margin-top: 6px;
}

.transcription-toggle {
    display: flex;
    align-items: center;
    gap: 4px;
    background: none;
    border: none;
    padding: 4px 0;
    cursor: pointer;
    font-size: 11px;
    color: #666;
}

.transcription-toggle:hover {
    color: #333;
}

.transcription-chevron {
    font-size: 10px;
    transition: transform 0.15s;
}

.transcription-accordion[data-open="true"] .transcription-chevron {
    transform: rotate(90deg);
}

.transcription-label {
    font-weight: 500;
}

.transcription-content {
    display: none;
    margin-top: 4px;
    padding: 8px 10px;
    background: #f8f8f8;
    border-radius: 6px;
    font-size: 12px;
    color: #333;
    line-height: 1.5;
    white-space: pre-wrap;
}

.transcription-accordion[data-open="true"] .transcription-content {
    display: block;
}

/* Badges de status (discretos, monocromáticos) */
.transcription-status-badge {
    margin-top: 6px;
    font-size: 10px;
    color: #888;
    display: flex;
    align-items: center;
    gap: 4px;
}

.transcription-status-badge.failed {
    color: #999;
}

.transcription-status-badge.failed:hover {
    color: #666;
    text-decoration: underline;
}

/* Spinner (discreto) */
.transcription-spinner {
    display: inline-block;
    width: 8px;
    height: 8px;
    border: 1.5px solid #ccc;
    border-top-color: #888;
    border-radius: 50%;
    animation: transcription-spin 0.8s linear infinite;
}

@keyframes transcription-spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="communication-hub-container">
    <!-- Topbar compacta -->
    <div class="communication-topbar">
        <h2>Painel de Comunicação</h2>
        <p>Gerencie conversas, envie mensagens e responda clientes em tempo real</p>
        
        <!-- Estatísticas colapsáveis - Métricas de produtividade -->
        <button class="communication-stats-toggle" onclick="toggleStats()" id="stats-toggle">
            <span id="stats-toggle-text">Mostrar estatísticas</span>
        </button>
        <?php
        // Formata tempo do mais antigo pendente
        $oldestMinutes = $stats['oldest_pending_minutes'] ?? 0;
        if ($oldestMinutes >= 60) {
            $oldestFormatted = round($oldestMinutes / 60) . ' h';
        } else {
            $oldestFormatted = $oldestMinutes . ' min';
        }
        ?>
        <div class="communication-stats" id="communication-stats">
            <div class="communication-stats-item <?= ($stats['pending_to_respond'] ?? 0) == 0 ? 'muted' : '' ?>">
                <span class="number"><?= $stats['pending_to_respond'] ?? 0 ?></span>
                <span class="label">Pendentes p/ responder</span>
            </div>
            <div class="communication-stats-item <?= ($stats['new_today'] ?? 0) == 0 ? 'muted' : '' ?>">
                <span class="number"><?= $stats['new_today'] ?? 0 ?></span>
                <span class="label">Novas hoje</span>
            </div>
            <div class="communication-stats-item <?= $oldestMinutes == 0 ? 'muted' : '' ?>">
                <span class="number"><?= $oldestMinutes == 0 ? '—' : $oldestFormatted ?></span>
                <span class="label">Mais antigo pendente</span>
            </div>
        </div>
        
        <!-- Filtros compactos -->
        <form method="GET" action="<?= pixelhub_url('/communication-hub') ?>" class="communication-filters" id="communication-filters-form">
            <div>
                <label>Canal</label>
                <select name="channel" id="filter-channel" onchange="onChannelFilterChange()">
                    <option value="all" <?= ($filters['channel'] === 'all') ? 'selected' : '' ?>>Todos</option>
                    <option value="whatsapp" <?= ($filters['channel'] === 'whatsapp') ? 'selected' : '' ?>>WhatsApp</option>
                    <option value="chat" <?= ($filters['channel'] === 'chat') ? 'selected' : '' ?>>Chat Interno</option>
                </select>
            </div>
            <div id="session-filter-container" style="<?= ($filters['channel'] !== 'whatsapp') ? 'display: none;' : '' ?>">
                <label>Sessão (WhatsApp)</label>
                <select name="session_id" id="filter-session" onchange="onSessionFilterChange()">
                    <option value="">Todas as sessões</option>
                    <?php foreach ($whatsapp_sessions as $session): ?>
                        <option value="<?= htmlspecialchars($session['id']) ?>" <?= ($filters['session_id'] === $session['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($session['name']) ?>
                            <?php if ($session['status'] === 'connected'): ?> ●<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Cliente</label>
                <div class="searchable-dropdown" id="clienteDropdown">
                    <input type="text" 
                           class="searchable-dropdown-input" 
                           id="clienteSearchInput"
                           placeholder="Buscar cliente..."
                           value="<?= $filters['tenant_id'] ? htmlspecialchars($tenants[array_search($filters['tenant_id'], array_column($tenants, 'id'))]['name'] ?? 'Todos') : 'Todos' ?>"
                           autocomplete="off">
                    <input type="hidden" name="tenant_id" id="clienteTenantId" value="<?= htmlspecialchars($filters['tenant_id'] ?? '') ?>">
                    <span class="searchable-dropdown-arrow">▼</span>
                    <div class="searchable-dropdown-list" id="clienteDropdownList">
                        <div class="searchable-dropdown-item" data-value="" data-name="Todos">
                            <div class="searchable-dropdown-item-name">Todos</div>
                            <div class="searchable-dropdown-item-detail">Exibir todas as conversas</div>
                        </div>
                        <?php foreach ($tenants as $tenant): ?>
                            <div class="searchable-dropdown-item" 
                                 data-value="<?= $tenant['id'] ?>" 
                                 data-name="<?= htmlspecialchars($tenant['name']) ?>"
                                 data-search="<?= htmlspecialchars(strtolower($tenant['name'])) ?>">
                                <div class="searchable-dropdown-item-name"><?= htmlspecialchars($tenant['name']) ?></div>
                                <div class="searchable-dropdown-item-detail">ID: <?= $tenant['id'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div>
                <label>Status</label>
                <select name="status" id="filter-status" onchange="onStatusFilterChange()">
                    <option value="active" <?= ($filters['status'] === 'active') ? 'selected' : '' ?>>Ativas</option>
                    <option value="archived" <?= ($filters['status'] === 'archived') ? 'selected' : '' ?>>Arquivadas</option>
                    <option value="ignored" <?= ($filters['status'] === 'ignored') ? 'selected' : '' ?>>Ignoradas</option>
                    <option value="all" <?= ($filters['status'] === 'all') ? 'selected' : '' ?>>Todas</option>
                </select>
            </div>
            <div>
                <button type="button" onclick="openNewMessageModal()" class="btn-nova-conversa" title="Iniciar nova conversa">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Nova Conversa
                </button>
            </div>
        </form>
    </div>

    <!-- Corpo principal -->
    <div class="communication-body" id="communication-body">
        <!-- Pane: Lista de Conversas -->
        <div class="pane-list conversation-list-pane">
            <div class="conversation-list-header">
                <h3>Conversas</h3>
            </div>
            <div class="conversation-list-scroll">
                <!-- Seção: Conversas Não Vinculadas -->
                <?php if (!empty($incoming_leads)): ?>
                    <div class="unlinked-conversations-section">
                        <div class="unlinked-conversations-header">
                            <h4 class="unlinked-conversations-title">
                                <svg class="unlinked-conversations-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                Conversas não vinculadas
                            </h4>
                            <span class="unlinked-conversations-badge">
                                <?= $stats['incoming_leads_count'] ?? count($incoming_leads) ?>
                            </span>
                        </div>
                        <p class="unlinked-conversations-description">
                            Conversas ainda não associadas a um cliente. Revise e vincule ou crie um novo.
                        </p>
                    </div>
                    
                    <?php foreach ($incoming_leads as $lead): ?>
                        <div class="conversation-item incoming-lead-item" 
                             onclick="handleConversationClick('<?= htmlspecialchars($lead['thread_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($lead['channel'] ?? 'whatsapp', ENT_QUOTES) ?>')"
                             data-thread-id="<?= htmlspecialchars($lead['thread_id'], ENT_QUOTES) ?>"
                             data-conversation-id="<?= $lead['conversation_id'] ?? 0 ?>"
                             style="cursor: pointer;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; color: #111b21; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($lead['contact_name'] ?? 'Contato Desconhecido') ?>
                                    </div>
                                    <div style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <span><?= htmlspecialchars($lead['contact'] ?? 'Número não identificado') ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                    <?php if (($lead['unread_count'] ?? 0) > 0): ?>
                                        <span class="hub-unread-badge" style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600;">
                                            <?= $lead['unread_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="incoming-lead-menu">
                                        <button type="button" class="incoming-lead-menu-toggle" onclick="event.stopPropagation(); toggleIncomingLeadMenu(this)" aria-label="Mais opções">
                                            ⋮
                                        </button>
                                        <div class="incoming-lead-menu-dropdown">
                                            <button type="button" class="incoming-lead-menu-item" onclick="event.stopPropagation(); openCreateTenantModal(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($lead['contact'] ?? '', ENT_QUOTES) ?>'); closeIncomingLeadMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                                Criar Cliente
                                            </button>
                                            <?php if (($filters['status'] ?? '') !== 'ignored'): ?>
                                            <button type="button" class="incoming-lead-menu-item" onclick="event.stopPropagation(); ignoreConversation(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? 'Contato', ENT_QUOTES) ?>'); closeIncomingLeadMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                                Ignorar
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="incoming-lead-menu-item danger" onclick="event.stopPropagation(); deleteConversation(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? 'Contato', ENT_QUOTES) ?>'); closeIncomingLeadMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                Excluir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="incoming-lead-actions">
                                <button type="button" class="incoming-lead-btn-primary" onclick="event.stopPropagation(); openLinkTenantModal(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? '', ENT_QUOTES) ?>')">
                                    Vincular
                                </button>
                                <!-- Botões ocultos mantidos para compatibilidade com JS existente -->
                                <button type="button" class="incoming-lead-hidden-btn" onclick="event.stopPropagation(); openCreateTenantModal(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($lead['contact'] ?? '', ENT_QUOTES) ?>')">Criar Cliente</button>
                                <button type="button" class="incoming-lead-hidden-btn" onclick="event.stopPropagation(); rejectIncomingLead(<?= $lead['conversation_id'] ?? 0 ?>)">Ignorar</button>
                            </div>
                            <div style="font-size: 11px; color: #667781; margin-top: 6px;">
                                <?php
                                $lastActivity = $lead['last_activity'] ?? 'now';
                                $dateStr = 'Agora';
                                try {
                                    // conversations.last_message_at está em UTC
                                    // Converte para Brasília (UTC-3)
                                    $cleanTimestamp = preg_replace('/\.\d+$/', '', $lastActivity);
                                    $dt = new DateTime($cleanTimestamp, new DateTimeZone('UTC'));
                                    $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                    $dateStr = $dt->format('d/m H:i');
                                } catch (Exception $e) {
                                    $dateStr = 'Agora';
                                }
                                ?>
                                <span><?= $dateStr ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="height: 12px; border-bottom: 2px solid #e4e6eb; margin: 12px 0;"></div>
                <?php endif; ?>
                
                <!-- Seção: Conversas Normais -->
                <?php if (empty($threads) && empty($incoming_leads)): ?>
                    <div style="padding: 40px; text-align: center; color: #667781;">
                        <p>Nenhuma conversa encontrada</p>
                        <p style="font-size: 13px; margin-top: 10px;">As conversas aparecerão aqui quando houver mensagens recebidas ou enviadas.</p>
                    </div>
                <?php elseif (!empty($threads)): ?>
                    <?php foreach ($threads as $thread): ?>
                        <div onclick="handleConversationClick('<?= htmlspecialchars($thread['thread_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($thread['channel'] ?? 'whatsapp', ENT_QUOTES) ?>')" 
                             class="conversation-item <?= ($thread['status'] ?? 'active') === 'archived' ? 'conversation-archived' : '' ?> <?= ($thread['status'] ?? 'active') === 'ignored' ? 'conversation-ignored' : '' ?>"
                             data-thread-id="<?= htmlspecialchars($thread['thread_id'], ENT_QUOTES) ?>"
                             data-conversation-id="<?= $thread['conversation_id'] ?? 0 ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; color: #111b21; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($thread['contact_name'] ?? $thread['tenant_name'] ?? 'Cliente') ?>
                                    </div>
                                    <div style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                    <?php if ($thread['channel'] === 'whatsapp'): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <span><?= htmlspecialchars($thread['contact'] ?? 'Número não identificado') ?></span>
                                        <?php if (!empty($thread['channel_id'])): ?>
                                            <span style="opacity: 0.6; font-size: 11px;">• <?= htmlspecialchars($thread['channel_id']) ?></span>
                                        <?php elseif (isset($thread['channel_type'])): ?>
                                            <span style="opacity: 0.7;">• <?= strtoupper($thread['channel_type']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        <span>Chat Interno</span>
                                    <?php endif; ?>
                                    <?php 
                                    // Sempre mostra tenant_name quando existir, mesmo que tenha contact_name
                                    // Isso permite identificar que múltiplos contatos pertencem ao mesmo tenant
                                    // Clicável para abrir página do tenant
                                    if (isset($thread['tenant_name']) && $thread['tenant_name'] !== 'Sem tenant' && !empty($thread['tenant_id'])): ?>
                                        <a href="<?= pixelhub_url('/tenants/view?id=' . $thread['tenant_id']) ?>" 
                                           onclick="event.stopPropagation();" 
                                           style="opacity: 0.7; font-weight: 500; color: #023A8D; cursor: pointer; text-decoration: underline; text-decoration-style: dotted;" 
                                           title="Clique para ver detalhes do cliente">• <?= htmlspecialchars($thread['tenant_name']) ?></a>
                                    <?php elseif (!isset($thread['tenant_name']) || $thread['tenant_id'] === null): ?>
                                        <span style="opacity: 0.7; font-size: 10px;">• Sem tenant</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($thread['conversation_key']) && defined('APP_DEBUG') && APP_DEBUG): ?>
                                    <div style="font-size: 10px; color: #999; margin-top: 2px; opacity: 0.6;">
                                        <?= htmlspecialchars($thread['conversation_key']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0; margin-left: 8px;">
                                    <?php if (($thread['unread_count'] ?? 0) > 0 && ($thread['thread_id'] ?? '') !== ($selected_thread_id ?? '')): ?>
                                        <span class="hub-unread-badge" style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 18px; text-align: center;">
                                            <?= $thread['unread_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="conversation-menu">
                                        <button type="button" class="conversation-menu-toggle" onclick="event.stopPropagation(); toggleConversationMenu(this)" aria-label="Mais opções">
                                            ⋮
                                        </button>
                                        <div class="conversation-menu-dropdown">
                                            <?php 
                                            $currentStatus = $thread['status'] ?? 'active';
                                            $conversationId = $thread['conversation_id'] ?? 0;
                                            $contactName = htmlspecialchars($thread['contact_name'] ?? 'Conversa', ENT_QUOTES);
                                            ?>
                                            <?php if ($currentStatus === 'active' || $currentStatus === ''): ?>
                                                <!-- ATIVA: Arquivar e Ignorar -->
                                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); archiveConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                                                    Arquivar
                                                </button>
                                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); ignoreConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                                    Ignorar
                                                </button>
                                            <?php elseif ($currentStatus === 'archived'): ?>
                                                <!-- ARQUIVADA: Desarquivar e Ignorar -->
                                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); reactivateConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                                                    Desarquivar
                                                </button>
                                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); ignoreConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                                    Ignorar
                                                </button>
                                            <?php elseif ($currentStatus === 'ignored'): ?>
                                                <!-- IGNORADA: Ativar e Arquivar -->
                                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); reactivateConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                    Ativar
                                                </button>
                                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); archiveConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                                                    Arquivar
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); openEditContactNameModal(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Editar nome
                                            </button>
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); openChangeTenantModal(<?= $conversationId ?>, '<?= $contactName ?>', <?= $thread['tenant_id'] ?? 'null' ?>, '<?= htmlspecialchars($thread['tenant_name'] ?? '', ENT_QUOTES) ?>'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                Alterar Cliente
                                            </button>
                                            <?php if (!empty($thread['tenant_id'])): ?>
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); unlinkConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                                                Desvincular
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="conversation-menu-item danger" onclick="event.stopPropagation(); deleteConversation(<?= $conversationId ?>, '<?= $contactName ?>'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                Excluir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: flex-end; align-items: center; font-size: 11px; color: #667781;">
                                <?php
                                $lastActivity = $thread['last_activity'] ?? ($thread['created_at'] ?? null);
                                $dateStr = 'Agora';
                                if ($lastActivity) {
                                    try {
                                        // conversations.last_message_at está em UTC
                                        // Precisa converter para Brasília (UTC-3)
                                        $cleanTimestamp = preg_replace('/[+-]\d{2}:\d{2}$/', '', $lastActivity);
                                        $cleanTimestamp = str_replace('T', ' ', $cleanTimestamp);
                                        $cleanTimestamp = preg_replace('/\.\d+$/', '', $cleanTimestamp);
                                        
                                        // Cria DateTime em UTC e converte para Brasília
                                        $dt = new DateTime($cleanTimestamp, new DateTimeZone('UTC'));
                                        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                        $dateStr = $dt->format('d/m H:i');
                                    } catch (Exception $e) {
                                        $dateStr = 'Agora';
                                    }
                                }
                                ?>
                                <span><?= $dateStr ?></span>
                            </div>
                        <?php
                        // Preview da última mensagem (se disponível)
                        // TODO: Buscar preview da última mensagem da conversa
                        // Por enquanto, apenas mostra contador
                        ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pane: Thread da Conversa -->
        <div class="pane-thread conversation-thread-pane">
            <!-- Placeholder inicial -->
            <div id="conversation-placeholder" class="conversation-placeholder">
                <div>
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#667781" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 16px; opacity: 0.5;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <h3 style="color: #111b21; margin-bottom: 8px; font-size: 18px;">Selecione uma conversa</h3>
                    <p style="font-size: 14px; color: #667781;">Escolha uma conversa na lista ao lado para começar a enviar mensagens</p>
                </div>
            </div>
            
            <!-- Área de conversa ativa (inicialmente oculta) -->
            <div id="conversation-content" style="display: none; flex: 1; flex-direction: column; min-height: 0; height: 100%;">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>
</div>


<!-- Modal: Nova Mensagem -->
<div id="new-message-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Nova Mensagem</h2>
            <button onclick="closeNewMessageModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
        </div>
        
        <form id="new-message-form" onsubmit="sendNewMessage(event)">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Canal</label>
                <select name="channel" id="new-message-channel" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" onchange="toggleNewMessageSessionField()">
                    <option value="">Selecione...</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="chat">Chat Interno</option>
                </select>
            </div>
            
            <!-- Sessão WhatsApp (mostrado quando canal = whatsapp) -->
            <div id="new-message-session-container" style="margin-bottom: 20px; display: none;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Sessão (WhatsApp) <span style="color: #dc3545;">*</span></label>
                <select name="channel_id" id="new-message-session" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione a sessão...</option>
                    <?php foreach ($whatsapp_sessions as $session): ?>
                        <option value="<?= htmlspecialchars($session['id']) ?>" <?= ($session['status'] === 'connected') ? 'data-connected="true"' : '' ?>>
                            <?= htmlspecialchars($session['name']) ?>
                            <?php if ($session['status'] === 'connected'): ?> (conectada)<?php else: ?> (offline)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (count($whatsapp_sessions) === 1): ?>
                        <script>
                            // Auto-seleciona se só tem uma sessão
                            document.addEventListener('DOMContentLoaded', function() {
                                const sessionSelect = document.getElementById('new-message-session');
                                if (sessionSelect && sessionSelect.options.length === 2) {
                                    sessionSelect.selectedIndex = 1;
                                }
                            });
                        </script>
                    <?php endif; ?>
                </select>
                <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">
                    Define por qual número/instância a mensagem será enviada
                </small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Cliente</label>
                <div class="searchable-dropdown" id="modalClienteDropdown">
                    <input type="text" 
                           class="searchable-dropdown-input" 
                           id="modalClienteSearchInput"
                           placeholder="Buscar cliente..."
                           autocomplete="off"
                           style="height: 42px; font-size: 14px;">
                    <input type="hidden" name="tenant_id" id="modalClienteTenantId" value="">
                    <span class="searchable-dropdown-arrow">▼</span>
                    <div class="searchable-dropdown-list" id="modalClienteDropdownList" style="max-height: 200px;">
                        <?php foreach ($tenants as $tenant): ?>
                            <div class="searchable-dropdown-item" 
                                 data-value="<?= $tenant['id'] ?>" 
                                 data-name="<?= htmlspecialchars($tenant['name']) ?>"
                                 data-phone="<?= htmlspecialchars($tenant['phone'] ?? '') ?>"
                                 data-search="<?= htmlspecialchars(strtolower($tenant['name'] . ' ' . ($tenant['phone'] ?? ''))) ?>">
                                <div class="searchable-dropdown-item-name"><?= htmlspecialchars($tenant['name']) ?></div>
                                <div class="searchable-dropdown-item-detail"><?= htmlspecialchars($tenant['phone'] ?? 'Sem telefone') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;" id="new-message-to-container">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Para (Telefone/E-mail)</label>
                <input type="text" name="to" id="new-message-to" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="5511999999999">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Mensagem</label>
                <textarea name="message" id="new-message-text" required rows="5" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Enviar
                </button>
                <button type="button" onclick="closeNewMessageModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================================
// Configuração Central de Polling (com intervalos dinâmicos e backoff)
// ============================================================================
// Intervalos base (em ms):
// - Conversa ativa: 2-4s
// - Aba aberta sem conversa: 6-10s
// - Aba em background: 15-30s
const POLLING_INTERVALS = {
    ACTIVE_CONVERSATION: 5000,      // 5s - Otimizado para reduzir carga
    NO_ACTIVE_CONVERSATION: 15000,  // 15s - Lista sem conversa aberta
    BACKGROUND: 60000,              // 60s - Aba em background
    MAX_BACKOFF: 120000,            // 2 min - Teto do backoff
    ERROR_BACKOFF: 30000            // 30s - Após erro de rede
};

// ============================================================================
// Polling para atualização da lista de conversas
// ============================================================================
const HubState = {
    lastUpdateTs: null,
    pollingInterval: null,
    isPageVisible: true,
    isUserInteracting: false,
    lastInteractionTime: null,
    interactionTimeout: null,
    consecutiveNoUpdates: 0,  // Contador de checks sem atualizações (para backoff)
    currentInterval: POLLING_INTERVALS.NO_ACTIVE_CONVERSATION
};

// Guard global para evitar múltiplos inícios de polling
let __hubPollingStarted = false;

function startListPolling() {
    if (HubState.pollingInterval || __hubPollingStarted) {
        return;
    }
    __hubPollingStarted = true;
    
    // Inicializa timestamp com o mais recente da lista
    // Usa o maior timestamp entre todos os threads para garantir que detecta atualizações
    const threads = <?= json_encode($threads ?? []) ?>;
    if (threads.length > 0) {
        let maxTs = null;
        threads.forEach(thread => {
            const ts = thread.last_activity || thread.updated_at || null;
            if (ts && (!maxTs || new Date(ts) > new Date(maxTs))) {
                maxTs = ts;
            }
        });
        HubState.lastUpdateTs = maxTs;
        console.log('[Hub] Polling iniciado com lastUpdateTs:', HubState.lastUpdateTs);
    }
    
    // Polling com intervalos dinâmicos baseado em:
    // - Se há conversa ativa
    // - Se página está visível
    // - Backoff se não há atualizações
    function scheduleNextCheck() {
        if (HubState.pollingInterval) {
            clearInterval(HubState.pollingInterval);
        }
        
        // Calcula intervalo baseado no estado
        let baseInterval;
        if (document.hidden) {
            // Aba em background
            baseInterval = POLLING_INTERVALS.BACKGROUND;
        } else if (ConversationState.currentThreadId) {
            // Conversa ativa aberta
            baseInterval = POLLING_INTERVALS.ACTIVE_CONVERSATION;
        } else {
            // Aba aberta sem conversa ativa
            baseInterval = POLLING_INTERVALS.NO_ACTIVE_CONVERSATION;
        }
        
        // Aplica backoff se não há atualizações consecutivas
        const backoffMultiplier = Math.min(1 + (HubState.consecutiveNoUpdates * 0.5), 3); // Max 3x
        const finalInterval = Math.min(baseInterval * backoffMultiplier, POLLING_INTERVALS.MAX_BACKOFF);
        
        HubState.currentInterval = finalInterval;
        
        HubState.pollingInterval = setTimeout(() => {
            if (HubState.isPageVisible && !HubState.isUserInteracting) {
                const timeSinceInteraction = HubState.lastInteractionTime 
                    ? Date.now() - HubState.lastInteractionTime 
                    : Infinity;
                
                // Só faz polling se não houve interação nos últimos 5 segundos
                if (timeSinceInteraction > 5000) {
                    checkForListUpdates();
                }
            }
            scheduleNextCheck(); // Agenda próximo check
        }, finalInterval);
    }
    
    scheduleNextCheck();
    
    // Primeiro check após 5 segundos (ao invés de 2s)
    setTimeout(() => {
        if (!HubState.isUserInteracting) {
            checkForListUpdates();
        }
    }, 5000);
}

async function checkForListUpdates() {
    try {
        const params = new URLSearchParams({
            status: '<?= htmlspecialchars($filters['status'] ?? 'active') ?>'
        });
        if (HubState.lastUpdateTs) {
            params.set('after_timestamp', HubState.lastUpdateTs);
        }
        <?php if (isset($filters['tenant_id']) && $filters['tenant_id']): ?>
        params.set('tenant_id', '<?= (int) $filters['tenant_id'] ?>');
        <?php endif; ?>
        
        const url = '<?= pixelhub_url('/communication-hub/check-updates') ?>?' + params.toString();
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.has_updates) {
            console.log('[Hub] ✅ Atualizações detectadas!', {
                after_timestamp: HubState.lastUpdateTs,
                latest_update_ts: result.latest_update_ts
            });
            
            // Reset backoff quando há atualizações
            HubState.consecutiveNoUpdates = 0;
            
            // CRÍTICO: NUNCA recarrega a página se houver conversa ativa
            // Atualiza apenas a lista via AJAX para preservar estado
            if (ConversationState.currentThreadId) {
                console.log('[Hub] Conversa ativa detectada, atualizando apenas lista (sem reload)');
                updateConversationListOnly();
            } else {
                // Só recarrega se não há conversa ativa E usuário não está interagindo
                if (HubState.isUserInteracting) {
                    console.log('[Hub] Usuário interagindo, aguardando para recarregar...');
                    if (HubState.interactionTimeout) {
                        clearTimeout(HubState.interactionTimeout);
                    }
                    HubState.interactionTimeout = setTimeout(() => {
                        if (!HubState.isUserInteracting && !ConversationState.currentThreadId) {
                            location.reload();
                        }
                    }, 3000);
                } else {
                    // Recarrega apenas se não há conversa ativa
                    if (!ConversationState.currentThreadId) {
                        location.reload();
                    }
                }
            }
        } else if (result.success && result.latest_update_ts) {
            // Atualiza timestamp mesmo sem mudanças (para manter sincronizado)
            const oldTs = HubState.lastUpdateTs;
            HubState.lastUpdateTs = result.latest_update_ts;
            if (oldTs !== HubState.lastUpdateTs) {
                console.log('[Hub] Timestamp atualizado:', oldTs, '->', HubState.lastUpdateTs);
            }
        } else {
            // Sem atualizações - incrementa contador para backoff
            HubState.consecutiveNoUpdates++;
            // Log silencioso quando não há atualizações (para não poluir o console)
            // console.log('[Hub] Sem atualizações (consecutivas: ' + HubState.consecutiveNoUpdates + ')');
        }
    } catch (error) {
        console.error('[Hub] ❌ Erro ao verificar atualizações:', error);
        // Aplica backoff agressivo em caso de erro de rede
        HubState.consecutiveNoUpdates = Math.min(HubState.consecutiveNoUpdates + 3, 10);
    }
}

// ============================================================================
// Detecção de Interação do Usuário
// ============================================================================

function markUserInteraction() {
    HubState.isUserInteracting = true;
    HubState.lastInteractionTime = Date.now();
    
    // Limpa timeout anterior se existir
    if (HubState.interactionTimeout) {
        clearTimeout(HubState.interactionTimeout);
    }
    
    // Marca como não interagindo após 2 segundos de inatividade
    HubState.interactionTimeout = setTimeout(() => {
        HubState.isUserInteracting = false;
    }, 2000);
}

// Detecta interações do usuário
document.addEventListener('mousedown', markUserInteraction);
document.addEventListener('keydown', markUserInteraction);
document.addEventListener('click', markUserInteraction);
document.addEventListener('focus', function(e) {
    // Só marca interação se for em elementos interativos
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || 
        e.target.tagName === 'BUTTON' || e.target.tagName === 'A' ||
        e.target.closest('button') || e.target.closest('a')) {
        markUserInteraction();
    }
});

// Page Visibility API
document.addEventListener('visibilitychange', function() {
    HubState.isPageVisible = !document.hidden;
    
    // Se página ficou oculta, pausa polling
    if (document.hidden) {
        if (HubState.pollingInterval) {
            clearTimeout(HubState.pollingInterval);
            HubState.pollingInterval = null;
        }
    } else {
        // Se página ficou visível, reinicia polling (reagenda com intervalo apropriado)
        if (!HubState.pollingInterval && __hubPollingStarted) {
            // Reagenda próximo check imediatamente
            const baseInterval = ConversationState.currentThreadId 
                ? POLLING_INTERVALS.ACTIVE_CONVERSATION 
                : POLLING_INTERVALS.NO_ACTIVE_CONVERSATION;
            const backoffMultiplier = Math.min(1 + (HubState.consecutiveNoUpdates * 0.5), 3);
            const finalInterval = Math.min(baseInterval * backoffMultiplier, POLLING_INTERVALS.MAX_BACKOFF);
            
            HubState.pollingInterval = setTimeout(() => {
                if (!HubState.isUserInteracting) {
                    checkForListUpdates();
                }
                // Continua polling normalmente
                if (__hubPollingStarted) {
                    const nextInterval = ConversationState.currentThreadId 
                        ? POLLING_INTERVALS.ACTIVE_CONVERSATION 
                        : POLLING_INTERVALS.NO_ACTIVE_CONVERSATION;
                    HubState.pollingInterval = setInterval(() => {
                        if (HubState.isPageVisible && !HubState.isUserInteracting) {
                            checkForListUpdates();
                        }
                    }, nextInterval);
                }
            }, 1000); // Primeiro check rápido após voltar
        }
    }
});

// Inicia polling quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    // Adiciona classe ao body para prevenir scroll
    document.body.classList.add('communication-hub-page');
    
    startListPolling();
    
    // Verifica se há conversa para reabrir (URL params ou sessionStorage)
    // IMPORTANTE: Se usuário clicar em outra thread, ignora restore
    const urlParams = new URLSearchParams(window.location.search);
    const threadIdFromUrl = urlParams.get('thread_id');
    const channelFromUrl = urlParams.get('channel') || 'whatsapp';
    
    // Tenta URL primeiro, depois sessionStorage
    const threadId = threadIdFromUrl || sessionStorage.getItem('hub_selected_thread_id');
    const channel = channelFromUrl || sessionStorage.getItem('hub_selected_channel') || 'whatsapp';
    
    if (threadId) {
        console.log('[Hub] Reabrindo conversa salva:', threadId, channel);
        console.log('[LOG TEMPORARIO] DOMContentLoaded - Reabrindo thread_id=' + threadId);
        
        // VALIDA: Se thread salva ainda existe na lista atual
        // Aguarda lista carregar primeiro
        setTimeout(() => {
            const savedThreadExists = document.querySelector(`[data-thread-id="${threadId}"]`);
            if (savedThreadExists) {
                // Thread existe, pode reabrir
                loadConversation(threadId, channel);
            } else {
                // Thread não existe mais ou usuário clicou em outra - não força reabertura
                console.log('[Hub] Thread salva não encontrada na lista, não reabre automaticamente');
                console.log('[LOG TEMPORARIO] DOMContentLoaded - Thread salva NÃO encontrada, não reabre');
            }
        }, 500);
    }
});

// Limpa polling ao sair
window.addEventListener('beforeunload', function() {
    if (HubState.pollingInterval) {
        clearInterval(HubState.pollingInterval);
        HubState.pollingInterval = null;
    }
    __hubPollingStarted = false;
});

/**
 * Atualiza apenas a lista de conversas via AJAX (sem recarregar página)
 * Preserva conversa ativa
 */
async function updateConversationListOnly() {
    try {
        // Busca lista atualizada via endpoint JSON
        const params = new URLSearchParams({
            channel: '<?= htmlspecialchars($filters['channel'] ?? 'all') ?>',
            status: '<?= htmlspecialchars($filters['status'] ?? 'active') ?>'
        });
        <?php if (isset($filters['tenant_id']) && $filters['tenant_id']): ?>
        params.set('tenant_id', '<?= (int) $filters['tenant_id'] ?>');
        <?php endif; ?>
        <?php if (isset($filters['session_id']) && $filters['session_id']): ?>
        params.set('session_id', '<?= htmlspecialchars($filters['session_id']) ?>');
        <?php endif; ?>
        
        const url = '<?= pixelhub_url('/communication-hub/conversations-list') ?>?' + params.toString();
        const response = await fetch(url);
        const result = await response.json();
        
        if (!result.success || !result.threads) {
            console.error('[Hub] Erro ao buscar lista atualizada:', result.error || 'Resposta inválida');
            return;
        }
        
        // Preserva estado atual
        const activeThreadId = ConversationState.currentThreadId;
        const listScroll = document.querySelector('.conversation-list-scroll');
        
        // Backend já retorna ordenado por last_activity DESC - não reordenar no front
        const threads = result.threads || [];
        const incomingLeads = result.incoming_leads || [];
        
        // Renderiza lista (já ordenada pelo backend)
        const incomingLeadsCount = result.incoming_leads_count !== undefined ? result.incoming_leads_count : incomingLeads.length;
        renderConversationList(threads, incomingLeads, incomingLeadsCount);
        
        // Sempre rola para o topo após atualização para mostrar conversas mais recentes
        if (listScroll) {
            listScroll.scrollTop = 0;
        }
        
        // Restaura conversa ativa
        if (activeThreadId) {
            document.querySelectorAll('.conversation-item').forEach(item => {
                if (item.dataset.threadId === activeThreadId) {
                    item.classList.add('active');
                    item.style.background = '#e7f3ff';
                    item.style.borderColor = '#007bff';
                } else {
                    item.classList.remove('active');
                    item.style.background = '';
                    item.style.borderColor = '';
                }
            });
        }
        
        // [LOG TEMPORARIO] Atualização concluída
        console.log('[LOG TEMPORARIO] updateConversationListOnly() - CONCLUIDO: lista atualizada, conversa ativa preservada=' + (activeThreadId ? 'SIM' : 'NÃO'));
        console.log('[Hub] Lista atualizada com sucesso (preservando conversa ativa)');
    } catch (error) {
        console.error('[Hub] Erro ao atualizar lista:', error);
        // [LOG TEMPORARIO] Erro
        console.error('[LOG TEMPORARIO] updateConversationListOnly() - ERRO: ' + error.message);
    }
}

/**
 * Renderiza lista de conversas no DOM
 * 
 * IMPORTANTE: threads já devem estar ordenados por last_activity DESC
 */
function renderConversationList(threads, incomingLeads = [], incomingLeadsCount = null) {
    const listContainer = document.querySelector('.conversation-list-scroll');
    if (!listContainer) {
        console.error('[Hub] Container da lista não encontrado');
        return;
    }
    
    if (threads.length === 0 && (!incomingLeads || incomingLeads.length === 0)) {
        listContainer.innerHTML = `
            <div style="padding: 40px; text-align: center; color: #667781;">
                <p>Nenhuma conversa encontrada</p>
                <p style="font-size: 13px; margin-top: 10px;">As conversas aparecerão aqui quando houver mensagens recebidas ou enviadas.</p>
            </div>
        `;
        return;
    }
    
    // [LOG TEMPORARIO] Ordem antes de renderizar
    console.log('[LOG TEMPORARIO] renderConversationList() - INICIADO: threads_count=' + threads.length);
    if (threads.length > 0) {
        console.log('[LOG TEMPORARIO] renderConversationList() - PRIMEIRO: thread_id=' + (threads[0].thread_id || 'N/A') + ', last_activity=' + (threads[0].last_activity || 'N/A'));
        if (threads.length > 1) {
            console.log('[LOG TEMPORARIO] renderConversationList() - SEGUNDO: thread_id=' + (threads[1].thread_id || 'N/A') + ', last_activity=' + (threads[1].last_activity || 'N/A'));
        }
    }
    
    let html = '';
    
    // Renderiza incoming leads primeiro (se houver)
    if (incomingLeads && incomingLeads.length > 0) {
        html += `
            <div class="unlinked-conversations-section">
                <div class="unlinked-conversations-header">
                    <h4 class="unlinked-conversations-title">
                        <svg class="unlinked-conversations-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Conversas não vinculadas
                    </h4>
                    <span class="unlinked-conversations-badge">
                        ${incomingLeadsCount !== null ? incomingLeadsCount : incomingLeads.length}
                    </span>
                </div>
                <p class="unlinked-conversations-description">
                    Conversas ainda não associadas a um cliente. Revise e vincule ou crie um novo.
                </p>
            </div>
        `;
        
        incomingLeads.forEach((lead) => {
            const threadId = escapeHtml(lead.thread_id || '');
            const contactName = escapeHtml(lead.contact_name || 'Contato Desconhecido');
            const contact = escapeHtml(lead.contact || 'Número não identificado');
            const conversationId = lead.conversation_id || 0;
            const unreadCount = lead.unread_count || 0;
            
            // Formata data (usa função que garante fuso de Brasília)
            const dateStr = formatDateBrasilia(lead.last_activity);
            
            const channel = lead.channel || 'whatsapp';
            html += `
                <div class="conversation-item incoming-lead-item" 
                     onclick="handleConversationClick('${threadId}', '${channel}')"
                     data-thread-id="${threadId}"
                     data-conversation-id="${conversationId}"
                     style="cursor: pointer;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; font-size: 14px; color: #111b21; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                ${contactName}
                            </div>
                            <div style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 2px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                <span>${contact}</span>
                                ${lead.channel_id ? `<span style="opacity: 0.6; font-size: 11px;">• ${escapeHtml(lead.channel_id)}</span>` : ''}
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                            ${unreadCount > 0 ? `
                                <span class="hub-unread-badge" style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600;">
                                    ${unreadCount}
                                </span>
                            ` : ''}
                            <div class="incoming-lead-menu">
                                <button type="button" class="incoming-lead-menu-toggle" onclick="event.stopPropagation(); toggleIncomingLeadMenu(this)" aria-label="Mais opções">
                                    ⋮
                                </button>
                                <div class="incoming-lead-menu-dropdown">
                                    <button type="button" class="incoming-lead-menu-item" onclick="event.stopPropagation(); openCreateTenantModal(${conversationId}, '${escapeHtml(contactName)}', '${escapeHtml(contact)}'); closeIncomingLeadMenu(this);">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                        Criar Cliente
                                    </button>
                                    <?php if (($filters['status'] ?? '') !== 'ignored'): ?>
                                    <button type="button" class="incoming-lead-menu-item" onclick="event.stopPropagation(); ignoreConversation(${conversationId}, '${escapeHtml(contactName)}'); closeIncomingLeadMenu(this);">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                        Ignorar
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="incoming-lead-menu-item danger" onclick="event.stopPropagation(); deleteConversation(${conversationId}, '${escapeHtml(contactName)}'); closeIncomingLeadMenu(this);">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="incoming-lead-actions">
                        <button type="button" class="incoming-lead-btn-primary" onclick="event.stopPropagation(); openLinkTenantModal(${conversationId}, '${escapeHtml(contactName)}')">
                            Vincular
                        </button>
                        <!-- Botões ocultos mantidos para compatibilidade com JS existente -->
                        <button type="button" class="incoming-lead-hidden-btn" onclick="event.stopPropagation(); openCreateTenantModal(${conversationId}, '${escapeHtml(contactName)}', '${escapeHtml(contact)}')">Criar Cliente</button>
                        <button type="button" class="incoming-lead-hidden-btn" onclick="event.stopPropagation(); rejectIncomingLead(${conversationId})">Ignorar</button>
                    </div>
                    <div style="font-size: 11px; color: #667781; margin-top: 6px;">
                        ${dateStr}
                    </div>
                </div>
            `;
        });
        
        // Adiciona separador entre incoming leads e conversas normais
        if (threads.length > 0) {
            html += `<div style="height: 12px; border-bottom: 2px solid #e4e6eb; margin: 12px 0;"></div>`;
        }
    }
    
    threads.forEach((thread, index) => {
        const threadId = escapeHtml(thread.thread_id || '');
        const channel = escapeHtml(thread.channel || 'whatsapp');
        const contactName = escapeHtml(thread.contact_name || thread.tenant_name || 'Cliente');
        const contact = escapeHtml(thread.contact || 'Número não identificado');
        const tenantName = escapeHtml(thread.tenant_name || 'Sem tenant');
        const unreadCount = thread.unread_count || 0;
        const messageCount = thread.message_count || 0;
        const lastActivity = thread.last_activity || 'now';
        
        // Formata data (usa função que garante fuso de Brasília)
        const dateStr = formatDateBrasilia(lastActivity);
        
        html += `
            <div onclick="handleConversationClick('${threadId}', '${channel}')" 
                 class="conversation-item"
                 data-thread-id="${threadId}">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 14px; color: #111b21; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${contactName}
                        </div>
                        <div style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                            ${channel === 'whatsapp' ? `
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                <span>${contact}</span>
                                ${thread.channel_id ? `<span style="opacity: 0.6; font-size: 11px;">• ${escapeHtml(thread.channel_id)}</span>` : (thread.channel_type ? `<span style="opacity: 0.7;">• ${thread.channel_type.toUpperCase()}</span>` : '')}
                            ` : `
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                <span>Chat Interno</span>
                            `}
                            ${thread.tenant_name && thread.tenant_name !== 'Sem tenant' && thread.tenant_id ? 
                                `<a href="/tenants/view?id=${thread.tenant_id}" 
                                   onclick="event.stopPropagation();" 
                                   style="opacity: 0.7; font-weight: 500; color: #023A8D; cursor: pointer; text-decoration: underline; text-decoration-style: dotted;" 
                                   title="Clique para ver detalhes do cliente">• ${tenantName}</a>` : 
                                (!thread.tenant_name || thread.tenant_id === null ? 
                                    '<span style="opacity: 0.7; font-size: 10px;">• Sem tenant</span>' : '')
                            }
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0; margin-left: 8px;">
                        ${unreadCount > 0 && threadId !== ConversationState.currentThreadId ? `
                            <span class="hub-unread-badge" style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 18px; text-align: center;">
                                ${unreadCount}
                            </span>
                        ` : ''}
                        <div class="conversation-menu">
                            <button type="button" class="conversation-menu-toggle" onclick="event.stopPropagation(); toggleConversationMenu(this)" aria-label="Mais opções">
                                ⋮
                            </button>
                            <div class="conversation-menu-dropdown">
                                ${(() => {
                                    const status = thread.status || 'active';
                                    const convId = thread.conversation_id || 0;
                                    const name = escapeHtml(thread.contact_name || 'Conversa');
                                    
                                    if (status === 'active' || status === '') {
                                        // ATIVA: Arquivar e Ignorar
                                        return `
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); archiveConversation(${convId}, '${name}'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                                                Arquivar
                                            </button>
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); ignoreConversation(${convId}, '${name}'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                                Ignorar
                                            </button>
                                        `;
                                    } else if (status === 'archived') {
                                        // ARQUIVADA: Desarquivar e Ignorar
                                        return `
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); reactivateConversation(${convId}, '${name}'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                                                Desarquivar
                                            </button>
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); ignoreConversation(${convId}, '${name}'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                                Ignorar
                                            </button>
                                        `;
                                    } else if (status === 'ignored') {
                                        // IGNORADA: Ativar e Arquivar
                                        return `
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); reactivateConversation(${convId}, '${name}'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                Ativar
                                            </button>
                                            <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); archiveConversation(${convId}, '${name}'); closeConversationMenu(this);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                                                Arquivar
                                            </button>
                                        `;
                                    }
                                    return '';
                                })()}
                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); openEditContactNameModal(${thread.conversation_id || 0}, '${escapeHtml(thread.contact_name || '')}'); closeConversationMenu(this);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Editar nome
                                </button>
                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); openChangeTenantModal(${thread.conversation_id || 0}, '${escapeHtml(thread.contact_name || '')}', ${thread.tenant_id || 'null'}, '${escapeHtml(thread.tenant_name || '')}'); closeConversationMenu(this);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Alterar Cliente
                                </button>
                                ${thread.tenant_id ? `
                                <button type="button" class="conversation-menu-item" onclick="event.stopPropagation(); unlinkConversation(${thread.conversation_id || 0}, '${escapeHtml(thread.contact_name || 'Conversa')}'); closeConversationMenu(this);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                                    Desvincular
                                </button>
                                ` : ''}
                                <button type="button" class="conversation-menu-item danger" onclick="event.stopPropagation(); deleteConversation(${thread.conversation_id || 0}, '${escapeHtml(thread.contact_name || 'Conversa')}'); closeConversationMenu(this);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; align-items: center; font-size: 11px; color: #667781;">
                    <span>${dateStr}</span>
                </div>
            </div>
        `;
    });
    
    // [LOG TEMPORARIO] Ordem após renderizar HTML
    console.log('[LOG TEMPORARIO] renderConversationList() - HTML GERADO: length=' + html.length);
    
    listContainer.innerHTML = html;
    
    // [LOG TEMPORARIO] Valida ordem no DOM após renderizar
    const renderedItems = listContainer.querySelectorAll('.conversation-item');
    if (renderedItems.length > 0) {
        const firstRendered = renderedItems[0];
        const firstThreadId = firstRendered.dataset.threadId;
        console.log('[LOG TEMPORARIO] renderConversationList() - DOM RENDERIZADO: primeiro_item_thread_id=' + (firstThreadId || 'N/A'));
    }
}

function openNewMessageModal() {
    document.getElementById('new-message-modal').style.display = 'flex';
}

function closeNewMessageModal() {
    document.getElementById('new-message-modal').style.display = 'none';
    document.getElementById('new-message-form').reset();
    document.getElementById('new-message-to-container').style.display = 'none';
    document.getElementById('new-message-session-container').style.display = 'none';
    // Reseta o dropdown pesquisável de cliente
    if (typeof resetModalClienteDropdown === 'function') {
        resetModalClienteDropdown();
    }
}

// ============================================================================
// Lógica de Sessão WhatsApp (Filtros e Nova Mensagem)
// ============================================================================

/**
 * Mostra/oculta o filtro de sessão baseado no canal selecionado
 * Sessão só aparece quando Canal = WhatsApp
 */
function toggleSessionFilter() {
    const channelSelect = document.getElementById('filter-channel');
    const sessionContainer = document.getElementById('session-filter-container');
    const sessionSelect = document.getElementById('filter-session');
    
    if (channelSelect && sessionContainer) {
        // Mostra filtro de sessão SOMENTE quando canal é 'whatsapp'
        if (channelSelect.value === 'whatsapp') {
            sessionContainer.style.display = '';
        } else {
            sessionContainer.style.display = 'none';
            // Limpa seleção de sessão quando esconde
            if (sessionSelect) {
                sessionSelect.value = '';
            }
        }
    }
}

/**
 * Handler para mudança de Canal - atualiza visibilidade e auto-submete
 */
function onChannelFilterChange() {
    toggleSessionFilter();
    // Auto-submit do formulário
    const form = document.getElementById('communication-filters-form');
    if (form) {
        form.submit();
    }
}

/**
 * Handler para mudança de Sessão - auto-submete
 */
function onSessionFilterChange() {
    const form = document.getElementById('communication-filters-form');
    if (form) {
        form.submit();
    }
}

/**
 * Handler para mudança de Status - auto-submete
 */
function onStatusFilterChange() {
    const form = document.getElementById('communication-filters-form');
    if (form) {
        form.submit();
    }
}

/**
 * Handler para mudança de Cliente - auto-submete (chamado pelo dropdown)
 */
function onClienteFilterChange() {
    const form = document.getElementById('communication-filters-form');
    if (form) {
        form.submit();
    }
}

/**
 * Mostra/oculta o campo de sessão no modal Nova Mensagem
 */
function toggleNewMessageSessionField() {
    const channelSelect = document.getElementById('new-message-channel');
    const sessionContainer = document.getElementById('new-message-session-container');
    const sessionSelect = document.getElementById('new-message-session');
    
    if (channelSelect && sessionContainer) {
        if (channelSelect.value === 'whatsapp') {
            sessionContainer.style.display = 'block';
            // Torna obrigatório se há mais de uma sessão
            if (sessionSelect) {
                const hasMultipleSessions = sessionSelect.options.length > 2;
                sessionSelect.required = hasMultipleSessions;
                
                // Auto-seleciona se só tem uma sessão
                if (sessionSelect.options.length === 2) {
                    sessionSelect.selectedIndex = 1;
                }
            }
        } else {
            sessionContainer.style.display = 'none';
            if (sessionSelect) {
                sessionSelect.required = false;
            }
        }
    }
}

// Auto-preenche telefone quando seleciona cliente no modal (via dropdown pesquisável)
function onModalClienteSelect(phone) {
    const channel = document.getElementById('new-message-channel').value;
    const toInput = document.getElementById('new-message-to');
    const toContainer = document.getElementById('new-message-to-container');
    
    if (channel === 'whatsapp' && phone) {
        toInput.value = phone;
        toContainer.style.display = 'block';
    }
}

document.getElementById('new-message-channel')?.addEventListener('change', function() {
    const channel = this.value;
    const toContainer = document.getElementById('new-message-to-container');
    
    // Atualiza campo de sessão
    toggleNewMessageSessionField();
    
    if (channel === 'whatsapp') {
        toContainer.style.display = 'block';
        // Auto-preenche telefone se cliente já estiver selecionado
        const hiddenInput = document.getElementById('modalClienteTenantId');
        if (hiddenInput && hiddenInput.value) {
            const list = document.getElementById('modalClienteDropdownList');
            const selectedItem = list?.querySelector(`[data-value="${hiddenInput.value}"]`);
            if (selectedItem) {
                const phone = selectedItem.dataset.phone;
                if (phone) {
                    document.getElementById('new-message-to').value = phone;
                }
            }
        }
    } else {
        toContainer.style.display = 'none';
    }
});

async function sendNewMessage(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    // Validação: se canal é WhatsApp e há múltiplas sessões, exige seleção
    if (data.channel === 'whatsapp') {
        const sessionSelect = document.getElementById('new-message-session');
        if (sessionSelect && sessionSelect.options.length > 2 && !data.channel_id) {
            alert('Por favor, selecione a sessão do WhatsApp para enviar a mensagem.');
            sessionSelect.focus();
            return;
        }
    }
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/send') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Mensagem enviada com sucesso!');
            closeNewMessageModal();
            location.reload();
        } else {
            alert('Erro: ' + (result.error || 'Erro ao enviar mensagem'));
        }
    } catch (error) {
        alert('Erro ao enviar mensagem: ' + error.message);
    }
}

// Fecha modal ao clicar fora
document.getElementById('new-message-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeNewMessageModal();
    }
});

// ============================================================================
// Carregamento de Conversa no Painel Direito
// ============================================================================

/**
 * Formata data/hora para exibição na lista de conversas
 * conversations.last_message_at está em UTC, converte para Brasília
 */
/**
 * Formata timestamp para lista de conversas (converte UTC → Brasília)
 * Usado para: conversations.last_message_at (armazenado em UTC)
 */
function formatDateBrasilia(dateStr) {
    if (!dateStr || dateStr === 'now') return 'Agora';
    
    try {
        // Timestamps da tabela conversations estão em UTC
        // Adiciona 'Z' para indicar UTC e deixa toLocaleString converter para Brasília
        let isoStr = dateStr;
        if (!dateStr.includes('T') && !dateStr.includes('Z') && !dateStr.includes('+') && !dateStr.includes('-', 10)) {
            isoStr = dateStr.replace(' ', 'T') + 'Z';
        }
        
        const dateTime = new Date(isoStr);
        if (isNaN(dateTime.getTime())) return 'Agora';
        
        // Converte para Brasília
        const options = { 
            timeZone: 'America/Sao_Paulo',
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        };
        
        const formatted = dateTime.toLocaleString('pt-BR', options);
        return formatted.replace(',', '').replace(/\/\d{4}/, '');
    } catch (e) {
        console.warn('[Hub] Erro ao formatar data:', dateStr, e);
        return 'Agora';
    }
}

/**
 * Formata timestamp de mensagens (exibe direto, SEM conversão de timezone)
 * Usado para: communication_events.created_at (armazenado em Brasília)
 */
function formatMessageTimestamp(dateStr) {
    if (!dateStr || dateStr === 'now') return 'Agora';
    
    try {
        // Timestamps de mensagens JÁ estão em Brasília
        // Parse manual para evitar conversão de timezone
        const match = dateStr.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
        if (match) {
            const [, year, month, day, hour, minute] = match;
            return `${day}/${month} ${hour}:${minute}`;
        }
        
        // Fallback: tenta extrair de formato ISO
        const isoMatch = dateStr.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
        if (isoMatch) {
            const [, year, month, day, hour, minute] = isoMatch;
            return `${day}/${month} ${hour}:${minute}`;
        }
        
        return 'Agora';
    } catch (e) {
        console.warn('[Hub] Erro ao formatar timestamp de mensagem:', dateStr, e);
        return 'Agora';
    }
}

/**
 * Escapa HTML para prevenir XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Escapa atributos HTML para prevenir XSS
 * URLs já vêm URL-encoded do backend, então apenas escapa caracteres HTML perigosos
 */
function escapeAttr(value) {
    if (!value) return '';
    // Escapa caracteres HTML perigosos para uso em atributos
    // A URL já vem URL-encoded do backend, então não precisa de encodeURI adicional
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// =========================================================================
// FUNÇÕES DE TRANSCRIÇÃO DE ÁUDIO
// =========================================================================

/**
 * Inicia transcrição de um áudio
 * @param {HTMLElement} btn - Botão que foi clicado
 * @param {string} eventId - ID do evento que contém o áudio
 */
async function transcribeAudio(btn, eventId) {
    if (!eventId) {
        console.error('[Transcription] event_id não fornecido');
        return;
    }
    
    // Encontra o container do media player
    const mediaContainer = btn.closest('div[style*="margin-bottom"]') || btn.closest('.message-bubble')?.querySelector('div[style*="margin-bottom"]') || btn.parentElement;
    
    // Cria badge de status discreto
    let statusBadge = mediaContainer.querySelector('.transcription-status-badge');
    if (!statusBadge) {
        statusBadge = document.createElement('div');
        statusBadge.className = 'transcription-status-badge processing';
        mediaContainer.appendChild(statusBadge);
    }
    statusBadge.innerHTML = '<span class="transcription-spinner"></span> Processando...';
    statusBadge.className = 'transcription-status-badge processing';
    
    // Esconde menu de áudio se veio de lá
    const menuWrapper = mediaContainer.querySelector('.audio-menu-wrapper');
    if (menuWrapper) menuWrapper.style.display = 'none';
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/transcribe') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'event_id=' + encodeURIComponent(eventId)
        });
        
        const data = await response.json();
        console.log('[Transcription] Resposta:', data);
        
        if (data.success && data.status === 'completed' && data.transcription) {
            // Transcrição concluída
            showTranscription(statusBadge, data.transcription);
        } else if (data.success && data.status === 'processing') {
            // Em processamento - inicia polling
            pollTranscriptionStatus(statusBadge, eventId, 0);
        } else {
            // Erro - badge discreto com retry
            console.error('[Transcription] Erro:', data.error);
            statusBadge.className = 'transcription-status-badge failed';
            statusBadge.innerHTML = 'Falhou · <span style="text-decoration:underline;cursor:pointer" onclick="transcribeAudio(this,\'' + escapeAttr(eventId) + '\')">Tentar novamente</span>';
            if (menuWrapper) menuWrapper.style.display = '';
        }
    } catch (error) {
        console.error('[Transcription] Exceção:', error);
        statusBadge.className = 'transcription-status-badge failed';
        statusBadge.innerHTML = 'Erro · <span style="text-decoration:underline;cursor:pointer" onclick="transcribeAudio(this,\'' + escapeAttr(eventId) + '\')">Tentar novamente</span>';
        if (menuWrapper) menuWrapper.style.display = '';
    }
}

/**
 * Polling para verificar status da transcrição
 * @param {HTMLElement} btn - Elemento do botão/status
 * @param {string} eventId - ID do evento
 * @param {number} attempts - Número de tentativas já feitas
 */
async function pollTranscriptionStatus(badge, eventId, attempts) {
    const maxAttempts = 30; // 30 tentativas * 2 segundos = 60 segundos max
    
    if (attempts >= maxAttempts) {
        badge.className = 'transcription-status-badge failed';
        badge.innerHTML = 'Timeout · <span style="text-decoration:underline;cursor:pointer" onclick="transcribeAudio(this,\'' + escapeAttr(eventId) + '\')">Tentar novamente</span>';
        return;
    }
    
    await new Promise(r => setTimeout(r, 2000)); // Aguarda 2 segundos
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/transcription-status') ?>?event_id=' + encodeURIComponent(eventId));
        const data = await response.json();
        
        if (data.success && data.status === 'completed' && data.transcription) {
            showTranscription(badge, data.transcription);
        } else if (data.status === 'processing') {
            // Ainda processando - continua polling
            pollTranscriptionStatus(badge, eventId, attempts + 1);
        } else if (data.status === 'failed') {
            badge.className = 'transcription-status-badge failed';
            badge.innerHTML = 'Falhou · <span style="text-decoration:underline;cursor:pointer" onclick="transcribeAudio(this,\'' + escapeAttr(eventId) + '\')">Tentar novamente</span>';
        } else {
            // Status desconhecido - continua polling
            pollTranscriptionStatus(badge, eventId, attempts + 1);
        }
    } catch (error) {
        console.error('[Transcription] Erro no polling:', error);
        pollTranscriptionStatus(badge, eventId, attempts + 1);
    }
}

/**
 * Exibe a transcrição na UI (formato accordion discreto)
 * @param {HTMLElement} btn - Elemento do botão/badge a ser substituído
 * @param {string} transcription - Texto da transcrição
 */
function showTranscription(btn, transcription) {
    // Encontra o container pai (pode ser badge de status ou menu)
    const container = btn.closest('.audio-player-container')?.parentElement || btn.parentElement;
    
    // Remove elementos antigos de transcrição se existirem
    const oldBadge = container.querySelector('.transcription-status-badge');
    if (oldBadge) oldBadge.remove();
    
    // Cria o accordion de transcrição
    const accordion = document.createElement('div');
    accordion.className = 'transcription-accordion';
    accordion.setAttribute('data-open', 'true');
    accordion.innerHTML = `
        <button type="button" class="transcription-toggle" onclick="toggleTranscription(this)">
            <span class="transcription-chevron">▸</span>
            <span class="transcription-label">Transcrição</span>
        </button>
        <div class="transcription-content">${escapeHtml(transcription)}</div>
    `;
    
    container.appendChild(accordion);
    
    // Remove o elemento original (se for badge/botão)
    if (btn.classList.contains('transcription-status-badge')) {
        btn.remove();
    }
}

/**
 * Toggle do menu de 3 pontinhos do áudio
 */
function toggleAudioMenu(btn) {
    const dropdown = btn.nextElementSibling;
    const isOpen = dropdown.classList.contains('open');
    
    // Fecha todos os outros menus abertos
    document.querySelectorAll('.audio-menu-dropdown.open').forEach(d => d.classList.remove('open'));
    
    if (!isOpen) {
        dropdown.classList.add('open');
        
        // Fecha ao clicar fora
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
}

/**
 * Fecha o menu de áudio
 */
function closeAudioMenu(btn) {
    const dropdown = btn.closest('.audio-menu-dropdown');
    if (dropdown) dropdown.classList.remove('open');
}

/**
 * Toggle do accordion de transcrição
 */
function toggleTranscription(btn) {
    const accordion = btn.closest('.transcription-accordion');
    if (!accordion) return;
    
    const isOpen = accordion.getAttribute('data-open') === 'true';
    accordion.setAttribute('data-open', !isOpen);
}

/**
 * Renderiza player de mídia baseado no tipo
 */
function renderMediaPlayer(media, eventId = null) {
    if (!media) return '';
    // Placeholder quando mídia não foi baixada (evento existe mas download falhou)
    if (media.media_failed) {
        const typeLabel = (media.media_type || 'arquivo') === 'audio' ? 'Áudio' : ((media.media_type || '') === 'image' ? 'Imagem' : (media.media_type || 'Mídia'));
        return `<div class="hub-media-failed-placeholder" style="padding:12px 16px;background:#f5f5f5;border-radius:8px;color:#666;font-size:13px;">
            <span style="opacity:0.7;">${typeLabel} não disponível</span>
        </div>`;
    }
    if (!media.url) return '';
    
    const mimeType = (media.mime_type || '').toLowerCase();
    const mediaType = (media.media_type || '').toLowerCase();
    const safeUrl = escapeAttr(media.url);
    const safeEventId = eventId ? escapeAttr(eventId) : (media.event_id ? escapeAttr(media.event_id) : '');
    
    // Determina tipo de mídia
    const isAudio = mimeType.startsWith('audio/') || mediaType === 'audio' || mediaType === 'voice';
    const isImage = mimeType.startsWith('image/') || mediaType === 'image' || mediaType === 'sticker';
    const isVideo = mimeType.startsWith('video/') || mediaType === 'video';
    
    let mediaHtml = '';
    
    if (isAudio) {
        const hasTranscription = media.transcription && media.transcription.trim();
        const transcriptionStatus = media.transcription_status || null;
        
        // Container do player com menu de ações
        mediaHtml = `<div class="audio-player-container">
            <audio controls preload="none" src="${safeUrl}" 
                onmouseenter="if(this.preload==='none'){this.preload='metadata';}" 
                onplay="if(this.preload==='none'){this.preload='metadata';}"></audio>`;
        
        // Botão de transcrição (apenas se tiver event_id e não tiver transcrição completa)
        if (safeEventId && !hasTranscription && transcriptionStatus !== 'processing') {
            mediaHtml += `
            <button type="button" class="audio-transcribe-btn" onclick="transcribeAudio(this, '${safeEventId}')" title="Transcrever áudio">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
            </button>`;
        }
        
        mediaHtml += `</div>`;
        
        // Área de transcrição (abaixo do player)
        if (hasTranscription) {
            // Tem transcrição - accordion discreto com chevron
            mediaHtml += `
                <div class="transcription-accordion" data-open="false">
                    <button type="button" class="transcription-toggle" onclick="toggleTranscription(this)">
                        <span class="transcription-chevron">▸</span>
                        <span class="transcription-label">Transcrição</span>
                    </button>
                    <div class="transcription-content">${escapeHtml(media.transcription)}</div>
                </div>`;
        } else if (transcriptionStatus === 'processing') {
            // Em processamento - badge textual discreto
            mediaHtml += `
                <div class="transcription-status-badge processing">
                    <span class="transcription-spinner"></span> Processando...
                </div>`;
        } else if (transcriptionStatus === 'failed') {
            // Falhou - badge discreto com retry no menu
            mediaHtml += `
                <div class="transcription-status-badge failed" onclick="transcribeAudio(this, '${safeEventId}')" style="cursor:pointer;" title="Clique para tentar novamente">
                    Transcrição falhou · Tentar novamente
                </div>`;
        }
    } else if (isImage) {
        // Envolve imagem com botão clicável para abrir viewer
        // CSS controla tamanho e proporção (hub-media-thumb)
        mediaHtml = `
            <button type="button" class="hub-media-open" data-src="${safeUrl}">
                <img src="${safeUrl}" 
                     class="hub-media-thumb" 
                     data-src="${safeUrl}" 
                     loading="lazy"
                     alt="Imagem"
                     onload="this.parentElement.classList.add('loaded')"
                     onerror="console.warn('[Hub] Falha ao carregar imagem:', this.src); this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="img-error-placeholder">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <span style="font-size:11px;color:#666;">Clique para ver</span>
                </div>
            </button>
        `;
    } else if (isVideo) {
        mediaHtml = `<video controls preload="metadata" src="${safeUrl}" style="max-width:240px;border-radius:8px;"></video>`;
    } else {
        // Tipo desconhecido - mostra link
        const typeLabel = mediaType || mimeType || 'arquivo';
        mediaHtml = `<a href="${safeUrl}" target="_blank" style="color: #023A8D; text-decoration: none; font-weight: 600;">📎 ${escapeHtml(typeLabel)}</a>`;
    }
    
    // Adiciona label do tipo/mime se disponível (opcional, pequeno)
    let labelHtml = '';
    if (mimeType || mediaType) {
        const label = mimeType || mediaType;
        labelHtml = `<div style="font-size: 10px; color: #667781; margin-top: 4px; opacity: 0.7;">${escapeHtml(label)}</div>`;
    }
    
    return `<div style="margin-bottom: 8px;">${mediaHtml}${labelHtml}</div>`;
}

const ConversationState = {
    currentThreadId: null,
    currentChannel: null,
    pollingInterval: null,
    lastTimestamp: null,
    lastEventId: null,
    messageIds: new Set(),
    isPageVisible: true,
    autoScroll: true,
    newMessagesCount: 0
};

/**
 * Carrega uma conversa no painel direito
 */
/**
 * Handler para clique em conversa da lista
 * FORÇA reset completo e carrega full (não incremental)
 * IMPORTANTE: Deve estar no escopo global para funcionar com onclick inline
 */
function handleConversationClick(clickedThreadId, channel) {
    console.log('[Hub] Clique em conversa:', clickedThreadId, channel);
    
    // IMEDIATO: Zera badge de não lidas ao clicar (comportamento WhatsApp)
    // Isso evita que o badge "pisque" durante o carregamento
    clearUnreadBadgeForThread(clickedThreadId);
    
    // FORÇA: setActiveThread(clickedThreadId) - ignora qualquer thread salva
    ConversationState.currentThreadId = clickedThreadId;
    ConversationState.currentChannel = channel;
    
    // FORÇA: reset completo de markers
    ConversationState.messageIds.clear();
    ConversationState.lastTimestamp = null;
    ConversationState.lastEventId = null;
    ConversationState.newMessagesCount = 0;
    
    // Carrega conversa (será full load, não incremental)
    loadConversation(clickedThreadId, channel);
}

// Garante que handleConversationClick esteja no escopo global (para onclick inline)
// Isso é crítico para que os elementos HTML possam chamar a função
if (typeof window !== 'undefined') {
    window.handleConversationClick = handleConversationClick;
    console.log('[Hub] handleConversationClick registrado no window');
} else {
    console.error('[Hub] ERRO: window não está disponível!');
}

/**
 * Remove o badge de não lidas do item da lista da conversa aberta.
 * Comportamento WhatsApp: ao abrir/visualizar a conversa, ela é considerada lida e o badge zera.
 */
function clearUnreadBadgeForThread(threadId) {
    if (!threadId) return;
    document.querySelectorAll('.conversation-item').forEach(function(item) {
        if (item.dataset.threadId === threadId) {
            const badge = item.querySelector('.hub-unread-badge');
            if (badge) badge.remove();
        }
    });
}

async function loadConversation(threadId, channel) {
    console.log('[Hub] Carregando conversa:', threadId, channel);
    
    // Para polling anterior se existir (limpa completamente antes de iniciar nova)
    stopConversationPolling();
    
    // Limpa estado anterior COMPLETAMENTE antes de carregar nova conversa
    // Isso garante que não há preservação de estado errado entre conversas
    ConversationState.messageIds.clear();
    ConversationState.lastTimestamp = null;
    ConversationState.lastEventId = null;
    ConversationState.newMessagesCount = 0;
    
    // [LOG TEMPORARIO] Reset de estado
    console.log('[LOG TEMPORARIO] loadConversation() - ESTADO RESETADO para thread_id=' + threadId);
    
    // Atualiza estado
    ConversationState.currentThreadId = threadId;
    ConversationState.currentChannel = channel;
    
    // Persiste na URL (sem recarregar página)
    const url = new URL(window.location);
    url.searchParams.set('thread_id', threadId);
    url.searchParams.set('channel', channel);
    window.history.pushState({ threadId, channel }, '', url);
    
    // Salva também no sessionStorage como backup
    sessionStorage.setItem('hub_selected_thread_id', threadId);
    sessionStorage.setItem('hub_selected_channel', channel);
    
    // Marca conversa como ativa na lista
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.threadId === threadId) {
            item.classList.add('active');
            item.style.background = '#e7f3ff';
            item.style.borderColor = '#007bff';
        } else {
            // Remove estilo de outras conversas
            item.style.background = '';
            item.style.borderColor = '';
        }
    });
    
    // Mostra loading
    const placeholder = document.getElementById('conversation-placeholder');
    const content = document.getElementById('conversation-content');
    placeholder.style.display = 'none';
    content.style.display = 'flex';
    content.innerHTML = '<div style="flex: 1; display: flex; align-items: center; justify-content: center;"><div style="text-align: center;"><div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #023A8D; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div><p>Carregando conversa...</p></div></div>';
    
    try {
        // Busca dados da conversa
        const url = '<?= pixelhub_url('/communication-hub/thread-data') ?>?' + 
                   new URLSearchParams({ thread_id: threadId, channel: channel });
        console.log('[Hub] Carregando conversa - URL:', url);
        const response = await fetch(url);
        
        if (!response.ok) {
            console.error('[Hub] Erro HTTP:', response.status, response.statusText);
            const errorText = await response.text();
            console.error('[Hub] Resposta de erro:', errorText);
            throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('[Hub] Resposta recebida:', result);
        
        if (!result.success) {
            console.error('[Hub] API retornou success=false:', result.error);
            throw new Error(result.error || 'Erro ao carregar conversa');
        }
        
        if (!result.thread) {
            console.error('[Hub] API não retornou thread:', result);
            throw new Error('Conversa não encontrada');
        }
        
        console.log('[Hub] Thread encontrado:', result.thread);
        console.log('[Hub] Mensagens encontradas:', result.messages?.length || 0);
        
        // Renderiza conversa
        renderConversation(result.thread, result.messages, result.channel);
        
        // Zera badge de não lidas na lista: conversa aberta = considerada lida (comportamento WhatsApp)
        clearUnreadBadgeForThread(threadId);
        
        // Inicializa marcadores baseado no último item renderizado da conversa
        // IMPORTANTE: Deve ser chamado APÓS renderConversation para garantir que os markers
        // sejam baseados no último item renderizado, não em estado anterior
        initializeConversationMarkers();
        
        // Inicia polling nessa thread e para a anterior sem preservar estado errado
        // (stopConversationPolling já foi chamado no início da função)
        startConversationPolling();
        
    } catch (error) {
        console.error('[Hub] Erro ao carregar conversa:', error);
        content.innerHTML = '<div style="flex: 1; display: flex; align-items: center; justify-content: center;"><div style="text-align: center; color: #dc3545;"><p>Erro ao carregar conversa</p><p style="font-size: 13px;">' + escapeHtml(error.message) + '</p></div></div>';
    }
}

/**
 * Inicializa event delegation global para viewer de mídia (executa uma vez)
 */
function initMediaViewerOnce() {
    if (window.__mediaViewerInitialized) return;
    window.__mediaViewerInitialized = true;
    
    // Event delegation global no document (funciona mesmo após re-render)
    document.addEventListener('click', function(e) {
        // Abrir modal ao clicar em mídia
        const btn = e.target.closest('.hub-media-open');
        const img = e.target.closest('.hub-media-thumb');
        const target = btn || img;
        
        if (target) {
            e.preventDefault();
            const src = target.getAttribute('data-src') || target.src;
            if (src) {
                openMediaViewer(src);
            }
            return;
        }
        
        // Fechar modal ao clicar no botão fechar
        const closeBtn = e.target.closest('#hub-media-close');
        if (closeBtn) {
            e.preventDefault();
            const viewer = document.getElementById('hub-media-viewer');
            if (viewer) {
                viewer.style.display = 'none';
            }
            return;
        }
        
        // Fechar modal ao clicar no overlay (fora da imagem e botões)
        const viewer = document.getElementById('hub-media-viewer');
        if (viewer && (e.target === viewer || e.target.classList.contains('viewer-container'))) {
            viewer.style.display = 'none';
            return;
        }
    });
    
    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const viewer = document.getElementById('hub-media-viewer');
            if (viewer && viewer.style.display !== 'none') {
                viewer.style.display = 'none';
            }
        }
    });
}

/**
 * Inicializa listeners do modal de viewer de mídia
 * Nota: Os listeners de fechar (botão, overlay, ESC) estão em initMediaViewerOnce()
 * usando event delegation para funcionar mesmo após re-render do modal
 */
function initMediaViewer() {
    const viewer = document.getElementById('hub-media-viewer');
    const img = document.getElementById('hub-media-viewer-img');
    const downloadBtn = document.getElementById('hub-media-download');
    const openNewBtn = document.getElementById('hub-media-open-new');
    
    if (!viewer || !img || !downloadBtn || !openNewBtn) {
        return; // Modal ainda não foi criado
    }
    
    // Os listeners de fechar estão em initMediaViewerOnce() usando event delegation
    // Isso garante que funcionem mesmo quando o modal é recriado
}

/**
 * Abre o viewer de mídia com a imagem especificada
 */
function openMediaViewer(src) {
    const viewer = document.getElementById('hub-media-viewer');
    const img = document.getElementById('hub-media-viewer-img');
    const downloadBtn = document.getElementById('hub-media-download');
    const openNewBtn = document.getElementById('hub-media-open-new');
    
    if (!viewer || !img || !downloadBtn || !openNewBtn) {
        return;
    }
    
    // Limpa estilos anteriores
    img.removeAttribute('style');
    img.removeAttribute('width');
    img.removeAttribute('height');
    
    // Função para escalar imagem (PARA CIMA se necessário)
    function fitToViewport() {
        const vw = window.innerWidth * 0.88;
        const vh = window.innerHeight * 0.88;
        const nw = img.naturalWidth;
        const nh = img.naturalHeight;
        
        if (!nw || !nh) return;
        
        // Calcula escala para caber na viewport
        const scale = Math.min(vw / nw, vh / nh);
        
        // Aplica dimensões (escala para cima OU para baixo conforme necessário)
        img.style.width = Math.round(nw * scale) + 'px';
        img.style.height = Math.round(nh * scale) + 'px';
    }
    
    // Quando carregar, ajusta tamanho
    img.onload = fitToViewport;
    
    // Define a imagem
    img.src = src;
    
    // Se já em cache, ajusta imediatamente
    if (img.complete && img.naturalWidth) {
        fitToViewport();
    }
    
    // Define o link de download
    downloadBtn.onclick = function() {
        const a = document.createElement('a');
        a.href = src;
        a.download = '';
        a.click();
    };
    
    // Define o link para abrir em nova aba
    openNewBtn.onclick = function() {
        window.open(src, '_blank');
    };
    
    // Mobile: garante ações visíveis
    const actions = viewer.querySelector('.viewer-actions');
    if (actions) {
        if (window.innerWidth <= 768) {
            actions.classList.add('visible');
        } else {
            actions.classList.remove('visible');
        }
    }
    
    // Mostra o modal
    viewer.style.display = 'flex';
}

/**
 * Renderiza a conversa no painel direito
 */
function renderConversation(thread, messages, channel) {
    const placeholder = document.getElementById('conversation-placeholder');
    const content = document.getElementById('conversation-content');
    
    // Log para debug: verifica channel_id da thread
    console.log('[CommunicationHub] renderConversation - thread.channel_id:', thread.channel_id, 'thread:', thread);
    
    // Oculta placeholder e mostra conteúdo
    if (placeholder) placeholder.style.display = 'none';
    if (content) content.style.display = 'flex';
    
    // Ativa modo thread no mobile
    const body = document.getElementById('communication-body');
    if (body) {
        body.classList.add('view-thread');
    }
    
    const contactName = thread.contact_name || thread.tenant_name || 'Cliente';
    const contact = thread.contact || 'Número não identificado';
    
    let html = `
        <div class="conversation-thread-header" id="conversation-header">
            <button class="back-button" onclick="backToConversationList()" title="Voltar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="info">
                <strong>${escapeHtml(contactName)}</strong>
                ${channel === 'whatsapp' ? `<small>${escapeHtml(contact)}${thread.channel_id ? ` • Sessão: ${escapeHtml(thread.channel_id)}` : ''}</small>` : ''}
            </div>
        </div>
        
        <div class="commhub-thread-shell">
            <div class="conversation-messages-container" id="messages-container" style="position: relative;">
                <div id="new-messages-badge" class="new-messages-badge">
                    <span id="new-messages-count">1</span> nova(s) mensagem(ns)
                </div>
                <div class="commhub-messages-inner">
    `;
    
    if (messages.length === 0) {
        html += `
            <div style="text-align: center; padding: 40px; color: #666;">
                <p>Nenhuma mensagem ainda</p>
                <p style="font-size: 13px; margin-top: 10px;">Envie a primeira mensagem abaixo</p>
            </div>
        `;
    } else {
        messages.forEach(msg => {
            const isOutbound = msg.direction === 'outbound';
            // CORREÇÃO: Timestamps de mensagens já estão em Brasília - exibe direto
            const timeStr = formatMessageTimestamp(msg.timestamp);
            
            // Renderiza mídia se existir (passa event_id para transcrição)
            const mediaHtml = (msg.media && msg.media.url) ? renderMediaPlayer(msg.media, msg.id) : '';
            
            // Conteúdo da mensagem (só mostra se não estiver vazio e não for placeholder de áudio com mídia)
            const content = msg.content || '';
            const isAudioPlaceholder = content && /^\[(?:Á|A)udio\]$/i.test(content.trim());
            const shouldShowContent = content && content.trim() && !(mediaHtml && isAudioPlaceholder);
            const contentHtml = shouldShowContent
                ? `<div style="font-size: 14.2px; color: #111b21; line-height: 1.4; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word; ${mediaHtml ? 'margin-top: 8px;' : ''}">${escapeHtml(content)}</div>`
                : '';
            
            // Se não há conteúdo nem mídia, pula
            const hasContent = shouldShowContent || mediaHtml;
            if (!hasContent) {
                return;
            }
            
            // Header: apenas remetente para mensagens outbound (sessão/canal mostrado só no header da conversa)
            const sentByName = msg.sent_by_name || '';
            let headerHtml = '';
            if (isOutbound && sentByName) {
                headerHtml = `<div style="font-size: 10px; color: #667781; margin-bottom: 3px;"><span style="font-weight: 600;">Enviado por: ${escapeHtml(sentByName)}</span></div>`;
            }
            
            html += `
                <div class="message-bubble ${msg.direction}" 
                     data-message-id="${escapeHtml(msg.id || '')}"
                     data-timestamp="${escapeHtml(msg.timestamp || '')}"
                     style="margin-bottom: 6px; display: flex; ${isOutbound ? 'justify-content: flex-end;' : 'justify-content: flex-start;'}">
                    <div style="max-width: 70%; padding: 7px 12px; border-radius: 7.5px; ${isOutbound ? 'background: #dcf8c6; margin-left: auto; border-bottom-right-radius: 2px;' : 'background: white; border-bottom-left-radius: 2px;'}">
                        ${headerHtml}
                        ${mediaHtml}
                        ${contentHtml}
                        <div style="font-size: 11px; color: #667781; margin-top: 3px; text-align: right; padding-top: 2px; opacity: 0.8;">
                            ${timeStr}
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    html += `
                </div>
            </div>
            
            <div class="conversation-composer">
                <div class="hub-composer-wrap">
                <!-- Composer único com estados: Idle / Recording / Preview -->
                <div class="hub-composer" id="hubComposer">
                    <!-- Botão Anexar (sempre visível) -->
                    <button class="hub-icon-btn" id="btnAttach" type="button" title="Anexar" aria-label="Anexar">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M16.5 6.5l-7.9 7.9a3 3 0 104.2 4.2l8.2-8.2a5 5 0 10-7.1-7.1L6.1 11.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>

                    <!-- Estado Idle: Textarea -->
                    <textarea id="hubText" class="hub-text" rows="1" placeholder="Digite sua mensagem..." 
                              onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); const btnSend = document.getElementById('btnSend'); if (!btnSend.hidden) btnSend.click(); }"
                              oninput="autoResizeTextarea(this)"></textarea>

                    <!-- Estado Recording: Timer + bolinha -->
                    <div id="hubRecStatus" class="hub-rec-status" hidden>
                        <span class="hub-rec-dot" aria-hidden="true"></span>
                        <span class="hub-rec-time" id="hubRecTime">0:00</span>
                        <span class="hub-rec-max" id="hubRecMax">/ 2:00</span>
                    </div>

                    <!-- Estado Preview: Player -->
                    <audio id="hubAudioPreview" controls class="hub-audio-preview" hidden></audio>

                    <!-- Estado Idle: Microfone ou Enviar -->
                    <button class="hub-icon-btn" id="btnMic" type="button" title="Gravar áudio" aria-label="Gravar áudio">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 14a3 3 0 003-3V6a3 3 0 10-6 0v5a3 3 0 003 3zm5-3a5 5 0 01-10 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 19v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 22h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>

                    <button class="hub-icon-btn hub-send" id="btnSend" type="button" title="Enviar" aria-label="Enviar" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M22 2L11 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M22 2l-7 20-4-9-9-4 20-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>

                    <!-- Estado Recording: Stop + Cancelar -->
                    <button class="hub-icon-btn" id="btnRecStop" type="button" title="Parar gravação" aria-label="Parar gravação" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>
                    </button>

                    <button class="hub-icon-btn" id="btnRecCancel" type="button" title="Cancelar" aria-label="Cancelar" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 6V4h8v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 6l1 16h10l1-16" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>

                    <!-- Estado Preview: Cancelar + Regravar + Enviar -->
                    <button class="hub-icon-btn" id="btnReviewCancel" type="button" title="Cancelar" aria-label="Cancelar" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 6V4h8v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 6l1 16h10l1-16" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>

                    <button class="hub-icon-btn" id="btnReviewRerecord" type="button" title="Regravar" aria-label="Regravar" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 14a3 3 0 003-3V6a3 3 0 10-6 0v5a3 3 0 003 3zm5-3a5 5 0 01-10 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 19v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 22h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>

                    <button class="hub-icon-btn hub-send" id="btnReviewSend" type="button" title="Enviar áudio" aria-label="Enviar áudio" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M22 2L11 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M22 2l-7 20-4-9-9-4 20-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>

                    <!-- Estado Sending: Spinner -->
                    <span id="hubSending" class="hub-sending" hidden>Enviando...</span>
                </div>
            </div>
            <!-- Input de arquivo oculto para anexos -->
            <input type="file" id="hub-file-input" accept="image/*,.pdf,.doc,.docx" style="display: none;">
            
            <!-- Preview de mídia selecionada (compacto, estilo WhatsApp) -->
            <div id="hub-media-preview" style="display: none; padding: 8px 12px; background: #f5f5f5; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e0e0e0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <!-- Thumbnail compacto (clicável) -->
                    <div id="hub-media-thumb-container" style="display: none; position: relative; cursor: pointer; flex-shrink: 0;" onclick="expandMediaPreview()" title="Clique para ampliar">
                        <img id="hub-media-preview-img" src="" alt="Preview" style="width: 80px; height: 80px; border-radius: 6px; object-fit: cover; border: 1px solid #ddd;">
                        <!-- Indicador monocromático -->
                        <div style="position: absolute; bottom: 3px; right: 3px; background: rgba(0,0,0,0.4); width: 16px; height: 16px; border-radius: 2px; display: flex; align-items: center; justify-content: center;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                        </div>
                    </div>
                    <!-- Ícone para documentos -->
                    <div id="hub-media-doc-container" style="display: none; width: 60px; height: 60px; background: white; border-radius: 6px; border: 1px solid #ddd; flex-shrink: 0; align-items: center; justify-content: center;">
                        <span id="hub-media-preview-icon" style="font-size: 28px; color: #666;">📄</span>
                    </div>
                    <!-- Info do arquivo -->
                    <div style="flex: 1; min-width: 0;">
                        <div id="hub-media-name" style="font-size: 12px; font-weight: 500; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;"></div>
                        <div id="hub-media-size" style="font-size: 11px; color: #888;"></div>
                    </div>
                    <!-- Botão remover (monocromático) -->
                    <button type="button" id="hub-media-remove" style="padding: 6px 10px; background: transparent; color: #666; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 11px; flex-shrink: 0; transition: all 0.15s;" onmouseover="this.style.background='#eee'; this.style.borderColor='#999';" onmouseout="this.style.background='transparent'; this.style.borderColor='#ccc';">✕</button>
                </div>
            </div>
            
            <input type="hidden" id="hub-channel" value="${escapeHtml(channel)}">
            <input type="hidden" id="hub-thread-id" value="${escapeHtml(thread.thread_id || '')}">
            <input type="hidden" id="hub-tenant-id" value="${thread.tenant_id || ''}">
            <input type="hidden" id="hub-channel-id" value="${thread.channel_id || ''}">
            ${channel === 'whatsapp' && thread.contact ? `<input type="hidden" id="hub-to" value="${escapeHtml(thread.contact)}">` : ''}
            </div>
        </div>
        
        <!-- Modal Viewer de Mídia (Redesign: ações no topo, fit proporcional) -->
        <style>
            #hub-media-viewer {
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
            #hub-media-viewer .viewer-container {
                position: relative;
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            /* ==============================================
               VIEWER: regras 100% ISOLADAS do thumbnail
               PRINCÍPIO: max-width + max-height + height:auto
               = escala proporcionalmente, nunca distorce
               ============================================== */
            #hub-media-viewer-img {
                /* REGRA DE OURO: só limites máximos, dimensões auto */
                max-width: 90vw;
                max-height: 90vh;
                width: auto;
                height: auto;
                /* Visual */
                border-radius: 8px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.6);
                background: transparent;
            }
            #hub-media-viewer .viewer-actions {
                position: absolute;
                top: 16px;
                right: 16px;
                display: flex;
                gap: 8px;
                opacity: 0;
                transition: opacity 0.2s ease;
                z-index: 10001;
            }
            #hub-media-viewer:hover .viewer-actions,
            #hub-media-viewer .viewer-actions.visible {
                opacity: 1;
            }
            #hub-media-viewer .viewer-btn {
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
            #hub-media-viewer .viewer-btn:hover {
                background: rgba(255,255,255,0.3);
                transform: scale(1.08);
            }
            #hub-media-viewer .viewer-btn svg {
                width: 20px;
                height: 20px;
                fill: currentColor;
            }
            #hub-media-viewer .viewer-btn-close {
                background: rgba(220,53,69,0.7);
            }
            #hub-media-viewer .viewer-btn-close:hover {
                background: rgba(220,53,69,0.9);
            }
            /* Mobile: sempre visível */
            @media (max-width: 768px) {
                #hub-media-viewer .viewer-actions {
                    opacity: 1;
                }
                #hub-media-viewer .viewer-btn {
                    width: 48px;
                    height: 48px;
                }
            }
            /* Tooltip */
            #hub-media-viewer .viewer-btn[title]:hover::after {
                content: attr(title);
                position: absolute;
                top: 52px;
                right: 0;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
            }
        </style>
        <div id="hub-media-viewer">
            <div class="viewer-container">
                <!-- Ações no topo direito -->
                <div class="viewer-actions">
                    <button id="hub-media-download" class="viewer-btn" title="Baixar">
                        <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    </button>
                    <button id="hub-media-open-new" class="viewer-btn" title="Abrir em nova aba">
                        <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                    </button>
                    <button id="hub-media-close" class="viewer-btn viewer-btn-close" title="Fechar">
                        <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </button>
                </div>
                <!-- Imagem proporcional (fit to screen) -->
                <img id="hub-media-viewer-img" src="" alt="Mídia">
            </div>
        </div>
        </div>
    `;
    
    if (content) {
        content.innerHTML = html;
    }
    
    // Scroll para o final após renderizar
    setTimeout(() => {
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
            ConversationState.autoScroll = true;
        }
    }, 150);
    
    // Adiciona listener no badge de novas mensagens
    const badge = document.getElementById('new-messages-badge');
    if (badge) {
        badge.addEventListener('click', function() {
            const container = document.getElementById('messages-container');
            if (container) {
                ConversationState.autoScroll = true;
                container.scrollTop = container.scrollHeight;
                hideNewMessagesBadge();
            }
        });
    }
    
    // Inicializa event delegation para viewer de mídia (uma vez, global)
    // Usa event delegation, então funciona mesmo após re-render do modal
    initMediaViewerOnce();
    
    // Inicializa listeners adicionais do modal (sempre que o modal é recriado)
    // Aguarda um pouco para garantir que o modal foi criado no DOM
    setTimeout(() => {
        initMediaViewer();
    }, 100);
    
    // Adiciona listener de scroll no header (sombra quando scrolla)
    const header = document.getElementById('conversation-header');
    const container = document.getElementById('messages-container');
    if (header && container) {
        container.addEventListener('scroll', function() {
            if (container.scrollTop > 0) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }
    
    // Inicializa composer de áudio
    initComposerAudio();
}

/**
 * Inicializa funcionalidade de gravação de áudio no composer
 * Estados: Idle → Recording → Review
 */
function initComposerAudio() {
    const hubText = document.getElementById('hubText');
    const btnMic = document.getElementById('btnMic');
    const btnSend = document.getElementById('btnSend');
    const hubRecStatus = document.getElementById('hubRecStatus');
    const hubRecTime = document.getElementById('hubRecTime');
    const hubRecMax = document.getElementById('hubRecMax');
    const hubAudioPreview = document.getElementById('hubAudioPreview');
    const btnRecStop = document.getElementById('btnRecStop');
    const btnRecCancel = document.getElementById('btnRecCancel');
    const btnReviewCancel = document.getElementById('btnReviewCancel');
    const btnReviewRerecord = document.getElementById('btnReviewRerecord');
    const btnReviewSend = document.getElementById('btnReviewSend');
    const hubSending = document.getElementById('hubSending');
    
    const MAX_RECORDING_TIME = 120000; // 2 minutos em ms
    
    if (!hubText || !btnMic || !btnSend) {
        console.warn('[AudioRecorder] Elementos básicos não encontrados, abortando inicialização');
        return; // Elementos não existem ainda
    }
    
    // Valida elementos críticos para gravação
    if (!hubRecTime) {
        console.error('[AudioRecorder] ERRO CRÍTICO: hubRecTime não encontrado! O timer não funcionará.');
    }
    if (!hubRecStatus) {
        console.error('[AudioRecorder] ERRO CRÍTICO: hubRecStatus não encontrado! O status de gravação não será exibido.');
    }
    
    let recorder = null;
    let recStream = null;
    let recChunks = [];
    let recBlob = null;
    let recTimer = null;
    let recStart = 0;
    let currentState = 'idle'; // idle, recording, preview, sending
    
    // ============================================================================
    // Estado de Mídia para Anexos (deve ser definido antes de updateSendMicVisibility)
    // ============================================================================
    const MediaAttachState = {
        file: null,
        base64: null,
        mimeType: null,
        fileName: null,
        fileSize: null,
        type: null // 'image' ou 'document'
    };
    
    function clearMediaAttachState() {
        MediaAttachState.file = null;
        MediaAttachState.base64 = null;
        MediaAttachState.mimeType = null;
        MediaAttachState.fileName = null;
        MediaAttachState.fileSize = null;
        MediaAttachState.type = null;
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }
    
    // Gerenciamento de estados
    function setState(state) {
        currentState = state;
        
        // Estado Idle
        if (state === 'idle') {
            hubText.hidden = false;
            hubRecStatus.hidden = true;
            hubAudioPreview.hidden = true;
            btnRecStop.hidden = true;
            btnRecCancel.hidden = true;
            btnReviewCancel.hidden = true;
            btnReviewSend.hidden = true;
            hubSending.hidden = true;
            updateSendMicVisibility();
        }
        // Estado Recording
        else if (state === 'recording') {
            hubText.hidden = true;
            hubRecStatus.hidden = false; // Mostra timer de gravação
            hubAudioPreview.hidden = true;
            btnRecStop.hidden = false;
            btnRecCancel.hidden = false;
            btnReviewCancel.hidden = true;
            btnReviewSend.hidden = true;
            btnMic.hidden = true;
            btnSend.hidden = true;
            hubSending.hidden = true;
            
            // Garante que o elemento de tempo está visível e atualizado
            if (hubRecTime) {
                hubRecTime.textContent = hubRecTime.textContent || '0:00';
                // Força visibilidade (remove atributo hidden se existir)
                hubRecTime.removeAttribute('hidden');
            }
            if (hubRecStatus) {
                hubRecStatus.removeAttribute('hidden');
            }
            
            console.log('[AudioRecorder] Estado mudado para recording. Elementos:', {
                hubRecStatusHidden: hubRecStatus ? hubRecStatus.hidden : 'N/A',
                hubRecTimeText: hubRecTime ? hubRecTime.textContent : 'N/A',
                hubRecTimeHidden: hubRecTime ? hubRecTime.hidden : 'N/A'
            });
        }
        // Estado Preview
        else if (state === 'preview') {
            hubText.hidden = true;
            hubRecStatus.hidden = true;
            hubAudioPreview.hidden = false;
            btnRecStop.hidden = true;
            btnRecCancel.hidden = true;
            btnReviewCancel.hidden = false;
            btnReviewRerecord.hidden = false;
            btnReviewSend.hidden = false;
            btnMic.hidden = true;
            btnSend.hidden = true;
            hubSending.hidden = true;
        }
        // Estado Sending
        else if (state === 'sending') {
            hubText.hidden = true;
            hubRecStatus.hidden = true;
            hubAudioPreview.hidden = true;
            btnRecStop.hidden = true;
            btnRecCancel.hidden = true;
            btnReviewCancel.hidden = true;
            btnReviewRerecord.hidden = true;
            btnReviewSend.hidden = true;
            btnMic.hidden = true;
            btnSend.hidden = true;
            hubSending.hidden = false;
        }
    }
    
    function updateSendMicVisibility() {
        const hasText = (hubText.value || '').trim().length > 0;
        const hasMedia = MediaAttachState.base64 !== null;
        const hasContent = hasText || hasMedia;
        btnSend.hidden = !hasContent || currentState !== 'idle';
        btnMic.hidden = hasContent || currentState !== 'idle';
    }
    
    hubText.addEventListener('input', updateSendMicVisibility);
    updateSendMicVisibility();
    
    function fmtTime(ms) {
        const s = Math.floor(ms / 1000);
        const mm = Math.floor(s / 60);
        const ss = String(s % 60).padStart(2, '0');
        return `${mm}:${ss}`;
    }
    
    // Estado: Idle → Recording
    async function startRecording() {
        try {
            // Verifica se a API está disponível
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Seu navegador não suporta gravação de áudio. Use Chrome, Firefox ou Edge atualizado.');
                return;
            }
            
            // Verifica se está em contexto seguro (HTTPS ou localhost)
            const isSecureContext = window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
            if (!isSecureContext) {
                alert('Gravação de áudio requer conexão segura (HTTPS). Acesse o site via HTTPS.');
                return;
            }
            
            recStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            // Tenta OGG/Opus primeiro (pra passar o "OpusHead")
            const candidates = [
                'audio/ogg;codecs=opus',
                'audio/ogg',
            ];
            const mimeType = candidates.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || '';
            
            // Configura gravação com bitrate baixo (24kbps é suficiente para voz)
            // Isso mantém áudios de 2 minutos abaixo de 400KB
            const recorderOptions = {
                audioBitsPerSecond: 24000 // 24kbps - qualidade boa para voz, tamanho pequeno
            };
            if (mimeType) {
                recorderOptions.mimeType = mimeType;
            }
            recorder = new MediaRecorder(recStream, recorderOptions);
            recChunks = [];
            recBlob = null;
            
            recorder.ondataavailable = (e) => { if (e.data && e.data.size) recChunks.push(e.data); };
            recorder.onstop = () => {
                recBlob = new Blob(recChunks, { type: recorder.mimeType || 'audio/ogg' });
            };
            
            recorder.start();
            
            // Muda para estado Recording ANTES de iniciar o timer
            setState('recording');
            
            recStart = Date.now();
            
            // Garante que o elemento existe antes de atualizar
            if (hubRecTime) {
                hubRecTime.textContent = '0:00';
            } else {
                console.error('[AudioRecorder] ERRO: hubRecTime não encontrado!');
            }
            
            if (hubRecMax) hubRecMax.hidden = false;
            
            // Inicia timer de atualização
            recTimer = setInterval(() => {
                // Valida elementos antes de atualizar
                if (!hubRecTime) {
                    console.error('[AudioRecorder] ERRO: hubRecTime não encontrado durante timer!');
                    clearInterval(recTimer);
                    recTimer = null;
                    return;
                }
                
                const elapsed = Date.now() - recStart;
                const timeStr = fmtTime(elapsed);
                hubRecTime.textContent = timeStr;
                
                // Log a cada 5 segundos para debug
                if (Math.floor(elapsed / 5000) !== Math.floor((elapsed - 200) / 5000)) {
                    console.log('[AudioRecorder] Tempo de gravação:', timeStr);
                }
                
                // Para automaticamente após 2 minutos
                if (elapsed >= MAX_RECORDING_TIME) {
                    console.log('[AudioRecorder] Tempo máximo atingido, parando gravação');
                    stopRecording();
                }
            }, 200);
            
            console.log('[AudioRecorder] Gravação iniciada, timer configurado. Elementos:', {
                hubRecTime: !!hubRecTime,
                hubRecStatus: !!hubRecStatus,
                hubRecStatusHidden: hubRecStatus ? hubRecStatus.hidden : 'N/A'
            });
        } catch (err) {
            console.error('[AudioRecorder] Erro ao acessar microfone:', err);
            
            let errorMessage = 'Não consegui acessar o microfone.';
            
            // Mensagens específicas baseadas no tipo de erro
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                errorMessage = 'Acesso ao microfone negado. Por favor, permita o acesso ao microfone nas configurações do navegador e tente novamente.';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                errorMessage = 'Nenhum microfone encontrado. Verifique se há um microfone conectado ao seu dispositivo.';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                errorMessage = 'O microfone está sendo usado por outra aplicação. Feche outras aplicações que possam estar usando o microfone e tente novamente.';
            } else if (err.name === 'OverconstrainedError' || err.name === 'ConstraintNotSatisfiedError') {
                errorMessage = 'O microfone não atende aos requisitos necessários. Tente usar outro dispositivo de áudio.';
            } else if (err.name === 'SecurityError') {
                errorMessage = 'Erro de segurança ao acessar o microfone. Certifique-se de que está acessando o site via HTTPS.';
            } else if (err.message) {
                errorMessage = `Erro ao acessar microfone: ${err.message}`;
            }
            
            errorMessage += '\n\nDica: Verifique as permissões do navegador no ícone de cadeado na barra de endereços.';
            
            alert(errorMessage);
            
            // Reseta para estado idle em caso de erro
            resetToIdle();
        }
    }
    
    // Estado: Recording → Review
    function stopRecording() {
        if (!recorder || recorder.state === 'inactive') return;
        
        clearInterval(recTimer);
        recTimer = null;
        recorder.stop();
        
        // Para o stream de áudio
        if (recStream) {
            recStream.getTracks().forEach(t => t.stop());
            recStream = null;
        }
        
        // Aguarda um pouco para garantir que onstop gerou o blob
        setTimeout(() => {
            if (!recBlob || recBlob.size < 2000) {
                resetToIdle();
                alert('Áudio muito curto. Grave um pouco mais.');
                return;
            }
            
            // Valida formato do áudio
            const mimeType = recorder.mimeType || recBlob.type || '';
            const isOggOpus = mimeType.includes('ogg') || mimeType.includes('opus');
            const isWebM = mimeType.includes('webm');
            
            console.log('[AudioRecorder] Formato capturado:', mimeType, '| Tamanho:', recBlob.size, 'bytes');
            
            // WebM/Opus é aceito (navegadores modernos gravam nesse formato)
            // O gateway pode aceitar WebM, então não bloqueamos
            if (isWebM && !isOggOpus) {
                console.log('[AudioRecorder] ℹ️ Áudio gravado em WebM/Opus - será enviado ao gateway (pode ser aceito)');
                // Não bloqueia mais - apenas informa
            }
            
            // Cria URL para preview
            const audioUrl = URL.createObjectURL(recBlob);
            hubAudioPreview.src = audioUrl;
            
            // Aguarda carregar metadados para mostrar duração correta
            hubAudioPreview.onloadedmetadata = () => {
                const duration = hubAudioPreview.duration;
                if (duration && !isNaN(duration) && isFinite(duration)) {
                    const minutes = Math.floor(duration / 60);
                    const seconds = Math.floor(duration % 60);
                    console.log('[AudioRecorder] Duração do áudio carregada:', minutes + ':' + String(seconds).padStart(2, '0'));
                } else {
                    console.warn('[AudioRecorder] Duração não disponível ou inválida');
                }
            };
            
            // Força carregar metadados
            hubAudioPreview.load();
            
            // Guarda URL para limpar depois
            window.__currentAudioUrl = audioUrl;
            
            // Muda para estado Preview
            setState('preview');
        }, 150);
    }
    
    // Estado: Review → Idle (cancelar)
    function resetToIdle() {
        // Para timer se estiver rodando
        if (recTimer) {
            clearInterval(recTimer);
            recTimer = null;
        }
        
        // Para recorder se estiver ativo
        if (recorder && recorder.state !== 'inactive') {
            try { recorder.stop(); } catch (e) {}
        }
        
        // Para stream
        if (recStream) {
            recStream.getTracks().forEach(t => t.stop());
            recStream = null;
        }
        
        // Limpa preview e memória
        if (hubAudioPreview.src) {
            URL.revokeObjectURL(hubAudioPreview.src);
            hubAudioPreview.src = '';
            hubAudioPreview.load(); // Força limpar buffer do player
        }
        
        // Limpa URL global se existir
        if (window.__currentAudioUrl) {
            try {
                URL.revokeObjectURL(window.__currentAudioUrl);
            } catch (e) {}
            window.__currentAudioUrl = null;
        }
        
        // Reseta variáveis (limpeza completa)
        recChunks = [];
        recBlob = null;
        recorder = null;
        recStream = null;
        hubRecTime.textContent = '0:00';
        if (hubRecMax) hubRecMax.hidden = true;
        
        // Para qualquer timer restante
        if (recTimer) {
            clearInterval(recTimer);
            recTimer = null;
        }
        
        // Volta para estado Idle
        setState('idle');
    }
    
    function blobToDataUrl(blob) {
        return new Promise((resolve, reject) => {
            const fr = new FileReader();
            fr.onload = () => resolve(fr.result);
            fr.onerror = reject;
            fr.readAsDataURL(blob);
        });
    }

    /**
     * Limite máximo de tamanho do áudio em bytes para envio.
     * O servidor LiteSpeed tem limite de ~500KB para request body.
     * Usamos 400KB para ter margem de segurança (base64 aumenta ~33%).
     */
    const MAX_AUDIO_SIZE_BYTES = 400 * 1024; // 400KB
    
    /**
     * Comprime o áudio reduzindo bitrate se exceder o limite.
     * Usa Web Audio API para decodificar e re-codificar com bitrate menor.
     */
    async function compressAudioIfNeeded(blob, targetSizeKb = 400) {
        const currentSizeKb = blob.size / 1024;
        
        // Se já está pequeno o suficiente, retorna sem modificar
        if (blob.size <= targetSizeKb * 1024) {
            console.log(`[AudioCompressor] Áudio já está pequeno (${currentSizeKb.toFixed(1)}KB), não precisa comprimir`);
            return blob;
        }
        
        console.log(`[AudioCompressor] Áudio grande (${currentSizeKb.toFixed(1)}KB), iniciando compressão...`);
        
        // Verifica suporte
        if (!window.AudioContext && !window.webkitAudioContext) {
            console.warn('[AudioCompressor] AudioContext não suportado, enviando original');
            return blob;
        }
        
        if (!window.MediaRecorder || !MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
            console.warn('[AudioCompressor] OGG/Opus não suportado, enviando original');
            return blob;
        }
        
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const arrayBuffer = await blob.arrayBuffer();
            const audioBuffer = await ctx.decodeAudioData(arrayBuffer);
            
            // Calcula bitrate alvo baseado no tamanho desejado
            // targetSize = (bitrate * duration) / 8
            // bitrate = (targetSize * 8) / duration
            const durationSecs = audioBuffer.duration;
            const targetBitrate = Math.floor((targetSizeKb * 1024 * 8) / durationSecs);
            
            // Limita bitrate entre 12kbps (mínimo aceitável) e 24kbps
            const finalBitrate = Math.max(12000, Math.min(24000, targetBitrate));
            
            console.log(`[AudioCompressor] Duração: ${durationSecs.toFixed(1)}s, Bitrate alvo: ${(finalBitrate/1000).toFixed(1)}kbps`);
            
            // Cria MediaStream a partir do AudioBuffer
            const dest = ctx.createMediaStreamDestination();
            const source = ctx.createBufferSource();
            source.buffer = audioBuffer;
            source.connect(dest);
            source.start(0);
            
            // Grava com o novo bitrate
            return new Promise((resolve, reject) => {
                const chunks = [];
                const mr = new MediaRecorder(dest.stream, { 
                    mimeType: 'audio/ogg;codecs=opus',
                    audioBitsPerSecond: finalBitrate
                });
                
                mr.ondataavailable = e => {
                    if (e.data && e.data.size) chunks.push(e.data);
                };
                
                mr.onstop = () => {
                    const compressedBlob = new Blob(chunks, { type: 'audio/ogg;codecs=opus' });
                    const newSizeKb = compressedBlob.size / 1024;
                    const reduction = ((1 - compressedBlob.size / blob.size) * 100).toFixed(1);
                    console.log(`[AudioCompressor] ✅ Compressão concluída: ${currentSizeKb.toFixed(1)}KB → ${newSizeKb.toFixed(1)}KB (${reduction}% redução)`);
                    ctx.close();
                    resolve(compressedBlob);
                };
                
                mr.onerror = (e) => {
                    console.error('[AudioCompressor] Erro na compressão:', e);
                    ctx.close();
                    reject(e);
                };
                
                mr.start(100);
                
                // Para a gravação quando o áudio terminar
                source.onended = () => {
                    setTimeout(() => {
                        try { mr.stop(); } catch (_) {}
                    }, 200);
                };
                
                // Timeout de segurança
                setTimeout(() => {
                    if (mr.state === 'recording') {
                        try { mr.stop(); } catch (_) {}
                    }
                }, Math.ceil(durationSecs * 1000) + 500);
            });
            
        } catch (err) {
            console.error('[AudioCompressor] Erro ao comprimir:', err);
            // Em caso de erro, retorna o blob original
            return blob;
        }
    }
    
    /**
     * Converte WebM/Opus para OGG/Opus quando o navegador suporta gravar em OGG.
     * WhatsApp exige OGG/Opus para voice; Chrome grava em WebM, Firefox em OGG.
     * Se não suportar OGG, retorna o blob original (o backend pode converter com ffmpeg).
     */
    async function ensureOggForSend(blob) {
        const mime = (blob.type || '').toLowerCase();
        if (mime.indexOf('ogg') >= 0 || mime.indexOf('opus') >= 0) {
            return blob;
        }
        if (mime.indexOf('webm') < 0) {
            return blob;
        }
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        let buf;
        try {
            buf = await ctx.decodeAudioData(await blob.arrayBuffer());
        } catch (e) {
            console.warn('[AudioRecorder] decodeAudioData falhou, enviando WebM:', e);
            return blob;
        }
        if (!window.MediaRecorder || !MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
            console.log('[AudioRecorder] Navegador não suporta OGG/Opus; enviando WebM (backend pode converter com ffmpeg)');
            return blob;
        }
        const dest = ctx.createMediaStreamDestination();
        const src = ctx.createBufferSource();
        src.buffer = buf;
        src.connect(dest);
        src.start(0);
        src.stop(buf.duration);
        return new Promise((resolve, reject) => {
            const chunks = [];
            const mr = new MediaRecorder(dest.stream, { mimeType: 'audio/ogg;codecs=opus' });
            mr.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };
            mr.onstop = () => resolve(new Blob(chunks, { type: 'audio/ogg;codecs=opus' }));
            mr.onerror = () => reject(new Error('Falha ao converter áudio para OGG'));
            mr.start(100);
            setTimeout(() => {
                try { mr.stop(); } catch (_) {}
            }, Math.ceil(buf.duration * 1000) + 300);
        });
    }
    
    function processMediaFile(file) {
        if (!file) return;
        console.log('[Media] Processando arquivo:', file.name, file.type, file.size);
        
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
        MediaAttachState.type = isImage ? 'image' : 'document';
        MediaAttachState.file = file;
        MediaAttachState.mimeType = file.type;
        MediaAttachState.fileName = file.name;
        MediaAttachState.fileSize = file.size;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const result = e.target.result;
            MediaAttachState.base64 = result.split(',')[1];
            console.log('[Media] Base64 gerado, tamanho:', MediaAttachState.base64.length);
            showMediaPreviewHub(file, result);
        };
        reader.onerror = function() {
            alert('Erro ao ler arquivo.');
            clearMediaAttachState();
        };
        reader.readAsDataURL(file);
    }
    
    function showMediaPreviewHub(file, dataUrl) {
        const container = document.getElementById('hub-media-preview');
        const thumbContainer = document.getElementById('hub-media-thumb-container');
        const docContainer = document.getElementById('hub-media-doc-container');
        const img = document.getElementById('hub-media-preview-img');
        const icon = document.getElementById('hub-media-preview-icon');
        const name = document.getElementById('hub-media-name');
        const size = document.getElementById('hub-media-size');
        
        if (!container) {
            console.warn('[Media] Container de preview não encontrado');
            return;
        }
        
        name.textContent = file.name;
        size.textContent = formatFileSize(file.size);
        
        if (file.type.startsWith('image/')) {
            // Mostra thumbnail da imagem
            img.src = dataUrl;
            if (thumbContainer) thumbContainer.style.display = 'block';
            if (docContainer) docContainer.style.display = 'none';
            
            // Guarda dataUrl para o modal de expansão
            window._mediaPreviewDataUrl = dataUrl;
            console.log('[Media] Preview de imagem configurado');
        } else {
            // Mostra ícone de documento
            if (thumbContainer) thumbContainer.style.display = 'none';
            if (docContainer) docContainer.style.display = 'block';
            icon.textContent = file.type.includes('pdf') ? '📄' : '📝';
            console.log('[Media] Preview de documento configurado');
        }
        
        container.style.display = 'block';
        
        // Mostra botão enviar se estava oculto
        updateSendMicVisibility();
    }
    
    // Função para criar modal lightbox dinamicamente (garante que fica no body, isolado)
    function ensureMediaModal() {
        let modal = document.getElementById('hub-media-expand-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'hub-media-expand-modal';
            modal.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:#000; z-index:999999; align-items:center; justify-content:center;';
            modal.onclick = function(e) { if (e.target === modal) window.closeMediaExpandModal(); };
            
            modal.innerHTML = `
                <button onclick="closeMediaExpandModal()" style="position:absolute; top:20px; right:20px; background:transparent; border:none; color:#888; font-size:28px; cursor:pointer; z-index:999999; width:44px; height:44px; display:flex; align-items:center; justify-content:center;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#888'">✕</button>
                <img id="hub-media-expand-img" src="" style="max-width:90vw; max-height:90vh; object-fit:contain;">
            `;
            
            document.body.appendChild(modal);
            console.log('[Media] Modal lightbox criado e anexado ao body');
        }
        return modal;
    }
    
    // Função para expandir preview da imagem (lightbox)
    window.expandMediaPreview = function() {
        if (!window._mediaPreviewDataUrl) return;
        
        const modal = ensureMediaModal();
        const expandImg = document.getElementById('hub-media-expand-img');
        
        if (modal && expandImg) {
            expandImg.src = window._mediaPreviewDataUrl;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            console.log('[Media] Modal aberto');
        }
    };
    
    // Função para fechar modal de expansão
    window.closeMediaExpandModal = function() {
        const modal = document.getElementById('hub-media-expand-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            console.log('[Media] Modal fechado');
        }
    };
    
    // Fecha modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('hub-media-expand-modal');
            if (modal && modal.style.display === 'flex') {
                window.closeMediaExpandModal();
            }
        }
    });
    
    function removeMediaPreviewHub() {
        clearMediaAttachState();
        const container = document.getElementById('hub-media-preview');
        const thumbContainer = document.getElementById('hub-media-thumb-container');
        const docContainer = document.getElementById('hub-media-doc-container');
        const fileInput = document.getElementById('hub-file-input');
        
        if (container) container.style.display = 'none';
        if (thumbContainer) thumbContainer.style.display = 'none';
        if (docContainer) docContainer.style.display = 'none';
        if (fileInput) fileInput.value = '';
        
        // Limpa URL guardada
        window._mediaPreviewDataUrl = null;
        
        updateSendMicVisibility();
        console.log('[Media] Preview removido');
    }
    
    function handleMediaPaste(event) {
        const clipboardData = event.clipboardData || window.clipboardData;
        if (!clipboardData) return;
        
        const items = clipboardData.items;
        if (!items) return;
        
        console.log('[Media] Paste detectado, items:', items.length);
        
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            console.log('[Media] Item', i, '- kind:', item.kind, 'type:', item.type);
            
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                event.preventDefault();
                event.stopPropagation();
                
                const file = item.getAsFile();
                if (file) {
                    const ext = item.type.split('/')[1] || 'png';
                    const fileName = `imagem_${Date.now()}.${ext}`;
                    const namedFile = new File([file], fileName, { type: file.type });
                    processMediaFile(namedFile);
                }
                return;
            }
        }
    }
    
    // Handler do botão de anexar
    const btnAttach = document.getElementById('btnAttach');
    const fileInput = document.getElementById('hub-file-input');
    
    if (btnAttach && fileInput) {
        btnAttach.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[Media] Botão anexar clicado');
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                console.log('[Media] Arquivo selecionado:', file.name);
                processMediaFile(file);
            }
        });
        
        console.log('[Media] Handlers de anexo registrados');
    }
    
    // Handler do botão remover preview
    const removeBtn = document.getElementById('hub-media-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            removeMediaPreviewHub();
        });
    }
    
    // Handler de paste no textarea
    if (hubText) {
        hubText.addEventListener('paste', handleMediaPaste);
        console.log('[Media] Handler de paste registrado');
    }
    
    // ============================================================================
    // Event listeners originais
    // ============================================================================
    
    // Event listeners
    btnMic.addEventListener('click', startRecording);
    
    btnRecStop.addEventListener('click', stopRecording);
    
    btnRecCancel.addEventListener('click', () => {
        resetToIdle();
    });
    
    btnReviewCancel.addEventListener('click', () => {
        resetToIdle();
    });
    
    // Estado: Preview → Regravar (volta para Recording)
    btnReviewRerecord.addEventListener('click', async () => {
        // Limpa preview sem resetar tudo
        if (hubAudioPreview.src) {
            URL.revokeObjectURL(hubAudioPreview.src);
            hubAudioPreview.src = '';
            hubAudioPreview.load();
        }
        if (window.__currentAudioUrl) {
            try {
                URL.revokeObjectURL(window.__currentAudioUrl);
            } catch (e) {}
            window.__currentAudioUrl = null;
        }
        
        // Reseta variáveis mas mantém contexto
        recChunks = [];
        recBlob = null;
        
        // Volta para Recording (reinicia gravação)
        try {
            await startRecording();
        } catch (err) {
            alert('Não consegui reiniciar a gravação. Permita o acesso ao microfone e tente novamente.');
            resetToIdle();
        }
    });
    
    // Estado: Preview → Envia
    btnReviewSend.addEventListener('click', async () => {
        const startTime = Date.now();
        console.log('[AudioRecorder] ===== INÍCIO ENVIO DE ÁUDIO =====');
        console.log('[AudioRecorder] Timestamp:', new Date().toISOString());
        
        if (!recBlob || recBlob.size < 2000) {
            alert('Áudio muito curto. Grave novamente.');
            resetToIdle();
            return;
        }
        
        // DEBUG: Informações do áudio
        const mimeType = recBlob.type || 'unknown';
        const audioSize = recBlob.size;
        const audioSizeKB = (audioSize / 1024).toFixed(2);
        const audioSizeMB = (audioSize / 1024 / 1024).toFixed(2);
        console.log('[AudioRecorder] Informações do áudio:', {
            mimeType: mimeType,
            size_bytes: audioSize,
            size_kb: audioSizeKB + ' KB',
            size_mb: audioSizeMB + ' MB',
            isOgg: mimeType.includes('ogg') || mimeType.includes('opus'),
            isWebM: mimeType.includes('webm')
        });
        
        try {
            // Muda para estado Sending
            setState('sending');
            console.log('[AudioRecorder] Estado alterado para: sending');
            
            const convertStartTime = Date.now();
            console.log('[AudioRecorder] Garantindo formato OGG/Opus (WhatsApp exige)...');
            let blobToSend = await ensureOggForSend(recBlob);
            if (blobToSend !== recBlob) {
                console.log('[AudioRecorder] Áudio convertido para OGG no navegador:', blobToSend.type);
            }
            
            // Comprime se necessário (servidor tem limite de ~500KB para POST)
            const originalSizeKB = (blobToSend.size / 1024).toFixed(1);
            console.log(`[AudioRecorder] Tamanho do áudio: ${originalSizeKB}KB`);
            
            if (blobToSend.size > MAX_AUDIO_SIZE_BYTES) {
                console.log(`[AudioRecorder] ⚠️ Áudio excede ${MAX_AUDIO_SIZE_BYTES/1024}KB, comprimindo...`);
                blobToSend = await compressAudioIfNeeded(blobToSend, MAX_AUDIO_SIZE_BYTES / 1024);
                const compressedSizeKB = (blobToSend.size / 1024).toFixed(1);
                console.log(`[AudioRecorder] ✅ Áudio comprimido: ${originalSizeKB}KB → ${compressedSizeKB}KB`);
            }
            
            console.log('[AudioRecorder] Iniciando conversão para base64...');
            const dataUrl = await blobToDataUrl(blobToSend);
            const convertTime = Date.now() - convertStartTime;
            const base64Length = dataUrl.length;
            const base64SizeKB = (base64Length / 1024).toFixed(2);
            console.log('[AudioRecorder] Conversão concluída:', {
                tempo_ms: convertTime,
                base64_length: base64Length,
                base64_size_kb: base64SizeKB + ' KB',
                base64_preview: dataUrl.substring(0, 100) + '...'
            });
            
            // Obtém dados da conversa
            const channel = document.getElementById('hub-channel')?.value || 'whatsapp';
            const to = document.getElementById('hub-to')?.value;
            const threadId = document.getElementById('hub-thread-id')?.value;
            const tenantId = document.getElementById('hub-tenant-id')?.value;
            const channelId = document.getElementById('hub-channel-id')?.value;
            
            console.log('[AudioRecorder] Dados da conversa:', {
                channel: channel,
                to: to,
                thread_id: threadId,
                tenant_id: tenantId,
                channel_id: channelId
            });
            
            if (!to) {
                alert('Erro: Destinatário não identificado.');
                resetToIdle();
                return;
            }
            
            // Validação específica para WhatsApp: channel_id é obrigatório
            if (channel === 'whatsapp' && !channelId) {
                alert('Erro: Canal não identificado. Esta conversa não possui um canal associado.\n\nPor favor, recarregue a página ou entre em contato com o suporte se o problema persistir.');
                console.error('[AudioRecorder] Tentativa de envio sem channel_id. Thread:', { threadId, channel, to });
                resetToIdle();
                return;
            }
            
            const sendStartTime = Date.now();
            console.log('[AudioRecorder] Iniciando envio para backend...');
            await sendHubMessage({
                channel: channel,
                to: to,
                thread_id: threadId || '',
                tenant_id: tenantId || '',
                channel_id: channelId || '',
                type: 'audio',
                base64Ptt: dataUrl
            });
            const sendTime = Date.now() - sendStartTime;
            const totalTime = Date.now() - startTime;
            console.log('[AudioRecorder] Envio concluído com sucesso:', {
                tempo_envio_ms: sendTime,
                tempo_total_ms: totalTime
            });
            console.log('[AudioRecorder] ===== FIM ENVIO DE ÁUDIO (SUCESSO) =====');
            
            resetToIdle();
        } catch (err) {
            const totalTime = Date.now() - startTime;
            console.error('[AudioRecorder] ===== ERRO NO ENVIO DE ÁUDIO =====');
            console.error('[AudioRecorder] Tempo até erro:', totalTime + ' ms');
            console.error('[AudioRecorder] Erro:', err);
            console.error('[AudioRecorder] Stack:', err.stack);
            console.error('[AudioRecorder] ===== FIM LOG DE ERRO =====');
            
            // Limpa memória mesmo em caso de erro
            if (hubAudioPreview.src) {
                URL.revokeObjectURL(hubAudioPreview.src);
                hubAudioPreview.src = '';
            }
            if (window.__currentAudioUrl) {
                try {
                    URL.revokeObjectURL(window.__currentAudioUrl);
                } catch (e) {}
                window.__currentAudioUrl = null;
            }
            alert(err.message || 'Erro ao enviar áudio');
            resetToIdle();
        }
    });
    
    btnSend.addEventListener('click', async () => {
        const text = (hubText.value || '').trim();
        const hasMedia = MediaAttachState.base64 !== null;
        
        if ((!text && !hasMedia) || currentState !== 'idle') return;
        
        // Obtém dados da conversa
        const channel = document.getElementById('hub-channel')?.value || 'whatsapp';
        const to = document.getElementById('hub-to')?.value;
        const threadId = document.getElementById('hub-thread-id')?.value;
        const tenantId = document.getElementById('hub-tenant-id')?.value;
        const channelId = document.getElementById('hub-channel-id')?.value;
        
        if (!to) {
            alert('Erro: Destinatário não identificado.');
            return;
        }
        
        try {
            if (hasMedia) {
                // Envio de mídia
                const payload = {
                    channel: channel,
                    to: to,
                    thread_id: threadId || '',
                    tenant_id: tenantId || '',
                    channel_id: channelId || '',
                    type: MediaAttachState.type,
                    caption: text || null // Texto vai como legenda
                };
                
                if (MediaAttachState.type === 'image') {
                    payload.base64Image = MediaAttachState.base64;
                } else {
                    payload.base64Document = MediaAttachState.base64;
                    payload.fileName = MediaAttachState.fileName;
                }
                
                console.log('[Media] Enviando mídia:', { type: MediaAttachState.type, fileName: MediaAttachState.fileName });
                await sendHubMessage(payload);
                removeMediaPreviewHub();
            } else {
                // Envio de texto normal
                await sendHubMessage({
                    channel: channel,
                    to: to,
                    thread_id: threadId || '',
                    tenant_id: tenantId || '',
                    channel_id: channelId || '',
                    type: 'text',
                    message: text
                });
            }
            hubText.value = '';
            updateSendMicVisibility();
        } catch (err) {
            alert(err.message || 'Erro ao enviar mensagem');
        }
    });
}

/**
 * Envia mensagem do painel (texto ou áudio)
 */
async function sendHubMessage(payload) {
    const requestStartTime = Date.now();
    const isAudio = payload.type === 'audio';
    const messageText = payload.message || '[Áudio]';
    
    console.log('[CommunicationHub] ===== INÍCIO sendHubMessage =====');
    console.log('[CommunicationHub] Timestamp:', new Date().toISOString());
    console.log('[CommunicationHub] Tipo:', payload.type);
    
    // VALIDAÇÃO: Garante que channel_id está presente para WhatsApp
    if (payload.channel === 'whatsapp' && !payload.channel_id) {
        alert('Erro: Canal não identificado. Recarregue a conversa e tente novamente.');
        console.error('[CommunicationHub] Tentativa de envio sem channel_id. Payload:', payload);
        throw new Error('Channel ID não encontrado');
    }
    
    // Log para debug
    console.log('[CommunicationHub] Enviando mensagem:', {
        channel: payload.channel,
        channel_id: payload.channel_id,
        thread_id: payload.thread_id,
        to: payload.to,
        type: payload.type,
        has_base64Ptt: !!(payload.base64Ptt),
        base64_length: payload.base64Ptt ? payload.base64Ptt.length : 0
    });
    
    // Mensagem otimista
    const tempId = 'temp_' + Date.now();
    const optimisticMessage = {
        id: tempId,
        direction: 'outbound',
        content: messageText,
        timestamp: new Date().toISOString(),
        type: payload.type
    };
    
    // Se for imagem, adiciona preview na mensagem otimista
    if (payload.type === 'image' && payload.base64Image) {
        // Detecta mime type do base64 (assume jpeg se não souber)
        let mimeType = 'image/jpeg';
        try {
            const binaryStr = atob(payload.base64Image.substring(0, 20));
            if (binaryStr.startsWith('\x89PNG')) mimeType = 'image/png';
            else if (binaryStr.startsWith('GIF8')) mimeType = 'image/gif';
        } catch(e) {}
        
        optimisticMessage.media = {
            url: 'data:' + mimeType + ';base64,' + payload.base64Image,
            media_type: 'image',
            mime_type: mimeType
        };
        optimisticMessage.content = payload.caption || '';
    } else if (payload.type === 'document') {
        optimisticMessage.content = '[Documento: ' + (payload.fileName || 'arquivo') + ']';
    }
    
    addMessageToPanel(optimisticMessage);
    
    // Limpa input de texto se não for áudio
    if (!isAudio) {
        const hubText = document.getElementById('hubText');
        if (hubText) hubText.value = '';
    }
    
    try {
        const sendUrl = '<?= pixelhub_url('/communication-hub/send') ?>';
        console.log('[CommunicationHub] Enviando POST para:', sendUrl);
        console.log('[CommunicationHub] Payload:', { ...payload, base64Ptt: payload.base64Ptt ? '[BASE64...]' : undefined });
        
        // Usa FormData ao invés de JSON (resolve o erro 400 "Canal é obrigatório")
        // Validação: Garante que ambos channel (provider) e channel_id estão presentes
        const channel = payload.channel || 'whatsapp';
        const channelId = payload.channel_id || '';
        
        if (!channel) {
            throw new Error('Channel (provider) é obrigatório');
        }
        
        if (channel === 'whatsapp' && !channelId) {
            throw new Error('Channel ID é obrigatório para WhatsApp');
        }
        
        const formData = new FormData();
        formData.append('channel', channel);
        formData.append('to', payload.to || '');
        formData.append('thread_id', payload.thread_id || '');
        formData.append('tenant_id', payload.tenant_id || '');
        formData.append('channel_id', channelId);
        formData.append('type', payload.type || 'text');
        
        if (payload.type === 'audio' && payload.base64Ptt) {
            formData.append('base64Ptt', payload.base64Ptt);
        } else if (payload.type === 'image' && payload.base64Image) {
            formData.append('base64Image', payload.base64Image);
            if (payload.caption) formData.append('caption', payload.caption);
        } else if (payload.type === 'document' && payload.base64Document) {
            formData.append('base64Document', payload.base64Document);
            formData.append('fileName', payload.fileName || 'document');
            if (payload.caption) formData.append('caption', payload.caption);
        } else if (payload.message) {
            formData.append('message', payload.message);
        }
        
        // Log para debug (sem expor base64 completo)
        console.log('[CommunicationHub] FormData campos:', {
            channel: formData.get('channel'),
            channel_id: formData.get('channel_id'),
            to: formData.get('to'),
            type: formData.get('type'),
            has_base64Ptt: !!formData.get('base64Ptt'),
            has_message: !!formData.get('message')
        });
        
        const fetchStartTime = Date.now();
        console.log('[CommunicationHub] Iniciando fetch para:', sendUrl);
        console.log('[CommunicationHub] Timestamp antes do fetch:', new Date().toISOString());
        
        const response = await fetch(sendUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const fetchTime = Date.now() - fetchStartTime;
        console.log('[CommunicationHub] Fetch concluído:', {
            status: response.status,
            tempo_ms: fetchTime,
            timestamp: new Date().toISOString()
        });
        
        if (!response.ok) {
            const errorTextStartTime = Date.now();
            const errorText = await response.text();
            const errorTextTime = Date.now() - errorTextStartTime;
            console.error('[CommunicationHub] Erro HTTP:', {
                status: response.status,
                tempo_leitura_erro_ms: errorTextTime,
                erro_preview: errorText.substring(0, 500)
            });
            
            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch (e) {
                console.error('[CommunicationHub] Erro ao parsear JSON de erro:', e);
                errorData = {
                    success: false,
                    error: `Erro HTTP ${response.status}: ${errorText.substring(0, 200)}`,
                    error_code: 'PARSE_ERROR'
                };
            }
            
            console.error('[CommunicationHub] Dados do erro:', errorData);
            
            // Melhora mensagem de erro baseado no código
            let errorMessage = errorData.error || 'Erro desconhecido ao enviar mensagem';
            
            if (errorData.error_code === 'GATEWAY_TIMEOUT') {
                // Timeout específico do gateway (504)
                errorMessage = 'Timeout do gateway (504). O servidor do gateway demorou mais de 60 segundos para processar o áudio. Possíveis causas:\n\n' +
                    '• Arquivo de áudio muito grande\n' +
                    '• Gateway sobrecarregado\n' +
                    '• Problemas de rede\n\n' +
                    'Tente novamente com um áudio menor ou aguarde alguns minutos.';
            } else if (errorData.error_code === 'GATEWAY_HTML_ERROR' || errorData.error_code === 'GATEWAY_SERVER_ERROR') {
                // Detecta se é timeout 504
                if (errorMessage.includes('504') || errorMessage.includes('Gateway Time-out') || errorMessage.includes('timeout')) {
                    errorMessage = 'Timeout do gateway (504). O servidor do gateway demorou mais de 60 segundos para processar o áudio. Possíveis causas:\n\n' +
                        '• Arquivo de áudio muito grande\n' +
                        '• Gateway sobrecarregado\n' +
                        '• Problemas de rede\n\n' +
                        'Tente novamente com um áudio menor ou aguarde alguns minutos.';
                } else {
                    errorMessage = 'O gateway retornou um erro interno. Isso pode indicar que o servidor do gateway está com problemas. Verifique os logs do servidor para mais detalhes.';
                }
            } else if (errorData.error_code === 'EMPTY_RESPONSE') {
                errorMessage = 'O gateway não retornou resposta. Verifique se o serviço do gateway está online e funcionando.';
            } else if (errorData.error_code === 'TIMEOUT') {
                errorMessage = 'Timeout ao enviar áudio. O gateway pode estar sobrecarregado ou o arquivo muito grande. Tente novamente ou reduza o tamanho do áudio.';
            } else if (errorData.error_code === 'WPPCONNECT_TIMEOUT') {
                errorMessage = 'O gateway WPPConnect está demorando mais de 30 segundos para processar o áudio. Isso pode acontecer se:\n\n' +
                    '• O áudio for muito grande (tente gravar menos de 1 minuto)\n' +
                    '• O gateway estiver sobrecarregado\n' +
                    '• A conexão com o WhatsApp estiver lenta\n\n' +
                    'Tente gravar um áudio mais curto ou aguarde alguns minutos e tente novamente.';
            } else if (errorData.error_code === 'WPPCONNECT_SEND_ERROR') {
                errorMessage = errorData.error || 'Falha ao enviar áudio via WPPConnect. Verifique se a sessão está conectada e se o formato do áudio está correto.';
            } else if (errorData.error_code === 'GATEWAY_ERROR' && errorMessage.includes('Syntax error')) {
                errorMessage = 'O gateway retornou uma resposta inválida (erro de sintaxe JSON). Isso geralmente indica um problema no servidor do gateway. Verifique os logs do servidor.';
            }
            
            const totalTime = Date.now() - requestStartTime;
            console.error('[CommunicationHub] Tempo total até erro:', totalTime + ' ms');
            console.error('[CommunicationHub] ===== FIM sendHubMessage (ERRO) =====');
            
            throw new Error(errorMessage);
        }
        
        const result = await response.json();
        const totalTime = Date.now() - requestStartTime;
        console.log('[CommunicationHub] Response JSON:', result);
        console.log('[CommunicationHub] Tempo total de envio:', totalTime + ' ms');
        console.log('[CommunicationHub] ===== FIM sendHubMessage (SUCESSO) =====');
        
        if (result.success && result.event_id) {
            // Busca mensagem confirmada
            await confirmSentMessageFromPanel(result.event_id, tempId);
        } else {
            // Erro: remove mensagem otimista
            const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
            if (tempMsg) tempMsg.remove();
            throw new Error(result.error || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
        if (tempMsg) tempMsg.remove();
        throw error;
    }
}

/**
 * Adiciona mensagem ao painel
 */
function addMessageToPanel(message) {
    console.log('[Hub] addMessageToPanel chamado:', {
        id: message.id,
        direction: message.direction,
        content: message.content?.substring(0, 50),
        hasMedia: !!message.media,
        mediaUrl: message.media?.url
    });
    
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    const msgId = message.id || '';
    const direction = message.direction || 'inbound';
    const content = message.content || '';
    const timestamp = message.timestamp || new Date().toISOString();
    
    // CORREÇÃO: Timestamps de mensagens já estão em Brasília - exibe direto
    const timeStr = formatMessageTimestamp(timestamp);
    
    const isOutbound = direction === 'outbound';
    
    // Renderiza mídia se existir (passa event_id para transcrição)
    const mediaHtml = message.media ? renderMediaPlayer(message.media, msgId) : '';
    console.log('[Hub] mediaHtml gerado:', mediaHtml ? 'SIM (' + mediaHtml.length + ' chars)' : 'VAZIO');
    
    // Conteúdo da mensagem (só mostra se não estiver vazio e não for placeholder de áudio com mídia)
    // Se tem mídia de áudio e conteúdo é [Áudio] ou [audio], não mostra o texto
    const isAudioPlaceholder = content && /^\[(?:Á|A)udio\]$/i.test(content.trim());
    const shouldShowContent = content && content.trim() && !(mediaHtml && isAudioPlaceholder);
    const contentHtml = shouldShowContent
        ? `<div style="font-size: 14.2px; color: #111b21; line-height: 1.4; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word; ${mediaHtml ? 'margin-top: 8px;' : ''}">${escapeHtml(content)}</div>`
        : '';
    
    // Se não há conteúdo nem mídia, não adiciona mensagem vazia
    const hasContent = shouldShowContent || mediaHtml;
    if (!hasContent) {
        return;
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message-bubble ' + direction;
    messageDiv.setAttribute('data-message-id', msgId);
    messageDiv.setAttribute('data-timestamp', timestamp);
    messageDiv.style.cssText = 'margin-bottom: 6px; display: flex; ' + (isOutbound ? 'justify-content: flex-end;' : 'justify-content: flex-start;');
    
    // Header: apenas remetente para mensagens outbound (sessão/canal mostrado só no header da conversa)
    const sentByName = message.sent_by_name || '';
    let headerHtml = '';
    if (isOutbound && sentByName) {
        headerHtml = `<div style="font-size: 10px; color: #667781; margin-bottom: 3px;"><span style="font-weight: 600;">Enviado por: ${escapeHtml(sentByName)}</span></div>`;
    }
    
    messageDiv.innerHTML = `
        <div style="max-width: 70%; padding: 7px 12px; border-radius: 7.5px; ${isOutbound ? 'background: #dcf8c6; margin-left: auto; border-bottom-right-radius: 2px;' : 'background: white; border-bottom-left-radius: 2px;'}">
            ${headerHtml}
            ${mediaHtml}
            ${contentHtml}
            <div style="font-size: 11px; color: #667781; margin-top: 3px; text-align: right; padding-top: 2px; opacity: 0.8;">
                ${timeStr}
            </div>
        </div>
    `;
    
    // Adiciona mensagem dentro do wrapper interno, se existir
    const innerWrapper = container.querySelector('.commhub-messages-inner');
    if (innerWrapper) {
        innerWrapper.appendChild(messageDiv);
    } else {
        // Fallback: adiciona diretamente no container (não deve acontecer)
        container.appendChild(messageDiv);
    }
    
    // Scroll para o final
    if (ConversationState.autoScroll) {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 50);
    }
}

/**
 * Confirma mensagem enviada
 */
async function confirmSentMessageFromPanel(eventId, tempId) {
    console.log('[Hub] confirmSentMessageFromPanel INICIADO:', { eventId, tempId });
    try {
        const url = '<?= pixelhub_url('/communication-hub/message') ?>?' + 
                   new URLSearchParams({
                       event_id: eventId,
                       thread_id: ConversationState.currentThreadId
                   });
        console.log('[Hub] Buscando mensagem:', url);
        const response = await fetch(url, {
            credentials: 'same-origin' // Envia cookies de sessão para autenticação
        });
        console.log('[Hub] Response status:', response.status);
        const result = await response.json();
        console.log('[Hub] Resultado da confirmação:', JSON.stringify(result, null, 2));
        
        if (result.success && result.message) {
            console.log('[Hub] Mensagem confirmada - tem mídia?', !!result.message.media);
            if (result.message.media) {
                console.log('[Hub] Mídia:', JSON.stringify(result.message.media, null, 2));
            }
            
            // Remove mensagem otimista
            const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
            console.log('[Hub] Mensagem otimista encontrada?', !!tempMsg);
            if (tempMsg) tempMsg.remove();
            
            // Adiciona mensagem confirmada
            console.log('[Hub] Chamando onNewMessagesFromPanel...');
            onNewMessagesFromPanel([result.message]);
            
            // Reabilita formulário
            const submitBtn = document.querySelector('#send-message-form button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar';
            }
        } else {
            console.error('[Hub] Confirmação falhou:', result);
        }
    } catch (error) {
        console.error('[Hub] Erro ao confirmar mensagem:', error);
    }
}

/**
 * Processa novas mensagens no painel
 */
function onNewMessagesFromPanel(messages) {
    console.log('[Hub] onNewMessagesFromPanel chamado com', messages.length, 'mensagem(s)');
    if (!messages || messages.length === 0) return;
    
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    // Log detalhado de cada mensagem
    messages.forEach((msg, idx) => {
        console.log(`[Hub] Mensagem ${idx + 1}:`, {
            id: msg.id,
            direction: msg.direction,
            content: msg.content?.substring(0, 50),
            hasMedia: !!msg.media,
            mediaType: msg.media?.type || msg.media?.media_type,
            mediaUrl: msg.media?.url
        });
    });
    
    // 🔍 PASSO 8: UI - Log antes de processar
    const activeThreadId = ConversationState.currentThreadId;
    
    // Filtra mensagens já existentes
    const newMessages = messages.filter(msg => {
        const msgId = msg.id || msg.event_id;
        if (!msgId || ConversationState.messageIds.has(msgId)) {
            return false;
        }
        ConversationState.messageIds.add(msgId);
        return true;
    });
    
    if (newMessages.length === 0) return;
    
    // 🔍 PASSO 8: UI - Log para cada mensagem nova
    newMessages.forEach(msg => {
        const msgThreadId = msg.thread_id || msg.conversation_id || null;
        const action = (msgThreadId && activeThreadId && msgThreadId.toString() === activeThreadId.toString()) ? 'append' : 'listOnly';
        
        console.log('[INCOMING_MSG] thread=' + (msgThreadId || 'NULL') + ' activeThread=' + (activeThreadId || 'NULL') + ' action=' + action + ' message_id=' + (msg.id || msg.event_id || 'NULL'));
    });
    
    // Atualiza marcadores
    const lastMessage = newMessages[newMessages.length - 1];
    ConversationState.lastTimestamp = lastMessage.timestamp || lastMessage.created_at;
    ConversationState.lastEventId = lastMessage.id || lastMessage.event_id;
    
    // Adiciona mensagens
    newMessages.forEach(msg => {
        addMessageToPanel(msg);
    });
    
    // Atualiza scroll
    updateConversationScroll();
}

/**
 * Atualiza scroll da conversa
 */
function updateConversationScroll() {
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
    
    if (isAtBottom || ConversationState.autoScroll) {
        container.scrollTop = container.scrollHeight;
        ConversationState.autoScroll = true;
        hideNewMessagesBadge();
    } else {
        ConversationState.autoScroll = false;
        ConversationState.newMessagesCount++;
        showNewMessagesBadge();
    }
}

function showNewMessagesBadge() {
    const badge = document.getElementById('new-messages-badge');
    const count = document.getElementById('new-messages-count');
    if (badge && count) {
        count.textContent = ConversationState.newMessagesCount;
        badge.classList.add('visible');
    }
}

function hideNewMessagesBadge() {
    const badge = document.getElementById('new-messages-badge');
    if (badge) {
        badge.classList.remove('visible');
        ConversationState.newMessagesCount = 0;
    }
}

/**
 * Volta para lista de conversas (mobile)
 */
function backToConversationList() {
    const body = document.getElementById('communication-body');
    if (body) {
        body.classList.remove('view-thread');
    }
    
    // Salva scroll da lista (se necessário)
    const listScroll = document.querySelector('.conversation-list-scroll');
    if (listScroll) {
        sessionStorage.setItem('hub_list_scroll', listScroll.scrollTop);
    }
}

/**
 * Auto-resize textarea do composer
 */
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    const newHeight = Math.min(textarea.scrollHeight, 120);
    textarea.style.height = newHeight + 'px';
}

/**
 * Toggle estatísticas (com localStorage para memorizar preferência)
 */
function toggleStats() {
    const stats = document.getElementById('communication-stats');
    const toggle = document.getElementById('stats-toggle-text');
    if (stats && toggle) {
        stats.classList.toggle('expanded');
        const isExpanded = stats.classList.contains('expanded');
        toggle.textContent = isExpanded ? 'Ocultar estatísticas' : 'Mostrar estatísticas';
        // Salva preferência no localStorage
        try {
            localStorage.setItem('hub_stats_expanded', isExpanded ? '1' : '0');
        } catch (e) {
            // Ignora erro de localStorage (modo privado, etc)
        }
    }
}

// Restaura preferência de estatísticas ao carregar página
(function initStatsPreference() {
    try {
        const savedPref = localStorage.getItem('hub_stats_expanded');
        if (savedPref === '1') {
            const stats = document.getElementById('communication-stats');
            const toggle = document.getElementById('stats-toggle-text');
            if (stats && toggle) {
                stats.classList.add('expanded');
                toggle.textContent = 'Ocultar estatísticas';
            }
        }
    } catch (e) {
        // Ignora erro de localStorage
    }
})();

/**
 * Inicializa marcadores da conversa
 */
function initializeConversationMarkers() {
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    const messages = container.querySelectorAll('[data-message-id]');
    
    if (messages.length > 0) {
        const lastMsg = messages[messages.length - 1];
        const lastTimestamp = lastMsg.getAttribute('data-timestamp');
        const lastEventId = lastMsg.getAttribute('data-message-id');
        
        // [LOG TEMPORARIO] Reset de markers baseado no último item renderizado
        console.log('[LOG TEMPORARIO] initializeConversationMarkers() - RESETANDO MARKERS:', {
            messages_count: messages.length,
            lastTimestamp: lastTimestamp,
            lastEventId: lastEventId,
            thread_id: ConversationState.currentThreadId
        });
        
        ConversationState.lastTimestamp = lastTimestamp;
        ConversationState.lastEventId = lastEventId;
        
        // Limpa messageIds e adiciona apenas os da conversa atual
        ConversationState.messageIds.clear();
        messages.forEach(msg => {
            const msgId = msg.getAttribute('data-message-id');
            if (msgId) ConversationState.messageIds.add(msgId);
        });
    } else {
        const now = new Date();
        now.setMinutes(now.getMinutes() - 1);
        ConversationState.lastTimestamp = now.toISOString();
        console.log('[LOG TEMPORARIO] initializeConversationMarkers() - NENHUMA MENSAGEM, usando timestamp atual -1min');
    }
    
    // Adiciona listener de scroll
    container.addEventListener('scroll', function() {
        const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
        ConversationState.autoScroll = isAtBottom;
        if (isAtBottom) {
            hideNewMessagesBadge();
        }
    });
}

// Guard global para polling da conversa
let __conversationPollingStarted = false;

/**
 * Inicia polling da conversa
 */
function startConversationPolling() {
    // Evita múltiplos inícios
    if (ConversationState.pollingInterval || __conversationPollingStarted) {
        console.log('[Hub] Polling da conversa já está ativo, ignorando novo início');
        return;
    }
    
    // Limpa qualquer intervalo anterior (segurança extra)
    if (ConversationState.pollingInterval) {
        clearInterval(ConversationState.pollingInterval);
        ConversationState.pollingInterval = null;
    }
    
    __conversationPollingStarted = true;
    console.log('[Hub] Iniciando polling da conversa para thread:', ConversationState.currentThreadId);
    
    // Primeiro check após 2 segundos
    setTimeout(() => {
        if (ConversationState.currentThreadId) {
            checkForNewConversationMessages();
        }
    }, 2000);
    
    // Polling periódico com intervalo dinâmico baseado em visibilidade da página
    function scheduleNextMessageCheck() {
        if (ConversationState.pollingInterval) {
            clearInterval(ConversationState.pollingInterval);
        }
        
        // Calcula intervalo baseado na visibilidade
        let interval;
        if (document.hidden) {
            // Aba em background: 15-30s
            interval = POLLING_INTERVALS.BACKGROUND;
        } else {
            // Conversa ativa aberta: 2-4s
            interval = POLLING_INTERVALS.ACTIVE_CONVERSATION;
        }
        
        ConversationState.pollingInterval = setInterval(() => {
            if (ConversationState.isPageVisible && ConversationState.currentThreadId) {
                checkForNewConversationMessages();
            }
        }, interval);
    }
    
    scheduleNextMessageCheck();
    
    // Reagenda quando visibilidade muda
    document.addEventListener('visibilitychange', function() {
        if (ConversationState.currentThreadId && !document.hidden) {
            scheduleNextMessageCheck();
        }
    });
}

/**
 * Para polling da conversa
 */
function stopConversationPolling() {
    if (ConversationState.pollingInterval) {
        console.log('[Hub] Parando polling da conversa');
        clearInterval(ConversationState.pollingInterval);
        ConversationState.pollingInterval = null;
    }
    __conversationPollingStarted = false;
    // Limpa também timeouts pendentes se houver
    // (não temos referência direta, mas o clearInterval já resolve)
}

/**
 * Verifica novas mensagens da conversa
 */
async function checkForNewConversationMessages() {
    if (!ConversationState.currentThreadId || !ConversationState.lastTimestamp) {
        // [LOG TEMPORARIO] Estado inválido
        console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - ESTADO INVALIDO: thread_id=' + (ConversationState.currentThreadId || 'NULL') + ', lastTimestamp=' + (ConversationState.lastTimestamp || 'NULL'));
        return;
    }
    
    // [LOG TEMPORARIO] Início do check
    console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - INICIADO: thread_id=' + ConversationState.currentThreadId + ', lastTimestamp=' + ConversationState.lastTimestamp + ', lastEventId=' + (ConversationState.lastEventId || 'NULL'));
    
    try {
        const checkParams = new URLSearchParams({
            thread_id: ConversationState.currentThreadId,
            after_timestamp: ConversationState.lastTimestamp
        });
        if (ConversationState.lastEventId) {
            checkParams.set('after_event_id', ConversationState.lastEventId);
        }
        const checkUrl = '<?= pixelhub_url('/communication-hub/messages/check') ?>?' + checkParams.toString();
        
        // [LOG TEMPORARIO] URL do check
        console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - URL: ' + checkUrl);
        
        const response = await fetch(checkUrl);
        const result = await response.json();
        
        // [LOG TEMPORARIO] Resultado do check
        console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - RESULTADO: success=' + (result.success ? 'true' : 'false') + ', has_new=' + (result.has_new ? 'true' : 'false'));
        
        if (result.success && result.has_new) {
            // [LOG TEMPORARIO] Buscando novas mensagens
            console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - BUSCANDO NOVAS MENSAGENS');
            
            // Busca novas mensagens
            const fetchParams = new URLSearchParams({
                thread_id: ConversationState.currentThreadId
            });
            if (ConversationState.lastTimestamp) {
                fetchParams.set('after_timestamp', ConversationState.lastTimestamp);
                if (ConversationState.lastEventId) {
                    fetchParams.set('after_event_id', ConversationState.lastEventId);
                }
            }
            const fetchUrl = '<?= pixelhub_url('/communication-hub/messages/new') ?>?' + fetchParams.toString();
            
            // [LOG TEMPORARIO] URL do fetch
            console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - FETCH URL: ' + fetchUrl);
            
            const fetchResponse = await fetch(fetchUrl);
            const fetchResult = await fetchResponse.json();
            
            // [LOG TEMPORARIO] Resultado do fetch
            console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - FETCH RESULTADO: success=' + (fetchResult.success ? 'true' : 'false') + ', messages_count=' + (fetchResult.messages?.length || 0));
            
            if (fetchResult.success && fetchResult.messages) {
                // [LOG TEMPORARIO] Processando mensagens
                console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - PROCESSANDO ' + fetchResult.messages.length + ' MENSAGENS');
                onNewMessagesFromPanel(fetchResult.messages);
            } else {
                // [LOG TEMPORARIO] Nenhuma mensagem ou erro
                console.warn('[LOG TEMPORARIO] checkForNewConversationMessages() - NENHUMA MENSAGEM OU ERRO: ' + (fetchResult.error || 'N/A'));
            }
        } else {
            // [LOG TEMPORARIO] Sem novas mensagens
            console.log('[LOG TEMPORARIO] checkForNewConversationMessages() - SEM NOVAS MENSAGENS (has_new=false)');
        }
    } catch (error) {
        console.error('[Hub] Erro ao verificar novas mensagens:', error);
        // [LOG TEMPORARIO] Erro
        console.error('[LOG TEMPORARIO] checkForNewConversationMessages() - ERRO: ' + error.message);
    }
}

// Page Visibility API para polling
document.addEventListener('visibilitychange', function() {
    ConversationState.isPageVisible = !document.hidden;
});

// Limpa polling da conversa ao sair
window.addEventListener('beforeunload', function() {
    stopConversationPolling();
});

// Handle browser back/forward (popstate)
window.addEventListener('popstate', function(event) {
    if (event.state && event.state.threadId) {
        // Reabre conversa se voltou para uma URL com thread_id
        loadConversation(event.state.threadId, event.state.channel);
    } else {
        // Remove conversa se voltou para URL sem thread_id
        const placeholder = document.getElementById('conversation-placeholder');
        const content = document.getElementById('conversation-content');
        if (placeholder && content) {
            placeholder.style.display = 'flex';
            content.style.display = 'none';
        }
        ConversationState.currentThreadId = null;
        ConversationState.currentChannel = null;
        stopConversationPolling();
        sessionStorage.removeItem('hub_selected_thread_id');
        sessionStorage.removeItem('hub_selected_channel');
        
        // Remove destaque das conversas
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
            item.style.background = 'white';
            item.style.borderColor = '#dee2e6';
        });
    }
});

    // Restaura scroll da lista ao voltar (mobile)
    const listScroll = document.querySelector('.conversation-list-scroll');
    if (listScroll) {
        const savedScroll = sessionStorage.getItem('hub_list_scroll');
        if (savedScroll) {
            listScroll.scrollTop = parseInt(savedScroll, 10);
        }
    }

// ============================================================================
// Menu de três pontos para conversas não vinculadas
// ============================================================================

/**
 * Abre/fecha o menu de três pontos
 */
function toggleIncomingLeadMenu(button) {
    const menu = button.closest('.incoming-lead-menu');
    const dropdown = menu.querySelector('.incoming-lead-menu-dropdown');
    
    // Fecha todos os outros menus abertos
    document.querySelectorAll('.incoming-lead-menu-dropdown.show').forEach(openMenu => {
        if (openMenu !== dropdown) {
            openMenu.classList.remove('show');
        }
    });
    
    // Toggle do menu atual
    dropdown.classList.toggle('show');
}

/**
 * Fecha o menu de três pontos
 */
function closeIncomingLeadMenu(button) {
    const menu = button.closest('.incoming-lead-menu');
    const dropdown = menu.querySelector('.incoming-lead-menu-dropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
}

// Fecha menus ao clicar fora
document.addEventListener('click', function(event) {
    if (!event.target.closest('.incoming-lead-menu') && !event.target.closest('.conversation-menu')) {
        document.querySelectorAll('.incoming-lead-menu-dropdown.show, .conversation-menu-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// ============================================================================
// Menu de ações para conversas vinculadas
// ============================================================================

/**
 * Abre/fecha o menu de três pontos para conversas
 */
function toggleConversationMenu(button) {
    const menu = button.closest('.conversation-menu');
    const dropdown = menu.querySelector('.conversation-menu-dropdown');
    
    // Fecha todos os outros menus abertos
    document.querySelectorAll('.conversation-menu-dropdown.show, .incoming-lead-menu-dropdown.show').forEach(openMenu => {
        if (openMenu !== dropdown) {
            openMenu.classList.remove('show');
        }
    });
    
    // Toggle do menu atual
    dropdown.classList.toggle('show');
}

/**
 * Fecha o menu de três pontos para conversas
 */
function closeConversationMenu(button) {
    const menu = button.closest('.conversation-menu');
    const dropdown = menu.querySelector('.conversation-menu-dropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
}

/**
 * Arquiva uma conversa
 */
async function archiveConversation(conversationId, contactName) {
    if (!confirm(`Arquivar conversa com "${contactName}"?\n\nA conversa será movida para "Arquivadas" e poderá ser reativada depois.`)) {
        return;
    }
    
    try {
        const response = await fetch('/communication-hub/conversation/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                status: 'archived'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conversa arquivada', 'success');
            // Remove da lista atual
            const item = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
            if (item) {
                item.style.transition = 'opacity 0.3s, transform 0.3s';
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => item.remove(), 300);
            }
            // Recarrega a página para atualizar listas
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Erro ao arquivar conversa', 'error');
        }
    } catch (error) {
        console.error('Erro ao arquivar conversa:', error);
        showToast('Erro ao arquivar conversa', 'error');
    }
}

/**
 * Ignora uma conversa
 */
async function ignoreConversation(conversationId, contactName) {
    if (!confirm(`Ignorar conversa com "${contactName}"?\n\nA conversa será movida para "Ignoradas".`)) {
        return;
    }
    
    try {
        const response = await fetch('/communication-hub/conversation/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                status: 'ignored'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conversa ignorada', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Erro ao ignorar conversa', 'error');
        }
    } catch (error) {
        console.error('Erro ao ignorar conversa:', error);
        showToast('Erro ao ignorar conversa', 'error');
    }
}

/**
 * Reativa uma conversa arquivada ou ignorada
 */
async function reactivateConversation(conversationId, contactName) {
    try {
        const response = await fetch('/communication-hub/conversation/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                status: 'active'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conversa reativada', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Erro ao reativar conversa', 'error');
        }
    } catch (error) {
        console.error('Erro ao reativar conversa:', error);
        showToast('Erro ao reativar conversa', 'error');
    }
}

/**
 * Exclui uma conversa permanentemente
 */
async function deleteConversation(conversationId, contactName) {
    if (!confirm(`EXCLUIR PERMANENTEMENTE a conversa com "${contactName}"?\n\n⚠️ Esta ação NÃO pode ser desfeita!\n\nTodas as mensagens serão removidas.`)) {
        return;
    }
    
    // Segunda confirmação para ações destrutivas
    if (!confirm(`Tem certeza ABSOLUTA?\n\nDigite OK para confirmar a exclusão de "${contactName}".`)) {
        return;
    }
    
    try {
        const response = await fetch('/communication-hub/conversation/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: conversationId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conversa excluída permanentemente', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Erro ao excluir conversa', 'error');
        }
    } catch (error) {
        console.error('Erro ao excluir conversa:', error);
        showToast('Erro ao excluir conversa', 'error');
    }
}

/**
 * Desvincula uma conversa de um tenant (move para "Não vinculados")
 */
async function unlinkConversation(conversationId, contactName) {
    if (!confirm(`Desvincular a conversa com "${contactName}" do cliente?\n\nA conversa será movida para "Não vinculados".`)) {
        return;
    }
    
    try {
        const response = await fetch('/communication-hub/conversation/unlink', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: conversationId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conversa desvinculada com sucesso', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Erro ao desvincular conversa', 'error');
        }
    } catch (error) {
        console.error('Erro ao desvincular conversa:', error);
        showToast('Erro ao desvincular conversa', 'error');
    }
}

// ============================================================================
// Incoming Leads - Ações (Criar Cliente, Vincular, Ignorar)
// ============================================================================

/**
 * Abre modal para criar novo tenant a partir de incoming lead
 */
function openCreateTenantModal(conversationId, contactName, contactPhone) {
    const modal = document.getElementById('create-tenant-modal');
    if (!modal) {
        console.error('Modal create-tenant-modal não encontrado');
        return;
    }
    
    // Preenche dados iniciais
    document.getElementById('create-tenant-conversation-id').value = conversationId;
    document.getElementById('create-tenant-name').value = contactName || '';
    document.getElementById('create-tenant-phone').value = contactPhone || '';
    document.getElementById('create-tenant-email').value = '';
    
    modal.style.display = 'flex';
}

/**
 * Fecha modal de criar tenant
 */
function closeCreateTenantModal() {
    const modal = document.getElementById('create-tenant-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Cria tenant a partir de incoming lead
 */
async function createTenantFromIncomingLead(event) {
    event.preventDefault();
    
    const conversationId = document.getElementById('create-tenant-conversation-id').value;
    const name = document.getElementById('create-tenant-name').value.trim();
    const phone = document.getElementById('create-tenant-phone').value.trim();
    const email = document.getElementById('create-tenant-email').value.trim();
    
    if (!name) {
        alert('Nome é obrigatório');
        return;
    }
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/incoming-lead/create-tenant') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: parseInt(conversationId),
                name: name,
                phone: phone,
                email: email
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Cliente criado e conversa vinculada com sucesso!');
            closeCreateTenantModal();
            // Recarrega a página para atualizar a lista
            window.location.reload();
        } else {
            alert('Erro: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao criar tenant:', error);
        alert('Erro ao criar cliente. Tente novamente.');
    }
}

/**
 * Abre modal para vincular incoming lead a tenant existente
 */
function openLinkTenantModal(conversationId, contactName) {
    const modal = document.getElementById('link-tenant-modal');
    if (!modal) {
        console.error('Modal link-tenant-modal não encontrado');
        return;
    }
    
    document.getElementById('link-tenant-conversation-id').value = conversationId;
    document.getElementById('link-tenant-contact-name').textContent = contactName || 'Contato Desconhecido';
    
    // Limpa campo de busca e restaura todas as opções
    const searchInput = document.getElementById('link-tenant-search');
    if (searchInput) {
        searchInput.value = '';
        filterLinkTenantOptions(''); // Restaura todas as opções
    }
    
    // Limpa seleção
    const select = document.getElementById('link-tenant-select');
    if (select) {
        select.value = '';
    }
    
    modal.style.display = 'flex';
}

/**
 * Fecha modal de vincular tenant
 */
function closeLinkTenantModal() {
    const modal = document.getElementById('link-tenant-modal');
    if (!modal) {
        return;
    }
    
    // Limpa campo de busca ao fechar
    const searchInput = document.getElementById('link-tenant-search');
    if (searchInput) {
        searchInput.value = '';
        filterLinkTenantOptions(''); // Restaura todas as opções
    }
    
    modal.style.display = 'none';
}

/**
 * Filtra opções de tenant no modal de vincular cliente
 */
function filterLinkTenantOptions(searchTerm) {
    const search = searchTerm.toLowerCase().trim();
    const select = document.getElementById('link-tenant-select');
    const noResults = document.getElementById('link-tenant-no-results');
    
    if (!select) return;
    
    const options = select.querySelectorAll('option');
    
    let visibleCount = 0;
    
    // Primeira opção (placeholder) sempre visível
    if (options.length > 0) {
        options[0].style.display = '';
    }
    
    // Filtra as demais opções
    for (let i = 1; i < options.length; i++) {
        const option = options[i];
        const name = option.getAttribute('data-name') || '';
        const email = option.getAttribute('data-email') || '';
        const phone = option.getAttribute('data-phone') || '';
        const cpfCnpj = option.getAttribute('data-cpf-cnpj') || '';
        
        // Remove espaços e caracteres especiais da busca para comparação
        const searchNormalized = search.replace(/[^a-z0-9]/g, '');
        
        // Verifica se a busca corresponde a algum campo
        const matchesName = name.includes(search) || name.replace(/[^a-z0-9]/g, '').includes(searchNormalized);
        const matchesEmail = email.includes(search);
        const matchesPhone = phone.includes(searchNormalized);
        const matchesCpfCnpj = cpfCnpj.includes(searchNormalized);
        
        if (search === '' || matchesName || matchesEmail || matchesPhone || matchesCpfCnpj) {
            option.style.display = '';
            visibleCount++;
        } else {
            option.style.display = 'none';
        }
    }
    
    // Mostra mensagem se não houver resultados
    if (noResults) {
        if (search !== '' && visibleCount === 0) {
            noResults.style.display = 'block';
            select.style.display = 'none';
        } else {
            noResults.style.display = 'none';
            select.style.display = 'block';
        }
    }
    
    // Se houver apenas uma opção visível (além do placeholder), seleciona automaticamente
    if (visibleCount === 1 && search !== '') {
        for (let i = 1; i < options.length; i++) {
            if (options[i].style.display !== 'none') {
                select.value = options[i].value;
                break;
            }
        }
    } else if (search === '') {
        // Limpa seleção quando busca é limpa
        select.value = '';
    }
}

/**
 * Vincula incoming lead a tenant existente
 */
async function linkIncomingLeadToTenant(event) {
    event.preventDefault();
    
    const conversationId = document.getElementById('link-tenant-conversation-id').value;
    const tenantId = document.getElementById('link-tenant-select').value;
    
    if (!tenantId) {
        alert('Selecione um cliente');
        return;
    }
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/incoming-lead/link-tenant') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: parseInt(conversationId),
                tenant_id: parseInt(tenantId)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Conversa vinculada ao cliente com sucesso!');
            closeLinkTenantModal();
            // Recarrega a página para atualizar a lista
            window.location.reload();
        } else {
            alert('Erro: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao vincular tenant:', error);
        alert('Erro ao vincular conversa. Tente novamente.');
    }
}

/**
 * Rejeita/ignora incoming lead
 */
async function rejectIncomingLead(conversationId) {
    if (!confirm('Tem certeza que deseja ignorar esta conversa? Ela será removida da lista de não vinculadas.')) {
        return;
    }
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/incoming-lead/reject') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: parseInt(conversationId)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Remove o item da lista imediatamente (sem recarregar a página)
            const item = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (item) {
                // Animação de fade out
                item.style.transition = 'opacity 0.3s ease';
                item.style.opacity = '0';
                item.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    item.remove();
                    // Atualiza contador imediatamente
                    updateIncomingLeadsCount();
                    
                    // Toast simples: "Conversa ignorada"
                    showToast('Conversa ignorada', 'success');
                }, 300);
            } else {
                // Fallback: recarrega se não encontrou o item
                window.location.reload();
            }
        } else {
            alert('Erro: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao ignorar conversa:', error);
        alert('Erro ao ignorar conversa. Tente novamente.');
    }
}

/**
 * Atualiza contador de incoming leads
 */
function updateIncomingLeadsCount() {
    const count = document.querySelectorAll('.incoming-lead-item').length;
    // Tenta encontrar o badge pelo seletor correto
    const badge = document.querySelector('.unlinked-conversations-badge') || 
                  document.querySelector('.incoming-leads-badge');
    if (badge) {
        badge.textContent = count;
        if (count === 0) {
            const section = document.querySelector('.unlinked-conversations-section') ||
                           document.querySelector('.incoming-leads-section');
            if (section) {
                section.style.display = 'none';
            }
        }
    }
}

/**
 * Mostra toast simples
 */
function showToast(message, type = 'info') {
    // Remove toast existente se houver
    const existingToast = document.querySelector('.toast-message');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Cria novo toast
    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#25d366' : '#023A8D'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-size: 14px;
        font-weight: 500;
        animation: slideIn 0.3s ease;
    `;
    
    // Adiciona animação CSS se não existir
    if (!document.querySelector('#toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(toast);
    
    // Remove após 3 segundos
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Abre modal para alterar tenant de uma conversa
 */
function openChangeTenantModal(conversationId, contactName, currentTenantId, currentTenantName) {
    const modal = document.getElementById('change-tenant-modal');
    if (!modal) {
        console.error('Modal change-tenant-modal não encontrado');
        return;
    }
    
    document.getElementById('change-tenant-conversation-id').value = conversationId;
    document.getElementById('change-tenant-contact-name').textContent = contactName || 'Contato Desconhecido';
    document.getElementById('change-tenant-current-tenant').textContent = currentTenantName || 'Cliente desconhecido';
    document.getElementById('change-tenant-select').value = currentTenantId || '';
    
    // Limpa campo de busca e restaura todas as opções
    const searchInput = document.getElementById('change-tenant-search');
    if (searchInput) {
        searchInput.value = '';
        filterChangeTenantOptions(''); // Restaura todas as opções
    }
    
    modal.style.display = 'flex';
    
    // Foca no campo de busca
    setTimeout(() => {
        if (searchInput) {
            searchInput.focus();
        }
    }, 100);
}

/**
 * Fecha modal de alterar tenant
 */
function closeChangeTenantModal() {
    const modal = document.getElementById('change-tenant-modal');
    if (modal) {
        // Limpa campo de busca ao fechar
        const searchInput = document.getElementById('change-tenant-search');
        if (searchInput) {
            searchInput.value = '';
            filterChangeTenantOptions(''); // Restaura todas as opções
        }
        modal.style.display = 'none';
    }
}

/**
 * Abre modal para editar nome do contato
 */
function openEditContactNameModal(conversationId, currentName) {
    const modal = document.getElementById('edit-contact-name-modal');
    if (!modal) {
        console.error('Modal edit-contact-name-modal não encontrado');
        return;
    }
    
    document.getElementById('edit-contact-conversation-id').value = conversationId;
    document.getElementById('edit-contact-current-name').textContent = currentName || 'Contato Desconhecido';
    document.getElementById('edit-contact-new-name').value = currentName || '';
    
    modal.style.display = 'flex';
    
    // Foca no campo de nome
    setTimeout(() => {
        const nameInput = document.getElementById('edit-contact-new-name');
        if (nameInput) {
            nameInput.focus();
            nameInput.select();
        }
    }, 100);
}

/**
 * Fecha modal de editar nome do contato
 */
function closeEditContactNameModal() {
    const modal = document.getElementById('edit-contact-name-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Atualiza nome do contato
 */
async function updateContactName(event) {
    event.preventDefault();
    
    const conversationId = document.getElementById('edit-contact-conversation-id').value;
    const newName = document.getElementById('edit-contact-new-name').value.trim();
    
    if (!newName) {
        alert('Nome é obrigatório');
        return;
    }
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/conversation/update-contact-name') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                conversation_id: parseInt(conversationId),
                contact_name: newName
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeEditContactNameModal();
            showToast('Nome atualizado com sucesso!', 'success');
            // Recarrega a página para mostrar nome atualizado
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Erro ao atualizar nome: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao atualizar nome do contato:', error);
        alert('Erro ao atualizar nome do contato');
    }
}

/**
 * Filtra opções do select de tenant baseado na busca
 */
function filterChangeTenantOptions(searchTerm) {
    const search = searchTerm.toLowerCase().trim();
    const select = document.getElementById('change-tenant-select');
    const noResults = document.getElementById('change-tenant-no-results');
    const options = select.querySelectorAll('option');
    
    let visibleCount = 0;
    
    // Primeira opção (placeholder) sempre visível
    if (options.length > 0) {
        options[0].style.display = '';
    }
    
    // Filtra as demais opções
    for (let i = 1; i < options.length; i++) {
        const option = options[i];
        const name = option.getAttribute('data-name') || '';
        const email = option.getAttribute('data-email') || '';
        const phone = option.getAttribute('data-phone') || '';
        const cpfCnpj = option.getAttribute('data-cpf-cnpj') || '';
        
        // Remove espaços e caracteres especiais da busca para comparação
        const searchNormalized = search.replace(/[^a-z0-9]/g, '');
        
        // Verifica se a busca corresponde a algum campo
        const matchesName = name.includes(search) || name.replace(/[^a-z0-9]/g, '').includes(searchNormalized);
        const matchesEmail = email.includes(search);
        const matchesPhone = phone.includes(searchNormalized);
        const matchesCpfCnpj = cpfCnpj.includes(searchNormalized);
        
        if (search === '' || matchesName || matchesEmail || matchesPhone || matchesCpfCnpj) {
            option.style.display = '';
            visibleCount++;
        } else {
            option.style.display = 'none';
        }
    }
    
    // Mostra mensagem se não houver resultados
    if (search !== '' && visibleCount === 0) {
        noResults.style.display = 'block';
        select.style.display = 'none';
    } else {
        noResults.style.display = 'none';
        select.style.display = 'block';
    }
    
    // Se houver apenas uma opção visível (além do placeholder), seleciona automaticamente
    if (visibleCount === 1 && search !== '') {
        for (let i = 1; i < options.length; i++) {
            if (options[i].style.display !== 'none') {
                select.value = options[i].value;
                break;
            }
        }
    } else if (search === '') {
        // Limpa seleção quando busca é limpa
        select.value = '';
    }
}

/**
 * Altera o tenant vinculado a uma conversa
 */
async function changeConversationTenant(event) {
    event.preventDefault();
    
    const conversationId = document.getElementById('change-tenant-conversation-id').value;
    const tenantId = document.getElementById('change-tenant-select').value;
    
    if (!tenantId) {
        alert('Selecione um cliente');
        return;
    }
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/conversation/change-tenant') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conversation_id: parseInt(conversationId),
                tenant_id: parseInt(tenantId)
            })
        });
        
        // Verifica se a resposta é OK
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Erro HTTP:', response.status, errorText);
            alert('Erro ao alterar cliente vinculado. Status: ' + response.status);
            return;
        }
        
        // Verifica se o Content-Type é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text);
            alert('Erro: Resposta inválida do servidor');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            alert('Cliente vinculado à conversa alterado com sucesso!');
            closeChangeTenantModal();
            // Recarrega a lista de conversas sem recarregar a página inteira
            updateConversationListOnly();
        } else {
            alert('Erro: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao alterar tenant:', error);
        alert('Erro ao alterar cliente vinculado. Tente novamente.');
    }
}

// ============================================================================
// Dropdown Pesquisável de Cliente
// ============================================================================
(function() {
    const dropdown = document.getElementById('clienteDropdown');
    if (!dropdown) return;
    
    const input = document.getElementById('clienteSearchInput');
    const hiddenInput = document.getElementById('clienteTenantId');
    const list = document.getElementById('clienteDropdownList');
    const items = list.querySelectorAll('.searchable-dropdown-item');
    
    let isOpen = false;
    let selectedValue = hiddenInput.value;
    
    // Abre dropdown ao clicar no input
    input.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });
    
    // Abre dropdown ao focar
    input.addEventListener('focus', function() {
        if (!isOpen) {
            openDropdown();
            // Seleciona todo o texto para facilitar nova busca
            this.select();
        }
    });
    
    // Filtra enquanto digita
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        filterItems(query);
        if (!isOpen) openDropdown();
    });
    
    // Navegação por teclado
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
            restoreSelectedValue();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const visibleItems = Array.from(items).filter(i => i.style.display !== 'none');
            if (visibleItems.length > 0) {
                selectItem(visibleItems[0]);
            }
            closeDropdown();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isOpen) openDropdown();
            focusNextItem(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusNextItem(-1);
        }
    });
    
    // Seleciona item ao clicar
    items.forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            selectItem(this);
            closeDropdown();
        });
        
        // Hover highlight
        item.addEventListener('mouseenter', function() {
            items.forEach(i => i.classList.remove('focused'));
            this.classList.add('focused');
        });
    });
    
    // Fecha dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
            closeDropdown();
            restoreSelectedValue();
        }
    });
    
    function toggleDropdown() {
        if (isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }
    
    function openDropdown() {
        list.classList.add('show');
        isOpen = true;
        // Mostra todos os itens ao abrir
        items.forEach(item => item.style.display = '');
        // Scroll para o item selecionado
        const selected = list.querySelector(`[data-value="${selectedValue}"]`);
        if (selected) {
            selected.scrollIntoView({ block: 'nearest' });
            selected.classList.add('selected');
        }
    }
    
    function closeDropdown() {
        list.classList.remove('show');
        isOpen = false;
        items.forEach(i => i.classList.remove('focused'));
    }
    
    function selectItem(item) {
        const value = item.dataset.value;
        const name = item.dataset.name;
        
        selectedValue = value;
        hiddenInput.value = value;
        input.value = name;
        
        // Atualiza classe selected
        items.forEach(i => i.classList.remove('selected'));
        item.classList.add('selected');
        
        // Auto-submit do formulário ao selecionar cliente
        if (typeof onClienteFilterChange === 'function') {
            onClienteFilterChange();
        }
    }
    
    function restoreSelectedValue() {
        // Restaura o valor selecionado se o usuário não escolheu nada
        const selected = list.querySelector(`[data-value="${selectedValue}"]`);
        if (selected) {
            input.value = selected.dataset.name;
        }
    }
    
    function filterItems(query) {
        let hasResults = false;
        
        items.forEach(item => {
            const name = (item.dataset.name || '').toLowerCase();
            const search = (item.dataset.search || '').toLowerCase();
            const detail = item.querySelector('.searchable-dropdown-item-detail')?.textContent?.toLowerCase() || '';
            
            // Busca em: nome, texto de busca adicional, e detalhes
            const matches = name.includes(query) || search.includes(query) || detail.includes(query);
            
            if (query === '' || matches) {
                item.style.display = '';
                hasResults = true;
                
                // Destaca o termo buscado
                if (query && query.length >= 2) {
                    highlightMatch(item.querySelector('.searchable-dropdown-item-name'), query, item.dataset.name);
                } else {
                    // Remove destaque
                    item.querySelector('.searchable-dropdown-item-name').textContent = item.dataset.name;
                }
            } else {
                item.style.display = 'none';
            }
        });
        
        // Mostra mensagem se não houver resultados
        let emptyMsg = list.querySelector('.searchable-dropdown-empty');
        if (!hasResults) {
            if (!emptyMsg) {
                emptyMsg = document.createElement('div');
                emptyMsg.className = 'searchable-dropdown-empty';
                emptyMsg.textContent = 'Nenhum cliente encontrado';
                list.appendChild(emptyMsg);
            }
            emptyMsg.style.display = '';
        } else if (emptyMsg) {
            emptyMsg.style.display = 'none';
        }
    }
    
    function highlightMatch(element, query, originalText) {
        const lowerText = originalText.toLowerCase();
        const index = lowerText.indexOf(query);
        
        if (index >= 0) {
            const before = originalText.substring(0, index);
            const match = originalText.substring(index, index + query.length);
            const after = originalText.substring(index + query.length);
            element.innerHTML = escapeHtml(before) + '<mark>' + escapeHtml(match) + '</mark>' + escapeHtml(after);
        } else {
            element.textContent = originalText;
        }
    }
    
    function focusNextItem(direction) {
        const visibleItems = Array.from(items).filter(i => i.style.display !== 'none');
        if (visibleItems.length === 0) return;
        
        const currentFocused = list.querySelector('.focused');
        let currentIndex = currentFocused ? visibleItems.indexOf(currentFocused) : -1;
        
        let nextIndex = currentIndex + direction;
        if (nextIndex < 0) nextIndex = visibleItems.length - 1;
        if (nextIndex >= visibleItems.length) nextIndex = 0;
        
        items.forEach(i => i.classList.remove('focused'));
        visibleItems[nextIndex].classList.add('focused');
        visibleItems[nextIndex].scrollIntoView({ block: 'nearest' });
    }
})();

// ============================================================================
// Dropdown Pesquisável de Cliente no Modal Nova Mensagem
// ============================================================================
(function() {
    const dropdown = document.getElementById('modalClienteDropdown');
    if (!dropdown) return;
    
    const input = document.getElementById('modalClienteSearchInput');
    const hiddenInput = document.getElementById('modalClienteTenantId');
    const list = document.getElementById('modalClienteDropdownList');
    const items = list.querySelectorAll('.searchable-dropdown-item');
    
    let isOpen = false;
    let selectedValue = '';
    
    // Abre dropdown ao clicar no input
    input.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });
    
    // Abre dropdown ao focar
    input.addEventListener('focus', function() {
        if (!isOpen) {
            openDropdown();
            this.select();
        }
    });
    
    // Filtra enquanto digita
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        filterItems(query);
        if (!isOpen) openDropdown();
    });
    
    // Navegação por teclado
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
            restoreSelectedValue();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const visibleItems = Array.from(items).filter(i => i.style.display !== 'none');
            if (visibleItems.length > 0) {
                selectItem(visibleItems[0]);
            }
            closeDropdown();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isOpen) openDropdown();
            focusNextItem(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusNextItem(-1);
        }
    });
    
    // Seleciona item ao clicar
    items.forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            selectItem(this);
            closeDropdown();
        });
        
        item.addEventListener('mouseenter', function() {
            items.forEach(i => i.classList.remove('focused'));
            this.classList.add('focused');
        });
    });
    
    // Fecha dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
            closeDropdown();
            restoreSelectedValue();
        }
    });
    
    function toggleDropdown() {
        if (isOpen) closeDropdown();
        else openDropdown();
    }
    
    function openDropdown() {
        list.classList.add('show');
        isOpen = true;
        items.forEach(item => item.style.display = '');
        const selected = list.querySelector(`[data-value="${selectedValue}"]`);
        if (selected) {
            selected.scrollIntoView({ block: 'nearest' });
            selected.classList.add('selected');
        }
    }
    
    function closeDropdown() {
        list.classList.remove('show');
        isOpen = false;
        items.forEach(i => i.classList.remove('focused'));
    }
    
    function selectItem(item) {
        const value = item.dataset.value;
        const name = item.dataset.name;
        const phone = item.dataset.phone || '';
        
        selectedValue = value;
        hiddenInput.value = value;
        input.value = name;
        
        items.forEach(i => i.classList.remove('selected'));
        item.classList.add('selected');
        
        // Auto-preenche telefone no modal
        if (typeof onModalClienteSelect === 'function') {
            onModalClienteSelect(phone);
        }
    }
    
    function restoreSelectedValue() {
        const selected = list.querySelector(`[data-value="${selectedValue}"]`);
        if (selected) {
            input.value = selected.dataset.name;
        } else if (!selectedValue) {
            input.value = '';
        }
    }
    
    function filterItems(query) {
        let hasResults = false;
        
        items.forEach(item => {
            const name = (item.dataset.name || '').toLowerCase();
            const search = (item.dataset.search || '').toLowerCase();
            const matches = name.includes(query) || search.includes(query);
            
            if (query === '' || matches) {
                item.style.display = '';
                hasResults = true;
                
                if (query && query.length >= 2) {
                    highlightMatch(item.querySelector('.searchable-dropdown-item-name'), query, item.dataset.name);
                } else {
                    item.querySelector('.searchable-dropdown-item-name').textContent = item.dataset.name;
                }
            } else {
                item.style.display = 'none';
            }
        });
        
        let emptyMsg = list.querySelector('.searchable-dropdown-empty');
        if (!hasResults) {
            if (!emptyMsg) {
                emptyMsg = document.createElement('div');
                emptyMsg.className = 'searchable-dropdown-empty';
                emptyMsg.textContent = 'Nenhum cliente encontrado';
                list.appendChild(emptyMsg);
            }
            emptyMsg.style.display = '';
        } else if (emptyMsg) {
            emptyMsg.style.display = 'none';
        }
    }
    
    function highlightMatch(element, query, originalText) {
        const lowerText = originalText.toLowerCase();
        const index = lowerText.indexOf(query);
        
        if (index >= 0) {
            const before = originalText.substring(0, index);
            const match = originalText.substring(index, index + query.length);
            const after = originalText.substring(index + query.length);
            element.innerHTML = escapeHtml(before) + '<mark>' + escapeHtml(match) + '</mark>' + escapeHtml(after);
        } else {
            element.textContent = originalText;
        }
    }
    
    function focusNextItem(direction) {
        const visibleItems = Array.from(items).filter(i => i.style.display !== 'none');
        if (visibleItems.length === 0) return;
        
        const currentFocused = list.querySelector('.focused');
        let currentIndex = currentFocused ? visibleItems.indexOf(currentFocused) : -1;
        
        let nextIndex = currentIndex + direction;
        if (nextIndex < 0) nextIndex = visibleItems.length - 1;
        if (nextIndex >= visibleItems.length) nextIndex = 0;
        
        items.forEach(i => i.classList.remove('focused'));
        visibleItems[nextIndex].classList.add('focused');
        visibleItems[nextIndex].scrollIntoView({ block: 'nearest' });
    }
    
    // Reset do dropdown quando modal abre
    window.resetModalClienteDropdown = function() {
        selectedValue = '';
        hiddenInput.value = '';
        input.value = '';
        items.forEach(i => {
            i.classList.remove('selected');
            i.style.display = '';
        });
    };
})();
</script>

<!-- Modal: Criar Cliente a partir de Incoming Lead -->
<div id="create-tenant-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Criar Novo Cliente</h2>
            <button onclick="closeCreateTenantModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
        </div>
        
        <form onsubmit="createTenantFromIncomingLead(event)">
            <input type="hidden" id="create-tenant-conversation-id" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nome *</label>
                <input type="text" id="create-tenant-name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Telefone</label>
                <input type="text" id="create-tenant-phone" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="5511999999999">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">E-mail</label>
                <input type="email" id="create-tenant-email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="cliente@exemplo.com">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Criar Cliente
                </button>
                <button type="button" onclick="closeCreateTenantModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Vincular a Cliente Existente -->
<div id="link-tenant-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Vincular a Cliente Existente</h2>
            <button onclick="closeLinkTenantModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
        </div>
        
        <div style="margin-bottom: 20px; padding: 12px; background: #f0f2f5; border-radius: 6px;">
            <div style="font-size: 12px; color: #667781; margin-bottom: 4px;">Contato:</div>
            <div style="font-weight: 600; color: #111b21;" id="link-tenant-contact-name"></div>
        </div>
        
        <form onsubmit="linkIncomingLeadToTenant(event)">
            <input type="hidden" id="link-tenant-conversation-id" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Buscar Cliente *</label>
                <input type="text" 
                       id="link-tenant-search" 
                       placeholder="Digite nome, email, telefone ou CPF/CNPJ..." 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;"
                       onkeyup="filterLinkTenantOptions(this.value)">
                <select id="link-tenant-select" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                    <option value="">Selecione um cliente...</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" 
                                data-name="<?= htmlspecialchars(strtolower($tenant['name'] ?? ''), ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars(strtolower($tenant['email'] ?? ''), ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $tenant['phone'] ?? ''), ENT_QUOTES) ?>"
                                data-cpf-cnpj="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $tenant['cpf_cnpj'] ?? $tenant['document'] ?? ''), ENT_QUOTES) ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                            <?php if (!empty($tenant['email'])): ?>
                                (<?= htmlspecialchars($tenant['email']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="link-tenant-no-results" style="display: none; margin-top: 8px; padding: 8px; background: #fff3cd; border-radius: 4px; color: #856404; font-size: 12px;">
                    Nenhum cliente encontrado com essa busca.
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Vincular
                </button>
                <button type="button" onclick="closeLinkTenantModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Alterar Cliente Vinculado -->
<div id="change-tenant-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Alterar Cliente Vinculado</h2>
            <button onclick="closeChangeTenantModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
        </div>
        
        <div style="margin-bottom: 20px; padding: 12px; background: #f0f2f5; border-radius: 6px;">
            <div style="font-size: 12px; color: #667781; margin-bottom: 4px;">Contato:</div>
            <div style="font-weight: 600; color: #111b21;" id="change-tenant-contact-name"></div>
        </div>
        
        <div style="margin-bottom: 20px; padding: 12px; background: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
            <div style="font-size: 12px; color: #856404; margin-bottom: 4px;">Cliente atual:</div>
            <div style="font-weight: 600; color: #856404;" id="change-tenant-current-tenant"></div>
        </div>
        
        <form onsubmit="changeConversationTenant(event)">
            <input type="hidden" id="change-tenant-conversation-id" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Buscar Cliente *</label>
                <input type="text" 
                       id="change-tenant-search" 
                       placeholder="Digite nome, email, telefone ou CPF/CNPJ..." 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;"
                       onkeyup="filterChangeTenantOptions(this.value)">
                <select id="change-tenant-select" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                    <option value="">Selecione um cliente...</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" 
                                data-name="<?= htmlspecialchars(strtolower($tenant['name'] ?? ''), ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars(strtolower($tenant['email'] ?? ''), ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $tenant['phone'] ?? ''), ENT_QUOTES) ?>"
                                data-cpf-cnpj="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $tenant['cpf_cnpj'] ?? $tenant['document'] ?? ''), ENT_QUOTES) ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                            <?php if (!empty($tenant['email'])): ?>
                                (<?= htmlspecialchars($tenant['email']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="change-tenant-no-results" style="display: none; margin-top: 8px; padding: 8px; background: #fff3cd; border-radius: 4px; color: #856404; font-size: 12px;">
                    Nenhum cliente encontrado com essa busca.
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Alterar Cliente
                </button>
                <button type="button" onclick="closeChangeTenantModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Nome do Contato -->
<div id="edit-contact-name-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 450px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Editar Nome do Contato</h2>
            <button onclick="closeEditContactNameModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
        </div>
        
        <div style="margin-bottom: 15px; padding: 12px; background: #f0f2f5; border-radius: 6px;">
            <div style="font-size: 12px; color: #667781; margin-bottom: 4px;">Nome atual:</div>
            <div style="font-weight: 600; color: #111b21;" id="edit-contact-current-name"></div>
        </div>
        
        <form onsubmit="updateContactName(event)">
            <input type="hidden" id="edit-contact-conversation-id" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Novo nome *</label>
                <input type="text" 
                       id="edit-contact-new-name" 
                       placeholder="Digite o nome do contato..." 
                       maxlength="255"
                       required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="font-size: 11px; color: #667781; margin-top: 4px;">
                    Este é o nome exibido na lista de conversas. Não altera o cliente vinculado.
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Salvar
                </button>
                <button type="button" onclick="closeEditContactNameModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
// Constrói caminho do layout: sobe 1 nível de communication_hub para views, depois layout/main.php
$viewsDir = dirname(__DIR__); // views/communication_hub -> views
$layoutFile = $viewsDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'main.php';
require $layoutFile;
?>


