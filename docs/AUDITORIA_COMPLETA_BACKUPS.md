# 🔍 Auditoria Completa do Sistema de Backups

**Data:** 2025-01-25  
**Objetivo:** Mapear completamente o fluxo de upload de backups, identificar pontos de falha e documentar a implementação real.

---

## 📋 Sumário Executivo

O sistema de backups possui:
- ✅ **Estrutura completa** de rotas, controllers, views e banco de dados
- ✅ **Sistema de upload em chunks** implementado (500MB-2GB)
- ✅ **Upload direto** para arquivos até 500MB
- ⚠️ **Problemas identificados** que podem causar falhas silenciosas

**Status:** Sistema funcional, mas com pontos de melhoria críticos.

---

## 1. Mapeamento Completo do Fluxo

### 1.1. Rotas e Entry Points

**Arquivo:** `public/index.php` (linhas 182-187)

| Rota | Método | Controller | Método | Descrição |
|------|--------|------------|--------|-----------|
| `/hosting/backups` | GET | `HostingBackupController` | `index()` | Lista backups de um hosting account |
| `/hosting/backups/upload` | POST | `HostingBackupController` | `upload()` | Upload direto (até 500MB) |
| `/hosting/backups/chunk-init` | POST | `HostingBackupController` | `chunkInit()` | Inicia sessão de upload em chunks |
| `/hosting/backups/chunk-upload` | POST | `HostingBackupController` | `chunkUpload()` | Recebe um chunk individual |
| `/hosting/backups/chunk-complete` | POST | `HostingBackupController` | `chunkComplete()` | Finaliza e reúne chunks |
| `/hosting/backups/download` | GET | `HostingBackupController` | `download()` | Download protegido de backup |

**Autenticação:** Todas as rotas requerem `Auth::requireInternal()` (usuário interno).

---

### 1.2. Views e Formulários

**Arquivo:** `views/hosting/backups.php`

#### Formulário de Upload (linhas 119-161)

```html
<form method="POST" action="/hosting/backups/upload" enctype="multipart/form-data">
    <input type="hidden" name="hosting_account_id" value="...">
    <input type="hidden" name="redirect_to" value="hosting">
    <input type="file" id="backup_file" name="backup_file" accept=".wpress" required>
    <textarea name="notes">...</textarea>
    <button type="submit" id="submit-btn">Enviar Backup</button>
</form>
```

**Características:**
- ✅ `enctype="multipart/form-data"` presente
- ✅ Campo `name="backup_file"` corresponde ao esperado no controller
- ✅ JavaScript intercepta submit para arquivos > 500MB (linhas 164-293)

#### JavaScript de Upload Inteligente (linhas 164-293)

**Lógica:**
1. Intercepta `submit` do formulário
2. Verifica tamanho do arquivo:
   - **≤ 500MB:** Deixa formulário submeter normalmente (POST direto)
   - **> 500MB:** Previne submit e chama `uploadInChunks()`

**Fluxo de Chunks:**
1. `chunkInit()` - Cria sessão de upload
2. Loop: `chunkUpload()` - Envia cada chunk (10MB por chunk)
3. `chunkComplete()` - Reúne chunks e salva no banco

**Tamanho de chunk:** 10MB (linha 172)

---

### 1.3. Controller - Upload Direto

**Arquivo:** `src/Controllers/HostingBackupController.php`  
**Método:** `upload()` (linhas 69-310)

#### Fluxo Passo a Passo

1. **Autenticação** (linha 71)
   - `Auth::requireInternal()`

2. **Verificação de Método HTTP** (linhas 82-86)
   - Verifica se é POST
   - Se não for, redireciona com `error=invalid_method`

3. **Detecção de POST Excedido** (linhas 96-118)
   - Verifica se `$_POST` e `$_FILES` estão vazios mas `CONTENT_LENGTH > 0`
   - Se sim, provavelmente excedeu `post_max_size`
   - Redireciona com `error=file_too_large_php`

4. **Validação de Arquivo** (linhas 159-199)
   - Verifica se `$_FILES['backup_file']` existe
   - Verifica código de erro do upload (`UPLOAD_ERR_OK`)
   - Trata diferentes códigos de erro do PHP

5. **Validação de Extensão** (linhas 206-211)
   - Apenas `.wpress` aceito

6. **Validação de Tamanho** (linhas 213-228)
   - Máximo total: 2GB
   - Máximo upload direto: 500MB
   - Se > 500MB, redireciona com `error=use_chunked_upload`

