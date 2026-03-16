<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
Env::load();

$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);
$row = $pdo->query("SELECT whapi_api_token FROM whatsapp_provider_configs WHERE provider_type='whapi' AND is_global=1 AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$token = CryptoHelper::decrypt(substr($row['whapi_api_token'], 10));

// Cria um PDF mínimo para teste (conteúdo mínimo válido)
$minPdf = "%PDF-1.4\n1 0 obj<</Type /Catalog /Pages 2 0 R>>endobj\n2 0 obj<</Type /Pages /Kids [3 0 R] /Count 1>>endobj\n3 0 obj<</Type /Page /MediaBox [0 0 612 792]>>endobj\nxref\n0 4\n0000000000 65535 f\ntrailer<</Size 4 /Root 1 0 R>>\nstartxref\n9\n%%EOF";
$base64Pdf = base64_encode($minPdf);
$testPhone = '5548993580049';

echo "=== Teste POST /messages/document com base64 ===\n";
$payload = json_encode([
    'to' => $testPhone,
    'media' => 'data:application/pdf;base64,' . $base64Pdf,
    'filename' => 'teste.pdf'
]);

$ch = curl_init('https://gate.whapi.cloud/messages/document');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP: $httpCode\n";
if ($err) echo "cURL error: $err\n";
echo "Response: $body\n";

// Testa também com base64 puro (sem data:URI)
echo "\n=== Teste 2: base64 puro (sem data:application/pdf;base64,) ===\n";
$payload2 = json_encode([
    'to' => $testPhone,
    'media' => $base64Pdf,
    'filename' => 'teste.pdf'
]);

$ch2 = curl_init('https://gate.whapi.cloud/messages/document');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload2,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);
$body2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "HTTP: $httpCode2\n";
echo "Response: $body2\n";
