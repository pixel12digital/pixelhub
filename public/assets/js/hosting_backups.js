/**
 * Sistema de Upload de Backups WordPress
 * Reutilizável para múltiplas telas (hosting/backups e tenants/view?tab=docs_backups)
 * 
 * Uso:
 *   HostingBackupUpload.init({
 *     formSelector: '#backup-form',
 *     fileInputSelector: '#backup_file',
 *     notesSelector: '#notes',
 *     submitBtnSelector: '#submit-btn',
 *     progressContainerSelector: '#chunked-upload-progress',
 *     progressBarSelector: '#chunked-progress-bar',
 *     statusTextSelector: '#chunked-status',
 *     maxDirectUploadBytes: 500 * 1024 * 1024, // 500MB
 *     chunkMaxBytes: 2 * 1024 * 1024 * 1024, // 2GB
 *     chunkSize: 10 * 1024 * 1024, // 10MB por chunk
 *     chunkInitUrl: '/hosting/backups/chunk-init',
 *     chunkUploadUrl: '/hosting/backups/chunk-upload',
 *     chunkCompleteUrl: '/hosting/backups/chunk-complete',
 *     onSuccess: function(hostingAccountId) {
 *       // Callback após sucesso (ex: redirecionar ou atualizar tabela)
 *       window.location.href = '/hosting/backups?hosting_id=' + hostingAccountId + '&success=uploaded';
 *     }
 *   });
 */
