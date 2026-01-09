<?php
ob_start();
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Clientes</h2>
        <p>Lista de todos os clientes</p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <form method="get" action="<?= pixelhub_url('/tenants') ?>" style="display: flex; align-items: center; gap: 8px; margin-right: 10px;">
            <input
                type="text"
                name="search"
                class="form-control form-control-sm"
                placeholder="Buscar por nome, email ou WhatsApp..."
                value="<?= htmlspecialchars($search ?? '') ?>"
                style="min-width: 260px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                onkeypress="if(event.key === 'Enter') { this.form.submit(); return false; }"
            >
            <button type="submit" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                Buscar
            </button>
            <?php if (!empty($search ?? '')): ?>
                <a href="<?= pixelhub_url('/tenants') ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                    Limpar
                </a>
            <?php endif; ?>
        </form>
        <div style="position: relative; display: inline-block;" id="newClientMenuContainer">
            <button type="button" 
                    onclick="toggleNewClientMenu()"
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                Novo Cliente
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div id="newClientMenu" style="display: none; position: absolute; right: 0; top: calc(100% + 8px); background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 220px; z-index: 1000; overflow: hidden;">
                <a href="<?= pixelhub_url('/tenants/create') ?>" 
                   style="display: block; padding: 12px 16px; color: #333; text-decoration: none; font-size: 14px; transition: background 0.2s;"
                   onmouseover="this.style.background='#f0f0f0'"
                   onmouseout="this.style.background='white'">
                    Novo Cliente
                </a>
                <a href="<?= pixelhub_url('/tenants/create?create_hosting=1') ?>" 
                   style="display: block; padding: 12px 16px; color: #333; text-decoration: none; font-size: 14px; transition: background 0.2s; border-top: 1px solid #eee;"
                   onmouseover="this.style.background='#f0f0f0'"
                   onmouseout="this.style.background='white'">
                    Novo Cliente + Hospedagem
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'Cliente criado com sucesso!';
            } elseif ($_GET['success'] === 'deleted') {
                echo 'Cliente excluído com sucesso!';
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
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Email</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">WhatsApp</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Sites</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Backups</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody id="tenants-table-body">
            <?php include __DIR__ . '/_table_rows.php'; ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-2" id="tenants-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 12px 0;">
    <div class="text-muted small" style="color: #666; font-size: 14px;">
        <?php if (($total ?? 0) > 0): ?>
            Exibindo <?= (($page ?? 1) - 1) * ($perPage ?? 25) + 1 ?>
            –
            <?= min(($page ?? 1) * ($perPage ?? 25), ($total ?? 0)) ?>
            de <?= $total ?? 0 ?> clientes
        <?php else: ?>
            Nenhum cliente encontrado.
        <?php endif; ?>
    </div>

    <div id="tenants-pagination-controls">
        <?php include __DIR__ . '/_pagination.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector('input[name="search"]');
    const tableBody = document.getElementById('tenants-table-body');
    const paginationContainer = document.getElementById('tenants-pagination');
    const paginationControls = document.getElementById('tenants-pagination-controls');
    const form = searchInput ? searchInput.form : null;

    if (!searchInput || !tableBody || !form) {
        return;
    }

    let debounceTimer = null;
    let lastQuery = searchInput.value.trim();

    function fetchTenants(query) {
        const params = new URLSearchParams(new FormData(form));
        params.set('search', query);
        params.set('page', '1'); // Sempre volta para página 1 no AJAX
        params.set('ajax', '1');

        // Loading simples
        tableBody.innerHTML = '<tr><td colspan="7" style="padding: 20px; text-align: center; color: #666;">Buscando...</td></tr>';

        fetch(form.action + '?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (typeof data.html === 'string') {
                    tableBody.innerHTML = data.html;
                } else {
                    console.error('Resposta inválida em /tenants AJAX', data);
                }

                // Atualiza paginação se vier no JSON
                if (paginationControls && typeof data.paginationHtml === 'string') {
                    paginationControls.innerHTML = data.paginationHtml;
                }

                // Atualiza texto "Exibindo X–Y de Z"
                if (paginationContainer && data.total !== undefined) {
                    const total = data.total || 0;
                    const page = data.page || 1;
                    const perPage = 25;
                    const start = (page - 1) * perPage + 1;
                    const end = Math.min(page * perPage, total);
                    
                    const infoText = total > 0 
                        ? `Exibindo ${start} – ${end} de ${total} clientes`
                        : 'Nenhum cliente encontrado.';
                    
                    const infoDiv = paginationContainer.querySelector('.text-muted.small');
                    if (infoDiv) {
                        infoDiv.textContent = infoText;
                    }
                }
            })
            .catch(err => {
                console.error('Erro na busca AJAX de clientes', err);
                tableBody.innerHTML = '<tr><td colspan="7" style="padding: 20px; text-align: center; color: #c33;">Erro ao buscar clientes. Tente recarregar a página.</td></tr>';
            });
    }

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        // Se apagou tudo ou tem 3+ caracteres, dispara busca
        const shouldSearch = (query.length === 0) || (query.length >= 3);

        if (!shouldSearch) {
            return;
        }

        // Evita chamadas redundantes
        if (query === lastQuery) {
            return;
        }
        lastQuery = query;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            fetchTenants(query);
        }, 300); // debounce de 300ms
    });

    // Fallback: se usuário apertar Enter, mantém comportamento normal do form (submit GET completo).
    // O onkeypress já está no HTML e funciona normalmente.
});

// Função para controlar menu dropdown de Novo Cliente
function toggleNewClientMenu() {
    const menu = document.getElementById('newClientMenu');
    if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
}

    // Fecha o menu ao clicar fora dele
document.addEventListener('click', function(event) {
    const container = document.getElementById('newClientMenuContainer');
    const menu = document.getElementById('newClientMenu');
    if (container && menu && !container.contains(event.target)) {
        menu.style.display = 'none';
    }
});

    // Remove title attributes from action buttons to prevent native tooltips
    function removeNativeTooltips() {
        document.querySelectorAll('td:last-child .btn').forEach(function(button) {
            if (button.hasAttribute('title')) {
                button.removeAttribute('title');
            }
        });
    }
    
    // Remove tooltips on initial load
    removeNativeTooltips();
    
    // Remove tooltips after AJAX updates
    const originalFetchTenants = window.fetchTenants;
    if (typeof originalFetchTenants === 'undefined') {
        // Observer para detectar mudanças no DOM após AJAX
        const observer = new MutationObserver(function(mutations) {
            removeNativeTooltips();
        });
        
        const tableBody = document.getElementById('tenants-table-body');
        if (tableBody) {
            observer.observe(tableBody, { childList: true, subtree: true });
        }
    }
</script>

<?php
$content = ob_get_clean();
$title = 'Clientes';

// Inclui modal de WhatsApp antes do layout
ob_start();
require __DIR__ . '/whatsapp_modal.php';
$modalContent = ob_get_clean();
$content .= $modalContent;

require __DIR__ . '/../layout/main.php';
?>

