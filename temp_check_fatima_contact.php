<?php

// Carrega bootstrap do PixelHub
require_once __DIR__ . '/public/index.php';

// Carrega apenas o necessário para DB
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Inicia sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'src/Core/DB.php';
$db = PixelHub\Core\DB::getConnection();

// Buscar conversa da Fátima pelo telefone final 1354
try {
    $stmt = $db->prepare("
        SELECT id, contact_external_id, channel_id, last_message_at
        FROM conversations
        WHERE REPLACE(REPLACE(contact_external_id, '+', ''), ' ', '') LIKE '%6185721354%'
        ORDER BY last_message_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Resultados da consulta:\n";
    foreach ($results as $row) {
        echo "ID: {$row['id']}, Contact: {$row['contact_external_id']}, Channel: {$row['channel_id']}, Last Msg: {$row['last_message_at']}\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
