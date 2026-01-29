<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();
$stmt = $db->query("SELECT payload FROM communication_events WHERE id = 82324");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$payload = json_decode($row['payload'], true);

// Remove dados base64 para visualização limpa
function cleanPayload($data) {
    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 100 && preg_match('/^[A-Za-z0-9+\/=]+$/', substr($value, 0, 100))) {
                $result[$key] = '[BASE64_DATA_REMOVED - ' . strlen($value) . ' chars]';
            } else {
                $result[$key] = cleanPayload($value);
            }
        }
        return $result;
    }
    return $data;
}

$cleanPayload = cleanPayload($payload);
echo "=== PAYLOAD 82324 (LIMPO) ===\n";
echo json_encode($cleanPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=== CAMPOS COM 'Corrigido' ===\n";
function findCorrigido($data, $path = '') {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            findCorrigido($value, $path . '.' . $key);
        }
    } elseif (is_string($data) && stripos($data, 'Corrigido') !== false) {
        echo "Encontrado em: {$path} = {$data}\n";
    }
}
findCorrigido($payload);
