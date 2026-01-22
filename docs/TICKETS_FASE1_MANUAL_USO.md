# Manual de Uso - Módulo de Tickets (Fase 1)

**Data:** 2025-01-25

## Como Criar Tickets

### Pontos de Entrada

Existem **3 formas principais** de criar um novo ticket:

#### 1. Na Tela de Clientes
- Acesse a tela de detalhes de um cliente (`/tenants/view?id=X`)
- Clique no botão **"🎫 Novo Ticket"** no topo da página
- O cliente já estará pré-selecionado no formulário

#### 2. Na Tela de Projetos
- Acesse a listagem de projetos (`/projects`)
- Para projetos que têm cliente vinculado, aparecerá o botão **"🎫 Abrir ticket"**
- Clique no botão e o projeto e cliente já estarão pré-selecionados

#### 3. No Quadro de Tarefas
- Acesse o Quadro de Tarefas (`/projects/board`)
- No topo da página, ao lado do botão "Nova tarefa", há o botão **"🎫 Novo Ticket"**
- Se o quadro estiver filtrado por um projeto, o projeto será pré-selecionado
- Se o projeto tiver cliente vinculado, o cliente também será pré-selecionado

### Formulário de Criação

O formulário de criação permite:
- **Título** (obrigatório) - máximo 200 caracteres
- **Descrição** (opcional) - texto livre
- **Cliente** (obrigatório) - deve selecionar um cliente
- **Projeto** (opcional) - pode deixar sem projeto
- **Prioridade** - Baixa, Média, Alta, Crítica (padrão: Média)
- **Origem** - Cliente, Interno, WhatsApp, Automático (padrão: Cliente)

## Como Criar Tarefa a Partir de um Ticket

### Passo a Passo

1. **Acesse o ticket** - Vá para `/tickets/show?id=X` ou clique em "Ver Detalhes" na listagem de tickets

2. **Verifique se o ticket tem projeto vinculado**
   - Se o ticket **não tiver projeto**, você precisará editá-lo primeiro e vincular um projeto
   - Tarefas precisam estar vinculadas a um projeto

3. **Crie a tarefa**
   - Se o ticket **já tiver tarefa vinculada**: aparecerá o botão "Ver Tarefa no Kanban"
   - Se o ticket **não tiver tarefa**: aparecerá o botão "Criar Tarefa para este Ticket"
   - Clique no botão "Criar Tarefa para este Ticket"

4. **A tarefa será criada automaticamente com:**
   - Título: `[Ticket #ID] {título do ticket}`
   - Descrição: copiada do ticket
   - Tipo: `client_ticket` (aparecerá com badge `[TCK]` no quadro)
   - Status inicial: `em_andamento`
   - Projeto: mesmo projeto do ticket

5. **Você será redirecionado** para o Quadro de Tarefas, já com a tarefa em foco

## Como Ver o Ticket no Quadro e na Agenda

### No Quadro de Tarefas

1. **Tarefas de ticket** aparecem com o badge **`[TCK]`** (laranja) no card da tarefa
2. **Ao clicar na tarefa**, o modal de detalhes exibe:
   - Tipo: "Ticket / Problema de cliente"
   - Seção "Tickets Relacionados" com link direto para o ticket

### Na Agenda

1. **Tarefas de ticket** são automaticamente direcionadas para blocos específicos:
   - Prioridade **Alta/Crítica** → Bloco **CLIENTES**
   - Prioridade **Baixa/Média** → Bloco **SUPORTE**

2. **No modo de trabalho do bloco** (`/agenda/bloco?id=X`):
   - Você pode ver e alterar o status das tarefas de ticket
   - A mudança de status sincroniza automaticamente com o ticket

## Como Funcionam os Anexos

### Anexos de Tickets (via Tarefa)

**Importante:** Tickets não têm sistema próprio de anexos. Os anexos são gerenciados através da tarefa vinculada.

### Para Anexar Arquivos a um Ticket

1. **Crie uma tarefa para o ticket** (se ainda não tiver)
   - Veja seção "Como Criar Tarefa a Partir de um Ticket" acima

2. **Acesse a tarefa no Quadro de Tarefas**
   - Clique na tarefa para abrir o modal de detalhes
   - Role até a seção "Anexos da Tarefa"

3. **Faça upload dos arquivos**
   - Use o formulário de upload na seção de anexos
   - Você pode anexar: imagens, prints, vídeos, documentos, etc.

4. **Visualize os anexos no ticket**
   - Volte para a tela de detalhes do ticket (`/tickets/show?id=X`)
   - Na seção "Anexos", você verá todos os arquivos anexados à tarefa
   - Clique em "Gerenciar Anexos na Tarefa" para adicionar mais arquivos

### Tipos de Anexos Suportados

- **Imagens** (JPG, PNG, GIF, etc.)
- **Documentos** (PDF, DOC, DOCX, etc.)
- **Vídeos** (incluindo gravações de tela)
- **Outros arquivos** (qualquer tipo)

