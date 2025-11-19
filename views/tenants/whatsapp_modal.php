<!-- Modal de WhatsApp Genérico -->
<div id="whatsapp-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 800px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: #023A8D;">Enviar Mensagem WhatsApp</h2>
            <button onclick="closeWhatsAppModal()" 
                    style="background: #c33; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600;">
                ✕ Fechar
            </button>
        </div>

        <!-- Passo 1: Escolher Template -->
        <div id="step-template" style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px; color: #023A8D;">Passo 1: Escolher Template</h3>
            <div style="margin-bottom: 15px;">
                <label for="template-select" style="display: block; margin-bottom: 5px; font-weight: 600;">Template:</label>
                <select id="template-select" 
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="">Selecione um template...</option>
                </select>
            </div>
            <button onclick="loadTemplate()" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Carregar Template
            </button>
        </div>

        <!-- Passo 2: Preview e Envio -->
        <div id="step-preview" style="display: none;">
            <h3 style="margin-bottom: 15px; color: #023A8D;">Passo 2: Preview e Envio</h3>
            
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
                <label for="message-content" style="display: block; margin-bottom: 5px; font-weight: 600;">Mensagem (pode editar):</label>
                <textarea id="message-content" 
                          rows="10" 
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical; font-family: inherit;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="copyMessage()" 
                        style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    📋 Copiar Mensagem
                </button>
                <button onclick="openWhatsApp()" 
                        id="btn-open-whatsapp"
                        style="background: #25D366; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; display: none;">
                    📱 Abrir WhatsApp Web
                </button>
            </div>
        </div>

        <div id="modal-error" style="display: none; background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-top: 15px;"></div>
    </div>
</div>

<script>
let currentTenantId = null;
let currentTemplates = [];
let currentMessage = '';
let currentWhatsAppLink = '';

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
    document.getElementById('btn-open-whatsapp').style.display = 'none';
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
    
    if (!templateId || !currentTenantId) {
        showError('Selecione um template primeiro.');
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
    btn.textContent = '✓ Copiado!';
    btn.style.background = '#3c3';
    setTimeout(() => {
        btn.textContent = originalText;
        btn.style.background = '#6c757d';
    }, 2000);
}

function openWhatsApp() {
    const message = document.getElementById('message-content').value;
    if (!currentWhatsAppLink && message) {
        // Se não tem link, tenta gerar um básico (precisa do telefone normalizado)
        showError('Telefone não disponível para gerar link.');
        return;
    }
    
    // Atualiza link com mensagem atual (caso tenha sido editada)
    if (message && currentWhatsAppLink) {
        const phoneMatch = currentWhatsAppLink.match(/wa\.me\/(\d+)/);
        if (phoneMatch) {
            const phone = phoneMatch[1];
            const encodedMessage = encodeURIComponent(message);
            const link = `https://wa.me/${phone}?text=${encodedMessage}`;
            window.open(link, '_blank');
        } else {
            window.open(currentWhatsAppLink, '_blank');
        }
    } else {
        window.open(currentWhatsAppLink, '_blank');
    }
}

function showError(message) {
    const errorDiv = document.getElementById('modal-error');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

// Fecha modal ao clicar fora
document.getElementById('whatsapp-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeWhatsAppModal();
    }
});
</script>

