<?php
/**
 * Diagnóstico: Áudio outbound enviado ao 81642320 (cliente que recebe "áudio não disponível")
 *
 * Objetivo: Investigar no banco e código o que podemos verificar sobre envios de áudio
 * para o número 555381642320, comparando com outros destinatários.
 *
 * Uso: php database/diagnostico-audio-outbound-81642320.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

echo "=== DIAGNÓSTICO: ÁUDIO OUTBOUND - Cliente 81642320 (áudio não disponível) ===\n\n";

try {
    $db = DB::getConnection();
    echo "✅ Conexão com banco OK\n\n";

    $targetNumber = '555381642320';

    // 1. Áudios outbound enviados AO 81642320
    echo "1. ÁUDIOS OUTBOUND ENVIADOS AO 81642320:\n";
    echo str_repeat("-", 80) . "\n";

    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.conversation_id,
            ce.tenant_id,
            ce.created_at,
            ce.payload,
            ce.metadata,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as to_number,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) as msg_type
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.outbound.message'
        AND (
            JSON_EXTRACT(ce.payload, '$.message.type') = '\"audio\"'
            OR JSON_EXTRACT(ce.payload, '$.type') = '\"audio\"'
        )
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE '%81642320%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE '%555381642320%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%81642320%'
        )
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $eventsTo8164 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($eventsTo8164)) {
        echo "   ❌ Nenhum áudio outbound encontrado para 81642320\n";
        echo "   (Pode ser que o payload use estrutura diferente - verifique manualmente)\n\n";
    } else {
        echo "   ✅ Encontrados " . count($eventsTo8164) . " áudio(s) outbound para 81642320:\n\n";
        foreach ($eventsTo8164 as $i => $ev) {
            echo "   Evento " . ($i + 1) . ":\n";
            echo "     event_id: {$ev['event_id']}\n";
            echo "     created_at: {$ev['created_at']}\n";
            echo "     conversation_id: " . ($ev['conversation_id'] ?: 'NULL') . "\n";
            echo "     to: {$ev['to_number']}\n";

            $mediaStmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
            $mediaStmt->execute([$ev['event_id']]);
            $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
            if ($media) {
                echo "     mídia: id={$media['id']}, file_size={$media['file_size']} bytes\n";
                echo "     stored_path: {$media['stored_path']}\n";
                $fullPath = __DIR__ . '/../storage/' . $media['stored_path'];
                echo "     arquivo existe: " . (file_exists($fullPath) ? '✅ SIM' : '❌ NÃO') . "\n";
            } else {
                echo "     mídia: ❌ SEM REGISTRO\n";
            }
            echo "\n";
        }
    }

    // 2. Áudios outbound para OUTROS números (comparação)
    echo "\n2. ÁUDIOS OUTBOUND PARA OUTROS NÚMEROS (últimos 10, para comparação):\n";
    echo str_repeat("-", 80) . "\n";

    $stmt2 = $db->query("
        SELECT 
            ce.event_id,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as to_number,
            cm.file_size,
            cm.stored_path
        FROM communication_events ce
        LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
        WHERE ce.event_type = 'whatsapp.outbound.message'
        AND (
            JSON_EXTRACT(ce.payload, '$.message.type') = '\"audio\"'
            OR JSON_EXTRACT(ce.payload, '$.type') = '\"audio\"'
        )
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) NOT LIKE '%81642320%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) IS NULL
        )
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $eventsOthers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if (empty($eventsOthers)) {
        echo "   ❌ Nenhum áudio outbound para outros números\n";
    } else {
        foreach ($eventsOthers as $ev) {
            $to = $ev['to_number'] ?: 'NULL';
            $size = $ev['file_size'] ?: 'N/A';
            $path = $ev['stored_path'] ?: 'N/A';
            $exists = $ev['stored_path'] && file_exists(__DIR__ . '/../storage/' . $ev['stored_path']) ? '✅' : '❌';
            echo "   {$ev['created_at']} | to={$to} | size={$size} bytes | {$exists}\n";
        }
    }

    // 3. Verificar estrutura do payload (um exemplo)
    echo "\n3. ESTRUTURA DO PAYLOAD (exemplo de áudio outbound):\n";
    echo str_repeat("-", 80) . "\n";

    $stmt3 = $db->query("
        SELECT ce.payload, ce.metadata
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.outbound.message'
        AND (
            JSON_EXTRACT(ce.payload, '$.message.type') = '\"audio\"'
            OR JSON_EXTRACT(ce.payload, '$.type') = '\"audio\"'
        )
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    $sample = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($sample) {
        $payload = json_decode($sample['payload'], true);
        $payloadKeys = array_keys($payload);
        echo "   Campos no payload: " . implode(', ', $payloadKeys) . "\n";
        echo "   (NÃO armazenamos audio_format/audio_mime - se WebM foi enviado, não sabemos pelo banco)\n";
    }

    // 4. Checklist de investigação
    echo "\n4. CHECKLIST DE INVESTIGAÇÃO (ver docs/INVESTIGACAO_AUDIO_OUTBOUND_81642320.md):\n";
    echo str_repeat("-", 80) . "\n";
    echo "   [ ] Rodar este script em produção (banco remoto)\n";
    echo "   [ ] Buscar nos logs: grep '555381642320' + 'sendAudioBase64Ptt' + 'audio_mime'\n";
    echo "   [ ] Se audio_mime=audio/webm aparecer: WebM foi enviado (gateway converte na VPS)\n";
    echo "   [ ] Comparar file_size: áudios ao 8164 vs outros - algum padrão?\n";
    echo "   [ ] Verificar se arquivo .ogg existe em storage para os eventos ao 8164\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
