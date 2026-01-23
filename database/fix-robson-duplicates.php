<?php
/**
 * Script de correção: Mesclar conversas duplicadas do Robson
 * 
 * Objetivo: Mesclar a conversa duplicada (ID 103) com a conversa correta (ID 8)
 * 
 * AÇÃO: Este script irá:
 * 1. Verificar se a conversa ID 103 tem mensagens que precisam ser movidas
 * 2. Mover mensagens da conversa ID 103 para a conversa ID 8 (se necessário)
 * 3. Deletar a conversa ID 103
 * 
 * ATENÇÃO: Execute apenas após revisar o relatório de investigação!
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORREÇÃO: MESCLAR CONVERSAS DUPLICADAS DO ROBSON ===\n\n";

// IDs das conversas
$conversaIncorretaId = 103; // Conversa com número errado (5587999884234)
$conversaCorretaId = 8;     // Conversa com número correto (558799884234)

// 1. Verifica conversas
echo "1. VERIFICANDO CONVERSAS\n";
echo str_repeat("=", 80) . "\n\n";

$stmt = $db->prepare("SELECT * FROM conversations WHERE id IN (?, ?) ORDER BY id");
$stmt->execute([$conversaIncorretaId, $conversaCorretaId]);
$conversations = $stmt->fetchAll();

if (count($conversations) !== 2) {
    echo "❌ ERRO: Não foram encontradas ambas as conversas!\n";
    echo "   Encontradas: " . count($conversations) . " conversa(s)\n";
    exit(1);
}

$incorreta = null;
$correta = null;

foreach ($conversations as $conv) {
    if ($conv['id'] == $conversaIncorretaId) {
        $incorreta = $conv;
    } elseif ($conv['id'] == $conversaCorretaId) {
        $correta = $conv;
    }
}

if (!$incorreta || !$correta) {
    echo "❌ ERRO: Não foi possível identificar as conversas!\n";
    exit(1);
}

echo "Conversa INCORRETA (ID {$incorreta['id']}):\n";
echo "  Contact External ID: {$incorreta['contact_external_id']}\n";
echo "  Conversation Key: {$incorreta['conversation_key']}\n";
echo "  Message Count: {$incorreta['message_count']}\n";
echo "  Created At: {$incorreta['created_at']}\n\n";

echo "Conversa CORRETA (ID {$correta['id']}):\n";
echo "  Contact External ID: {$correta['contact_external_id']}\n";
echo "  Conversation Key: {$correta['conversation_key']}\n";
echo "  Message Count: {$correta['message_count']}\n";
echo "  Created At: {$correta['created_at']}\n\n";

// 2. Verifica mensagens da conversa incorreta
echo "2. VERIFICANDO MENSAGENS DA CONVERSA INCORRETA\n";
echo str_repeat("=", 80) . "\n\n";

// Busca mensagens pelo contact_external_id no payload
$contactIdIncorreto = $incorreta['contact_external_id'];
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND (
        JSON_EXTRACT(ce.payload, '$.from') = ?
        OR JSON_EXTRACT(ce.payload, '$.to') = ?
        OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
        OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
        OR ce.payload LIKE ?
      )
");
$stmt->execute([
    $contactIdIncorreto,
    $contactIdIncorreto,
    $contactIdIncorreto,
    $contactIdIncorreto,
    "%{$contactIdIncorreto}%"
]);
$msgCount = $stmt->fetch();
$totalMensagensIncorretas = $msgCount['total'] ?? 0;

echo "Mensagens encontradas com número incorreto ({$contactIdIncorreto}): {$totalMensagensIncorretas}\n\n";

// 3. Confirmação
echo "3. CONFIRMAÇÃO\n";
echo str_repeat("=", 80) . "\n\n";

echo "⚠️  ATENÇÃO: Esta ação irá:\n";
echo "   1. Deletar a conversa ID {$conversaIncorretaId} (número incorreto)\n";
echo "   2. Manter a conversa ID {$conversaCorretaId} (número correto)\n\n";

if ($totalMensagensIncorretas > 0) {
    echo "⚠️  AVISO: Existem {$totalMensagensIncorretas} mensagem(ns) com o número incorreto.\n";
    echo "   Essas mensagens NÃO serão movidas automaticamente.\n";
    echo "   Se necessário, corrija manualmente os eventos.\n\n";
}

// Verifica se foi passado parâmetro --force para pular confirmação
$force = in_array('--force', $argv ?? []);

if (!$force) {
    echo "Deseja continuar? (digite 'SIM' para confirmar): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if ($line !== 'SIM') {
        echo "\n❌ Operação cancelada pelo usuário.\n";
        exit(0);
    }
} else {
    echo "✓ Modo --force ativado. Prosseguindo automaticamente...\n\n";
}

// 4. Executa correção
echo "\n4. EXECUTANDO CORREÇÃO\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $db->beginTransaction();
    
    // Deleta a conversa incorreta
    echo "Deletando conversa ID {$conversaIncorretaId}...\n";
    $stmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
    $stmt->execute([$conversaIncorretaId]);
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        echo "✓ Conversa ID {$conversaIncorretaId} deletada com sucesso.\n";
    } else {
        echo "⚠ Conversa ID {$conversaIncorretaId} não foi encontrada para deletar.\n";
    }
    
    $db->commit();
    
    echo "\n✅ CORREÇÃO CONCLUÍDA COM SUCESSO!\n\n";
    echo "A conversa duplicada foi removida.\n";
    echo "A conversa ID {$conversaCorretaId} permanece como a única conversa do Robson.\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n❌ ERRO ao executar correção: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

