<?php
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Templates de Cobrança</h2>
    <p class="text-muted" style="margin: 5px 0 0;">Visualização dos templates usados no sistema (read-only)</p>
</div>

<!-- Filtros -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end; flex-wrap: wrap;">
    <div>
        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Canal</label>
        <select id="channelFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="all" <?= $channel === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="whatsapp" <?= $channel === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            <option value="e-mail" <?= $channel === 'e-mail' ? 'selected' : '' ?>>E-mail</option>
        </select>
    </div>
    <div style="flex: 1; min-width: 200px;">
        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Buscar</label>
        <input type="text" id="searchFilter" placeholder="Buscar por nome ou estágio..." 
               value="<?= htmlspecialchars($search) ?>"
               style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
    </div>
    <button onclick="filterTemplates()" style="padding: 8px 16px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
        Filtrar
    </button>
</div>

<!-- Tabela de Templates -->
<div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #555;">Canal</th>
                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #555;">Estágio</th>
                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #555;">Nome</th>
                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #555;">Formato</th>
                <th style="padding: 12px 16px; text-align: center; font-size: 13px; font-weight: 600; color: #555;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="5" style="padding: 30px; text-align: center; color: #999;">
                        Nenhum template encontrado
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px 16px;">
                            <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; background: <?= $template['channel'] === 'WhatsApp' ? '#25D366' : '#023A8D' ?>;">
                                <?= htmlspecialchars($template['channel']) ?>
                            </span>
                        </td>
                        <td style="padding: 10px 16px;">
                            <code style="background: #f1f3f5; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?= htmlspecialchars($template['stage']) ?></code>
                        </td>
                        <td style="padding: 10px 16px; font-size: 14px;"><?= htmlspecialchars($template['label']) ?></td>
                        <td style="padding: 10px 16px;">
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; background: #e9ecef; color: #555;">
                                <?= htmlspecialchars($template['format']) ?>
                            </span>
                        </td>
                        <td style="padding: 10px 16px; text-align: center;">
                            <button onclick="viewTemplate('<?= htmlspecialchars($template['key']) ?>')"
                                    style="padding: 5px 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                Ver
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Visualização -->
<div id="templateModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 8px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #eee;">
            <h3 id="templateModalTitle" style="margin: 0; font-size: 18px;">Template</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #999;">&times;</button>
        </div>
        <div id="templateContent" style="padding: 20px;">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 16px 20px; border-top: 1px solid #eee;">
            <button onclick="closeModal()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Fechar</button>
            <button id="copyBtn" onclick="copyTemplate()" style="padding: 8px 16px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Copiar
            </button>
        </div>
    </div>
</div>

<style>
.template-body {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
    font-size: 13px;
    line-height: 1.5;
}
.placeholder-chip {
    display: inline-block;
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 15px;
    padding: 4px 10px;
    margin: 2px;
    font-size: 12px;
    font-family: monospace;
}
</style>

<script>
let currentTemplateData = null;

function filterTemplates() {
    const channel = document.getElementById('channelFilter').value;
    const search = document.getElementById('searchFilter').value;
    const params = new URLSearchParams({ channel: channel, search: search });
    window.location.href = '/billing/templates?' + params.toString();
}

function viewTemplate(key) {
    fetch('/billing/templates/view?key=' + encodeURIComponent(key))
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Erro: ' + data.error); return; }
            
            currentTemplateData = data.template;
            const t = data.template;
            
            document.getElementById('templateModalTitle').textContent = t.channel + ' — ' + t.label;
            
            let html = '';
            
            if (t.subject) {
                html += '<div style="margin-bottom: 15px;">';
                html += '<label style="display: block; font-weight: 600; margin-bottom: 5px;">Assunto:</label>';
                html += '<div class="template-body">' + escapeHtml(t.subject) + '</div>';
                html += '</div>';
            }
            
            html += '<div style="margin-bottom: 15px;">';
            html += '<label style="display: block; font-weight: 600; margin-bottom: 5px;">Mensagem:</label>';
            html += '<div class="template-body">' + escapeHtml(t.body) + '</div>';
            html += '</div>';
            
            html += '<div style="margin-bottom: 15px;">';
            html += '<label style="display: block; font-weight: 600; margin-bottom: 5px;">Variáveis Disponíveis:</label>';
            html += '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px;">';
            Object.entries(t.placeholders).forEach(function([k, desc]) {
                html += '<span class="placeholder-chip" title="' + escapeHtml(desc) + '">' + escapeHtml(k) + '</span> ';
            });
            html += '</div></div>';
            
            document.getElementById('templateContent').innerHTML = html;
            document.getElementById('templateModal').style.display = 'flex';
        })
        .catch(err => { console.error(err); alert('Erro ao carregar template'); });
}

function closeModal() {
    document.getElementById('templateModal').style.display = 'none';
}

function copyTemplate() {
    if (!currentTemplateData) return;
    const t = currentTemplateData;
    const text = t.subject ? 'Assunto: ' + t.subject + '\n\n' + t.body : t.body;
    navigator.clipboard.writeText(text).then(function() {
        const btn = document.getElementById('copyBtn');
        btn.textContent = 'Copiado!';
        setTimeout(function() { btn.textContent = 'Copiar'; }, 2000);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.getElementById('channelFilter').addEventListener('change', filterTemplates);
document.getElementById('searchFilter').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') filterTemplates();
});

document.getElementById('templateModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php
$content = ob_get_clean();
$title = 'Templates de Cobrança';
require __DIR__ . '/../layout/main.php';
?>
