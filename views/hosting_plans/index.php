<?php
ob_start();
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Planos</h2>
        <p>Gerencie planos de serviços recorrentes</p>
    </div>
    <a href="<?= pixelhub_url('/hosting-plans/create') ?>" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
        Novo Plano
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'Plano criado com sucesso!';
            } elseif ($_GET['success'] === 'updated') {
                echo 'Plano atualizado com sucesso!';
            } elseif ($_GET['success'] === 'toggled') {
                echo 'Status do plano alterado com sucesso!';
            } elseif ($_GET['success'] === 'deleted') {
                echo 'Plano excluído com sucesso!';
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
            if ($error === 'toggle_failed') {
                echo 'Erro ao alterar status do plano.';
            } elseif ($error === 'delete_failed') {
                echo 'Erro ao excluir o plano.';
            } elseif ($error === 'cannot_delete_has_hosting') {
                echo 'Não é possível excluir o plano pois existem contas de hospedagem vinculadas a ele.';
            } elseif ($error === 'cannot_delete_has_contracts') {
                echo 'Não é possível excluir o plano pois existem contratos vinculados a ele.';
            } elseif ($error === 'missing_id') {
                echo 'ID do plano não informado.';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Serviço</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Provedor</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Valor</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ciclo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($plans)): ?>
                <tr>
                    <td colspan="7" style="padding: 20px; text-align: center; color: #666;">
                        Nenhum plano cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($plan['name']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $serviceTypeLabels = ['hospedagem' => 'Hospedagem', 'ecommerce' => 'E-commerce', 'manutencao' => 'Manutenção', 'saas' => 'SaaS'];
                        $st = $plan['service_type'] ?? '';
                        echo htmlspecialchars($serviceTypeLabels[$st] ?? ($st ?: '—'));
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $providerLabels = ['hostmedia' => 'HostMídia', 'vercel' => 'Vercel'];
                        $prov = $plan['provider'] ?? '';
                        echo htmlspecialchars($providerLabels[$prov] ?? ($prov ?: '—'));
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php if (!empty($plan['annual_enabled']) && !empty($plan['annual_total_amount']) && !empty($plan['annual_monthly_amount'])): ?>
                            <div style="line-height: 1.6;">
                                <div><strong>Mensal:</strong> R$ <?= number_format($plan['amount'], 2, ',', '.') ?></div>
                                <div style="color: #F7931E;">
                                    <strong>Anual:</strong> R$ <?= number_format($plan['annual_total_amount'], 2, ',', '.') ?> 
                                    (R$ <?= number_format($plan['annual_monthly_amount'], 2, ',', '.') ?>/mês)
                                </div>
                            </div>
                        <?php else: ?>
                            R$ <?= number_format($plan['amount'], 2, ',', '.') ?> / Mensal
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= ucfirst($plan['billing_cycle']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $statusColor = $plan['is_active'] ? '#3c3' : '#c33';
                        $statusText = $plan['is_active'] ? 'Ativo' : 'Inativo';
                        echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusText . '</span>';
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px;">
                            <a href="<?= pixelhub_url('/hosting-plans/edit?id=' . $plan['id']) ?>" 
                               style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                Editar
                            </a>
                            <form method="POST" action="<?= pixelhub_url('/hosting-plans/toggle-status') ?>" 
                                  style="display: inline-block; margin: 0;">
                                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                <button type="submit" 
                                        style="background: <?= $plan['is_active'] ? '#f93' : '#3c3' ?>; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                    <?= $plan['is_active'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                            <form method="POST" action="<?= pixelhub_url('/hosting-plans/delete') ?>" 
                                  style="display: inline-block; margin: 0;"
                                  onsubmit="return confirm('Tem certeza que deseja excluir este plano? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                <button type="submit" 
                                        style="background: #c33; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
$title = 'Planos';
require __DIR__ . '/../layout/main.php';
?>

