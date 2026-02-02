<?php
use PixelHub\Core\Storage;

ob_start();

$activeTab = $activeTab ?? 'overview';
$providerMap = $providerMap ?? [];
$emailAccounts = $emailAccounts ?? [];
?>

<style>
/* Tooltips para botões de ação */
[data-tooltip] {
    position: relative;
}

[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    margin-bottom: 5px;
    padding: 6px 10px;
    background: #333;
    color: white;
    font-size: 12px;
    border-radius: 4px;
    pointer-events: none;
    z-index: 1000;
    opacity: 0;
    animation: tooltipFadeIn 0.2s ease forwards;
    /* Largura: mínima suficiente, máxima controlada */
    min-width: 120px;
    max-width: 220px;
    /* Controle de quebra de palavras: evita quebra agressiva */
    white-space: normal;
    word-break: normal;
    word-wrap: break-word;
    hyphens: none;
    /* Layout e espaçamento */
    text-align: center;
    line-height: 1.3;
    /* Posicionamento padrão: centralizado */
    left: 50%;
    transform: translateX(-50%);
}

/* Para botões no final da linha (último botão), alinha à direita */
.content-header > div:last-child [data-tooltip]:last-of-type:hover::after,
.content-header > div:last-child [data-tooltip]:last-child:hover::after {
    left: auto;
    right: 0;
    transform: none;
}

[data-tooltip]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    margin-bottom: -1px;
    border: 5px solid transparent;
    border-top-color: #333;
    pointer-events: none;
    z-index: 1000;
    opacity: 0;
    animation: tooltipFadeIn 0.2s ease forwards;
    /* Posicionamento padrão: centralizado */
    left: 50%;
    transform: translateX(-50%);
}

.content-header > div:last-child [data-tooltip]:last-of-type:hover::before,
.content-header > div:last-child [data-tooltip]:last-child:hover::before {
    left: auto;
    right: 8px;
    transform: none;
}

@keyframes tooltipFadeIn {
    to {
        opacity: 1;
    }
}

/* Botões compactos de ação */
.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 32px;
    height: 32px;
}

.btn-action svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.btn-action-primary {
    background: #023A8D;
    color: white;
}

.btn-action-primary:hover {
    background: #022a6d;
}

.btn-action-secondary {
    background: #6c757d;
    color: white;
}

.btn-action-secondary:hover {
    background: #555;
}

.btn-action-danger {
    background: #c33;
    color: white;
}

.btn-action-danger:hover {
    background: #a22;
}

.btn-action-success {
    background: #28a745;
    color: white;
}

