<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->query("SELECT task_id, github_data FROM tasks WHERE github_state IS NULL OR github_state = 'open'");
$tasks = $stmt->fetchAll();

echo "Starting backfill for " . count($tasks) . " tasks...\n";

$updateStmt = $pdo->prepare("UPDATE tasks SET github_state = ? WHERE task_id = ?");

foreach ($tasks as $task) {
    $data = json_decode($task['github_data'] ?? '{}', true);
    $state = $data['state'] ?? 'open';
    $updateStmt->execute([$state, $task['task_id']]);
    echo "Task #{$task['task_id']}: github_state set to $state\n";
}

echo "Backfill complete.\n";
