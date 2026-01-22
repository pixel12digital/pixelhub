# Investigação: Upload de Backups (.WPRESS) - Relatório Técnico

**Data:** 2025-01-25  
**Objetivo:** Investigar por que o upload de arquivos .wpress não está salvando os backups, mesmo quando o formulário parece enviar corretamente.

---

## 1. Arquivos e Componentes Identificados

### 1.1. Rotas

**Arquivo:** `public/index.php` (linhas 182-184)

```182:184:public/index.php
$router->get('/hosting/backups', 'HostingBackupController@index');
$router->post('/hosting/backups/upload', 'HostingBackupController@upload');
$router->get('/hosting/backups/download', 'HostingBackupController@download');
```

**Rotas identificadas:**
- `GET /hosting/backups` → Lista backups de um hosting account
- `POST /hosting/backups/upload` → Processa upload de backup
- `GET /hosting/backups/download` → Download protegido de backup

### 1.2. Controller

**Arquivo:** `src/Controllers/HostingBackupController.php`

**Métodos principais:**
- `index()` - Lista backups (linhas 19-64)
- `upload()` - Processa upload (linhas 69-194)
- `download()` - Download de backup (linhas 199-247)

### 1.3. View

**Arquivo:** `views/hosting/backups.php`

**Seções principais:**
- Bloco "Informações do Site" (linhas 52-87)
- Bloco "Enviar Novo Backup" com formulário (linhas 89-110)
- Bloco "Backups Existentes" (linhas 112-158)

### 1.4. Service/Helper

**Arquivo:** `src/Core/Storage.php`

**Métodos:**
- `getTenantBackupDir()` - Retorna caminho do diretório de backups
- `ensureDirExists()` - Cria diretório se não existir
- `generateSafeFileName()` - Sanitiza nome do arquivo
- `formatFileSize()` - Formata tamanho para exibição

### 1.5. Banco de Dados

**Tabela:** `hosting_backups`

**Migration:** `database/migrations/20251117_create_hosting_backups_table.php`

**Estrutura:**
- `id` - INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `hosting_account_id` - INT UNSIGNED NOT NULL
- `type` - VARCHAR(50) NOT NULL DEFAULT 'all_in_one_wp'
- `file_name` - VARCHAR(255) NOT NULL
- `file_size` - BIGINT UNSIGNED NULL
- `stored_path` - VARCHAR(500) NOT NULL
- `notes` - TEXT NULL
- `created_at` - DATETIME NULL

---

## 2. Análise do Formulário de Upload

### 2.1. HTML do Formulário

**Localização:** `views/hosting/backups.php` (linhas 91-109)

```91:109:views/hosting/backups.php
    <form method="POST" action="<?= pixelhub_url('/hosting/backups/upload') ?>" enctype="multipart/form-data">
        <input type="hidden" name="hosting_account_id" value="<?= $hostingAccount['id'] ?>">
        <input type="hidden" name="redirect_to" value="hosting">
        
        <div style="margin-bottom: 15px;">
            <label for="backup_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Arquivo .wpress:</label>
            <input type="file" id="backup_file" name="backup_file" accept=".wpress" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Apenas arquivos .wpress do All-in-One WP Migration. Tamanho máximo: 2GB</small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas (opcional):</label>
            <textarea id="notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
        </div>
        
        <button type="submit" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            Enviar Backup
        </button>
    </form>
```

### 2.2. Verificações do Formulário

✅ **CORRETO:**
- `method="POST"` está presente
- `enctype="multipart/form-data"` está presente (essencial para upload de arquivos)
- Campo `name="backup_file"` corresponde ao esperado no controller
- Campo `name="hosting_account_id"` está presente como hidden
- Campo `name="notes"` está presente

❌ **POSSÍVEL PROBLEMA:**
- Não há JavaScript interceptando o submit (verificado no layout `main.php`)
- O formulário é submetido normalmente via POST tradicional

### 2.3. JavaScript

