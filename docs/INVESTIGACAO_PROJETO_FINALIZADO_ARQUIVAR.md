# Investigação: Projeto Finalizado — Marcar como Concluído e Arquivar

**Cenário:** Projeto "Cartão de Visita Profissional - Falcon Securitizadora Sa" foi finalizado. O usuário precisa marcá-lo como concluído e arquivá-lo.

**Objetivo:** Avaliar se o sistema está alinhado com o mercado e qual o caminho mais fácil para o usuário. **Apenas investigação — sem implementações.**

---

## 1. O que o PixelHub oferece hoje

### 1.1 Ação de arquivar

- **Onde:** Na **lista de projetos** (`/projects`), coluna "Ações"
- **Botão:** "Arquivar" (vermelho, `btn-danger`)
- **Condição:** Só aparece quando `status === 'ativo'`
- **Fluxo:** Clique → confirmação "Tem certeza que deseja arquivar o projeto?" → POST `/projects/archive` → status vira `arquivado`
- **Efeito:** Projeto some da lista principal (filtro padrão = Ativo) e passa a aparecer só com filtro "Arquivado"

### 1.2 Onde NÃO há ação de arquivar

- **Página de detalhes do projeto** (`/projects/show?id=X`): não há botão Arquivar
- **Quadro Kanban** (`/projects/board`): não há ação de arquivar o projeto

### 1.3 Semântica

- **"Arquivar"** no PixelHub = marcar projeto como finalizado e ocultá-lo da vista principal
- Não existe ação separada "Concluir" e "Arquivar" — arquivar é o equivalente a concluir para projetos

---

## 2. Sistemas profissionais — como tratam projetos finalizados

### 2.1 Asana

- Projetos não têm "Marcar como concluído"
- **Arquivar = concluir:** menu (⋯) ao lado do projeto → "Archive"
- Projeto arquivado some da lista principal
- Pode ser desarquivado depois

### 2.2 Trello

- **Cards:** checkbox "Mark complete" → depois "Archive" no hover
- **Boards:** menu do board → "Close board" (arquiva o board inteiro)
- Arquivar é ação explícita, separada de "concluir" em cards

### 2.3 Basecamp

- Projetos podem ser arquivados
- Arquivar = "pack away" — projeto some da lista principal
- Acesso a arquivados via seção dedicada

### 2.4 ClickUp

- Tarefas: menu (⋯) → "Archive"
- Projetos: fluxo similar — arquivar = ocultar da vista principal
- Arquivados continuam acessíveis por filtro

### 2.5 Padrão de mercado

| Aspecto | Padrão | PixelHub |
|---------|--------|----------|
| Termo usado | "Archive" ou "Close" | "Arquivar" ✅ |
| Onde arquivar | Lista e/ou detalhe do projeto | Só na lista |
| Reversível | Sim (desarquivar) | Sim (via Editar → status) |
| Confirmação | Sim, para evitar erro | Sim ✅ |
| Cor do botão | Neutra ou secundária | Vermelha (btn-danger) |

---

## 3. Pontos de atenção no PixelHub

### 3.1 Cor vermelha do botão "Arquivar"

- **Problema:** Vermelho costuma indicar ação destrutiva (ex.: excluir)
- **Risco:** Usuário pode achar que vai apagar o projeto
- **Mercado:** Asana e Trello usam opção em menu, sem botão vermelho em destaque

### 3.2 Arquivar só na lista

- **Problema:** Na tela de detalhes (`/projects/show`) não há como arquivar
- **Fluxo atual:** Usuário precisa voltar à lista → localizar o projeto → clicar em Arquivar
- **Mercado:** Em vários sistemas é possível arquivar tanto na lista quanto na tela de detalhes

### 3.3 Termo "Arquivar" vs "Concluir"

- **"Arquivar"** é tecnicamente correto e alinhado ao mercado
- **"Concluir"** ou **"Finalizar"** podem ser mais intuitivos para quem acabou de entregar o projeto
- Alternativas usadas no mercado: "Mark complete", "Close project", "Archive"

---

## 4. Caminho mais fácil para o usuário hoje

### 4.1 Fluxo atual (mínimo de cliques)

1. Acessar **Projetos & Tarefas** (`/projects`)
2. Garantir filtro **Status = Ativo** (padrão)
3. Localizar o projeto na tabela
4. Clicar em **"Arquivar"** na coluna Ações
5. Confirmar no diálogo

**Total:** 2 cliques (Arquivar + Confirmar), além da navegação até a lista.

### 4.2 Se o usuário estiver na tela de detalhes

1. Clicar em **"← Voltar"** (ou "Ver Todos os Projetos")
2. Localizar o projeto na lista
3. Clicar em **"Arquivar"**
4. Confirmar

**Total:** 3 cliques adicionais, pois não há Arquivar na tela de detalhes.

---

## 5. Respostas às perguntas

### "O que um sistema profissional teria de diferente?"

1. **Arquivar na tela de detalhes:** botão "Concluir" ou "Arquivar" na página do projeto
2. **Cor do botão:** evitar vermelho para arquivar; usar cor neutra ou secundária
3. **Termo opcional:** "Concluir e arquivar" ou "Finalizar projeto" para deixar claro que o projeto foi entregue
4. **Menu de contexto:** em alguns sistemas, Arquivar fica em menu (⋯) em vez de botão direto

### "Nosso sistema está de acordo já?"

**Em parte.**

- ✅ Arquivar existe e funciona
- ✅ É reversível (via Editar)
- ✅ Há confirmação
- ✅ Termo "Arquivar" é adequado
- ⚠️ Falta Arquivar na tela de detalhes
- ⚠️ Cor vermelha pode gerar dúvida (arquivar vs excluir)

### "Qual seria o caminho mais fácil para o usuário dar ok/concluído e arquivar?"

**Hoje:** Lista → Arquivar → Confirmar (2 cliques).

**Melhoria possível:** Incluir botão "Concluir e Arquivar" na tela de detalhes do projeto, para arquivar sem voltar à lista.

### "Está de acordo já?"

**Sim, para o fluxo via lista.** O sistema permite arquivar de forma adequada.

**Não, para o fluxo via detalhes.** Quem está vendo o projeto em `/projects/show` não tem como arquivar ali e precisa voltar à lista.

---

## 6. Resumo

| Aspecto | Status | Observação |
|---------|--------|------------|
| Funcionalidade de arquivar | ✅ | Implementada e funcional |
| Local (lista) | ✅ | Botão na coluna Ações |
| Local (detalhes) | ❌ | Sem botão de arquivar |
| Semântica (Arquivar = concluir) | ✅ | Alinhada ao mercado |
| Confirmação | ✅ | Diálogo antes de arquivar |
| Cor do botão | ⚠️ | Vermelho pode parecer exclusão |
| Reversibilidade | ✅ | Possível via Editar |

**Conclusão:** O sistema está adequado para arquivar projetos pela lista. Para ficar mais alinhado ao mercado e facilitar o uso, faria sentido adicionar a ação de arquivar na tela de detalhes do projeto e revisar a cor do botão.
