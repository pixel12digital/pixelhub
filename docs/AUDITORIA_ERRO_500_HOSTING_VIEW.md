# Auditoria Completa: Erro 500 em `/hosting/view?id=1`

**Data do Relatório:** 20/11/2025  
**Desenvolvedor Responsável:** Assistente AI (Auto)  
**Status:** 🔴 **PROBLEMA PERSISTENTE**  
**Prioridade:** Alta

---

## 📋 Sumário Executivo

O endpoint `/hosting/view?id=1` está retornando **HTTP 500 (Internal Server Error)** quando acessado via requisição AJAX. O erro ocorre no método `view()` do `HostingController`, impedindo que o modal de detalhes de hospedagem seja exibido na interface do cliente.

**Impacto:** Usuários não conseguem visualizar detalhes completos das contas de hospedagem através do botão "Ver" na tabela de hospedagens.

---

## 🔍 Descrição Detalhada do Problema

### Sintomas Observados

1. **No Navegador:**
   - Modal de carregamento aparece com título "Carregando..."
   - Após alguns segundos, exibe erro: "Erro ao carregar dados: Erro interno do servidor"
   - Console do navegador mostra: `Failed to load resource: the server responded with a status of 500 (Internal Server Error)`
   - URL da requisição: `/painel.pixel12digital/public/hosting/view?id=1`

2. **No Servidor:**
   - Resposta HTTP 500
   - Resposta vazia ou JSON com `{"error": "Erro interno do servidor"}`
   - Nenhum log de erro específico encontrado nos logs padrão

### Contexto da Requisição

- **Endpoint:** `GET /hosting/view?id=1`
- **Controller:** `HostingController@view`
- **Método:** Retorna JSON via AJAX para modal de detalhes
- **Autenticação:** Requer usuário interno autenticado
- **Uso:** Chamado quando usuário clica no botão "Ver" na tabela de hospedagens

---

## 🛠️ Tentativas de Resolução Implementadas

### Tentativa 1: Correção do Import PDO
**Data:** Início da investigação  
**Arquivo:** `src/Controllers/HostingController.php`

**Problema Identificado:**
- O método `view()` usava `PDO::FETCH_ASSOC` sem importar a classe `PDO`
- Isso causaria um erro fatal: `Class 'PDO' not found`

**Solução Aplicada:**
```php
use PDO; // Adicionado no topo do arquivo
```

**Resultado:** ❌ Problema persiste (erro não era este)

---

### Tentativa 2: Melhoria do Tratamento de Erros
**Data:** Primeira iteração  
**Arquivo:** `src/Controllers/HostingController.php`

**Mudanças:**
- Adicionado limpeza de buffers de saída
- Implementado tratamento de exceções com `\Throwable`
- Adicionada função auxiliar `$sendError` para padronizar respostas
- Melhorado tratamento de valores vazios na função `$calculateStatus`

**Código Adicionado:**
```php
// Limpa qualquer output anterior
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Desabilita exibição de erros para não quebrar JSON
$oldDisplayErrors = ini_get('display_errors');
$oldErrorReporting = error_reporting();
ini_set('display_errors', '0');
error_reporting(0);
```

**Resultado:** ❌ Problema persiste

---

### Tentativa 3: Correção da Indentação na Função `$calculateStatus`
**Data:** Segunda iteração  
**Arquivo:** `src/Controllers/HostingController.php`

**Problema Identificado:**
- Indentação incorreta na função `$calculateStatus` (linhas 445-483)
- Código após `$daysLeft = floor(...)` estava no nível errado

**Solução Aplicada:**
- Corrigida indentação de todo o bloco da função
- Adicionada validação para `strtotime()` retornar `false`

**Resultado:** ❌ Problema persiste

---

### Tentativa 4: Melhoria do Tratamento de Erros no JavaScript
**Data:** Terceira iteração  
**Arquivo:** `views/tenants/view.php`

**Mudanças:**
- Verificação do status da resposta antes de parsear JSON
- Tratamento de respostas vazias ou inválidas
- Mensagens de erro mais claras

**Código Adicionado:**
```javascript
.then(response => {
    return response.text().then(text => {
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor (status ' + response.status + ')');
        }
        // ... tratamento de JSON
    });
})
```

