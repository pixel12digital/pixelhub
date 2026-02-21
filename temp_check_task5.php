<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim(trim($value), '"\'');
        putenv(trim($key).'='.trim(trim($value), '"\''));
    }
}
$db = \PixelHub\Core\DB::getConnection();

// Status da tarefa 5
$stmt = $db->query("SELECT id, title, status, deleted_at, project_id FROM tasks WHERE id = 5");
$t = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Tarefa 5:\n";
print_r($t);

// O que retorna o endpoint tasks-by-project para o projeto dela
$projectId = $t['project_id'];
echo "\nProjeto ID: $projectId\n";

$stmt2 = $db->prepare("SELECT id, title, status FROM tasks WHERE project_id = ? AND deleted_at IS NULL ORDER BY status ASC, title ASC");
$stmt2->execute([$projectId]);
$tasks = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\nTodas as tarefas do projeto (não deletadas):\n";
foreach ($tasks as $task) {
    echo "  id={$task['id']} status={$task['status']} title={$task['title']}\n";
}
