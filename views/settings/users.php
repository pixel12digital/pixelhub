<?php
$roles = $roles ?? [];
$roleDescriptions = $roleDescriptions ?? [];
$users = $users ?? [];
ob_start();
?>

<div style="max-width: 1100px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="margin: 0 0 4px 0; font-size: 24px; color: #111;">Usuários</h1>
            <p style="margin: 0; color: #666; font-size: 14px;">Gerencie os usuários que acessam o PixelHub</p>
        </div>
        <button onclick="openCreateModal()" style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Usuário
        </button>
    </div>

    <!-- Cards de resumo -->
    <div style="display: flex; gap: 16px; margin-bottom: 24px;">
        <?php
        $totalUsers = count($users);
        $activeUsers = count(array_filter($users, fn($u) => $u['is_active']));
        $inactiveUsers = $totalUsers - $activeUsers;
        $adminCount = count(array_filter($users, fn($u) => ($u['role'] ?? 'admin') === 'admin' && $u['is_active']));
        ?>
        <div style="flex: 1; background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="font-size: 28px; font-weight: 700; color: #023A8D;"><?= $totalUsers ?></div>
            <div style="font-size: 13px; color: #666;">Total de usuários</div>
        </div>
        <div style="flex: 1; background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="font-size: 28px; font-weight: 700; color: #198754;"><?= $activeUsers ?></div>
            <div style="font-size: 13px; color: #666;">Ativos</div>
        </div>
        <div style="flex: 1; background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="font-size: 28px; font-weight: 700; color: #dc3545;"><?= $inactiveUsers ?></div>
            <div style="font-size: 13px; color: #666;">Inativos</div>
        </div>
        <div style="flex: 1; background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="font-size: 28px; font-weight: 700; color: #6f42c1;"><?= $adminCount ?></div>
            <div style="font-size: 13px; color: #666;">Administradores</div>
        </div>
    </div>

    <!-- Tabela de usuários -->
    <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #495057; font-size: 13px;">Usuário</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #495057; font-size: 13px;">Perfil</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #495057; font-size: 13px;">Status</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #495057; font-size: 13px;">Último acesso</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #495057; font-size: 13px;">Criado em</th>
                    <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #495057; font-size: 13px; width: 120px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $role = $u['role'] ?? 'admin';
                    $roleName = $roles[$role] ?? $role;
                    $isActive = (bool)$u['is_active'];
                    $roleColors = [
                        'admin' => ['bg' => '#6f42c1', 'text' => '#fff'],
                        'operator' => ['bg' => '#0d6efd', 'text' => '#fff'],
                        'viewer' => ['bg' => '#6c757d', 'text' => '#fff'],
                    ];
                    $rc = $roleColors[$role] ?? $roleColors['viewer'];
                ?>
                <tr style="border-bottom: 1px solid #f0f0f0; <?= !$isActive ? 'opacity: 0.5;' : '' ?>"
                    onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                    <td style="padding: 12px 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: <?= $rc['bg'] ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                                <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #111; font-size: 14px;"><?= htmlspecialchars($u['name']) ?></div>
                                <div style="font-size: 12px; color: #888;"><?= htmlspecialchars($u['email']) ?></div>
                                <?php if (!empty($u['phone'])): ?>
                                    <div style="font-size: 11px; color: #aaa;"><?= htmlspecialchars($u['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 12px 16px;">
                        <span style="background: <?= $rc['bg'] ?>; color: <?= $rc['text'] ?>; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?= htmlspecialchars($roleName) ?>
                        </span>
                    </td>
                    <td style="padding: 12px 16px;">
                        <?php if ($isActive): ?>
                            <span style="display: inline-flex; align-items: center; gap: 4px; color: #198754; font-size: 13px; font-weight: 600;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #198754; display: inline-block;"></span>
                                Ativo
                            </span>
                        <?php else: ?>
                            <span style="display: inline-flex; align-items: center; gap: 4px; color: #dc3545; font-size: 13px; font-weight: 600;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #dc3545; display: inline-block;"></span>
                                Inativo
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 16px; color: #666; font-size: 13px;">
                        <?php if (!empty($u['last_login_at'])): ?>
                            <?= date('d/m/Y H:i', strtotime($u['last_login_at'])) ?>
                        <?php else: ?>
                            <span style="color: #ccc;">Nunca</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 16px; color: #888; font-size: 13px;">
                        <?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
                    </td>
                    <td style="padding: 12px 16px; text-align: center;">
                        <div style="display: flex; gap: 6px; justify-content: center;">
                            <button onclick="openEditModal(<?= $u['id'] ?>)" title="Editar"
                                    style="width: 32px; height: 32px; border-radius: 6px; border: 1px solid #ddd; background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;"
                                    onmouseover="this.style.background='#e7f1ff'; this.style.borderColor='#0d6efd'" onmouseout="this.style.background='white'; this.style.borderColor='#ddd'">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>', <?= $isActive ? 'true' : 'false' ?>)" 
                                    title="<?= $isActive ? 'Desativar' : 'Ativar' ?>"
                                    style="width: 32px; height: 32px; border-radius: 6px; border: 1px solid #ddd; background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;"
                                    onmouseover="this.style.background='<?= $isActive ? '#fff5f5' : '#e8f5e9' ?>'; this.style.borderColor='<?= $isActive ? '#dc3545' : '#198754' ?>'" onmouseout="this.style.background='white'; this.style.borderColor='#ddd'">
                                <?php if ($isActive): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#198754" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <?php endif; ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Criar/Editar Usuário -->
<div id="user-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 id="modal-title" style="margin: 0; font-size: 20px;">Novo Usuário</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>

        <form id="user-form" onsubmit="return submitUserForm(event)">
            <input type="hidden" id="user-id" value="">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Nome completo *</label>
                <input type="text" id="user-name" required placeholder="Ex: João Silva"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">E-mail *</label>
                <input type="email" id="user-email" required placeholder="usuario@empresa.com"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Telefone</label>
                <input type="text" id="user-phone" placeholder="(47) 99999-9999"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Perfil de acesso *</label>
                <select id="user-role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: white;">
                    <?php foreach ($roles as $rk => $rl): ?>
                        <option value="<?= $rk ?>"><?= htmlspecialchars($rl) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="role-description" style="margin-top: 6px; font-size: 12px; color: #888; padding: 6px 10px; background: #f8f9fa; border-radius: 4px;">
                    <?= htmlspecialchars($roleDescriptions['admin'] ?? '') ?>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">
                    <span id="password-label">Senha *</span>
                </label>
                <input type="password" id="user-password" placeholder="Mínimo 6 caracteres"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px;">
                <div id="password-hint" style="display: none; margin-top: 4px; font-size: 11px; color: #888;">Deixe em branco para manter a senha atual</div>
            </div>

            <div id="form-error" style="display: none; padding: 10px; background: #fff5f5; border: 1px solid #dc3545; border-radius: 6px; color: #dc3545; font-size: 13px; margin-bottom: 16px;"></div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer; font-size: 14px; color: #666;">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit" style="padding: 10px 24px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var baseUrl = '<?= function_exists("pixelhub_url") ? rtrim(pixelhub_url(""), "/") : "" ?>';
var roleDescriptions = <?= json_encode($roleDescriptions, JSON_UNESCAPED_UNICODE) ?>;

// Atualiza descrição do perfil ao mudar select
document.getElementById('user-role').addEventListener('change', function() {
    document.getElementById('role-description').textContent = roleDescriptions[this.value] || '';
});

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Novo Usuário';
    document.getElementById('user-id').value = '';
    document.getElementById('user-name').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-phone').value = '';
    document.getElementById('user-role').value = 'operator';
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = true;
    document.getElementById('password-label').textContent = 'Senha *';
    document.getElementById('password-hint').style.display = 'none';
    document.getElementById('form-error').style.display = 'none';
    document.getElementById('role-description').textContent = roleDescriptions['operator'] || '';
    document.getElementById('user-modal').style.display = 'flex';
}

function openEditModal(userId) {
    document.getElementById('form-error').style.display = 'none';
    document.getElementById('modal-title').textContent = 'Editar Usuário';
    document.getElementById('user-password').required = false;
    document.getElementById('password-label').textContent = 'Nova senha';
    document.getElementById('password-hint').style.display = 'block';
    document.getElementById('user-password').value = '';

    fetch(baseUrl + '/settings/users/get?id=' + userId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) {
            alert(data.error || 'Erro ao carregar usuário');
            return;
        }
        var u = data.user;
        document.getElementById('user-id').value = u.id;
        document.getElementById('user-name').value = u.name || '';
        document.getElementById('user-email').value = u.email || '';
        document.getElementById('user-phone').value = u.phone || '';
        document.getElementById('user-role').value = u.role || 'operator';
        document.getElementById('role-description').textContent = roleDescriptions[u.role] || '';
        document.getElementById('user-modal').style.display = 'flex';
    })
    .catch(function(err) {
        alert('Erro ao carregar usuário: ' + err.message);
    });
}

