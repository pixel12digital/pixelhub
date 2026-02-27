<?php
/**
 * Endpoint temporário para salvar config Meta
 * Workaround para nginx que não redireciona rotas
 */

// Simula a rota correta
$_SERVER['REQUEST_URI'] = '/settings/whatsapp-providers/meta/save';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Carrega o index.php que vai processar a rota
require_once __DIR__ . '/index.php';