## Como Funciona a Sincronização de Status

### Ticket ↔ Tarefa

A sincronização é **automática e bidirecional**:

#### Quando você conclui uma tarefa:
- Se a tarefa estiver vinculada a um ticket
- E o ticket estiver em status: `aberto`, `em_atendimento` ou `aguardando_cliente`
- O ticket é **automaticamente marcado como `resolvido`**
- A data de resolução é preenchida automaticamente

#### Quando você resolve/cancela um ticket:
- Se o ticket tiver uma tarefa vinculada
- A tarefa é **automaticamente marcada como `concluída`**
- Os campos `completed_at` e `completed_by` são preenchidos

### Onde a Sincronização Funciona

A sincronização funciona em **todos os pontos** onde o status é alterado:

1. **Quadro de Tarefas** - Ao alterar status via select no card ou modal
2. **Agenda** - Ao alterar status no modo de trabalho do bloco
3. **Tela de Ticket** - Ao editar e alterar o status do ticket

## Como Editar um Ticket

### Passo a Passo

1. **Acesse o ticket** - Vá para `/tickets/show?id=X`

2. **Clique no botão "Editar Ticket"** no rodapé da página

3. **Edite os campos desejados:**
   - Título
   - Descrição
   - Projeto (pode ser alterado)
   - Prioridade
   - Origem
   - **Status** (disponível apenas na edição)

4. **Importante:**
   - O **cliente não pode ser alterado** após a criação do ticket
   - Ao alterar o status para `resolvido` ou `cancelado`, a tarefa vinculada será automaticamente concluída

5. **Clique em "Salvar Alterações"**

## Fluxo Completo de Trabalho

### Cenário: Cliente reporta um problema

1. **Criar o ticket**
   - Acesse a tela do cliente
   - Clique em "🎫 Novo Ticket"
   - Preencha título, descrição, prioridade
   - Salve

2. **Criar tarefa para trabalhar**
   - Abra o ticket recém-criado
   - Se necessário, edite e vincule um projeto
   - Clique em "Criar Tarefa para este Ticket"
   - Você será redirecionado para o Quadro de Tarefas

3. **Agendar na Agenda (opcional)**
   - No Quadro de Tarefas, clique na tarefa
   - Clique em "Agendar na Agenda"
   - Selecione um bloco (será direcionado para CLIENTES ou SUPORTE conforme prioridade)

4. **Trabalhar no bloco**
   - Acesse o bloco na Agenda (`/agenda/bloco?id=X`)
   - Veja a tarefa na lista de "Tarefas do Bloco"
   - Altere o status conforme progride
   - Anexe arquivos se necessário (via tarefa)

5. **Concluir**
   - Quando terminar, marque a tarefa como "Concluída"
   - O ticket será automaticamente marcado como "Resolvido"
   - Ou marque o ticket como "Resolvido" diretamente
   - A tarefa será automaticamente concluída

## Dicas e Boas Práticas

### Organização

- **Use projetos** para agrupar tickets relacionados ao mesmo trabalho maior
- **Tickets sem projeto** são úteis para chamados pontuais e isolados
- **Prioridade alta/crítica** direciona para bloco CLIENTES (mais visível)
- **Prioridade baixa/média** direciona para bloco SUPORTE

### Rastreabilidade

- **Sempre crie tarefa** para tickets que precisam ser trabalhados na Agenda
- **Use anexos** para documentar evidências (prints, vídeos, logs)
- **Acompanhe o status** tanto no ticket quanto na tarefa (eles sincronizam)

### Fluxo Recomendado

1. Cliente reporta problema → Criar ticket
2. Ticket criado → Criar tarefa vinculada
3. Tarefa criada → Agendar na Agenda (bloco CLIENTES ou SUPORTE)
4. Trabalhar no bloco → Alterar status, anexar arquivos
5. Concluir → Marcar tarefa como concluída (ticket resolve automaticamente)

## Limitações da Fase 1

### O que ainda não está disponível:

- ❌ Sistema de comentários/mensagens no ticket
- ❌ Histórico de mudanças do ticket
- ❌ Anexos diretos ao ticket (sempre via tarefa)
- ❌ Notificações automáticas
- ❌ Dashboard de métricas de tickets

### O que está disponível:

- ✅ CRUD completo de tickets
- ✅ Criação de tarefa a partir de ticket
- ✅ Sincronização automática de status
- ✅ Anexos via tarefa vinculada
- ✅ Edição de tickets
- ✅ Integração com Agenda e Quadro de Tarefas
- ✅ Múltiplos pontos de entrada para criar tickets

## Resolução de Problemas

### "Ticket precisa estar vinculado a um projeto para criar tarefa"

**Solução:** Edite o ticket e vincule um projeto antes de criar a tarefa.

