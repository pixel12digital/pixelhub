# Auditoria Técnica: Gravação de Telas no PixelHub

**Data do Relatório:** 25/01/2025  
**Desenvolvedor Responsável:** Assistente AI (Auto)  
**Objetivo:** Preparar implementação de funcionalidade de gravação de tela (screen recording) com áudio opcional, integrada ao fluxo de tarefas

---

## 📋 Sumário Executivo

Esta auditoria mapeia a arquitetura do PixelHub para identificar os melhores pontos de integração de uma funcionalidade de gravação de tela similar ao ClickUp. O sistema já possui infraestrutura de uploads e anexos de tarefas, o que facilita a implementação.

**Principais Descobertas:**
- ✅ Sistema já possui tabela `task_attachments` e controller dedicado
- ✅ Infraestrutura de uploads funcionando (limite: 200MB por arquivo)
- ✅ Modal de detalhes da tarefa já exibe lista de anexos
- ✅ Estrutura de armazenamento organizada por tarefa (`storage/tasks/{taskId}/`)
- ⚠️ Necessário adicionar suporte para vídeos WebM/MP4 na validação
- ⚠️ Necessário criar componente JavaScript para gravação de tela

---

## 1. Arquitetura Geral do Projeto

### 1.1. Back-end

**Linguagem e Framework:**
- **PHP 8.0+** (puro, sem frameworks externos)
- **Padrão:** MVC simplificado (PSR-4)
- **Autoload:** Composer ou manual (spl_autoload_register)

**Estrutura de Organização:**
```
src/
├── Core/              # Classes core (Router, Auth, DB, Storage, etc.)
├── Controllers/       # Controllers MVC (TaskBoardController, TaskAttachmentsController, etc.)
├── Services/          # Lógica de negócio (TaskService, ProjectService, etc.)
└── Models/            # Models (vazio - acesso direto via Services)
```

**Padrão de Rotas:**
- Definidas em `public/index.php` (linhas 140-283)
- Router simples com suporte a parâmetros dinâmicos `{id}`
- Handlers: `Controller@method` ou `Closure`

**Banco de Dados:**
- **SGBD:** MySQL/MariaDB
- **Conexão:** PDO (singleton via `DB::getConnection()`)
- **Migrations:** Sistema próprio em `database/migrations/`
- **Charset:** utf8mb4

### 1.2. Front-end

**Tecnologias:**
- **HTML5 + PHP** (templates PHP com output buffering)
- **JavaScript Vanilla** (sem frameworks - jQuery não encontrado)
- **CSS3** inline e em `<style>` tags

**Organização de Assets:**
- **JavaScript:** Inline nas views (principalmente em `views/tasks/board.php`)
- **CSS:** Inline nas views ou em `<style>` tags
- **Não há sistema de build** (Webpack, Vite, Gulp, etc.)
- **Arquivos estáticos:** Diretos, sem minificação

**Estrutura de Views:**
```
views/
├── layout/
│   ├── main.php       # Layout principal (master)
│   └── auth.php       # Layout de autenticação
├── tasks/
│   ├── board.php      # Quadro Kanban (contém modal de detalhes)
│   └── _task_card.php # Partial: card de tarefa
├── partials/
│   └── task_attachments_table.php  # Tabela de anexos
└── [outros módulos]/
```

**Padrão de Renderização:**
- Controllers usam `$this->view('nome.view', $data)`
- Notação com ponto: `tasks.board` → `views/tasks/board.php`
- Partials incluídos via `require` ou `include`

---

## 2. Módulo de Tarefas / Kanban

### 2.1. Estrutura de Arquivos

| Componente | Arquivo | Responsabilidade |
|------------|---------|------------------|
| **Controller** | `src/Controllers/TaskBoardController.php` | Gerencia quadro Kanban e operações de tarefas |
| **Service** | `src/Services/TaskService.php` | Lógica de negócio de tarefas |
| **View Principal** | `views/tasks/board.php` | Renderiza Kanban e modal de detalhes |
| **Partial Card** | `views/tasks/_task_card.php` | Card individual de tarefa no Kanban |

### 2.2. Rotas Relacionadas a Tarefas

| Método | Rota | Controller | Método | Descrição |
|--------|------|------------|--------|-----------|
| GET | `/projects/board` | TaskBoardController | `board()` | Exibe quadro Kanban |
| POST | `/tasks/store` | TaskBoardController | `store()` | Cria nova tarefa |
| POST | `/tasks/update` | TaskBoardController | `update()` | Atualiza tarefa |
| POST | `/tasks/move` | TaskBoardController | `move()` | Move tarefa entre colunas |
| GET | `/tasks/{id}` | TaskBoardController | `show()` | Retorna dados da tarefa em JSON |

