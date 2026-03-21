<?php ob_start(); ?>
<style>
.st-wrap {
    max-width: 880px;
    margin: 0 auto;
}
/* ── Tabs ──────────────────────────────────────────────────────────────────── */
.st-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0;
}
.st-tab {
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color .15s, border-color .15s;
    border-radius: 6px 6px 0 0;
}
.st-tab:hover { color: #023A8D; }
.st-tab.active { color: #023A8D; border-bottom-color: #023A8D; }
.st-tab-panel { display: none; }
.st-tab-panel.active { display: block; }
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
/* ── Roteiro ─────────────────────────────────────────────────────────────── */
.st-roteiro-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 24px;
}
.st-scenario-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    background: #fff;
}
.st-scenario-card .sc-label {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.st-scenario-card .sc-examples {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 8px;
    font-style: italic;
}
.st-scenario-card .sc-script {
    font-size: 12px;
    color: #1e293b;
    background: #f0fdf4;
    border-left: 3px solid #22c55e;
    padding: 8px 10px;
    border-radius: 0 6px 6px 0;
    white-space: pre-wrap;
    line-height: 1.5;
}
.st-scenario-card .sc-branch {
    margin-top: 8px;
    font-size: 12px;
    color: #1e293b;
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
    padding: 8px 10px;
    border-radius: 0 6px 6px 0;
    white-space: pre-wrap;
    line-height: 1.5;
}
.st-roteiro-orsegups {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
}
.st-roteiro-orsegups .roh-header {
    background: #023A8D;
    color: #fff;
    padding: 14px 20px;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.st-roteiro-orsegups .roh-body {
    padding: 20px;
}
.st-flow-msg {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 12px;
    font-size: 13px;
    color: #1e293b;
    white-space: pre-wrap;
    line-height: 1.6;
}
.st-flow-msg .fm-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #94a3b8;
    margin-bottom: 6px;
}
.st-flow-arrow {
    text-align: center;
    color: #94a3b8;
    font-size: 18px;
    margin: -4px 0 8px;
}
.st-flow-branches {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
    margin-top: 12px;
}
.st-branch-card {
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12px;
    line-height: 1.5;
}
.st-branch-card .bc-label {
    font-weight: 700;
    margin-bottom: 6px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.st-branch-card.bc-green { background: #f0fdf4; border: 1px solid #bbf7d0; }
.st-branch-card.bc-green .bc-label { color: #15803d; }
.st-branch-card.bc-orange { background: #fff7ed; border: 1px solid #fed7aa; }
.st-branch-card.bc-orange .bc-label { color: #c2410c; }
.st-branch-card.bc-blue { background: #eff6ff; border: 1px solid #bfdbfe; }
.st-branch-card.bc-blue .bc-label { color: #1d4ed8; }
/* ── Simular Prospect ───────────────────────────────────────────────────── */
.st-scenario-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}
.st-scen-btn {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 10px 8px;
    background: #fff;
    cursor: pointer;
    text-align: left;
    transition: border-color .15s, background .15s;
    font-size: 11px;
    color: #374151;
    line-height: 1.4;
}
.st-scen-btn .sb-emoji { font-size: 18px; display: block; margin-bottom: 4px; }
.st-scen-btn .sb-title { font-weight: 700; font-size: 11px; display: block; }
.st-scen-btn .sb-desc { color: #94a3b8; font-size: 10px; display: block; margin-top: 2px; }
.st-scen-btn:hover { border-color: #a5b4fc; background: #f5f3ff; }
.st-scen-btn.selected { border-color: #023A8D; background: #eff6ff; }
.st-sim-chat-wrap { display: none; }
.st-sim-messages {
    min-height: 200px;
    max-height: 420px;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.st-sim-msg {
    max-width: 80%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.st-sim-msg.prospect { align-self: flex-start; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px 12px 12px 12px; color: #1e293b; }
.st-sim-msg.salesperson { align-self: flex-end; background: #023A8D; color: #fff; border-radius: 12px 4px 12px 12px; }
.st-sim-msg.system { align-self: center; max-width: 95%; padding: 6px 12px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 12px; text-align: center; }
.st-feedback-bubble {
    align-self: center;
    max-width: 90%;
    background: #fefce8;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 12px;
    color: #713f12;
    line-height: 1.5;
}
.st-prospect-label {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 2px;
    align-self: flex-start;
}
.st-seller-label {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 2px;
    align-self: flex-end;
}
</style>

<div class="st-wrap">

    <div class="st-header">
        <h2>🎯 Treinamento de Vendas — Prospecção Ativa</h2>
        <p>Gere abordagens, consulte o roteiro de respostas e simule conversas com prospects reais.</p>
    </div>

    <!-- Tab navigation -->
    <div class="st-tabs">
        <button class="st-tab active" onclick="stSwitchTab('abordagem', this)">✏️ Gerar Abordagem</button>
        <button class="st-tab" onclick="stSwitchTab('roteiro', this)">📋 Roteiro de Respostas</button>
        <button class="st-tab" onclick="stSwitchTab('simular', this)">🎭 Simular Prospect</button>
    </div>

    <!-- ═══════════════════════════════════════════════════════ TAB 1: ABORDAGEM -->
    <div id="stPanel-abordagem" class="st-tab-panel active">

        <div class="st-card">
            <div class="st-card-header">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Dados do Prospect</span>
            </div>
            <div class="st-card-body">
                <textarea id="stProspectData" class="st-prospect-area" placeholder="Cole aqui os dados do prospect. Exemplo:

ItoupavaCell Celulares 🔗
Rua Dr. Pedro Zimmermann, 6005 - Itoupava Central, Blumenau - SC, 89068-003, Brasil
itoupavacellcelulares.com.br"></textarea>
                <br>
                <button class="st-btn-generate" id="stBtnGenerate" onclick="stGenerate()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Gerar primeira abordagem
                </button>
            </div>
        </div>

        <div class="st-card st-chat" id="stChatCard">
            <div class="st-card-header">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Refinamento</span>
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
                <textarea id="stFeedbackInput" class="st-feedback-input" placeholder="Dê feedback para refinar… (ex: mais curto, aprovado ✓)" rows="1" onkeydown="stFeedbackKeydown(event)" oninput="stAutoResize(this)"></textarea>
                <button class="st-btn-send" id="stBtnSend" onclick="stSendFeedback()">Enviar</button>
            </div>
            <div class="st-reset-row">
                <button class="st-btn-reset" onclick="stReset()">↺ Reiniciar</button>
            </div>
        </div>

    </div><!-- /tab abordagem -->

    <!-- ═══════════════════════════════════════════════════════ TAB 2: ROTEIRO -->
    <div id="stPanel-roteiro" class="st-tab-panel">

        <!-- Orsegups qualification flow -->
        <div class="st-roteiro-orsegups">
            <div class="roh-header">🔐 Orsegups — Roteiro de Qualificação (Mensagem 3)</div>
            <div class="roh-body">
                <div style="font-size:12px;color:#64748b;margin-bottom:14px;">Após passar pela barreira inicial e despertar interesse, conduza para a apresentação + qualificação:</div>
                <div class="st-flow-msg">
                    <div class="fm-label">Mensagem 3 — Apresentação + Qualificação</div>Sou o Charles, trabalho com a Orsegups aqui na região com monitoramento eletrônico.
Me diz uma coisa — hoje vocês já usam algum tipo de monitoramento aí ou ainda não?</div>
                <div class="st-flow-arrow">↓ baseado na resposta</div>
                <div class="st-flow-branches">
                    <div class="st-branch-card bc-green">
                        <div class="bc-label">✅ Caso 1 — Decisor</div>
                        Responde direto, sabe do assunto<br><br>
                        👉 Continua o roteiro normalmente
                    </div>
                    <div class="st-branch-card bc-orange">
                        <div class="bc-label">⚠ Caso 2 — Funcionário</div>
                        Resposta vaga: "acho que tem", "não sei direito"<br><br>
                        👉 <em>"Entendi — normalmente quem cuida dessa parte aí é você ou outra pessoa?"</em>
                    </div>
                    <div class="st-branch-card bc-blue">
                        <div class="bc-label">🔁 Caso 3 — Redireciona</div>
                        "Isso é com meu chefe"<br><br>
                        👉 <em>"Perfeito — você consegue me indicar com quem falo pra te explicar rapidinho?"</em>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7 scenario cards -->
        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:12px;">🗂 Os 7 Cenários de Resposta — Script para cada um</div>
        <div class="st-roteiro-grid">

            <div class="st-scenario-card">
                <div class="sc-label">🟢 1. Resposta Positiva Direta</div>
                <div class="sc-examples">Ex: "pode", "sim", "claro", "pode falar"</div>
                <div class="sc-script">Bom dia! Sou o Charles, da Orsegups — trabalho com monitoramento eletrônico aqui na região.
Me diz uma coisa — hoje vocês já usam algum sistema de monitoramento aí ou ainda não?</div>
            </div>

            <div class="st-scenario-card">
                <div class="sc-label">🟡 2. "Sobre o quê?"</div>
                <div class="sc-examples">Ex: "sobre o que?", "qual assunto?", "do que se trata?"</div>
                <div class="sc-script">Boa pergunta! É sobre monitoramento eletrônico — trabalho com a Orsegups aqui na região.
Estou passando por alguns estabelecimentos essa semana. Posso te fazer uma pergunta rápida?</div>
            </div>

            <div class="st-scenario-card">
                <div class="sc-label">🟠 3. "Quem fala?"</div>
                <div class="sc-examples">Ex: "quem é?", "quem está falando?"</div>
                <div class="sc-script">Oi! Me chamo Charles, trabalho com a Orsegups — monitoramento eletrônico.
Entrei em contato porque estou visitando comércios aqui no bairro essa semana. Faz sentido conversar rapidinho?</div>
            </div>

            <div class="st-scenario-card">
                <div class="sc-label">🔵 4. "Não sou eu / Outra pessoa"</div>
                <div class="sc-examples">Ex: "não sou responsável", "aqui é recepção", "tem que falar com fulano"</div>
                <div class="sc-script">Entendi! Sem problema.
Você consegue me indicar com quem falo? É rápido — só quero fazer uma pergunta sobre a segurança de vocês.</div>
            </div>

            <div class="st-scenario-card">
                <div class="sc-label">🟣 5. Resposta Curta Neutra</div>
                <div class="sc-examples">Ex: "sim, diga", "pode falar", "ok"</div>
                <div class="sc-script">Opa, boa tarde! Sou o Charles, da Orsegups — trabalho com monitoramento eletrônico aqui na região.
Me diz uma coisa — hoje vocês já têm algum sistema de monitoramento aí ou ainda não?</div>
            </div>

            <div class="st-scenario-card">
                <div class="sc-label">🔴 6. Não Responde (Ghost)</div>
                <div class="sc-examples">Leu, mas não respondeu a 1ª mensagem</div>
                <div class="sc-script">Oi! Vi que você recebeu minha mensagem.
Não vou tomar seu tempo — só queria saber se faz sentido conversar sobre segurança eletrônica pro seu negócio. Posso te fazer uma pergunta rápida?</div>
                <div class="sc-branch">⏱ Se ignorar de novo → aguardar 2–3 dias e tentar uma última vez com outro ângulo.</div>
            </div>

            <div class="st-scenario-card">
                <div class="sc-label">⚫ 7. Rejeição Direta</div>
                <div class="sc-examples">Ex: "não tenho interesse", "não precisa", "obrigado"</div>
                <div class="sc-script">Entendo! Sem problema nenhum.
Posso perguntar só uma coisa — é porque já têm monitoramento ou não veem necessidade por enquanto?</div>
                <div class="sc-branch">👉 Dependendo da resposta: se "já tem" → qualifica o fornecedor atual. Se "não vê necessidade" → abre brecha para educar.</div>
            </div>

        </div>
    </div><!-- /tab roteiro -->

    <!-- ═══════════════════════════════════════════════════════ TAB 3: SIMULAR -->
    <div id="stPanel-simular" class="st-tab-panel">

        <div class="st-card">
            <div class="st-card-header">
                <span>Selecione o cenário para simular</span>
                <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400;">IA = prospect · Você = vendedor Charles</span>
            </div>
            <div class="st-card-body" style="padding-bottom:12px;">
                <div class="st-scenario-selector">
                    <button class="st-scen-btn" data-scenario="positivo" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">🟢</span>
                        <span class="sb-title">Positivo direto</span>
                        <span class="sb-desc">"pode", "sim", "claro"</span>
                    </button>
                    <button class="st-scen-btn" data-scenario="sobre_que" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">🟡</span>
                        <span class="sb-title">Sobre o quê?</span>
                        <span class="sb-desc">"qual assunto?"</span>
                    </button>
                    <button class="st-scen-btn" data-scenario="quem_fala" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">🟠</span>
                        <span class="sb-title">Quem fala?</span>
                        <span class="sb-desc">"quem é você?"</span>
                    </button>
                    <button class="st-scen-btn" data-scenario="nao_sou_eu" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">🔵</span>
                        <span class="sb-title">Não sou eu</span>
                        <span class="sb-desc">"aqui é recepção"</span>
                    </button>
                    <button class="st-scen-btn" data-scenario="neutro" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">🟣</span>
                        <span class="sb-title">Neutro curto</span>
                        <span class="sb-desc">"ok", "pode falar"</span>
                    </button>
                    <button class="st-scen-btn" data-scenario="ghost" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">🔴</span>
                        <span class="sb-title">Ghost</span>
                        <span class="sb-desc">Não respondeu</span>
                    </button>
                    <button class="st-scen-btn" data-scenario="rejeicao" onclick="stSelectScenario(this)">
                        <span class="sb-emoji">⚫</span>
                        <span class="sb-title">Rejeição</span>
                        <span class="sb-desc">"não tenho interesse"</span>
                    </button>
                </div>
                <button class="st-btn-generate" id="stBtnStartSim" onclick="stStartSimulation()" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Iniciar simulação
                </button>
            </div>
        </div>

        <div class="st-card st-sim-chat-wrap" id="stSimChatWrap">
            <div class="st-card-header">
                <span id="stSimScenarioLabel">Simulando…</span>
                <span style="margin-left:auto;font-size:11px;color:#94a3b8;">Digite como se fosse o Charles</span>
            </div>
            <div id="stSimMessages" class="st-sim-messages"></div>
            <div class="st-input-row">
                <textarea id="stSimInput" class="st-feedback-input" placeholder="Digite sua mensagem como vendedor… (Enter para enviar)" rows="1" onkeydown="stSimKeydown(event)" oninput="stAutoResize(this)"></textarea>
                <button class="st-btn-send" id="stBtnSimSend" onclick="stSimSend()">Enviar</button>
            </div>
            <div class="st-reset-row">
                <button class="st-btn-reset" onclick="stSimReset()">↺ Trocar cenário</button>
            </div>
        </div>

    </div><!-- /tab simular -->

</div>

<script>
(function() {

    var _baseUrl      = '<?= rtrim(pixelhub_url(''), '/') ?>';
    var _generating   = false;

    /* ── shared loading helpers ─────────────────────────────────────────── */
    function addLoading(containerId) {
        var msgs = document.getElementById(containerId);
        var d = document.createElement('div');
        d.id = containerId + '_loading';
        d.className = 'st-loading';
        d.innerHTML = '<div class="st-dot"></div><div class="st-dot"></div><div class="st-dot"></div>';
        msgs.appendChild(d);
        msgs.scrollTop = msgs.scrollHeight;
    }
    function removeLoading(containerId) {
        var el = document.getElementById(containerId + '_loading');
        if (el) el.remove();
    }

    window.stAutoResize = function(ta) {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    };

    /* ── Tab switching ──────────────────────────────────────────────────── */
    window.stSwitchTab = function(name, btn) {
        document.querySelectorAll('.st-tab').forEach(function(t) { t.classList.remove('active'); });
        document.querySelectorAll('.st-tab-panel').forEach(function(p) { p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('stPanel-' + name).classList.add('active');
    };

    /* ══════════════════════════════════════ TAB 1 — ABORDAGEM ═══════════ */

    var _prospectData = '';
    var _chatHistory  = [];

    window.stGenerate = function() {
        var ta = document.getElementById('stProspectData');
        _prospectData = ta.value.trim();
        if (!_prospectData) { ta.focus(); return; }
        if (_generating) return;
        _generating = true;

        var btn = document.getElementById('stBtnGenerate');
        btn.disabled = true;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .8s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Gerando…';

        _chatHistory = [];
        var card = document.getElementById('stChatCard');
        card.style.display = 'block';
        document.getElementById('stChatMessages').innerHTML = '';
        addLoading('stChatMessages');

        var fd = new FormData();
        fd.append('prospect_data', _prospectData);

        fetch(_baseUrl + '/prospecting/training/generate', { method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            removeLoading('stChatMessages');
            _generating = false;
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Gerar primeira abordagem';
            if (!data.success) { addChatMsg('stChatMessages', 'system', '⚠ ' + (data.error || 'Erro')); return; }
            _chatHistory.push({ role:'assistant', content:data.message });
            addChatMsgWithCopy('stChatMessages', 'ai', data.message);
            document.getElementById('stFeedbackInput').focus();
        })
        .catch(function(err) {
            removeLoading('stChatMessages'); _generating = false;
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Gerar primeira abordagem';
            addChatMsg('stChatMessages', 'system', '⚠ Erro: ' + err.message);
        });
    };

    window.stSendFeedback = function() {
        var ta = document.getElementById('stFeedbackInput');
        var txt = ta.value.trim();
        if (!txt || _generating) return;
        _generating = true;
        document.getElementById('stBtnSend').disabled = true;

        _chatHistory.push({ role:'user', content:txt });
        addChatMsg('stChatMessages', 'trainer', txt);
        ta.value = ''; stAutoResize(ta);
        addLoading('stChatMessages');

        fetch(_baseUrl + '/prospecting/training/chat', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ prospect_data:_prospectData, chat_history:_chatHistory.slice(0,-1), user_message:txt })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            removeLoading('stChatMessages'); _generating = false;
            document.getElementById('stBtnSend').disabled = false;
            if (!data.success) { addChatMsg('stChatMessages', 'system', '⚠ ' + (data.error||'Erro')); return; }
            _chatHistory.push({ role:'assistant', content:data.message });
            addChatMsgWithCopy('stChatMessages', 'ai', data.message);
            document.getElementById('stFeedbackInput').focus();
        })
        .catch(function(err) {
            removeLoading('stChatMessages'); _generating = false;
            document.getElementById('stBtnSend').disabled = false;
            addChatMsg('stChatMessages', 'system', '⚠ Erro: ' + err.message);
        });
    };

    window.stReset = function() {
        _chatHistory = []; _prospectData = '';
        document.getElementById('stProspectData').value = '';
        document.getElementById('stChatMessages').innerHTML = '';
        document.getElementById('stChatCard').style.display = 'none';
        document.getElementById('stFeedbackInput').value = '';
        document.getElementById('stProspectData').focus();
    };

    window.stInsertHint = function(el) {
        var ta = document.getElementById('stFeedbackInput');
        ta.value = el.textContent.trim(); ta.focus(); stAutoResize(ta);
    };

    window.stFeedbackKeydown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); stSendFeedback(); }
    };

    /* ══════════════════════════════════════ TAB 3 — SIMULAR ═════════════ */

    var _simScenario   = '';
    var _simHistory    = [];
    var _simGenerating = false;

    window.stSelectScenario = function(btn) {
        document.querySelectorAll('.st-scen-btn').forEach(function(b) { b.classList.remove('selected'); });
        btn.classList.add('selected');
        _simScenario = btn.getAttribute('data-scenario');
        document.getElementById('stBtnStartSim').disabled = false;
    };

    window.stStartSimulation = function() {
        if (!_simScenario || _simGenerating) return;
        _simGenerating = true;
        _simHistory = [];

        var labels = {
            positivo:'🟢 Resposta Positiva', sobre_que:'🟡 "Sobre o quê?"',
            quem_fala:'🟠 "Quem fala?"', nao_sou_eu:'🔵 Não sou eu',
            neutro:'🟣 Neutro curto', ghost:'🔴 Ghost', rejeicao:'⚫ Rejeição direta'
        };
        document.getElementById('stSimScenarioLabel').textContent = labels[_simScenario] || 'Simulando…';

        var wrap = document.getElementById('stSimChatWrap');
        wrap.style.display = 'block';
        var msgs = document.getElementById('stSimMessages');
        msgs.innerHTML = '';
        addLoading('stSimMessages');

        fetch(_baseUrl + '/prospecting/training/prospect', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ scenario:_simScenario, chat_history:[], salesperson_message:'' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            removeLoading('stSimMessages'); _simGenerating = false;
            if (!data.success) { addSimMsg('system', '⚠ ' + (data.error||'Erro')); return; }
            _simHistory.push({ role:'assistant', content: (data.prospect_reply||'') + (data.feedback ? '\n[coach]' + data.feedback : '') });
            addSimProspectMsg(data.prospect_reply || '');
            if (data.feedback) addSimFeedback(data.feedback);
            document.getElementById('stSimInput').focus();
        })
        .catch(function(err) {
            removeLoading('stSimMessages'); _simGenerating = false;
            addSimMsg('system', '⚠ Erro: ' + err.message);
        });
    };

    window.stSimSend = function() {
        var ta = document.getElementById('stSimInput');
        var txt = ta.value.trim();
        if (!txt || _simGenerating) return;
        _simGenerating = true;
        document.getElementById('stBtnSimSend').disabled = true;

        addSimSalesMsg(txt);
        ta.value = ''; stAutoResize(ta);
        addLoading('stSimMessages');

        var historyToSend = _simHistory.slice();
        _simHistory.push({ role:'user', content: txt });

        fetch(_baseUrl + '/prospecting/training/prospect', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ scenario:_simScenario, chat_history:historyToSend, salesperson_message:txt })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            removeLoading('stSimMessages'); _simGenerating = false;
            document.getElementById('stBtnSimSend').disabled = false;
            if (!data.success) { addSimMsg('system', '⚠ ' + (data.error||'Erro')); return; }
            _simHistory.push({ role:'assistant', content: (data.prospect_reply||'') });
            addSimProspectMsg(data.prospect_reply || '');
            if (data.feedback) addSimFeedback(data.feedback);
            document.getElementById('stSimInput').focus();
        })
        .catch(function(err) {
            removeLoading('stSimMessages'); _simGenerating = false;
            document.getElementById('stBtnSimSend').disabled = false;
            addSimMsg('system', '⚠ Erro: ' + err.message);
        });
    };

    window.stSimReset = function() {
        _simScenario = ''; _simHistory = [];
        document.querySelectorAll('.st-scen-btn').forEach(function(b) { b.classList.remove('selected'); });
        document.getElementById('stBtnStartSim').disabled = true;
        document.getElementById('stSimChatWrap').style.display = 'none';
        document.getElementById('stSimMessages').innerHTML = '';
        document.getElementById('stSimInput').value = '';
    };

    window.stSimKeydown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); stSimSend(); }
    };

    /* ── DOM helpers ────────────────────────────────────────────────────── */

    function addChatMsg(containerId, role, text) {
        var msgs = document.getElementById(containerId);
        var div = document.createElement('div');
        div.className = 'st-msg ' + role;
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function addChatMsgWithCopy(containerId, role, text) {
        var msgs = document.getElementById(containerId);
        var div = document.createElement('div');
        div.className = 'st-msg ' + role;
        div.textContent = text;
        var actions = document.createElement('div');
        actions.className = 'st-msg-actions';
        actions.innerHTML = '<button onclick="stCopyText(this)" class="btn-copy" data-text="' + encodeURI(text) + '">Copiar</button>';
        div.appendChild(actions);
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function addSimProspectMsg(text) {
        var msgs = document.getElementById('stSimMessages');
        var lbl = document.createElement('div');
        lbl.className = 'st-prospect-label';
        lbl.textContent = 'Prospect';
        msgs.appendChild(lbl);
        var div = document.createElement('div');
        div.className = 'st-sim-msg prospect';
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function addSimSalesMsg(text) {
        var msgs = document.getElementById('stSimMessages');
        var lbl = document.createElement('div');
        lbl.className = 'st-seller-label';
        lbl.textContent = 'Você (Charles)';
        msgs.appendChild(lbl);
        var div = document.createElement('div');
        div.className = 'st-sim-msg salesperson';
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function addSimFeedback(text) {
        var msgs = document.getElementById('stSimMessages');
        var div = document.createElement('div');
        div.className = 'st-feedback-bubble';
        div.textContent = '💬 Coach: ' + text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function addSimMsg(role, text) {
        var msgs = document.getElementById('stSimMessages');
        var div = document.createElement('div');
        div.className = 'st-sim-msg ' + role;
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    window.stCopyText = function(btn) {
        var text = decodeURI(btn.getAttribute('data-text') || '');
        if (!text) return;
        navigator.clipboard.writeText(text).then(function() {
            var orig = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(function() { btn.textContent = orig; }, 1500);
        });
    };

    // spinner keyframe
    var s = document.createElement('style');
    s.textContent = '@keyframes spin{to{transform:rotate(360deg)}} @keyframes stBounce{0%,80%,100%{transform:scale(.8);opacity:.5}40%{transform:scale(1.2);opacity:1}}';
    document.head.appendChild(s);

})();
</script>
<?php
$content = ob_get_clean();
$title = 'Simulador de Treinamento — Prospecção';
require_once __DIR__ . '/../layout/main.php';
