<?php
use PixelHub\Core\Storage;

ob_start();

$activeTab = $activeTab ?? 'overview';
$providerMap = $providerMap ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2><?= htmlspecialchars($tenant['name']) ?></h2>
        <p>Painel do Cliente</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="openWhatsAppModal(<?= $tenant['id'] ?>)" 
                style="background: #25D366; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
            📱 WhatsApp
        </button>
        <a href="<?= pixelhub_url('/tenants/edit?id=' . $tenant['id']) ?>" 
           style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            Editar Cliente
        </a>
        <form method="POST" action="<?= pixelhub_url('/tenants/delete') ?>" 
              onsubmit="return confirm('Tem certeza que deseja excluir este cliente? Esta ação não poderá ser desfeita.');" 
              style="display: inline-block; margin: 0;">
            <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
            <button type="submit" 
                    style="background: #c33; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                Excluir Cliente
            </button>
        </form>
    </div>
</div>

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
        Hospedagem & Sites
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
       style="display: inline-block; padding: 12px 20px; text-decoration: none; color: <?= $activeTab === 'financial' ? '#023A8D' : '#666' ?>; border-bottom: 2px solid <?= $activeTab === 'financial' ? '#023A8D' : 'transparent' ?>; font-weight: <?= $activeTab === 'financial' ? '600' : '400' ?>;">
        Financeiro
    </a>
</div>

