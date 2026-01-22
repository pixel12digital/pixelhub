# Instruções de Uso - Sistema de Backups

## 📋 Como Usar o Sistema de Backups

### 1. Cadastrar Conta de Hospedagem

Primeiro, você precisa cadastrar o site na tabela `hosting_accounts`. Você pode fazer isso via SQL ou criar uma interface depois.

**Exemplo SQL:**
```sql
INSERT INTO hosting_accounts 
(tenant_id, domain, current_provider, hostinger_expiration_date, decision, backup_status, migration_status)
VALUES 
(1, 'exemplo.com.br', 'hostinger', '2025-12-31', 'pendente', 'nenhum', 'nao_iniciada');
```

**Campos importantes:**
- `tenant_id`: ID do cliente (tenant)
- `domain`: Domínio do site
- `current_provider`: Provedor atual (padrão: 'hostinger')
- `hostinger_expiration_date`: Data de expiração da Hostinger
- `decision`: Decisão sobre o site (pendente, migrar_pixel, hostinger_afiliado, encerrar)
- `backup_status`: Status do backup (nenhum, completo)
- `migration_status`: Status da migração (nao_iniciada, em_andamento, concluida)

---

### 2. Acessar Lista de Sites

Acesse:
```
http://localhost/painel.pixel12digital/public/hosting
```

Você verá uma tabela com todos os sites cadastrados e um botão "Backups" em cada linha.

---

### 3. Gerenciar Backups de um Site

Clique no botão "Backups" de um site ou acesse diretamente:
```
http://localhost/painel.pixel12digital/public/hosting/backups?hosting_id=1
```

---

### 4. Fazer Upload de Backup

1. Na página de backups, você verá:
   - **Informações do site:** Domínio, cliente, provedor, status de backup, data de expiração
   - **Formulário de upload:** Com informações sobre limites do PHP e sistema inteligente
   - **Lista de backups existentes:** Todos os backups já enviados para este site

2. Para fazer upload:
   - Clique em "Escolher arquivo"
   - Selecione o arquivo `.wpress` baixado do All-in-One WP Migration
   - (Opcional) Adicione notas sobre o backup
   - Clique em "Enviar Backup"

3. **Sistema de Upload Inteligente:**
   - **Arquivos até 500MB:** Upload direto (rápido e simples)
   - **Arquivos entre 500MB e 2GB:** Upload automático em partes (chunks) - mais seguro e confiável
   - O sistema detecta automaticamente o tamanho e escolhe o método adequado

4. O sistema irá:
   - Validar o arquivo (extensão `.wpress` obrigatória, tamanho máximo 2GB)
   - Para uploads diretos: Enviar arquivo completo em uma única requisição
   - Para uploads em chunks: Dividir em partes de 10MB e enviar sequencialmente
   - Salvar em: `/storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file_name}.wpress`
   - Registrar no banco de dados (tabela `hosting_backups`)
   - Atualizar `backup_status` para 'completo' e `last_backup_at` na tabela `hosting_accounts`

---

### 5. Visualizar e Baixar Backups

**Lista de Backups:**
- A lista mostra todos os backups do site, ordenados por data (mais recente primeiro)
- Informações exibidas:
  - **Data:** Data e hora do upload
  - **Tipo:** Tipo de backup (geralmente "all_in_one_wp")
  - **Arquivo:** Nome do arquivo
  - **Tamanho:** Tamanho formatado (ex: "150.5 MB")
  - **Notas:** Notas adicionadas no upload
  - **Ações:** Link para download

**Download:**
- Clique em "Download" na linha do backup desejado
- O download é protegido (requer autenticação interna)
- O arquivo será baixado com o nome original (sanitizado)

---

## ⚙️ Configurações do PHP

O sistema exibe os limites atuais do PHP na página de upload. Para uploads grandes, verifique as configurações:

**Arquivo:** `php.ini` (XAMPP: `C:\xampp\php\php.ini`)

**Configuração recomendada:**
```ini
upload_max_filesize = 500M      # Suficiente para upload direto
post_max_size = 500M            # Deve ser >= upload_max_filesize
max_execution_time = 300        # 5 minutos (suficiente para uploads)
memory_limit = 256M             # Memória adequada
```

**Nota:** O sistema funciona mesmo com limites menores, pois arquivos > 500MB usam upload em chunks (cada chunk é 10MB).

**Verificar configurações atuais:**
```bash
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize');"
php -r "echo 'post_max_size: ' . ini_get('post_max_size');"
```

**Limites do sistema:**
- **Upload direto:** Até 500MB (requer `upload_max_filesize` e `post_max_size` >= 500M)
- **Upload em chunks:** 500MB até 2GB (funciona mesmo com limites menores do PHP)
- **Tamanho máximo total:** 2GB

---

## 📁 Estrutura de Armazenamento

Os arquivos são salvos em:
```
/storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file_name}.wpress
```