7. **Criação de Diretório** (linhas 230-239)
   - `Storage::getTenantBackupDir()` - Retorna caminho
   - `Storage::ensureDirExists()` - Cria diretório
   - Verifica se diretório é gravável

8. **Movimentação de Arquivo** (linhas 241-250)
   - `Storage::generateSafeFileName()` - Sanitiza nome
   - `move_uploaded_file()` - Move para destino final

9. **Salvamento no Banco** (linhas 256-282)
   - Transação iniciada
   - INSERT em `hosting_backups`
   - UPDATE em `hosting_accounts` (backup_status, last_backup_at)
   - Commit

10. **Redirecionamento** (linhas 294-298)
    - Sucesso: `?success=uploaded`
    - Erro: `?error=...`

---

### 1.4. Controller - Upload em Chunks

#### Método `chunkInit()` (linhas 410-472)

**Função:** Inicia sessão de upload em chunks

**Parâmetros (JSON):**
- `hosting_account_id`
- `file_name`
- `file_size`
- `total_chunks`
- `upload_id`
- `notes`

**Processo:**
1. Valida dados
2. Valida extensão `.wpress`
3. Valida tamanho máximo (2GB)
4. Busca hosting account
5. Cria diretório temporário: `storage/temp/chunks/{upload_id}/`
6. Salva metadados em `session.json`

**Resposta:** `{success: true, upload_id: "..."}`

---

#### Método `chunkUpload()` (linhas 477-504)

**Função:** Recebe um chunk individual

**Parâmetros (FormData):**
- `upload_id`
- `chunk_index`
- `chunk` (arquivo)
- `total_chunks`

**Processo:**
1. Valida dados
2. Verifica se sessão existe
3. Move chunk para: `storage/temp/chunks/{upload_id}/chunk_{index}.bin`

**Resposta:** `{success: true, chunk_index: ..., total_chunks: ...}`

---

#### Método `chunkComplete()` (linhas 509-654)

**Função:** Reúne todos os chunks e salva no banco

**Parâmetros (JSON):**
- `upload_id`

**Processo:**
1. Carrega metadados da sessão
2. Verifica se todos os chunks foram recebidos
3. Cria arquivo final no diretório de backups
4. Reúne chunks usando `stream_copy_to_stream()`
5. Valida tamanho final
6. Limpa chunks temporários
7. Salva no banco (mesmo processo do upload direto)

**Resposta:** `{success: true, message: "..."}`

---

### 1.5. Banco de Dados

#### Tabela `hosting_backups`

**Migration:** `database/migrations/20251117_create_hosting_backups_table.php`

**Estrutura:**
```sql
CREATE TABLE hosting_backups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'all_in_one_wp',
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NULL,
    stored_path VARCHAR(500) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    INDEX idx_hosting_account_id (hosting_account_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
)
```

**Relacionamento:**
- `hosting_account_id` → `hosting_accounts.id`

---

#### Tabela `hosting_accounts`

**Campos relacionados a backups:**
- `backup_status` - Valores: `nenhum`, `completo`
- `last_backup_at` - DATETIME NULL

**Atualização:** Após upload bem-sucedido, atualiza:
- `backup_status = 'completo'`
- `last_backup_at = NOW()`

---

### 1.6. Estrutura de Armazenamento

**Classe:** `src/Core/Storage.php`

**Métodos:**
- `getTenantBackupDir($tenantId, $hostingAccountId)` - Retorna caminho absoluto
- `ensureDirExists($path)` - Cria diretório recursivamente
- `generateSafeFileName($originalName)` - Sanitiza nome
- `formatFileSize($bytes)` - Formata para exibição

**Estrutura de diretórios:**
```
/storage/
  /tenants/
    /{tenant_id}/
      /backups/
        /{hosting_account_id}/
          /{file_name}.wpress
  /temp/
    /chunks/
      /{upload_id}/
        /session.json
        /chunk_000000
        /chunk_000001
        ...
```

**Caminho relativo salvo no banco:**
```
/storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file_name}.wpress
```

---

## 2. Validações e Limites

### 2.1. Validações Implementadas

