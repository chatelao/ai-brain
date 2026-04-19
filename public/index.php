<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

echo "<h1>Agent Control PHP Application</h1>";

try {
    $db = new Database();
    // We don't call getConnection() here because we don't have a real DB setup yet in this environment
    // but we can check if the object is instantiated.
    echo "<p>Database connection class initialized.</p>";

    $db_name = getenv('DB_NAME') ?: 'not configured';
    echo "<p>Database Name: " . htmlspecialchars($db_name) . "</p>";

} catch (\Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
