/**
 * JavaScript para gerenciar uploads e exclusões via AJAX na aba Docs & Backups
 */

(function() {
    'use strict';

    /**
     * Envia formulário via AJAX
     */
    function sendAjaxForm(form, targetContainerId) {
        return new Promise((resolve, reject) => {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        
        // Desabilita botão e mostra feedback
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';
        }

        // Esconde mensagens de erro anteriores
        const errorContainer = document.getElementById('wp-backup-error-message') || 
                               document.getElementById('doc-error-message');
        if (errorContainer) {
            errorContainer.style.display = 'none';
            errorContainer.textContent = '';
        }

        fetch(form.action, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // Verifica o Content-Type da resposta
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // Se não for JSON, pode ser um erro do servidor
                return response.text().then(text => {
                    console.error('Resposta não é JSON:', text);
                    throw new Error('Resposta inválida do servidor. Recarregue a página.');
                });
            }

            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Erro ao processar a requisição.');
                }).catch((err) => {
                    if (err.message) {
                        throw err;
                    }
                    throw new Error('Erro ao processar a requisição.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }

            if (!data || typeof data.success === 'undefined') {
                showError('Erro inesperado ao processar a requisição.');
                reject(new Error('Resposta inválida do servidor'));
                return;
            }

            if (!data.success) {
                showError(data.message || 'Erro ao processar a requisição.');
                reject(new Error(data.message || 'Erro ao processar a requisição'));
                return;
            }

            // Atualiza a tabela com o HTML retornado
            if (data.html && targetContainerId) {
                // Mapeia os IDs dos containers
                let containerId;
                if (targetContainerId === 'tenant-wp-backups') {
                    containerId = 'wp-backups-table-container';
                } else if (targetContainerId === 'tenant-documents') {
                    containerId = 'documents-table-container';
                } else {
                    containerId = targetContainerId + '-table-container';
                }
                
                console.log('Tentando atualizar container:', containerId, 'targetContainerId:', targetContainerId);
                const container = document.getElementById(containerId);
                if (container) {
                    console.log('Container encontrado, atualizando HTML...');
                    container.innerHTML = data.html;
                    console.log('Tabela atualizada com sucesso:', containerId);
                } else {
                    console.error('Container não encontrado:', containerId);
                    console.error('Elementos disponíveis:', {
                        'wp-backups-table-container': document.getElementById('wp-backups-table-container'),
                        'documents-table-container': document.getElementById('documents-table-container'),
                        'tenant-wp-backups': document.getElementById('tenant-wp-backups'),
                        'tenant-documents': document.getElementById('tenant-documents')
                    });
                    showError('Erro ao atualizar a tabela. Recarregue a página.');
                }
            } else {
                console.warn('HTML ou targetContainerId não fornecido:', { html: !!data.html, targetContainerId });
            }

            // Mostra mensagem de sucesso
            if (data.message) {
                showSuccess(data.message);
            }

            // Limpa formulário se for upload
            if (form.id === 'form-wp-backup' || form.id === 'form-tenant-document') {
                form.reset();
            }

            resolve(data);
        })
        .catch(err => {
            console.error('Erro na requisição AJAX:', err);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
            showError(err.message || 'Erro de comunicação com o servidor.');
            reject(err);
        });
        });
    }

    /**
     * Mostra mensagem de erro
     */
    function showError(message) {
        const errorContainer = document.getElementById('wp-backup-error-message') || 
                               document.getElementById('doc-error-message');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';
        } else {
            alert(message);
        }
    }

    /**
     * Mostra mensagem de sucesso
     */
    function showSuccess(message) {
        // Cria ou atualiza um elemento de sucesso temporário
        let successContainer = document.getElementById('ajax-success-message');
        if (!successContainer) {
            successContainer = document.createElement('div');
            successContainer.id = 'ajax-success-message';
            successContainer.style.cssText = 'background: #efe; color: #3c3; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #3c3;';
            
            // Insere no início do container apropriado
            const wpBackups = document.getElementById('tenant-wp-backups');
            const documents = document.getElementById('tenant-documents');
            const target = wpBackups || documents;
            if (target) {
                target.insertBefore(successContainer, target.firstChild);
            }
        }
        
        successContainer.textContent = message;
        successContainer.style.display = 'block';
        
        // Remove após 5 segundos
        setTimeout(() => {
            if (successContainer) {
                successContainer.style.display = 'none';
            }
        }, 5000);
    }

    /**
     * Inicializa quando o DOM estiver pronto
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Intercepta formulário de backup WordPress
        // Agora o formulário de backup usa apenas URL externa (não faz mais upload de arquivo)
        const formBackup = document.getElementById('form-wp-backup');
        if (formBackup) {
            formBackup.addEventListener('submit', function(e) {
                e.preventDefault();
                // Envia apenas URL externa (sem upload de arquivo)
                sendAjaxForm(formBackup, 'tenant-wp-backups');
            });
        }

        // Intercepta formulário de documento
        const formDoc = document.getElementById('form-tenant-document');
        if (formDoc) {
            formDoc.addEventListener('submit', function(e) {
                e.preventDefault();
                sendAjaxForm(formDoc, 'documents');
            });
        }

        // Intercepta todos os formulários de exclusão
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;

            // Verifica se é um formulário de exclusão
            const submitBtn = form.querySelector('button[type="submit"][data-action], input[type="submit"][data-action]');
            if (!submitBtn) return;

            const action = submitBtn.getAttribute('data-action');
            if (action !== 'delete-backup' && action !== 'delete-document') return;

            // Previne o comportamento padrão
            e.preventDefault();
            e.stopPropagation();

            if (!confirm('Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.')) {
                return;
            }

            const targetContainer = submitBtn.getAttribute('data-target-container');
            if (!targetContainer) {
                console.error('data-target-container não encontrado no botão');
                showError('Erro: atributo data-target-container não encontrado.');
                return;
            }

            // Mostra feedback visual no botão
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Excluindo...';

            sendAjaxForm(form, targetContainer).then(() => {
                // Sucesso - a tabela será atualizada e o botão será removido
                console.log('Exclusão realizada com sucesso');
            }).catch((err) => {
                // Restaura o botão em caso de erro
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                console.error('Erro na exclusão:', err);
            });
        });

        // Funcionalidade para copiar link do backup
        // Usa event delegation para funcionar mesmo quando a tabela é atualizada via AJAX
        document.addEventListener('click', function(e) {
            const copyBtn = e.target.closest('.copy-backup-link-btn');
            if (!copyBtn) return;

            e.preventDefault();
            e.stopPropagation();

            const url = copyBtn.getAttribute('data-url');
            if (!url) {
                showError('URL do backup não encontrada.');
                return;
            }

            // Tenta copiar para a área de transferência usando a API moderna
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    // Feedback visual: muda o texto do botão temporariamente
                    const originalText = copyBtn.textContent;
                    copyBtn.textContent = '✓ Copiado!';
                    copyBtn.style.background = '#28a745';
                    
                    // Restaura após 2 segundos
                    setTimeout(() => {
                        copyBtn.textContent = originalText;
                        copyBtn.style.background = '#28a745';
                    }, 2000);
                }).catch(err => {
                    console.error('Erro ao copiar:', err);
                    // Fallback: usa método antigo
                    fallbackCopyTextToClipboard(url, copyBtn);
                });
            } else {
                // Fallback para navegadores mais antigos
                fallbackCopyTextToClipboard(url, copyBtn);
            }
        });

        /**
         * Método alternativo para copiar texto (fallback)
         */
        function fallbackCopyTextToClipboard(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    const originalText = button.textContent;
                    button.textContent = '✓ Copiado!';
                    button.style.background = '#28a745';
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.background = '#28a745';
                    }, 2000);
                } else {
                    showError('Não foi possível copiar o link. Tente selecionar e copiar manualmente.');
                }
            } catch (err) {
                console.error('Erro ao copiar:', err);
                showError('Erro ao copiar o link. Tente selecionar e copiar manualmente.');
            } finally {
                document.body.removeChild(textArea);
            }
        }
    });
})();

