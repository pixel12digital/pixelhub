# Diagnóstico: Atualização em Tempo Real — Communication Hub

**Data:** 2026-01-09  
**Versão:** 1.0  
**Status:** 🔴 Em Análise

---

## Contexto Inicial (Problema Reportado)

Na Central de Comunicação (Communication Hub), ao abrir uma conversa específica (WhatsApp / thread), foi observado que:

### Sintomas

1. **Mensagens novas não aparecem automaticamente na UI**
   - Para enxergar mensagens recebidas, é necessário atualizar a página (F5/CTRL+F5)
   - Isso afeta diretamente a percepção de "tempo real" e cria uma experiência inferior ao padrão de CRMs e do próprio WhatsApp

2. **Scroll inicial incorreto**
   - Ao acessar uma conversa específica, o scroll do histórico abre "bem acima"
   - Comportamento esperado (padrão WhatsApp/CRM): ao entrar na conversa, o histórico deve posicionar automaticamente no final, mantendo a mensagem mais recente visível e a área de digitação pronta

### Importante

Após atualizar a página, as mensagens aparecem no histórico, o que sugere que:

- ✅ O gateway/webhook está funcionando
- ✅ O backend está persistindo corretamente
- 🔴 O problema está no comportamento "vivo" do frontend (atualização incremental em tempo real)

---

## Direção Definida (Abordagem Recomendada)

Para evitar inflar consultas ao banco e ainda entregar UX "tipo CRM", foi adotada uma estratégia progressiva:

### Fase 1 — Atualização incremental no envio

- ✅ Remover recarregamento de página após envio
- ✅ Manter mensagem otimista e depois confirmar/substituir com a mensagem real

### Fase 2 — Recebimento automático com polling inteligente e barato

- ✅ Check leve primeiro (para não carregar payload desnecessário)
- ✅ Buscar mensagens completas apenas quando houver novidade
- ✅ Pausar quando a aba estiver inativa e limpar o polling ao sair da thread
- ✅ Lógica de scroll profissional (auto-scroll só quando usuário está no final; badge quando não está)

### Fase 3 — Evolução futura (SSE)

- ✅ Estruturar funções de UI de forma que a fonte de atualização (polling ou SSE) seja substituível sem retrabalho

---

## Resumo do Que Foi Implementado

### Backend

**Endpoints incrementais:**

- ✅ `/communication-hub/messages/check` — verificar se há novas mensagens (leve, apenas boolean)
- ✅ `/communication-hub/messages/new` — trazer apenas mensagens novas após marcador
- ✅ `/communication-hub/message` — buscar mensagem específica para confirmação pós-envio

**Marcadores de continuidade:**

- ✅ `created_at` indexado + tie-breaker `event_id`

**Ajustes de segurança:**

- ✅ Validação opcional com `thread_id` no `getMessage` para garantir isolamento

**Otimização:**

- ✅ `checkNewMessages` reduzido para `LIMIT 20` e coerente com `getNewMessages`

### Frontend

- ✅ Remoção do reload da página no envio
- ✅ Substituição de mensagem otimista por confirmada
- ✅ Polling com Page Visibility (pausa em aba inativa)
- ✅ Dedupe via Set de IDs
- ✅ Gestão de scroll + badge de novas mensagens
- ✅ Estrutura preparada para SSE (abstração de `onNewMessages`)

---

## Etapa Atual: Testes Práticos e Achados Relevantes

Durante testes práticos com a thread aberta, foram notados dois sintomas consistentes:

### 1. Scroll inicial ao entrar na conversa

Ao acessar uma conversa específica, o scroll do histórico abre "bem acima".

**Comportamento esperado (padrão WhatsApp/CRM):** ao entrar na conversa, o histórico deve posicionar automaticamente no final, mantendo a mensagem mais recente visível e a área de digitação pronta.

### 2. Mensagens não chegam automaticamente na UI

As mensagens estão sendo recebidas e persistidas normalmente (aparecem ao recarregar a página).

Porém, com a conversa aberta, elas não entram na UI sem refresh.

### Evidência Objetiva Coletada

Na aba Network (DevTools), com a conversa aberta:

- ❌ **Não foi observado tráfego automático** (nenhuma chamada periódica visível no momento do teste)
- Isso reforça a hipótese de que, nessa view específica, o mecanismo de atualização incremental (polling/check/new) pode não estar sendo iniciado, ou está sendo interrompido/condicionado a algo que não acontece ao entrar na thread

