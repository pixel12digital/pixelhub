/**
 * PixelHub Screen Recorder
 * Componente para gravação de tela integrado às tarefas
 */
(function() {
    'use strict';

    // Configuração: Limite máximo de gravação em segundos (10 minutos)
    // TODO: Futuramente, este valor pode ser obtido via configuração server-side
    // para permitir ajuste sem alterar código
    const MAX_RECORDING_DURATION_SECONDS = 600; // 10 minutos

    // Estado do gravador
    const state = {
        currentTaskId: null,
        stream: null,
        mediaRecorder: null,
        chunks: [],
        isRecording: false,
        durationSeconds: 0,
        timerIntervalId: null,
        videoBlob: null,
        videoUrl: null,
        isUploading: false, // Flag para controlar estado de upload
        includeAudio: true, // Flag para controlar se deve gravar áudio do microfone
        mode: 'task', // 'task', 'quick' ou 'library' - modo de operação do gravador
        isCompact: false, // Flag para indicar se está no modo compacto (floating)
        isPaused: false, // Flag para indicar se a gravação está pausada
        countdownIntervalId: null, // ID do intervalo do countdown
        hasDetachedFloating: false, // Flag para indicar se o painel foi destacado do overlay
        screenStream: null, // Stream de tela (separado do stream combinado)
        micStream: null // Stream de microfone (separado do stream combinado)
    };

    // Verifica compatibilidade do navegador
    function isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia);
    }

    // Formata duração em segundos para mm:ss
    function formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }
    
    // Escapa HTML para prevenir XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Cria o overlay HTML se não existir
    function ensureOverlayExists() {
        let overlay = document.getElementById('screen-recording-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'screen-recording-overlay';
            overlay.style.cssText = `
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 10000;
                justify-content: center;
                align-items: center;
            `;
            
            overlay.innerHTML = `
                <div style="background: white; border-radius: 8px; padding: 30px; max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
                    <button id="screen-recording-close-btn" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 5px 10px;">&times;</button>
                    <div id="screen-recording-content">
                        <!-- Conteúdo será injetado dinamicamente -->
                    </div>
                </div>
                <!-- Painel compacto flutuante (modo compacto) -->
                <div id="screen-recording-floating" style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 99999; pointer-events: auto;">
                    <!-- Conteúdo será montado via JS -->
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            // Listener para fechar ao clicar no X
            document.getElementById('screen-recording-close-btn').addEventListener('click', function() {
                window.PixelHubScreenRecorder.close();
            });
        }
        return overlay;
    }

    // Renderiza o estado inicial do overlay (modo tarefa ou library)
    function renderInitialState() {
        const content = document.getElementById('screen-recording-content');
        if (!content) return;
        
        // Reseta includeAudio para o padrão (true)
        state.includeAudio = true;
        
        // Define título e texto baseado no modo
        let title, description;
        if (state.mode === 'library') {
            title = 'Gravar tela na biblioteca';
            description = 'A gravação será salva na biblioteca geral e você poderá compartilhar o link com clientes. Você poderá escolher gravar com ou sem áudio do microfone. O navegador pode pedir permissão para compartilhar sua tela e, se você optar por gravar com áudio, também solicitará permissão para o microfone.';
        } else {
            title = 'Gravar tela da tarefa';
            description = 'Você poderá escolher gravar com ou sem áudio do microfone. O navegador pode pedir permissão para compartilhar sua tela e, se você optar por gravar com áudio, também solicitará permissão para o microfone.';
        }
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">${title}</h3>
            <p style="color: #666; margin-bottom: 15px; line-height: 1.5; font-size: 14px;">
                ${description}
            </p>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 20px; cursor: pointer; padding: 8px; border-radius: 4px; background: #f8f9fa;">
                <input type="checkbox" id="screen-recording-include-audio" checked style="width: 18px; height: 18px; cursor: pointer;">
                <span style="color: #333; font-weight: 500;">Gravar áudio (microfone)</span>
            </label>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="screen-recording-start-btn" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                    Iniciar gravação
                </button>
            </div>
        `;
        
        document.getElementById('screen-recording-start-btn').addEventListener('click', function() {
            window.PixelHubScreenRecorder.start();
        });
    }

    // Renderiza o estado inicial do overlay (modo rápido)
    function renderQuickModeInitialState() {
        const content = document.getElementById('screen-recording-content');
        if (!content) return;
        
        // Reseta includeAudio para o padrão (true)
        state.includeAudio = true;
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">Gravação Rápida</h3>
            <p style="color: #666; margin-bottom: 15px; line-height: 1.5; font-size: 14px;">
                Gravação rápida para download local. Você poderá escolher gravar com ou sem áudio do microfone. O navegador pode pedir permissão para compartilhar sua tela e, se você optar por gravar com áudio, também solicitará permissão para o microfone.
            </p>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 20px; cursor: pointer; padding: 8px; border-radius: 4px; background: #f8f9fa;">
                <input type="checkbox" id="screen-recording-include-audio" checked style="width: 18px; height: 18px; cursor: pointer;">
                <span style="color: #333; font-weight: 500;">Gravar áudio (microfone)</span>
            </label>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="screen-recording-start-btn" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                    Iniciar gravação
                </button>
            </div>
        `;
        
        document.getElementById('screen-recording-start-btn').addEventListener('click', function() {
            window.PixelHubScreenRecorder.start();
        });
    }

    // Renderiza tela de countdown
    function renderCountdownState() {
        const content = document.getElementById('screen-recording-content');
        if (!content) return;
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">Preparando gravação…</h3>
            <p style="color: #666; margin-bottom: 20px; line-height: 1.5; font-size: 16px; text-align: center;">
                A gravação vai começar em <span id="screen-recording-countdown" style="font-size: 24px; font-weight: 600; color: #023A8D;">3</span> segundos.
            </p>
        `;
    }

    // Renderiza o estado de gravação (não usado mais no modo compacto, mas mantido para compatibilidade)
    function renderRecordingState() {
        const content = document.getElementById('screen-recording-content');
        if (!content) return;
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">Gravando...</h3>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <div style="width: 16px; height: 16px; background: #c33; border-radius: 50%; animation: pulse 1.5s infinite;"></div>
                <span style="font-size: 24px; font-weight: 600; color: #333; font-family: monospace;" id="screen-recording-timer">00:00</span>
            </div>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
                A gravação está em andamento. Clique em "Parar" quando terminar.
            </p>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="screen-recording-stop-btn" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 600; background: #c33;">
                    Parar gravação
                </button>
            </div>
            <style>
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
            </style>
        `;
        
        document.getElementById('screen-recording-stop-btn').addEventListener('click', function() {
            window.PixelHubScreenRecorder.stop();
        });
    }

    // Renderiza o painel compacto flutuante
    function renderCompactPanel() {
        const floating = document.getElementById('screen-recording-floating');
        if (!floating) return;
        
        const isPaused = state.isPaused;
        const statusText = isPaused ? 'Pausado' : 'Gravando…';
        const pauseButtonText = isPaused ? 'Retomar' : 'Pausar';
        
        floating.innerHTML = `
            <div style="background: #ffffff; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.2); padding: 10px 14px; min-width: 220px; font-family: inherit; font-size: 14px; cursor: move;" id="screen-recording-floating-drag-handle">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <strong style="color: #023A8D; font-size: 13px;">${statusText}</strong>
                    <span id="screen-recording-floating-timer" style="font-weight: 600; font-family: monospace;">00:00</span>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button"
                            id="screen-recording-btn-toggle-pause"
                            class="btn btn-sm btn-outline-secondary"
                            onclick="window.PixelHubScreenRecorder.togglePause(); return false;"
                            style="padding: 4px 12px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; background: white; color: #666; cursor: pointer; font-weight: 600;">
                        ${pauseButtonText}
                    </button>
                    <button type="button"
                            id="screen-recording-btn-stop"
                            class="btn btn-sm btn-danger"
                            onclick="window.PixelHubScreenRecorder.stop(); return false;"
                            style="padding: 4px 12px; font-size: 12px; border: none; border-radius: 4px; background: #c33; color: white; cursor: pointer; font-weight: 600;">
                        Encerrar
                    </button>
                </div>
            </div>
        `;
        
        // Atualiza o timer com o valor atual (evita zerar ao pausar)
        const timerEl = document.getElementById('screen-recording-floating-timer');
        if (timerEl) {
            timerEl.textContent = formatDuration(state.durationSeconds);
        }
        
        // Verifica se os botões foram criados (apenas para log)
        const pauseBtn = document.getElementById('screen-recording-btn-toggle-pause');
        const stopBtn = document.getElementById('screen-recording-btn-stop');
        
        console.log('[ScreenRecorder] renderCompactPanel: pauseBtn=', !!pauseBtn, 'stopBtn=', !!stopBtn);
        
        // Implementação básica de drag (opcional)
        makeFloatingDraggable(floating);
    }

    // Torna o painel flutuante arrastável
    function makeFloatingDraggable(element) {
        const dragHandle = element.querySelector('#screen-recording-floating-drag-handle');
        if (!dragHandle) return;
        
        let isDragging = false;
        let currentX = 0;
        let currentY = 0;
        let initialX = 0;
        let initialY = 0;
        
        // Remove listeners anteriores se existirem
        const newDragHandle = dragHandle.cloneNode(true);
        dragHandle.parentNode.replaceChild(newDragHandle, dragHandle);
        
        newDragHandle.addEventListener('mousedown', function(e) {
            // Não arrasta se clicar em botão ou em elementos interativos
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                return;
            }
            
            isDragging = true;
            const rect = element.getBoundingClientRect();
            initialX = e.clientX - rect.left;
            initialY = e.clientY - rect.top;
            newDragHandle.style.cursor = 'grabbing';
            e.preventDefault();
        });
        
        const handleMouseMove = function(e) {
            if (!isDragging) return;
            
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            
            // Limita aos limites da tela
            const maxX = window.innerWidth - element.offsetWidth;
            const maxY = window.innerHeight - element.offsetHeight;
            currentX = Math.max(0, Math.min(currentX, maxX));
            currentY = Math.max(0, Math.min(currentY, maxY));
            
            element.style.left = currentX + 'px';
            element.style.top = currentY + 'px';
            element.style.right = 'auto';
            element.style.bottom = 'auto';
        };
        
        const handleMouseUp = function() {
            if (isDragging) {
                isDragging = false;
                newDragHandle.style.cursor = 'move';
            }
        };
        
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }

    // Entra no modo compacto (floating)
    function enterCompactMode() {
        state.isCompact = true;
        
        const overlay = document.getElementById('screen-recording-overlay');
        const content = document.getElementById('screen-recording-content');
        const floating = document.getElementById('screen-recording-floating');
        
        if (!overlay || !floating) return;
        
        // Confirma onde o painel está no DOM
        console.log('[ScreenRecorder] enterCompactMode: floating parent id =', floating.parentElement && floating.parentElement.id);
        
        // Garante que o painel compacto esteja diretamente no body
        if (floating.parentElement !== document.body) {
            document.body.appendChild(floating);
            console.log('[ScreenRecorder] enterCompactMode: movendo floating para document.body');
            state.hasDetachedFloating = true;
        }
        
        // Esconde completamente o overlay grande
        overlay.style.display = 'none';
        
        // Garante que o conteúdo grande não apareça
        if (content) {
            content.style.display = 'none';
        }
        
        // Garante estilos do painel (z-index e posição)
        floating.style.position = 'fixed';
        floating.style.bottom = '20px';
        floating.style.right = '20px';
        floating.style.zIndex = '99999';
        floating.style.pointerEvents = 'auto';
        
        // Mostra painel compacto
        floating.style.display = 'block';
        
        // Renderiza UI do painel (botões, timer, etc.)
        renderCompactPanel();
        
        // TODO: Se quisermos que o painel nunca apareça na gravação,
        // será necessário mover os controles para outra aba/janela
        // ou usar uma extensão / app nativo. Em um único tab, o browser
        // sempre captura tudo que for renderizado nessa aba.
    }

    // Sai do modo compacto
    function exitCompactMode() {
        state.isCompact = false;
        
        const overlay = document.getElementById('screen-recording-overlay');
        const content = document.getElementById('screen-recording-content');
        const floating = document.getElementById('screen-recording-floating');
        
        if (!overlay || !floating) return;
        
        // Mostra de novo o overlay (usando display: flex como usado em open())
        overlay.style.display = 'flex';
        
        // Restaura fundo escuro
        overlay.style.background = 'rgba(0, 0, 0, 0.7)';
        
        // Garante que o conteúdo grande volte a aparecer (para preview / estado inicial)
        if (content) {
            content.style.display = 'block';
        }
        
        // Esconde o painel compacto
        floating.style.display = 'none';
        floating.style.pointerEvents = 'none';
    }

    // Renderiza o estado de preview
    function renderPreviewState() {
        const content = document.getElementById('screen-recording-content');
        if (!content || !state.videoUrl) return;
        
        // Se não houver currentTaskId e estiver em modo task, converte para library (não quick)
        if (state.mode === 'task' && !state.currentTaskId) {
            console.warn('[ScreenRecorder] renderPreviewState: modo task sem currentTaskId, convertendo para library');
            state.mode = 'library';
        }
        
        const duration = formatDuration(state.durationSeconds);
        const isQuickMode = (state.mode === 'quick');
        const isLibraryMode = (state.mode === 'library');
        const isTaskMode = (state.mode === 'task');
        
        console.log('[ScreenRecorder] renderPreviewState: mode=', state.mode, 'isQuickMode=', isQuickMode, 'isLibraryMode=', isLibraryMode, 'currentTaskId=', state.currentTaskId);
        
        let primaryLabel;
        let primaryId;
        
        if (isQuickMode) {
            primaryLabel = 'Baixar vídeo';
            primaryId = 'screen-recording-download-btn';
        } else if (isLibraryMode) {
            primaryLabel = 'Salvar na biblioteca';
            primaryId = 'screen-recording-save-library-btn';
        } else {
            primaryLabel = 'Salvar na tarefa';
            primaryId = 'screen-recording-save-btn';
        }
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">Preview da gravação</h3>
            <p style="color: #666; margin-bottom: 10px; font-size: 13px;">
                Duração: <strong>${duration}</strong>
            </p>
            <video id="screen-recording-preview-video" controls style="width: 100%; max-height: 400px; border-radius: 6px; margin-bottom: 20px; background: #000;"></video>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="screen-recording-discard-btn" class="btn btn-outline-secondary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                    Descartar
                </button>
                <button type="button" id="${primaryId}" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                    ${primaryLabel}
                </button>
            </div>
        `;
        
        const discardBtn = document.getElementById('screen-recording-discard-btn');
        if (discardBtn) {
            discardBtn.onclick = function() {
                window.PixelHubScreenRecorder.reset();
            };
        }
        
        if (isQuickMode) {
            const downloadBtn = document.getElementById('screen-recording-download-btn');
            if (downloadBtn) {
                downloadBtn.onclick = function() {
                    window.PixelHubScreenRecorder.download();
                };
            }
        } else if (isLibraryMode) {
            const saveLibraryBtn = document.getElementById('screen-recording-save-library-btn');
            if (saveLibraryBtn) {
                saveLibraryBtn.onclick = function() {
                    window.PixelHubScreenRecorder.saveToLibrary();
                };
            }
        } else {
            const saveBtn = document.getElementById('screen-recording-save-btn');
            if (saveBtn) {
                saveBtn.onclick = function() {
                    window.PixelHubScreenRecorder.save();
                };
            }
        }
        
        // Configura o vídeo para não estar mudo e ter volume máximo
        const videoEl = document.getElementById('screen-recording-preview-video');
        if (videoEl && state.videoUrl) {
            videoEl.src = state.videoUrl;
            videoEl.removeAttribute('muted');
            videoEl.volume = 1;
            videoEl.load();
        }
    }

    // Renderiza mensagem de erro
    function renderErrorState(message) {
        const content = document.getElementById('screen-recording-content');
        if (!content) return;
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #c33; font-size: 20px;">Erro</h3>
            <p style="color: #666; margin-bottom: 20px; line-height: 1.5;">
                ${message}
            </p>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="screen-recording-retry-btn" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                    Tentar novamente
                </button>
            </div>
        `;
        
        document.getElementById('screen-recording-retry-btn').addEventListener('click', function() {
            renderInitialState();
        });
    }

    // Renderiza estado de upload
    function renderUploadingState() {
        const content = document.getElementById('screen-recording-content');
        if (!content) return;
        
        // Desabilita botão de fechar durante upload
        const closeBtn = document.getElementById('screen-recording-close-btn');
        if (closeBtn) {
            closeBtn.disabled = true;
            closeBtn.style.opacity = '0.5';
            closeBtn.style.cursor = 'not-allowed';
        }
        
        content.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">Enviando gravação...</h3>
            <p style="color: #666; margin-bottom: 20px; line-height: 1.5;">
                Aguarde enquanto o vídeo é enviado para o servidor. Isso pode levar alguns instantes dependendo do tamanho do arquivo.
            </p>
            <div style="text-align: center; padding: 20px;">
                <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #023A8D; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `;
    }
    
    // Reabilita botões após upload
    function reenableButtons() {
        const closeBtn = document.getElementById('screen-recording-close-btn');
        if (closeBtn) {
            closeBtn.disabled = false;
            closeBtn.style.opacity = '1';
            closeBtn.style.cursor = 'pointer';
        }
    }

    // Solicita o stream de tela do navegador (com áudio de microfone opcional)
    async function requestScreenStream() {
        // Lê o checkbox de áudio
        const audioCheckbox = document.getElementById('screen-recording-include-audio');
        state.includeAudio = audioCheckbox ? !!audioCheckbox.checked : true;

        // 1) Captura da tela (sem áudio por padrão)
        const screenStream = await navigator.mediaDevices.getDisplayMedia({
            video: true,
            audio: false // importante: não depender do áudio da aba
        });

        // Se não for gravar áudio, retorna só a tela
        if (!state.includeAudio) {
            return screenStream;
        }

        // 2) Captura do microfone
        const micStream = await navigator.mediaDevices.getUserMedia({
            audio: true
        });

        // 3) Cria um novo stream combinando vídeo da tela + áudio do microfone
        const combinedStream = new MediaStream();

        // Video tracks da tela
        screenStream.getVideoTracks().forEach(track => combinedStream.addTrack(track));

        // Audio tracks do microfone
        micStream.getAudioTracks().forEach(track => combinedStream.addTrack(track));

        // Guarda referências se precisar parar depois
        state.screenStream = screenStream;
        state.micStream = micStream;

        return combinedStream;
    }

    // Trata erros do getDisplayMedia
    function handleGetDisplayMediaError(error) {
        console.error('Erro ao solicitar stream de tela:', error);
        state.isRecording = false;
        
        let errorMessage = 'Erro ao iniciar a gravação.';
        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            if (state.includeAudio) {
                errorMessage = 'Permissão negada. Verifique se você permitiu o compartilhamento da tela e, se estiver gravando com áudio, a permissão do microfone.';
            } else {
                errorMessage = 'Permissão de compartilhamento de tela negada. Por favor, permita o acesso e tente novamente.';
            }
        } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            errorMessage = 'Nenhuma fonte de vídeo encontrada.';
        } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            if (state.includeAudio) {
                errorMessage = 'Não foi possível acessar a fonte de vídeo ou microfone. Verifique se outra aplicação não está usando sua tela ou microfone.';
            } else {
                errorMessage = 'Não foi possível acessar a fonte de vídeo. Verifique se outra aplicação não está usando sua tela.';
            }
        }
        
        renderErrorState(errorMessage);
    }

    // API pública do gravador
    window.PixelHubScreenRecorder = {
        /**
         * Abre o overlay de gravação para uma tarefa ou modo rápido
         * @param {number|null} taskId - ID da tarefa (null para modo rápido)
         * @param {string} mode - 'task' ou 'quick' (padrão: 'task' se taskId for fornecido, 'quick' caso contrário)
         */
        open: function(taskId, mode) {
            console.log('[ScreenRecorder] open() chamado. taskId=', taskId, 'mode arg=', mode);
            
            // Normaliza o modo
            if (mode === 'quick') {
                state.mode = 'quick';
                state.currentTaskId = null;
            } else if (mode === 'library') {
                state.mode = 'library';
                state.currentTaskId = null;
            } else if (mode === 'task') {
                // se veio "task" mas taskId é inválido, cai pra quick
                if (taskId && Number(taskId) > 0) {
                    state.mode = 'task';
                    state.currentTaskId = Number(taskId);
                } else {
                    state.mode = 'quick';
                    state.currentTaskId = null;
                }
            } else {
                // Sem modo explícito → decide pelo taskId
                if (taskId && Number(taskId) > 0) {
                    state.mode = 'task';
                    state.currentTaskId = Number(taskId);
                } else {
                    state.mode = 'quick';
                    state.currentTaskId = null;
                }
            }
            
            console.log('[ScreenRecorder] open() definido. state.mode=', state.mode, 'state.currentTaskId=', state.currentTaskId);
            if (!isSupported()) {
                const overlay = ensureOverlayExists();
                const content = document.getElementById('screen-recording-content');
                if (content) {
                    content.innerHTML = `
                        <h3 style="margin: 0 0 15px 0; color: #c33; font-size: 20px;">Navegador não suportado</h3>
                        <p style="color: #666; margin-bottom: 20px; line-height: 1.5;">
                            Seu navegador não suporta gravação de tela. Tente usar a versão mais recente do Google Chrome ou Microsoft Edge.
                        </p>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button id="screen-recording-close-error-btn" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                                Fechar
                            </button>
                        </div>
                    `;
                    document.getElementById('screen-recording-close-error-btn').addEventListener('click', function() {
                        this.close();
                    }.bind(this));
                }
                overlay.style.display = 'flex';
                return;
            }
            
            // Salva o modo antes de resetar (para não perder o modo library)
            const savedMode = state.mode;
            const savedTaskId = state.currentTaskId;
            
            this.reset();
            
            // Restaura o modo após reset (importante para modo library)
            state.mode = savedMode;
            state.currentTaskId = savedTaskId;
            
            // Reseta flags para garantir que comece no modo "grande" normal
            state.isCompact = false;
            state.isPaused = false;
            
            const overlay = ensureOverlayExists();
            renderInitialState();
            overlay.style.display = 'flex';
        },

        /**
         * Inicia a gravação com countdown (recebe o stream já obtido)
         */
        startWithCountdown: function(stream) {
            // Salva o stream no state (se ainda não estiver salvo)
            if (stream) {
                state.stream = stream;
            }
            
            // Verifica se temos um stream válido
            if (!state.stream) {
                renderErrorState('Não foi possível acessar a tela. Tente iniciar a gravação novamente.');
                return;
            }
            
            // Renderiza tela de countdown
            renderCountdownState();
            
            let countdown = 3;
            const countdownEl = document.getElementById('screen-recording-countdown');
            
            state.countdownIntervalId = setInterval(function() {
                countdown--;
                
                if (countdownEl) {
                    countdownEl.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(state.countdownIntervalId);
                    state.countdownIntervalId = null;
                    
                    // Inicia a gravação de fato
                    this.doStartRecordingInternal();
                }
            }.bind(this), 1000);
        },

        /**
         * Inicia a gravação de tela (chamado internamente após countdown)
         * Usa o stream que já foi obtido anteriormente
         */
        doStartRecordingInternal: function() {
            // Usa o stream que já foi obtido
            const stream = state.stream;
            
            if (!stream) {
                renderErrorState('Não foi possível acessar a tela. Tente iniciar a gravação novamente.');
                return;
            }
            
            state.chunks = [];
            state.durationSeconds = 0;
            state.isRecording = true;
            
            // Configura o MediaRecorder
            const options = {
                mimeType: 'video/webm;codecs=vp9'
            };
            
            // Verifica se o codec é suportado, senão usa o padrão
            if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                delete options.mimeType;
            }
            
            state.mediaRecorder = new MediaRecorder(stream, options);
                
                // Evento quando dados estão disponíveis
                state.mediaRecorder.ondataavailable = function(event) {
                    if (event.data && event.data.size > 0) {
                        state.chunks.push(event.data);
                    }
                };
                
                // Evento quando a gravação para
                state.mediaRecorder.onstop = function() {
                    console.log('[ScreenRecorder] mediaRecorder.onstop');
                    state.isRecording = false;
                    state.isPaused = false;
                    
                    // Sai do modo compacto
                    exitCompactMode();
                    
                    // Verifica se há chunks gravados
                    if (!state.chunks || !state.chunks.length) {
                        console.error('[ScreenRecorder] Nenhum chunk gravado');
                        renderErrorState('Não foi possível finalizar a gravação. Tente novamente.');
                        return;
                    }
                    
                    // Monta o Blob e a URL
                    state.videoBlob = new Blob(state.chunks, { type: 'video/webm' });
                    
                    // Cria URL para preview
                    if (state.videoUrl) {
                        URL.revokeObjectURL(state.videoUrl);
                    }
                    state.videoUrl = URL.createObjectURL(state.videoBlob);
                    
                    console.log('[ScreenRecorder] onstop: videoBlob size=', state.videoBlob.size);
                    
                    // Para todos os streams
                    if (state.screenStream) {
                        state.screenStream.getTracks().forEach(t => t.stop());
                        state.screenStream = null;
                    }
                    if (state.micStream) {
                        state.micStream.getTracks().forEach(t => t.stop());
                        state.micStream = null;
                    }
                    if (state.stream) {
                        state.stream.getTracks().forEach(t => t.stop());
                        state.stream = null;
                    }
                    
                    // Renderiza preview
                    renderPreviewState();
                };
                
                // Handler quando a gravação realmente inicia
                state.mediaRecorder.onstart = function() {
                    console.log('[ScreenRecorder] mediaRecorder.onstart');
                    state.isRecording = true;
                    state.isPaused = false;
                    
                    // Inicia o timer
                    state.timerIntervalId = setInterval(function() {
                        // Não incrementa se estiver pausado
                        if (state.isPaused) {
                            return;
                        }
                        
                        state.durationSeconds++;
                        
                        // Atualiza timer no painel compacto (se existir)
                        const floatingTimerEl = document.getElementById('screen-recording-floating-timer');
                        if (floatingTimerEl) {
                            floatingTimerEl.textContent = formatDuration(state.durationSeconds);
                        }
                        
                        // Atualiza timer no modal (se existir, para compatibilidade)
                        const timerEl = document.getElementById('screen-recording-timer');
                        if (timerEl) {
                            timerEl.textContent = formatDuration(state.durationSeconds);
                        }
                        
                        // Verifica se atingiu o limite máximo de gravação
                        if (state.durationSeconds >= MAX_RECORDING_DURATION_SECONDS) {
                            // Para a gravação automaticamente
                            this.stop();
                        }
                    }.bind(this), 1000);
                    
                    // Entra no modo compacto após iniciar gravação
                    console.log('[ScreenRecorder] Chamando enterCompactMode() a partir de onstart');
                    enterCompactMode();
                }.bind(this);
                
                // Inicia a gravação (após definir todos os handlers)
                console.log('[ScreenRecorder] Chamando mediaRecorder.start()');
                state.mediaRecorder.start(1000); // Coleta dados a cada 1 segundo
                
                // Listener para quando o usuário para o compartilhamento manualmente
                if (stream && stream.getVideoTracks().length > 0) {
                    stream.getVideoTracks()[0].addEventListener('ended', function() {
                        if (state.isRecording) {
                            this.stop();
                        }
                    }.bind(this));
                }
        },

        /**
         * Inicia a gravação (primeiro pede o stream, depois countdown)
         */
        start: function() {
            if (!isSupported()) {
                renderErrorState('Seu navegador não suporta gravação de tela.');
                return;
            }

            // Primeiro: pede o stream (abre janela nativa do navegador)
            requestScreenStream()
                .then((stream) => {
                    // Salva o stream no state
                    state.stream = stream;
                    
                    // Agora inicia o countdown com o stream já obtido
                    this.startWithCountdown(stream);
                })
                .catch((error) => {
                    // Trata erros do getDisplayMedia
                    handleGetDisplayMediaError(error);
                });
        },

        /**
         * Para a gravação
         * @param {Function} callback - Função opcional para executar após parar a gravação
         */
        stop: function(force, callback) {
            console.log('[ScreenRecorder] Clique no botão Encerrar');
            console.log('[ScreenRecorder] stop chamado. isRecording=', state.isRecording, 'mediaRecorder.state=', state.mediaRecorder && state.mediaRecorder.state, 'force=', force);
            
            // Limpa countdown se ainda estiver rodando
            if (state.countdownIntervalId) {
                clearInterval(state.countdownIntervalId);
                state.countdownIntervalId = null;
            }
            
            // Limpa timer
            if (state.timerIntervalId) {
                clearInterval(state.timerIntervalId);
                state.timerIntervalId = null;
            }
            
            if (!state.mediaRecorder) {
                console.warn('[ScreenRecorder] stop: mediaRecorder inexistente');
                this.close(true);
                return;
            }
            
            if (state.mediaRecorder.state === 'inactive') {
                console.warn('[ScreenRecorder] stop: mediaRecorder já está inativo');
                this.close(true);
                return;
            }
            
            try {
                console.log('[ScreenRecorder] Chamando mediaRecorder.stop()');
                state.mediaRecorder.stop();
            } catch (e) {
                console.error('[ScreenRecorder] Erro ao chamar mediaRecorder.stop():', e);
                this.close(true);
            }
        },

        /**
         * Pausa ou retoma a gravação
         */
        togglePause: function() {
            console.log('[ScreenRecorder] Clique no botão Pausar/Retomar');
            console.log('[ScreenRecorder] togglePause chamado. isRecording=', state.isRecording, 'isPaused=', state.isPaused, 'mediaRecorder=', state.mediaRecorder && state.mediaRecorder.state);
            
            if (!state.mediaRecorder) {
                console.warn('[ScreenRecorder] togglePause: mediaRecorder inexistente');
                return;
            }
            
            // Verifica se o navegador suporta pause/resume
            if (typeof state.mediaRecorder.pause !== 'function' || typeof state.mediaRecorder.resume !== 'function') {
                console.warn('[ScreenRecorder] Pausar/Retomar não é suportado neste navegador');
                return;
            }
            
            if (state.mediaRecorder.state === 'recording') {
                console.log('[ScreenRecorder] Pausando gravação');
                try {
                    state.mediaRecorder.pause();
                    state.isPaused = true;
                    renderCompactPanel();
                } catch (e) {
                    console.error('[ScreenRecorder] Erro ao pausar:', e);
                }
                return;
            }
            
            if (state.mediaRecorder.state === 'paused') {
                console.log('[ScreenRecorder] Retomando gravação');
                try {
                    state.mediaRecorder.resume();
                    state.isPaused = false;
                    renderCompactPanel();
                } catch (e) {
                    console.error('[ScreenRecorder] Erro ao retomar:', e);
                }
                return;
            }
            
            console.warn('[ScreenRecorder] togglePause: estado inesperado do mediaRecorder:', state.mediaRecorder.state);
        },

        /**
         * Salva a gravação na tarefa
         */
        save: function() {
            console.log(
                '[ScreenRecorder] save() chamado.',
                'mode=', state.mode,
                'currentTaskId=', state.currentTaskId,
                'videoBlob=', state.videoBlob,
                'chunks=', state.chunks && state.chunks.length,
                'duration=', state.durationSeconds
            );
            
            // Segurança: se estiver em modo rápido por qualquer motivo → apenas download
            if (state.mode === 'quick' || !state.currentTaskId) {
                console.warn('[ScreenRecorder] save() sem currentTaskId; executando download() em vez de salvar na tarefa.');
                this.download();
                return;
            }
            
            if (!state.videoBlob) {
                console.error('[ScreenRecorder] save() abortado: videoBlob inexistente');
                renderErrorState('Erro: dados da gravação não encontrados.');
                return;
            }
            
            // Previne múltiplos envios
            if (state.isUploading) {
                console.warn('[ScreenRecorder] Upload já em andamento, ignorando chamada duplicada');
                return;
            }
            
            state.isUploading = true;
            renderUploadingState();
            this.upload({ target: 'task', taskId: state.currentTaskId });
        },

        /**
         * Salva a gravação na biblioteca geral (modo library)
         */
        saveToLibrary: function() {
            console.log('[ScreenRecorder] saveToLibrary() chamado. mode=', state.mode, 'videoBlob=', state.videoBlob);
            
            if (!state.videoBlob) {
                renderErrorState('Erro: dados da gravação não encontrados.');
                return;
            }
            
            // Garante o modo correto
            state.mode = 'library';
            state.currentTaskId = null;
            
            // Previne múltiplos envios
            if (state.isUploading) {
                console.warn('[ScreenRecorder] Upload já em andamento, ignorando chamada duplicada');
                return;
            }
            
            state.isUploading = true;
            renderUploadingState();
            this.upload({ target: 'library' });
        },

        /**
         * Faz download do vídeo (modo rápido)
         */
        download: function() {
            if (!state.videoBlob) {
                renderErrorState('Erro: vídeo não encontrado.');
                return;
            }
            
            // Cria link de download temporário
            const url = state.videoUrl || URL.createObjectURL(state.videoBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'screen-recording-' + new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5) + '.webm';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            // Fecha o overlay após download
            setTimeout(() => {
                this.close();
            }, 500);
        },

        /**
         * Faz upload do vídeo para o servidor
         * @param {object} options - Opções de upload
         * @param {string} options.target - 'task' ou 'library'
         * @param {number} [options.taskId] - ID da tarefa (obrigatório se target='task')
         */
        upload: function(options) {
            const target = (options && options.target) ? options.target : 'task';
            const taskId = (options && options.taskId) ? options.taskId : state.currentTaskId;
            const blob = state.videoBlob;
            const durationSeconds = state.durationSeconds || 0;
            
            if (!blob) {
                console.error('[ScreenRecorder] upload() abortado: videoBlob inexistente');
                state.isUploading = false;
                reenableButtons();
                renderErrorState('Erro: dados da gravação não encontrados.');
                return;
            }
            
            const formData = new FormData();
            formData.append('mode', target);
            formData.append('duration_seconds', durationSeconds);
            formData.append('has_audio', state.includeAudio ? 1 : 0);
            
            if (target === 'task' && taskId) {
                formData.append('task_id', taskId);
                formData.append('recording_type', 'screen_recording');
                formData.append('duration', durationSeconds); // Mantém compatibilidade
            }
            
            // Gera nome do arquivo com timestamp
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
            formData.append('file', blob, 'screen-recording-' + timestamp + '.webm');
            
            // Obtém a URL base do projeto (reutiliza o padrão do board.php)
            const uploadUrl = window.pixelhubUploadUrl || '/tasks/attachments/upload';
            
            fetch(uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Erro HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                state.isUploading = false;
                reenableButtons();
                
                if (data.success) {
                    // Limpa videoBlob após upload bem-sucedido
                    if (state.videoUrl) {
                        URL.revokeObjectURL(state.videoUrl);
                    }
                    state.videoBlob = null;
                    state.videoUrl = null;
                    
                    // Fecha o overlay
                    this.close();
                    
                    // Mensagem de sucesso baseada no modo
                    if (target === 'library' && data.public_url) {
                        // Dispara evento para recarregar a lista de gravações
                        document.dispatchEvent(new CustomEvent('screenRecordingUploaded', {
                            detail: { 
                                id: data.id,
                                url: data.public_url,
                                context: 'library'
                            }
                        }));
                        
                        const message = 'Gravação salva na biblioteca.\n\nLink para compartilhar:\n' + data.public_url;
                        if (typeof showToast === 'function') {
                            showToast('Gravação salva com sucesso!', 'success');
                        } else {
                            alert(message);
                        }
                        // Opcional: copiar link automaticamente
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(data.public_url).catch(function(err) {
                                console.warn('Não foi possível copiar o link automaticamente:', err);
                            });
                        }
                        
                        // Recarrega a página após 1 segundo para mostrar a nova gravação na lista
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else if (target === 'task') {
                        // Dispara evento customizado para atualizar lista de anexos
                        document.dispatchEvent(new CustomEvent('screenRecordingUploaded', {
                            detail: { taskId: taskId }
                        }));
                        
                        if (typeof showToast === 'function') {
                            showToast('Gravação salva com sucesso!', 'success');
                        } else {
                            alert('Gravação salva com sucesso!');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('Gravação salva com sucesso!', 'success');
                        } else {
                            alert('Gravação salva com sucesso!');
                        }
                    }
                } else {
                    throw new Error(data.message || 'Erro ao salvar gravação');
                }
            }.bind(this))
            .catch(function(error) {
                state.isUploading = false;
                reenableButtons();
                console.error('Erro no upload:', error);
                
                // Renderiza estado de erro mas mantém o preview para permitir nova tentativa
                const content = document.getElementById('screen-recording-content');
                if (content) {
                    const errorMessage = error.message || 'Erro desconhecido';
                    const errorHtml = `
                        <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 12px; margin-bottom: 15px; color: #721c24;">
                            <strong>Erro ao enviar gravação</strong>
                            <p style="margin: 8px 0 0 0; font-size: 13px;">${escapeHtml(errorMessage)}</p>
                            <p style="margin: 8px 0 0 0; font-size: 13px;">Verifique sua conexão e tente novamente.</p>
                        </div>
                    `;
                    
                    // Mantém o preview do vídeo
                    const previewHtml = `
                        <h3 style="margin: 0 0 15px 0; color: #023A8D; font-size: 20px;">Preview da gravação</h3>
                        <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                            Duração: <strong>${formatDuration(durationSeconds)}</strong>
                        </p>
                        <video id="screen-recording-preview-video" controls style="width: 100%; max-height: 400px; border-radius: 6px; margin-bottom: 20px; background: #000;" preload="metadata">
                            <source src="${state.videoUrl}" type="video/webm">
                            Seu navegador não suporta a reprodução deste vídeo.
                        </video>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button id="screen-recording-discard-btn" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                                Descartar
                            </button>
                            <button id="screen-recording-save-btn" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                                Tentar novamente
                            </button>
                        </div>
                    `;
                    
                    content.innerHTML = errorHtml + previewHtml;
                    
                    // Reanexa listeners
                    document.getElementById('screen-recording-discard-btn').addEventListener('click', function() {
                        this.reset();
                    }.bind(this));
                    
                    document.getElementById('screen-recording-save-btn').addEventListener('click', function() {
                        this.save();
                    }.bind(this));
                }
            }.bind(this));
        },

        /**
         * Fecha o overlay e reseta o estado
         * @param {boolean} force - Se true, fecha sem confirmação (usado internamente)
         * @param {Function} callback - Função opcional para executar após fechar
         */
        close: function(force, callback) {
            // Se estiver fazendo upload, não permite fechar
            if (state.isUploading && !force) {
                return;
            }
            
            // Se estiver gravando, pede confirmação
            if (state.isRecording && !force) {
                const confirmed = window.confirm(
                    'A gravação está em andamento. Se você fechar agora, a gravação será perdida. Deseja realmente sair?'
                );
                if (!confirmed) {
                    return; // Usuário cancelou, mantém overlay aberto
                }
                // Usuário confirmou, para a gravação mas não gera preview
                this.stop();
                // Para o stream imediatamente
                if (state.stream) {
                    state.stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                    state.stream = null;
                }
            }
            
            // Se houver vídeo gravado mas não enviado, pede confirmação apenas em modo tarefa com ID válido
            if (state.videoBlob && !state.isUploading && !force && state.mode === 'task' && state.currentTaskId) {
                const confirmed = window.confirm(
                    'Você tem uma gravação pronta que ainda não foi salva na tarefa. Deseja descartá-la?'
                );
                if (!confirmed) {
                    return; // Usuário cancelou, mantém overlay aberto
                }
            }
            
            // Para todos os streams
            if (state.screenStream) {
                state.screenStream.getTracks().forEach(t => t.stop());
                state.screenStream = null;
            }
            if (state.micStream) {
                state.micStream.getTracks().forEach(t => t.stop());
                state.micStream = null;
            }
            if (state.stream) {
                state.stream.getTracks().forEach(function(track) {
                    track.stop();
                });
                state.stream = null;
            }
            
            // Limpa countdown se existir
            if (state.countdownIntervalId) {
                clearInterval(state.countdownIntervalId);
                state.countdownIntervalId = null;
            }
            
            // Limpa o timer
            if (state.timerIntervalId) {
                clearInterval(state.timerIntervalId);
                state.timerIntervalId = null;
            }
            
            // Limpa a URL do vídeo
            if (state.videoUrl) {
                URL.revokeObjectURL(state.videoUrl);
                state.videoUrl = null;
            }
            
            // Sai do modo compacto se estiver nele
            if (state.isCompact) {
                exitCompactMode();
            }
            
            // Reseta o estado (mas mantém videoBlob se ainda estiver no preview)
            state.currentTaskId = null;
            state.mediaRecorder = null;
            state.chunks = [];
            state.isRecording = false;
            state.durationSeconds = 0;
            // videoBlob só é zerado quando descartar ou após upload bem-sucedido
            // state.videoBlob = null; // REMOVIDO - não zerar aqui
            state.isUploading = false;
            state.includeAudio = true; // Reseta para padrão
            // Não reseta state.mode aqui - mantém o modo que foi definido no open()
            state.isCompact = false; // Reseta modo compacto
            state.isPaused = false; // Reseta pausa
            
            // Reabilita botões
            reenableButtons();
            
            // Esconde o overlay e o painel compacto
            const overlay = document.getElementById('screen-recording-overlay');
            const floating = document.getElementById('screen-recording-floating');
            
            if (overlay) {
                overlay.style.display = 'none';
            }
            
            if (floating) {
                floating.style.display = 'none';
                floating.style.pointerEvents = 'none';
            }
            
            // Executa callback se fornecido
            if (typeof callback === 'function') {
                callback();
            }
        },

        /**
         * Reseta o estado e volta para o estado inicial
         */
        reset: function() {
            // Limpa countdown se existir
            if (state.countdownIntervalId) {
                clearInterval(state.countdownIntervalId);
                state.countdownIntervalId = null;
            }
            
            // Limpa timer
            if (state.timerIntervalId) {
                clearInterval(state.timerIntervalId);
                state.timerIntervalId = null;
            }
            
            // Sai do modo compacto se estiver nele
            if (state.isCompact) {
                exitCompactMode();
            }
            
            // Reseta flags
            state.isCompact = false;
            state.isPaused = false;
            
            // Se existe vídeo e estamos numa tarefa válida, perguntar antes
            if (state.videoBlob && state.mode === 'task' && state.currentTaskId) {
                const confirmar = window.confirm(
                    'Você tem uma gravação pronta que ainda não foi salva na tarefa. Deseja descartá-la?'
                );
                if (!confirmar) {
                    return; // não descarta
                }
            }
            
            // Zera videoBlob ao descartar (reset é chamado quando usuário clica em Descartar)
            if (state.videoBlob) {
                if (state.videoUrl) {
                    URL.revokeObjectURL(state.videoUrl);
                }
                state.videoBlob = null;
                state.videoUrl = null;
            }
            
            this.close(true); // Fecha sem confirmação
            
            // Garante que overlay e painel estão escondidos
            const overlay = document.getElementById('screen-recording-overlay');
            const floating = document.getElementById('screen-recording-floating');
            
            if (overlay) {
                overlay.style.display = 'none';
            }
            
            if (floating) {
                floating.style.display = 'none';
                floating.style.pointerEvents = 'none';
            }
            
            // Recria overlay e renderiza estado inicial
            const newOverlay = ensureOverlayExists();
            // Renderiza estado inicial baseado no modo
            if (state.mode === 'quick') {
                renderQuickModeInitialState();
            } else if (state.mode === 'library') {
                renderInitialState(); // renderInitialState agora detecta modo library
            } else {
                renderInitialState();
            }
            newOverlay.style.display = 'flex';
        },

        /**
         * Abre o gravador em modo rápido (sem vincular a tarefa, apenas download)
         */
        openQuick: function() {
            if (!isSupported()) {
                const overlay = ensureOverlayExists();
                const content = document.getElementById('screen-recording-content');
                if (content) {
                    content.innerHTML = `
                        <h3 style="margin: 0 0 15px 0; color: #c33; font-size: 20px;">Navegador não suportado</h3>
                        <p style="color: #666; margin-bottom: 20px; line-height: 1.5;">
                            Seu navegador não suporta gravação de tela. Tente usar a versão mais recente do Google Chrome ou Microsoft Edge.
                        </p>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button id="screen-recording-close-error-btn" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                                Fechar
                            </button>
                        </div>
                    `;
                    document.getElementById('screen-recording-close-error-btn').addEventListener('click', function() {
                        this.close();
                    }.bind(this));
                }
                overlay.style.display = 'flex';
                return;
            }
            
            // Modo rápido: não vincula a tarefa
            state.currentTaskId = null;
            state.mode = 'quick';
            
            // Reseta flags para garantir que comece no modo "grande" normal
            state.isCompact = false;
            state.isPaused = false;
            
            this.reset();
            
            const overlay = ensureOverlayExists();
            renderQuickModeInitialState();
            overlay.style.display = 'flex';
        },

        /**
         * Formata duração em segundos para mm:ss
         */
        formatDuration: formatDuration
    };

    // Inicializa o overlay quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureOverlayExists);
    } else {
        ensureOverlayExists();
    }
})();


