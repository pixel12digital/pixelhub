# Explicação Detalhada: Problemas do Inbox

**Objetivo:** Documento único que explica o problema, os documentos criados, as causas possíveis e as soluções propostas, para uso pela equipe de desenvolvimento.

---

## 1. Resumo do problema

O **Inbox** (drawer de conversas no layout global do PixelHub) apresenta três comportamentos indesejados:

| # | Sintoma | Quando ocorre |
|---|---------|----------------|
| 1 | Campo de envio cortado e sem microfone | Ao abrir o Inbox pela primeira vez (sem refresh) |
| 2 | Microfone desaparece | ~1 segundo após abrir ou ao clicar no campo de mensagem |
| 3 | Campo de mensagem "explode" horizontalmente | Ao digitar ou ao interagir; empurra microfone e botão Enviar para fora da tela |

**Contexto:** O usuário acessa o Inbox a partir de qualquer página (ex.: `show?id=3` - visualização de projeto). O Inbox é um drawer fixo no canto direito, carregado pelo layout global (`main.php`).

---

## 2. Documentos criados

| Documento | Propósito |
|------------|-----------|
| `INVESTIGACAO_INBOX_PROBLEMAS_RENDERIZACAO_E_INPUT.md` | Investigação inicial dos 3 problemas: causas prováveis, soluções recomendadas e checklist de implementação. |
| `RELATORIO_INBOX_COMPORTAMENTO_PERSISTENTE.md` | Registro de tudo que foi implementado e o que ainda pode estar causando o problema após as correções. |
| `EXPLICACAO_DETALHADA_PROBLEMAS_INBOX.md` | Este documento: explicação detalhada para a equipe de desenvolvimento. |

---

## 3. Explicação técnica detalhada

### 3.1. Problema 1: Campo cortado ao abrir

**O que acontece:** Na primeira abertura do Inbox (sem refresh), a área de envio aparece cortada ou incompleta. Após dar refresh, o layout aparece normalmente.

**Causa técnica (provável):**

- O painel de chat (`#inboxChat`) inicia com `style="display: none"`.
- Ao selecionar uma conversa (ou restaurar do `sessionStorage` após 300ms), o JS define `chat.style.display = 'flex'`.
- O navegador pode não calcular corretamente o layout flex na primeira exibição.
- A estrutura flex em cascata (`.inbox-drawer`, `.inbox-drawer-body`, `.inbox-drawer-chat`) depende de alturas bem definidas. Se o pai não tiver altura definida no momento do primeiro paint, os cálculos de `flex: 1` podem falhar.
- Há uma race condition: o chat passa de oculto a visível enquanto os dados ainda estão sendo carregados.

**Correções já aplicadas:**

- `min-height: 0` em `.inbox-drawer-body` e `.inbox-drawer-chat` para o flex calcular corretamente.
- `requestAnimationFrame(() => { chat.offsetHeight; })` após `chat.style.display = 'flex'` para forçar reflow.

---

### 3.2. Problema 2: Microfone desaparece

**O que acontece:** O microfone aparece inicialmente. Ao clicar no campo de mensagem (ou ~1 segundo após abrir), o microfone some e o botão "Enviar" aparece, mesmo sem o usuário ter digitado nada.

**Causa técnica (provável):**

A lógica de visibilidade está em `updateInboxSendMicVisibility()`:

```javascript
const hasText = input && input.value.trim().length > 0;
const hasMedia = InboxMediaState.base64 !== null;
const hasContent = hasText || hasMedia;

if (btnMic) btnMic.style.display = hasContent ? 'none' : 'block';
if (btnSend) btnSend.style.display = hasContent ? 'block' : 'none';
```

O microfone some quando `hasContent` é `true`. Isso ocorre quando:

1. **O campo tem texto** — sem o usuário ter digitado, o que sugere autofill/autocomplete.
2. **O campo tem mídia anexada** — `InboxMediaState.base64` preenchido indevidamente.
3. **O estado de áudio está incorreto** — `setInboxAudioUI` esconde o `.inbox-drawer-input` inteiro quando o estado não é `'idle'`.

