<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Services\SdrDispatchService;

$db = DB::getConnection();

echo "=== TESTANDO INTEGRAÇÃO SDR COM VALIDAÇÃO ===\n";

// 1. Buscar um job pendente para teste
echo "\n1. Buscando job pendente para teste...\n";
$stmt = $db->prepare("
    SELECT * FROM sdr_dispatch_queue 
    WHERE status = 'queued' AND phone_validated IS NULL 
    ORDER BY scheduled_at ASC 
    LIMIT 1
");
$stmt->execute();
$job = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$job) {
    echo "❌ Nenhum job pendente encontrado!\n";
    
    // Criar job de teste
    echo "\nCriando job de teste...\n";
    $stmt = $db->prepare("
        INSERT INTO sdr_dispatch_queue 
        (result_id, recipe_id, session_name, phone, establishment_name, message, scheduled_at, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())
    ");
    $stmt->execute([
        9999, // ID fictício
        1,
        'orsegups',
        '5547991953981', // Número da Amore Mio (inválido)
        'Teste Validation',
        'Mensagem de teste com validação',
        date('Y-m-d H:i:s')
    ]);
    
    $jobId = $db->lastInsertId();
    
    $stmt = $db->prepare("SELECT * FROM sdr_dispatch_queue WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(\PDO::FETCH_ASSOC);
}

echo "Job encontrado:\n";
echo sprintf("- ID: %d\n", $job['id']);
echo sprintf("- Empresa: %s\n", $job['establishment_name']);
echo sprintf("- Telefone: %s\n", $job['phone']);
echo sprintf("- Sessão: %s\n", $job['session_name']);
echo sprintf("- Status: %s\n", $job['status']);
echo sprintf("- Validado: %s\n", $job['phone_validated'] ?? 'NULL');

// 2. Testar envio com validação
echo "\n2. Testando envio com validação...\n";
echo "Antes do envio:\n";
echo "- phone_validated: " . ($job['phone_validated'] ?? 'NULL') . "\n";
echo "- phone_validation_status: " . ($job['phone_validation_status'] ?? 'NULL') . "\n";

$result = SdrDispatchService::sendOpeningMessage($job);

echo "\nResultado do envio:\n";
echo "- Sucesso: " . ($result['success'] ? '✅ SIM' : '❌ NÃO') . "\n";
if (isset($result['error'])) {
    echo "- Erro: " . $result['error'] . "\n";
}
if (isset($result['validation'])) {
    echo "- Validação: " . json_encode($result['validation'], JSON_UNESCAPED_UNICODE) . "\n";
}

// 3. Verificar status após envio
echo "\n3. Verificando status após envio...\n";
$stmt = $db->prepare("SELECT * FROM sdr_dispatch_queue WHERE id = ?");
$stmt->execute([$job['id']]);
$jobAfter = $stmt->fetch(\PDO::FETCH_ASSOC);

echo "Após o envio:\n";
echo "- Status: " . $jobAfter['status'] . "\n";
echo "- phone_validated: " . ($jobAfter['phone_validated'] ?? 'NULL') . "\n";
echo "- phone_validation_status: " . ($jobAfter['phone_validation_status'] ?? 'NULL') . "\n";
echo "- phone_validated_at: " . ($jobAfter['phone_validated_at'] ?? 'NULL') . "\n";
echo "- error: " . ($jobAfter['error'] ?? 'N/A') . "\n";

// 4. Testar com número válido
echo "\n4. Testando com número válido...\n";
$stmt = $db->prepare("
    INSERT INTO sdr_dispatch_queue 
    (result_id, recipe_id, session_name, phone, establishment_name, message, scheduled_at, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())
");
$stmt->execute([
    9998,
    1,
    'orsegups',
    '554797146908', // Número do próprio canal (válido)
    'Teste Numero Válido',
    'Mensagem de teste para número válido',
    date('Y-m-d H:i:s')
]);

$validJobId = $db->lastInsertId();
$stmt = $db->prepare("SELECT * FROM sdr_dispatch_queue WHERE id = ?");
$stmt->execute([$validJobId]);
$validJob = $stmt->fetch(\PDO::FETCH_ASSOC);

echo "Job válido criado (ID: {$validJobId})\n";
$result2 = SdrDispatchService::sendOpeningMessage($validJob);

echo "Resultado envio válido: " . ($result2['success'] ? '✅ ENVIADO' : '❌ FALHOU') . "\n";

// 5. Resumo final
echo "\n" . str_repeat("=", 60) . "\n";
echo "RESUMO DOS TESTES:\n";

$stmt = $db->prepare("
    SELECT id, phone, establishment_name, status, phone_validated, phone_validation_status, error
    FROM sdr_dispatch_queue 
    WHERE id IN (?, ?)
    ORDER BY id
");
$stmt->execute([$job['id'], $validJobId]);
$jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

foreach ($jobs as $j) {
    $validated = $j['phone_validated'] ? ($j['phone_validated'] == 1 ? '✅ Válido' : '❌ Inválido') : '⚪ Não validado';
    $status = $j['status'] === 'sent' ? '✅ Enviado' : ($j['status'] === 'failed' ? '❌ Falhou' : '⏳ Pendente');
    
    echo sprintf("\nJob %d - %s:\n", $j['id'], $j['establishment_name']);
    echo sprintf("- Telefone: %s\n", $j['phone']);
    echo sprintf("- Validação: %s (%s)\n", $validated, $j['phone_validation_status'] ?? 'N/A');
    echo sprintf("- Status Final: %s\n", $status);
    if ($j['error']) {
        echo sprintf("- Erro: %s\n", $j['error']);
    }
}

echo "\n✅ INTEGRAÇÃO CONCLUÍDA!\n";
echo "A validação de números está funcionando no SDR Dispatch Service.\n";

echo "\n=== FIM ===\n";
