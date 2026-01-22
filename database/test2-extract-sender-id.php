<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== TESTE 2: Extrair identificador real do remetente do evento 6710 ===\n\n";

// 2.1) Mostrar um pedaço grande do payload do evento 6710
echo "2.1) PAYLOAD DO EVENTO 6710 (primeiros 2000 caracteres):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT 
  id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS `from`,
  LEFT(payload, 2000) AS payload_2000
FROM communication_events
WHERE id = 6710";

$stmt1 = $pdo->query($sql1);
$result1 = $stmt1->fetch(PDO::FETCH_ASSOC);

if ($result1) {
    echo "ID: {$result1['id']}\n";
    echo "Channel ID: {$result1['channel_id']}\n";
    echo "From (extraído): {$result1['from']}\n\n";
    echo "Payload (primeiros 2000 chars):\n";
    echo str_repeat("-", 100) . "\n";
    echo $result1['payload_2000'] . "\n";
    echo str_repeat("-", 100) . "\n";
} else {
    echo "❌ Evento 6710 não encontrado.\n";
    exit;
}

echo "\n\n";

// 2.2) Procurar explicitamente pelo telefone dentro do payload
echo "2.2) VERIFICAÇÃO DE PADRÕES NO PAYLOAD:\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT 
  id,
  payload LIKE '%554796474223%' AS tem_telefone,
  payload LIKE '%@c.us%' AS tem_cus,
  payload LIKE '%remoteJid%' AS tem_remoteJid,
  payload LIKE '%participant%' AS tem_participant,
  payload LIKE '%@lid%' AS tem_lid
FROM communication_events
WHERE id = 6710";

$stmt2 = $pdo->query($sql2);
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($result2) {
    echo "ID: {$result2['id']}\n";
    echo "Tem telefone (554796474223): " . ($result2['tem_telefone'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "Tem @c.us: " . ($result2['tem_cus'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "Tem remoteJid: " . ($result2['tem_remoteJid'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "Tem participant: " . ($result2['tem_participant'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "Tem @lid: " . ($result2['tem_lid'] ? '✅ SIM' : '❌ NÃO') . "\n";
} else {
    echo "❌ Evento 6710 não encontrado.\n";
    exit;
}

echo "\n\n";

// 2.3) Se aparecer @c.us ou remoteJid, listar os trechos onde aparece
if ($result2['tem_cus'] || $result2['tem_remoteJid'] || $result2['tem_participant']) {
    echo "2.3) TRECHOS ESPECÍFICOS DO PAYLOAD:\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql3 = "SELECT 
      SUBSTRING(payload, GREATEST(1, LOCATE('@c.us', payload) - 60), 200) AS trecho_cus,
      SUBSTRING(payload, GREATEST(1, LOCATE('remoteJid', payload) - 60), 240) AS trecho_remoteJid,
      SUBSTRING(payload, GREATEST(1, LOCATE('participant', payload) - 60), 240) AS trecho_participant,
      SUBSTRING(payload, GREATEST(1, LOCATE('554796474223', payload) - 60), 240) AS trecho_telefone
    FROM communication_events
    WHERE id = 6710";
    
    $stmt3 = $pdo->query($sql3);
    $result3 = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    if ($result3) {
        if ($result3['trecho_cus']) {
            echo "📌 Trecho com @c.us:\n";
            echo str_repeat("-", 100) . "\n";
            echo $result3['trecho_cus'] . "\n\n";
        }
        
        if ($result3['trecho_remoteJid']) {
            echo "📌 Trecho com remoteJid:\n";
            echo str_repeat("-", 100) . "\n";
            echo $result3['trecho_remoteJid'] . "\n\n";
        }
        
        if ($result3['trecho_participant']) {
            echo "📌 Trecho com participant:\n";
            echo str_repeat("-", 100) . "\n";
            echo $result3['trecho_participant'] . "\n\n";
        }
        
        if ($result3['trecho_telefone']) {
            echo "📌 Trecho com telefone (554796474223):\n";
            echo str_repeat("-", 100) . "\n";
            echo $result3['trecho_telefone'] . "\n\n";
        }
    }
}

echo "\n";

// Análise e conclusão
echo "=== CONCLUSÃO ===\n";
echo str_repeat("=", 100) . "\n";

if ($result2['tem_cus'] || ($result2['tem_telefone'] && strpos($result1['payload_2000'], '@c.us') !== false)) {
    echo "✅ O payload CONTÉM o telefone em formato @c.us ou E.164.\n";
    echo "   Problema: O Hub está salvando 'from' como @lid ao invés de usar o número real.\n";
    echo "   Correção: Normalizar from/contact_external_id priorizando @c.us / E.164,\n";
    echo "             e só usar @lid como fallback.\n";
} elseif ($result2['tem_telefone']) {
    echo "✅ O payload CONTÉM o telefone (554796474223), mas sem @c.us.\n";
    echo "   Correção: Extrair o número do payload e normalizar para E.164.\n";
} elseif ($result2['tem_lid'] && !$result2['tem_cus'] && !$result2['tem_telefone']) {
    echo "❌ O payload SÓ TEM @lid, sem número real.\n";
    echo "   Problema: WPPConnect/Gateway está entregando sem o número real.\n";
    echo "   Correção: Resolver LID → telefone via API do WPPConnect antes de gravar conversa.\n";
} else {
    echo "⚠️  Situação ambígua. Verificar payload completo.\n";
}

echo "\n";

