<?php
/**
 * Script para simular um POST de webhook da Meta
 */

echo "=== TESTE DE WEBHOOK META (SIMULAÇÃO) ===\n\n";

// Simula payload de mensagem da Meta
$payload = [
    'object' => 'whatsapp_business_account',
    'entry' => [
        [
            'id' => '254678720572925',
            'changes' => [
                [
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15551747592',
                            'phone_number_id' => '996185130245903'
                        ],
                        'contacts' => [
                            [
                                'profile' => ['name' => 'Teste Usuario'],
                                'wa_id' => '5547996164699'
                            ]
                        ],
                        'messages' => [
                            [
                                'from' => '5547996164699',
                                'id' => 'wamid.test_' . time(),
                                'timestamp' => time(),
                                'text' => ['body' => 'Mensagem de teste do PixelHub'],
                                'type' => 'text'
                            ]
                        ]
                    ],
                    'field' => 'messages'
                ]
            ]
        ]
    ]
];

echo "1. Enviando webhook simulado para o endpoint...\n";
echo "   URL: https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook\n\n";

$url = 'https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Meta-Webhook-Test'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "2. Resultado:\n";
echo "   HTTP Status: {$httpCode}\n";

if ($error) {
    echo "   ❌ Erro cURL: {$error}\n";
} else {
    echo "   Resposta: {$response}\n\n";
    
    if ($httpCode === 200) {
        echo "✅ Endpoint está acessível e respondeu 200!\n";
        echo "   Agora verifique se a mensagem foi processada:\n";
        echo "   php check_meta_messages.php\n";
    } else {
        echo "⚠️  Endpoint respondeu mas com erro HTTP {$httpCode}\n";
    }
}

echo "\n=== FIM ===\n";
