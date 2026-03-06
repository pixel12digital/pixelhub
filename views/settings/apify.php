<?php ob_start(); ?>

<div class="content-header" style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px;">Configurações — Apify</h2>
    <p style="margin:0;font-size:13px;color:#64748b;">Configure a chave API do Apify para prospecção no Instagram por hashtag.</p>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #c3e6cb;">
    ✓ <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Operação realizada com sucesso!')) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #f5c6cb;">
    ✗ <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Ocorreu um erro.')) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['warning'])): ?>
<div style="background:#fff3cd;color:#856404;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #ffc107;">
    ⚠ <?= htmlspecialchars(urldecode($_GET['message'] ?? '')) ?>
</div>
<?php endif; ?>

<div style="display:grid;gap:24px;max-width:700px;">

    <!-- Card: Chave API -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <div style="width:40px;height:40px;background:linear-gradient(135deg,#ff6154,#ff9b40);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">🤖</div>
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;color:#1e293b;">Apify API Key</h3>
                <p style="margin:0;font-size:12px;color:#64748b;">Necessária para prospecção no Instagram por hashtag.</p>
            </div>
            <?php if ($hasKey): ?>
            <span style="margin-left:auto;padding:4px 12px;background:#dcfce7;color:#15803d;border-radius:20px;font-size:12px;font-weight:600;">✓ Configurada</span>
            <?php else: ?>
            <span style="margin-left:auto;padding:4px 12px;background:#fef3c7;color:#92400e;border-radius:20px;font-size:12px;font-weight:600;">⚠ Não configurada</span>
            <?php endif; ?>
        </div>

        <?php if ($hasKey && $maskedKey): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <span style="font-size:12px;color:#15803d;font-weight:600;">Chave atual:</span>
                <code style="font-size:13px;color:#1e293b;margin-left:8px;"><?= htmlspecialchars($maskedKey) ?></code>
            </div>
            <button onclick="testApiKey()" style="padding:6px 14px;background:#15803d;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Testar Conexão</button>
        </div>
        <div id="testResult" style="display:none;margin-bottom:16px;padding:10px 14px;border-radius:6px;font-size:13px;"></div>
        <?php endif; ?>

        <form method="POST" action="<?= pixelhub_url('/settings/apify/save') ?>">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">
                <?= $hasKey ? 'Atualizar chave' : 'Informe a chave API' ?> <span style="color:#dc2626;">*</span>
            </label>
            <div style="display:flex;gap:10px;">
                <input type="password" name="api_key" required
                       placeholder="apify_api_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                       style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:monospace;">
                <button type="submit" style="padding:9px 18px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                    <?= $hasKey ? 'Atualizar' : 'Salvar' ?>
                </button>
            </div>
            <p style="margin:6px 0 0;font-size:11px;color:#94a3b8;">
                A chave é armazenada criptografada. Obtenha em
                <a href="https://console.apify.com/account/integrations" target="_blank" style="color:#023A8D;">console.apify.com → Settings → Integrations</a>.
            </p>
        </form>
    </div>

    <!-- Card: Como usar -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <h3 style="margin:0 0 16px;font-size:15px;color:#1e293b;">Como funciona a prospecção Instagram</h3>
        <div style="display:grid;gap:12px;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <span style="width:24px;height:24px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#1d4ed8;flex-shrink:0;">1</span>
                <div>
                    <strong style="font-size:13px;color:#1e293b;">Criar receita com hashtags</strong>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Ex: <code>#imobiliaria</code>, <code>#corretordeimoveis</code>, <code>#lancamento</code></p>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <span style="width:24px;height:24px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#1d4ed8;flex-shrink:0;">2</span>
                <div>
                    <strong style="font-size:13px;color:#1e293b;">Buscar perfis únicos</strong>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Apify busca os autores dos posts com essas hashtags e retorna perfis únicos.</p>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <span style="width:24px;height:24px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#1d4ed8;flex-shrink:0;">3</span>
                <div>
                    <strong style="font-size:13px;color:#1e293b;">Enriquecer com telefone</strong>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Para perfis business: busca o <strong>telefone público</strong> do botão "Contato" do Instagram.</p>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <span style="width:24px;height:24px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#1d4ed8;flex-shrink:0;">4</span>
                <div>
                    <strong style="font-size:13px;color:#1e293b;">Converter em Lead</strong>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b;">1 clique → Lead criado no CRM + pronto para enviar mensagem no Inbox WhatsApp.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Custo estimado -->
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:20px 24px;">
        <h3 style="margin:0 0 12px;font-size:14px;color:#92400e;">Custo estimado Apify</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px;">
            <div><strong>Crédito gratuito mensal:</strong></div><div>$5 (já incluso em toda conta)</div>
            <div><strong>~15.000 perfis/mês:</strong></div><div style="color:#15803d;font-weight:600;">$0 (coberto pelo gratuito)</div>
            <div><strong>Uso moderado:</strong></div><div>~$5–10/mês</div>
        </div>
        <p style="margin:12px 0 0;font-size:12px;color:#92400e;">
            Crie conta gratuita em <a href="https://apify.com" target="_blank" style="color:#92400e;font-weight:600;">apify.com</a> e obtenha a chave API sem cartão de crédito.
        </p>
    </div>
</div>

<script>
function testApiKey() {
    const btn = event.target;
    const orig = btn.textContent;
    const res = document.getElementById('testResult');
    btn.textContent = 'Testando...';
    btn.disabled = true;

    fetch('<?= pixelhub_url('/settings/apify/test') ?>', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        res.style.display = 'block';
        if (data.success) {
            res.style.background = '#d4edda';
            res.style.color = '#155724';
            res.style.border = '1px solid #c3e6cb';
            res.textContent = '✓ ' + data.message;
        } else {
            res.style.background = '#f8d7da';
            res.style.color = '#721c24';
            res.style.border = '1px solid #f5c6cb';
            res.textContent = '✗ ' + (data.message || 'Erro desconhecido');
        }
    })
    .catch(() => {
        res.style.display = 'block';
        res.style.background = '#f8d7da';
        res.style.color = '#721c24';
        res.textContent = '✗ Erro ao testar a conexão.';
    })
    .finally(() => {
        btn.textContent = orig;
        btn.disabled = false;
    });
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Configurações — Apify';
include __DIR__ . '/../layout/main.php';
?>
