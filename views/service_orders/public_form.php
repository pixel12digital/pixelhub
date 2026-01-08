<?php
use PixelHub\Services\CompanySettingsService;

// Busca dados da empresa
$logoUrl = CompanySettingsService::getLogoUrl();
$companyName = CompanySettingsService::getSettings()['company_name'] ?? 'Pixel12 Digital';

$serviceName = $order['service_name'] ?? 'Serviço';
$currentStep = $currentStep ?? 'client_data'; // Vem do controller

// Decodifica briefing template
$questions = [];
if (!empty($briefingTemplate)) {
    $template = json_decode($briefingTemplate, true);
    $questions = $template['questions'] ?? [];
    // Ordena por order
    usort($questions, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preencher Pedido - <?= htmlspecialchars($serviceName) ?></title>
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
            line-height: 1.6;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
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
        
        .header {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            padding: 30px 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .steps {
            display: flex;
            justify-content: center;
            padding: 30px 40px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            position: relative;
        }
        
        .step {
            display: flex;
            align-items: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: calc(50% + 20px);
            right: -50%;
            height: 2px;
            background: #ddd;
            z-index: 0;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }
        
        .step.active:not(:last-child)::after {
            background: #023A8D;
        }
        
        .step.completed:not(:last-child)::after {
            background: #4caf50;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            position: relative;
            z-index: 2;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .step.active .step-circle {
            background: #023A8D;
            color: white;
        }
        
        .step.completed .step-circle {
            background: #4caf50;
            color: white;
        }
        
        .step.completed .step-circle::before {
            content: '✓';
        }
        
        .step-label {
            font-size: 13px;
            font-weight: 500;
            color: #666;
            text-decoration: none !important;
            position: relative;
            z-index: 3;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 3px;
        }
        
        .step.active .step-label {
            color: #023A8D;
            font-weight: 600;
            text-decoration: none !important;
            background: #f8f9fa;
        }
        
        .step.completed .step-label {
            text-decoration: none !important;
            background: #f8f9fa;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #023A8D;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #023A8D;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0354b8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border-left: 4px solid #1976d2;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: #023A8D;
            background: #f8f9fa;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload-label {
            color: #023A8D;
            font-weight: 600;
            cursor: pointer;
        }
        
        .file-list {
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }
        
        .file-preview {
            margin-top: 15px;
            display: none;
        }
        
        .file-preview.active {
            display: block;
        }
        
        .file-preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #ddd;
            padding: 5px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .file-preview-info {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #023A8D;
        }
        
        .file-preview-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .file-preview-size {
            font-size: 12px;
            color: #666;
        }
        
        .file-preview-remove {
            margin-top: 8px;
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
        
        .file-preview-remove:hover {
            background: #c82333;
        }
        
        .summary-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: 600;
            color: #666;
        }
        
        .summary-value {
            color: #333;
        }
        
        /* Chat Interface - Melhorado */
        .chat-container {
            border: none;
            border-radius: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 30px;
            max-height: 700px;
            overflow-y: auto;
            margin-bottom: 20px;
            min-height: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .chat-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .chat-container::-webkit-scrollbar-thumb {
            background: #023A8D;
            border-radius: 10px;
        }
        
        .chat-container::-webkit-scrollbar-thumb:hover {
            background: #0354b8;
        }
        
        .chat-message {
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            animation: slideIn 0.4s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .chat-message.bot {
            align-items: flex-start;
        }
        
        .chat-message.user {
            align-items: flex-end;
        }
        
        .chat-bubble {
            max-width: 85%;
            padding: 18px 22px;
            border-radius: 18px;
            word-wrap: break-word;
            line-height: 1.7;
            font-size: 15px;
            position: relative;
        }
        
        .chat-message.bot .chat-bubble {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #2d3748;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-bottom-left-radius: 4px;
        }
        
        .chat-message.user .chat-bubble {
            background: #023A8D;
            color: white;
            box-shadow: 0 2px 8px rgba(2, 58, 141, 0.2);
            border-bottom-right-radius: 4px;
        }
        
        .chat-message.user .chat-bubble::after {
            content: '●';
            position: absolute;
            right: -25px;
            top: 10px;
            font-size: 12px;
            color: #023A8D;
            opacity: 0.6;
        }
        
        /* Ícones monocromáticos - remove cores dos emojis */
        .icon-monochrome,
        .chat-option .icon-monochrome,
        .welcome-message .icon-monochrome,
        .chat-summary-label {
            filter: grayscale(100%);
            opacity: 0.7;
        }
        
        .welcome-message ul li,
        .chat-option,
        .chat-bubble {
            font-variant-emoji: text;
        }
        
        .chat-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }
        
        .chat-option {
            padding: 12px 22px;
            background: white;
            border: 2px solid #023A8D;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #023A8D;
            font-weight: 600;
            user-select: none;
            box-shadow: 0 2px 8px rgba(2, 58, 141, 0.15);
        }
        
        .chat-option:hover {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(2, 58, 141, 0.3);
            border-color: #0354b8;
        }
        
        .chat-option.selected {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.25);
        }
        
        .chat-option:active {
            transform: translateY(-1px);
        }
        
        .chat-input-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
            width: 100%;
            max-width: 600px;
        }
        
        .chat-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            font-family: inherit;
        }
        
        .chat-input:focus {
            border-color: #023A8D;
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.15);
            outline: none;
        }
        
        .chat-send-btn {
            padding: 16px 32px;
            background: #023A8D;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(2, 58, 141, 0.2);
            font-size: 15px;
            align-self: flex-start;
        }
        
        .chat-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(2, 58, 141, 0.35);
        }
        
        .chat-send-btn:active {
            transform: translateY(0);
        }
        
        .chat-send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .chat-summary {
            background: linear-gradient(135deg, #e8f4f8 0%, #f0f8fa 100%);
            border-left: 5px solid #023A8D;
            padding: 20px;
            border-radius: 12px;
            margin-top: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .chat-summary-title {
            font-weight: 700;
            color: #023A8D;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .chat-summary-item {
            padding: 10px 0;
            color: #2d3748;
            border-bottom: 1px solid #d0e8f0;
        }
        
        .chat-summary-item:last-child {
            border-bottom: none;
        }
        
        .chat-summary-label {
            font-weight: 600;
            color: #4a5568;
            display: inline-block;
            min-width: 120px;
        }
        
        /* Ícone de atendente - Monocromático */
        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e8ecf4;
            border: 2px solid #d0d7e0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-right: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .chat-avatar svg {
            width: 22px;
            height: 22px;
            fill: #4a5568;
        }
        
        .chat-message.bot {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .chat-message.user {
            margin-bottom: 24px;
        }
        
        .chat-message.bot .chat-bubble {
            margin-left: 0;
            flex: 1;
        }
        
        .welcome-message {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #2d3748;
            padding: 24px;
            border-radius: 18px;
            margin-bottom: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-bottom-left-radius: 4px;
        }
        
        .welcome-message h3 {
            margin: 0 0 14px 0;
            font-size: 18px;
            font-weight: 700;
            color: #023A8D;
        }
        
        .welcome-message p {
            margin: 10px 0;
            line-height: 1.7;
            color: #4a5568;
        }
        
        .welcome-message ul {
            margin: 14px 0 0 0;
            padding-left: 24px;
        }
        
        .welcome-message li {
            margin: 8px 0;
            color: #4a5568;
            line-height: 1.6;
        }
        
        .welcome-message strong {
            color: #023A8D;
            font-weight: 600;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #023A8D;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .steps {
                flex-direction: column;
                gap: 15px;
            }
            
            .step:not(:last-child)::after {
                display: none;
            }
            
            .step-circle {
                margin-right: 0;
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($serviceName) ?></h1>
            <p>Preencha seus dados e o briefing para iniciarmos o projeto</p>
        </div>
        
        <!-- Indicador de etapas (oculto no modo chat) -->
        <div class="steps" style="display: none;">
            <div class="step <?= $currentStep === 'client_data' ? 'active' : (in_array($currentStep, ['address', 'briefing', 'approval']) ? 'completed' : '') ?>">
                <div class="step-circle">1</div>
                <div class="step-label">Dados Cadastrais</div>
            </div>
            <div class="step <?= $currentStep === 'address' ? 'active' : (in_array($currentStep, ['briefing', 'approval']) ? 'completed' : '') ?>">
                <div class="step-circle">2</div>
                <div class="step-label">Endereço</div>
            </div>
            <div class="step <?= $currentStep === 'briefing' ? 'active' : ($currentStep === 'approval' ? 'completed' : '') ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Briefing</div>
            </div>
            <div class="step <?= $currentStep === 'approval' ? 'active' : '' ?>">
                <div class="step-circle">4</div>
                <div class="step-label">Aprovação</div>
            </div>
        </div>
        
        <div class="content">
            <!-- CHAT COMPLETO - TODAS AS ETAPAS -->
            <div class="step-content active" id="step-chat">
                <h2 style="margin-bottom: 20px; color: #023A8D;">Vamos conversar?</h2>
                
                <!-- Chat Container Principal -->
                <div class="chat-container" id="main-chat" style="min-height: 500px; max-height: 700px; position: relative;">
                    <!-- Mensagens serão inseridas aqui via JavaScript -->
                    
                    <!-- Botão flutuante para ver resumo -->
                    <button id="summary-float-btn" onclick="showSummaryFromButton()" style="position: absolute; bottom: 20px; right: 20px; background: #023A8D; color: white; border: none; border-radius: 50px; padding: 12px 20px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-weight: 600; display: none; z-index: 1000; transition: all 0.3s;">
                        📋 Ver Resumo
                    </button>
                </div>
                
                <!-- Formulário hidden para enviar dados -->
                <form id="main-form" style="display: none;">
                    <!-- Dados Cadastrais -->
                    <input type="hidden" name="name" id="form-name">
                    <input type="hidden" name="email" id="form-email">
                    <input type="hidden" name="phone" id="form-phone">
                    <input type="hidden" name="cpf_cnpj" id="form-cpf_cnpj">
                    <input type="hidden" name="person_type" id="form-person_type" value="pf">
                    
                    <!-- Endereço -->
                    <input type="hidden" name="cep" id="form-cep">
                    <input type="hidden" name="address[street]" id="form-address_street">
                    <input type="hidden" name="address[number]" id="form-address_number">
                    <input type="hidden" name="address[complement]" id="form-address_complement">
                    <input type="hidden" name="address[neighborhood]" id="form-address_neighborhood">
                    <input type="hidden" name="address[city]" id="form-address_city">
                    <input type="hidden" name="address[state]" id="form-address_state">
                    
                    <!-- Briefing -->
                    <?php foreach ($questions as $index => $question): ?>
                        <input type="hidden" 
                               name="q_<?= htmlspecialchars($question['id']) ?>" 
                               id="form-q-<?= htmlspecialchars($question['id']) ?>"
                               <?= ($question['required'] ?? false) ? 'required' : '' ?>>
                    <?php endforeach; ?>
                    
                    <div class="btn-group" style="margin-top: 20px; display: none;" id="form-buttons">
                        <button type="submit" class="btn btn-primary" id="main-submit-btn">Finalizar</button>
                    </div>
                </form>
                
                <script>
                    // Inicializa chat completo quando o DOM estiver pronto
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function() {
                            initMainChat(
                                <?= json_encode($clientData ?? []) ?>,
                                <?= json_encode($briefingData ?? []) ?>,
                                <?= json_encode($questions ?? []) ?>,
                                '<?= $token ?>'
                            );
                        });
                    } else {
                        initMainChat(
                            <?= json_encode($clientData ?? []) ?>,
                            <?= json_encode($briefingData ?? []) ?>,
                            <?= json_encode($questions ?? []) ?>,
                            '<?= $token ?>'
                        );
                    }
                </script>
            </div>
            
            <!-- FORMULÁRIOS TRADICIONAIS (OCULTOS) -->
            <div class="step-content" id="step-client-data" style="display: none;">
                <h2 style="margin-bottom: 20px; color: #023A8D;">Dados Cadastrais</h2>
                
                <form id="client-data-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome Completo <span class="required">*</span></label>
                            <input type="text" name="name" id="name" required value="<?= htmlspecialchars($clientData['name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   required 
                                   pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                   value="<?= htmlspecialchars($clientData['email'] ?? '') ?>">
                            <small style="color: #666;">Digite um email válido</small>
                            <div id="email-error" style="display: none; color: #dc3545; font-size: 13px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Telefone/Celular <span class="required">*</span></label>
                            <input type="tel" 
                                   name="phone" 
                                   id="phone" 
                                   required 
                                   maxlength="15"
                                   placeholder="(00) 00000-0000"
                                   value="<?= htmlspecialchars($clientData['phone'] ?? '') ?>">
                            <small style="color: #666;">Digite o telefone com DDD (ex: (47) 99999-9999)</small>
                            <div id="phone-error" style="display: none; color: #dc3545; font-size: 13px; margin-top: 5px;"></div>
                        </div>
                        <div class="form-group">
                            <label>CPF/CNPJ <span class="required">*</span></label>
                            <input type="text" 
                                   name="cpf_cnpj" 
                                   id="cpf_cnpj"
                                   required 
                                   value="<?= htmlspecialchars($clientData['cpf_cnpj'] ?? '') ?>">
                            <small>O tipo de pessoa será detectado automaticamente</small>
                            <div id="cpf-cnpj-error" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="person_type" id="person_type" value="<?= htmlspecialchars($clientData['person_type'] ?? 'pf') ?>">
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Continuar para Endereço</button>
                    </div>
                </form>
            </div>
            
            <!-- ETAPA 2: ENDEREÇO -->
            <div class="step-content <?= $currentStep === 'address' ? 'active' : '' ?>" id="step-address">
                <h2 style="margin-bottom: 20px; color: #023A8D;">Endereço</h2>
                
                <form id="address-form">
                    <div class="form-group">
                        <label>CEP <span class="required">*</span></label>
                        <input type="text" 
                               name="cep" 
                               id="cep" 
                               required
                               maxlength="9"
                               pattern="[0-9]{5}-?[0-9]{3}"
                               placeholder="00000-000"
                               value="<?= htmlspecialchars(isset($clientData['address']['cep']) ? $clientData['address']['cep'] : (isset($clientData['cep']) ? $clientData['cep'] : '')) ?>">
                        <small>Digite o CEP para buscar automaticamente o endereço</small>
                    </div>
                    
                    <div id="cep-error" style="display: none; background: #fee; border-left: 4px solid #c33; padding: 12px; margin-bottom: 20px; border-radius: 4px;">
                        <p style="color: #c33; margin: 0; font-weight: 600;">CEP inválido ou não encontrado</p>
                        <p style="color: #c33; margin: 5px 0 0 0; font-size: 13px;">Por favor, verifique o CEP digitado e tente novamente.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Endereço</label>
                        <input type="text" 
                               name="address[street]" 
                               id="address_street"
                               value="<?= htmlspecialchars($clientData['address']['street'] ?? ($clientData['address'] ?? '')) ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" 
                                   name="address[number]" 
                                   id="address_number"
                                   value="<?= htmlspecialchars($clientData['address']['number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Complemento</label>
                            <input type="text" 
                                   name="address[complement]" 
                                   id="address_complement"
                                   value="<?= htmlspecialchars($clientData['address']['complement'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bairro</label>
                            <input type="text" 
                                   name="address[neighborhood]" 
                                   id="address_neighborhood"
                                   value="<?= htmlspecialchars($clientData['address']['neighborhood'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Cidade <span class="required">*</span></label>
                            <input type="text" 
                                   name="address[city]" 
                                   id="address_city"
                                   required
                                   readonly
                                   value="<?= htmlspecialchars($clientData['address']['city'] ?? ($clientData['city'] ?? '')) ?>"
                                   style="background-color: #f5f5f5; cursor: not-allowed;">
                            <small id="city-help" style="color: #666;">Preenchido automaticamente pelo CEP</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Estado <span class="required">*</span></label>
                        <input type="text" 
                               name="address[state]" 
                               id="address_state"
                               required
                               maxlength="2"
                               readonly
                               placeholder="SP"
                               value="<?= htmlspecialchars($clientData['address']['state'] ?? ($clientData['state'] ?? '')) ?>"
                               style="background-color: #f5f5f5; cursor: not-allowed;">
                        <small id="state-help" style="color: #666;">Preenchido automaticamente pelo CEP</small>
                    </div>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="goToStep('client_data')">Voltar</button>
                        <button type="submit" class="btn btn-primary">Salvar e Continuar</button>
                    </div>
                </form>
            </div>
            
            <!-- ETAPA 3: BRIEFING -->
            <div class="step-content <?= $currentStep === 'briefing' ? 'active' : '' ?>" id="step-briefing">
                <h2 style="margin-bottom: 20px; color: #023A8D;">Briefing do Projeto</h2>
                
                <!-- Chat Completo do Briefing -->
                <div class="chat-container" id="briefing-chat" style="min-height: 400px; max-height: 600px;">
                    <!-- Mensagens serão inseridas aqui via JavaScript -->
                </div>
                
                <!-- Formulário hidden para enviar dados -->
                <form id="briefing-form" style="display: none;">
                    <?php foreach ($questions as $index => $question): ?>
                        <input type="hidden" 
                               name="q_<?= htmlspecialchars($question['id']) ?>" 
                               id="briefing-data-<?= $question['id'] ?>"
                               <?= ($question['required'] ?? false) ? 'required' : '' ?>
                               value="<?= htmlspecialchars($briefingData['q_' . $question['id']] ?? '') ?>">
                    <?php endforeach; ?>
                    
                    <div class="btn-group" style="margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="goToStep('address')">Voltar</button>
                        <button type="submit" class="btn btn-primary" id="briefing-submit-btn" style="display: none;">Salvar e Continuar</button>
                    </div>
                </form>
                
                <script>
                    // Chat do briefing é gerenciado pelo chat principal (initMainChat)
                    // Não é necessário inicializar aqui separadamente
                    console.log('Briefing step loaded - managed by main chat');
                </script>
            </div>
            
            <!-- ETAPA 4: APROVAÇÃO -->
            <div class="step-content <?= $currentStep === 'approval' ? 'active' : '' ?>" id="step-approval">
                <h2 style="margin-bottom: 20px; color: #023A8D;">Revisão e Aprovação</h2>
                
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="summary-label">Serviço:</span>
                        <span class="summary-value"><?= htmlspecialchars($serviceName) ?></span>
                    </div>
                    <?php if (!empty($order['contract_value'])): ?>
                        <div class="summary-row">
                            <span class="summary-label">Valor:</span>
                            <span class="summary-value">R$ <?= number_format($order['contract_value'], 2, ',', '.') ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_condition'])): ?>
                        <div class="summary-row">
                            <span class="summary-label">Condição de Pagamento:</span>
                            <span class="summary-value"><?= htmlspecialchars($order['payment_condition']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($order['status'] === 'approved'): ?>
                    <div class="alert alert-success">
                        <strong>✓ Pedido Aprovado!</strong><br>
                        Seu pedido foi aprovado e o projeto será criado em breve. Você receberá atualizações por email.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Revise todas as informações acima. Ao aprovar, o projeto será criado automaticamente e você receberá um email de confirmação.
                    </div>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="goToStep('briefing')">Voltar</button>
                        <button type="button" class="btn btn-success" onclick="approveOrder()">Aprovar e Finalizar</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p style="margin-top: 15px; color: #666;">Processando...</p>
            </div>
        </div>
    </div>
    
    <script>
        const token = '<?= htmlspecialchars($token) ?>';
        const currentStep = '<?= $currentStep ?>';
        
        // Variável para controlar se CEP é válido
        let cepValid = false;
        
        // Função para buscar CEP
        async function buscarCEP() {
            const cepInput = document.getElementById('cep');
            const cepError = document.getElementById('cep-error');
            const cep = cepInput.value.replace(/\D/g, '');
            
            // Limpa erro anterior
            cepError.style.display = 'none';
            cepValid = false;
            
            // Limpa campos de endereço
            document.getElementById('address_street').value = '';
            document.getElementById('address_number').value = '';
            document.getElementById('address_complement').value = '';
            document.getElementById('address_neighborhood').value = '';
            document.getElementById('address_city').value = '';
            document.getElementById('address_state').value = '';
            
            if (cep.length !== 8) {
                if (cep.length > 0) {
                    cepError.style.display = 'block';
                }
                return false;
            }
            
            // Formata CEP
            cepInput.value = cep.replace(/^(\d{5})(\d{3})$/, '$1-$2');
            
            try {
                const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const data = await response.json();
                
                if (data.erro) {
                    cepError.style.display = 'block';
                    cepValid = false;
                    return false;
                }
                
                // Preenche campos
                document.getElementById('address_street').value = data.logradouro || '';
                document.getElementById('address_neighborhood').value = data.bairro || '';
                document.getElementById('address_city').value = data.localidade || '';
                document.getElementById('address_state').value = data.uf || '';
                
                cepValid = true;
                cepError.style.display = 'none';
                return true;
            } catch (error) {
                console.error('Erro ao buscar CEP:', error);
                cepError.style.display = 'block';
                cepValid = false;
                return false;
            }
        }
        
        // Busca CEP ao sair do campo
        document.getElementById('cep')?.addEventListener('blur', buscarCEP);
        
        // Busca CEP ao pressionar Enter
        document.getElementById('cep')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCEP();
            }
        });
        
        // Máscara de CEP
        document.getElementById('cep')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.replace(/^(\d{5})(\d{0,3})/, '$1-$2');
            }
            e.target.value = value;
            
            // Se CEP estiver completo, busca automaticamente
            if (value.replace(/\D/g, '').length === 8) {
                buscarCEP();
            } else {
                // Limpa validação se CEP incompleto
                cepValid = false;
                document.getElementById('cep-error').style.display = 'none';
            }
        });
        
        // Validação de CPF/CNPJ
        function validarCPFCNPJ(cpfCnpj) {
            const documento = cpfCnpj.replace(/\D/g, '');
            
            if (documento.length === 11) {
                return validarCPF(documento);
            } else if (documento.length === 14) {
                return validarCNPJ(documento);
            }
            return false;
        }
        
        function validarCPF(cpf) {
            if (cpf.length !== 11) return false;
            if (/^(\d)\1{10}$/.test(cpf)) return false;
            
            let soma = 0;
            for (let i = 0; i < 9; i++) {
                soma += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf.charAt(9))) return false;
            
            soma = 0;
            for (let i = 0; i < 10; i++) {
                soma += parseInt(cpf.charAt(i)) * (11 - i);
            }
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf.charAt(10))) return false;
            
            return true;
        }
        
        function validarCNPJ(cnpj) {
            if (cnpj.length !== 14) return false;
            if (/^(\d)\1{13}$/.test(cnpj)) return false;
            
            let tamanho = cnpj.length - 2;
            let numeros = cnpj.substring(0, tamanho);
            let digitos = cnpj.substring(tamanho);
            let soma = 0;
            let pos = tamanho - 7;
            
            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
            if (resultado !== parseInt(digitos.charAt(0))) return false;
            
            tamanho = tamanho + 1;
            numeros = cnpj.substring(0, tamanho);
            soma = 0;
            pos = tamanho - 7;
            
            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
            if (resultado !== parseInt(digitos.charAt(1))) return false;
            
            return true;
        }
        
        // Valida CPF/CNPJ ao sair do campo
        document.getElementById('cpf_cnpj')?.addEventListener('blur', function() {
            const cpfCnpj = this.value.replace(/\D/g, '');
            const errorMsg = document.getElementById('cpf-cnpj-error');
            
            if (cpfCnpj.length > 0) {
                if (!validarCPFCNPJ(this.value)) {
                    if (!errorMsg) {
                        const errorDiv = document.createElement('div');
                        errorDiv.id = 'cpf-cnpj-error';
                        errorDiv.style.cssText = 'background: #fee; border-left: 4px solid #c33; padding: 12px; margin-top: 5px; border-radius: 4px;';
                        errorDiv.innerHTML = '<p style="color: #c33; margin: 0; font-weight: 600;">CPF/CNPJ inválido</p>';
                        this.parentElement.appendChild(errorDiv);
                    }
                    this.style.borderColor = '#dc3545';
                } else {
                    if (errorMsg) errorMsg.remove();
                    this.style.borderColor = '#28a745';
                    detectPersonType();
                }
            }
        });
        
        // Detecta tipo de pessoa pelo CPF/CNPJ
        function detectPersonType() {
            const cpfCnpj = document.getElementById('cpf_cnpj')?.value.replace(/\D/g, '') || '';
            const personTypeField = document.getElementById('person_type');
            
            if (cpfCnpj.length === 11) {
                personTypeField.value = 'pf';
            } else if (cpfCnpj.length === 14) {
                personTypeField.value = 'pj';
            }
        }
        
        // Validação de email
        function validarEmail(email) {
            const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return re.test(email);
        }
        
        // Validação de telefone/celular (DDD + 8 ou 9 dígitos)
        function validarTelefone(phone) {
            // Remove formatação
            const phoneClean = phone.replace(/\D/g, '');
            // DDD (2 dígitos) + número (8 ou 9 dígitos) = 10 ou 11 dígitos
            // Celular: 11 dígitos (2 DDD + 9 número)
            // Fixo: 10 dígitos (2 DDD + 8 número)
            return phoneClean.length >= 10 && phoneClean.length <= 11;
        }
        
        // Máscara de telefone
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 2) {
                // Apenas DDD
                value = value.length > 0 ? '(' + value : '';
            } else if (value.length <= 6) {
                // DDD + número (até 4 dígitos)
                value = value.replace(/^(\d{2})(\d{0,4})/, '($1) $2');
            } else if (value.length <= 10) {
                // Telefone fixo: (DD) 1234-5678
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else {
                // Celular: (DD) 91234-5678 (limita a 11 dígitos)
                value = value.substring(0, 11);
                value = value.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
            }
            
            e.target.value = value;
            
            // Valida em tempo real
            const phoneError = document.getElementById('phone-error');
            if (value.replace(/\D/g, '').length > 0) {
                if (!validarTelefone(value)) {
                    if (phoneError) {
                        phoneError.textContent = 'Telefone inválido. Use o formato (DD) 00000-0000';
                        phoneError.style.display = 'block';
                        e.target.style.borderColor = '#dc3545';
                    }
                } else {
                    if (phoneError) {
                        phoneError.style.display = 'none';
                        e.target.style.borderColor = '#28a745';
                    }
                }
            } else {
                if (phoneError) phoneError.style.display = 'none';
                e.target.style.borderColor = '';
            }
        });
        
        // Valida email em tempo real
        document.getElementById('email')?.addEventListener('blur', function(e) {
            const emailError = document.getElementById('email-error');
            if (e.target.value.trim() !== '') {
                if (!validarEmail(e.target.value)) {
                    if (emailError) {
                        emailError.textContent = 'Email inválido. Use o formato exemplo@dominio.com';
                        emailError.style.display = 'block';
                        e.target.style.borderColor = '#dc3545';
                    }
                } else {
                    if (emailError) {
                        emailError.style.display = 'none';
                        e.target.style.borderColor = '#28a745';
                    }
                }
            } else {
                if (emailError) emailError.style.display = 'none';
                e.target.style.borderColor = '';
            }
        });
        
        // Valida telefone ao sair do campo
        document.getElementById('phone')?.addEventListener('blur', function(e) {
            const phoneError = document.getElementById('phone-error');
            if (e.target.value.replace(/\D/g, '').length > 0) {
                if (!validarTelefone(e.target.value)) {
                    if (phoneError) {
                        phoneError.textContent = 'Telefone inválido. Use o formato (DD) 00000-0000';
                        phoneError.style.display = 'block';
                        e.target.style.borderColor = '#dc3545';
                    }
                } else {
                    if (phoneError) {
                        phoneError.style.display = 'none';
                        e.target.style.borderColor = '#28a745';
                    }
                }
            }
        });
        
        // Navegação entre etapas
        function goToStep(step) {
            const baseUrl = '<?= pixelhub_url("/client-portal/orders/" . $token) ?>';
            if (step === 'client_data') {
                window.location.href = baseUrl;
            } else if (step === 'address') {
                window.location.href = baseUrl + '?step=address';
            } else if (step === 'briefing') {
                window.location.href = baseUrl + '?step=briefing';
            } else if (step === 'approval') {
                window.location.href = baseUrl + '?step=approval';
            }
        }
        
        // Salva dados do cliente (Etapa 1) - apenas dados pessoais
        document.getElementById('client-data-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Valida email
            const emailInput = document.getElementById('email');
            const email = emailInput.value.trim();
            
            if (!email) {
                alert('Por favor, informe o email.');
                emailInput.focus();
                return;
            }
            
            if (!validarEmail(email)) {
                alert('Email inválido. Por favor, verifique o email digitado.');
                emailInput.focus();
                return;
            }
            
            // Valida telefone
            const phoneInput = document.getElementById('phone');
            const phone = phoneInput.value.trim();
            
            if (!phone) {
                alert('Por favor, informe o telefone.');
                phoneInput.focus();
                return;
            }
            
            if (!validarTelefone(phone)) {
                alert('Telefone inválido. Por favor, informe o telefone com DDD (ex: (47) 99999-9999).');
                phoneInput.focus();
                return;
            }
            
            // Valida CPF/CNPJ
            const cpfCnpjInput = document.getElementById('cpf_cnpj');
            const cpfCnpj = cpfCnpjInput.value.replace(/\D/g, '');
            
            if (cpfCnpj.length === 0) {
                alert('Por favor, informe o CPF/CNPJ.');
                cpfCnpjInput.focus();
                return;
            }
            
            if (!validarCPFCNPJ(cpfCnpjInput.value)) {
                alert('CPF/CNPJ inválido. Por favor, verifique os dados digitados.');
                cpfCnpjInput.focus();
                return;
            }
            
            const formData = new FormData(this);
            const clientData = {};
            
            // Processa apenas campos pessoais
            for (const [key, value] of formData.entries()) {
                if (key !== 'person_type' || value) {
                    clientData[key] = value;
                }
            }
            
            // Garante person_type
            if (!clientData.person_type) {
                clientData.person_type = cpfCnpj.length === 11 ? 'pf' : 'pj';
            }
            
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('<?= pixelhub_url("/client-portal/orders/save-client-data") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token,
                        client_data: clientData
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '<?= pixelhub_url("/client-portal/orders/" . $token) ?>?step=address';
                } else {
                    alert(data.error || 'Erro ao salvar dados');
                    document.getElementById('loading').classList.remove('active');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao salvar dados. Tente novamente.');
                document.getElementById('loading').classList.remove('active');
            }
        });
        
        // Salva endereço (Etapa 2)
        document.getElementById('address-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Valida CEP
            const cepInput = document.getElementById('cep');
            const cep = cepInput.value.replace(/\D/g, '');
            
            if (cep.length !== 8 || !cepValid) {
                document.getElementById('cep-error').style.display = 'block';
                cepInput.focus();
                alert('Por favor, informe um CEP válido antes de continuar.');
                return;
            }
            
            // Valida cidade e estado (devem estar preenchidos pelo CEP)
            const city = document.getElementById('address_city').value.trim();
            const state = document.getElementById('address_state').value.trim();
            
            if (!city || !state) {
                alert('CEP inválido ou incompleto. Por favor, verifique o CEP e tente novamente.');
                cepInput.focus();
                return;
            }
            
            const formData = new FormData(this);
            
            // Busca dados já salvos do pedido (via PHP)
            const savedClientData = <?= json_encode($clientData ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
            const clientData = { ...savedClientData };
            
            // Se não encontrou dados salvos, tenta pegar dos campos (caso ainda estejam na página)
            if (!clientData.name || !clientData.email) {
                const nameField = document.getElementById('name');
                const emailField = document.getElementById('email');
                const phoneField = document.getElementById('phone');
                const cpfCnpjField = document.getElementById('cpf_cnpj');
                const personTypeField = document.getElementById('person_type');
                
                if (nameField) clientData.name = nameField.value;
                if (emailField) clientData.email = emailField.value;
                if (phoneField) clientData.phone = phoneField.value;
                if (cpfCnpjField) clientData.cpf_cnpj = cpfCnpjField.value;
                if (personTypeField) clientData.person_type = personTypeField.value;
            }
            
            // Garante person_type se não estiver definido
            if (!clientData.person_type && clientData.cpf_cnpj) {
                const cpfCnpj = clientData.cpf_cnpj.replace(/\D/g, '');
                clientData.person_type = cpfCnpj.length === 11 ? 'pf' : 'pj';
            }
            
            // Processa endereço
            const address = {};
            if (formData.get('address[street]')) address.street = formData.get('address[street]');
            if (formData.get('address[number]')) address.number = formData.get('address[number]');
            if (formData.get('address[complement]')) address.complement = formData.get('address[complement]');
            if (formData.get('address[neighborhood]')) address.neighborhood = formData.get('address[neighborhood]');
            if (city) address.city = city;
            if (state) address.state = state.toUpperCase();
            
            // Adiciona CEP ao objeto address
            if (formData.get('cep')) {
                address.cep = formData.get('cep');
            }
            
            clientData.address = address;
            
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('<?= pixelhub_url("/client-portal/orders/save-client-data") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token,
                        client_data: clientData
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '<?= pixelhub_url("/client-portal/orders/" . $token) ?>?step=briefing';
                } else {
                    alert(data.error || 'Erro ao salvar endereço');
                    document.getElementById('loading').classList.remove('active');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao salvar endereço. Tente novamente.');
                document.getElementById('loading').classList.remove('active');
            }
        });
        
        // Salva briefing (Etapa 2)
        document.getElementById('briefing-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const briefingData = {};
            
            // Processa campos normais
            for (const [key, value] of formData.entries()) {
                if (key.startsWith('q_')) {
                    briefingData[key] = value;
                }
            }
            
            // Processa arquivos
            const fileInputs = this.querySelectorAll('input[type="file"]');
            for (const input of fileInputs) {
                if (input.files.length > 0) {
                    // Por enquanto, apenas salva o nome do arquivo
                    // Em produção, você precisaria fazer upload real
                    briefingData[input.name] = input.files[0].name;
                }
            }
            
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('<?= pixelhub_url("/client-portal/orders/save-briefing") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token,
                        briefing_data: briefingData
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '<?= pixelhub_url("/client-portal/orders/" . $token) ?>?step=approval';
                } else {
                    alert(data.error || 'Erro ao salvar briefing');
                    document.getElementById('loading').classList.remove('active');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao salvar briefing. Tente novamente.');
                document.getElementById('loading').classList.remove('active');
            }
        });
        
        // Aprova pedido (Etapa 3)
        async function approveOrder() {
            if (!confirm('Tem certeza que deseja aprovar este pedido? O projeto será criado automaticamente.')) {
                return;
            }
            
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('<?= pixelhub_url("/client-portal/orders/approve") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Erro ao aprovar pedido');
                    document.getElementById('loading').classList.remove('active');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao aprovar pedido. Tente novamente.');
                document.getElementById('loading').classList.remove('active');
            }
        }
        
        // Função para formatar tamanho do arquivo
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Função para remover preview do arquivo
        function removeFilePreview(questionId) {
            const fileInput = document.getElementById('file_' + questionId);
            const preview = document.getElementById('file-preview-' + questionId);
            const fileList = document.getElementById('file-list-' + questionId);
            
            if (fileInput) {
                fileInput.value = '';
            }
            if (preview) {
                preview.classList.remove('active');
            }
            if (fileList) {
                fileList.innerHTML = '';
            }
        }
        
        // Sistema de Chat Interativo para Informações do Cartão
        function initCardInfoChat(questionId, questionLabel, savedData) {
            const chatContainer = document.getElementById('chat-' + questionId);
            const hiddenInput = document.getElementById('chat-data-' + questionId);
            
            if (!chatContainer) return;
            
            const isFront = questionLabel.toLowerCase().includes('frente');
            const cardData = savedData ? (typeof savedData === 'string' ? JSON.parse(savedData) : savedData) : {};
            
            let currentStep = 0;
            const selectedItems = { ...cardData };
            
            // Opções disponíveis
            const availableOptions = [
                { id: 'nome', label: 'Nome Completo', icon: '•' },
                { id: 'telefone', label: 'Telefone/Celular', icon: '•' },
                { id: 'email', label: 'Email', icon: '•' },
                { id: 'site', label: 'Site/URL', icon: '•' },
                { id: 'qrcode', label: 'QR Code', icon: '•' },
                { id: 'cargo', label: 'Cargo/Função', icon: '•' },
                { id: 'endereco', label: 'Endereço', icon: '•' },
                { id: 'instagram', label: 'Instagram', icon: '•' },
                { id: 'facebook', label: 'Facebook', icon: '•' },
                { id: 'linkedin', label: 'LinkedIn', icon: '•' },
                { id: 'whatsapp', label: 'WhatsApp', icon: '•' },
            ];
            
            // Itens comuns na frente
            const frontCommon = ['nome', 'telefone', 'email', 'site', 'qrcode', 'cargo'];
            // Itens comuns no verso
            const backCommon = ['endereco', 'instagram', 'facebook', 'linkedin', 'whatsapp', 'site', 'qrcode'];
            
            const options = isFront ? frontCommon : backCommon;
            
            // Renderiza mensagem no chat
            function addMessage(text, isBot = true, buttonOptions = null) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `chat-message ${isBot ? 'bot' : 'user'}`;
                
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                bubble.textContent = text;
                messageDiv.appendChild(bubble);
                
                if (buttonOptions && buttonOptions.length > 0) {
                    const optionsDiv = document.createElement('div');
                    optionsDiv.className = 'chat-options';
                    
                    buttonOptions.forEach(btnOption => {
                        const optionBtn = document.createElement('button');
                        optionBtn.type = 'button';
                        optionBtn.className = 'chat-option';
                        
                        // Verifica se é opção Sim/Não ou opção normal
                        if (btnOption.label === 'Sim' || btnOption.label === 'Não') {
                            optionBtn.textContent = btnOption.icon ? (btnOption.icon + ' ' + btnOption.label) : btnOption.label;
                        } else {
                            optionBtn.textContent = btnOption.icon ? (btnOption.icon + ' ' + btnOption.label) : btnOption.label;
                            const isSelected = selectedItems[btnOption.id] === true;
                            if (isSelected) {
                                optionBtn.classList.add('selected');
                            }
                        }
                        
                        optionBtn.addEventListener('click', function() {
                            // Se for opção Sim/Não
                            if (btnOption.label === 'Sim' || btnOption.label === 'Não') {
                                const selectedOption = availableOptions.find(opt => opt.id === btnOption.id);
                                if (selectedOption) {
                                    // Mostra resposta do usuário
                                    addUserMessage(btnOption.label);
                                    
                                    if (btnOption.label === 'Sim') {
                                        // Se sim, pergunta pela informação
                                        askForInformation(selectedOption);
                                    } else {
                                        // Se não, marca como não incluído e continua
                                        selectedItems[selectedOption.id] = {
                                            include: false
                                        };
                                        updateHiddenInput();
                                        setTimeout(() => askNextQuestion(), 500);
                                    }
                                    return;
                                }
                            } else {
                                // Opção normal (seleção múltipla)
                                this.classList.toggle('selected');
                                const isSelected = this.classList.contains('selected');
                                selectedItems[btnOption.id] = isSelected;
                                
                                // Mostra resposta do usuário
                                const optionLabel = availableOptions.find(opt => opt.id === btnOption.id)?.label || btnOption.label;
                                addUserMessage(isSelected ? `Sim, adicionar ${optionLabel.toLowerCase()}` : `Não, não adicionar`);
                                updateHiddenInput();
                                // Continua na mesma pergunta permitindo múltiplas seleções
                            }
                        });
                        
                        optionsDiv.appendChild(optionBtn);
                    });
                    
                    messageDiv.appendChild(optionsDiv);
                }
                
                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
            
            // Adiciona mensagem do usuário
            function addUserMessage(text) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message user';
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                bubble.textContent = text;
                messageDiv.appendChild(bubble);
                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
            
            // Atualiza input hidden com dados selecionados
            function updateHiddenInput() {
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(selectedItems);
                }
            }
            
            // Armazena a opção atual sendo perguntada para fazer follow-up
            let currentAskingOption = null;
            
            // Faz próxima pergunta sequencial
            function askNextQuestion() {
                const remainingOptions = availableOptions.filter(opt => 
                    options.includes(opt.id) && selectedItems[opt.id] === undefined
                );
                
                if (remainingOptions.length > 0) {
                    const option = remainingOptions[0];
                    currentAskingOption = option;
                    setTimeout(() => {
                        addMessage(`Você gostaria de adicionar ${option.label.toLowerCase()}?`, true, [
                            { id: option.id, label: 'Sim', icon: '✓' },
                            { id: option.id, label: 'Não', icon: '✕' }
                        ]);
                    }, 300);
                } else {
                    // Todas as opções foram perguntadas, mostra resumo
                    setTimeout(() => {
                        showSummary();
                    }, 300);
                }
            }
            
            // Coleta a informação do usuário após ele confirmar que quer adicionar
            function askForInformation(option) {
                let promptText = '';
                let placeholder = '';
                let validation = null;
                
                switch(option.id) {
                    case 'nome':
                        promptText = 'Qual nome completo você gostaria de exibir no cartão?';
                        placeholder = 'Ex: João Silva';
                        break;
                    case 'telefone':
                        promptText = 'Qual telefone ou celular você gostaria de exibir?';
                        placeholder = 'Ex: (11) 98765-4321';
                        validation = (val) => /[\d\s\(\)\-]{10,}/.test(val);
                        break;
                    case 'email':
                        promptText = 'Qual email você gostaria de exibir?';
                        placeholder = 'Ex: contato@empresa.com.br';
                        validation = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
                        break;
                    case 'site':
                        promptText = 'Qual URL do site você gostaria de exibir?';
                        placeholder = 'Ex: https://www.empresa.com.br';
                        validation = (val) => /^https?:\/\/.+/.test(val) || /^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*\.[a-zA-Z]{2,}/.test(val);
                        break;
                    case 'qrcode':
                        promptText = 'Para onde o QR Code deve direcionar? (URL, WhatsApp, etc.)';
                        placeholder = 'Ex: https://www.empresa.com.br ou https://wa.me/5511987654321';
                        break;
                    case 'cargo':
                        promptText = 'Qual cargo ou função você gostaria de exibir?';
                        placeholder = 'Ex: Gerente de Vendas';
                        break;
                    case 'endereco':
                        promptText = 'Qual endereço completo você gostaria de exibir?';
                        placeholder = 'Ex: Rua Exemplo, 123 - Centro, São Paulo - SP';
                        break;
                    case 'instagram':
                        promptText = 'Qual seu Instagram? (sem o @)';
                        placeholder = 'Ex: empresa_insta';
                        validation = (val) => /^[a-zA-Z0-9._]+$/.test(val);
                        break;
                    case 'facebook':
                        promptText = 'Qual a URL ou nome da sua página do Facebook?';
                        placeholder = 'Ex: empresa.fb ou https://facebook.com/empresa';
                        break;
                    case 'linkedin':
                        promptText = 'Qual a URL do seu LinkedIn?';
                        placeholder = 'Ex: https://linkedin.com/in/joao-silva';
                        break;
                    case 'whatsapp':
                        promptText = 'Qual número do WhatsApp para contato?';
                        placeholder = 'Ex: (11) 98765-4321';
                        validation = (val) => /[\d\s\(\)\-]{10,}/.test(val);
                        break;
                    default:
                        promptText = `Informe o texto para ${option.label.toLowerCase()}:`;
                        placeholder = 'Digite aqui...';
                }
                
                setTimeout(() => {
                    addInputMessage(promptText, placeholder, option, validation);
                }, 300);
            }
            
            // Adiciona mensagem com campo de input para coletar informação
            function addInputMessage(text, placeholder, option, validation) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message bot';
                
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                bubble.textContent = text;
                messageDiv.appendChild(bubble);
                
                // Campo de input
                const inputGroup = document.createElement('div');
                inputGroup.className = 'chat-input-group';
                inputGroup.style.marginTop = '10px';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'chat-input';
                input.placeholder = placeholder;
                input.style.width = '100%';
                input.style.maxWidth = '400px';
                
                const sendBtn = document.createElement('button');
                sendBtn.type = 'button';
                sendBtn.className = 'chat-send-btn';
                sendBtn.textContent = 'Enviar';
                sendBtn.style.marginLeft = '10px';
                
                let errorDiv = null;
                
                const handleSubmit = () => {
                    const value = input.value.trim();
                    
                    if (!value) {
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.style.color = '#dc3545';
                            errorDiv.style.fontSize = '13px';
                            errorDiv.style.marginTop = '5px';
                            inputGroup.appendChild(errorDiv);
                        }
                        errorDiv.textContent = 'Por favor, preencha este campo.';
                        return;
                    }
                    
                    // Validação se existir
                    if (validation && !validation(value)) {
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.style.color = '#dc3545';
                            errorDiv.style.fontSize = '13px';
                            errorDiv.style.marginTop = '5px';
                            inputGroup.appendChild(errorDiv);
                        }
                        
                        if (option.id === 'email') {
                            errorDiv.textContent = 'Por favor, informe um email válido.';
                        } else if (option.id === 'telefone' || option.id === 'whatsapp') {
                            errorDiv.textContent = 'Por favor, informe um telefone válido.';
                        } else if (option.id === 'site') {
                            errorDiv.textContent = 'Por favor, informe uma URL válida (começando com http:// ou https:// ou apenas o domínio).';
                        } else {
                            errorDiv.textContent = 'Por favor, verifique o formato da informação.';
                        }
                        return;
                    }
                    
                    // Remove erro se existir
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                    
                    // Salva a informação
                    if (!selectedItems[option.id]) {
                        selectedItems[option.id] = {};
                    }
                    selectedItems[option.id] = {
                        include: true,
                        value: value
                    };
                    
                    // Mostra resposta do usuário
                    addUserMessage(value);
                    updateHiddenInput();
                    
                    // Remove o input group
                    inputGroup.remove();
                    
                    // Continua para próxima pergunta
                    setTimeout(() => askNextQuestion(), 500);
                };
                
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        handleSubmit();
                    }
                });
                
                sendBtn.addEventListener('click', handleSubmit);
                
                inputGroup.appendChild(input);
                inputGroup.appendChild(sendBtn);
                messageDiv.appendChild(inputGroup);
                
                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                // Foca no input
                setTimeout(() => input.focus(), 100);
            }
            
            // Mostra resumo
            function showSummary() {
                const selected = availableOptions.filter(opt => 
                    selectedItems[opt.id] && 
                    (selectedItems[opt.id] === true || (selectedItems[opt.id].include === true))
                );
                
                if (selected.length === 0) {
                    addMessage('Nenhum item selecionado. Você pode reiniciar clicando nos botões acima.', true);
                } else {
                    const summaryDiv = document.createElement('div');
                    summaryDiv.className = 'chat-summary';
                    
                    let summaryHTML = `<div class="chat-summary-title">Resumo - ${isFront ? 'Frente' : 'Verso'} do Cartão:</div>`;
                    
                    selected.forEach(item => {
                        const itemData = selectedItems[item.id];
                        const value = itemData && itemData.value ? itemData.value : '';
                        
                        summaryHTML += `
                            <div class="chat-summary-item">
                                <span class="chat-summary-label">${item.icon}</span>
                                <span><strong>${item.label}:</strong> ${value || '(será preenchido)'}</span>
                            </div>
                        `;
                    });
                    
                    summaryDiv.innerHTML = summaryHTML;
                    chatContainer.appendChild(summaryDiv);
                    
                    addMessage('Perfeito! Suas escolhas foram salvas. Você pode continuar ou alterar selecionando novamente os itens acima.', true);
                }
            }
            
            // Inicializa chat
            function initChat() {
                const greeting = isFront 
                    ? `Olá! Vamos definir o que aparecerá na FRENTE do seu cartão de visita. Vou fazer algumas perguntas rápidas!`
                    : `Agora vamos definir o que aparecerá no VERSO do seu cartão. É bem rápido!`;
                
                addMessage(greeting, true);
                
                // Inicia com a primeira pergunta após o greeting
                setTimeout(() => {
                    askNextQuestion();
                }, 800);
            }
            
            // Se já tem dados salvos, mostra resumo direto
            if (Object.keys(cardData).length > 0) {
                // Converte formato antigo (true/false) para novo formato (objeto)
                Object.keys(cardData).forEach(key => {
                    if (cardData[key] === true || cardData[key] === false) {
                        selectedItems[key] = {
                            include: cardData[key]
                        };
                    } else if (typeof cardData[key] === 'object') {
                        selectedItems[key] = cardData[key];
                    }
                });
                
                const selected = availableOptions.filter(opt => 
                    selectedItems[opt.id] && 
                    (selectedItems[opt.id] === true || (selectedItems[opt.id].include === true))
                );
                
                if (selected.length > 0) {
                    showSummary();
                    setTimeout(() => {
                        addMessage('Deseja alterar suas escolhas? Você pode clicar nas opções abaixo para modificar:', true, 
                            availableOptions.filter(opt => options.includes(opt.id)).map(opt => ({
                                id: opt.id,
                                label: opt.label,
                                icon: opt.icon
                            }))
                        );
                    }, 500);
                } else {
                    initChat();
                }
            } else {
                initChat();
            }
            
            updateHiddenInput();
        }
        
        // ============================================
        // CHAT PRINCIPAL - TODAS AS ETAPAS
        // ============================================
        function initMainChat(clientData, briefingData, questions, token) {
            const chatContainer = document.getElementById('main-chat');
            if (!chatContainer) {
                console.error('Chat container não encontrado! Procurando...');
                // Tenta encontrar novamente após um delay
                setTimeout(() => {
                    const retryContainer = document.getElementById('main-chat');
                    if (retryContainer) {
                        console.log('Chat container encontrado após retry!');
                        initMainChat(clientData, briefingData, questions, token);
                    } else {
                        console.error('Chat container ainda não encontrado!');
                    }
                }, 500);
                return;
            }
            
            console.log('Chat inicializado!', { clientData, briefingData, questions: questions?.length });
            
            // Armazena questions no escopo da função para uso em startBriefing e handleQuestion
            const questionsData = questions || [];
            
            const formData = {
                client: { ...(clientData || {}) },
                address: { ...((clientData && clientData.address) || {}) },
                briefing: { ...(briefingData || {}) },
                service_code: '<?= htmlspecialchars($serviceCode ?? '') ?>'
            };
            
            let currentStep = 'greeting';
            let currentQuestionIndex = -1;
            let waitingForFile = null;
            let isProcessing = false;
            let conversationHistory = []; // Histórico para IA
            let existingClientLookupAttempts = 0;
            let lastLookupIdentifier = null;
            
            // Estado do fluxo guiado do verso
            let versoGuidedState = {
                selectedItems: [], // Itens selecionados na etapa A
                currentSubStep: null, // Substitui para perguntas condicionais
                collectedData: {} // Dados coletados durante o fluxo
            };
            let lastLookupNotFound = false;
            
            // Mostra/esconde botão de resumo baseado no progresso
            function updateSummaryButton() {
                const summaryBtn = document.getElementById('summary-float-btn');
                if (summaryBtn) {
                    // Mostra botão se tiver pelo menos nome e email preenchidos
                    const hasBasicData = formData.client.name && formData.client.email;
                    summaryBtn.style.display = hasBasicData ? 'block' : 'none';
                }
            }
            
            // Função global para mostrar resumo a partir do botão
            window.showSummaryFromButton = function() {
                showSummary();
                // Scroll para o resumo
                setTimeout(() => {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }, 100);
            };
            
            // Detecta intenção do usuário na mensagem
            function detectUserIntention(text) {
                const lowerText = text.toLowerCase().trim();
                
                // Padrões para correção/alteracao
                const correctPatterns = [
                    /(corrigir|corrige|correção|alterar|altera|alterar|mudar|muda|trocar|troca|quero mudar|quero alterar|quero corrigir|preciso corrigir|preciso alterar|preciso mudar)/i,
                    /(nome|email|telefone|cpf|cnpj|cep|endereço|endereco)/i
                ];
                
                // Padrões para ver resumo
                const summaryPatterns = [
                    /(resumo|resumir|mostrar dados|ver dados|dados informados|informações|informacoes|revisar|revisão|revisao)/i
                ];
                
                // Padrões para voltar
                const backPatterns = [
                    /(voltar|volta|anterior|volta para|voltar para)/i
                ];
                
                // Verifica se quer corrigir campo específico
                let fieldToCorrect = null;
                if (correctPatterns[0].test(lowerText)) {
                    if (lowerText.includes('nome') || lowerText.includes('name')) {
                        fieldToCorrect = 'name';
                    } else if (lowerText.includes('email') || lowerText.includes('e-mail')) {
                        fieldToCorrect = 'email';
                    } else if (lowerText.includes('telefone') || lowerText.includes('phone') || lowerText.includes('celular')) {
                        fieldToCorrect = 'phone';
                    } else if (lowerText.includes('cpf') || lowerText.includes('cnpj')) {
                        fieldToCorrect = 'cpf_cnpj';
                    } else if (lowerText.includes('cep')) {
                        fieldToCorrect = 'cep';
                    } else if (lowerText.includes('endereço') || lowerText.includes('endereco') || lowerText.includes('número') || lowerText.includes('numero')) {
                        if (lowerText.includes('número') || lowerText.includes('numero')) {
                            fieldToCorrect = 'address_number';
                        } else {
                            fieldToCorrect = 'cep'; // Por padrão volta para CEP
                        }
                    }
                    
                    return {
                        action: fieldToCorrect ? 'correct' : 'correct_unspecified',
                        field: fieldToCorrect
                    };
                }
                
                // Verifica se quer ver resumo
                if (summaryPatterns.some(pattern => pattern.test(lowerText))) {
                    return {
                        action: 'show_summary'
                    };
                }
                
                // Verifica se quer voltar
                if (backPatterns.some(pattern => pattern.test(lowerText))) {
                    return {
                        action: 'back'
                    };
                }
                
                return null;
            }
            
            // Retorna label amigável do campo
            function getFieldLabel(fieldName) {
                const labels = {
                    'name': 'o nome',
                    'email': 'o email',
                    'phone': 'o telefone',
                    'cpf_cnpj': 'o CPF/CNPJ',
                    'cep': 'o CEP',
                    'address_number': 'o número do endereço',
                    'address_complement': 'o complemento'
                };
                return labels[fieldName] || 'este campo';
            }
            
            // Função de validação contextual inteligente
            function getContextualValidation(field, value) {
                const suggestions = [];
                
                switch(field) {
                    case 'name':
                        if (value.length < 3) {
                            suggestions.push('Nome muito curto. Informe o nome completo, por favor.');
                        } else if (!value.includes(' ')) {
                            suggestions.push('Parece que falta o sobrenome. Pode informar nome e sobrenome?');
                        } else if (value.split(' ').length < 2) {
                            suggestions.push('Para um melhor atendimento, informe pelo menos nome e sobrenome.');
                        }
                        break;
                    
                    case 'email':
                        if (!value.includes('@')) {
                            suggestions.push('Email inválido. Falta o símbolo @. Exemplo: seuemail@exemplo.com');
                        } else if (!value.includes('.')) {
                            suggestions.push('Email parece incompleto. Falta o domínio (ex: .com, .com.br)');
                        } else if (value.length < 5) {
                            suggestions.push('Email muito curto. Verifique se está completo.');
                        } else if (!value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                            suggestions.push('Formato de email inválido. Use o formato: exemplo@dominio.com');
                        }
                        break;
                    
                    case 'phone':
                        const phoneDigits = value.replace(/\D/g, '');
                        if (phoneDigits.length < 10) {
                            suggestions.push('Telefone incompleto. Informe com DDD (ex: (47) 99999-9999)');
                        } else if (phoneDigits.length > 11) {
                            suggestions.push('Telefone com muitos dígitos. Verifique o número informado.');
                        }
                        break;
                    
                    case 'cpf_cnpj':
                        const digits = value.replace(/\D/g, '');
                        if (digits.length === 0) {
                            suggestions.push('Por favor, informe um CPF ou CNPJ.');
                        } else if (digits.length < 11) {
                            suggestions.push('CPF deve ter 11 dígitos. Verifique se está completo.');
                        } else if (digits.length > 11 && digits.length < 14) {
                            suggestions.push('CNPJ deve ter 14 dígitos. Verifique se está completo.');
                        } else if (digits.length > 14) {
                            suggestions.push('Número de dígitos inválido. CPF tem 11 dígitos e CNPJ tem 14.');
                        }
                        break;
                }
                
                return suggestions;
            }
            
            // Determina etapa inicial - mas força greeting se não tiver dados essenciais
            if (formData.client.name && formData.client.email && formData.address.city) {
                if (Object.keys(formData.briefing).length > 0) {
                    currentStep = 'approval';
                } else {
                    currentStep = 'briefing';
                    currentQuestionIndex = 0;
                }
            } else if (formData.client.name && formData.client.email) {
                currentStep = 'address';
            } else {
                // Força greeting para sempre mostrar mensagem de boas-vindas
                currentStep = 'greeting';
            }
            
            console.log('Current step:', currentStep);
            
            // Renderiza mensagem no chat
            function addMessage(text, isBot = true, options = null, inputType = null, isHTML = false) {
                // Adiciona ao histórico se for mensagem do bot
                if (isBot && text) {
                    conversationHistory.push({ role: 'bot', content: text });
                }
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `chat-message ${isBot ? 'bot' : 'user'}`;
                
                // Adiciona avatar para mensagens do bot
                if (isBot) {
                    const avatar = document.createElement('div');
                    avatar.className = 'chat-avatar';
                    avatar.innerHTML = `
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    `;
                    messageDiv.appendChild(avatar);
                }
                
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                if (isHTML) {
                    bubble.innerHTML = text;
                } else {
                    bubble.textContent = text;
                }
                messageDiv.appendChild(bubble);
                
                if (options && options.length > 0) {
                    const optionsDiv = document.createElement('div');
                    optionsDiv.className = 'chat-options';
                    
                    options.forEach(option => {
                        const optionBtn = document.createElement('button');
                        optionBtn.type = 'button';
                        optionBtn.className = 'chat-option';
                        optionBtn.textContent = option.icon ? (option.icon + ' ' + option.label) : option.label;
                        
                        optionBtn.addEventListener('click', function() {
                            handleOptionClick(option, optionBtn);
                        });
                        
                        optionsDiv.appendChild(optionBtn);
                    });
                    
                    messageDiv.appendChild(optionsDiv);
                }
                
                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                if (inputType) {
                    // Adiciona o input após a mensagem estar no DOM
                    setTimeout(() => {
                        // Se for segment, passa as opções
                        let fieldOptions = null;
                        if (inputType === 'segment') {
                            fieldOptions = [
                                { label: 'Corporativo / Empresarial', value: 'corporativo' },
                                { label: 'Saúde / Medicina', value: 'saude' },
                                { label: 'Beleza / Estética', value: 'beleza' },
                                { label: 'Advocacia / Jurídico', value: 'advocacia' },
                                { label: 'Arquitetura / Engenharia', value: 'arquitetura' },
                                { label: 'Educação / Ensino', value: 'educacao' },
                                { label: 'Tecnologia / TI', value: 'tecnologia' },
                                { label: 'Marketing / Publicidade', value: 'marketing' },
                                { label: 'Gastronomia / Restaurante', value: 'gastronomia' },
                                { label: 'Vendas / Comércio', value: 'vendas' },
                                { label: 'Consultoria', value: 'consultoria' },
                                { label: 'Outro', value: 'outro' }
                            ];
                        }
                        addInputField(messageDiv, inputType, fieldOptions);
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    }, 200);
                }
            }
            
            // Adiciona campo de input
            function addInputField(messageDiv, inputType, options = null) {
                console.log('addInputField chamado com inputType:', inputType, 'options:', options);
                
                // Remove inputs anteriores se houver
                const existingInputs = chatContainer.querySelectorAll('.chat-input-container');
                existingInputs.forEach(el => el.remove());
                
                // Se tem opções, mostra botões ao invés de input
                if (options && Array.isArray(options) && options.length > 0) {
                    const optionsDiv = document.createElement('div');
                    optionsDiv.className = 'chat-options';
                    optionsDiv.style.marginTop = '16px';
                    
                    options.forEach(opt => {
                        const optionBtn = document.createElement('button');
                        optionBtn.className = 'chat-option';
                        optionBtn.textContent = opt.label || opt;
                        optionBtn.onclick = () => {
                            const value = opt.value || opt.label || opt;
                            addUserMessage(value);
                            // Salva o valor
                            if (inputType === 'segment') {
                                formData.briefing = formData.briefing || {};
                                formData.briefing.segment = value;
                            }
                            optionsDiv.remove();
                            // Continua para próxima pergunta
                            setTimeout(() => nextStep(), 500);
                        };
                        optionsDiv.appendChild(optionBtn);
                    });
                    
                    messageDiv.appendChild(optionsDiv);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    return;
                }
                
                // Cria container para o input (abaixo da mensagem)
                const inputContainer = document.createElement('div');
                inputContainer.className = 'chat-input-container';
                inputContainer.style.marginTop = '16px';
                inputContainer.style.width = '100%';
                inputContainer.style.maxWidth = '600px';
                
                const inputGroup = document.createElement('div');
                inputGroup.className = 'chat-input-group';
                
                const input = document.createElement('input');
                input.type = inputType === 'email' ? 'email' : inputType === 'tel' ? 'tel' : 'text';
                input.className = 'chat-input';
                
                let placeholder = '';
                let validation = null;
                
                switch(inputType) {
                    case 'existing_client_lookup':
                        placeholder = 'Digite seu email ou CPF/CNPJ (ex: joao@email.com ou 000.000.000-00)';
                        validation = (val) => {
                            const v = (val || '').trim();
                            if (!v) return false;
                            // aceita email ou CPF/CNPJ com 11/14 dígitos
                            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return true;
                            const digits = v.replace(/\D/g, '');
                            return digits.length === 11 || digits.length === 14;
                        };
                        break;
                    case 'name':
                        placeholder = 'Digite seu nome completo (ou digite "corrigir" para alterar outro campo)';
                        break;
                    case 'email':
                        placeholder = 'exemplo@email.com (ou digite "corrigir nome" para alterar outro campo)';
                        validation = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
                        break;
                    case 'phone':
                        placeholder = '(00) 00000-0000';
                        // Aplica máscara
                        input.addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\D/g, '');
                            if (value.length <= 11) {
                                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                                if (value.length <= 14) {
                                    value = value.replace(/^(\d{2})(\d{4})(\d{4}).*/, '($1) $2-$3');
                                }
                                e.target.value = value;
                            }
                        });
                        validation = (val) => {
                            const digits = val.replace(/\D/g, '');
                            return digits.length >= 10 && digits.length <= 11;
                        };
                        break;
                    case 'cpf_cnpj':
                        placeholder = '000.000.000-00 ou 00.000.000/0000-00';
                        input.addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\D/g, '');
                            if (value.length <= 11) {
                                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
                            } else {
                                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
                            }
                            e.target.value = value;
                        });
                        validation = (val) => {
                            // Usa a função de validação completa que já existe no código
                            return validarCPFCNPJ(val);
                        };
                        break;
                    case 'cep':
                        placeholder = '00000-000';
                        input.maxLength = 9;
                        input.addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\D/g, '');
                            if (value.length <= 8) {
                                value = value.replace(/^(\d{5})(\d{3}).*/, '$1-$2');
                                e.target.value = value;
                            }
                        });
                        validation = (val) => {
                            const digits = val.replace(/\D/g, '');
                            return digits.length === 8;
                        };
                        break;
                    case 'text':
                    default:
                        placeholder = 'Digite sua resposta...';
                }
                
                input.placeholder = placeholder;
                
                const sendBtn = document.createElement('button');
                sendBtn.type = 'button';
                sendBtn.className = 'chat-send-btn';
                sendBtn.textContent = 'Enviar';
                
                let errorDiv = null;
                
                const handleSubmit = async () => {
                    if (isProcessing) return; // Previne múltiplos envios
                    
                    const value = input.value.trim();
                    const lowerValue = value.toLowerCase();
                    isProcessing = true;
                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Processando...';
                    
                    try {
                        // ============================
                        // LOOKUP CLIENTE EXISTENTE (SEM IA)
                        // ============================
                        if (inputType === 'existing_client_lookup' || currentStep === 'existing_client_lookup') {
                            // Se a última tentativa não encontrou e o usuário confirmou, cai para cadastro rápido
                            if (lastLookupNotFound && (lowerValue === 'sim' || lowerValue === 's' || lowerValue.includes('é esse') || lowerValue.includes('esse mesmo'))) {
                                conversationHistory.push({ role: 'user', content: value });
                                addUserMessage(value);
                                inputGroup.remove();
                                
                                lastLookupNotFound = false;
                                existingClientLookupAttempts++;
                                
                                addMessage('Entendi. Não consegui localizar com esse dado. Vamos fazer um cadastro rapidinho — só algumas informações básicas, ok?', true);
                                currentStep = 'client_data';
                                setTimeout(() => nextStep(), 900);
                                return;
                            }
                            
                            // Tenta lookup com email/CPF/CNPJ
                            lastLookupIdentifier = value;
                            const lookupResp = await fetch('<?= pixelhub_url('/client-portal/orders/lookup-existing-client') ?>', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    token: token,
                                    identifier: value
                                })
                            });
                            
                            const lookupData = await lookupResp.json();
                            
                            conversationHistory.push({ role: 'user', content: value });
                            addUserMessage(value);
                            inputGroup.remove();
                            
                            if (!lookupData.success) {
                                addMessage(lookupData.error || 'Não consegui consultar agora. Você pode tentar novamente ou seguimos com cadastro rápido.', true);
                                lastLookupNotFound = true;
                                // Recolhe novamente
                                setTimeout(() => {
                                    const lastMessage = Array.from(chatContainer.querySelectorAll('.chat-message')).pop();
                                    if (lastMessage) addInputField(lastMessage, 'existing_client_lookup');
                                }, 400);
                                return;
                            }
                            
                            if (!lookupData.found) {
                                // Não encontrado: pede confirmação (o usuário responde SIM ou manda outro identificador)
                                addMessage(lookupData.message || 'Não localizei seu cadastro. É esse mesmo?', true);
                                lastLookupNotFound = !!lookupData.needs_confirmation;
                                
                                setTimeout(() => {
                                    const lastMessage = Array.from(chatContainer.querySelectorAll('.chat-message')).pop();
                                    if (lastMessage) addInputField(lastMessage, lookupData.needs_confirmation ? 'text' : 'existing_client_lookup');
                                }, 450);
                                return;
                            }
                            
                            // Encontrado: aplica dados no formData e avança automaticamente
                            lastLookupNotFound = false;
                            
                            const extractedFields = {};
                            if (lookupData.client) {
                                if (lookupData.client.name) extractedFields['name'] = lookupData.client.name;
                                if (lookupData.client.email) extractedFields['email'] = lookupData.client.email;
                                if (lookupData.client.phone) extractedFields['phone'] = lookupData.client.phone;
                                if (lookupData.client.cpf_cnpj) extractedFields['cpf_cnpj'] = lookupData.client.cpf_cnpj;
                            }
                            if (lookupData.address) {
                                if (lookupData.address.street) extractedFields['address_street'] = lookupData.address.street;
                                if (lookupData.address.number) extractedFields['address_number'] = lookupData.address.number;
                                if (lookupData.address.complement !== undefined && lookupData.address.complement !== null) extractedFields['address_complement'] = lookupData.address.complement;
                                if (lookupData.address.neighborhood) extractedFields['address_neighborhood'] = lookupData.address.neighborhood;
                                if (lookupData.address.city) extractedFields['address_city'] = lookupData.address.city;
                                if (lookupData.address.state) extractedFields['address_state'] = lookupData.address.state;
                                // CEP por último para evitar perguntar número (buscarCEP) quando já temos address_number
                                if (lookupData.address.cep) extractedFields['cep'] = lookupData.address.cep;
                            }
                            
                            // Mostra mensagem de sucesso e processa os campos (reaproveita pipeline)
                                const pseudoAnalysis = {
                                intention: 'informar_dado',
                                field: null,
                                value: null,
                                extractedFields: extractedFields,
                                isValidData: true,
                                validation: { valid: true, error: null, suggestion: null },
                                action: 'extract_multiple',
                                response: lookupData.message || 'Encontrei seu cadastro! Vou seguir.',
                                needsConfirmation: false
                            };
                            
                                await processAIAnalysis(pseudoAnalysis, value, inputType);
                            return;
                        }
                        
                        // Se estamos no fluxo guiado do verso, processa inputs condicionais
                        // Verifica se estamos em qualquer etapa do fluxo guiado (não apenas 'confirm_fields')
                        if (versoGuidedState && versoGuidedState.currentSubStep && 
                            versoGuidedState.currentSubStep !== 'select_items' && 
                            versoGuidedState.currentSubStep !== null) {
                            handleVersoGuidedInput(value, inputType);
                            inputGroup.remove();
                            return;
                        }
                        
                        // Se estamos no step briefing, processa diretamente via handleQuestion
                        if (currentStep === 'briefing' && questionsData && currentQuestionIndex >= 0 && currentQuestionIndex < questionsData.length) {
                            const question = questionsData[currentQuestionIndex];
                            if (question) {
                                // Se for verso_guided, não processa aqui (já tem fluxo próprio)
                                if (question.type === 'verso_guided' || question.id === 'verso_informacoes') {
                                    // Já está sendo processado pelo fluxo guiado
                                    return;
                                }
                                
                                // Salva resposta do briefing
                                formData.briefing['q_' + question.id] = value;
                                // Se for segment, também salva em briefing.segment
                                if (question.id === 'segment') {
                                    formData.briefing.segment = value;
                                }
                                updateFormField('form-q-' + question.id, value);
                                addUserMessage(value);
                                inputGroup.remove();
                                conversationHistory.push({ role: 'user', content: value });
                                currentQuestionIndex++;
                                // Avança para próxima pergunta
                                setTimeout(() => startBriefing(), 500);
                                return;
                            }
                        }
                        
                        // USA IA PARA ANALISAR MENSAGEM
                        console.log('[IA] Enviando mensagem para análise:', value);
                        const response = await analyzeWithAI(value, inputType);
                        
                        // Adiciona mensagem do usuário ao histórico
                        conversationHistory.push({ role: 'user', content: value });
                        addUserMessage(value);
                        inputGroup.remove();
                        
                        // Processa análise da IA
                        console.log('[IA] Análise recebida:', response);
                        await processAIAnalysis(response, value, inputType);
                    } catch (error) {
                        console.error('[IA] Erro ao processar com IA:', error);
                        
                        // Tenta usar fallback da resposta se disponível
                        if (error.analysis) {
                            console.log('[IA] Usando análise do fallback:', error.analysis);
                            conversationHistory.push({ role: 'user', content: value });
                            addUserMessage(value);
                            inputGroup.remove();
                            await processAIAnalysis(error.analysis, value, inputType);
                        } else {
                            // Fallback para detecção simples
                            console.log('[IA] Usando fallback sem IA');
                            await processWithoutAI(value, inputType);
                        }
                    } finally {
                        isProcessing = false;
                        sendBtn.disabled = false;
                        sendBtn.textContent = 'Enviar';
                    }
                };
                
                // Analisa mensagem usando IA
                async function analyzeWithAI(userMessage, currentInputType) {
                    try {
                        console.log('[IA] Preparando requisição...', {
                            message: userMessage,
                            historyLength: conversationHistory.length,
                            currentStep: currentStep,
                            formDataKeys: Object.keys(formData)
                        });
                        
                        const response = await fetch('<?= pixelhub_url('/client-portal/orders/ai-orchestrate') ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                message: userMessage,
                                history: conversationHistory,
                                formData: formData,
                                currentStep: currentStep,
                                currentQuestion: currentQuestionIndex >= 0 && questionsData ? questionsData[currentQuestionIndex] : null,
                                serviceType: 'business_card'
                            })
                        });
                        
                        console.log('[IA] Resposta HTTP recebida:', response.status, response.statusText);
                        
                        // Verifica se a resposta é OK antes de tentar parsear
                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('[IA] Erro HTTP:', response.status, errorText);
                            throw new Error(`Erro HTTP ${response.status}: ${errorText.substring(0, 100)}`);
                        }
                        
                        const data = await response.json();
                        console.log('[IA] Dados parseados:', data);
                        
                        if (data.success && data.analysis) {
                            console.log('[IA] Análise válida recebida:', JSON.stringify(data.analysis, null, 2));
                            console.log('[IA] Detalhes da análise:', {
                                intention: data.analysis.intention,
                                action: data.analysis.action,
                                field: data.analysis.field,
                                value: data.analysis.value,
                                extractedFields: data.analysis.extractedFields,
                                isValidData: data.analysis.isValidData,
                                validation: data.analysis.validation
                            });
                            return data.analysis;
                        } else if (data.fallback) {
                            // Se tem fallback na resposta, retorna mas marca como erro
                            console.log('[IA] Usando fallback do servidor:', data.fallback);
                            const error = new Error(data.error || 'Usando fallback');
                            error.analysis = data.fallback;
                            throw error;
                        } else {
                            console.error('[IA] Erro na resposta:', data.error);
                            throw new Error(data.error || 'Erro na análise');
                        }
                    } catch (error) {
                        console.error('[IA] Erro ao chamar IA:', error);
                        // Se for erro de rede, preserva o erro para o catch externo
                        if (error.analysis) {
                            throw error; // Preserva análise do fallback
                        }
                        throw error; // Vai para fallback
                    }
                }
                
                // Processa análise da IA
                async function processAIAnalysis(analysis, originalValue, currentInputType) {
                    console.log('[IA] processAIAnalysis chamado:', {
                        analysis,
                        originalValue,
                        currentInputType,
                        formDataAtual: formData
                    });
                    
                    // Processa perguntas do usuário sobre o serviço
                    if (analysis.intention === 'fazer_pergunta' || analysis.action === 'answer_question') {
                        console.log('[IA] Processando pergunta do usuário - mostrando resposta da IA');
                        // IA respondeu uma pergunta do usuário
                        if (analysis.response) {
                            addMessage(analysis.response, true);
                            conversationHistory.push({ role: 'bot', content: analysis.response });
                        }
                        
                        // Se a IA sugeriu mudar de campo ou se não há campo atual, não adiciona input
                        // A ideia é que após responder a pergunta, o sistema deve aguardar a resposta do usuário
                        // para o campo que estava sendo solicitado, mas sem forçar o input novamente
                        // O input só deve ser adicionado se realmente não existir nenhum input visível
                        setTimeout(() => {
                            // Verifica se já existe um input visível no chat
                            const existingInput = chatContainer.querySelector('.chat-input-container input');
                            
                            if (existingInput) {
                                console.log('[IA] Input já existe, não adicionando novo input após resposta da IA');
                                // Foca no input existente para facilitar a digitação
                                existingInput.focus();
                                return;
                            }
                            
                            // Verifica se a IA sugeriu mudar de campo
                            if (analysis.suggestedField && analysis.suggestedField !== currentInputType) {
                                console.log('[IA] IA sugeriu mudar de campo para:', analysis.suggestedField);
                                // Se sugeriu mudar, chama nextStep para avançar
                                setTimeout(() => nextStep(), 300);
                                return;
                            }
                            
                            // Se não há input e ainda estamos no mesmo campo, adiciona o input
                            // Isso permite que o usuário continue informando o dado solicitado
                            if (currentInputType) {
                                const lastMessage = chatContainer.querySelectorAll('.chat-message').length > 0 
                                    ? Array.from(chatContainer.querySelectorAll('.chat-message')).pop() 
                                    : null;
                                if (lastMessage) {
                                    console.log('[IA] Adicionando input para continuar coleta do campo:', currentInputType);
                                    addInputField(lastMessage, currentInputType);
                                }
                            }
                        }, 500);
                        return;
                    }
                    
                    // Mostra resposta da IA se houver
                    if (analysis.response) {
                        addMessage(analysis.response, true);
                        conversationHistory.push({ role: 'bot', content: analysis.response });
                    }
                    
                    // Processa intenções
                    if (analysis.intention === 'corrigir_campo' && analysis.field) {
                        setTimeout(() => {
                            correctField(analysis.field);
                        }, 500);
                        return;
                    }
                    
                    if (analysis.intention === 'ver_resumo' || analysis.action === 'show_summary') {
                        setTimeout(() => {
                            showSummary();
                        }, 500);
                        return;
                    }
                    
                    // Processa múltiplos campos extraídos PRIMEIRO
                    let fieldsProcessed = false;
                    if (analysis.extractedFields && Object.keys(analysis.extractedFields).length > 0) {
                        console.log('[IA] Processando extractedFields:', analysis.extractedFields);
                        await processExtractedFields(analysis.extractedFields);
                        fieldsProcessed = true;
                    }
                    
                    // Valida e salva dado atual (apenas se não foi processado via extractedFields)
                    // Aceita qualquer ação que indique que o dado foi aceito
                    const shouldAccept = analysis.action === 'accept' 
                        || analysis.action === 'extract_multiple'
                        || analysis.action === 'ask_next'
                        || (analysis.intention === 'informar_dado' && analysis.isValidData !== false);
                    
                    if (shouldAccept) {
                        if (analysis.validation && !analysis.validation.valid) {
                            // Mostra erro de validação
                            const inputContainer = chatContainer.querySelector('.chat-input-container');
                            if (inputContainer) {
                                const errorDiv = document.createElement('div');
                                errorDiv.style.color = '#dc3545';
                                errorDiv.style.fontSize = '13px';
                                errorDiv.style.marginTop = '10px';
                                errorDiv.textContent = analysis.validation.error || 'Dado inválido. Por favor, verifique.';
                                inputContainer.appendChild(errorDiv);
                                
                                // Re-adiciona input para correção
                                setTimeout(() => {
                                    const lastMessage = chatContainer.querySelectorAll('.chat-message').length > 0 
                                        ? Array.from(chatContainer.querySelectorAll('.chat-message')).pop() 
                                        : null;
                                    if (lastMessage && currentInputType) {
                                        addInputField(lastMessage, currentInputType);
                                    }
                                }, 500);
                            }
                            return;
                        }
                        
                        // Só salva o campo individual se não foi processado via extractedFields
                        // ou se o campo é diferente dos que foram processados
                        if (!fieldsProcessed || (analysis.field && !analysis.extractedFields?.[analysis.field])) {
                            // Determina qual campo salvar
                            const fieldToSave = analysis.field || currentInputType;
                            const valueToSave = analysis.value || originalValue;
                            
                            console.log('[IA] Salvando campo individual:', fieldToSave, 'com valor:', valueToSave);
                            
                            // Salva dado
                            await saveFieldValue(fieldToSave, valueToSave);
                        } else {
                            console.log('[IA] Campo já foi processado via extractedFields, pulando salvamento individual');
                        }
                        
                        console.log('[IA] Campo salvo. FormData atualizado:', formData);
                        console.log('[IA] Chamando nextStep()...');
                        
                        // Se processou extractedFields (cadastro encontrado), aguarda mais tempo antes de avançar
                        // para dar tempo do usuário ver a mensagem de confirmação
                        const delayAfterExtractedFields = fieldsProcessed ? 2000 : 500;
                        setTimeout(() => nextStep(), delayAfterExtractedFields);
                    } else if (analysis.action === 'clarify') {
                        // IA precisa esclarecer algo - não avança, aguarda resposta
                        setTimeout(() => {
                            const lastMessage = chatContainer.querySelectorAll('.chat-message').length > 0 
                                ? Array.from(chatContainer.querySelectorAll('.chat-message')).pop() 
                                : null;
                            if (lastMessage && currentInputType) {
                                addInputField(lastMessage, currentInputType);
                            }
                        }, 500);
                    } else {
                        // Ação não reconhecida - usa fallback: salva e avança
                        console.warn('[IA] Ação não reconhecida:', analysis.action, '- usando fallback');
                        await saveFieldValue(analysis.field || currentInputType, analysis.value || originalValue);
                        setTimeout(() => nextStep(), 500);
                    }
                }
                
                // Processa múltiplos campos extraídos
                async function processExtractedFields(extractedFields) {
                    for (const [field, value] of Object.entries(extractedFields)) {
                        await saveFieldValue(field, value);
                    }
                }
                
                // Salva valor do campo
                async function saveFieldValue(fieldName, value) {
                    console.log('[IA] saveFieldValue chamado:', fieldName, '=', value);
                    
                    // Normaliza nomes de campos (aceita variações)
                    const normalizedField = (fieldName || '').toLowerCase().trim();
                    
                    // Mapeia campos da IA para campos do formData
                    if (normalizedField === 'name' || normalizedField === 'nome' || normalizedField === 'nome_completo' || normalizedField === 'nomecompleto') {
                        formData.client.name = value;
                        updateFormField('form-name', value);
                        updateSummaryButton();
                        console.log('[IA] Nome salvo:', formData.client.name);
                    } else if (normalizedField === 'email' || normalizedField === 'e-mail' || normalizedField === 'e_mail') {
                        formData.client.email = value;
                        updateFormField('form-email', value);
                        updateSummaryButton();
                        console.log('[IA] Email salvo:', formData.client.email);
                    } else if (normalizedField === 'phone' || normalizedField === 'telefone' || normalizedField === 'celular') {
                        formData.client.phone = value;
                        updateFormField('form-phone', value);
                        updateSummaryButton();
                        console.log('[IA] Telefone salvo:', formData.client.phone);
                    } else if (normalizedField === 'cpf_cnpj' || normalizedField === 'cpf' || normalizedField === 'cnpj' || normalizedField === 'documento') {
                        formData.client.cpf_cnpj = value;
                        const digits = value.replace(/\D/g, '');
                        formData.client.person_type = digits.length === 11 ? 'pf' : 'pj';
                        updateFormField('form-cpf_cnpj', value);
                        updateFormField('form-person_type', formData.client.person_type);
                        updateSummaryButton();
                        console.log('[IA] CPF/CNPJ salvo:', formData.client.cpf_cnpj);
                    } else if (normalizedField === 'cep') {
                        formData.address.cep = value;
                        updateFormField('form-cep', value);
                        // Só busca CEP se o endereço ainda não estiver completo
                        // Evita buscar novamente quando o endereço já veio do cadastro encontrado
                        if (!formData.address.street || !formData.address.city || !formData.address.state) {
                            await buscarCEP(value);
                        } else {
                            console.log('[IA] CEP salvo, mas endereço já está completo, pulando busca CEP');
                        }
                        updateSummaryButton();
                        console.log('[IA] CEP salvo:', formData.address.cep);
                    } else if (normalizedField === 'address_street' || normalizedField === 'rua') {
                        formData.address.street = value;
                        updateFormField('form-address_street', value);
                        updateSummaryButton();
                    } else if (normalizedField === 'address_neighborhood' || normalizedField === 'bairro') {
                        formData.address.neighborhood = value;
                        updateFormField('form-address_neighborhood', value);
                        updateSummaryButton();
                    } else if (normalizedField === 'address_city' || normalizedField === 'cidade') {
                        formData.address.city = value;
                        updateFormField('form-address_city', value);
                        updateSummaryButton();
                    } else if (normalizedField === 'address_state' || normalizedField === 'estado' || normalizedField === 'uf') {
                        formData.address.state = value;
                        updateFormField('form-address_state', value);
                        updateSummaryButton();
                    } else if (normalizedField === 'address_number' || normalizedField === 'numero' || normalizedField === 'número') {
                        formData.address.number = value;
                        updateFormField('form-address_number', value);
                        updateSummaryButton();
                    } else if (normalizedField === 'address_complement' || normalizedField === 'complemento') {
                        formData.address.complement = value;
                        updateFormField('form-address_complement', value);
                        updateSummaryButton();
                    } else if (normalizedField === 'segment' || normalizedField === 'segmento') {
                        formData.briefing = formData.briefing || {};
                        formData.briefing.segment = value;
                        updateSummaryButton();
                        console.log('[IA] Segmento salvo:', formData.briefing.segment);
                    } else {
                        console.warn('[IA] Campo não reconhecido:', fieldName, '- valor:', value);
                    }
                }
                
                // Fallback sem IA
                async function processWithoutAI(value, currentInputType) {
                    // DETECÇÃO DE INTENÇÕES SIMPLES - Fallback
                    const detectedIntention = detectUserIntention(value);
                    
                    if (detectedIntention) {
                        // Usuário quer fazer algo diferente de informar o dado atual
                        addUserMessage(value);
                        inputGroup.remove();
                        
                        if (detectedIntention.action === 'correct' && detectedIntention.field) {
                            // Usuário quer corrigir um campo específico
                            const fieldToCorrect = detectedIntention.field;
                            setTimeout(() => {
                                addMessage(`Entendi! Vou te ajudar a corrigir ${getFieldLabel(fieldToCorrect)}.`, true);
                                setTimeout(() => {
                                    correctField(fieldToCorrect);
                                }, 800);
                            }, 300);
                            return;
                        } else if (detectedIntention.action === 'correct_unspecified') {
                            // Usuário quer corrigir mas não especificou o campo
                            setTimeout(() => {
                                addMessage('Claro! Vou mostrar o resumo dos dados para você escolher o que deseja corrigir. Clique em "✏️ Corrigir" ao lado do campo que quiser alterar.', true);
                                setTimeout(() => {
                                    showSummary();
                                }, 800);
                            }, 300);
                            return;
                        } else if (detectedIntention.action === 'show_summary' || detectedIntention.action === 'review') {
                            // Usuário quer ver resumo
                            setTimeout(() => {
                                addMessage('Vou mostrar o resumo dos dados informados.', true);
                                setTimeout(() => {
                                    showSummary();
                                }, 500);
                            }, 300);
                            return;
                        } else if (detectedIntention.action === 'back' || detectedIntention.action === 'return') {
                            // Usuário quer voltar - ainda não implementado completamente
                            setTimeout(() => {
                                addMessage('Para corrigir um dado específico, diga qual campo deseja alterar ou use o botão "📋 Ver Resumo" para ver todos os dados.', true);
                            }, 300);
                            return;
                        }
                    }
                    
                    // Permite campo vazio apenas para complemento do endereço (opcional)
                    const isOptionalComplement = currentStep === 'address' && formData.address.number && !formData.address.complement;
                    
                    if (!value && !isOptionalComplement) {
                        showError('Por favor, preencha este campo.');
                        return;
                    }
                    
                    if (validation && !validation(value)) {
                        let errorMessage = '';
                        const contextualSuggestions = getContextualValidation(inputType, value);
                        
                        if (inputType === 'email') {
                            errorMessage = contextualSuggestions.length > 0 
                                ? contextualSuggestions[0] 
                                : 'Por favor, informe um email válido (exemplo: seuemail@dominio.com).';
                            // Adiciona dica se parece ser um comando
                            if (!value.includes('@') && (value.toLowerCase().includes('corrigir') || value.toLowerCase().includes('nome'))) {
                                errorMessage += '\n💡 Dica: Se quiser corrigir outro dado, digite "corrigir nome" ou use o botão "📋 Ver Resumo".';
                            }
                        } else if (inputType === 'phone') {
                            errorMessage = contextualSuggestions.length > 0 
                                ? contextualSuggestions[0] 
                                : 'Por favor, informe um telefone válido com DDD (ex: (47) 99999-9999).';
                        } else if (inputType === 'cpf_cnpj') {
                            const digits = value.replace(/\D/g, '');
                            if (digits.length !== 11 && digits.length !== 14) {
                                errorMessage = contextualSuggestions.length > 0 
                                    ? contextualSuggestions[0] 
                                    : 'Por favor, informe um CPF (11 dígitos) ou CNPJ (14 dígitos).';
                            } else {
                                errorMessage = 'CPF/CNPJ inválido. Os dígitos verificadores não conferem. Por favor, verifique novamente.';
                            }
                        } else if (inputType === 'cep') {
                            errorMessage = 'Por favor, informe um CEP válido com 8 dígitos (ex: 86046-650).';
                        } else if (inputType === 'name') {
                            errorMessage = contextualSuggestions.length > 0 
                                ? contextualSuggestions[0] 
                                : 'Por favor, informe seu nome completo.';
                        } else {
                            errorMessage = 'Por favor, verifique o formato da informação.';
                        }
                        
                        showError(errorMessage);
                        return;
                    }
                    
                    // Validação contextual adicional mesmo se passar na validação de formato
                    if (inputType === 'name' || inputType === 'email') {
                        const contextualSuggestions = getContextualValidation(inputType, value);
                        if (contextualSuggestions.length > 0 && inputType === 'name') {
                            // Para nome, apenas avisa mas não bloqueia se tiver pelo menos 3 caracteres
                            if (value.length >= 3) {
                                // Permite mas mostra aviso suave
                            }
                        }
                    }
                    
                    if (errorDiv) errorDiv.remove();
                    
                    // Salva valor
                    if (inputType === 'name') {
                        formData.client.name = value;
                        updateFormField('form-name', value);
                        updateSummaryButton();
                    } else if (inputType === 'email') {
                        formData.client.email = value;
                        updateFormField('form-email', value);
                        updateSummaryButton();
                    } else if (inputType === 'phone') {
                        formData.client.phone = value;
                        updateFormField('form-phone', value);
                        updateSummaryButton();
                    } else if (inputType === 'cpf_cnpj') {
                        formData.client.cpf_cnpj = value;
                        const digits = value.replace(/\D/g, '');
                        formData.client.person_type = digits.length === 11 ? 'pf' : 'pj';
                        updateFormField('form-cpf_cnpj', value);
                        updateFormField('form-person_type', formData.client.person_type);
                        updateSummaryButton();
                    } else if (inputType === 'cep') {
                        formData.address.cep = value;
                        updateFormField('form-cep', value);
                        // Busca CEP automaticamente
                        await buscarCEP(value);
                        updateSummaryButton();
                    } else if (inputType === 'text') {
                        // Trata campos de texto genéricos baseado na etapa atual
                        if (currentStep === 'address') {
                            if (!formData.address.number) {
                                // Salva número do endereço
                                formData.address.number = value;
                                updateFormField('form-address_number', value);
                            } else {
                                // Salva complemento (pode ser vazio para pular)
                                formData.address.complement = value || '';
                                updateFormField('form-address_complement', value || '');
                            }
                            updateSummaryButton();
                        } else if (currentStep === 'briefing' && questionsData && currentQuestionIndex >= 0 && currentQuestionIndex < questionsData.length) {
                            // Salva resposta do briefing
                            const question = questionsData[currentQuestionIndex];
                            if (question) {
                                formData.briefing['q_' + question.id] = value;
                                updateFormField('form-q-' + question.id, value);
                                currentQuestionIndex++;
                            }
                        }
                    }
                    
                    addUserMessage(value);
                    inputGroup.remove();
                    setTimeout(() => nextStep(), 500);
                };
                
                function showError(msg) {
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.style.color = '#dc3545';
                        errorDiv.style.fontSize = '13px';
                        errorDiv.style.marginTop = '5px';
                        inputGroup.appendChild(errorDiv);
                    }
                    errorDiv.textContent = msg;
                }
                
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        handleSubmit();
                    }
                });
                
                sendBtn.addEventListener('click', handleSubmit);
                
                inputGroup.appendChild(input);
                inputGroup.appendChild(sendBtn);
                inputContainer.appendChild(inputGroup);
                
                // Adiciona o input abaixo da mensagem do bot
                chatContainer.appendChild(inputContainer);
                console.log('Input adicionado ao DOM');
                
                setTimeout(() => {
                    input.focus();
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }, 100);
            }
            
            // Busca CEP
            async function buscarCEP(cep) {
                const digits = cep.replace(/\D/g, '');
                if (digits.length !== 8) return;
                
                try {
                    const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
                    const data = await response.json();
                    
                    if (data.erro) {
                        addMessage('CEP não encontrado. Por favor, verifique o CEP digitado.', true);
                        return;
                    }
                    
                    formData.address.street = data.logradouro || '';
                    formData.address.neighborhood = data.bairro || '';
                    formData.address.city = data.localidade || '';
                    formData.address.state = data.uf || '';
                    
                    updateFormField('form-address_street', formData.address.street);
                    updateFormField('form-address_neighborhood', formData.address.neighborhood);
                    updateFormField('form-address_city', formData.address.city);
                    updateFormField('form-address_state', formData.address.state);
                    
                    addMessage(`✓ Endereço encontrado!\n\n` +
                        `${formData.address.street ? 'Rua: ' + formData.address.street + '\n' : ''}` +
                        `${formData.address.neighborhood ? 'Bairro: ' + formData.address.neighborhood + '\n' : ''}` +
                        `${formData.address.city ? 'Cidade: ' + formData.address.city + '\n' : ''}` +
                        `${formData.address.state ? 'Estado: ' + formData.address.state : ''}`, true);
                    
                    // Só pergunta pelo número se ainda não estiver preenchido
                    // Evita pergunta duplicada quando o endereço já veio do cadastro encontrado
                    if (!formData.address.number) {
                        setTimeout(() => {
                            addMessage('Qual o número do endereço?', true, null, 'text');
                        }, 800);
                    } else {
                        console.log('[IA] Número do endereço já está preenchido, pulando pergunta');
                    }
                } catch (error) {
                    addMessage('Erro ao buscar CEP. Por favor, preencha manualmente.', true);
                }
            }
            
            // Adiciona mensagem do usuário
            function addUserMessage(text) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message user';
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                bubble.textContent = text;
                messageDiv.appendChild(bubble);
                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
            
            // Atualiza campo do formulário
            function updateFormField(id, value) {
                const field = document.getElementById(id);
                if (field) field.value = value;
            }
            
            // Handler de opções
            function handleOptionClick(option, btn) {
                if (option.action) {
                    option.action();
                }
            }
            
            // Próximo passo
            function nextStep() {
                console.log('[IA] nextStep() chamado. Step atual:', currentStep, 'FormData:', formData);
                
            if (currentStep === 'greeting') {
                // Primeiro passo: verificar se já é cliente
                currentStep = 'check_existing_client';
                addMessage('Antes de começarmos, você já é cliente da Pixel12 Digital?', true, [
                    {
                        label: 'Sim, já sou cliente',
                        icon: '✓',
                        action: () => {
                            addUserMessage('Sim, já sou cliente');
                            // Vai para lookup do cliente existente
                            existingClientLookupAttempts = 0;
                            lastLookupIdentifier = null;
                            lastLookupNotFound = false;
                            currentStep = 'existing_client_lookup';
                            addMessage('Ótimo! Vou precisar do seu email ou CPF/CNPJ para localizar seu cadastro e sincronizar com o Asaas. Pode informar?', true, null, 'existing_client_lookup');
                        }
                    },
                    {
                        label: 'Não, sou novo cliente',
                        icon: '➕',
                        action: () => {
                            addUserMessage('Não, sou novo cliente');
                            currentStep = 'client_data';
                            setTimeout(() => {
                                addMessage('Perfeito! Vamos fazer seu cadastro primeiro. Isso é necessário para emitirmos a nota fiscal e processarmos o pagamento.', true);
                                setTimeout(() => nextStep(), 1500);
                            }, 500);
                        }
                    }
                ]);
                return;
            }

            // Se acabou de localizar cliente existente, decide próximo passo automaticamente
            if (currentStep === 'existing_client_lookup') {
                // Se já temos pelo menos nome+email, seguimos
                if (formData.client.name && formData.client.email) {
                    // Se já temos cidade/UF (endereço suficiente), vai direto para briefing
                    // Mas aguarda um tempo para dar tempo do usuário ver a mensagem de confirmação do cadastro
                    if (formData.address.city && formData.address.state) {
                        currentStep = 'briefing';
                        currentQuestionIndex = 0;
                        // Aguarda mais tempo antes de iniciar briefing para evitar perguntas seguidas
                        setTimeout(() => {
                            startBriefing();
                        }, 1500);
                        return;
                    }
                    
                    // Senão, coleta endereço
                    currentStep = 'address';
                    // Aguarda um pouco antes de fazer a próxima pergunta após validação do cadastro
                    setTimeout(() => {
                        addMessage('Ótimo! Agora vamos ao endereço. Qual seu CEP?', true, null, 'cep');
                    }, 1500);
                    return;
                }
                
                // Fallback: se por algum motivo não trouxe dados, volta para cadastro
                currentStep = 'client_data';
                nextStep();
                return;
            }
            
            if (currentStep === 'client_data') {
                    console.log('[IA] Verificando campos client_data:', {
                        name: formData.client.name,
                        email: formData.client.email,
                        phone: formData.client.phone,
                        cpf_cnpj: formData.client.cpf_cnpj
                    });
                    
                    if (!formData.client.name) {
                        console.log('[IA] Nome faltando, perguntando nome');
                        addMessage('Qual seu nome completo?', true, null, 'name');
                    } else if (!formData.client.email) {
                        console.log('[IA] Email faltando, perguntando email');
                        addMessage('Qual seu email?', true, null, 'email');
                    } else if (!formData.client.phone) {
                        console.log('[IA] Telefone faltando, perguntando telefone');
                        addMessage('Qual seu telefone ou celular? (com DDD)', true, null, 'phone');
                    } else if (!formData.client.cpf_cnpj) {
                        console.log('[IA] CPF/CNPJ faltando, perguntando CPF/CNPJ');
                        addMessage('Qual seu CPF ou CNPJ?', true, null, 'cpf_cnpj');
                    } else {
                        console.log('[IA] Todos os campos coletados, avançando para address');
                        currentStep = 'address';
                        addMessage('Ótimo! Agora vamos ao endereço. Qual seu CEP?', true, null, 'cep');
                    }
                } else if (currentStep === 'address') {
                    if (!formData.address.cep) {
                        addMessage('Qual seu CEP?', true, null, 'cep');
                    } else if (!formData.address.number) {
                        // Já foi perguntado no buscarCEP, aguarda resposta
                    } else if (formData.address.complement === undefined) {
                        // Ainda não foi perguntado sobre complemento
                        addMessage('Tem complemento? (opcional - deixe em branco para pular)', true, null, 'text');
                    } else {
                        // Complemento foi respondido (pode ser vazio), avança para briefing
                        currentStep = 'briefing';
                        currentQuestionIndex = 0;
                        startBriefing();
                    }
                } else if (currentStep === 'briefing') {
                    startBriefing();
                } else if (currentStep === 'approval') {
                    showApproval();
                }
            }
            
            // Flag para evitar múltiplas chamadas simultâneas
            let isProcessingBriefing = false;
            
            // Inicia briefing
            function startBriefing() {
                // Evita múltiplas chamadas simultâneas
                if (isProcessingBriefing) {
                    console.log('[Briefing] startBriefing já está em execução, ignorando chamada duplicada');
                    return;
                }
                
                console.log('[Briefing] startBriefing chamado. Questions:', questionsData?.length, 'CurrentIndex:', currentQuestionIndex);
                if (!questionsData || questionsData.length === 0) {
                    console.log('[Briefing] Sem perguntas, indo para aprovação');
                    currentStep = 'approval';
                    showApproval();
                    return;
                }
                
                if (currentQuestionIndex >= questionsData.length) {
                    console.log('[Briefing] Todas as perguntas respondidas, indo para aprovação');
                    currentStep = 'approval';
                    showApproval();
                    return;
                }
                
                // Verifica se a pergunta atual já foi respondida
                const question = questionsData[currentQuestionIndex];
                if (!question) {
                    console.log('[Briefing] Pergunta não encontrada no índice:', currentQuestionIndex);
                    return;
                }
                
                const savedValue = formData.briefing['q_' + question.id];
                if (savedValue !== undefined && savedValue !== null && savedValue !== '') {
                    // Pergunta já foi respondida, avança para próxima
                    console.log('[Briefing] Pergunta já respondida, avançando:', question.id);
                    currentQuestionIndex++;
                    isProcessingBriefing = false;
                    setTimeout(() => startBriefing(), 300);
                    return;
                }
                
                isProcessingBriefing = true;
                console.log('[Briefing] Processando pergunta:', question.id, 'tipo:', question.type);
                handleQuestion(question);
                // Reseta flag após um delay para permitir interação do usuário
                setTimeout(() => {
                    isProcessingBriefing = false;
                }, 1000);
            }
            
            // Processa pergunta do briefing
            function handleQuestion(question) {
                const savedValue = formData.briefing['q_' + question.id];
                
                if (question.type === 'textarea') {
                    // Para textarea, sempre usa campo de texto simples
                    // Se for informações do cartão (frente/verso), usa textarea, senão usa text
                    addMessage(question.label + (question.required ? ' *' : ''), true, null, 'textarea');
                } else if (question.type === 'segment') {
                    // Campo segment: mostra opções de segmento como botões
                    const segmentOptions = [
                        { label: 'Corporativo / Empresarial', value: 'corporativo' },
                        { label: 'Saúde / Medicina', value: 'saude' },
                        { label: 'Beleza / Estética', value: 'beleza' },
                        { label: 'Advocacia / Jurídico', value: 'advocacia' },
                        { label: 'Arquitetura / Engenharia', value: 'arquitetura' },
                        { label: 'Educação / Ensino', value: 'educacao' },
                        { label: 'Tecnologia / TI', value: 'tecnologia' },
                        { label: 'Marketing / Publicidade', value: 'marketing' },
                        { label: 'Gastronomia / Restaurante', value: 'gastronomia' },
                        { label: 'Vendas / Comércio', value: 'vendas' },
                        { label: 'Consultoria', value: 'consultoria' },
                        { label: 'Outro', value: 'outro' }
                    ];
                    const options = segmentOptions.map(opt => ({
                        label: opt.label,
                        action: () => {
                            console.log('[Briefing] Segmento selecionado:', opt.value);
                            formData.briefing['q_' + question.id] = opt.value;
                            // Também salva em briefing.segment para compatibilidade
                            formData.briefing.segment = opt.value;
                            updateFormField('form-q-' + question.id, opt.value);
                            addUserMessage(opt.label);
                            // Remove botões para evitar múltiplos cliques
                            const optionsDiv = chatContainer.querySelector('.chat-options');
                            if (optionsDiv) optionsDiv.remove();
                            currentQuestionIndex++;
                            isProcessingBriefing = false;
                            setTimeout(() => startBriefing(), 500);
                        }
                    }));
                    console.log('[Briefing] Exibindo botões de segmento para pergunta:', question.id);
                    addMessage(question.label + (question.required ? ' *' : ''), true, options);
                    // Reseta flag após exibir pergunta para permitir interação
                    isProcessingBriefing = false;
                } else if (question.type === 'select') {
                    const options = (question.options || []).map(opt => ({
                        label: opt,
                        action: () => {
                            formData.briefing['q_' + question.id] = opt;
                            updateFormField('form-q-' + question.id, opt);
                            addUserMessage(opt);
                            currentQuestionIndex++;
                            setTimeout(() => startBriefing(), 500);
                        }
                    }));
                    addMessage(question.label + (question.required ? ' *' : ''), true, options);
                } else if (question.type === 'file') {
                    addMessage(question.label + (question.required ? ' *' : '') + '\n\n📎 Clique no botão abaixo para fazer upload:', true);
                    addFileUpload(question);
                } else if (question.type === 'checkbox') {
                    const options = [
                        {
                            label: 'Sim',
                            icon: '✓',
                            action: () => {
                                formData.briefing['q_' + question.id] = '1';
                                updateFormField('form-q-' + question.id, '1');
                                addUserMessage('Sim');
                                currentQuestionIndex++;
                                setTimeout(() => startBriefing(), 500);
                            }
                        },
                        {
                            label: 'Não',
                            icon: '✕',
                            action: () => {
                                formData.briefing['q_' + question.id] = '';
                                updateFormField('form-q-' + question.id, '');
                                addUserMessage('Não');
                                currentQuestionIndex++;
                                setTimeout(() => startBriefing(), 500);
                            }
                        }
                    ];
                    addMessage(question.label + (question.required ? ' *' : ''), true, options);
                } else if (question.type === 'verso_guided' || question.id === 'verso_informacoes') {
                    // Fluxo guiado para informações do verso
                    startVersoGuidedFlow(question);
                } else {
                    addMessage(question.label + (question.required ? ' *' : ''), true, null, 'text');
                }
            }
            
            // Fluxo guiado para informações do verso
            function startVersoGuidedFlow(question) {
                versoGuidedState = {
                    selectedItems: [],
                    currentSubStep: 'select_items',
                    collectedData: {}
                };
                
                showVersoItemSelection();
            }
            
            // Etapa A: Seleção de itens para o verso
            function showVersoItemSelection() {
                const versoItems = [
                    { id: 'name_title', label: 'Nome e cargo', icon: '👤' },
                    { id: 'whatsapp', label: 'WhatsApp', icon: '💬' },
                    { id: 'phone_extra', label: 'Telefone (se diferente)', icon: '📞' },
                    { id: 'email', label: 'E-mail', icon: '📧' },
                    { id: 'website', label: 'Site', icon: '🌐' },
                    { id: 'instagram', label: 'Instagram', icon: '📸' },
                    { id: 'address', label: 'Endereço', icon: '📍' },
                    { id: 'qr_code', label: 'QR Code (recomendado)', icon: '📱', recommended: true },
                    { id: 'slogan', label: 'Slogan / frase curta', icon: '✨' },
                    { id: 'services', label: 'Serviços (máx. 3)', icon: '⚙️' }
                ];
                
                addMessage('O que você quer que apareça no verso do cartão?\n\nSelecione as opções abaixo (você pode escolher mais de uma).', true);
                
                // Cria container para chips
                const lastMessage = Array.from(chatContainer.querySelectorAll('.chat-message')).pop();
                if (lastMessage) {
                    const chipsContainer = document.createElement('div');
                    chipsContainer.className = 'verso-chips-container';
                    chipsContainer.style.marginTop = '16px';
                    chipsContainer.style.display = 'flex';
                    chipsContainer.style.flexWrap = 'wrap';
                    chipsContainer.style.gap = '10px';
                    
                    versoItems.forEach(item => {
                        const chip = document.createElement('button');
                        chip.type = 'button';
                        chip.className = 'verso-chip';
                        chip.dataset.itemId = item.id;
                        chip.style.cssText = `
                            padding: 10px 16px;
                            border: 2px solid #ddd;
                            background: white;
                            border-radius: 20px;
                            cursor: pointer;
                            font-size: 14px;
                            transition: all 0.2s;
                            display: flex;
                            align-items: center;
                            gap: 6px;
                        `;
                        
                        if (item.recommended) {
                            chip.style.borderColor = '#023A8D';
                            chip.style.background = '#f0f5ff';
                        }
                        
                        chip.innerHTML = `${item.icon} ${item.label}`;
                        
                        chip.addEventListener('click', () => {
                            const isSelected = chip.classList.contains('selected');
                            if (isSelected) {
                                chip.classList.remove('selected');
                                chip.style.background = 'white';
                                chip.style.borderColor = '#ddd';
                                chip.style.color = 'inherit';
                                versoGuidedState.selectedItems = versoGuidedState.selectedItems.filter(id => id !== item.id);
                            } else {
                                chip.classList.add('selected');
                                chip.style.background = '#023A8D';
                                chip.style.borderColor = '#023A8D';
                                chip.style.color = 'white';
                                versoGuidedState.selectedItems.push(item.id);
                                
                                // Limite de 3 para serviços
                                if (item.id === 'services') {
                                    versoGuidedState.selectedItems = versoGuidedState.selectedItems.filter(id => id !== 'services' || versoGuidedState.selectedItems.filter(s => s === 'services').length <= 1);
                                }
                            }
                            
                            updateVersoContinueButton();
                        });
                        
                        chipsContainer.appendChild(chip);
                    });
                    
                    lastMessage.appendChild(chipsContainer);
                    
                    // Botão continuar e usar padrão
                    const actionsContainer = document.createElement('div');
                    actionsContainer.style.marginTop = '20px';
                    actionsContainer.style.display = 'flex';
                    actionsContainer.style.gap = '10px';
                    actionsContainer.style.flexWrap = 'wrap';
                    
                    const continueBtn = document.createElement('button');
                    continueBtn.type = 'button';
                    continueBtn.className = 'chat-option';
                    continueBtn.textContent = 'Continuar';
                    continueBtn.style.cssText = `
                        background: #023A8D;
                        color: white;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        opacity: 0.5;
                        pointer-events: none;
                    `;
                    continueBtn.id = 'verso-continue-btn';
                    
                    const defaultBtn = document.createElement('button');
                    defaultBtn.type = 'button';
                    defaultBtn.textContent = 'Usar padrão recomendado';
                    defaultBtn.style.cssText = `
                        background: transparent;
                        color: #023A8D;
                        border: 1px solid #023A8D;
                        padding: 12px 24px;
                        border-radius: 6px;
                        cursor: pointer;
                    `;
                    
                    defaultBtn.addEventListener('click', () => {
                        // Aplica padrão recomendado
                        versoGuidedState.selectedItems = ['name_title', 'whatsapp', 'email', 'qr_code'];
                        // Atualiza visualmente os chips
                        document.querySelectorAll('.verso-chip').forEach(chip => {
                            const itemId = chip.dataset.itemId;
                            if (versoGuidedState.selectedItems.includes(itemId)) {
                                chip.classList.add('selected');
                                chip.style.background = '#023A8D';
                                chip.style.borderColor = '#023A8D';
                                chip.style.color = 'white';
                            }
                        });
                        updateVersoContinueButton();
                        continueVersoFlow();
                    });
                    
                    continueBtn.addEventListener('click', () => {
                        if (versoGuidedState.selectedItems.length === 0) {
                            // Se nada selecionado, aplica padrão
                            versoGuidedState.selectedItems = ['name_title', 'whatsapp', 'email', 'qr_code'];
                        }
                        continueVersoFlow();
                    });
                    
                    actionsContainer.appendChild(continueBtn);
                    actionsContainer.appendChild(defaultBtn);
                    lastMessage.appendChild(actionsContainer);
                    
                    updateVersoContinueButton();
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            }
            
            function updateVersoContinueButton() {
                const btn = document.getElementById('verso-continue-btn');
                if (btn) {
                    if (versoGuidedState.selectedItems.length > 0) {
                        btn.style.opacity = '1';
                        btn.style.pointerEvents = 'auto';
                    } else {
                        btn.style.opacity = '0.5';
                        btn.style.pointerEvents = 'none';
                    }
                }
            }
            
            function continueVersoFlow() {
                // Remove os chips
                const chipsContainer = document.querySelector('.verso-chips-container');
                if (chipsContainer) chipsContainer.remove();
                const actionsContainer = chipsContainer?.nextElementSibling;
                if (actionsContainer) actionsContainer.remove();
                
                // Se nada foi selecionado, aplica padrão
                if (versoGuidedState.selectedItems.length === 0) {
                    versoGuidedState.selectedItems = ['name_title', 'whatsapp', 'email', 'qr_code'];
                }
                
                addUserMessage('Continuar');
                addMessage('Agora confirme seus dados para o verso.', true);
                
                // Processa perguntas condicionais
                versoGuidedState.currentSubStep = 'confirm_fields';
                processVersoConditionalQuestions();
            }
            
            let versoCurrentItemIndex = 0;
            let versoItemsQueue = [];
            
            function processVersoConditionalQuestions() {
                versoItemsQueue = [...versoGuidedState.selectedItems];
                versoCurrentItemIndex = 0;
                processNextVersoItem();
            }
            
            function processNextVersoItem() {
                if (versoCurrentItemIndex >= versoItemsQueue.length) {
                    // Todas as perguntas condicionais foram respondidas
                    showVersoConfirmation();
                    return;
                }
                
                const itemId = versoItemsQueue[versoCurrentItemIndex];
                
                // Aguarda um pouco antes de processar próxima pergunta
                setTimeout(() => {
                    switch(itemId) {
                        case 'name_title':
                            confirmNameAndTitle(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'whatsapp':
                            confirmWhatsApp(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'phone_extra':
                            askPhoneExtra(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'email':
                            confirmEmail(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'website':
                            askWebsite(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'instagram':
                            askInstagram(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'address':
                            askAddress(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'qr_code':
                            askQRCode(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'slogan':
                            askSlogan(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        case 'services':
                            askServices(() => {
                                versoCurrentItemIndex++;
                                processNextVersoItem();
                            });
                            break;
                        default:
                            versoCurrentItemIndex++;
                            processNextVersoItem();
                    }
                }, 400);
            }
            
            // Funções de confirmação/pergunta condicionais
            function confirmNameAndTitle(onComplete) {
                const name = formData.client.name || formData.briefing?.q_empresa_nome || '';
                const jobTitle = versoGuidedState.collectedData.job_title || '';
                
                addMessage('Confirme seu nome para o cartão:', true);
                
                setTimeout(() => {
                    addMessage(`Nome: ${name || '[preencher]'}\nCargo: ${jobTitle || '[não informado]'}`, true);
                    
                    if (!name) {
                        // Se não tem nome, pergunta
                        setTimeout(() => {
                            versoGuidedState.currentSubStep = 'waiting_full_name';
                            addMessage('Qual seu nome completo para aparecer no cartão?', true, null, 'text');
                            versoGuidedState.onCompleteCallback = () => {
                                if (!jobTitle) {
                                    setTimeout(() => {
                                        versoGuidedState.currentSubStep = 'waiting_job_title';
                                        addMessage('Qual seu cargo?', true, null, 'text');
                                        versoGuidedState.onCompleteCallback = onComplete;
                                    }, 500);
                                } else {
                                    onComplete();
                                }
                            };
                        }, 500);
                    } else if (!jobTitle) {
                        // Se tem nome mas não tem cargo, pergunta cargo
                        setTimeout(() => {
                            const options = [
                                {
                                    label: 'Confirmar nome',
                                    action: () => {
                                        versoGuidedState.collectedData.full_name = name;
                                        addUserMessage('Confirmar nome');
                                        versoGuidedState.currentSubStep = 'waiting_job_title';
                                        addMessage('Qual seu cargo?', true, null, 'text');
                                        versoGuidedState.onCompleteCallback = onComplete;
                                    }
                                },
                                {
                                    label: 'Alterar nome',
                                    action: () => {
                                        addUserMessage('Alterar nome');
                                        versoGuidedState.currentSubStep = 'waiting_full_name';
                                        addMessage('Qual seu nome completo para aparecer no cartão?', true, null, 'text');
                                        versoGuidedState.onCompleteCallback = () => {
                                            setTimeout(() => {
                                                versoGuidedState.currentSubStep = 'waiting_job_title';
                                                addMessage('Qual seu cargo?', true, null, 'text');
                                                versoGuidedState.onCompleteCallback = onComplete;
                                            }, 500);
                                        };
                                    }
                                }
                            ];
                            setTimeout(() => addMessage('', true, options), 300);
                        }, 500);
                    } else {
                        // Tem nome e cargo, apenas confirma
                        setTimeout(() => {
                            const options = [
                                {
                                    label: 'Confirmar',
                                    action: () => {
                                        versoGuidedState.collectedData.full_name = name;
                                        versoGuidedState.collectedData.job_title = jobTitle;
                                        addUserMessage('Confirmar');
                                        onComplete();
                                    }
                                },
                                {
                                    label: 'Alterar',
                                    action: () => {
                                        addUserMessage('Alterar');
                                        versoGuidedState.currentSubStep = 'waiting_full_name';
                                        addMessage('Qual seu nome completo para aparecer no cartão?', true, null, 'text');
                                        versoGuidedState.onCompleteCallback = () => {
                                            setTimeout(() => {
                                                versoGuidedState.currentSubStep = 'waiting_job_title';
                                                addMessage('Qual seu cargo?', true, null, 'text');
                                                versoGuidedState.onCompleteCallback = onComplete;
                                            }, 500);
                                        };
                                    }
                                }
                            ];
                            setTimeout(() => addMessage('', true, options), 300);
                        }, 500);
                    }
                }, 300);
            }
            
            
            // Captura inputs durante o fluxo guiado do verso
            let versoInputHandler = null;
            let versoOnComplete = null;
            
            function handleVersoGuidedInput(value, inputType) {
                addUserMessage(value);
                conversationHistory.push({ role: 'user', content: value });
                
                // Identifica qual campo está sendo preenchido baseado no contexto
                const lastBotMessage = Array.from(chatContainer.querySelectorAll('.chat-message.bot')).pop();
                const lastMessageText = lastBotMessage?.textContent || '';
                
                if (versoGuidedState.currentSubStep === 'waiting_job_title' || lastMessageText.includes('cargo')) {
                    versoGuidedState.collectedData.job_title = value;
                    versoGuidedState.currentSubStep = 'confirm_fields';
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    }
                } else if (lastMessageText.includes('WhatsApp') || lastMessageText.includes('número') || versoGuidedState.currentSubStep === 'waiting_whatsapp') {
                    versoGuidedState.collectedData.whatsapp = value.replace(/\D/g, '');
                    versoGuidedState.currentSubStep = null;
                    // Continua para próxima pergunta
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    } else {
                        versoCurrentItemIndex++;
                        processNextVersoItem();
                    }
                } else if (lastMessageText.includes('telefone adicional') || versoGuidedState.currentSubStep === 'waiting_phone_extra') {
                    versoGuidedState.collectedData.phone_extra = value;
                    versoGuidedState.currentSubStep = null;
                    versoCurrentItemIndex++;
                    processNextVersoItem();
                } else if (versoGuidedState.currentSubStep === 'waiting_email' || ((lastMessageText.includes('e-mail') || lastMessageText.includes('email')) && !lastMessageText.includes('está correto'))) {
                    versoGuidedState.collectedData.email = value;
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    } else {
                        versoCurrentItemIndex++;
                        processNextVersoItem();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_website' || lastMessageText.includes('site') || lastMessageText.includes('Site')) {
                    let website = value.trim();
                    if (website && !website.startsWith('http')) {
                        website = 'https://' + website;
                    }
                    versoGuidedState.collectedData.website = website;
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    } else {
                        versoCurrentItemIndex++;
                        processNextVersoItem();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_instagram' || (lastMessageText.includes('Instagram') && !lastMessageText.includes('está correto'))) {
                    let instagram = value.trim();
                    instagram = instagram.replace(/^@/, '').replace(/^https?:\/\/(www\.)?instagram\.com\//, '');
                    versoGuidedState.collectedData.instagram = instagram ? '@' + instagram : '';
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    } else {
                        versoCurrentItemIndex++;
                        processNextVersoItem();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_slogan' || lastMessageText.includes('frase') || lastMessageText.includes('slogan')) {
                    versoGuidedState.collectedData.slogan = value.substring(0, 60);
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    } else {
                        versoCurrentItemIndex++;
                        processNextVersoItem();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_services' || lastMessageText.includes('serviços') || lastMessageText.includes('Serviços')) {
                    if (!versoGuidedState.collectedData.services) {
                        versoGuidedState.collectedData.services = [];
                    }
                    const services = value.split(',').map(s => s.trim()).filter(s => s).slice(0, 3);
                    versoGuidedState.collectedData.services = services;
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    } else {
                        versoCurrentItemIndex++;
                        processNextVersoItem();
                    }
                } else if (lastMessageText.includes('nome completo')) {
                    versoGuidedState.collectedData.full_name = value;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_full_name') {
                    versoGuidedState.collectedData.full_name = value;
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_job_title') {
                    versoGuidedState.collectedData.job_title = value;
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_qr_website') {
                    let website = value.trim();
                    if (website && !website.startsWith('http')) {
                        website = 'https://' + website;
                    }
                    versoGuidedState.collectedData.qr_website = website;
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    }
                } else if (versoGuidedState.currentSubStep === 'waiting_qr_instagram') {
                    let instagram = value.trim();
                    instagram = instagram.replace(/^@/, '').replace(/^https?:\/\/(www\.)?instagram\.com\//, '');
                    versoGuidedState.collectedData.qr_instagram = instagram ? '@' + instagram : '';
                    versoGuidedState.currentSubStep = null;
                    if (versoGuidedState.onCompleteCallback) {
                        const callback = versoGuidedState.onCompleteCallback;
                        versoGuidedState.onCompleteCallback = null;
                        callback();
                    }
                }
            }
            
            function confirmWhatsApp(onComplete) {
                const phone = formData.client.phone || '';
                const formattedPhone = phone ? `(${phone.substring(0,2)}) ${phone.substring(2,7)}-${phone.substring(7)}` : '';
                
                addMessage(`Seu WhatsApp é este?\n${formattedPhone || '[não informado]'}`, true);
                
                setTimeout(() => {
                    const options = [
                        {
                            label: 'Sim, está correto',
                            action: () => {
                                versoGuidedState.collectedData.whatsapp = phone;
                                addUserMessage('Sim, está correto');
                                onComplete();
                            }
                        },
                        {
                            label: 'Alterar número',
                            action: () => {
                                addUserMessage('Alterar número');
                                addMessage('Qual seu número de WhatsApp? (formato: DDD + número)', true, null, 'phone');
                                versoGuidedState.currentSubStep = 'waiting_whatsapp';
                                versoGuidedState.onCompleteCallback = onComplete;
                            }
                        }
                    ];
                    const lastMessage = Array.from(chatContainer.querySelectorAll('.chat-message')).pop();
                    setTimeout(() => {
                        addMessage('', true, options);
                    }, 300);
                }, 300);
            }
            
            function askPhoneExtra(onComplete) {
                versoGuidedState.currentSubStep = 'waiting_phone_extra';
                addMessage('Qual telefone adicional deve aparecer? (caso seja diferente do WhatsApp)', true, null, 'phone');
                versoGuidedState.onCompleteCallback = onComplete;
            }
            
            function confirmEmail(onComplete) {
                const email = formData.client.email || '';
                
                addMessage(`Seu e-mail para o cartão é este?\n${email || '[não informado]'}`, true);
                
                setTimeout(() => {
                    const options = [
                        {
                            label: 'Sim, está correto',
                            action: () => {
                                versoGuidedState.collectedData.email = email;
                                addUserMessage('Sim, está correto');
                                onComplete();
                            }
                        },
                        {
                            label: 'Alterar e-mail',
                            action: () => {
                                addUserMessage('Alterar e-mail');
                                versoGuidedState.currentSubStep = 'waiting_email';
                                addMessage('Qual seu e-mail para o cartão?', true, null, 'email');
                                versoGuidedState.onCompleteCallback = onComplete;
                            }
                        }
                    ];
                    setTimeout(() => {
                        addMessage('', true, options);
                    }, 300);
                }, 300);
            }
            
            function askWebsite(onComplete) {
                versoGuidedState.currentSubStep = 'waiting_website';
                addMessage('Qual seu site? (ex: www.empresa.com.br)', true, null, 'text');
                versoGuidedState.onCompleteCallback = onComplete;
            }
            
            function askInstagram(onComplete) {
                versoGuidedState.currentSubStep = 'waiting_instagram';
                addMessage('Qual seu Instagram? (ex: @empresa)', true, null, 'text');
                versoGuidedState.onCompleteCallback = onComplete;
            }
            
            function askAddress(onComplete) {
                addMessage('Deseja colocar endereço completo ou só cidade/estado?', true);
                
                setTimeout(() => {
                    const options = [
                        {
                            label: 'Completo',
                            action: () => {
                                addUserMessage('Completo');
                                const fullAddress = `${formData.address.street || ''}, ${formData.address.number || ''}, ${formData.address.neighborhood || ''}, ${formData.address.city || ''}/${formData.address.state || ''}`.trim();
                                versoGuidedState.collectedData.address = fullAddress;
                                onComplete();
                            }
                        },
                        {
                            label: 'Cidade/Estado',
                            action: () => {
                                addUserMessage('Cidade/Estado');
                                const cityState = `${formData.address.city || ''}/${formData.address.state || ''}`.trim();
                                versoGuidedState.collectedData.address = cityState;
                                onComplete();
                            }
                        }
                    ];
                    setTimeout(() => {
                        addMessage('', true, options);
                    }, 300);
                }, 300);
            }
            
            function askQRCode(onComplete) {
                addMessage('O QR Code deve levar para onde?', true);
                
                setTimeout(() => {
                    const options = [
                        {
                            label: 'WhatsApp',
                            action: () => {
                                versoGuidedState.collectedData.qr = {
                                    enabled: true,
                                    target: 'whatsapp',
                                    value: `https://wa.me/55${formData.client.phone?.replace(/\D/g, '') || ''}`
                                };
                                addUserMessage('WhatsApp');
                                onComplete();
                            }
                        },
                        {
                            label: 'Site',
                            action: () => {
                                addUserMessage('Site');
                                // Se já tem website coletado, usa ele, senão pergunta
                                if (versoGuidedState.collectedData.website) {
                                    versoGuidedState.collectedData.qr = {
                                        enabled: true,
                                        target: 'website',
                                        value: versoGuidedState.collectedData.website
                                    };
                                    onComplete();
                                } else {
                                    versoGuidedState.currentSubStep = 'waiting_qr_website';
                                    addMessage('Qual o link do seu site?', true, null, 'text');
                                    versoGuidedState.onCompleteCallback = () => {
                                        versoGuidedState.collectedData.qr = {
                                            enabled: true,
                                            target: 'website',
                                            value: versoGuidedState.collectedData.qr_website || versoGuidedState.collectedData.website || ''
                                        };
                                        onComplete();
                                    };
                                }
                            }
                        },
                        {
                            label: 'Instagram',
                            action: () => {
                                addUserMessage('Instagram');
                                // Se já tem instagram coletado, usa ele, senão pergunta
                                if (versoGuidedState.collectedData.instagram) {
                                    versoGuidedState.collectedData.qr = {
                                        enabled: true,
                                        target: 'instagram',
                                        value: `https://instagram.com/${versoGuidedState.collectedData.instagram.replace('@', '')}`
                                    };
                                    onComplete();
                                } else {
                                    versoGuidedState.currentSubStep = 'waiting_qr_instagram';
                                    addMessage('Qual seu Instagram?', true, null, 'text');
                                    versoGuidedState.onCompleteCallback = () => {
                                        const insta = versoGuidedState.collectedData.qr_instagram || versoGuidedState.collectedData.instagram || '';
                                        versoGuidedState.collectedData.qr = {
                                            enabled: true,
                                            target: 'instagram',
                                            value: insta.startsWith('http') ? insta : `https://instagram.com/${insta.replace('@', '')}`
                                        };
                                        onComplete();
                                    };
                                }
                            }
                        }
                    ];
                    setTimeout(() => {
                        addMessage('', true, options);
                    }, 300);
                }, 300);
            }
            
            function askSlogan(onComplete) {
                addMessage('Quer uma frase pronta sugerida pela IA ou você já tem uma?', true);
                
                setTimeout(() => {
                    const options = [
                        {
                            label: 'IA sugere',
                            action: () => {
                                addUserMessage('IA sugere');
                                // Aqui poderia chamar IA para sugerir slogans
                                addMessage('Escolha uma das opções:', true);
                                const sloganOptions = [
                                    { label: 'Slogan 1', value: 'Slogan sugerido 1' },
                                    { label: 'Slogan 2', value: 'Slogan sugerido 2' },
                                    { label: 'Slogan 3', value: 'Slogan sugerido 3' }
                                ].map(s => ({
                                    label: s.label,
                                    action: () => {
                                        versoGuidedState.collectedData.slogan = s.value;
                                        addUserMessage(s.label);
                                        onComplete();
                                    }
                                }));
                                setTimeout(() => addMessage('', true, sloganOptions), 300);
                            }
                        },
                        {
                            label: 'Eu tenho',
                            action: () => {
                                addUserMessage('Eu tenho');
                                versoGuidedState.currentSubStep = 'waiting_slogan';
                                addMessage('Digite sua frase (máximo 60 caracteres):', true, null, 'text');
                                versoGuidedState.onCompleteCallback = onComplete;
                            }
                        }
                    ];
                    setTimeout(() => {
                        addMessage('', true, options);
                    }, 300);
                }, 300);
            }
            
            function askServices(onComplete) {
                const segment = formData.briefing?.segment || formData.briefing?.q_segment || '';
                versoGuidedState.currentSubStep = 'waiting_services';
                addMessage('Selecione até 3 serviços principais do seu negócio (separados por vírgula):', true, null, 'text');
                versoGuidedState.onCompleteCallback = onComplete;
            }
            
            // Etapa C: Confirmação visual
            function showVersoConfirmation() {
                const backData = {
                    include: versoGuidedState.selectedItems,
                    fields: {
                        full_name: versoGuidedState.collectedData.full_name || formData.client.name || '',
                        job_title: versoGuidedState.collectedData.job_title || '',
                        whatsapp: versoGuidedState.collectedData.whatsapp || formData.client.phone || '',
                        phone_extra: versoGuidedState.collectedData.phone_extra || '',
                        email: versoGuidedState.collectedData.email || formData.client.email || '',
                        website: versoGuidedState.collectedData.website || '',
                        instagram: versoGuidedState.collectedData.instagram || '',
                        address: versoGuidedState.collectedData.address || '',
                        slogan: versoGuidedState.collectedData.slogan || '',
                        services: versoGuidedState.collectedData.services || []
                    },
                    qr: versoGuidedState.collectedData.qr || { enabled: false, target: null, value: null }
                };
                
                // Monta resumo visual
                let summary = '📋 **Resumo do Verso:**\n\n';
                summary += '**Itens selecionados:**\n';
                backData.include.forEach(itemId => {
                    const labels = {
                        'name_title': 'Nome e cargo',
                        'whatsapp': 'WhatsApp',
                        'phone_extra': 'Telefone',
                        'email': 'E-mail',
                        'website': 'Site',
                        'instagram': 'Instagram',
                        'address': 'Endereço',
                        'qr_code': 'QR Code',
                        'slogan': 'Slogan',
                        'services': 'Serviços'
                    };
                    summary += `• ${labels[itemId] || itemId}\n`;
                });
                
                summary += '\n**Dados confirmados:**\n';
                if (backData.fields.full_name) summary += `Nome: ${backData.fields.full_name}\n`;
                if (backData.fields.job_title) summary += `Cargo: ${backData.fields.job_title}\n`;
                if (backData.fields.whatsapp) summary += `WhatsApp: ${backData.fields.whatsapp}\n`;
                if (backData.fields.email) summary += `E-mail: ${backData.fields.email}\n`;
                
                addMessage(summary, true);
                
                setTimeout(() => {
                    addMessage('Confirmar informações do verso?', true);
                    
                    const options = [
                        {
                            label: '✅ Confirmar',
                            action: () => {
                                // Salva dados estruturados
                                formData.briefing.verso_guided = backData;
                                formData.briefing['q_verso_informacoes'] = JSON.stringify(backData);
                                updateFormField('form-q-verso_informacoes', JSON.stringify(backData));
                                addUserMessage('Confirmar');
                                
                                // Finaliza e avança para próxima pergunta
                                versoGuidedState = { selectedItems: [], currentSubStep: null, collectedData: {} };
                                currentQuestionIndex++;
                                isProcessingBriefing = false;
                                setTimeout(() => startBriefing(), 500);
                            }
                        },
                        {
                            label: '✏️ Ajustar',
                            action: () => {
                                addUserMessage('Ajustar');
                                // Volta para seleção inicial
                                versoGuidedState = { selectedItems: [], currentSubStep: null, collectedData: {} };
                                showVersoItemSelection();
                            }
                        }
                    ];
                    setTimeout(() => {
                        addMessage('', true, options);
                    }, 300);
                }, 500);
            }
            
            // Adiciona upload de arquivo
            function addFileUpload(question) {
                const uploadDiv = document.createElement('div');
                uploadDiv.style.marginTop = '10px';
                
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*,.pdf,.doc,.docx';
                fileInput.style.display = 'none';
                
                const uploadBtn = document.createElement('button');
                uploadBtn.type = 'button';
                uploadBtn.className = 'chat-option';
                uploadBtn.textContent = '📎 Escolher arquivo';
                uploadBtn.style.cursor = 'pointer';
                
                uploadBtn.addEventListener('click', () => fileInput.click());
                
                fileInput.addEventListener('change', function(e) {
                    if (e.target.files.length > 0) {
                        const file = e.target.files[0];
                        // Aqui você pode fazer upload do arquivo via AJAX
                        // Por enquanto, apenas salva o nome
                        formData.briefing['q_' + question.id] = file.name;
                        updateFormField('form-q-' + question.id, file.name);
                        addUserMessage('📎 ' + file.name);
                        uploadDiv.remove();
                        currentQuestionIndex++;
                        setTimeout(() => startBriefing(), 500);
                    }
                });
                
                uploadDiv.appendChild(fileInput);
                uploadDiv.appendChild(uploadBtn);
                chatContainer.appendChild(uploadDiv);
            }
            
            // Mostra resumo e permite correções
            function showSummary() {
                const summaryHTML = `
                    <div style="background: #f8f9fa; border: 2px solid #023A8D; border-radius: 12px; padding: 20px; margin: 20px 0;">
                        <h4 style="margin: 0 0 15px 0; color: #023A8D;">📋 Resumo dos Dados Informados</h4>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: #555;">Dados Cadastrais:</strong>
                            <div style="margin-left: 15px; margin-top: 5px;">
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #666;">Nome:</span> 
                                    <strong>${formData.client.name || 'Não informado'}</strong>
                                    <button onclick="correctField('name')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #666;">Email:</span> 
                                    <strong>${formData.client.email || 'Não informado'}</strong>
                                    <button onclick="correctField('email')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #666;">Telefone:</span> 
                                    <strong>${formData.client.phone || 'Não informado'}</strong>
                                    <button onclick="correctField('phone')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #666;">CPF/CNPJ:</span> 
                                    <strong>${formData.client.cpf_cnpj || 'Não informado'}</strong>
                                    <button onclick="correctField('cpf_cnpj')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button>
                                </div>
                            </div>
                        </div>
                        
                        ${formData.address.cep ? `
                        <div style="margin-bottom: 15px;">
                            <strong style="color: #555;">Endereço:</strong>
                            <div style="margin-left: 15px; margin-top: 5px;">
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #666;">CEP:</span> <strong>${formData.address.cep}</strong>
                                    <button onclick="correctField('cep')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button>
                                </div>
                                ${formData.address.street ? `<div style="margin-bottom: 4px;"><span style="color: #666;">Rua:</span> <strong>${formData.address.street}</strong></div>` : ''}
                                ${formData.address.number ? `<div style="margin-bottom: 4px;"><span style="color: #666;">Número:</span> <strong>${formData.address.number}</strong> <button onclick="correctField('address_number')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button></div>` : ''}
                                ${formData.address.complement ? `<div style="margin-bottom: 4px;"><span style="color: #666;">Complemento:</span> <strong>${formData.address.complement}</strong> <button onclick="correctField('address_complement')" style="margin-left: 10px; padding: 4px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">✏️ Corrigir</button></div>` : ''}
                                ${formData.address.neighborhood ? `<div style="margin-bottom: 4px;"><span style="color: #666;">Bairro:</span> <strong>${formData.address.neighborhood}</strong></div>` : ''}
                                ${formData.address.city ? `<div style="margin-bottom: 4px;"><span style="color: #666;">Cidade:</span> <strong>${formData.address.city}</strong></div>` : ''}
                                ${formData.address.state ? `<div style="margin-bottom: 4px;"><span style="color: #666;">Estado:</span> <strong>${formData.address.state}</strong></div>` : ''}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <button onclick="closeSummary()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px;">Fechar</button>
                            <button onclick="continueToBriefing()" style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer;">Continuar</button>
                        </div>
                    </div>
                `;
                
                addMessage(summaryHTML, true, null, null, true);
            }
            
            // Função global para corrigir campo (chamada pelos botões)
            window.correctField = function(fieldName) {
                let inputType = fieldName;
                let question = null;
                
                // Mapeia nomes de campos para tipos de input
                const fieldMapping = {
                    'name': 'name',
                    'email': 'email',
                    'phone': 'phone',
                    'cpf_cnpj': 'cpf_cnpj',
                    'cep': 'cep',
                    'address_number': 'text',
                    'address_complement': 'text'
                };
                
                inputType = fieldMapping[fieldName] || 'text';
                
                // Determina qual pergunta fazer
                let message = '';
                if (fieldName === 'name') {
                    message = 'Qual seu nome completo?';
                    currentStep = 'client_data';
                } else if (fieldName === 'email') {
                    message = 'Qual seu email?';
                    currentStep = 'client_data';
                } else if (fieldName === 'phone') {
                    message = 'Qual seu telefone ou celular? (com DDD)';
                    currentStep = 'client_data';
                } else if (fieldName === 'cpf_cnpj') {
                    message = 'Qual seu CPF ou CNPJ?';
                    currentStep = 'client_data';
                } else if (fieldName === 'cep') {
                    message = 'Qual seu CEP?';
                    currentStep = 'address';
                    // Limpa dados do endereço para buscar novamente
                    formData.address = { cep: '' };
                } else if (fieldName === 'address_number') {
                    message = 'Qual o número do endereço?';
                    currentStep = 'address';
                    formData.address.number = '';
                } else if (fieldName === 'address_complement') {
                    message = 'Tem complemento? (opcional - deixe em branco para pular)';
                    currentStep = 'address';
                    formData.address.complement = undefined;
                }
                
                // Limpa o valor anterior
                if (fieldName.startsWith('address_')) {
                    // Já foi tratado acima
                } else if (fieldName === 'name') {
                    formData.client.name = '';
                } else if (fieldName === 'email') {
                    formData.client.email = '';
                } else if (fieldName === 'phone') {
                    formData.client.phone = '';
                } else if (fieldName === 'cpf_cnpj') {
                    formData.client.cpf_cnpj = '';
                }
                
                addMessage('Vou corrigir esse dado. ' + message, true, null, inputType);
            };
            
            // Função para fechar resumo
            window.closeSummary = function() {
                // Remove última mensagem (resumo) se necessário
                const messages = chatContainer.querySelectorAll('.chat-message');
                if (messages.length > 0) {
                    const lastMessage = messages[messages.length - 1];
                    if (lastMessage.querySelector('.chat-bubble')?.innerHTML.includes('Resumo dos Dados')) {
                        lastMessage.remove();
                    }
                }
            };
            
            // Função para continuar após resumo
            window.continueToBriefing = function() {
                closeSummary();
                if (currentStep !== 'briefing') {
                    currentStep = 'briefing';
                    currentQuestionIndex = 0;
                    startBriefing();
                }
            };
            
            // Mostra aprovação
            function showApproval() {
                // Primeiro mostra resumo
                showSummary();
                
                setTimeout(() => {
                    addMessage('Perfeito! Agora vou iniciar a criação do seu cartão.\n\nAntes de começar, você pode revisar as informações ou iniciar a criação diretamente.', true, [
                        {
                            label: 'Iniciar criação do cartão',
                            icon: '🎨',
                            action: () => {
                                startCardGeneration();
                            }
                        },
                        {
                            label: 'Revisar informações',
                            icon: '📋',
                            action: () => {
                                addUserMessage('Revisar informações');
                                showSummary();
                                setTimeout(() => {
                                    addMessage('Deseja alterar alguma informação? Você pode voltar às etapas anteriores ou iniciar a criação agora.', true, [
                                        {
                                            label: 'Iniciar criação do cartão',
                                            icon: '🎨',
                                            action: () => {
                                                startCardGeneration();
                                            }
                                        },
                                        {
                                            label: 'Voltar ao briefing',
                                            icon: '←',
                                            action: () => {
                                                addUserMessage('Voltar ao briefing');
                                                currentStep = 'briefing';
                                                currentQuestionIndex = 0;
                                                setTimeout(() => startBriefing(), 500);
                                            }
                                        }
                                    ]);
                                }, 300);
                            }
                        }
                    ]);
                }, 500);
            }
            
            // Inicia geração do cartão
            async function startCardGeneration() {
                addUserMessage('Iniciar criação do cartão');
                
                // Valida se o briefing está completo
                const validationResult = await validateIntakeReady();
                
                if (!validationResult.ready) {
                    // Se faltar dados, mostra mensagem e volta para a etapa específica
                    addMessage(`Antes de iniciar a criação, preciso de algumas informações adicionais:\n\n${validationResult.missing.join('\n')}\n\nVou te ajudar a completar essas informações agora.`, true);
                    
                    // Volta para a etapa que falta
                    if (validationResult.missingStep) {
                        setTimeout(() => {
                            if (validationResult.missingStep === 'briefing') {
                                currentStep = 'briefing';
                                currentQuestionIndex = validationResult.missingQuestionIndex || 0;
                                startBriefing();
                            } else {
                                currentStep = validationResult.missingStep;
                                nextStep();
                            }
                        }, 1000);
                    }
                    return;
                }
                
                // Mostra mensagem de progresso
                addMessage('Gerando seu cartão... (isso pode levar alguns instantes) ⏳', true);
                
                // Chama endpoint para iniciar geração
                try {
                    const response = await fetch('<?= pixelhub_url('/client-portal/orders/start-generation') ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            token: token
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        addMessage('Aqui está seu cartão pronto! 🎉\n\n📄 PDF para impressão\n📱 PNG digital\n\nVocê pode baixar os arquivos abaixo.', true);
                        
                        // Mostra links de download se disponíveis
                        if (data.deliverables) {
                            const deliverablesHTML = `
                                <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    ${data.deliverables.pdf ? `<a href="${data.deliverables.pdf}" target="_blank" style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">📄 Baixar PDF</a>` : ''}
                                    ${data.deliverables.png ? `<a href="${data.deliverables.png}" target="_blank" style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">📱 Baixar PNG</a>` : ''}
                                </div>
                            `;
                            addMessage(deliverablesHTML, true, null, null, true);
                        }
                        
                        // Atualiza status do pedido
                        if (data.order_status) {
                            document.querySelector('.step.approval')?.classList.add('completed');
                        }
                    } else {
                        addMessage(`Ops, algo deu errado ao gerar o cartão. ${data.error || 'Tente novamente em alguns instantes.'}`, true);
                    }
                } catch (error) {
                    console.error('Erro ao iniciar geração:', error);
                    addMessage('Erro ao conectar com o servidor. Tente novamente em alguns instantes.', true);
                }
            }
            
            // Valida se o briefing está completo
            async function validateIntakeReady() {
                const missing = [];
                let missingStep = null;
                let missingQuestionIndex = null;
                
                // Validações obrigatórias
                if (!formData.client?.name && !formData.briefing?.q_empresa_nome) {
                    missing.push('• Nome completo');
                    missingStep = 'client_data';
                }
                
                if (!formData.client?.phone && !formData.briefing?.back_side?.fields?.whatsapp) {
                    missing.push('• WhatsApp ou telefone');
                    if (!missingStep) missingStep = 'client_data';
                }
                
                if (!formData.client?.email && !formData.briefing?.back_side?.fields?.email) {
                    missing.push('• E-mail');
                    if (!missingStep) missingStep = 'client_data';
                }
                
                // Validações de briefing específicas para cartão de visita
                if (formData.service_code === 'business_card') {
                    if (!formData.briefing?.segment) {
                        missing.push('• Segmento do negócio');
                        missingStep = 'briefing';
                        // Encontra índice da pergunta de segmento
                        if (questionsData) {
                            const segmentIndex = questionsData.findIndex(q => q.id === 'segment' || q.type === 'segment');
                            if (segmentIndex >= 0) missingQuestionIndex = segmentIndex;
                        }
                    }
                    
                    if (!formData.briefing?.cores_preferencia) {
                        missing.push('• Preferência de cores (Claras/Escuras/Neutras/Coloridas)');
                        if (!missingStep) {
                            missingStep = 'briefing';
                            if (questionsData) {
                                const colorsIndex = questionsData.findIndex(q => q.id === 'cores_preferencia');
                                if (colorsIndex >= 0) missingQuestionIndex = colorsIndex;
                            }
                        }
                    }
                    
                    // Se QR foi selecionado, deve estar configurado
                    if (formData.briefing?.back_side?.include?.includes('qr_code')) {
                        if (!formData.briefing?.back_side?.qr?.value) {
                            missing.push('• Configuração do QR Code');
                            if (!missingStep) {
                                missingStep = 'briefing';
                                if (questionsData) {
                                    const versoIndex = questionsData.findIndex(q => q.type === 'verso_guided');
                                    if (versoIndex >= 0) missingQuestionIndex = versoIndex;
                                }
                            }
                        }
                    }
                }
                
                return {
                    ready: missing.length === 0,
                    missing: missing,
                    missingStep: missingStep,
                    missingQuestionIndex: missingQuestionIndex
                };
            }
            
            // Submete formulário
            async function submitForm() {
                document.getElementById('main-submit-btn').click();
            }
            
            // Mensagem de boas-vindas especial
            function showWelcomeMessage() {
                const welcomeHTML = `
                    <div class="welcome-message">
                        <h3>Olá! 👋</h3>
                        <p><strong>Bem-vindo(a) à Pixel12 Digital!</strong></p>
                        <p>Estamos aqui para criar seu <strong><?= htmlspecialchars($serviceName) ?></strong> com cuidado e atenção aos detalhes que você precisa.</p>
                        <p style="margin-top: 15px; font-weight: 600; color: #023A8D;">Vou te guiar passo a passo durante o processo!</p>
                    </div>
                `;
                
                addMessage(welcomeHTML, true, null, null, true);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                // Aguarda um pouco e pergunta se já é cliente
                setTimeout(() => {
                    nextStep(); // Isso vai acionar a verificação de cliente
                }, 1500);
            }
            
            // Inicia chat
            if (currentStep === 'greeting') {
                console.log('Mostrando mensagem de boas-vindas...');
                showWelcomeMessage();
            } else {
                console.log('Continuando de onde parou...', currentStep);
                // Continua de onde parou, mas mostra uma saudação rápida
                addMessage('Olá! Continuando de onde paramos...', true);
                setTimeout(() => {
                    nextStep();
                }, 1000);
            }
        }
        
        // Preview de arquivos selecionados
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const questionId = this.id.replace('file_', '');
                const fileList = document.getElementById('file-list-' + questionId);
                const preview = document.getElementById('file-preview-' + questionId);
                const previewName = document.getElementById('file-preview-name-' + questionId);
                const previewSize = document.getElementById('file-preview-size-' + questionId);
                const previewImage = document.getElementById('file-preview-image-' + questionId);
                
                if (this.files.length > 0) {
                    const file = this.files[0];
                    
                    // Atualiza lista de arquivos
                    if (fileList) {
                        fileList.innerHTML = '📎 ' + file.name;
                    }
                    
                    // Mostra preview
                    if (preview) {
                        preview.classList.add('active');
                        
                        if (previewName) {
                            previewName.textContent = file.name;
                        }
                        
                        if (previewSize) {
                            previewSize.textContent = 'Tamanho: ' + formatFileSize(file.size);
                        }
                        
                        // Se for imagem, mostra miniatura
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                if (previewImage) {
                                    previewImage.src = e.target.result;
                                    previewImage.style.display = 'block';
                                }
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // Esconde imagem se não for arquivo de imagem
                            if (previewImage) {
                                previewImage.style.display = 'none';
                            }
                        }
                    }
                } else {
                    // Limpa preview se não houver arquivo
                    if (preview) {
                        preview.classList.remove('active');
                    }
                    if (fileList) {
                        fileList.innerHTML = '';
                    }
                    if (previewImage) {
                        previewImage.style.display = 'none';
                        previewImage.src = '';
                    }
                }
            });
        });
    </script>
</body>
</html>

