# Relatório: Comportamento Persistente no Inbox

**Data:** 30/01/2026  
**Status:** Problemas continuam após múltiplas rodadas de correções.  
**Objetivo:** Consolidar tudo que foi feito e levantar hipóteses do que ainda pode estar causando o problema.

---

## 1. O que já foi implementado

### Rodada 1 – Problemas originais (doc: INVESTIGACAO_INBOX_PROBLEMAS_RENDERIZACAO_E_INPUT.md)

| # | Problema | Correção aplicada | Arquivo/Linha |
|---|----------|------------------|---------------|
| 1 | Campo cortado ao abrir | `min-height: 0` em `.inbox-drawer-body` e `.inbox-drawer-chat` | main.php ~277–281, ~356–360 |
| 1 | Reflow no primeiro render | `requestAnimationFrame(() => { chat.offsetHeight; })` após `chat.style.display = 'flex'` | main.php ~2471–2472 |
| 2 | Microfone some ao clicar | `autocomplete="off"` (depois trocado por `autocomplete="nope"`) | main.php ~1677 |
| 3 | Texto em uma linha | Troca de `<input type="text">` por `<textarea>` | main.php ~1677 |
| 3 | Auto-resize | Função `autoResizeInboxTextarea()` com altura máx. 120px | main.php ~2297–2302 |
| 3 | Enter/Shift+Enter | Enter envia, Shift+Enter quebra linha em `handleInboxInputKeypress` | main.php ~2618–2627 |
| 3 | Reset após envio | Chamada a `autoResizeInboxTextarea` após limpar o campo | main.php ~2640–2642 |

### Rodada 2 – Persistência dos problemas

| # | Problema | Correção aplicada | Arquivo/Linha |
|---|----------|------------------|---------------|
| 2 | Anti-autofill reforçado | `autocomplete="nope"`, `data-lpignore="true"`, `data-1p-ignore`, `data-form-type="other"` | main.php ~1677 |
| 2 | Reset ao trocar conversa | Limpar `input.value`, chamar `autoResizeInboxTextarea` e `updateInboxSendMicVisibility` em `loadInboxConversation` | main.php ~2476–2482 |
| 3 | Campo explodindo | `min-width: 0` em `.inbox-drawer-input` e no textarea | main.php ~430–431, ~435 |
| 3 | Overflow horizontal | `overflow: hidden` no container, `overflow-x: hidden` e `width: 100%` no textarea, `box-sizing: border-box` | main.php ~430, ~436, ~446–447 |

### Correção extra

| Item | Correção |
|------|----------|
| Erro SVG no ícone de regravar | Path `a5 0 01-10 0` corrigido para `a5 5 0 0 1-10 0` (arc flag malformado) | main.php ~1655 |

---

## 2. Comportamento que ainda persiste

- **Microfone some** cerca de 1 segundo após abrir o Inbox ou ao interagir com o campo.
- **Campo “explode”** horizontalmente, avançando para a direita e empurrando elementos.
- **net::ERR_CONNECTION_RESET** ao carregar mídias (imagens/áudios) — provável problema de rede/servidor.

---

## 3. O que ainda pode estar causando o problema

### 3.1. Microfone some

**Hipóteses ainda não descartadas:**

1. **Cache do navegador**  
   O usuário pode estar vendo HTML/CSS/JS antigos. O documento antigo usava `<input type="text">` e não tinha as proteções anti-autofill atuais.

2. **Autofill após o reset**  
   Em `loadInboxConversation` limpamos o campo e chamamos `updateInboxSendMicVisibility`. O autofill pode rodar depois, ao focar o campo, e preencher o valor, disparando `input` e escondendo o microfone.

3. **Extensões do navegador**  
   Extensões (ex.: screen-recorder.js) podem alterar o DOM ou injetar scripts e interferir no comportamento do campo.

4. **`InboxMediaState.base64` incorreto**  
   Se `InboxMediaState.base64` estiver preenchido sem o usuário ter anexado arquivo, `hasContent` fica `true` e o microfone é escondido. Hoje isso só é definido no handler de `change` do file input; vale checar se há outro ponto que altera esse estado.

5. **CSS mais específico**  
   Alguma regra de outra página (ex.: `show?id=3`) pode sobrescrever `display` do botão do microfone.