| Validação | Localização | Ação em Falha |
|-----------|-------------|---------------|
| Autenticação | `Auth::requireInternal()` | Redireciona para login |
| Método HTTP | Linha 82 | `error=invalid_method` |
| POST excedido | Linhas 96-118 | `error=file_too_large_php` |
| Arquivo presente | Linha 159 | `error=no_file` |
| Código de erro upload | Linhas 169-198 | Vários códigos de erro |
| Extensão .wpress | Linhas 206-211 | `error=invalid_extension` |
| Tamanho máximo 2GB | Linhas 217-220 | `error=file_too_large` |
| Tamanho > 500MB (direto) | Linhas 223-228 | `error=use_chunked_upload` |
| Diretório gravável | Linhas 235-239 | `error=dir_not_writable` |
| Move arquivo | Linhas 246-250 | `error=move_failed` |
| Banco de dados | Linhas 299-309 | `error=database_error` |

---

### 2.2. Limites Configurados

| Limite | Valor | Localização |
|--------|-------|-------------|
| Upload direto máximo | 500MB | Linha 214 |
| Tamanho total máximo | 2GB | Linha 215 |
| Tamanho de chunk | 10MB | `views/hosting/backups.php` linha 172 |

**Limites do PHP (verificados na view):**
- `upload_max_filesize`
- `post_max_size`
- `max_execution_time`
- `memory_limit`

---

## 3. Códigos de Erro Possíveis

### 3.1. Erros do Controller

| Código | Mensagem | Causa Provável |
|--------|----------|----------------|
| `missing_id` | ID do hosting account não fornecido | `hosting_account_id` ausente |
| `not_found` | Hosting account não encontrado | ID inválido |
| `invalid_method` | Método HTTP inválido | Não é POST |
| `file_too_large_php` | Arquivo excede limites do PHP | `post_max_size` ou `upload_max_filesize` |
| `no_file` | Nenhum arquivo enviado | `$_FILES['backup_file']` ausente |
| `upload_failed` | Erro genérico de upload | Código de erro do PHP desconhecido |
| `invalid_extension` | Apenas .wpress aceito | Extensão diferente |
| `file_too_large` | Arquivo > 2GB | Tamanho excede limite total |
| `use_chunked_upload` | Arquivo > 500MB | Deve usar chunks (mas JS deveria interceptar) |
| `dir_not_writable` | Diretório sem permissão | Permissões do servidor |
| `move_failed` | Erro ao mover arquivo | Permissões ou espaço em disco |
| `database_error` | Erro no banco | Falha na transação |

### 3.2. Erros do PHP (UPLOAD_ERR_*)

| Código | Constante | Tratamento no Controller |
|--------|-----------|--------------------------|
| 0 | `UPLOAD_ERR_OK` | ✅ Sucesso |
| 1 | `UPLOAD_ERR_INI_SIZE` | `error=file_too_large_php` |
| 2 | `UPLOAD_ERR_FORM_SIZE` | `error=file_too_large_php` |
| 3 | `UPLOAD_ERR_PARTIAL` | `error=partial_upload` |
| 4 | `UPLOAD_ERR_NO_FILE` | `error=no_file` |
| 6 | `UPLOAD_ERR_NO_TMP_DIR` | `error=no_tmp_dir` |
| 7 | `UPLOAD_ERR_CANT_WRITE` | `error=cant_write` |
| 8 | `UPLOAD_ERR_EXTENSION` | `error=php_extension` |

---

## 4. Fluxo Completo de Upload

### 4.1. Upload Direto (≤ 500MB)

```
1. USUÁRIO
   └─> Acessa /hosting/backups?hosting_id=1
   └─> Seleciona arquivo .wpress (ex: 100MB)
   └─> Clica "Enviar Backup"

2. NAVEGADOR
   └─> JavaScript verifica tamanho (≤ 500MB)
   └─> Permite submit normal do formulário
   └─> POST para /hosting/backups/upload
       └─> Content-Type: multipart/form-data
       └─> Campos: hosting_account_id, backup_file, notes, redirect_to

3. SERVIDOR (public/index.php)
   └─> Router::dispatch('POST', '/hosting/backups/upload')
   └─> Encontra rota: HostingBackupController@upload

4. CONTROLLER (HostingBackupController::upload)
   └─> Auth::requireInternal()
   └─> Verifica método POST
   └─> Verifica se POST excedeu post_max_size
   └─> Valida $_FILES['backup_file']
   └─> Valida extensão .wpress
   └─> Valida tamanho (≤ 2GB, ≤ 500MB para direto)
   └─> Storage::getTenantBackupDir()
   └─> Storage::ensureDirExists()
   └─> Verifica permissões de escrita
   └─> Storage::generateSafeFileName()
   └─> move_uploaded_file()
   └─> $db->beginTransaction()
   └─> INSERT INTO hosting_backups
   └─> UPDATE hosting_accounts SET backup_status='completo'
   └─> $db->commit()
   └─> redirect('/hosting/backups?hosting_id={id}&success=uploaded')

5. VIEW (views/hosting/backups.php)
   └─> Exibe mensagem de sucesso
   └─> Lista backups atualizada
```