**Verificação:** Não há JavaScript interceptando o submit do formulário de backup.

O único JavaScript no layout (`views/layout/main.php`) é para o accordion do menu lateral e não interfere com formulários.

---

## 3. Análise do Controller de Upload

### 3.1. Método `upload()` - Código Completo

**Localização:** `src/Controllers/HostingBackupController.php` (linhas 69-194)

```69:194:src/Controllers/HostingBackupController.php
    public function upload(): void
    {
        Auth::requireInternal();

        $hostingAccountId = $_POST['hosting_account_id'] ?? null;
        $notes = $_POST['notes'] ?? '';

        if (!$hostingAccountId) {
            $this->redirect('/hosting/backups?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca hosting account para obter tenant_id
        $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
        $stmt->execute([$hostingAccountId]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            $this->redirect('/hosting/backups?error=not_found');
            return;
        }

        $tenantId = $hostingAccount['tenant_id'];
        $redirectTo = $_POST['redirect_to'] ?? 'hosting';

        // Helper para redirecionar com erro
        $redirectWithError = function($error) use ($redirectTo, $tenantId, $hostingAccountId) {
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=' . $error);
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingAccountId . '&error=' . $error);
            }
        };

        // Valida arquivo
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $redirectWithError('upload_failed');
            return;
        }

        $file = $_FILES['backup_file'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $tmpPath = $file['tmp_name'];

        // Valida extensão
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'wpress') {
            $redirectWithError('invalid_extension');
            return;
        }

        // Valida tamanho (máximo 2GB)
        $maxSize = 2 * 1024 * 1024 * 1024; // 2GB
        if ($fileSize > $maxSize) {
            $redirectWithError('file_too_large');
            return;
        }

        // Monta diretório de destino
        $backupDir = Storage::getTenantBackupDir($tenantId, $hostingAccountId);
        Storage::ensureDirExists($backupDir);

        // Gera nome de arquivo seguro
        $safeFileName = Storage::generateSafeFileName($originalName);
        $destinationPath = $backupDir . DIRECTORY_SEPARATOR . $safeFileName;

        // Move arquivo
        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            error_log("Erro ao mover arquivo de backup: {$tmpPath} para {$destinationPath}");
            $redirectWithError('move_failed');
            return;
        }

        // Caminho relativo para salvar no banco
        $relativePath = '/storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId . '/' . $safeFileName;

        // Salva no banco
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO hosting_backups 
                (hosting_account_id, type, file_name, file_size, stored_path, notes, created_at)
                VALUES (?, 'all_in_one_wp', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $hostingAccountId,
                $safeFileName,
                $fileSize,
                $relativePath,
                $notes
            ]);

            // Atualiza backup_status e last_backup_at do hosting account
            $stmt = $db->prepare("
                UPDATE hosting_accounts 
                SET backup_status = 'completo', 
                    last_backup_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hostingAccountId]);

            $db->commit();

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&success=uploaded');
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingAccountId . '&success=uploaded');
            }
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao salvar backup no banco: " . $e->getMessage());
            
            // Remove arquivo se salvou mas falhou no banco
            if (isset($destinationPath) && file_exists($destinationPath)) {
                unlink($destinationPath);
            }
            
            $redirectWithError('database_error');
        }
    }
```

### 3.2. Validações Implementadas

1. **Autenticação:** `Auth::requireInternal()` (linha 71)
2. **hosting_account_id:** Verifica se está presente em `$_POST` (linha 73)
3. **Hosting account existe:** Busca no banco (linhas 84-91)
4. **Arquivo presente:** Verifica `$_FILES['backup_file']` e `UPLOAD_ERR_OK` (linha 106)
5. **Extensão:** Valida se é `.wpress` (linhas 117-121)
6. **Tamanho:** Máximo 2GB (linhas 123-128)

### 3.3. Processamento do Arquivo

