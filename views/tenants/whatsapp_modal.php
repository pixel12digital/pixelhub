<!-- Modal de WhatsApp Genérico -->
<div id="whatsapp-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 800px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: #023A8D;">Enviar Mensagem WhatsApp</h2>
            <button onclick="closeWhatsAppModal()" 
                    style="background: #c33; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Fechar
            </button>
        </div>

        <!-- Seleção de Template (opcional) -->
        <div id="step-template" style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px; color: #023A8D;">Template (opcional)</h3>
            <div style="margin-bottom: 15px;">
                <label for="template-select" style="display: block; margin-bottom: 5px; font-weight: 600;">Template:</label>
                <select id="template-select" 
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="">Selecione um template (opcional)...</option>
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">Você pode escolher um template ou escrever uma mensagem do zero.</small>
            </div>
            <button onclick="loadTemplate()" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Carregar Template
            </button>
            <button onclick="startWithoutTemplate()" 
                    style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; margin-left: 10px;">
                Iniciar conversa sem template
            </button>
        </div>

        <!-- Preview e Envio -->
        <div id="step-preview" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #023A8D;">Mensagem e Envio</h3>
            
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div style="margin-bottom: 10px;">
                    <strong>Cliente:</strong> <span id="preview-tenant-name">-</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Telefone:</strong> <span id="preview-phone">-</span>
                </div>
                <div>
                    <strong>Template:</strong> <span id="preview-template-name">-</span>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label for="message-content" style="display: block; margin-bottom: 5px; font-weight: 600;">Mensagem:</label>
                <textarea id="message-content" 
                          rows="10" 
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical; font-family: inherit;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="copyMessage()" 
                        style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                    Copiar Mensagem
                </button>
                <button onclick="openWhatsApp()" 
                        id="btn-open-whatsapp"
                        style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; display: none; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    Abrir WhatsApp Web
                </button>
            </div>
        </div>

        <div id="modal-error" style="display: none; background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-top: 15px;"></div>
        <div id="modal-success" style="display: none; background: #efe; color: #3c3; padding: 10px; border-radius: 4px; margin-top: 15px;"></div>
    </div>
</div>

<script>
let currentTenantId = null;
let currentTemplates = [];
let currentMessage = '';
let currentWhatsAppLink = '';
let currentTemplateId = null; // Armazena o template_id selecionado
let currentPhoneRaw = ''; // Armazena o telefone raw (não normalizado)

function openWhatsAppModal(tenantId) {
    currentTenantId = tenantId;
    document.getElementById('whatsapp-modal').style.display = 'block';
    loadTemplates();
}

function closeWhatsAppModal() {
    document.getElementById('whatsapp-modal').style.display = 'none';
    document.getElementById('step-preview').style.display = 'none';
    document.getElementById('template-select').value = '';
    document.getElementById('message-content').value = '';
    currentMessage = '';
    currentWhatsAppLink = '';
    currentTemplateId = null;
    currentPhoneRaw = '';
    document.getElementById('btn-open-whatsapp').style.display = 'none';
}

function startWithoutTemplate() {
    if (!currentTenantId) {
        showError('ID do cliente não disponível.');
        return;
    }

    // Busca dados do tenant sem template
    fetch('<?= pixelhub_url('/settings/whatsapp-templates/template-data') ?>?template_id=&tenant_id=' + currentTenantId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showError(data.error);
                return;
            }

            // Limpa template selecionado
            document.getElementById('template-select').value = '';
            currentTemplateId = null;
            
            // Preenche dados básicos
            if (data.tenant) {
                document.getElementById('preview-tenant-name').textContent = data.tenant.name || '-';
            }
            if (data.phone) {
                document.getElementById('preview-phone').textContent = data.phone || '-';
                currentPhoneRaw = data.phone;
                // Gera link básico do WhatsApp Web (sem mensagem ainda)
                const phoneNormalized = data.phone_normalized || data.phone.replace(/[^0-9]/g, '');
                if (phoneNormalized) {
                    currentWhatsAppLink = 'https://web.whatsapp.com/send?phone=' + phoneNormalized;
                }
            } else {
                showError('Cliente não possui telefone cadastrado.');
                return;
            }
            document.getElementById('preview-template-name').textContent = 'Sem template / mensagem livre';
            
            // Limpa textarea e habilita
            document.getElementById('message-content').value = '';
            currentMessage = '';
            
            // Mostra preview e habilita botão
            document.getElementById('step-preview').style.display = 'block';
            document.getElementById('btn-open-whatsapp').style.display = 'inline-block';
            
            document.getElementById('modal-error').style.display = 'none';
        })
        .catch(err => {
            console.error('Erro ao carregar dados do tenant:', err);
            showError('Erro ao carregar dados do cliente. Tente novamente.');
        });
}

