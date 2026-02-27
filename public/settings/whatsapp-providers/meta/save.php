<?php
/**
 * Workaround temporário para nginx que não redireciona para index.php
 * Este arquivo será encontrado diretamente pelo nginx
 */

// Redireciona para o index.php com a rota correta
$_SERVER['REQUEST_URI'] = '/settings/whatsapp-providers/meta/save';
$_SERVER['PATH_INFO'] = '/settings/whatsapp-providers/meta/save';

require_once __DIR__ . '/../../../index.php';
