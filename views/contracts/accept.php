<?php
use PixelHub\Services\CompanySettingsService;

// Busca o logo da empresa
$logoUrl = CompanySettingsService::getLogoUrl();
$companyName = CompanySettingsService::getSettings()['company_name'] ?? 'Pixel12 Digital';
$serviceName = $contract['service_name'] ?? 'Serviço Digital';
$contractValue = number_format((float) $contract['contract_value'], 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Prestação de Serviços Digitais - <?= htmlspecialchars($contract['project_name'] ?? 'Projeto') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
            max-width: 900px;
            width: 100%;
            padding: 0;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ============================================
           HEADER INSTITUCIONAL
           ============================================ */
        .header {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            padding: 35px 40px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.1); opacity: 0.6; }
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        
        .security-badge svg {
            width: 14px;
            height: 14px;
        }
        
        .progress-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        
        .progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
        }
        
        .progress-dot.active {
            background: white;
            box-shadow: 0 0 8px rgba(255,255,255,0.8);
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }
        
        .header .subtitle {
            font-size: 15px;
            opacity: 0.95;
            font-weight: 400;
            line-height: 1.6;
        }
        
        /* ============================================
           CONTEÚDO PRINCIPAL (PADDING RESPONSIVO)
           ============================================ */
        .content {
            padding: 20px;
            max-width: 980px;
            margin: 0 auto;
        }
        
        @media (min-width: 768px) {
            .content {
                padding: 32px;
            }
        }
        
        @media (min-width: 1200px) {
            .content {
                padding: 56px;
            }
        }
        
        /* ============================================
           LOGO (MENOS PROTAGONISTA)
           ============================================ */
        .logo-section {
            text-align: center;
            margin: 20px 0 30px;
            padding: 20px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .logo-section img {
            max-height: 50px;
            max-width: 200px;
            height: auto;
            width: auto;
            object-fit: contain;
            opacity: 0.8;
        }
        
        .logo-placeholder {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
            color: #6c757d;
            opacity: 0.7;
        }
        
        .logo-placeholder .logo-code {
            font-family: 'Courier New', monospace;
            color: #667eea;
            font-size: 20px;
        }
        
        /* ============================================
           HIERARQUIA VISUAL - NÍVEL 1 (DECISÃO)
           ============================================ */
        .decision-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border: 2px solid #e3e8ff;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(2, 58, 141, 0.08);
        }
        
        .service-name {
            font-size: 24px;
            font-weight: 700;
            color: #023A8D;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }
        
        .contract-value-wrapper {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-top: 20px;
        }
        
        .contract-value-label {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
        }
        
        .contract-value {
            font-size: 42px;
            font-weight: 800;
            color: #023A8D;
            letter-spacing: -1px;
            line-height: 1;
        }
        
        .contract-value-badge {
            display: inline-block;
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-left: auto;
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.25);
        }
        
        /* ============================================
           HIERARQUIA VISUAL - NÍVEL 2 (CONTEXTO)
           ============================================ */
        .context-section {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .context-section-title {
            font-size: 14px;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 16px 20px;
            align-items: start;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .info-value {
            color: #212529;
            font-size: 15px;
            word-break: break-word;
        }
        
        /* ============================================
           HIERARQUIA VISUAL - NÍVEL 3 (DETALHES)
           ============================================ */
        .details-section {
            margin: 30px 0;
            padding: 25px 0;
            border-top: 1px solid #e9ecef;
        }
        
        .details-section:first-of-type {
            border-top: none;
            padding-top: 0;
        }
        
        .details-section-title {
            color: #495057;
            margin-bottom: 18px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .details-section-title::before {
            content: '';
            width: 3px;
            height: 18px;
            background: #dee2e6;
            border-radius: 2px;
        }
        
        .details-content {
            color: #495057;
            line-height: 1.8;
            font-size: 14px;
        }
        
        .details-content p {
            margin-bottom: 14px;
            text-align: justify;
        }
        
        .details-list {
            list-style: none;
            padding: 0;
            margin: 16px 0;
        }
        
        .details-list li {
            padding: 10px 0 10px 24px;
            position: relative;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .details-list li:last-child {
            border-bottom: none;
        }
        
        .details-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #023A8D;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* ============================================
           CLÁUSULAS DO CONTRATO
           ============================================ */
        .contract-content {
            color: #495057;
            line-height: 1.8;
            font-size: 14px;
        }
        
        .contract-content > div {
            font-family: inherit !important;
        }
        
        .contract-content h2 {
            color: #023A8D !important;
            font-size: 20px !important;
            font-weight: 700 !important;
            text-align: center !important;
            margin: 25px 0 !important;
            padding: 0 !important;
        }
        
        .contract-content h3 {
            color: #023A8D !important;
            font-size: 16px !important;
            font-weight: 700 !important;
            margin: 25px 0 12px !important;
            padding-top: 18px !important;
            border-top: 1px solid #e9ecef !important;
        }
        
        .contract-content h3:first-of-type {
            border-top: none !important;
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
        
        .contract-content p {
            margin-bottom: 12px !important;
            text-align: justify !important;
            color: #495057 !important;
            line-height: 1.8 !important;
            font-size: 14px !important;
        }
        
        .contract-content p strong {
            color: #212529 !important;
            font-weight: 700 !important;
        }
        
        .contract-content ul,
        .contract-content ol {
            margin: 12px 0 !important;
            padding-left: 24px !important;
        }
        
        .contract-content li {
            margin-bottom: 8px !important;
            color: #495057 !important;
            line-height: 1.8 !important;
        }
        
        .contract-content hr {
            margin: 25px 0 !important;
            border: none !important;
            border-top: 1px solid #e9ecef !important;
        }
        
        .contract-content img {
            display: none !important; /* Remove logo duplicado das cláusulas */
        }
        
        .contract-content div[style*="text-align: center"] img {
            display: none !important; /* Remove logo do centro das cláusulas */
        }
        
        /* ============================================
           ALERTA E AÇÕES
           ============================================ */
        .warning-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-left: 6px solid #ffc107;
            padding: 20px 24px;
            border-radius: 12px;
            margin: 35px 0;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.15);
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        
        .warning-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            margin-top: 2px;
        }
        
        .warning-box p {
            color: #856404;
            margin: 0;
            line-height: 1.7;
            font-size: 14px;
            font-weight: 500;
        }
        
        .warning-box strong {
            color: #856404;
            font-weight: 700;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
        }
        
        .btn {
            flex: 1;
            padding: 18px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.3);
        }
        
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #022a70 0%, #023A8D 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(2, 58, 141, 0.4);
        }
        
        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2);
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .btn-icon {
            width: 20px;
            height: 20px;
        }
        
        /* ============================================
           FOOTER COM SELO DE SEGURANÇA
           ============================================ */
        .footer {
            background: #f8f9fa;
            padding: 25px 40px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer-security {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .footer-security-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6c757d;
            font-size: 12px;
            font-weight: 500;
        }
        
        .footer-security-item svg {
            width: 14px;
            height: 14px;
            color: #28a745;
        }
        
        .footer p {
            margin: 4px 0;
            color: #6c757d;
            font-size: 13px;
        }
        
        .footer p:first-of-type {
            font-weight: 500;
            color: #495057;
            margin-top: 10px;
        }
        
        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 25px 20px;
            }
            
            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .header h1 {
                font-size: 22px;
            }
            
            .header .subtitle {
                font-size: 14px;
            }
            
            .content {
                padding: 20px;
            }
            
            .service-name {
                font-size: 20px;
            }
            
            .contract-value {
                font-size: 32px;
            }
            
            .contract-value-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .contract-value-badge {
                margin-left: 0;
                margin-top: 8px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .info-label {
                font-weight: 700;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
            }
            
            .footer-security {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .form-actions {
                display: none;
            }
            
            .warning-box {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER INSTITUCIONAL -->
        <div class="header">
            <div class="header-content">
                <div class="header-top">
                    <div class="security-badge">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Documento com validade jurídica
                    </div>
                    <div class="progress-indicator">
                        <span class="progress-dot active"></span>
                        <span>Etapa 1 de 2 — Revisão</span>
                    </div>
                </div>
                <h1>Contrato de Prestação de Serviços Digitais</h1>
                <p class="subtitle">Este documento formaliza a contratação do serviço descrito abaixo. Ao prosseguir, você confirma que leu, compreendeu e concorda com os termos apresentados.</p>
            </div>
        </div>
        
        <div class="content">
            <!-- LOGO (MENOS PROTAGONISTA) -->
            <div class="logo-section">
                <?php if ($logoUrl): 
                    $fullLogoUrl = strpos($logoUrl, 'http') === 0 ? $logoUrl : pixelhub_url($logoUrl);
                ?>
                    <img src="<?= htmlspecialchars($fullLogoUrl) ?>" alt="<?= htmlspecialchars($companyName) ?>">
                <?php else: ?>
                    <div class="logo-placeholder">
                        <span class="logo-code">&lt;/&gt;</span>
                        <span>PIXEL12 DIGITAL</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- NÍVEL 1: DECISÃO (SERVIÇO + VALOR) -->
            <div class="decision-section">
                <div class="service-name"><?= htmlspecialchars($serviceName) ?></div>
                <div class="contract-value-wrapper">
                    <span class="contract-value-label">Valor do Contrato:</span>
                    <span class="contract-value">R$ <?= $contractValue ?></span>
                    <span class="contract-value-badge">Valor Final</span>
                </div>
            </div>
            
            <!-- NÍVEL 2: CONTEXTO -->
            <div class="context-section">
                <div class="context-section-title">Resumo do Contrato</div>
                <div class="info-grid">
                    <div class="info-label">Cliente:</div>
                    <div class="info-value"><?= htmlspecialchars($contract['tenant_name'] ?? 'N/A') ?></div>
                    
                    <div class="info-label">Projeto:</div>
                    <div class="info-value"><?= htmlspecialchars($contract['project_name'] ?? 'N/A') ?></div>
                    
                    <?php if (!empty($contract['service_name'])): ?>
                    <div class="info-label">Serviço:</div>
                    <div class="info-value"><?= htmlspecialchars($contract['service_name']) ?></div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($contract['service_price']) && (float) $contract['service_price'] != (float) $contract['contract_value']): ?>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
                    <div class="info-grid">
                        <div class="info-label">Preço Original:</div>
                        <div class="info-value" style="text-decoration: line-through; color: #999;">R$ <?= number_format((float) $contract['service_price'], 2, ',', '.') ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- NÍVEL 3: DETALHES -->
            <?php if (!empty($contract['project_description']) || !empty($contract['service_description'])): ?>
            <div class="details-section">
                <h3 class="details-section-title">Detalhes do Projeto</h3>
                <div class="details-content">
                    <?php if (!empty($contract['project_description'])): 
                        // Remove texto sobre assistente de cadastramento (informação interna)
                        $projectDescription = $contract['project_description'];
                        $projectDescription = preg_replace('/Projeto criado via assistente de cadastramento\.?\s*/i', '', $projectDescription);
                        $projectDescription = trim($projectDescription);
                    ?>
                        <?php if (!empty($projectDescription)): ?>
                            <p><?= nl2br(htmlspecialchars($projectDescription)) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($contract['service_description'])): ?>
                        <p><strong>Descrição do Serviço:</strong></p>
                        <p><?= nl2br(htmlspecialchars($contract['service_description'])) ?></p>
                        
                        <?php
                        // Tenta extrair informações estruturadas do texto
                        $description = $contract['service_description'];
                        $includes = [];
                        
                        // Procura por padrões comuns de "inclui"
                        if (preg_match('/[Ii]nclui[:\s]+(.+?)(?:\.|$)/', $description, $matches)) {
                            $includesText = $matches[1];
                            // Divide por vírgulas ou pontos
                            $includes = array_map('trim', preg_split('/[,;]| e /', $includesText));
                            $includes = array_filter($includes, function($item) {
                                return strlen($item) > 3;
                            });
                        }
                        
                        if (!empty($includes)): ?>
                            <ul class="details-list">
                                <?php foreach ($includes as $item): ?>
                                    <li><?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- CLÁUSULAS DO CONTRATO -->
            <?php if (!empty($contract['contract_content'])): ?>
            <div class="details-section">
                <h3 class="details-section-title">Cláusulas do Contrato</h3>
                <div class="contract-content">
                    <?= $contract['contract_content'] ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ALERTA DE ATENÇÃO -->
            <div class="warning-box">
                <svg class="warning-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#856404" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p><strong>Atenção:</strong> Ao aceitar este contrato, você está concordando com os termos e valores especificados acima. Esta ação será registrada com seu IP e data/hora, e não poderá ser desfeita.</p>
            </div>
            
            <!-- FORMULÁRIO DE ACEITE -->
            <form method="POST" action="<?= pixelhub_url('/contract/accept') ?>" id="acceptForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Aceitar Contrato
                    </button>
                </div>
            </form>
        </div>
        
        <!-- FOOTER COM SELO DE SEGURANÇA -->
        <div class="footer">
            <div class="footer-security">
                <div class="footer-security-item">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Documento com validade jurídica
                </div>
                <div class="footer-security-item">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 8V12M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Registro de IP e data na aceitação
                </div>
            </div>
            <p>Este contrato foi gerado automaticamente pelo sistema Pixel Hub</p>
            <p>Em caso de dúvidas, entre em contato conosco</p>
        </div>
    </div>
    
    <script>
        document.getElementById('acceptForm').addEventListener('submit', function(e) {
            if (!confirm('Tem certeza que deseja aceitar este contrato? Esta ação não poderá ser desfeita.')) {
                e.preventDefault();
                return false;
            }
            
            // Desabilita botão para evitar duplo submit
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="btn-icon" style="animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2V6M12 18V22M6 12H2M22 12H18M19.07 19.07L16.24 16.24M19.07 4.93L16.24 7.76M4.93 19.07L7.76 16.24M4.93 4.93L7.76 7.76" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Processando...';
        });
        
        // Adiciona estilo para animação de loading
        const style = document.createElement('style');
        style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    </script>
</body>
</html>