function closeModal() {
    document.getElementById('user-modal').style.display = 'none';
}

function submitUserForm(e) {
    e.preventDefault();
    var errorEl = document.getElementById('form-error');
    errorEl.style.display = 'none';

    var userId = document.getElementById('user-id').value;
    var isEdit = !!userId;
    var url = isEdit ? baseUrl + '/settings/users/update' : baseUrl + '/settings/users/store';

    var payload = {
        name: document.getElementById('user-name').value.trim(),
        email: document.getElementById('user-email').value.trim(),
        phone: document.getElementById('user-phone').value.trim(),
        role: document.getElementById('user-role').value,
        password: document.getElementById('user-password').value,
    };
    if (isEdit) payload.id = parseInt(userId);

    var btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.textContent = 'Salvar';
        if (data.success) {
            closeModal();
            window.location.reload();
        } else {
            errorEl.textContent = data.error || 'Erro ao salvar';
            errorEl.style.display = 'block';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Salvar';
        errorEl.textContent = 'Erro de conexão: ' + err.message;
        errorEl.style.display = 'block';
    });

    return false;
}

function toggleUserStatus(userId, userName, isActive) {
    var action = isActive ? 'desativar' : 'ativar';
    if (!confirm('Deseja ' + action + ' o usuário "' + userName + '"?')) return;

    fetch(baseUrl + '/settings/users/toggle-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify({ id: userId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erro ao alterar status');
        }
    })
    .catch(function(err) {
        alert('Erro: ' + err.message);
    });
}

// Fechar modal ao clicar fora
document.getElementById('user-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
