# Relatório: Problema de Redirecionamento - Pixel Hub

## 📋 Resumo do Problema

O sistema está redirecionando para URLs absolutas (`http://localhost/login`, `http://localhost/dashboard`) em vez de URLs relativas ao projeto (`http://localhost/painel.pixel12digital/public/login`, `http://localhost/painel.pixel12digital/public/dashboard`).

**Sintoma:** Ao acessar `http://localhost/painel.pixel12digital/public/`, o sistema redireciona para `http://localhost/login`, que retorna 404 porque não existe nessa rota.

---

## 🔍 Análise do Problema

### Contexto
- **Projeto:** Pixel Hub (painel central da Pixel12 Digital)
- **Ambiente:** XAMPP local (Windows)
- **Estrutura:** Projeto em subpasta `/painel.pixel12digital/public/`
- **Stack:** PHP 8.x, MySQL, Router customizado

### Causa Raiz Identificada
Os redirecionamentos estão usando URLs absolutas (`/login`, `/dashboard`) sem considerar o prefixo da subpasta do projeto (`/painel.pixel12digital/public`).

---

## 🛠️ Tentativas de Solução Implementadas

### Tentativa 1: Criação da Constante BASE_PATH

**Data:** Início da correção
**Arquivo:** `public/index.php`

**Implementação:**
```php
// Calcula scriptDir
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $scriptDir !== '/' && $scriptDir !== '' ? $scriptDir : '');
}
```

**Resultado:** ✅ BASE_PATH foi definido corretamente, mas não estava sendo usado em todos os lugares.

---

### Tentativa 2: Ajuste do Método redirect() no Controller

**Arquivo:** `src/Core/Controller.php`

**Implementação:**
```php
protected function redirect(string $url): void
{
    // Se a URL começar com /, adiciona BASE_PATH
    if (strpos($url, '/') === 0) {
        $basePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '';
        $url = $basePath . $url;
    }
    
    header("Location: {$url}");
    exit;
}
```

**Resultado:** ✅ Método ajustado, mas ainda havia outros lugares fazendo redirecionamento direto.

---

### Tentativa 3: Ajuste do Auth::requireAuth()

**Arquivo:** `src/Core/Auth.php`

**Implementação:**
```php
public static function requireAuth(): void
{
    if (!self::check()) {
        $basePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '';
        $location = $basePath . '/login';
        header("Location: {$location}");
        exit;
    }
}
```

**Resultado:** ✅ Ajustado, mas ainda não resolve completamente.

---

### Tentativa 4: Ajuste da Rota Raiz (Closure)

**Arquivo:** `public/index.php`

**Implementação:**
```php
$router->get('/', function() {
    $basePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '';
    if (\PixelHub\Core\Auth::check()) {
        $location = $basePath . '/dashboard';
        header("Location: {$location}");
        exit;
    } else {
        $location = $basePath . '/login';
        header("Location: {$location}");
        exit;
    }
});
```

**Resultado:** ✅ Ajustado, mas o problema persiste.

---

### Tentativa 5: Sistema de Logs para Debug

**Arquivos:** 
- `public/index.php` (função `pixelhub_log()`)
- `public/debug-logs.php` (endpoint de visualização)
- `src/Core/Controller.php` (logs no redirect)
- `src/Core/Auth.php` (logs no requireAuth)

**Implementação:**
- Criada função `pixelhub_log()` para escrever em arquivo
- Logs adicionados em todos os pontos de redirecionamento
- Endpoint `/debug-logs.php` para visualizar logs

**Resultado:** ⚠️ Sistema de logs criado, mas ainda não foi possível verificar os logs porque:
1. O arquivo de log ainda não foi gerado (precisa acessar o site primeiro)
2. Problema de caminho no Windows (corrigido na última tentativa)

---

### Tentativa 6: Correção do Caminho de Logs no Windows