1. **Diretório:** Usa `Storage::getTenantBackupDir()` (linha 131)
2. **Criação de diretório:** `Storage::ensureDirExists()` (linha 132)
3. **Nome seguro:** `Storage::generateSafeFileName()` (linha 135)
4. **Move arquivo:** `move_uploaded_file()` (linha 139)
5. **Salva no banco:** INSERT em `hosting_backups` (linhas 152-163)
6. **Atualiza hosting_account:** UPDATE com `backup_status` e `last_backup_at` (linhas 166-173)

### 3.4. Tratamento de Erros

O controller redireciona com parâmetros de erro na URL:
- `error=missing_id`
- `error=not_found`
- `error=upload_failed`
- `error=invalid_extension`
- `error=file_too_large`
- `error=move_failed`
- `error=database_error`

A view exibe essas mensagens (linhas 13-38 de `views/hosting/backups.php`).

---

## 4. Análise do Carregamento da Lista de Backups

### 4.1. Query de Listagem

**Localização:** `src/Controllers/HostingBackupController.php` (linhas 47-54)

```47:54:src/Controllers/HostingBackupController.php
        // Busca backups
        $stmt = $db->prepare("
            SELECT * FROM hosting_backups
            WHERE hosting_account_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$hostingId]);
        $backups = $stmt->fetchAll();
```

**Filtro:** `WHERE hosting_account_id = ?` usando o `$hostingId` do `$_GET['hosting_id']`

### 4.2. Exibição na View

**Localização:** `views/hosting/backups.php` (linhas 115-157)

```115:157:views/hosting/backups.php
    <?php if (empty($backups)): ?>
        <p style="color: #666;">Nenhum backup encontrado.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Arquivo</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Notas</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= $backup['created_at'] ? date('d/m/Y H:i', strtotime($backup['created_at'])) : 'N/A' ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($backup['type']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($backup['file_name']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= Storage::formatFileSize($backup['file_size'] ?? 0) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($backup['notes'] ?? '') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <a href="<?= pixelhub_url('/hosting/backups/download?id=' . $backup['id']) ?>" 
                           style="color: #023A8D; text-decoration: none; font-weight: 600;">
                            Download
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
```

---

## 5. Análise da Classe Storage

### 5.1. Método `getTenantBackupDir()`

**Localização:** `src/Core/Storage.php` (linhas 13-17)

```13:17:src/Core/Storage.php
    public static function getTenantBackupDir(int $tenantId, int $hostingAccountId): string
    {
        $baseDir = __DIR__ . '/../../storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId;
        return $baseDir;
    }
```

**Caminho gerado:** `{PROJECT_ROOT}/storage/tenants/{tenant_id}/backups/{hosting_account_id}/`

### 5.2. Método `ensureDirExists()`

**Localização:** `src/Core/Storage.php` (linhas 22-27)

```22:27:src/Core/Storage.php
    public static function ensureDirExists(string $path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }
```

**Observação:** Usa `mkdir()` com `recursive=true`, o que deve criar toda a hierarquia de diretórios.

### 5.3. Método `generateSafeFileName()`

**Localização:** `src/Core/Storage.php` (linhas 32-45)

```32:45:src/Core/Storage.php
    public static function generateSafeFileName(string $originalName): string
    {
        // Remove caracteres perigosos
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        
        // Limita tamanho
        if (strlen($safeName) > 200) {
            $ext = pathinfo($safeName, PATHINFO_EXTENSION);
            $name = substr(pathinfo($safeName, PATHINFO_FILENAME), 0, 200 - strlen($ext) - 1);
            $safeName = $name . '.' . $ext;
        }
        
        return $safeName;
    }
```

---

## 6. Possíveis Pontos de Falha Identificados

### 🔴 PROBLEMA CRÍTICO #1: Validação de Erro de Upload Genérica

**Localização:** `src/Controllers/HostingBackupController.php` (linha 106)

```106:109:src/Controllers/HostingBackupController.php
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $redirectWithError('upload_failed');
            return;
        }
```