---

## Diagnóstico Técnico

### Bug Crítico Identificado

**Arquivo:** `views/communication_hub/thread.php`  
**Função:** `checkForNewMessages()` (linha 287)

**Problema:**
```javascript
async function checkForNewMessages() {
    if (!ThreadState.isPageVisible) return;
    if (ThreadState.isChecking) return; // BLOQUEIA se já está checking
    
    ThreadState.isChecking = true; // MARCA como checking
    
    try {
        // ... lógica de check ...
    } catch (error) {
        console.error('Erro ao verificar novas mensagens:', error);
    }
    // ❌ FALTA: ThreadState.isChecking = false; nunca é resetado!
}
```

**Consequência:**
- Na primeira execução, `ThreadState.isChecking` é marcado como `true`
- Todas as execuções subsequentes são bloqueadas pela verificação `if (ThreadState.isChecking) return;`
- O polling fica travado após a primeira tentativa
- **Resultado:** Nenhuma chamada periódica ocorre, explicando a ausência de tráfego no Network

### Problema do Scroll Inicial

**Arquivo:** `views/communication_hub/thread.php`  
**Função:** `DOMContentLoaded` (linha 515)

**Problema Potencial:**
O scroll inicial é feito no `DOMContentLoaded`, mas pode ocorrer antes do container de mensagens estar totalmente renderizado ou com altura calculada corretamente.

---

## Conclusão Mais Provável

Como o backend está persistindo (mensagens aparecem após reload), o problema está concentrado no **"lifecycle" do frontend** na tela da thread:

1. **Bug crítico:** `ThreadState.isChecking` nunca é resetado, bloqueando todas as execuções após a primeira
2. **O polling pode não estar sendo disparado** ao entrar na conversa (thread view não reconhecida como ativa, ou inicialização não ocorrendo após render/navegação interna)
3. **O scroll inicial** pode não estar executando no momento correto (container ainda não renderizado)

---

## O Que Seria Importante Verificar (Sem Mudar Estratégia)

1. ✅ **Bug confirmado:** `ThreadState.isChecking` precisa ser resetado após cada execução (finally block)
2. ✅ **Inicialização:** Se, ao entrar na thread, existe efetivamente "atividade periódica" (check) e, ao chegar mensagem nova, ocorre a sequência "check detecta novidade → new traz delta → UI atualiza"
3. ✅ **Scroll inicial:** Se o comportamento de scroll desejado está alinhado ao padrão:
   - primeiro load da conversa: ir para o fim
   - durante uso: auto-scroll somente se o usuário estiver no fim; caso contrário, badge

---

## Critério de Validação Final (Para Considerar OK)

Abrir a thread e, **sem recarregar**:

- ✅ Mensagens recebidas devem aparecer automaticamente
- ✅ Envio não deve recarregar página
- ✅ Scroll deve iniciar no final e respeitar leitura do usuário
- ✅ Network deve mostrar check periódico e new apenas quando necessário (sem inflar banco)

---

## Soluções Propostas

### Correção 1: Reset de Flag de Checking

**Arquivo:** `views/communication_hub/thread.php`  
**Função:** `checkForNewMessages()`

Adicionar `finally` block para garantir reset:

```javascript
async function checkForNewMessages() {
    if (!ThreadState.isPageVisible) return;
    if (ThreadState.isChecking) return;
    
    ThreadState.isChecking = true;
    
    try {
        // ... lógica existente ...
    } catch (error) {
        console.error('Erro ao verificar novas mensagens:', error);
    } finally {
        ThreadState.isChecking = false; // ✅ RESET obrigatório
    }
}
```

### Correção 2: Scroll Inicial Melhorado

**Arquivo:** `views/communication_hub/thread.php`  
**Função:** `DOMContentLoaded`

Adicionar pequeno delay ou usar `requestAnimationFrame` para garantir renderização completa:

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // ... código existente ...
    
    // Auto-scroll inicial (aguarda renderização)
    requestAnimationFrame(() => {
        setTimeout(() => {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
                ThreadState.autoScroll = true;
            }
        }, 100);
    });
});
```

---

## Status

🔴 **Crítico:** Bug identificado que impede funcionamento do polling  
🟡 **Moderado:** Scroll inicial pode precisar ajuste de timing

**Próximo passo:** Aplicar correções e testar em ambiente de desenvolvimento.