**Fluxo do autofill (hipótese principal):**

1. Usuário clica no campo de mensagem (ou o campo recebe foco).
2. O navegador autopreenche o campo (histórico, sugestões, extensões).
3. O evento `input` dispara.
4. `updateInboxSendMicVisibility()` é chamada.
5. `hasText` fica `true` → microfone escondido, botão Enviar exibido.

**Correções já aplicadas:**

- `autocomplete="nope"` (alguns navegadores ignoram `off`).
- `data-lpignore="true"` (LastPass), `data-1p-ignore` (1Password), `data-form-type="other"`.
- Reset do campo e chamada de `updateInboxSendMicVisibility()` ao trocar de conversa em `loadInboxConversation`.

---

### 3.3. Problema 3: Campo explode horizontalmente

**O que acontece:** Ao digitar texto longo, o campo de mensagem se estende horizontalmente e empurra o microfone e o botão Enviar para fora da área visível.

**Causa técnica (provável):**

- **Se era `<input type="text">`:** o input não suporta múltiplas linhas; o texto sempre fica em uma linha e se estende horizontalmente.
- **Em flexbox:** itens com `flex: 1` têm `min-width: auto` por padrão. O navegador não permite que o item encolha abaixo da largura intrínseca do conteúdo. Para um textarea sem `min-width: 0`, isso pode causar overflow horizontal e o container expandir além do viewport.
- **Textos longos sem espaços podem não quebrar** sem `word-break` ou `overflow-wrap`.

**Correções já aplicadas:**

- Troca de `<input type="text">` por `<textarea>`.
- Função `autoResizeInboxTextarea()` com altura máxima de 120px.
- `min-width: 0` no textarea e no container `.inbox-drawer-input`.
- `overflow: hidden` no container, `overflow-x: hidden` e `width: 100%` no textarea.
- `box-sizing: border-box` no textarea.

---

## 4. Possíveis causas ainda não resolvidas

### 4.1. Microfone some

| Causa | Explicação |
|-------|------------|
| **Cache do navegador** | O usuário pode estar vendo HTML/CSS/JS antigos (com `<input>` e sem anti-autofill). |
| **Autofill após o reset** | Em `loadInboxConversation` limpamos o campo e chamamos `updateInboxSendMicVisibility`. O autofill pode rodar depois, ao focar o campo, preenchendo o valor e disparando `input`. |
| **Extensões do navegador** | Extensões (ex.: screen-recorder.js) podem alterar o DOM ou injetar scripts e interferir no campo. |
| **InboxMediaState.base64 incorreto** | Se estiver preenchido sem o usuário ter anexado arquivo, `hasContent` fica `true` e o microfone é escondido. |
| **CSS mais específico** | Regras de outra página (ex.: `show?id=3`) podem sobrescrever `display` do botão do microfone. |
| **setInboxAudioUI em estado incorreto** | Se `InboxAudioState.state` ficar diferente de `'idle'` sem o usuário gravar, `setInboxAudioUI` esconde o `.inbox-drawer-input` inteiro. |
| **Ordem de execução** | `updateInboxSendMicVisibility` é chamada em vários pontos; alguma chamada pode ocorrer depois de um autofill ou de outro evento que altere o valor do campo. |

### 4.2. Campo explode horizontalmente

| Causa | Explicação |
|-------|------------|
| **Cache do navegador** | Se o cache ainda servir o antigo `<input type="text">`, o comportamento de “explosão” horizontal continuará. |
| **Conflito de CSS** | Estilos da página interna podem sobrescrever `min-width`, `overflow` ou `width` do textarea/container. |
| **Contexto de layout** | O Inbox está em `main.php` (layout global). O conteúdo da página é injetado em `<main class="content">`. O drawer do Inbox é irmão desse `main`. CSS global pode afetar o drawer. |
| **Largura do drawer** | `.inbox-drawer` tem `width: 850px` e `max-width: 95vw`. Em viewports pequenos ou com zoom, o cálculo pode falhar. |
| **Falta de word-break** | Textos longos sem espaços podem não quebrar. O textarea pode precisar de `word-break: break-word` ou `overflow-wrap: break-word`. |
| **Diferença entre navegadores** | Alguns navegadores podem tratar `min-width: 0` em flex de forma diferente. |

