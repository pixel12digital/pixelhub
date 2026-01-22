<?php
ob_start();

$settings = $settings ?? null;
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Dados da Empresa</h2>
        <p>Configure os dados da empresa que serão usados em contratos e documentos</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            Configurações atualizadas com sucesso!
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?= htmlspecialchars(urldecode($_GET['error'])) ?>
        </p>
    </div>
<?php endif; ?>

<?php if (!$settings): ?>
    <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Tabela não encontrada</h3>
        <p style="color: #856404; margin: 0 0 10px 0;">
            A tabela de configurações da empresa ainda não foi criada. Execute a migration:
        </p>
        <code style="background: #fff; padding: 8px; border-radius: 4px; display: block; margin-top: 10px;">
            php database/migrate.php
        </code>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url('/settings/company') ?>" enctype="multipart/form-data">
        <h3 style="margin: 0 0 20px 0; color: #023A8D; font-size: 18px; border-bottom: 2px solid #023A8D; padding-bottom: 10px;">
            Dados Básicos
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="company_name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome da Empresa *</label>
                <input type="text" 
                       id="company_name" 
                       name="company_name" 
                       value="<?= htmlspecialchars($settings['company_name'] ?? 'Pixel12 Digital') ?>" 
                       required
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="company_name_fantasy" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome Fantasia</label>
                <input type="text" 
                       id="company_name_fantasy" 
                       name="company_name_fantasy" 
                       value="<?= htmlspecialchars($settings['company_name_fantasy'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="cnpj" style="display: block; margin-bottom: 5px; font-weight: 600;">CNPJ</label>
                <input type="text" 
                       id="cnpj" 
                       name="cnpj" 
                       value="<?= htmlspecialchars($settings['cnpj'] ?? '') ?>" 
                       placeholder="00.000.000/0000-00"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="ie" style="display: block; margin-bottom: 5px; font-weight: 600;">Inscrição Estadual (IE)</label>
                <input type="text" 
                       id="ie" 
                       name="ie" 
                       value="<?= htmlspecialchars($settings['ie'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="im" style="display: block; margin-bottom: 5px; font-weight: 600;">Inscrição Municipal (IM)</label>
                <input type="text" 
                       id="im" 
                       name="im" 
                       value="<?= htmlspecialchars($settings['im'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <h3 style="margin: 30px 0 20px 0; color: #023A8D; font-size: 18px; border-bottom: 2px solid #023A8D; padding-bottom: 10px;">
            Endereço
        </h3>

        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="address_street" style="display: block; margin-bottom: 5px; font-weight: 600;">Rua</label>
                <input type="text" 
                       id="address_street" 
                       name="address_street" 
                       value="<?= htmlspecialchars($settings['address_street'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="address_number" style="display: block; margin-bottom: 5px; font-weight: 600;">Número</label>
                <input type="text" 
                       id="address_number" 
                       name="address_number" 
                       value="<?= htmlspecialchars($settings['address_number'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="address_complement" style="display: block; margin-bottom: 5px; font-weight: 600;">Complemento</label>
                <input type="text" 
                       id="address_complement" 
                       name="address_complement" 
                       value="<?= htmlspecialchars($settings['address_complement'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="address_neighborhood" style="display: block; margin-bottom: 5px; font-weight: 600;">Bairro</label>
                <input type="text" 
                       id="address_neighborhood" 
                       name="address_neighborhood" 
                       value="<?= htmlspecialchars($settings['address_neighborhood'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="address_city" style="display: block; margin-bottom: 5px; font-weight: 600;">Cidade</label>
                <input type="text" 
                       id="address_city" 
                       name="address_city" 
                       value="<?= htmlspecialchars($settings['address_city'] ?? '') ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="address_state" style="display: block; margin-bottom: 5px; font-weight: 600;">Estado (UF)</label>
                <input type="text" 
                       id="address_state" 
                       name="address_state" 
                       value="<?= htmlspecialchars($settings['address_state'] ?? '') ?>" 
                       placeholder="SP"
                       maxlength="2"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; text-transform: uppercase;">
            </div>
            <div>
                <label for="address_cep" style="display: block; margin-bottom: 5px; font-weight: 600;">CEP</label>
                <input type="text" 
                       id="address_cep" 
                       name="address_cep" 
                       value="<?= htmlspecialchars($settings['address_cep'] ?? '') ?>" 
                       placeholder="00000-000"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <h3 style="margin: 30px 0 20px 0; color: #023A8D; font-size: 18px; border-bottom: 2px solid #023A8D; padding-bottom: 10px;">
            Contato
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="phone" style="display: block; margin-bottom: 5px; font-weight: 600;">Telefone</label>
                <input type="text" 
                       id="phone" 
                       name="phone" 
                       value="<?= htmlspecialchars($settings['phone'] ?? '') ?>" 
                       placeholder="(00) 00000-0000"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="<?= htmlspecialchars($settings['email'] ?? '') ?>" 
                       placeholder="contato@empresa.com.br"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label for="website" style="display: block; margin-bottom: 5px; font-weight: 600;">Website</label>
                <input type="url" 
                       id="website" 
                       name="website" 
                       value="<?= htmlspecialchars($settings['website'] ?? '') ?>" 
                       placeholder="https://www.empresa.com.br"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <h3 style="margin: 30px 0 20px 0; color: #023A8D; font-size: 18px; border-bottom: 2px solid #023A8D; padding-bottom: 10px;">
            Logo da Empresa
        </h3>

        <div style="margin-bottom: 20px;">
            <?php if (!empty($settings['logo_path']) || !empty($settings['logo_url'])): ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Logo Atual</label>
                    <?php 
                    $logoUrl = $settings['logo_url'] ?? $settings['logo_path'] ?? '';
                    if (!empty($logoUrl)): 
                        if (strpos($logoUrl, 'http') === 0) {
                            $fullLogoUrl = $logoUrl;
                        } else {
                            $fullLogoUrl = pixelhub_url($logoUrl);
                        }
                    ?>
                        <div style="margin-top: 10px;">
                            <img src="<?= htmlspecialchars($fullLogoUrl) ?>" 
                                 alt="Logo da Empresa" 
                                 style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 10px; background: white; border-radius: 4px;">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: 15px;">
                <label for="logo_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Upload de Logo</label>
                <input type="file" 
                       id="logo_file" 
                       name="logo_file" 
                       accept="image/*"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                <small style="color: #666; font-size: 12px;">Formatos aceitos: JPG, PNG, GIF, SVG, WEBP. Tamanho recomendado: máximo 500KB</small>
            </div>

            <div>
                <label for="logo_url" style="display: block; margin-bottom: 5px; font-weight: 600;">OU URL do Logo</label>
                <input type="url" 
                       id="logo_url" 
                       name="logo_url" 
                       value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" 
                       placeholder="https://www.exemplo.com/logo.png"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                <small style="color: #666; font-size: 12px;">Se preferir, informe uma URL externa do logo</small>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                Salvar Configurações
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = 'Dados da Empresa';
require __DIR__ . '/../layout/main.php';
?>

