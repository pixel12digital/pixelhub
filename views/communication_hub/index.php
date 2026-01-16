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

/* Estatísticas colapsáveis */
.communication-stats {
    display: none; /* Oculto por padrão */
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 12px;
    padding: 12px;
    background: #f0f2f5;
    border-radius: 8px;
}

.communication-stats.expanded {
    display: grid;
}

.communication-stats-item {
    text-align: center;
    padding: 12px;
    background: #023A8D;
    color: white;
    border-radius: 6px;
    font-size: 12px;
}

.communication-stats-item .number {
    font-size: 24px;
    font-weight: bold;
    display: block;
    margin-bottom: 4px;
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

/* Container de mensagens (scrollável) */
.conversation-messages-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 16px;
    min-height: 0;
    background: #f0f2f5;
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
    padding: 10px 12px;
    background: #f0f2f5;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    z-index: 5;
    position: sticky;
    bottom: 0;
}

.conversation-composer form {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.conversation-composer textarea {
    flex: 1;
    padding: 9px 14px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 21px;
    font-family: inherit;
    font-size: 14px;
    resize: none;
    max-height: 100px;
    background: white;
    line-height: 1.4;
    min-height: 44px;
    box-sizing: border-box;
}

.conversation-composer textarea:focus {
    outline: none;
    border-color: #023A8D;
}

.conversation-composer button {
    padding: 9px 18px;
    background: #023A8D;
    color: white;
    border: none;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    height: 44px;
    box-sizing: border-box;
}

.conversation-composer button:hover {
    background: #022a6d;
}

.conversation-composer button:disabled {
    background: #ccc;
    cursor: not-allowed;
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
    .conversation-composer {
        padding-bottom: max(10px, env(safe-area-inset-bottom));
    }
}

/* Ajuste mobile para composer e mensagens */
@media (max-width: 767px) {
    .conversation-composer {
        padding: 10px 12px;
    }
    
    .conversation-composer textarea {
        font-size: 16px; /* Evita zoom no iOS */
    }
    
    .conversation-messages-container {
        padding: 10px 12px;
    }
    
    /* Mensagens mais largas no mobile */
    .message-bubble [style*="max-width: 72%"] {
        max-width: 88% !important;
    }
}
</style>

<div class="communication-hub-container">
    <!-- Topbar compacta -->
    <div class="communication-topbar">
        <h2>Painel de Comunicação</h2>
        <p>Gerencie conversas, envie mensagens e responda clientes em tempo real</p>
        
        <!-- Estatísticas colapsáveis -->
        <button class="communication-stats-toggle" onclick="toggleStats()" id="stats-toggle">
            <span id="stats-toggle-text">Mostrar estatísticas</span>
        </button>
        <div class="communication-stats" id="communication-stats">
            <div class="communication-stats-item">
                <span class="number"><?= $stats['whatsapp_active'] ?></span>
                <span>WhatsApp</span>
            </div>
            <div class="communication-stats-item">
                <span class="number"><?= $stats['chat_active'] ?></span>
                <span>Chats</span>
            </div>
            <div class="communication-stats-item">
                <span class="number"><?= $stats['total_unread'] ?></span>
                <span>Não Lidas</span>
            </div>
        </div>
        
        <!-- Filtros compactos -->
        <form method="GET" action="<?= pixelhub_url('/communication-hub') ?>" class="communication-filters">
            <div>
                <label>Canal</label>
                <select name="channel">
                    <option value="all" <?= ($filters['channel'] === 'all') ? 'selected' : '' ?>>Todos</option>
                    <option value="whatsapp" <?= ($filters['channel'] === 'whatsapp') ? 'selected' : '' ?>>WhatsApp</option>
                    <option value="chat" <?= ($filters['channel'] === 'chat') ? 'selected' : '' ?>>Chat Interno</option>
                </select>
            </div>
            <div>
                <label>Cliente</label>
                <select name="tenant_id">
                    <option value="">Todos</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] == $tenant['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($filters['status'] === 'active') ? 'selected' : '' ?>>Ativas</option>
                    <option value="all" <?= ($filters['status'] === 'all') ? 'selected' : '' ?>>Todas</option>
                </select>
            </div>
            <div>
                <button type="submit">Filtrar</button>
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
                <!-- Seção: Incoming Leads (Leads Entrantes) -->
                <?php if (!empty($incoming_leads)): ?>
                    <div style="padding: 12px 8px 8px 8px; background: #fff3cd; border-radius: 8px; margin-bottom: 12px; border: 1px solid #ffc107;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #856404;">
                                📥 Leads Entrantes
                            </h4>
                            <span style="background: #ffc107; color: #856404; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                <?= count($incoming_leads) ?>
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 11px; color: #856404; line-height: 1.4;">
                            Números não cadastrados. Revise e vincule a um cliente ou crie um novo.
                        </p>
                    </div>
                    
                    <?php foreach ($incoming_leads as $lead): ?>
                        <div class="conversation-item incoming-lead-item" 
                             data-thread-id="<?= htmlspecialchars($lead['thread_id'], ENT_QUOTES) ?>"
                             data-conversation-id="<?= $lead['conversation_id'] ?? 0 ?>"
                             style="background: #fff3cd; border: 1px solid #ffc107; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; color: #111b21; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($lead['contact_name'] ?? 'Contato Desconhecido') ?>
                                    </div>
                                    <div style="font-size: 12px; color: #667781; display: flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <span><?= htmlspecialchars($lead['contact'] ?? 'Número não identificado') ?></span>
                                    </div>
                                </div>
                                <?php if (($lead['unread_count'] ?? 0) > 0): ?>
                                    <span style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600;">
                                        <?= $lead['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;">
                                <button onclick="event.stopPropagation(); openCreateTenantModal(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($lead['contact'] ?? '', ENT_QUOTES) ?>')" 
                                        style="flex: 1; padding: 6px 10px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; white-space: nowrap;">
                                    Criar Cliente
                                </button>
                                <button onclick="event.stopPropagation(); openLinkTenantModal(<?= $lead['conversation_id'] ?? 0 ?>, '<?= htmlspecialchars($lead['contact_name'] ?? '', ENT_QUOTES) ?>')" 
                                        style="flex: 1; padding: 6px 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; white-space: nowrap;">
                                    Vincular
                                </button>
                                <button onclick="event.stopPropagation(); rejectIncomingLead(<?= $lead['conversation_id'] ?? 0 ?>)" 
                                        style="padding: 6px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                                    Ignorar
                                </button>
                            </div>
                            <div style="font-size: 11px; color: #667781; margin-top: 6px;">
                                <?php
                                $lastActivity = $lead['last_activity'] ?? 'now';
                                try {
                                    $dateTime = new DateTime($lastActivity);
                                    $dateStr = $dateTime->format('d/m H:i');
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
                             class="conversation-item"
                             data-thread-id="<?= htmlspecialchars($thread['thread_id'], ENT_QUOTES) ?>">
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
                                        <?php if (isset($thread['channel_type'])): ?>
                                            <span style="opacity: 0.7;">• <?= strtoupper($thread['channel_type']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        <span>Chat Interno</span>
                                    <?php endif; ?>
                                    <?php 
                                    // Mostra tenant_name apenas se não houver contact_name (nome do WhatsApp)
                                    // Isso evita mostrar nome do cliente quando já temos o nome do contato
                                    if (empty($thread['contact_name']) && isset($thread['tenant_name']) && $thread['tenant_name'] !== 'Sem tenant'): ?>
                                        <span style="opacity: 0.7;">• <?= htmlspecialchars($thread['tenant_name']) ?></span>
                                    <?php elseif (empty($thread['contact_name']) && (!isset($thread['tenant_name']) || $thread['tenant_id'] === null)): ?>
                                        <span style="opacity: 0.7; font-size: 10px;">• Sem tenant</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($thread['conversation_key']) && defined('APP_DEBUG') && APP_DEBUG): ?>
                                    <div style="font-size: 10px; color: #999; margin-top: 2px; opacity: 0.6;">
                                        <?= htmlspecialchars($thread['conversation_key']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                                <div style="text-align: right; flex-shrink: 0; margin-left: 8px;">
                                    <?php if (($thread['unread_count'] ?? 0) > 0): ?>
                                        <span style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 18px; text-align: center;">
                                            <?= $thread['unread_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #667781;">
                                <span><?= $thread['message_count'] ?? 0 ?> mensagens</span>
                                <?php
                                $lastActivity = $thread['last_activity'] ?? ($thread['created_at'] ?? 'now');
                                try {
                                    $dateTime = new DateTime($lastActivity);
                                    $dateStr = $dateTime->format('d/m H:i');
                                } catch (Exception $e) {
                                    $dateStr = 'Agora';
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
                <select name="channel" id="new-message-channel" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="chat">Chat Interno</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Cliente</label>
                <select name="tenant_id" id="new-message-tenant" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" data-phone="<?= htmlspecialchars($tenant['phone'] ?? '') ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;" id="new-message-to-container" style="display: none;">
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
    ACTIVE_CONVERSATION: 3000,      // 3s (média entre 2-4s)
    NO_ACTIVE_CONVERSATION: 8000,   // 8s (média entre 6-10s)
    BACKGROUND: 20000,              // 20s (média entre 15-30s)
    MAX_BACKOFF: 30000              // Teto do backoff: 30s
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
        // [LOG TEMPORARIO] Início da atualização
        console.log('[LOG TEMPORARIO] updateConversationListOnly() - INICIADO');
        
        // Busca lista atualizada via endpoint JSON
        const params = new URLSearchParams({
            channel: '<?= htmlspecialchars($filters['channel'] ?? 'all') ?>',
            status: '<?= htmlspecialchars($filters['status'] ?? 'active') ?>'
        });
        <?php if (isset($filters['tenant_id']) && $filters['tenant_id']): ?>
        params.set('tenant_id', '<?= (int) $filters['tenant_id'] ?>');
        <?php endif; ?>
        
        const url = '<?= pixelhub_url('/communication-hub/conversations-list') ?>?' + params.toString();
        const response = await fetch(url);
        const result = await response.json();
        
        if (!result.success || !result.threads) {
            console.error('[Hub] Erro ao buscar lista atualizada:', result.error || 'Resposta inválida');
            return;
        }
        
        // [LOG TEMPORARIO] Resposta recebida
        console.log('[LOG TEMPORARIO] updateConversationListOnly() - RESPOSTA RECEBIDA: threads_count=' + (result.threads?.length || 0));
        
        // [LOG TEMPORARIO] Valida ordenação do backend
        if (result.threads && result.threads.length > 0) {
            const firstThread = result.threads[0];
            const lastThread = result.threads[result.threads.length - 1];
            console.log('[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: primeiro_thread_id=' + (firstThread.thread_id || 'N/A') + ', last_activity=' + (firstThread.last_activity || 'N/A'));
            console.log('[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO BACKEND: ultimo_thread_id=' + (lastThread.thread_id || 'N/A') + ', last_activity=' + (lastThread.last_activity || 'N/A'));
            
            // Valida se está ordenado por last_activity DESC
            let isOrdered = true;
            for (let i = 1; i < result.threads.length; i++) {
                const prevTime = new Date(result.threads[i-1].last_activity || '1970-01-01').getTime();
                const currTime = new Date(result.threads[i].last_activity || '1970-01-01').getTime();
                if (currTime > prevTime) {
                    isOrdered = false;
                    console.warn('[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO QUEBRADA: thread[' + (i-1) + '].last_activity=' + result.threads[i-1].last_activity + ' < thread[' + i + '].last_activity=' + result.threads[i].last_activity);
                    break;
                }
            }
            console.log('[LOG TEMPORARIO] updateConversationListOnly() - ORDENACAO VALIDADA: ' + (isOrdered ? 'CORRETA (DESC)' : 'INCORRETA'));
        }
        
        // Preserva estado atual
        const activeThreadId = ConversationState.currentThreadId;
        const listScroll = document.querySelector('.conversation-list-scroll');
        const scrollPosition = listScroll ? listScroll.scrollTop : 0;
        
        // CRÍTICO: Garante que threads estão ordenados por last_activity DESC antes de renderizar
        // (Backend já retorna ordenado, mas garante aqui também para segurança)
        const sortedThreads = [...(result.threads || [])].sort((a, b) => {
            const timeA = new Date(a.last_activity || '1970-01-01').getTime();
            const timeB = new Date(b.last_activity || '1970-01-01').getTime();
            return timeB - timeA; // DESC: mais recente primeiro
        });
        
        // [LOG TEMPORARIO] Ordenação após sort
        if (sortedThreads.length > 0) {
            console.log('[LOG TEMPORARIO] updateConversationListOnly() - APOS SORT: primeiro_thread_id=' + (sortedThreads[0].thread_id || 'N/A') + ', last_activity=' + (sortedThreads[0].last_activity || 'N/A'));
        }
        
        // Renderiza lista atualizada (já ordenada)
        renderConversationList(sortedThreads);
        
        // Restaura scroll
        if (listScroll) {
            listScroll.scrollTop = scrollPosition;
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
function renderConversationList(threads) {
    const listContainer = document.querySelector('.conversation-list-scroll');
    if (!listContainer) {
        console.error('[Hub] Container da lista não encontrado');
        return;
    }
    
    if (threads.length === 0) {
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
    threads.forEach((thread, index) => {
        const threadId = escapeHtml(thread.thread_id || '');
        const channel = escapeHtml(thread.channel || 'whatsapp');
        const contactName = escapeHtml(thread.contact_name || thread.tenant_name || 'Cliente');
        const contact = escapeHtml(thread.contact || 'Número não identificado');
        const tenantName = escapeHtml(thread.tenant_name || 'Sem tenant');
        const unreadCount = thread.unread_count || 0;
        const messageCount = thread.message_count || 0;
        const lastActivity = thread.last_activity || 'now';
        
        // Formata data
        let dateStr = 'Agora';
        try {
            const dateTime = new Date(lastActivity);
            if (!isNaN(dateTime.getTime())) {
                const day = String(dateTime.getDate()).padStart(2, '0');
                const month = String(dateTime.getMonth() + 1).padStart(2, '0');
                const hours = String(dateTime.getHours()).padStart(2, '0');
                const minutes = String(dateTime.getMinutes()).padStart(2, '0');
                dateStr = `${day}/${month} ${hours}:${minutes}`;
            }
        } catch (e) {
            // Mantém 'Agora' se erro ao formatar
        }
        
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
                                ${thread.channel_type ? `<span style="opacity: 0.7;">• ${thread.channel_type.toUpperCase()}</span>` : ''}
                            ` : `
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                <span>Chat Interno</span>
                            `}
                            ${!thread.contact_name && thread.tenant_name && thread.tenant_name !== 'Sem tenant' ? 
                                `<span style="opacity: 0.7;">• ${tenantName}</span>` : 
                                (!thread.contact_name && (!thread.tenant_name || thread.tenant_id === null) ? 
                                    '<span style="opacity: 0.7; font-size: 10px;">• Sem tenant</span>' : '')
                            }
                        </div>
                    </div>
                    <div style="text-align: right; flex-shrink: 0; margin-left: 8px;">
                        ${unreadCount > 0 ? `
                            <span style="background: #25d366; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 18px; text-align: center;">
                                ${unreadCount}
                            </span>
                        ` : ''}
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #667781;">
                    <span>${messageCount} mensagens</span>
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
}

// Auto-preenche telefone quando seleciona cliente (WhatsApp)
document.getElementById('new-message-tenant')?.addEventListener('change', function() {
    const channel = document.getElementById('new-message-channel').value;
    const tenantSelect = this;
    const toInput = document.getElementById('new-message-to');
    const toContainer = document.getElementById('new-message-to-container');
    
    if (channel === 'whatsapp' && tenantSelect.value) {
        const phone = tenantSelect.options[tenantSelect.selectedIndex].dataset.phone;
        if (phone) {
            toInput.value = phone;
            toContainer.style.display = 'block';
        }
    }
});

document.getElementById('new-message-channel')?.addEventListener('change', function() {
    const channel = this.value;
    const toContainer = document.getElementById('new-message-to-container');
    
    if (channel === 'whatsapp') {
        toContainer.style.display = 'block';
        // Auto-preenche telefone se cliente já estiver selecionado
        const tenantSelect = document.getElementById('new-message-tenant');
        if (tenantSelect.value) {
            const phone = tenantSelect.options[tenantSelect.selectedIndex].dataset.phone;
            if (phone) {
                document.getElementById('new-message-to').value = phone;
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

/**
 * Renderiza player de mídia baseado no tipo
 */
function renderMediaPlayer(media) {
    if (!media || !media.url) return '';
    
    const mimeType = (media.mime_type || '').toLowerCase();
    const mediaType = (media.media_type || '').toLowerCase();
    const safeUrl = escapeAttr(media.url);
    
    // Determina tipo de mídia
    const isAudio = mimeType.startsWith('audio/') || mediaType === 'audio' || mediaType === 'voice';
    const isImage = mimeType.startsWith('image/') || mediaType === 'image' || mediaType === 'sticker';
    const isVideo = mimeType.startsWith('video/') || mediaType === 'video';
    
    let mediaHtml = '';
    
    if (isAudio) {
        mediaHtml = `<audio controls preload="none" src="${safeUrl}"></audio>`;
    } else if (isImage) {
        // Envolve imagem com botão clicável para abrir viewer
        mediaHtml = `
            <button type="button" class="hub-media-open" data-src="${safeUrl}" style="background: none; border: none; padding: 0; cursor: pointer; display: block;">
                <img src="${safeUrl}" class="hub-media-thumb" data-src="${safeUrl}" style="max-width:240px;border-radius:8px;display:block;" alt="Imagem">
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
 */
function handleConversationClick(clickedThreadId, channel) {
    console.log('[Hub] Clique em conversa:', clickedThreadId, channel);
    console.log('[LOG TEMPORARIO] handleConversationClick() - activeThreadId ANTES=' + (ConversationState.currentThreadId || 'NULL'));
    
    // FORÇA: setActiveThread(clickedThreadId) - ignora qualquer thread salva
    ConversationState.currentThreadId = clickedThreadId;
    ConversationState.currentChannel = channel;
    
    console.log('[LOG TEMPORARIO] handleConversationClick() - activeThreadId DEPOIS=' + clickedThreadId);
    
    // FORÇA: reset completo de markers
    ConversationState.messageIds.clear();
    ConversationState.lastTimestamp = null;
    ConversationState.lastEventId = null;
    ConversationState.newMessagesCount = 0;
    
    console.log('[LOG TEMPORARIO] handleConversationClick() - MARKERS RESETADOS');
    
    // Carrega conversa (será full load, não incremental)
    loadConversation(clickedThreadId, channel);
}

// Garante que handleConversationClick esteja no escopo global (para onclick inline)
window.handleConversationClick = handleConversationClick;

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
        const response = await fetch(url);
        const result = await response.json();
        
        if (!result.success || !result.thread) {
            throw new Error(result.error || 'Erro ao carregar conversa');
        }
        
        // Renderiza conversa
        renderConversation(result.thread, result.messages, result.channel);
        
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
        const btn = e.target.closest('.hub-media-open');
        const img = e.target.closest('.hub-media-thumb');
        const target = btn || img;
        
        if (target) {
            e.preventDefault();
            const src = target.getAttribute('data-src') || target.src;
            if (src) {
                openMediaViewer(src);
            }
        }
    });
}

/**
 * Inicializa listeners do modal de viewer de mídia
 */
function initMediaViewer() {
    const viewer = document.getElementById('hub-media-viewer');
    const img = document.getElementById('hub-media-viewer-img');
    const downloadBtn = document.getElementById('hub-media-download');
    const openNewBtn = document.getElementById('hub-media-open-new');
    const closeBtn = document.getElementById('hub-media-close');
    
    if (!viewer || !img || !downloadBtn || !openNewBtn || !closeBtn) {
        return; // Modal ainda não foi criado
    }
    
    // Botão fechar
    closeBtn.addEventListener('click', function() {
        viewer.style.display = 'none';
    });
    
    // Fechar ao clicar no overlay (fora da imagem)
    viewer.addEventListener('click', function(e) {
        if (e.target === viewer) {
            viewer.style.display = 'none';
        }
    });
    
    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && viewer.style.display !== 'none') {
            viewer.style.display = 'none';
        }
    });
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
    
    // Define a imagem
    img.src = src;
    
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
                ${channel === 'whatsapp' ? `<small>${escapeHtml(contact)}</small>` : ''}
            </div>
            <div class="header-actions">
                <button class="new-message-header-btn" onclick="openNewMessageModal()" title="Nova Mensagem">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </button>
                <div class="status">
                    <span style="color: #25d366; font-weight: 600;">Ativa</span>
                </div>
            </div>
        </div>
        
        <div class="conversation-messages-container" id="messages-container" style="position: relative;">
            <div id="new-messages-badge" class="new-messages-badge">
                <span id="new-messages-count">1</span> nova(s) mensagem(ns)
            </div>
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
            const msgDate = new Date(msg.timestamp);
            const timeStr = String(msgDate.getDate()).padStart(2, '0') + '/' + 
                          String(msgDate.getMonth() + 1).padStart(2, '0') + ' ' +
                          String(msgDate.getHours()).padStart(2, '0') + ':' + 
                          String(msgDate.getMinutes()).padStart(2, '0');
            
            const channelId = msg.channel_id || '';
            const channelIdHtml = channelId ? `<div style="font-size: 10px; color: #667781; margin-bottom: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">${escapeHtml(channelId)}</div>` : '';
            
            // Renderiza mídia se existir
            const mediaHtml = (msg.media && msg.media.url) ? renderMediaPlayer(msg.media) : '';
            
            // Conteúdo da mensagem (só mostra se não estiver vazio)
            const contentHtml = (msg.content && msg.content.trim()) 
                ? `<div style="font-size: 14.2px; color: #111b21; line-height: 1.4; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word; ${mediaHtml ? 'margin-top: 8px;' : ''}">${escapeHtml(msg.content)}</div>`
                : '';
            
            // Se não há conteúdo nem mídia, mostra placeholder
            const hasContent = (msg.content && msg.content.trim()) || mediaHtml;
            if (!hasContent) {
                // Pula mensagens completamente vazias
                return;
            }
            
            html += `
                <div class="message-bubble ${msg.direction}" 
                     data-message-id="${escapeHtml(msg.id || '')}"
                     data-timestamp="${escapeHtml(msg.timestamp || '')}"
                     style="margin-bottom: 6px; display: flex; ${isOutbound ? 'justify-content: flex-end;' : 'justify-content: flex-start;'}">
                    <div style="max-width: 72%; padding: 7px 12px; border-radius: 7.5px; ${isOutbound ? 'background: #dcf8c6; margin-left: auto; border-bottom-right-radius: 2px;' : 'background: white; border-bottom-left-radius: 2px;'}">
                        ${channelIdHtml}
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
        
        <div class="conversation-composer">
            <form id="send-message-form" onsubmit="sendMessageFromPanel(event)">
                <input type="hidden" name="channel" value="${escapeHtml(channel)}">
                <input type="hidden" name="thread_id" value="${escapeHtml(thread.thread_id || '')}">
                <input type="hidden" name="tenant_id" value="${thread.tenant_id || ''}">
                ${thread.channel_id ? `<input type="hidden" name="channel_id" value="${thread.channel_id}">` : ''}
                ${channel === 'whatsapp' && thread.contact ? `<input type="hidden" name="to" value="${escapeHtml(thread.contact)}">` : ''}
                
                <textarea name="message" id="message-input" required rows="1" 
                          placeholder="Digite sua mensagem..." 
                          onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.getElementById('send-message-form').dispatchEvent(new Event('submit')); }"
                          oninput="autoResizeTextarea(this)"></textarea>
                <button type="submit">Enviar</button>
            </form>
        </div>
        
        <!-- Modal Viewer de Mídia -->
        <div id="hub-media-viewer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; align-items: center; justify-content: center;">
            <div style="position: relative; max-width: 90%; max-height: 90%; display: flex; flex-direction: column; align-items: center;">
                <img id="hub-media-viewer-img" src="" style="max-width: 100%; max-height: 80vh; border-radius: 8px; object-fit: contain;">
                <div style="margin-top: 20px; display: flex; gap: 12px;">
                    <button id="hub-media-download" style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">Baixar</button>
                    <button id="hub-media-open-new" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">Abrir em Nova Aba</button>
                    <button id="hub-media-close" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">Fechar</button>
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
    
    // Inicializa listeners do modal (uma vez, se ainda não foi inicializado)
    if (!window.hubMediaViewerInitialized) {
        window.hubMediaViewerInitialized = true;
        // Aguarda um pouco para garantir que o modal foi criado no DOM
        setTimeout(() => {
            initMediaViewer();
        }, 100);
    }
    
    // Inicializa event delegation para viewer de mídia (uma vez, global)
    initMediaViewerOnce();
    
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
}

/**
 * Envia mensagem do painel
 */
async function sendMessageFromPanel(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const messageInput = document.getElementById('message-input');
    const messageText = messageInput.value.trim();
    
    if (!messageText) {
        return;
    }
    
    // VALIDAÇÃO: Garante que channel_id está presente para WhatsApp
    const channel = formData.get('channel');
    const channelId = formData.get('channel_id');
    if (channel === 'whatsapp' && !channelId) {
        alert('Erro: Canal não identificado. Recarregue a conversa e tente novamente.');
        console.error('[CommunicationHub] Tentativa de envio sem channel_id. FormData:', Object.fromEntries(formData));
        return;
    }
    
    // Log para debug
    console.log('[CommunicationHub] Enviando mensagem:', {
        channel: channel,
        channel_id: channelId,
        thread_id: formData.get('thread_id'),
        to: formData.get('to')
    });
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';
    
    // Mensagem otimista
    const tempId = 'temp_' + Date.now();
    const optimisticMessage = {
        id: tempId,
        direction: 'outbound',
        content: messageText,
        timestamp: new Date().toISOString()
    };
    
    addMessageToPanel(optimisticMessage);
    messageInput.value = '';
    
    try {
        const sendUrl = '<?= pixelhub_url('/communication-hub/send') ?>';
        const response = await fetch(sendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(formData)
        });
        
        const result = await response.json();
        
        if (result.success && result.event_id) {
            // Busca mensagem confirmada
            await confirmSentMessageFromPanel(result.event_id, tempId);
        } else {
            // Erro: remove mensagem otimista
            const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
            if (tempMsg) tempMsg.remove();
            alert('Erro: ' + (result.error || 'Erro ao enviar mensagem'));
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enviar';
        }
    } catch (error) {
        const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
        if (tempMsg) tempMsg.remove();
        alert('Erro ao enviar mensagem: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enviar';
    }
}

/**
 * Adiciona mensagem ao painel
 */
function addMessageToPanel(message) {
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    const msgId = message.id || '';
    const direction = message.direction || 'inbound';
    const content = message.content || '';
    const timestamp = message.timestamp || new Date().toISOString();
    
    const date = new Date(timestamp);
    const timeStr = String(date.getDate()).padStart(2, '0') + '/' + 
                   String(date.getMonth() + 1).padStart(2, '0') + ' ' +
                   String(date.getHours()).padStart(2, '0') + ':' + 
                   String(date.getMinutes()).padStart(2, '0');
    
    const isOutbound = direction === 'outbound';
    
    // Renderiza mídia se existir
    const mediaHtml = (message.media && message.media.url) ? renderMediaPlayer(message.media) : '';
    
    // Conteúdo da mensagem (só mostra se não estiver vazio)
    const contentHtml = (content && content.trim()) 
        ? `<div style="font-size: 14.2px; color: #111b21; line-height: 1.4; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word; ${mediaHtml ? 'margin-top: 8px;' : ''}">${escapeHtml(content)}</div>`
        : '';
    
    // Se não há conteúdo nem mídia, não adiciona mensagem vazia
    const hasContent = (content && content.trim()) || mediaHtml;
    if (!hasContent) {
        return;
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message-bubble ' + direction;
    messageDiv.setAttribute('data-message-id', msgId);
    messageDiv.setAttribute('data-timestamp', timestamp);
    messageDiv.style.cssText = 'margin-bottom: 6px; display: flex; ' + (isOutbound ? 'justify-content: flex-end;' : 'justify-content: flex-start;');
    
    const channelId = message.channel_id || '';
    const channelIdHtml = channelId ? `<div style="font-size: 10px; color: #667781; margin-bottom: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">${escapeHtml(channelId)}</div>` : '';
    
    messageDiv.innerHTML = `
        <div style="max-width: 72%; padding: 7px 12px; border-radius: 7.5px; ${isOutbound ? 'background: #dcf8c6; margin-left: auto; border-bottom-right-radius: 2px;' : 'background: white; border-bottom-left-radius: 2px;'}">
            ${channelIdHtml}
            ${mediaHtml}
            ${contentHtml}
            <div style="font-size: 11px; color: #667781; margin-top: 3px; text-align: right; padding-top: 2px; opacity: 0.8;">
                ${timeStr}
            </div>
        </div>
    `;
    
    container.appendChild(messageDiv);
    
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
    try {
        const url = '<?= pixelhub_url('/communication-hub/message') ?>?' + 
                   new URLSearchParams({
                       event_id: eventId,
                       thread_id: ConversationState.currentThreadId
                   });
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.message) {
            // Remove mensagem otimista
            const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
            if (tempMsg) tempMsg.remove();
            
            // Adiciona mensagem confirmada
            onNewMessagesFromPanel([result.message]);
            
            // Reabilita formulário
            const submitBtn = document.querySelector('#send-message-form button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar';
            }
        }
    } catch (error) {
        console.error('[Hub] Erro ao confirmar mensagem:', error);
    }
}

/**
 * Processa novas mensagens no painel
 */
function onNewMessagesFromPanel(messages) {
    if (!messages || messages.length === 0) return;
    
    const container = document.getElementById('messages-container');
    if (!container) return;
    
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
 * Toggle estatísticas
 */
function toggleStats() {
    const stats = document.getElementById('communication-stats');
    const toggle = document.getElementById('stats-toggle-text');
    if (stats && toggle) {
        stats.classList.toggle('expanded');
        toggle.textContent = stats.classList.contains('expanded') ? 'Ocultar estatísticas' : 'Mostrar estatísticas';
    }
}

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
    
    modal.style.display = 'flex';
}

/**
 * Fecha modal de vincular tenant
 */
function closeLinkTenantModal() {
    const modal = document.getElementById('link-tenant-modal');
    if (modal) {
        modal.style.display = 'none';
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
    if (!confirm('Tem certeza que deseja ignorar este lead? A conversa será arquivada.')) {
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
            // Remove o item da lista sem recarregar a página
            const item = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (item) {
                item.style.opacity = '0.5';
                item.style.pointerEvents = 'none';
                setTimeout(() => {
                    item.remove();
                    // Atualiza contador se necessário
                    updateIncomingLeadsCount();
                }, 300);
            } else {
                window.location.reload();
            }
        } else {
            alert('Erro: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao rejeitar incoming lead:', error);
        alert('Erro ao ignorar lead. Tente novamente.');
    }
}

/**
 * Atualiza contador de incoming leads
 */
function updateIncomingLeadsCount() {
    const count = document.querySelectorAll('.incoming-lead-item').length;
    const badge = document.querySelector('.incoming-leads-badge');
    if (badge) {
        badge.textContent = count;
        if (count === 0) {
            const section = document.querySelector('.incoming-leads-section');
            if (section) {
                section.style.display = 'none';
            }
        }
    }
}
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
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Selecione o Cliente *</label>
                <select id="link-tenant-select" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione um cliente...</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                            <?php if (!empty($tenant['email'])): ?>
                                (<?= htmlspecialchars($tenant['email']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

<?php
$content = ob_get_clean();
// Constrói caminho do layout: sobe 1 nível de communication_hub para views, depois layout/main.php
$viewsDir = dirname(__DIR__); // views/communication_hub -> views
$layoutFile = $viewsDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'main.php';
require $layoutFile;
?>