**Resultado:** ✅ Melhorou feedback ao usuário, mas erro 500 persiste

---

### Tentativa 5: Adição de Handler de Erros Fatais no `index.php`
**Data:** Quarta iteração  
**Arquivo:** `public/index.php`

**Mudanças:**
- Adicionado `register_shutdown_function` para capturar erros fatais
- Detecção automática de requisições AJAX
- Retorno de JSON para rotas AJAX em caso de erro

**Código Adicionado:**
```php
register_shutdown_function(function() use ($path) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // ... tratamento de erro fatal
    }
});
```

**Resultado:** ❌ Problema persiste, mas agora retorna JSON válido em caso de erro fatal

---

### Tentativa 6: Adição de Logs Detalhados
**Data:** Quinta iteração  
**Arquivos:** 
- `src/Controllers/HostingController.php`
- `src/Core/Router.php`
- `public/index.php`

**Mudanças:**
- Substituído `error_log` por `pixelhub_log` para garantir escrita no arquivo de log
- Adicionados logs em cada etapa crítica:
  - Router::dispatch (verifica se rota é encontrada)
  - Router::executeHandler (verifica instanciação do controller)
  - HostingController::view (cada etapa do método)

**Logs Adicionados:**
```php
// No Router
pixelhub_log("Router::dispatch: Buscando rota {$method} {$path}");
pixelhub_log("Router: Tentando executar {$controllerClass}@{$method}");

// No HostingController
pixelhub_log("HostingController@view: Iniciando");
pixelhub_log("HostingController@view: Verificando autenticação");
pixelhub_log("HostingController@view: Obtendo conexão DB");
// ... etc
```

**Resultado:** ⚠️ Logs adicionados, mas **NENHUM log aparece no arquivo `logs/pixelhub.log`**

**Observação Crítica:** A ausência de logs indica que:
1. O código não está sendo executado, OU
2. A função `pixelhub_log` não está disponível no contexto, OU
3. O erro ocorre antes mesmo do Router ser chamado

---

## 📊 Análise dos Logs

### Logs Coletados

**Arquivo:** `logs/pixelhub.log`

**Conteúdo Observado:**
- Apenas logs de `BASE_PATH definido como: '/painel.pixel12digital/public'`
- **NENHUM log do Router**
- **NENHUM log do HostingController**
- **NENHUM log de erro**

**Últimas Entradas:**
```
[2025-11-20 09:25:49] BASE_PATH definido como: '/painel.pixel12digital/public' (scriptDir: '/painel.pixel12digital/public')
```

**Análise:**
- O `index.php` está sendo executado (BASE_PATH é definido)
- Mas nenhum log subsequente aparece, indicando que:
  - O Router não está sendo chamado, OU
  - O erro ocorre antes do Router, OU
  - Os logs não estão sendo escritos por algum motivo

### Logs do Apache/PHP

**Arquivo:** `C:\xampp\apache\logs\error.log`
- Apenas logs de inicialização do Apache
- Nenhum erro relacionado ao PHP

**Arquivo:** `C:\xampp\php\logs\php_error_log`
- Arquivo não encontrado ou vazio

---

## 🔬 Hipóteses sobre a Causa Raiz

### Hipótese 1: Erro Fatal Antes do Router
**Probabilidade:** 🔴 Alta

**Evidências:**
- Nenhum log do Router aparece
- BASE_PATH é definido, mas nada mais acontece
- Resposta 500 vazia ou genérica

**Possíveis Causas:**
- Erro de sintaxe PHP não detectado
- Erro ao carregar classe via autoload
- Erro ao instanciar `HostingController`
- Erro fatal em dependência (DB, Auth, etc.)

### Hipótese 2: Problema no Autoload
**Probabilidade:** 🟡 Média

**Evidências:**
- Controller pode não estar sendo encontrado
- Namespace pode estar incorreto

**Verificação Necessária:**
- Verificar se `HostingController` está no namespace correto
- Verificar se o autoload está funcionando

### Hipótese 3: Erro na Conexão com Banco de Dados
**Probabilidade:** 🟡 Média

**Evidências:**
- Método `view()` acessa o banco de dados
- Erro pode ocorrer em `DB::getConnection()`

