# Diagnóstico: Inbox — mensagens enviadas invisíveis + textarea estoura + microfone some

**Data:** 29/01/2026  
**Sintomas:** (1) mensagens enviadas não aparecem; (2) textarea/composer estoura para a direita; (3) microfone some (empurrado/clipado).

---

## 1. Causa raiz (1–2 frases)

O painel de chat (`.inbox-drawer-chat`) não tem `min-width: 0` nem `overflow: hidden`. Com `min-width: auto` (padrão em flex), o item flex não encolhe abaixo da largura intrínseca do conteúdo; o composer (textarea + botões) força o chat a crescer além do drawer, o `.inbox-drawer-body` com `overflow: hidden` corta o excesso à direita — onde ficam o microfone e as mensagens enviadas (últimas da lista).

---

## 2. Seletores/classes/arquivos envolvidos

| Elemento | Arquivo | Regra atual | Problema |
|----------|---------|-------------|---------|
| `.inbox-drawer-chat` | `views/layout/main.php` ~575 | `flex: 1; min-height: 0;` (sem `min-width`, sem `overflow`) | Item flex com `min-width: auto` não encolhe; conteúdo empurra para a direita |
| `.inbox-drawer-body` | `views/layout/main.php` ~335 | `overflow: hidden` | Corta o overflow do chat à direita (mic e mensagens sumindo) |
| `.inbox-drawer-input` | `views/layout/main.php` ~641 | `overflow: hidden; min-width: 0` | Já tem proteção, mas o pai (chat) não contém o fluxo |
| `.inbox-drawer-input textarea` | `views/layout/main.php` ~651 | `flex: 1; min-width: 0; width: 100%` | Já tem proteção |

**Cadeia de layout:**
```
.inbox-drawer (850px)
  └── .inbox-drawer-body (overflow: hidden)
        └── .inbox-drawer-chat (flex: 1, sem min-width: 0)  ← CAUSA
              ├── .inbox-drawer-messages (flex: 1)
              └── .inbox-drawer-input (attach | textarea | mic | send)
```

O chat cresce além do body → body corta à direita → mic e últimas mensagens ficam fora da área visível.

---

## 3. Relação com ajustes recentes (minimizar/maximizar/gutter/seta)

- **Gutter** (`body.inbox-minimized .container, .header { margin-right: 40px }`): não interfere no layout interno do Inbox; só reserva espaço quando minimizado.
- **Minimizado** (`.inbox-drawer.inbox--minimized`): esconde header e body; não afeta o composer quando o Inbox está aberto.
- **Seta** (`.inbox-chevron-handle`): posicionada com `transform` fora do fluxo; não altera larguras internas.

O problema vem da cadeia flex original (chat sem `min-width: 0`), não dos ajustes de minimizar/gutter/seta.

---

## 4. Mensagens enviadas invisíveis

- As mensagens enviadas são as últimas da lista (`.msg.outbound`).
- O chat crescendo para a direita + `overflow: hidden` no body faz com que a área visível seja deslocada/cortada.
- O scroll (`container.scrollTop = container.scrollHeight`) leva ao fim da lista, mas se o container de mensagens está sendo comprimido ou o layout está quebrado, as últimas bolhas podem ficar atrás do composer ou fora da área visível.
- Com a correção do chat (conter o overflow), o composer deixa de “estourar” e as mensagens voltam a ficar visíveis na área de scroll.

---

## 5. Correção mínima recomendada (apenas CSS)

Adicionar em `.inbox-drawer-chat`:

```css
.inbox-drawer-chat {
    flex: 1;
    min-height: 0;
    min-width: 0;        /* NOVO: permite encolher dentro do flex pai */
    overflow: hidden;     /* NOVO: contém overflow do composer/mensagens */
    display: flex;
    flex-direction: column;
    background: #f0f2f5;
}
```

**Arquivo:** `views/layout/main.php` (bloco de estilos do Inbox, ~575).

**Efeito esperado:**
- O chat passa a respeitar a largura do drawer.
- O composer (textarea + mic + enviar) deixa de estourar.
- O microfone permanece visível.
- As mensagens enviadas ficam visíveis na área de scroll.

---

## 6. Checklist de validação

- [ ] Inbox aberto (lista + chat): composer não estoura, mic visível, mensagens enviadas visíveis.
- [ ] Inbox minimizado: comportamento inalterado (só alça visível).
- [ ] Mobile (chat-open): layout responsivo continua correto.
- [ ] Troca de conversa: composer e mic permanecem corretos.