.btn-action-success:hover {
    background: #218838;
}
</style>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2><?= htmlspecialchars($tenant['name']) ?></h2>
        <p>Painel do Cliente</p>
    </div>
    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
        <button onclick="openWhatsAppModal(<?= $tenant['id'] ?>)" 
                class="btn-action btn-action-success"
                data-tooltip="WhatsApp"
                aria-label="WhatsApp">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
        </button>
        <a href="<?= pixelhub_url('/tickets/create?tenant_id=' . $tenant['id']) ?>" 
           class="btn-action btn-action-primary"
           data-tooltip="Novo Ticket"
           aria-label="Novo Ticket">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </a>
        <a href="<?= pixelhub_url('/tenants/edit?id=' . $tenant['id']) ?>" 
           class="btn-action btn-action-secondary"
           data-tooltip="Editar Cliente"
           aria-label="Editar Cliente">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
        </a>
        <form method="POST" action="<?= pixelhub_url('/tenants/delete') ?>" 
              onsubmit="return confirm('Tem certeza que deseja excluir este cliente? Esta ação não poderá ser desfeita.');" 
              style="display: inline-block; margin: 0;">
            <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
            <button type="submit" 
                    class="btn-action btn-action-danger"
                    data-tooltip="Excluir Cliente"
                    aria-label="Excluir Cliente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
            </button>
        </form>
        <?php if (!empty($tenant['is_archived'])): ?>
            <form method="POST" action="<?= pixelhub_url('/tenants/archive') ?>" 
                  style="display: inline-block; margin: 0;">
                <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
                <input type="hidden" name="action" value="unarchive">
                <button type="submit" 
                        class="btn-action btn-action-primary"
                        data-tooltip="Desarquivar Cliente"
                        aria-label="Desarquivar Cliente">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="<?= pixelhub_url('/tenants/archive') ?>" 
                  onsubmit="return confirm('Tem certeza que deseja arquivar este cliente? Ele não aparecerá mais na lista de clientes, mas continuará acessível na Central de Cobrança.');"
                  style="display: inline-block; margin: 0;">
                <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
                <input type="hidden" name="action" value="archive">
                <button type="submit" 
                        class="btn-action btn-action-secondary"
                        data-tooltip="Arquivar Cliente (Somente Financeiro)"
                        aria-label="Arquivar Cliente">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($tenant['is_archived'])): ?>
    <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
        <p style="color: #856404; margin: 0; display: flex; align-items: center; gap: 8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Este cliente está <strong>arquivado</strong> e não aparece na lista de clientes. 
            Ele permanece acessível para consultas financeiras e na Central de Cobrança.
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'uploaded') {
                echo 'Backup enviado com sucesso!';
            } elseif ($_GET['success'] === 'created') {
                echo 'Cliente criado com sucesso!';
            } elseif ($_GET['success'] === 'updated') {
                echo 'Cliente atualizado com sucesso!';
            } elseif ($_GET['success'] === 'deleted') {
                echo 'Backup excluído com sucesso!';
            } elseif ($_GET['success'] === 'deleted_but_file_remains') {
                echo 'Backup excluído do banco de dados, mas o arquivo físico não pôde ser removido.';
            } elseif ($_GET['success'] === 'doc_uploaded') {
                echo 'Documento enviado com sucesso!';
            } elseif ($_GET['success'] === 'doc_deleted') {
                echo 'Documento excluído com sucesso!';
            } elseif ($_GET['success'] === 'archived') {
                if (isset($_GET['message'])) {
                    echo htmlspecialchars(urldecode($_GET['message']));
                } else {
                    echo 'Cliente arquivado com sucesso.';
                }
            } elseif ($_GET['success'] === 'unarchived') {
                if (isset($_GET['message'])) {
                    echo htmlspecialchars(urldecode($_GET['message']));
                } else {
                    echo 'Cliente desarquivado com sucesso.';
                }
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'cannot_delete_has_hosting') {
                echo 'Não é possível excluir um cliente que possui contas de hospedagem vinculadas.';
            } elseif ($error === 'delete_failed') {
                echo 'Erro ao excluir o cliente. Tente novamente.';
            } elseif ($error === 'archive_failed') {
                echo 'Erro ao arquivar/desarquivar o cliente. Tente novamente.';
            } elseif ($error === 'doc_no_file_or_link') {
                echo 'É necessário fornecer um arquivo ou uma URL para cadastrar o documento.';
            } elseif ($error === 'doc_invalid_extension') {
                echo 'Extensão de arquivo não permitida. Extensões permitidas: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, JPG, JPEG, PNG, WEBP, GIF, ZIP, RAR, 7Z, TAR, GZ, SQL, MP4.';
            } elseif ($error === 'doc_file_too_large') {
                echo 'Arquivo muito grande. O limite é 200MB.';
            } elseif ($error === 'doc_dir_not_writable') {
                echo 'Erro ao salvar arquivo. Diretório não é gravável.';
            } elseif ($error === 'doc_move_failed') {
                echo 'Falha ao salvar o arquivo.';
            } elseif ($error === 'doc_database_error') {
                echo 'Erro ao registrar o documento no banco de dados.';
            } elseif ($error === 'doc_delete_missing_id') {
                echo 'ID do documento não fornecido para exclusão.';
            } elseif ($error === 'doc_delete_not_found') {
                echo 'Documento não encontrado para exclusão.';
            } elseif ($error === 'doc_delete_database_error') {
                echo 'Erro ao excluir documento do banco de dados.';
            } elseif ($error === 'duplicate_failed') {
                echo 'Erro ao duplicar conta de email. Tente novamente.';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<!-- Abas -->
<div style="border-bottom: 2px solid #ddd; margin-bottom: 20px;">
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=overview') ?>" 
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'overview' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'overview' ? '#023A8D' : 'transparent' ?>; margin-right: 10px; font-weight: <?= $activeTab === 'overview' ? '600' : '400' ?>;">
        Visão Geral
    </a>
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=hosting') ?>" 
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'hosting' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'hosting' ? '#023A8D' : 'transparent' ?>; margin-right: 10px; font-weight: <?= $activeTab === 'hosting' ? '600' : '400' ?>;">
        Serviços Ativos
    </a>
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=docs_backups') ?>" 
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'docs_backups' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'docs_backups' ? '#023A8D' : 'transparent' ?>; margin-right: 10px; font-weight: <?= $activeTab === 'docs_backups' ? '600' : '400' ?>;">
        Docs & Backups
    </a>
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=tasks') ?>" 
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'tasks' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'tasks' ? '#023A8D' : 'transparent' ?>; margin-right: 10px; font-weight: <?= $activeTab === 'tasks' ? '600' : '400' ?>;">
        Tarefas & Projetos
    </a>
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=financial') ?>" 
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'financial' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'financial' ? '#023A8D' : 'transparent' ?>; margin-right: 10px; font-weight: <?= $activeTab === 'financial' ? '600' : '400' ?>;">
        Financeiro
    </a>
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=notifications') ?>" 
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'notifications' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'notifications' ? '#023A8D' : 'transparent' ?>; font-weight: <?= $activeTab === 'notifications' ? '600' : '400' ?>;">
        Notificações
    </a>
</div>

<!-- Conteúdo das Abas -->
<?php if ($activeTab === 'overview'): ?>
    <!-- ABA: Visão Geral -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
            <h3 style="margin: 0; color: #333; font-size: 18px; font-weight: 600;">Informações do Cliente</h3>
            <div style="display: flex; gap: 5px;">
                <button onclick="syncAsaasData()" id="sync-asaas-btn"
                        class="btn-action btn-action-secondary"
                        data-tooltip="Sincronizar com Asaas"
                        aria-label="Sincronizar com Asaas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    <span id="sync-asaas-text" style="display: none;">Sincronizar com Asaas</span>
                </button>
                <button onclick="openEditAsaasFieldsModal()" 
                        class="btn-action btn-action-secondary"
                        data-tooltip="Editar Campos do Asaas"
                        aria-label="Editar Campos do Asaas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div id="sync-asaas-message" style="display: none; margin-bottom: 20px; padding: 14px; border-radius: 6px; font-size: 14px; line-height: 1.6;"></div>
        
        <!-- Informações Básicas -->
        <div style="margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">
            <h4 style="margin: 0 0 20px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Dados Pessoais</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Nome</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;"><?= htmlspecialchars($tenant['name']) ?></div>
                </div>
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Tipo de Pessoa</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;">
                        <?= ($tenant['person_type'] ?? 'pf') === 'pj' ? 'Pessoa Jurídica' : 'Pessoa Física' ?>
                    </div>
                </div>
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">CPF/CNPJ</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400; font-family: 'Courier New', monospace;">
                        <?= htmlspecialchars($tenant['cpf_cnpj'] ?? ($tenant['document'] ?? '-')) ?>
                    </div>
                </div>
                <?php if (($tenant['person_type'] ?? 'pf') === 'pj'): ?>
                    <?php if ($tenant['razao_social']): ?>
                    <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Razão Social</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;"><?= htmlspecialchars($tenant['razao_social']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($tenant['nome_fantasia']): ?>
                    <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Nome Fantasia</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;"><?= htmlspecialchars($tenant['nome_fantasia']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($tenant['responsavel_nome']): ?>
                    <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Responsável</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= htmlspecialchars($tenant['responsavel_nome']) ?>
                            <?php if ($tenant['responsavel_cpf']): ?>
                                <span style="color: #666; margin-left: 8px; font-size: 13px;">(CPF: <?= htmlspecialchars($tenant['responsavel_cpf']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informações de Contato -->
        <div style="margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">
            <h4 style="margin: 0 0 20px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Contato</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Email</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;">
                        <?php if ($tenant['email']): ?>
                            <a href="mailto:<?= htmlspecialchars($tenant['email']) ?>" style="color: #333; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border-bottom: 1px solid transparent; transition: border-color 0.2s;" onmouseover="this.style.borderBottomColor='#999'" onmouseout="this.style.borderBottomColor='transparent'">
                                <?= htmlspecialchars($tenant['email']) ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Não informado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">WhatsApp</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;">
                        <?php if ($tenant['phone']): ?>
                            <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $tenant['phone']) ?>" target="_blank" rel="noopener noreferrer" style="color: #333; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border-bottom: 1px solid transparent; transition: border-color 0.2s;" onmouseover="this.style.borderBottomColor='#999'" onmouseout="this.style.borderBottomColor='transparent'">
                                <?= htmlspecialchars($tenant['phone']) ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Não informado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Telefone Fixo</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;">
                        <?= $tenant['phone_fixed'] ? htmlspecialchars($tenant['phone_fixed']) : '<span style="color: #999; font-style: italic;">Não informado</span>' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <?php 
        $hasAddress = !empty($tenant['address_cep']) || !empty($tenant['address_street']) || !empty($tenant['address_city']);
        if ($hasAddress):
        // Processa cidade: remove códigos numéricos no início (ex: "12533 - São Paulo" vira "São Paulo")
        $cityName = $tenant['address_city'] ?? '';
        if ($cityName) {
            // Remove padrões como "12533 - " ou "12533 -" do início
            $cityName = preg_replace('/^\d+\s*-\s*/', '', $cityName);
            $cityName = trim($cityName);
            // Se for apenas números (código IBGE), não exibe como cidade válida
            // Será corrigido na próxima sincronização que prioriza valores com letras
            if (preg_match('/^\d+$/', $cityName)) {
                $cityName = ''; // Vazio força exibição de "Não informado"
            }
        }
        ?>
        <div style="margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">
            <h4 style="margin: 0 0 20px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Endereço</h4>
            <div style="border: 1px solid #e0e0e0; padding: 20px; background: #fff;">
                <!-- Linha 1: Rua + Número -->
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;">
                    <div>
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Rua</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= $tenant['address_street'] ? htmlspecialchars($tenant['address_street']) : '<span style="color: #999; font-style: italic;">Não informado</span>' ?>
                        </div>
                    </div>
                    <div style="min-width: 80px;">
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Número</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= $tenant['address_number'] ? htmlspecialchars($tenant['address_number']) : '<span style="color: #999; font-style: italic;">S/N</span>' ?>
                        </div>
                    </div>
                </div>
                
                <!-- Linha 2: Bairro + Cidade + UF -->
                <div style="display: grid; grid-template-columns: 1fr 1.5fr auto; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Bairro</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= $tenant['address_neighborhood'] ? htmlspecialchars($tenant['address_neighborhood']) : '<span style="color: #999; font-style: italic;">Não informado</span>' ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Cidade</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= $cityName ? htmlspecialchars($cityName) : '<span style="color: #999; font-style: italic;">Não informado</span>' ?>
                        </div>
                    </div>
                    <div style="min-width: 50px;">
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">UF</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= $tenant['address_state'] ? htmlspecialchars($tenant['address_state']) : '<span style="color: #999; font-style: italic;">-</span>' ?>
                        </div>
                    </div>
                </div>
                
                <!-- Meta info: CEP e Complemento -->
                <div style="display: flex; gap: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <div>
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">CEP</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400; font-family: 'Courier New', monospace;">
                            <?= $tenant['address_cep'] ? htmlspecialchars($tenant['address_cep']) : '<span style="color: #999; font-style: italic;">Não informado</span>' ?>
                        </div>
                    </div>
                    <?php if ($tenant['address_complement']): ?>
                    <div>
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Complemento</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400;">
                            <?= htmlspecialchars($tenant['address_complement']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Informações do Asaas e Status -->
        <div style="margin-bottom: 30px;">
            <h4 style="margin: 0 0 20px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Informações do Asaas</h4>
            
            <!-- ID Asaas -->
            <?php if ($tenant['asaas_customer_id']): ?>
            <div style="margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                        <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">ID Asaas</div>
                        <div style="font-size: 14px; color: #333; font-weight: 400; font-family: 'Courier New', monospace;">
                            <?php
                            $asaasUrl = \PixelHub\Core\AsaasHelper::buildCustomerPanelUrl($tenant['asaas_customer_id']);
                            if (!empty($asaasUrl)):
                            ?>
                                <a href="<?= htmlspecialchars($asaasUrl) ?>" target="_blank" rel="noopener noreferrer" 
                                   style="color: #333; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border-bottom: 1px solid transparent; transition: border-color 0.2s;" onmouseover="this.style.borderBottomColor='#999'" onmouseout="this.style.borderBottomColor='transparent'">
                                    <?= htmlspecialchars($tenant['asaas_customer_id']) ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span><?= htmlspecialchars($tenant['asaas_customer_id']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Status & Atividade -->
            <div style="margin-bottom: 20px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">
                <h5 style="margin: 0 0 20px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Status & Atividade</h5>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <?php if ($tenant['billing_status']): ?>
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Status Financeiro</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;">
                        <?php
                        $billingStatusLabels = [
                            'sem_cobranca' => 'Sem Cobrança',
                            'em_dia' => 'Em Dia',
                            'atrasado_parcial' => 'Atrasado Parcial',
                            'atrasado_total' => 'Atrasado Total'
                        ];
                        $status = $tenant['billing_status'];
                        $label = $billingStatusLabels[$status] ?? $status;
                        ?>
                        <span style="color: #333; font-weight: 400;">
                            <?= htmlspecialchars($label) ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($tenant['billing_last_check_at']): ?>
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Última Verificação Financeira</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400; margin-bottom: 4px;">
                        <?= date('d/m/Y H:i', strtotime($tenant['billing_last_check_at'])) ?>
                    </div>
                    <div style="font-size: 11px; color: #999; font-style: italic; margin-top: 4px;">
                        Atualizado automaticamente via Asaas
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Último Contato WhatsApp</div>
                    <div id="last-whatsapp-contact" style="font-size: 14px; color: #333; font-weight: 400;">
                        <?php if (!empty($lastWhatsAppContact)): ?>
                            <?php
                            $sentAt = $lastWhatsAppContact['sent_at'] ?? null;
                            $sentAtFormatted = $sentAt ? date('d/m/Y H:i', strtotime($sentAt)) : '-';
                            $source = $lastWhatsAppContact['source'] ?? 'generic';
                            $sourceLabel = $source === 'billing' ? 'Financeiro' : 'Visão Geral';
                            $templateName = $lastWhatsAppContact['template_name'] ?? null;
                            $templateId = $lastWhatsAppContact['template_id'] ?? null;
                            
                            if ($templateId === null && $source === 'generic') {
                                $templateInfo = 'Sem template (mensagem livre)';
                            } elseif ($templateName) {
                                $templateInfo = htmlspecialchars($templateName);
                            } else {
                                $templateInfo = 'Sem template';
                            }
                            ?>
                            <div style="margin-bottom: 4px;"><?= htmlspecialchars($sentAtFormatted) ?></div>
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                Origem: <?= htmlspecialchars($sourceLabel) ?> • Template: <?= $templateInfo ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Nenhum contato registrado</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 11px; color: #999; font-style: italic; margin-top: 6px;">
                        Registro automático de mensagens enviadas
                    </div>
                </div>
                
                <div style="border: 1px solid #e0e0e0; padding: 12px 15px; background: #fff;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Status do Cliente</div>
                    <div style="font-size: 14px; color: #333; font-weight: 400;">
                        <?php
                        $statusLabel = $tenant['status'] === 'active' ? 'Ativo' : 'Inativo';
                        ?>
                        <span style="color: #333; font-weight: 400;">
                            <?= $statusLabel ?>
                        </span>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Observações Internas -->
        <?php if ($tenant['internal_notes']): ?>
        <div style="margin-bottom: 20px; padding-bottom: 25px; border-bottom: 1px solid #e0e0e0;">
            <h4 style="margin: 0 0 20px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Observações Internas</h4>
            <div style="border: 1px solid #e0e0e0; padding: 15px; background: #fff;">
                <div style="font-size: 14px; color: #333; white-space: pre-wrap; line-height: 1.6; font-weight: 400;"><?= htmlspecialchars($tenant['internal_notes']) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Edição dos Campos do Asaas -->
    <div id="edit-asaas-fields-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div style="max-width: 800px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
            <button onclick="closeEditAsaasFieldsModal()" 
                    style="position: absolute; top: 15px; right: 15px; background: #c33; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; line-height: 1;">×</button>
            
            <h2 style="margin: 0 0 20px 0; color: #023A8D;">Editar Campos do Asaas</h2>
            <p style="color: #666; margin-bottom: 20px;">Atualize os campos que serão sincronizados com o Asaas. Se o cliente não estiver vinculado ao Asaas, será criado automaticamente.</p>
            
            <?php
            // Prepara dados para o formulário: prioriza dados locais, mas preenche com consolidados se vazio
            $formEmail = $tenant['email'] ?? ($consolidatedAsaasData['email'] ?? '');
            $formPhone = $tenant['phone'] ?? '';
            $formPhoneFixed = $tenant['phone_fixed'] ?? ($consolidatedAsaasData['phone_fixed'] ?? '');
            $formAddressCep = $tenant['address_cep'] ?? ($consolidatedAsaasData['address_cep'] ?? '');
            $formAddressStreet = $tenant['address_street'] ?? ($consolidatedAsaasData['address_street'] ?? '');
            $formAddressNumber = $tenant['address_number'] ?? ($consolidatedAsaasData['address_number'] ?? '');
            $formAddressComplement = $tenant['address_complement'] ?? ($consolidatedAsaasData['address_complement'] ?? '');
            $formAddressNeighborhood = $tenant['address_neighborhood'] ?? ($consolidatedAsaasData['address_neighborhood'] ?? '');
            $formAddressCity = $tenant['address_city'] ?? ($consolidatedAsaasData['address_city'] ?? '');
            $formAddressState = $tenant['address_state'] ?? ($consolidatedAsaasData['address_state'] ?? '');
            
            // Mostra aviso se houver dados consolidados sendo usados
            $hasConsolidatedData = !empty($consolidatedAsaasData);
            $usingConsolidatedData = false;
            if ($hasConsolidatedData) {
                $usingConsolidatedData = (
                    (empty($tenant['email']) && !empty($consolidatedAsaasData['email'])) ||
                    (empty($tenant['phone_fixed']) && !empty($consolidatedAsaasData['phone_fixed'])) ||
                    (empty($tenant['address_cep']) && !empty($consolidatedAsaasData['address_cep'])) ||
                    (empty($tenant['address_street']) && !empty($consolidatedAsaasData['address_street']))
                );
            }
            ?>
            
            <?php if ($usingConsolidatedData): ?>
            <div style="margin-bottom: 20px; padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
                <p style="margin: 0; color: #1976d2; font-size: 14px;">
                    <strong><span class="icon-info" style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></span> Dados consolidados do Asaas:</strong> Alguns campos foram preenchidos automaticamente com dados consolidados de múltiplos cadastros do Asaas para este CPF.
                </p>
            </div>
            <?php endif; ?>
            
            <form id="edit-asaas-fields-form" onsubmit="saveAsaasFields(event)">
                <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($formEmail) ?>" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">WhatsApp:</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($formPhone) ?>" 
                           placeholder="(00) 00000-0000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Telefone Fixo:</label>
                    <input type="text" name="phone_fixed" value="<?= htmlspecialchars($formPhoneFixed) ?>" 
                           placeholder="(00) 0000-0000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px;">Endereço</h3>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">CEP:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="modal-address_cep" name="address_cep" value="<?= htmlspecialchars($formAddressCep) ?>" 
                                   placeholder="00000-000" maxlength="9"
                                   style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <button type="button" onclick="buscarCepModal()" 
                                    style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                                Buscar CEP
                            </button>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Rua / Logradouro:</label>
                            <input type="text" name="address_street" value="<?= htmlspecialchars($formAddressStreet) ?>" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Número:</label>
                            <input type="text" name="address_number" value="<?= htmlspecialchars($formAddressNumber) ?>" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Complemento:</label>
                        <input type="text" name="address_complement" value="<?= htmlspecialchars($formAddressComplement) ?>" 
                               placeholder="Apto, Sala, etc."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bairro:</label>
                            <input type="text" name="address_neighborhood" value="<?= htmlspecialchars($formAddressNeighborhood) ?>" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Cidade:</label>
                            <input type="text" name="address_city" value="<?= htmlspecialchars($formAddressCity) ?>" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Estado (UF):</label>
                            <input type="text" name="address_state" value="<?= htmlspecialchars($formAddressState) ?>" 
                                   placeholder="SP" maxlength="2" style="text-transform: uppercase; width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>
                
                <div id="asaas-fields-message" style="margin-bottom: 20px; display: none;"></div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditAsaasFieldsModal()" 
                            style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Cancelar
                    </button>
                    <button type="submit" 
                            style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Salvar e Sincronizar com Asaas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Detalhes da Mensagem -->
    <div id="message-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div style="max-width: 700px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
            <button onclick="closeMessageModal()" 
                    style="position: absolute; top: 15px; right: 15px; background: #c33; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; line-height: 1;">×</button>
            
            <h2 style="margin: 0 0 20px 0; color: #023A8D;">Detalhes da Mensagem</h2>
            
            <div id="message-detail-content" style="color: #666;">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
    </div>

    <script>
    // Função para atualizar timeline via AJAX
    function updateWhatsAppTimeline(tenantId) {
        if (!tenantId) {
            console.error('tenantId não fornecido para atualizar timeline');
            return;
        }

        fetch('<?= pixelhub_url('/tenants/whatsapp-timeline-ajax') ?>?tenant_id=' + tenantId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualiza último contato
                    updateLastContact(data.lastContact);
                    
                    // Atualiza timeline
                    updateTimelineTable(data.timeline);
                } else {
                    console.error('Erro ao atualizar timeline:', data.message);
                }
            })
            .catch(err => {
                console.error('Erro ao buscar timeline:', err);
            });
    }

    function updateLastContact(lastContact) {
        const container = document.getElementById('last-whatsapp-contact');
        if (!container) return;

        if (!lastContact) {
            container.innerHTML = '<span style="color: #999;">Nenhum contato registrado</span>';
            return;
        }

        const sentAt = lastContact.sent_at || null;
        const sentAtFormatted = sentAt ? formatDateTime(sentAt) : '-';
        const source = lastContact.source || 'generic';
        const sourceLabel = source === 'billing' ? 'Financeiro' : 'Visão Geral';
        const templateName = lastContact.template_name || null;
        const templateId = lastContact.template_id || null;

        let templateInfo = 'Sem template';
        if (templateId === null && source === 'generic') {
            templateInfo = 'Sem template (mensagem livre)';
        } else if (templateName) {
            templateInfo = escapeHtml(templateName);
        }

        container.innerHTML = `${escapeHtml(sentAtFormatted)} – Origem: ${escapeHtml(sourceLabel)} – Template: ${templateInfo}`;
    }

    function updateTimelineTable(timeline) {
        const container = document.getElementById('whatsapp-timeline-container');
        if (!container) return;

        if (!timeline || timeline.length === 0) {
            container.innerHTML = '<p style="color: #666; text-align: center; padding: 20px;">Nenhum histórico de WhatsApp registrado.</p>';
            return;
        }

        let html = '<table style="width: 100%; border-collapse: collapse;">';
        html += '<thead>';
        html += '<tr style="background: #f5f5f5;">';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data/Hora</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Origem</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Template</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Observação</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody id="whatsapp-timeline-tbody">';

        timeline.forEach((item, index) => {
            const sentAt = item.sent_at || null;
            const sentAtFormatted = sentAt ? formatDateTime(sentAt) : '-';
            const source = item.source || 'generic';
            const sourceLabel = source === 'billing' ? 'Financeiro' : 'Visão Geral';
            const templateId = item.template_id || null;
            const templateName = item.template_name || null;

            let templateHtml = '<span style="color: #999;">-</span>';
            if (templateId === null && source === 'generic') {
                templateHtml = '<span style="color: #999;">Sem template / mensagem livre</span>';
            } else if (templateName) {
                templateHtml = escapeHtml(templateName);
            }

            const message = item.message || item.message_full || '';
            const messageFull = item.message_full || message;
            const preview = message.length > 120 ? message.substring(0, 120) + '...' : message;

            let observationHtml = '';
            if (source === 'billing') {
                observationHtml = escapeHtml(item.description || '');
                if (message) {
                    observationHtml += '<br><span style="color: #666; font-size: 12px;">' + escapeHtml(preview) + '</span>';
                }
            } else {
                observationHtml = `<span style="color: #666; font-size: 13px;">${escapeHtml(preview)}</span>`;
            }

            const actionsHtml = messageFull ? 
                `<button onclick="openMessageModal(${index})" style="background: #023A8D; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">Ver mensagem</button>
                 <div id="message-data-${index}" style="display: none;" 
                      data-sent-at="${escapeHtml(sentAt || '')}"
                      data-source="${escapeHtml(source)}"
                      data-source-label="${escapeHtml(sourceLabel)}"
                      data-template-name="${escapeHtml(templateName || (templateId === null && source === 'generic' ? 'Sem template / mensagem livre' : 'Sem template'))}"
                      data-phone="${escapeHtml(item.phone || '')}"
                      data-message-full="${escapeHtml(messageFull)}"></div>` :
                '<span style="color: #999; font-size: 13px;">-</span>';

            html += '<tr>';
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${escapeHtml(sentAtFormatted)}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${escapeHtml(sourceLabel)}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${templateHtml}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${observationHtml}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${actionsHtml}</td>`;
            html += '</tr>';
        });

        html += '</tbody>';
        html += '</table>';

        container.innerHTML = html;
    }

    // Funções do modal de detalhes da mensagem
    function openMessageModal(index) {
        const dataDiv = document.getElementById('message-data-' + index);
        if (!dataDiv) {
            console.error('Dados da mensagem não encontrados para índice:', index);
            return;
        }

        const sentAt = dataDiv.getAttribute('data-sent-at') || '';
        const sourceLabel = dataDiv.getAttribute('data-source-label') || '';
        const templateName = dataDiv.getAttribute('data-template-name') || 'Sem template';
        const phone = dataDiv.getAttribute('data-phone') || '';
        const messageFull = dataDiv.getAttribute('data-message-full') || '';

        // Formata data/hora
        let sentAtFormatted = '-';
        if (sentAt) {
            try {
                const date = new Date(sentAt);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                sentAtFormatted = `${day}/${month}/${year} ${hours}:${minutes}`;
            } catch (e) {
                sentAtFormatted = sentAt;
            }
        }

        // Formata telefone (se houver)
        let phoneFormatted = phone;
        if (phone && phone.length >= 10) {
            // Formata como (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
            const digits = phone.replace(/\D/g, '');
            if (digits.length === 11) {
                phoneFormatted = `(${digits.substring(0, 2)}) ${digits.substring(2, 7)}-${digits.substring(7)}`;
            } else if (digits.length === 10) {
                phoneFormatted = `(${digits.substring(0, 2)}) ${digits.substring(2, 6)}-${digits.substring(6)}`;
            }
        }

        // Monta conteúdo do modal
        let content = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        content += '<tr><td style="padding: 8px; font-weight: 600; width: 150px; border-bottom: 1px solid #eee;">Data/Hora:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(sentAtFormatted) + '</td></tr>';
        content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Origem:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(sourceLabel) + '</td></tr>';
        content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Template:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(templateName) + '</td></tr>';
        if (phoneFormatted) {
            content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Telefone:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(phoneFormatted) + '</td></tr>';
        }
        content += '</table>';

        content += '<div style="margin-top: 20px;">';
        content += '<h3 style="margin: 0 0 10px 0; font-size: 16px; color: #023A8D;">Mensagem Completa:</h3>';
        // Preserva quebras de linha usando <pre> ou nl2br
        const messageEscaped = escapeHtml(messageFull);
        const messageWithBreaks = messageEscaped.replace(/\n/g, '<br>');
        content += '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #023A8D; white-space: pre-wrap; word-wrap: break-word; font-family: inherit; line-height: 1.6;">' + messageWithBreaks + '</div>';
        content += '</div>';

        document.getElementById('message-detail-content').innerHTML = content;
        document.getElementById('message-detail-modal').style.display = 'block';
    }

    function closeMessageModal() {
        document.getElementById('message-detail-modal').style.display = 'none';
    }

    // Fecha modal ao clicar fora
    document.getElementById('message-detail-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeMessageModal();
        }
    });

    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return '-';
        const date = new Date(dateTimeString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Armazena tenant_id globalmente para uso no modal
    window.currentTenantId = <?= $tenant['id'] ?? 0 ?>;

    function copyInvoiceUrlFromBtn(btn) {
        var url = btn.getAttribute('data-invoice-url');
        copyInvoiceUrl(url, btn);
    }

    function copyInvoiceUrl(url, btn) {
        if (!url) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                var originalTitle = btn.getAttribute('data-tooltip');
                btn.setAttribute('data-tooltip', 'Copiado!');
                btn.style.background = '#28a745';
                setTimeout(function() {
                    btn.setAttribute('data-tooltip', originalTitle || 'Copiar link');
                    btn.style.background = '';
                }, 2000);
            }).catch(function() {
                alert('Não foi possível copiar. Tente selecionar o link manualmente.');
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                var originalTitle = btn.getAttribute('data-tooltip');
                btn.setAttribute('data-tooltip', 'Copiado!');
                btn.style.background = '#28a745';
                setTimeout(function() {
                    btn.setAttribute('data-tooltip', originalTitle || 'Copiar link');
                    btn.style.background = '';
                }, 2000);
            } catch (e) {
                alert('Não foi possível copiar. Tente selecionar o link manualmente.');
            }
            document.body.removeChild(ta);
        }
    }
    </script>

    <!-- Scripts para modal de edição dos campos do Asaas -->
    <script>
    function openEditAsaasFieldsModal() {
        document.getElementById('edit-asaas-fields-modal').style.display = 'block';
    }

    function closeEditAsaasFieldsModal() {
        document.getElementById('edit-asaas-fields-modal').style.display = 'none';
        const messageDiv = document.getElementById('asaas-fields-message');
        messageDiv.style.display = 'none';
        messageDiv.innerHTML = '';
    }

    // Fecha modal ao clicar fora
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('edit-asaas-fields-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditAsaasFieldsModal();
                }
            });
        }

        // Formata CEP ao digitar no modal
        const cepInput = document.getElementById('modal-address_cep');
        if (cepInput) {
            cepInput.addEventListener('input', function(e) {
                let cep = e.target.value.replace(/\D/g, '');
                if (cep.length === 8) {
                    cep = cep.substring(0, 5) + '-' + cep.substring(5);
                }
                e.target.value = cep;
            });
        }
    });

    function saveAsaasFields(event) {
        event.preventDefault();
        
        const form = document.getElementById('edit-asaas-fields-form');
        const formData = new FormData(form);
        const messageDiv = document.getElementById('asaas-fields-message');
        
        // Mostra loading
        messageDiv.style.display = 'block';
        messageDiv.style.background = '#e3f2fd';
        messageDiv.style.border = '1px solid #2196f3';
        messageDiv.style.color = '#1976d2';
        messageDiv.style.padding = '10px';
        messageDiv.style.borderRadius = '4px';
        messageDiv.innerHTML = 'Salvando e sincronizando com Asaas...';
        
        fetch('<?= pixelhub_url('/tenants/update-asaas-fields') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageDiv.style.background = '#d4edda';
                messageDiv.style.border = '1px solid #c3e6cb';
                messageDiv.style.color = '#155724';
                messageDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Sucesso!</strong> ' + (data.message || 'Campos atualizados e sincronizados com o Asaas.');
                
                // Recarrega a página após 1.5 segundos
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                messageDiv.style.background = '#f8d7da';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.style.color = '#721c24';
                messageDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></span> Erro:</strong> ' + (data.message || 'Erro ao atualizar campos.');
            }
        })
        .catch(error => {
            messageDiv.style.background = '#f8d7da';
            messageDiv.style.border = '1px solid #f5c6cb';
            messageDiv.style.color = '#721c24';
            messageDiv.innerHTML = '<strong>❌ Erro:</strong> Erro ao comunicar com o servidor.';
            console.error('Erro:', error);
        });
    }

    function buscarCepModal() {
        const cepInput = document.getElementById('modal-address_cep');
        const cep = cepInput.value.replace(/\D/g, '');
        
        if (cep.length !== 8) {
            alert('CEP deve ter 8 dígitos');
            return;
        }
        
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (data.erro) {
                    alert('CEP não encontrado');
                    return;
                }
                
                // Preenche campos automaticamente
                document.querySelector('input[name="address_street"]').value = data.logradouro || '';
                document.querySelector('input[name="address_neighborhood"]').value = data.bairro || '';
                document.querySelector('input[name="address_city"]').value = data.localidade || '';
                document.querySelector('input[name="address_state"]').value = data.uf || '';
                
                // Foca no campo número
                document.querySelector('input[name="address_number"]').focus();
            })
            .catch(error => {
                alert('Erro ao buscar CEP. Tente novamente.');
                console.error('Erro:', error);
            });
    }

    function syncAsaasData() {
        const tenantId = <?= $tenant['id'] ?>;
        const btn = document.getElementById('sync-asaas-btn');
        const btnText = document.getElementById('sync-asaas-text');
        const messageDiv = document.getElementById('sync-asaas-message');
        
        // Desabilita botão e mostra loading
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        btnText.textContent = 'Sincronizando...';
        btn.setAttribute('data-tooltip', 'Sincronizando...');
        
        // Mostra mensagem de loading
        messageDiv.style.display = 'block';
        messageDiv.style.background = '#e3f2fd';
        messageDiv.style.border = '1px solid #2196f3';
        messageDiv.style.color = '#1976d2';
        messageDiv.style.padding = '12px';
        messageDiv.style.borderRadius = '4px';
        messageDiv.innerHTML = '<strong>🔄 Sincronizando...</strong> Buscando e consolidando dados de todos os cadastros do Asaas para este CPF...';
        
        fetch('<?= pixelhub_url('/tenants/sync-asaas-data') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'tenant_id=' + tenantId
        })
        .then(response => response.json())
        .then(data => {
            // Reabilita botão
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btnText.textContent = 'Sincronizar com Asaas';
            btn.setAttribute('data-tooltip', 'Sincronizar com Asaas');
            
            if (data.success) {
                messageDiv.style.background = '#d4edda';
                messageDiv.style.border = '1px solid #c3e6cb';
                messageDiv.style.color = '#155724';
                
                let message = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Sincronização concluída!</strong><br>';
                if (data.customers_found) {
                    message += `Foram encontrados <strong>${data.customers_found} cadastro(s)</strong> no Asaas para este CPF.<br>`;
                }
                if (data.fields_updated && data.fields_updated.length > 0) {
                    message += `Campos atualizados: <strong>${data.fields_updated.join(', ')}</strong>.<br>`;
                }
                if (data.customers_synced) {
                    message += `Dados sincronizados com <strong>${data.customers_synced} cadastro(s)</strong> no Asaas.`;
                } else {
                    message += data.message || 'Dados atualizados com sucesso.';
                }
                
                messageDiv.innerHTML = message;
                
                // Recarrega a página após 2 segundos para mostrar dados atualizados
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                messageDiv.style.background = '#f8d7da';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.style.color = '#721c24';
                messageDiv.innerHTML = '<strong>❌ Erro:</strong> ' + (data.message || 'Erro ao sincronizar dados.');
            }
        })
        .catch(error => {
            // Reabilita botão
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btnText.textContent = 'Sincronizar com Asaas';
            btn.setAttribute('data-tooltip', 'Sincronizar com Asaas');
            
            messageDiv.style.background = '#f8d7da';
            messageDiv.style.border = '1px solid #f5c6cb';
            messageDiv.style.color = '#721c24';
            messageDiv.innerHTML = '<strong>❌ Erro:</strong> Erro ao comunicar com o servidor.';
            console.error('Erro:', error);
        });
    }
    </script>

<?php elseif ($activeTab === 'hosting'): ?>
    <!-- ABA: Serviços Ativos -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Sites e Hospedagens</h3>
            <a href="<?= pixelhub_url('/hosting/create?tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
               style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                Nova conta de hospedagem
            </a>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
            <div style="background: #efe; color: #3c3; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                Conta de hospedagem criada com sucesso!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
            <div style="background: #efe; color: #3c3; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                Conta de hospedagem atualizada com sucesso!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] === 'email_duplicated'): ?>
            <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
                <p style="color: #3c3; margin: 0;">Conta de email duplicada com sucesso!</p>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] === 'email_created'): ?>
            <div style="background: #efe; color: #3c3; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                Conta de email criada com sucesso!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] === 'email_updated'): ?>
            <div style="background: #efe; color: #3c3; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                Conta de email atualizada com sucesso!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] === 'email_deleted'): ?>
            <div style="background: #efe; color: #3c3; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                Conta de email excluída com sucesso!
            </div>
        <?php endif; ?>
        
        <?php if (empty($hostingAccounts)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <p style="color: #666; margin-bottom: 20px;">Nenhum site cadastrado para este cliente.</p>
                <a href="<?= pixelhub_url('/hosting/create?tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
                   style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
                    Nova conta de hospedagem
                </a>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Provedor</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Valor</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Situação</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Backup</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hostingAccounts as $hosting): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <a href="<?= pixelhub_url('/hosting/edit?id=' . $hosting['id'] . '&tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
                               style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                <?= htmlspecialchars($hosting['domain']) ?>
                            </a>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?php
                            $providerSlug = $hosting['current_provider'] ?? '';
                            $providerName = $providerMap[$providerSlug] ?? $providerSlug;
                            
                            if ($providerSlug === 'nenhum_backup') {
                                echo '<span style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block;">Somente backup</span>';
                            } else {
                                echo htmlspecialchars($providerName);
                            }
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?php
                            $amount = $hosting['amount'] ?? 0;
                            if ($amount > 0) {
                                echo 'R$ ' . number_format($amount, 2, ',', '.');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?php
                            // Função helper para calcular status baseado em data de vencimento
                            $calculateStatus = function($expirationDate, $type = '') {
                                if (empty($expirationDate)) {
                                    $text = $type === 'domain' ? 'Domínio: Sem data' : ($type === 'hosting' ? 'Hospedagem: Sem data' : 'Sem data');
                                    return [
                                        'text' => $text,
                                        'style' => 'background: #e9ecef; color: #6c757d; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                                    ];
                                }
                                
                                $expDate = strtotime($expirationDate);
                                $today = strtotime('today');
                                $daysLeft = floor(($expDate - $today) / (60 * 60 * 24));
                                
                                // Formata informação de dias
                                $daysInfo = '';
                                if ($daysLeft > 0) {
                                    $daysInfo = $daysLeft == 1 ? ' (vence em 1 dia)' : ' (vence em ' . $daysLeft . ' dias)';
                                } elseif ($daysLeft == 0) {
                                    $daysInfo = ' (vence hoje)';
                                } else {
                                    $daysOverdue = abs($daysLeft);
                                    $daysInfo = $daysOverdue == 1 ? ' (vencido há 1 dia)' : ' (vencido há ' . $daysOverdue . ' dias)';
                                }
                                
                                // Determina status base e texto
                                if ($daysLeft > 30) {
                                    $statusText = $type === 'domain' ? 'Domínio: Ativo' : ($type === 'hosting' ? 'Hospedagem: Ativa' : 'Ativo');
                                    $text = $statusText . $daysInfo;
                                    return [
                                        'text' => $text,
                                        'style' => 'background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                                    ];
                                } elseif ($daysLeft >= 15 && $daysLeft <= 30) {
                                    $statusText = $type === 'domain' ? 'Domínio: Vencendo' : ($type === 'hosting' ? 'Hospedagem: Vencendo' : 'Vencendo');
                                    $text = $statusText . $daysInfo;
                                    return [
                                        'text' => $text,
                                        'style' => 'background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                                    ];
                                } elseif ($daysLeft >= 0 && $daysLeft < 15) {
                                    $statusText = $type === 'domain' ? 'Domínio: Urgente' : ($type === 'hosting' ? 'Hospedagem: Urgente' : 'Urgente');
                                    $text = $statusText . $daysInfo;
                                    return [
                                        'text' => $text,
                                        'style' => 'background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                                    ];
                                } else {
                                    $statusText = $type === 'domain' ? 'Domínio: Vencido' : ($type === 'hosting' ? 'Hospedagem: Vencida' : 'Vencido');
                                    $text = $statusText . $daysInfo;
                                    return [
                                        'text' => $text,
                                        'style' => 'background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
                                    ];
                                }
                            };
                            
                            // Calcula status da hospedagem
                            $hostingStatus = $calculateStatus($hosting['hostinger_expiration_date'] ?? null, 'hosting');
                            
                            // Calcula status do domínio
                            $domainStatus = $calculateStatus($hosting['domain_expiration_date'] ?? null, 'domain');
                            
                            // Exibe ambos os status empilhados
                            echo '<div style="display: flex; flex-direction: column; gap: 4px;">';
                            echo '<span style="' . $hostingStatus['style'] . '">' . htmlspecialchars($hostingStatus['text']) . '</span>';
                            echo '<span style="' . $domainStatus['style'] . '">' . htmlspecialchars($domainStatus['text']) . '</span>';
                            echo '</div>';
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?php
                            if ($hosting['backup_status'] === 'completo' && !empty($hosting['last_backup_at'])) {
                                $backupDate = date('d/m/Y', strtotime($hosting['last_backup_at']));
                                echo '<span style="background: #3c3; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">Backup em ' . $backupDate . '</span>';
                            } else {
                                echo '<span style="background: #c33; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">Sem backup</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <button onclick="openHostingDetailsModal(<?= $hosting['id'] ?>)" 
                                    style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: inline-block; margin-right: 5px; font-weight: 600;">
                                Ver
                            </button>
                            <a href="<?= pixelhub_url('/hosting/edit?id=' . $hosting['id'] . '&tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
                               style="background: #F7931E; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; margin-right: 5px;">
                                Editar
                            </a>
                            <a href="<?= pixelhub_url('/hosting/backups?hosting_id=' . $hosting['id']) ?>" 
                               style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                Backups
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Seção: Contas de Email -->
    <div class="card" style="margin-top: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Contas de Email</h3>
            <a href="<?= pixelhub_url('/email-accounts/create?tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
               style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                Nova Conta de Email
            </a>
        </div>
        
        <?php if (empty($emailAccounts ?? [])): ?>
            <div style="text-align: center; padding: 20px;">
                <p style="color: #666; margin-bottom: 15px;">Nenhuma conta de email cadastrada para este cliente.</p>
                <a href="<?= pixelhub_url('/email-accounts/create?tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
                   style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                    Cadastrar Primeira Conta de Email
                </a>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Email</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Usuário</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Senha</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Provedor</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio Vinculado</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emailAccounts as $account): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <strong style="color: #023A8D;"><?= htmlspecialchars($account['email']) ?></strong>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($account['username'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; gap: 5px; align-items: center;">
                                <span id="email_password_<?= $account['id'] ?>" style="font-family: monospace; color: #666;">
                                    <?= !empty($account['password_encrypted']) ? '••••••••' : '-' ?>
                                </span>
                                <?php if (!empty($account['password_encrypted'])): ?>
                                <button type="button" 
                                        onclick="toggleEmailPassword(<?= $account['id'] ?>, this)" 
                                        style="background: #666; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;"
                                        title="Mostrar/Ocultar senha">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($account['provider'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($account['hosting_domain'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <a href="<?= pixelhub_url('/email-accounts/duplicate?id=' . $account['id'] . '&redirect_to=tenant') ?>" 
                                   style="background: #28a745; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;"
                                   title="Duplicar conta de email">
                                    Duplicar
                                </a>
                                <a href="<?= pixelhub_url('/email-accounts/edit?id=' . $account['id'] . '&redirect_to=tenant') ?>" 
                                   style="background: #F7931E; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                    Editar
                                </a>
                                <form method="POST" action="<?= pixelhub_url('/email-accounts/delete') ?>" 
                                      onsubmit="return confirm('Tem certeza que deseja excluir a conta de email <?= htmlspecialchars($account['email']) ?>?');" 
                                      style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($account['id']) ?>">
                                    <input type="hidden" name="redirect_to" value="tenant">
                                    <button type="submit" 
                                            style="background: #c33; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                        Excluir
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 15px; text-align: right;">
                <a href="<?= pixelhub_url('/email-accounts?tenant_id=' . $tenant['id'] . '&redirect_to=tenant') ?>" 
                   style="color: #023A8D; text-decoration: none; font-size: 14px;">
                    Ver todas as contas de email →
                </a>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($activeTab === 'docs_backups'): ?>
    <!-- ABA: Docs & Backups -->
    
    <div id="tenant-wp-backups">
    <!-- Seção 1: Backups do Site -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 20px;">Backups do Site</h3>
        <p style="color: #666; margin-bottom: 20px;">
            Registre aqui os backups do site (WordPress ou outros tipos), informando apenas a URL do backup e, opcionalmente, o repositório de código.
        </p>
        
        <!-- Formulário de Upload -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 15px;">Enviar Novo Backup</h4>
            <div id="wp-backup-error-message" style="display: none; background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 15px;"></div>
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?php
                    $error = $_GET['error'];
                    if ($error === 'missing_backup_or_repo') echo 'Informe pelo menos um dos campos: URL do backup (Google Drive) ou Repositório GitHub.';
                    elseif ($error === 'missing_external_url') echo 'URL do backup é obrigatória. Informe o link do backup (Google Drive ou outro serviço externo).';
                    elseif ($error === 'invalid_external_url') echo 'URL inválida. Informe uma URL válida começando com http:// ou https://';
                    elseif ($error === 'external_url_too_long') echo 'URL muito longa. Máximo de 500 caracteres.';
                    elseif ($error === 'invalid_github_url') echo 'URL do GitHub inválida. Informe uma URL válida começando com http:// ou https://';
                    elseif ($error === 'github_url_too_long') echo 'URL do GitHub muito longa. Máximo de 500 caracteres.';
                    elseif ($error === 'database_error') echo 'Erro ao registrar o backup no banco de dados.';
                    elseif ($error === 'delete_missing_id') echo 'ID do backup não fornecido para exclusão.';
                    elseif ($error === 'delete_not_found') echo 'Backup não encontrado para exclusão.';
                    elseif ($error === 'delete_database_error') echo 'Erro ao excluir backup do banco de dados.';
                    else echo 'Erro desconhecido.';
                    ?>
                </div>
            <?php endif; ?>
            <form id="form-wp-backup" method="POST" action="<?= pixelhub_url('/hosting/backups/upload') ?>">
                <input type="hidden" name="redirect_to" value="tenant">
                
                <div style="margin-bottom: 15px;">
                    <label for="hosting_account_id" style="display: block; margin-bottom: 5px; font-weight: 600;">Site/Hospedagem:</label>
                    <select id="hosting_account_id" name="hosting_account_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Selecione um site...</option>
                        <?php foreach ($hostingAccounts as $hosting): ?>
                            <option value="<?= $hosting['id'] ?>"><?= htmlspecialchars($hosting['domain']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="external_url" style="display: block; margin-bottom: 5px; font-weight: 600;">URL do backup (Google Drive) <span style="color: #666; font-weight: normal;">(opcional)</span>:</label>
                    <input 
                        type="url" 
                        id="external_url" 
                        name="external_url" 
                        class="form-control" 
                        placeholder="Cole aqui o link compartilhável do backup (arquivo ou pasta no Google Drive)"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                    >
                    <small class="form-text text-muted" style="display: block; color: #666; margin-top: 5px;">
                        Use um link compartilhável do Google Drive (ou outro serviço externo) com acesso adequado para restauração. Preencha este campo ou o repositório GitHub.
                    </small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="github_repo_url" style="display: block; margin-bottom: 5px; font-weight: 600;">Repositório GitHub (opcional):</label>
                    <input 
                        type="url" 
                        id="github_repo_url" 
                        name="github_repo_url" 
                        class="form-control" 
                        placeholder="Cole aqui a URL do repositório no GitHub (ou outro controle de versão)"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                    >
                    <small class="form-text text-muted" style="display: block; color: #666; margin-top: 5px;">
                        Use este campo para registrar o repositório de código relacionado a este site/backup. Preencha este campo ou a URL do backup.
                    </small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas (opcional):</label>
                    <textarea id="notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                </div>
                
                <button type="submit" id="submit-btn" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Registrar Backup
                </button>
            </form>
            
        </div>
        
        <!-- Lista de Backups -->
        <div id="wp-backups-table-container">
            <?php require __DIR__ . '/../partials/tenant_wp_backups_table.php'; ?>
        </div>
    </div>
    </div>
    
    <!-- Seção 2: Contratos de Projetos -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 20px;">Contratos de Projetos</h3>
        
        <?php if (empty($contracts ?? [])): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <p style="font-size: 16px; margin-bottom: 10px;">Nenhum contrato encontrado para este cliente.</p>
                <p style="font-size: 14px;">Os contratos são gerados automaticamente ao criar projetos através do Assistente de Cadastramento.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Projeto</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Serviço</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd; font-weight: 600;">Valor</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600;">Status</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600;">Criado em</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                            <?php
                            $statusLabels = [
                                'draft' => ['label' => 'Rascunho', 'color' => '#6c757d', 'bg' => '#e9ecef'],
                                'sent' => ['label' => 'Enviado', 'color' => '#856404', 'bg' => '#fff3cd'],
                                'accepted' => ['label' => 'Aceito', 'color' => '#155724', 'bg' => '#d4edda'],
                                'rejected' => ['label' => 'Rejeitado', 'color' => '#721c24', 'bg' => '#f8d7da']
                            ];
                            $statusInfo = $statusLabels[$contract['status']] ?? ['label' => $contract['status'], 'color' => '#666', 'bg' => '#f0f0f0'];
                            $canEdit = $contract['status'] !== 'accepted' && $contract['status'] !== 'rejected';
                            $publicLink = \PixelHub\Services\ProjectContractService::generatePublicLink($contract['contract_token']);
                            ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;">
                                    <?php if ($contract['project_name']): ?>
                                        <a href="<?= pixelhub_url('/projects/board?project_id=' . $contract['project_id']) ?>" style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                            <?= htmlspecialchars($contract['project_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <?= htmlspecialchars($contract['service_name'] ?? '-') ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 600;">
                                    R$ <?= number_format((float) $contract['contract_value'], 2, ',', '.') ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?= $statusInfo['bg'] ?>; color: <?= $statusInfo['color'] ?>;">
                                        <?= htmlspecialchars($statusInfo['label']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: center; color: #666; font-size: 13px;">
                                    <?= date('d/m/Y H:i', strtotime($contract['created_at'])) ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center;">
                                        <a href="<?= $publicLink ?>" target="_blank" 
                                           style="padding: 6px 12px; background: #023A8D; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                            Ver
                                        </a>
                                        <a href="<?= pixelhub_url('/contracts/download-pdf?id=' . $contract['id']) ?>" 
                                           style="padding: 6px 12px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline points="14 2 14 8 20 8"/>
                                                <line x1="16" y1="13" x2="8" y2="13"/>
                                                <line x1="16" y1="17" x2="8" y2="17"/>
                                                <polyline points="10 9 9 9 8 9"/>
                                            </svg>
                                            PDF
                                        </a>
                                        <?php if ($canEdit): ?>
                                            <button onclick="editContract(<?= $contract['id'] ?>)" 
                                                    style="padding: 6px 12px; background: #ffc107; color: #333; border: none; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                                Editar
                                            </button>
                                        <?php else: ?>
                                            <span style="padding: 6px 12px; background: #e9ecef; color: #6c757d; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: not-allowed;" title="Contrato não pode ser editado após <?= $contract['status'] === 'accepted' ? 'aceito' : 'rejeitado' ?>">
                                                Bloqueado
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="tenant-documents">
    <!-- Seção 3: Documentos Gerais -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">Documentos Gerais</h3>
        
        <!-- Formulário de Upload -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 15px;">Enviar Novo Documento</h4>
            <div id="doc-error-message" style="display: none; background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 15px;"></div>
            <form id="form-tenant-document" method="POST" action="<?= pixelhub_url('/tenants/documents/upload') ?>" enctype="multipart/form-data">
                <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($tenant['id']) ?>">
                
                <div style="margin-bottom: 15px;">
                    <label for="doc_title" style="display: block; margin-bottom: 5px; font-weight: 600;">Título do documento:</label>
                    <input type="text" id="doc_title" name="title" placeholder="Título do documento" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Se deixar em branco, será usado o nome do arquivo.</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="doc_category" style="display: block; margin-bottom: 5px; font-weight: 600;">Categoria:</label>
                    <select id="doc_category" name="category" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Selecione uma categoria (opcional)</option>
                        <option value="contrato">Contratos</option>
                        <option value="assets_site">Assets do Site</option>
                        <option value="banco_dados">Banco de Dados</option>
                        <option value="midia">Mídias (imagens/vídeos)</option>
                        <option value="documentos">Documentos</option>
                        <option value="outros">Outros</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="doc_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Arquivo:</label>
                    <input type="file" id="doc_file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif,.zip,.rar,.7z,.tar,.gz,.sql,.mp4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, JPG, JPEG, PNG, WEBP, GIF, ZIP, RAR, 7Z, TAR, GZ, SQL, MP4. Tamanho máximo: 200MB</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="doc_link_url" style="display: block; margin-bottom: 5px; font-weight: 600;">URL importante (opcional):</label>
                    <input type="url" id="doc_link_url" name="link_url" placeholder="https://exemplo.com/documento" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Use este campo se quiser cadastrar apenas um link sem arquivo, ou em conjunto com o arquivo.</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="doc_notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas (opcional):</label>
                    <textarea id="doc_notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                </div>
                
                <button type="submit" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Enviar
                </button>
            </form>
        </div>
        
        <!-- Lista de Documentos -->
        <div id="documents-table-container">
            <?php require __DIR__ . '/../partials/tenant_documents_table.php'; ?>
        </div>
    </div>
    </div>
    
    <!-- Script para AJAX na aba Docs & Backups -->
    <?php if ($activeTab === 'docs_backups'): ?>
    <script src="<?= pixelhub_url('/assets/js/tenant_docs_backups.js') ?>"></script>
    <script>
    function editContract(id) {
        // Busca dados do contrato
        fetch('<?= pixelhub_url('/contracts/show') ?>?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.contract) {
                    const contract = data.contract;
                    const newValue = prompt('Digite o novo valor do contrato:', contract.contract_value);
                    
                    if (newValue === null) return;
                    
                    const value = parseFloat(newValue.replace(/\./g, '').replace(',', '.'));
                    if (isNaN(value) || value <= 0) {
                        alert('Valor inválido');
                        return;
                    }
                    
                    // Atualiza valor
                    fetch('<?= pixelhub_url('/contracts/update-value') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + id + '&contract_value=' + value.toFixed(2).replace('.', ',')
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            alert('Contrato atualizado com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro: ' + (result.error || 'Erro desconhecido'));
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao atualizar contrato');
                    });
                } else {
                    alert('Erro ao carregar contrato');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao carregar contrato');
            });
    }
    </script>
    <?php endif; ?>

<?php elseif ($activeTab === 'financial'): ?>
    <!-- ABA: Financeiro -->
    
    <!-- Barra de Sincronização -->
    <div class="card" style="margin-bottom: 20px; background: #f9f9f9;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <form method="POST" action="<?= pixelhub_url('/tenants/sync-billing') ?>" style="display: inline-block; margin: 0;">
                    <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($tenant['id']) ?>">
                    <button type="submit" 
                            style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        Sincronizar com Asaas
                    </button>
                </form>
                <div style="margin-top: 8px; font-size: 13px; color: #666;">
                    <?php
                    if (!empty($tenant['billing_last_check_at'])) {
                        echo 'Última sincronização: ' . date('d/m/Y H:i', strtotime($tenant['billing_last_check_at']));
                    } else {
                        echo '<span style="color: #999;">Nunca sincronizado</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'synced'): ?>
        <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
            <p style="color: #3c3; margin: 0;">
                <?php
                if (isset($_GET['message'])) {
                    echo htmlspecialchars(urldecode($_GET['message']));
                } else {
                    echo 'Sincronização com Asaas concluída com sucesso!';
                }
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'whatsapp_sent'): ?>
        <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
            <p style="color: #155724; margin: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Cobrança via WhatsApp marcada como enviada com sucesso!
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
            <p style="color: #c33; margin: 0;">
                <?php
                $error = $_GET['error'];
                if ($error === 'asaas_not_configured') {
                    // Usa mensagem da exception se disponível, senão usa mensagem padrão
                    if (isset($_GET['message'])) {
                        echo htmlspecialchars(urldecode($_GET['message']));
                    } else {
                        echo 'Asaas não está configurado. Verifique ASAAS_API_KEY, ASAAS_ENV e ASAAS_WEBHOOK_TOKEN no arquivo .env.';
                    }
                } elseif ($error === 'asaas_config_error') {
                    // Usa mensagem da exception se disponível
                    if (isset($_GET['message'])) {
                        echo htmlspecialchars(urldecode($_GET['message']));
                    } else {
                        echo 'Erro na configuração do Asaas. Verifique as variáveis de ambiente.';
                    }
                } elseif ($error === 'sync_failed') {
                    if (isset($_GET['message'])) {
                        echo htmlspecialchars(urldecode($_GET['message']));
                    } else {
                        echo 'Erro ao sincronizar com Asaas. Tente novamente.';
                    }
                } else {
                    echo 'Erro desconhecido.';
                }
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Resumo Financeiro -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 20px;">Resumo Financeiro</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #023A8D;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Status de Cobrança</div>
                <div style="font-size: 20px; font-weight: 600; color: #023A8D;">
                    <?php
                    $billingStatus = $tenant['billing_status'] ?? 'sem_cobranca';
                    $statusLabels = [
                        'sem_cobranca' => 'Sem cobrança ativa',
                        'em_dia' => 'Em dia',
                        'atrasado_parcial' => 'Em atraso (parcial)',
                        'atrasado_total' => 'Em atraso (total)',
                    ];
                    $statusColors = [
                        'sem_cobranca' => '#666',
                        'em_dia' => '#3c3',
                        'atrasado_parcial' => '#f93',
                        'atrasado_total' => '#c33',
                    ];
                    $label = $statusLabels[$billingStatus] ?? $billingStatus;
                    $color = $statusColors[$billingStatus] ?? '#666';
                    echo '<span style="color: ' . $color . ';">' . htmlspecialchars($label) . '</span>';
                    ?>
                </div>
            </div>
            
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #F7931E;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Última Verificação</div>
                <div style="font-size: 20px; font-weight: 600; color: #F7931E;">
                    <?php
                    if (!empty($tenant['billing_last_check_at'])) {
                        echo date('d/m/Y H:i', strtotime($tenant['billing_last_check_at']));
                    } else {
                        echo '<span style="color: #999;">Nunca atualizado</span>';
                    }
                    ?>
                </div>
            </div>
            
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #c33;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Faturas em Atraso</div>
                <div style="font-size: 20px; font-weight: 600; color: #c33;">
                    <?= $overdueCount ?? 0 ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Cadastros no Asaas para este CPF -->
    <?php if (isset($asaasCustomersByCpf) && !empty($asaasCustomersByCpf)): ?>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 20px;">Cadastros no Asaas para este CPF</h3>
            
            <?php
            $customersCount = count($asaasCustomersByCpf);
            $primaryCustomerId = $asaasPrimaryCustomerId ?? null;
            ?>
            
            <div style="margin-bottom: 15px; padding: 12px; background: #f0f7ff; border-left: 4px solid #023A8D; border-radius: 4px;">
                <?php if ($customersCount === 1): ?>
                    <p style="margin: 0; color: #023A8D; font-size: 14px;">
                        Foi encontrado <strong>1 cadastro</strong> no Asaas para este CPF.
                    </p>
                <?php else: ?>
                    <p style="margin: 0; color: #023A8D; font-size: 14px;">
                        Foram encontrados <strong><?= $customersCount ?> cadastros</strong> no Asaas para este CPF. 
                        As cobranças já estão consolidadas aqui no painel, mas ajustes (exclusão/edição) podem precisar ser feitos em mais de um cadastro no Asaas.
                    </p>
                <?php endif; ?>
            </div>
            
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">E-mail</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">CPF/CNPJ</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">ID Asaas</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asaasCustomersByCpf as $customer): ?>
                        <?php
                        $customerId = $customer['id'] ?? '';
                        $isPrimary = ($customerId === $primaryCustomerId);
                        $customerUrl = \PixelHub\Core\AsaasHelper::buildCustomerPanelUrl($customerId);
                        ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= htmlspecialchars($customer['name'] ?? '-') ?>
                                <?php if ($isPrimary): ?>
                                    <span style="background: #023A8D; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 8px;">
                                        Principal
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= htmlspecialchars($customer['email'] ?? '-') ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= htmlspecialchars($customer['cpfCnpj'] ?? '-') ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?= htmlspecialchars($customerId) ?>
                                </code>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php if (!empty($customerUrl)): ?>
                                    <a href="<?= htmlspecialchars($customerUrl) ?>" 
                                       target="_blank"
                                       style="background: #F7931E; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; font-weight: 600;">
                                        Abrir no Asaas
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 13px;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Lista de Faturas -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Faturas</h3>
            <a href="<?= pixelhub_url('/billing/collections?tenant_id=' . $tenant['id']) ?>" 
               style="color: #6c757d; text-decoration: none; font-size: 13px; font-weight: 400;">
                Ver histórico completo de cobranças →
            </a>
        </div>
        
        <?php if (empty($invoices)): ?>
            <p style="color: #666;">Nenhuma fatura encontrada.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Vencimento</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Valor</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo de Cobrança</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Descrição</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : '-' ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            R$ <?= number_format($invoice['amount'], 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?php
                            // Determina o label do status, considerando se está vencida ou não
                            $status = $invoice['status'] ?? 'pending';
                            $dueDate = $invoice['due_date'] ?? null;
                            $isOverdue = false;
                            
                            if ($status === 'overdue') {
                                $isOverdue = true;
                            } elseif ($dueDate) {
                                try {
                                    $due = new \DateTime($dueDate);
                                    $today = new \DateTime();
                                    $today->setTime(0, 0, 0);
                                    $due->setTime(0, 0, 0);
                                    if ($due < $today) {
                                        $isOverdue = true;
                                    }
                                } catch (\Exception $e) {
                                    $isOverdue = ($status === 'overdue');
                                }
                            }
                            
                            $statusLabels = [
                                'pending' => $isOverdue ? 'Vencida' : 'A vencer',
                                'paid' => 'Pago',
                                'overdue' => 'Em atraso',
                                'canceled' => 'Cancelado',
                                'refunded' => 'Reembolsado',
                            ];
                            $statusColors = [
                                'pending' => '#f93',
                                'paid' => '#3c3',
                                'overdue' => '#c33',
                                'canceled' => '#999',
                                'refunded' => '#666',
                            ];
                            $status = $invoice['status'] ?? 'pending';
                            $label = $statusLabels[$status] ?? $status;
                            $color = $statusColors[$status] ?? '#666';
                            echo '<span style="color: ' . $color . '; font-weight: 600;">' . htmlspecialchars($label) . '</span>';
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($invoice['billing_type'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($invoice['description'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                <?php if (!empty($invoice['invoice_url'])): ?>
                                    <a href="<?= htmlspecialchars($invoice['invoice_url']) ?>" 
                                       target="_blank"
                                       class="btn-action btn-action-primary"
                                       style="text-decoration: none;"
                                       data-tooltip="Ver Fatura"
                                       aria-label="Ver Fatura">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <line x1="10" y1="14" x2="21" y2="3"></line>
                                        </svg>
                                    </a>
                                    <button type="button"
                                            data-invoice-url="<?= htmlspecialchars($invoice['invoice_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="copyInvoiceUrlFromBtn(this)"
                                            class="btn-action btn-action-secondary"
                                            data-tooltip="Copiar link"
                                            aria-label="Copiar link da fatura">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                <a href="<?= pixelhub_url('/billing/whatsapp-modal?invoice_id=' . $invoice['id'] . '&redirect_to=tenant') ?>" 
                                   class="btn-action btn-action-primary"
                                   style="text-decoration: none;"
                                   data-tooltip="Cobrar"
                                   aria-label="Cobrar via WhatsApp">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($activeTab === 'notifications'): ?>
    <!-- ABA: Notificações -->
    
    <!-- Histórico WhatsApp Unificado -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Histórico de Comunicações WhatsApp</h3>
            <a href="<?= pixelhub_url('/tenants/whatsapp-history?id=' . $tenant['id']) ?>" 
               style="color: #023A8D; text-decoration: none; font-size: 14px; font-weight: 600;">
                Ver histórico completo →
            </a>
        </div>
        <div id="whatsapp-timeline-container">
            <?php if (empty($whatsappTimeline)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">Nenhum histórico de WhatsApp registrado.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data/Hora</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Origem</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Template</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Observação</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="whatsapp-timeline-tbody">
                        <?php foreach ($whatsappTimeline as $index => $item): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php
                                $sentAt = $item['sent_at'] ?? null;
                                echo $sentAt ? htmlspecialchars(date('d/m/Y H:i', strtotime($sentAt))) : '-';
                                ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php
                                $source = $item['source'] ?? 'generic';
                                $sourceLabel = $source === 'billing' ? 'Financeiro' : 'Visão Geral';
                                echo htmlspecialchars($sourceLabel);
                                ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php
                                $templateId = $item['template_id'] ?? null;
                                $templateName = $item['template_name'] ?? null;
                                
                                if ($templateId === null && $source === 'generic') {
                                    echo '<span style="color: #999;">Sem template / mensagem livre</span>';
                                } elseif ($templateName) {
                                    echo htmlspecialchars($templateName);
                                } else {
                                    echo '<span style="color: #999;">-</span>';
                                }
                                ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php
                                $message = $item['message'] ?? $item['message_full'] ?? '';
                                $messageFull = $item['message_full'] ?? $message;
                                
                                // Preview de 100-120 caracteres
                                $preview = mb_substr($message, 0, 120);
                                if (mb_strlen($message) > 120) {
                                    $preview .= '...';
                                }
                                
                                if ($source === 'billing') {
                                    // Para billing, mostra descrição + preview da mensagem se houver
                                    $description = $item['description'] ?? '';
                                    echo htmlspecialchars($description);
                                    if (!empty($message)) {
                                        echo '<br><span style="color: #666; font-size: 12px;">' . htmlspecialchars($preview) . '</span>';
                                    }
                                } else {
                                    // Para generic, mostra preview da mensagem
                                    echo '<span style="color: #666; font-size: 13px;">' . htmlspecialchars($preview) . '</span>';
                                }
                                ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php if (!empty($messageFull)): ?>
                                    <button onclick="openMessageModal(<?= $index ?>)" 
                                            style="background: #023A8D; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                        Ver mensagem
                                    </button>
                                    <!-- Dados da mensagem completa (ocultos) -->
                                    <div id="message-data-<?= $index ?>" style="display: none;" 
                                         data-sent-at="<?= htmlspecialchars($item['sent_at'] ?? '', ENT_QUOTES) ?>"
                                         data-source="<?= htmlspecialchars($source, ENT_QUOTES) ?>"
                                         data-source-label="<?= htmlspecialchars($sourceLabel, ENT_QUOTES) ?>"
                                         data-template-name="<?= htmlspecialchars($templateName ?? ($templateId === null && $source === 'generic' ? 'Sem template / mensagem livre' : 'Sem template'), ENT_QUOTES) ?>"
                                         data-phone="<?= htmlspecialchars($item['phone'] ?? '', ENT_QUOTES) ?>"
                                         data-message-full="<?= htmlspecialchars($messageFull, ENT_QUOTES) ?>">
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 13px;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Últimas Cobranças por WhatsApp -->
    <div class="card" style="margin-top: 20px;">
        <h3 style="margin-bottom: 20px;">Últimas Cobranças por WhatsApp</h3>
        
        <?php if (empty($whatsappNotifications)): ?>
            <p style="color: #666; text-align: center; padding: 20px;">
                Nenhuma cobrança via WhatsApp registrada ainda.
            </p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data/Hora</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Template</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($whatsappNotifications as $notification): ?>
                        <?php
                        $templateLabels = [
                            'pre_due' => 'Pré-vencimento',
                            'overdue_3d' => 'Cobrança 1 (+3d)',
                            'overdue_7d' => 'Cobrança 2 (+7d)',
                            'bulk_reminder' => 'Lembrete em Massa',
                        ];
                        $templateLabel = $templateLabels[$notification['template']] ?? $notification['template'];
                        
                        $statusLabels = [
                            'prepared' => 'Preparada',
                            'sent_manual' => 'Enviada',
                            'opened' => 'Aberta',
                            'skipped' => 'Ignorada',
                            'failed' => 'Falhou',
                        ];
                        $statusLabel = $statusLabels[$notification['status']] ?? $notification['status'];
                        $statusColor = $notification['status'] === 'sent_manual' ? '#3c3' : ($notification['status'] === 'failed' ? '#c33' : '#666');
                        
                        $sentAt = $notification['sent_at'] ?? $notification['created_at'];
                        $sentAtFormatted = 'N/A';
                        if ($sentAt) {
                            try {
                                $date = new DateTime($sentAt);
                                $sentAtFormatted = $date->format('d/m/Y H:i');
                            } catch (Exception $e) {}
                        }
                        ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= $sentAtFormatted ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= htmlspecialchars($templateLabel) ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <span style="color: <?= $statusColor ?>; font-weight: 600;">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php if ($notification['invoice_id']): ?>
                                    <a href="<?= pixelhub_url('/billing/whatsapp-modal?invoice_id=' . $notification['invoice_id']) ?>" 
                                       style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        Cobrar Novamente
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Modal de Detalhes da Mensagem -->
    <div id="message-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div style="max-width: 700px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
            <button onclick="closeMessageModal()" 
                    style="position: absolute; top: 15px; right: 15px; background: #c33; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; line-height: 1;">×</button>
            
            <h2 style="margin: 0 0 20px 0; color: #023A8D;">Detalhes da Mensagem</h2>
            
            <div id="message-detail-content" style="color: #666;">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
    </div>

    <script>
    // Função para atualizar timeline via AJAX
    function updateWhatsAppTimeline(tenantId) {
        if (!tenantId) {
            console.error('tenantId não fornecido para atualizar timeline');
            return;
        }

        fetch('<?= pixelhub_url('/tenants/whatsapp-timeline-ajax') ?>?tenant_id=' + tenantId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualiza timeline
                    updateTimelineTable(data.timeline);
                } else {
                    console.error('Erro ao atualizar timeline:', data.message);
                }
            })
            .catch(err => {
                console.error('Erro ao buscar timeline:', err);
            });
    }

    function updateTimelineTable(timeline) {
        const container = document.getElementById('whatsapp-timeline-container');
        if (!container) return;

        if (!timeline || timeline.length === 0) {
            container.innerHTML = '<p style="color: #666; text-align: center; padding: 20px;">Nenhum histórico de WhatsApp registrado.</p>';
            return;
        }

        let html = '<table style="width: 100%; border-collapse: collapse;">';
        html += '<thead>';
        html += '<tr style="background: #f5f5f5;">';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data/Hora</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Origem</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Template</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Observação</th>';
        html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody id="whatsapp-timeline-tbody">';

        timeline.forEach((item, index) => {
            const sentAt = item.sent_at || null;
            const sentAtFormatted = sentAt ? formatDateTime(sentAt) : '-';
            const source = item.source || 'generic';
            const sourceLabel = source === 'billing' ? 'Financeiro' : 'Visão Geral';
            const templateId = item.template_id || null;
            const templateName = item.template_name || null;

            let templateHtml = '<span style="color: #999;">-</span>';
            if (templateId === null && source === 'generic') {
                templateHtml = '<span style="color: #999;">Sem template / mensagem livre</span>';
            } else if (templateName) {
                templateHtml = escapeHtml(templateName);
            }

            const message = item.message || item.message_full || '';
            const messageFull = item.message_full || message;
            const preview = message.length > 120 ? message.substring(0, 120) + '...' : message;

            let observationHtml = '';
            if (source === 'billing') {
                observationHtml = escapeHtml(item.description || '');
                if (message) {
                    observationHtml += '<br><span style="color: #666; font-size: 12px;">' + escapeHtml(preview) + '</span>';
                }
            } else {
                observationHtml = `<span style="color: #666; font-size: 13px;">${escapeHtml(preview)}</span>`;
            }

            const actionsHtml = messageFull ? 
                `<button onclick="openMessageModal(${index})" style="background: #023A8D; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">Ver mensagem</button>
                 <div id="message-data-${index}" style="display: none;" 
                      data-sent-at="${escapeHtml(sentAt || '')}"
                      data-source="${escapeHtml(source)}"
                      data-source-label="${escapeHtml(sourceLabel)}"
                      data-template-name="${escapeHtml(templateName || (templateId === null && source === 'generic' ? 'Sem template / mensagem livre' : 'Sem template'))}"
                      data-phone="${escapeHtml(item.phone || '')}"
                      data-message-full="${escapeHtml(messageFull)}"></div>` :
                '<span style="color: #999; font-size: 13px;">-</span>';

            html += '<tr>';
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${escapeHtml(sentAtFormatted)}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${escapeHtml(sourceLabel)}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${templateHtml}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${observationHtml}</td>`;
            html += `<td style="padding: 12px; border-bottom: 1px solid #eee;">${actionsHtml}</td>`;
            html += '</tr>';
        });

        html += '</tbody>';
        html += '</table>';

        container.innerHTML = html;
    }

    // Funções do modal de detalhes da mensagem
    function openMessageModal(index) {
        const dataDiv = document.getElementById('message-data-' + index);
        if (!dataDiv) {
            console.error('Dados da mensagem não encontrados para índice:', index);
            return;
        }

        const sentAt = dataDiv.getAttribute('data-sent-at') || '';
        const sourceLabel = dataDiv.getAttribute('data-source-label') || '';
        const templateName = dataDiv.getAttribute('data-template-name') || 'Sem template';
        const phone = dataDiv.getAttribute('data-phone') || '';
        const messageFull = dataDiv.getAttribute('data-message-full') || '';

        // Formata data/hora
        let sentAtFormatted = '-';
        if (sentAt) {
            try {
                const date = new Date(sentAt);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                sentAtFormatted = `${day}/${month}/${year} ${hours}:${minutes}`;
            } catch (e) {
                sentAtFormatted = sentAt;
            }
        }

        // Formata telefone (se houver)
        let phoneFormatted = phone;
        if (phone && phone.length >= 10) {
            // Formata como (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
            const digits = phone.replace(/\D/g, '');
            if (digits.length === 11) {
                phoneFormatted = `(${digits.substring(0, 2)}) ${digits.substring(2, 7)}-${digits.substring(7)}`;
            } else if (digits.length === 10) {
                phoneFormatted = `(${digits.substring(0, 2)}) ${digits.substring(2, 6)}-${digits.substring(6)}`;
            }
        }

        // Monta conteúdo do modal
        let content = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        content += '<tr><td style="padding: 8px; font-weight: 600; width: 150px; border-bottom: 1px solid #eee;">Data/Hora:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(sentAtFormatted) + '</td></tr>';
        content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Origem:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(sourceLabel) + '</td></tr>';
        content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Template:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(templateName) + '</td></tr>';
        if (phoneFormatted) {
            content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Telefone:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(phoneFormatted) + '</td></tr>';
        }
        content += '</table>';

        content += '<div style="margin-top: 20px;">';
        content += '<h3 style="margin: 0 0 10px 0; font-size: 16px; color: #023A8D;">Mensagem Completa:</h3>';
        // Preserva quebras de linha usando <pre> ou nl2br
        const messageEscaped = escapeHtml(messageFull);
        const messageWithBreaks = messageEscaped.replace(/\n/g, '<br>');
        content += '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #023A8D; white-space: pre-wrap; word-wrap: break-word; font-family: inherit; line-height: 1.6;">' + messageWithBreaks + '</div>';
        content += '</div>';

        document.getElementById('message-detail-content').innerHTML = content;
        document.getElementById('message-detail-modal').style.display = 'block';
    }

    function closeMessageModal() {
        document.getElementById('message-detail-modal').style.display = 'none';
    }

    // Fecha modal ao clicar fora
    document.getElementById('message-detail-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeMessageModal();
        }
    });

    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return '-';
        const date = new Date(dateTimeString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Armazena tenant_id globalmente para uso no modal
    window.currentTenantId = <?= $tenant['id'] ?? 0 ?>;
    </script>

<?php elseif ($activeTab === 'tasks'): ?>
    <!-- ABA: Tarefas & Projetos -->
    
    <?php
    // Busca projetos do cliente
    $clientProjects = $clientProjects ?? [];
    ?>
    
    <!-- Seção: Projetos do Cliente -->
    <div class="card" style="margin-bottom: 30px; border-left: 4px solid #023A8D;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h3 style="margin: 0; color: #023A8D; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                    <span>Projetos do Cliente</span>
                    <span style="background: #023A8D; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                        <?= count($clientProjects) ?>
                    </span>
                </h3>
                <p style="margin: 5px 0 0 0; color: #999; font-size: 13px;">Projetos ativos vinculados a este cliente</p>
            </div>
            <a href="<?= pixelhub_url('/projects?type=cliente&tenant_id=' . $tenant['id']) ?>" 
               class="btn-action btn-action-primary"
               data-tooltip="Ver Todos os Projetos"
               aria-label="Ver Todos os Projetos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </a>
        </div>
        
        <?php if (empty($clientProjects)): ?>
            <p style="color: #666; text-align: center; padding: 40px 20px;">
                Nenhum projeto cadastrado para este cliente até o momento.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Projeto</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Serviço</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Prioridade</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Prazo</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Status</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600; width: 150px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientProjects as $project): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                                <?php if (!empty($project['base_url'])): ?>
                                    <br><small style="color: #666;"><a href="<?= htmlspecialchars($project['base_url']) ?>" target="_blank" style="color: #023A8D;"><?= htmlspecialchars($project['base_url']) ?></a></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= !empty($project['service_name']) ? htmlspecialchars($project['service_name']) : '<span style="color: #999;">—</span>' ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php
                                $priorityLabels = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
                                $priority = $project['priority'] ?? 'media';
                                $label = $priorityLabels[$priority] ?? 'Média';
                                $priorityColors = [
                                    'baixa' => 'background: #e8f5e9; color: #2e7d32;',
                                    'media' => 'background: #fff3e0; color: #e65100;',
                                    'alta' => 'background: #ffebee; color: #c62828;',
                                    'critica' => 'background: #fce4ec; color: #880e4f;'
                                ];
                                $priorityStyle = $priorityColors[$priority] ?? $priorityColors['media'];
                                ?>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; <?= $priorityStyle ?>"><?= $label ?></span>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php if (!empty($project['due_date'])): ?>
                                    <?= date('d/m/Y', strtotime($project['due_date'])) ?>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php
                                $statusColor = $project['status'] === 'ativo' ? '#3c3' : '#666';
                                $statusLabel = $project['status'] === 'ativo' ? 'Ativo' : 'Arquivado';
                                ?>
                                <span style="color: <?= $statusColor ?>; font-weight: 600;"><?= $statusLabel ?></span>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                                <a href="<?= pixelhub_url('/projects/board?tenant_id=' . $tenant['id'] . '&project_id=' . $project['id']) ?>" 
                                   class="btn-action btn-action-primary"
                                   data-tooltip="Ver Projeto"
                                   aria-label="Ver Projeto">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Seção: Tarefas do Cliente -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Resumo de Tarefas do Cliente</h3>
            <a href="<?= pixelhub_url('/projects/board?tenant_id=' . $tenant['id']) ?>" 
               class="btn-action btn-action-primary"
               data-tooltip="Ver no Quadro Kanban"
               aria-label="Ver no Quadro Kanban">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </a>
        </div>
        
        <?php
        // Gera contadores por status usando o array $tasks recebido do controller
        $counts = [
            'backlog' => 0,
            'em_andamento' => 0,
            'aguardando_cliente' => 0,
            'concluida' => 0,
        ];
        
        $tasks = $tasks ?? [];
        foreach ($tasks as $task) {
            $statusKey = $task['status'] ?? '';
            if (isset($counts[$statusKey])) {
                $counts[$statusKey]++;
            }
        }
        ?>
        
        <!-- Cards de Resumo -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #023A8D;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Total</div>
                <div style="font-size: 24px; font-weight: 600; color: #023A8D;">
                    <?= count($tasks) ?>
                </div>
            </div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #6c757d;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Backlog</div>
                <div style="font-size: 24px; font-weight: 600; color: #6c757d;">
                    <?= $counts['backlog'] ?>
                </div>
            </div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #F7931E;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Em Andamento</div>
                <div style="font-size: 24px; font-weight: 600; color: #F7931E;">
                    <?= $counts['em_andamento'] ?>
                </div>
            </div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Aguardando Cliente</div>
                <div style="font-size: 24px; font-weight: 600; color: #ffc107;">
                    <?= $counts['aguardando_cliente'] ?>
                </div>
            </div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Concluída</div>
                <div style="font-size: 24px; font-weight: 600; color: #28a745;">
                    <?= $counts['concluida'] ?>
                </div>
            </div>
        </div>
        
        <?php if (empty($tasks)): ?>
            <p style="color: #666; text-align: center; padding: 40px 20px;">
                Nenhuma tarefa vinculada a este cliente até o momento.
            </p>
        <?php else: ?>
            <?php
            // Agrupa tarefas por status para exibir em blocos
            $tasksByStatus = [];
            foreach ($tasks as $task) {
                $statusKey = $task['status'] ?? 'outros';
                $tasksByStatus[$statusKey][] = $task;
            }
            
            // Função auxiliar para label legível
            function renderStatusLabel($statusKey) {
                switch ($statusKey) {
                    case 'backlog': return 'Backlog';
                    case 'em_andamento': return 'Em Andamento';
                    case 'aguardando_cliente': return 'Aguardando Cliente';
                    case 'concluida': return 'Concluída';
                    default: return ucfirst(str_replace('_', ' ', $statusKey));
                }
            }
            
            // Função auxiliar para formatar data (evita bug de timezone)
            function formatTaskDate($dateStr) {
                if (empty($dateStr)) {
                    return '—';
                }
                // Se vier como Y-m-d, converte diretamente
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $matches)) {
                    return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
                }
                // Fallback
                try {
                    $date = new \DateTime($dateStr);
                    return $date->format('d/m/Y');
                } catch (\Exception $e) {
                    return $dateStr;
                }
            }
            ?>
            
            <?php 
            // Ordem de exibição dos status
            $statusOrder = ['backlog', 'em_andamento', 'aguardando_cliente', 'concluida'];
            foreach ($statusOrder as $statusKey): 
                if (!isset($tasksByStatus[$statusKey]) || empty($tasksByStatus[$statusKey])) {
                    continue;
                }
                $statusTasks = $tasksByStatus[$statusKey];
            ?>
                <h4 style="margin-top: 30px; margin-bottom: 15px; color: #023A8D;">
                    <?= renderStatusLabel($statusKey) ?>
                    <span style="background: #e9ecef; color: #6c757d; padding: 4px 10px; border-radius: 12px; font-size: 14px; font-weight: 600; margin-left: 10px;">
                        <?= count($statusTasks) ?>
                    </span>
                </h4>
                
                <div style="overflow-x: auto; margin-bottom: 30px;">
                    <table style="width: 100%; border-collapse: collapse; background: white;">
                        <thead>
                            <tr style="background: #f5f5f5;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Tarefa</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Projeto</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Responsável</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Prazo</th>
                                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600; width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($statusTasks as $task): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <strong><?= htmlspecialchars($task['title'] ?? '—') ?></strong>
                                    <?php if (!empty($task['description'])): ?>
                                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                            <?= htmlspecialchars(substr($task['description'], 0, 100)) ?><?= strlen($task['description']) > 100 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <?= htmlspecialchars($task['project_name'] ?? '—') ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <?= htmlspecialchars($task['assignee'] ?? '—') ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <?php
                                    $dueDateFormatted = formatTaskDate($task['due_date'] ?? null);
                                    $isOverdue = false;
                                    if (!empty($task['due_date'])) {
                                        $dueDateStr = $task['due_date'];
                                        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dueDateStr)) {
                                            $dueDate = strtotime($dueDateStr);
                                            $today = strtotime('today');
                                            $isOverdue = $dueDate < $today && $task['status'] !== 'concluida';
                                        }
                                    }
                                    ?>
                                    <span style="<?= $isOverdue ? 'color: #c33; font-weight: 600;' : '' ?>">
                                        <?= $dueDateFormatted ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                                    <a href="<?= pixelhub_url('/projects/board?tenant_id=' . $tenant['id'] . '&project_id=' . ($task['project_id'] ?? '')) ?>" 
                                       class="btn-action btn-action-primary"
                                       data-tooltip="Ver no Kanban"
                                       aria-label="Ver no Kanban">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="14" width="7" height="7"></rect>
                                            <rect x="3" y="14" width="7" height="7"></rect>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <?php
            // Exibe tarefas com status desconhecido (se houver)
            foreach ($tasksByStatus as $statusKey => $statusTasks) {
                if (!in_array($statusKey, $statusOrder)) {
                    ?>
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #023A8D;">
                        <?= renderStatusLabel($statusKey) ?>
                        <span style="background: #e9ecef; color: #6c757d; padding: 4px 10px; border-radius: 12px; font-size: 14px; font-weight: 600; margin-left: 10px;">
                            <?= count($statusTasks) ?>
                        </span>
                    </h4>
                    
                    <div style="overflow-x: auto; margin-bottom: 30px;">
                        <table style="width: 100%; border-collapse: collapse; background: white;">
                            <thead>
                                <tr style="background: #f5f5f5;">
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Tarefa</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Projeto</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Responsável</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Prazo</th>
                                    <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600; width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($statusTasks as $task): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <strong><?= htmlspecialchars($task['title'] ?? '—') ?></strong>
                                        <?php if (!empty($task['description'])): ?>
                                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                                <?= htmlspecialchars(substr($task['description'], 0, 100)) ?><?= strlen($task['description']) > 100 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <?= htmlspecialchars($task['project_name'] ?? '—') ?>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <?= htmlspecialchars($task['assignee'] ?? '—') ?>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <?= formatTaskDate($task['due_date'] ?? null) ?>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                                        <a href="<?= pixelhub_url('/projects/board?tenant_id=' . $tenant['id'] . '&project_id=' . ($task['project_id'] ?? '')) ?>" 
                                           style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; font-weight: 600;">
                                            Ver no Kanban
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                }
            }
            ?>
        <?php endif; ?>
    </div>
    
    <!-- Seção de Tickets -->
    <?php
    $tickets = $tickets ?? [];
    ?>
    <div class="card" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Tickets do Cliente</h3>
            <a href="<?= pixelhub_url('/tickets/create?tenant_id=' . $tenant['id']) ?>" 
               class="btn-action btn-action-primary"
               data-tooltip="Criar Novo Ticket"
               aria-label="Criar Novo Ticket">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </a>
        </div>
        
        <?php if (empty($tickets)): ?>
            <p style="color: #666; text-align: center; padding: 40px 20px;">
                Nenhum ticket registrado para este cliente até o momento.
            </p>
        <?php else: ?>
            <?php
            // Função auxiliar para label de status legível
            function renderTicketStatusLabel($status) {
                $labels = [
                    'aberto' => 'Aberto',
                    'em_atendimento' => 'Em Atendimento',
                    'aguardando_cliente' => 'Aguardando Cliente',
                    'resolvido' => 'Resolvido',
                    'cancelado' => 'Cancelado',
                ];
                return $labels[$status] ?? ucfirst($status);
            }
            
            // Função auxiliar para cor de status
            function getTicketStatusColor($status) {
                $colors = [
                    'aberto' => '#1976d2',
                    'em_atendimento' => '#f57c00',
                    'aguardando_cliente' => '#c2185b',
                    'resolvido' => '#388e3c',
                    'cancelado' => '#757575',
                ];
                return $colors[$status] ?? '#666';
            }
            
            // Função auxiliar para cor de prioridade
            function getTicketPrioridadeColor($prioridade) {
                $colors = [
                    'baixa' => '#1976d2',
                    'media' => '#f57c00',
                    'alta' => '#d32f2f',
                    'critica' => '#7b1fa2',
                ];
                return $colors[$prioridade] ?? '#666';
            }
            ?>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Ticket</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Status</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Prioridade</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Criado em</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-weight: 600; width: 150px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <strong>#<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['titulo'] ?? '—') ?></strong>
                                    <?php if (!empty($ticket['descricao'])): ?>
                                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                            <?= htmlspecialchars(substr($ticket['descricao'], 0, 100)) ?><?= strlen($ticket['descricao']) > 100 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['project_name'])): ?>
                                        <div style="font-size: 11px; color: #999; margin-top: 4px;">
                                            Projeto: <?= htmlspecialchars($ticket['project_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <span style="background: <?= getTicketStatusColor($ticket['status']) ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        <?= renderTicketStatusLabel($ticket['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <span style="background: <?= getTicketPrioridadeColor($ticket['prioridade']) ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        <?= ucfirst($ticket['prioridade']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                    <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                                    <a href="<?= pixelhub_url('/tickets/show?id=' . $ticket['id']) ?>" 
                                       class="btn-action btn-action-primary"
                                       data-tooltip="Ver Detalhes"
                                       aria-label="Ver Detalhes">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<!-- Modal de Detalhes de Hospedagem -->
<div id="hostingDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="background: white; margin: 50px auto; max-width: 800px; border-radius: 8px; padding: 30px; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <button onclick="closeHostingDetailsModal()" style="position: absolute; top: 15px; right: 15px; background: #c33; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; line-height: 1;">×</button>
        
        <h2 id="modalTitle" style="margin: 0 0 20px 0; color: #023A8D;">Carregando...</h2>
        
        <div id="modalContent" style="color: #666;">
            <p>Carregando informações...</p>
        </div>
    </div>
</div>

<script>
function openHostingDetailsModal(hostingId) {
    var modal = document.getElementById('hostingDetailsModal');
    var modalTitle = document.getElementById('modalTitle');
    var modalContent = document.getElementById('modalContent');
    
    modal.style.display = 'block';
    modalTitle.textContent = 'Carregando...';
    modalContent.innerHTML = '<p>Carregando informações...</p>';
    
    // Faz requisição AJAX
    fetch('<?= pixelhub_url('/hosting/view?id=') ?>' + hostingId)
        .then(response => {
            // Tenta ler o texto primeiro para verificar se há conteúdo
            return response.text().then(text => {
                // Se a resposta está vazia, lança erro
                if (!text || text.trim() === '') {
                    throw new Error('Resposta vazia do servidor (status ' + response.status + ')');
                }
                
                // Tenta parsear como JSON
                try {
                    const data = JSON.parse(text);
                    // Se não é OK e tem erro, lança o erro
                    if (!response.ok && data.error) {
                        throw new Error(data.error);
                    }
                    // Se não é OK mas não tem erro, lança erro genérico
                    if (!response.ok) {
                        throw new Error('Erro ao carregar dados (status ' + response.status + ')');
                    }
                    return data;
                } catch (parseError) {
                    // Se não conseguiu parsear JSON, lança erro
                    if (parseError instanceof Error && parseError.message.includes('JSON')) {
                        throw new Error('Resposta inválida do servidor. Erro: ' + parseError.message);
                    }
                    throw parseError;
                }
            });
        })
        .then(data => {
            // Trata novo formato com success/error ou formato antigo
            if (data.success === false || data.error) {
                modalContent.innerHTML = '<p style="color: #c33;">Erro: ' + escapeHtml(data.error || 'Erro desconhecido') + '</p>';
                return;
            }
            
            // Suporta novo formato (data.hosting) e formato antigo (data direto)
            const hosting = data.hosting || data;
            const providerName = data.provider_name || data.provider || 'N/A';
            const hostingStatus = data.status_hospedagem || data.hosting_status;
            const domainStatus = data.status_dominio || data.domain_status;
            
            // Debug: log dos dados recebidos (remover após correção)
            console.log('Dados recebidos:', data);
            console.log('Hosting object:', hosting);
            console.log('hostinger_expiration_date:', hosting.hostinger_expiration_date || data.hostinger_expiration_date);
            console.log('domain_expiration_date:', hosting.domain_expiration_date || data.domain_expiration_date);
            
            // Atualiza título
            modalTitle.textContent = escapeHtml((hosting.domain || data.domain || '').toUpperCase()) + ' — ' + escapeHtml(providerName);
            
            // Monta conteúdo do modal
            var html = '<div style="margin-bottom: 25px;">';
            html += '<h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D; border-bottom: 2px solid #023A8D; padding-bottom: 8px;">Resumo</h3>';
            html += '<table style="width: 100%; border-collapse: collapse;">';
            html += '<tr><td style="padding: 8px; font-weight: 600; width: 150px;">Plano / Valor:</td><td style="padding: 8px;">' + escapeHtml(hosting.plan_name || data.plan_name || '-') + ' / ' + escapeHtml(hosting.amount || data.amount || '-') + '</td></tr>';
            // Verifica se é "nenhum_backup" para mostrar badge
            var currentProvider = data.current_provider || (hosting && hosting.current_provider) || '';
            var providerDisplay = providerName;
            if (currentProvider === 'nenhum_backup' || providerName === 'Nenhum (Somente backup externo)') {
                providerDisplay = '<span style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block;">Somente backup</span>';
            } else {
                providerDisplay = escapeHtml(providerName);
            }
            html += '<tr><td style="padding: 8px; font-weight: 600;">Provedor:</td><td style="padding: 8px;">' + providerDisplay + '</td></tr>';
            
            // Extrai datas corretamente (tenta todas as possibilidades)
            const hostingExpDate = hosting.hostinger_expiration_date || data.hostinger_expiration_date || hosting.hostinger_expiration_date;
            const domainExpDate = hosting.domain_expiration_date || data.domain_expiration_date || hosting.domain_expiration_date;
            
            html += '<tr><td style="padding: 8px; font-weight: 600;">Venc. Hospedagem:</td><td style="padding: 8px;">' + (hostingExpDate ? formatDate(hostingExpDate) : '-') + '</td></tr>';
            html += '<tr><td style="padding: 8px; font-weight: 600;">Venc. Domínio:</td><td style="padding: 8px;">' + (domainExpDate ? formatDate(domainExpDate) : '-') + '</td></tr>';
            html += '<tr><td style="padding: 8px; font-weight: 600;">Situação:</td><td style="padding: 8px;">';
            html += '<div style="display: flex; flex-direction: column; gap: 4px;">';
            if (hostingStatus && hostingStatus.style && hostingStatus.text) {
                html += '<span style="' + hostingStatus.style + '">' + escapeHtml(hostingStatus.text) + '</span>';
            }
            if (domainStatus && domainStatus.style && domainStatus.text) {
                html += '<span style="' + domainStatus.style + '">' + escapeHtml(domainStatus.text) + '</span>';
            }
            html += '</div>';
            html += '</td></tr>';
            html += '</table>';
            html += '</div>';
            
            // Credenciais de Acesso
            html += '<div style="margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 4px; border-left: 4px solid #023A8D;">';
            html += '<h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Credenciais de Acesso</h3>';
            
            // Painel de Hospedagem
            html += '<div style="margin-bottom: 20px;">';
            html += '<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">Painel de Hospedagem</h4>';
            const hostingPanelUrl = hosting.hosting_panel_url || data.hosting_panel_url;
            const hostingPanelUsername = hosting.hosting_panel_username || data.hosting_panel_username;
            const hostingPanelPassword = hosting.hosting_panel_password || data.hosting_panel_password;
            
            if (hostingPanelUrl) {
                html += '<p style="margin: 5px 0;"><strong>URL:</strong> <a href="' + escapeHtml(hostingPanelUrl) + '" target="_blank" style="color: #023A8D;">' + escapeHtml(hostingPanelUrl) + '</a></p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>URL:</strong> Não informado</p>';
            }
            if (hostingPanelUsername) {
                html += '<p style="margin: 5px 0;"><strong>Usuário:</strong> ' + escapeHtml(hostingPanelUsername) + '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Usuário:</strong> Não informado</p>';
            }
            if (hostingPanelPassword) {
                html += '<p style="margin: 5px 0;"><strong>Senha:</strong> ';
                html += '<input type="password" id="hosting_panel_password_display" value="' + escapeHtml(hostingPanelPassword) + '" readonly style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; width: 200px;"> ';
                html += '<button type="button" onclick="togglePasswordDisplay(\'hosting_panel_password_display\', this)" style="background: #666; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>';
                html += '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Senha:</strong> Não informado</p>';
            }
            html += '</div>';
            
            // Admin do Site
            html += '<div style="margin-bottom: 0;">';
            html += '<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">Admin do Site</h4>';
            const siteAdminUrl = hosting.site_admin_url || data.site_admin_url;
            const siteAdminUsername = hosting.site_admin_username || data.site_admin_username;
            const siteAdminPassword = hosting.site_admin_password || data.site_admin_password;
            
            if (siteAdminUrl) {
                html += '<p style="margin: 5px 0;"><strong>URL:</strong> <a href="' + escapeHtml(siteAdminUrl) + '" target="_blank" style="color: #023A8D;">' + escapeHtml(siteAdminUrl) + '</a></p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>URL:</strong> Não informado</p>';
            }
            if (siteAdminUsername) {
                html += '<p style="margin: 5px 0;"><strong>Usuário:</strong> ' + escapeHtml(siteAdminUsername) + '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Usuário:</strong> Não informado</p>';
            }
            if (siteAdminPassword) {
                html += '<p style="margin: 5px 0;"><strong>Senha:</strong> ';
                html += '<input type="password" id="site_admin_password_display" value="' + escapeHtml(siteAdminPassword) + '" readonly style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; width: 200px;"> ';
                html += '<button type="button" onclick="togglePasswordDisplay(\'site_admin_password_display\', this)" style="background: #666; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>';
                html += '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Senha:</strong> Não informado</p>';
            }
            html += '</div>';
            html += '</div>';
            
            // Ações Rápidas
            html += '<div style="margin-bottom: 0; padding: 15px; background: #f0f7ff; border-radius: 4px;">';
            html += '<h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Ações Rápidas</h3>';
            html += '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            const finalHostingPanelUrl = hosting.hosting_panel_url || data.hosting_panel_url;
            const finalSiteAdminUrl = hosting.site_admin_url || data.site_admin_url;
            
            if (finalHostingPanelUrl) {
                html += '<a href="' + escapeHtml(finalHostingPanelUrl) + '" target="_blank" style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">Abrir Painel de Hospedagem</a>';
            }
            if (finalSiteAdminUrl) {
                html += '<a href="' + escapeHtml(finalSiteAdminUrl) + '" target="_blank" style="background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">Abrir Admin do Site</a>';
            }
            if (!finalHostingPanelUrl && !finalSiteAdminUrl) {
                html += '<p style="color: #999; margin: 0;">Nenhuma ação rápida disponível (URLs não configuradas)</p>';
            }
            html += '</div>';
            html += '</div>';
            
            modalContent.innerHTML = html;
        })
        .catch(error => {
            modalContent.innerHTML = '<p style="color: #c33;">Erro ao carregar dados: ' + escapeHtml(error.message) + '</p>';
        });
}

function closeHostingDetailsModal() {
    document.getElementById('hostingDetailsModal').style.display = 'none';
}

function togglePasswordDisplay(inputId, button) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
    } else {
        input.type = 'password';
        button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }
}

