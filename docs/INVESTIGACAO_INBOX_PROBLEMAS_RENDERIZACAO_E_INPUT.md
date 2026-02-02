# Investigação: Problemas do Inbox (Renderização e Campo de Mensagem)

**Objetivo:** Registrar e diagnosticar os problemas reportados no Inbox, com causas prováveis e soluções recomendadas, **sem implementações** — apenas documentação para referência futura.

---

## Resumo dos problemas

| # | Problema | Status |
|---|----------|--------|
| 1 | Campo de envio cortado e sem microfone ao abrir o Inbox | Investigado |
| 2 | Microfone desaparece ao clicar no campo de mensagem | Investigado |
| 3 | Texto digitado não quebra linha e empurra elementos da tela | Investigado |

---

## Problema 1: Campo de envio cortado e sem microfone ao abrir

### Descrição
Ao abrir o Inbox pela primeira vez (sem refresh), a área de envio de mensagens aparece cortada ou incompleta: o campo de texto, o microfone e outros controles não são exibidos corretamente. Após dar refresh na página, o layout aparece normalmente.

### Causa provável
**Problema de layout/reflow no primeiro render:**

1. **Transição display:none → flex:** O painel de chat (`#inboxChat`) inicia com `style="display: none"`. Quando uma conversa é selecionada (ou restaurada do `sessionStorage` após 300ms), o JS define `chat.style.display = 'flex'`. O navegador pode não calcular corretamente o layout flex na primeira exibição (altura do container, `flex: 1` da área de mensagens, etc.).

2. **Estrutura flex em cascata:** O `.inbox-drawer-chat` tem `flex: 1`, `flex-direction: column`. Os filhos são: header (fixo), messages (flex: 1, overflow-y: auto), preview de mídia, UIs de áudio (recording/preview/sending) e `.inbox-drawer-input`. Se o pai `.inbox-drawer-body` não tiver altura definida no momento do primeiro paint, os cálculos de `flex: 1` podem falhar.

3. **Race condition:** `openInboxDrawer` chama `loadInboxConversations()` e, 300ms depois, `loadInboxConversation(savedThreadId, savedChannel)` se houver conversa salva. O chat passa de oculto a visível enquanto os dados ainda estão sendo carregados. O reflow pode ocorrer antes do conteúdo estar estável.

### Solução recomendada
- Garantir que o container pai (`.inbox-drawer`, `.inbox-drawer-body`) tenha altura explícita (ex.: `height: 100%` ou `min-height`) para o flex funcionar desde o primeiro render.
- Considerar `requestAnimationFrame` ou `setTimeout(0)` após `chat.style.display = 'flex'` para forçar um reflow antes de interações.
- Verificar se `overflow: hidden` em algum ancestral está cortando o conteúdo.

---

## Problema 2: Microfone desaparece ao clicar no campo de mensagem

### Descrição
O ícone do microfone aparece normalmente. Ao clicar no campo de mensagem para digitar, o microfone some e o botão "Enviar" pode aparecer no lugar, mesmo sem o usuário ter digitado nada.

### Causa provável
**Autocomplete/autofill do navegador:**

O campo está definido assim:

```html
<input type="text" id="inboxMessageInput" placeholder="Digite sua mensagem..." 
       onkeypress="handleInboxInputKeypress(event)" 
       oninput="updateInboxSendMicVisibility()">
```

- **Não há `autocomplete="off"`** — o navegador pode autopreencher o campo ao receber foco.
- Ao focar o input, o autofill pode inserir um valor (histórico, sugestões, etc.).
- Isso dispara o evento `input`.
- `updateInboxSendMicVisibility()` é chamada.
- A lógica usa `hasText = input.value.trim().length > 0` → `hasText = true`.
- O mic é ocultado e o botão Enviar é exibido:

```javascript
if (btnMic) btnMic.style.display = hasContent ? 'none' : 'block';
if (btnSend) btnSend.style.display = hasContent ? 'block' : 'none';
```

