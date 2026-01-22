<?php
ob_start();
?>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    .modal-header h3 {
        margin: 0;
        color: #023A8D;
    }
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        border: none;
        background: none;
    }
    .close:hover {
        color: #000;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .form-group textarea {
        min-height: 80px;
        resize: vertical;
    }
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary {
        background: #023A8D;
        color: white;
    }
    .btn-primary:hover {
        background: #022a6d;
    }
    .btn-secondary {
        background: #666;
        color: white;
    }
    .btn-secondary:hover {
        background: #555;
    }
    .btn-danger {
        background: #c33;
        color: white;
    }
    .btn-danger:hover {
        background: #a22;
    }
    .btn-small {
        padding: 5px 10px;
        font-size: 12px;
    }
    .password-display {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .password-value {
        font-family: monospace;
        background: #f5f5f5;
        padding: 5px 10px;
        border-radius: 4px;
        margin-right: 5px;
    }
    .copy-btn {
        background: #F7931E;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    .copy-btn:hover {
        background: #d67a0a;
    }
    .view-password-btn {
        background: #666;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    .view-password-btn:hover {
        background: #555;
    }
</style>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Acessos & Links da Agência</h2>
        <p>Gerenciamento de links e credenciais de infraestrutura</p>
    </div>
    <button id="btn-new-access" 
            style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-weight: 600; font-size: 14px;">
        Novo acesso
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'Acesso criado com sucesso!';
            } elseif ($_GET['success'] === 'updated') {
                echo 'Acesso atualizado com sucesso!';
            } elseif ($_GET['success'] === 'deleted') {
                echo 'Acesso excluído com sucesso!';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            Erro: <?= htmlspecialchars($_GET['error']) ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Categoria</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">URL</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Usuário</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Senha</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($shortcuts)): ?>
                <tr>
                    <td colspan="6" style="padding: 20px; text-align: center; color: #666;">
                        Nenhum acesso cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($shortcuts as $shortcut): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($categoryLabels[$shortcut['category']] ?? $shortcut['category']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong><?= htmlspecialchars($shortcut['label']) ?></strong>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php if (!empty($shortcut['url'])): ?>
                            <a href="<?= htmlspecialchars($shortcut['url']) ?>" target="_blank" 
                               style="color: #023A8D; text-decoration: none;">
                                <?= htmlspecialchars($shortcut['url']) ?>
                            </a>
                            <a href="<?= htmlspecialchars($shortcut['url']) ?>" target="_blank" 
                               style="background: #023A8D; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 12px; margin-left: 5px;">
                                Abrir
                            </a>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Sem URL</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= $shortcut['username'] ? htmlspecialchars($shortcut['username']) : '-' ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php if ($shortcut['password_encrypted']): ?>
                            <span class="password-display">
                                <span style="font-family: monospace;">••••••••</span>
                                <button class="copy-btn btn-copy-password" data-id="<?= $shortcut['id'] ?>">
                                    Copiar
                                </button>
                                <button class="view-password-btn btn-view-password" data-id="<?= $shortcut['id'] ?>">
                                    Ver
                                </button>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px;">
                            <button class="btn btn-secondary btn-small btn-edit-shortcut"
                                    data-id="<?= $shortcut['id'] ?>"
                                    data-category="<?= htmlspecialchars($shortcut['category']) ?>"
                                    data-label="<?= htmlspecialchars($shortcut['label']) ?>"
                                    data-url="<?= htmlspecialchars($shortcut['url'] ?? '') ?>"
                                    data-username="<?= htmlspecialchars($shortcut['username'] ?? '') ?>"
                                    data-notes="<?= htmlspecialchars($shortcut['notes'] ?? '') ?>">
                                Editar
                            </button>
                            <button class="btn btn-danger btn-small btn-delete-shortcut"
                                    data-id="<?= $shortcut['id'] ?>"
                                    data-label="<?= htmlspecialchars($shortcut['label']) ?>">
                                Excluir
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Criar/Editar -->
<div id="accessModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Novo Acesso</h3>
            <button class="close" id="btn-close-access-modal">&times;</button>
        </div>
        <form id="accessForm" method="POST" action="<?= pixelhub_url('/owner/shortcuts/store') ?>">
            <input type="hidden" name="id" id="formId">
            
            <div class="form-group">
                <label for="category">Categoria *</label>
                <select name="category" id="category" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($categoryLabels as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="label">Nome do acesso *</label>
                <input type="text" name="label" id="label" required>
            </div>

            <div class="form-group">
                <label for="url">URL</label>
                <input type="url" name="url" id="url" placeholder="Opcional - deixe em branco se não houver URL">
            </div>

            <div class="form-group">
                <label for="username">Usuário / Login</label>
                <input type="text" name="username" id="username" autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" autocomplete="new-password"
                       placeholder="<?= isset($_GET['edit']) ? 'Deixe em branco para manter a senha atual' : '' ?>">
            </div>

            <div class="form-group">
                <label for="notes">Notas</label>
                <textarea name="notes" id="notes"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="btn-cancel-access-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Confirmação do PIN -->
<div id="keyConfirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirmação de Segurança</h3>
            <button class="close" id="btn-close-key-modal">&times;</button>
        </div>
        <form id="keyConfirmForm">
            <div style="padding: 20px 0;">
                <p style="margin-bottom: 15px; color: #666;">
                    Para visualizar a senha, digite o PIN de visualização:
                </p>
                <div class="form-group">
                    <label for="viewPinInput">PIN de Visualização *</label>
                    <input type="password" id="viewPinInput" name="view_pin" autocomplete="off" 
                           inputmode="numeric" pattern="[0-9]*"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;" 
                           placeholder="Informe o PIN configurado no sistema" autofocus required>
                </div>
                <div id="keyError" style="color: #c33; margin-bottom: 15px; display: none;"></div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-key-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Visualizar Senha -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Senha</h3>
            <button class="close" id="btn-close-password-modal">&times;</button>
        </div>
        <div style="padding: 20px 0;">
            <div class="password-display">
                <span class="password-value" id="passwordValue">Carregando...</span>
                <button class="copy-btn" id="btn-copy-password-modal">Copiar</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentPasswordId = null;
    let currentPassword = '';
    let pendingAction = null; // 'view' ou 'copy'
    let pendingId = null;

    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Novo Acesso';
        document.getElementById('accessForm').action = '<?= pixelhub_url('/owner/shortcuts/store') ?>';
        document.getElementById('accessForm').reset();
        document.getElementById('formId').value = '';
        document.getElementById('password').placeholder = '';
        document.getElementById('accessModal').style.display = 'block';
    }

    function openEditModal(shortcut) {
        document.getElementById('modalTitle').textContent = 'Editar Acesso';
        document.getElementById('accessForm').action = '<?= pixelhub_url('/owner/shortcuts/update') ?>';
        document.getElementById('formId').value = shortcut.id;
        document.getElementById('category').value = shortcut.category;
        document.getElementById('label').value = shortcut.label;
        document.getElementById('url').value = shortcut.url;
        document.getElementById('username').value = shortcut.username || '';
        document.getElementById('password').value = '';
        document.getElementById('password').placeholder = 'Deixe em branco para manter a senha atual';
        document.getElementById('notes').value = shortcut.notes || '';
        document.getElementById('accessModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('accessModal').style.display = 'none';
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
        currentPasswordId = null;
        currentPassword = '';
        document.getElementById('passwordValue').textContent = 'Carregando...';
    }

    function closeKeyConfirmModal() {
        document.getElementById('keyConfirmModal').style.display = 'none';
        document.getElementById('viewPinInput').value = '';
        document.getElementById('keyError').style.display = 'none';
        document.getElementById('keyError').textContent = '';
        pendingAction = null;
        pendingId = null;
    }

    function copyPassword(id) {
        // Se já temos a senha em cache, copia direto
        if (currentPasswordId === id && currentPassword) {
            copyToClipboard(currentPassword);
            return;
        }

        // Senão, pede o PIN primeiro
        pendingAction = 'copy';
        pendingId = id;
        document.getElementById('viewPinInput').value = '';
        document.getElementById('keyError').style.display = 'none';
        document.getElementById('keyConfirmModal').style.display = 'block';
        document.getElementById('viewPinInput').focus();
    }

    function viewPassword(id) {
        // Se já temos a senha em cache, mostra direto
        if (currentPasswordId === id && currentPassword) {
            document.getElementById('passwordValue').textContent = currentPassword;
            document.getElementById('passwordModal').style.display = 'block';
            return;
        }

        // Senão, pede o PIN primeiro
        pendingAction = 'view';
        pendingId = id;
        document.getElementById('viewPinInput').value = '';
        document.getElementById('keyError').style.display = 'none';
        document.getElementById('keyConfirmModal').style.display = 'block';
        document.getElementById('viewPinInput').focus();
    }

    function confirmKeyAndView() {
        const viewPin = document.getElementById('viewPinInput').value.trim();
        const errorDiv = document.getElementById('keyError');

        if (!viewPin) {
            errorDiv.textContent = 'Por favor, digite o PIN de visualização';
            errorDiv.style.display = 'block';
            return;
        }

        if (!pendingId || pendingId <= 0) {
            errorDiv.textContent = 'Erro: ID do acesso não identificado';
            errorDiv.style.display = 'block';
            return;
        }

        if (!pendingAction) {
            errorDiv.textContent = 'Erro: Ação não identificada';
            errorDiv.style.display = 'block';
            return;
        }

        // Salva os valores antes de fechar o modal
        const currentPendingId = pendingId;
        const currentPendingAction = pendingAction;

        // Fecha o modal de confirmação (mas não limpa as variáveis ainda)
        document.getElementById('keyConfirmModal').style.display = 'none';
        document.getElementById('viewPinInput').value = '';
        document.getElementById('keyError').style.display = 'none';
        document.getElementById('keyError').textContent = '';

        // Faz a requisição com o PIN
        const formData = new FormData();
        formData.append('id', currentPendingId);
        formData.append('view_pin', viewPin);

        console.log('Enviando requisição:', {
            id: currentPendingId,
            action: currentPendingAction,
            url: '<?= pixelhub_url('/owner/shortcuts/password') ?>'
        });

        fetch('<?= pixelhub_url('/owner/shortcuts/password') ?>', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            let errorData = null;
            
            if (contentType && contentType.includes('application/json')) {
                try {
                    errorData = await response.json();
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                }
            } else {
                const text = await response.text();
                console.error('Resposta não-JSON:', text);
                errorData = { error: text || 'Erro desconhecido' };
            }
            
            if (!response.ok) {
                const errorMsg = errorData?.error || `Erro ao obter senha (status: ${response.status})`;
                console.error('Erro na resposta:', {
                    status: response.status,
                    statusText: response.statusText,
                    data: errorData
                });
                throw new Error(errorMsg);
            }
            
            return errorData || {};
        })
        .then(data => {
            if (data.error) {
                alert('Erro: ' + data.error);
                // Limpa as variáveis em caso de erro
                pendingAction = null;
                pendingId = null;
                return;
            }

            if (!data.password) {
                alert('Erro: Senha não retornada');
                // Limpa as variáveis apenas em caso de erro
                pendingAction = null;
                pendingId = null;
                return;
            }

            currentPasswordId = currentPendingId;
            currentPassword = data.password;

            if (currentPendingAction === 'view') {
                document.getElementById('passwordValue').textContent = data.password;
                document.getElementById('passwordModal').style.display = 'block';
            } else if (currentPendingAction === 'copy') {
                copyToClipboard(data.password);
            }

            // Limpa as variáveis após sucesso
            pendingAction = null;
            pendingId = null;
        })
        .catch(error => {
            console.error('Erro completo:', error);
            alert('Erro ao obter senha: ' + error.message);
            // Limpa as variáveis em caso de erro
            pendingAction = null;
            pendingId = null;
        });
    }

    function copyPasswordFromModal() {
        if (currentPassword) {
            copyToClipboard(currentPassword);
        }
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Senha copiada para a área de transferência!');
        }).catch(err => {
            console.error('Erro ao copiar:', err);
            alert('Erro ao copiar senha');
        });
    }

    function confirmDelete(id, label) {
        if (confirm('Tem certeza que deseja excluir o acesso "' + label + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= pixelhub_url('/owner/shortcuts/delete') ?>';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            form.appendChild(input);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Inicializa todos os event listeners quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Botão "Novo acesso"
        const btnNewAccess = document.getElementById('btn-new-access');
        if (btnNewAccess) {
            btnNewAccess.addEventListener('click', openCreateModal);
        }

        // Botões "Editar"
        document.querySelectorAll('.btn-edit-shortcut').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const shortcut = {
                    id: this.dataset.id,
                    category: this.dataset.category,
                    label: this.dataset.label,
                    url: this.dataset.url,
                    username: this.dataset.username || '',
                    notes: this.dataset.notes || ''
                };
                openEditModal(shortcut);
            });
        });

        // Botões "Excluir"
        document.querySelectorAll('.btn-delete-shortcut').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = parseInt(this.dataset.id);
                const label = this.dataset.label;
                confirmDelete(id, label);
            });
        });

        // Botões "Copiar" senha
        document.querySelectorAll('.btn-copy-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = parseInt(this.dataset.id);
                copyPassword(id);
            });
        });

        // Botões "Ver" senha
        document.querySelectorAll('.btn-view-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = parseInt(this.dataset.id);
                viewPassword(id);
            });
        });

        // Botão fechar modal de acesso
        const btnCloseAccessModal = document.getElementById('btn-close-access-modal');
        if (btnCloseAccessModal) {
            btnCloseAccessModal.addEventListener('click', closeModal);
        }

        // Botão cancelar modal de acesso
        const btnCancelAccessModal = document.getElementById('btn-cancel-access-modal');
        if (btnCancelAccessModal) {
            btnCancelAccessModal.addEventListener('click', closeModal);
        }

        // Botão fechar modal de confirmação de PIN
        const btnCloseKeyModal = document.getElementById('btn-close-key-modal');
        if (btnCloseKeyModal) {
            btnCloseKeyModal.addEventListener('click', closeKeyConfirmModal);
        }

        // Botão cancelar modal de confirmação de PIN
        const btnCancelKeyModal = document.getElementById('btn-cancel-key-modal');
        if (btnCancelKeyModal) {
            btnCancelKeyModal.addEventListener('click', closeKeyConfirmModal);
        }

        // Botão fechar modal de senha
        const btnClosePasswordModal = document.getElementById('btn-close-password-modal');
        if (btnClosePasswordModal) {
            btnClosePasswordModal.addEventListener('click', closePasswordModal);
        }

        // Botão copiar senha do modal
        const btnCopyPasswordModal = document.getElementById('btn-copy-password-modal');
        if (btnCopyPasswordModal) {
            btnCopyPasswordModal.addEventListener('click', copyPasswordFromModal);
        }

        // Formulário de confirmação de PIN
        const keyConfirmForm = document.getElementById('keyConfirmForm');
        if (keyConfirmForm) {
            keyConfirmForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                confirmKeyAndView();
                return false;
            });
        }

        // Permite Enter no campo de PIN
        const pinInput = document.getElementById('viewPinInput');
        if (pinInput) {
            pinInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    confirmKeyAndView();
                }
            });
        }

        // Fecha modal ao clicar fora
        window.onclick = function(event) {
            const accessModal = document.getElementById('accessModal');
            const passwordModal = document.getElementById('passwordModal');
            const keyConfirmModal = document.getElementById('keyConfirmModal');
            if (event.target === accessModal) {
                closeModal();
            }
            if (event.target === passwordModal) {
                closePasswordModal();
            }
            if (event.target === keyConfirmModal) {
                closeKeyConfirmModal();
            }
        };
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Acessos & Links';
require __DIR__ . '/../layout/main.php';
?>

