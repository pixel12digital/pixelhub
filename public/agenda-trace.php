<?php
/**
 * Rastreia erro na rota /agenda - acesse via /agenda-trace.php (logado).
 * O index.php detecta agenda-trace e força display_errors para exibir o erro na tela.
 * Remove após identificar o problema.
 */

$_SERVER['REQUEST_URI'] = '/agenda' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');

require __DIR__ . '/index.php';