**Verificação Necessária:**
- Verificar configuração do banco de dados
- Verificar se a tabela `hosting_accounts` existe
- Verificar se a tabela `hosting_providers` existe

### Hipótese 4: Problema com HostingProviderService
**Probabilidade:** 🟡 Média

**Evidências:**
- Método `view()` chama `HostingProviderService::getSlugToNameMap()`
- Se a tabela `hosting_providers` não existir, pode causar erro

**Verificação Necessária:**
- Verificar se a tabela `hosting_providers` existe
- Verificar se há dados na tabela

### Hipótese 5: Erro de Permissões ou Sessão
**Probabilidade:** 🟢 Baixa

**Evidências:**
- Método verifica autenticação
- Mas deveria retornar 401/403, não 500

---

## 📝 Código Relevante

### Método `view()` Atual

**Arquivo:** `src/Controllers/HostingController.php`  
**Linhas:** 354-567

```php
public function view(): void
{
    // Log para debug usando pixelhub_log se disponível
    if (function_exists('pixelhub_log')) {
        pixelhub_log("HostingController@view: Iniciando");
    } else {
        @error_log("HostingController@view: Iniciando");
    }
    
    // Limpa qualquer output anterior
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Desabilita exibição de erros para não quebrar JSON
    $oldDisplayErrors = ini_get('display_errors');
    $oldErrorReporting = error_reporting();
    ini_set('display_errors', '0');
    error_reporting(0);
    
    try {
        // Verifica autenticação
        if (!Auth::check()) {
            // ... retorna 401
        }
        
        if (!Auth::isInternal()) {
            // ... retorna 403
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            // ... retorna 400
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $hostingAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hostingAccount) {
            // ... retorna 404
        }

        // Busca nome do provedor
        $providerMap = HostingProviderService::getSlugToNameMap();
        // ... resto do código
    } catch (\Throwable $e) {
        // ... tratamento de erro
    }
}
```

### Rota Definida

**Arquivo:** `public/index.php`  
**Linha:** 182

```php
$router->get('/hosting/view', 'HostingController@view');
```

---

## 🎯 Próximos Passos Recomendados

### 1. Verificação Imediata (Alta Prioridade)

#### 1.1. Habilitar Exibição de Erros Temporariamente
```php
// Em public/index.php, temporariamente:
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

**Objetivo:** Ver o erro real na tela

#### 1.2. Verificar Estrutura do Banco de Dados
```sql
-- Verificar se tabelas existem
SHOW TABLES LIKE 'hosting_accounts';
SHOW TABLES LIKE 'hosting_providers';

-- Verificar estrutura
DESCRIBE hosting_accounts;
DESCRIBE hosting_providers;

-- Verificar dados
SELECT COUNT(*) FROM hosting_accounts;
SELECT COUNT(*) FROM hosting_providers;
```

#### 1.3. Testar Endpoint Diretamente
```bash
# Via curl ou Postman
curl -X GET "http://localhost/painel.pixel12digital/public/hosting/view?id=1" \
  -H "Cookie: PHPSESSID=..." \
  -v
