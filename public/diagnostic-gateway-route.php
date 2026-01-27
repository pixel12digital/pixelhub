<?php
/**
 * Diagnóstico de rota até o gateway (WPP_GATEWAY_BASE_URL).
 * Testa URL exata do .env e, se não houver porta, variante :8443.
 * Para cada alvo: GET / e GET /api/health (ou /health).
 * Só usa host do .env; sem input livre de URL.
 * Acesso: GET https://hub.pixel12digital.com.br/diagnostic-gateway-route.php
 */
header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) require $file;
    });
}

try {
    \PixelHub\Core\Env::load(__DIR__ . '/../.env');
} catch (\Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'env_load_failed', 'message' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
$baseUrl = rtrim((string) \PixelHub\Core\Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br'), '/');

$out = [
    'base_url' => $baseUrl,
    'host' => null,
    'dns_ips' => [],
    'tests' => [],
];

$parsed = parse_url($baseUrl);
if (!$parsed || empty($parsed['host'])) {
    echo json_encode(array_merge($out, ['error' => 'WPP_GATEWAY_BASE_URL inválido']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
$host = $parsed['host'];
$out['host'] = $host;

$dnsA = @dns_get_record($host, DNS_A);
$dnsAAAA = @dns_get_record($host, DNS_AAAA);
$ips = [];
if (is_array($dnsA)) {
    foreach ($dnsA as $r) {
        if (!empty($r['ip'])) $ips[] = $r['ip'];
    }
}
if (is_array($dnsAAAA)) {
    foreach ($dnsAAAA as $r) {
        if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
    }
}
$out['dns_ips'] = array_values(array_unique($ips));

$keyHeaders = ['server', 'via', 'cf-ray', 'x-cache', 'x-served-by', 'x-amz-cf-id'];

function runProbe(string $url, array $keyHeaders): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'PixelHub-Diagnostic-Gateway-Route/1',
    ]);
    $raw = curl_exec($ch);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
    $bodyStr = $headerSize > 0 ? substr($raw, $headerSize) : $raw;
    $res = [
        'effective_url' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: null,
        'primary_ip' => curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: null,
        'http_code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'content_type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null,
        'resp_headers_preview' => [],
        'timings' => [
            'namelookup' => curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
            'connect' => curl_getinfo($ch, CURLINFO_CONNECT_TIME),
            'appconnect' => curl_getinfo($ch, CURLINFO_APPCONNECT_TIME),
            'starttransfer' => curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME),
            'total' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        ],
    ];
    foreach (explode("\n", str_replace("\r\n", "\n", $headerStr)) as $line) {
        if (strpos($line, ':') !== false) {
            [$name, $val] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            if (in_array($name, $keyHeaders, true)) {
                $res['resp_headers_preview'][$name] = trim($val);
            }
        }
    }
    curl_close($ch);
    return $res;
}

$targets = [['label' => 'env_exact', 'url' => $baseUrl]];
$hasPort = isset($parsed['port']);
if (!$hasPort) {
    $scheme = $parsed['scheme'] ?? 'https';
    $targets[] = ['label' => 'env_8443', 'url' => $scheme . '://' . $host . ':8443'];
}

foreach ($targets as $t) {
    $label = $t['label'];
    $rootUrl = rtrim($t['url'], '/') . '/';
    $healthUrl = rtrim($t['url'], '/') . '/api/health';
    $out['tests'][$label] = [
        'target' => $t['url'],
        'get_root' => runProbe($rootUrl, $keyHeaders),
        'get_api_health' => runProbe($healthUrl, $keyHeaders),
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