### 2.3. Estrutura de Banco de Dados - Tabela `tasks`

**Arquivo de Migration:** `database/migrations/20251123_create_tasks_table.php`

**Campos Principais:**
```sql
CREATE TABLE tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,           -- FK para projects
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'backlog',  -- backlog, em_andamento, aguardando_cliente, concluida
    `order` INT NOT NULL DEFAULT 0,             -- Ordem dentro da coluna
    assignee VARCHAR(150) NULL,                  -- Nome/email do responsável
    due_date DATE NULL,
    created_by INT UNSIGNED NULL,               -- FK para users
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    -- Índices e Foreign Keys
    INDEX idx_project_id (project_id),
    INDEX idx_status_project_order (status, project_id, `order`),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
)
```

**Status Possíveis:**
- `backlog`
- `em_andamento`
- `aguardando_cliente`
- `concluida`

### 2.4. Modal de Detalhes da Tarefa

**Localização:** Renderizado dinamicamente via JavaScript em `views/tasks/board.php`

**Estrutura HTML:**
```html
<div id="taskDetailModal" class="modal task-details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="taskDetailTitle">Detalhes da Tarefa</h3>
            <button class="close" id="btn-close-task-detail-modal">&times;</button>
        </div>
        <div id="taskDetailContent">
            <!-- Conteúdo injetado dinamicamente -->
        </div>
    </div>
</div>
```

**Função JavaScript Principal:**
- `openTaskDetail(taskId)` - Abre modal e carrega dados via AJAX
- `renderTaskDetailModal(data, taskId, isEditing)` - Renderiza conteúdo do modal

**Fluxo de Abertura:**
1. Usuário clica em card de tarefa no Kanban
2. `openTaskDetail(taskId)` é chamada
3. Requisição AJAX para `GET /tasks/{id}` (retorna JSON)
4. `renderTaskDetailModal()` monta HTML com:
   - Informações da tarefa (título, descrição, status, datas)
   - Checklist (se houver)
   - **Seção de Anexos** (linhas 896-947 de `board.php`)
   - Formulário de upload de anexos

**Seção de Anexos no Modal:**
- Localização: Linhas 896-947 de `views/tasks/board.php`
- Exibe tabela com anexos existentes (se houver)
- Formulário de upload: `#task-attachments-container`
- Função de upload: `uploadTaskAttachment(taskId)`

---

## 3. Sistema Atual de Uploads / Arquivos / Backups

### 3.1. Anexos de Tarefas (`task_attachments`)

**Controller:** `src/Controllers/TaskAttachmentsController.php`

**Rotas:**
| Método | Rota | Método Controller | Descrição |
|--------|------|------------------|-----------|
| POST | `/tasks/attachments/upload` | `upload()` | Processa upload de anexo |
| GET | `/tasks/attachments/list` | `list()` | Lista anexos (retorna HTML) |
| GET | `/tasks/attachments/download` | `download()` | Download de anexo |
| POST | `/tasks/attachments/delete` | `delete()` | Exclui anexo |

**Tabela `task_attachments`:**
```sql
CREATE TABLE task_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL,                -- FK opcional (herdado do projeto)
    task_id INT UNSIGNED NOT NULL,               -- FK para tasks
    file_name VARCHAR(255) NOT NULL,             -- Nome seguro gerado
    original_name VARCHAR(255) NOT NULL,         -- Nome original do arquivo
    file_path VARCHAR(500) NOT NULL,             -- Caminho relativo (ex: /storage/tasks/1/arquivo.pdf)
    file_size BIGINT UNSIGNED NULL,               -- Tamanho em bytes
    mime_type VARCHAR(100) NULL,                 -- Tipo MIME
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT UNSIGNED NULL,               -- FK para users
    -- Índices e Foreign Keys
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_task_id (task_id),
    INDEX idx_uploaded_at (uploaded_at),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
)
```

**Fluxo de Upload:**
1. Front-end envia `FormData` com `task_id` e `file` via AJAX
2. `TaskAttachmentsController::upload()` valida:
   - Autenticação (requer usuário interno)
   - `task_id` válido
   - Arquivo presente em `$_FILES['file']`
   - Extensão permitida (lista definida no controller)
   - Tamanho máximo: **200MB**
