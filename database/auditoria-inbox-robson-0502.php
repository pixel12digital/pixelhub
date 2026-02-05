<?php
/**
 * Auditoria de confiabilidade — Inbox WhatsApp (mensagens ausentes)
 *
 * Contexto: Robson +55 87 9988-4234, 05/02, janela 12:04–12:15
 * Objetivo: Verificar se os 4 itens ausentes existem no banco (webhook → communication_events → UI)
 *
 * Itens ausentes no Inbox:
 * - Áudio 1 (1:59) 12:04
 * - Áudio 2 (0:24) 12:04
 * - Texto D ("Relatorio de turmas teoricas...") 12:13
 * - Imagem 12:15
 *
 * Execução: php database/auditoria-inbox-robson-0502.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$numeroRobson = '558799884234'; // E.164
$padroes = ['%4234%', '%8799884234%', '%558799884234%', '%4234@%'];
$dataAlvo = '2026-02-05'; // 05/02
$janelaInicio = '2026-02-05 12:00:00';
$janelaFim = '2026-02-05 12:25:00';

echo "=== AUDITORIA INBOX — Robson 4234 | 05/02 12:04–12:15 ===\n\n";

// 1. Buscar conversa
$placeholders = implode(' OR ', array_fill(0, count($padroes), 'contact_external_id LIKE ?'));
$stmt = $db->prepare("
    SELECT id, conversation_key, contact_external_id, remote_key, tenant_id, channel_id, last_message_at
    FROM conversations
    WHERE channel_type = 'whatsapp' AND ({$placeholders})
    ORDER BY last_message_at DESC
    LIMIT 1
");
$stmt->execute($padroes);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo "❌ Nenhuma conversa encontrada para 4234.\n";
    echo "   Verifique se o número está em outro formato ou se a conversa existe.\n";
    exit(1);
}

$convId = (int) $conv['id'];
$tenantId = $conv['tenant_id'];
$sessionId = $conv['channel_id'] ?? '';

echo "1. CONVERSA ENCONTRADA\n";
echo "   id={$conv['id']} | contact={$conv['contact_external_id']} | tenant_id=" . ($tenantId ?: 'NULL') . " | channel_id=" . ($sessionId ?: 'NULL') . "\n\n";

// 2. Buscar TODOS os eventos da conversa na janela (por conversation_id OU por payload)
$paramsJanela = [$janelaInicio, $janelaFim, $convId];
foreach ($padroes as $p) {
    $paramsJanela[] = $p;
}
$stmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.event_type, ce.conversation_id, ce.status, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as msg_type,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) as raw_type,
           COALESCE(
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.conversation')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.data.message.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.body'))
           ) as text_preview,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body_preview,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) as msg_body,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
           ce.payload
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= ? AND ce.created_at <= ?
    AND (
        ce.conversation_id = ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
    )
    ORDER BY ce.created_at ASC
");
$stmt->execute([$janelaInicio, $janelaFim, $convId, '%558799884234%', '%558799884234%', '%558799884234%', '%558799884234%']);
$eventosJanela = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Busca AMPLA: eventos na janela por número (sem filtro de conversa) — pega eventos sem conversation_id
$stmt2 = $db->prepare("
    SELECT ce.id, ce.event_id, ce.event_type, ce.conversation_id, ce.status, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as msg_type,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) as raw_type,
           LEFT(COALESCE(
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.conversation')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.data.message.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.data.message.conversation')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.body')),
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.conversation'))
           ), 300) as content_preview
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= ? AND ce.created_at <= ?
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
    )
    ORDER BY ce.created_at ASC
");
$stmt2->execute([$janelaInicio, $janelaFim, '%4234%', '%558799884234%']);
$eventosAmplos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// 4. Identificar os 4 itens ausentes
$textoTurmas = 'Relatorio de turmas teoricas'; // início do texto D

$resultado = [
    'audio_1_1204' => ['desc' => 'Áudio 1 (1:59) 12:04', 'encontrado' => false, 'event_id' => null, 'motivo' => null, 'em_media' => false],
    'audio_2_1204' => ['desc' => 'Áudio 2 (0:24) 12:04', 'encontrado' => false, 'event_id' => null, 'motivo' => null, 'em_media' => false],
    'texto_d_1213' => ['desc' => "Texto D (turmas teóricas) 12:13", 'encontrado' => false, 'event_id' => null, 'motivo' => null],
    'imagem_1215' => ['desc' => 'Imagem 12:15', 'encontrado' => false, 'event_id' => null, 'motivo' => null, 'em_media' => false],
];

$audios1204 = [];
$textosEncontrados = [];
$imagensEncontradas = [];

foreach (array_merge($eventosJanela, $eventosAmplos) as $e) {
    $type = strtolower(($e['raw_type'] ?? $e['msg_type'] ?? '') ?: 'chat');
    $content = $e['text_preview'] ?? $e['body_preview'] ?? $e['msg_body'] ?? $e['content_preview'] ?? '';
    $created = $e['created_at'];
    $h = substr($created, 11, 2);
    $m = (int) substr($created, 14, 2);

    if (in_array($type, ['audio', 'ptt', 'voice'])) {
        if ($h === '12' && $m <= 5) {
            $audios1204[] = ['event_id' => $e['event_id'], 'id' => $e['id'], 'created' => $created, 'type' => $type];
        }
        if ($h === '12' && $m >= 13 && $m <= 14) {
            // áudios 12:13
        }
    }

    if ($type === 'image' || stripos($type, 'image') !== false) {
        if ($h === '12' && $m >= 14) {
            $imagensEncontradas[] = ['event_id' => $e['event_id'], 'id' => $e['id'], 'created' => $created];
        }
    }

    if ($type === 'chat' || $type === 'conversation' || empty($type)) {
        if (stripos($content, $textoTurmas) !== false || stripos($content, 'turmas teoricas') !== false) {
            $textosEncontrados[] = ['event_id' => $e['event_id'], 'id' => $e['id'], 'created' => $created, 'preview' => substr($content, 0, 80)];
        }
    }
}

// Conta áudios às 12:04
$audios1204Unicos = [];
foreach ($audios1204 as $a) {
    $audios1204Unicos[$a['event_id']] = $a;
}
if (count($audios1204Unicos) >= 1) {
    $resultado['audio_1_1204']['encontrado'] = true;
    $resultado['audio_1_1204']['event_id'] = array_values($audios1204Unicos)[0]['event_id'];
    $resultado['audio_1_1204']['motivo'] = 'Existe em communication_events';
}
if (count($audios1204Unicos) >= 2) {
    $resultado['audio_2_1204']['encontrado'] = true;
    $resultado['audio_2_1204']['event_id'] = array_values($audios1204Unicos)[1]['event_id'];
    $resultado['audio_2_1204']['motivo'] = 'Existe em communication_events';
}
if (count($audios1204Unicos) === 1) {
    $resultado['audio_2_1204']['motivo'] = 'Só 1 áudio às 12:04 no banco; o 2º não chegou ou foi deduplicado';
}
if (count($audios1204Unicos) === 0) {
    $resultado['audio_1_1204']['motivo'] = 'Nenhum áudio 12:04 no banco (webhook não chegou ou evento não persistiu)';
    $resultado['audio_2_1204']['motivo'] = 'Nenhum áudio 12:04 no banco';
}

if (!empty($textosEncontrados)) {
    $resultado['texto_d_1213']['encontrado'] = true;
    $resultado['texto_d_1213']['event_id'] = $textosEncontrados[0]['event_id'];
    $resultado['texto_d_1213']['motivo'] = 'Existe em communication_events';
} else {
    // Busca direta no payload (payload é TEXT/JSON)
    $stmtTurmas = $db->prepare("
        SELECT ce.id, ce.event_id, ce.created_at
        FROM communication_events ce
        WHERE ce.conversation_id = ? AND ce.event_type = 'whatsapp.inbound.message'
        AND ce.created_at >= ? AND ce.created_at <= ?
        AND (ce.payload LIKE ? OR ce.payload LIKE ?)
    ");
    $stmtTurmas->execute([$convId, $janelaInicio, $janelaFim, '%turmas%', '%teoricas%']);
    $turmasMatch = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($turmasMatch)) {
        $resultado['texto_d_1213']['encontrado'] = true;
        $resultado['texto_d_1213']['event_id'] = $turmasMatch[0]['event_id'];
        $resultado['texto_d_1213']['motivo'] = 'Existe (encontrado via LIKE no payload)';
    } else {
        $resultado['texto_d_1213']['motivo'] = 'Texto "turmas teóricas" não encontrado no banco';
    }
}

if (!empty($imagensEncontradas)) {
    $resultado['imagem_1215']['encontrado'] = true;
    $resultado['imagem_1215']['event_id'] = $imagensEncontradas[0]['event_id'];
    $resultado['imagem_1215']['motivo'] = 'Existe em communication_events';
    // Verificar se mídia foi baixada
    $imgEvId = $imagensEncontradas[0]['event_id'];
    $stmtImg = $db->prepare("SELECT id, stored_path FROM communication_media WHERE event_id = ?");
    $stmtImg->execute([$imgEvId]);
    $imgMedia = $stmtImg->fetch(PDO::FETCH_ASSOC);
    if ($imgMedia && $imgMedia['stored_path']) {
        $sp = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
        $resultado['imagem_1215']['em_media'] = file_exists($sp . '/' . $imgMedia['stored_path']);
    } else {
        $resultado['imagem_1215']['em_media'] = false;
        $resultado['imagem_1215']['motivo'] = 'Existe em communication_events, mas mídia não baixada';
    }
} else {
    $resultado['imagem_1215']['motivo'] = 'Nenhuma imagem ~12:15 no banco';
}

// 5. Verificar communication_media para áudios e imagem
$todosEventIds = array_values(array_unique(array_filter(array_merge(
    array_column($audios1204, 'event_id'),
    array_column($textosEncontrados, 'event_id'),
    array_column($imagensEncontradas, 'event_id')
))));
if (!empty($todosEventIds)) {
    $ph = implode(',', array_fill(0, count($todosEventIds), '?'));
    $stmtM = $db->prepare("SELECT event_id, media_type, stored_path, file_name, file_size FROM communication_media WHERE event_id IN ($ph)");
    $stmtM->execute(array_values($todosEventIds));
    $medias = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    $mediaPorEvento = [];
    foreach ($medias as $m) {
        $mediaPorEvento[$m['event_id']] = $m;
    }
    $storageRoot = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
    foreach ($resultado as $rk => $rv) {
        if (!empty($rv['event_id']) && isset($mediaPorEvento[$rv['event_id']])) {
            $resultado[$rk]['em_media'] = true;
            $mp = $mediaPorEvento[$rv['event_id']];
            $resultado[$rk]['media_path'] = $mp['stored_path'];
            $resultado[$rk]['file_exists'] = $storageRoot && $mp['stored_path'] && file_exists($storageRoot . '/' . $mp['stored_path']);
        }
    }
}

// 6. Listar TODOS os eventos da janela para análise manual
echo "2. EVENTOS NA JANELA 12:00–12:25 (communication_events)\n";
$todosEventos = [];
$seen = [];
foreach (array_merge($eventosJanela, $eventosAmplos) as $e) {
    if (isset($seen[$e['event_id']])) continue;
    $seen[$e['event_id']] = true;
    $todosEventos[] = $e;
}
usort($todosEventos, function ($a, $b) { return strcmp($a['created_at'], $b['created_at']); });

if (empty($todosEventos)) {
    echo "   ❌ NENHUM evento encontrado na janela.\n";
    echo "   → Hipótese: webhook não chegou OU eventos foram descartados (dedupe) OU conversation_id NULL e padrão de busca não encontrou.\n";
} else {
    echo "   ✓ " . count($todosEventos) . " evento(s):\n\n";
    foreach ($todosEventos as $e) {
        $type = $e['raw_type'] ?? $e['msg_type'] ?? 'chat';
        $dir = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
        $convIdNote = $e['conversation_id'] ? "conv={$e['conversation_id']}" : "conv=NULL";
        $preview = mb_substr($e['text_preview'] ?? $e['body_preview'] ?? $e['msg_body'] ?? $e['content_preview'] ?? '-', 0, 50);
        echo "   - {$e['created_at']} | {$dir} | type={$type} | {$convIdNote} | id={$e['id']}\n";
        echo "     preview: {$preview}\n";
    }
}

// 7. Raio-X
echo "\n3. RAIO-X (tabela de classificação)\n";
echo str_repeat('-', 100) . "\n";
printf("%-35s | %-18s | %-20s | %-25s | %s\n", 'Item', 'webhook/events', 'communication_events', 'messages/UI', 'Motivo provável');
echo str_repeat('-', 100) . "\n";

$ordem = ['audio_1_1204', 'audio_2_1204', 'texto_d_1213', 'imagem_1215'];
foreach ($ordem as $key) {
    if (!isset($resultado[$key])) continue;
    $r = $resultado[$key];
    $ce = $r['encontrado'] ? 'sim' : 'não';
    $ui = $r['encontrado'] ? 'deveria exibir' : 'não';
    if (in_array($key, ['audio_1_1204', 'audio_2_1204', 'imagem_1215']) && $r['encontrado']) {
        $emMedia = $r['em_media'] ?? false;
        if (!$emMedia) {
            $ui = 'evento sim, mídia falhou';
        }
    }
    printf("%-35s | %-18s | %-20s | %-25s | %s\n",
        $r['desc'],
        $ce ? 'chegou' : '?',
        $ce,
        $ui,
        $r['motivo'] ?? '-'
    );
}

// 8. Classificação A/B/C/D
echo "\n4. CLASSIFICAÇÃO POR ITEM\n";
foreach ($ordem as $key) {
    if (!isset($resultado[$key])) continue;
    $r = $resultado[$key];
    if (!$r['encontrado']) {
        echo "   {$r['desc']}: A) não existe registro (perda antes de persistir)\n";
    } elseif (isset($r['em_media']) && $r['em_media'] && isset($r['file_exists']) && !$r['file_exists']) {
        echo "   {$r['desc']}: D) existe mas mídia não baixou; UI pode não mostrar placeholder\n";
    } else {
        echo "   {$r['desc']}: Existe. Se não aparece na UI: C) filtro/consulta/ordenação ou frontend\n";
    }
}

// 9. Causa raiz provável
echo "\n5. CAUSA RAIZ MAIS PROVÁVEL\n";
$nenhum = !$resultado['audio_1_1204']['encontrado'] && !$resultado['audio_2_1204']['encontrado']
    && !$resultado['texto_d_1213']['encontrado'] && !$resultado['imagem_1215']['encontrado'];
if ($nenhum) {
    echo "   - Nenhum dos 4 itens existe no banco.\n";
    echo "   - Hipótese principal: webhook não recebeu OU gateway não enviou na janela 12:04–12:15.\n";
    echo "   - Alternativa: idempotência/dedupe descartou (improvável para conteúdos distintos).\n";
} else {
    $perdidos = array_filter($resultado, fn($r) => !$r['encontrado']);
    $encontrados = array_filter($resultado, fn($r) => $r['encontrado']);
    echo "   - " . count($encontrados) . " item(ns) encontrado(s), " . count($perdidos) . " perdido(s).\n";
    if (!empty($perdidos)) {
        echo "   - Para os perdidos: verificar logs [HUB_WEBHOOK_IN], [HUB_MSG_SAVE], [HUB_MSG_DROP] na janela 12:03–12:16.\n";
    }
}

echo "\n6. PRÓXIMOS PASSOS SUGERIDOS\n";
echo "   - Rodar na VPS: grep 'HUB_WEBHOOK_IN\\|HUB_MSG_SAVE\\|HUB_MSG_DROP' /var/log/... na janela 05/02 12:03–12:16.\n";
echo "   - Verificar se gateway envia webhooks para TODOS os tipos (ptt, audio, image, chat).\n";
echo "   - Se evento existe mas não aparece na UI: conferir filtros em getWhatsAppMessagesFromConversation (remote_key, contact patterns).\n";
echo "\n";
