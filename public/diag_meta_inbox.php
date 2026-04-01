<?php
/**
 * Diagnóstico: Meta API + Inbox para conversas outbound
 * Acesse: https://hub.pixel12digital.com.br/diag_meta_inbox.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Bootstrap inline (igual ao index.php)
if (session_status() === PHP_SESSION_NONE) session_start();
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $base = __DIR__ . '/../src/';
        $file = $base . str_replace('\\', '/', str_replace('PixelHub\\', '', $class)) . '.php';
        if (file_exists($file)) require_once $file;
    });
}
if (class_exists('PixelHub\\Core\\Env')) {
    \PixelHub\Core\Env::load(__DIR__ . '/../');
} elseif (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}

echo "===== DIAGNÓSTICO META API + INBOX =====\n\n";

// 1. Conexão com o banco
try {
    $db = \PixelHub\Core\DB::getConnection();
    echo "1) DB: OK\n\n";
} catch (\Exception $e) {
    die("1) DB ERRO: " . $e->getMessage() . "\n");
}

// 2. Últimas 5 conversas Meta no banco
echo "2) Últimas 5 conversas Meta (provider_type=meta_official):\n";
$stmt = $db->query("
    SELECT id, conversation_key, contact_external_id, contact_name,
           status, is_incoming_lead, source, last_message_at, last_message_direction,
           message_count, created_at
    FROM conversations
    WHERE provider_type = 'meta_official'
    ORDER BY created_at DESC
    LIMIT 5
");
$convs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (empty($convs)) {
    echo "   NENHUMA conversa Meta encontrada!\n";
} else {
    foreach ($convs as $c) {
        echo "   ID={$c['id']} contact={$c['contact_external_id']} name={$c['contact_name']}\n";
        echo "     status={$c['status']} incoming={$c['is_incoming_lead']} source={$c['source']}\n";
        echo "     last_msg_at={$c['last_message_at']} direction={$c['last_message_direction']} count={$c['message_count']}\n";
        echo "     created={$c['created_at']} key={$c['conversation_key']}\n";
    }
}
echo "\n";

// 3. Eventos vinculados às conversas Meta
echo "3) Eventos communication_events vinculados a conversas Meta:\n";
if (!empty($convs)) {
    $convIds = array_column($convs, 'id');
    $inList = implode(',', $convIds);
    try {
        $stmt2 = $db->query("
            SELECT event_id, event_type, source_system, conversation_id,
                   JSON_EXTRACT(payload, '$.to') as to_phone,
                   JSON_EXTRACT(payload, '$.type') as msg_type,
                   JSON_EXTRACT(payload, '$.message_id') as message_id,
                   status, created_at
            FROM communication_events
            WHERE conversation_id IN ({$inList})
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $events = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($events)) {
            echo "   NENHUM evento vinculado a conversas Meta!\n";
        } else {
            foreach ($events as $ev) {
                echo "   event_id={$ev['event_id']}\n";
                echo "     type={$ev['event_type']} source={$ev['source_system']} conv_id={$ev['conversation_id']}\n";
                echo "     to={$ev['to_phone']} msg_type={$ev['msg_type']} msg_id={$ev['message_id']}\n";
                echo "     status={$ev['status']} created={$ev['created_at']}\n";
            }
        }
    } catch (\Exception $e) {
        echo "   ERRO ao buscar eventos: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 4. Verificação do status Meta API (code_verification_status)
echo "4) Status Meta API (phone number):\n";
try {
    $cfgStmt = $db->query("
        SELECT id, meta_phone_number_id, meta_business_account_id, meta_access_token, is_active
        FROM whatsapp_provider_configs
        WHERE provider_type = 'meta_official' AND is_active = 1
        LIMIT 1
    ");
    $cfg = $cfgStmt->fetch(\PDO::FETCH_ASSOC);
    if (!$cfg) {
        echo "   NENHUMA config Meta encontrada!\n";
    } else {
        $phoneNumberId = $cfg['meta_phone_number_id'];
        $encToken = $cfg['meta_access_token'];
        
        // Descriptografa token
        $accessToken = null;
        if (strpos($encToken, 'encrypted:') === 0) {
            $encData = substr($encToken, 10);
            $key = $_ENV['APP_KEY'] ?? '';
            if ($key) {
                try {
                    if (class_exists('PixelHub\\Core\\Encryption')) {
                        $accessToken = \PixelHub\Core\Encryption::decrypt($encData, $key);
                    }
                } catch (\Exception $e) {
                    echo "   Token decrypt ERRO: " . $e->getMessage() . "\n";
                }
            }
        } else {
            $accessToken = $encToken;
        }
        
        if (!$accessToken) {
            echo "   Não foi possível descriptografar o token.\n";
        } else {
            $ch = curl_init("https://graph.facebook.com/v18.0/{$phoneNumberId}?fields=verified_name,code_verification_status,display_phone_number,quality_rating,platform_type");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($resp, true);
            echo "   HTTP {$code}\n";
            echo "   display_phone_number: " . ($data['display_phone_number'] ?? 'N/A') . "\n";
            echo "   code_verification_status: " . ($data['code_verification_status'] ?? 'N/A') . "\n";
            echo "   quality_rating: " . ($data['quality_rating'] ?? 'N/A') . "\n";
            echo "   verified_name: " . ($data['verified_name'] ?? 'N/A') . "\n";
            if (isset($data['error'])) {
                echo "   ERRO Meta: " . json_encode($data['error']) . "\n";
            }
            
            // 5. Envio de teste para número configurado
            echo "\n5) Teste de envio para 47996164699:\n";
            $normalized = '+554796164699';
            
            // Busca template aprovado
            $tplStmt = $db->query("SELECT template_name, language FROM whatsapp_message_templates WHERE status='approved' LIMIT 1");
            $tpl = $tplStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$tpl) {
                echo "   Nenhum template aprovado encontrado.\n";
            } else {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $normalized,
                    'type' => 'template',
                    'template' => [
                        'name' => $tpl['template_name'],
                        'language' => ['code' => $tpl['language']]
                    ]
                ];
                echo "   Template: {$tpl['template_name']} ({$tpl['language']})\n";
                echo "   Para: {$normalized}\n";
                $ch2 = curl_init("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages");
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
                $resp2 = curl_exec($ch2);
                $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch2);
                curl_close($ch2);
                echo "   HTTP {$code2}: " . ($curlErr ?: $resp2) . "\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "   ERRO: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Últimos 5 eventos whatsapp.outbound.message (qualquer)
echo "6) Últimos 5 eventos outbound Meta (source_system=meta_official):\n";
try {
    $outStmt = $db->query("
        SELECT event_id, conversation_id, status,
               JSON_EXTRACT(payload, '$.to') as to_phone,
               JSON_EXTRACT(payload, '$.message_id') as msg_id,
               created_at
        FROM communication_events
        WHERE source_system = 'meta_official'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $outEvents = $outStmt->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($outEvents)) {
        echo "   Nenhum evento Meta outbound encontrado.\n";
    } else {
        foreach ($outEvents as $ev) {
            echo "   event_id={$ev['event_id']} conv_id=" . ($ev['conversation_id'] ?? 'NULL') . "\n";
            echo "     to={$ev['to_phone']} msg_id={$ev['msg_id']} status={$ev['status']} created={$ev['created_at']}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ERRO: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Thread-data endpoint para a conversa Meta mais recente
if (!empty($convs)) {
    $latestConv = $convs[0];
    $threadId = 'whatsapp_' . $latestConv['id'];
    echo "7) Thread-data para {$threadId}:\n";
    
    try {
        // Simula o que getWhatsAppMessagesFromConversation faz
        $msgStmt = $db->prepare("
            SELECT event_id, event_type, source_system, conversation_id,
                   JSON_EXTRACT(payload, '$.body') as body,
                   JSON_EXTRACT(payload, '$.type') as type,
                   JSON_EXTRACT(payload, '$.from_me') as from_me,
                   created_at
            FROM communication_events
            WHERE conversation_id = ?
            ORDER BY created_at ASC
            LIMIT 10
        ");
        $msgStmt->execute([$latestConv['id']]);
        $msgs = $msgStmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($msgs)) {
            echo "   NENHUMA mensagem encontrada para conversation_id={$latestConv['id']}!\n";
            
            // Tenta FASE 2: busca por número de telefone
            echo "   Tentando FASE 2 - busca por to/from no payload:\n";
            $phone = $latestConv['contact_external_id'];
            $phase2Stmt = $db->prepare("
                SELECT event_id, conversation_id, source_system,
                       JSON_EXTRACT(payload, '$.to') as to_phone,
                       JSON_EXTRACT(payload, '$.from') as from_phone,
                       created_at
                FROM communication_events
                WHERE (
                    JSON_EXTRACT(payload, '$.to') = ?
                    OR JSON_EXTRACT(payload, '$.to') = ?
                )
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $phoneNoPlus = ltrim($phone, '+');
            $phase2Stmt->execute([$phone, $phoneNoPlus]);
            $phase2 = $phase2Stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($phase2)) {
                echo "   NENHUM evento por telefone ({$phone} ou {$phoneNoPlus})\n";
            } else {
                foreach ($phase2 as $p2) {
                    echo "   event_id={$p2['event_id']} conv_id=" . ($p2['conversation_id'] ?? 'NULL') . "\n";
                    echo "     to={$p2['to_phone']} from={$p2['from_phone']} created={$p2['created_at']}\n";
                }
            }
        } else {
            echo "   Mensagens encontradas: " . count($msgs) . "\n";
            foreach ($msgs as $m) {
                echo "   - body={$m['body']} type={$m['type']} from_me={$m['from_me']} created={$m['created_at']}\n";
            }
        }
    } catch (\Exception $e) {
        echo "   ERRO: " . $e->getMessage() . "\n";
    }
}

echo "\n===== FIM DO DIAGNÓSTICO =====\n";
