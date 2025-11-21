# Como Configurar o Limite de Tamanho de Arquivo no All-in-One WP Migration

## 📋 Visão Geral

O plugin **All-in-One WP Migration** tem uma limitação padrão de tamanho de arquivo para restauração de backups. Por padrão, o limite é de **512MB**, mas você pode aumentar isso conforme necessário.

**⚠️ IMPORTANTE:** Para funcionar completamente, você precisa configurar **DOIS arquivos**:
1. **`.htaccess`** - Aumenta o limite do PHP do servidor (ESSENCIAL)
2. **`constants.php`** do plugin - Aumenta o limite do plugin (ESSENCIAL)

---

## 🎯 Guia Passo a Passo - Método Testado e Funcional

### PARTE 1: Configurar o .htaccess (Aumenta limite do PHP)

#### PASSO 1: Localizar o arquivo .htaccess

1. Acesse o **cPanel File Manager** (ou use FTP)
2. Navegue até a **raiz do WordPress** (pasta `public_html`)
3. Procure pelo arquivo: `.htaccess`
   - Se não aparecer, ative "Mostrar arquivos ocultos" no File Manager

**Caminho completo:**
```
public_html/.htaccess
```

---

#### PASSO 2: Fazer backup do .htaccess (IMPORTANTE!)

**Antes de editar qualquer arquivo, sempre faça backup!**

1. No cPanel File Manager, clique com o botão direito no arquivo `.htaccess`
2. Selecione **"Copy"** (Copiar)
3. Cole na mesma pasta (vai criar `.htaccess copy`)

**Agora você tem uma cópia de segurança!**

---

#### PASSO 3: Editar o arquivo .htaccess

1. Clique com o botão direito no arquivo `.htaccess`
2. Selecione **"Edit"** ou **"Code Edit"**
3. Vá até o **início do arquivo** (antes de qualquer código)

---

#### PASSO 4: Adicionar código no início do .htaccess

**No início do arquivo (antes de tudo), adicione estas linhas:**

```apache
# Configurações para All-in-One WP Migration
php_value upload_max_filesize 2048M
php_value post_max_size 2048M
php_value max_execution_time 300
php_value memory_limit 512M
```

**Exemplo de como deve ficar:**

```apache
# Configurações para All-in-One WP Migration
php_value upload_max_filesize 2048M
php_value post_max_size 2048M
php_value max_execution_time 300
php_value memory_limit 512M

# BEGIN WordPress
...
```

---

#### PASSO 5: Salvar o .htaccess

1. Clique em **"Salvar alterações"** ou **"Save Changes"**
2. Confirme se necessário
3. O arquivo será salvo automaticamente

---

### PARTE 2: Configurar o constants.php do Plugin (Aumenta limite do plugin)

#### PASSO 6: Localizar o arquivo constants.php

1. No **cPanel File Manager**, navegue até:
   - `public_html/wp-content/plugins/all-in-one-wp-migration/`
2. Procure pelo arquivo: `constants.php`

**Caminho completo:**
```
public_html/wp-content/plugins/all-in-one-wp-migration/constants.php
```

---

#### PASSO 7: Fazer backup do constants.php

1. Clique com o botão direito no arquivo `constants.php`
2. Selecione **"Copy"** (Copiar)
3. Cole na mesma pasta para criar backup

---

#### PASSO 8: Editar o arquivo constants.php

1. Clique com o botão direito no arquivo `constants.php`
2. Selecione **"Edit"** ou **"Code Edit"**
3. Role até o **final do arquivo**

---

#### PASSO 9: Adicionar código no final do constants.php

**No final do arquivo, adicione esta linha:**

```php
define('AI1WM_MAX_FILE_SIZE', 2147483648); // 2GB
```

**Onde adicionar:**
- Se o arquivo termina com `?>`, adicione **ANTES** do `?>`
- Se o arquivo não tem `?>`, adicione no **final do arquivo**

**Exemplo de como deve ficar:**

```php
// ... código existente do plugin ...

define('AI1WM_MAX_FILE_SIZE', 2147483648); // 2GB
```

---

