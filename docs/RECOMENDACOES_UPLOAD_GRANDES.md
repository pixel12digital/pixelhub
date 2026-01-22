# 📊 Recomendações para Upload de Arquivos Grandes

## ⚠️ Impacto no Sistema

### 1. **Memória (RAM)**
- **Impacto:** Durante o upload, o PHP mantém o arquivo temporariamente na memória
- **Exemplo:** Upload de 100MB pode usar ~100-150MB de RAM temporariamente
- **Risco:** Baixo (memória é liberada após o upload)

### 2. **Tempo de Execução**
- **Impacto:** Uploads grandes mantêm a conexão aberta por mais tempo
- **Exemplo:** 100MB a 10Mbps = ~80 segundos
- **Risco:** Médio (pode causar timeout se não configurado)

### 3. **Espaço em Disco**
- **Impacto:** Arquivos são salvos permanentemente no servidor
- **Exemplo:** 10 backups de 100MB cada = 1GB de espaço
- **Risco:** Médio (precisa monitorar espaço disponível)

### 4. **Conexão HTTP**
- **Impacto:** Mantém a conexão aberta durante todo o upload
- **Risco:** Baixo (mas pode afetar outros usuários se muitos uploads simultâneos)

---

## ✅ Recomendações por Cenário

### **Cenário 1: Backups Ocasionais (1-2x por mês)**
**Recomendação: AUMENTAR LIMITES DO PHP**

```ini
# php.ini (XAMPP)
upload_max_filesize = 200M
post_max_size = 200M
max_execution_time = 300
memory_limit = 256M
```

**Por quê?**
- ✅ Impacto mínimo (uso esporádico)
- ✅ Solução mais simples
- ✅ Não requer mudanças no código

**Quando usar:**
- Backups antes de migrações
- Backups mensais de segurança
- Poucos uploads por vez

---

### **Cenário 2: Backups Frequentes (vários por semana)**
**Recomendação: UPLOAD VIA FTP/SFTP**

**Vantagens:**
- ✅ Não consome recursos do PHP/Apache
- ✅ Mais rápido (transferência direta)
- ✅ Pode ser feito em background
- ✅ Não tem limite de tamanho do PHP

**Como implementar:**
1. Criar diretório FTP: `/storage/tenants/{id}/backups/{hosting_id}/`
2. Usuário faz upload direto via FileZilla/WinSCP
3. Sistema detecta arquivo e registra no banco

**Quando usar:**
- Múltiplos backups por semana
- Arquivos muito grandes (&gt;500MB)
- Necessidade de upload em background

---

### **Cenário 3: Arquivos Muito Grandes (&gt;500MB)**
**Recomendação: DIVIDIR ARQUIVO OU USAR FTP**

**Opção A: Dividir arquivo .wpress**
- Usar ferramenta de split (7-Zip, WinRAR)
- Upload de partes menores
- Sistema reúne as partes automaticamente

**Opção B: Upload via linha de comando**
```bash
# Script para upload direto via SSH
php upload-backup.php --file=backup.wpress --hosting-id=5
```

**Quando usar:**
- Arquivos &gt;500MB regularmente
- Servidor com recursos limitados
- Necessidade de automação

---

## 🎯 Recomendação Final para Seu Caso

### **Para Backups de Sites Completos:**

**Configuração Recomendada (BALANCEADA):**

```ini
# php.ini - Configuração balanceada
upload_max_filesize = 200M      # Suficiente para maioria dos backups
post_max_size = 200M            # Deve ser >= upload_max_filesize
max_execution_time = 300        # 5 minutos (suficiente para uploads)
memory_limit = 256M             # Memória adequada
```

**Por quê 200MB?**
- ✅ Maioria dos backups WordPress ficam entre 50-150MB
- ✅ Não sobrecarrega o servidor
- ✅ Permite uploads ocasionais maiores
- ✅ Balanceia performance e funcionalidade

**Se precisar de mais:**
- Aumente gradualmente: 300M → 500M → 1G
- Monitore uso de memória e disco
- Considere FTP para arquivos &gt;500MB

---

## 📋 Checklist de Implementação

### **Opção 1: Aumentar Limites PHP (Recomendado para começar)**

1. ✅ Editar `php.ini` do XAMPP
   - Localização: `C:\xampp\php\php.ini`
   
2. ✅ Ajustar valores:
   ```ini
   upload_max_filesize = 200M
   post_max_size = 200M
   max_execution_time = 300
   memory_limit = 256M
   ```

3. ✅ Reiniciar Apache no XAMPP

4. ✅ Testar com arquivo pequeno primeiro

5. ✅ Monitorar logs após uploads grandes

---

### **Opção 2: Implementar Upload via FTP (Futuro)**

1. ⏳ Criar endpoint para registrar backup manual
2. ⏳ Documentar processo de upload via FTP
3. ⏳ Adicionar validação de arquivo após upload
4. ⏳ Interface para "importar" backup já no servidor

---

## 🔍 Monitoramento

### **Métricas a Observar:**

1. **Espaço em disco:**
   ```bash
   # Verificar espaço usado em storage/tenants
   du -sh storage/tenants/*
   ```

2. **Memória durante upload:**
   - Verificar logs do PHP
   - Monitorar uso de RAM no servidor

3. **Tempo de upload:**
   - Logs já registram tempo
   - Alertar se &gt;5 minutos

---

## 💡 Dicas Finais

1. **Comece conservador:** 200MB é um bom ponto de partida
2. **Aumente conforme necessário:** Se precisar, suba para 300-500MB
3. **Use FTP para casos especiais:** Arquivos muito grandes ou frequentes
4. **Monitore o espaço:** Backups ocupam espaço permanente
5. **Considere compressão:** Arquivos .wpress já são compactados, mas pode ajudar

---

**Conclusão:** Para seu caso (backups ocasionais de sites completos), **aumentar para 200-300MB é seguro e recomendado**. O impacto no sistema será mínimo e a solução é simples.

