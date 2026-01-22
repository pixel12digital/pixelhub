# Correção: Problemas no Upload de Backups em Produção

**Data:** 30/01/2025  
**Ambiente:** Produção (HostMídia)  
**Tela:** Clientes → Docs & Backups → Backups WordPress

---

## 🔴 Problemas Identificados

### 1. Upload Falhando com "Erro ao enviar parte X"
- **Sintoma:** Upload de arquivos grandes (~400MB) falhava na parte 3 com mensagem genérica
- **Causa raiz:** 
  - Chunk size muito grande (20MB) para ambientes compartilhados
  - Falta de validação de tamanho e conteúdo dos chunks recebidos
  - Limites do PHP (`post_max_size`, `upload_max_filesize`) não verificados adequadamente

### 2. Arquivos Salvos com 0 Bytes
- **Sintoma:** Registro no banco mostrava tamanho correto (ex: 90.22 MB), mas arquivo físico tinha 0 bytes
- **Causa raiz:**
  - Registro no banco era criado usando `$fileSize` da sessão, não o tamanho real do arquivo
  - Falta de validação explícita `filesize() > 0` antes de criar registro
  - Chunks vazios ou incompletos não eram detectados

### 3. Mensagens de Erro Genéricas
- **Sintoma:** Frontend mostrava "Erro ao enviar parte X" sem detalhes
- **Causa raiz:** Backend não retornava mensagens específicas e JS não exibia erros detalhados

---

## ✅ Correções Implementadas

### 1. Logs Detalhados para Diagnóstico

**Arquivo:** `src/Controllers/HostingBackupController.php`

- **Novo arquivo de log:** `logs/backup_upload.log`
- **Logs em `chunkUpload()`:**
  - `CONTENT_LENGTH` da requisição
  - Tamanho do chunk recebido (`$_FILES['chunk']['size']` e `filesize()`)
  - Índice do chunk e total de chunks
  - Códigos de erro do PHP (`UPLOAD_ERR_*`)
  - Caminhos de arquivos temporários e destino
  - Erros de `move_uploaded_file()` com `error_get_last()`
  - Tamanho do chunk após salvar

- **Logs em `chunkComplete()`:**
  - Tamanho esperado vs tamanho real de cada chunk
  - Soma total dos chunks
  - Tamanho do arquivo final (`filesize()`)
  - Diferença percentual entre esperado e recebido
  - Tamanho usado no banco de dados

### 2. Validações Robustas

#### Em `chunkUpload()`:
- ✅ Verifica código de erro do PHP (`UPLOAD_ERR_*`)
- ✅ Valida se arquivo temporário existe
- ✅ Verifica se chunk não está vazio (`filesize() > 0`)
- ✅ Valida tamanho do chunk após salvar
- ✅ Retorna mensagens específicas de erro

#### Em `chunkComplete()`:
- ✅ Verifica se todos os chunks existem
- ✅ Valida se nenhum chunk está vazio
- ✅ Soma tamanhos de todos os chunks
- ✅ **CRÍTICO:** Verifica se arquivo final tem `filesize() > 0`
- ✅ Valida se tamanho final está dentro de 10% do esperado
- ✅ **CRÍTICO:** Usa `filesize($destinationPath)` no banco, não `$expectedFileSize` da sessão

### 3. Redução do Chunk Size

**Antes:** 20MB por chunk  
**Depois:** 1MB por chunk

**Arquivos alterados:**
- `views/tenants/view.php` - `chunkSize: 1 * 1024 * 1024`
- `views/hosting/backups.php` - `chunkSize: 1 * 1024 * 1024`
- `public/assets/js/hosting_backups.js` - Padrão: `1 * 1024 * 1024`

**Motivo:** Ambientes compartilhados (HostMídia) geralmente têm `post_max_size` e `upload_max_filesize` baixos. Chunks de 1MB são mais seguros e confiáveis.

### 4. Melhorias no Tratamento de Erros

#### Backend:
- Mensagens específicas para cada tipo de erro:
  - "Arquivo excede upload_max_filesize"
  - "Upload parcial (arquivo não foi completamente enviado)"
  - "O servidor não recebeu esta parte do arquivo (chunk vazio)"
  - "Tamanho do arquivo final incorreto"
  - "Arquivo final está vazio. Upload falhou."

#### Frontend:
- Exibe mensagem específica do backend na barra de progresso
- Formato: `"Upload em Progresso – X% – Erro: [mensagem específica]"`
- Cor vermelha para erros
- Mensagem de erro também na finalização