// Armazena senhas descriptografadas em memória (não persiste após recarregar)
var emailPasswordsCache = {};
var pendingEmailPasswordId = null;

function toggleEmailPassword(accountId, button) {
    var passwordSpan = document.getElementById('email_password_' + accountId);
    var isVisible = passwordSpan.dataset.visible === 'true';
    
    if (isVisible) {
        // Ocultar senha
        passwordSpan.textContent = '••••••••';
        passwordSpan.dataset.visible = 'false';
        passwordSpan.style.color = '#666';
        passwordSpan.style.fontWeight = 'normal';
        button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    } else {
        // Mostrar senha
        if (emailPasswordsCache[accountId]) {
            // Usa senha do cache
            passwordSpan.textContent = emailPasswordsCache[accountId];
            passwordSpan.dataset.visible = 'true';
            passwordSpan.style.color = '#023A8D';
            passwordSpan.style.fontWeight = '600';
            button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            // Tenta buscar senha do servidor (sem PIN primeiro)
            fetchEmailPassword(accountId, button, passwordSpan, '');
        }
    }
}

function fetchEmailPassword(accountId, button, passwordSpan, viewPin) {
    button.disabled = true;
    button.style.opacity = '0.6';
    passwordSpan.textContent = 'Carregando...';
    
    var formData = new FormData();
    formData.append('id', accountId);
    if (viewPin) {
        formData.append('view_pin', viewPin);
    }
    
    fetch('<?= pixelhub_url('/email-accounts/password') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Lê o texto da resposta primeiro
        return response.text().then(text => {
            let data = null;
            const status = response.status;
            
            // Tenta parsear JSON
            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e, 'Texto:', text);
                }
            }
            
            return { status: status, data: data, ok: response.ok };
        });
    })
    .then(result => {
        const { status, data, ok } = result;
        
        button.disabled = false;
        button.style.opacity = '1';
        
        // PRIORIDADE 1: Verifica se precisa de PIN (erro 400 com mensagem sobre PIN)
        if (status === 400 && data && data.error) {
            const errorMsg = (data.error || '').toLowerCase();
            if (errorMsg.includes('pin') || errorMsg.includes('visualização') || errorMsg.includes('visualizacao')) {
                // Mostra modal de PIN
                pendingEmailPasswordId = accountId;
                
                // Aguarda um pouco para garantir que o DOM está pronto
                setTimeout(function() {
                    var pinModal = document.getElementById('emailPasswordPinModal');
                    var pinInput = document.getElementById('emailPasswordPinInput');
                    var pinError = document.getElementById('emailPasswordError');
                    
                    if (pinModal && pinInput && pinError) {
                        pinInput.value = '';
                        pinError.style.display = 'none';
                        pinError.textContent = '';
                        pinModal.style.display = 'block';
                        pinInput.focus();
                        passwordSpan.textContent = '••••••••';
                    } else {
                        console.error('Modal não encontrado!', {
                            modal: !!pinModal,
                            input: !!pinInput,
                            error: !!pinError
                        });
                        passwordSpan.textContent = 'Erro';
                        passwordSpan.style.color = '#c33';
                        alert('Erro: Modal de PIN não encontrado. Recarregue a página.');
                    }
                }, 100);
                return;
            }
        }
        
        // PRIORIDADE 2: Outros erros
        if (!ok) {
            passwordSpan.textContent = 'Erro';
            passwordSpan.style.color = '#c33';
            var errorMsg = (data && data.error) ? data.error : 'Erro desconhecido';
            alert('Erro ao buscar senha: ' + errorMsg);
            return;
        }
        
        // PRIORIDADE 3: Sucesso
        if (data && data.password !== undefined) {
            var password = data.password || '';
            emailPasswordsCache[accountId] = password;
            passwordSpan.textContent = password || '-';
            passwordSpan.dataset.visible = 'true';
            passwordSpan.style.color = '#023A8D';
            passwordSpan.style.fontWeight = '600';
            button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            passwordSpan.textContent = 'Erro';
            passwordSpan.style.color = '#c33';
            alert('Erro: resposta inválida do servidor');
        }
    })
    .catch(error => {
        button.disabled = false;
        button.style.opacity = '1';
        passwordSpan.textContent = 'Erro';
        passwordSpan.style.color = '#c33';
        console.error('Erro na requisição:', error);
        alert('Erro ao buscar senha. Verifique o console para mais detalhes.');
    });
}