#### PASSO 10: Escolher o tamanho desejado

**Substitua o número `2147483648` pelo valor que você precisa:**

- **512MB** = `536870912`
- **1GB** = `1073741824`
- **2GB** = `2147483648` ⭐ Recomendado
- **4GB** = `4294967296`
- **8GB** = `8589934592`

**Exemplo para 4GB:**
```php
define('AI1WM_MAX_FILE_SIZE', 4294967296); // 4GB
```

---

#### PASSO 11: Salvar o constants.php

1. Clique em **"Salvar alterações"** ou **"Save Changes"**
2. Confirme se necessário

---

### PARTE 3: Testar se funcionou

#### PASSO 12: Verificar se funcionou

1. Acesse o **WordPress Admin** do seu site
2. Vá em **All-in-One WP Migration** → **Import**
3. Tente fazer upload de um arquivo maior que 64MB (ou 512MB)
4. Se não der erro de "arquivo muito grande", funcionou! ✅

---

## ✅ Pronto! 

Se funcionou, você está pronto! As configurações estão salvas e funcionarão para todos os uploads futuros.

---

## 📝 Para Novos Sites (Resumo Rápido)

Quando precisar configurar em outro site WordPress, faça apenas:

### 1. Editar .htaccess (ESSENCIAL)
- Localizar: `public_html/.htaccess`
- Adicionar no **início** do arquivo:
```apache
# Configurações para All-in-One WP Migration
php_value upload_max_filesize 2048M
php_value post_max_size 2048M
php_value max_execution_time 300
php_value memory_limit 512M
```

### 2. Editar constants.php do plugin (ESSENCIAL)
- Localizar: `public_html/wp-content/plugins/all-in-one-wp-migration/constants.php`
- Adicionar no **final** do arquivo:
```php
define('AI1WM_MAX_FILE_SIZE', 2147483648); // 2GB
```

**Pronto!** Não precisa editar `wp-config.php` se o `.htaccess` funcionar.

---

## 🔧 Método Alternativo (se .htaccess não funcionar)

Se o método do `.htaccess` não funcionar (alguns servidores não permitem), tente editar o `wp-config.php`:

### Editar wp-config.php

**PASSO 1:** Localizar o arquivo
- No cPanel File Manager, vá até a **raiz do WordPress** (pasta `public_html`)
- Procure pelo arquivo: `wp-config.php`

**PASSO 2:** Fazer backup
- Copie o arquivo `wp-config.php` para `wp-config.php.backup`

**PASSO 3:** Editar o arquivo
- Abra o arquivo `wp-config.php` para edição
- Procure pela linha que diz: `/* That's all, stop editing! Happy publishing. */`
- **ANTES dessa linha**, adicione:

```php
// Configurações para All-in-One WP Migration
@ini_set('upload_max_filesize', '2048M');
@ini_set('post_max_size', '2048M');
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');
```

**PASSO 4:** Salvar e verificar
- Salve o arquivo
- Teste fazer upload no plugin

**Nota:** Geralmente o `.htaccess` funciona melhor que o `wp-config.php`. Use `wp-config.php` apenas se o `.htaccess` não funcionar.

---

## 🔍 Se Ainda Não Funcionou

### Opção 1: Verificar configurações do PHP

**PASSO 1:** Criar arquivo de teste
- Crie um arquivo chamado `teste-limite.php` na raiz do WordPress
- Adicione este código:

```php
<?php
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
?>
```

**PASSO 2:** Acessar o arquivo
- Acesse: `seusite.com/teste-limite.php`
- Veja os valores exibidos

**PASSO 3:** Deletar o arquivo
- **IMPORTANTE:** Delete o arquivo `teste-limite.php` após verificar (questão de segurança!)

### Opção 2: Contatar suporte da hospedagem

Se nenhum método funcionar, pode ser que o servidor não permita alterar esses valores. Nesse caso:
1. Contate o suporte da sua hospedagem
2. Peça para aumentar `upload_max_filesize` e `post_max_size` para 2GB ou mais
3. Explique que precisa para restaurar backups do WordPress

---

## ❓ Problemas Comuns e Soluções

