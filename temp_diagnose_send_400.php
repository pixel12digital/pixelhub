<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== DIAGNÓSTICO COMPLETO DE ERRO 400 NO ENVIO ===\n\n";

$db = DB::getConnection();

// Simula o payload que está sendo enviado
echo "1. SIMULANDO PAYLOAD DO MODAL 'NOVA MENSAGEM':\n";
echo str_repeat('-', 80) . "\n";

$simulatedPayload = [
    'channel' => 'whatsapp',
    'to' => '(47) 99929-1994',  // Telefone da oportunidade
    'message' => 'Teste de mensagem',
    'tenant_id' => null,  // POSSÍVEL PROBLEMA: tenant_id pode estar vazio
    'channel_id' => 'pixel12digital',
    'thread_id' => null  // Nova conversa
];

echo "Payload simulado:\n";
print_r($simulatedPayload);

// Verifica validações que podem falhar
echo "\n2. VERIFICANDO VALIDAÇÕES:\n";
echo str_repeat('-', 80) . "\n";

// Validação 1: Canal vazio
if (empty($simulatedPayload['channel'])) {
    echo "❌ FALHA: Canal vazio\n";
} else {
    echo "✅ OK: Canal = {$simulatedPayload['channel']}\n";
}

// Validação 2: Telefone vazio
if (empty($simulatedPayload['to'])) {
    echo "❌ FALHA: Telefone vazio\n";
} else {
    echo "✅ OK: Telefone = {$simulatedPayload['to']}\n";
}

// Validação 3: Mensagem vazia
if (empty($simulatedPayload['message'])) {
    echo "❌ FALHA: Mensagem vazia\n";
} else {
    echo "✅ OK: Mensagem = {$simulatedPayload['message']}\n";
}

// Validação 4: tenant_id vazio (pode ser problema)
if (empty($simulatedPayload['tenant_id']) && empty($simulatedPayload['thread_id'])) {
    echo "⚠️ ATENÇÃO: tenant_id E thread_id estão vazios!\n";
    echo "   Isso pode causar problemas na resolução do canal WhatsApp\n";
} else {
    echo "✅ OK: tenant_id ou thread_id presente\n";
}

// Validação 5: Normalização de telefone
echo "\n3. TESTANDO NORMALIZAÇÃO DE TELEFONE:\n";
echo str_repeat('-', 80) . "\n";

$phone = $simulatedPayload['to'];
$phoneNormalized = preg_replace('/[^0-9]/', '', $phone);

echo "Telefone original: {$phone}\n";
echo "Telefone normalizado: {$phoneNormalized}\n";

if (empty($phoneNormalized)) {
    echo "❌ FALHA: Telefone normalizado está vazio\n";
} else {
    echo "✅ OK: Telefone normalizado com sucesso\n";
}

// Validação 6: Verificar se há canal WhatsApp configurado
echo "\n4. VERIFICANDO CANAIS WHATSAPP CONFIGURADOS:\n";
echo str_repeat('-', 80) . "\n";

$channels = $db->query("
    SELECT id, tenant_id, channel_id, provider, is_enabled 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway' 
    AND is_enabled = 1
")->fetchAll(PDO::FETCH_ASSOC);

if (count($channels) === 0) {
    echo "❌ ERRO CRÍTICO: Nenhum canal WhatsApp habilitado no sistema!\n";
    echo "   Isso causaria erro 400: 'Nenhum canal WhatsApp configurado no sistema'\n";
} else {
    echo "✅ OK: " . count($channels) . " canal(is) WhatsApp habilitado(s):\n";
    foreach ($channels as $ch) {
        echo "   - ID: {$ch['id']}, tenant_id: {$ch['tenant_id']}, channel_id: {$ch['channel_id']}\n";
    }
}

// Validação 7: Verificar se channel_id existe
echo "\n5. VERIFICANDO SE CHANNEL_ID 'pixel12digital' EXISTE:\n";
echo str_repeat('-', 80) . "\n";

$channelId = $simulatedPayload['channel_id'];
$channelExists = $db->query("
    SELECT id, tenant_id, is_enabled 
    FROM tenant_message_channels 
    WHERE channel_id = '{$channelId}' 
    AND provider = 'wpp_gateway'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$channelExists) {
    echo "❌ ERRO: channel_id '{$channelId}' não encontrado!\n";
    echo "   Isso causaria erro 400: 'Nenhum canal WhatsApp identificado para envio'\n";
} else {
    echo "✅ OK: channel_id '{$channelId}' encontrado\n";
    echo "   tenant_id: {$channelExists['tenant_id']}\n";
    echo "   is_enabled: {$channelExists['is_enabled']}\n";
}

// Verificar oportunidade ID 29
echo "\n6. VERIFICANDO OPORTUNIDADE ID 29:\n";
echo str_repeat('-', 80) . "\n";

$opp = $db->query("
    SELECT o.id, o.name, o.tenant_id, t.name as tenant_name, t.phone 
    FROM opportunities o
    LEFT JOIN tenants t ON o.tenant_id = t.id
    WHERE o.id = 29
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($opp) {
    echo "✅ Oportunidade encontrada:\n";
    echo "   ID: {$opp['id']}\n";
    echo "   Nome: {$opp['name']}\n";
    echo "   tenant_id: {$opp['tenant_id']}\n";
    echo "   Cliente: {$opp['tenant_name']}\n";
    echo "   Telefone: {$opp['phone']}\n";
} else {
    echo "❌ Oportunidade ID 29 não encontrada\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "DIAGNÓSTICO CONCLUÍDO\n";
echo str_repeat('=', 80) . "\n";