function confirmEmailPasswordPin() {
    var viewPin = document.getElementById('emailPasswordPinInput').value.trim();
    var errorDiv = document.getElementById('emailPasswordError');
    
    if (!viewPin) {
        errorDiv.textContent = 'Por favor, digite o PIN de visualização';
        errorDiv.style.display = 'block';
        return;
    }
    
    if (!pendingEmailPasswordId || pendingEmailPasswordId <= 0) {
        errorDiv.textContent = 'Erro: ID da conta não identificado';
        errorDiv.style.display = 'block';
        return;
    }
    
    var currentPendingId = pendingEmailPasswordId;
    
    // Fecha o modal de confirmação
    document.getElementById('emailPasswordPinModal').style.display = 'none';
    document.getElementById('emailPasswordPinInput').value = '';
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    
    // Busca a senha com o PIN
    var passwordSpan = document.getElementById('email_password_' + currentPendingId);
    var button = passwordSpan.nextElementSibling;
    fetchEmailPassword(currentPendingId, button, passwordSpan, viewPin);
    
    pendingEmailPasswordId = null;
}

function closeEmailPasswordPinModal() {
    document.getElementById('emailPasswordPinModal').style.display = 'none';
    document.getElementById('emailPasswordPinInput').value = '';
    document.getElementById('emailPasswordError').style.display = 'none';
    document.getElementById('emailPasswordError').textContent = '';
    pendingEmailPasswordId = null;
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    var date = new Date(dateStr + 'T00:00:00');
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var year = date.getFullYear();
    return day + '/' + month + '/' + year;
}

