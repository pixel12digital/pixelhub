<?php

/**
 * Script para verificar permissões do usuário MySQL
 */

require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;

Env::load();

$host = Env::get('DB_HOST', 'localhost');
$port = Env::get('DB_PORT', '3306');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');
$database = Env::get('DB_NAME', 'pixel_hub');

echo "=== Verificação de Permissões MySQL ===\n\n";
echo "Configurações:\n";
echo "  Host: {$host}\n";
echo "  Usuário: {$username}\n";
echo "  Banco: {$database}\n\n";

echo "PROBLEMA IDENTIFICADO:\n";
echo "O usuário '{$username}' não tem permissão para acessar o banco '{$database}' remotamente.\n\n";

echo "SOLUÇÃO:\n";
echo "Execute os seguintes comandos SQL no servidor MySQL remoto:\n\n";
echo "Opção 1: Permitir acesso de qualquer IP (menos seguro, mas funcional):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'%';\n";
echo "FLUSH PRIVILEGES;\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Opção 2: Permitir acesso apenas do seu IP específico (mais seguro):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'179.187.207.148';\n";
echo "FLUSH PRIVILEGES;\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "ONDE EXECUTAR:\n";
echo "1. Via phpMyAdmin: Acesse o phpMyAdmin do servidor remoto e vá na aba 'SQL'\n";
echo "2. Via SSH: Conecte-se ao servidor via SSH e execute: mysql -u root -p\n";
echo "3. Via cPanel: Use o MySQL Database ou phpMyAdmin do cPanel\n\n";

echo "ARQUIVO GERADO:\n";
echo "Um arquivo SQL foi criado em: database/fix-remote-permissions.sql\n";
echo "Você pode copiar o conteúdo desse arquivo e executar no servidor.\n\n";