```

### 2. Debugging Avançado (Média Prioridade)

#### 2.1. Criar Endpoint de Teste Simples
```php
// Em public/index.php, adicionar temporariamente:
$router->get('/hosting/test', function() {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'Router funcionando']);
    exit;
});
```

**Objetivo:** Verificar se o Router está funcionando

#### 2.2. Testar Instanciação do Controller
```php
// Criar arquivo de teste: test_hosting_controller.php
<?php
require_once __DIR__ . '/public/index.php';
// ... código para testar instanciação
```

#### 2.3. Verificar Logs do PHP
- Verificar `php.ini` para localização do `error_log`
- Verificar se `log_errors = On`
- Verificar permissões de escrita nos diretórios de log

### 3. Análise de Código (Baixa Prioridade)

#### 3.1. Revisar Autoload
- Verificar se todas as classes estão sendo carregadas corretamente
- Verificar namespaces

#### 3.2. Revisar Dependências
- Verificar se todas as classes usadas existem
- Verificar imports

---

## 📋 Checklist de Diagnóstico

- [ ] Erro aparece na tela quando `display_errors = 1`
- [ ] Tabela `hosting_accounts` existe e tem dados
- [ ] Tabela `hosting_providers` existe e tem dados
- [ ] Endpoint `/hosting/test` funciona
- [ ] Controller pode ser instanciado manualmente
- [ ] `DB::getConnection()` funciona
- [ ] `Auth::check()` retorna true
- [ ] `HostingProviderService::getSlugToNameMap()` funciona
- [ ] Logs aparecem quando `pixelhub_log` é chamado diretamente
- [ ] Permissões de escrita nos diretórios estão corretas

---

## 🔧 Comandos Úteis para Diagnóstico

### Verificar Logs em Tempo Real
```powershell
# Windows PowerShell
Get-Content logs\pixelhub.log -Wait -Tail 20
```

### Verificar Sintaxe PHP
```bash
php -l src/Controllers/HostingController.php
```

### Testar Conexão com Banco
```php
<?php
require 'config/database.php';
$db = new PDO(...);
// testar conexão
```

### Verificar Sessão
```php
<?php
session_start();
var_dump($_SESSION);
```

---

## 📚 Arquivos Modificados Durante a Investigação

1. **src/Controllers/HostingController.php**
   - Adicionado `use PDO;`
   - Melhorado tratamento de erros
   - Corrigida indentação em `$calculateStatus`
   - Adicionados logs detalhados
   - Adicionado tratamento de erro em `HostingProviderService`

2. **views/tenants/view.php**
   - Melhorado tratamento de erros no JavaScript
   - Adicionada verificação de status da resposta

3. **public/index.php**
   - Adicionado `register_shutdown_function` para erros fatais
   - Melhorado tratamento de exceções
   - Adicionados logs

4. **src/Core/Router.php**
   - Adicionados logs em `dispatch()` e `executeHandler()`

---

## 🚨 Observações Importantes

1. **Ausência de Logs:** O fato de nenhum log aparecer é extremamente suspeito e indica que o código pode não estar sendo executado ou há um problema fundamental no sistema de logging.

2. **Erro Genérico:** O erro 500 genérico sem detalhes torna o diagnóstico difícil. É necessário habilitar exibição de erros temporariamente.

3. **Possível Erro Fatal:** Se for um erro fatal (E_ERROR, E_PARSE), ele pode estar sendo capturado pelo `register_shutdown_function`, mas os logs podem não estar sendo escritos.

4. **Dependências:** O método `view()` depende de várias classes e serviços. Qualquer falha em uma delas causaria erro 500.

---

## 💡 Recomendações Finais

1. **Prioridade Máxima:** Habilitar exibição de erros e ver o erro real
2. **Verificar Banco de Dados:** Confirmar que todas as tabelas necessárias existem
3. **Testar Isoladamente:** Criar endpoint de teste simples para isolar o problema
4. **Revisar Logs do Sistema:** Verificar logs do Apache e PHP do sistema operacional
5. **Considerar Rollback:** Se necessário, reverter para versão anterior que funcionava

---

## 📞 Informações de Contato

**Desenvolvedor Responsável:** Assistente AI (Auto)  
**Data do Relatório:** 20/11/2025  
**Última Atualização:** 20/11/2025 09:30

---

**Status Atual:** 🔴 **PROBLEMA NÃO RESOLVIDO - REQUER INTERVENÇÃO DE DESENVOLVEDOR SÊNIOR**

---

## 🔄 Atualização: Segunda Tentativa de Correção

**Data:** 25/01/2025  
**Status:** 🔴 **EM INVESTIGAÇÃO - display_errors HABILITADO**

### Ações Realizadas

1. **Habilitado display_errors temporariamente** em `public/index.php`:
   - `ini_set('display_errors', '1')` e `error_reporting(E_ALL)` habilitados
   - Isso permite ver o erro PHP real na tela do navegador

2. **Verificada estrutura do banco de dados**:
   - ✅ Tabela `hosting_accounts` existe e tem todas as colunas necessárias
   - ✅ Tabela `hosting_providers` existe e tem dados (3 provedores)
   - ✅ Todas as migrations foram aplicadas corretamente
   - **Conclusão:** O problema NÃO é estrutura do banco

3. **Melhorado tratamento de erros** em `HostingController`:
   - Métodos `view()` e `edit()` agora exibem erro detalhado quando `display_errors = 1`
   - Erros são logados com stack trace completo
   - Em modo debug, mostra HTML com detalhes do erro

4. **Criados scripts de diagnóstico**:
   - `database/check-hosting-accounts-structure.php` - Verifica estrutura da tabela
   - `database/check-hosting-providers.php` - Verifica tabela de provedores

### Próximos Passos

**IMPORTANTE:** Com `display_errors = 1` habilitado, o erro real deve aparecer na tela do navegador.

1. **Acesse diretamente no navegador (local):**
   ```
   http://localhost/painel.pixel12digital/public/hosting/view?id=1
   http://localhost/painel.pixel12digital/public/hosting/edit?id=1&tenant_id=2&redirect_to=tenant
   ```

2. **Copie o erro PHP EXATO** que aparecer (mensagem, arquivo, linha)

3. **Adicione o erro na seção abaixo** "Erro real exibido com display_errors = 1"

4. **Após identificar o erro real**, corrigir a causa raiz

---

## 📝 Erro Real Exibido com display_errors = 1

**Data do Erro:** 25/01/2025  
**URL Acessada:** `http://localhost/painel.pixel12digital/public/hosting/view?id=1`  
**Mensagem de Erro:** 