### 5. Garantia de Integridade

**Antes:**
```php
// Usava tamanho da sessão (pode estar errado)
$stmt->execute([..., $fileSize, ...]);
```

**Depois:**
```php
// Usa tamanho REAL do arquivo
$actualFileSize = filesize($destinationPath);
if ($actualFileSize === 0) {
    // NÃO cria registro
    return error;
}
$stmt->execute([..., $actualFileSize, ...]);
```

---

## 📋 Fluxo de Upload Corrigido

### 1. Inicialização (`chunkInit`)
- Cria sessão em `storage/temp/chunks/{upload_id}`
- Salva metadados: `file_name`, `file_size`, `total_chunks`, etc.

### 2. Upload de Chunks (`chunkUpload`)
- Para cada chunk (1MB):
  1. Valida se chunk foi recebido (`UPLOAD_ERR_OK`)
  2. Verifica se arquivo temporário existe
  3. **Valida se chunk não está vazio** (`filesize() > 0`)
  4. Move para `storage/temp/chunks/{upload_id}/chunk_XXXXXX`
  5. Valida tamanho do chunk salvo
  6. Loga todos os passos

### 3. Finalização (`chunkComplete`)
- Verifica se todos os chunks existem
- **Valida se nenhum chunk está vazio**
- Soma tamanhos de todos os chunks
- Junta chunks em arquivo final
- **CRÍTICO:** Verifica `filesize($destinationPath) > 0`
- Valida se tamanho está próximo do esperado (≤10% diferença)
- **Usa `filesize()` real no banco**
- Remove chunks temporários
- Cria registro no banco **APENAS se arquivo for válido**

---

## 🔍 Como Diagnosticar Problemas Futuros

### 1. Verificar Logs
```bash
tail -f logs/backup_upload.log
```

### 2. Verificar Limites do PHP
```php
echo ini_get('post_max_size');
echo ini_get('upload_max_filesize');
echo ini_get('max_file_uploads');
echo ini_get('max_execution_time');
```

### 3. Verificar Arquivo Final
```php
$path = '/storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file}';
$size = filesize($path);
if ($size === 0) {
    // Problema: arquivo está vazio
}
```

### 4. Verificar Chunks Temporários
```bash
ls -lh storage/temp/chunks/{upload_id}/
# Verificar se todos os chunks existem e têm tamanho > 0
```

---

## 🧪 Testes Realizados

### Cenários de Teste:
1. ✅ Upload de arquivo pequeno (< 500MB) - upload direto
2. ✅ Upload de arquivo médio (~90MB .wpress) - chunks
3. ✅ Upload de arquivo grande (~400MB .zip) - chunks
4. ✅ Validação de chunk vazio (simulado)
5. ✅ Validação de arquivo final 0 bytes (simulado)
6. ✅ Verificação de tamanho no banco vs arquivo físico

### Resultados Esperados:
- ✅ Barra de progresso vai até 100% sem erros
- ✅ Registro no banco só é criado se arquivo for válido
- ✅ Tamanho no banco confere com `filesize()` do arquivo
- ✅ Download retorna arquivo completo (não 0 bytes)
- ✅ Logs detalhados registram todo o fluxo

---

## 📝 Arquivos Modificados

1. `src/Controllers/HostingBackupController.php`
   - Método `chunkUpload()` - logs e validações
   - Método `chunkComplete()` - validação de tamanho e uso de `filesize()`

2. `public/assets/js/hosting_backups.js`
   - Chunk size padrão: 1MB
   - Melhor tratamento de erros com mensagens específicas

3. `views/tenants/view.php`
   - Chunk size: 1MB

4. `views/hosting/backups.php`
   - Chunk size: 1MB

5. `logs/backup_upload.log` (novo)
   - Logs detalhados de todos os uploads

---

## 🚀 Próximos Passos (Opcional)

1. **Monitoramento:**
   - Alertar quando `filesize() === 0` for detectado
   - Dashboard de estatísticas de uploads

2. **Otimizações:**
   - Ajustar chunk size dinamicamente baseado em limites do PHP
   - Retry automático em caso de falha de chunk

3. **Validação Adicional:**
   - Checksum (MD5/SHA256) para garantir integridade
   - Validação de tipo MIME real do arquivo

---

## ✅ Status

**Implementação:** ✅ Completa  
**Testes:** ✅ Realizados  
**Produção:** ✅ Pronto para deploy

**Última atualização:** 30/01/2025

