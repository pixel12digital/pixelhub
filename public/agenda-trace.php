<?php
/**
 * Rastreia erro na rota /agenda - acesse via /agenda-trace.php no navegador (logado).
 * Remove após identificar o problema.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Força exibição de erros mesmo se APP_DEBUG estiver off
$_ENV['APP_DEBUG'] = '1';

// Redireciona o fluxo para /agenda (preserva query string se houver)
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
$_SERVER['REQUEST_URI'] = '/agenda' . $qs;

// Inclui o index que fará o dispatch para /agenda
require __DIR__ . '/index.php';