function loadTemplates() {
    fetch('<?= pixelhub_url('/settings/whatsapp-templates/ajax-templates') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.templates) {
                currentTemplates = data.templates;
                const select = document.getElementById('template-select');
                select.innerHTML = '<option value="">Selecione um template...</option>';
                
                data.templates.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    const categoryLabel = template.category === 'comercial' ? 'Comercial' : 
                                        template.category === 'campanha' ? 'Campanha' : 'Geral';
                    option.textContent = template.name + ' (' + categoryLabel + ')';
                    select.appendChild(option);
                });
            } else {
                showError('Nenhum template ativo encontrado.');
            }
        })
        .catch(err => {
            console.error('Erro ao carregar templates:', err);
            showError('Erro ao carregar templates. Tente recarregar a página.');
        });
}

function loadTemplate() {
    const templateId = document.getElementById('template-select').value;
    
    if (!currentTenantId) {
        showError('ID do cliente não disponível.');
        return;
    }
    
    if (!templateId) {
        showError('Selecione um template primeiro ou use "Iniciar conversa sem template".');
        return;
    }

    // Mostra loading
    document.getElementById('step-preview').style.display = 'block';
    document.getElementById('message-content').value = 'Carregando...';
    document.getElementById('btn-open-whatsapp').style.display = 'none';

    fetch('<?= pixelhub_url('/settings/whatsapp-templates/template-data') ?>?template_id=' + templateId + '&tenant_id=' + currentTenantId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showError(data.error);
                return;
            }

            if (data.success) {
                currentMessage = data.message || '';
                currentWhatsAppLink = data.whatsapp_link || '';
                currentTemplateId = templateId ? parseInt(templateId) : null; // Armazena template_id
                currentPhoneRaw = data.phone || ''; // Armazena telefone raw
                
                document.getElementById('preview-tenant-name').textContent = data.tenant.name || '-';
                document.getElementById('preview-phone').textContent = data.phone || '-';
                document.getElementById('preview-template-name').textContent = data.template.name || '-';
                document.getElementById('message-content').value = currentMessage;
                
                if (currentWhatsAppLink) {
                    document.getElementById('btn-open-whatsapp').style.display = 'inline-block';
                }
                
                document.getElementById('modal-error').style.display = 'none';
            } else {
                showError('Erro ao carregar template.');
            }
        })
        .catch(err => {
            console.error('Erro ao carregar template:', err);
            showError('Erro ao carregar template. Tente novamente.');
        });
}

function copyMessage() {
    const textarea = document.getElementById('message-content');
    textarea.select();
    document.execCommand('copy');
    
    // Feedback visual
    const btn = event.target;
    const originalText = btn.textContent;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><polyline points="20 6 9 17 4 12"/></svg> Copiado!';
    btn.style.background = '#3c3';
    setTimeout(() => {
        btn.textContent = originalText;
        btn.style.background = '#6c757d';
    }, 2000);
}

