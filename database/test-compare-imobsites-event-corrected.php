<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== TESTE: Comparar evento ImobSites com conversation 34 (caminhos corretos) ===\n\n";

// Buscando nos caminhos corretos do JSON (message.from, message.to, message.text, etc)
$sql = "SELECT
  ce.id,
  ce.created_at,
  JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
  ce.event_type,
  COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.from')),
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.message.from')),
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.raw.payload.from'))
  ) AS from_id,
  COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.to')),
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.message.to')),
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.raw.payload.to'))
  ) AS to_id,
  COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.body')),
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.message.text')),
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload,'$.raw.payload.body'))
  ) AS body
FROM communication_events ce
WHERE ce.tenant_id = 2
  AND JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = 'ImobSites'
  AND ce.event_type = 'whatsapp.inbound.message'
ORDER BY ce.id DESC
LIMIT 20";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ " . count($events) . " evento(s) encontrado(s):\n\n";
    echo str_repeat("=", 160) . "\n";
    echo sprintf("%-8s | %-19s | %-25s | %-35s | %-50s | %-50s | %-70s\n",
        "ID", "CREATED_AT", "CHANNEL_ID", "EVENT_TYPE", "FROM_ID", "TO_ID", "BODY");
    echo str_repeat("-", 160) . "\n";
    
    foreach ($events as $e) {
        echo sprintf("%-8s | %-19s | %-25s | %-35s | %-50s | %-50s | %-70s\n",
            $e['id'],
            $e['created_at'],
            $e['channel_id'] ?: 'NULL',
            substr($e['event_type'], 0, 33),
            substr($e['from_id'] ?: 'NULL', 0, 48),
            substr($e['to_id'] ?: 'NULL', 0, 48),
            substr($e['body'] ?: 'NULL', 0, 68)
        );
    }
    
    echo str_repeat("=", 160) . "\n\n";
    
    // Análise detalhada
    echo "=== ANÁLISE DETALHADA ===\n\n";
    
    $fromIdFormats = [];
    
    foreach ($events as $e) {
        $fromId = $e['from_id'];
        $toId = $e['to_id'];
        $body = $e['body'];
        
        echo "📋 Evento ID {$e['id']} ({$e['created_at']}):\n";
        
        // FROM_ID
        echo "   FROM_ID: " . ($fromId ?: 'NULL');
        if ($fromId) {
            if (strpos($fromId, '@lid') !== false) {
                echo " → formato: @lid ❌ (precisa resolver para número)\n";
                // Extrair pnLid
                $pnLid = preg_replace('/[^0-9]/', '', $fromId);
                echo "      pnLid extraído: {$pnLid}\n";
                $fromIdFormats['@lid'][] = $pnLid;
            } elseif (strpos($fromId, '@c.us') !== false) {
                echo " → formato: @c.us ✅ (tem número, precisa extrair)\n";
                $phone = preg_replace('/[^0-9]/', '', $fromId);
                echo "      telefone extraído: {$phone}\n";
                $fromIdFormats['@c.us'][] = $phone;
            } elseif (preg_match('/^[0-9]+$/', $fromId)) {
                echo " → formato: número puro ✅ (E.164 direto)\n";
                $fromIdFormats['number'][] = $fromId;
            } else {
                echo " → formato: outro ('{$fromId}')\n";
            }
        } else {
            echo " ❌ (ausente)\n";
            $fromIdFormats['null'][] = $e['id'];
        }
        
        // TO_ID
        echo "   TO_ID: " . ($toId ?: 'NULL');
        if ($toId) {
            if (strpos($toId, '@c.us') !== false) {
                echo " → formato: @c.us ✅ (número do canal)\n";
                $channelPhone = preg_replace('/[^0-9]/', '', $toId);
                echo "      telefone do canal: {$channelPhone}\n";
            } elseif (preg_match('/^[0-9]+$/', $toId)) {
                echo " → formato: número puro\n";
            } else {
                echo " → formato: outro ('{$toId}')\n";
            }
        } else {
            echo " (ausente)\n";
        }
        
        // BODY
        echo "   BODY: " . ($body ? substr($body, 0, 80) : 'NULL');
        if ($body && (
            stripos($body, 'teste') !== false || 
            stripos($body, 'TESTE') !== false ||
            stripos($body, 'imobsites') !== false
        )) {
            echo " ✅ (contém texto de teste)\n";
        } else {
            echo "\n";
        }
        
        echo "\n";
    }
    
    // Resumo de formatos
    echo "=== RESUMO DE FORMATOS ===\n\n";
    echo "FROM_ID por formato:\n";
    if (isset($fromIdFormats['@lid'])) {
        echo "  @lid: " . count($fromIdFormats['@lid']) . " eventos\n";
        echo "    pnLids: " . implode(', ', array_unique($fromIdFormats['@lid'])) . "\n";
    }
    if (isset($fromIdFormats['@c.us'])) {
        echo "  @c.us: " . count($fromIdFormats['@c.us']) . " eventos\n";
        echo "    telefones: " . implode(', ', array_unique($fromIdFormats['@c.us'])) . "\n";
    }
    if (isset($fromIdFormats['number'])) {
        echo "  número puro: " . count($fromIdFormats['number']) . " eventos\n";
        echo "    números: " . implode(', ', array_unique($fromIdFormats['number'])) . "\n";
    }
    if (isset($fromIdFormats['null'])) {
        echo "  NULL: " . count($fromIdFormats['null']) . " eventos\n";
    }
    
    // Comparar com conversation 34
    echo "\n=== COMPARAÇÃO COM CONVERSATION 34 ===\n\n";
    
    $convSql = "SELECT id, channel_id, contact_external_id, tenant_id, updated_at 
                FROM conversations WHERE id = 34";
    $convStmt = $pdo->query($convSql);
    $conversation = $convStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        echo "Conversation 34:\n";
        echo "  Channel ID: {$conversation['channel_id']}\n";
        echo "  Contact External ID: {$conversation['contact_external_id']}\n";
        echo "  Updated At: {$conversation['updated_at']}\n\n";
        
        // Verificar compatibilidade
        $contactId = $conversation['contact_external_id'];
        $eventsWithMatchingContact = [];
        $eventsWithLid = [];
        
        foreach ($events as $e) {
            $fromId = $e['from_id'];
            if (!$fromId) continue;
            
            // Extrair número do from_id
            $fromNumber = null;
            if (strpos($fromId, '@lid') !== false) {
                // @lid precisa ser resolvido
                $pnLid = preg_replace('/[^0-9]/', '', $fromId);
                $eventsWithLid[] = ['id' => $e['id'], 'pnLid' => $pnLid];
                echo "⚠️  Evento ID {$e['id']}: FROM_ID é @lid (pnLid: {$pnLid}), precisa resolver via API\n";
            } elseif (strpos($fromId, '@c.us') !== false) {
                $fromNumber = preg_replace('/[^0-9]/', '', $fromId);
            } elseif (preg_match('/^[0-9]+$/', $fromId)) {
                $fromNumber = $fromId;
            }
            
            if ($fromNumber && strpos($contactId, $fromNumber) !== false) {
                $eventsWithMatchingContact[] = $e['id'];
            }
        }
        
        if (count($eventsWithMatchingContact) > 0) {
            echo "\n✅ Eventos que BATE com contact_external_id: " . implode(', ', $eventsWithMatchingContact) . "\n";
        } else {
            echo "\n❌ Nenhum evento bate diretamente com contact_external_id '{$contactId}'\n";
            if (count($eventsWithLid) > 0) {
                echo "   Motivo: " . count($eventsWithLid) . " evento(s) têm FROM_ID como @lid e precisam ser resolvidos\n";
                echo "   Solução: Chamar /api/{session}/contact/pn-lid/{pnLid} para resolver\n";
            }
        }
    }
    
} else {
    echo "❌ Nenhum evento encontrado.\n";
}

echo "\n";