---

### 4.2. Upload em Chunks (> 500MB até 2GB)

```
1. USUÁRIO
   └─> Acessa /hosting/backups?hosting_id=1
   └─> Seleciona arquivo .wpress (ex: 800MB)
   └─> Clica "Enviar Backup"

2. NAVEGADOR (JavaScript)
   └─> JavaScript verifica tamanho (> 500MB)
   └─> Previne submit do formulário
   └─> Chama uploadInChunks()

3. CHUNK INIT
   └─> POST /hosting/backups/chunk-init (JSON)
       └─> {hosting_account_id, file_name, file_size, total_chunks, upload_id, notes}
   └─> Controller cria diretório temporário
   └─> Salva session.json

4. CHUNK UPLOAD (loop)
   └─> Para cada chunk (10MB):
       └─> POST /hosting/backups/chunk-upload (FormData)
           └─> {upload_id, chunk_index, chunk, total_chunks}
       └─> Controller salva chunk em storage/temp/chunks/{upload_id}/chunk_{index}
       └─> Atualiza barra de progresso

5. CHUNK COMPLETE
   └─> POST /hosting/backups/chunk-complete (JSON)
       └─> {upload_id}
   └─> Controller verifica todos os chunks
   └─> Reúne chunks em arquivo final
   └─> Valida tamanho final
   └─> Limpa chunks temporários
   └─> Salva no banco (mesmo processo do upload direto)
   └─> redirect('/hosting/backups?hosting_id={id}&success=uploaded')

6. VIEW
   └─> Exibe mensagem de sucesso
   └─> Lista backups atualizada
```

---

## 5. Problemas Identificados

### 🔴 PROBLEMA CRÍTICO #1: Condição de Chunks Pode Não Ser Satisfeita

**Localização:** `views/hosting/backups.php` linha 180

**Problema:**
- JavaScript intercepta apenas se `file.size > MAX_DIRECT_UPLOAD` (500MB)
- Mas o controller também verifica isso (linha 224)
- Se o JavaScript falhar ou não carregar, o formulário é submetido normalmente
- Controller detecta arquivo > 500MB e redireciona com `error=use_chunked_upload`
- **Resultado:** Upload falha, mas não há feedback claro de que precisa usar chunks

**Evidência:**
- Linha 224 do controller: `if ($fileSize > $maxDirectUpload) { $redirectWithError('use_chunked_upload'); }`
- Linha 180 da view: `if (file.size > MAX_DIRECT_UPLOAD) { e.preventDefault(); ... }`

**Probabilidade:** 🔴 ALTA - Se JavaScript não carregar ou falhar, upload falha silenciosamente.

---

### 🔴 PROBLEMA CRÍTICO #2: Verificação de POST Excedido Pode Ser Incorreta

**Localização:** `HostingBackupController.php` linhas 96-118

**Problema:**
- Verifica se `$_POST` e `$_FILES` estão vazios E `CONTENT_LENGTH > 0`
- Mas se o POST exceder `post_max_size`, o PHP pode não definir `$_POST` mas ainda definir `$_FILES` parcialmente
- A condição pode não capturar todos os casos

**Evidência:**
```php
if (empty($_POST) && empty($_FILES) && $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize)
```

**Probabilidade:** 🟡 MÉDIA - Pode não detectar todos os casos de POST excedido.

---

### 🟡 PROBLEMA #3: Falta de Validação de Tamanho de Chunk

**Localização:** `HostingBackupController.php` método `chunkUpload()`

**Problema:**
- Não valida se o tamanho do chunk recebido corresponde ao esperado
- Não valida se o `chunk_index` está dentro do range esperado
- Pode permitir chunks duplicados ou fora de ordem

**Probabilidade:** 🟡 MÉDIA - Pode causar corrupção de arquivo se chunks chegarem fora de ordem.

---

