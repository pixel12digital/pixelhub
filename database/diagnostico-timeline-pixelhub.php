<?php
/**
 * Diagnóstico: dados do Pixel Hub (projeto 3) para a Visão Macro
 * Uso: php database/diagnostico-timeline-pixelhub.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}
PixelHub\Core\Env::load();

$db = PixelHub\Core\DB::getConnection();

$projectId = 3; // Pixel Hub

echo "=== Diagnóstico Timeline - Projeto Pixel Hub (id=$projectId) ===\n\n";

// 1. Dados brutos do projeto
$stmt = $db->prepare("SELECT id, name, created_at, due_date, status FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    echo "Projeto $projectId não encontrado.\n";
    exit(1);
}

echo "1) PROJETO (tabela projects):\n";
echo "   id: {$p['id']}\n";
echo "   name: {$p['name']}\n";
echo "   created_at (RAW): " . var_export($p['created_at'], true) . "\n";
echo "   due_date (RAW): " . var_export($p['due_date'], true) . "\n";
echo "   status: {$p['status']}\n\n";

// 2. Com DATE_FORMAT como na query real
$stmt2 = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as created_at_fmt FROM projects WHERE id = ?");
$stmt2->execute([$projectId]);
$row = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "2) created_at com DATE_FORMAT('%Y-%m-%d'): " . var_export($row['created_at_fmt'] ?? 'NULL', true) . "\n\n";

// 3. Tarefas abertas com due_date (next_task + future_tasks)
$stmt3 = $db->prepare("
    SELECT id, title, due_date, status
    FROM tasks
    WHERE project_id = ? AND deleted_at IS NULL
    AND status NOT IN ('concluida', 'completed')
    AND due_date IS NOT NULL
    ORDER BY due_date ASC
    LIMIT 5
");
$stmt3->execute([$projectId]);
$tasks = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "3) Tarefas abertas com prazo (primeiras 5):\n";
foreach ($tasks as $t) {
    echo "   id={$t['id']} due_date(RAW)=" . var_export($t['due_date'], true) . " | {$t['title']}\n";
}

echo "\n4) parseDateToTs (simulação):\n";
function parseDateToTs(?string $dateStr): ?int {
    if (!$dateStr || trim($dateStr) === '') return null;
    $s = trim($dateStr);
    $datePart = substr($s, 0, 10);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datePart, $m)) {
        return mktime(12, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
    }
    return null;
}
$createdFmt = $row['created_at_fmt'] ?? null;
$tsCreated = parseDateToTs($createdFmt);
echo "   created_at_fmt='$createdFmt' => ts=" . ($tsCreated ? date('Y-m-d', $tsCreated) : 'NULL') . "\n";
if (!empty($tasks)) {
    $firstDue = $tasks[0]['due_date'];
    $tsDue = parseDateToTs($firstDue);
    echo "   first_task due_date='$firstDue' => ts=" . ($tsDue ? date('Y-m-d', $tsDue) : 'NULL') . "\n";
}

echo "\n=== Fim diagnóstico ===\n";