<!-- Conteúdo das Abas -->
<?php if ($activeTab === 'overview'): ?>
    <!-- ABA: Visão Geral -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">Informações do Cliente</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600; width: 200px;">Nome:</td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($tenant['name']) ?></td>
            </tr>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">Tipo de Pessoa:</td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= ($tenant['person_type'] ?? 'pf') === 'pj' ? 'Pessoa Jurídica' : 'Pessoa Física' ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">CPF/CNPJ:</td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= htmlspecialchars($tenant['cpf_cnpj'] ?? ($tenant['document'] ?? '-')) ?>
                </td>
            </tr>
            <?php if (($tenant['person_type'] ?? 'pf') === 'pj'): ?>
                <?php if ($tenant['razao_social']): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">Razão Social:</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($tenant['razao_social']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($tenant['nome_fantasia']): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">Nome Fantasia:</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($tenant['nome_fantasia']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($tenant['responsavel_nome']): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">Responsável:</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($tenant['responsavel_nome']) ?>
                        <?php if ($tenant['responsavel_cpf']): ?>
                            <span style="color: #666; margin-left: 10px;">(CPF: <?= htmlspecialchars($tenant['responsavel_cpf']) ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($tenant['email']): ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">Email:</td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <a href="mailto:<?= htmlspecialchars($tenant['email']) ?>"><?= htmlspecialchars($tenant['email']) ?></a>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($tenant['phone']): ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">WhatsApp:</td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $tenant['phone']) ?>" target="_blank">
                        <?= htmlspecialchars($tenant['phone']) ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">Status:</td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php
                    $statusColor = $tenant['status'] === 'active' ? '#3c3' : '#c33';
                    $statusLabel = $tenant['status'] === 'active' ? 'Ativo' : 'Inativo';
                    echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusLabel . '</span>';
                    ?>
                </td>
            </tr>
        </table>
    </div>

<?php elseif ($activeTab === 'hosting'): ?>
    <!-- ABA: Hospedagem & Sites -->
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
                            echo htmlspecialchars($providerName);
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

<?php elseif ($activeTab === 'docs_backups'): ?>
    <!-- ABA: Docs & Backups -->
    
    <!-- Seção 1: Backups WordPress -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 20px;">Backups WordPress</h3>
        
        <!-- Formulário de Upload -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 15px;">Enviar Novo Backup</h4>
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?php
                    $error = $_GET['error'];
                    if ($error === 'upload_failed') echo 'Erro ao enviar arquivo. Verifique se o arquivo foi selecionado.';
                    elseif ($error === 'invalid_extension') echo 'Apenas arquivos .wpress são permitidos.';
                    elseif ($error === 'file_too_large') echo 'Arquivo muito grande. O limite é 2GB.';
                    elseif ($error === 'move_failed') echo 'Falha ao salvar o arquivo.';
                    elseif ($error === 'database_error') echo 'Erro ao registrar o backup no banco de dados.';
                    elseif ($error === 'delete_missing_id') echo 'ID do backup não fornecido para exclusão.';
                    elseif ($error === 'delete_not_found') echo 'Backup não encontrado para exclusão.';
                    elseif ($error === 'delete_database_error') echo 'Erro ao excluir backup do banco de dados.';
                    else echo 'Erro desconhecido.';
                    ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="<?= pixelhub_url('/hosting/backups/upload') ?>" enctype="multipart/form-data">
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
                    <label for="backup_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Arquivo .wpress:</label>
                    <input type="file" id="backup_file" name="backup_file" accept=".wpress" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Apenas arquivos .wpress do All-in-One WP Migration. Tamanho máximo: 2GB</small>
                    <div id="chunked-upload-progress" style="display: none; margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                        <h4 style="margin: 0 0 10px 0; color: #023A8D;">Upload em Progresso</h4>
                        <div style="background: #ddd; height: 25px; border-radius: 4px; overflow: hidden; position: relative;">
                            <div id="chunked-progress-bar" style="background: #4caf50; height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">
                                0%
                            </div>
                        </div>
                        <p id="chunked-status" style="margin: 10px 0 0 0; color: #666; font-size: 13px;">Preparando upload...</p>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas (opcional):</label>
                    <textarea id="notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                </div>
                
                <button type="submit" id="submit-btn" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Enviar Backup
                </button>
            </form>
            
            <?php
            // Calcula limites do PHP para upload (mesma lógica da tela interna)
            function php_ini_to_bytes_tenant(string $value): int {
                $value = trim($value);
                if (empty($value)) return 0;
                $last = strtolower(substr($value, -1));
                $num = (int)$value;
                switch ($last) {
                    case 'g': $num *= 1024;
                    case 'm': $num *= 1024;
                    case 'k': $num *= 1024;
                }
                return $num;
            }
            
            $phpUploadMax = ini_get('upload_max_filesize');
            $phpPostMax = ini_get('post_max_size');
            $uploadMaxBytes = php_ini_to_bytes_tenant($phpUploadMax);
            $postMaxBytes = php_ini_to_bytes_tenant($phpPostMax);
            $phpHardLimitBytes = min($uploadMaxBytes, $postMaxBytes);
            $systemMaxDirectBytes = 500 * 1024 * 1024; // 500MB
            $maxDirectUploadBytes = min($systemMaxDirectBytes, $phpHardLimitBytes);
            if ($maxDirectUploadBytes <= 0) {
                $maxDirectUploadBytes = 30 * 1024 * 1024; // 30MB fallback
            }
            ?>
            
            <script src="<?= pixelhub_url('/assets/js/hosting_backups.js') ?>"></script>
            <script>
            // Inicializa upload em chunks para aba docs_backups
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof HostingBackupUpload !== 'undefined') {
                    HostingBackupUpload.init({
                        formSelector: 'form[enctype="multipart/form-data"]',
                        fileInputSelector: '#backup_file',
                        notesSelector: '#notes',
                        submitBtnSelector: '#submit-btn',
                        progressContainerSelector: '#chunked-upload-progress',
                        progressBarSelector: '#chunked-progress-bar',
                        statusTextSelector: '#chunked-status',
                        maxDirectUploadBytes: <?= (int) $maxDirectUploadBytes ?>,
                        chunkMaxBytes: <?= 2 * 1024 * 1024 * 1024 ?>, // 2GB
                        chunkSize: 10 * 1024 * 1024, // 10MB por chunk
                        chunkInitUrl: '<?= pixelhub_url('/hosting/backups/chunk-init') ?>',
                        chunkUploadUrl: '<?= pixelhub_url('/hosting/backups/chunk-upload') ?>',
                        chunkCompleteUrl: '<?= pixelhub_url('/hosting/backups/chunk-complete') ?>',
                        onSuccess: function(hostingAccountId) {
                            // Recarrega a aba docs_backups após sucesso
                            window.location.href = '<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=docs_backups&success=uploaded') ?>';
                        }
                    });
                }
            });
            </script>
        </div>
        
        <!-- Lista de Backups -->
        <?php if (empty($backups)): ?>
            <p style="color: #666;">Nenhum backup encontrado.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Notas</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($backup['domain']) ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= $backup['created_at'] ? date('d/m/Y H:i', strtotime($backup['created_at'])) : 'N/A' ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($backup['type']) ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= Storage::formatFileSize($backup['file_size'] ?? 0) ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($backup['notes'] ?? '') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <?php if (!empty($backup['file_exists'])): ?>
                                    <a href="<?= pixelhub_url('/hosting/backups/download?id=' . $backup['id']) ?>" 
                                       style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                        Download
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px; font-style: italic;" title="Backup feito em outro ambiente">
                                        Arquivo indisponível
                                    </span>
                                <?php endif; ?>
                                
                                <form method="POST" action="<?= pixelhub_url('/hosting/backups/delete') ?>" 
                                      style="display: inline-block; margin: 0;"
                                      onsubmit="return confirm('Tem certeza que deseja excluir este backup? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="backup_id" value="<?= $backup['id'] ?>">
                                    <input type="hidden" name="hosting_id" value="<?= $backup['hosting_account_id'] ?>">
                                    <input type="hidden" name="redirect_to" value="tenant">
                                    <button type="submit" 
                                            style="background: #c33; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                        Excluir
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Seção 2: Documentos Gerais (placeholder) -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">Documentos Gerais</h3>
        <p style="color: #666;">Funcionalidade de documentos será implementada em breve.</p>
    </div>

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
                        🔄 Sincronizar com Asaas
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
            <p style="color: #155724; margin: 0;">
                ✓ Cobrança via WhatsApp marcada como enviada com sucesso!
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
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <?php if (!empty($invoice['invoice_url'])): ?>
                                    <a href="<?= htmlspecialchars($invoice['invoice_url']) ?>" 
                                       target="_blank"
                                       style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                        Ver Fatura
                                    </a>
                                <?php endif; ?>
                                <a href="<?= pixelhub_url('/billing/whatsapp-modal?invoice_id=' . $invoice['id'] . '&redirect_to=tenant') ?>" 
                                   style="background: #25D366; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                    📱 Cobrar
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Histórico de Cobranças WhatsApp -->
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
                                       style="background: #25D366; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                        📱 Cobrar Novamente
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