### 🟡 PROBLEMA #4: Limpeza de Chunks Temporários Pode Falhar

**Localização:** `HostingBackupController.php` linhas 587-592

**Problema:**
- Usa `@unlink()` e `@rmdir()` (suprime erros)
- Se a limpeza falhar, chunks temporários ficam no servidor
- Pode acumular espaço em disco

**Probabilidade:** 🟡 MÉDIA - Acúmulo de arquivos temporários.

---

### 🟢 PROBLEMA MENOR #5: Falta de Timeout em Upload de Chunks

**Problema:**
- Se o upload de chunks demorar muito, pode exceder `max_execution_time`
- Não há mecanismo de retry automático
- Se um chunk falhar, todo o upload precisa ser reiniciado

**Probabilidade:** 🟢 BAIXA - Mas pode ser problemático para arquivos muito grandes.

---

## 6. Pontos de Quebra Mais Prováveis

### Ranking de Probabilidade

1. **🔴 ALTA: JavaScript não intercepta arquivo > 500MB**
   - JavaScript não carregou
   - Erro no JavaScript
   - Navegador antigo
   - **Resultado:** Upload direto falha, redireciona com `error=use_chunked_upload`

2. **🔴 ALTA: Limites do PHP (`post_max_size` / `upload_max_filesize`)**
   - Arquivo maior que limites do PHP
   - PHP descarta dados antes de chegar ao controller
   - **Resultado:** `error=file_too_large_php` ou `error=upload_failed`

3. **🟡 MÉDIA: Permissões de diretório**
   - Diretório `storage/tenants/` sem permissão de escrita
   - **Resultado:** `error=dir_not_writable` ou `error=move_failed`

4. **🟡 MÉDIA: Espaço em disco insuficiente**
   - Servidor sem espaço
   - **Resultado:** `error=move_failed`

5. **🟢 BAIXA: Erro no banco de dados**
   - Falha na transação
   - **Resultado:** `error=database_error` (arquivo já salvo no disco)

---

## 7. Logs e Diagnóstico

### 7.1. Logs Implementados

**Localização:** `HostingBackupController.php` método `upload()`

**Pontos de log:**
- Início do upload (linha 89)
- Método HTTP, Content-Type, Content-Length (linhas 90-92)
- Chaves de `$_POST` e `$_FILES` (linhas 93-94)
- Limites do PHP (linhas 100-102)
- Detecção de POST excedido (linhas 106-108)
- Detalhes de `$_FILES['backup_file']` (linha 121)
- Sucesso do upload (linhas 285-291)

**Função de log:**
- Usa `pixelhub_log()` se disponível
- Fallback para `error_log()`
- Arquivo: `logs/pixelhub.log`

---

### 7.2. Como Diagnosticar Problemas

1. **Verificar logs:**
   ```bash
   tail -f logs/pixelhub.log
   ```

2. **Verificar permissões:**
   ```bash
   ls -la storage/tenants/
   ```

3. **Verificar limites do PHP:**
   ```php
   php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize');"
   php -r "echo 'post_max_size: ' . ini_get('post_max_size');"
   ```

4. **Verificar espaço em disco:**
   ```bash
   df -h storage/
   ```

5. **Verificar se arquivo foi salvo:**
   ```bash
   ls -lh storage/tenants/{tenant_id}/backups/{hosting_account_id}/
   ```

6. **Verificar banco de dados:**
   ```sql
   SELECT * FROM hosting_backups WHERE hosting_account_id = ?;
   ```

---

## 8. Conclusão

O sistema de backups está **estruturalmente completo** e funcional, mas possui **pontos de melhoria críticos**:

### ✅ Pontos Fortes
- Sistema de upload em chunks implementado
- Validações robustas
- Tratamento de erros detalhado
- Logs para diagnóstico
- Estrutura de banco de dados adequada

### ⚠️ Pontos Fracos
- Dependência de JavaScript para interceptar uploads grandes
- Verificação de POST excedido pode não capturar todos os casos
- Falta de validação de chunks individuais
- Limpeza de arquivos temporários pode falhar silenciosamente

### 🎯 Próximos Passos Recomendados

1. **Melhorar detecção de POST excedido**
2. **Adicionar fallback quando JavaScript falhar**
3. **Validar chunks individuais**
4. **Melhorar limpeza de arquivos temporários**
5. **Adicionar retry automático para chunks**

---

**Fim da Auditoria**

