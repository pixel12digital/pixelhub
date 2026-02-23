<?php
ob_start();
?>

<div class="content-header">
    <div>
        <h2>CNPJ.ws API</h2>
        <p>Configure a chave de API para usar a Prospecção Ativa por CNAE (busca de empresas por segmento e cidade)</p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/prospecting?source=cnpjws') ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#023A8D;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Ir para Prospecção por CNAE
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #c3e6cb;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Configuração salva com sucesso!')) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['warning'])): ?>
<div style="background:#fff3cd;color:#856404;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #ffeaa7;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Atenção')) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #f5c6cb;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Erro ao salvar configuração.')) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

    <!-- Formulário principal -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:28px;">
        <h3 style="margin:0 0 6px;font-size:16px;color:#1e293b;">Chave de API</h3>
        <p style="margin:0 0 24px;font-size:13px;color:#64748b;">
            A chave é armazenada de forma criptografada no banco de dados.
        </p>

        <!-- Status atual -->
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;margin-bottom:24px;<?= $hasKey ? 'background:#f0fdf4;border:1px solid #bbf7d0;' : 'background:#fef9c3;border:1px solid #fde68a;' ?>">
            <?php if ($hasKey): ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#15803d;">Chave configurada</div>
                    <div style="font-size:12px;color:#166534;font-family:monospace;"><?= htmlspecialchars($maskedKey ?? '') ?></div>
                </div>
            <?php else: ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div style="font-size:13px;font-weight:600;color:#92400e;">Nenhuma chave configurada</div>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= pixelhub_url('/settings/cnpjws/save') ?>">
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                    <?= $hasKey ? 'Nova chave de API (deixe em branco para manter a atual)' : 'Chave de API do CNPJ.ws' ?>
                </label>
                <div style="position:relative;">
                    <input type="password" name="api_key" id="apiKeyInput"
                           placeholder="Cole aqui o token Bearer do CNPJ.ws..."
                           style="width:100%;padding:10px 40px 10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:monospace;box-sizing:border-box;"
                           <?= !$hasKey ? 'required' : '' ?>>
                    <button type="button" onclick="toggleApiKeyVisibility()"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;padding:0;">
                        <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div style="display:flex;gap:10px;align-items:center;">
                <button type="submit" style="padding:10px 20px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                    Salvar Chave
                </button>
                <?php if ($hasKey): ?>
                <button type="button" onclick="testApiKey()" id="testBtn"
                        style="padding:10px 20px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                    Testar Conexão
                </button>
                <?php endif; ?>
            </div>
        </form>

        <div id="testResult" style="display:none;margin-top:16px;padding:12px 16px;border-radius:6px;font-size:13px;"></div>
    </div>

    <!-- Painel lateral: instruções -->
    <div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
            <h4 style="margin:0 0 12px;font-size:14px;color:#1e293b;display:flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#023A8D" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Como obter a chave
            </h4>
            <ol style="margin:0;padding-left:18px;font-size:12px;color:#475569;line-height:1.8;">
                <li>Acesse <a href="https://www.cnpj.ws" target="_blank" style="color:#023A8D;">cnpj.ws</a> e faça login</li>
                <li>Vá em <strong>Minha Conta → API Keys</strong></li>
                <li>Crie ou copie o <strong>token Bearer</strong></li>
                <li>Cole o token no campo ao lado e salve</li>
            </ol>
        </div>

        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:20px;margin-bottom:16px;">
            <h4 style="margin:0 0 10px;font-size:14px;color:#1e40af;display:flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                O que esta integração faz
            </h4>
            <p style="margin:0;font-size:12px;color:#1e40af;line-height:1.7;">
                Permite buscar empresas ativas na Receita Federal por <strong>CNAE + cidade/estado</strong>.
                Ideal para prospecção B2B segmentada por segmento de negócio e região.
            </p>
        </div>

        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:20px;">
            <h4 style="margin:0 0 10px;font-size:14px;color:#9a3412;display:flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c2410c" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Plano e limites
            </h4>
            <p style="margin:0;font-size:12px;color:#7c2d12;line-height:1.7;">
                Verifique os limites do seu plano em <a href="https://www.cnpj.ws/planos" target="_blank" style="color:#9a3412;font-weight:600;">cnpj.ws/planos</a>.
                Cada busca consome créditos conforme o número de resultados retornados.
            </p>
        </div>
    </div>
</div>

<script>
function toggleApiKeyVisibility() {
    const input = document.getElementById('apiKeyInput');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

function testApiKey() {
    const btn    = document.getElementById('testBtn');
    const result = document.getElementById('testResult');
    btn.disabled = true;
    btn.textContent = 'Testando...';
    result.style.display = 'none';

    fetch('<?= pixelhub_url('/settings/cnpjws/test') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    })
    .then(r => r.json())
    .then(data => {
        result.style.display = 'block';
        if (data.success) {
            result.style.background = '#f0fdf4';
            result.style.border     = '1px solid #bbf7d0';
            result.style.color      = '#15803d';
            result.innerHTML = '✓ ' + (data.message || 'Conexão bem-sucedida!');
        } else {
            result.style.background = '#fef2f2';
            result.style.border     = '1px solid #fecaca';
            result.style.color      = '#dc2626';
            result.innerHTML = '✗ ' + (data.message || 'Falha na conexão.');
        }
    })
    .catch(() => {
        result.style.display = 'block';
        result.style.background = '#fef2f2';
        result.style.border     = '1px solid #fecaca';
        result.style.color      = '#dc2626';
        result.innerHTML = '✗ Erro de comunicação com o servidor.';
    })
    .finally(() => {
        btn.disabled    = false;
        btn.textContent = 'Testar Conexão';
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
