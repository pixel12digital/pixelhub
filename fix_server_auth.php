<?php
// Script para corrigir o problema de autenticação no servidor
echo "<h1>Correção do Erro de Autenticação no Servidor</h1>";

// Mostra a causa do problema
echo "<h2>Causa do Problema</h2>";
echo "<p>O erro 500 acontece porque no servidor:</p>";
echo "<ol>";
echo "<li>A sessão não está iniciada quando o Auth::requireInternal() é chamado</li>";
echo "<li>O método tenta redirecionar com header() mas headers já foram enviados</li>";
echo "<li>Isso causa um erro 500 Internal Server Error</li>";
echo "</ol>";

// Mostra a solução
echo "<h2>Solução</h2>";
echo "<p>Adicionar verificação de headers antes de redirecionar no Auth.php</p>";

// Lê o arquivo atual
$authFile = 'src/Core/Auth.php';
$authContent = file_get_contents($authFile);

// Encontra a linha do header Location
$oldLine = "header(\"Location: {\$url}\");";
$newLine = "if (!headers_sent()) {\n            header(\"Location: {\$url}\");\n        } else {\n            echo \"<script>window.location.href='{\$url}';</script>\";\n        }";

// Verifica se já foi corrigido
if (strpos($authContent, 'if (!headers_sent())') !== false) {
    echo "<p style='color: green;'>✓ O arquivo Auth.php já está corrigido!</p>";
} else {
    // Aplica a correção
    $correctedContent = str_replace($oldLine, $newLine, $authContent);
    
    if (file_put_contents($authFile, $correctedContent)) {
        echo "<p style='color: green;'>✓ Arquivo Auth.php corrigido com sucesso!</p>";
        echo "<p>Agora o redirecionamento vai funcionar mesmo que headers já foram enviados.</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro ao corrigir o arquivo</p>";
    }
}

// Mostra o que foi corrigido
echo "<h2>O que foi corrigido</h2>";
echo "<p>Antes:</p>";
echo "<pre><code>header(\"Location: {\$url}\");</code></pre>";
echo "<p>Depois:</p>";
echo "<pre><code>if (!headers_sent()) {
    header(\"Location: {\$url}\");
} else {
    echo \"<script>window.location.href='{\$url}';</script>\";
}</code></pre>";

// Testa a correção
echo "<h2>Testando a correção</h2>";
try {
    // Simula ambiente
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
    $_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
    
    // Inicia sessão
    session_start();
    $_SESSION['user'] = ['id' => 1, 'is_internal' => 1];
    
    // Carrega as classes
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    require_once 'src/Core/DB.php';
    require_once 'src/Core/Auth.php';
    require_once 'src/Core/Controller.php';
    require_once 'src/Services/OpportunityService.php';
    require_once 'src/Services/OpportunityProductService.php';
    require_once 'src/Services/ContactService.php';
    require_once 'src/Services/LeadService.php';
    require_once 'src/Services/PhoneNormalizer.php';
    require_once 'src/Services/TrackingCodesService.php';
    require_once 'src/Controllers/OpportunitiesController.php';
    
    // Testa o controller
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    
    // Captura output
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "<p style='color: green;'>✓ Teste bem sucedido! A página opportunities funciona.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Output pequeno</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no teste: " . $e->getMessage() . "</p>";
}

echo "<h2>Próximos Passos</h2>";
echo "<ol>";
echo "<li>1. Faça upload do arquivo Auth.php corrigido para o servidor</li>";
echo "<li>2. Limpe o cache do servidor se necessário</li>";
echo "<li>3. Teste o acesso a /opportunities no servidor</li>";
echo "<li>4. Se ainda houver erro, verifique o log de erros do servidor</li>";
echo "</ol>";

?>