3. Arquivo salvo em: `storage/tasks/{taskId}/{safeFileName}`
4. Registro criado em `task_attachments` com caminho relativo
5. Retorna JSON com HTML atualizado da tabela de anexos

**Extensões Permitidas (atual):**
```php
['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'rar', '7z', 'tar', 'gz', 'sql', 'mp4']
```
**Nota:** `mp4` já está permitido, mas `webm` (formato comum de gravação de tela) **não está**.

**Armazenamento Físico:**
- **Diretório base:** `storage/tasks/{taskId}/`
- **Helper:** `Storage::getTaskAttachmentsDir($taskId)`
- **Caminho relativo salvo no banco:** `/storage/tasks/{taskId}/{safeFileName}`
- **Caminho absoluto:** `__DIR__ . '/../../storage/tasks/{taskId}/{safeFileName}'`

**Validações:**
- Tamanho máximo: **200MB** (definido no controller)
- Extensão validada contra lista permitida
- Nome de arquivo sanitizado via `Storage::generateSafeFileName()`
- Verificação de permissões de escrita no diretório

### 3.2. Backups de Hospedagem (`hosting_backups`)

**Controller:** `src/Controllers/HostingBackupController.php`

**Características:**
- Suporta upload em chunks (arquivos grandes até 2GB)
- Armazena em: `storage/tenants/{tenantId}/backups/{hostingAccountId}/`
- Tabela: `hosting_backups` (campos: `file_name`, `file_path`, `file_size`, `type`, etc.)
- **Não é relevante para gravação de tela** (contexto diferente)

### 3.3. Documentos de Tenants (`tenant_documents`)

**Controller:** `src/Controllers/TenantDocumentsController.php`

**Características:**
- Armazena documentos gerais de clientes
- Diretório: `storage/tenants/{tenantId}/docs/`
- **Não é relevante para gravação de tela** (contexto diferente)

### 3.4. Classe `Storage` (Helper)

**Arquivo:** `src/Core/Storage.php`

**Métodos Úteis:**
```php
Storage::getTaskAttachmentsDir(int $taskId): string
Storage::ensureDirExists(string $path): void
Storage::generateSafeFileName(string $originalName): string
Storage::formatFileSize(int $bytes): string
Storage::fileExists(string $storedPath): bool
```

---

## 4. Pontos de Integração para Gravação de Tela

### 4.1. Front-end: Onde Adicionar o Botão "Gravar Tela"

**Localização Recomendada:** Dentro do modal de detalhes da tarefa, na seção de anexos

**Arquivo:** `views/tasks/board.php` (função `renderTaskDetailModal()`)

**Posição Sugerida:**
- **Linha ~940** (após o formulário de upload de arquivos)
- Adicionar botão ao lado do botão "Enviar Arquivo"
- Ou criar uma seção separada "Gravações de Tela" acima da seção de anexos

**Estrutura Proposta:**
```html
<div class="task-screen-recordings-section" style="margin-top: 24px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
    <h4 style="margin-bottom: 15px; color: #023A8D;">Gravações de Tela</h4>
    <div id="task-screen-recordings-container">
        <!-- Lista de vídeos gravados -->
    </div>
    <button id="btn-start-screen-recording" class="btn btn-primary">
        🎥 Gravar Tela
    </button>
</div>
```

### 4.2. Front-end: Componente JavaScript de Gravação

**Arquivo Recomendado:** Criar novo arquivo `public/assets/js/screen-recorder.js` ou adicionar inline em `views/tasks/board.php`

**Funcionalidades Necessárias:**
1. **Iniciar gravação:**
   - Solicitar permissão de tela via `navigator.mediaDevices.getDisplayMedia()`
   - Opção de incluir áudio do sistema e/ou microfone
   - Iniciar `MediaRecorder` com codec apropriado (WebM/VP9 ou H.264)

2. **Controles durante gravação:**
   - Botão "Pausar/Retomar"
   - Botão "Parar"
   - Indicador visual de gravação (contador de tempo, ícone pulsante)

3. **Finalizar e enviar:**
   - Preview do vídeo gravado
   - Campo opcional para nome/comentário
   - Upload via `FormData` para endpoint do backend

**API do Navegador:**
- `navigator.mediaDevices.getDisplayMedia()` - Captura de tela
- `MediaRecorder` - Gravação de stream
- Formatos suportados: WebM (Chrome/Firefox), MP4 (Safari com limitações)

