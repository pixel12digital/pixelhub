<?php
ob_start();
?>

<div class="content-header">
    <h2>Bem-vindo ao Pixel Hub</h2>
    <p>Painel central da Pixel12 Digital</p>
</div>

<div class="stats">
    <div class="stat-card">
        <h3>Total de Tenants</h3>
        <div class="value"><?= $tenantsCount ?? 0 ?></div>
    </div>
    
    <div class="stat-card">
        <h3>Total de Faturas</h3>
        <div class="value"><?= $invoicesCount ?? 0 ?></div>
    </div>
    
    <div class="stat-card">
        <h3>Faturas Pendentes</h3>
        <div class="value"><?= $pendingInvoices ?? 0 ?></div>
    </div>
</div>

<div class="card">
    <h3>Informações do Sistema</h3>
    <p style="margin-top: 10px; color: #666;">
        Sistema em desenvolvimento - Fase 0 concluída.<br>
        Conexão com banco de dados estabelecida com sucesso.
    </p>
</div>

<?php
$content = ob_get_clean();
$title = 'Dashboard';
require __DIR__ . '/../layout/main.php';
?>

