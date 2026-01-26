# 📝 Como Criar o Arquivo no cPanel File Manager

## 🎯 Problema Identificado

- Arquivo `atualizar-repositorio.php` existe mas está **vazio (0 bytes)**
- Arquivo está no diretório raiz, mas precisa estar em `public/` ou `public_html/`
- Por isso dá erro 404

## ✅ Solução Passo a Passo

### 1. Navegar até a Pasta Correta

No File Manager:
1. Entre na pasta **`public/`** (ou `public_html/` se existir)
2. Se não existir, crie uma pasta chamada `public`

### 2. Criar Novo Arquivo

**Opção A: Usar "Editor de HTML"**
1. Clique no botão **"Editor de HTML"** na barra de ferramentas
2. Clique em **"Novo Arquivo"** ou **"Arquivo" → "Novo"**
3. Nome do arquivo: `atualizar-repositorio.php`
4. Cole o conteúdo completo (veja abaixo)
5. Clique em **"Salvar"**

**Opção B: Usar "Editar"**
1. Clique no botão **"Editar"** na barra de ferramentas
2. Selecione **"Criar novo arquivo"**
3. Nome: `atualizar-repositorio.php`
4. Cole o conteúdo
5. Salve

### 3. Conteúdo do Arquivo

**Copie TODO o conteúdo abaixo** e cole no arquivo:

```php
<?php
$repoDir = '/home/pixel12digital/hub.pixel12digital.com.br';
if (!chdir($repoDir)) {
    die("ERRO: Não foi possível acessar o diretório: $repoDir");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Atualizar Repositório Git</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; border-radius: 4px; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Atualizar Repositório Git</h1>
        <div class="info">
            <strong>⚠️ ATENÇÃO:</strong> Este script irá sobrescrever mudanças locais no servidor.
        </div>
        <?php
        if (!is_dir('.git')) {
            echo '<div class="error">❌ ERRO: Não é um repositório Git!</div>';
            exit;
        }
        echo '<div class="step"><h3>[1/5] Verificando repositório...</h3><pre>';
        echo shell_exec('pwd 2>&1');
        echo '</pre><div class="success">✓ Repositório Git encontrado</div></div>';
        echo '<div class="step"><h3>[2/5] Estado atual...</h3><pre>';
        echo shell_exec('git status --short 2>&1');
        echo '</pre></div>';
        echo '<div class="step"><h3>[3/5] Atualizando referências (git fetch)...</h3><pre>';
        $fetchOutput = shell_exec('git fetch origin 2>&1');
        echo $fetchOutput;
        echo '</pre>';
        if (strpos($fetchOutput, 'fatal') === false) {
            echo '<div class="success">✓ Fetch concluído</div>';
        } else {
            echo '<div class="error">❌ Erro ao fazer fetch</div>';
        }
        echo '</div>';
        echo '<div class="step"><h3>[4/5] Resetando para origin/main...</h3><div class="warning">⚠️ Isso irá sobrescrever mudanças locais</div><pre>';
        $resetOutput = shell_exec('git reset --hard origin/main 2>&1');
        echo $resetOutput;
        echo '</pre>';
        if (strpos($resetOutput, 'fatal') === false) {
            echo '<div class="success">✓ Reset concluído</div>';
        } else {
            echo '<div class="error">❌ Erro ao fazer reset</div>';
        }
        echo '</div>';
        echo '<div class="step"><h3>[5/5] Verificando resultado...</h3><pre>';
        echo shell_exec('git status 2>&1');
        echo '</pre><h4>Últimos commits:</h4><pre>';
        echo shell_exec('git log --oneline -5 2>&1');
        echo '</pre></div>';
        echo '<div class="info"><h3>✅ Atualização concluída!</h3><p><strong>Próximos passos:</strong></p><ol><li>Volte ao cPanel Git Version Control</li><li>Tente fazer deploy novamente</li><li><strong>IMPORTANTE:</strong> Remova este arquivo PHP por segurança!</li></ol></div>';
        ?>
        <div class="info" style="background: #fff3cd; border-left-color: #ffc107;">
            <strong>🔒 Segurança:</strong> Após usar, delete este arquivo do servidor.
        </div>
    </div>
</body>
</html>
```

### 4. Acessar via Navegador

Após criar o arquivo em `public/`, acesse:
```
https://hub.pixel12digital.com.br/atualizar-repositorio.php
```

## 🔍 Verificar Estrutura

No File Manager, verifique:
- Existe pasta `public/`? → Coloque o arquivo aqui
- Existe pasta `public_html/`? → OU aqui
- Se nenhuma existir, crie `public/`

## ⚠️ Importante

- O arquivo **DEVE estar em `public/` ou `public_html/`** para ser acessível
- O arquivo **NÃO pode estar vazio** (precisa ter todo o conteúdo)
- Após usar, **REMOVA o arquivo** por segurança

---

**Local correto**: `/home/pixel12digital/hub.pixel12digital.com.br/public/atualizar-repositorio.php`
**Conteúdo**: Cole o código PHP completo acima


