<?php
/**
 * Carteira Recorrente - Visão consolidada de contratos recorrentes
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Carteira Recorrente</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Visão consolidada de contratos recorrentes (hospedagem, SaaS e serviços) – somente leitura.
    </p>
</div>

<!-- Cards de Resumo -->
<div class="stats" style="margin-bottom: 30px;">
    <div class="stat-card">
        <h3>Contratos Ativos</h3>
        <div class="value"><?= number_format($summary['total_contracts_ativos'] ?? 0, 0, ',', '.') ?></div>
    </div>
    <div class="stat-card">
        <h3>Receita Mensal</h3>
        <div class="value">R$ <?= number_format($summary['total_mensal'] ?? 0, 2, ',', '.') ?></div>
        <small style="color: #666; font-size: 12px;">Contratos mensais</small>
    </div>
    <div class="stat-card">
        <h3>Receita Anual</h3>
        <div class="value">R$ <?= number_format($summary['total_anual'] ?? 0, 2, ',', '.') ?></div>
        <small style="color: #666; font-size: 12px;">Contratos anuais</small>
    </div>
    <div class="stat-card">
        <h3>Receita Recorrente Total</h3>
        <div class="value">R$ <?= number_format($summary['total_receita'] ?? 0, 2, ',', '.') ?></div>
        <small style="color: #666; font-size: 12px;">Sem normalização de ciclo</small>
    </div>
</div>

<!-- Resumo por Categoria -->
<div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #023A8D;">
    <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; font-weight: 600;">Receita Mensal por Categoria (Contratos Ativos)</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div>
            <div style="color: #6c757d; font-size: 14px; margin-bottom: 5px;">Receita mensal de Hospedagem</div>
            <div style="font-size: 20px; font-weight: 600; color: #023A8D;">R$ <?= number_format($summary['mensal_hospedagem'] ?? 0, 2, ',', '.') ?></div>
        </div>
        <div>
            <div style="color: #6c757d; font-size: 14px; margin-bottom: 5px;">Receita mensal de Outros Serviços</div>
            <div style="font-size: 20px; font-weight: 600; color: #023A8D;">R$ <?= number_format($summary['mensal_outros'] ?? 0, 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<!-- Barra de Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" action="<?= pixelhub_url('/recurring-contracts') ?>">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Cliente:</label>
                <select name="tenant_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Todos os clientes</option>
                    <?php foreach ($tenantsOptions ?? [] as $t): ?>
                        <?php
                        $tenantName = $t['name'];
                        if (($t['person_type'] ?? 'pf') === 'pj' && !empty($t['nome_fantasia'])) {
                            $tenantName = $t['nome_fantasia'];
                        }
                        ?>
                        <option value="<?= $t['id'] ?>" <?= ($filters['tenant_id'] ?? null) == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenantName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Status:</label>
                <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?= ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($statusOptions ?? [] as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= ($filters['status'] ?? 'all') === $status ? 'selected' : '' ?>>
                            <?= ucfirst(htmlspecialchars($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Ciclo de Cobrança:</label>
                <select name="billing_mode" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?= ($filters['billing_mode'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="mensal" <?= ($filters['billing_mode'] ?? 'all') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                    <option value="anual" <?= ($filters['billing_mode'] ?? 'all') === 'anual' ? 'selected' : '' ?>>Anual</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Categoria / Tipo:</label>
                <select name="category" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?= ($filters['category'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="hospedagem" <?= ($filters['category'] ?? 'all') === 'hospedagem' ? 'selected' : '' ?>>Hospedagem</option>
                    <?php foreach ($serviceTypesOptions ?? [] as $st): ?>
                        <option value="<?= htmlspecialchars($st['slug']) ?>" <?= ($filters['category'] ?? 'all') === $st['slug'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm" style="background: #023A8D; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; margin-right: 10px;">Filtrar</button>
                <a href="<?= pixelhub_url('/recurring-contracts') ?>" class="btn btn-secondary btn-sm" style="display: inline-block; padding: 8px 15px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 14px;">Limpar Filtros</a>
            </div>
        </div>
    </form>
</div>

<!-- Tabela Principal -->
<div class="card">
    <?php if (empty($contracts)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 16px; margin-bottom: 10px;">Nenhum contrato encontrado.</p>
            <p style="font-size: 14px;">A tabela billing_contracts está vazia ou não há registros que correspondam aos filtros selecionados.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Cliente</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Contrato / Plano</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Domínio</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Provedor</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ciclo</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Categoria / Tipo</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Valor</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Status</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Criado em</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $contract): ?>
                    <?php
                    $tenantName = $contract['tenant_name'] ?? 'N/A';
                    if (($contract['person_type'] ?? 'pf') === 'pj' && !empty($contract['nome_fantasia'])) {
                        $tenantName = $contract['nome_fantasia'];
                    }
                    
                    $planName = $contract['plan_snapshot_name'] ?? 'N/A';
                    $domain = $contract['hosting_domain'] ?? null;
                    $billingMode = $contract['billing_mode'] ?? 'mensal';
                    $billingModeLabel = $billingMode === 'mensal' ? 'Mensal' : 'Anual';
                    $category = $contract['category'] ?? 'outros';
                    $categoryLabel = $contract['category_name'] ?? 'Outros serviços';
                    $categorySlug = $contract['category'] ?? 'outros';
                    $hostingProvider = $contract['hosting_provider'] ?? null;
                    $hostingProviderLabel = ucfirst($hostingProvider ?? '—');
                    $hostingAccountId = $contract['hosting_account_id'] ?? null;
                    $amount = (float) ($contract['amount'] ?? 0);
                    $status = $contract['status'] ?? 'ativo';
                    $createdAt = $contract['created_at'] ?? null;
                    $contractId = $contract['id'] ?? 0;
                    
                    // Formata data de criação
                    $createdAtFormatted = 'N/A';
                    if ($createdAt) {
                        try {
                            $date = new DateTime($createdAt);
                            $createdAtFormatted = $date->format('d/m/Y');
                        } catch (Exception $e) {
                            // mantém N/A
                        }
                    }
                    
                    // Badge de status
                    $statusBadge = ucfirst($status);
                    $statusClass = 'badge-default';
                    if ($status === 'ativo') {
                        $statusClass = 'badge-success';
                    } elseif ($status === 'cancelado') {
                        $statusClass = 'badge-danger';
                    }
                    ?>
                    <tr style="border-bottom: 1px solid #dee2e6;" data-contract-id="<?= $contractId ?>">
                        <td style="padding: 12px;">
                            <a href="<?= pixelhub_url('/tenants/view?id=' . $contract['tenant_id']) ?>" style="color: #023A8D; text-decoration: none; font-weight: 500;">
                                <?= htmlspecialchars($tenantName) ?>
                            </a>
                        </td>
                        <td style="padding: 12px;">
                            <?= htmlspecialchars($planName) ?>
                        </td>
                        <td style="padding: 12px; color: #6c757d;">
                            <?= $domain ? htmlspecialchars($domain) : '—' ?>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #6c757d;">
                            <?= htmlspecialchars($hostingProviderLabel) ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?= htmlspecialchars($billingModeLabel) ?>
                        </td>
                        <td style="padding: 12px;">
                            <span class="category-display" data-category-slug="<?= htmlspecialchars($categorySlug) ?>">
                                <?= htmlspecialchars($categoryLabel) ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 500;">
                            R$ <?= number_format($amount, 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span class="badge <?= $statusClass ?>">
                                <?= htmlspecialchars($statusBadge) ?>
                            </span>
                        </td>
                        <td style="padding: 12px;">
                            <?= $createdAtFormatted ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <button type="button" class="btn-edit-category" 
                                        data-contract-id="<?= $contractId ?>"
                                        data-current-category="<?= htmlspecialchars($categorySlug) ?>"
                                        data-tenant-name="<?= htmlspecialchars($tenantName) ?>"
                                        data-plan-name="<?= htmlspecialchars($planName) ?>"
                                        style="background: none; border: none; color: #023A8D; cursor: pointer; font-size: 13px; text-decoration: underline; padding: 0;">
                                    Editar Categoria
                                </button>
                                <?php if ($hostingAccountId): ?>
                                    <span style="color: #dee2e6;">|</span>
                                    <a href="<?= pixelhub_url('/hosting/edit?id=' . $hostingAccountId) ?>" 
                                       style="color: #023A8D; text-decoration: none; font-size: 13px;">
                                        Abrir Hospedagem
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Paginação -->
        <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                <div style="color: #6c757d; font-size: 14px;">
                    Página <?= $pagination['current_page'] ?> de <?= $pagination['total_pages'] ?> 
                    (<?= $pagination['total_records'] ?> registro(s) no total)
                </div>
                <div style="display: flex; gap: 10px;">
                    <?php if ($pagination['current_page'] > 1): ?>
                        <?php
                        $prevParams = $_GET;
                        $prevParams['page'] = $pagination['current_page'] - 1;
                        $prevUrl = pixelhub_url('/recurring-contracts?' . http_build_query($prevParams));
                        ?>
                        <a href="<?= $prevUrl ?>" class="btn btn-secondary btn-sm" style="display: inline-block; padding: 6px 12px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 13px;">← Anterior</a>
                    <?php endif; ?>
                    
                    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                        <?php
                        $nextParams = $_GET;
                        $nextParams['page'] = $pagination['current_page'] + 1;
                        $nextUrl = pixelhub_url('/recurring-contracts?' . http_build_query($nextParams));
                        ?>
                        <a href="<?= $nextUrl ?>" class="btn btn-secondary btn-sm" style="display: inline-block; padding: 6px 12px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 13px;">Próxima →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal de Edição de Categoria -->
<div id="categoryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h3 style="margin: 0 0 20px 0; color: #023A8D; font-size: 20px;">Editar Categoria do Contrato</h3>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Cliente:</label>
            <input type="text" id="modal-tenant-name" readonly 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Contrato / Plano:</label>
            <input type="text" id="modal-plan-name" readonly 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Categoria / Tipo de Serviço:</label>
            <select id="modal-category-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">— Sem categoria —</option>
                <option value="hospedagem">Hospedagem</option>
                <?php foreach ($serviceTypesOptions ?? [] as $st): ?>
                    <option value="<?= htmlspecialchars($st['slug']) ?>">
                        <?= htmlspecialchars($st['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" id="modal-cancel" 
                    style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Cancelar
            </button>
            <button type="button" id="modal-save" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Observação no rodapé -->
<div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-left: 4px solid #023A8D; border-radius: 4px;">
    <p style="margin: 0; color: #6c757d; font-size: 13px; line-height: 1.6;">
        <strong>Observação:</strong> Esta tela é uma visão gerencial baseada nos contratos recorrentes (billing_contracts). 
        Ela não substitui a aba Financeiro dos clientes nem a Central de Cobranças.
    </p>
</div>

<style>
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.badge-default {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #023A8D;
    color: white;
}

.btn-primary:hover {
    background: #022a6d;
}

.btn-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('categoryModal');
    const btnEditCategory = document.querySelectorAll('.btn-edit-category');
    const btnCancel = document.getElementById('modal-cancel');
    const btnSave = document.getElementById('modal-save');
    let currentContractId = null;

    // Abre modal ao clicar em "Editar Categoria"
    btnEditCategory.forEach(btn => {
        btn.addEventListener('click', function() {
            currentContractId = this.getAttribute('data-contract-id');
            const tenantName = this.getAttribute('data-tenant-name');
            const planName = this.getAttribute('data-plan-name');
            const currentCategory = this.getAttribute('data-current-category');
            
            document.getElementById('modal-tenant-name').value = tenantName;
            document.getElementById('modal-plan-name').value = planName;
            document.getElementById('modal-category-select').value = currentCategory || '';
            
            modal.style.display = 'flex';
        });
    });

    // Fecha modal ao clicar em Cancelar ou fora do modal
    btnCancel.addEventListener('click', function() {
        modal.style.display = 'none';
        currentContractId = null;
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            currentContractId = null;
        }
    });

    // Salva categoria via AJAX
    btnSave.addEventListener('click', function() {
        if (!currentContractId) return;

        const serviceType = document.getElementById('modal-category-select').value;
        const btn = btnSave;
        const originalText = btn.textContent;
        
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        const formData = new FormData();
        formData.append('contract_id', currentContractId);
        formData.append('service_type', serviceType);

        fetch('<?= pixelhub_url('/recurring-contracts/update-category') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualiza a célula da categoria na tabela
                const row = document.querySelector(`tr[data-contract-id="${currentContractId}"]`);
                if (row) {
                    const categoryCell = row.querySelector('.category-display');
                    if (categoryCell) {
                        categoryCell.textContent = data.category_name;
                        categoryCell.setAttribute('data-category-slug', data.category_slug);
                    }
                    
                    // Atualiza o botão de editar também
                    const editBtn = row.querySelector('.btn-edit-category');
                    if (editBtn) {
                        editBtn.setAttribute('data-current-category', data.category_slug);
                    }
                }
                
                modal.style.display = 'none';
                currentContractId = null;
            } else {
                alert('Erro: ' + (data.message || 'Erro ao atualizar categoria'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao atualizar categoria. Tente novamente.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
$title = 'Carteira Recorrente';
require __DIR__ . '/../layout/main.php';
?>

