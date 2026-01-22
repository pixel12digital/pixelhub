<?php
/**
 * Script simples para verificar se o deploy foi feito corretamente
 * Acesse: https://hub.pixel12digital.com.br/public/verificar-deploy.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Verificação de Deploy ===\n\n";

// Verifica se HostingController tem método show()
$controllerFile = __DIR__ . '/../src/Controllers/HostingController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    if (strpos($content, 'public function show(): void') !== false) {
        echo "✓ Método show() encontrado no HostingController\n";
    } else {
        echo "✗ Método show() NÃO encontrado\n";
    }
    
    if (strpos($content, 'public function view(): void') !== false) {
        echo "⚠ ATENÇÃO: Método view() ainda existe (pode causar conflito)\n";
    } else {
        echo "✓ Método view() não existe (correto)\n";
    }
} else {
    echo "✗ Arquivo HostingController.php não encontrado\n";
}

// Verifica se a rota está correta
$indexFile = __DIR__ . '/index.php';
if (file_exists($indexFile)) {
    $content = file_get_contents($indexFile);
    
    if (strpos($content, "HostingController@show") !== false) {
        echo "✓ Rota configurada: HostingController@show\n";
    } elseif (strpos($content, "HostingController@view") !== false) {
        echo "✗ Rota ainda usa: HostingController@view (PRECISA ATUALIZAR)\n";
    } else {
        echo "✗ Rota /hosting/view não encontrada\n";
    }
} else {
    echo "✗ Arquivo index.php não encontrado\n";
}

echo "\n=== Fim da Verificação ===\n";