function openWhatsApp() {
    const message = document.getElementById('message-content').value;
    
    // Validações básicas
    if (!currentTenantId) {
        showError('ID do cliente não disponível.');
        return;
    }
    
    if (!message || message.trim() === '') {
        showError('Mensagem não pode estar vazia.');
        return;
    }
    
    // Normaliza telefone para gerar link do WhatsApp Web
    let phoneNormalized = null;
    if (currentPhoneRaw) {
        phoneNormalized = currentPhoneRaw.replace(/[^0-9]/g, '');
    } else if (currentWhatsAppLink) {
        // Tenta extrair do link existente (suporta tanto wa.me quanto web.whatsapp.com)
        const phoneMatch = currentWhatsAppLink.match(/(?:wa\.me|web\.whatsapp\.com\/send\?phone=)(\d+)/);
        if (phoneMatch) {
            phoneNormalized = phoneMatch[1];
        }
    }
    
    if (!phoneNormalized) {
        showError('Telefone não disponível para gerar link.');
        return;
    }
    
    // Prepara dados para o log
    const logData = {
        tenant_id: currentTenantId,
        template_id: currentTemplateId, // Pode ser null se nenhum template foi usado
        phone_raw: currentPhoneRaw || '', // Telefone que o usuário está vendo no modal
        message: message.trim() // Mensagem final do textarea (já editada pelo usuário)
    };
    
    // Registra log antes de abrir WhatsApp
    fetch('<?= pixelhub_url('/tenants/whatsapp-generic-log') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(logData)
    })
    .then(response => response.json())
    .then(data => {
        // Monta URL do WhatsApp Web (sempre usa web.whatsapp.com)
        const encodedMessage = encodeURIComponent(message);
        const whatsappWebUrl = `https://web.whatsapp.com/send?phone=${phoneNormalized}&text=${encodedMessage}`;
        
        if (data.success) {
            // Log registrado com sucesso - abre WhatsApp Web
            window.open(whatsappWebUrl, '_blank', 'noopener,noreferrer');
            
            // Feedback de sucesso
            showSuccess('Contato registrado com sucesso!');
            
            // Atualiza timeline via AJAX (se a função estiver disponível)
            if (typeof updateWhatsAppTimeline === 'function' && typeof window.currentTenantId !== 'undefined') {
                updateWhatsAppTimeline(window.currentTenantId);
            }
            
            // Fecha modal automaticamente após 1 segundo (tempo para ver o toast)
            setTimeout(() => {
                closeWhatsAppModal();
            }, 1000);
        } else {
            // Erro ao registrar log - exibe mensagem e mantém modal aberto
            showError('Aviso: Não foi possível registrar o log. ' + (data.message || 'Erro desconhecido'));
            
            // Mesmo com erro no log, abre o WhatsApp (não bloqueia o envio)
            window.open(whatsappWebUrl, '_blank', 'noopener,noreferrer');
            // Modal permanece aberto para permitir correção/reenvio
        }
    })
    .catch(err => {
        console.error('Erro ao registrar log:', err);
        
        // Monta URL do WhatsApp Web mesmo em caso de erro
        const encodedMessage = encodeURIComponent(message);
        const whatsappWebUrl = `https://web.whatsapp.com/send?phone=${phoneNormalized}&text=${encodedMessage}`;
        
        // Em caso de erro de rede, exibe aviso mas não bloqueia
        showError('Aviso: Erro ao registrar log. O WhatsApp será aberto mesmo assim.');
        
        // Abre WhatsApp Web mesmo com erro no log
        window.open(whatsappWebUrl, '_blank', 'noopener,noreferrer');
        // Modal permanece aberto para permitir correção/reenvio
    });
}

function showError(message) {
    const errorDiv = document.getElementById('modal-error');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    // Esconde mensagem de sucesso se houver
    document.getElementById('modal-success').style.display = 'none';
}

function showSuccess(message) {
    const successDiv = document.getElementById('modal-success');
    successDiv.textContent = message;
    successDiv.style.display = 'block';
    // Esconde mensagem de erro se houver
    document.getElementById('modal-error').style.display = 'none';
    // Auto-esconde após 3 segundos
    setTimeout(() => {
        successDiv.style.display = 'none';
    }, 3000);
}

// Fecha modal ao clicar fora
document.getElementById('whatsapp-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeWhatsAppModal();
    }
});
</script>