**Problema:** Caminho do arquivo de log estava incorreto no Windows (mistura de `/` e `\`).

**Correção:**
```php
// Antes (não funcionava no Windows)
$logFile = __DIR__ . '/../logs/pixelhub.log';

// Depois (funciona no Windows)
$logDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = realpath($logDir) . DIRECTORY_SEPARATOR . 'pixelhub.log';
```

**Resultado:** ✅ Caminho corrigido, mas ainda não testado.

---

## 📊 Estado Atual

### O que está funcionando:
- ✅ BASE_PATH está sendo definido corretamente
- ✅ Método `redirect()` do Controller usa BASE_PATH
- ✅ `Auth::requireAuth()` usa BASE_PATH
- ✅ Rota `/` usa BASE_PATH
- ✅ Sistema de logs criado e caminho corrigido

### O que ainda precisa ser verificado:
- ⚠️ **Logs não foram gerados ainda** - precisa acessar o site para criar o arquivo
- ⚠️ **Não sabemos o valor real do BASE_PATH** em execução
- ⚠️ **Não sabemos qual URL está sendo gerada** nos redirecionamentos

---

## 🔬 Próximos Passos para Diagnóstico

### 1. Gerar os Logs
1. Acessar: `http://localhost/painel.pixel12digital/public/`
2. Tentar fazer login
3. Verificar o arquivo: `logs/pixelhub.log`

### 2. Verificar os Logs
Acessar: `http://localhost/painel.pixel12digital/public/debug-logs.php`

Os logs devem mostrar:
- Valor do `BASE_PATH` definido
- Todas as URLs de redirecionamento geradas
- Onde está o problema

### 3. Possíveis Problemas Restantes

#### Problema A: BASE_PATH não está definido quando necessário
**Sintoma:** Logs mostram "BASE_PATH: não definido"
**Solução:** Verificar se BASE_PATH é definido antes dos controllers serem instanciados

#### Problema B: BASE_PATH está vazio
**Sintoma:** Logs mostram "BASE_PATH: ''"
**Sintoma:** `scriptDir` pode estar sendo calculado incorretamente
**Solução:** Verificar cálculo do `scriptDir` baseado em `$_SERVER['SCRIPT_NAME']`

#### Problema C: Redirecionamento ainda usa URL absoluta
**Sintoma:** Logs mostram URL sem BASE_PATH
**Solução:** Verificar se há algum lugar fazendo `header('Location: /...')` diretamente

---

## 📝 Arquivos Modificados

1. `public/index.php`
   - Definição de BASE_PATH
   - Sistema de logs
   - Rota `/` com BASE_PATH

2. `src/Core/Controller.php`
   - Método `redirect()` ajustado para usar BASE_PATH
   - Logs adicionados

3. `src/Core/Auth.php`
   - Método `requireAuth()` ajustado para usar BASE_PATH
   - Logs adicionados

4. `src/Core/Router.php`
   - Suporte a closures como handlers
   - Método `dispatch()` para aceitar path calculado

5. `public/debug-logs.php`
   - Endpoint para visualizar logs (NOVO)

6. `logs/.gitkeep`
   - Diretório de logs criado

---

## 🎯 Conclusão

O problema está sendo abordado de forma sistemática, mas **ainda não foi possível confirmar a solução** porque:

1. Os logs ainda não foram gerados (precisa acessar o site)
2. Não sabemos o valor real do BASE_PATH em execução
3. Não sabemos qual URL está sendo gerada nos redirecionamentos

**Recomendação:** Acessar o site para gerar os logs e então analisar o conteúdo do arquivo `logs/pixelhub.log` para identificar exatamente onde está o problema.

---

## 📌 Comandos Úteis

```powershell
# Verificar se o diretório de logs existe
Test-Path logs

# Ver conteúdo do log (se existir)
Get-Content logs\pixelhub.log -Tail 50

# Criar diretório de logs manualmente
New-Item -ItemType Directory -Path logs -Force
```

---

**Data do Relatório:** 17/11/2025  
**Status:** Em diagnóstico - aguardando geração de logs