```
Fatal error: Declaration of PixelHub\Controllers\HostingController::view(): void 
must be compatible with PixelHub\Core\Controller::view(string $view, array $data = []): void 
in C:\xampp\htdocs\painel.pixel12digital\src\Controllers\HostingController.php on line 415
```

**Tipo de Erro:** E_COMPILE_ERROR (64)

**Causa Raiz Identificada:**
- O método `view()` no `HostingController` estava declarado como `public function view(): void` (sem parâmetros)
- Mas a classe pai `Controller` tem um método `protected function view(string $view, array $data = []): void` (com parâmetros)
- O PHP 8+ exige que métodos sobrescritos tenham assinaturas compatíveis
- Como o método do `HostingController` é público e o do `Controller` é protegido, e as assinaturas são diferentes, ocorre erro fatal de compilação

**Correção Aplicada:**
- Renomeado o método `view()` do `HostingController` para `show()` para evitar conflito
- Atualizada a rota em `public/index.php` de `HostingController@view` para `HostingController@show`
- O JavaScript que chama `/hosting/view` continua funcionando (a rota permanece a mesma, apenas o método interno mudou)

---

## 🔧 Correções Aplicadas na Segunda Tentativa

### 1. Ajustado `register_shutdown_function` em `public/index.php`
- Agora verifica se `display_errors = 1` antes de retornar erro genérico
- Quando `display_errors = 1`, mostra erro detalhado com HTML formatado

### 2. Ajustado `try/catch` em `public/index.php`
- Agora verifica se `display_errors = 1` antes de retornar erro genérico
- Quando `display_errors = 1`, mostra erro detalhado com stack trace

### 3. Melhorado tratamento de erros no `Router.php`
- Adicionado tratamento para exibir erro detalhado quando `display_errors = 1`
- Logs mais detalhados em cada etapa

### 4. Criado script de teste
- `public/test-hosting-controller.php` - Testa isoladamente se HostingController funciona
- Acesse: `http://localhost/painel.pixel12digital/public/test-hosting-controller.php`

### Próximos Passos

1. **Acesse o script de teste primeiro:**
   ```
   http://localhost/painel.pixel12digital/public/test-hosting-controller.php
   ```
   Isso vai verificar se o problema está no controller ou na rota.

2. **Acesse novamente as URLs problemáticas:**
   ```
   http://localhost/painel.pixel12digital/public/hosting/view?id=1
   http://localhost/painel.pixel12digital/public/hosting/edit?id=1&tenant_id=2&redirect_to=tenant
   ```
   Com as correções aplicadas, o erro real deve aparecer agora.

3. **Copie o erro PHP EXATO** e adicione na seção acima.

---

**Status Atual:** 🔴 **AGUARDANDO ERRO REAL - CORREÇÕES APLICADAS**