**Problema:**
- A validação não diferencia os tipos de erro de upload do PHP
- Se o erro for `UPLOAD_ERR_INI_SIZE` (arquivo maior que `upload_max_filesize` do php.ini) ou `UPLOAD_ERR_FORM_SIZE` (arquivo maior que `MAX_FILE_SIZE` do formulário), o usuário só vê "Erro ao fazer upload do arquivo"
- O código não loga qual foi o erro específico (`$_FILES['backup_file']['error']`)

**Evidência:** O código verifica apenas se `error !== UPLOAD_ERR_OK`, mas não trata os diferentes códigos de erro do PHP.

**Probabilidade:** 🔴 ALTA - Se o arquivo for maior que os limites do PHP, o upload falha silenciosamente.

---

### 🔴 PROBLEMA CRÍTICO #2: Falta de Verificação de Permissões de Diretório

**Localização:** `src/Controllers/HostingBackupController.php` (linhas 131-132)

```131:132:src/Controllers/HostingBackupController.php
        $backupDir = Storage::getTenantBackupDir($tenantId, $hostingAccountId);
        Storage::ensureDirExists($backupDir);
```

**Problema:**
- `Storage::ensureDirExists()` cria o diretório, mas não verifica se tem permissão de escrita
- Se o diretório não puder ser criado ou não tiver permissão de escrita, `move_uploaded_file()` falhará
- O erro só será detectado na linha 139, mas a mensagem será genérica ("move_failed")

**Evidência:** Não há verificação de `is_writable()` ou `file_exists()` após criar o diretório.

**Probabilidade:** 🟡 MÉDIA - Depende das permissões do servidor.

---

### 🟡 PROBLEMA #3: Caminho Relativo vs Absoluto no Banco

**Localização:** `src/Controllers/HostingBackupController.php` (linha 146)

```146:146:src/Controllers/HostingBackupController.php
        $relativePath = '/storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId . '/' . $safeFileName;
```

**Problema:**
- O caminho salvo no banco é relativo (`/storage/tenants/...`)
- O método `download()` constrói o caminho absoluto usando `__DIR__ . '/../../'` (linha 230)
- Se houver inconsistência entre o caminho relativo e a estrutura real, o download pode falhar

**Evidência:** O caminho relativo não usa `BASE_PATH` ou constante similar, pode não corresponder à estrutura real.

**Probabilidade:** 🟡 MÉDIA - Pode causar problemas no download, mas não no upload.

---

### 🟡 PROBLEMA #4: Falta de Log Detalhado

**Localização:** Todo o método `upload()`

**Problema:**
- Apenas dois pontos têm `error_log()`:
  - Linha 140: Erro ao mover arquivo
  - Linha 185: Erro ao salvar no banco
- Não há log quando:
  - O arquivo não chega ao servidor (erro de upload)
  - A validação de extensão falha
  - A validação de tamanho falha
  - O diretório não pode ser criado

**Evidência:** Falta de rastreabilidade para debug.

**Probabilidade:** 🟡 MÉDIA - Dificulta diagnóstico, mas não impede funcionamento.

---

### 🟢 PROBLEMA MENOR #5: Validação de Tamanho Duplicada

**Localização:** `src/Controllers/HostingBackupController.php` (linhas 123-128)

**Problema:**
- O código valida tamanho máximo de 2GB no PHP
- Mas o PHP pode ter limites menores em `php.ini` (`upload_max_filesize`, `post_max_size`)
- Se o arquivo for maior que os limites do PHP, nunca chegará ao código de validação

**Evidência:** A validação de 2GB só roda se o arquivo já passou pelos limites do PHP.

**Probabilidade:** 🟢 BAIXA - Mas pode causar confusão se o usuário tentar enviar arquivo grande.

---

### 🟢 PROBLEMA MENOR #6: Transação sem Verificação de Sucesso

**Localização:** `src/Controllers/HostingBackupController.php` (linhas 149-175)