<?php elseif ($activeTab === 'tasks'): ?>
    <!-- ABA: Tarefas & Projetos -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Resumo de Tarefas do Cliente</h3>
            <a href="<?= pixelhub_url('/projects/board?tenant_id=' . $tenant['id']) ?>" 
               style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                Ver no Quadro Kanban
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
                                       style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; font-weight: 600;">
                                        Ver no Kanban
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
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalContent.innerHTML = '<p style="color: #c33;">Erro: ' + escapeHtml(data.error) + '</p>';
                return;
            }
            
            // Atualiza título
            modalTitle.textContent = escapeHtml(data.domain.toUpperCase()) + ' — ' + escapeHtml(data.provider);
            
            // Monta conteúdo do modal
            var html = '<div style="margin-bottom: 25px;">';
            html += '<h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D; border-bottom: 2px solid #023A8D; padding-bottom: 8px;">Resumo</h3>';
            html += '<table style="width: 100%; border-collapse: collapse;">';
            html += '<tr><td style="padding: 8px; font-weight: 600; width: 150px;">Plano / Valor:</td><td style="padding: 8px;">' + escapeHtml(data.plan_name || '-') + ' / ' + escapeHtml(data.amount) + '</td></tr>';
            html += '<tr><td style="padding: 8px; font-weight: 600;">Provedor:</td><td style="padding: 8px;">' + escapeHtml(data.provider) + '</td></tr>';
            html += '<tr><td style="padding: 8px; font-weight: 600;">Venc. Hospedagem:</td><td style="padding: 8px;">' + (data.hostinger_expiration_date ? formatDate(data.hostinger_expiration_date) : '-') + '</td></tr>';
            html += '<tr><td style="padding: 8px; font-weight: 600;">Venc. Domínio:</td><td style="padding: 8px;">' + (data.domain_expiration_date ? formatDate(data.domain_expiration_date) : '-') + '</td></tr>';
            html += '<tr><td style="padding: 8px; font-weight: 600;">Situação:</td><td style="padding: 8px;">';
            html += '<div style="display: flex; flex-direction: column; gap: 4px;">';
            html += '<span style="' + data.hosting_status.style + '">' + escapeHtml(data.hosting_status.text) + '</span>';
            html += '<span style="' + data.domain_status.style + '">' + escapeHtml(data.domain_status.text) + '</span>';
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
            if (data.hosting_panel_url) {
                html += '<p style="margin: 5px 0;"><strong>URL:</strong> <a href="' + escapeHtml(data.hosting_panel_url) + '" target="_blank" style="color: #023A8D;">' + escapeHtml(data.hosting_panel_url) + '</a></p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>URL:</strong> Não informado</p>';
            }
            if (data.hosting_panel_username) {
                html += '<p style="margin: 5px 0;"><strong>Usuário:</strong> ' + escapeHtml(data.hosting_panel_username) + '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Usuário:</strong> Não informado</p>';
            }
            if (data.hosting_panel_password) {
                html += '<p style="margin: 5px 0;"><strong>Senha:</strong> ';
                html += '<input type="password" id="hosting_panel_password_display" value="' + escapeHtml(data.hosting_panel_password) + '" readonly style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; width: 200px;"> ';
                html += '<button type="button" onclick="togglePasswordDisplay(\'hosting_panel_password_display\', this)" style="background: #666; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">👁️</button>';
                html += '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Senha:</strong> Não informado</p>';
            }
            html += '</div>';
            
            // Admin do Site
            html += '<div style="margin-bottom: 0;">';
            html += '<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">Admin do Site</h4>';
            if (data.site_admin_url) {
                html += '<p style="margin: 5px 0;"><strong>URL:</strong> <a href="' + escapeHtml(data.site_admin_url) + '" target="_blank" style="color: #023A8D;">' + escapeHtml(data.site_admin_url) + '</a></p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>URL:</strong> Não informado</p>';
            }
            if (data.site_admin_username) {
                html += '<p style="margin: 5px 0;"><strong>Usuário:</strong> ' + escapeHtml(data.site_admin_username) + '</p>';
            } else {
                html += '<p style="margin: 5px 0; color: #999;"><strong>Usuário:</strong> Não informado</p>';
            }
            if (data.site_admin_password) {
                html += '<p style="margin: 5px 0;"><strong>Senha:</strong> ';
                html += '<input type="password" id="site_admin_password_display" value="' + escapeHtml(data.site_admin_password) + '" readonly style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; width: 200px;"> ';
                html += '<button type="button" onclick="togglePasswordDisplay(\'site_admin_password_display\', this)" style="background: #666; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">👁️</button>';
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
            if (data.hosting_panel_url) {
                html += '<a href="' + escapeHtml(data.hosting_panel_url) + '" target="_blank" style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">Abrir Painel de Hospedagem</a>';
            }
            if (data.site_admin_url) {
                html += '<a href="' + escapeHtml(data.site_admin_url) + '" target="_blank" style="background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">Abrir Admin do Site</a>';
            }
            if (!data.hosting_panel_url && !data.site_admin_url) {
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
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
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