### 4.3. net::ERR_CONNECTION_RESET nas mídias

| Causa | Explicação |
|-------|------------|
| **Limite de conexões** | O navegador limita conexões simultâneas por domínio. Com muitas conversas e mídias, o limite pode ser atingido. |
| **Servidor** | O servidor pode estar fechando conexões, por timeout ou por configuração. |
| **Proxy/firewall** | Pode estar interrompendo conexões longas ou com muitos arquivos. |

---

## 5. Possíveis soluções (não implementadas)

### 5.1. Microfone some

| Solução | Descrição |
|---------|-----------|
| **readonly inicial** | Iniciar o campo com `readonly` e remover no primeiro `focus`. Campos readonly costumam não ser autofillados. |
| **Debounce no updateInboxSendMicVisibility** | Atrasar a atualização em alguns ms para ignorar eventos `input` de autofill. |
| **Verificar origem do input** | No handler de `input`, checar se o evento veio de `isTrusted` ou de outra forma de autofill. |
| **Isolar o Inbox em iframe** | O iframe teria próprio contexto de formulário, reduzindo chance de autofill. |
| **Usar contenteditable** | Substituir textarea por `div` com `contenteditable="true"` (mais complexo, menos sujeito a autofill). |
| **Logs de diagnóstico** | Adicionar logs temporários para rastrear quando e por que `updateInboxSendMicVisibility` e `setInboxAudioUI` são chamados. |

### 5.2. Campo explode horizontalmente

| Solução | Descrição |
|---------|-----------|
| **word-break** | Adicionar `word-break: break-word` ou `overflow-wrap: break-word` no textarea. |
| **max-width explícito** | Definir `max-width: 100%` ou `max-width` em pixels no textarea. |
| **Largura fixa no container** | Garantir que `.inbox-drawer-input` tenha `width` definido (ex.: `width: 100%`). |
| **!important** | Usar `!important` em regras críticas para evitar sobrescrita por CSS de outras páginas (não ideal, mas pode ser paliativo). |
| **Isolamento de escopo** | Usar `all: initial` ou `all: revert` em um wrapper do Inbox para reduzir conflitos de CSS. |
| **Cache busting** | Adicionar parâmetro de versão em assets para forçar atualização do cache. |

### 5.3. net::ERR_CONNECTION_RESET nas mídias

| Solução | Descrição |
|---------|-----------|
| **Lazy loading** | Carregar mídias apenas quando visíveis (Intersection Observer). |
| **Limitar conexões** | Fazer fila de carregamento de mídias com limite de conexões simultâneas. |
| **Proxy/CDN** | Servir mídias via CDN ou proxy com configuração adequada. |
| **Ajustes no servidor** | Revisar timeouts, keep-alive e limites de conexão no servidor. |

---

## 6. Passos de diagnóstico recomendados

1. **Confirmar versão em uso**  
   - Hard refresh (Ctrl+Shift+R) ou aba anônima.  
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

## 7. Referências de código

| Arquivo | Trechos relevantes |
|---------|--------------------|
| `views/layout/main.php` | ~277–281, ~356–360 (CSS flex), ~423–450 (CSS input), ~1671–1687 (HTML input), ~2028–2049 (setInboxAudioUI), ~2297–2316 (autoResize, updateInboxSendMicVisibility), ~2471–2482 (reflow, reset em loadInboxConversation) |
| `views/communication_hub/index.php` | ~3513–3533 (textarea e botões), ~3922–3931 (updateSendMicVisibility), ~929–941 (hub-text CSS) |

---

*Documento criado em 30/01/2026 para uso pela equipe de desenvolvimento.*