### Problema: "Ainda mostra erro de 64MB"

**Causa:** O `.htaccess` não está funcionando ou não foi configurado.

**Soluções:**
1. **Verifique se editou o `.htaccess` corretamente:**
   - O código deve estar no **início** do arquivo
   - Deve estar antes de `# BEGIN WordPress`

2. **Tente o método alternativo:**
   - Edite o `wp-config.php` (veja seção "Método Alternativo" acima)

3. **Verifique se o servidor permite:**
   - Alguns servidores compartilhados não permitem `php_value` no `.htaccess`
   - Nesse caso, contate o suporte da hospedagem

---

### Problema: "Ainda mostra erro de 512MB"

**Causa:** O `constants.php` do plugin não foi configurado ou não está funcionando.

**Soluções:**
1. **Verifique se editou o `constants.php` corretamente:**
   - A linha deve estar no **final** do arquivo
   - Confirme que salvou o arquivo

2. **Limpar cache do WordPress:**
   - No WordPress Admin, vá em **Plugins**
   - **Desative** o plugin All-in-One WP Migration
   - **Ative** novamente
   - Tente fazer upload novamente

---

### Problema: "Mudanças não funcionaram"

**Solução 1:** Verificar se salvou ambos os arquivos
- Confirme que salvou o `.htaccess`
- Confirme que salvou o `constants.php`

**Solução 2:** Verificar se o código está correto
- `.htaccess` deve ter as linhas no **início**
- `constants.php` deve ter a linha no **final**

**Solução 3:** Tentar método alternativo
- Se o `.htaccess` não funcionar, tente editar `wp-config.php` (veja seção acima)

---

### Problema: "Ainda dá erro de arquivo muito grande"

**Possíveis causas:**
1. O valor que você colocou está muito baixo
   - **Solução:** Aumente o valor (veja tabela de valores abaixo)

2. O servidor tem limite próprio que não pode ser alterado
   - **Solução:** Contate o suporte da hospedagem

3. O arquivo realmente é muito grande
   - **Solução:** Considere dividir o backup em partes menores

---

## 📊 Tabela de Valores (para referência)

| Tamanho | Valor em Bytes | Código para usar |
|---------|---------------|------------------|
| 512MB   | 536870912     | `536870912` |
| 1GB     | 1073741824    | `1073741824` |
| 2GB     | 2147483648    | `2147483648` ⭐ Recomendado |
| 4GB     | 4294967296    | `4294967296` |
| 8GB     | 8589934592    | `8589934592` |

---

## 🚨 Dicas Importantes

1. ✅ **Sempre faça backup** do arquivo antes de editar
2. ✅ **Use valores razoáveis** (2GB a 4GB geralmente é suficiente)
3. ✅ **Teste após cada mudança** para ver se funcionou
4. ⚠️ **Não use valores muito altos** (ex: 100GB) - pode travar o servidor
5. ⚠️ **Em servidores compartilhados**, pode ser necessário contatar o suporte

---

## 📝 Resumo Rápido - Método Testado e Funcional

**✅ Método que funciona (use este):**

1. **`.htaccess`** (na raiz do WordPress):
   - Adicione no **início** do arquivo:
   ```apache
   php_value upload_max_filesize 2048M
   php_value post_max_size 2048M
   ```

2. **`constants.php`** (do plugin):
   - Adicione no **final** do arquivo:
   ```php
   define('AI1WM_MAX_FILE_SIZE', 2147483648); // 2GB
   ```

3. Salve ambos os arquivos e teste!

**⚠️ Importante:** Você precisa configurar **AMBOS** os arquivos para funcionar completamente.

---

## 🎯 O Que Cada Arquivo Faz

- **`.htaccess`** → Aumenta o limite do **PHP do servidor** (resolve erro "64MB")
- **`constants.php`** → Aumenta o limite do **plugin** (resolve erro "512MB")
- **`wp-config.php`** → Alternativa ao `.htaccess` (use apenas se `.htaccess` não funcionar)

---

**Data:** 21/11/2025  
**Última atualização:** 21/11/2025  
**Status:** ✅ Testado e funcionando em produção

