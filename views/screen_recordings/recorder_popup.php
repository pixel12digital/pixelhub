<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gravação de Tela — PixelHub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .recorder-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .recorder-panel h2 {
            font-size: 18px;
            color: #023A8D;
            margin-bottom: 8px;
        }
        .recorder-panel p {
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .timer {
            font-size: 48px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            color: #111;
            margin: 20px 0;
        }
        .timer.recording { color: #dc3545; }
        .timer.paused { color: #fd7e14; }
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .status-badge.idle { background: #e9ecef; color: #666; }
        .status-badge.recording { background: #fde8e8; color: #dc3545; animation: pulse 1.5s infinite; }
        .status-badge.paused { background: #fff3e0; color: #fd7e14; }
        .status-badge.stopped { background: #d4edda; color: #198754; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }
        .dot.red { background: #dc3545; }
        .dot.orange { background: #fd7e14; }
        .dot.green { background: #198754; }
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background: #023A8D; color: white; }
        .btn-primary:hover:not(:disabled) { background: #022a6b; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover:not(:disabled) { background: #b02a37; }
        .btn-warning { background: #fd7e14; color: white; }
        .btn-warning:hover:not(:disabled) { background: #e06b0a; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover:not(:disabled) { background: #565e64; }
        .btn-success { background: #198754; color: white; }
        .btn-success:hover:not(:disabled) { background: #146c43; }
        .audio-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #555;
        }
        .audio-toggle input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .preview-container {
            margin-top: 16px;
        }
        .preview-container video {
            width: 100%;
            max-height: 200px;
            border-radius: 8px;
            background: #000;
        }
        .upload-progress {
            margin-top: 12px;
            font-size: 13px;
            color: #666;
        }
        .upload-progress .bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 6px;
            overflow: hidden;
        }
        .upload-progress .bar-fill {
            height: 100%;
            background: #023A8D;
            border-radius: 3px;
            transition: width 0.3s;
        }
        .info-text {
            font-size: 11px;
            color: #999;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="recorder-panel" id="panel">
        <!-- Estado inicial: pronto para gravar -->
        <div id="state-idle">
            <h2>Gravação de Tela</h2>
            <p>Selecione a tela inteira para gravar.<br>Você pode navegar livremente no PixelHub enquanto grava.</p>
            <div class="audio-toggle">
                <input type="checkbox" id="include-audio" checked>
                <label for="include-audio">Incluir áudio do microfone</label>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="btn-start" onclick="startRecording()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="8"/></svg>
                    Iniciar Gravação
                </button>
            </div>
        </div>

        <!-- Estado: countdown -->
        <div id="state-countdown" style="display:none;">
            <h2>Preparando...</h2>
            <div class="timer" id="countdown-number">3</div>
            <p>A gravação iniciará em instantes</p>
        </div>

        <!-- Estado: gravando -->
        <div id="state-recording" style="display:none;">
            <span class="status-badge recording"><span class="dot red"></span>Gravando</span>
            <div class="timer recording" id="recording-timer">00:00</div>
            <div class="btn-group">
                <button class="btn btn-warning" id="btn-pause" onclick="togglePause()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                    Pausar
                </button>
                <button class="btn btn-danger" id="btn-stop" onclick="stopRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                    Encerrar
                </button>
            </div>
            <div class="info-text">Você pode navegar livremente no PixelHub. Esta janela mantém a gravação ativa.</div>
        </div>

        <!-- Estado: pausado -->
        <div id="state-paused" style="display:none;">
            <span class="status-badge paused"><span class="dot orange"></span>Pausado</span>
            <div class="timer paused" id="paused-timer">00:00</div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="togglePause()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg>
                    Retomar
                </button>
                <button class="btn btn-danger" onclick="stopRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                    Encerrar
                </button>
            </div>
        </div>

        <!-- Estado: preview -->
        <div id="state-preview" style="display:none;">
            <span class="status-badge stopped"><span class="dot green"></span>Gravação finalizada</span>
            <div class="preview-container">
                <video id="preview-video" controls></video>
            </div>
            <div class="btn-group">
                <button class="btn btn-success" id="btn-save" onclick="saveRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Salvar
                </button>
                <button class="btn btn-secondary" onclick="discardRecording()">Descartar</button>
                <button class="btn btn-primary" onclick="resetToIdle()">Nova Gravação</button>
            </div>
            <div id="upload-status" class="upload-progress" style="display:none;">
                <span id="upload-text">Enviando...</span>
                <div class="bar"><div class="bar-fill" id="upload-bar" style="width:0%"></div></div>
            </div>
        </div>

        <!-- Estado: erro -->
        <div id="state-error" style="display:none;">
            <h2 style="color:#dc3545;">Erro</h2>
            <p id="error-message" style="color:#666;"></p>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="resetToIdle()">Tentar Novamente</button>
            </div>
        </div>
    </div>

<script>
const MAX_DURATION = 600; // 10 minutos
const BASE_URL = window.location.origin + '<?= pixelhub_url('') ?>'.replace(/\/$/, '');

// BroadcastChannel para comunicar com a janela principal
const channel = new BroadcastChannel('pixelhub-screen-recorder');

// Estado
let screenStream = null;
let micStream = null;
let combinedStream = null;
let mediaRecorder = null;
let chunks = [];
let isRecording = false;
let isPaused = false;
let durationSeconds = 0;
let timerInterval = null;
let videoBlob = null;
let videoUrl = null;
let mode = 'library';
let taskId = null;

// Lê parâmetros da URL
const params = new URLSearchParams(window.location.search);
if (params.get('task_id')) {
    taskId = parseInt(params.get('task_id'));
    mode = 'task';
}
if (params.get('mode')) {
    mode = params.get('mode');
}

function formatDuration(s) {
    return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}

function showState(stateId) {
    ['idle', 'countdown', 'recording', 'paused', 'preview', 'error'].forEach(s => {
        document.getElementById('state-' + s).style.display = (s === stateId) ? 'block' : 'none';
    });
}

async function startRecording() {
    try {
        // 1) Captura da tela
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });

        // 2) Captura do microfone (se habilitado)
        const includeAudio = document.getElementById('include-audio').checked;
        try {
            if (includeAudio) {
                micStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            }
        } catch (e) {
            console.warn('[RecorderPopup] Microfone não disponível:', e);
        }

        // 3) Combina streams
        combinedStream = new MediaStream();
        screenStream.getVideoTracks().forEach(t => combinedStream.addTrack(t));
        if (micStream) {
            micStream.getAudioTracks().forEach(t => combinedStream.addTrack(t));
        }

        // 4) Countdown
        showState('countdown');
        let count = 3;
        document.getElementById('countdown-number').textContent = count;
        
        await new Promise(resolve => {
            const countdownInterval = setInterval(() => {
                count--;
                if (count <= 0) {
                    clearInterval(countdownInterval);
                    resolve();
                } else {
                    document.getElementById('countdown-number').textContent = count;
                }
            }, 1000);
        });

        // 5) Inicia MediaRecorder
        const options = { mimeType: 'video/webm;codecs=vp9' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            delete options.mimeType;
        }

        chunks = [];
        durationSeconds = 0;
        mediaRecorder = new MediaRecorder(combinedStream, options);

        mediaRecorder.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) {
                chunks.push(e.data);
            }
        };

        mediaRecorder.onstop = function() {
            isRecording = false;
            isPaused = false;
            clearInterval(timerInterval);

            if (!chunks.length) {
                showError('Nenhum dado foi gravado. Tente novamente.');
                return;
            }

            videoBlob = new Blob(chunks, { type: 'video/webm' });
            videoUrl = URL.createObjectURL(videoBlob);

            const video = document.getElementById('preview-video');
            video.src = videoUrl;

            showState('preview');

            // Notifica janela principal
            channel.postMessage({ type: 'recording-stopped', duration: durationSeconds });

            // Para streams
            stopAllStreams();
        };

        mediaRecorder.onstart = function() {
            isRecording = true;
            isPaused = false;
            showState('recording');

            timerInterval = setInterval(() => {
                if (!isPaused) {
                    durationSeconds++;
                    const timerEl = document.getElementById('recording-timer');
                    if (timerEl) timerEl.textContent = formatDuration(durationSeconds);

                    // Notifica janela principal do progresso
                    channel.postMessage({ type: 'recording-tick', duration: durationSeconds });

                    if (durationSeconds >= MAX_DURATION) {
                        stopRecording();
                    }
                }
            }, 1000);

            // Notifica janela principal
            channel.postMessage({ type: 'recording-started' });
        };

        mediaRecorder.start(1000);

        // Listener para quando o usuário para o compartilhamento manualmente
        if (screenStream.getVideoTracks().length > 0) {
            screenStream.getVideoTracks()[0].addEventListener('ended', function() {
                if (isRecording) {
                    stopRecording();
                }
            });
        }

    } catch (error) {
        console.error('[RecorderPopup] Erro:', error);
        if (error.name === 'NotAllowedError') {
            showError('Permissão negada. Selecione uma tela para compartilhar.');
        } else {
            showError('Erro ao iniciar gravação: ' + error.message);
        }
        stopAllStreams();
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch (e) { console.error(e); }
    }
}

function togglePause() {
    if (!mediaRecorder) return;

    if (mediaRecorder.state === 'recording') {
        mediaRecorder.pause();
        isPaused = true;
        document.getElementById('paused-timer').textContent = formatDuration(durationSeconds);
        showState('paused');
        channel.postMessage({ type: 'recording-paused' });
    } else if (mediaRecorder.state === 'paused') {
        mediaRecorder.resume();
        isPaused = false;
        showState('recording');
        channel.postMessage({ type: 'recording-resumed' });
    }
}

function stopAllStreams() {
    if (screenStream) { screenStream.getTracks().forEach(t => t.stop()); screenStream = null; }
    if (micStream) { micStream.getTracks().forEach(t => t.stop()); micStream = null; }
    if (combinedStream) { combinedStream.getTracks().forEach(t => t.stop()); combinedStream = null; }
}

async function saveRecording() {
    if (!videoBlob) return;

    const btnSave = document.getElementById('btn-save');
    btnSave.disabled = true;

    const uploadStatus = document.getElementById('upload-status');
    const uploadText = document.getElementById('upload-text');
    const uploadBar = document.getElementById('upload-bar');
    uploadStatus.style.display = 'block';
    uploadText.textContent = 'Enviando...';
    uploadBar.style.width = '0%';

    const formData = new FormData();
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
    formData.append('file', videoBlob, 'screen-recording-' + timestamp + '.webm');
    formData.append('mode', mode === 'task' ? 'task' : 'library');
    formData.append('duration_seconds', durationSeconds);
    formData.append('has_audio', micStream ? '1' : '0');

    if (mode === 'task' && taskId) {
        formData.append('task_id', taskId);
        formData.append('recording_type', 'screen_recording');
        formData.append('duration', durationSeconds);
    }

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + '/tasks/attachments/upload');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.withCredentials = true;

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                uploadBar.style.width = pct + '%';
                uploadText.textContent = 'Enviando... ' + pct + '%';
            }
        };

        xhr.onload = function() {
            console.log('[RecorderPopup] Upload response:', xhr.status, xhr.responseText.substring(0, 500));
            if (xhr.status >= 200 && xhr.status < 300) {
                let data;
                try { data = JSON.parse(xhr.responseText); } catch (e) { data = {}; }

                uploadText.textContent = 'Salvo com sucesso!';
                uploadBar.style.width = '100%';
                uploadBar.style.background = '#198754';

                // Notifica janela principal
                channel.postMessage({
                    type: 'recording-saved',
                    data: data,
                    taskId: taskId,
                    mode: mode
                });

                // Fecha popup após 2 segundos
                setTimeout(() => window.close(), 2000);
            } else {
                uploadText.textContent = 'Erro ao salvar. Tente novamente.';
                btnSave.disabled = false;
            }
        };

        xhr.onerror = function() {
            uploadText.textContent = 'Erro de conexão. Tente novamente.';
            btnSave.disabled = false;
        };

        xhr.send(formData);
    } catch (e) {
        uploadText.textContent = 'Erro: ' + e.message;
        btnSave.disabled = false;
    }
}

function discardRecording() {
    if (confirm('Deseja realmente descartar esta gravação?')) {
        if (videoUrl) URL.revokeObjectURL(videoUrl);
        videoBlob = null;
        videoUrl = null;
        chunks = [];
        channel.postMessage({ type: 'recording-discarded' });
        resetToIdle();
    }
}

function resetToIdle() {
    stopAllStreams();
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    if (videoUrl) { URL.revokeObjectURL(videoUrl); videoUrl = null; }
    videoBlob = null;
    chunks = [];
    isRecording = false;
    isPaused = false;
    durationSeconds = 0;
    mediaRecorder = null;
    showState('idle');
}

function showError(msg) {
    document.getElementById('error-message').textContent = msg;
    showState('error');
    stopAllStreams();
}

// Escuta mensagens da janela principal (header indicator)
channel.onmessage = function(e) {
    if (e.data.type === 'request-status') {
        channel.postMessage({
            type: 'status',
            isRecording: isRecording,
            isPaused: isPaused,
            duration: durationSeconds
        });
    } else if (e.data.type === 'command-pause') {
        if (isRecording && !isPaused) togglePause();
    } else if (e.data.type === 'command-resume') {
        if (isRecording && isPaused) togglePause();
    } else if (e.data.type === 'command-stop') {
        if (isRecording) stopRecording();
    }
};

// Avisa ao fechar a popup
window.addEventListener('beforeunload', function() {
    if (isRecording) {
        stopRecording();
    }
    channel.postMessage({ type: 'popup-closed' });
});
</script>
</body>
</html>