### Solução recomendada
- Adicionar `autocomplete="off"` no input de mensagem do Inbox para evitar autofill e o disparo indevido de `updateInboxSendMicVisibility`.

### Como confirmar
No console do navegador, antes de clicar no campo:

```javascript
document.getElementById('inboxMessageInput').addEventListener('input', () => 
  console.log('INPUT EVENT - value:', document.getElementById('inboxMessageInput').value));
```

Se o evento `input` disparar logo ao clicar (sem digitar), indica autofill.

---

## Problema 3: Texto não quebra linha e empurra elementos da tela

### Descrição
Ao digitar uma mensagem longa, o texto permanece em uma única linha, avançando horizontalmente e empurrando o microfone, o botão Enviar e outros elementos para fora da área visível. O campo não quebra linha como em aplicativos de chat (WhatsApp, etc.).

### Causa
**Uso de `<input type="text">` em vez de `<textarea>`:**

- O Inbox usa `<input type="text">`, que por definição HTML **não suporta múltiplas linhas**.
- O texto sempre fica em uma linha e se estende horizontalmente.
- O input tem `flex: 1` no container `.inbox-drawer-input`, então ele expande e pode empurrar os irmãos (anexo, mic, enviar) para fora da viewport.
- Não há `overflow` ou `text-overflow` que limite o crescimento horizontal de forma adequada.

**Comparação com o Painel de Comunicação:**

O Painel usa `<textarea>` com auto-resize:

```html
<textarea id="hubText" class="hub-text" rows="1" placeholder="Digite sua mensagem..." 
          oninput="autoResizeTextarea(this)"></textarea>
```

E a função `autoResizeTextarea`:

```javascript
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    const newHeight = Math.min(textarea.scrollHeight, 120);
    textarea.style.height = newHeight + 'px';
}
```

O textarea permite múltiplas linhas, quebra de linha natural e altura controlada (até 120px).

### Solução recomendada
1. **Trocar `<input type="text">` por `<textarea>`** no Inbox.
2. **Implementar auto-resize** equivalente ao Painel (ex.: `autoResizeTextarea` ou similar), com altura máxima (ex.: 120px) e `overflow-y: auto` além disso.
3. **Ajustar o handler de Enter:** No Painel, Enter sem Shift envia; Enter com Shift quebra linha. O Inbox hoje usa `handleInboxInputKeypress` que envia em Enter (sem Shift). Com textarea, Enter com Shift deve inserir quebra de linha; Enter sem Shift deve enviar.
4. **CSS:** Garantir `resize: none` no textarea (para controle manual da altura) e estilos compatíveis com o layout atual (bordas, padding, etc.).

### Considerações
- O `handleInboxInputKeypress` atual faz `e.preventDefault()` em Enter e chama `sendInboxMessage()`. Com textarea, será preciso diferenciar Enter (enviar) de Shift+Enter (quebra de linha).
- A função `updateInboxSendMicVisibility` e `sendInboxMessage` usam `input.value` — com textarea será `textarea.value`; a lógica permanece a mesma.

---

## Referências de código

| Arquivo | Trechos relevantes |
|---------|---------------------|
| `views/layout/main.php` | Linha 1664: input do Inbox; linhas 2284–2296: `updateInboxSendMicVisibility`; linhas 2586–2591: `handleInboxInputKeypress` |
| `views/communication_hub/index.php` | Linha 3513: textarea do Painel; linhas 5270–5273: `autoResizeTextarea` |

---

## Checklist para implementação futura

- [x] Problema 1: Revisar altura/overflow dos containers do Inbox; forçar reflow se necessário.
- [x] Problema 2: Adicionar `autocomplete="off"` no input/textarea de mensagem.
- [x] Problema 3: Trocar input por textarea; implementar auto-resize; ajustar Enter (enviar) vs Shift+Enter (quebra de linha).

---

*Documento criado em 29/01/2026. Sem implementações — apenas investigação e registro.*