### "Não consigo anexar arquivos no ticket"

**Solução:** Crie uma tarefa para o ticket primeiro. Os anexos são gerenciados através da tarefa.

### "Status do ticket e da tarefa estão diferentes"

**Solução:** Isso não deveria acontecer com a sincronização automática. Se acontecer:
1. Verifique se a tarefa está realmente vinculada ao ticket (campo `task_id` no ticket)
2. Altere o status em um dos lados (ticket ou tarefa) e a sincronização deve ocorrer automaticamente

### "Não vejo o botão de criar ticket"

**Solução:** Verifique se você está em uma das telas que tem o botão:
- Tela de detalhes do cliente
- Listagem de projetos (apenas projetos com cliente)
- Quadro de Tarefas

## Fase 2 — Novas Funcionalidades

### Como Identificar Tickets na Agenda

**No Modo de Trabalho do Bloco:**
- Tarefas vinculadas a tickets aparecem com o badge **🎫 TCK-#ID** ao lado do título
- Ao passar o mouse sobre o badge, você vê o título e status do ticket
- Clique no badge para abrir a tela do ticket

**Na Agenda Diária:**
- Os tickets aparecem indiretamente através das tarefas vinculadas
- Tarefas de ticket são direcionadas automaticamente para blocos CLIENTES ou SUPORTE conforme a prioridade

### Como Criar Ticket a Partir de uma Tarefa

**Passo a Passo:**

1. **Acesse a tarefa** - No Quadro de Tarefas, clique em uma tarefa para abrir o modal de detalhes

2. **Verifique se já há tickets vinculados**
   - Se houver tickets, eles aparecerão na seção "Tickets Relacionados"
   - Se não houver, aparecerá o botão "Criar ticket a partir desta tarefa"

3. **Clique no botão** "Criar ticket a partir desta tarefa"

4. **Preencha o formulário**
   - O título será sugerido como `[Suporte] {título da tarefa}`
   - A descrição incluirá automaticamente o contexto da tarefa
   - Cliente e projeto serão pré-selecionados se a tarefa tiver essas informações

5. **Salve o ticket**
   - O ticket será criado e automaticamente vinculado à tarefa
   - Você será redirecionado para a tela de detalhes do ticket

### Como Agendar Ticket na Agenda (a partir do ticket)

**Passo a Passo:**

1. **Acesse o ticket** - Vá para `/tickets/show?id=X`

2. **Verifique a seção "Blocos de Agenda relacionados"**
   - Se o ticket já tiver tarefa vinculada e estiver agendado, você verá todos os blocos onde a tarefa está agendada
   - Cada bloco mostra: data, horário, tipo de bloco e status
   - Clique em "Abrir bloco" para ver detalhes do bloco

3. **Se não houver blocos agendados:**
   - Se o ticket **já tiver tarefa vinculada**, aparecerá o botão "Agendar tarefa na Agenda"
   - Clique no botão para abrir o modal de agendamento no Quadro de Tarefas
   - Selecione um bloco disponível e vincule a tarefa

4. **Se o ticket não tiver tarefa:**
   - Primeiro crie uma tarefa para o ticket (botão "Criar Tarefa para este Ticket")
   - Depois use o botão "Agendar tarefa na Agenda"

### Fluxo Completo: Tarefa → Ticket → Agenda

**Cenário: Você está trabalhando em uma tarefa e precisa criar um ticket de suporte**

1. **No Quadro de Tarefas:**
   - Abra a tarefa no modal de detalhes
   - Clique em "Criar ticket a partir desta tarefa"
   - Preencha e salve o ticket

2. **No Ticket criado:**
   - O ticket já estará vinculado à tarefa
   - Use o botão "Agendar tarefa na Agenda" para agendar
   - Ou vá para o Quadro de Tarefas e agende normalmente

3. **Na Agenda:**
   - A tarefa aparecerá nos blocos agendados com o badge **🎫 TCK-#ID**
   - Você pode trabalhar na tarefa normalmente
   - Mudanças de status sincronizam automaticamente com o ticket

### Visualização de Blocos Relacionados

**Na tela do ticket (`/tickets/show?id=X`):**

A seção "Blocos de Agenda relacionados" mostra:
- **Data formatada** (ex: 25/01/2025)
- **Horário planejado** (ex: 09:00 – 11:00)
- **Tipo de bloco** (ex: CLIENTES, SUPORTE) com cor identificadora
- **Status do bloco** (Planejado, Em Andamento, Concluído, etc.)
- **Link "Abrir bloco"** para acessar o modo de trabalho do bloco

**Casos especiais:**
- Se não houver blocos: mensagem "Este ticket ainda não está agendado em nenhum bloco da Agenda"
- Se não houver tarefa: mensagem "Crie uma tarefa para este ticket para poder agendá-la na Agenda"

---

**Última atualização:** 2025-01-25 (Fase 2)