// Fecha modal ao clicar fora
document.addEventListener('click', function(event) {
    var modal = document.getElementById('hostingDetailsModal');
    if (event.target === modal) {
        closeHostingDetailsModal();
    }
});
</script>

<!-- Modal de Confirmação do PIN para Senha de Email -->
<div id="emailPasswordPinModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="position: relative; background: white; margin: 50px auto; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #023A8D;">Confirmação de Segurança</h3>
            <button onclick="closeEmailPasswordPinModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <p style="margin-bottom: 15px; color: #666;">
                Para visualizar a senha, digite o PIN de visualização:
            </p>
            <div style="margin-bottom: 15px;">
                <label for="emailPasswordPinInput" style="display: block; margin-bottom: 5px; font-weight: 600;">PIN de Visualização *</label>
                <input type="password" id="emailPasswordPinInput" name="view_pin" autocomplete="off" 
                       inputmode="numeric" pattern="[0-9]*"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;" 
                       placeholder="Informe o PIN configurado no sistema" autofocus required>
            </div>
            <div id="emailPasswordError" style="color: #c33; margin-bottom: 15px; display: none;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEmailPasswordPinModal()" 
                        style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Cancelar
                </button>
                <button type="button" onclick="confirmEmailPasswordPin()" 
                        style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Cliente - ' . htmlspecialchars($tenant['name']);

// Inclui modal de WhatsApp antes do layout
ob_start();
require __DIR__ . '/whatsapp_modal.php';
$modalContent = ob_get_clean();
$content .= $modalContent;

require __DIR__ . '/../layout/main.php';
?>



