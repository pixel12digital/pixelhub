<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Aceito - <?= htmlspecialchars($contract['project_name'] ?? 'Projeto') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }
        
        h1 {
            color: #28a745;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .contract-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: 600;
            color: #333;
        }
        
        .summary-value {
            color: #666;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>Contrato Aceito com Sucesso!</h1>
        <p>Obrigado por aceitar o contrato. Seu aceite foi registrado em nosso sistema.</p>
        
        <div class="contract-summary">
            <div class="summary-row">
                <span class="summary-label">Projeto:</span>
                <span class="summary-value"><?= htmlspecialchars($contract['project_name'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Valor:</span>
                <span class="summary-value"><strong>R$ <?= number_format((float) $contract['contract_value'], 2, ',', '.') ?></strong></span>
            </div>
            <?php if (!empty($contract['accepted_at'])): ?>
            <div class="summary-row">
                <span class="summary-label">Data de Aceite:</span>
                <span class="summary-value"><?= date('d/m/Y H:i', strtotime($contract['accepted_at'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <p style="color: #28a745; font-weight: 600;">Você receberá um e-mail de confirmação em breve.</p>
        
        <div class="footer">
            <p>Este contrato foi gerado automaticamente pelo sistema Pixel Hub</p>
        </div>
    </div>
</body>
</html>