(function() {
    'use strict';

    const HostingBackupUpload = {
        config: null,
        form: null,
        fileInput: null,
        submitBtn: null,
        progressDiv: null,
        progressBar: null,
        statusText: null,

        /**
         * Inicializa o sistema de upload
         */
        init: function(config) {
            this.config = {
                formSelector: config.formSelector || 'form[enctype="multipart/form-data"]',
                fileInputSelector: config.fileInputSelector || '#backup_file',
                notesSelector: config.notesSelector || '#notes',
                submitBtnSelector: config.submitBtnSelector || '#submit-btn',
                progressContainerSelector: config.progressContainerSelector || '#chunked-upload-progress',
                progressBarSelector: config.progressBarSelector || '#chunked-progress-bar',
                statusTextSelector: config.statusTextSelector || '#chunked-status',
                maxDirectUploadBytes: config.maxDirectUploadBytes || (500 * 1024 * 1024),
                chunkMaxBytes: config.chunkMaxBytes || (2 * 1024 * 1024 * 1024),
                chunkSize: config.chunkSize || (1 * 1024 * 1024), // 1MB padrão para ambientes compartilhados
                chunkInitUrl: config.chunkInitUrl || '/hosting/backups/chunk-init',
                chunkUploadUrl: config.chunkUploadUrl || '/hosting/backups/chunk-upload',
                chunkCompleteUrl: config.chunkCompleteUrl || '/hosting/backups/chunk-complete',
                onSuccess: config.onSuccess || function(hostingAccountId) {
                    // Default: recarrega a página
                    window.location.reload();
                }
            };

            // Busca elementos
            this.form = document.querySelector(this.config.formSelector);
            if (!this.form) {
                console.warn('HostingBackupUpload: Formulário não encontrado:', this.config.formSelector);
                return;
            }

            this.fileInput = this.form.querySelector(this.config.fileInputSelector);
            if (!this.fileInput) {
                console.warn('HostingBackupUpload: Input de arquivo não encontrado:', this.config.fileInputSelector);
                return;
            }

            this.submitBtn = this.form.querySelector(this.config.submitBtnSelector);
            this.progressDiv = document.querySelector(this.config.progressContainerSelector);
            this.progressBar = document.querySelector(this.config.progressBarSelector);
            this.statusText = document.querySelector(this.config.statusTextSelector);

            // Adiciona listener no formulário
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        },

        /**
         * Trata o submit do formulário
         */
        handleSubmit: async function(e) {
            const file = this.fileInput.files[0];
            if (!file) {
                return; // Deixa validação HTML5 funcionar
            }

            // Arquivo maior que o limite máximo absoluto (2GB) → nem tenta
            if (file.size > this.config.chunkMaxBytes) {
                e.preventDefault();
                alert('Arquivo muito grande. O limite máximo para backup é de 2GB.');
                return;
            }

            // Se o arquivo for menor ou igual ao limite calculado → upload direto
            if (file.size <= this.config.maxDirectUploadBytes) {
                // Deixa o submit seguir normalmente (upload direto)
                return;
            }

            // Se chegou aqui, o arquivo é maior que o que o PHP aguenta de uma vez,
            // então força upload em chunks
            e.preventDefault();
            
            // Marca o formulário para indicar que está fazendo upload em chunks
            // (isso evita que tenant_docs_backups.js tente interceptar)
            if (this.form) {
                this.form.dataset.chunkedUpload = 'true';
            }
            
            await this.uploadInChunks(file);
        },

        /**
         * Faz upload em chunks
         */
        uploadInChunks: async function(file) {
            const hostingAccountId = this.form.querySelector('[name="hosting_account_id"]')?.value;
            if (!hostingAccountId) {
                alert('ID da hospedagem não encontrado.');
                return;
            }

            const notesElement = this.form.querySelector(this.config.notesSelector);
            const notes = notesElement ? notesElement.value || '' : '';
            const totalChunks = Math.ceil(file.size / this.config.chunkSize);
            const uploadId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Salva texto original do botão
            const originalBtnText = this.submitBtn ? this.submitBtn.textContent : 'Enviar Backup';
            if (this.submitBtn) {
                this.submitBtn.dataset.originalText = originalBtnText;
            }
            
            // Mostra progresso
            if (this.progressDiv) {
                this.progressDiv.style.display = 'block';
            }
            if (this.submitBtn) {
                this.submitBtn.disabled = true;
                this.submitBtn.textContent = 'Enviando...';
            }

            try {
                // Inicia sessão de upload
                const initResponse = await fetch(this.config.chunkInitUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        hosting_account_id: hostingAccountId,
                        file_name: file.name,
                        file_size: file.size,
                        total_chunks: totalChunks,
                        upload_id: uploadId,
                        notes: notes
                    })
                });

                if (!initResponse.ok) {
                    throw new Error('Erro ao iniciar upload');
                }

                const initData = await initResponse.json();
                if (!initData.success) {
                    throw new Error(initData.error || 'Erro ao iniciar upload');
                }

                // Envia cada chunk
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * this.config.chunkSize;
                    const end = Math.min(start + this.config.chunkSize, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('upload_id', uploadId);
                    formData.append('chunk_index', chunkIndex);
                    formData.append('chunk', chunk);
                    formData.append('total_chunks', totalChunks);

                    if (this.statusText) {
                        this.statusText.textContent = `Enviando parte ${chunkIndex + 1} de ${totalChunks}...`;
                    }
                    if (this.progressBar) {
                        const progress = ((chunkIndex + 1) / totalChunks) * 100;
                        this.progressBar.style.width = progress + '%';
                        this.progressBar.textContent = Math.round(progress) + '%';
                    }

                    const chunkResponse = await fetch(this.config.chunkUploadUrl, {
                        method: 'POST',
                        body: formData
                    });

                    if (!chunkResponse.ok) {
                        const errorData = await chunkResponse.json().catch(() => ({}));
                        const errorMsg = errorData.error || `Erro ao enviar parte ${chunkIndex + 1}`;
                        if (this.statusText) {
                            this.statusText.textContent = `Upload em Progresso – ${Math.round(((chunkIndex + 1) / totalChunks) * 100)}% – Erro: ${errorMsg}`;
                            this.statusText.style.color = '#d32f2f';
                        }
                        throw new Error(errorMsg);
                    }

                    const chunkData = await chunkResponse.json();
                    if (!chunkData.success) {
                        const errorMsg = chunkData.error || `Erro ao enviar parte ${chunkIndex + 1}`;
                        if (this.statusText) {
                            this.statusText.textContent = `Upload em Progresso – ${Math.round(((chunkIndex + 1) / totalChunks) * 100)}% – Erro: ${errorMsg}`;
                            this.statusText.style.color = '#d32f2f';
                        }
                        throw new Error(errorMsg);
                    }
                }

                // Finaliza upload
                if (this.statusText) {
                    this.statusText.textContent = 'Finalizando upload...';
                }
                const finalResponse = await fetch(this.config.chunkCompleteUrl, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ upload_id: uploadId })
                });

                if (!finalResponse.ok) {
                    throw new Error('Erro ao finalizar upload');
                }

                const finalData = await finalResponse.json();
                if (!finalData.success) {
                    const errorMsg = finalData.error || 'Erro ao finalizar upload';
                    if (this.statusText) {
                        this.statusText.textContent = `Erro: ${errorMsg}`;
                        this.statusText.style.color = '#d32f2f';
                    }
                    throw new Error(errorMsg);
                }

                // Sucesso!
                if (this.progressBar) {
                    this.progressBar.style.width = '100%';
                    this.progressBar.textContent = '100%';
                }
                if (this.statusText) {
                    this.statusText.textContent = 'Upload concluído com sucesso!';
                    this.statusText.style.color = '#4caf50';
                    this.statusText.style.fontWeight = 'bold';
                }

                // Chama callback de sucesso passando a resposta do servidor
                if (this.config.onSuccess) {
                    setTimeout(() => {
                        this.config.onSuccess(hostingAccountId, finalData);
                    }, 1000);
                }
                
                // Remove a marca de upload em chunks após sucesso
                if (this.form) {
                    delete this.form.dataset.chunkedUpload;
                }

            } catch (error) {
                // Remove a marca de upload em chunks em caso de erro
                if (this.form) {
                    delete this.form.dataset.chunkedUpload;
                }
                console.error('Erro no upload:', error);
                if (this.statusText) {
                    this.statusText.textContent = 'Erro: ' + error.message;
                    this.statusText.style.color = '#d32f2f';
                }
                if (this.progressBar) {
                    this.progressBar.style.background = '#d32f2f';
                }
                if (this.submitBtn) {
                    this.submitBtn.disabled = false;
                    // Restaura texto original ou usa "Tentar Novamente" se não houver original salvo
                    const originalBtnText = this.submitBtn.dataset.originalText || 'Enviar Backup';
                    this.submitBtn.textContent = originalBtnText;
                }
            }
        }
    };

    // Expõe globalmente
    window.HostingBackupUpload = HostingBackupUpload;
})();