### 4.3. Back-end: Endpoint para Upload de Vídeo

**Controller Recomendado:** Reaproveitar `TaskAttachmentsController` ou criar método específico

**Rota Sugerida:**
```
POST /tasks/screen-recordings/upload
```

**Alternativa (mais simples):**
- Adicionar extensão `webm` à lista de extensões permitidas em `TaskAttachmentsController::upload()`
- Usar rota existente: `POST /tasks/attachments/upload`
- Diferenciar por `mime_type` ou campo adicional na tabela

**Validações Necessárias:**
- Tipo MIME: `video/webm`, `video/mp4`, `video/x-matroska`
- Tamanho máximo: **200MB** (ou aumentar para vídeos, ex: 500MB)
- Duração máxima sugerida: 10-15 minutos (validar no front-end)

### 4.4. Back-end: Estrutura de Dados

**Opção A: Reaproveitar `task_attachments` (Recomendado)**

**Vantagens:**
- ✅ Já existe infraestrutura completa
- ✅ Lista de anexos já exibe vídeos
- ✅ Menos código para implementar

**Modificações Necessárias:**
1. Adicionar campo `recording_type` (opcional) para diferenciar:
   - `NULL` ou `'file'` = anexo normal
   - `'screen_recording'` = gravação de tela
2. Adicionar campo `duration` (INT, segundos) para duração do vídeo
3. Adicionar extensão `webm` à lista de extensões permitidas

**Opção B: Criar Tabela Dedicada `task_screen_recordings`**

**Estrutura Proposta:**
```sql
CREATE TABLE task_screen_recordings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT UNSIGNED NULL,
    mime_type VARCHAR(100) NULL,
    duration INT UNSIGNED NULL,                 -- Duração em segundos
    has_audio TINYINT(1) DEFAULT 0,             -- 1 = com áudio, 0 = sem áudio
    recording_title VARCHAR(200) NULL,            -- Título/comentário opcional
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT UNSIGNED NULL,
    INDEX idx_task_id (task_id),
    INDEX idx_uploaded_at (uploaded_at),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
)
```

**Vantagens:**
- ✅ Separação clara de responsabilidades
- ✅ Campos específicos para gravações (duration, has_audio, title)
- ✅ Facilita queries específicas (ex: "listar apenas gravações")

**Desvantagens:**
- ⚠️ Duplicação de código (controller, views, rotas)
- ⚠️ Mais complexidade de manutenção

**Recomendação:** **Opção A** (reaproveitar `task_attachments` com campo adicional)

### 4.5. Armazenamento Físico

**Diretório Recomendado:**
- **Mesmo padrão:** `storage/tasks/{taskId}/{safeFileName}`
- **Não é necessário criar subdiretório separado** (ex: `storage/tasks/{taskId}/recordings/`)
- Arquivos podem coexistir: `arquivo.pdf`, `gravacao.webm`, `outro.zip`

**Nomenclatura de Arquivo:**
- Usar `Storage::generateSafeFileName()` (já sanitiza)
- Sugestão de prefixo: `screen-recording-{timestamp}.webm` (opcional)

---

## 5. Sugestões de Implementação

### 5.1. Abordagem Técnica para Gravação de Tela

**Conceito:**
1. **Captura:** Utilizar `getDisplayMedia()` + `MediaRecorder` para capturar tela e áudio
2. **Formato:** Gerar arquivo `.webm` (formato nativo do MediaRecorder no Chrome/Firefox)
3. **Upload:** Enviar via `FormData` para endpoint existente ou novo
4. **Playback:** Utilizar `<video controls>` no front-end para reprodução