**Exemplo:**
```
/storage/tenants/1/backups/5/site-exemplo-2025-11-17.wpress
```

---

## 🔒 Segurança

- ✅ **Autenticação:** Apenas usuários internos podem acessar (`Auth::requireInternal()`)
- ✅ **Validação de extensão:** Apenas arquivos `.wpress` são aceitos
- ✅ **Validação de tamanho:** Máximo 2GB (500MB para upload direto)
- ✅ **Nome de arquivo sanitizado:** Remove caracteres perigosos e limita tamanho
- ✅ **Download protegido:** Requer autenticação e verifica existência do arquivo
- ✅ **Diretório protegido:** Arquivos salvos fora do diretório público
- ✅ **Validação de hosting account:** Verifica se o hosting account existe antes de salvar

---

## 📊 Status dos Backups

O sistema atualiza automaticamente o campo `backup_status` da tabela `hosting_accounts`:

- **`nenhum`**: Nenhum backup foi feito ainda
- **`completo`**: Pelo menos um backup foi feito

Isso permite que você veja rapidamente quais sites já têm backup antes da expiração da Hostinger.

---

## 🎯 Fluxo de Trabalho Recomendado

1. **Antes da expiração da Hostinger:**
   - Acesse a lista de sites em `/hosting`
   - Verifique quais têm `backup_status = 'nenhum'`
   - Faça backup de cada site que ainda não tem

2. **Após fazer backup:**
   - O status muda automaticamente para `completo`
   - Você pode ver a lista de backups na página do site
   - Todos os backups ficam organizados por site

3. **Para migração:**
   - Use os backups salvos para restaurar em novo servidor
   - Atualize `migration_status` conforme o progresso

---

---

## ⚠️ Mensagens de Erro Possíveis

Se algo der errado durante o upload, você verá uma mensagem de erro. Aqui estão os erros possíveis e o que significam:

| Erro | Significado | O que fazer |
|------|-------------|-------------|
| `missing_id` | ID do hosting account não fornecido | Recarregue a página e tente novamente |
| `not_found` | Hosting account não encontrado | Verifique se o site está cadastrado |
| `invalid_method` | Método HTTP inválido | O formulário deve ser enviado via POST |
| `file_too_large_php` | Arquivo excede limites do PHP | Ajuste `upload_max_filesize` e `post_max_size` no php.ini, ou use arquivo menor |
| `no_file` | Nenhum arquivo foi enviado | Selecione um arquivo antes de enviar |
| `invalid_extension` | Arquivo não é .wpress | Use apenas arquivos com extensão `.wpress` |
| `file_too_large` | Arquivo maior que 2GB | Use um arquivo menor ou divida o backup |
| `use_chunked_upload` | Arquivo > 500MB detectado no upload direto | O sistema deveria usar chunks automaticamente. Verifique se JavaScript está habilitado |
| `partial_upload` | Upload foi interrompido | Tente novamente |
| `dir_not_writable` | Diretório sem permissão de escrita | Verifique permissões de `storage/tenants/` no servidor |
| `move_failed` | Erro ao mover arquivo | Verifique permissões e espaço em disco |
| `database_error` | Erro ao salvar no banco | Verifique logs do servidor |
| `upload_failed` | Erro genérico de upload | Verifique logs para mais detalhes |

**Como verificar logs:**
- Acesse: `/view-backup-logs` (link disponível na página de backups)
- Ou verifique: `logs/pixelhub.log`

---

## 🐛 Problemas Conhecidos / Próximos Passos

### Problemas Identificados

1. **Dependência de JavaScript para uploads grandes**
   - Se JavaScript não carregar, arquivos > 500MB falham
   - **Status:** Sistema funciona, mas pode falhar se JS estiver desabilitado
   - **Próximo passo:** Implementar fallback no servidor

2. **Verificação de POST excedido pode não capturar todos os casos**
   - Alguns casos de arquivo muito grande podem não ser detectados corretamente
   - **Status:** Funciona na maioria dos casos
   - **Próximo passo:** Melhorar detecção

3. **Limpeza de chunks temporários pode falhar silenciosamente**
   - Chunks temporários podem acumular no servidor
   - **Status:** Não afeta funcionalidade, mas pode ocupar espaço
   - **Próximo passo:** Implementar limpeza automática periódica

### Melhorias Planejadas

- [ ] Adicionar retry automático para chunks que falharem
- [ ] Implementar validação de integridade de chunks
- [ ] Adicionar limpeza automática de chunks antigos
- [ ] Melhorar feedback visual durante upload em chunks
- [ ] Adicionar suporte a cancelamento de upload

---

**Data:** 25/01/2025  
**Status:** ✅ Sistema Funcional - Melhorias Planejadas  
**Última Auditoria:** Ver `AUDITORIA_COMPLETA_BACKUPS.md` para detalhes técnicos

