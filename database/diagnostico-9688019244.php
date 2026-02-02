<?php
/**
 * Diagnóstico: Por que a mensagem com imagem do número 96 8801 9244 não aparece no Inbox?
 * 
 * Execução: php database/diagnostico-9688019244.php
 * Ou via navegador: /database/diagnostico-9688019244.php (se configurado)
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

$numero = '9688019244'; // Número sem DDI para busca flexível
$numeroE164 = '559688019244'; // E.164 Brasil

echo "=== DIAGNÓSTICO: Mensagem do 96 8801 9244 no Inbox ===\n\n";
echo "Número buscado: {$numero} / {$numeroE164}\n\n";

// 1. CONVERSAS com este número
echo "1. CONVERSAS (tabela conversations) com contact_external_id contendo {$numero}:\n";
$stmt = $db->prepare("
    SELECT id, conversation_key, contact_external_id, contact_name, tenant_id, 
           channel_id, status, COALESCE(is_incoming_lead, 0) as is_incoming_lead, last_message_at, message_count, created_at
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
    AND (contact_external_id LIKE ? OR contact_external_id LIKE ? OR contact_external_id LIKE ?)
    ORDER BY last_message_at DESC
");
$stmt->execute(["%{$numero}%", "%{$numeroE164}%", "%55{$numero}%"]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ❌ NENHUMA conversa encontrada para este número.\n";
    echo "   → A conversa nunca foi criada. Possíveis causas:\n";
    echo "     - Webhook nunca recebeu mensagem deste número\n";
    echo "     - Mensagem foi enviada pelo negócio VIA WHATSAPP WEB (não pelo Pixel Hub)\n";
    echo "     - Gateway não está enviando webhooks de mensagens outbound\n";
    echo "     - Número está em formato diferente no payload (ex: @lid)\n\n";
} else {
    echo "   ✓ Encontradas " . count($conversations) . " conversa(s):\n";
    foreach ($conversations as $c) {
        $lead = !empty($c['is_incoming_lead']) ? 'SIM (incoming_lead)' : 'não';
        echo "   - id={$c['id']} | contact={$c['contact_external_id']} | tenant_id=" . ($c['tenant_id'] ?: 'NULL') . " | channel_id=" . ($c['channel_id'] ?: 'NULL') . " | status=" . ($c['status'] ?: 'NULL') . " | is_incoming_lead={$lead} | last_message_at=" . ($c['last_message_at'] ?: 'NULL') . "\n";
    }
    echo "\n";
    
    // Verifica se está em incoming_leads (não aparece no Inbox atual)
    $hasUnlinked = false;
    foreach ($conversations as $c) {
        if (!empty($c['is_incoming_lead']) || empty($c['tenant_id'])) {
            $hasUnlinked = true;
            break;
        }
    }
    if ($hasUnlinked) {
        echo "   ⚠️  CAUSA IDENTIFICADA: Conversa com tenant_id=NULL é 'incoming lead'.\n";
        echo "   O Inbox atual exibe APENAS result.threads (conversas vinculadas).\n";
        echo "   Conversas em result.incoming_leads NÃO são exibidas no Inbox!\n\n";
    }
}

// 2. EVENTOS (communication_events) com from/to contendo o número
echo "2. EVENTOS (communication_events) com from/to contendo {$numero} (últimos 7 dias):\n";
$stmt = $db->prepare("
    SELECT event_id, event_type, conversation_id, status, created_at, 
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_field,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_field,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as msg_type
    FROM communication_events 
    WHERE source_system = 'wpp_gateway'
    AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ? 
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.to') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute(["%{$numero}%", "%{$numero}%", "%{$numero}%", "%{$numero}%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM evento de mensagem encontrado para este número.\n";
    echo "   → O webhook NUNCA recebeu mensagem envolvendo 96 8801 9244.\n";
    echo "   → Se a mensagem foi enviada pelo WhatsApp Web (não pelo Pixel Hub), o gateway pode não enviar webhook de outbound.\n";
    echo "   → Se a mensagem foi INBOUND (cliente enviou), o webhook deveria ter sido disparado.\n\n";
} else {
    echo "   ✓ Encontrados " . count($events) . " evento(s):\n";
    foreach ($events as $e) {
        echo "   - {$e['created_at']} | {$e['event_type']} | type={$e['msg_type']} | from={$e['from_field']} | to={$e['to_field']} | conv_id=" . ($e['conversation_id'] ?: 'NULL') . " | status={$e['status']}\n";
    }
    echo "\n";
}

// 3. Eventos de IMAGEM especificamente
echo "3. EVENTOS DE IMAGEM (type=image) envolvendo {$numero} (últimos 7 dias):\n";
$stmt = $db->prepare("
    SELECT event_id, event_type, conversation_id, created_at,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_field,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_field
    FROM communication_events 
    WHERE source_system = 'wpp_gateway'
    AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (
        JSON_EXTRACT(payload, '$.type') = '\"image\"'
        OR JSON_EXTRACT(payload, '$.message.type') = '\"image\"'
    )
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ? 
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute(["%{$numero}%", "%{$numero}%"]);
$imageEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($imageEvents)) {
    echo "   ❌ Nenhum evento de imagem encontrado para este número.\n\n";
} else {
    echo "   ✓ Encontrados " . count($imageEvents) . " evento(s) de imagem:\n";
    foreach ($imageEvents as $e) {
        echo "   - {$e['created_at']} | {$e['event_type']} | from={$e['from_field']} | to={$e['to_field']}\n";
    }
    echo "\n";
}

// 4. Verifica se conversa existe mas está fora do LIMIT 100 da lista
if (!empty($conversations)) {
    $conv = $conversations[0];
    echo "4. POSIÇÃO NA LISTA (getConversationsList usa LIMIT 100, ordenado por last_message_at DESC):\n";
    $stmt = $db->prepare("
        SELECT COUNT(*) as pos
        FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        AND (c.status IS NULL OR c.status NOT IN ('closed', 'archived', 'ignored'))
        AND COALESCE(c.last_message_at, c.created_at) >= COALESCE(?, '1970-01-01')
    ");
    $stmt->execute([$conv['last_message_at']]);
    $pos = $stmt->fetch()['pos'] ?? 0;
    echo "   Conversa id={$conv['id']} está entre as " . ($pos) . " mais recentes.\n";
    if ($pos > 100) {
        echo "   ⚠️  FORA DO LIMIT 100! A conversa existe mas não aparece na lista do Inbox.\n";
    } else {
        echo "   ✓ Dentro do LIMIT 100. Deveria aparecer na lista.\n";
    }
    echo "\n";
}

// 5. Resumo
echo str_repeat("=", 70) . "\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo str_repeat("=", 70) . "\n\n";

if (empty($conversations) && empty($events)) {
    echo "CAUSA PROVÁVEL: A mensagem NUNCA chegou ao Pixel Hub via webhook.\n\n";
    echo "Cenários possíveis:\n";
    echo "1. Mensagem enviada PELO NEGÓCIO via WhatsApp Web (não pelo Pixel Hub):\n";
    echo "   - O gateway WPP Connect pode não enviar webhook para mensagens outbound enviadas por outros clientes (WhatsApp Web).\n";
    echo "   - O webhook é disparado quando o gateway RECEBE eventos do WhatsApp; mensagens enviadas pelo Web podem não gerar evento no gateway.\n\n";
    echo "2. Mensagem enviada PELO CLIENTE (96 8801 9244) para o negócio:\n";
    echo "   - O webhook DEVERIA ter sido disparado. Se não há evento, verificar:\n";
    echo "   - Webhook configurado no gateway? URL correta?\n";
    echo "   - Gateway conectado e recebendo mensagens?\n";
    echo "   - Logs do servidor: grep 'HUB_WEBHOOK_IN' ou '9688019244' nos logs.\n\n";
    echo "3. Observação do print do WhatsApp Web:\n";
    echo "   - A mensagem mostra 'Não foi possível carregar a mensagem' - a IMAGEM falhou ao carregar no WhatsApp Web.\n";
    echo "   - Isso pode indicar problema no próprio WhatsApp (mídia expirada, servidor) e não necessariamente no Pixel Hub.\n\n";
} elseif (!empty($conversations) && empty($events)) {
    echo "Conversa existe mas não há eventos recentes. A conversa pode ter sido criada por mensagem anterior.\n";
    echo "Verificar se a mensagem com imagem foi enviada/recebida após a criação da conversa.\n\n";
} elseif (!empty($events)) {
    echo "Eventos encontrados. Verificar:\n";
    echo "- Se conversation_id está preenchido nos eventos (vinculação com conversa)\n";
    echo "- Se a conversa está com status que a exclui da lista (archived, ignored)\n";
    echo "- Se o tenant_id/channel_id está correto para o canal esperado\n\n";
}

echo "Script de acesso ao banco: php database/diagnostico-9688019244.php\n";