**Problema:**
- O código usa `$db->beginTransaction()` e `$db->commit()`
- Mas não verifica se o `commit()` foi bem-sucedido
- Se o commit falhar silenciosamente, o arquivo fica no disco mas não no banco

**Evidência:** Não há verificação de retorno do `commit()`.

**Probabilidade:** 🟢 BAIXA - PDO geralmente lança exceção, mas não é garantido.

---

## 7. Fluxo Completo do Upload

```
1. USUÁRIO
   └─> Preenche formulário em /hosting/backups?hosting_id=1
       └─> Seleciona arquivo .wpress
       └─> Clica "Enviar Backup"

2. NAVEGADOR
   └─> Envia POST para /hosting/backups/upload
       └─> Content-Type: multipart/form-data
       └─> Campos: hosting_account_id, backup_file, notes, redirect_to

3. SERVIDOR (public/index.php)
   └─> Router::dispatch('POST', '/hosting/backups/upload')
       └─> Encontra rota: HostingBackupController@upload

4. CONTROLLER (HostingBackupController::upload)
   └─> Auth::requireInternal() [VERIFICA AUTENTICAÇÃO]
   └─> Valida $_POST['hosting_account_id']
   └─> Busca hosting_account no banco
   └─> Valida $_FILES['backup_file']
       └─> Verifica UPLOAD_ERR_OK
       └─> Valida extensão .wpress
       └─> Valida tamanho <= 2GB
   └─> Storage::getTenantBackupDir()
       └─> Retorna: {PROJECT_ROOT}/storage/tenants/{tenant_id}/backups/{hosting_account_id}/
   └─> Storage::ensureDirExists()
       └─> Cria diretório se não existir
   └─> Storage::generateSafeFileName()
       └─> Sanitiza nome do arquivo
   └─> move_uploaded_file()
       └─> Move de /tmp para destino final
   └─> $db->beginTransaction()
   └─> INSERT INTO hosting_backups
   └─> UPDATE hosting_accounts SET backup_status='completo'
   └─> $db->commit()
   └─> redirect('/hosting/backups?hosting_id={id}&success=uploaded')

5. VIEW (views/hosting/backups.php)
   └─> Controller::index() busca backups do banco
   └─> Exibe lista ou "Nenhum backup encontrado"
```

---

## 8. Pontos de Falha Mais Prováveis

### 🎯 RANKING DE PROBABILIDADE

1. **🔴 ALTA PROBABILIDADE: Limites do PHP (upload_max_filesize / post_max_size)**
   - Se o arquivo for maior que `upload_max_filesize` ou `post_max_size` do php.ini, o PHP não processa o upload
   - `$_FILES['backup_file']` pode não existir ou ter `error = UPLOAD_ERR_INI_SIZE`
   - O código redireciona com erro genérico "upload_failed"
   - **Como verificar:** Verificar logs do PHP ou adicionar log do código de erro específico

2. **🔴 ALTA PROBABILIDADE: Permissões de diretório**
   - Se `storage/tenants/` não tiver permissão de escrita, `mkdir()` pode falhar silenciosamente
   - `move_uploaded_file()` falhará e retornará `false`
   - O código redireciona com erro "move_failed"
   - **Como verificar:** Verificar permissões do diretório `storage/tenants/` e subdiretórios

3. **🟡 MÉDIA PROBABILIDADE: Rota não encontrada**
   - Se o Router não encontrar a rota POST `/hosting/backups/upload`, retorna 404
   - O formulário pode parecer enviar, mas nada acontece
   - **Como verificar:** Verificar logs do Router (linha 89-90 de Router.php)

4. **🟡 MÉDIA PROBABILIDADE: Erro silencioso no banco**
   - Se o INSERT ou UPDATE falhar sem lançar exceção, o commit pode passar
   - O arquivo fica no disco, mas não aparece na listagem
   - **Como verificar:** Verificar se há registros na tabela `hosting_backups` após upload

