<?php ob_start(); ?>
<style>
.st-wrap {
    max-width: 820px;
    margin: 0 auto;
}
.st-header {
    margin-bottom: 24px;
}
.st-header h2 {
    margin: 0 0 4px;
    font-size: 20px;
    color: #1e293b;
}
.st-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}
.st-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
}
.st-card-header {
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.st-card-header span {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}
.st-card-body {
    padding: 20px;
}
.st-prospect-area {
    width: 100%;
    min-height: 100px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
    color: #1e293b;
    line-height: 1.6;
    outline: none;
    transition: border-color .2s;
    box-sizing: border-box;
}
.st-prospect-area:focus {
    border-color: #023A8D;
    box-shadow: 0 0 0 3px rgba(2,58,141,.08);
}
.st-btn-generate {
    margin-top: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #023A8D;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
.st-btn-generate:hover { background: #012d6e; }
.st-btn-generate:disabled { background: #94a3b8; cursor: not-allowed; }
/* Chat area */
.st-chat {
    display: none;
}
.st-chat-messages {
    min-height: 180px;
    max-height: 420px;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.st-msg {
    max-width: 82%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.st-msg.ai {
    align-self: flex-start;
    background: #e8f0fe;
    color: #1e293b;
    border-radius: 4px 12px 12px 12px;
}
.st-msg.trainer {
    align-self: flex-end;
    background: #023A8D;
    color: #fff;
    border-radius: 12px 4px 12px 12px;
}
.st-msg.system {
    align-self: center;
    background: #fef3c7;
    color: #92400e;
    border-radius: 8px;
    font-size: 12px;
    max-width: 95%;
    padding: 8px 12px;
    text-align: center;
}
.st-msg-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}
.st-msg-actions button {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 5px;
    border: 1px solid #c7d2fe;
    background: #fff;
    color: #023A8D;
    cursor: pointer;
    font-weight: 600;
    transition: background .15s;
}
.st-msg-actions button:hover { background: #e0e7ff; }
.st-msg-actions .btn-copy { border-color: #d1d5db; color: #6b7280; }
.st-msg-actions .btn-copy:hover { background: #f3f4f6; }
/* Input */
.st-input-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 12px 16px;
}
.st-feedback-input {
    flex: 1;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    font-family: inherit;
    resize: none;
    outline: none;
    min-height: 42px;
    max-height: 120px;
    line-height: 1.5;
    transition: border-color .2s;
}
.st-feedback-input:focus { border-color: #023A8D; box-shadow: 0 0 0 3px rgba(2,58,141,.08); }
.st-btn-send {
    padding: 10px 16px;
    background: #023A8D;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
    white-space: nowrap;
}
.st-btn-send:hover { background: #012d6e; }
.st-btn-send:disabled { background: #94a3b8; cursor: not-allowed; }
.st-reset-row {
    padding: 10px 16px 14px;
    text-align: right;
    border-top: 1px solid #f1f5f9;
}
.st-btn-reset {
    font-size: 12px;
    color: #94a3b8;
    background: none;
    border: none;
    cursor: pointer;
    text-decoration: underline;
}
.st-btn-reset:hover { color: #ef4444; }
/* Loading dot animation */
.st-loading {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 10px 14px;
    align-self: flex-start;
}
.st-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #94a3b8;
    animation: stBounce .9s infinite ease-in-out;
}
.st-dot:nth-child(2) { animation-delay: .15s; }
.st-dot:nth-child(3) { animation-delay: .3s; }
@keyframes stBounce {
    0%, 80%, 100% { transform: scale(.8); opacity: .5; }
    40% { transform: scale(1.2); opacity: 1; }
}
/* Hint bar */
.st-hint {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 16px 0;
}
.st-hint-chip {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.st-hint-chip:hover { background: #e0e7ff; border-color: #a5b4fc; color: #023A8D; }
</style>

<div class="st-wrap">

    <div class="st-header">
        <h2>🎯 Simulador de Abordagem — Prospecção Ativa</h2>
        <p>Cole os dados de um prospect (copiados dos resultados de prospecção) e o sistema gera a primeira mensagem. Você valida e refina.</p>
    </div>

    <!-- Prospect data input -->
    <div class="st-card">
        <div class="st-card-header">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Dados do Prospect</span>
        </div>
        <div class="st-card-body">
            <textarea
                id="stProspectData"
                class="st-prospect-area"
                placeholder="Cole aqui os dados do prospect. Exemplo:

ItoupavaCell Celulares 🔗
Rua Dr. Pedro Zimmermann, 6005 - Itoupava Central, Blumenau - SC, 89068-003, Brasil
itoupavacellcelulares.com.br"
            ></textarea>
            <br>
            <button class="st-btn-generate" id="stBtnGenerate" onclick="stGenerate()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Gerar primeira abordagem
            </button>
        </div>
    </div>

    <!-- Chat simulation -->
    <div class="st-card st-chat" id="stChatCard">
        <div class="st-card-header">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Simulação de Treinamento</span>
            <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400;">IA = vendedor · Você = treinador</span>
        </div>

        <div id="stChatMessages" class="st-chat-messages"></div>

        <div class="st-hint" id="stHintBar">
            <span class="st-hint-chip" onclick="stInsertHint(this)">mais curto</span>
            <span class="st-hint-chip" onclick="stInsertHint(this)">tom mais casual</span>
            <span class="st-hint-chip" onclick="stInsertHint(this)">troque o bairro</span>
            <span class="st-hint-chip" onclick="stInsertHint(this)">mais direto ao ponto</span>
            <span class="st-hint-chip" onclick="stInsertHint(this)">remova o emoji</span>
            <span class="st-hint-chip" onclick="stInsertHint(this)">aprovado ✓</span>
        </div>

        <div class="st-input-row">
            <textarea
                id="stFeedbackInput"
                class="st-feedback-input"
                placeholder="Dê feedback para refinar… (ex: mais curto, troque o bairro, aprovado ✓)"
                rows="1"
                onkeydown="stFeedbackKeydown(event)"
                oninput="stAutoResize(this)"
            ></textarea>
            <button class="st-btn-send" id="stBtnSend" onclick="stSendFeedback()">Enviar</button>
        </div>

        <div class="st-reset-row">
            <button class="st-btn-reset" onclick="stReset()">↺ Reiniciar simulação</button>
        </div>
    </div>

</div>

<script>
(function() {

    var _prospectData  = '';
    var _chatHistory   = [];  // [{role, content}]
    var _generating    = false;
    var _baseUrl       = '<?= rtrim(pixelhub_url(''), '/') ?>';

    // ── Gerar abordagem inicial ──────────────────────────────────────────────

    window.stGenerate = function() {
        var ta = document.getElementById('stProspectData');
        _prospectData = ta.value.trim();
        if (!_prospectData) {
            ta.focus();
            return;
        }
        if (_generating) return;
        _generating = true;

        var btn = document.getElementById('stBtnGenerate');
        btn.disabled = true;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .8s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Gerando…';

        _chatHistory = [];
        var card = document.getElementById('stChatCard');
        card.style.display = 'block';
        var msgs = document.getElementById('stChatMessages');
        msgs.innerHTML = '';
        stAddLoading();

        var fd = new FormData();
        fd.append('prospect_data', _prospectData);

        fetch(_baseUrl + '/prospecting/training/generate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            stRemoveLoading();
            _generating = false;
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Gerar primeira abordagem';

            if (!data.success) {
                stAddMsg('system', '⚠ ' + (data.error || 'Erro desconhecido'));
                return;
            }

            _chatHistory.push({ role: 'assistant', content: data.message });
            stAddAIMsg(data.message);
            document.getElementById('stFeedbackInput').focus();
        })
        .catch(function(err) {
            stRemoveLoading();
            _generating = false;
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Gerar primeira abordagem';
            stAddMsg('system', '⚠ Erro de conexão: ' + err.message);
        });
    };

    // ── Enviar feedback do treinador ─────────────────────────────────────────

    window.stSendFeedback = function() {
        var ta = document.getElementById('stFeedbackInput');
        var txt = ta.value.trim();
        if (!txt || _generating) return;
        _generating = true;

        var sendBtn = document.getElementById('stBtnSend');
        sendBtn.disabled = true;

        _chatHistory.push({ role: 'user', content: txt });
        stAddMsg('trainer', txt);
        ta.value = '';
        stAutoResize(ta);
        stAddLoading();

        fetch(_baseUrl + '/prospecting/training/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                prospect_data: _prospectData,
                chat_history:  _chatHistory.slice(0, -1), // sem a última (já foi como user_message)
                user_message:  txt
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            stRemoveLoading();
            _generating = false;
            sendBtn.disabled = false;

            if (!data.success) {
                stAddMsg('system', '⚠ ' + (data.error || 'Erro desconhecido'));
                return;
            }

            _chatHistory.push({ role: 'assistant', content: data.message });
            stAddAIMsg(data.message);
            document.getElementById('stFeedbackInput').focus();
        })
        .catch(function(err) {
            stRemoveLoading();
            _generating = false;
            sendBtn.disabled = false;
            stAddMsg('system', '⚠ Erro de conexão: ' + err.message);
        });
    };

    // ── Reset ────────────────────────────────────────────────────────────────

    window.stReset = function() {
        _chatHistory  = [];
        _prospectData = '';
        document.getElementById('stProspectData').value = '';
        document.getElementById('stChatMessages').innerHTML = '';
        document.getElementById('stChatCard').style.display = 'none';
        document.getElementById('stFeedbackInput').value = '';
        document.getElementById('stProspectData').focus();
    };

    // ── Hints ────────────────────────────────────────────────────────────────

    window.stInsertHint = function(el) {
        var ta = document.getElementById('stFeedbackInput');
        ta.value = el.textContent.trim();
        ta.focus();
        stAutoResize(ta);
    };

    // ── Keyboard ─────────────────────────────────────────────────────────────

    window.stFeedbackKeydown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            stSendFeedback();
        }
    };

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function stAddAIMsg(text) {
        var msgs = document.getElementById('stChatMessages');
        var div = document.createElement('div');
        div.className = 'st-msg ai';
        div.textContent = text;

        var actions = document.createElement('div');
        actions.className = 'st-msg-actions';
        actions.innerHTML =
            '<button onclick="stCopyMsg(this)" class="btn-copy" data-text="' + encodeURI(text) + '">Copiar</button>';
        div.appendChild(actions);

        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function stAddMsg(role, text) {
        var msgs = document.getElementById('stChatMessages');
        var div = document.createElement('div');
        div.className = 'st-msg ' + role;
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function stAddLoading() {
        var msgs = document.getElementById('stChatMessages');
        var div = document.createElement('div');
        div.id = 'stLoadingDots';
        div.className = 'st-loading';
        div.innerHTML = '<div class="st-dot"></div><div class="st-dot"></div><div class="st-dot"></div>';
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function stRemoveLoading() {
        var el = document.getElementById('stLoadingDots');
        if (el) el.remove();
    }

    window.stCopyMsg = function(btn) {
        var text = decodeURI(btn.getAttribute('data-text') || '');
        if (!text) return;
        navigator.clipboard.writeText(text).then(function() {
            var orig = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(function() { btn.textContent = orig; }, 1500);
        });
    };

    window.stAutoResize = function(ta) {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    };

    // CSS spin keyframe (for generate button spinner)
    var styleEl = document.createElement('style');
    styleEl.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(styleEl);

})();
</script>
<?php
$content = ob_get_clean();
$title = 'Simulador de Treinamento — Prospecção';
require_once __DIR__ . '/../layout/main.php';
