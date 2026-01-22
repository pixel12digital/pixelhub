# 📋 Instruções: Executar Script no cPanel File Manager

## 🎯 Objetivo

Atualizar o repositório Git no servidor para resolver o erro de deploy, **sem acesso SSH**.

## 📝 Passo a Passo

### 1. Fazer Upload do Script

1. **Acesse o File Manager do cPanel**
2. **Navegue até**: `/home/pixel12digital/hub.pixel12digital.com.br/`
3. **Faça upload** do arquivo `atualizar-repositorio-servidor.sh`
   - Clique em "Upload" no File Manager
   - Selecione o arquivo `atualizar-repositorio-servidor.sh`
   - Aguarde o upload completar

### 2. Dar Permissão de Execução

**Opção A: Via Interface do File Manager**

1. **Clique com botão direito** no arquivo `atualizar-repositorio-servidor.sh`
2. Selecione **"Change Permissions"** ou **"Alterar Permissões"**
3. Marque a opção **"Execute"** (ou digite `755` no campo numérico)
4. Clique em **"Change Permissions"**

**Opção B: Via Terminal do File Manager**

1. No File Manager, procure por **"Terminal"** ou **"SSH/Terminal"**
2. Execute:
   ```bash
   cd /home/pixel12digital/hub.pixel12digital.com.br
   chmod +x atualizar-repositorio-servidor.sh
   ```

### 3. Executar o Script

**Opção A: Via Interface do File Manager**

1. **Clique com botão direito** no arquivo `atualizar-repositorio-servidor.sh`
2. Selecione **"Execute"** ou **"Executar"**
3. O script será executado e mostrará o resultado

**Opção B: Via Terminal do File Manager**

1. No Terminal do File Manager, execute:
   ```bash
   cd /home/pixel12digital/hub.pixel12digital.com.br
   ./atualizar-repositorio-servidor.sh
   ```

### 4. Verificar Resultado

O script mostrará:
- ✅ Status do repositório
- ✅ Últimos commits
- ✅ Confirmação de sucesso

### 5. Testar Deploy no cPanel

1. Volte ao **Git Version Control** no cPanel
2. Vá em **"Pull or Deploy"**
3. Tente fazer deploy novamente
4. O erro de "diverging branches" deve estar resolvido

## 🔍 Se o File Manager Não Tiver Opção "Execute"

### Alternativa: Criar Script PHP

Se o File Manager não permitir executar scripts bash, crie um arquivo PHP:

**Nome do arquivo**: `atualizar-repositorio.php`

```php
<?php
// Script PHP para atualizar repositório Git via cPanel
// Coloque este arquivo em: /home/pixel12digital/hub.pixel12digital.com.br/

$repoDir = '/home/pixel12digital/hub.pixel12digital.com.br';
chdir($repoDir);

echo "<h2>Atualizando Repositório Git</h2>";
echo "<pre>";

// Executar comandos Git
$commands = [
    'git fetch origin',
    'git reset --hard origin/main',
    'git status',
    'git log --oneline -5'
];

foreach ($commands as $cmd) {
    echo "\n>>> Executando: $cmd\n";
    echo shell_exec("$cmd 2>&1");
    echo "\n";
}

echo "</pre>";
echo "<p><strong>✅ Concluído! Agora tente fazer deploy no cPanel.</strong></p>";
?>
```

**Como usar:**
1. Faça upload do arquivo PHP
2. Acesse via navegador: `https://hub.pixel12digital.com.br/atualizar-repositorio.php`
3. O script será executado e mostrará o resultado

## ⚠️ Importante

- **Backup**: O script sobrescreve mudanças locais no servidor
- **Permissões**: Certifique-se de que o script tem permissão de execução (755)
- **Segurança**: Após usar, considere remover o script PHP do servidor

## 🆘 Solução de Problemas

### Erro: "Permission denied"
- Verifique se o arquivo tem permissão de execução (755)
- Use `chmod +x atualizar-repositorio-servidor.sh`

### Erro: "Not a git repository"
- Verifique se está no diretório correto
- O script tenta mudar automaticamente, mas pode falhar

### Script não executa
- Use a alternativa PHP acima
- Ou entre em contato com o suporte do hosting para executar via SSH

## 📞 Próximos Passos

Após executar o script:
1. ✅ Repositório atualizado
2. ✅ Deploy deve funcionar no cPanel
3. ⚠️ Considere tornar repositório privado
4. ⚠️ Revogue credenciais expostas no servidor

---

**Arquivo criado**: `atualizar-repositorio-servidor.sh`
**Alternativa**: `atualizar-repositorio.php` (se bash não funcionar)