5. **🟢 BAIXA PROBABILIDADE: Incompatibilidade de nomes**
   - O formulário usa `name="backup_file"` e o controller espera `$_FILES['backup_file']`
   - **Status:** ✅ COMPATÍVEL - Nomes correspondem

6. **🟢 BAIXA PROBABILIDADE: Falta de enctype**
   - O formulário tem `enctype="multipart/form-data"`
   - **Status:** ✅ CORRETO

---

## 9. Sugestões de Correções (Alto Nível)

### 9.1. Melhorar Tratamento de Erros de Upload

**Problema:** Não diferencia tipos de erro de upload.

**Solução:**
- Adicionar switch/case para tratar cada código de erro do PHP (`UPLOAD_ERR_*`)
- Logar o código de erro específico
- Redirecionar com mensagem mais específica (ex: "Arquivo muito grande para o servidor")

### 9.2. Verificar Permissões de Diretório

**Problema:** Não verifica se o diretório foi criado com sucesso ou tem permissão de escrita.

**Solução:**
- Após `Storage::ensureDirExists()`, verificar se o diretório existe e é gravável
- Se não for, logar erro e redirecionar com mensagem específica

### 9.3. Adicionar Logs Detalhados

**Problema:** Falta de rastreabilidade.

**Solução:**
- Adicionar `error_log()` em pontos críticos:
  - Início do upload (com tamanho do arquivo)
  - Após cada validação
  - Antes e depois de `move_uploaded_file()`
  - Antes e depois do commit da transação

### 9.4. Verificar Limites do PHP

**Problema:** Não informa ao usuário sobre limites do servidor.

**Solução:**
- Exibir na view os limites atuais (`upload_max_filesize`, `post_max_size`)
- Validar tamanho antes do upload (JavaScript) e informar se exceder

### 9.5. Melhorar Validação de Tamanho

**Problema:** Validação de 2GB pode ser maior que os limites do PHP.

**Solução:**
- Verificar `ini_get('upload_max_filesize')` e `ini_get('post_max_size')`
- Usar o menor valor entre 2GB e os limites do PHP
- Informar ao usuário qual é o limite real

### 9.6. Verificar Sucesso do Commit

**Problema:** Não verifica se o commit foi bem-sucedido.

**Solução:**
- Verificar retorno de `commit()` ou usar try/catch mais específico
- Se falhar, fazer rollback e remover arquivo do disco

---

## 10. Checklist de Diagnóstico

Para identificar o problema específico, verificar:

- [ ] **Logs do PHP:** Verificar `error_log` ou arquivo de log do servidor após tentativa de upload
- [ ] **Permissões:** Verificar se `storage/tenants/` tem permissão 755 ou 775 e é gravável
- [ ] **Limites do PHP:** Executar `phpinfo()` ou verificar `ini_get('upload_max_filesize')` e `ini_get('post_max_size')`
- [ ] **Banco de dados:** Verificar se há registros na tabela `hosting_backups` após upload
- [ ] **Disco:** Verificar se arquivos estão sendo salvos em `storage/tenants/{tenant_id}/backups/{hosting_account_id}/`
- [ ] **Router:** Verificar logs do Router (linha 89-90) para ver se a rota está sendo encontrada
- [ ] **Network tab:** Verificar no DevTools do navegador se a requisição POST está sendo enviada e qual é a resposta

---

## 11. Conclusão

O código está **estruturalmente correto**, mas possui **falhas na detecção e tratamento de erros** que podem fazer com que o upload falhe silenciosamente.

**Principais suspeitos:**
1. Limites do PHP (`upload_max_filesize` / `post_max_size`) não permitindo arquivos grandes
2. Permissões de diretório impedindo criação/gravação
3. Falta de logs detalhados dificultando diagnóstico

**Próximos passos recomendados:**
1. Adicionar logs detalhados em todos os pontos críticos
2. Melhorar tratamento de erros de upload do PHP
3. Verificar permissões e limites do servidor
4. Adicionar validação de permissões de diretório antes de tentar salvar arquivo

---

**Fim do Relatório**