6. **`setInboxAudioUI` em estado incorreto**  
   Se `InboxAudioState.state` ficar diferente de `'idle'` sem o usuário gravar, `setInboxAudioUI` pode esconder o `.inbox-drawer-input` inteiro (incluindo mic e campo).

7. **Ordem de execução**  
   `updateInboxSendMicVisibility` é chamada em vários pontos; alguma chamada pode ocorrer depois de um autofill ou de outro evento que altere o valor do campo.

### 3.2. Campo explodindo horizontalmente

**Hipóteses ainda não descartadas:**

1. **Cache do navegador**  
   Se o cache ainda servir o antigo `<input type="text">`, o comportamento de “explosão” horizontal continuará, pois input não quebra linha.

2. **Conflito de CSS**  
   Estilos da página interna (ex.: projeto `show?id=3`) podem sobrescrever `min-width`, `overflow` ou `width` do textarea/container.

3. **Contexto de layout**  
   O Inbox está em `main.php` (layout global). O conteúdo da página (`$content`) é injetado em `<main class="content">`. O drawer do Inbox é irmão desse `main`. Se houver CSS global que afete flex ou width, pode impactar o drawer.

4. **Largura do drawer**  
   `.inbox-drawer` tem `width: 850px` e `max-width: 95vw`. Em viewports pequenos ou com zoom, o cálculo de largura pode falhar e o flex não conseguir limitar o textarea.

5. **`word-break` / `overflow-wrap`**  
   Textos longos sem espaços podem não quebrar. O textarea pode precisar de `word-break: break-word` ou `overflow-wrap: break-word`.

6. **Diferença entre navegadores**  
   Alguns navegadores podem tratar `min-width: 0` em flex de forma diferente.

### 3.3. Pontos de chamada de `updateInboxSendMicVisibility`

| Local | Quando |
|-------|--------|
| `oninput` do textarea | A cada alteração de texto |
| `updateInboxMediaPreview` | Ao anexar arquivo |
| `removeInboxMediaPreview` | Ao remover preview de mídia |
| `sendInboxMessage` | Após envio bem-sucedido |
| `loadInboxConversation` | Ao carregar/trocar conversa |

---

## 4. Próximos passos sugeridos (diagnóstico, sem implementar)

1. **Confirmar versão em uso**  
   - Hard refresh (Ctrl+Shift+R) ou abrir em aba anônima.  
   - No DevTools (Elements), verificar se o elemento é `<textarea>` ou ainda `<input>`.

2. **Rastrear o evento `input`**  
   - No console, antes de interagir:
   ```javascript
   const el = document.getElementById('inboxMessageInput');
   el.addEventListener('input', () => console.log('INPUT', el.value, new Error().stack));
   ```
   - Ver se `input` dispara sem digitação e qual a stack trace.

3. **Checar estado e visibilidade**  
   - No momento em que o microfone some:
   ```javascript
   console.log({
     value: document.getElementById('inboxMessageInput')?.value,
     base64: typeof InboxMediaState !== 'undefined' ? InboxMediaState.base64 : 'N/A',
     micDisplay: document.getElementById('inboxBtnMic')?.style.display,
     audioState: typeof InboxAudioState !== 'undefined' ? InboxAudioState.state : 'N/A'
   });
   ```

4. **Testar sem extensões**  
   - Abrir em modo anônimo com extensões desabilitadas e repetir o fluxo.

5. **Inspecionar CSS aplicado**  
   - No DevTools, inspecionar o textarea e o botão do microfone e ver quais regras de `display`, `min-width`, `overflow` e `width` estão efetivamente aplicadas.

6. **Comparar com o Painel de Comunicação**  
   - O Painel (`communication_hub/index.php`) usa textarea e mic sem esses problemas. Comparar estrutura HTML, CSS e fluxo de `updateSendMicVisibility` com o Inbox.

---

## 5. Referências de código

| Arquivo | Trechos relevantes |
|---------|--------------------|
| `views/layout/main.php` | ~423–450 (CSS input), ~1671–1687 (HTML input), ~2028–2049 (setInboxAudioUI), ~2297–2316 (autoResize, updateInboxSendMicVisibility), ~2476–2482 (reset em loadInboxConversation) |
| `views/communication_hub/index.php` | ~3513–3533 (textarea e botões), ~3922–3931 (updateSendMicVisibility), ~929–941 (hub-text CSS) |

---

*Relatório gerado em 30/01/2026. Sem implementações — apenas documentação para diagnóstico.*
