<?php
/**
 * Verifica o payload completo do evento
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

$stmt = $db->prepare("
    SELECT ce.payload, ce.metadata
    FROM communication_events ce
    WHERE ce.event_id = '9d9c1322-0da8-4259-a689-e7371071c934'
    LIMIT 1
");

$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

$payload = json_decode($event['payload'], true);

echo "=== PAYLOAD COMPLETO ===\n\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== BUSCANDO NÚMEROS DE TELEFONE ===\n\n";

// Função recursiva para buscar números
function findPhoneNumbers($data, $path = '') {
    $results = [];
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;
            
            if (is_string($value)) {
                // Verifica se parece um número de telefone
                if (preg_match('/\d{10,}/', $value)) {
                    $digits = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($digits) >= 10) {
                        $results[] = [
                            'path' => $currentPath,
                            'value' => $value,
                            'digits' => $digits,
                            'length' => strlen($digits)
                        ];
                    }
                }
            } elseif (is_array($value) || is_object($value)) {
                    $results = array_merge($results, findPhoneNumbers($value, $currentPath));
            }
        }
    }
    
    return $results;
}

$phoneNumbers = findPhoneNumbers($payload);

echo "📞 NÚMEROS ENCONTRADOS:\n";
foreach ($phoneNumbers as $phone) {
    echo "   {$phone['path']}: {$phone['value']} (digits: {$phone['digits']}, len: {$phone['length']})\n";
    
    // Verifica se começa com 55 e tem 12-13 dígitos
    if (substr($phone['digits'], 0, 2) === '55' && strlen($phone['digits']) >= 12 && strlen($phone['digits']) <= 13) {
        echo "      ✅ Formato válido do Brasil!\n";
    }
}