**Bibliotecas Opcionais (não obrigatórias):**
- **RecordRTC** (https://recordrtc.org/) - Facilita uso do MediaRecorder
- **Screen Recording API** (nativa do navegador) - Não requer bibliotecas externas

**Compatibilidade de Navegadores:**
- ✅ Chrome/Edge: Suporte completo (WebM)
- ✅ Firefox: Suporte completo (WebM)
- ⚠️ Safari: Suporte limitado (requer polyfill ou conversão)

### 5.2. Roteiro de Implementação (Passos Numerados)

#### **Fase 1: Preparação do Back-end**

**Passo 1.1:** Adicionar extensão `webm` à lista de extensões permitidas
- **Arquivo:** `src/Controllers/TaskAttachmentsController.php`
- **Linha:** ~171 (array `$allowedExtensions`)
- **Ação:** Adicionar `'webm'` ao array

**Passo 1.2:** Adicionar campo `recording_type` à tabela `task_attachments` (opcional)
- **Arquivo:** Nova migration `database/migrations/YYYYMMDD_add_recording_type_to_task_attachments.php`
- **SQL:** `ALTER TABLE task_attachments ADD COLUMN recording_type VARCHAR(50) NULL AFTER mime_type;`
- **Índice:** `INDEX idx_recording_type (recording_type)` (se necessário)

**Passo 1.3:** Adicionar campo `duration` à tabela `task_attachments` (opcional)
- **Arquivo:** Mesma migration do Passo 1.2
- **SQL:** `ALTER TABLE task_attachments ADD COLUMN duration INT UNSIGNED NULL AFTER file_size;`

**Passo 1.4:** Ajustar validação de tamanho máximo para vídeos (opcional)
- **Arquivo:** `src/Controllers/TaskAttachmentsController.php`
- **Ação:** Verificar `mime_type` e aplicar limite maior para vídeos (ex: 500MB)

#### **Fase 2: Componente JavaScript de Gravação**

**Passo 2.1:** Criar função `initScreenRecorder(taskId)` em `views/tasks/board.php`
- Solicitar permissão de tela
- Configurar opções de áudio (sistema/microfone)
- Inicializar `MediaRecorder`

**Passo 2.2:** Criar UI de controles de gravação
- Botão "Iniciar Gravação"
- Indicador visual (contador, ícone)
- Botões "Pausar" e "Parar"

**Passo 2.3:** Implementar função de finalização
- Parar gravação e obter blob
- Exibir preview do vídeo
- Permitir nome/comentário opcional
- Chamar função de upload

**Passo 2.4:** Implementar função `uploadScreenRecording(taskId, blob, metadata)`
- Criar `FormData` com `task_id`, `file` (blob), e metadados
- Enviar via AJAX para `POST /tasks/attachments/upload`
- Atualizar lista de anexos após sucesso

#### **Fase 3: Integração no Modal de Detalhes**

**Passo 3.1:** Adicionar seção "Gravações de Tela" no modal
- **Arquivo:** `views/tasks/board.php` (função `renderTaskDetailModal()`)
- **Posição:** Após seção de anexos ou antes (linha ~940)

**Passo 3.2:** Filtrar e exibir apenas gravações na seção dedicada
- Modificar query em `TaskBoardController::show()` para incluir `recording_type`
- Renderizar lista de vídeos com player `<video controls>`

**Passo 3.3:** Adicionar botão "Gravar Tela" na seção
- Chamar `initScreenRecorder(taskId)` ao clicar

#### **Fase 4: Player de Vídeo**

**Passo 4.1:** Renderizar player HTML5 para vídeos
- **Arquivo:** `views/tasks/board.php` ou `views/partials/task_attachments_table.php`
- **HTML:** `<video controls src="{url_download}" style="max-width: 100%;"></video>`

**Passo 4.2:** Ajustar endpoint de download para streaming (opcional)
- **Arquivo:** `src/Controllers/TaskAttachmentsController.php` (método `download()`)
- **Ação:** Adicionar headers para streaming de vídeo (Range requests)

#### **Fase 5: Testes e Ajustes**

**Passo 5.1:** Testar gravação em diferentes navegadores
- Chrome, Firefox, Edge, Safari

**Passo 5.2:** Testar upload de vídeos de diferentes tamanhos
- Pequenos (< 10MB), médios (10-50MB), grandes (50-200MB)

**Passo 5.3:** Validar playback em diferentes dispositivos
- Desktop, mobile (se aplicável)

---

## 6. Estrutura de Dados Recomendada

### 6.1. Tabela `task_attachments` (Modificada)

**Campos Existentes:**
- `id`, `tenant_id`, `task_id`, `file_name`, `original_name`, `file_path`, `file_size`, `mime_type`, `uploaded_at`, `uploaded_by`

**Campos Adicionais Sugeridos:**
```sql
ALTER TABLE task_attachments 
ADD COLUMN recording_type VARCHAR(50) NULL AFTER mime_type,
ADD COLUMN duration INT UNSIGNED NULL AFTER file_size,
ADD INDEX idx_recording_type (recording_type);
```

**Valores de `recording_type`:**
- `NULL` ou `'file'` = anexo normal (comportamento atual)
- `'screen_recording'` = gravação de tela

**Valores de `duration`:**
- `NULL` = não aplicável ou não informado
- `INT` = duração em segundos (ex: 120 = 2 minutos)

### 6.2. Exemplo de Registro

```sql
INSERT INTO task_attachments 
(task_id, tenant_id, file_name, original_name, file_path, file_size, mime_type, recording_type, duration, uploaded_at, uploaded_by)
VALUES 
(1, 2, 'screen-recording-20250125-143022.webm', 'Gravacao Tela - Bug Login.webm', '/storage/tasks/1/screen-recording-20250125-143022.webm', 15728640, 'video/webm', 'screen_recording', 45, NOW(), 1);
```

---

## 7. Limites e Configurações

### 7.1. Limites de Upload (PHP)

**Configurações Atuais (identificadas no código):**
- **Tamanho máximo no controller:** 200MB (`TaskAttachmentsController::upload()`)
- **Extensões permitidas:** Lista definida no controller

**Configurações PHP (php.ini) - Verificar:**
- `upload_max_filesize` (padrão: 2M ou 8M)
- `post_max_size` (deve ser >= upload_max_filesize)
- `max_execution_time` (para uploads grandes)
- `memory_limit` (para processamento)

**Recomendações:**
- Aumentar `upload_max_filesize` para 500M (se necessário para vídeos longos)
- Aumentar `post_max_size` para 510M
- Aumentar `max_execution_time` para 300 segundos (5 minutos)

### 7.2. Limites de Duração (Front-end)

**Sugestão:**
- **Máximo:** 10-15 minutos por gravação
- **Validação:** No JavaScript antes de iniciar gravação
- **Feedback:** Alertar usuário se exceder limite

### 7.3. Limpeza de Arquivos Antigos

**Atual:** Não há sistema de limpeza automática identificado

**Recomendação Futura:**
- Criar job/cron para limpar vídeos antigos (> 90 dias)
- Ou permitir exclusão manual pelo usuário (já implementado)

---

## 8. Conclusão e Recomendações

### 8.1. Resumo da Estratégia Recomendada

1. **Reaproveitar infraestrutura existente:**
   - Tabela `task_attachments` (com campos adicionais opcionais)
   - Controller `TaskAttachmentsController` (adicionar extensão `webm`)
   - Sistema de armazenamento (`storage/tasks/{taskId}/`)

2. **Adicionar componente JavaScript:**
   - Função de gravação usando `getDisplayMedia()` + `MediaRecorder`
   - UI de controles no modal de detalhes da tarefa
   - Upload via AJAX para endpoint existente

3. **Melhorias opcionais:**
   - Campo `recording_type` para diferenciar gravações
   - Campo `duration` para duração do vídeo
   - Seção dedicada "Gravações de Tela" no modal

### 8.2. Pontos de Atenção

⚠️ **Compatibilidade de Navegadores:**
- Safari tem suporte limitado a `getDisplayMedia()`
- Considerar fallback ou mensagem informativa

⚠️ **Tamanho de Arquivos:**
- Vídeos podem ser grandes (10-200MB)
- Validar limites do PHP e do servidor
- Considerar compressão no front-end (opcional)

⚠️ **Performance:**
- Uploads grandes podem demorar
- Implementar feedback visual de progresso
- Considerar upload em chunks (já existe exemplo em `HostingBackupController`)

### 8.3. Próximos Passos

1. ✅ **Auditoria concluída** (este documento)
2. ⏳ Revisar e aprovar estratégia proposta
3. ⏳ Implementar modificações no back-end (Fase 1)
4. ⏳ Implementar componente JavaScript (Fase 2)
5. ⏳ Integrar no modal de detalhes (Fase 3)
6. ⏳ Testes e ajustes (Fase 5)

---

## 9. Arquivos Modificados/Criados (Resumo)

### Arquivos a Modificar:
1. `src/Controllers/TaskAttachmentsController.php` - Adicionar `webm` às extensões
2. `views/tasks/board.php` - Adicionar componente de gravação e seção no modal
3. `database/migrations/` - Nova migration para campos opcionais (se necessário)

### Arquivos a Criar (Opcional):
1. `public/assets/js/screen-recorder.js` - Componente isolado (se preferir separar)
2. `database/migrations/YYYYMMDD_add_recording_fields_to_task_attachments.php` - Migration

### Arquivos de Referência:
- `src/Controllers/HostingBackupController.php` - Exemplo de upload em chunks
- `views/partials/task_attachments_table.php` - Template de lista de anexos

---

**Fim do Relatório de Auditoria**

**Última atualização:** 25/01/2025  
**Versão:** 1.0.0


