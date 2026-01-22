# 📦 Upload de Arquivos de 2GB - Análise Técnica

## ❌ Resposta Direta: **NÃO É PRÁTICO via HTTP**

### Por que arquivos de 2GB são problemáticos via navegador?

#### 1. **Timeout de Conexão**
- **Problema:** Upload de 2GB leva muito tempo
- **Exemplo:** 2GB a 10Mbps = ~27 minutos
- **Risco:** Conexão pode cair, navegador pode travar
- **Solução:** Aumentar `max_execution_time` para 1800+ segundos (30 min)

#### 2. **Memória do Servidor**
- **Problema:** PHP precisa manter arquivo na memória temporariamente
- **Exemplo:** 2GB pode usar 2-3GB de RAM durante upload
- **Risco:** Servidor pode ficar sem memória
- **Solução:** Aumentar `memory_limit` para 3-4GB (muito alto!)

#### 3. **Limites do Apache/Nginx**
- **Problema:** Servidores web têm limites próprios
- **Apache:** `LimitRequestBody` (padrão: ilimitado, mas pode ter timeout)
- **Nginx:** `client_max_body_size` (precisa configurar)
- **Risco:** Servidor pode rejeitar antes do PHP processar

#### 4. **Limites do Navegador**
- **Problema:** Navegadores podem travar com uploads muito longos
- **Risco:** Perda de progresso, necessidade de reiniciar
- **Solução:** Nenhuma (limitação do cliente)

#### 5. **Risco de Falha**
- **Problema:** Qualquer interrupção = perda total do upload
- **Risco:** Ter que reiniciar do zero (27 minutos perdidos!)
- **Solução:** Upload em chunks (muito complexo)

---

## ✅ Alternativas Práticas para Arquivos de 2GB

### **Opção 1: Upload via FTP/SFTP (RECOMENDADO)**

**Vantagens:**
- ✅ Não consome recursos do PHP/Apache
- ✅ Mais rápido (transferência direta)
- ✅ Pode retomar se interrompido (alguns clientes FTP)
- ✅ Não tem limite de tamanho do PHP
- ✅ Pode fazer em background

**Como fazer:**
1. Conectar ao servidor via FileZilla/WinSCP
2. Navegar até: `/storage/tenants/{tenant_id}/backups/{hosting_account_id}/`
3. Fazer upload do arquivo .wpress
4. Registrar manualmente no sistema (ou criar script automático)

**Implementação futura:**
- Criar endpoint para "importar" backup já no servidor
- Sistema detecta arquivo e registra no banco

---

### **Opção 2: Dividir Arquivo em Partes**

**Como fazer:**
1. Dividir arquivo .wpress em partes menores:
   ```bash
   # Usando 7-Zip (Windows)
   7z a -v500m backup.part backup.wpress
   
   # Ou WinRAR
   winrar a -v500m backup.part backup.wpress
   ```

2. Fazer upload de cada parte via navegador

3. Sistema reúne as partes automaticamente (precisa implementar)

**Vantagens:**
- ✅ Pode usar upload via navegador
- ✅ Menor risco de falha (se uma parte falhar, só repete ela)
- ✅ Não precisa aumentar limites muito

**Desvantagens:**
- ❌ Requer implementação de merge de arquivos
- ❌ Mais trabalho manual

---

### **Opção 3: Upload via Linha de Comando**

**Script PHP para upload direto:**

```php
<?php
// upload-backup-cli.php
$filePath = $argv[1] ?? null;
$hostingId = $argv[2] ?? null;

if (!$filePath || !$hostingId) {
    die("Uso: php upload-backup-cli.php <arquivo.wpress> <hosting_id>\n");
}

// Copia arquivo diretamente para destino
// Registra no banco
// Não tem limites de upload_max_filesize
```

**Vantagens:**
- ✅ Sem limites do PHP
- ✅ Pode ser automatizado
- ✅ Mais rápido

**Desvantagens:**
- ❌ Requer acesso SSH/linha de comando
- ❌ Precisa implementar script

---

### **Opção 4: Aumentar Limites ao Máximo (NÃO RECOMENDADO)**

**Configuração extrema:**

```ini
# php.ini - NÃO RECOMENDADO
upload_max_filesize = 2048M
post_max_size = 2048M
max_execution_time = 1800      # 30 minutos
memory_limit = 4096M           # 4GB de RAM!
```

**Problemas:**
- ❌ Consome MUITA memória do servidor
- ❌ Risco alto de timeout/falha
- ❌ Pode travar o servidor
- ❌ Navegador pode travar
- ❌ Qualquer interrupção = perda total

**Quando usar:**
- ⚠️ Apenas em último caso
- ⚠️ Servidor dedicado com recursos abundantes
- ⚠️ Conexão muito estável
- ⚠️ Aceitar risco de falha

---

## 🎯 Recomendação Final

### **Para arquivos de 2GB:**

**✅ MELHOR OPÇÃO: Upload via FTP/SFTP**

1. **Por quê?**
   - Mais confiável
   - Não sobrecarrega servidor
   - Pode retomar se interrompido
   - Sem limites técnicos

2. **Como implementar:**
   - Criar diretório FTP para cada tenant/hosting
   - Documentar processo
   - (Futuro) Criar interface para "importar" backup já no servidor

3. **Processo atual:**
   ```
   1. Usuário faz upload via FTP para:
      /storage/tenants/{id}/backups/{hosting_id}/
   
   2. Usuário acessa sistema e clica em "Importar Backup"
   
   3. Sistema lista arquivos .wpress no diretório
   
   4. Usuário seleciona e sistema registra no banco
   ```

---

## 📊 Comparação de Métodos

| Método | Tamanho Máximo | Confiabilidade | Complexidade | Recomendado? |
|--------|----------------|----------------|--------------|--------------|
| **HTTP (navegador)** | 200-500MB | ⚠️ Média | ✅ Simples | ✅ Até 500MB |
| **HTTP (2GB)** | 2GB | ❌ Baixa | ✅ Simples | ❌ Não |
| **FTP/SFTP** | Ilimitado | ✅ Alta | ✅ Simples | ✅ Sim |
| **Dividir arquivo** | Ilimitado | ✅ Alta | ⚠️ Média | ⚠️ Se necessário |
| **CLI/Script** | Ilimitado | ✅ Alta | ⚠️ Média | ⚠️ Se tiver acesso |

---

## 💡 Conclusão

**Para arquivos de 2GB:**
- ❌ **NÃO** use upload via navegador HTTP
- ✅ **USE** FTP/SFTP (mais confiável e prático)
- ⚠️ **ALTERNATIVA:** Dividir arquivo em partes menores

**Configuração recomendada para HTTP:**
- `upload_max_filesize = 200-500M` (suficiente para maioria)
- Para arquivos maiores, use FTP

---

## 🚀 Próximos Passos

1. **Imediato:** Aumentar limites para 200-300MB (cobre maioria dos casos)
2. **Futuro:** Implementar sistema de importação via FTP
3. **Opcional:** Implementar upload em chunks para arquivos grandes

